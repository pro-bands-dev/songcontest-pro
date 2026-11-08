<?php

if (!defined('ABSPATH')) exit;

/**
 * SongContest Pro - öffentlicher Voter-Login
 *
 * Normaler Contest:
 * - einmalige E-Mail-Verifizierung per Magic-Link
 * - danach langlebige HttpOnly-Session
 * - tägliche Stimmsperre erfolgt über voter_id in scp_votes
 *
 * WICHTIG:
 * IP / Fingerprint bleiben Analysemerkmale und sind keine harte
 * Sperre mehr für verifizierte Voter.
 */

define('SCP_VOTER_COOKIE', 'scp_voter_session');
define('SCP_VOTER_SESSION_DAYS', 90);
define('SCP_VOTER_MAGIC_MINUTES', 20);


/* =========================================================
   HELPERS
========================================================= */

function scp_voter_normalize_email($email){

    $email = sanitize_email($email);

    return strtolower(trim($email));
}


function scp_voter_email_hash($email){

    return hash(
        'sha256',
        scp_voter_normalize_email($email)
    );
}


function scp_voter_token_hash($token){

    return hash('sha256', (string)$token);
}


function scp_voter_mask_email($email){

    $email = scp_voter_normalize_email($email);

    if(!$email || strpos($email, '@') === false){
        return '';
    }

    [$local, $domain] = explode('@', $email, 2);

    $first = mb_substr($local, 0, 1);
    $masked_local = $first . str_repeat('*', max(3, mb_strlen($local) - 1));

    return $masked_local . '@' . $domain;
}


function scp_voter_random_token(){

    try{
        return bin2hex(random_bytes(32));
    }catch(Exception $e){
        return wp_generate_password(64, false, false);
    }
}


function scp_voter_current_relative_url(){

    $uri = isset($_SERVER['REQUEST_URI'])
        ? wp_unslash($_SERVER['REQUEST_URI'])
        : '/';

    if(!$uri || $uri[0] !== '/'){
        $uri = '/';
    }

    return $uri;
}


function scp_voter_sanitize_return_url($url){

    $url = is_string($url) ? trim($url) : '';

    if(!$url){
        return scp_voter_current_relative_url();
    }

    /*
     * Nur Pfad + Query auf der eigenen Site speichern.
     * Dadurch gibt es keinen Open-Redirect.
     */
    $parts = wp_parse_url($url);

    if($parts === false){
        return '/';
    }

    if(!empty($parts['host'])){

        $home = wp_parse_url(home_url('/'));

        if(
            empty($home['host'])
            ||
            strtolower($parts['host']) !== strtolower($home['host'])
        ){
            return '/';
        }
    }

    $path = !empty($parts['path'])
        ? $parts['path']
        : '/';

    if($path[0] !== '/'){
        $path = '/'.$path;
    }

    if(!empty($parts['query'])){
        $path .= '?'.$parts['query'];
    }

    return $path;
}


function scp_voter_build_redirect_url($relative_url, $args = []){

    $relative_url = scp_voter_sanitize_return_url($relative_url);

    $url = home_url($relative_url);

    if(!empty($args)){
        $url = add_query_arg($args, $url);
    }

    return $url;
}


/* =========================================================
   SESSION
========================================================= */

function scp_get_current_voter(){

    static $resolved = false;
    static $voter = null;

    if($resolved){
        return $voter;
    }

    $resolved = true;

    if(empty($_COOKIE[SCP_VOTER_COOKIE])){
        return null;
    }

    $raw_token = sanitize_text_field(
        wp_unslash($_COOKIE[SCP_VOTER_COOKIE])
    );

    if(!$raw_token || strlen($raw_token) < 32){
        return null;
    }

    global $wpdb;

    $sessions = $wpdb->prefix.'scp_voter_sessions';
    $voters   = $wpdb->prefix.'scp_voters';

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT
                v.id,
                v.email,
                v.verified_at,
                s.id AS session_id,
                s.expires_at
            FROM $sessions s
            INNER JOIN $voters v
                ON v.id = s.voter_id
            WHERE s.token_hash = %s
            AND s.expires_at >= %s
            LIMIT 1
            ",
            scp_voter_token_hash($raw_token),
            current_time('mysql')
        )
    );

    if(!$row || empty($row->verified_at)){
        return null;
    }

    $voter = $row;

    return $voter;
}


function scp_get_current_voter_id(){

    $voter = scp_get_current_voter();

    return $voter
        ? intval($voter->id)
        : 0;
}


function scp_voter_is_logged_in(){

    return scp_get_current_voter_id() > 0;
}


function scp_voter_set_session_cookie($raw_token, $expires_ts){

    $secure = is_ssl();

    /*
     * PHP 7.3+ Cookie-Optionen.
     * Path "/" ist absichtlich siteweit, da Voting-Liste,
     * Bandprofile und Magic-Link unterschiedliche URLs haben.
     */
    setcookie(
        SCP_VOTER_COOKIE,
        $raw_token,
        [
            'expires'  => $expires_ts,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    $_COOKIE[SCP_VOTER_COOKIE] = $raw_token;
}


function scp_voter_clear_session_cookie(){

    setcookie(
        SCP_VOTER_COOKIE,
        '',
        [
            'expires'  => time() - HOUR_IN_SECONDS,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    unset($_COOKIE[SCP_VOTER_COOKIE]);
}


function scp_voter_create_session($voter_id){

    global $wpdb;

    $sessions = $wpdb->prefix.'scp_voter_sessions';

    $raw_token = scp_voter_random_token();

    $expires_ts = time() + (SCP_VOTER_SESSION_DAYS * DAY_IN_SECONDS);
    $expires_at = wp_date(
        'Y-m-d H:i:s',
        $expires_ts,
        wp_timezone()
    );

    $ok = $wpdb->insert(
        $sessions,
        [
            'voter_id'    => intval($voter_id),
            'token_hash'  => scp_voter_token_hash($raw_token),
            'expires_at'  => $expires_at,
            'created_at'  => current_time('mysql'),
            'last_used_at'=> current_time('mysql')
        ]
    );

    if($ok === false){
        return new WP_Error(
            'session_db_error',
            'Voting-Sitzung konnte nicht erstellt werden.'
        );
    }

    /*
     * Alte / abgelaufene Sessions dieses Voters aufräumen.
     * Maximal die letzten 5 gültigen Geräte-Sessions behalten.
     */
    $wpdb->query(
        $wpdb->prepare(
            "
            DELETE FROM $sessions
            WHERE voter_id=%d
            AND expires_at < %s
            ",
            intval($voter_id),
            current_time('mysql')
        )
    );

    $old_ids = $wpdb->get_col(
        $wpdb->prepare(
            "
            SELECT id
            FROM $sessions
            WHERE voter_id=%d
            ORDER BY id DESC
            LIMIT 18446744073709551615 OFFSET 5
            ",
            intval($voter_id)
        )
    );

    if(!empty($old_ids)){

        $old_ids = array_map('intval', $old_ids);

        $wpdb->query(
            "DELETE FROM $sessions WHERE id IN (".implode(',', $old_ids).")"
        );
    }

    scp_voter_set_session_cookie(
        $raw_token,
        $expires_ts
    );

    return true;
}


/* =========================================================
   VOTER UI
========================================================= */

function scp_voter_status_html(){

    if(scp_voter_is_logged_in()){

        $voter = scp_get_current_voter();

        return "
            <div class='scp-voter-status scp-voter-status-ok'>
                <div>
                    <strong>✓ Voting freigeschaltet</strong>
                    <span>
                        Angemeldet als ".esc_html(scp_voter_mask_email($voter->email))."
                    </span>
                </div>
                <a
                    class='scp-voter-logout'
                    href='".esc_url(
                        wp_nonce_url(
                            admin_url('admin-post.php?action=scp_voter_logout'),
                            'scp_voter_logout'
                        )
                    )."'
                >
                    Abmelden
                </a>
            </div>
        ";
    }

    return "
        <div class='scp-voter-status scp-voter-status-info'>
            <strong>Zum Abstimmen einmalig E-Mail bestätigen</strong>
            <span>
                Danach bleibst du auf diesem Gerät angemeldet und kannst täglich abstimmen.
            </span>
        </div>
    ";
}


function scp_render_voter_modal(){

    if(is_admin() || scp_voter_is_logged_in()){
        return;
    }

    ?>
    <div class="scp-voter-modal" id="scp-voter-modal" aria-hidden="true">
        <div class="scp-voter-modal-backdrop"></div>

        <div class="scp-voter-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="scp-voter-modal-title">

            <button type="button" class="scp-voter-modal-close" aria-label="Schließen">×</button>

            <h3 id="scp-voter-modal-title">
                Einmalig fürs Voting anmelden
            </h3>

            <p>
                Gib deine E-Mail-Adresse ein. Du erhältst einen Bestätigungslink.
                Danach bleibst du auf diesem Gerät angemeldet und kannst einmal täglich abstimmen.
            </p>

            <form id="scp-voter-login-form">

                <label for="scp-voter-email">
                    E-Mail-Adresse
                </label>

                <input
                    type="email"
                    id="scp-voter-email"
                    name="email"
                    autocomplete="email"
                    required
                >

                <input type="hidden" id="scp-voter-artist" value="">
                <input type="hidden" id="scp-voter-ref" value="0">

                <button type="submit" class="scp-voter-submit">
                    Bestätigungslink senden
                </button>

                <div class="scp-voter-form-message" aria-live="polite"></div>
            </form>

            <p class="scp-voter-privacy">
                Deine E-Mail-Adresse wird ausschließlich zur Verifizierung deiner Stimme
                und zur Verhinderung von Mehrfachabstimmungen verwendet.
            </p>
        </div>
    </div>
    <?php
}

add_action(
    'wp_footer',
    'scp_render_voter_modal',
    50
);


/* =========================================================
   LOGIN REQUEST
========================================================= */

add_action(
    'wp_ajax_scp_request_voter_login',
    'scp_request_voter_login'
);

add_action(
    'wp_ajax_nopriv_scp_request_voter_login',
    'scp_request_voter_login'
);


function scp_request_voter_login(){

    check_ajax_referer(
        'scp_public_vote',
        'nonce'
    );

    if(scp_voter_is_logged_in()){

        wp_send_json_success([
            'status' => 'already_logged_in'
        ]);
    }

    $email = scp_voter_normalize_email(
        $_POST['email'] ?? ''
    );

    if(!$email || !is_email($email)){

        wp_send_json_error([
            'code'    => 'invalid_email',
            'message' => 'Bitte gib eine gültige E-Mail-Adresse ein.'
        ], 400);
    }

    $artist_id = intval($_POST['artist'] ?? 0);
    $ref_id    = intval($_POST['ref'] ?? 0);
    $return_url = scp_voter_sanitize_return_url(
        $_POST['return_url'] ?? '/'
    );

    if($artist_id <= 0){

        wp_send_json_error([
            'code'    => 'invalid_artist',
            'message' => 'Die Band konnte nicht ermittelt werden.'
        ], 400);
    }

    $contest = function_exists('scp_get_public_contest')
        ? scp_get_public_contest()
        : null;

    if(!$contest){

        wp_send_json_error([
            'code'    => 'no_contest',
            'message' => 'Aktuell ist kein Voting geöffnet.'
        ], 400);
    }

    $contest_id = intval($contest->id);

    $phase = function_exists('scp_get_contest_phase')
        ? scp_get_contest_phase($contest_id)
        : 'closed';

    if(!in_array($phase, ['top20','final'], true)){

        wp_send_json_error([
            'code'    => 'voting_closed',
            'message' => 'Aktuell ist keine Stimmabgabe möglich.'
        ], 400);
    }

    global $wpdb;

    $artists = $wpdb->prefix.'scp_artists';

    $allowed_status = $phase === 'final'
        ? 'finale'
        : 'top20';

    $valid_artist = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT id
            FROM $artists
            WHERE id=%d
            AND contest_id=%d
            AND status=%s
            LIMIT 1
            ",
            $artist_id,
            $contest_id,
            $allowed_status
        )
    );

    if(!$valid_artist){

        wp_send_json_error([
            'code'    => 'artist_not_eligible',
            'message' => 'Für diese Band ist aktuell keine Stimmabgabe möglich.'
        ], 400);
    }


    /* -----------------------------------------------------
       Rate Limit
    ----------------------------------------------------- */

    $ip = isset($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field($_SERVER['REMOTE_ADDR'])
        : '';

    $email_rate_key = 'scp_voter_mail_'.substr(
        hash('sha256', $email),
        0,
        24
    );

    $ip_rate_key = 'scp_voter_ip_'.substr(
        hash('sha256', $ip),
        0,
        24
    );

    $email_rate = intval(get_transient($email_rate_key));
    $ip_rate    = intval(get_transient($ip_rate_key));

    if($email_rate >= 3 || $ip_rate >= 10){

        wp_send_json_error([
            'code'    => 'rate_limit',
            'message' => 'Es wurden zu viele Bestätigungslinks angefordert. Bitte versuche es in einigen Minuten erneut.'
        ], 429);
    }

    set_transient(
        $email_rate_key,
        $email_rate + 1,
        15 * MINUTE_IN_SECONDS
    );

    set_transient(
        $ip_rate_key,
        $ip_rate + 1,
        15 * MINUTE_IN_SECONDS
    );


    /* -----------------------------------------------------
       Voter anlegen / laden
    ----------------------------------------------------- */

    $voters = $wpdb->prefix.'scp_voters';
    $tokens = $wpdb->prefix.'scp_voter_login_tokens';

    $email_hash = scp_voter_email_hash($email);

    $voter = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $voters
            WHERE email_hash=%s
            LIMIT 1
            ",
            $email_hash
        )
    );

    if(!$voter){

        $inserted = $wpdb->insert(
            $voters,
            [
                'email'      => $email,
                'email_hash' => $email_hash,
                'created_at' => current_time('mysql')
            ]
        );

        if($inserted === false){

            wp_send_json_error([
                'code'    => 'db_error',
                'message' => 'Die Anmeldung konnte nicht vorbereitet werden.'
            ], 500);
        }

        $voter_id = intval($wpdb->insert_id);

    }else{

        $voter_id = intval($voter->id);

        /*
         * Schreibweise der Adresse ggf. aktualisieren.
         */
        if($voter->email !== $email){

            $wpdb->update(
                $voters,
                ['email' => $email],
                ['id' => $voter_id]
            );
        }
    }


    /* -----------------------------------------------------
       Einmal-Token
    ----------------------------------------------------- */

    $raw_token = scp_voter_random_token();

    $expires_at = wp_date(
        'Y-m-d H:i:s',
        time() + (SCP_VOTER_MAGIC_MINUTES * MINUTE_IN_SECONDS),
        wp_timezone()
    );

    /*
     * Noch offene alte Tokens dieses Voters ungültig machen.
     */
    $wpdb->query(
        $wpdb->prepare(
            "
            UPDATE $tokens
            SET used_at=%s
            WHERE voter_id=%d
            AND used_at IS NULL
            ",
            current_time('mysql'),
            $voter_id
        )
    );

    $inserted = $wpdb->insert(
        $tokens,
        [
            'voter_id'   => $voter_id,
            'contest_id' => $contest_id,
            'artist_id'  => $artist_id,
            'ref_id'     => $ref_id,
            'token_hash' => scp_voter_token_hash($raw_token),
            'return_url' => $return_url,
            'expires_at' => $expires_at,
            'created_at' => current_time('mysql')
        ]
    );

    if($inserted === false){

        wp_send_json_error([
            'code'    => 'token_db_error',
            'message' => 'Der Bestätigungslink konnte nicht erstellt werden.'
        ], 500);
    }

    $token_id = intval($wpdb->insert_id);

    $magic_url = add_query_arg(
        [
            'action' => 'scp_voter_magic_login',
            'token'  => $raw_token
        ],
        admin_url('admin-post.php')
    );


    /* -----------------------------------------------------
       E-Mail
    ----------------------------------------------------- */

    $subject = 'Dein Zugang zum PRO-BANDS Song Contest Voting';

    $message = "
        <div style='font-family:Arial,sans-serif;line-height:1.6;color:#222'>
            <h2>Song Contest Voting bestätigen</h2>

            <p>
                Klicke auf den folgenden Button, um deine E-Mail-Adresse
                einmalig für das Song Contest Voting zu bestätigen.
            </p>

            <p style='margin:26px 0'>
                <a
                    href='".esc_url($magic_url)."'
                    style='display:inline-block;background:#e99a2c;color:#fff;
                           text-decoration:none;padding:12px 20px;border-radius:5px;
                           font-weight:700'
                >
                    Voting freischalten
                </a>
            </p>

            <p>
                Der Link ist ".intval(SCP_VOTER_MAGIC_MINUTES)." Minuten gültig.
            </p>

            <p>
                Danach bleibst du auf diesem Gerät angemeldet und kannst
                während des normalen Votings einmal täglich abstimmen.
            </p>

            <p style='font-size:12px;color:#666'>
                Falls du diesen Link nicht angefordert hast, kannst du diese E-Mail ignorieren.
            </p>
        </div>
    ";

    $sent = wp_mail(
        $email,
        $subject,
        $message,
        [
            'Content-Type: text/html; charset=UTF-8'
        ]
    );

    if(!$sent){

        $wpdb->delete(
            $tokens,
            ['id' => $token_id]
        );

        wp_send_json_error([
            'code'    => 'mail_error',
            'message' => 'Die Bestätigungs-E-Mail konnte nicht versendet werden.'
        ], 500);
    }

    wp_send_json_success([
        'status'  => 'mail_sent',
        'message' => 'Bestätigungslink wurde gesendet. Bitte prüfe dein E-Mail-Postfach.'
    ]);
}


/* =========================================================
   MAGIC LINK
========================================================= */

add_action(
    'admin_post_scp_voter_magic_login',
    'scp_voter_magic_login'
);

add_action(
    'admin_post_nopriv_scp_voter_magic_login',
    'scp_voter_magic_login'
);


function scp_voter_magic_login(){

    $raw_token = isset($_GET['token'])
        ? sanitize_text_field(wp_unslash($_GET['token']))
        : '';

    if(!$raw_token){

        wp_safe_redirect(
            home_url('/')
        );
        exit;
    }

    global $wpdb;

    $tokens = $wpdb->prefix.'scp_voter_login_tokens';
    $voters = $wpdb->prefix.'scp_voters';

    $token = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $tokens
            WHERE token_hash=%s
            AND used_at IS NULL
            AND expires_at >= %s
            LIMIT 1
            ",
            scp_voter_token_hash($raw_token),
            current_time('mysql')
        )
    );

    if(!$token){

        wp_safe_redirect(
            add_query_arg(
                'scp_voter',
                'invalid',
                home_url('/')
            )
        );
        exit;
    }

    $voter_id = intval($token->voter_id);

    $wpdb->update(
        $tokens,
        [
            'used_at' => current_time('mysql')
        ],
        [
            'id' => intval($token->id)
        ]
    );

    $wpdb->update(
        $voters,
        [
            'verified_at' => current_time('mysql'),
            'last_login_at' => current_time('mysql')
        ],
        [
            'id' => $voter_id
        ]
    );

    $session = scp_voter_create_session(
        $voter_id
    );

    if(is_wp_error($session)){

        wp_safe_redirect(
            add_query_arg(
                'scp_voter',
                'session_error',
                home_url('/')
            )
        );
        exit;
    }

    $redirect = scp_voter_build_redirect_url(
        $token->return_url,
        [
            'scp_voter'    => 'verified',
            'scp_autovote' => '1',
            'band'         => intval($token->artist_id)
        ]
    );

    wp_safe_redirect($redirect);
    exit;
}


/* =========================================================
   LOGOUT
========================================================= */

add_action(
    'admin_post_scp_voter_logout',
    'scp_voter_logout'
);

add_action(
    'admin_post_nopriv_scp_voter_logout',
    'scp_voter_logout'
);


function scp_voter_logout(){

    check_admin_referer(
        'scp_voter_logout'
    );

    global $wpdb;

    if(!empty($_COOKIE[SCP_VOTER_COOKIE])){

        $raw_token = sanitize_text_field(
            wp_unslash($_COOKIE[SCP_VOTER_COOKIE])
        );

        $wpdb->delete(
            $wpdb->prefix.'scp_voter_sessions',
            [
                'token_hash' => scp_voter_token_hash($raw_token)
            ]
        );
    }

    scp_voter_clear_session_cookie();

    $redirect = wp_get_referer();

    if(!$redirect){
        $redirect = home_url('/');
    }

    wp_safe_redirect($redirect);
    exit;
}

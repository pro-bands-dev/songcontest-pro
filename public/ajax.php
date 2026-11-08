<?php

if (!defined('ABSPATH')) exit;


function scp_get_geo_data($ip){

    global $wpdb;

    $geo = array(
        'country' => '',
        'city'    => '',
        'isp'     => '',
        'is_vpn'  => 0
    );

    $cache_table = $wpdb->prefix.'scp_ip_cache';

    $cache = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT country, city, isp, is_vpn
            FROM $cache_table
            WHERE ip = %s
            LIMIT 1
            ",
            $ip
        )
    );

    if($cache){

        $geo['country'] = $cache->country;
        $geo['city']    = $cache->city;
        $geo['isp']     = $cache->isp;
        $geo['is_vpn']  = (int)$cache->is_vpn;

        return $geo;
    }

    $response = wp_remote_get(
        "https://ipwho.is/".$ip,
        array(
            'timeout' => 5
        )
    );

    if(is_wp_error($response)){
        return $geo;
    }

    if(wp_remote_retrieve_response_code($response) != 200){

        error_log("IPWho Fehler: ".wp_remote_retrieve_body($response));

        return $geo;
    }

    $body = wp_remote_retrieve_body($response);

    $json = json_decode($body);

    if(isset($json->success) && $json->success === false){

        error_log("IPWho Fehler: ".$body);

        return $geo;
    }

    if(!$json || empty($json->success)){
        return $geo;
    }

    if(isset($json->country_code)){
        $geo['country'] = sanitize_text_field($json->country_code);
    }

    if(isset($json->city)){
        $geo['city'] = sanitize_text_field($json->city);
    }

    if(isset($json->connection->isp)){
        $geo['isp'] = sanitize_text_field($json->connection->isp);
    }

    if(
        !empty($json->security->proxy)
        ||
        !empty($json->security->vpn)
        ||
        !empty($json->security->tor)
    ){
        $geo['is_vpn'] = 1;
    }

    $wpdb->replace(
        $cache_table,
        [
            'ip'         => $ip,
            'country'    => $geo['country'],
            'city'       => $geo['city'],
            'isp'        => $geo['isp'],
            'is_vpn'     => $geo['is_vpn'],
            'checked_at' => current_time('mysql')
        ]
    );

    return $geo;
}


// =========================
//   JURY VOTING
// ========================= 

add_action("wp_ajax_scp_jury_vote","scp_jury_vote");

function scp_jury_vote(){

    if(!is_user_logged_in()){
        wp_send_json_error("Nicht eingeloggt");
    }

    global $wpdb;

    $user   = get_current_user_id();
    $artist = intval($_POST['artist'] ?? 0);
    $contest_id = intval($_POST['contest_id'] ?? 0);
    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1;
    }

    $phase = function_exists('scp_get_contest_phase') ? scp_get_contest_phase($contest_id) : scp_get_current_phase();
    if($phase !== "final"){
        wp_send_json_error("Jury Voting nicht aktiv");
    }

    $artist_contest = intval($wpdb->get_var($wpdb->prepare(
        "SELECT contest_id FROM {$wpdb->prefix}scp_artists WHERE id=%d", $artist
    )));
    if($artist_contest !== $contest_id){
        wp_send_json_error("Teilnehmer gehört nicht zu diesem Contest");
    }
    $points = intval($_POST['points'] ?? 0);

    if($artist <= 0 || $points < 1 || $points > 10){
        wp_send_json_error("Ungültige Bewertung");
    }

    $table = $wpdb->prefix.'scp_jury_votes';

    $existing = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT id
            FROM $table
            WHERE contest_id=%d
            AND artist_id=%d
            AND jury_user=%d
            LIMIT 1
            ",
            $contest_id,
            $artist,
            $user
        )
    );

    if($existing){

        $result = $wpdb->update(
            $table,
            [
                'points'    => $points,
                'vote_date' => current_time('mysql')
            ],
            [
                'id' => intval($existing)
            ]
        );

    }else{

        $result = $wpdb->insert(
            $table,
            [
                'contest_id' => $contest_id,
                'artist_id'  => $artist,
                'jury_user'  => $user,
                'points'     => $points,
                'vote_date'  => current_time('mysql')
            ]
        );
    }

    if($result === false){
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success("Gespeichert");
}


// =========================
//   PUBLIC VOTING
// =========================

add_action("wp_ajax_scp_vote","scp_vote");
add_action("wp_ajax_nopriv_scp_vote","scp_vote");

function scp_vote(){

    check_ajax_referer(
        'scp_public_vote',
        'nonce'
    );

    global $wpdb;

    $contest = function_exists('scp_get_public_contest')
        ? scp_get_public_contest()
        : null;

    if(!$contest){
        wp_send_json_error([
            'code'    => 'no_contest',
            'message' => 'Aktuell ist kein Contest-Voting geöffnet.'
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

    $artist = intval($_POST['artist'] ?? 0);

    if($artist <= 0){
        wp_send_json_error([
            'code'    => 'invalid_artist',
            'message' => 'Ungültige Band.'
        ], 400);
    }

    $voter_id = function_exists('scp_get_current_voter_id')
        ? scp_get_current_voter_id()
        : 0;

    /*
     * Admins duerfen weiterhin Testvotes ohne Voter-Login abgeben.
     * Ist jedoch eine verifizierte Voter-Session vorhanden, muss auch
     * bei einem eingeloggten WordPress-Administrator die voter_id
     * gespeichert und die normale Tages-Sperre angewendet werden.
     */
    $is_admin_bypass = current_user_can('manage_options') && !$voter_id;

    if(!$voter_id && !$is_admin_bypass){
        wp_send_json_error([
            'code'    => 'login_required',
            'message' => 'Bitte bestätige zuerst deine E-Mail-Adresse.'
        ], 401);
    }

    /* Band / Contest prüfen */

    $artists_table = $wpdb->prefix.'scp_artists';

    $allowed_status = $phase === 'final'
        ? 'finale'
        : 'top20';

    $valid_artist = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT id
            FROM $artists_table
            WHERE id=%d
            AND contest_id=%d
            AND status=%s
            LIMIT 1
            ",
            $artist,
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

    /* Analysemerkmale */

    $ip = isset($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field($_SERVER['REMOTE_ADDR'])
        : '';

    $ip_prefix = '';

    if(strpos($ip,':') !== false){

        $parts = explode(':',$ip);

        $ip_prefix =
            ($parts[0] ?? '').':'.
            ($parts[1] ?? '').':'.
            ($parts[2] ?? '').':'.
            ($parts[3] ?? '');

    }else{

        $parts = explode('.',$ip);

        $ip_prefix =
            ($parts[0] ?? '').'.'.
            ($parts[1] ?? '').'.'.
            ($parts[2] ?? '').'.*';
    }

    $fingerprint = isset($_POST['fingerprint'])
        ? sanitize_text_field($_POST['fingerprint'])
        : '';

    $ref = isset($_POST['ref'])
        ? intval($_POST['ref'])
        : 0;

    $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
        ? sanitize_text_field($_SERVER['HTTP_USER_AGENT'])
        : '';

    $table = $wpdb->prefix.'scp_votes';
    $vote_day = current_time('Y-m-d');

    /*
     * Präfix nur noch protokollieren.
     * Keine harte Sperre mehr über IP oder Fingerprint.
     */
    $prefix_votes_today = 0;

    if($ip_prefix){

        $prefix_votes_today = intval(
            $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT COUNT(*)
                    FROM $table
                    WHERE contest_id=%d
                    AND ip_prefix=%s
                    AND vote_day=%s
                    ",
                    $contest_id,
                    $ip_prefix,
                    $vote_day
                )
            )
        );
    }

    if($prefix_votes_today >= 10){
        error_log(
            "[SCP PREFIX WARNING] Contest: ".$contest_id.
            " | Prefix: ".$ip_prefix.
            " | Votes heute: ".$prefix_votes_today
        );
    }

    if($prefix_votes_today >= 25){
        error_log(
            "[SCP PREFIX CRITICAL] Contest: ".$contest_id.
            " | Prefix: ".$ip_prefix.
            " | Votes heute: ".$prefix_votes_today
        );
    }

    /*
     * Ein Voter = eine Stimme pro Contest und Kalendertag.
     */
    if($voter_id && !$is_admin_bypass){

        $already_voted = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT id
                FROM $table
                WHERE contest_id=%d
                AND voter_id=%d
                AND vote_day=%s
                LIMIT 1
                ",
                $contest_id,
                $voter_id,
                $vote_day
            )
        );

        if($already_voted){
            wp_send_json_error([
                'code'    => 'already_voted',
                'message' => 'Du hast heute bereits abgestimmt.'
            ], 409);
        }
    }

    /* Geo / VPN weiterhin als Analysemerkmal erfassen */

    $geo = scp_get_geo_data($ip);

    $insert_data = [
        'contest_id'    => $contest_id,
        'voter_id'      => $voter_id ?: null,
        'artist_id'     => $artist,
        'ip'            => $ip,
        'ip_prefix'     => $ip_prefix,
        'prefix_votes'  => $prefix_votes_today,
        'fingerprint'   => $fingerprint,
        'country'       => $geo['country'],
        'city'          => $geo['city'],
        'isp'           => $geo['isp'],
        'is_vpn'        => $geo['is_vpn'],
        'user_agent'    => $user_agent,
        'ref_id'        => $ref,
        'vote_day'      => $vote_day,
        'vote_date'     => current_time('mysql')
    ];

    /*
     * Admin-Testvotes bleiben möglich und bekommen keine voter_id.
     */
    if($is_admin_bypass){
        $insert_data['voter_id'] = null;
    }

    $insert = $wpdb->insert(
        $table,
        $insert_data
    );

    if($insert === false){

        if(strpos(strtolower($wpdb->last_error),'duplicate') !== false){
            wp_send_json_error([
                'code'    => 'already_voted',
                'message' => 'Du hast heute bereits abgestimmt.'
            ], 409);
        }

        error_log("[SCP DB ERROR] ".$wpdb->last_error);

        wp_send_json_error([
            'code'    => 'db_error',
            'message' => 'Die Stimme konnte nicht gespeichert werden.'
        ], 500);
    }

    if(
        $voter_id
        &&
        defined('SCP_VOTER_COOKIE')
        &&
        !empty($_COOKIE[SCP_VOTER_COOKIE])
        &&
        function_exists('scp_voter_token_hash')
    ){
        $wpdb->update(
            $wpdb->prefix.'scp_voter_sessions',
            ['last_used_at' => current_time('mysql')],
            [
                'token_hash' => scp_voter_token_hash(
                    sanitize_text_field(
                        wp_unslash($_COOKIE[SCP_VOTER_COOKIE])
                    )
                )
            ]
        );
    }

    wp_send_json_success([
        'code'      => 'vote_saved',
        'message'   => 'Deine Stimme wurde gezählt.',
        'artist_id' => $artist,
        'vote_day'  => $vote_day
    ]);
}

/*
function scp_get_geo_data($ip){

    global $wpdb;

    $geo = array(
        'country' => '',
        'city'    => '',
        'isp'     => '',
        'is_vpn'  => 0
    );

// Bereits vorhandene Daten suchen

$cache = $wpdb->get_row(
    $wpdb->prepare(
        "
        SELECT
            country,
            city,
            isp,
            is_vpn
        FROM {$wpdb->prefix}scp_votes
        WHERE ip = %s
        ORDER BY id DESC
        LIMIT 1
        ",
        $ip
    )
);

//Cache gefunden und Geodaten vorhanden

if(
    $cache
    &&
    (
        !empty($cache->country)
        ||
        !empty($cache->city)
        ||
        !empty($cache->isp)
    )
){

    $geo['country'] = $cache->country;
    $geo['city']    = $cache->city;
    $geo['isp']     = $cache->isp;
    $geo['is_vpn']  = (int)$cache->is_vpn;

    return $geo;
}

    //API aufrufen

    $response = wp_remote_get(
        "https://ipwho.is/".$ip,
        array(
            'timeout' => 5
        )
    );

    if(is_wp_error($response)){
        return $geo;
    }

    if(wp_remote_retrieve_response_code($response) != 200){

        error_log(
            "IPWho Fehler: ".
            wp_remote_retrieve_body($response)
        );

        return $geo;
    }

    $body = wp_remote_retrieve_body($response);

    $json = json_decode($body);

	if(
    isset($json->success)
    &&
    $json->success === false
){

    error_log(
        "IPWho Fehler: ".$body
    );

    return $geo;
}
	
    if(!$json || empty($json->success)){
        return $geo;
    }

    if(isset($json->country_code)){
        $geo['country'] = sanitize_text_field($json->country_code);
    }

    if(isset($json->city)){
        $geo['city'] = sanitize_text_field($json->city);
    }

    if(isset($json->connection->isp)){
        $geo['isp'] = sanitize_text_field($json->connection->isp);
    }

    if(
        !empty($json->security->proxy)
        ||
        !empty($json->security->vpn)
        ||
        !empty($json->security->tor)
    ){
        $geo['is_vpn'] = 1;
    }

    return $geo;
}

add_action("wp_ajax_scp_jury_vote","scp_jury_vote");

function scp_jury_vote(){

    if(!is_user_logged_in()){
        wp_send_json_error("Nicht eingeloggt");
    }

    $phase = scp_get_current_phase();

    // Nur im Finale erlaubt
    if($phase !== "final"){
        wp_send_json_error("Jury Voting nicht aktiv");
    }

    global $wpdb;

    $user   = get_current_user_id();
    $artist = intval($_POST['artist']);
    $points = intval($_POST['points']);

    $table = $wpdb->prefix.'scp_jury_votes';

    // REPLACE = überschreibt bestehenden Wert
    $wpdb->replace(
        $table,
        [
            'artist_id' => $artist,
            'jury_user' => $user,
            'points'    => $points,
            'vote_date' => current_time('mysql')
        ]
    );

    wp_send_json_success("Gespeichert");
}


// =========================
//   PUBLIC VOTING
// =========================

add_action("wp_ajax_scp_vote","scp_vote");
add_action("wp_ajax_nopriv_scp_vote","scp_vote");

function scp_vote(){

    global $wpdb;

    $artist = intval($_POST['artist']);

    $ip = $_SERVER['REMOTE_ADDR'];

    //IP PREFIX

    $ip_prefix = '';

    // IPv6
    if(strpos($ip,':') !== false){

        $parts = explode(':',$ip);

        $ip_prefix =
            ($parts[0] ?? '').':'.
            ($parts[1] ?? '').':'.
            ($parts[2] ?? '').':'.
            ($parts[3] ?? '');

    }
    // IPv4
    else{

        $parts = explode('.',$ip);

        $ip_prefix =
            ($parts[0] ?? '').'.'.
            ($parts[1] ?? '').'.'.
            ($parts[2] ?? '').'.*';
    }

    //ADMIN BYPASS

    $is_admin_bypass = current_user_can('manage_options');

    if($is_admin_bypass){
        error_log("SCP: Admin erkannt");
    }else{
        error_log("SCP: Kein Admin");
    }

    $fingerprint = isset($_POST['fingerprint'])
        ? sanitize_text_field($_POST['fingerprint'])
        : '';

    if($is_admin_bypass){
        $fingerprint .= '_admin_' . time();
    }

    $ref = isset($_POST['ref'])
        ? intval($_POST['ref'])
        : 0;

    $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
        ? sanitize_text_field($_SERVER['HTTP_USER_AGENT'])
        : '';

    $table = $wpdb->prefix.'scp_votes';

    //PREFIX ANALYSE

	$today_start = current_time('Y-m-d 00:00:00');
	$today_end   = current_time('Y-m-d 23:59:59');

	$prefix_votes_today = $wpdb->get_var(
		$wpdb->prepare(
			"
			SELECT COUNT(*)
			FROM $table
			WHERE ip_prefix=%s
			AND vote_date BETWEEN %s AND %s
			",
			$ip_prefix,
			$today_start,
			$today_end
		)
	);

    //   Auffälligkeiten loggen

    if($prefix_votes_today >= 10){

        error_log(
            "[SCP PREFIX WARNING] Prefix: "
            .$ip_prefix.
            " | Votes heute: "
            .$prefix_votes_today
        );
    }

    //  Sehr auffällig

    if($prefix_votes_today >= 25){

        error_log(
            "[SCP PREFIX CRITICAL] Prefix: "
            .$ip_prefix.
            " | Votes heute: "
            .$prefix_votes_today
        );
    }

    //IP CHECK

	$today_start = current_time('Y-m-d 00:00:00');
	$today_end   = current_time('Y-m-d 23:59:59');

	$exists_ip = $wpdb->get_var(
		$wpdb->prepare(
			"
			SELECT COUNT(*)
			FROM $table
			WHERE ip=%s
			AND vote_date BETWEEN %s AND %s
			",
			$ip,
			$today_start,
			$today_end
		)
	);

	if($exists_ip && !$is_admin_bypass){

        echo "Heute bereits abgestimmt";

        wp_die();
    }


    //FINGERPRINT CHECK
  
	if(!empty($fingerprint)){

		$today_start = current_time('Y-m-d 00:00:00');
		$today_end   = current_time('Y-m-d 23:59:59');

		$exists_fp = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT COUNT(*)
				FROM $table
				WHERE fingerprint=%s
				AND vote_date BETWEEN %s AND %s
				",
				$fingerprint,
				$today_start,
				$today_end
			)
		);

		if($exists_fp && !$is_admin_bypass){

			echo "Fingerprint bereits verwendet";

			wp_die();
		}
	}
	
    //GEO / VPN CHECK

	$geo = scp_get_geo_data($ip);

	$country = $geo['country'];
	$city     = $geo['city'];
	$isp      = $geo['isp'];
	$is_vpn   = $geo['is_vpn'];


    //VOTE SPEICHERN

    $insert = $wpdb->insert(
        $table,
        [
            'artist_id'     => $artist,
            'ip'            => $ip,
            'ip_prefix'     => $ip_prefix,
            'prefix_votes'  => (int)$prefix_votes_today,
            'fingerprint'   => $fingerprint,
            'country'       => $country,
            'city'          => $city,
            'isp'           => $isp,
            'is_vpn'        => $is_vpn,
            'user_agent'    => $user_agent,
            'ref_id'        => $ref,
			'vote_day'      => current_time('Y-m-d'),
            'vote_date'     => current_time('mysql')
        ]
    );

    // RESPONSE

    if($insert === false){

//        echo "DB Fehler: ".$wpdb->last_error;

		// UNIQUE Fingerprint + vote_day
    if(
        strpos(
            strtolower($wpdb->last_error),
            'duplicate'
        ) !== false
    ){

        echo "Heute bereits abgestimmt";

    }else{

        error_log(
            "[SCP DB ERROR] ".$wpdb->last_error
        );

        echo "Vote konnte nicht gespeichert werden";
    }

    }else{

        echo "Vote gespeichert | FP: ".$fingerprint;
    }

    wp_die();
}
*/
<?php

if (!defined('ABSPATH')) exit;

/**
 * Song Contest PRO – Einwilligung für Infos & Angebote per E-Mail (Double-Opt-In)
 *
 * Ablauf:
 * 1. Bewerber setzt im Bewerbungsformular das (freiwillige, nicht vorangekreuzte) Kästchen.
 * 2. Beim Speichern der Bewerbung werden Zeitpunkt und exakter Wortlaut festgehalten
 *    und eine Bestätigungs-Mail mit persönlichem Link verschickt.
 * 3. Erst nach Klick auf den Link gilt die Einwilligung als bestätigt.
 * 4. Widerruf: über Abmelde-Link (für künftige Mails) oder manuell im Backend.
 *
 * Die Contest-Mails (Bewerbungsbestätigung, Voting, Finale, Gewinner) sind davon
 * unabhängig und laufen wie bisher.
 */

/* =========================================================
   TEXTE
========================================================= */

function scp_marketing_privacy_url(){

    $url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';

    if(empty($url)){
        $url = home_url('/impressum/#Datenschutz');
    }

    return $url;
}


/**
 * Haupttext des Kästchens (HTML erlaubt, wird im Formular ausgegeben).
 */
function scp_marketing_consent_label_html(){

    $default = 'Ja, ich möchte per E-Mail Tipps zu Musikproduktion und Bandentwicklung '
        .'sowie Angebote von PRO-BANDS erhalten. Ich kann diese Einwilligung jederzeit '
        .'widerrufen. Hinweise dazu finde ich in der '
        .'<a href="%s" target="_blank" rel="noopener noreferrer">Datenschutzerklärung</a>.';

    $html = sprintf($default, esc_url(scp_marketing_privacy_url()));

    return apply_filters('scp_marketing_consent_label_html', $html);
}


/**
 * Datenschutzhinweis zur Bewerbung (HTML, nur Link erlaubt).
 */
function scp_application_privacy_notice_html(){

    $html = '<strong>Datenschutzhinweis:</strong> Wir verarbeiten Deine Angaben '
        .'(z.&nbsp;B. Band-/Künstlername, Ansprechpartner, E-Mail, Links, Foto und Audiodatei), '
        .'um Deine Bewerbung am Song Contest durchzuführen (Art.&nbsp;6 Abs.&nbsp;1 lit.&nbsp;b DSGVO). '
        .'Deine Daten werden ab Beginn des Contests gespeichert. '
        .'Drei Jahre nach Ende des Contests löschen wir Deine Kontaktdaten (E-Mail, Ansprechpartner, Links) und Deine Bewerbungstexte. '
        .'Bandname, Songtitel, Foto, Audiodatei und Platzierung bleiben im Rückblick auf vergangene Contests sichtbar '
        .'(berechtigtes Interesse, Art.&nbsp;6 Abs.&nbsp;1 lit.&nbsp;f DSGVO); Du kannst jederzeit verlangen, dass sie entfernt werden. '
        .'Wenn Du Tipps und Angebote per E-Mail erhalten möchtest, speichern wir Deine E-Mail-Adresse bis zum Widerruf Deiner Einwilligung. '
        .'Wir nutzen Deine E-Mail-Adresse für Benachrichtigungen zum Contest '
        .'(z.&nbsp;B. Bewerbungsbestätigung, Voting, Finale, Gewinner) '
        .'sowie für Hinweise und Tipps rund um den Contest. '
        .'Weitere Informationen und Deine Rechte findest Du in der '
        .'<a href="'.esc_url(scp_marketing_privacy_url()).'" target="_blank" rel="noopener noreferrer">Datenschutzerklärung</a>.';

    return apply_filters('scp_application_privacy_notice_html', $html);
}


/**
 * Hinweis unter dem Kästchen.
 */
function scp_marketing_consent_note(){

    $note = 'Freiwillig: Deine Teilnahme am Contest hängt nicht davon ab. '
        .'Nach dem Absenden erhältst Du eine E-Mail mit einem Bestätigungslink; '
        .'erst danach ist Deine Einwilligung gültig.';

    return apply_filters('scp_marketing_consent_note', $note);
}


/**
 * Der Wortlaut, den die Person tatsächlich gesehen hat (als Klartext zum Archivieren).
 */
function scp_marketing_consent_text_snapshot(){

    $label = trim(wp_strip_all_tags(scp_marketing_consent_label_html()));
    $note  = trim(wp_strip_all_tags(scp_marketing_consent_note()));

    return $label."\n".$note;
}


/* =========================================================
   TOKEN / LINKS
========================================================= */

function scp_marketing_token_hash($raw_token){
    return hash('sha256', (string) $raw_token);
}


function scp_marketing_optin_url($artist_id, $raw_token){

    return add_query_arg(
        [
            'scp_optin' => intval($artist_id),
            'k'         => $raw_token,
        ],
        home_url('/')
    );
}


function scp_marketing_optout_signature($artist_id){
    return hash_hmac('sha256', 'scp_optout|'.intval($artist_id), wp_salt('auth'));
}


/**
 * Abmelde-Link für künftige Werbe-Mails. In jede solche Mail einbauen.
 */
function scp_marketing_unsubscribe_url($artist_id){

    return add_query_arg(
        [
            'scp_optout' => intval($artist_id),
            's'          => scp_marketing_optout_signature($artist_id),
        ],
        home_url('/')
    );
}


/* =========================================================
   STATUS
========================================================= */

/**
 * none      = kein Kästchen gesetzt
 * pending   = Kästchen gesetzt, Bestätigungslink noch nicht angeklickt
 * confirmed = Double-Opt-In abgeschlossen
 * revoked   = widerrufen
 */
function scp_marketing_status($row){

    if(empty($row) || empty($row->marketing_consent)){
        return 'none';
    }

    if(!empty($row->marketing_revoked_at)){
        return 'revoked';
    }

    if(!empty($row->marketing_confirmed_at)){
        return 'confirmed';
    }

    return 'pending';
}


function scp_marketing_status_labels(){

    return [
        'none'      => 'Keine Einwilligung',
        'pending'   => 'Bestätigung offen',
        'confirmed' => 'Bestätigt',
        'revoked'   => 'Widerrufen',
    ];
}


/* =========================================================
   EMPFÄNGER FÜR WERBE-/INFO-MAILS
========================================================= */

/**
 * Liefert ausschließlich Kontakte mit bestätigter, nicht widerrufener Einwilligung.
 * Jede E-Mail-Adresse nur einmal (jüngste Bewerbung gewinnt).
 *
 * Für künftige Aussendungen immer diese Funktion verwenden – niemals direkt
 * alle Bewerbungen anschreiben.
 *
 * @param int $contest_id 0 = alle Contests
 * @return array Objekte mit id, name, contact, email, contest_id, marketing_confirmed_at
 */
function scp_get_marketing_recipients($contest_id = 0){

    global $wpdb;

    $table = $wpdb->prefix.'scp_artists';

    $where  = "marketing_consent = 1
               AND marketing_confirmed_at IS NOT NULL
               AND marketing_revoked_at IS NULL
               AND email <> ''";
    $params = [];

    if(intval($contest_id) > 0){
        $where   .= " AND contest_id = %d";
        $params[] = intval($contest_id);
    }

    $sql = "SELECT id, name, contact, email, contest_id, marketing_confirmed_at
            FROM $table
            WHERE $where
            ORDER BY marketing_confirmed_at DESC, id DESC";

    if($params){
        $sql = $wpdb->prepare($sql, $params);
    }

    $rows = $wpdb->get_results($sql);

    $seen   = [];
    $result = [];

    foreach((array) $rows as $row){

        $key = strtolower(trim($row->email));

        if($key === '' || isset($seen[$key])){
            continue;
        }

        // Wurde die Adresse NACH dieser Bestätigung widerrufen, nicht anschreiben.
        // (Eine spätere, neu bestätigte Einwilligung hebt einen älteren Widerruf auf.)
        $revoked = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table
                 WHERE LOWER(email) = %s
                 AND marketing_revoked_at IS NOT NULL
                 AND marketing_revoked_at >= %s",
                $key,
                $row->marketing_confirmed_at
            )
        );

        if(intval($revoked) > 0){
            $seen[$key] = true;
            continue;
        }

        $seen[$key] = true;
        $result[]   = $row;
    }

    return $result;
}


/* =========================================================
   BESTÄTIGUNGS-MAIL
========================================================= */

function scp_marketing_optin_default_template(){

    return "Hallo {band},\n\n"
        ."Du hast bei Deiner Bewerbung zum Song Contest angegeben, dass Du Tipps zu Musikproduktion und Bandentwicklung sowie Angebote von PRO-BANDS per E-Mail erhalten möchtest.\n\n"
        ."Bitte bestätige das mit einem Klick auf diesen Link:\n{confirm_link}\n\n"
        ."Der Link ist 14 Tage gültig. Wenn Du das nicht warst oder nichts erhalten möchtest, ignoriere diese E-Mail einfach. Dann passiert nichts.\n\n"
        ."Deine Teilnahme am Contest ist davon nicht betroffen.\n\n"
        ."Viele Grüße\nPRO-BANDS Song Contest";
}


function scp_mail_marketing_optin($email, $band, $artist_id, $raw_token){

    $template = get_option('scp_mail_marketing_optin');

    if(empty($template)){
        $template = scp_marketing_optin_default_template();
    }

    // Reiner Text (wie die bisherigen Vorlagen) -> Zeilenumbrüche für HTML-Mail erhalten.
    $is_plain = (wp_strip_all_tags($template) === $template);

    $link      = scp_marketing_optin_url($artist_id, $raw_token);
    $link_html = '<a href="'.esc_url($link).'">'.esc_html($link).'</a>';

    if($is_plain){
        $template = nl2br(esc_html($template));
    }

    $message = str_replace(
        ['{band}', '{site}', '{confirm_link}'],
        [esc_html($band), esc_html(get_bloginfo('name')), $link_html],
        $template
    );

    scp_send_mail($email, 'Bitte bestätige Deine Anmeldung zu Tipps & Angeboten', $message);
}


/* =========================================================
   SPEICHERN BEI BEWERBUNG
========================================================= */

/**
 * Liefert die zusätzlichen DB-Felder für den INSERT einer Bewerbung
 * sowie den Roh-Token (nur im Speicher, wird nicht gespeichert).
 *
 * @return array [ $db_fields, $raw_token ]  – ohne Einwilligung: [ [], '' ]
 */
function scp_marketing_prepare_application_fields(){

    if(empty($_POST['marketing_consent'])){
        return [[], ''];
    }

    $raw_token = wp_generate_password(40, false, false);

    return [
        [
            'marketing_consent'      => 1,
            'marketing_consent_at'   => current_time('mysql'),
            'marketing_consent_text' => scp_marketing_consent_text_snapshot(),
            'marketing_optin_token'  => scp_marketing_token_hash($raw_token),
        ],
        $raw_token,
    ];
}


/* =========================================================
   ÖFFENTLICHE LINKS: BESTÄTIGEN / ABMELDEN
========================================================= */

add_action('template_redirect', 'scp_marketing_handle_public_links', 1);

function scp_marketing_handle_public_links(){

    if(isset($_GET['scp_optin'])){
        scp_marketing_handle_optin();
    }

    if(isset($_GET['scp_optout'])){
        scp_marketing_handle_optout();
    }
}


function scp_marketing_message_page($title, $message){

    wp_die(
        '<p>'.esc_html($message).'</p><p><a href="'.esc_url(home_url('/')).'">Zur Startseite</a></p>',
        esc_html($title),
        ['response' => 200]
    );
}


function scp_marketing_handle_optin(){

    global $wpdb;

    $table = $wpdb->prefix.'scp_artists';

    $artist_id = intval($_GET['scp_optin']);
    $raw_token = isset($_GET['k']) ? sanitize_text_field(wp_unslash($_GET['k'])) : '';

    if($artist_id <= 0 || $raw_token === ''){
        scp_marketing_message_page('Link ungültig', 'Dieser Bestätigungslink ist ungültig.');
    }

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $artist_id)
    );

    if(!$row || empty($row->marketing_consent) || empty($row->marketing_optin_token)){

        // Bereits bestätigt und Token verbraucht?
        if($row && !empty($row->marketing_confirmed_at) && empty($row->marketing_revoked_at)){
            scp_marketing_message_page('Schon bestätigt', 'Deine Anmeldung ist bereits bestätigt. Danke!');
        }

        scp_marketing_message_page('Link ungültig', 'Dieser Bestätigungslink ist ungültig oder bereits verwendet worden.');
    }

    if(!hash_equals($row->marketing_optin_token, scp_marketing_token_hash($raw_token))){
        scp_marketing_message_page('Link ungültig', 'Dieser Bestätigungslink ist ungültig.');
    }

    if(!empty($row->marketing_revoked_at)){
        scp_marketing_message_page('Widerrufen', 'Diese Einwilligung wurde widerrufen. Bei Interesse kannst Du Dich bei einer neuen Bewerbung erneut anmelden.');
    }

    // 14 Tage gültig ab Anfrage
    $requested = !empty($row->marketing_consent_at) ? strtotime($row->marketing_consent_at) : 0;

    if($requested && (current_time('timestamp') - $requested) > 14 * DAY_IN_SECONDS){
        scp_marketing_message_page('Link abgelaufen', 'Dieser Bestätigungslink ist abgelaufen (14 Tage). Du kannst die Anmeldung bei einer neuen Bewerbung erneut setzen.');
    }

    $wpdb->update(
        $table,
        [
            'marketing_confirmed_at' => current_time('mysql'),
            'marketing_optin_token'  => null, // Einmal-Link
        ],
        ['id' => $artist_id]
    );

    scp_marketing_message_page('Anmeldung bestätigt', 'Danke! Deine Anmeldung ist bestätigt. Du erhältst künftig Tipps und Angebote von PRO-BANDS. Abmelden kannst Du Dich jederzeit über den Link in jeder Mail.');
}


function scp_marketing_handle_optout(){

    global $wpdb;

    $table = $wpdb->prefix.'scp_artists';

    $artist_id = intval($_GET['scp_optout']);
    $sig       = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

    if($artist_id <= 0 || $sig === '' || !hash_equals(scp_marketing_optout_signature($artist_id), $sig)){
        scp_marketing_message_page('Link ungültig', 'Dieser Abmelde-Link ist ungültig.');
    }

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT email FROM $table WHERE id = %d", $artist_id)
    );

    if(!$row){
        scp_marketing_message_page('Abgemeldet', 'Du erhältst keine Tipps und Angebote mehr von uns.');
    }

    // Widerruf gilt für die E-Mail-Adresse in allen Bewerbungen.
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE $table
             SET marketing_revoked_at = %s
             WHERE LOWER(email) = %s
             AND marketing_consent = 1
             AND marketing_revoked_at IS NULL",
            current_time('mysql'),
            strtolower(trim($row->email))
        )
    );

    scp_marketing_message_page('Abgemeldet', 'Du bist abgemeldet und erhältst keine Tipps und Angebote mehr von uns. Contest-Mails zu Deiner Bewerbung sind davon nicht betroffen.');
}


/* =========================================================
   ADMIN: WIDERRUF & CSV-EXPORT
========================================================= */

/**
 * Widerruf manuell setzen (z. B. wenn jemand per Mail widerruft).
 * Gilt für die E-Mail-Adresse in allen Bewerbungen.
 */
function scp_marketing_admin_revoke($artist_id){

    global $wpdb;

    $table = $wpdb->prefix.'scp_artists';

    $email = $wpdb->get_var(
        $wpdb->prepare("SELECT email FROM $table WHERE id = %d", intval($artist_id))
    );

    if(!$email){
        return 0;
    }

    return intval($wpdb->query(
        $wpdb->prepare(
            "UPDATE $table
             SET marketing_revoked_at = %s
             WHERE LOWER(email) = %s
             AND marketing_consent = 1
             AND marketing_revoked_at IS NULL",
            current_time('mysql'),
            strtolower(trim($email))
        )
    ));
}


function scp_marketing_csv_cell($value){

    $value = (string) $value;

    // Schutz vor Formel-Injection in Excel/Calc
    if($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)){
        $value = "'".$value;
    }

    return $value;
}


function scp_marketing_export_url(){

    return wp_nonce_url(
        admin_url('admin.php?page=songcontest-applications&scp_export_consent=1'),
        'scp_export_consent'
    );
}


add_action('admin_init', 'scp_marketing_maybe_export_csv');

function scp_marketing_maybe_export_csv(){

    if(
        !isset($_GET['page'], $_GET['scp_export_consent'])
        || $_GET['page'] !== 'songcontest-applications'
    ){
        return;
    }

    if(!current_user_can('manage_options')){
        wp_die('Keine Berechtigung.', 403);
    }

    check_admin_referer('scp_export_consent');

    $recipients = scp_get_marketing_recipients(0);

    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="songcontest-einwilligungen-'.gmdate('Y-m-d').'.csv"');

    $out = fopen('php://output', 'w');

    // BOM, damit Excel Umlaute korrekt anzeigt
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Bandname', 'Ansprechpartner', 'E-Mail', 'Contest-ID', 'Einwilligung bestätigt am'], ';');

    foreach($recipients as $r){
        fputcsv($out, [
            scp_marketing_csv_cell($r->name),
            scp_marketing_csv_cell($r->contact),
            scp_marketing_csv_cell($r->email),
            intval($r->contest_id),
            $r->marketing_confirmed_at,
        ], ';');
    }

    fclose($out);
    exit;
}

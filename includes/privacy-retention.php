<?php

if (!defined('ABSPATH')) exit;

/**
 * Song Contest PRO – Datenschutz: Bereinigung alter Contests
 *
 * Drei Jahre nach Ende eines archivierten Contests werden die personenbezogenen
 * Kontaktdaten und Bewerbungstexte der Bewerbungen geleert.
 *
 * Es werden KEINE Zeilen gelöscht. Dadurch bleiben Band Grid, Ergebnisseiten,
 * Votes und Ergebnis-Snapshots unverändert. Erhalten bleiben:
 *   Bandname, Songtitel, Genre, Bandfoto, Audiodatei, Status/Platzierung.
 *
 * Geleert werden:
 *   E-Mail, Ansprechpartner, Bandbeschreibung, Songbeschreibung,
 *   Instagram-, Facebook- und Website-Link, Bestätigungs-Token/Wortlaut.
 *
 * Ausnahme: Bewerbungen mit bestätigter, nicht widerrufener Einwilligung für
 * Infos & Angebote behalten E-Mail und Ansprechpartner (Speicherung bis zum
 * Widerruf). Alle übrigen Felder werden auch dort geleert.
 *
 * Die Bereinigung läuft nie automatisch. Sie wird im Backend unter
 * Song Contest → Datenschutz-Bereinigung erst als Vorschau angezeigt und dann
 * per Button ausgelöst.
 */

function scp_retention_years(){

    $years = intval(apply_filters('scp_retention_years', 3));

    return max(1, $years);
}


/**
 * Zeitpunkt, ab dem ein Contest als beendet gilt (frühester vorhandener Wert).
 */
function scp_retention_contest_end_sql(){
    return "COALESCE(c.winner_date, c.final_end, c.archived_at, c.application_end)";
}


function scp_retention_cutoff(){

    return date(
        'Y-m-d H:i:s',
        strtotime('-'.scp_retention_years().' years', current_time('timestamp'))
    );
}


/**
 * Alle archivierten Contests mit Enddatum und Fälligkeit.
 *
 * @return array Objekte: id, name, year, ref_date, due_date, is_due, pending_rows, kept_email_rows
 */
function scp_retention_overview(){

    global $wpdb;

    $contests = $wpdb->prefix.'scp_contests';
    $artists  = $wpdb->prefix.'scp_artists';
    $end_sql  = scp_retention_contest_end_sql();
    $cutoff   = scp_retention_cutoff();

    $rows = $wpdb->get_results(
        "SELECT c.id, c.name, c.year, $end_sql AS ref_date
         FROM $contests c
         WHERE c.status IN ('archived','archieved')
         ORDER BY c.year DESC, c.id DESC"
    );

    $result = [];

    foreach((array) $rows as $row){

        $row->due_date = '';
        $row->is_due   = false;

        if(!empty($row->ref_date)){
            $row->due_date = date('Y-m-d', strtotime('+'.scp_retention_years().' years', strtotime($row->ref_date)));
            $row->is_due   = ($row->ref_date < $cutoff);
        }

        $row->pending_rows    = 0;
        $row->kept_email_rows = 0;

        if($row->is_due){

            $row->pending_rows = intval($wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $artists WHERE contest_id=%d AND contact_purged_at IS NULL",
                    intval($row->id)
                )
            ));

            $row->kept_email_rows = intval($wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $artists
                     WHERE contest_id=%d AND contact_purged_at IS NULL
                     AND marketing_consent=1
                     AND marketing_confirmed_at IS NOT NULL
                     AND marketing_revoked_at IS NULL",
                    intval($row->id)
                )
            ));
        }

        $result[] = $row;
    }

    return $result;
}


/**
 * Anzahl der Bewerbungen, die aktuell bereinigt werden könnten (für den Hinweis im Backend).
 */
function scp_retention_due_count(){

    $count = 0;

    foreach(scp_retention_overview() as $row){
        $count += intval($row->pending_rows);
    }

    return $count;
}


/**
 * Führt die Bereinigung für alle fälligen Contests aus.
 *
 * @return array [ 'contests' => int, 'rows' => int, 'kept_email' => int ]
 */
function scp_retention_run(){

    global $wpdb;

    $artists = $wpdb->prefix.'scp_artists';
    $now     = current_time('mysql');

    $total_rows = 0;
    $kept       = 0;
    $contests   = 0;

    foreach(scp_retention_overview() as $row){

        if(!$row->is_due || intval($row->pending_rows) === 0){
            continue;
        }

        $contest_id = intval($row->id);

        // A) Bestätigte Einwilligung: E-Mail und Ansprechpartner bleiben, Rest wird geleert.
        $a = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $artists
                 SET band_info=NULL, song_info=NULL,
                     instagram=NULL, facebook=NULL, website=NULL,
                     contact_purged_at=%s
                 WHERE contest_id=%d AND contact_purged_at IS NULL
                 AND marketing_consent=1
                 AND marketing_confirmed_at IS NOT NULL
                 AND marketing_revoked_at IS NULL",
                $now,
                $contest_id
            )
        );

        // B) Alle übrigen: zusätzlich E-Mail, Ansprechpartner, Token und Einwilligungs-Wortlaut leeren.
        $b = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $artists
                 SET email='', contact=NULL,
                     band_info=NULL, song_info=NULL,
                     instagram=NULL, facebook=NULL, website=NULL,
                     marketing_optin_token=NULL, marketing_consent_text=NULL,
                     contact_purged_at=%s
                 WHERE contest_id=%d AND contact_purged_at IS NULL",
                $now,
                $contest_id
            )
        );

        $a = ($a === false) ? 0 : intval($a);
        $b = ($b === false) ? 0 : intval($b);

        $total_rows += $a + $b;
        $kept       += $a;
        $contests++;

        if(function_exists('scp_log_event')){
            scp_log_event(
                'info',
                'privacy_retention',
                'Kontaktdaten alter Bewerbungen bereinigt',
                [
                    'contest_id'   => $contest_id,
                    'rows'         => $a + $b,
                    'email_kept'   => $a,
                    'retention_yr' => scp_retention_years(),
                ]
            );
        }
    }

    return [
        'contests'   => $contests,
        'rows'       => $total_rows,
        'kept_email' => $kept,
    ];
}


/* =========================================================
   ADMIN
========================================================= */

add_action('admin_menu', 'scp_retention_admin_menu', 20);

function scp_retention_admin_menu(){

    add_submenu_page(
        'songcontest-dashboard',
        'Datenschutz-Bereinigung',
        'Datenschutz-Bereinigung',
        'manage_options',
        'songcontest-privacy',
        'scp_retention_admin_page'
    );
}


function scp_retention_admin_page(){

    if(!current_user_can('manage_options')){
        wp_die('Keine Berechtigung.', 403);
    }

    $years = scp_retention_years();

    echo "<div class='wrap'>";
    echo "<h1>Datenschutz-Bereinigung</h1>";

    if(isset($_POST['scp_run_retention'])){

        check_admin_referer('scp_run_retention');

        $res = scp_retention_run();

        echo "<div class='updated'><p>".sprintf(
            'Bereinigung abgeschlossen: %1$d Bewerbungen in %2$d Contest(s) bearbeitet, bei %3$d davon bleiben E-Mail und Ansprechpartner wegen bestätigter Einwilligung erhalten.',
            intval($res['rows']),
            intval($res['contests']),
            intval($res['kept_email'])
        )."</p></div>";
    }

    echo "<p>Drei Jahre (".intval($years).") nach Ende eines archivierten Contests werden die Kontaktdaten und Bewerbungstexte der Bewerbungen geleert. ";
    echo "<strong>Es werden keine Bewerbungen gelöscht.</strong> Bandname, Songtitel, Genre, Bandfoto, Audiodatei und Platzierung bleiben erhalten, damit Band Grid und Rückblicke unverändert funktionieren.</p>";

    echo "<p><strong>Geleert werden:</strong> E-Mail, Ansprechpartner, Bandbeschreibung, Songbeschreibung, Instagram-, Facebook- und Website-Link, Bestätigungs-Token und Wortlaut der Einwilligung.<br>";
    echo "<strong>Ausnahme:</strong> Bei bestätigter, nicht widerrufener Einwilligung für Infos &amp; Angebote bleiben E-Mail und Ansprechpartner stehen (bis zum Widerruf).</p>";

    $overview = scp_retention_overview();
    $due_rows = 0;

    if(empty($overview)){
        echo "<p>Es gibt noch keine archivierten Contests.</p></div>";
        return;
    }

    echo "<table class='widefat striped' style='max-width:900px;'>";
    echo "<thead><tr>
        <th>Contest</th>
        <th>Ende des Contests</th>
        <th>Fällig ab</th>
        <th>Status</th>
        <th>Bewerbungen mit Daten</th>
        <th>davon E-Mail bleibt (Einwilligung)</th>
    </tr></thead><tbody>";

    foreach($overview as $row){

        $label = '#'.intval($row->id);

        if(!empty($row->name)){
            $label .= ' – '.$row->name;
        }

        if(!empty($row->year)){
            $label .= ' ('.intval($row->year).')';
        }

        if(empty($row->ref_date)){
            $state = 'Kein Enddatum hinterlegt, wird nicht bereinigt';
        }elseif($row->is_due && $row->pending_rows > 0){
            $state = '<strong style="color:#b32d2e;">Fällig</strong>';
        }elseif($row->is_due){
            $state = 'Bereits bereinigt';
        }else{
            $state = 'Noch nicht fällig';
        }

        $due_rows += intval($row->pending_rows);

        echo "<tr>";
        echo "<td>".esc_html($label)."</td>";
        echo "<td>".esc_html($row->ref_date ? substr($row->ref_date,0,10) : '–')."</td>";
        echo "<td>".esc_html($row->due_date ?: '–')."</td>";
        echo "<td>".wp_kses($state, ['strong' => ['style' => []]])."</td>";
        echo "<td>".($row->is_due ? intval($row->pending_rows) : '–')."</td>";
        echo "<td>".($row->is_due ? intval($row->kept_email_rows) : '–')."</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";

    if($due_rows > 0){

        echo "<form method='post' style='margin-top:20px;' onsubmit=\"return confirm('Kontaktdaten und Bewerbungstexte von ".intval($due_rows)." Bewerbungen jetzt endgültig leeren? Das kann nicht rückgängig gemacht werden. Bitte vorher ein Datenbank-Backup erstellen.');\">";
        wp_nonce_field('scp_run_retention');
        echo "<p><strong>Vor dem Ausführen bitte ein Datenbank-Backup erstellen.</strong> Das Leeren kann nicht rückgängig gemacht werden.</p>";
        echo "<button class='button button-primary' name='scp_run_retention' value='1'>".intval($due_rows)." Bewerbungen jetzt bereinigen</button>";
        echo "</form>";

    }else{
        echo "<p style='margin-top:20px;'>Aktuell ist nichts zu bereinigen.</p>";
    }

    echo "</div>";
}


/**
 * Hinweis auf allen Song-Contest-Seiten im Backend, wenn etwas fällig ist.
 */
add_action('admin_notices', 'scp_retention_admin_notice');

function scp_retention_admin_notice(){

    if(!current_user_can('manage_options')){
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

    if(strpos($page, 'songcontest') !== 0 || $page === 'songcontest-privacy'){
        return;
    }

    $count = scp_retention_due_count();

    if($count <= 0){
        return;
    }

    echo "<div class='notice notice-warning'><p>";
    echo "<strong>Datenschutz:</strong> Bei ".intval($count)." Bewerbungen alter Contests ist die Aufbewahrungsfrist abgelaufen. ";
    echo "<a href='".esc_url(admin_url('admin.php?page=songcontest-privacy'))."'>Vorschau und Bereinigung öffnen</a>";
    echo "</p></div>";
}

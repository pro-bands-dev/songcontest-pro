<?php

if (!defined('ABSPATH')) exit;

// Contest context / management layer for SongContest Pro 3.0.

function scp_get_current_contest_id(){

    $contest_id = intval(get_option('scp_current_contest_id', 1));

    if($contest_id <= 0){
        $contest_id = 1;
    }

    return $contest_id;
}


function scp_get_current_contest(){

    return scp_get_contest(scp_get_current_contest_id());
}


function scp_get_contest_public_base_url($contest_id = 0){
    // Feste Contest-Seite (Song Contest → Seiten & Links), z. B. /contest/
    if(function_exists('scp_get_public_page_url')){
        $page = scp_get_public_page_url('contest');
        if($page) return trailingslashit($page);
    }
    $contest_id = intval($contest_id);
    $contest = $contest_id > 0 ? scp_get_contest($contest_id) : scp_get_current_contest();
    $slug = ($contest && !empty($contest->slug)) ? sanitize_title($contest->slug) : 'song-contest';
    return trailingslashit(site_url('/'.$slug.'/'));
}

function scp_get_band_public_url($band_id, $contest_id = 0, $ref = 0){
    $band_page = function_exists('scp_get_public_page_url') ? scp_get_public_page_url('band') : '';
    $url = $band_page
        ? add_query_arg('band', intval($band_id), $band_page)
        : add_query_arg('band', intval($band_id), scp_get_contest_public_base_url($contest_id).'band/');
    if(intval($ref) > 0){ $url = add_query_arg('ref', intval($ref), $url); }
    return $url;
}


function scp_get_contest($contest_id){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        return null;
    }

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}scp_contests WHERE id=%d",
            $contest_id
        )
    );
}


function scp_get_admin_selected_contest_id(){

    if(isset($_GET['contest_id'])){
        $contest_id = intval($_GET['contest_id']);

        if($contest_id > 0){
            return $contest_id;
        }
    }

    return scp_get_current_contest_id();
}


function scp_get_contests(){

    global $wpdb;

    return $wpdb->get_results("
        SELECT *
        FROM {$wpdb->prefix}scp_contests
        ORDER BY `year` DESC, id DESC
    ");
}


function scp_admin_contest_selector($page_slug, $selected_id = 0){

    $selected_id = intval($selected_id);

    if($selected_id <= 0){
        $selected_id = scp_get_admin_selected_contest_id();
    }

    $contests = scp_get_contests();

    echo "<form method='get' style='margin:15px 0 25px 0;'>";
    echo "<input type='hidden' name='page' value='".esc_attr($page_slug)."'>";
    echo "<label><strong>Contest auswählen:</strong> </label>";
    echo "<select name='contest_id' onchange='this.form.submit()'>";

    foreach($contests as $contest){

        $label = "#".intval($contest->id)." - ".esc_html($contest->name);

        if(!empty($contest->year)){
            $label .= " (".esc_html($contest->year).")";
        }

        if(intval($contest->id) === scp_get_current_contest_id()){
            $label .= " - aktiv";
        }

        echo "<option value='".intval($contest->id)."' ".selected($selected_id, $contest->id, false).">".$label."</option>";
    }

    echo "</select>";
    echo " <noscript><button class='button'>Anzeigen</button></noscript>";
    echo "</form>";
}


function scp_get_contest_phase_value($field, $contest_id = 0){

    $contest_id = intval($contest_id);

    $contest = $contest_id > 0
        ? scp_get_contest($contest_id)
        : scp_get_current_contest();

    // Contest records are the single source of truth for phase dates.
    // Do not fall back to legacy global options when a contest exists,
    // otherwise old dates from previous contests can control the current UI.
    if($contest){
        return (isset($contest->$field) && !empty($contest->$field)) ? $contest->$field : '';
    }

    // Legacy fallback is kept only for installations that do not yet have
    // a contest record (migration/backward compatibility).
    $fallback_map = [
        'application_start' => 'scp_phase_application_start',
        'application_end'   => 'scp_phase_application_end',
        'top20_start'       => 'scp_phase_top20_start',
        'top20_end'         => 'scp_phase_top20_end',
        'vaus_start'        => 'scp_phase_vaus_start',
        'vaus_end'          => 'scp_phase_vaus_end',
        'final_start'       => 'scp_phase_final_start',
        'final_end'         => 'scp_phase_final_end',
        'winner_date'       => 'scp_phase_winner_date'
    ];

    if(isset($fallback_map[$field])){
        return get_option($fallback_map[$field]);
    }

    return '';
}


function scp_get_contest_int_value($field, $contest_id = 0, $default = 0){

    $contest_id = intval($contest_id);

    $contest = $contest_id > 0
        ? scp_get_contest($contest_id)
        : scp_get_current_contest();

    if($contest && isset($contest->$field) && intval($contest->$field) > 0){
        return intval($contest->$field);
    }

    return intval($default);
}


function scp_get_max_voting_participants($contest_id = 0){

    return scp_get_contest_int_value('max_voting_participants', $contest_id, intval(get_option('scp_voting_limit', 30)));
}


function scp_get_max_finalists($contest_id = 0){

    return scp_get_contest_int_value('max_finalists', $contest_id, 5);
}


function scp_get_contest_phase($contest_id = 0){

    $override = function_exists('scp_get_test_phase_override')
        ? scp_get_test_phase_override()
        : false;

    if($override){
        return $override;
    }

    $now = current_time('timestamp');

    $app_start = strtotime(scp_get_contest_phase_value('application_start', $contest_id));
    $app_end   = strtotime(scp_get_contest_phase_value('application_end', $contest_id));

    $top20_start = strtotime(scp_get_contest_phase_value('top20_start', $contest_id));
    $top20_end   = strtotime(scp_get_contest_phase_value('top20_end', $contest_id));

    $vaus_start = strtotime(scp_get_contest_phase_value('vaus_start', $contest_id));
    $vaus_end   = strtotime(scp_get_contest_phase_value('vaus_end', $contest_id));

    $final_start = strtotime(scp_get_contest_phase_value('final_start', $contest_id));
    $final_end   = strtotime(scp_get_contest_phase_value('final_end', $contest_id));

    $winner_date = strtotime(scp_get_contest_phase_value('winner_date', $contest_id));

    if($app_start && $app_end && $now >= $app_start && $now <= $app_end){
        return 'application';
    }

    if($top20_start && $top20_end && $now >= $top20_start && $now <= $top20_end){
        return 'top20';
    }

    if($vaus_start && $vaus_end && $now >= $vaus_start && $now <= $vaus_end){
        return 'vauswertung';
    }

    if($final_start && $final_end && $now >= $final_start && $now <= $final_end){
        return 'final';
    }

    if($winner_date && $now >= $winner_date){
        return 'winner';
    }

    return 'closed';
}




function scp_normalize_contest_status_value($status){

    $status = sanitize_text_field($status);

    if($status === 'archieved'){
        return 'archived';
    }

    return $status;
}


function scp_fix_legacy_contest_statuses(){

    global $wpdb;

    $wpdb->query(
        "
        UPDATE {$wpdb->prefix}scp_contests
        SET status='archived'
        WHERE status='archieved'
        "
    );
}


// Returns the contest that should receive new applications.
// Important:
// - No fallback to contest_id = 1.
// - A contest receives applications only while its application phase is open.// - Status may be draft or active.
// - archived / archieved contests are excluded.

function scp_get_application_contest_id(){

    global $wpdb;

    $now = current_time('mysql');

    scp_fix_legacy_contest_statuses();

    $contest_id = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT id
            FROM {$wpdb->prefix}scp_contests
            WHERE status IN ('draft','active')
            AND application_start IS NOT NULL
            AND application_end IS NOT NULL
            AND application_start <= %s
            AND application_end >= %s
            ORDER BY
                CASE status
                    WHEN 'active' THEN 1
                    WHEN 'draft' THEN 2
                    ELSE 9
                END ASC,
                application_start DESC,
                id DESC
            LIMIT 1
            ",
            $now,
            $now
        )
    );

    return $contest_id ? intval($contest_id) : 0;
}


// Returns the next contest whose application phase has not started yet.
// Archived contests are excluded. The nearest start date wins.

function scp_get_next_application_contest(){

    global $wpdb;

    $now = current_time('mysql');

    scp_fix_legacy_contest_statuses();

    return $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM {$wpdb->prefix}scp_contests
            WHERE status IN ('draft','active')
            AND application_start IS NOT NULL
            AND application_start > %s
            ORDER BY application_start ASC, id ASC
            LIMIT 1
            ",
            $now
        )
    );
}


// Context used by the public application shortcode when no application phase
// is currently open.

function scp_get_application_display_context(){

    $next_contest = scp_get_next_application_contest();

    if($next_contest){
        return [
            'state'       => 'countdown',
            'contest'     => $next_contest,
            'phase'       => 'before_application',
            'target_date' => $next_contest->application_start
        ];
    }

    $current_contest = scp_get_current_contest();
    $current_phase = $current_contest && function_exists('scp_get_contest_phase')
        ? scp_get_contest_phase($current_contest->id)
        : 'closed';

    if($current_phase === 'winner'){
        return [
            'state'   => 'planning',
            'contest' => $current_contest,
            'phase'   => $current_phase
        ];
    }

    if(in_array($current_phase, ['top20','vauswertung','final','winner'], true)){
        return [
            'state'   => 'running',
            'contest' => $current_contest,
            'phase'   => $current_phase
        ];
    }

    return [
        'state'   => 'planning',
        'contest' => $current_contest,
        'phase'   => $current_phase
    ];
}


function scp_log_event($level, $source, $message, $context = []){

    global $wpdb;

    $table = $wpdb->prefix.'scp_logs';

    $wpdb->insert(
        $table,
        [
            'contest_id' => scp_get_current_contest_id(),
            'level'      => sanitize_text_field($level),
            'source'     => sanitize_text_field($source),
            'message'    => sanitize_textarea_field($message),
            'context'    => !empty($context) ? wp_json_encode($context) : null,
            'created_at' => current_time('mysql')
        ]
    );
}


function scp_sanitize_datetime_value($value){

    $value = sanitize_text_field($value);

    if(empty($value)){
        return null;
    }

    return str_replace('T', ' ', $value);
}


function scp_build_contest_data_from_array($data){

    return [
        'name'                    => sanitize_text_field($data['name'] ?? ''),
        'slug'                    => sanitize_title($data['slug'] ?? ($data['name'] ?? '')),
        'year'                    => intval($data['year'] ?? date('Y')),
        'status'                  => scp_normalize_contest_status_value($data['status'] ?? 'draft'),
        'application_start'       => scp_sanitize_datetime_value($data['application_start'] ?? ''),
        'application_end'         => scp_sanitize_datetime_value($data['application_end'] ?? ''),
        'top20_start'             => scp_sanitize_datetime_value($data['top20_start'] ?? ''),
        'top20_end'               => scp_sanitize_datetime_value($data['top20_end'] ?? ''),
        'vaus_start'              => scp_sanitize_datetime_value($data['vaus_start'] ?? ''),
        'vaus_end'                => scp_sanitize_datetime_value($data['vaus_end'] ?? ''),
        'max_voting_participants' => intval($data['max_voting_participants'] ?? 30),
        'max_finalists'           => intval($data['max_finalists'] ?? 5),
        'final_start'             => scp_sanitize_datetime_value($data['final_start'] ?? ''),
        'final_end'               => scp_sanitize_datetime_value($data['final_end'] ?? ''),
        'winner_date'             => scp_sanitize_datetime_value($data['winner_date'] ?? '')
    ];
}


function scp_create_contest($data){

    global $wpdb;

    $table = $wpdb->prefix.'scp_contests';

    $insert = scp_build_contest_data_from_array($data);

    if(empty($insert['name'])){
        return new WP_Error('missing_name','Contest-Name fehlt.');
    }

    if(empty($insert['slug'])){
        $insert['slug'] = sanitize_title($insert['name']);
    }

    $insert['created_at'] = current_time('mysql');

    $wpdb->insert($table, $insert);

    if(!$wpdb->insert_id){
        return new WP_Error('db_error',$wpdb->last_error);
    }

    return intval($wpdb->insert_id);
}


function scp_update_contest($contest_id, $data){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        return new WP_Error('invalid_id','Ungültige Contest-ID.');
    }

    $update = scp_build_contest_data_from_array($data);

    if(empty($update['name'])){
        return new WP_Error('missing_name','Contest-Name fehlt.');
    }

    if(empty($update['slug'])){
        $update['slug'] = sanitize_title($update['name']);
    }

    $update['updated_at'] = current_time('mysql');

    $result = $wpdb->update(
        $wpdb->prefix.'scp_contests',
        $update,
        ['id' => $contest_id]
    );

    if($result === false){
        return new WP_Error('db_error',$wpdb->last_error);
    }

    return true;
}


function scp_set_current_contest($contest_id){

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        return false;
    }

    update_option('scp_current_contest_id', $contest_id);

    return true;
}

function scp_get_public_contest(){

    global $wpdb;

    $table = $wpdb->prefix.'scp_contests';
    $now   = current_time('mysql');

    /*
     * 1. Laufenden, öffentlichen Contest suchen.
     */
    $contest = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $table
            WHERE status='active'
            AND (
                winner_date IS NULL
                OR winner_date >= %s
                OR final_end >= %s
                OR top20_end >= %s
                OR application_end >= %s
            )
            ORDER BY id DESC
            LIMIT 1
            ",
            $now,
            $now,
            $now,
            $now
        )
    );

    if($contest){
        return $contest;
    }

    /*
     * 2. Nächsten geplanten Contest suchen.
     *
     * Ein Draft-Contest darf bereits Bewerber enthalten,
     * wird aber noch nicht als laufender Contest behandelt.
     */
    $contest = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $table
            WHERE status='draft'
            AND (
                application_end IS NULL
                OR application_end >= %s
            )
            ORDER BY
                CASE
                    WHEN application_start IS NULL THEN 1
                    ELSE 0
                END,
                application_start ASC,
                id ASC
            LIMIT 1
            ",
            $now
        )
    );

    return $contest ?: null;
}


function scp_get_public_contest_id(){

    $contest = scp_get_public_contest();

    return $contest
        ? intval($contest->id)
        : 0;
}


/**
 * Contest, den die öffentlichen Seiten anzeigen (Band-Profil, Countdown, Charts).
 *
 * Voting-Liste, Stimmabgabe und Voter-Login nutzen den "öffentlichen" Contest
 * (Status Aktiv bzw. nächster Entwurf). Damit alle öffentlichen Seiten
 * denselben Contest zeigen, folgen sie derselben Auswahl. Gibt es keinen
 * öffentlichen Contest, gilt der im Backend gesetzte aktuelle Contest.
 */
function scp_get_frontend_contest_id(){

    static $cache = null;

    if($cache !== null){
        return $cache;
    }

    $contest_id = function_exists('scp_get_public_contest_id')
        ? scp_get_public_contest_id()
        : 0;

    if($contest_id <= 0){
        $contest_id = scp_get_current_contest_id();
    }

    $cache = intval($contest_id);

    return $cache;
}

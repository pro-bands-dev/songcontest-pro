<?php
/*
add_shortcode("songcontest_charts","scp_charts");

// =========================
//   CHARTS HELPER
// =========================

function scp_charts_get_phase_context(){

    $phase = function_exists('scp_get_current_phase')
        ? scp_get_current_phase()
        : 'application';

    $now = current_time('timestamp');

    $final_start = (function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_start') : get_option('scp_phase_final_start'));
    $final_end   = (function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_end') : get_option('scp_phase_final_end'));
    $winner_date = (function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('winner_date') : get_option('scp_phase_winner_date'));

    $final_start_ts = !empty($final_start) ? strtotime($final_start) : 0;
    $final_end_ts   = !empty($final_end) ? strtotime($final_end) : 0;
    $winner_ts      = !empty($winner_date) ? strtotime($winner_date) : 0;

    //  Finale Gesamtwertung anzeigen:
    //  - während der Finalphase
    //  - nach Ende der Finalphase bis zur Winner-Veröffentlichung
    //  
    //  Dadurch bleibt die Live-Charts-Seite in der manuellen Prüfphase
    //  zwischen Finale und Winner auf dem Gesamtscore der Finalisten.

    $show_final_score = false;

    if($phase === 'final'){
        $show_final_score = true;
    }

    if(
        !$show_final_score
        &&
        $final_end_ts
        &&
        $now > $final_end_ts
        &&
        (
            !$winner_ts
            ||
            $now < $winner_ts
        )
    ){
        $show_final_score = true;
    }

    return [
        'phase'            => $phase,
        'show_final_score' => $show_final_score,
        'final_start'      => $final_start
    ];
}


function scp_charts_prepare_final_score_rows(&$rows){

    $max_public = 0;
    $max_jury   = 0;

    foreach($rows as $row){

        $public_votes = intval($row->votes);
        $jury_points  = intval($row->jury_points);

        if($public_votes > $max_public){
            $max_public = $public_votes;
        }

        if($jury_points > $max_jury){
            $max_jury = $jury_points;
        }
    }

    foreach($rows as $row){

        $public_votes = intval($row->votes);
        $jury_points  = intval($row->jury_points);

        $public_score = ($max_public > 0)
            ? ($public_votes / $max_public) * 100
            : 0;

        $jury_score = ($max_jury > 0)
            ? ($jury_points / $max_jury) * 100
            : 0;

        $p70_score = $public_score * 0.70;
        $j30_score = $jury_score * 0.30;

        $row->public_score = round($public_score,2);
        $row->jury_score   = round($jury_score,2);
        $row->p70_score    = round($p70_score,2);
        $row->j30_score    = round($j30_score,2);
        $row->score        = round($p70_score + $j30_score,2);
    }

    usort($rows,function($a,$b){

        if($a->score == $b->score){

            if($a->votes == $b->votes){
                return strcasecmp($a->name ?? '', $b->name ?? '');
            }

            return $b->votes <=> $a->votes;
        }

        return $b->score <=> $a->score;
    });
}


function scp_charts_get_final_score_rows(){

    global $wpdb;

    $final_start = (function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_start') : get_option('scp_phase_final_start'));

    if(empty($final_start)){
        return [];
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT
                a.id,
                a.name,
                COUNT(v.id) AS votes,
                COALESCE(j.jury_points,0) AS jury_points
            FROM {$wpdb->prefix}scp_artists a
            LEFT JOIN {$wpdb->prefix}scp_votes v
                ON a.id = v.artist_id
                AND v.vote_date >= %s
            LEFT JOIN (
                SELECT
                    artist_id,
                    SUM(points) AS jury_points
                FROM {$wpdb->prefix}scp_jury_votes
                GROUP BY artist_id
            ) j
                ON a.id = j.artist_id
            WHERE a.status='finale'
            GROUP BY a.id
            ",
            $final_start
        )
    );

    scp_charts_prepare_final_score_rows($rows);

    return $rows;
}


function scp_charts_get_vote_rows($phase){

    global $wpdb;

    $status_filter = "'approved','top20'";
    $limit = 10;

    if($phase === 'top20'){
        $status_filter = "'top20'";
    }
    elseif($phase === 'vauswertung'){
        $status_filter = "'top20','finale'";
    }
    elseif($phase === 'winner'){
        $status_filter = "'winner','finale'";
        $limit = 5;
    }

    return $wpdb->get_results("
        SELECT
            a.id,
            a.name,
            COUNT(v.id) AS votes
        FROM {$wpdb->prefix}scp_artists a
        LEFT JOIN {$wpdb->prefix}scp_votes v
            ON a.id = v.artist_id
        WHERE a.status IN ($status_filter)
        GROUP BY a.id
        ORDER BY votes DESC, a.name ASC
        LIMIT ".intval($limit)."
    ");
}


// =========================
//   SHORTCODE OUTPUT
// =========================

function scp_charts(){

    $context = scp_charts_get_phase_context();

    $phase = $context['phase'];
    $show_final_score = $context['show_final_score'];

    $ref = isset($_GET['ref']) ? intval($_GET['ref']) : 0;

    $voting_url = function_exists('scp_get_public_page_url')
        ? scp_get_public_page_url('voting', site_url('/song-contest-2026/song-contest-voting/'))
        : site_url('/song-contest-2026/song-contest-voting/');

    if($ref){
        $voting_url .= '?ref='.$ref;
    }

    if($show_final_score){

        $rows = scp_charts_get_final_score_rows();

        $headline = "🏆 Finale Gesamtwertung";

        $chart_label = "Gesamtscore";

        $chart_mode = "score";

    }else{

        $rows = scp_charts_get_vote_rows($phase);

        $headline = "🏆 Live Voting Charts";

        $chart_label = "Votes";

        $chart_mode = "votes";
    }

    $html = "<div class='scp-public-leaderboard'>";

    $html .= "<h2>".esc_html($headline)."</h2>";

    if($show_final_score){
        $html .= "<p class='scp-chart-note'>Publikum 70% / Jury 30% — beide Werte normalisiert.</p>";
    }

    $html .= "<ol class='scp-charts' data-phase='".esc_attr($phase)."' data-chart-mode='".esc_attr($chart_mode)."'>";

    foreach($rows as $row){

        $value = $show_final_score
            ? number_format((float)$row->score,2,'.','')
            : intval($row->votes);

        $html .= "
        <li data-band='".esc_attr($row->id)."'>
            <span class='chart-name'>".esc_html($row->name)."</span>
            (<span class='chart-votes'>".esc_html($value)."</span> ".esc_html($chart_label).")
        </li>
        ";
    }

    $html .= "</ol>";

    $html .= "</div>";

    return $html;
}


// =========================
//   LIVE CHARTS AJAX
// =========================

add_action("wp_ajax_scp_get_chart_votes","scp_get_chart_votes");
add_action("wp_ajax_nopriv_scp_get_chart_votes","scp_get_chart_votes");

function scp_get_chart_votes(){

    $context = scp_charts_get_phase_context();

    $phase = $context['phase'];
    $show_final_score = $context['show_final_score'];

    if($show_final_score){
        $rows = scp_charts_get_final_score_rows();
    }else{
        $rows = scp_charts_get_vote_rows($phase);
    }

    $data = [];

    foreach($rows as $row){

        if($show_final_score){
            $data[intval($row->id)] = number_format((float)$row->score,2,'.','');
        }else{
            $data[intval($row->id)] = intval($row->votes);
        }
    }

    wp_send_json([
        'mode' => $show_final_score ? 'score' : 'votes',
        'data' => $data
    ]);
}
*/

add_shortcode("songcontest_charts","scp_charts");

// =========================
//   CHARTS HELPER
// =========================

function scp_charts_get_phase_context(){

    $contest_id = function_exists('scp_get_frontend_contest_id')
        ? scp_get_frontend_contest_id()
        : (function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1);

    $phase = function_exists('scp_get_contest_phase')
        ? scp_get_contest_phase($contest_id)
        : (function_exists('scp_get_current_phase') ? scp_get_current_phase() : 'application');

    $now = current_time('timestamp');

    $final_start = function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_start', $contest_id) : (function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_start') : get_option('scp_phase_final_start'));
    $final_end   = function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_end', $contest_id) : (function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_end') : get_option('scp_phase_final_end'));
    $winner_date = function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('winner_date', $contest_id) : (function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('winner_date') : get_option('scp_phase_winner_date'));

    $final_start_ts = !empty($final_start) ? strtotime($final_start) : 0;
    $final_end_ts   = !empty($final_end) ? strtotime($final_end) : 0;
    $winner_ts      = !empty($winner_date) ? strtotime($winner_date) : 0;

    // * Finale Gesamtwertung anzeigen:
    // * - während der Finalphase
    //  * - nach Ende der Finalphase bis zur Winner-Veröffentlichung
    //  *
    //  * Dadurch bleibt die Live-Charts-Seite in der manuellen Prüfphase
    //  * zwischen Finale und Winner auf dem Gesamtscore der Finalisten.

    $show_final_score = false;

    if($phase === 'final'){
        $show_final_score = true;
    }

    if(
        !$show_final_score
        &&
        $final_end_ts
        &&
        $now > $final_end_ts
        &&
        (
            !$winner_ts
            ||
            $now < $winner_ts
        )
    ){
        $show_final_score = true;
    }

    return [
        'phase'            => $phase,
        'show_final_score' => $show_final_score,
        'final_start'      => $final_start
    ];
}


function scp_charts_prepare_final_score_rows(&$rows){

    $max_public = 0;
    $max_jury   = 0;

    foreach($rows as $row){

        $public_votes = intval($row->votes);
        $jury_points  = intval($row->jury_points);

        if($public_votes > $max_public){
            $max_public = $public_votes;
        }

        if($jury_points > $max_jury){
            $max_jury = $jury_points;
        }
    }

    foreach($rows as $row){

        $public_votes = intval($row->votes);
        $jury_points  = intval($row->jury_points);

        $public_score = ($max_public > 0)
            ? ($public_votes / $max_public) * 100
            : 0;

        $jury_score = ($max_jury > 0)
            ? ($jury_points / $max_jury) * 100
            : 0;

        $p70_score = $public_score * 0.70;
        $j30_score = $jury_score * 0.30;

        $row->public_score = round($public_score,2);
        $row->jury_score   = round($jury_score,2);
        $row->p70_score    = round($p70_score,2);
        $row->j30_score    = round($j30_score,2);
        $row->score        = round($p70_score + $j30_score,2);
    }

    usort($rows,function($a,$b){

        if($a->score == $b->score){

            if($a->votes == $b->votes){
                return strcasecmp($a->name ?? '', $b->name ?? '');
            }

            return $b->votes <=> $a->votes;
        }

        return $b->score <=> $a->score;
    });
}


function scp_charts_get_final_score_rows(){

    global $wpdb;

    $contest_id = function_exists('scp_get_frontend_contest_id')
        ? scp_get_frontend_contest_id()
        : (function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1);

    $final_start = function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_start', $contest_id) : (function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_start') : get_option('scp_phase_final_start'));

    if(empty($final_start)){
        return [];
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT
                a.id,
                a.name,
                COUNT(v.id) AS votes,
                COALESCE(j.jury_points,0) AS jury_points
            FROM {$wpdb->prefix}scp_artists a
            LEFT JOIN {$wpdb->prefix}scp_votes v
                ON a.id = v.artist_id
                AND v.contest_id = %d
                AND v.vote_date >= %s
            LEFT JOIN (
                SELECT
                    artist_id,
                    SUM(points) AS jury_points
                FROM {$wpdb->prefix}scp_jury_votes
                WHERE contest_id = %d
                GROUP BY artist_id
            ) j
                ON a.id = j.artist_id
            WHERE a.contest_id = %d
            AND a.status='finale'
            GROUP BY a.id
            ",
            $contest_id,
            $final_start,
            $contest_id,
            $contest_id
        )
    );

    scp_charts_prepare_final_score_rows($rows);

    return $rows;
}


function scp_charts_get_vote_rows($phase){

    global $wpdb;

    $contest_id = function_exists('scp_get_frontend_contest_id')
        ? scp_get_frontend_contest_id()
        : (function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1);

    $status_filter = "'approved','top20'";
    $limit = 10;

    if($phase === 'top20'){
        $status_filter = "'top20'";
    }
    elseif($phase === 'vauswertung'){
        $status_filter = "'top20','finale'";
    }
    elseif($phase === 'winner'){
        $status_filter = "'winner','finale'";
        $limit = 5;
    }

    return $wpdb->get_results("
        SELECT
            a.id,
            a.name,
            COUNT(v.id) AS votes
        FROM {$wpdb->prefix}scp_artists a
        LEFT JOIN {$wpdb->prefix}scp_votes v
            ON a.id = v.artist_id
            AND v.contest_id = $contest_id
        WHERE a.contest_id = $contest_id
        AND a.status IN ($status_filter)
        GROUP BY a.id
        ORDER BY votes DESC, a.name ASC
        LIMIT ".intval($limit)."
    ");
}


// =========================
//   SHORTCODE OUTPUT
// =========================

function scp_charts(){

    $context = scp_charts_get_phase_context();

    $phase = $context['phase'];
    $show_final_score = $context['show_final_score'];

    $ref = isset($_GET['ref']) ? intval($_GET['ref']) : 0;

    $voting_url = function_exists('scp_get_public_page_url') && scp_get_public_page_url('voting')
        ? scp_get_public_page_url('voting')
        : (function_exists('scp_get_contest_public_base_url') && function_exists('scp_get_frontend_contest_id')
            ? scp_get_contest_public_base_url(scp_get_frontend_contest_id()).'song-contest-voting/'
            : site_url('/song-contest-2026/song-contest-voting/'));

    if($ref){
        $voting_url .= '?ref='.$ref;
    }

    if($show_final_score){

        $rows = scp_charts_get_final_score_rows();

        $headline = "🏆 Finale Gesamtwertung";

        $chart_label = "Gesamtscore";

        $chart_mode = "score";

    }else{

        $rows = scp_charts_get_vote_rows($phase);

        $headline = "🏆 Live Voting Charts";

        $chart_label = "Votes";

        $chart_mode = "votes";
    }

    $html = "<div class='scp-public-leaderboard'>";

    $html .= "<h2>".esc_html($headline)."</h2>";

    if($show_final_score){
        $html .= "<p class='scp-chart-note'>Publikum 70% / Jury 30% — beide Werte normalisiert.</p>";
    }

    $html .= "<ol class='scp-charts' data-phase='".esc_attr($phase)."' data-chart-mode='".esc_attr($chart_mode)."'>";

    foreach($rows as $row){

        $value = $show_final_score
            ? number_format((float)$row->score,2,'.','')
            : intval($row->votes);

        $html .= "
        <li data-band='".esc_attr($row->id)."'>
            <span class='chart-name'>".esc_html($row->name)."</span>
            (<span class='chart-votes'>".esc_html($value)."</span> ".esc_html($chart_label).")
        </li>
        ";
    }

    $html .= "</ol>";

    $html .= "</div>";

    return $html;
}


// =========================
//   LIVE CHARTS AJAX
// =========================

add_action("wp_ajax_scp_get_chart_votes","scp_get_chart_votes");
add_action("wp_ajax_nopriv_scp_get_chart_votes","scp_get_chart_votes");

function scp_get_chart_votes(){

    $context = scp_charts_get_phase_context();

    $phase = $context['phase'];
    $show_final_score = $context['show_final_score'];

    if($show_final_score){
        $rows = scp_charts_get_final_score_rows();
    }else{
        $rows = scp_charts_get_vote_rows($phase);
    }

    $data = [];

    foreach($rows as $row){

        if($show_final_score){
            $data[intval($row->id)] = number_format((float)$row->score,2,'.','');
        }else{
            $data[intval($row->id)] = intval($row->votes);
        }
    }

    wp_send_json([
        'mode' => $show_final_score ? 'score' : 'votes',
        'data' => $data
    ]);
}
	
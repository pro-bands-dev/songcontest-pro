<?php

if (!defined('ABSPATH')) exit;

function scp_get_test_phase_override(){
    if(isset($_GET['testphase'])){
        return sanitize_text_field($_GET['testphase']);
    }
    return false;
}


function scp_get_current_phase(){

    if(function_exists('scp_get_contest_phase')){
        return scp_get_contest_phase(scp_get_current_contest_id());
    }

    $override = scp_get_test_phase_override();
    if($override){
        return $override;
    }

    $now = current_time('timestamp');

    $app_start = strtotime(get_option('scp_phase_application_start'));
    $app_end = strtotime(get_option('scp_phase_application_end'));

    $top20_start = strtotime(get_option('scp_phase_top20_start'));
    $top20_end = strtotime(get_option('scp_phase_top20_end'));

    $vaus_start = strtotime(get_option('scp_phase_vaus_start'));
    $vaus_end = strtotime(get_option('scp_phase_vaus_end'));

    $final_start = strtotime(get_option('scp_phase_final_start'));
    $final_end = strtotime(get_option('scp_phase_final_end'));

    $winner_date = strtotime(get_option('scp_phase_winner_date'));

    if($app_start && $app_end && $now >= $app_start && $now <= $app_end){
        return "application";
    }

    if($top20_start && $top20_end && $now >= $top20_start && $now <= $top20_end){
        return "top20";
    }

    if($vaus_start && $vaus_end && $now >= $vaus_start && $now <= $vaus_end){
        return "vauswertung";
    }

    if($final_start && $final_end && $now >= $final_start && $now <= $final_end){
        return "final";
    }

    if($winner_date && $now >= $winner_date){
        return "winner";
    }

    return "closed";
}


function scp_get_phase_vote_start(){

    $phase = scp_get_current_phase();

    switch($phase){

        case 'final':
            return function_exists('scp_get_contest_phase_value')
                ? scp_get_contest_phase_value('final_start')
                : get_option('scp_phase_final_start');

        default:
            return false;
    }
}


function scp_get_top_bands($limit, $contest_id = 0){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1;
    }

    $table_artists = $wpdb->prefix.'scp_artists';
    $table_votes   = $wpdb->prefix.'scp_votes';

    $top20bands = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT a.*, COUNT(v.id) AS votes
            FROM $table_artists a
            LEFT JOIN $table_votes v
                ON a.id = v.artist_id
                AND v.contest_id = %d
            WHERE a.status = 'approved'
            AND a.contest_id = %d
            GROUP BY a.id
            ORDER BY votes DESC
            LIMIT %d
            ",
            $contest_id,
            $contest_id,
            intval($limit)
        )
    );

    return $top20bands;
}


function scp_get_jury_scores($contest_id = 0){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1;
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT artist_id, SUM(points) points
            FROM {$wpdb->prefix}scp_jury_votes
            WHERE contest_id=%d
            GROUP BY artist_id
            ORDER BY points DESC
            ",
            $contest_id
        )
    );
}


function scp_get_final_score_ranking($limit = 0, $contest_id = 0){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1;
    }

    $final_start = function_exists('scp_get_contest_phase_value')
        ? scp_get_contest_phase_value('final_start', $contest_id)
        : get_option('scp_phase_final_start');

    if(empty($final_start)){
        return [];
    }

    $bands_all = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT
                a.*,
                COALESCE(v.public_votes,0) AS public_votes,
                COALESCE(j.jury_points,0) AS jury_points

            FROM {$wpdb->prefix}scp_artists a

            LEFT JOIN (
                SELECT
                    artist_id,
                    COUNT(id) AS public_votes
                FROM {$wpdb->prefix}scp_votes
                WHERE contest_id=%d
                AND vote_date >= %s
                GROUP BY artist_id
            ) v
            ON a.id = v.artist_id

            LEFT JOIN (
                SELECT
                    artist_id,
                    SUM(points) AS jury_points
                FROM {$wpdb->prefix}scp_jury_votes
                WHERE contest_id=%d
                GROUP BY artist_id
            ) j
            ON a.id = j.artist_id

            WHERE a.contest_id=%d
            AND a.status IN ('finale','winner')
            ",
            $contest_id,
            $final_start,
            $contest_id,
            $contest_id
        )
    );

    if(empty($bands_all)){
        return [];
    }

    $max_public = 0;
    $max_jury   = 0;

    foreach($bands_all as $band){

        $public = intval($band->public_votes);
        $jury   = intval($band->jury_points);

        if($public > $max_public){
            $max_public = $public;
        }

        if($jury > $max_jury){
            $max_jury = $jury;
        }
    }

    foreach($bands_all as $band){

        $public = intval($band->public_votes);
        $jury   = intval($band->jury_points);

        $public_score =
            ($max_public > 0)
            ? ($public / $max_public) * 100
            : 0;

        $jury_score =
            ($max_jury > 0)
            ? ($jury / $max_jury) * 100
            : 0;

        $p70_score = $public_score * 0.70;
        $j30_score = $jury_score * 0.30;

        $total_score = $p70_score + $j30_score;

        $band->votes         = $public;
        $band->public_votes  = $public;
        $band->jury_points   = $jury;
        $band->public_score  = round($public_score,2);
        $band->jury_score    = round($jury_score,2);
        $band->p70_score     = round($p70_score,2);
        $band->j30_score     = round($j30_score,2);
        $band->total_score   = round($total_score,2);
    }

    usort($bands_all,function($a,$b){

        if($b->total_score == $a->total_score){
            return $b->public_votes <=> $a->public_votes;
        }

        return $b->total_score <=> $a->total_score;
    });

    if($limit > 0){
        return array_slice($bands_all,0,intval($limit));
    }

    return $bands_all;
}


function scp_get_winner($contest_id = 0){

    $ranking = scp_get_final_score_ranking(1, $contest_id);

    if(empty($ranking)){
        return null;
    }

    return $ranking[0];
}


function scp_set_finalists($contest_id = 0){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1;
    }

    $limit = function_exists('scp_get_max_finalists') ? scp_get_max_finalists($contest_id) : 5;

    $top5 = scp_get_top_bands($limit, $contest_id);

    $wpdb->query(
        $wpdb->prepare(
            "
            UPDATE {$wpdb->prefix}scp_artists
            SET status='top20'
            WHERE status='finale'
            AND contest_id=%d
            ",
            $contest_id
        )
    );

    foreach($top5 as $band){

        $wpdb->update(
            $wpdb->prefix.'scp_artists',
            ['status'=>'finale'],
            ['id'=>$band->id, 'contest_id'=>$contest_id]
        );
    }
}


function scp_set_winner_status($contest_id = 0){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1;
    }

    $winner = scp_get_winner($contest_id);

    if(!$winner){
        return;
    }

    $wpdb->query(
        $wpdb->prepare(
            "
            UPDATE {$wpdb->prefix}scp_artists
            SET status='finale'
            WHERE status='winner'
            AND contest_id=%d
            ",
            $contest_id
        )
    );

    $wpdb->update(
        $wpdb->prefix.'scp_artists',
        ['status'=>'winner'],
        ['id'=>$winner->id, 'contest_id'=>$contest_id]
    );
}

<?php

if (!defined('ABSPATH')) exit;

/**
 * Contest result snapshots.
 *
 * A snapshot freezes the historical result of one contest so later changes
 * to artist status or raw voting data do not mix completed contests.
 */

function scp_get_results_table(){
    global $wpdb;
    return $wpdb->prefix.'scp_contest_results';
}


function scp_get_contest_results($contest_id){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        return [];
    }

    $table = scp_get_results_table();

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM $table
            WHERE contest_id=%d
            ORDER BY
                CASE result_group
                    WHEN 'winner' THEN 1
                    WHEN 'finalist' THEN 2
                    WHEN 'top_voting' THEN 3
                    ELSE 4
                END ASC,
                CASE WHEN position IS NULL THEN 999999 ELSE position END ASC,
                artist_name ASC
            ",
            $contest_id
        )
    );
}


function scp_get_contest_result_count($contest_id){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        return 0;
    }

    return intval(
        $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM ".scp_get_results_table()." WHERE contest_id=%d",
                $contest_id
            )
        )
    );
}


function scp_get_contest_result_snapshot_date($contest_id){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        return '';
    }

    return (string)$wpdb->get_var(
        $wpdb->prepare(
            "SELECT MAX(snapshot_at) FROM ".scp_get_results_table()." WHERE contest_id=%d",
            $contest_id
        )
    );
}


function scp_get_contest_total_votes_map($contest_id){

    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT artist_id, COUNT(*) AS votes
            FROM {$wpdb->prefix}scp_votes
            WHERE contest_id=%d
            GROUP BY artist_id
            ",
            intval($contest_id)
        )
    );

    $map = [];

    foreach($rows as $row){
        $map[intval($row->artist_id)] = intval($row->votes);
    }

    return $map;
}


function scp_build_contest_results_snapshot($contest_id, $force = false){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        return new WP_Error('invalid_contest','Ungültige Contest-ID.');
    }

    $contest = function_exists('scp_get_contest')
        ? scp_get_contest($contest_id)
        : null;

    if(!$contest){
        return new WP_Error('contest_not_found','Contest wurde nicht gefunden.');
    }

    $results_table = scp_get_results_table();
    $existing = scp_get_contest_result_count($contest_id);

    if($existing > 0 && !$force){
        return new WP_Error(
            'snapshot_exists',
            'Für diesen Contest existiert bereits ein Ergebnis-Snapshot.'
        );
    }

    $artists = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM {$wpdb->prefix}scp_artists
            WHERE contest_id=%d
            AND status IN ('approved','top20','final','finale','winner')
            ORDER BY id ASC
            ",
            $contest_id
        )
    );

    if(empty($artists)){
        return new WP_Error('no_participants','Für diesen Contest wurden keine Teilnehmer gefunden.');
    }

    $total_votes = scp_get_contest_total_votes_map($contest_id);

    /*
     * Finale: Platz 1..N nach dem bereits im Plugin verwendeten
     * normalisierten 70/30-Gesamtscore.
     */
    $max_finalists = function_exists('scp_get_max_finalists')
        ? max(1, scp_get_max_finalists($contest_id))
        : 5;

    $max_voting = function_exists('scp_get_max_voting_participants')
        ? max($max_finalists, scp_get_max_voting_participants($contest_id))
        : 30;

    $final_ranking = function_exists('scp_get_final_score_ranking')
        ? scp_get_final_score_ranking(0, $contest_id)
        : [];

    $final_ranking = array_slice($final_ranking, 0, $max_finalists);

    $final_by_id = [];
    $final_ids = [];

    foreach($final_ranking as $index => $band){
        $artist_id = intval($band->id);
        $final_ids[] = $artist_id;
        $final_by_id[$artist_id] = [
            'position'     => $index + 1,
            'public_votes' => intval($band->public_votes ?? 0),
            'jury_points'  => intval($band->jury_points ?? 0),
            'public_score' => floatval($band->public_score ?? 0),
            'jury_score'   => floatval($band->jury_score ?? 0),
            'p70_score'    => floatval($band->p70_score ?? 0),
            'j30_score'    => floatval($band->j30_score ?? 0),
            'total_score'  => floatval($band->total_score ?? 0)
        ];
    }

    /*
     * Fallback für historische Daten: falls der Final-Score nicht mehr
     * berechnet werden kann, vorhandene finale/winner-Statuswerte verwenden.
     */
    if(empty($final_ids)){

        $fallback_finalists = array_values(array_filter($artists,function($artist){
            return in_array($artist->status,['final','finale','winner'],true);
        }));

        usort($fallback_finalists,function($a,$b) use ($total_votes){
            $av = $total_votes[intval($a->id)] ?? 0;
            $bv = $total_votes[intval($b->id)] ?? 0;

            if($a->status === 'winner' && $b->status !== 'winner') return -1;
            if($b->status === 'winner' && $a->status !== 'winner') return 1;

            return $bv <=> $av;
        });

        $fallback_finalists = array_slice($fallback_finalists,0,$max_finalists);

        foreach($fallback_finalists as $index => $band){
            $artist_id = intval($band->id);
            $final_ids[] = $artist_id;
            $final_by_id[$artist_id] = [
                'position'     => $index + 1,
                'public_votes' => 0,
                'jury_points'  => 0,
                'public_score' => 0,
                'jury_score'   => 0,
                'p70_score'    => 0,
                'j30_score'    => 0,
                'total_score'  => 0
            ];
        }
    }

    /*
     * Top-Voting-Teilnehmer: historische Top-Gruppe anhand des gespeicherten
     * Artist-Status. Finalisten werden separat behandelt.
     */
    $top_voting_candidates = [];

    foreach($artists as $artist){
        $artist_id = intval($artist->id);

        if(in_array($artist_id,$final_ids,true)){
            continue;
        }

        if($artist->status === 'top20'){
            $top_voting_candidates[] = $artist;
        }
    }

    usort($top_voting_candidates,function($a,$b) use ($total_votes){
        $av = $total_votes[intval($a->id)] ?? 0;
        $bv = $total_votes[intval($b->id)] ?? 0;

        if($av === $bv){
            return strcasecmp($a->name,$b->name);
        }

        return $bv <=> $av;
    });

    $top_slots = max(0, $max_voting - count($final_ids));
    $top_voting_candidates = array_slice($top_voting_candidates,0,$top_slots);

    $top_ids = array_map(function($artist){
        return intval($artist->id);
    },$top_voting_candidates);

    /*
     * Restliche Teilnehmer werden nur gruppiert. Die Position ist eine
     * Darstellungsreihenfolge und keine offizielle Platzierung.
     */
    $remaining = [];

    foreach($artists as $artist){
        $artist_id = intval($artist->id);

        if(in_array($artist_id,$final_ids,true) || in_array($artist_id,$top_ids,true)){
            continue;
        }

        $remaining[] = $artist;
    }

    usort($remaining,function($a,$b) use ($total_votes){
        $av = $total_votes[intval($a->id)] ?? 0;
        $bv = $total_votes[intval($b->id)] ?? 0;

        if($av === $bv){
            return strcasecmp($a->name,$b->name);
        }

        return $bv <=> $av;
    });

    $wpdb->query('START TRANSACTION');

    if($force){
        $deleted = $wpdb->delete($results_table,['contest_id'=>$contest_id],['%d']);

        if($deleted === false){
            $wpdb->query('ROLLBACK');
            return new WP_Error('delete_failed',$wpdb->last_error);
        }
    }

    $snapshot_at = current_time('mysql');
    $inserted = 0;

    $insert_row = function($artist,$group,$position,$stats = []) use (
        $wpdb,$results_table,$contest_id,$total_votes,$snapshot_at,&$inserted
    ){

        $artist_id = intval($artist->id);

        $data = [
            'contest_id'    => $contest_id,
            'artist_id'     => $artist_id,
            'result_group'  => $group,
            'position'      => $position,
            'artist_status' => sanitize_text_field($artist->status),
            'artist_name'   => sanitize_text_field($artist->name),
            'song_title'    => sanitize_text_field($artist->title ?? ''),
            'genre'         => sanitize_text_field($artist->genre ?? ''),
            'image_url'     => esc_url_raw($artist->image ?? ''),
            'audio_url'     => esc_url_raw($artist->audio ?? ''),
            'total_votes'   => intval($total_votes[$artist_id] ?? 0),
            'final_votes'   => intval($stats['public_votes'] ?? 0),
            'jury_points'   => intval($stats['jury_points'] ?? 0),
            'public_score'  => floatval($stats['public_score'] ?? 0),
            'jury_score'    => floatval($stats['jury_score'] ?? 0),
            'p70_score'     => floatval($stats['p70_score'] ?? 0),
            'j30_score'     => floatval($stats['j30_score'] ?? 0),
            'total_score'   => floatval($stats['total_score'] ?? 0),
            'snapshot_at'   => $snapshot_at
        ];

        $ok = $wpdb->insert($results_table,$data);

        if($ok !== false){
            $inserted++;
        }

        return $ok;
    };

    /* Finalisten nach Score-Reihenfolge einfügen. */
    foreach($final_ranking as $index => $final_band){

        $artist = null;

        foreach($artists as $candidate){
            if(intval($candidate->id) === intval($final_band->id)){
                $artist = $candidate;
                break;
            }
        }

        if(!$artist){
            continue;
        }

        $artist_id = intval($artist->id);
        $group = ($index === 0) ? 'winner' : 'finalist';

        if($insert_row($artist,$group,$index+1,$final_by_id[$artist_id] ?? []) === false){
            $wpdb->query('ROLLBACK');
            return new WP_Error('insert_failed',$wpdb->last_error);
        }
    }

    /* Fallback-Finalisten, falls final_ranking leer war. */
    if(empty($final_ranking)){
        foreach($final_ids as $index => $artist_id){
            foreach($artists as $artist){
                if(intval($artist->id) === intval($artist_id)){
                    $group = ($index === 0) ? 'winner' : 'finalist';
                    if($insert_row($artist,$group,$index+1,$final_by_id[$artist_id] ?? []) === false){
                        $wpdb->query('ROLLBACK');
                        return new WP_Error('insert_failed',$wpdb->last_error);
                    }
                    break;
                }
            }
        }
    }

    $position = count($final_ids) + 1;

    foreach($top_voting_candidates as $artist){
        if($insert_row($artist,'top_voting',$position,[]) === false){
            $wpdb->query('ROLLBACK');
            return new WP_Error('insert_failed',$wpdb->last_error);
        }
        $position++;
    }

    foreach($remaining as $artist){
        if($insert_row($artist,'participant',$position,[]) === false){
            $wpdb->query('ROLLBACK');
            return new WP_Error('insert_failed',$wpdb->last_error);
        }
        $position++;
    }

    $wpdb->query('COMMIT');

    if(function_exists('scp_log_event')){
        scp_log_event(
            'info',
            'contest_results',
            'Ergebnis-Snapshot erstellt',
            [
                'contest_id' => $contest_id,
                'rows'       => $inserted,
                'force'      => (bool)$force
            ]
        );
    }

    return [
        'contest_id'  => $contest_id,
        'rows'        => $inserted,
        'snapshot_at' => $snapshot_at
    ];
}


function scp_ensure_contest_results_snapshot($contest_id){

    if(scp_get_contest_result_count($contest_id) > 0){
        return true;
    }

    return scp_build_contest_results_snapshot($contest_id,false);
}

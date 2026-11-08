<?php

if (!defined('ABSPATH')) exit;

function scp_run_scheduler(){

    global $wpdb;

    if(function_exists('scp_fix_legacy_contest_statuses')){
        scp_fix_legacy_contest_statuses();
    }

    $contests = $wpdb->get_results("
        SELECT *
        FROM {$wpdb->prefix}scp_contests
        WHERE status IN ('draft','active','archived')
        ORDER BY id ASC
    ");

    if(empty($contests)){
        return;
    }

    foreach($contests as $contest){

        $contest_id = intval($contest->id);

        if(function_exists('scp_get_contest_phase')){
            $phase = scp_get_contest_phase($contest_id);
        }else{
            $phase = scp_get_current_phase();
        }

        $stored_phase = get_option('scp_current_phase_'.$contest_id);

        if($phase !== $stored_phase){
            update_option('scp_current_phase_'.$contest_id, $phase);
        }

        /*
         * Voting Mail bleibt bewusst manuell.
         */

        if($phase === 'final'){

            $final_processed = get_option('scp_final_processed_'.$contest_id);

            if(!$final_processed){
                scp_set_finalists($contest_id);
                scp_notify_finalists($contest_id);
                update_option('scp_final_processed_'.$contest_id, current_time('mysql'));
            }
        }

        if($phase === 'winner'){

            $winner_processed = get_option('scp_winner_processed_'.$contest_id);

            if(!$winner_processed){
                scp_set_winner_status($contest_id);
                scp_notify_winner($contest_id);
                update_option('scp_winner_processed_'.$contest_id, current_time('mysql'));
            }
        }
    }
}

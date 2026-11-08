<?php

if (!defined('ABSPATH')) exit;

function scp_results_admin_page(){

    if(!current_user_can('manage_options')){
        return;
    }

    $contest_id = function_exists('scp_get_admin_selected_contest_id')
        ? scp_get_admin_selected_contest_id()
        : 0;

    echo "<div class='wrap'>";
    echo "<h1>Contest Ergebnisarchiv</h1>";

    if(function_exists('scp_admin_contest_selector')){
        scp_admin_contest_selector('songcontest-results',$contest_id);
    }

    if($contest_id <= 0){
        echo "<p>Bitte Contest auswählen.</p></div>";
        return;
    }

    $contest = scp_get_contest($contest_id);

    if(!$contest){
        echo "<p>Contest nicht gefunden.</p></div>";
        return;
    }

    $count = scp_get_contest_result_count($contest_id);
    $snapshot = scp_get_contest_result_snapshot_date($contest_id);

    echo "<p><strong>Contest:</strong> ".esc_html($contest->name)." (#".intval($contest_id).")</p>";
    echo "<p><strong>Snapshot:</strong> ".($count > 0 ? intval($count)." Teilnehmer · ".esc_html($snapshot) : "noch nicht erstellt")."</p>";

    if($count > 0){
        echo "<hr>";
        echo do_shortcode('[songcontest_results contest="'.intval($contest_id).'"]');
    }else{
        echo "<p>Für diesen Contest wurde noch kein Ergebnis-Snapshot angelegt. Erstelle ihn in der Contestverwaltung.</p>";
    }

    echo "</div>";
}

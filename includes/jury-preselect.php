<?php

/* =========================
   ZENTRALES LIMIT
========================= */

function scp_get_voting_limit(){
    return intval(get_option('scp_voting_limit',30));
}


/* =========================
   TOP X BANDS FÜR VOTING
========================= */

function scp_get_jury_top_bands($limit = null, $contest_id = 0){
    global $wpdb;
    $contest_id = intval($contest_id);
    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1;
    }
    if(!$limit){
        $limit = function_exists('scp_get_max_voting_participants') ? scp_get_max_voting_participants($contest_id) : scp_get_voting_limit();
    }
    $table = $wpdb->prefix.'scp_jury_preselect';
    $artists = $wpdb->prefix.'scp_artists';
    return $wpdb->get_results($wpdb->prepare("
        SELECT a.*, COUNT(p.id) votes
        FROM $artists a
        LEFT JOIN $table p ON a.id=p.artist_id AND p.contest_id=%d AND p.selected=1
        WHERE a.contest_id=%d AND a.status IN ('approved','top20','finale','winner')
        GROUP BY a.id
        ORDER BY votes DESC, a.name ASC
        LIMIT %d
    ", $contest_id, $contest_id, intval($limit)));
}


/* =========================
   JURY TOGGLE PICK
========================= */

/* Neu Toggle*/
function scp_toggle_jury_pick(){
    if(!is_user_logged_in()){
        wp_send_json_error("Nicht eingeloggt");
    }
    global $wpdb;
    $user = get_current_user_id();
    $artist = intval($_POST['artist']);
    $table = $wpdb->prefix.'scp_jury_preselect';
    $artists = $wpdb->prefix.'scp_artists';
    $contest_id = intval($wpdb->get_var($wpdb->prepare("SELECT contest_id FROM $artists WHERE id=%d", $artist)));
    if($contest_id <= 0){ wp_send_json_error("Ungültiger Teilnehmer"); }

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE contest_id=%d AND jury_user=%d AND artist_id=%d",
        $contest_id, $user, $artist
    ));
    if($exists){
        $wpdb->delete($table, ['contest_id'=>$contest_id, 'jury_user'=>$user, 'artist_id'=>$artist]);
        wp_send_json_success("removed");
    }

    $count = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE contest_id=%d AND jury_user=%d AND selected=1",
        $contest_id, $user
    )));
    $max_picks = function_exists('scp_get_max_voting_participants') ? scp_get_max_voting_participants($contest_id) : 30;
    if($count >= $max_picks){ wp_send_json_error("Limit erreicht"); }

    $wpdb->insert($table, [
        'contest_id'=>$contest_id,
        'artist_id'=>$artist,
        'jury_user'=>$user,
        'selected'=>1,
        'vote_date'=>current_time('mysql')
    ]);
    wp_send_json_success("added");
}

add_action("wp_ajax_scp_toggle_jury_pick","scp_toggle_jury_pick");

/* =========================
   ADMIN TOGGLE
========================= */

function scp_admin_toggle_pick(){
    if(!current_user_can('manage_options')){
        wp_send_json_error("no_permission");
    }
    global $wpdb;
    $artist = intval($_POST['artist']);
    $table_preselect = $wpdb->prefix.'scp_jury_preselect';
    $table_artists = $wpdb->prefix.'scp_artists';
    $contest_id = intval($wpdb->get_var($wpdb->prepare("SELECT contest_id FROM $table_artists WHERE id=%d", $artist)));
    if($contest_id <= 0){ wp_send_json_error("invalid_artist"); }

    $exists = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_preselect WHERE contest_id=%d AND artist_id=%d AND jury_user=0 AND selected=1",
        $contest_id, $artist
    )));
    if($exists){
        $wpdb->delete($table_preselect, ['contest_id'=>$contest_id, 'artist_id'=>$artist, 'jury_user'=>0]);
        wp_send_json_success(['status'=>'removed']);
    }

    $wpdb->insert($table_preselect, [
        'contest_id'=>$contest_id,
        'artist_id'=>$artist,
        'jury_user'=>0,
        'selected'=>1,
        'vote_date'=>current_time('mysql')
    ]);
    wp_send_json_success(['status'=>'added']);
}

add_action(
    "wp_ajax_scp_admin_toggle_pick",
    "scp_admin_toggle_pick"
);
/*
function scp_admin_toggle_pick(){

    if(!current_user_can('manage_options')){
        wp_send_json_error("no_permission");
    }

    global $wpdb;

    $artist = intval($_POST['artist']);
    $table = $wpdb->prefix.'scp_jury_preselect';

    $exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM $table
        WHERE artist_id=%d
        AND selected=1
    ",$artist));

    if($exists){

        $wpdb->delete($table,[
            'artist_id'=>$artist
        ]);

        wp_send_json_success("removed");

    }else{

        $wpdb->insert($table,[
            'artist_id'=>$artist,
            'jury_user'=>0,
            'selected'=>1,
            'vote_date'=>current_time('mysql')
        ]);

        wp_send_json_success("added");
    }
}

add_action("wp_ajax_scp_admin_toggle_pick","scp_admin_toggle_pick");
*/
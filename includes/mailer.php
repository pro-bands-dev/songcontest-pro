<?php

function scp_send_mail($email,$subject,$message){

    $headers = ['Content-Type: text/html; charset=UTF-8'];

    wp_mail($email,$subject,$message,$headers);
}


/* Bewerbung */

function scp_mail_application_received($email,$band){

    $template = get_option('scp_mail_application');

    $message = str_replace(
        ['{band}','{site}'],
        [$band,get_bloginfo('name')],
        $template
    );

    scp_send_mail($email,"Song Contest Bewerbung",$message);
}


/* Voting Start */

function scp_mail_voting_start($email,$band,$band_id){

    $template = get_option('scp_mail_voting');

    global $wpdb;
    $contest_id = intval($wpdb->get_var($wpdb->prepare("SELECT contest_id FROM {$wpdb->prefix}scp_artists WHERE id=%d", $band_id)));
    $contest = function_exists('scp_get_contest') ? scp_get_contest($contest_id) : null;
    $vote_link = function_exists('scp_get_band_public_url')
        ? scp_get_band_public_url($band_id, $contest_id, $band_id)
        : add_query_arg(['band'=>$band_id,'ref'=>$band_id], site_url('/band/'));

    $top20_start = ($contest && !empty($contest->top20_start)) ? wp_date('d.m.Y', strtotime($contest->top20_start)) : '';
    $top20_end   = ($contest && !empty($contest->top20_end)) ? wp_date('d.m.Y', strtotime($contest->top20_end)) : '';
    $contest_url = function_exists('scp_get_contest_public_base_url') ? scp_get_contest_public_base_url($contest_id) : site_url('/');

    // Replace the known legacy 2026 template automatically. Custom admin
    // templates remain untouched and can use the dynamic placeholders below.
    if(empty($template) || strpos($template, '23.05.2026') !== false || strpos($template, 'contest.pro-bands.de') !== false){
        $template = "Hallo {band},\n\ndas Voting für den Song Contest hat begonnen.\n\nHier ist euer persönlicher Voting-Link: {vote_link}\n\nVoting-Zeitraum: {top20_start} bis {top20_end}.\nNatürlich kann auch direkt über {contest_url} abgestimmt werden.\n\nTeilt euren Voting-Link mit euren Fans – zum Beispiel über Instagram, Facebook oder WhatsApp.\n\nViele Grüße\nSong Contest Team";
    }

    $message = str_replace(
        ['{band}','{site}','{vote_link}','{top20_start}','{top20_end}','{contest_url}'],
        [$band,get_bloginfo('name'),$vote_link,$top20_start,$top20_end,$contest_url],
        $template
    );

    scp_send_mail($email,"🎤 Voting gestartet",$message);
}


function scp_notify_voting_start($contest_id = 0){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1;
    }

    $bands = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM {$wpdb->prefix}scp_artists
            WHERE contest_id=%d
            AND status='top20'
            ",
            $contest_id
        )
    );

    if(empty($bands)){
        return 0;
    }

    $sent = 0;

    foreach($bands as $band){

        if(empty($band->email)){
            continue;
        }

        scp_mail_voting_start(
            $band->email,
            $band->name,
            $band->id
        );

        $sent++;
    }

    return $sent;
}


/* Finale */

function scp_mail_final($email,$band){

    $template = get_option('scp_mail_final');

    $message = str_replace(
        ['{band}','{site}'],
        [$band,get_bloginfo('name')],
        $template
    );

    scp_send_mail($email,"🎤 Du bist im Finale!",$message);
}


function scp_notify_finalists($contest_id = 0){

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1;
    }

    $bands = scp_get_top_bands(
        function_exists('scp_get_max_finalists') ? scp_get_max_finalists($contest_id) : 5,
        $contest_id
    );

    $sent = 0;

    foreach($bands as $band){

        if(empty($band->email)){
            continue;
        }

        scp_mail_final($band->email,$band->name);
        $sent++;
    }

    return $sent;
}


/* Gewinner */

function scp_mail_winner($email,$band){

    $template = get_option('scp_mail_winner');

    $message = str_replace(
        ['{band}','{site}'],
        [$band,get_bloginfo('name')],
        $template
    );

    scp_send_mail($email,"🏆 Gewinner Song Contest",$message);
}


function scp_notify_winner($contest_id = 0){

    $winner = scp_get_winner($contest_id);

    if($winner){
        scp_mail_winner($winner->email,$winner->name);
    }
}

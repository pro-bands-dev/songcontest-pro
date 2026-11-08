<?php

if (!defined('ABSPATH')) exit;

/* =========================
   REFERRAL TRACKING
========================= */

function scp_track_referral(){

    /* Prüfen ob ref vorhanden */
    if(!isset($_GET['ref'])) return;

    $ref = intval($_GET['ref']);

    if(!$ref) return;

    /* Optional: Nur gültige Band IDs tracken */
    global $wpdb;

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}scp_artists WHERE id = %d",
        $ref
    ));

    if(!$exists) return;

    /* Cookie Schutz (verhindert Spam Reloads) */
    if(isset($_COOKIE['scp_ref_'.$ref])) return;

    setcookie('scp_ref_'.$ref, 1, time()+3600, "/"); // 1 Stunde

    /* Klick zählen */

    $wpdb->query($wpdb->prepare("
        UPDATE {$wpdb->prefix}scp_artists
        SET referral_clicks = referral_clicks + 1
        WHERE id = %d
    ", $ref));

}

/* Hook */

add_action('init','scp_track_referral');
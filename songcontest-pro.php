<?php
/*
Plugin Name: Song Contest PRO
Description: Komplettes Song Contest System mit Bewerbungen, Voting, Jury Voting, Live Ranking und Gewinnerseite
Version: 3.0.0-dev7.11.1
Author: PRO-BANDS Tonstudio
Author URI: https://pro-bands.de/
*/


if (!defined('ABSPATH')) exit;

// PATHS
define("SCP_PATH", plugin_dir_path(__FILE__));
define("SCP_URL", plugin_dir_url(__FILE__));
define("SCP_VERSION", "3.0.0-dev7.11.1");
define("SCP_DB_VERSION", "3.0.0-dev7.8.16");

// INSTALL
require_once SCP_PATH."install.php";

// CORE MODULES
require_once SCP_PATH."includes/contest-management.php";
require_once SCP_PATH."includes/public-pages.php";
require_once SCP_PATH."includes/contest-phase.php";
require_once SCP_PATH."includes/contest-results.php";
require_once SCP_PATH."includes/mailer.php";
require_once SCP_PATH."includes/marketing-consent.php";
require_once SCP_PATH."includes/privacy-retention.php";
require_once SCP_PATH."includes/scheduler.php";
require_once SCP_PATH."includes/referral-tracking.php";
require_once SCP_PATH."includes/media.php";
require_once SCP_PATH."includes/jury-preselect.php";
require_once SCP_PATH."includes/jury-role.php";
require_once SCP_PATH."includes/voter-auth.php";
require_once SCP_PATH."includes/final-voting.php";

// ADMIN
require_once SCP_PATH."admin/dashboard.php";
require_once SCP_PATH."admin/contests.php";
require_once SCP_PATH."admin/contest-results-snapshot.php";
require_once SCP_PATH."admin/applications.php";
require_once SCP_PATH."admin/phases.php";
require_once SCP_PATH."admin/statistics.php";
require_once SCP_PATH."admin/emails.php";
require_once SCP_PATH."admin/jury-dashboard.php";
require_once SCP_PATH."admin/jury-preselect.php";
require_once SCP_PATH."admin/results.php";
require_once SCP_PATH."admin/final-voting.php";

// PUBLIC
require_once SCP_PATH."public/application-form.php";
require_once SCP_PATH."public/ajax.php";
require_once SCP_PATH."public/voting.php";
require_once SCP_PATH."public/jury-voting.php";
require_once SCP_PATH."public/jury-preselect.php";
require_once SCP_PATH."public/charts.php";
require_once SCP_PATH."public/countdown.php";
require_once SCP_PATH."public/contest-schedule.php";
require_once SCP_PATH."public/winner.php";
require_once SCP_PATH."public/band-page.php";
require_once SCP_PATH."public/band-grid.php";
require_once SCP_PATH."public/phases.php";
require_once SCP_PATH."public/phase-banner.php";
require_once SCP_PATH."public/phase-timeline.php";
require_once SCP_PATH."public/band-profile.php";
require_once SCP_PATH."public/results.php";
require_once SCP_PATH."public/final-voting.php";
require_once SCP_PATH."public/home-teaser.php";



// DB MIGRATION / VERSION CHECK
add_action('plugins_loaded','scp_maybe_upgrade_db');

function scp_maybe_upgrade_db(){

    $target = SCP_DB_VERSION;
    $current = get_option('scp_db_version','0');

    if($current !== $target){
        scp_install();
    }
}

// ACTIVATION
register_activation_hook(__FILE__,'scp_install');
register_activation_hook(__FILE__,'scp_schedule_cron');


/* =========================================================
   CONTEST SCHEDULER
   5-Minuten-Intervall statt jede Minute
========================================================= */

function scp_add_cron_interval($schedules){

    $schedules['scp_five_minutes'] = [
        'interval' => 300,
        'display'  => 'Alle 5 Minuten'
    ];

    return $schedules;
}

add_filter(
    'cron_schedules',
    'scp_add_cron_interval'
);


function scp_schedule_cron(){

    /*
     * Bei Aktivierung vorhandenen alten Cron entfernen,
     * damit kein 1-Minuten-Event parallel weiterläuft.
     */
    wp_clear_scheduled_hook(
        'scp_contest_scheduler'
    );

    wp_schedule_event(
        time() + 60,
        'scp_five_minutes',
        'scp_contest_scheduler'
    );
}


/*
 * Bestehende Installationen automatisch vom alten
 * Minuten-Intervall auf 5 Minuten umstellen.
 *
 * Die Prüfung ist sehr leichtgewichtig und verändert
 * den Cron nur, wenn wirklich eine Umstellung nötig ist.
 */
function scp_ensure_correct_cron_schedule(){

    $event = wp_get_scheduled_event(
        'scp_contest_scheduler'
    );

    if(!$event){

        wp_schedule_event(
            time() + 60,
            'scp_five_minutes',
            'scp_contest_scheduler'
        );

        return;
    }

    if(
        empty($event->schedule)
        ||
        $event->schedule !== 'scp_five_minutes'
    ){

        wp_clear_scheduled_hook(
            'scp_contest_scheduler'
        );

        wp_schedule_event(
            time() + 60,
            'scp_five_minutes',
            'scp_contest_scheduler'
        );
    }
}

add_action(
    'init',
    'scp_ensure_correct_cron_schedule'
);

add_action(
    'scp_contest_scheduler',
    'scp_run_scheduler'
);


register_deactivation_hook(
    __FILE__,
    'scp_clear_cron'
);

function scp_clear_cron(){

    wp_clear_scheduled_hook(
        'scp_contest_scheduler'
    );
}


/* =========================================================
   FRONTEND ASSETS
========================================================= */

function scp_assets(){

    /*
     * CSS absichtlich auf allen Frontend-Seiten laden.
     *
     * Divi 5 kann Shortcodes in Theme-Builder- und
     * Code-Modulen ausgeben, die nicht zuverlässig in
     * $post->post_content erkannt werden.
     *
     * Die CSS-Datei ist klein. Die teuren JavaScript-
     * Bibliotheken bleiben dagegen selektiv.
     */
    wp_enqueue_style(
        'scp-style',
        SCP_URL . 'assets/style.css',
        [],
        SCP_VERSION
    );

    /*
     * Festival-/Jury-Stylesheet (vollständiger alter Stand) nur dort,
     * wo Jury-Oberflächen im Frontend erscheinen.
     */
    $scp_req = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $scp_post = get_post();
    // Nicht mehr über den Pfad /jury… – festival.css enthält globale Regeln
    // (body, Divi-Sektionen), die z. B. die Jury-Vorstellung verbiegen würden.
    $scp_needs_festival = false;
    if(!$scp_needs_festival && $scp_post instanceof WP_Post){
        $scp_needs_festival = has_shortcode($scp_post->post_content, 'songcontest_jury_voting');
    }
    if($scp_needs_festival){
        wp_enqueue_style(
            'scp-festival',
            SCP_URL . 'assets/festival.css',
            ['scp-style'],
            SCP_VERSION
        );
    }


    $needs_contest_js  = false;
    $needs_fingerprint = false;
    $needs_wavesurfer  = false;


    /* -----------------------------------------------------
       Shortcodes im normalen WordPress-Seiteninhalt
    ----------------------------------------------------- */

    global $post;

    if($post instanceof WP_Post){

        $content = $post->post_content;

        $all_js_shortcodes = [
            'songcontest_voting',
            'songcontest_band_profile',
            'songcontest_charts',
            'songcontest_jury_voting',
            'songcontest_jury_preselect',
            'songcontest_winner',
            'songcontest_band_grid',
            'songcontest_results'
        ];

        foreach($all_js_shortcodes as $shortcode){

            if(has_shortcode($content,$shortcode)){
                $needs_contest_js = true;
                break;
            }
        }

        if(
            has_shortcode($content,'songcontest_voting')
            ||
            has_shortcode($content,'songcontest_band_profile')
        ){
            $needs_fingerprint = true;
        }

        if(
            has_shortcode($content,'songcontest_band_profile')
            ||
            has_shortcode($content,'songcontest_jury_voting')
        ){
            $needs_wavesurfer = true;
        }
    }


    /* -----------------------------------------------------
       Divi 5 / Theme-Builder Fallback über Request-Pfad
    ----------------------------------------------------- */

    $request_uri = isset($_SERVER['REQUEST_URI'])
        ? sanitize_text_field(
            wp_unslash($_SERVER['REQUEST_URI'])
        )
        : '';


    $contest_js_paths = [
        '/voting',
        '/song-contest-voting',
        '/band/',
        '/song-contest-archiv',
        '/ergebnisse',
        '/jury'
    ];

    foreach($contest_js_paths as $path){

        if(strpos($request_uri,$path) !== false){

            $needs_contest_js = true;
            break;
        }
    }


    /*
     * FingerprintJS nur dort laden, wo öffentlich
     * abgestimmt werden kann.
     */
    if(
        strpos($request_uri,'/voting') !== false
        ||
        strpos($request_uri,'/song-contest-voting') !== false
        ||
        strpos($request_uri,'/band/') !== false
    ){
        $needs_fingerprint = true;
    }


    /*
     * WaveSurfer nur auf Seiten mit Waveform-Player.
     */
    if(
        strpos($request_uri,'/band/') !== false
        ||
        strpos($request_uri,'/jury') !== false
    ){
        $needs_wavesurfer = true;
    }


    /*
     * Keine Contest-JavaScript-Funktionen benötigt.
     * CSS wurde oben bereits geladen.
     */
    if(!$needs_contest_js){
        return;
    }


    /* -----------------------------------------------------
       FingerprintJS
    ----------------------------------------------------- */

    if($needs_fingerprint){

        wp_enqueue_script(
            'fingerprintjs',
            'https://openfpcdn.io/fingerprintjs/v3/iife.min.js',
            [],
            null,
            true
        );
    }


    /* -----------------------------------------------------
       WaveSurfer
    ----------------------------------------------------- */

    if($needs_wavesurfer){

        wp_enqueue_script(
            'wavesurfer',
            'https://unpkg.com/wavesurfer.js',
            [],
            null,
            true
        );
    }


    /* -----------------------------------------------------
       SongContest JavaScript
    ----------------------------------------------------- */

    $dependencies = ['jquery'];

    if($needs_fingerprint){
        $dependencies[] = 'fingerprintjs';
    }

    if($needs_wavesurfer){
        $dependencies[] = 'wavesurfer';
    }

    wp_enqueue_script(
        'scp-script',
        SCP_URL . 'assets/script.js',
        $dependencies,
        SCP_VERSION,
        true
    );


    /* -----------------------------------------------------
       AJAX
    ----------------------------------------------------- */

    wp_localize_script(
        'scp-script',
        'scp_ajax',
        [
            'ajaxurl' => admin_url(
                'admin-ajax.php'
            ),
            'nonce' => wp_create_nonce(
                'scp_public_vote'
            ),
            'voter_logged_in' => function_exists('scp_voter_is_logged_in')
                && scp_voter_is_logged_in()
                ? 1
                : 0
        ]
    );
}

add_action(
    'wp_enqueue_scripts',
    'scp_assets'
);


// WP Mediathek laden
add_action('admin_enqueue_scripts', function(){
wp_enqueue_media();
});

// ADMIN MENU
add_action('admin_menu','scp_admin_menu');

function scp_admin_menu(){

add_menu_page(
'Song Contest',
'Song Contest',
'manage_options',
'songcontest-dashboard',
'scp_dashboard_page',
'dashicons-microphone',
6
);

add_submenu_page(
'songcontest-dashboard',
'Dashboard',
'Dashboard',
'manage_options',
'songcontest-dashboard',
'scp_dashboard_page'
);

add_submenu_page(
'songcontest-dashboard',
'Contestverwaltung',
'Contestverwaltung',
'manage_options',
'songcontest-contests',
'scp_contests_page'
);

add_submenu_page(
'songcontest-dashboard',
'Bewerbungen',
'Bewerbungen',
'manage_options',
'songcontest-applications',
'scp_applications_page'
);

add_submenu_page(
'songcontest-dashboard',
'Contest Phasen',
'Contest Phasen',
'manage_options',
'songcontest-phases',
'scp_phases_page'
);

add_submenu_page(
'songcontest-dashboard',
'Statistik',
'Statistik',
'manage_options',
'songcontest-statistics',
'scp_statistics_page'
);

add_submenu_page(
'songcontest-dashboard',
'Ergebnisarchiv',
'Ergebnisarchiv',
'manage_options',
'songcontest-results',
'scp_results_admin_page'
);

add_submenu_page(
'songcontest-dashboard',
'E-Mail Einstellungen',
'E-Mail Einstellungen',
'manage_options',
'songcontest-emails',
'scp_email_settings_page'
);


add_submenu_page(
'songcontest-dashboard',
'Final-Voting',
'Final-Voting',
'manage_options',
'songcontest-final-voting',
'scp_final_voting_admin_page'
);

add_submenu_page(
'songcontest-dashboard',
'Jury Dashboard',
'Jury Dashboard',
'manage_options',
'songcontest-jury',
'scp_jury_dashboard_page'
);
	
add_submenu_page(
'songcontest-dashboard',
'Jury Vorauswahl',
'Jury Vorauswahl',
'manage_options',
'songcontest-jury-preselect',
'scp_admin_jury_preselect_page'
);
}

// Band URL Rewrite
function scp_band_rewrite_rule(){
add_rewrite_rule(
'^band/([^/]+)/?$',
'index.php?scp_band=$matches[1]',
'top'
);
}

add_action('init','scp_band_rewrite_rule');

function scp_band_query_vars($vars){
$vars[] = 'scp_band';
return $vars;
}

add_filter('query_vars','scp_band_query_vars');

function scp_band_template($template){
$slug = get_query_var('scp_band');
if(!$slug) return $template;
return plugin_dir_path(__FILE__).'templates/band-page.php';
}

add_filter('template_include','scp_band_template');

// LIVE VOTE
add_action("wp_ajax_scp_get_votes","scp_get_votes");
add_action("wp_ajax_nopriv_scp_get_votes","scp_get_votes");

function scp_get_votes(){

    global $wpdb;

    $contest_id = function_exists('scp_get_current_contest_id')
        ? scp_get_current_contest_id()
        : 1;

    $phase = function_exists('scp_get_current_phase')
        ? scp_get_current_phase()
        : '';

    if($phase === 'final'){

        $final_start = function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_start', $contest_id) : get_option('scp_phase_final_start');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT artist_id, COUNT(*) votes
                FROM {$wpdb->prefix}scp_votes
                WHERE contest_id=%d
                AND vote_date >= %s
                GROUP BY artist_id
                ",
                $contest_id,
                $final_start
            )
        );

    }else{

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT artist_id, COUNT(*) votes
                FROM {$wpdb->prefix}scp_votes
                WHERE contest_id=%d
                GROUP BY artist_id
                ",
                $contest_id
            )
        );
    }

    $data = [];

    foreach($rows as $r){
        $data[$r->artist_id] = intval($r->votes);
    }

    wp_send_json($data);
}

add_action('admin_enqueue_scripts', function(){

    // Plugin-Styles auch im Song-Contest-Admin laden (Festival-Cockpit / Jury-UI).
    wp_enqueue_style(
        'scp-admin-style',
        plugin_dir_url(__FILE__) . 'assets/festival.css',
        [],
        SCP_VERSION
    );

    wp_enqueue_script(
        'scp-admin-js',
        plugin_dir_url(__FILE__) . 'assets/script.js',
        ['jquery'],
        SCP_VERSION,
        true
    );

    wp_localize_script('scp-admin-js','scp_ajax',[
        'ajaxurl' => admin_url('admin-ajax.php')
    ]);

});

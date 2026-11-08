<?php

if (!defined('ABSPATH')) exit;

/**
 * SongContest Pro 3.0 DB install / migration.
 *
 * Ziele:
 * - vollständige Tabellenstruktur für Neuinstallationen
 * - sichere Erweiterung bestehender Installationen
 * - contest_id für mehrere Contests
 * - eigene IP-Cache-Tabelle
 * - Indizes für Voting/Referral/Statistik
 */

function scp_install(){

    global $wpdb;

    require_once(ABSPATH.'wp-admin/includes/upgrade.php');

    $charset_collate = $wpdb->get_charset_collate();

    $contests  = $wpdb->prefix.'scp_contests';
    $artists   = $wpdb->prefix.'scp_artists';
    $votes     = $wpdb->prefix.'scp_votes';
    $jury      = $wpdb->prefix.'scp_jury_votes';
    $preselect = $wpdb->prefix.'scp_jury_preselect';
    $ip_cache  = $wpdb->prefix.'scp_ip_cache';
    $logs      = $wpdb->prefix.'scp_logs';
    $results   = $wpdb->prefix.'scp_contest_results';
    $voters    = $wpdb->prefix.'scp_voters';
    $sessions  = $wpdb->prefix.'scp_voter_sessions';
    $tokens    = $wpdb->prefix.'scp_voter_login_tokens';
    $final_sessions = $wpdb->prefix.'scp_final_sessions';
    $final_votes = $wpdb->prefix.'scp_final_votes';
    $final_events = $wpdb->prefix.'scp_final_events';
    $final_jury_scores = $wpdb->prefix.'scp_final_jury_scores';

    $sql_contests = "CREATE TABLE $contests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(200) NOT NULL,
        slug VARCHAR(200) NOT NULL,
        year INT DEFAULT NULL,
        status VARCHAR(30) DEFAULT 'draft',
        application_start DATETIME DEFAULT NULL,
        application_end DATETIME DEFAULT NULL,
        top20_start DATETIME DEFAULT NULL,
        top20_end DATETIME DEFAULT NULL,
        vaus_start DATETIME DEFAULT NULL,
        vaus_end DATETIME DEFAULT NULL,
        max_voting_participants INT DEFAULT 30,
        max_finalists INT DEFAULT 5,
        final_start DATETIME DEFAULT NULL,
        final_end DATETIME DEFAULT NULL,
        winner_date DATETIME DEFAULT NULL,
        archived_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY status (status),
        KEY contest_year (year)
    ) $charset_collate;";

    $sql_artists = "CREATE TABLE $artists (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contest_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
        name VARCHAR(200) NOT NULL,
        email VARCHAR(200) NOT NULL,
        title VARCHAR(200) DEFAULT NULL,
        slug VARCHAR(220) DEFAULT NULL,
        genre VARCHAR(120) DEFAULT NULL,
        length VARCHAR(50) DEFAULT NULL,
        band_info TEXT DEFAULT NULL,
        song_info TEXT DEFAULT NULL,
        contact VARCHAR(200) DEFAULT NULL,
        instagram TEXT DEFAULT NULL,
        facebook TEXT DEFAULT NULL,
        website TEXT DEFAULT NULL,
        image TEXT DEFAULT NULL,
        audio TEXT DEFAULT NULL,
        status VARCHAR(30) DEFAULT 'pending',
        referral_clicks BIGINT UNSIGNED DEFAULT 0,
        marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
        marketing_consent_at DATETIME DEFAULT NULL,
        marketing_consent_text TEXT DEFAULT NULL,
        marketing_optin_token CHAR(64) DEFAULT NULL,
        marketing_confirmed_at DATETIME DEFAULT NULL,
        marketing_revoked_at DATETIME DEFAULT NULL,
        contact_purged_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY contest_status (contest_id,status),
        KEY contest_created (contest_id,created_at),
        KEY slug (slug),
        KEY status (status)
    ) $charset_collate;";

    $sql_votes = "CREATE TABLE $votes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contest_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
        voter_id BIGINT UNSIGNED DEFAULT NULL,
        artist_id BIGINT UNSIGNED NOT NULL,
        ref_id BIGINT UNSIGNED DEFAULT NULL,
        ip VARCHAR(100) DEFAULT NULL,
        ip_prefix VARCHAR(100) DEFAULT NULL,
        prefix_votes INT DEFAULT 0,
        fingerprint VARCHAR(255) DEFAULT NULL,
        country VARCHAR(20) DEFAULT NULL,
        city VARCHAR(150) DEFAULT NULL,
        isp VARCHAR(255) DEFAULT NULL,
        is_vpn TINYINT DEFAULT 0,
        user_agent TEXT DEFAULT NULL,
        vote_day DATE DEFAULT NULL,
        vote_date DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY contest_voter_day (contest_id,voter_id,vote_day),
        KEY voter_id (voter_id),
        KEY contest_artist_date (contest_id,artist_id,vote_date),
        KEY contest_vote_day_ip (contest_id,vote_day,ip),
        KEY contest_vote_day_fingerprint (contest_id,vote_day,fingerprint),
        KEY contest_ref (contest_id,ref_id),
        KEY ip_lookup (ip),
        KEY vote_date (vote_date)
    ) $charset_collate;";

    $sql_jury = "CREATE TABLE $jury (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contest_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
        voter_id BIGINT UNSIGNED DEFAULT NULL,
        artist_id BIGINT UNSIGNED NOT NULL,
        jury_user BIGINT UNSIGNED NOT NULL,
        points INT NOT NULL,
        vote_date DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY contest_artist_jury (contest_id,artist_id,jury_user),
        KEY contest_artist (contest_id,artist_id),
        KEY contest_jury (contest_id,jury_user)
    ) $charset_collate;";

    $sql_preselect = "CREATE TABLE $preselect (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contest_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
        voter_id BIGINT UNSIGNED DEFAULT NULL,
        artist_id BIGINT UNSIGNED NOT NULL,
        jury_user BIGINT UNSIGNED NOT NULL,
        selected TINYINT DEFAULT 1,
        vote_date DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY contest_artist (contest_id,artist_id),
        KEY contest_jury (contest_id,jury_user),
        KEY selected (selected)
    ) $charset_collate;";

    $sql_ip_cache = "CREATE TABLE $ip_cache (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ip VARCHAR(100) NOT NULL,
        country VARCHAR(20) DEFAULT NULL,
        city VARCHAR(150) DEFAULT NULL,
        isp VARCHAR(255) DEFAULT NULL,
        is_vpn TINYINT DEFAULT 0,
        checked_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ip (ip),
        KEY checked_at (checked_at),
        KEY is_vpn (is_vpn)
    ) $charset_collate;";

    $sql_logs = "CREATE TABLE $logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contest_id BIGINT UNSIGNED DEFAULT NULL,
        level VARCHAR(30) DEFAULT 'info',
        source VARCHAR(100) DEFAULT NULL,
        message TEXT DEFAULT NULL,
        context LONGTEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY contest_level (contest_id,level),
        KEY source (source),
        KEY created_at (created_at)
    ) $charset_collate;";

    $sql_results = "CREATE TABLE $results (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contest_id BIGINT UNSIGNED NOT NULL,
        artist_id BIGINT UNSIGNED NOT NULL,
        result_group VARCHAR(30) NOT NULL,
        position INT DEFAULT NULL,
        artist_status VARCHAR(30) DEFAULT NULL,
        artist_name VARCHAR(200) NOT NULL,
        song_title VARCHAR(200) DEFAULT NULL,
        genre VARCHAR(120) DEFAULT NULL,
        image_url TEXT DEFAULT NULL,
        audio_url TEXT DEFAULT NULL,
        total_votes INT DEFAULT 0,
        final_votes INT DEFAULT 0,
        jury_points INT DEFAULT 0,
        public_score DECIMAL(10,2) DEFAULT 0,
        jury_score DECIMAL(10,2) DEFAULT 0,
        p70_score DECIMAL(10,2) DEFAULT 0,
        j30_score DECIMAL(10,2) DEFAULT 0,
        total_score DECIMAL(10,2) DEFAULT 0,
        snapshot_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY contest_artist (contest_id,artist_id),
        KEY contest_group (contest_id,result_group),
        KEY contest_position (contest_id,position),
        KEY artist_id (artist_id),
        KEY snapshot_at (snapshot_at)
    ) $charset_collate;";


    $sql_voters = "CREATE TABLE $voters (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(255) NOT NULL,
        email_hash CHAR(64) NOT NULL,
        verified_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY email_hash (email_hash),
        KEY verified_at (verified_at),
        KEY created_at (created_at)
    ) $charset_collate;";

    $sql_sessions = "CREATE TABLE $sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        voter_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY token_hash (token_hash),
        KEY voter_id (voter_id),
        KEY expires_at (expires_at)
    ) $charset_collate;";

    $sql_tokens = "CREATE TABLE $tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        voter_id BIGINT UNSIGNED NOT NULL,
        contest_id BIGINT UNSIGNED NOT NULL,
        artist_id BIGINT UNSIGNED NOT NULL,
        ref_id BIGINT UNSIGNED DEFAULT NULL,
        token_hash CHAR(64) NOT NULL,
        return_url TEXT DEFAULT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY token_hash (token_hash),
        KEY voter_id (voter_id),
        KEY expires_at (expires_at),
        KEY contest_artist (contest_id,artist_id)
    ) $charset_collate;";

    $sql_final_sessions = "CREATE TABLE $final_sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, contest_id BIGINT UNSIGNED NOT NULL, status VARCHAR(20) DEFAULT 'prepared', jury_status VARCHAR(20) DEFAULT 'closed', show_standings TINYINT DEFAULT 0, reveal_place INT DEFAULT 0, started_at DATETIME DEFAULT NULL, stopped_at DATETIME DEFAULT NULL, jury_started_at DATETIME DEFAULT NULL, jury_stopped_at DATETIME DEFAULT NULL, started_by BIGINT UNSIGNED DEFAULT NULL, stopped_by BIGINT UNSIGNED DEFAULT NULL, jury_started_by BIGINT UNSIGNED DEFAULT NULL, jury_stopped_by BIGINT UNSIGNED DEFAULT NULL, jury_snapshot LONGTEXT DEFAULT NULL, result_snapshot LONGTEXT DEFAULT NULL, is_test TINYINT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY contest_status (contest_id,status)
    ) $charset_collate;";
    $sql_final_votes = "CREATE TABLE $final_votes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, session_id BIGINT UNSIGNED NOT NULL, contest_id BIGINT UNSIGNED NOT NULL, artist_id BIGINT UNSIGNED NOT NULL, email VARCHAR(255) NOT NULL, email_hash CHAR(64) NOT NULL, ip VARCHAR(100) DEFAULT NULL, fingerprint VARCHAR(255) DEFAULT NULL, user_agent TEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), UNIQUE KEY session_email (session_id,email_hash), KEY session_artist (session_id,artist_id), KEY contest_artist (contest_id,artist_id)
    ) $charset_collate;";
    $sql_final_events = "CREATE TABLE $final_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, session_id BIGINT UNSIGNED NOT NULL, event_type VARCHAR(50) NOT NULL, event_data LONGTEXT DEFAULT NULL, user_id BIGINT UNSIGNED DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY session_event (session_id,event_type), KEY created_at (created_at)
    ) $charset_collate;";
    $sql_final_jury_scores = "CREATE TABLE $final_jury_scores (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, session_id BIGINT UNSIGNED NOT NULL, contest_id BIGINT UNSIGNED NOT NULL, artist_id BIGINT UNSIGNED NOT NULL, jury_user BIGINT UNSIGNED NOT NULL, song_arrangement TINYINT UNSIGNED NOT NULL, musical TINYINT UNSIGNED NOT NULL, vocals TINYINT UNSIGNED NOT NULL, performance TINYINT UNSIGNED NOT NULL, overall TINYINT UNSIGNED NOT NULL, weighted_score DECIMAL(6,3) NOT NULL DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id), UNIQUE KEY session_artist_jury (session_id,artist_id,jury_user), KEY session_jury (session_id,jury_user), KEY session_artist (session_id,artist_id)
    ) $charset_collate;";

    dbDelta($sql_contests);
    dbDelta($sql_artists);
    dbDelta($sql_votes);
    dbDelta($sql_jury);
    dbDelta($sql_preselect);
    dbDelta($sql_ip_cache);
    dbDelta($sql_logs);
    dbDelta($sql_results);
    dbDelta($sql_voters);
    dbDelta($sql_sessions);
    dbDelta($sql_tokens);
    dbDelta($sql_final_sessions);
    dbDelta($sql_final_votes);
    dbDelta($sql_final_events);
    dbDelta($sql_final_jury_scores);

    // dev7.7.1: Existing final session tables from dev7.6.x need the new
    // jury workflow columns explicitly. dbDelta is not reliable enough for
    // adding several columns to an already existing custom table on update.
    $jury_columns = [
        'jury_status'     => "VARCHAR(20) DEFAULT 'closed' AFTER status",
        'jury_started_at' => "DATETIME DEFAULT NULL AFTER stopped_at",
        'jury_stopped_at' => "DATETIME DEFAULT NULL AFTER jury_started_at",
        'jury_started_by' => "BIGINT UNSIGNED DEFAULT NULL AFTER stopped_by",
        'jury_stopped_by' => "BIGINT UNSIGNED DEFAULT NULL AFTER jury_started_by",
        'jury_snapshot'   => "LONGTEXT DEFAULT NULL AFTER jury_stopped_by",
    ];
    foreach ($jury_columns as $column => $definition) {
        $exists = $wpdb->get_var("SHOW COLUMNS FROM $final_sessions LIKE '$column'");
        if (!$exists) {
            $wpdb->query("ALTER TABLE $final_sessions ADD COLUMN $column $definition");
        }
    }

    // dev7.6.2: Existing final session tables from dev7.6 may not receive
    // the test marker reliably via dbDelta alone. Ensure the column exists.
    $is_test_col = $wpdb->get_var("SHOW COLUMNS FROM $final_sessions LIKE 'is_test'");
    if (!$is_test_col) {
        $wpdb->query("ALTER TABLE $final_sessions ADD COLUMN is_test TINYINT DEFAULT 0 AFTER result_snapshot");
    }

    scp_install_migrate_contests_schema();
    scp_install_migrate_marketing_consent();

    scp_install_default_contest();
    scp_migrate_existing_contest_data();
    if(!get_option('scp_ip_cache_seeded')){
        scp_seed_ip_cache_from_votes();
        update_option('scp_ip_cache_seeded', 1, false);
    }

    update_option('scp_db_version', defined('SCP_DB_VERSION') ? SCP_DB_VERSION : '3.0.0-dev7.8.16');
}


/**
 * Backwards compatibility: existing activation hook still calls this.
 * The table is now created inside scp_install().
 */
function scp_install_preselect(){
    return true;
}


function scp_install_column_exists($table, $column){

    global $wpdb;

    $found = $wpdb->get_var(
        $wpdb->prepare(
            "SHOW COLUMNS FROM $table LIKE %s",
            $column
        )
    );

    return !empty($found);
}


function scp_install_migrate_contests_schema(){

    global $wpdb;

    $table = $wpdb->prefix.'scp_contests';

    /*
     * Backward compatibility for early v3 dev builds:
     * Some builds used contest_year, the final schema uses year.
     */
    $has_year = scp_install_column_exists($table, 'year');
    $has_contest_year = scp_install_column_exists($table, 'contest_year');

    if(!$has_year){
        $wpdb->query("ALTER TABLE $table ADD COLUMN year INT DEFAULT NULL AFTER slug");
    }

    if($has_contest_year){
        $wpdb->query("UPDATE $table SET year = contest_year WHERE (year IS NULL OR year = 0) AND contest_year IS NOT NULL");
    }

    if(!scp_install_column_exists($table, 'vaus_start')){
        $wpdb->query("ALTER TABLE $table ADD COLUMN vaus_start DATETIME DEFAULT NULL AFTER top20_end");
    }

    if(!scp_install_column_exists($table, 'vaus_end')){
        $wpdb->query("ALTER TABLE $table ADD COLUMN vaus_end DATETIME DEFAULT NULL AFTER vaus_start");
    }

    if(!scp_install_column_exists($table, 'max_voting_participants')){
        $wpdb->query("ALTER TABLE $table ADD COLUMN max_voting_participants INT DEFAULT 30 AFTER vaus_end");
    }

    if(!scp_install_column_exists($table, 'max_finalists')){
        $wpdb->query("ALTER TABLE $table ADD COLUMN max_finalists INT DEFAULT 5 AFTER max_voting_participants");
    }

    $wpdb->query("
        UPDATE $table
        SET status='archived'
        WHERE status='archieved'
    ");
}



/**
 * dev7.8.13: Einwilligung für Infos/Angebote per E-Mail (Double-Opt-In).
 * Bestehende Bewerbungen bleiben unverändert (marketing_consent = 0 = keine Einwilligung).
 * dbDelta legt die Spalten in der Regel schon an; hier die Absicherung per ALTER TABLE.
 */
function scp_install_migrate_marketing_consent(){

    global $wpdb;

    $table = $wpdb->prefix.'scp_artists';

    $columns = [
        'marketing_consent'      => "TINYINT(1) NOT NULL DEFAULT 0",
        'marketing_consent_at'   => "DATETIME DEFAULT NULL",
        'marketing_consent_text' => "TEXT DEFAULT NULL",
        'marketing_optin_token'  => "CHAR(64) DEFAULT NULL",
        'marketing_confirmed_at' => "DATETIME DEFAULT NULL",
        'marketing_revoked_at'   => "DATETIME DEFAULT NULL",
        // dev7.8.16: Zeitpunkt der Datenschutz-Bereinigung (Kontaktdaten geleert)
        'contact_purged_at'      => "DATETIME DEFAULT NULL",
    ];

    foreach($columns as $column => $definition){
        if(!scp_install_column_exists($table, $column)){
            $wpdb->query("ALTER TABLE $table ADD COLUMN $column $definition");
        }
    }
}


function scp_install_default_contest(){

    global $wpdb;

    $table = $wpdb->prefix.'scp_contests';

    $existing = $wpdb->get_var("SELECT COUNT(*) FROM $table");

    if($existing){
        if(!get_option('scp_current_contest_id')){
            $first = $wpdb->get_var("SELECT id FROM $table ORDER BY id ASC LIMIT 1");
            update_option('scp_current_contest_id', intval($first));
        }
        return;
    }

    $year = date('Y');

    $legacy_name = 'Song Contest '.$year;

    $wpdb->insert(
        $table,
        [
            'name'              => $legacy_name,
            'slug'              => 'song-contest-'.$year,
            'year'              => intval($year),
            'status'            => 'active',
            'application_start' => get_option('scp_phase_application_start') ?: null,
            'application_end'   => get_option('scp_phase_application_end') ?: null,
            'top20_start'       => get_option('scp_phase_top20_start') ?: null,
            'top20_end'         => get_option('scp_phase_top20_end') ?: null,
            'vaus_start'        => get_option('scp_phase_vaus_start') ?: null,
            'vaus_end'          => get_option('scp_phase_vaus_end') ?: null,
            'max_voting_participants' => intval(get_option('scp_voting_limit', 30)),
            'max_finalists'           => 5,
            'final_start'       => get_option('scp_phase_final_start') ?: null,
            'final_end'         => get_option('scp_phase_final_end') ?: null,
            'winner_date'       => get_option('scp_phase_winner_date') ?: null,
            'created_at'        => current_time('mysql')
        ]
    );

    $contest_id = intval($wpdb->insert_id);

    if($contest_id){
        update_option('scp_current_contest_id', $contest_id);
    }
}


function scp_migrate_existing_contest_data(){

    global $wpdb;

    $contest_id = intval(get_option('scp_current_contest_id', 1));
    if($contest_id <= 0){
        $contest_id = 1;
    }

    $tables = [
        $wpdb->prefix.'scp_artists',
        $wpdb->prefix.'scp_votes',
        $wpdb->prefix.'scp_jury_votes',
        $wpdb->prefix.'scp_jury_preselect'
    ];

    foreach($tables as $table){
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET contest_id=%d WHERE contest_id IS NULL OR contest_id=0",
                $contest_id
            )
        );
    }

    // vote_day für ältere Votes nachpflegen
    $wpdb->query("
        UPDATE {$wpdb->prefix}scp_votes
        SET vote_day = DATE(vote_date)
        WHERE vote_day IS NULL
        AND vote_date IS NOT NULL
    ");
}


function scp_seed_ip_cache_from_votes(){

    global $wpdb;

    $ip_cache = $wpdb->prefix.'scp_ip_cache';
    $votes    = $wpdb->prefix.'scp_votes';

    $rows = $wpdb->get_results("
        SELECT
            ip,
            MAX(country) AS country,
            MAX(city) AS city,
            MAX(isp) AS isp,
            MAX(is_vpn) AS is_vpn,
            MAX(vote_date) AS checked_at
        FROM $votes
        WHERE ip IS NOT NULL
        AND ip <> ''
        GROUP BY ip
    ");

    if(empty($rows)){
        return;
    }

    foreach($rows as $row){

        $wpdb->query(
            $wpdb->prepare(
                "
                INSERT INTO $ip_cache
                (ip,country,city,isp,is_vpn,checked_at,created_at)
                VALUES (%s,%s,%s,%s,%d,%s,%s)
                ON DUPLICATE KEY UPDATE
                    country=VALUES(country),
                    city=VALUES(city),
                    isp=VALUES(isp),
                    is_vpn=VALUES(is_vpn),
                    checked_at=VALUES(checked_at)
                ",
                $row->ip,
                $row->country,
                $row->city,
                $row->isp,
                intval($row->is_vpn),
                $row->checked_at,
                current_time('mysql')
            )
        );
    }
}

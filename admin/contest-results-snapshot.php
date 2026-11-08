<?php

if (!defined('ABSPATH')) {
    exit;
}


/* =========================================================
   CONTEST RESULT TABLE
========================================================= */

function scp_results_table_name(){

    global $wpdb;

    return $wpdb->prefix . 'scp_contest_results';
}


/* =========================================================
   RESULT TABLE ERSTELLEN / PRÜFEN
========================================================= */

function scp_install_contest_results_table(){

    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = scp_results_table_name();

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (

        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

        contest_id BIGINT UNSIGNED NOT NULL,

        artist_id BIGINT UNSIGNED NOT NULL,

        result_group VARCHAR(30) NOT NULL,

        position INT DEFAULT NULL,

        total_votes INT DEFAULT 0,

        final_votes INT DEFAULT 0,

        jury_points INT DEFAULT 0,

        public_score DECIMAL(8,2) DEFAULT 0,

        jury_score DECIMAL(8,2) DEFAULT 0,

        total_score DECIMAL(8,2) DEFAULT 0,

        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        UNIQUE KEY contest_artist (contest_id, artist_id),

        KEY contest_position (contest_id, position),

        KEY contest_group (contest_id, result_group)

    ) $charset_collate;";

    dbDelta($sql);
}


/*
 * Beim Öffnen des Adminbereiches sicherstellen,
 * dass die Tabelle vorhanden ist.
 */
add_action(
    'admin_init',
    'scp_install_contest_results_table'
);


/* =========================================================
   SNAPSHOT VORHANDEN?
========================================================= */

function scp_contest_result_snapshot_exists($contest_id){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        return false;
    }

    $table = scp_results_table_name();

    $count = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT COUNT(*)
            FROM $table
            WHERE contest_id=%d
            ",
            $contest_id
        )
    );

    return intval($count) > 0;
}


/* =========================================================
   GESAMTVOTES EINES TEILNEHMERS
========================================================= */

function scp_get_artist_total_votes_for_contest(
    $contest_id,
    $artist_id
){

    global $wpdb;

    return intval(
        $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM {$wpdb->prefix}scp_votes
                WHERE contest_id=%d
                AND artist_id=%d
                ",
                intval($contest_id),
                intval($artist_id)
            )
        )
    );
}


/* =========================================================
   FINAL-VOTES
========================================================= */

function scp_get_artist_final_votes_for_contest(
    $contest_id,
    $artist_id,
    $final_start
){

    global $wpdb;

    if(empty($final_start)){
        return 0;
    }

    return intval(
        $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM {$wpdb->prefix}scp_votes
                WHERE contest_id=%d
                AND artist_id=%d
                AND vote_date >= %s
                ",
                intval($contest_id),
                intval($artist_id),
                $final_start
            )
        )
    );
}


/* =========================================================
   JURY-PUNKTE
========================================================= */

function scp_get_artist_jury_points_for_contest(
    $contest_id,
    $artist_id
){

    global $wpdb;

    return intval(
        $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COALESCE(SUM(points),0)
                FROM {$wpdb->prefix}scp_jury_votes
                WHERE contest_id=%d
                AND artist_id=%d
                ",
                intval($contest_id),
                intval($artist_id)
            )
        )
    );
}


/* =========================================================
   FINAL-RANKING DES CONTESTS
========================================================= */

function scp_get_snapshot_final_ranking($contest_id){

    global $wpdb;

    $contest_id = intval($contest_id);

    if($contest_id <= 0){
        return [];
    }


    /*
     * Bestehende zentrale Ranking-Funktion benutzen,
     * wenn diese bereits contestbezogen arbeitet.
     */
    if(function_exists('scp_get_final_score_ranking')){

        $ranking = scp_get_final_score_ranking(
            0,
            $contest_id
        );

        if(!empty($ranking)){
            return $ranking;
        }
    }


    /*
     * Fallback-Berechnung.
     */

    $contest = function_exists('scp_get_contest')
        ? scp_get_contest($contest_id)
        : null;

    if(!$contest){
        return [];
    }

    $final_start = $contest->final_start ?? '';

    if(empty($final_start)){
        return [];
    }


    $bands = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT
                a.id,
                a.name,

                COUNT(v.id) AS public_votes,

                COALESCE(j.jury_points,0)
                AS jury_points

            FROM {$wpdb->prefix}scp_artists a

            LEFT JOIN {$wpdb->prefix}scp_votes v

                ON a.id = v.artist_id

                AND v.contest_id=%d

                AND v.vote_date >= %s


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

            AND a.status IN (
                'finale',
                'winner'
            )

            GROUP BY a.id
            ",
            $contest_id,
            $final_start,
            $contest_id,
            $contest_id
        )
    );


    if(empty($bands)){
        return [];
    }


    $max_public = 0;
    $max_jury   = 0;


    foreach($bands as $band){

        $public = intval(
            $band->public_votes
        );

        $jury = intval(
            $band->jury_points
        );


        if($public > $max_public){
            $max_public = $public;
        }


        if($jury > $max_jury){
            $max_jury = $jury;
        }
    }


    foreach($bands as $band){

        $public = intval(
            $band->public_votes
        );

        $jury = intval(
            $band->jury_points
        );


        $public_score =
            ($max_public > 0)
            ? ($public / $max_public) * 100
            : 0;


        $jury_score =
            ($max_jury > 0)
            ? ($jury / $max_jury) * 100
            : 0;


        $total_score =
            ($public_score * 0.70)
            +
            ($jury_score * 0.30);


        $band->public_score =
            round(
                $public_score,
                2
            );


        $band->jury_score =
            round(
                $jury_score,
                2
            );


        $band->total_score =
            round(
                $total_score,
                2
            );
    }


    usort(
        $bands,
        function($a,$b){

            if(
                floatval($a->total_score)
                ==
                floatval($b->total_score)
            ){

                return
                    intval($b->public_votes)
                    <=>
                    intval($a->public_votes);
            }

            return
                floatval($b->total_score)
                <=>
                floatval($a->total_score);
        }
    );


    return $bands;
}


/* =========================================================
   RESULT SNAPSHOT ERSTELLEN
========================================================= */

function scp_create_contest_result_snapshot(
    $contest_id,
    $force = false
){

    global $wpdb;

    $contest_id = intval($contest_id);


    if($contest_id <= 0){

        return new WP_Error(
            'invalid_contest',
            'Ungültige Contest-ID.'
        );
    }


    $contest = function_exists('scp_get_contest')
        ? scp_get_contest($contest_id)
        : $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$wpdb->prefix}scp_contests
                WHERE id=%d
                ",
                $contest_id
            )
        );


    if(!$contest){

        return new WP_Error(
            'contest_not_found',
            'Contest wurde nicht gefunden.'
        );
    }


    $results_table =
        scp_results_table_name();


    /*
     * Vorhandenen Snapshot schützen.
     */

    if(
        !$force
        &&
        scp_contest_result_snapshot_exists(
            $contest_id
        )
    ){

        return new WP_Error(
            'snapshot_exists',
            'Für diesen Contest existiert bereits ein Ergebnis-Snapshot.'
        );
    }


    /*
     * Bei Neuberechnung alte Daten löschen.
     */

    if($force){

        $wpdb->delete(
            $results_table,
            [
                'contest_id'
                =>
                $contest_id
            ],
            ['%d']
        );
    }


    /*
     * Alle Teilnehmer des Contests laden.
     */

    $artists = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM {$wpdb->prefix}scp_artists
            WHERE contest_id=%d
            AND status NOT IN (
                'pending',
                'rejected',
                'withdrawn'
            )
            ",
            $contest_id
        )
    );


    if(empty($artists)){

        return new WP_Error(
            'no_artists',
            'Für diesen Contest wurden keine Teilnehmer gefunden.'
        );
    }


    /*
     * Finale-Ranking.
     */

    $final_ranking =
        scp_get_snapshot_final_ranking(
            $contest_id
        );


    /*
     * Finalisten nach Position ablegen.
     */

    $final_positions = [];

    $position = 1;


    foreach($final_ranking as $row){

        $artist_id =
            intval($row->id);


        $final_positions[$artist_id] = [
            'position'
            =>
            $position,

            'public_votes'
            =>
            intval(
                $row->public_votes ?? 0
            ),

            'jury_points'
            =>
            intval(
                $row->jury_points ?? 0
            ),

            'public_score'
            =>
            floatval(
                $row->public_score ?? 0
            ),

            'jury_score'
            =>
            floatval(
                $row->jury_score ?? 0
            ),

            'total_score'
            =>
            floatval(
                $row->total_score ?? 0
            )
        ];


        $position++;
    }


    /*
     * Maximalzahl Voting-Teilnehmer.
     */

    $max_voting_participants =
        isset($contest->max_voting_participants)
        ?
        intval(
            $contest->max_voting_participants
        )
        :
        30;


    if($max_voting_participants <= 0){
        $max_voting_participants = 30;
    }


    /*
     * Finalistenanzahl.
     */

    $max_finalists =
        isset($contest->max_finalists)
        ?
        intval(
            $contest->max_finalists
        )
        :
        5;


    if($max_finalists <= 0){
        $max_finalists = 5;
    }


    /*
     * Nicht-Finalisten nach Gesamtvotes sortieren.
     */

    $non_finalists = [];


    foreach($artists as $artist){

        $artist_id =
            intval($artist->id);


        if(
            isset(
                $final_positions[$artist_id]
            )
        ){

            continue;
        }


        $total_votes =
            scp_get_artist_total_votes_for_contest(
                $contest_id,
                $artist_id
            );


        $non_finalists[] = [
            'artist'
            =>
            $artist,

            'total_votes'
            =>
            $total_votes
        ];
    }


    usort(
        $non_finalists,
        function($a,$b){

            if(
                intval($a['total_votes'])
                ==
                intval($b['total_votes'])
            ){

                return
                    strcasecmp(
                        $a['artist']->name,
                        $b['artist']->name
                    );
            }

            return
                intval($b['total_votes'])
                <=>
                intval($a['total_votes']);
        }
    );


    /*
     * Result-Snapshot erzeugen.
     */

    $snapshot_rows = [];


    /*
     * Winner + Finalisten.
     */

    foreach(
        $final_positions
        as
        $artist_id
        =>
        $final_data
    ){

        $total_votes =
            scp_get_artist_total_votes_for_contest(
                $contest_id,
                $artist_id
            );


        $result_group =
            intval(
                $final_data['position']
            )
            ===
            1
            ?
            'winner'
            :
            'finalist';


        $snapshot_rows[] = [

            'contest_id'
            =>
            $contest_id,

            'artist_id'
            =>
            $artist_id,

            'result_group'
            =>
            $result_group,

            'position'
            =>
            intval(
                $final_data['position']
            ),

            'total_votes'
            =>
            $total_votes,

            'final_votes'
            =>
            intval(
                $final_data['public_votes']
            ),

            'jury_points'
            =>
            intval(
                $final_data['jury_points']
            ),

            'public_score'
            =>
            floatval(
                $final_data['public_score']
            ),

            'jury_score'
            =>
            floatval(
                $final_data['jury_score']
            ),

            'total_score'
            =>
            floatval(
                $final_data['total_score']
            )
        ];
    }


    /*
     * Position nach Finalisten weiterzählen.
     */

    $next_position =
        count($final_positions)
        + 1;


    foreach(
        $non_finalists
        as
        $row
    ){

        $artist =
            $row['artist'];


        /*
         * Bis max_voting_participants
         * = Top Voting Gruppe.
         */

        if(
            $next_position
            <=
            $max_voting_participants
        ){

            $result_group =
                'top_voting';

        }else{

            $result_group =
                'participant';
        }


        $snapshot_rows[] = [

            'contest_id'
            =>
            $contest_id,

            'artist_id'
            =>
            intval(
                $artist->id
            ),

            'result_group'
            =>
            $result_group,

            'position'
            =>
            $next_position,

            'total_votes'
            =>
            intval(
                $row['total_votes']
            ),

            'final_votes'
            =>
            0,

            'jury_points'
            =>
            0,

            'public_score'
            =>
            0,

            'jury_score'
            =>
            0,

            'total_score'
            =>
            0
        ];


        $next_position++;
    }


    /*
     * Daten speichern.
     */

    foreach(
        $snapshot_rows
        as
        $row
    ){

        $insert = $wpdb->insert(
            $results_table,
            [
                'contest_id'
                =>
                intval(
                    $row['contest_id']
                ),

                'artist_id'
                =>
                intval(
                    $row['artist_id']
                ),

                'result_group'
                =>
                sanitize_key(
                    $row['result_group']
                ),

                'position'
                =>
                intval(
                    $row['position']
                ),

                'total_votes'
                =>
                intval(
                    $row['total_votes']
                ),

                'final_votes'
                =>
                intval(
                    $row['final_votes']
                ),

                'jury_points'
                =>
                intval(
                    $row['jury_points']
                ),

                'public_score'
                =>
                floatval(
                    $row['public_score']
                ),

                'jury_score'
                =>
                floatval(
                    $row['jury_score']
                ),

                'total_score'
                =>
                floatval(
                    $row['total_score']
                ),

                'created_at'
                =>
                current_time('mysql')
            ]
        );


        if($insert === false){

            return new WP_Error(
                'snapshot_insert_error',
                $wpdb->last_error
            );
        }
    }


    return count($snapshot_rows);
}


/* =========================================================
   ADMIN HANDLER:
   SNAPSHOT ERSTELLEN
========================================================= */

add_action(
    'admin_post_scp_create_result_snapshot',
    'scp_admin_create_result_snapshot'
);


function scp_admin_create_result_snapshot(){

    if(
        !current_user_can(
            'manage_options'
        )
    ){

        wp_die(
            'Keine Berechtigung.'
        );
    }


    $contest_id =
        intval(
            $_GET['contest_id'] ?? 0
        );


    check_admin_referer(
        'scp_create_snapshot_'
        .$contest_id
    );


    $result =
        scp_create_contest_result_snapshot(
            $contest_id,
            false
        );


    if(is_wp_error($result)){

        $message =
            $result->get_error_message();

        $type =
            'error';

    }else{

        $message =
            intval($result)
            .' Ergebnisdatensätze wurden erstellt.';

        $type =
            'success';
    }


    $url =
        admin_url(
            'admin.php?page=songcontest-contests'
            .'&edit_contest='
            .$contest_id
            .'&scp_result_message='
            .rawurlencode($message)
            .'&scp_result_type='
            .$type
        );


    wp_safe_redirect($url);

    exit;
}


/* =========================================================
   ADMIN HANDLER:
   SNAPSHOT NEU BERECHNEN
========================================================= */

add_action(
    'admin_post_scp_rebuild_result_snapshot',
    'scp_admin_rebuild_result_snapshot'
);


function scp_admin_rebuild_result_snapshot(){

    if(
        !current_user_can(
            'manage_options'
        )
    ){

        wp_die(
            'Keine Berechtigung.'
        );
    }


    $contest_id =
        intval(
            $_GET['contest_id'] ?? 0
        );


    check_admin_referer(
        'scp_rebuild_snapshot_'
        .$contest_id
    );


    $result =
        scp_create_contest_result_snapshot(
            $contest_id,
            true
        );


    if(is_wp_error($result)){

        $message =
            $result->get_error_message();

        $type =
            'error';

    }else{

        $message =
            intval($result)
            .' Ergebnisdatensätze wurden neu berechnet.';

        $type =
            'success';
    }


    $url =
        admin_url(
            'admin.php?page=songcontest-contests'
            .'&edit_contest='
            .$contest_id
            .'&scp_result_message='
            .rawurlencode($message)
            .'&scp_result_type='
            .$type
        );


    wp_safe_redirect($url);

    exit;
}


/* =========================================================
   ADMIN RESULT BOX
========================================================= */

function scp_render_contest_result_admin_box(
    $contest_id
){

    global $wpdb;

    $contest_id =
        intval(
            $contest_id
        );


    if($contest_id <= 0){
        return;
    }


    $exists =
        scp_contest_result_snapshot_exists(
            $contest_id
        );


    $count = 0;


    if($exists){

        $count =
            intval(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "
                        SELECT COUNT(*)
                        FROM "
                        .scp_results_table_name().
                        "
                        WHERE contest_id=%d
                        ",
                        $contest_id
                    )
                )
            );
    }


    $create_url =
        wp_nonce_url(
            admin_url(
                'admin-post.php'
                .'?action=scp_create_result_snapshot'
                .'&contest_id='
                .$contest_id
            ),
            'scp_create_snapshot_'
            .$contest_id
        );


    $rebuild_url =
        wp_nonce_url(
            admin_url(
                'admin-post.php'
                .'?action=scp_rebuild_result_snapshot'
                .'&contest_id='
                .$contest_id
            ),
            'scp_rebuild_snapshot_'
            .$contest_id
        );


    echo "
    <div
        style='
            margin-top:30px;
            padding:20px;
            background:#fff;
            border:1px solid #ccd0d4;
            max-width:850px;
        '
    >";


    echo "<h2>
        Ergebnis-Snapshot
    </h2>";


    if($exists){

        echo "
        <p>
            ✅ Snapshot vorhanden:
            <strong>"
            .$count.
            " Teilnehmer</strong>
        </p>";


        echo "
        <p>
            <a
                class='button button-secondary'
                href='"
                .esc_url($rebuild_url).
                "'
                onclick='
                    return confirm(
                        \"Bestehenden Snapshot wirklich neu berechnen?\"
                    );
                '
            >
                Ergebnis neu berechnen
            </a>
        </p>";

    }else{

        echo "
        <p>
            ⚠️ Für diesen Contest
            existiert noch kein
            Ergebnis-Snapshot.
        </p>";


        echo "
        <p>
            <a
                class='button button-primary'
                href='"
                .esc_url($create_url).
                "'
            >
                Ergebnis-Snapshot erstellen
            </a>
        </p>";
    }


    echo "
        <p class='description'>
            Der Snapshot speichert die endgültige
            Platzierung dieses Contests dauerhaft.
            Gewinner und Finalisten erhalten zusätzlich
            Final-Votes, Jurypunkte und Gesamtscore.
        </p>
    ";


    echo "</div>";
}


/* =========================================================
   ADMIN MESSAGE
========================================================= */

function scp_render_contest_result_admin_message(){

    if(
        empty(
            $_GET['scp_result_message']
        )
    ){
        return;
    }


    $message =
        sanitize_text_field(
            wp_unslash(
                $_GET[
                    'scp_result_message'
                ]
            )
        );


    $type =
        sanitize_key(
            $_GET[
                'scp_result_type'
            ]
            ??
            'success'
        );


    $class =
        ($type === 'error')
        ?
        'notice notice-error'
        :
        'notice notice-success';


    echo "
    <div class='"
        .esc_attr($class).
        " is-dismissible'
    >
        <p>"
        .esc_html($message).
        "</p>
    </div>";
}
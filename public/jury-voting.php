<?php

add_shortcode("songcontest_jury_voting","scp_jury_voting");

function scp_jury_voting($atts = []){

    $atts = shortcode_atts(['contest_id' => 0], $atts, 'songcontest_jury_voting');
    $contest_id = intval($atts['contest_id']);
    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1;
    }

    if(!is_user_logged_in()){
        return '
        <p>Nur Jury-Mitglieder können hier abstimmen.</p>
        <p><a href="/wp-login.php">Einloggen</a></p>';
    }

    if(!function_exists('scp_get_current_phase')){
        return "<p>Systemfehler: Contest-Phase konnte nicht ermittelt werden.</p>";
    }

    $phase = function_exists('scp_get_contest_phase') ? scp_get_contest_phase($contest_id) : scp_get_current_phase();

    if($phase !== "final"){
        return "<p>Jury-Voting ist derzeit nicht aktiv.</p>";
    }

    global $wpdb;

    $user_id = get_current_user_id();

    $table_artists = $wpdb->prefix.'scp_artists';
    $table_votes   = $wpdb->prefix.'scp_votes';
    $table_jury    = $wpdb->prefix.'scp_jury_votes';

    /*
     * Finale Bands laden
     * Status = final
     * Sortierung nach aktuellem Publikumsvoting
     */
	$final_start = (function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_start', $contest_id) : get_option('scp_phase_final_start'));
	
/*				$bands = $wpdb->get_results("
					SELECT
						a.*,
						COUNT(v.id) AS votes
					FROM $table_artists a
					LEFT JOIN $table_votes v
						ON a.id = v.artist_id
					WHERE a.status='finale'
					GROUP BY a.id
					ORDER BY votes DESC, a.name ASC
				");
				*/
	
	$bands = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT
            a.*,
            COUNT(v.id) AS votes

        FROM $table_artists a

        LEFT JOIN $table_votes v
            ON a.id = v.artist_id
            AND v.vote_date >= %s
            AND v.contest_id = %d

        WHERE a.status='finale'
          AND a.contest_id = %d

        GROUP BY a.id

        ORDER BY votes DESC, a.name ASC
        ",
        $final_start,
        $contest_id,
        $contest_id
    )
);
	
    if(empty($bands)){
        return "<p>Keine Finalisten gefunden.</p>";
    }

    $html = "<div class='jury-voting'>";

    $html .= "
    <h2>Jury Voting</h2>
    <h3>
        Vergib für jede Finalband zwischen 1 und 10 Punkte.
        Bereits vergebene Punkte können jederzeit geändert werden.
    </h3>";

    foreach($bands as $band){

        $current_points = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT points
                FROM $table_jury
                WHERE contest_id=%d
                AND artist_id=%d
                AND jury_user=%d
                ",
                $contest_id,
                $band->id,
                $user_id
            )
        );

        $html .= "<div class='jury-card'>";

        /* Bild */

        if(!empty($band->image)){

            $html .= "
            <div class='jury-image'>
                <img src='".esc_url(scp_image_url($band->image,'500'))."'
                     alt='".esc_attr($band->name)."'>
            </div>";
        }

        /* Bandname */

        $html .= "
        <h3>".esc_html($band->name)."</h3>

        <p class='song-title'>
            ".esc_html($band->title)."
        </p>";

        /* Genre */

        if(!empty($band->genre)){

            $html .= "
            <p class='jury-genre'>
                ".esc_html($band->genre)."
            </p>";
        }

        /* Audio */

        if(!empty($band->audio)){

            $html .= "
            <audio controls class='jury-audio'>
                <source src='".esc_url($band->audio)."' type='audio/mpeg'>
            </audio>";
        }

        /* Aktuelle Votes */

        $html .= "
        <p class='jury-public-votes'>
            🔥 ".intval($band->votes)." Community Votes
        </p>";

        /* Jury Punkte */

        $html .= "
        <select class='jury-score'
                data-band='".intval($band->id)."'
                data-contest='".intval($contest_id)."'>";

        $html .= "
            <option value=''>
                Punkte vergeben
            </option>";

        for($i=1;$i<=10;$i++){

            $selected = ($current_points == $i)
                ? "selected"
                : "";

            $html .= "
            <option value='".$i."' ".$selected.">
                ".$i." Punkte
            </option>";
        }

        $html .= "</select>";

        $html .= "</div>";
    }

    $html .= "</div>";

    return $html;
}

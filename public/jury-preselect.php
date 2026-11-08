<?php
/*

add_shortcode("songcontest_jury_preselect","scp_jury_preselect");

function scp_jury_preselect($atts = []){

    $atts = shortcode_atts(['contest_id' => 0], $atts, 'songcontest_jury_preselect');
    $contest_id = intval($atts['contest_id']);
    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1;
    }

    if(!is_user_logged_in()){
        return '<p>Nur Jury-Mitglieder können hier abstimmen.</p>
        <p><a href="/wp-login.php">Einloggen</a></p>';
    }

    if(!function_exists('scp_get_top_bands')){
        return "<p>Systemfehler.</p>";
    }

    $bands = scp_get_top_bands(100);

    global $wpdb;
    $user = get_current_user_id();
    $table = $wpdb->prefix.'scp_jury_preselect';

    $html = "<div class='jury-preselect'>";

    foreach($bands as $band){

        $selected = $wpdb->get_var($wpdb->prepare("
            SELECT selected FROM $table
            WHERE artist_id=%d AND jury_user=%d
        ", $band->id,$user));

        $active = ($selected) ? "active" : "";

        $html .= "<div class='jury-card'>";

        $html .= "<h3>".$band->name."</h3>";
		
        $html .= "<button class='jury-pick $active' data-id='".$band->id."'>
            ".($selected ? "✔ gewählt" : "Auswählen")."
        </button>";

        $html .= "</div>";
    }

    $html .= "</div>";

    return $html;
}

<?php  */

add_shortcode("songcontest_jury_preselect","scp_jury_preselect");

function scp_jury_preselect($atts = []){
    $atts = shortcode_atts(['contest_id' => 0], $atts, 'songcontest_jury_preselect');
    $contest_id = absint($atts['contest_id']);
    if ($contest_id <= 0) {
        $contest_id = function_exists('scp_get_frontend_contest_id')
            ? absint(scp_get_frontend_contest_id())
            : (function_exists('scp_get_current_contest_id') ? absint(scp_get_current_contest_id()) : 1);
    }

    if(!is_user_logged_in()){
        return '
        <p>Nur Jury-Mitglieder können hier abstimmen.</p>
        <p><a href="/wp-login.php">Einloggen</a></p>';
    }

    if(!function_exists('scp_get_top_bands')){
        return "<p>Systemfehler.</p>";
    }

    $bands = scp_get_top_bands(100, $contest_id);

    global $wpdb;
    $user = get_current_user_id();
    $table = $wpdb->prefix.'scp_jury_preselect';

    $html = "<div class='jury-preselect'>";

    foreach($bands as $band){

        $selected = $wpdb->get_var($wpdb->prepare("
            SELECT selected 
            FROM $table
            WHERE contest_id = %d
            AND artist_id = %d
            AND jury_user = %d
        ", $contest_id, $band->id, $user));

        $active = ($selected) ? "active" : "";

        $html .= "<div class='jury-card'>";

        // Bandname
        $html .= "<h3>" . esc_html($band->name) . "</h3>";

        // Songtitel
        if(!empty($band->title)){
            $html .= "<p class='jury-song-title'>
                        <strong>Song:</strong> " . esc_html($band->title) . "
                      </p>";
        }

        // Audio Player
        if(!empty($band->audio)){

            $html .= "
                <audio controls preload='none' class='jury-audio-player'>
                    <source src='" . esc_url($band->audio) . "' type='audio/mpeg'>
                    Dein Browser unterstützt keinen Audio-Player.
                </audio>
            ";
        } else {

            $html .= "
                <p class='no-audio'>
                    Kein Song verfügbar
                </p>
            ";
        }

        // Auswahlbutton
        $html .= "
            <button 
                class='jury-pick $active'
                data-id='" . esc_attr($band->id) . "'
                type='button'
            >
                " . ($selected ? "✔ gewählt" : "Auswählen") . "
            </button>
        ";

        $html .= "</div>";
    }

    $html .= "</div>";

    return $html;
}
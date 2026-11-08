<?php
add_shortcode("songcontest_band","scp_band");

function scp_band(){
if(!isset($_GET['band'])) return "";
global $wpdb;
$id = intval($_GET['band']);
$band = $wpdb->get_row(
"SELECT * FROM {$wpdb->prefix}scp_artists WHERE id=$id"
);
$votes = $wpdb->get_var(
"SELECT COUNT(*) FROM {$wpdb->prefix}scp_votes WHERE artist_id=$id"
);
$html = "<div class='band-profile'>";
$html .= "<h2>".$band->name."</h2>";
$html .= "<p><strong>".$band->title."</strong></p>";
$html .= "<p>".$band->band_info."</p>";

/* AUDIO PLAYER */
if($band->audio){
$html .= "
<div class='wave-player' data-audio='".$band->audio."'>
<div class='waveform'></div>
<button class='play-btn'>▶</button>
</div>
";
}

$html .= "<p><strong>Votes:</strong> ".$votes."</p>";
$html .= "</div>";
return $html;
}
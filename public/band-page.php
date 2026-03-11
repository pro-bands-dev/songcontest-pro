<?php

add_shortcode("songcontest_band","scp_band");

function scp_band(){

if(!isset($_GET['band'])) return "";

global $wpdb;

$id = intval($_GET['band']);

$row = $wpdb->get_row(
"SELECT * FROM {$wpdb->prefix}scp_artists WHERE id=$id"
);

$votes = $wpdb->get_var(
"SELECT COUNT(*) FROM {$wpdb->prefix}scp_votes WHERE artist_id=$id"
);

$html="<div class='band-profile'>";

$html.="<h2>".$row->name."</h2>";
$html.="<p><strong>Song:</strong> ".$row->title."</p>";
$html.="<p>".$row->band_info."</p>";
$html.="<p><strong>Votes:</strong> ".$votes."</p>";

$html.="</div>";

return $html;

}
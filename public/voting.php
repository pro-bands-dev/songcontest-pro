<?php

add_shortcode("songcontest_voting","scp_voting");

function scp_voting(){

global $wpdb;

$rows = $wpdb->get_results("
SELECT a.*,COUNT(v.id) votes
FROM {$wpdb->prefix}scp_artists a
LEFT JOIN {$wpdb->prefix}scp_votes v
ON a.id=v.artist_id
GROUP BY a.id
ORDER BY votes DESC
");

$html = "<div class='scp-voting'>";

foreach($rows as $r){

$link = site_url("/?band=".$r->id);

$html .= "<div class='artist'>";
$html .= "<h3>".$r->name."</h3>";
$html .= "<p>".$r->title."</p>";
$html .= "<p>".$r->band_info."</p>";
$html .= "<p>Votes: ".$r->votes."</p>";

$html .= "<button class='vote' data-id='".$r->id."'>Vote</button>";

$html .= "<p><a href='".$link."'>Bandprofil ansehen</a></p>";

$html .= "</div>";

}

$html .= "</div>";

return $html;

}
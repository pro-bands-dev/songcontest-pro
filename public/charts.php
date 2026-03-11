<?php

add_shortcode("songcontest_charts","scp_charts");

function scp_charts(){

global $wpdb;

$rows = $wpdb->get_results("
SELECT a.name,COUNT(v.id) votes
FROM {$wpdb->prefix}scp_artists a
LEFT JOIN {$wpdb->prefix}scp_votes v
ON a.id=v.artist_id
GROUP BY a.id
ORDER BY votes DESC
LIMIT 10
");

$html = "<ol class='scp-charts'>";

foreach($rows as $r){

$html .= "<li>".$r->name." (".$r->votes." Votes)</li>";

}

$html .= "</ol>";

return $html;

}
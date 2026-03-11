<?php

add_shortcode("songcontest_winner","scp_winner");

function scp_winner(){

global $wpdb;

$row = $wpdb->get_row("
SELECT a.name,a.title,COUNT(v.id) votes
FROM {$wpdb->prefix}scp_artists a
LEFT JOIN {$wpdb->prefix}scp_votes v
ON a.id=v.artist_id
GROUP BY a.id
ORDER BY votes DESC
LIMIT 1
");

if(!$row) return "Noch kein Gewinner";

return "<h2>Gewinner: ".$row->name."</h2>
<p>".$row->title." (".$row->votes." Votes)</p>";

}
<?php

add_shortcode("songcontest_jury_voting","scp_jury_voting");

function scp_jury_voting(){

if(!is_user_logged_in()){
return "Nur für Jury-Mitglieder";
}

global $wpdb;

$rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}scp_artists");

$html="";

foreach($rows as $r){

$html.="<div class='artist'>";
$html.="<h3>".$r->name."</h3>";
$html.="<p>".$r->title."</p>";
$html.="<button>Jury Vote</button>";
$html.="</div>";

}

return $html;

}
<?php

function scp_install(){

global $wpdb;

require_once(ABSPATH.'wp-admin/includes/upgrade.php');

$artists = $wpdb->prefix.'scp_artists';
$votes = $wpdb->prefix.'scp_votes';

$sql1 = "CREATE TABLE $artists(
id INT AUTO_INCREMENT,
name VARCHAR(200),
email VARCHAR(200),
title VARCHAR(200),
band_info TEXT,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY(id)
)";

$sql2 = "CREATE TABLE $votes(
id INT AUTO_INCREMENT,
artist_id INT,
ip VARCHAR(100),
vote_date DATETIME,
PRIMARY KEY(id)
)";

dbDelta($sql1);
dbDelta($sql2);

}
<?php
/*
Plugin Name: Song Contest PRO
Description: Song Contest System with applications, voting, jury voting, countdown and band pages.
Version: 1.0
Author: PRO-BANDS Tonstudio
*/

if (!defined('ABSPATH')) exit;

define("SCP_PATH", plugin_dir_path(__FILE__));
define("SCP_URL", plugin_dir_url(__FILE__));

require_once SCP_PATH."install.php";

require_once SCP_PATH."public/application-form.php";
require_once SCP_PATH."public/voting.php";
require_once SCP_PATH."public/jury-voting.php";
require_once SCP_PATH."public/charts.php";
require_once SCP_PATH."public/winner.php";
require_once SCP_PATH."public/phases.php";
require_once SCP_PATH."public/countdown.php";
require_once SCP_PATH."public/band-page.php";

register_activation_hook(__FILE__, "scp_install");


function scp_assets(){

wp_enqueue_style(
'scp-font',
'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap'
);

wp_enqueue_style(
'scp-style',
SCP_URL.'assets/style.css'
);

wp_enqueue_script(
'scp-script',
SCP_URL.'assets/script.js',
array('jquery'),
null,
true
);

wp_localize_script(
'scp-script',
'scp_ajax',
array('ajaxurl'=>admin_url('admin-ajax.php'))
);

}

add_action('wp_enqueue_scripts','scp_assets');


add_action("wp_ajax_scp_vote","scp_vote");
add_action("wp_ajax_nopriv_scp_vote","scp_vote");

function scp_vote(){

global $wpdb;

$artist = intval($_POST['artist']);
$ip = $_SERVER['REMOTE_ADDR'];

$table = $wpdb->prefix.'scp_votes';

$exists = $wpdb->get_var(
$wpdb->prepare(
"SELECT COUNT(*) FROM $table WHERE ip=%s AND DATE(vote_date)=CURDATE()",$ip
));

if($exists){
echo "Heute bereits abgestimmt";
wp_die();
}

$wpdb->insert(
$table,
[
'artist_id'=>$artist,
'ip'=>$ip,
'vote_date'=>current_time('mysql')
]
);

echo "Vote gespeichert";

wp_die();

}
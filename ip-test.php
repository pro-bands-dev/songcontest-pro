<?php

require_once('../../../wp-load.php'); // Pfad ggf. anpassen

$ip = $_SERVER['REMOTE_ADDR'];

$response = wp_remote_get("https://ipwho.is/".$ip);

if (is_wp_error($response)) {
    echo "<pre>";
    print_r($response);
    wp_die();
}

echo "<pre>";
echo "HTTP-Code: " . wp_remote_retrieve_response_code($response) . "\n\n";

echo "Headers:\n";
print_r(wp_remote_retrieve_headers($response));

echo "\n\nBody:\n";
echo wp_remote_retrieve_body($response);

wp_die();
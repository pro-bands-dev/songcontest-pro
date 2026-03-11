<?php

add_shortcode("songcontest_countdown","scp_countdown");

function scp_countdown(){

$end = get_option("scp_vote_end","2026-12-31 23:59:59");

$html = "<div id='contest-countdown' data-end='".$end."'></div>";

return $html;

}
<?php

add_shortcode("songcontest_phase","scp_phase");

function scp_phase(){

$phase = get_option("scp_contest_phase","bewerbung");

$labels = [
"bewerbung"=>"🎤 Bewerbungsphase läuft",
"top20"=>"🔥 Top 20 Voting läuft",
"finale"=>"🏆 Finale läuft",
"winner"=>"🥇 Gewinner wird bekannt gegeben"
];

return "<div class='contest-phase'>".$labels[$phase]."</div>";

}
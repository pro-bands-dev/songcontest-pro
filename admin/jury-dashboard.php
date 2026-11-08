<?php

function scp_jury_dashboard_page(){

if(!(function_exists('scp_user_is_jury') ? scp_user_is_jury() : current_user_can('edit_posts'))){
    return;
}

global $wpdb;

$contest_id = function_exists('scp_get_admin_selected_contest_id')
    ? scp_get_admin_selected_contest_id()
    : (function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1);

$phase = function_exists('scp_get_contest_phase')
    ? scp_get_contest_phase($contest_id)
    : scp_get_current_phase();

$user = get_current_user_id();

$table_artists = $wpdb->prefix.'scp_artists';
$table_votes   = $wpdb->prefix.'scp_votes';
$table_jury    = $wpdb->prefix.'scp_jury_votes';

$final_start = function_exists('scp_get_contest_phase_value')
    ? scp_get_contest_phase_value('final_start', $contest_id)
    : get_option('scp_phase_final_start');

if(empty($final_start)){
    $final_start = '1970-01-01 00:00:00';
}

$bands = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT a.*, COUNT(v.id) as votes
        FROM $table_artists a
        LEFT JOIN $table_votes v
            ON a.id = v.artist_id
            AND v.contest_id=%d
            AND v.vote_date >= %s
        WHERE a.contest_id=%d
        AND a.status IN ('finale','winner')
        GROUP BY a.id
        ORDER BY votes DESC, a.name ASC
        ",
        $contest_id,
        $final_start,
        $contest_id
    )
);

echo "<div class='wrap'>";
echo "<h1>Jury Dashboard</h1>";

if(function_exists('scp_admin_contest_selector')){
    scp_admin_contest_selector('songcontest-jury', $contest_id);
}

if($phase != "final"){
    echo "<div class='notice notice-warning'><p>Jury Voting ist aktuell nicht aktiv. Aktuelle Phase: ".esc_html($phase)."</p></div>";
}else{
    echo "<p style='color:green;font-weight:bold'>Jury Voting aktiv</p>";
}

echo "<h2>Finale Bands</h2>";

echo "<table class='widefat striped'>";
echo "<tr>
<th>Band</th>
<th>Song</th>
<th>Final Votes</th>
<th>Audio</th>
<th>Deine Punkte</th>
</tr>";

foreach($bands as $band){

$current = $wpdb->get_var(
    $wpdb->prepare(
        "
        SELECT points
        FROM $table_jury
        WHERE contest_id=%d
        AND artist_id=%d
        AND jury_user=%d
        ",
        $contest_id,
        $band->id,
        $user
    )
);

echo "<tr>";
echo "<td>";

if($band->image){
    echo "<img src='".esc_url($band->image)."' style='width:80px;'><br>";
}

echo esc_html($band->name);
echo "</td>";
echo "<td>".esc_html($band->title)."</td>";
echo "<td>".intval($band->votes)."</td>";
echo "<td>";

if($band->audio){
    echo "
    <div class='wave-player' data-audio='".esc_url($band->audio)."'>
    <div class='waveform'></div>
    <button class='play-btn'>▶</button>
    </div>";
}

echo "</td>";
echo "<td>";

$disabled = ($phase != "final") ? "disabled" : "";

echo "<select class='jury-score' $disabled data-band='".intval($band->id)."'>";
echo "<option value=''>--</option>";

for($i=1;$i<=10;$i++){
    $selected = ($current==$i) ? "selected" : "";
    echo "<option value='$i' $selected>$i</option>";
}

echo "</select>";
echo "</td>";
echo "</tr>";
}

echo "</table>";


/* JURY RANKING */
echo "<h2 style='margin-top:40px;'>Jury Ranking</h2>";

$jury_scores = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT j.artist_id, a.name, SUM(j.points) points
        FROM $table_jury j
        INNER JOIN $table_artists a
            ON a.id=j.artist_id
        WHERE j.contest_id=%d
        AND a.contest_id=%d
        GROUP BY j.artist_id
        ORDER BY points DESC
        ",
        $contest_id,
        $contest_id
    )
);

echo "<table class='widefat striped'>";
echo "<tr><th>Band</th><th>Jury Punkte</th></tr>";

foreach($jury_scores as $score){
    echo "<tr>";
    echo "<td>".esc_html($score->name)."</td>";
    echo "<td>".intval($score->points)."</td>";
    echo "</tr>";
}

echo "</table>";


/* GESAMTWERTUNG */
echo "<h2 style='margin-top:40px;'>Gesamtwertung (70% Publikum / 30% Jury)</h2>";
echo "<p>Publikums-Stimmen werden auf % gerechnet, wobei die meisten Stimmen = 100% sind.</p>";
echo "<p>Jury-Punkte werden auf % gerechnet, wobei die meisten Punkte = 100% sind.</p>";

$ranking = function_exists('scp_get_final_score_ranking')
    ? scp_get_final_score_ranking(0, $contest_id)
    : [];

echo "<table class='widefat striped'>";

echo "<tr>
<th>Platz</th>
<th>Band</th>
<th>Final Votes</th>
<th>Jury Punkte</th>
<th>Publikum (Score)</th>
<th>Jury (Score)</th>
<th>Publikum (70%)</th>
<th>Jury (30%)</th>
<th>Gesamt</th>
</tr>";

$place = 1;

foreach($ranking as $row){

    echo "<tr>";
    echo "<td>".$place."</td>";
    echo "<td>".esc_html($row->name)."</td>";
    echo "<td>".intval($row->public_votes)."</td>";
    echo "<td>".intval($row->jury_points)."</td>";
    echo "<td>".esc_html($row->public_score)."</td>";
    echo "<td>".esc_html($row->jury_score)."</td>";
    echo "<td>".esc_html($row->p70_score)."</td>";
    echo "<td>".esc_html($row->j30_score)."</td>";
    echo "<td><strong>".esc_html($row->total_score)."</strong></td>";
    echo "</tr>";

    $place++;
}

echo "</table>";

echo "</div>";

}

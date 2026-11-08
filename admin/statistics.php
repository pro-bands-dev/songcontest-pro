<?php

function scp_statistics_page(){

global $wpdb;

$contest_id = function_exists('scp_get_admin_selected_contest_id')
    ? scp_get_admin_selected_contest_id()
    : 1;

$rows = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT
            a.id,
            a.name,
            a.status,
            COUNT(v.id) votes
        FROM {$wpdb->prefix}scp_artists a
        LEFT JOIN {$wpdb->prefix}scp_votes v
            ON a.id=v.artist_id
            AND v.contest_id=%d
        WHERE a.contest_id=%d
        GROUP BY a.id
        ORDER BY votes DESC
        ",
        $contest_id,
        $contest_id
    )
);

echo "<div class='wrap'>";
echo "<h1>Voting Statistik</h1>";

if(function_exists('scp_admin_contest_selector')){
    scp_admin_contest_selector('songcontest-statistics', $contest_id);
}

echo "<table class='widefat striped'>";
echo "<tr>
<th>ID</th>
<th>Band</th>
<th>Status</th>
<th>Votes</th>
</tr>";

foreach($rows as $r){

echo "<tr>";
echo "<td>".intval($r->id)."</td>";
echo "<td>".esc_html($r->name)."</td>";
echo "<td>".esc_html($r->status)."</td>";
echo "<td>".intval($r->votes)."</td>";
echo "</tr>";
}

echo "</table>";
echo "</div>";
}

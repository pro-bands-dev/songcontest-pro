<?php

function scp_dashboard_page(){

global $wpdb;

$contest_id = function_exists('scp_get_admin_selected_contest_id')
    ? scp_get_admin_selected_contest_id()
    : 1;

$table_artists = $wpdb->prefix.'scp_artists';
$table_votes = $wpdb->prefix.'scp_votes';

/* =========================
   BASIS STATISTIK
========================= */

$applications_total = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_artists WHERE contest_id=%d",
        $contest_id
    )
);

$approved_total = $wpdb->get_var(
    $wpdb->prepare(
        "
        SELECT COUNT(*)
        FROM $table_artists
        WHERE contest_id=%d
        AND status IN ('approved','top20','finale','winner')
        ",
        $contest_id
    )
);

$votes_total = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_votes WHERE contest_id=%d",
        $contest_id
    )
);

$votes_today = $wpdb->get_var(
    $wpdb->prepare(
        "
        SELECT COUNT(*)
        FROM $table_votes
        WHERE contest_id=%d
        AND vote_day=%s
        ",
        $contest_id,
        current_time('Y-m-d')
    )
);


/* =========================
   KPI BERECHNUNG
========================= */

$total_clicks = $wpdb->get_var(
    $wpdb->prepare(
        "
        SELECT SUM(referral_clicks)
        FROM $table_artists
        WHERE contest_id=%d
        ",
        $contest_id
    )
);

$total_ref_votes = $wpdb->get_var(
    $wpdb->prepare(
        "
        SELECT COUNT(*)
        FROM $table_votes
        WHERE contest_id=%d
        AND ref_id IS NOT NULL
        AND ref_id > 0
        ",
        $contest_id
    )
);

$conversion_total = ($total_clicks > 0) 
    ? round(($total_ref_votes / $total_clicks) * 100,1) 
    : 0;


/* =========================
   TOP BANDS
========================= */

$top_bands = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT 
            a.id,
            a.name,
            a.title,
            a.status,
            a.image,
            a.audio,
            a.referral_clicks,
            COUNT(v.id) AS votes,
            SUM(CASE WHEN v.ref_id = a.id THEN 1 ELSE 0 END) AS referral_votes
        FROM $table_artists a
        LEFT JOIN $table_votes v
            ON a.id = v.artist_id
            AND v.contest_id=%d
        WHERE a.contest_id=%d
        AND a.status IN ('top20', 'finale', 'winner')
        GROUP BY a.id
        ORDER BY votes DESC
        LIMIT 30
        ",
        $contest_id,
        $contest_id
    )
);


echo "<div class='wrap'>";

echo "<h1>Song Contest Dashboard</h1>";

if(function_exists('scp_admin_contest_selector')){
    scp_admin_contest_selector('songcontest-dashboard', $contest_id);
}

/* =========================
   STATISTIK
========================= */

echo "<h2>Statistiken</h2>";

echo "<table class='widefat striped' style='max-width:600px'>";

echo "<tr><th>Bewerbungen gesamt</th><td>".intval($applications_total)."</td></tr>";
echo "<tr><th>Freigegebene Bands</th><td>".intval($approved_total)."</td></tr>";
echo "<tr><th>Votes gesamt</th><td>".intval($votes_total)."</td></tr>";
echo "<tr><th>Votes heute</th><td>".intval($votes_today)."</td></tr>";

echo "</table>";


/* =========================
   KPI BOXEN
========================= */

echo "<h2 style='margin-top:30px'>Performance Übersicht</h2>";

echo "<div style='display:flex;gap:20px;margin:20px 0;flex-wrap:wrap'>";

echo "<div style='background:#fff;padding:15px;border:1px solid #ddd'>
<b>Referral Klicks</b><br>".intval($total_clicks)."
</div>";

echo "<div style='background:#fff;padding:15px;border:1px solid #ddd'>
<b>Referral Votes</b><br>".intval($total_ref_votes)."
</div>";

echo "<div style='background:#fff;padding:15px;border:1px solid #ddd'>
<b>Ø Conversion</b><br>".esc_html($conversion_total)."%
</div>";

echo "</div>";


/* =========================
   TOP 30 BANDS
========================= */

echo "<h2 style='margin-top:40px'>Top 30 Bands</h2>";

echo "<table class='widefat striped'>";

echo "<tr>
<th>Bild</th>
<th>Band</th>
<th>Song</th>
<th>Status</th>
<th>Audio</th>
<th>Votes</th>
<th>Referral Klicks</th>
<th>Referral Votes</th>
<th>Conversion</th>
</tr>";

foreach($top_bands as $band){

echo "<tr>";

echo "<td>";
if($band->image){
echo "<img src='".esc_url($band->image)."' style='width:80px;height:auto;'>";
}
echo "</td>";

echo "<td>".esc_html($band->name)."</td>";
echo "<td>".esc_html($band->title)."</td>";
echo "<td>".esc_html($band->status)."</td>";

echo "<td>";
if($band->audio){
echo "<audio controls style='width:200px'>
<source src='".esc_url($band->audio)."'>
</audio>";
}
echo "</td>";

$votes = intval($band->votes);
$clicks = intval($band->referral_clicks);
$ref_votes = intval($band->referral_votes);

$conversion = ($clicks > 0)
    ? round(($ref_votes / $clicks) * 100,1)."%"
    : "-";

echo "<td>$votes</td>";
echo "<td>$clicks</td>";
echo "<td>$ref_votes</td>";
echo "<td>$conversion</td>";

echo "</tr>";
}

echo "</table>";


/* =========================
   TOP REFERRER
========================= */

$top_referrer = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT 
            a.name,
            a.referral_clicks,
            SUM(CASE WHEN v.ref_id = a.id THEN 1 ELSE 0 END) AS ref_votes
        FROM $table_artists a
        LEFT JOIN $table_votes v
            ON a.id = v.artist_id
            AND v.contest_id=%d
        WHERE a.contest_id=%d
        GROUP BY a.id
        ORDER BY a.referral_clicks DESC
        LIMIT 20
        ",
        $contest_id,
        $contest_id
    )
);

echo "<h2 style='margin-top:40px'>🔥 Top Referrer</h2>";

echo "<table class='widefat striped'>";
echo "<tr><th>Band</th><th>Klicks</th><th>Votes</th></tr>";

foreach($top_referrer as $r){

echo "<tr>";
echo "<td>".esc_html($r->name)."</td>";
echo "<td>".intval($r->referral_clicks)."</td>";
echo "<td>".intval($r->ref_votes)."</td>";
echo "</tr>";
}

echo "</table>";

echo "</div>";

}

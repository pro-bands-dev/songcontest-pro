<?php
function scp_admin_jury_preselect_page(){

    if(!current_user_can('manage_options')){
        return;
    }

    global $wpdb;

    $artists   = $wpdb->prefix.'scp_artists';
    $preselect = $wpdb->prefix.'scp_jury_preselect';

    $contest_id = function_exists('scp_get_admin_selected_contest_id')
        ? scp_get_admin_selected_contest_id()
        : (function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1);

    $limit = function_exists('scp_get_max_voting_participants')
        ? scp_get_max_voting_participants($contest_id)
        : (function_exists('scp_get_voting_limit') ? scp_get_voting_limit() : 30);

    /* =========================
       MANUELL STATUS TOP20
    ========================= */

    if(isset($_GET['set_top20']) && check_admin_referer('scp_set_top20_'.intval($_GET['set_top20']))){
        $artist_id = intval($_GET['set_top20']);
        $belongs = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $artists WHERE id=%d AND contest_id=%d",
            $artist_id,
            $contest_id
        )));

        if($belongs){
            $wpdb->update($artists, ['status'=>'top20'], ['id'=>$artist_id, 'contest_id'=>$contest_id]);
            echo "<div class='updated'><p>Band wurde manuell auf TOP".intval($limit)." gesetzt.</p></div>";
        }
    }

    if(isset($_GET['remove_top20']) && check_admin_referer('scp_remove_top20_'.intval($_GET['remove_top20']))){
        $artist_id = intval($_GET['remove_top20']);
        $belongs = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $artists WHERE id=%d AND contest_id=%d",
            $artist_id,
            $contest_id
        )));

        if($belongs){
            $wpdb->update($artists, ['status'=>'approved'], ['id'=>$artist_id, 'contest_id'=>$contest_id]);
            echo "<div class='updated'><p>Band wurde wieder auf APPROVED gesetzt.</p></div>";
        }
    }

    /* =========================
       BANDS + JURY VOTES
    ========================= */

    $bands = $wpdb->get_results($wpdb->prepare("
        SELECT
            a.*,
            COUNT(p.id) AS jury_votes
        FROM $artists a
        LEFT JOIN $preselect p
            ON a.id = p.artist_id
            AND p.contest_id = %d
            AND p.selected=1
        WHERE a.contest_id = %d
          AND a.status IN ('approved','top20','finale','winner')
        GROUP BY a.id
        ORDER BY jury_votes DESC, a.name ASC
    ", $contest_id, $contest_id));

    echo "<div class='wrap'>";
    echo "<h1>Jury Vorauswahl</h1>";

    if(function_exists('scp_admin_contest_selector')){
        scp_admin_contest_selector('songcontest-jury-preselect', $contest_id);
    }

    echo "<p>Grün markiert = aktuell automatisch in den Top ".intval($limit)." durch Jury Votes.</p>";
    echo "<p>Zusätzlich kannst du Teilnehmer manuell fest als TOP".intval($limit)." setzen oder wieder entfernen.</p>";

    if(empty($bands)){
        echo "<div class='notice notice-info'><p>Für diesen Contest sind aktuell keine freigegebenen Teilnehmer für die Jury-Vorauswahl vorhanden.</p></div>";
        echo "</div>";
        return;
    }

    echo "<table class='widefat striped'>";
    echo "<tr><th>#</th><th>Band</th><th>Songtitel</th><th>Status</th><th>Jury Votes</th><th>Aktionen</th></tr>";

    $rank = 1;

    foreach($bands as $band){
        $isAutoTop = ($rank <= $limit);
        $isManualTop = ($band->status === 'top20');

        echo "<tr ".($isAutoTop ? "style='background:#e6ffe6'" : "").">";
        echo "<td>".$rank."</td>";
        echo "<td><strong>".esc_html($band->name)."</strong></td>";
        echo "<td>".esc_html($band->title)."</td>";
        echo "<td>";
        if($isManualTop){
            echo "<span style='color:green;font-weight:bold;'>TOP".intval($limit)."</span>";
        }else{
            echo esc_html($band->status);
        }
        echo "</td>";
        echo "<td>".intval($band->jury_votes)."</td>";
        echo "<td>";
        echo "<button class='button scp-admin-pick' data-id='".intval($band->id)."'>";
        echo $isAutoTop ? "❌ Jury Pick entfernen" : "✔ Jury Pick setzen";
        echo "</button> ";

        $base = admin_url('admin.php?page=songcontest-jury-preselect&contest_id='.intval($contest_id));
        if(!$isManualTop){
            echo "<a class='button button-primary' href='".esc_url(wp_nonce_url(add_query_arg('set_top20', intval($band->id), $base), 'scp_set_top20_'.intval($band->id)))."'>→ Fix in TOP".intval($limit)."</a>";
        }else{
            echo "<a class='button' href='".esc_url(wp_nonce_url(add_query_arg('remove_top20', intval($band->id), $base), 'scp_remove_top20_'.intval($band->id)))."'>TOP".intval($limit)." entfernen</a>";
        }
        echo "</td></tr>";
        $rank++;
    }

    echo "</table></div>";
}

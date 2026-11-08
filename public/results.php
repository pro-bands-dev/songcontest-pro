<?php

if (!defined('ABSPATH')) exit;

add_shortcode('songcontest_results','scp_results_shortcode');


function scp_results_render_stat($label,$value){
    return "<div class='scp-result-stat'><span>".esc_html($label)."</span><strong>".esc_html($value)."</strong></div>";
}


function scp_results_render_contest($contest){

    $rows = scp_get_contest_results($contest->id);

    if(empty($rows)){
        return '';
    }

    $winner = [];
    $finalists = [];
    $top_voting = [];
    $participants = [];

    foreach($rows as $row){
        switch($row->result_group){
            case 'winner':
                $winner[] = $row;
                break;
            case 'finalist':
                $finalists[] = $row;
                break;
            case 'top_voting':
                $top_voting[] = $row;
                break;
            default:
                $participants[] = $row;
                break;
        }
    }

    $contest_label = 'Contest #'.intval($contest->id);

    if(!empty($contest->name)){
        $contest_label .= ' – '.esc_html($contest->name);
    }

    if(!empty($contest->year)){
        $contest_label .= ' ('.intval($contest->year).')';
    }

    $html = "<section class='scp-result-contest' data-contest='".intval($contest->id)."'>";
    $html .= "<header class='scp-result-header'><h2>".$contest_label."</h2></header>";

    if(!empty($winner)){
        $row = $winner[0];

        $html .= "<div class='scp-result-group scp-result-winner-group'>";
        $html .= "<h3>🏆 Gewinner</h3>";
        $html .= "<article class='scp-result-featured scp-result-winner'>";

        if(!empty($row->image_url)){
            $html .= "<img src='".esc_url(scp_image_url($row->image_url,'500'))."' alt='".esc_attr($row->artist_name)."'>";
        }

        $html .= "<div class='scp-result-featured-content'>";
        $html .= "<div class='scp-result-position scp-result-position-winner'>1</div>";
        $html .= "<h4>".esc_html($row->artist_name)."</h4>";

        if(!empty($row->song_title)){
            $html .= "<p class='scp-result-song'>".esc_html($row->song_title)."</p>";
        }

        $html .= "<div class='scp-result-stats'>";
        $html .= scp_results_render_stat('Gesamtscore',number_format((float)$row->total_score,2,',','.'));
        $html .= scp_results_render_stat('Final-Votes',intval($row->final_votes));
        $html .= scp_results_render_stat('Jurypunkte',intval($row->jury_points));
        $html .= scp_results_render_stat('Gesamtvotes',intval($row->total_votes));
        $html .= "</div>";
        $html .= "</div></article></div>";
    }

    if(!empty($finalists)){
        $html .= "<div class='scp-result-group'><h3>🏅 Finalisten</h3><div class='scp-result-finalists'>";

        foreach($finalists as $row){
            $html .= "<article class='scp-result-finalist'>";

            if(!empty($row->image_url)){
                $html .= "<img src='".esc_url(scp_image_url($row->image_url,'500'))."' alt='".esc_attr($row->artist_name)."'>";
            }

            $html .= "<div class='scp-result-finalist-content'>";
            $html .= "<div class='scp-result-position'>#".intval($row->position)."</div>";
            $html .= "<h4>".esc_html($row->artist_name)."</h4>";

            if(!empty($row->song_title)){
                $html .= "<p class='scp-result-song'>".esc_html($row->song_title)."</p>";
            }

            $html .= "<div class='scp-result-stats'>";
            $html .= scp_results_render_stat('Gesamtscore',number_format((float)$row->total_score,2,',','.'));
            $html .= scp_results_render_stat('Final-Votes',intval($row->final_votes));
            $html .= scp_results_render_stat('Jurypunkte',intval($row->jury_points));
            $html .= scp_results_render_stat('Gesamtvotes',intval($row->total_votes));
            $html .= "</div></div></article>";
        }

        $html .= "</div></div>";
    }

    if(!empty($top_voting)){
        $top_label = 'Top '.intval($contest->max_voting_participants ?: count($top_voting) + count($finalists) + count($winner));
        $html .= "<div class='scp-result-group'><h3>🔥 ".esc_html($top_label)."</h3><ol class='scp-result-list'>";

        foreach($top_voting as $row){
            $html .= "<li value='".intval($row->position)."'><strong>".esc_html($row->artist_name)."</strong>";
            if(!empty($row->song_title)){
                $html .= " <span>– ".esc_html($row->song_title)."</span>";
            }
            $html .= "</li>";
        }

        $html .= "</ol></div>";
    }

    if(!empty($participants)){
        $html .= "<div class='scp-result-group'><h3>Weitere Teilnehmer</h3><div class='scp-result-participants'>";

        foreach($participants as $row){
            $html .= "<div class='scp-result-participant'><strong>".esc_html($row->artist_name)."</strong>";
            if(!empty($row->song_title)){
                $html .= "<span>".esc_html($row->song_title)."</span>";
            }
            $html .= "</div>";
        }

        $html .= "</div></div>";
    }

    $html .= "</section>";

    return $html;
}


function scp_results_shortcode($atts){

    global $wpdb;

    $atts = shortcode_atts(
        [
            'contest' => 0
        ],
        $atts,
        'songcontest_results'
    );

    $contest_id = intval($atts['contest']);

    if($contest_id > 0){
        $contest = function_exists('scp_get_contest') ? scp_get_contest($contest_id) : null;

        if(!$contest){
            return "<p>Contest nicht gefunden.</p>";
        }

        $html = scp_results_render_contest($contest);
        return $html ?: "<p>Für diesen Contest wurde noch kein Ergebnis archiviert.</p>";
    }

    /*
     * Ohne ID: alle Contests mit vorhandenem Ergebnis-Snapshot getrennt
     * voneinander ausgeben. Dadurch können sich Ergebnisse nie vermischen.
     */
    $contests = $wpdb->get_results(
        "
        SELECT DISTINCT c.*
        FROM {$wpdb->prefix}scp_contests c
        INNER JOIN {$wpdb->prefix}scp_contest_results r
            ON r.contest_id=c.id
        ORDER BY c.year DESC, c.id DESC
        "
    );

    if(empty($contests)){
        return "<p>Noch keine abgeschlossenen Contest-Ergebnisse vorhanden.</p>";
    }

    $html = "<div class='scp-results-archive'>";

    foreach($contests as $contest){
        $html .= scp_results_render_contest($contest);
    }

    $html .= "</div>";

    return $html;
}

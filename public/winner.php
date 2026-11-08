<?php
/*
add_shortcode("songcontest_winner","scp_winner_page");

function scp_winner_page(){

$winner = scp_get_winner();

if(!$winner)
return "<p>Noch kein Gewinner verfügbar.</p>";

$html = "<div class='winner-box'>";

$html .= "<h2>🏆 Gewinner des Song Contest</h2>";

if($winner->image){

$html .= "<img class='winner-image' src='".$winner->image."'>";

}

$html .= "<h3>".$winner->name."</h3>";

$html .= "<p class='winner-song'>".$winner->title."</p>";

$html .= "<p>".$winner->band_info."</p>";

if($winner->audio){

$html .= "<audio controls>
<source src='".$winner->audio."'>
</audio>";

}

$html .= "<p class='winner-votes'>
Gesamt Votes: ".$winner->votes."
</p>";

$html .= "</div>";

return $html;

}
*/

add_shortcode("songcontest_winner","scp_winner_page");

function scp_winner_page(){

    global $wpdb;

    if(!function_exists('scp_get_winner')){
        return "<p>Gewinnerfunktion nicht verfügbar.</p>";
    }

    $winner = scp_get_winner();

    if(!$winner){
        return "<p>Noch kein Gewinner verfügbar.</p>";
    }

    $final_start = (function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_start') : get_option('scp_phase_final_start'));

    // Fallbacks, falls scp_get_winner() in einer älteren Plugin-Version
    // noch keine Score-Felder am Winner-Objekt mitliefert.


    $final_votes = isset($winner->public_votes)
        ? intval($winner->public_votes)
        : 0;

    if($final_votes === 0 && !empty($final_start)){

        $final_votes = intval(
            $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT COUNT(*)
                    FROM {$wpdb->prefix}scp_votes
                    WHERE artist_id=%d
                    AND vote_date >= %s
                    ",
                    $winner->id,
                    $final_start
                )
            )
        );
    }

    $jury_points = isset($winner->jury_points)
        ? intval($winner->jury_points)
        : intval(
            $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT COALESCE(SUM(points),0)
                    FROM {$wpdb->prefix}scp_jury_votes
                    WHERE artist_id=%d
                    ",
                    $winner->id
                )
            )
        );

    $total_score = isset($winner->total_score)
        ? floatval($winner->total_score)
        : 0;

    $contest_votes = intval(
        $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM {$wpdb->prefix}scp_votes
                WHERE artist_id=%d
                ",
                $winner->id
            )
        )
    );

    ob_start();
    ?>

    <div class="winner-box">

        <h2>🏆 Gewinner des Song Contest</h2>

        <?php if(!empty($winner->image)){ ?>
            <img class="winner-image"
                 src="<?php echo esc_url($winner->image); ?>"
                 alt="<?php echo esc_attr($winner->name); ?>">
        <?php } ?>

        <h3><?php echo esc_html($winner->name); ?></h3>

        <?php if(!empty($winner->title)){ ?>
            <p class="winner-song">
                <?php echo esc_html($winner->title); ?>
            </p>
        <?php } ?>

        <div class="winner-stats">

            <div class="winner-stat">
                <span class="winner-stat-label">Gesamtscore</span>
                <strong><?php echo esc_html(number_format($total_score, 2, ',', '.')); ?></strong>
            </div>

            <div class="winner-stat">
                <span class="winner-stat-label">Publikumsvotes im Finale</span>
                <strong><?php echo intval($final_votes); ?></strong>
            </div>

            <div class="winner-stat">
                <span class="winner-stat-label">Jurypunkte</span>
                <strong><?php echo intval($jury_points); ?></strong>
            </div>

            <div class="winner-stat">
                <span class="winner-stat-label">Publikumsvotes im gesamten Contest</span>
                <strong><?php echo intval($contest_votes); ?></strong>
            </div>

        </div>

        <?php if(!empty($winner->band_info)){ ?>
            <div class="winner-info">
                <?php echo wp_kses_post(wpautop($winner->band_info)); ?>
            </div>
        <?php } ?>

        <?php if(!empty($winner->audio)){ ?>
            <div class="winner-audio">
                <audio controls controlsList="nodownload">
                    <source src="<?php echo esc_url($winner->audio); ?>" type="audio/mpeg">
                </audio>
            </div>
        <?php } ?>

    </div>

    <?php

    return ob_get_clean();
}

<?php

add_shortcode('songcontest_band_profile','scp_band_profile');

function scp_band_profile(){

    if(!isset($_GET['band'])){
        return "<p>Band nicht gefunden.</p>";
    }

    global $wpdb;

    $ref = isset($_GET['ref'])
        ? absint(wp_unslash($_GET['ref']))
        : 0;

    $band_param = sanitize_text_field(wp_unslash($_GET['band']));

    // =========================
    //   BAND LADEN
    // =========================
    // Die eindeutige Band-ID bestimmt das Profil, nicht der aktive Contest.

    if (ctype_digit((string) $band_param) && (int) $band_param > 0) {

        $band = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}scp_artists WHERE id = %d LIMIT 1",
            (int) $band_param
        ));

    } else {

        // Bestehende Slug-Links weiterhin unterstützen.
        // Bei identischen Slugs in mehreren Contests ist die ID vorzuziehen.
        $band = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}scp_artists WHERE slug = %s ORDER BY id DESC LIMIT 1",
            $band_param
        ));
    }

    if (!$band) {
        return "<p>Band nicht gefunden.</p>";
    }

    // Erst NACH dem Laden ist die tatsächliche Contest-ID bekannt.
    $contest_id = (int) $band->contest_id;

    // =========================
    //   ZURÜCK ZUR VOTING-ÜBERSICHT
    // =========================
    $back_args = ['contest_id' => $contest_id];

    if ($ref > 0) {
        $back_args['ref'] = $ref;
    }

    $back_url = add_query_arg($back_args, home_url('/voting/'));


    // =========================
    //  VOTING FREIGABE
    // =========================

    $phase = function_exists('scp_get_contest_phase')
        ? scp_get_contest_phase($contest_id)
        : (function_exists('scp_get_current_phase') ? scp_get_current_phase() : 'top20');

    $allow_vote = false;

    // TOP20 Voting

    if(
        $phase === 'top20'
        && $band->status === 'top20'
    ){
        $allow_vote = true;
    }

    // Finale Voting

    if(
        $phase === 'final'
        && $band->status === 'finale'
    ){
        $allow_vote = true;
    }


    // =========================
    //   URLS
    // =========================

    $band_url = function_exists('scp_get_band_public_url')
        ? scp_get_band_public_url($band->id, $contest_id, $ref)
        : site_url('/band/?band='.$band->id.'&ref='.$ref);


    // =========================
    //   VOTES
    // =========================
	
	if($phase === 'final'){

    $final_start = function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_start', $contest_id) : (function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_start') : get_option('scp_phase_final_start'));

    $votes = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT COUNT(*)
            FROM {$wpdb->prefix}scp_votes
            WHERE contest_id=%d
            AND artist_id=%d
            AND vote_date >= %s
            ",
            $contest_id,
            $band->id,
            $final_start
        )
    );

}else{

    $votes = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT COUNT(*)
            FROM {$wpdb->prefix}scp_votes
            WHERE contest_id=%d
            AND artist_id=%d
            ",
            $contest_id,
            $band->id
        )
    );
}

    ob_start();
?>

<?php
if(
    $allow_vote
    &&
    function_exists('scp_voter_status_html')
){
    echo scp_voter_status_html();
}
?>

<div class="band-profile-card">
    <div class="band-image2">
        <?php if(!empty($band->image)){ ?>
            <img src="<?php echo esc_url($band->image); ?>"
                 alt="<?php echo esc_attr($band->name); ?>">
        <?php } ?>
    </div>

    <div class="scp-band-profile-card band-content">
        <div>
            <h1>
                <?php echo esc_html($band->name); ?>
            </h1>
            <div class="scp-vote-count" style="float:none;">
                🔥 <span class="votes">
                    <?php echo intval($votes); ?>
                </span> Votes
            </div>

            <?php if($allow_vote){ ?>
                <button class="vote vote-btn scp-vote-btn"
                        data-id="<?php echo $band->id; ?>"
                        data-ref="<?php echo $ref; ?>">
                    👍 Jetzt abstimmen
                </button>
            <?php } else { ?>
                <div class="scp-vote-disabled">
                    Band ist leider nicht im aktuellen Voting.
                </div>
            <?php } ?>
        </div>

        <!-- SHARE -->

		<div class="share-wrapper">
			<div class="share-title">
				🔗 Teile deinen Voting-Link
			</div>
			<div class="share-bar">

				<a href="https://wa.me/?text=<?php echo esc_url($band_url); ?>"
				   target="_blank"
				   class="share-btn whatsapp">
					WhatsApp
				</a>

				<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_url($band_url); ?>"
				   target="_blank"
				   class="share-btn facebook">
					Facebook
				</a>

				<a href="https://twitter.com/intent/tweet?url=<?php echo esc_url($band_url); ?>"
				   target="_blank"
				   class="share-btn twitter">
					X
				</a>
				<?php
					$ref_link = $band_url;
					?>

				<span class="scp-share-box">
					<input type="text"
						   value="<?php echo esc_url($ref_link); ?>"
						   class="scp-ref-link"
						   readonly>
					<button class="scp-copy-link">
						📋 Link kopieren
					</button>
				</span>
			</div>
		</div>		
    </div>
</div>

<!-- AUDIO PLAYER -->

<?php if(!empty($band->audio)){ ?>

<div class="wave-player"
     data-audio="<?php echo esc_url($band->audio); ?>">

    <div class="wave-player-title">

        <h3>
            <?php echo esc_html($band->name); ?>
            -
            <?php echo esc_html($band->title); ?>
        </h3>

        <?php if(!empty($band->genre)){ ?>

            <h4>
                (
                <?php echo esc_html($band->genre); ?>
                )
            </h4>

        <?php } ?>

    </div>

    <div class="waveform"></div>

    <button class="play-btn">▶</button>

</div>

<?php } ?>


<!-- BAND INFO -->

<div class="band-profile-card">

    <div class="scp-band-info">

        <strong>
            Info zu <?php echo esc_html($band->name); ?>:
        </strong>

        <br />

        <?php echo wp_kses_post($band->band_info); ?>

    </div>


    <?php if(!empty($band->instagram)){ ?>

    <div class="scp-social-grid">

        <div style="width:150px">
            <strong>Instagram:</strong>
        </div>

        <div>
			<a href="<?php echo esc_url($band->instagram); ?>" target="_blank" rel="noopener noreferrer">
				<?php echo esc_html($band->instagram); ?>
			</a>
		</div>

    </div>

    <?php } ?>


    <?php if(!empty($band->facebook)){ ?>

    <div class="scp-social-grid">

        <div style="width:150px">
            <strong>Facebook:</strong>
        </div>
		<div>
			<a href="<?php echo esc_url($band->facebook); ?>" target="_blank" rel="noopener noreferrer">
				<?php echo esc_html($band->facebook); ?>
			</a>
		</div>

    </div>

    <?php } ?>


    <?php if(!empty($band->website)){ ?>

    <div class="scp-social-grid">

        <div style="width:150px">
            <strong>Website:</strong>
        </div>

		<div>
			<a href="<?php echo esc_url($band->website); ?>" target="_blank" rel="noopener noreferrer">
				<?php echo esc_html($band->website); ?>
			</a>
		</div>

    </div>

    <?php } ?>

</div>


<a href="<?php echo esc_url($back_url); ?>"
   class="vote-btn scp-back-btn">

    ⬅ Zurück zur Übersicht

</a>

<?php

    return ob_get_clean();
}
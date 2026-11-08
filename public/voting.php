<?php
add_shortcode("songcontest_voting","scp_voting");

// =========================
//   BAND CARD RENDER
// =========================
function scp_render_band_card(
    $band,
    $rank,
    $allow_vote = true,
    $phase = null
){

    if($phase === null){

        $phase = function_exists('scp_get_current_phase')
            ? scp_get_current_phase()
            : 'closed';
    }

    $allow_vote =
        $allow_vote
        &&
        in_array($phase, ['top20','final'], true);

    $ref = isset($_GET['ref'])
        ? intval($_GET['ref'])
        : 0;

    $contest_id = isset($band->contest_id) ? intval($band->contest_id) : (function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 0);
    $band_url = function_exists('scp_get_band_public_url')
        ? scp_get_band_public_url($band->id, $contest_id)
        : add_query_arg('band', intval($band->id), site_url('/band/'));

    if($ref){
        $band_url .= "&ref=".$ref;
    }
	
	$html = "<div
    id='band-".intval($band->id)."'
    class='band-card'
    data-band='".intval($band->id)."'
    data-votes='".intval($band->votes)."'
    data-ref='".intval($ref)."'>

<div class='rank-number'>
            #".intval($rank)."
        </div>

        <div class='band-image'>";

    if(!empty($band->image)){

        $html .= "
            <img
                src='".esc_url(scp_image_url($band->image,'500'))."'
                alt='".esc_attr($band->name)."'
            >";
    }

    $html .= "
        </div>

        <div class='band-content'>

            <h3>
                ".esc_html($band->name)."
            </h3>

            <p class='song-title'>
                ".esc_html($band->title);

    if(!empty($band->genre)){

        $html .= "
                (".esc_html($band->genre).")";
    }

    $html .= "
            </p>

            <div class='vote-bar'>";

    if(!empty($band->audio)){

        $html .= "
                <audio controls controlsList='nodownload'>
                    <source
                        src='".esc_url($band->audio)."'
                        type='audio/mpeg'
                    >
                </audio>";
    }

    $html .= "
                <span class='scp-vote-count'>
                    🔥
                    <span class='votes'>
                        ".intval($band->votes)."
                    </span>
                </span>";

    if($allow_vote){

        $html .= "
                <button
                    class='vote-btn scp-vote-btn'
                    data-id='".intval($band->id)."'
                    data-ref='".intval($ref)."'
                >
                    👍 Vote
                </button>";
    }

    $html .= "
            </div>

            <a
                href='".esc_url($band_url)."'
                class='scp-details-btn'
            >
                Mehr erfahren
            </a>";

    $share_text = urlencode(
        "🔥 Stimme jetzt für "
        .$band->name
        ." beim Song Contest ab! "
        .$band_url
    );

    $html .= "
            <div class='share-wrapper'>

                <div class='share-title'>
                    📲 Band teilen & unterstützen
                </div>

                <div class='share-bar'>

                    <a
                        class='share-btn whatsapp'
                        href='https://wa.me/?text=".$share_text."'
                        target='_blank'
                        rel='noopener'
                    >
                        📱 WhatsApp
                    </a>

                    <a
                        class='share-btn facebook'
                        href='https://www.facebook.com/sharer/sharer.php?u="
                            .rawurlencode($band_url)."'
                        target='_blank'
                        rel='noopener'
                    >
                        📘 Facebook
                    </a>

                    <a
                        class='share-btn twitter'
                        href='https://twitter.com/intent/tweet?url="
                            .rawurlencode($band_url)."'
                        target='_blank'
                        rel='noopener'
                    >
                        𝕏 Twitter
                    </a>

                </div>

            </div>

        </div>

    </div>";

    return $html;
}
/*
function scp_render_band_card($band,$rank,$allow_vote=true){

    $phase = function_exists('scp_get_current_phase') 
        ? scp_get_current_phase() 
        : 'top20';

    $allow_vote = $allow_vote && in_array($phase, ['top20','final']);

    $ref = isset($_GET['ref']) ? intval($_GET['ref']) : 0;

    $band_url = function_exists('scp_get_band_public_url')
        ? scp_get_band_public_url($band->id, isset($band->contest_id) ? intval($band->contest_id) : 0)
        : site_url("/song-contest-2026/band/?band=".$band->id);

    if($ref){
        $band_url .= "&ref=".$ref;
    }

    $html = "<div class='band-card' data-band='".intval($band->id)."' data-votes='".intval($band->votes)."' data-ref='".intval($ref)."'>

        <div class='rank-number'> #".intval($rank)."</div>

        <div class='band-image'>";

    if(!empty($band->image)){
        $html .= "<img src='".esc_url(scp_image_url($band->image,'500'))."' alt='".esc_attr($band->name)."'>";
    }

    $html .= "</div>

        <div class='band-content'>

            <h3>".esc_html($band->name)."</h3>

            <p class='song-title'>".esc_html($band->title);

    if(!empty($band->genre)){
        $html .= " (".esc_html($band->genre).")";
    }

    $html .= "</p>";

    $html .= "<div class='vote-bar'>";

    if(!empty($band->audio)){
        $html .= "<audio controls controlsList='nodownload'>
            <source src='".esc_url($band->audio)."' type='audio/mpeg'>
        </audio>";
    }

    $html .="<span class='scp-vote-count'>
        🔥 <span class='votes'>".intval($band->votes)."</span>
    </span>";

    if($allow_vote){
        $html .= "<button class='vote-btn scp-vote-btn' 
            data-id='".intval($band->id)."' 
            data-ref='".intval($ref)."'>
            👍 Vote
        </button>";
    }

    $html .= "</div>";

    $html .= "<a href='".esc_url($band_url)."' class='scp-details-btn'>
        Mehr erfahren
    </a>";

    $share_text = urlencode(
        "🔥 Stimme jetzt für ".$band->name." beim Song Contest ab! ".$band_url
    );

    $html .= "
    <div class='share-wrapper'>
        <div class='share-title'>📲 Band teilen & unterstützen</div>
        <div class='share-bar'>
            <a class='share-btn whatsapp' href='https://wa.me/?text=".$share_text."' target='_blank'>📱 WhatsApp</a>
            <a class='share-btn facebook' href='https://www.facebook.com/sharer/sharer.php?u=".urlencode($band_url)."' target='_blank'>📘 Facebook</a>
            <a class='share-btn twitter' href='https://twitter.com/intent/tweet?url=".urlencode($band_url)."' target='_blank'>𝕏 Twitter</a>
        </div>
    </div>";

    $html .= "</div></div>";

    return $html;
}
*/

// =========================
//   MAIN VOTING OUTPUT
// =========================

// =========================
//   ADMIN-DIAGNOSE (nur für Administratoren sichtbar)
// =========================

function scp_voting_admin_diagnostic($contest, $phase){

    global $wpdb;

    if(!current_user_can('manage_options')){
        return '';
    }

    $contest_id = intval($contest->id);
    $now        = current_time('timestamp');
    $table      = $wpdb->prefix.'scp_artists';

    $fields = [
        'Bewerbung'   => ['application_start','application_end'],
        'Top-Voting'  => ['top20_start','top20_end'],
        'Auswertung'  => ['vaus_start','vaus_end'],
        'Finale'      => ['final_start','final_end'],
    ];

    $inside = [];
    $rows   = '';

    foreach($fields as $label => $f){

        $a = scp_get_contest_phase_value($f[0], $contest_id);
        $b = scp_get_contest_phase_value($f[1], $contest_id);
        $ta = $a ? strtotime($a) : 0;
        $tb = $b ? strtotime($b) : 0;
        $is_now = ($ta && $tb && $now >= $ta && $now <= $tb);

        if($is_now){
            $inside[] = $label;
        }

        $rows .= "<tr><td>".esc_html($label)."</td><td>".esc_html($a ?: '–')."</td><td>".esc_html($b ?: '–')."</td><td>".($is_now ? '<strong>◀ jetzt</strong>' : '')."</td></tr>";
    }

    $counts = $wpdb->get_results($wpdb->prepare(
        "SELECT status, COUNT(*) AS n FROM $table WHERE contest_id=%d GROUP BY status",
        $contest_id
    ));

    $count_txt = [];
    foreach((array) $counts as $c){
        $count_txt[] = esc_html($c->status).': '.intval($c->n);
    }

    $override = isset($_GET['testphase']) ? sanitize_text_field(wp_unslash($_GET['testphase'])) : '';
    $can_vote = in_array($phase, ['top20','final'], true);

    $hints = [];

    if(count($inside) > 1){
        $hints[] = 'Mehrere Phasen-Zeiträume enthalten die aktuelle Zeit ('.esc_html(implode(' + ', $inside)).'). Die Bewerbungsphase hat Vorrang vor allen anderen – deshalb wird kein Voting angeboten. Lege die Zeiträume so, dass sie sich nicht überschneiden (z. B. Bewerbungsende vor Top-Voting-Beginn).';
    }
    if(!$can_vote){
        $hints[] = 'Aktuelle Phase ist „'.esc_html($phase).'“. Buttons gibt es nur in den Phasen „top20“ und „final“.';
    }
    if($override !== ''){
        $hints[] = 'Die Phase wird über ?testphase='.esc_html($override).' erzwungen. Das ändert nur die Anzeige. Beim tatsächlichen Abstimmen prüft das System die echten Zeiträume, deshalb kann ein Klick trotzdem abgelehnt werden (Administratoren sind davon ausgenommen).';
    }
    if($phase === 'top20' && !$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE contest_id=%d AND status='top20'", $contest_id))){
        $hints[] = 'Keine Band hat den Status „top20“ (Voting-Teilnehmer). In der Top-Voting-Phase werden nur Bands mit diesem Status angezeigt.';
    }
    if($phase === 'final' && !$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE contest_id=%d AND status='finale'", $contest_id))){
        $hints[] = 'Keine Band hat den Status „finale“. In der Finalphase werden nur Finalisten angezeigt.';
    }

    $out  = "<div style='border:2px dashed #b32d2e;background:#fff8f8;color:#222;padding:12px 16px;margin:0 0 20px;font-size:13px;line-height:1.5;'>";
    $out .= "<strong>Diagnose (nur für Administratoren sichtbar)</strong><br>";
    $out .= "Angezeigter Contest: #".$contest_id." – ".esc_html($contest->name)." (Status: ".esc_html($contest->status).")<br>";
    $out .= "Erkannte Phase: <strong>".esc_html($phase)."</strong> – Stimmabgabe möglich: <strong>".($can_vote ? 'ja' : 'nein')."</strong><br>";
    $out .= "Jetzt: ".esc_html(date('Y-m-d H:i', $now))."<br>";
    $out .= "Bands in diesem Contest nach Status: ".($count_txt ? implode(', ', $count_txt) : 'keine')."<br>";
    $out .= "<table style='border-collapse:collapse;margin:8px 0;'><tr><th style='text-align:left;padding-right:12px;'>Phase</th><th style='text-align:left;padding-right:12px;'>Start</th><th style='text-align:left;padding-right:12px;'>Ende</th><th></th></tr>".$rows."</table>";

    if($hints){
        $out .= "<div style='color:#b32d2e;'><strong>Mögliche Ursache:</strong><ul style='margin:4px 0 0 18px;'>";
        foreach($hints as $h){
            $out .= "<li>".$h."</li>";
        }
        $out .= "</ul></div>";
    }else{
        $out .= "<span style='color:#1a7f37;'>Keine Auffälligkeit gefunden. Falls trotzdem kein Button erscheint: Seiten-Cache leeren.</span>";
    }

    $out .= "</div>";

    return $out;
}


function scp_voting(){

    if(!function_exists('scp_get_current_phase')){
        return "<p>Contest System nicht aktiv.</p>";
    }

    global $wpdb;

    $table = $wpdb->prefix.'scp_artists';
    $votes_table = $wpdb->prefix.'scp_votes';

    //$contest_id = function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 1;
    //$max_voting_participants = function_exists('scp_get_max_voting_participants') ? scp_get_max_voting_participants($contest_id) : 30;

    //$phase = scp_get_current_phase();
	
	$contest = function_exists('scp_get_public_contest')
		? scp_get_public_contest()
		: null;

	if(!$contest){

		return "
			<div class='scp-contest-message'>
				<p>
					Aktuell ist kein Song Contest veröffentlicht oder geplant.
				</p>
			</div>
		";
	}

	$contest_id = intval($contest->id);

	$max_voting_participants =
		function_exists('scp_get_max_voting_participants')
		? scp_get_max_voting_participants($contest_id)
		: 30;

	$phase =
		function_exists('scp_get_contest_phase')
		? scp_get_contest_phase($contest_id)
		: 'closed';

    $html = '';

    $html .= scp_voting_admin_diagnostic($contest, $phase);

    if(
        in_array($phase, ['top20','final'], true)
        &&
        function_exists('scp_voter_status_html')
    ){
        $html .= scp_voter_status_html();
    }

    $html .= "<div id='band-list'>";

    $now = current_time('timestamp');

    $top20_end = strtotime(function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('top20_end', $contest_id) : get_option('scp_phase_top20_end'));
    $final_end = strtotime(function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_end', $contest_id) : get_option('scp_phase_final_end'));
    $winner_date = strtotime(function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('winner_date', $contest_id) : get_option('scp_phase_winner_date'));

    $is_result_review = false;

    if($phase === 'closed'){

        if($top20_end && $now > $top20_end){
            $is_result_review = true;
        }

        if($final_end && $now > $final_end){
            $is_result_review = true;
        }

        if($winner_date && $now >= $winner_date){
            $is_result_review = false;
        }
    }

    $headline = "Bewerber";

    if($phase == "top20"){
        $headline = "Top-".$max_voting_participants." Voting";
    }elseif($phase == "vauswertung" || $is_result_review){
        $headline = "Voting Auswertung";
    }elseif($phase == "final"){
        $headline = "Final-Voting";
    }elseif($phase == "winner"){
        $headline = "Gewinner";
    }

    $html .= "<h3 class='contest-headline'>".$headline."</h3>";

    $bands = [];

    if($is_result_review){

        $bands = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT a.*, COUNT(v.id) AS votes
                FROM $table a
                LEFT JOIN $votes_table v
                    ON a.id = v.artist_id
                    AND v.contest_id = %d
                WHERE a.contest_id = %d
                AND a.status IN ('approved','top20','finale','winner')
                GROUP BY a.id
                ORDER BY votes DESC, a.name ASC
                ",
                $contest_id,
                $contest_id
            )
        );

    }elseif($phase == 'application' || $phase == 'closed'){

        $bands = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT a.*, COUNT(v.id) AS votes
                FROM $table a
                LEFT JOIN $votes_table v
                    ON a.id = v.artist_id
                    AND v.contest_id = %d
                WHERE a.contest_id = %d
                AND a.status IN ('approved','top20')
                GROUP BY a.id
                ORDER BY a.id DESC
                ",
                $contest_id,
                $contest_id
            )
        );

    }elseif(in_array($phase, ['top20','vauswertung'])){

        $bands = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT a.*, COUNT(v.id) AS votes
                FROM $table a
                LEFT JOIN $votes_table v
                    ON a.id = v.artist_id
                    AND v.contest_id = %d
                WHERE a.contest_id = %d
                AND a.status IN ('top20','finale')
                GROUP BY a.id
                ORDER BY votes DESC, a.name ASC
                ",
                $contest_id,
                $contest_id
            )
        );

    }elseif($phase == "final"){

        $final_start = function_exists('scp_get_contest_phase_value') ? scp_get_contest_phase_value('final_start', $contest_id) : get_option('scp_phase_final_start');

        $bands = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT a.*, COUNT(v.id) AS votes
                FROM $table a
                LEFT JOIN $votes_table v
                    ON a.id = v.artist_id
                    AND v.contest_id = %d
                    AND v.vote_date >= %s
                WHERE a.contest_id = %d
                AND a.status='finale'
                GROUP BY a.id
                ORDER BY votes DESC, a.name ASC
                ",
                $contest_id,
                $final_start,
                $contest_id
            )
        );

    }elseif($phase == "winner"){

        $bands = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT a.*, COUNT(v.id) AS votes
                FROM $table a
                LEFT JOIN $votes_table v
                    ON a.id = v.artist_id
                    AND v.contest_id = %d
                WHERE a.contest_id = %d
                AND a.status IN ('approved','top20','finale','winner')
                GROUP BY a.id
                ORDER BY votes DESC, a.name ASC
                ",
                $contest_id,
                $contest_id
            )
        );

    }else{

        $bands = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT a.*, COUNT(v.id) AS votes
                FROM $table a
                LEFT JOIN $votes_table v
                    ON a.id = v.artist_id
                    AND v.contest_id = %d
                WHERE a.contest_id = %d
                AND a.status='approved'
                GROUP BY a.id
                ORDER BY a.id DESC
                ",
                $contest_id,
                $contest_id
            )
        );
    }

    $rank = 1;

    foreach($bands as $band){

        $allow_vote = in_array($phase,['top20','final']);

//        $html .= scp_render_band_card(
//            $band,
//            $rank,
//            $allow_vote
//        );
		$html .= scp_render_band_card(
			$band,
			$rank,
			$allow_vote,
			$phase
		);
		
        $rank++;
    }

    $html .= "</div>";

    return $html;
}

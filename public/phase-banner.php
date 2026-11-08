<?php
/**
 * Shortcode [songcontest_phase_banner]
 *
 * Zeigt ein Contest-Banner, das sich automatisch an die aktuelle Phase anpasst
 * (Bewerbung, Jury-Vorauswahl, Voting, Auswertung, Finale, Gewinner, Pause).
 * Gedacht für die Startseite und andere Seiten, z. B. innerhalb einer
 * Divi-Sektion mit den Klassen "pb-section pb-section--rost pb-section--compact pb-torn".
 *
 * Nutzt ausschließlich Lesefunktionen – es werden keine Daten verändert.
 *
 * Attribute (alle optional):
 *   title        Überschrift                       (Standard: "PRO-BANDS Song-Contest")
 *   apply_url    Link zum Bewerbungsformular       (Standard: /song-contest-bewerbung/)
 *   voting_url   Link zur Voting-Seite              (Standard: /voting/)
 *   info_url     Link zur Contest-Seite             (Standard: <Contest-Seite>/)
 *   winner_url   Link zur Gewinnerseite             (Standard: info_url)
 *   archive_url  Link zu vergangenen Contests       (Standard: info_url)
 *   hide_closed  "1" = nichts anzeigen, wenn kein Contest geplant ist
 *   countdown    "0" = Countdown ausblenden (Standard: eingeblendet)
 *   layout       "buttons" = nur die passenden Buttons (+ Countdown) ausgeben, z. B. für einen Hero
 *   info_label   Beschriftung des „Mehr erfahren“-Buttons (z. B. "So läuft's")
 *
 * Testen: Seite mit ?testphase=application | top20 | vauswertung | final | winner | closed aufrufen.
 */

if (!defined('ABSPATH')) exit;

add_shortcode('songcontest_phase_banner', 'scp_phase_banner_shortcode');


function scp_phase_banner_date($value, $with_time = false){
    if(empty($value)) return '';
    $ts = strtotime($value);
    if(!$ts) return '';
    return wp_date($with_time ? 'd.m.Y, H:i \U\h\r' : 'd.m.Y', $ts, wp_timezone());
}


function scp_phase_banner_days_left($value){
    if(empty($value)) return '';
    $end = strtotime($value);
    if(!$end) return '';
    $now  = current_time('timestamp');
    $diff = $end - $now;
    if($diff <= 0) return '';
    $days = (int) ceil($diff / DAY_IN_SECONDS);
    if($days <= 1) return 'nur noch heute';
    return 'noch ' . $days . ' Tage';
}


/**
 * Ermittelt den anzuzeigenden Zustand inkl. der Lücken zwischen den Phasen,
 * die scp_get_contest_phase() als "closed" meldet.
 */
function scp_phase_banner_state($contest){

    $contest_id = $contest ? intval($contest->id) : 0;
    $phase = function_exists('scp_get_contest_phase')
        ? scp_get_contest_phase($contest_id)
        : 'closed';

    if($phase !== 'closed' || !$contest){
        return $phase;
    }

    // Testmodus: ?testphase=closed soll wirklich "closed" zeigen.
    if(function_exists('scp_get_test_phase_override') && scp_get_test_phase_override()){
        return 'closed';
    }

    $now = current_time('timestamp');
    $ts  = function($field) use ($contest){
        return !empty($contest->$field) ? strtotime($contest->$field) : 0;
    };

    if($ts('application_start') && $now < $ts('application_start')){
        return 'upcoming';
    }
    if($ts('application_end') && $ts('top20_start') && $now > $ts('application_end') && $now < $ts('top20_start')){
        return 'jury';
    }
    if($ts('top20_end') && $ts('vaus_start') && $now > $ts('top20_end') && $now < $ts('vaus_start')){
        return 'vauswertung';
    }
    if($ts('vaus_end') && $ts('final_start') && $now > $ts('vaus_end') && $now < $ts('final_start')){
        return 'pre_final';
    }
    if($ts('final_end') && $ts('winner_date') && $now > $ts('final_end') && $now < $ts('winner_date')){
        return 'pre_winner';
    }

    return 'closed';
}


function scp_phase_banner_shortcode($atts = []){

    $atts = shortcode_atts([
        'title'       => 'PRO-BANDS Song-Contest',
        'apply_url'   => '',
        'voting_url'  => '',
        'info_url'    => '',
        'winner_url'  => '',
        'archive_url' => '',
        'hide_closed' => '0',
        'countdown'   => '1',
        'layout'      => 'banner',
        'info_label'  => 'Mehr erfahren',
    ], $atts, 'songcontest_phase_banner');

    $contest_id = function_exists('scp_get_frontend_contest_id')
        ? scp_get_frontend_contest_id()
        : (function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 0);

    $contest = ($contest_id && function_exists('scp_get_contest')) ? scp_get_contest($contest_id) : null;
    $state   = scp_phase_banner_state($contest);

    // Nächster geplanter Contest (für Pause und Gewinnerphase).
    $next = function_exists('scp_get_next_application_contest') ? scp_get_next_application_contest() : null;
    if($next && $contest && intval($next->id) === intval($contest->id)){
        $next = null; // derselbe Contest – wird bereits als "upcoming" gezeigt
    }

    // Pause: gibt es einen kommenden Contest, wird dieser angekündigt.
    if($state === 'closed' && $next){
        $contest = $next;
        $state   = 'upcoming';
        $next    = null;
    }

    if($state === 'closed' && $atts['hide_closed'] === '1'){
        return '';
    }

    // Links
    $base = ($contest && function_exists('scp_get_contest_public_base_url'))
        ? scp_get_contest_public_base_url(intval($contest->id))
        : home_url('/');

    $info_url    = $atts['info_url']    ?: $base;
    $apply_url   = $atts['apply_url']   ?: (function_exists('scp_get_public_page_url') ? scp_get_public_page_url('application', home_url('/song-contest-bewerbung/')) : home_url('/song-contest-bewerbung/'));
    $voting_url  = $atts['voting_url']  ?: (function_exists('scp_get_public_page_url') ? scp_get_public_page_url('voting', home_url('/voting/')) : home_url('/voting/'));
    $winner_url  = $atts['winner_url']  ?: $info_url;
    $archive_url = $atts['archive_url'] ?: $info_url;

    $f = function($field) use ($contest){
        return ($contest && !empty($contest->$field)) ? $contest->$field : '';
    };

    $eyebrow = '';
    $text    = '';
    $buttons = []; // [Label, URL, Variante]
    $note    = '';

    switch($state){

        case 'upcoming':
            $eyebrow = 'Nächster Contest · Bewerbung ab ' . scp_phase_banner_date($f('application_start'));
            $text    = 'Trag dir den Start ein – die Teilnahme ist kostenlos. Zeig deinen Song, sammle Stimmen, gewinne einen Profi-Mix.';
            $buttons[] = [$atts['info_label'], $info_url, 'primary'];
            break;

        case 'application':
            $left    = $atts['countdown'] === '0' ? scp_phase_banner_days_left($f('application_end')) : '';
            $eyebrow = 'Jetzt live · Bewerbung bis ' . scp_phase_banner_date($f('application_end')) . ($left ? ' · ' . $left : '');
            $text    = 'Zeig deinen Song, sammle Stimmen, gewinne einen Profi-Mix. Die Teilnahme ist kostenlos.';
            $buttons[] = ['Jetzt bewerben', $apply_url, 'primary'];
            $buttons[] = [$atts['info_label'], $info_url, 'outline'];
            break;

        case 'jury':
            $eyebrow = 'Bewerbung geschlossen · Jury-Vorauswahl';
            $start   = scp_phase_banner_date($f('top20_start'));
            $text    = 'Die Jury hört gerade alle Einsendungen.' . ($start ? ' Das Publikums-Voting startet am ' . $start . '.' : '');
            $buttons[] = [$atts['info_label'], $info_url, 'outline'];
            break;

        case 'top20':
            $left    = $atts['countdown'] === '0' ? scp_phase_banner_days_left($f('top20_end')) : '';
            $eyebrow = 'Jetzt live · Voting bis ' . scp_phase_banner_date($f('top20_end')) . ($left ? ' · ' . $left : '');
            $text    = 'Hör rein und stimm jeden Tag für deinen Favoriten ab.';
            $buttons[] = ['Jetzt abstimmen', $voting_url, 'primary'];
            $buttons[] = [$atts['info_label'], $info_url, 'outline'];
            break;

        case 'vauswertung':
            $eyebrow = 'Voting beendet · Auswertung';
            $start   = scp_phase_banner_date($f('final_start'));
            $text    = 'Die Finalisten werden gerade ermittelt.' . ($start ? ' Das Live-Finale findet am ' . $start . ' statt.' : '');
            $buttons[] = [$atts['info_label'], $info_url, 'outline'];
            break;

        case 'pre_final':
            $max     = function_exists('scp_get_max_finalists') ? scp_get_max_finalists(intval($contest->id)) : 5;
            $eyebrow = 'Live-Finale am ' . scp_phase_banner_date($f('final_start'), true);
            $text    = 'Die Top ' . intval($max) . ' stehen fest und spielen live um den Sieg.';
            $buttons[] = ['Finalisten ansehen', $info_url, 'primary'];
            break;

        case 'final':
            $eyebrow = 'Jetzt live · Finale';
            $text    = 'Die Finalisten spielen gerade live um den Sieg.';
            $buttons[] = ['Zum Finale', $info_url, 'primary'];
            break;

        case 'pre_winner':
            $eyebrow = 'Finale gespielt · Auswertung';
            $date    = scp_phase_banner_date($f('winner_date'));
            $text    = 'Jury- und Publikumswertung werden zusammengeführt.' . ($date ? ' Der Gewinner wird am ' . $date . ' bekanntgegeben.' : '');
            $buttons[] = [$atts['info_label'], $info_url, 'outline'];
            break;

        case 'winner':
            global $wpdb;
            $winner_name = $contest ? $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT name FROM {$wpdb->prefix}scp_artists WHERE contest_id=%d AND status='winner' ORDER BY id ASC LIMIT 1",
                    intval($contest->id)
                )
            ) : '';
            $eyebrow = 'Contest beendet · Der Gewinner steht fest';
            $text    = $winner_name
                ? 'Glückwunsch an ' . $winner_name . '! Danke an alle Bands und alle, die abgestimmt haben.'
                : 'Danke an alle Bands und alle, die abgestimmt haben.';
            $buttons[] = ['Zum Gewinner', $winner_url, 'primary'];
            if($next && !empty($next->application_start)){
                $note = 'Nächster Contest: Bewerbung ab ' . scp_phase_banner_date($next->application_start) . '.';
            }
            break;

        default: // closed
            $eyebrow = 'Pause zwischen den Contests';
            $text    = 'Der nächste Song-Contest ist in Planung. Schau dir bis dahin die Bands der letzten Runden an.';
            $buttons[] = ['Vergangene Contests', $archive_url, 'outline'];
            break;
    }

    $cd = ($atts['countdown'] !== '0' && function_exists('scp_countdown_for_state'))
        ? scp_countdown_for_state($state, $contest)
        : null;

    if($atts['layout'] === 'buttons'){
        ob_start(); ?>
        <div class="scp-phase-buttons" data-phase="<?php echo esc_attr($state); ?>">
            <?php if(!empty($buttons)): ?>
            <div class="pb-btn-group">
                <?php foreach($buttons as $b): ?>
                    <a class="pb-btn<?php echo $b[2] === 'outline' ? ' pb-btn--outline' : ''; ?>" href="<?php echo esc_url($b[1]); ?>"><?php echo esc_html($b[0]); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if($cd){ echo scp_countdown_markup($cd['ms'], $cd['label']); } ?>
        </div>
        <?php
        return ob_get_clean();
    }

    ob_start(); ?>
    <div class="pb-banner scp-phase-banner scp-phase-banner--<?php echo esc_attr($state); ?>" data-phase="<?php echo esc_attr($state); ?>">
        <div class="pb-banner__text">
            <p class="pb-eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <h2><?php echo esc_html($atts['title']); ?></h2>
            <p><?php echo esc_html($text); ?></p>
            <?php
            if($cd){ echo scp_countdown_markup($cd['ms'], $cd['label']); }
            ?>
            <?php if($note): ?><p class="pb-note"><?php echo esc_html($note); ?></p><?php endif; ?>
        </div>
        <?php if(!empty($buttons)): ?>
        <div class="pb-btn-group">
            <?php foreach($buttons as $b): ?>
                <a class="pb-btn<?php echo $b[2] === 'outline' ? ' pb-btn--outline' : ''; ?>" href="<?php echo esc_url($b[1]); ?>"><?php echo esc_html($b[0]); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

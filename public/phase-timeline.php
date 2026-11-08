<?php
/**
 * Shortcode [songcontest_phase_timeline]
 *
 * Phasen-Leiste für die Contest-Seite: zeigt alle Schritte des Contests mit
 * Zeitraum und markiert automatisch, was abgeschlossen ist, was gerade läuft
 * und was als Nächstes kommt. Nutzt dieselbe Phasen-Erkennung wie
 * [songcontest_phase_banner] (public/phase-banner.php).
 *
 * Nur Lesezugriffe – es werden keine Daten verändert.
 *
 * Attribute (optional):
 *   contest_id   bestimmten Contest anzeigen (Standard: der öffentlich angezeigte bzw. nächste Contest)
 *   final_text   eigener Text für das Live-Finale
 *
 * Testen: Seite mit ?testphase=application | top20 | vauswertung | final | winner aufrufen.
 */

if (!defined('ABSPATH')) exit;

add_shortcode('songcontest_phase_timeline', 'scp_phase_timeline_shortcode');


function scp_phase_timeline_shortcode($atts = []){

    $atts = shortcode_atts([
        'contest_id' => 0,
        'final_text' => '',
    ], $atts, 'songcontest_phase_timeline');

    if(!function_exists('scp_phase_banner_state')){
        return '';
    }

    $contest_id = intval($atts['contest_id']);
    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_frontend_contest_id')
            ? scp_get_frontend_contest_id()
            : (function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 0);
    }

    $contest = ($contest_id && function_exists('scp_get_contest')) ? scp_get_contest($contest_id) : null;
    $state   = scp_phase_banner_state($contest);

    // Pause: kommenden Contest zeigen, falls schon angelegt.
    if($state === 'closed' && intval($atts['contest_id']) <= 0 && function_exists('scp_get_next_application_contest')){
        $next = scp_get_next_application_contest();
        if($next){
            $contest = $next;
            $state   = 'upcoming';
        }
    }

    if(!$contest){
        return '';
    }

    $f = function($field) use ($contest){
        return !empty($contest->$field) ? $contest->$field : '';
    };

    // Zeitraum der Jury-Vorauswahl = Lücke zwischen Bewerbungsende und Voting-Start.
    $jury_start = $f('application_end') ? date('Y-m-d H:i:s', strtotime($f('application_end') . ' +1 minute')) : '';
    $jury_end   = $f('top20_start')     ? date('Y-m-d H:i:s', strtotime($f('top20_start') . ' -1 minute'))     : '';

    $jury_url = function_exists('scp_get_contest_jury_page_url') ? scp_get_contest_jury_page_url(intval($contest->id)) : '';

    $max = function_exists('scp_get_max_finalists') ? intval(scp_get_max_finalists(intval($contest->id))) : 5;

    $range = function($a, $b, $with_time = false){
        $x = scp_phase_banner_date($a, $with_time);
        $y = scp_phase_banner_date($b, $with_time);
        if($x && $y){
            // Gleicher Tag → nur einmal das Datum
            if(substr($x, 0, 10) === substr($y, 0, 10)) return $x;
            return $x . ' – ' . $y;
        }
        return $x ?: $y;
    };

    $steps = [
        ['Bewerbung',          $range($f('application_start'), $f('application_end')), 'Song, Bandfoto und Bandinfo über das Bewerbungsformular einreichen.'],
        ['Jury-Vorauswahl',    $range($jury_start, $jury_end),                         'Die Jury prüft alle Einsendungen und gibt sie für das Voting frei.'],
        ['Online-Voting',      $range($f('top20_start'), $f('top20_end')),            'Jeden Tag eine Stimme pro bestätigter E-Mail-Adresse.'],
        ['Finalisten',         $range($f('vaus_start'), $f('vaus_end')),              'Das Voting wird geprüft, die Top ' . $max . ' ziehen ins Finale ein.'],
        ['Live-Finale',        $range($f('final_start'), $f('final_end'), true),      $atts['final_text'] ?: 'Die Top ' . $max . ' treten live gegeneinander an.'],
        ['Gewinner',           scp_phase_banner_date($f('winner_date')),              'Jury- und Publikumswertung ergeben den Gewinner.'],
    ];

    // Welcher Schritt läuft (now) bzw. kommt als Nächstes (next)?
    $map = [
        'upcoming'    => ['next', 0],
        'application' => ['now', 0],
        'jury'        => ['now', 1],
        'top20'       => ['now', 2],
        'vauswertung' => ['now', 3],
        'pre_final'   => ['next', 4],
        'final'       => ['now', 4],
        'pre_winner'  => ['next', 5],
        'winner'      => ['now', 5],
    ];
    $mode  = isset($map[$state]) ? $map[$state][0] : '';
    $index = isset($map[$state]) ? $map[$state][1] : -1;

    $labels = [
        'done'  => 'Abgeschlossen',
        'now'   => 'Läuft jetzt',
        'next'  => 'Als Nächstes',
        'later' => 'Demnächst',
    ];
    if($state === 'winner'){
        $labels['now'] = 'Steht fest';
    }

    ob_start(); ?>
    <ol class="scp-timeline" data-phase="<?php echo esc_attr($state); ?>">
        <?php foreach($steps as $i => $step):
            if($index < 0){
                $status = 'later';
            } elseif($i < $index){
                $status = 'done';
            } elseif($i === $index){
                $status = $mode;
            } else {
                $status = 'later';
            }
        ?>
        <li class="scp-timeline__step is-<?php echo esc_attr($status); ?>"<?php echo $status === 'now' ? ' aria-current="step"' : ''; ?>>
            <span class="scp-timeline__status"><?php echo esc_html($labels[$status]); ?></span>
            <span class="scp-timeline__title"><?php echo esc_html(($i + 1) . '. ' . $step[0]); ?></span>
            <?php if($step[1]): ?><span class="scp-timeline__date"><?php echo esc_html($step[1]); ?></span><?php endif; ?>
            <span class="scp-timeline__text"><?php echo esc_html($step[2]); ?></span>
            <?php if($i === 1 && $jury_url): ?>
                <a class="scp-timeline__link" href="<?php echo esc_url($jury_url); ?>">Wer ist die Jury? →</a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php
    return ob_get_clean();
}

<?php
/**
 * Kompakter Countdown für den Song-Contest.
 *
 * - [songcontest_countdown]  eigenständiger Countdown bis zum nächsten Phasenwechsel
 *                            (Attribut: label="…" für eigenen Text)
 * - scp_countdown_markup()   wird auch vom [songcontest_phase_banner] genutzt
 *
 * Läuft ohne zusätzliche Bibliothek. Läuft der Countdown ab, lädt die Seite
 * einmal neu, damit Banner und Buttons zur neuen Phase wechseln.
 */

if (!defined('ABSPATH')) exit;

add_shortcode('songcontest_countdown','scp_countdown');


/**
 * Wandelt einen Contest-Zeitpunkt (Ortszeit der Website) in echte Millisekunden um.
 */
function scp_countdown_target_ms($value){
    if(empty($value)) return 0;
    try{
        $dt = new DateTime($value, wp_timezone());
        return $dt->getTimestamp() * 1000;
    }catch(Exception $e){
        return 0;
    }
}


/**
 * Liefert Ziel und Beschriftung passend zur Phase (siehe scp_phase_banner_state()).
 */
function scp_countdown_for_state($state, $contest){

    if(!$contest) return null;

    $map = [
        'upcoming'    => ['application_start', 'Bewerbung startet in'],
        'application' => ['application_end',   'Bewerbungsschluss in'],
        'jury'        => ['top20_start',       'Voting startet in'],
        'top20'       => ['top20_end',         'Voting endet in'],
        'vauswertung' => ['vaus_end',          'Finalisten stehen fest in'],
        'pre_final'   => ['final_start',       'Live-Finale in'],
        'pre_winner'  => ['winner_date',       'Gewinner wird bekannt in'],
    ];

    if(!isset($map[$state])) return null;

    $field = $map[$state][0];
    if(empty($contest->$field)) return null;

    $ms = scp_countdown_target_ms($contest->$field);
    if($ms <= time() * 1000) return null;

    return ['ms' => $ms, 'label' => $map[$state][1]];
}


/**
 * HTML des Countdowns. Das kleine Skript wird nur einmal pro Seite ausgegeben.
 */
function scp_countdown_markup($target_ms, $label = ''){

    static $script_done = false;

    $target_ms = intval($target_ms);
    if($target_ms <= 0) return '';

    $diff = max(0, (int) floor(($target_ms / 1000) - time()));
    $d = (int) floor($diff / 86400);
    $h = (int) floor(($diff % 86400) / 3600);
    $m = (int) floor(($diff % 3600) / 60);
    $s = $diff % 60;

    ob_start(); ?>
    <div class="scp-cd" data-target="<?php echo esc_attr($target_ms); ?>" role="timer" aria-live="off">
        <?php if($label): ?><span class="scp-cd__label"><?php echo esc_html($label); ?></span><?php endif; ?>
        <span class="scp-cd__units">
            <span class="scp-cd__unit"><strong data-u="d"><?php echo esc_html($d); ?></strong><small>Tage</small></span>
            <span class="scp-cd__unit"><strong data-u="h"><?php echo esc_html(str_pad($h, 2, '0', STR_PAD_LEFT)); ?></strong><small>Std</small></span>
            <span class="scp-cd__unit"><strong data-u="m"><?php echo esc_html(str_pad($m, 2, '0', STR_PAD_LEFT)); ?></strong><small>Min</small></span>
            <span class="scp-cd__unit"><strong data-u="s"><?php echo esc_html(str_pad($s, 2, '0', STR_PAD_LEFT)); ?></strong><small>Sek</small></span>
        </span>
    </div>
    <?php if(!$script_done): $script_done = true; ?>
    <script>
    (function(){
        if(window.scpCountdownInit) return;
        window.scpCountdownInit = true;
        var loadedAt = Date.now();
        function pad(n){ return (n < 10 ? '0' : '') + n; }
        function tick(){
            var els = document.querySelectorAll('.scp-cd[data-target]');
            for(var i = 0; i < els.length; i++){
                var el = els[i];
                var left = Math.max(0, Math.floor((parseInt(el.getAttribute('data-target'), 10) - Date.now()) / 1000));
                var v = { d: Math.floor(left / 86400), h: pad(Math.floor(left % 86400 / 3600)), m: pad(Math.floor(left % 3600 / 60)), s: pad(left % 60) };
                for(var k in v){
                    var n = el.querySelector('[data-u="' + k + '"]');
                    if(n && n.textContent !== String(v[k])) n.textContent = v[k];
                }
                if(left === 0 && !el.getAttribute('data-done')){
                    el.setAttribute('data-done', '1');
                    /* Einmal neu laden, damit die neue Phase erscheint (nicht direkt nach dem Laden, um Schleifen zu vermeiden) */
                    if(Date.now() - loadedAt > 10000){ setTimeout(function(){ window.location.reload(); }, 3000); }
                }
            }
        }
        if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', tick); } else { tick(); }
        setInterval(tick, 1000);
    })();
    </script>
    <?php endif;

    return ob_get_clean();
}


/**
 * [songcontest_countdown] – Countdown bis zum nächsten Phasenwechsel des angezeigten Contests.
 */
function scp_countdown($atts = []){

    $atts = shortcode_atts(['label' => ''], $atts, 'songcontest_countdown');

    $contest_id = function_exists('scp_get_frontend_contest_id')
        ? scp_get_frontend_contest_id()
        : (function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 0);
    $contest = ($contest_id && function_exists('scp_get_contest')) ? scp_get_contest($contest_id) : null;

    // Bevorzugt: Ziel und Text passend zur erkannten Phase
    if(function_exists('scp_phase_banner_state')){
        $state = scp_phase_banner_state($contest);
        if($state === 'closed' && function_exists('scp_get_next_application_contest')){
            $next = scp_get_next_application_contest();
            if($next){ $contest = $next; $state = 'upcoming'; }
        }
        $cd = scp_countdown_for_state($state, $contest);
        if($cd){
            return scp_countdown_markup($cd['ms'], $atts['label'] !== '' ? $atts['label'] : $cd['label']);
        }
        return '';
    }

    // Rückfall: nächster zukünftiger Termin des Contests
    if(!$contest) return '';
    $fields = ['application_start','application_end','top20_start','top20_end','vaus_start','vaus_end','final_start','final_end','winner_date'];
    foreach($fields as $field){
        $ms = !empty($contest->$field) ? scp_countdown_target_ms($contest->$field) : 0;
        if($ms > time() * 1000){
            return scp_countdown_markup($ms, $atts['label']);
        }
    }
    return '';
}

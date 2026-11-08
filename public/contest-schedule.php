<?php
if (!defined('ABSPATH')) exit;

add_shortcode('songcontest_schedule', 'scp_contest_schedule_shortcode');

function scp_schedule_date($value, $with_time = false){
    if(empty($value)) return '';
    $ts = strtotime($value);
    if(!$ts) return '';
    return wp_date($with_time ? 'd.m.Y, H:i' : 'd.m.Y', $ts, wp_timezone());
}

function scp_schedule_range($start, $end, $with_time = false){
    $a = scp_schedule_date($start, $with_time);
    $b = scp_schedule_date($end, $with_time);
    if($a && $b) return $a . ' – ' . $b;
    return $a ?: $b;
}

function scp_contest_schedule_shortcode($atts = []){
    $atts = shortcode_atts(['contest_id' => 0], $atts, 'songcontest_schedule');
    $contest_id = intval($atts['contest_id']);
    if($contest_id <= 0) $contest_id = scp_get_current_contest_id();
    $c = scp_get_contest($contest_id);
    if(!$c) return '<p>Kein Contest gefunden.</p>';

    $jury_start = !empty($c->application_end) ? date('Y-m-d H:i:s', strtotime($c->application_end . ' +1 minute')) : '';
    $jury_end = !empty($c->top20_start) ? date('Y-m-d H:i:s', strtotime($c->top20_start . ' -1 minute')) : '';

    $rows = [
        ['1. Bewerbung', scp_schedule_range($c->application_start, $c->application_end), 'Bewerbung mit EPK über das Bewerbungsformular.'],
        ['2. Jury-Vorauswahl', scp_schedule_range($jury_start, $jury_end), 'Die eingereichten Titel werden anhand der Teilnahmevoraussetzungen geprüft und für das Online-Voting freigegeben.'],
        ['3. Online-Voting', scp_schedule_range($c->top20_start, $c->top20_end), 'Es kann jeden Tag mit einer verifizierten E-Mail-Adresse eine Stimme abgegeben werden.'],
        ['4. Finalisten ermitteln', scp_schedule_range($c->vaus_start, $c->vaus_end), 'Das Voting wird überprüft und die fünf Finalteilnehmer werden anhand der gültigen Publikumsstimmen ermittelt.'],
        ['5. Live-Finale', scp_schedule_range($c->final_start, $c->final_end, true), 'Die Top 5 treten beim Neuendorf Rockt Festival live gegeneinander an.'],
        ['6. Gewinner', scp_schedule_date($c->winner_date), 'Der Gewinner wird nach dem Finale aus der vorgesehenen Publikums- und Jurywertung ermittelt.'],
    ];

    ob_start(); ?>
    <div class="scp-contest-schedule" data-contest-id="<?php echo esc_attr($contest_id); ?>">
        <h2>Contest-Ablauf</h2>
        <?php foreach($rows as $row): ?>
            <div class="scp-schedule-step">
                <h3><?php echo esc_html($row[0]); ?></h3>
                <?php if($row[1]): ?><p><strong><?php echo esc_html($row[1]); ?></strong></p><?php endif; ?>
                <p><?php echo esc_html($row[2]); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <?php return ob_get_clean();
}

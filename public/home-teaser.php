<?php
/**
 * Shortcode [scp_home_teaser] – Contest-Streifen für die Startseite.
 *
 * Zeigt je nach Phase etwas anderes, damit sichtbar ist, dass sich etwas tut:
 *   upcoming     Start der Bewerbung + Countdown
 *   application  Zähler „X Bands im Rennen“ + die neuesten freigegebenen Bewerber
 *   jury         „Die Jury hört gerade X Bands“ + Countdown zum Voting
 *   top20        Live-Charts (Top 5) – aktualisiert sich automatisch
 *   vauswertung  Endstand des Votings (Top 5)
 *   pre_final / final / pre_winner   die Finalisten (ohne Punktestand – keine Vorwegnahme)
 *   winner       Gewinnerband
 *   closed       letzte Gewinnerband bzw. nichts (hide_closed="1")
 *
 * Der wechselnde Teil wird nach dem Laden einmal frisch über
 * /wp-json/scp/v1/home-teaser geholt (Seiten-Cache spielt keine Rolle)
 * und in der Voting-Phase alle `refresh` Sekunden aktualisiert.
 *
 * Attribute (alle optional):
 *   title        Überschrift                         (Standard: "PRO-BANDS Song-Contest")
 *   count        Anzahl Bands in Bewerbungsphase     (Standard: 4, max. 8)
 *   top          Anzahl Plätze in den Charts         (Standard: 5, max. 10)
 *   status       welche Bewerber gezeigt werden: "approved" (nur freigegebene, Standard)
 *                oder "all" (auch noch nicht geprüfte – nicht empfohlen)
 *   refresh      Sekunden zwischen Chart-Updates     (Standard: 45, min. 15)
 *   hide_closed  "1" = nichts ausgeben, wenn kein Contest läuft oder geplant ist
 *
 * Testen: Startseite mit ?testphase=application | top20 | vauswertung | final | winner | closed aufrufen.
 */

if (!defined('ABSPATH')) exit;

add_shortcode('scp_home_teaser', 'scp_home_teaser_shortcode');
add_action('rest_api_init', 'scp_home_teaser_register_route');


function scp_home_teaser_defaults(){
    return [
        'title'       => 'PRO-BANDS Song-Contest',
        'count'       => '4',
        'top'         => '5',
        'status'      => 'approved',
        'refresh'     => '45',
        'hide_closed' => '0',
    ];
}


function scp_home_teaser_clean_atts($atts){
    $a = shortcode_atts(scp_home_teaser_defaults(), (array) $atts, 'scp_home_teaser');
    $a['count']   = max(1, min(8, intval($a['count'])));
    $a['top']     = max(3, min(10, intval($a['top'])));
    $a['refresh'] = max(15, intval($a['refresh']));
    $a['status']  = $a['status'] === 'all' ? 'all' : 'approved';
    return $a;
}


/* =========================================================
   DATEN
========================================================= */

/**
 * Contest und Zustand wie beim [songcontest_phase_banner].
 */
function scp_home_teaser_context(){

    $contest_id = function_exists('scp_get_frontend_contest_id')
        ? scp_get_frontend_contest_id()
        : (function_exists('scp_get_current_contest_id') ? scp_get_current_contest_id() : 0);

    $contest = ($contest_id && function_exists('scp_get_contest')) ? scp_get_contest($contest_id) : null;
    $state   = function_exists('scp_phase_banner_state') ? scp_phase_banner_state($contest) : 'closed';

    if($state === 'closed' && function_exists('scp_get_next_application_contest')){
        $next = scp_get_next_application_contest();
        if($next && (!$contest || intval($next->id) !== intval($contest->id))){
            // Zwischen zwei Contests: den nächsten ankündigen, den letzten Gewinner merken.
            return ['contest' => $next, 'state' => 'upcoming', 'previous' => $contest];
        }
    }

    return ['contest' => $contest, 'state' => $state, 'previous' => null];
}


function scp_home_teaser_artist_fields(){
    return 'a.id, a.contest_id, a.name, a.title, a.genre, a.band_info, a.image, a.status, a.created_at';
}


/**
 * Daten für den wechselnden Teil. Für 30 Sekunden zwischengespeichert,
 * damit viele gleichzeitige Besucher die Datenbank nicht belasten.
 */
function scp_home_teaser_data($atts){

    $ctx     = scp_home_teaser_context();
    $contest = $ctx['contest'];
    $state   = $ctx['state'];
    $cid     = $contest ? intval($contest->id) : 0;

    $is_test = function_exists('scp_get_test_phase_override') && scp_get_test_phase_override();
    $key     = 'scp_home_teaser_' . md5(wp_json_encode([$cid, $state, $atts['count'], $atts['top'], $atts['status']]));

    if(!$is_test){
        $cached = get_transient($key);
        if(is_array($cached)) return $cached;
    }

    global $wpdb;
    $A = $wpdb->prefix . 'scp_artists';
    $V = $wpdb->prefix . 'scp_votes';
    $F = scp_home_teaser_artist_fields();

    $data = [
        'state'      => $state,
        'contest_id' => $cid,
        'total'      => 0,
        'bands'      => [],
        'chart'      => [],
        'winner'     => null,
    ];

    switch($state){

        case 'application':
        case 'jury':
            if($cid){
                $data['total'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $A WHERE contest_id=%d AND status NOT IN ('rejected','withdrawn')",
                    $cid
                ));
            }
            if($state === 'application' && $cid){
                $where = $atts['status'] === 'all'
                    ? "status NOT IN ('rejected','withdrawn')"
                    : "status IN ('approved','top20','finale','winner')";
                $data['bands'] = $wpdb->get_results($wpdb->prepare(
                    "SELECT $F FROM $A a WHERE a.contest_id=%d AND a.$where ORDER BY a.created_at DESC, a.id DESC LIMIT %d",
                    $cid, intval($atts['count'])
                ));
            }
            break;

        case 'top20':
        case 'vauswertung':
            if($cid){
                $statuses = $state === 'top20' ? "'top20'" : "'top20','finale'";
                $data['chart'] = $wpdb->get_results($wpdb->prepare(
                    "SELECT $F, COUNT(v.id) AS votes
                     FROM $A a
                     LEFT JOIN $V v ON v.artist_id = a.id AND v.contest_id = %d
                     WHERE a.contest_id = %d AND a.status IN ($statuses)
                     GROUP BY a.id
                     ORDER BY votes DESC, a.name ASC
                     LIMIT %d",
                    $cid, $cid, intval($atts['top'])
                ));
                $data['total'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $V WHERE contest_id=%d", $cid
                ));
            }
            break;

        case 'pre_final':
        case 'final':
        case 'pre_winner':
            if($cid){
                $data['bands'] = $wpdb->get_results($wpdb->prepare(
                    "SELECT $F FROM $A a WHERE a.contest_id=%d AND a.status IN ('finale','winner') ORDER BY a.name ASC LIMIT 10",
                    $cid
                ));
            }
            break;

        case 'winner':
        case 'upcoming':
        case 'closed':
            $wcid = $cid;
            if($state !== 'winner'){
                // Letzter Gewinner (vorheriger Contest oder zuletzt abgeschlossener)
                $wcid = !empty($ctx['previous']) ? intval($ctx['previous']->id) : 0;
            }
            $sql = $wcid
                ? $wpdb->prepare("SELECT $F FROM $A a WHERE a.contest_id=%d AND a.status='winner' ORDER BY a.id ASC LIMIT 1", $wcid)
                : "SELECT $F FROM $A a WHERE a.status='winner' ORDER BY a.contest_id DESC, a.id ASC LIMIT 1";
            $data['winner'] = $wpdb->get_row($sql);
            break;
    }

    if(!$is_test){
        set_transient($key, $data, 30);
    }

    return $data;
}


/* =========================================================
   AUSGABE
========================================================= */

function scp_home_teaser_img($url, $alt, $size = '250', $class = ''){

    $class = trim('scp-ht-img ' . $class);

    if(empty($url)){
        $letter = function_exists('mb_substr') ? mb_substr((string) $alt, 0, 1) : substr((string) $alt, 0, 1);
        return '<span class="' . esc_attr($class) . ' scp-ht-img--empty" aria-hidden="true">' . esc_html($letter) . '</span>';
    }

    $src    = function_exists('scp_image_url') ? scp_image_url($url, $size) : $url;
    $srcset = '';

    if($size === '250' && function_exists('scp_image_url')){
        $big = scp_image_url($url, '500');
        if($big !== $src){
            $srcset = ' srcset="' . esc_url($src) . ' 1x, ' . esc_url($big) . ' 2x"';
        }
    }

    return '<img class="' . esc_attr($class) . '" src="' . esc_url($src) . '"' . $srcset
         . ' alt="' . esc_attr($alt) . '" loading="lazy" decoding="async" width="' . intval($size) . '" height="' . intval($size) . '">';
}


function scp_home_teaser_band_url($band){
    return function_exists('scp_get_band_public_url')
        ? scp_get_band_public_url(intval($band->id), intval($band->contest_id))
        : '';
}


function scp_home_teaser_excerpt($text, $len = 110){
    $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $text)));
    if($text === '') return '';
    if(function_exists('mb_strlen') && mb_strlen($text) > $len){
        $cut = mb_substr($text, 0, $len);
        $sp  = mb_strrpos($cut, ' ');
        return ($sp > 60 ? mb_substr($cut, 0, $sp) : $cut) . ' …';
    }
    return $text;
}


function scp_home_teaser_band_card($band, $badge = ''){

    $url = scp_home_teaser_band_url($band);
    $tag = $url ? 'a' : 'div';
    $href = $url ? ' href="' . esc_url($url) . '"' : '';

    $meta = array_filter([(string) $band->genre, $band->title ? '„' . $band->title . '“' : '']);

    $h  = '<' . $tag . ' class="scp-ht-card"' . $href . ' data-band="' . intval($band->id) . '">';
    $h .= '<span class="scp-ht-card__media">' . scp_home_teaser_img($band->image, $band->name, '250');
    if($badge) $h .= '<span class="scp-ht-badge">' . esc_html($badge) . '</span>';
    $h .= '</span>';
    $h .= '<span class="scp-ht-card__body">';
    $h .= '<strong class="scp-ht-card__name">' . esc_html($band->name) . '</strong>';
    if($meta) $h .= '<span class="scp-ht-card__meta">' . esc_html(implode(' · ', $meta)) . '</span>';
    $ex = scp_home_teaser_excerpt($band->band_info);
    if($ex) $h .= '<span class="scp-ht-card__info">' . esc_html($ex) . '</span>';
    $h .= '</span></' . $tag . '>';

    return $h;
}


/**
 * HTML des wechselnden Teils (wird auch vom REST-Endpunkt geliefert).
 */
function scp_home_teaser_live_html($data){

    $state = $data['state'];
    $h = '';

    switch($state){

        case 'application':
            $total = intval($data['total']);
            $h .= '<p class="scp-ht-stat"><strong>' . esc_html(number_format_i18n($total)) . '</strong> '
                . esc_html($total === 1 ? 'Band ist im Rennen' : 'Bands sind im Rennen') . '</p>';

            if(!empty($data['bands'])){
                $h .= '<p class="scp-ht-sub">Neu dabei:</p><div class="scp-ht-grid">';
                $first = true;
                foreach($data['bands'] as $b){
                    $h .= scp_home_teaser_band_card($b, $first ? 'Neu' : '');
                    $first = false;
                }
                $h .= '</div>';
            }else{
                $h .= '<p class="scp-ht-sub">Noch ist Platz ganz vorne – sei eine der ersten Bands.</p>';
            }
            break;

        case 'jury':
            $total = intval($data['total']);
            $h .= '<p class="scp-ht-stat"><strong>' . esc_html(number_format_i18n($total)) . '</strong> '
                . esc_html($total === 1 ? 'Bewerbung liegt bei der Jury' : 'Bewerbungen liegen bei der Jury') . '</p>';
            break;

        case 'top20':
        case 'vauswertung':
            if(!empty($data['chart'])){
                $max = 0;
                foreach($data['chart'] as $r){ $max = max($max, intval($r->votes)); }

                $h .= '<ol class="scp-ht-chart">';
                $pos = 0;
                foreach($data['chart'] as $r){
                    $pos++;
                    $votes = intval($r->votes);
                    $pct   = $max > 0 ? round($votes / $max * 100) : 0;
                    $url   = scp_home_teaser_band_url($r);

                    $h .= '<li class="scp-ht-row' . ($pos === 1 ? ' is-first' : '') . '" data-band="' . intval($r->id) . '">';
                    $h .= '<span class="scp-ht-row__pos">' . $pos . '</span>';
                    $h .= scp_home_teaser_img($r->image, $r->name, '250', 'scp-ht-row__img');
                    $h .= '<span class="scp-ht-row__main">';
                    $h .= $url
                        ? '<a class="scp-ht-row__name" href="' . esc_url($url) . '">' . esc_html($r->name) . '</a>'
                        : '<span class="scp-ht-row__name">' . esc_html($r->name) . '</span>';
                    $h .= '<span class="scp-ht-bar"><span style="width:' . intval($pct) . '%"></span></span>';
                    $h .= '</span>';
                    $h .= '<span class="scp-ht-row__votes"><strong>' . esc_html(number_format_i18n($votes)) . '</strong> Votes</span>';
                    $h .= '</li>';
                }
                $h .= '</ol>';

                if(intval($data['total']) > 0){
                    $h .= '<p class="scp-ht-sub">' . esc_html(number_format_i18n(intval($data['total']))) . ' Stimmen bisher'
                        . ($state === 'top20' ? ' · <span class="scp-ht-live">live</span>' : ' · Endstand') . '</p>';
                }
            }else{
                $h .= '<p class="scp-ht-sub">Die Voting-Liste wird gerade vorbereitet.</p>';
            }
            break;

        case 'pre_final':
        case 'final':
        case 'pre_winner':
            if(!empty($data['bands'])){
                $h .= '<div class="scp-ht-grid">';
                foreach($data['bands'] as $b){
                    $h .= scp_home_teaser_band_card($b, 'Finalist');
                }
                $h .= '</div>';
            }
            break;

        case 'winner':
        case 'upcoming':
        case 'closed':
            if(!empty($data['winner'])){
                $w = $data['winner'];
                $url = scp_home_teaser_band_url($w);
                $label = $state === 'winner' ? 'Gewinner' : 'Letzter Gewinner';
                $h .= '<div class="scp-ht-winner">';
                $h .= '<span class="scp-ht-winner__media">' . scp_home_teaser_img($w->image, $w->name, '500', 'scp-ht-winner__img')
                    . '<span class="scp-ht-badge scp-ht-badge--gold">1</span></span>';
                $h .= '<span class="scp-ht-winner__body"><span class="scp-ht-sub">' . esc_html($label) . '</span>';
                $h .= '<strong class="scp-ht-winner__name">' . esc_html($w->name) . '</strong>';
                if($w->genre) $h .= '<span class="scp-ht-card__meta">' . esc_html($w->genre) . '</span>';
                if($url) $h .= '<a class="scp-ht-link" href="' . esc_url($url) . '">Zum Bandprofil →</a>';
                $h .= '</span></div>';
            }
            break;
    }

    return $h;
}


/**
 * Texte und Buttons je Zustand (fester Teil).
 */
function scp_home_teaser_frame($state, $contest){

    $f = function($field) use ($contest){
        return ($contest && !empty($contest->$field)) ? $contest->$field : '';
    };
    $date = function($v, $time = false){
        return function_exists('scp_phase_banner_date') ? scp_phase_banner_date($v, $time) : '';
    };

    $page = function($key, $fallback){
        return function_exists('scp_get_public_page_url') ? scp_get_public_page_url($key, home_url($fallback)) : home_url($fallback);
    };

    $info   = $page('contest', '/contest/');
    $apply  = $page('application', '/song-contest-bewerbung/');
    $voting = $page('voting', '/voting/');

    $eyebrow = ''; $text = ''; $buttons = [];

    switch($state){
        case 'upcoming':
            $start   = $date($f('application_start'));
            $eyebrow = 'Nächster Contest' . ($start ? ' · Bewerbung ab ' . $start : '');
            $text    = 'Zeig deinen Song, sammle Stimmen, gewinne einen Profi-Mix. Die Teilnahme ist kostenlos.';
            $buttons = [['Mehr erfahren', $info, 'primary']];
            break;
        case 'application':
            $eyebrow = 'Jetzt live · Bewerbung bis ' . $date($f('application_end'));
            $text    = 'Zeig deinen Song, sammle Stimmen, gewinne einen Profi-Mix. Die Teilnahme ist kostenlos.';
            $buttons = [['Jetzt bewerben', $apply, 'primary'], ['So läuft\'s', $info, 'outline']];
            break;
        case 'jury':
            $start   = $date($f('top20_start'));
            $eyebrow = 'Bewerbung geschlossen · Jury-Vorauswahl';
            $text    = 'Die Jury hört gerade alle Einsendungen.' . ($start ? ' Das Publikums-Voting startet am ' . $start . '.' : '');
            $buttons = [['Mehr erfahren', $info, 'outline']];
            break;
        case 'top20':
            $eyebrow = 'Jetzt live · Voting bis ' . $date($f('top20_end'));
            $text    = 'Die aktuellen Live-Charts – stimm jeden Tag für deinen Favoriten ab.';
            $buttons = [['Jetzt abstimmen', $voting, 'primary'], ['Mehr erfahren', $info, 'outline']];
            break;
        case 'vauswertung':
            $eyebrow = 'Voting beendet · Auswertung';
            $text    = 'Danke für alle Stimmen! Die Finalisten werden gerade ermittelt.';
            $buttons = [['Mehr erfahren', $info, 'outline']];
            break;
        case 'pre_final':
            $eyebrow = 'Live-Finale am ' . $date($f('final_start'), true);
            $text    = 'Diese Bands spielen live um den Sieg.';
            $buttons = [['Zum Finale', $info, 'primary']];
            break;
        case 'final':
            $eyebrow = 'Jetzt live · Finale';
            $text    = 'Die Finalisten spielen gerade live um den Sieg.';
            $buttons = [['Zum Finale', $info, 'primary']];
            break;
        case 'pre_winner':
            $eyebrow = 'Finale gespielt · Auswertung';
            $wd      = $date($f('winner_date'));
            $text    = 'Jury- und Publikumswertung werden zusammengeführt.' . ($wd ? ' Bekanntgabe am ' . $wd . '.' : '');
            $buttons = [['Mehr erfahren', $info, 'outline']];
            break;
        case 'winner':
            $eyebrow = 'Contest beendet · Der Gewinner steht fest';
            $text    = 'Danke an alle Bands und alle, die abgestimmt haben.';
            $buttons = [['Zum Contest', $info, 'primary']];
            break;
        default:
            $eyebrow = 'Pause zwischen den Contests';
            $text    = 'Der nächste Song-Contest ist in Planung.';
            $buttons = [['Vergangene Contests', $info, 'outline']];
    }

    return [$eyebrow, $text, $buttons];
}


function scp_home_teaser_shortcode($atts = []){

    $atts = scp_home_teaser_clean_atts($atts);
    $ctx  = scp_home_teaser_context();

    $state   = $ctx['state'];
    $contest = $ctx['contest'];

    if($state === 'closed' && $atts['hide_closed'] === '1'){
        return '';
    }

    $data = scp_home_teaser_data($atts);
    list($eyebrow, $text, $buttons) = scp_home_teaser_frame($state, $contest);

    $cd = function_exists('scp_countdown_for_state') ? scp_countdown_for_state($state, $contest) : null;

    // Skript nur, wenn der Shortcode tatsächlich auf der Seite steht.
    wp_enqueue_script('scp-home-teaser', SCP_URL . 'assets/home-teaser.js', [], SCP_VERSION, true);

    $config = [
        'endpoint' => esc_url_raw(rest_url('scp/v1/home-teaser')),
        'args'     => [
            'count'  => $atts['count'],
            'top'    => $atts['top'],
            'status' => $atts['status'],
        ],
        'state'    => $state,
        'poll'     => $state === 'top20' ? $atts['refresh'] : 0,
    ];

    if(function_exists('scp_get_test_phase_override') && scp_get_test_phase_override()){
        $config['args']['testphase'] = scp_get_test_phase_override();
    }

    ob_start(); ?>
    <section class="scp-home-teaser scp-home-teaser--<?php echo esc_attr($state); ?>"
             data-config="<?php echo esc_attr(wp_json_encode($config)); ?>">
        <div class="scp-ht-head">
            <div class="scp-ht-head__text">
                <p class="pb-eyebrow scp-ht-eyebrow"><?php echo esc_html($eyebrow); ?></p>
                <h2 class="scp-ht-title"><?php echo esc_html($atts['title']); ?></h2>
                <p class="scp-ht-text"><?php echo esc_html($text); ?></p>
            </div>
            <?php if($cd): ?>
                <div class="scp-ht-head__cd"><?php echo scp_countdown_markup($cd['ms'], $cd['label']); ?></div>
            <?php endif; ?>
        </div>

        <div class="scp-ht-body" data-scp-ht-live><?php echo scp_home_teaser_live_html($data); ?></div>

        <?php if(!empty($buttons)): ?>
        <div class="pb-btn-group scp-ht-buttons">
            <?php foreach($buttons as $b): ?>
                <a class="pb-btn<?php echo $b[2] === 'outline' ? ' pb-btn--outline' : ''; ?>" href="<?php echo esc_url($b[1]); ?>"><?php echo esc_html($b[0]); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}


/* =========================================================
   REST-ENDPUNKT  GET /wp-json/scp/v1/home-teaser
========================================================= */

function scp_home_teaser_register_route(){
    register_rest_route('scp/v1', '/home-teaser', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => 'scp_home_teaser_rest',
        'args'                => [
            'count'     => ['type' => 'integer', 'default' => 4],
            'top'       => ['type' => 'integer', 'default' => 5],
            'status'    => ['type' => 'string',  'default' => 'approved'],
            'testphase' => ['type' => 'string',  'default' => ''],
        ],
    ]);
}


function scp_home_teaser_rest(WP_REST_Request $req){

    // ?testphase= wie beim Seitenaufruf (nur Anzeige, ändert keine Daten)
    $test = sanitize_key((string) $req->get_param('testphase'));
    if($test !== ''){
        $_GET['testphase'] = $test;
    }

    $atts = scp_home_teaser_clean_atts([
        'count'  => $req->get_param('count'),
        'top'    => $req->get_param('top'),
        'status' => $req->get_param('status'),
    ]);

    $data = scp_home_teaser_data($atts);

    $res = new WP_REST_Response([
        'state' => $data['state'],
        'html'  => scp_home_teaser_live_html($data),
    ]);

    // Kurz cachebar für CDNs/Proxys, aber nie lange veraltet.
    $res->header('Cache-Control', 'public, max-age=20');

    return $res;
}

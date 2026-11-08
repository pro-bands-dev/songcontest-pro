<?php
/**
 * Öffentliche Seiten des Contests (feste Adressen statt Contest-Slug).
 *
 * Bisher bauten Links (Banner, Charts, Band-Profile, E-Mails) ihre Adresse aus
 * dem Slug des Contests, z. B. /song-contest-2027/. Gibt es dazu keine
 * WordPress-Seite, landet man auf „Seite nicht gefunden“.
 *
 * Jetzt gilt:
 *  1. Im Backend festgelegte Seite (Song Contest → Seiten & Links)
 *  2. sonst automatisch erkannt (Seite mit passendem Shortcode bzw. Adresse)
 *  3. sonst das bisherige Verhalten
 *
 * Inhalte kommen weiterhin immer aus dem aktuell öffentlichen Contest.
 * Alte Contest-Adressen (/song-contest-2027/, …/band/, …/song-contest-voting/)
 * werden per 301 auf die festen Seiten umgeleitet, wenn sie nicht existieren.
 */

if (!defined('ABSPATH')) exit;


function scp_public_page_definitions(){
    return [
        'contest'     => ['label' => 'Contest-Seite',        'path' => 'contest',                'shortcode' => ''],
        'application' => ['label' => 'Bewerbungsseite',      'path' => 'song-contest-bewerbung', 'shortcode' => 'songcontest_application'],
        'voting'      => ['label' => 'Voting-Seite',         'path' => 'voting',                 'shortcode' => 'songcontest_voting'],
        'band'        => ['label' => 'Band-Profil-Seite',    'path' => 'contest/band',           'shortcode' => 'songcontest_band_profile'],
        'jury'        => ['label' => 'Jury-Seite (Vorauswahl)', 'path' => 'jury-vorauswahl',     'shortcode' => 'songcontest_jury_preselect'],
    ];
}


/**
 * Sucht eine veröffentlichte Seite, die den Shortcode enthält (Ergebnis 12 h zwischengespeichert).
 */
function scp_find_page_with_shortcode($shortcode){
    if(empty($shortcode)) return 0;

    $key = 'scp_page_sc_' . md5($shortcode);
    $cached = get_transient($key);
    if($cached !== false) return intval($cached);

    global $wpdb;
    $id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type='page' AND post_status='publish'
             AND post_content LIKE %s
             ORDER BY ID ASC LIMIT 1",
            '%' . $wpdb->esc_like('[' . $shortcode) . '%'
        )
    );
    $id = intval($id);
    set_transient($key, $id, 12 * HOUR_IN_SECONDS);
    return $id;
}


/**
 * Seiten-ID für einen Bereich (0 = keine gefunden).
 */
function scp_get_public_page_id($key){

    $defs = scp_public_page_definitions();
    if(!isset($defs[$key])) return 0;

    // 1. Backend-Einstellung
    $saved = get_option('scp_public_pages', []);
    if(is_array($saved) && !empty($saved[$key])){
        $id = intval($saved[$key]);
        if($id > 0 && get_post_status($id) === 'publish') return $id;
    }

    // 2. Band-Profil: feste Adresse /contest/band/ hat Vorrang vor der Shortcode-Suche,
    //    damit eine alte Seite (z. B. /song-contest-2026/band/) nicht mehr gewinnt.
    if($key === 'band' && !empty($defs[$key]['path'])){
        $page = get_page_by_path($defs[$key]['path']);
        if($page && $page->post_status === 'publish') return intval($page->ID);
    }

    // 2a. Seite mit passendem Shortcode
    if(!empty($defs[$key]['shortcode'])){
        $id = scp_find_page_with_shortcode($defs[$key]['shortcode']);
        if($id > 0) return $id;
    }

    // 2b. Seite unter der bekannten Adresse
    if(!empty($defs[$key]['path'])){
        $page = get_page_by_path($defs[$key]['path']);
        if($page && $page->post_status === 'publish') return intval($page->ID);
    }

    return 0;
}


/**
 * Adresse einer öffentlichen Seite oder $fallback.
 */
function scp_get_public_page_url($key, $fallback = ''){
    $id = scp_get_public_page_id($key);
    if($id > 0){
        $url = get_permalink($id);
        if($url) return $url;
    }
    return $fallback;
}


/* Zwischenspeicher leeren, wenn Seiten geändert werden */
function scp_public_pages_flush_cache($post_id = 0){
    if($post_id && get_post_type($post_id) !== 'page') return;
    foreach(scp_public_page_definitions() as $def){
        if(!empty($def['shortcode'])){
            delete_transient('scp_page_sc_' . md5($def['shortcode']));
        }
    }
}
add_action('save_post', 'scp_public_pages_flush_cache');
add_action('deleted_post', 'scp_public_pages_flush_cache');
add_action('trashed_post', 'scp_public_pages_flush_cache');


/**
 * Alte Contest-Adressen umleiten, aber nur, wenn WordPress dort nichts findet.
 */
function scp_redirect_legacy_contest_urls(){

    // Alte Band-Profil-Links mit ?band= immer prüfen – WordPress liefert dort
    // nicht zwingend 404 (z. B. wenn ein Medium „band“ heißt).
    $is_band_link = isset($_GET['band']);

    if(!is_404() && !$is_band_link) return;

    global $wpdb;

    $path = trim((string) parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH), '/');
    if($path === '') return;

    // Installation in Unterordner berücksichtigen
    $home_path = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
    if($home_path !== '' && strpos($path, $home_path . '/') === 0){
        $path = substr($path, strlen($home_path) + 1);
    }

    $slugs = (array) $wpdb->get_col("SELECT slug FROM {$wpdb->prefix}scp_contests WHERE slug <> ''");

    $target = '';

    // Alte Band-Profil-Adressen (/band/, /<irgendwas>/band/) mit ?band=… → aktuelle Band-Profil-Seite
    if(isset($_GET['band']) && ($path === 'band' || substr($path, -5) === '/band')){
        $target = scp_get_public_page_url('band');
    }

    // Ab hier nur noch echte 404-Seiten (alte Contest-Adressen)
    if(!$target && !is_404()) return;

    foreach($slugs as $slug){
        if($target) break;
        $slug = sanitize_title($slug);
        if($path === $slug){
            $target = scp_get_public_page_url('contest');
        } elseif($path === $slug . '/band'){
            $target = scp_get_public_page_url('band');
        } elseif($path === $slug . '/song-contest-voting'){
            $target = scp_get_public_page_url('voting');
        }
        if($target) break;
    }

    if(!$target) return;

    // Keine Umleitung auf sich selbst
    $target_path = trim((string) parse_url($target, PHP_URL_PATH), '/');
    if($home_path !== '' && strpos($target_path, $home_path . '/') === 0){
        $target_path = substr($target_path, strlen($home_path) + 1);
    }
    if($target_path === $path) return;

    // Parameter (z. B. ?band=12&ref=3) übernehmen
    if(!empty($_GET)){
        $args = array_map('sanitize_text_field', wp_unslash($_GET));
        $target = add_query_arg($args, $target);
    }

    wp_safe_redirect($target, 301);
    exit;
}
add_action('template_redirect', 'scp_redirect_legacy_contest_urls', 5);


/**
 * Gibt es noch eine zweite (alte) Seite mit [songcontest_band_profile],
 * z. B. /song-contest-2026/band/, wird sie per 301 auf die aktuelle
 * Band-Profil-Seite (/contest/band/) umgeleitet – inkl. ?band= und ?ref=.
 */
function scp_redirect_duplicate_band_page(){

    if(!is_page() || is_admin()) return;

    $current = get_queried_object();
    if(!($current instanceof WP_Post)) return;

    $band_id = scp_get_public_page_id('band');
    if(!$band_id || intval($current->ID) === $band_id) return;

    if(strpos((string) $current->post_content, '[songcontest_band_profile') === false) return;

    // Im Divi-Builder / in der Vorschau nicht umleiten
    if(isset($_GET['et_fb']) || isset($_GET['preview']) || is_preview()) return;

    $target = get_permalink($band_id);
    if(!$target) return;

    if(!empty($_GET)){
        $args = array_map('sanitize_text_field', wp_unslash($_GET));
        $target = add_query_arg($args, $target);
    }

    wp_safe_redirect($target, 301);
    exit;
}
add_action('template_redirect', 'scp_redirect_duplicate_band_page', 6);


/* =========================================================
   Backend: Song Contest → Seiten & Links
   ========================================================= */

function scp_public_pages_admin_menu(){
    add_submenu_page(
        'songcontest-dashboard',
        'Seiten & Links',
        'Seiten & Links',
        'manage_options',
        'songcontest-public-pages',
        'scp_public_pages_admin_page'
    );
}
add_action('admin_menu', 'scp_public_pages_admin_menu', 20);


function scp_public_pages_admin_page(){

    if(!current_user_can('manage_options')) return;

    $defs = scp_public_page_definitions();

    if(isset($_POST['scp_public_pages_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scp_public_pages_nonce'])), 'scp_public_pages_save')){
        $new = [];
        foreach($defs as $key => $def){
            $new[$key] = isset($_POST['scp_page'][$key]) ? intval($_POST['scp_page'][$key]) : 0;
        }
        update_option('scp_public_pages', $new);
        scp_public_pages_flush_cache();
        echo '<div class="notice notice-success is-dismissible"><p>Seiten gespeichert.</p></div>';
    }

    $saved = get_option('scp_public_pages', []);
    ?>
    <div class="wrap">
        <h1>Seiten &amp; Links</h1>
        <p>Diese Seiten verwenden alle Links des Contests – Banner, Charts, Band-Profile und E-Mails.
           Die Inhalte kommen immer aus dem aktuell öffentlichen Contest.<br>
           „Automatisch“ sucht eine Seite mit dem passenden Shortcode bzw. unter der üblichen Adresse.</p>

        <form method="post">
            <?php wp_nonce_field('scp_public_pages_save', 'scp_public_pages_nonce'); ?>
            <table class="form-table" role="presentation">
                <?php foreach($defs as $key => $def):
                    $current = isset($saved[$key]) ? intval($saved[$key]) : 0;
                    $active_url = scp_get_public_page_url($key);
                ?>
                <tr>
                    <th scope="row"><label for="scp_page_<?php echo esc_attr($key); ?>"><?php echo esc_html($def['label']); ?></label></th>
                    <td>
                        <?php wp_dropdown_pages([
                            'name'              => 'scp_page[' . $key . ']',
                            'id'                => 'scp_page_' . $key,
                            'selected'          => $current,
                            'show_option_none'  => '— Automatisch —',
                            'option_none_value' => '0',
                            'post_status'       => 'publish',
                        ]); ?>
                        <p class="description">
                            <?php if($def['shortcode']): ?>Erkennung über <code>[<?php echo esc_html($def['shortcode']); ?>]</code>. <?php endif; ?>
                            Aktuell verwendet:
                            <?php if($active_url): ?>
                                <a href="<?php echo esc_url($active_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($active_url); ?></a>
                            <?php else: ?>
                                <em>keine Seite gefunden – bisheriges Verhalten</em>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php submit_button('Speichern'); ?>
        </form>
        <?php if(function_exists('scp_contest_jury_pages_admin_section')) scp_contest_jury_pages_admin_section(); ?>
    </div>
    <?php
}


/* =========================================================
   Jury-Vorstellung je Contest (öffentliche Info-Seite für Bands)
   z. B. /contest/jury2027/ oder /song-contest-2026/jury/
   ========================================================= */

/**
 * Seiten-ID der Jury-Vorstellung eines Contests (0 = keine).
 * 1. Auswahl unter „Seiten & Links“  2. automatisch über übliche Adressen
 */
function scp_get_contest_jury_page_id($contest_id = 0){

    $contest_id = intval($contest_id);
    if($contest_id <= 0){
        $contest_id = function_exists('scp_get_frontend_contest_id') ? scp_get_frontend_contest_id() : 0;
    }
    if($contest_id <= 0) return 0;

    $saved = get_option('scp_contest_jury_pages', []);
    if(is_array($saved) && !empty($saved[$contest_id])){
        $id = intval($saved[$contest_id]);
        if($id > 0 && get_post_status($id) === 'publish') return $id;
    }

    $contest = function_exists('scp_get_contest') ? scp_get_contest($contest_id) : null;
    if(!$contest) return 0;

    $year = !empty($contest->year) ? intval($contest->year) : 0;
    if(!$year && preg_match('/(20\d{2})/', (string) $contest->slug . ' ' . (string) $contest->name, $m)){
        $year = intval($m[1]);
    }

    $candidates = [];
    if($year){
        $candidates[] = 'contest/jury' . $year;
        $candidates[] = 'contest/jury-' . $year;
        $candidates[] = 'jury' . $year;
    }
    if(!empty($contest->slug)){
        $candidates[] = sanitize_title($contest->slug) . '/jury';
    }

    foreach($candidates as $path){
        $page = get_page_by_path($path);
        if($page && $page->post_status === 'publish') return intval($page->ID);
    }
    return 0;
}

function scp_get_contest_jury_page_url($contest_id = 0){
    $id = scp_get_contest_jury_page_id($contest_id);
    return $id ? get_permalink($id) : '';
}


/**
 * [songcontest_jury_link] – Link/Button zur Jury-Vorstellung des aktiven Contests.
 * Attribute: label, class, contest_id. Gibt nichts aus, wenn keine Seite hinterlegt ist.
 */
function scp_jury_link_shortcode($atts = []){
    $atts = shortcode_atts([
        'label'      => 'Die Jury kennenlernen',
        'class'      => 'pb-btn pb-btn--outline',
        'contest_id' => 0,
    ], $atts, 'songcontest_jury_link');

    $url = scp_get_contest_jury_page_url(intval($atts['contest_id']));
    if(!$url) return '';

    return '<a class="' . esc_attr($atts['class']) . '" href="' . esc_url($url) . '">' . esc_html($atts['label']) . '</a>';
}
add_shortcode('songcontest_jury_link', 'scp_jury_link_shortcode');


/* Backend: Auswahl je Contest auf „Seiten & Links“ */
function scp_contest_jury_pages_admin_section(){

    if(!current_user_can('manage_options') || !function_exists('scp_get_contests')) return;

    if(isset($_POST['scp_jury_pages_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scp_jury_pages_nonce'])), 'scp_jury_pages_save')){
        $new = [];
        if(!empty($_POST['scp_jury_page']) && is_array($_POST['scp_jury_page'])){
            foreach(wp_unslash($_POST['scp_jury_page']) as $cid => $pid){
                $cid = intval($cid); $pid = intval($pid);
                if($cid > 0 && $pid > 0) $new[$cid] = $pid;
            }
        }
        update_option('scp_contest_jury_pages', $new);
        echo '<div class="notice notice-success is-dismissible"><p>Jury-Seiten gespeichert.</p></div>';
    }

    $saved    = get_option('scp_contest_jury_pages', []);
    $contests = scp_get_contests();
    ?>
    <h2 style="margin-top:40px">Jury-Vorstellung je Contest</h2>
    <p>Die öffentliche Seite, auf der Bands die Jurymitglieder kennenlernen.
       Wird z. B. in der Phasen-Leiste und mit <code>[songcontest_jury_link]</code> verlinkt.<br>
       „Automatisch“ sucht /contest/jury&lt;Jahr&gt;/ bzw. /&lt;contest-slug&gt;/jury/.</p>
    <form method="post">
        <?php wp_nonce_field('scp_jury_pages_save', 'scp_jury_pages_nonce'); ?>
        <table class="form-table" role="presentation">
        <?php foreach((array) $contests as $c):
            $cid = intval($c->id);
            $url = scp_get_contest_jury_page_url($cid);
        ?>
            <tr>
                <th scope="row"><label for="scp_jury_page_<?php echo $cid; ?>"><?php echo esc_html('#' . $cid . ' – ' . $c->name); ?></label></th>
                <td>
                    <?php wp_dropdown_pages([
                        'name'              => 'scp_jury_page[' . $cid . ']',
                        'id'                => 'scp_jury_page_' . $cid,
                        'selected'          => isset($saved[$cid]) ? intval($saved[$cid]) : 0,
                        'show_option_none'  => '— Automatisch —',
                        'option_none_value' => '0',
                        'post_status'       => 'publish',
                    ]); ?>
                    <p class="description">Aktuell verwendet:
                        <?php if($url): ?><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html($url); ?></a>
                        <?php else: ?><em>keine Seite gefunden</em><?php endif; ?>
                    </p>
                </td>
            </tr>
        <?php endforeach; ?>
        </table>
        <?php submit_button('Jury-Seiten speichern'); ?>
    </form>
    <?php
}

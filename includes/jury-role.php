<?php
/**
 * Jury-Rolle und Jury-Bereich.
 *
 * - Eigene WordPress-Rolle „Jury“ (scp_jury) mit dem Recht „scp_jury“.
 *   Administratoren erhalten das Recht ebenfalls.
 *   Bereits vorhandene Rollen mit „jury“ im Namen bekommen es auch,
 *   damit bestehende Jury-Konten weiter funktionieren.
 * - Jurymitglieder sehen im Backend nur „Jury“ (Vorauswahl + Dashboard)
 *   und ihr Profil – keine Beiträge, Seiten oder Einstellungen.
 * - Vorauswahl, Jury-Wertung und das Speichern der Auswahl prüfen auf
 *   das Jury-Recht statt nur auf „eingeloggt“.
 * - Nach dem Login landen Jurymitglieder auf der Jury-Seite der Website
 *   (z. B. /jury-vorauswahl/), sonst im Jury-Bereich des Backends.
 *
 * Bestehende Wertungen und Auswahlen bleiben unverändert.
 */

if (!defined('ABSPATH')) exit;

define('SCP_JURY_ROLE_VERSION', '1');


/* ---------------------------------------------------------
   Rolle einrichten (auch nach Updates per FTP/GitHub)
   --------------------------------------------------------- */
function scp_jury_setup_role(){

    if(get_option('scp_jury_role_version') === SCP_JURY_ROLE_VERSION && get_role('scp_jury')){
        return;
    }

    if(!get_role('scp_jury')){
        add_role('scp_jury', 'Jury', ['read' => true, 'scp_jury' => true]);
    } else {
        get_role('scp_jury')->add_cap('scp_jury');
    }

    $admin = get_role('administrator');
    if($admin){
        $admin->add_cap('scp_jury');
    }

    // Vorhandene Jury-Rollen (z. B. per Rollen-Plugin angelegt) einbeziehen
    foreach(wp_roles()->roles as $slug => $data){
        $name = isset($data['name']) ? (string) $data['name'] : '';
        if(stripos($slug, 'jury') !== false || stripos($name, 'jury') !== false){
            $role = get_role($slug);
            if($role) $role->add_cap('scp_jury');
        }
    }

    update_option('scp_jury_role_version', SCP_JURY_ROLE_VERSION);
}
add_action('init', 'scp_jury_setup_role', 5);


/**
 * Ist der Nutzer Jurymitglied (oder Administrator)?
 */
function scp_user_is_jury($user_id = 0){
    $user_id = $user_id ? intval($user_id) : get_current_user_id();
    if(!$user_id) return false;
    return user_can($user_id, 'scp_jury') || user_can($user_id, 'manage_options');
}

/**
 * Jurymitglied ohne Administratorrechte?
 */
function scp_user_is_jury_only($user_id = 0){
    $user_id = $user_id ? intval($user_id) : get_current_user_id();
    return $user_id && user_can($user_id, 'scp_jury') && !user_can($user_id, 'manage_options');
}


/* ---------------------------------------------------------
   Frontend: Jury-Shortcodes nur für die Jury
   --------------------------------------------------------- */
function scp_jury_guard_shortcodes($output, $tag){

    if(!in_array($tag, ['songcontest_jury_preselect', 'songcontest_jury_voting'], true)){
        return $output;
    }
    if(scp_user_is_jury()){
        return $output;
    }

    $here = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');

    if(!is_user_logged_in()){
        return '<div class="scp-contest-message"><p><strong>Jury-Bereich</strong></p>'
             . '<p>Bitte melde dich mit deinem Jury-Zugang an.</p>'
             . '<p><a class="pb-btn" href="' . esc_url(wp_login_url($here)) . '">Als Jury anmelden</a></p></div>';
    }

    return '<div class="scp-contest-message"><p><strong>Jury-Bereich</strong></p>'
         . '<p>Dein Konto ist nicht als Jurymitglied freigeschaltet. Bitte wende dich an PRO-BANDS.</p></div>';
}
add_filter('pre_do_shortcode_tag', 'scp_jury_guard_shortcodes', 10, 2);


/* ---------------------------------------------------------
   AJAX: Auswahl und Wertung nur für die Jury
   (läuft vor den bestehenden Funktionen und bricht sonst ab)
   --------------------------------------------------------- */
function scp_jury_guard_ajax(){
    if(!scp_user_is_jury()){
        wp_send_json_error('Nur Jurymitglieder dürfen hier auswählen bzw. bewerten.');
    }
}
add_action('wp_ajax_scp_toggle_jury_pick', 'scp_jury_guard_ajax', 1);
add_action('wp_ajax_scp_jury_vote',        'scp_jury_guard_ajax', 1);


/* ---------------------------------------------------------
   Backend: eigener Jury-Bereich
   --------------------------------------------------------- */
function scp_jury_admin_menu(){

    // Administratoren nutzen weiterhin „Song Contest“ – dort zusätzlich die Jury-Ansicht
    if(current_user_can('manage_options')){
        add_submenu_page(
            'songcontest-dashboard',
            'Jury-Ansicht',
            'Jury-Ansicht',
            'manage_options',
            'songcontest-jury-view',
            'scp_jury_admin_preselect_page'
        );
        return;
    }

    if(!current_user_can('scp_jury')) return;

    add_menu_page('Jury', 'Jury', 'scp_jury', 'scp-jury', 'scp_jury_admin_preselect_page', 'dashicons-awards', 3);
    add_submenu_page('scp-jury', 'Vorauswahl', 'Vorauswahl', 'scp_jury', 'scp-jury', 'scp_jury_admin_preselect_page');
    if(function_exists('scp_jury_dashboard_page')){
        add_submenu_page('scp-jury', 'Jury Dashboard', 'Jury Dashboard', 'scp_jury', 'songcontest-jury-member', 'scp_jury_dashboard_page');
    }
}
add_action('admin_menu', 'scp_jury_admin_menu', 30);


/**
 * Jury Dashboard unter „Song Contest“ nur noch für Administratoren
 * (Jurymitglieder erreichen es über das Menü „Jury“).
 */
function scp_jury_restrict_song_contest_menu(){
    if(!current_user_can('manage_options')){
        remove_submenu_page('songcontest-dashboard', 'songcontest-jury');
        remove_menu_page('songcontest-dashboard');
    }
}
add_action('admin_menu', 'scp_jury_restrict_song_contest_menu', 99);


/**
 * Vorauswahl im Backend – dieselbe Ansicht wie auf der Website.
 */
function scp_jury_admin_preselect_page(){

    if(!scp_user_is_jury()){
        wp_die('Keine Berechtigung.');
    }

    $contest_id = function_exists('scp_get_frontend_contest_id') ? scp_get_frontend_contest_id() : 0;
    $page_url   = function_exists('scp_get_public_page_url') ? scp_get_public_page_url('jury') : '';
    $contest    = ($contest_id && function_exists('scp_get_contest')) ? scp_get_contest($contest_id) : null;
    ?>
    <div class="wrap">
        <h1>Jury-Vorauswahl<?php echo $contest ? ' – ' . esc_html($contest->name) : ''; ?></h1>
        <p>Hör dir die Bewerbungen an und wähle deine Favoriten für das Voting aus. Deine Auswahl wird sofort gespeichert.</p>
        <?php if($page_url): ?>
            <p><a class="button" href="<?php echo esc_url($page_url); ?>" target="_blank" rel="noopener">Vorauswahl auf der Website öffnen</a></p>
        <?php endif; ?>
        <div class="scp-jury-admin-stage">
            <?php
            if(function_exists('scp_jury_preselect')){
                echo scp_jury_preselect(['contest_id' => $contest_id]); // gibt bereits escaptes HTML aus
            }
            ?>
        </div>
    </div>
    <?php
}


/* Stylesheet der öffentlichen Seiten für die Jury-Ansicht im Backend */
function scp_jury_admin_assets($hook){
    if(strpos((string) $hook, 'scp-jury') === false && strpos((string) $hook, 'songcontest-jury-view') === false){
        return;
    }
    wp_enqueue_style('scp-style-public', SCP_URL . 'assets/style.css', [], SCP_VERSION);
    wp_add_inline_style('scp-style-public',
        '.scp-jury-admin-stage{background:#0B0B0B;padding:24px;margin-top:16px}'
    );
}
add_action('admin_enqueue_scripts', 'scp_jury_admin_assets', 20);


/* WordPress-Dashboard und unnötige Menüs für reine Jury-Konten ausblenden */
function scp_jury_trim_admin(){
    if(!scp_user_is_jury_only()) return;
    remove_menu_page('index.php');
    remove_menu_page('edit.php');
    remove_menu_page('edit-comments.php');
    remove_menu_page('tools.php');
}
add_action('admin_menu', 'scp_jury_trim_admin', 999);

function scp_jury_redirect_dashboard(){
    global $pagenow;
    if($pagenow === 'index.php' && scp_user_is_jury_only() && !wp_doing_ajax()){
        wp_safe_redirect(admin_url('admin.php?page=scp-jury'));
        exit;
    }
}
add_action('admin_init', 'scp_jury_redirect_dashboard');


/* Nach dem Login: Jury-Seite der Website, sonst Jury-Bereich im Backend */
function scp_jury_login_redirect($redirect_to, $requested, $user){
    if(!($user instanceof WP_User) || !user_can($user, 'scp_jury') || user_can($user, 'manage_options')){
        return $redirect_to;
    }
    // Ausdrücklich angefragte Seite (z. B. von der Jury-Seite aus) beibehalten
    if(!empty($requested) && strpos($requested, 'wp-admin') === false){
        return $requested;
    }
    $page = function_exists('scp_get_public_page_url') ? scp_get_public_page_url('jury') : '';
    return $page ?: admin_url('admin.php?page=scp-jury');
}
add_filter('login_redirect', 'scp_jury_login_redirect', 10, 3);


/* Jury-Seite nicht in Suchmaschinen */
function scp_jury_page_noindex($robots){
    if(!function_exists('scp_get_public_page_id')) return $robots;
    $id = scp_get_public_page_id('jury');
    if($id && is_page($id)){
        $robots['noindex']  = true;
        $robots['nofollow'] = true;
    }
    return $robots;
}
add_filter('wp_robots', 'scp_jury_page_noindex');

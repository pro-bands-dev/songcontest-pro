<?php
/**
 * Bandfotos und Audio der Bewerbungen.
 *
 * Bilder: Beim Upload bleiben das Original (Band-Profil, Gewinnerseite) und
 * zusätzlich zwei quadratische, mittig zugeschnittene Versionen erhalten:
 *   - 500 × 500 px  für Voting-Listen, Jury, Band-Grid, Finale
 *   - 250 × 250 px  als Thumbnail (Backend, Startseiten-Teaser)
 *
 * Die Versionen liegen neben dem Original:  bandfoto.jpg
 *                                           bandfoto-scp500.jpg
 *                                           bandfoto-scp250.jpg
 * In der Datenbank steht weiterhin nur die URL des Originals (Spalte "image").
 * Fehlt eine Version, wird automatisch das Original ausgegeben.
 *
 * Audio: WAV wird bereits im Browser der Band in MP3 umgewandelt
 * (assets/audio-convert.js). Der Server nimmt nur MP3 an.
 */

if (!defined('ABSPATH')) exit;


function scp_image_variant_sizes(){
    return [
        '500' => 500,
        '250' => 250,
    ];
}


/**
 * Dateipfad zu einer Upload-URL (leer, wenn die Datei nicht in wp-content/uploads liegt).
 */
function scp_upload_url_to_path($url){

    $url = (string) $url;
    if($url === '') return '';

    $uploads = wp_get_upload_dir();
    if(empty($uploads['baseurl']) || empty($uploads['basedir'])) return '';

    // http/https und www. ignorieren
    $strip = function($u){
        return preg_replace('#^https?://(www\.)?#i', '', $u);
    };

    $base = rtrim($strip($uploads['baseurl']), '/') . '/';
    $rel  = $strip(strtok($url, '?'));

    if(strpos($rel, $base) !== 0) return '';

    $relative = substr($rel, strlen($base));

    // Keine Pfad-Tricks
    if(strpos($relative, '..') !== false) return '';

    return trailingslashit($uploads['basedir']) . rawurldecode($relative);
}


/**
 * Dateiname einer Version: bandfoto.jpg -> bandfoto-scp500.jpg
 */
function scp_image_variant_filename($path_or_url, $size){
    $info = pathinfo($path_or_url);
    if(empty($info['filename']) || empty($info['extension'])) return '';
    $dir = isset($info['dirname']) && $info['dirname'] !== '.' ? $info['dirname'] . '/' : '';
    return $dir . $info['filename'] . '-scp' . intval($size) . '.' . $info['extension'];
}


/**
 * URL der gewünschten Version – oder das Original, wenn es sie (noch) nicht gibt.
 *
 * @param string     $url  URL des Originals (Spalte "image")
 * @param string|int $size '500', '250' oder 'original'
 */
function scp_image_url($url, $size = '500'){

    static $cache = [];

    $url  = (string) $url;
    $size = (string) $size;

    if($url === '' || $size === 'original' || !isset(scp_image_variant_sizes()[$size])){
        return $url;
    }

    $key = $size . '|' . $url;
    if(isset($cache[$key])) return $cache[$key];

    $result = $url;
    $path   = scp_upload_url_to_path($url);

    if($path !== ''){
        $variant_path = scp_image_variant_filename($path, $size);
        if($variant_path !== '' && file_exists($variant_path)){
            $result = scp_image_variant_filename(strtok($url, '?'), $size);
        }
    }

    $cache[$key] = $result;
    return $result;
}


/**
 * Erzeugt die quadratischen Versionen eines Bandfotos (mittig zugeschnitten).
 * Ist das Bild kleiner als die Zielgröße, wird nicht hochskaliert, sondern
 * nur quadratisch zugeschnitten.
 *
 * @param string $url           URL des Originals
 * @param int    $attachment_id Mediathek-ID (optional, wird sonst gesucht)
 * @param bool   $force         vorhandene Versionen neu erzeugen
 * @return array ['created' => int, 'errors' => string[]]
 */
function scp_generate_image_variants($url, $attachment_id = 0, $force = false){

    $out  = ['created' => 0, 'errors' => []];
    $path = scp_upload_url_to_path($url);

    if($path === '' || !file_exists($path)){
        $out['errors'][] = 'Originaldatei nicht gefunden: ' . $url;
        return $out;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $meta_sizes = [];

    foreach(scp_image_variant_sizes() as $key => $target){

        $dest = scp_image_variant_filename($path, $key);
        if($dest === '') continue;

        if(!$force && file_exists($dest)){
            continue;
        }

        $editor = wp_get_image_editor($path);
        if(is_wp_error($editor)){
            $out['errors'][] = $editor->get_error_message();
            return $out;
        }

        $editor->set_quality(82);

        $dims = $editor->get_size();
        $w = intval($dims['width']);
        $h = intval($dims['height']);
        if($w <= 0 || $h <= 0) continue;

        $side  = min($w, $h);
        $src_x = (int) floor(($w - $side) / 2);
        $src_y = (int) floor(($h - $side) / 2);
        $dst   = min($target, $side);

        $crop = $editor->crop($src_x, $src_y, $side, $side, $dst, $dst);
        if(is_wp_error($crop)){
            $out['errors'][] = $crop->get_error_message();
            continue;
        }

        $saved = $editor->save($dest);
        if(is_wp_error($saved)){
            $out['errors'][] = $saved->get_error_message();
            continue;
        }

        $out['created']++;

        $meta_sizes['scp' . $key] = [
            'file'      => wp_basename($saved['path']),
            'width'     => intval($saved['width']),
            'height'    => intval($saved['height']),
            'mime-type' => $saved['mime-type'],
        ];
    }

    /*
     * In den Mediathek-Metadaten eintragen. Dann löscht WordPress die
     * Versionen mit, wenn das Bild aus der Mediathek entfernt wird.
     */
    if(!empty($meta_sizes)){

        if(!$attachment_id){
            $attachment_id = attachment_url_to_postid($url);
        }

        if($attachment_id){
            $meta = wp_get_attachment_metadata($attachment_id);
            if(is_array($meta)){
                if(empty($meta['sizes']) || !is_array($meta['sizes'])){
                    $meta['sizes'] = [];
                }
                $meta['sizes'] = array_merge($meta['sizes'], $meta_sizes);
                wp_update_attachment_metadata($attachment_id, $meta);
            }
        }
    }

    return $out;
}


/* =========================================================
   UPLOADS AUS DEM BEWERBUNGSFORMULAR
========================================================= */

/**
 * Prüft die Upload-Felder, bevor irgendetwas gespeichert wird.
 *
 * @return string Fehlermeldung oder '' wenn alles passt
 */
function scp_validate_application_uploads(){

    if(!empty($_FILES['band_image']['name'])){
        $check = wp_check_filetype(sanitize_file_name($_FILES['band_image']['name']));
        if(!in_array(strtolower((string) $check['ext']), ['jpg', 'jpeg', 'png', 'webp'], true)){
            return 'Das Bandfoto muss eine JPG-, PNG- oder WebP-Datei sein.';
        }
    }

    if(!empty($_FILES['band_audio']['name'])){
        $check = wp_check_filetype(sanitize_file_name($_FILES['band_audio']['name']));
        if(strtolower((string) $check['ext']) !== 'mp3'){
            return 'Die Audiodatei konnte nicht in MP3 umgewandelt werden. Bitte lade Deinen Song als MP3 hoch '
                 . '(WAV wird normalerweise automatisch umgewandelt – das klappt nur mit aktiviertem JavaScript in einem aktuellen Browser).';
        }
    }

    return '';
}


/**
 * Bandfoto speichern: Original bleibt erhalten, dazu 500er und 250er Version.
 *
 * @return string URL des Originals oder ''
 */
function scp_handle_application_image_upload(){

    if(empty($_FILES['band_image']['name'])) return '';

    $attachment_id = media_handle_upload('band_image', 0);

    if(is_wp_error($attachment_id)){
        error_log('[SCP IMAGE UPLOAD] ' . $attachment_id->get_error_message());
        return '';
    }

    $url = wp_get_attachment_url($attachment_id);

    if($url){
        $result = scp_generate_image_variants($url, $attachment_id);
        if(!empty($result['errors'])){
            error_log('[SCP IMAGE VARIANTS] ' . implode(' | ', $result['errors']));
        }
    }

    return $url ?: '';
}


/**
 * Audio speichern (kommt bereits als MP3 aus dem Browser).
 *
 * @return string URL oder ''
 */
function scp_handle_application_audio_upload(){

    if(empty($_FILES['band_audio']['name'])) return '';

    $attachment_id = media_handle_upload('band_audio', 0);

    if(is_wp_error($attachment_id)){
        error_log('[SCP AUDIO UPLOAD] ' . $attachment_id->get_error_message());
        return '';
    }

    return wp_get_attachment_url($attachment_id) ?: '';
}


/**
 * Skript für die WAV→MP3-Umwandlung im Browser.
 * Wird aus dem Bewerbungs-Shortcode heraus geladen (funktioniert auch in Divi-Modulen).
 */
function scp_enqueue_audio_converter(){

    wp_enqueue_script(
        'scp-audio-convert',
        SCP_URL . 'assets/audio-convert.js',
        [],
        SCP_VERSION,
        true
    );

    wp_localize_script('scp-audio-convert', 'scpAudioConvert', [
        'lameUrl' => SCP_URL . 'assets/vendor/lame.min.js?ver=1.2.1',
        'kbps'    => 192,
    ]);
}


/* =========================================================
   BACKEND: Bild im Bewerbungs-Editor geändert -> Versionen erzeugen
========================================================= */

function scp_maybe_generate_variants_after_admin_save(){

    if(!is_admin() || empty($_POST['update_band']) || empty($_POST['image'])) return;
    if(!current_user_can('manage_options')) return;

    $url = esc_url_raw(wp_unslash($_POST['image']));
    if($url !== ''){
        scp_generate_image_variants($url);
    }
}
add_action('admin_init', 'scp_maybe_generate_variants_after_admin_save');


/* =========================================================
   BACKEND: Versionen für bestehende Bewerbungen nachträglich erzeugen
========================================================= */

function scp_media_admin_menu(){
    add_submenu_page(
        'songcontest-dashboard',
        'Bildversionen',
        'Bildversionen',
        'manage_options',
        'songcontest-image-variants',
        'scp_media_admin_page'
    );
}
add_action('admin_menu', 'scp_media_admin_menu', 20);


function scp_media_admin_page(){

    if(!current_user_can('manage_options')) return;

    global $wpdb;
    $table = $wpdb->prefix . 'scp_artists';

    $total   = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE image IS NOT NULL AND image <> ''");
    $done    = 0;
    $missing = 0;
    $report  = null;

    if(isset($_POST['scp_regen_variants']) && check_admin_referer('scp_regen_variants')){

        @set_time_limit(300);

        $force  = !empty($_POST['scp_force']);
        $report = ['created' => 0, 'images' => 0, 'errors' => []];

        $urls = $wpdb->get_col("SELECT DISTINCT image FROM $table WHERE image IS NOT NULL AND image <> ''");

        foreach($urls as $url){
            $r = scp_generate_image_variants($url, 0, $force);
            $report['images']++;
            $report['created'] += $r['created'];
            foreach($r['errors'] as $e){
                $report['errors'][] = $e;
            }
        }
    }

    // Stand ermitteln
    $urls = $wpdb->get_col("SELECT DISTINCT image FROM $table WHERE image IS NOT NULL AND image <> ''");
    foreach($urls as $url){
        $complete = true;
        foreach(array_keys(scp_image_variant_sizes()) as $key){
            if(scp_image_url($url, $key) === $url){ $complete = false; break; }
        }
        $complete ? $done++ : $missing++;
    }

    echo '<div class="wrap"><h1>Bildversionen der Bandfotos</h1>';
    echo '<p>Neue Bewerbungen erhalten automatisch eine 500 × 500 px und eine 250 × 250 px Version (mittig zugeschnitten). '
       . 'Hier kannst Du die Versionen für bereits vorhandene Bewerbungen erzeugen.</p>';

    if($report !== null){
        echo '<div class="notice notice-success"><p>'
           . esc_html(sprintf('%d Bilder geprüft, %d Versionen erzeugt.', $report['images'], $report['created']))
           . '</p></div>';
        if(!empty($report['errors'])){
            echo '<div class="notice notice-warning"><p><strong>Hinweise:</strong></p><ul style="list-style:disc;margin-left:20px">';
            foreach(array_slice(array_unique($report['errors']), 0, 30) as $e){
                echo '<li>' . esc_html($e) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    echo '<table class="widefat" style="max-width:520px"><tbody>';
    echo '<tr><td>Bewerbungen mit Bandfoto</td><td><strong>' . intval($total) . '</strong></td></tr>';
    echo '<tr><td>Unterschiedliche Bilddateien mit allen Versionen</td><td><strong>' . intval($done) . '</strong></td></tr>';
    echo '<tr><td>Bilddateien ohne (vollständige) Versionen</td><td><strong>' . intval($missing) . '</strong></td></tr>';
    echo '</tbody></table>';

    echo '<form method="post" style="margin-top:20px">';
    wp_nonce_field('scp_regen_variants');
    echo '<p><label><input type="checkbox" name="scp_force" value="1"> Vorhandene Versionen neu erzeugen</label></p>';
    echo '<p><button class="button button-primary" name="scp_regen_variants" value="1">Fehlende Versionen jetzt erzeugen</button></p>';
    echo '</form>';

    echo '<p class="description">Hinweis: Bis Version 3.0.0-dev7.10.1 wurde das Original beim Upload auf maximal 400 px verkleinert. '
       . 'Für diese älteren Bewerbungen wird nicht hochskaliert – die „500er“ Version ist dann so groß wie das vorhandene Bild.</p>';

    echo '</div>';
}

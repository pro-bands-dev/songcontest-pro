<?php
add_shortcode("songcontest_application","scp_application");

function scp_application(){

    $contest_id = function_exists('scp_get_application_contest_id')
        ? scp_get_application_contest_id()
        : 0;

    if(!$contest_id){

        $context = function_exists('scp_get_application_display_context')
            ? scp_get_application_display_context()
            : ['state' => 'planning'];

        ob_start();
        ?>

        <div class="band-bew-card application-card scp-application-closed">
            <h2>Song Contest Bewerbung</h2>

            <?php if(($context['state'] ?? '') === 'countdown' && !empty($context['target_date'])){ ?>

                <?php
                try{
                    $target_datetime = new DateTime($context['target_date'], wp_timezone());
                    $target_ms = $target_datetime->getTimestamp() * 1000;
                }catch(Exception $e){
                    $target_ms = 0;
                }
                ?>

                <p>Die Bewerbungsphase ist noch nicht geöffnet.</p>

                <?php if(!empty($context['contest']->name)){ ?>
                    <p><strong><?php echo esc_html($context['contest']->name); ?></strong></p>
                <?php } ?>

                <?php
                if(function_exists('scp_countdown_markup') && $target_ms > 0){
                    echo scp_countdown_markup($target_ms, 'Bewerbung startet in');
                }
                ?>

            <?php }elseif(($context['state'] ?? '') === 'running'){ ?>

                <p>Der aktuelle Contest läuft bereits. Leider sind keine Bewerbungen mehr möglich.</p>

            <?php }else{ ?>

                <p>Der nächste Contest ist bereits in Planung.</p>
                <p>
                    Bitte folge uns auf
                    <a href="https://www.instagram.com/pro_bands_audio/"
                       target="_blank"
                       rel="noopener noreferrer">Instagram</a>,
                    um informiert zu werden, wann der nächste Contest startet.
                </p>

            <?php } ?>
        </div>

        <?php
        return ob_get_clean();
    }

    ob_start();

    if(isset($_POST['apply'])){

        global $wpdb;

        /*
         * Sicherheitsprüfung:
         * Auch beim Absenden noch einmal prüfen, damit keine Bewerbung
         * in einen falschen oder bereits geschlossenen Contest geschrieben wird.
         */
        $contest_id = function_exists('scp_get_application_contest_id')
            ? scp_get_application_contest_id()
            : 0;

        if(!$contest_id){
            echo "<p class='contest-error'>Die Bewerbungsphase ist aktuell geschlossen.</p>";
            return ob_get_clean();
        }

        require_once(ABSPATH.'wp-admin/includes/file.php');
        require_once(ABSPATH.'wp-admin/includes/media.php');
        require_once(ABSPATH.'wp-admin/includes/image.php');

        $image_url = "";
        $audio_url = "";

        // IMAGE UPLOAD
        if(!empty($_FILES['band_image']['name'])){

            $attachment_id = media_handle_upload('band_image',0);

            if(!is_wp_error($attachment_id)){

                $image_url = wp_get_attachment_url($attachment_id);

                // Bildgröße optimieren
                $path = get_attached_file($attachment_id);
                $editor = wp_get_image_editor($path);

                if(!is_wp_error($editor)){

                    $size = $editor->get_size();
                    $max = 400;

                    if($size['width'] > $size['height']){
                        $new_width = $max;
                        $new_height = intval(($size['height']/$size['width'])*$max);
                    }else{
                        $new_height = $max;
                        $new_width = intval(($size['width']/$size['height'])*$max);
                    }

                    $editor->resize($new_width,$new_height,false);
                    $editor->save($path);
                }
            }
        }

        // AUDIO UPLOAD
        if(!empty($_FILES['band_audio']['name'])){

            $attachment_id = media_handle_upload('band_audio',0);

            if(!is_wp_error($attachment_id)){
                $audio_url = wp_get_attachment_url($attachment_id);
            }
        }

        // Einwilligung für Infos/Angebote per E-Mail (freiwillig, Double-Opt-In)
        list($marketing_fields, $marketing_raw_token) = function_exists('scp_marketing_prepare_application_fields')
            ? scp_marketing_prepare_application_fields()
            : [[], ''];

        // INSERT
        $insert = $wpdb->insert(
            $wpdb->prefix.'scp_artists',
            array_merge([
                'contest_id' => $contest_id,
                'name'      => sanitize_text_field($_POST['bandname']),
                'title'     => sanitize_text_field($_POST['songtitle']),
                'genre'     => sanitize_text_field($_POST['genre']),
                'length'    => sanitize_text_field($_POST['length']),
                'band_info' => sanitize_textarea_field($_POST['band_info']),
                'song_info' => sanitize_textarea_field($_POST['song_info']),
                'contact'   => sanitize_text_field($_POST['contact']),
                'email'     => sanitize_email($_POST['email']),
                'instagram' => esc_url_raw($_POST['instagram']),
                'facebook'  => esc_url_raw($_POST['facebook']),
                'website'   => esc_url_raw($_POST['website']),
                'image'     => $image_url,
                'audio'     => $audio_url,
                'status'    => 'pending',
                'created_at' => current_time('mysql')
            ], $marketing_fields)
        );

        if($insert === false){

            error_log('[SCP APPLICATION ERROR] '.$wpdb->last_error);

            echo "<p class='contest-error'>Die Bewerbung konnte nicht gespeichert werden.</p>";

        }else{

            $new_artist_id = intval($wpdb->insert_id);

            scp_mail_application_received(
                sanitize_email($_POST['email']),
                sanitize_text_field($_POST['bandname'])
            );

            echo "<p class='contest-success'>Bewerbung erfolgreich gesendet.</p>";

            if($marketing_raw_token !== '' && $new_artist_id > 0){

                scp_mail_marketing_optin(
                    sanitize_email($_POST['email']),
                    sanitize_text_field($_POST['bandname']),
                    $new_artist_id,
                    $marketing_raw_token
                );

                echo "<p class='contest-success'>Wir haben Dir eine E-Mail mit einem Bestätigungslink geschickt. Bitte bestätige damit, dass Du Tipps und Angebote erhalten möchtest.</p>";
            }
        }
    }
    ?>

    <div class="band-bew-card application-card">
        <h2>Song Contest Bewerbung</h2>

        <form method="post" enctype="multipart/form-data" class="contest-form">

            <h3>Band Infos</h3>

            <h4>Felder mit * sind Pflichtfelder</h4>

            <input type="text" name="bandname" placeholder="Band/Künstlername*" required>
            <input type="text" name="contact" placeholder="Ansprechpartner">
            <input type="email" name="email" placeholder="E-Mail*" required>
            <textarea name="band_info" placeholder="Bandbeschreibung" required></textarea>

            <input type="text" name="instagram" placeholder="Instagram Link (https://instagram.com/...)">
            <input type="text" name="facebook" placeholder="Facebook Link (https://facebook.com/...)">
            <input type="text" name="website" placeholder="Website (https://...)">

            <h3>Song Infos</h3>

            <input type="text" name="songtitle" placeholder="Songtitel*" required>
            <input type="text" name="genre" placeholder="Genre*" required>
            <input type="text" name="length" placeholder="Länge (z.B. 03:42)">
            <textarea name="song_info" placeholder="Songbeschreibung"></textarea>

            <h3>Upload</h3>

            <label>Bandfoto</label>
            <input type="file" name="band_image" accept="image/jpeg,image/png,image/webp">

            <label>Audiofile</label>
            <input type="file" name="band_audio" accept="audio/mpeg,audio/wav">

            <?php if(function_exists('scp_application_privacy_notice_html')){ ?>
                <p class="scp-privacy-notice"><?php echo wp_kses(
                    scp_application_privacy_notice_html(),
                    ['a' => ['href' => [], 'target' => [], 'rel' => []], 'strong' => []]
                ); ?></p>
            <?php } ?>

            <?php if(function_exists('scp_marketing_consent_label_html')){ ?>
                <div class="scp-consent">
                    <label class="scp-consent-label">
                        <input type="checkbox" name="marketing_consent" value="1">
                        <span><?php echo wp_kses(
                            scp_marketing_consent_label_html(),
                            ['a' => ['href' => [], 'target' => [], 'rel' => []]]
                        ); ?></span>
                    </label>
                    <p class="scp-consent-note"><?php echo esc_html(scp_marketing_consent_note()); ?></p>
                </div>
            <?php } ?>

            <button class="contest-submit" name="apply">
                Bewerbung absenden
            </button>

        </form>
    </div>

    <?php

    return ob_get_clean();
}

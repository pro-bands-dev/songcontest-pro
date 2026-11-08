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

    global $wpdb;

    $scp_application_error = '';
    $scp_application_saved = false;

    if(isset($_POST['apply'])){

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

        /*
         * Uploads zuerst prüfen. Audio kommt als MP3 an – WAV wird im
         * Browser vor dem Absenden umgewandelt (assets/audio-convert.js).
         */
        $upload_error = function_exists('scp_validate_application_uploads')
            ? scp_validate_application_uploads()
            : '';

        if($upload_error !== ''){
            $scp_application_error = $upload_error;
        }
    }

    if(isset($_POST['apply']) && $scp_application_error === ''){

        // IMAGE UPLOAD: Original + 500×500 + 250×250 (siehe includes/media.php)
        $image_url = scp_handle_application_image_upload();

        // AUDIO UPLOAD (MP3)
        $audio_url = scp_handle_application_audio_upload();

        // Einwilligung für Infos/Angebote per E-Mail (freiwillig, Double-Opt-In)
        list($marketing_fields, $marketing_raw_token) = function_exists('scp_marketing_prepare_application_fields')
            ? scp_marketing_prepare_application_fields()
            : [[], ''];

        // INSERT
        $insert = $wpdb->insert(
            $wpdb->prefix.'scp_artists',
            array_merge([
                'contest_id' => $contest_id,
                'name'      => sanitize_text_field(wp_unslash($_POST['bandname'] ?? '')),
                'title'     => sanitize_text_field(wp_unslash($_POST['songtitle'] ?? '')),
                'genre'     => sanitize_text_field(wp_unslash($_POST['genre'] ?? '')),
                'length'    => sanitize_text_field(wp_unslash($_POST['length'] ?? '')),
                'band_info' => sanitize_textarea_field(wp_unslash($_POST['band_info'] ?? '')),
                'song_info' => sanitize_textarea_field(wp_unslash($_POST['song_info'] ?? '')),
                'contact'   => sanitize_text_field(wp_unslash($_POST['contact'] ?? '')),
                'email'     => sanitize_email(wp_unslash($_POST['email'] ?? '')),
                'instagram' => esc_url_raw(wp_unslash($_POST['instagram'] ?? '')),
                'facebook'  => esc_url_raw(wp_unslash($_POST['facebook'] ?? '')),
                'website'   => esc_url_raw(wp_unslash($_POST['website'] ?? '')),
                'image'     => $image_url,
                'audio'     => $audio_url,
                'status'    => 'pending',
                'created_at' => current_time('mysql')
            ], $marketing_fields)
        );

        if($insert === false){

            error_log('[SCP APPLICATION ERROR] '.$wpdb->last_error);

            $scp_application_error = 'Die Bewerbung konnte nicht gespeichert werden.';

        }else{

            $new_artist_id = intval($wpdb->insert_id);

            scp_mail_application_received(
                sanitize_email(wp_unslash($_POST['email'])),
                sanitize_text_field(wp_unslash($_POST['bandname']))
            );

            $scp_application_saved = true;

            echo "<p class='contest-success'>Bewerbung erfolgreich gesendet.</p>";

            if($marketing_raw_token !== '' && $new_artist_id > 0){

                scp_mail_marketing_optin(
                    sanitize_email(wp_unslash($_POST['email'])),
                    sanitize_text_field(wp_unslash($_POST['bandname'])),
                    $new_artist_id,
                    $marketing_raw_token
                );

                echo "<p class='contest-success'>Wir haben Dir eine E-Mail mit einem Bestätigungslink geschickt. Bitte bestätige damit, dass Du Tipps und Angebote erhalten möchtest.</p>";
            }
        }
    }

    if($scp_application_error !== ''){
        echo "<p class='contest-error'>".esc_html($scp_application_error)."</p>";
    }

    // Nach einem Fehler die Eingaben wieder einsetzen (Dateien muss die Band neu wählen).
    $old = function($key) use ($scp_application_error){
        if($scp_application_error === '' || !isset($_POST[$key])) return '';
        return esc_attr(wp_unslash($_POST[$key]));
    };
    $old_text = function($key) use ($scp_application_error){
        if($scp_application_error === '' || !isset($_POST[$key])) return '';
        return esc_textarea(wp_unslash($_POST[$key]));
    };

    if(function_exists('scp_enqueue_audio_converter')){
        scp_enqueue_audio_converter();
    }
    ?>

    <div class="band-bew-card application-card">
        <h2>Song Contest Bewerbung</h2>

        <form method="post" enctype="multipart/form-data" class="contest-form" data-scp-audio-convert="1">

            <h3>Band Infos</h3>

            <h4>Felder mit * sind Pflichtfelder</h4>

            <input type="text" name="bandname" placeholder="Band/Künstlername*" required value="<?php echo $old('bandname'); ?>">
            <input type="text" name="contact" placeholder="Ansprechpartner" value="<?php echo $old('contact'); ?>">
            <input type="email" name="email" placeholder="E-Mail*" required value="<?php echo $old('email'); ?>">
            <textarea name="band_info" placeholder="Bandbeschreibung" required><?php echo $old_text('band_info'); ?></textarea>

            <input type="text" name="instagram" placeholder="Instagram Link (https://instagram.com/...)" value="<?php echo $old('instagram'); ?>">
            <input type="text" name="facebook" placeholder="Facebook Link (https://facebook.com/...)" value="<?php echo $old('facebook'); ?>">
            <input type="text" name="website" placeholder="Website (https://...)" value="<?php echo $old('website'); ?>">

            <h3>Song Infos</h3>

            <input type="text" name="songtitle" placeholder="Songtitel*" required value="<?php echo $old('songtitle'); ?>">
            <input type="text" name="genre" placeholder="Genre*" required value="<?php echo $old('genre'); ?>">
            <input type="text" name="length" placeholder="Länge (z.B. 03:42)" value="<?php echo $old('length'); ?>">
            <textarea name="song_info" placeholder="Songbeschreibung"><?php echo $old_text('song_info'); ?></textarea>

            <h3>Upload</h3>

            <label>Bandfoto</label>
            <input type="file" name="band_image" accept="image/jpeg,image/png,image/webp">

            <label>Audiofile (MP3 oder WAV – WAV wird automatisch in MP3 umgewandelt)</label>
            <input type="file" name="band_audio" accept="audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/wave,.mp3,.wav">
            <p class="scp-audio-status" aria-live="polite" hidden></p>

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

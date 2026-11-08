<?php

function scp_applications_page(){

    global $wpdb;

    $table = $wpdb->prefix.'scp_artists';

    $contest_id = function_exists('scp_get_admin_selected_contest_id')
        ? scp_get_admin_selected_contest_id()
        : 1;

    $allowed_statuses = [
        'pending'   => 'Bewerbung eingegangen',
        'approved'  => 'Freigegeben',
        'top20'     => 'Voting-Teilnehmer',
        'finale'    => 'Top 5 / Finalist',
        'winner'    => 'Gewinner',
        'rejected'  => 'Abgelehnt',
        'withdrawn' => 'Zurückgezogen'
    ];

    /* FREIGEBEN */
    if(isset($_GET['approve'])){
        $id = intval($_GET['approve']);
        $wpdb->update(
            $table,
            ['status'=>'approved'],
            ['id'=>$id,'contest_id'=>$contest_id]
        );
    }

    /* LÖSCHEN */
    if(isset($_GET['delete'])){
        $id = intval($_GET['delete']);
        $wpdb->delete(
            $table,
            ['id'=>$id,'contest_id'=>$contest_id]
        );
    }

    /* UPDATE SPEICHERN */
    if(isset($_POST['update_band'])){

        $new_status = sanitize_key($_POST['status'] ?? 'pending');

        if(!array_key_exists($new_status,$allowed_statuses)){
            $new_status = 'pending';
        }

        $wpdb->update(
            $table,
            [
                'name'=>sanitize_text_field($_POST['name']),
                'email'=>sanitize_email($_POST['email']),
                'title'=>sanitize_text_field($_POST['title']),
                'genre'=>sanitize_text_field($_POST['genre']),
                'length'=>sanitize_text_field($_POST['length']),
                'band_info'=>sanitize_textarea_field($_POST['band_info']),
                'song_info'=>sanitize_textarea_field($_POST['song_info']),
                'contact'=>sanitize_text_field($_POST['contact']),
                'instagram'=>sanitize_text_field($_POST['instagram']),
                'facebook'=>sanitize_text_field($_POST['facebook']),
                'website'=>sanitize_text_field($_POST['website']),
                'image'=>esc_url_raw($_POST['image']),
                'audio'=>esc_url_raw($_POST['audio']),
                'status'=>$new_status
            ],
            [
                'id'=>intval($_POST['id']),
                'contest_id'=>intval($_POST['contest_id'])
            ]
        );

        if(
            ($_POST['marketing_action'] ?? '') === 'revoke'
            && function_exists('scp_marketing_admin_revoke')
        ){
            scp_marketing_admin_revoke(intval($_POST['id']));
        }

        echo "<div class='updated'><p>Bewerbung gespeichert.</p></div>";
    }

    /* BEARBEITEN */
    if(isset($_GET['edit'])){

        $id = intval($_GET['edit']);

        $band = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id=%d AND contest_id=%d",
                $id,
                $contest_id
            )
        );

        if(!$band){
            echo "<div class='wrap'><div class='notice notice-error'><p>Bewerbung nicht gefunden.</p></div></div>";
            return;
        }
        ?>

        <div class="wrap">
            <h1>Bewerbung bearbeiten</h1>

            <form method="post">

                <input type="hidden" name="id" value="<?php echo intval($band->id); ?>">
                <input type="hidden" name="contest_id" value="<?php echo intval($contest_id); ?>">

                <table class="form-table">

                    <tr>
                        <th>Bandname</th>
                        <td>
                            <input type="text" name="name" value="<?php echo esc_attr($band->name); ?>" style="width:400px;">
                        </td>

                        <th>Band ID</th>
                        <td>
                            <input type="text" value="<?php echo intval($band->id); ?>" style="width:60px;" disabled>
                        </td>
                    </tr>

                    <tr>
                        <th>Status</th>
                        <td colspan="3">
                            <select name="status" style="min-width:280px;">
                                <?php foreach($allowed_statuses as $status_value => $status_label){ ?>
                                    <option value="<?php echo esc_attr($status_value); ?>" <?php selected($band->status,$status_value); ?>>
                                        <?php echo esc_html($status_label); ?> (<?php echo esc_html($status_value); ?>)
                                    </option>
                                <?php } ?>
                            </select>
                            <p class="description">Der Status wird nur für diesen Teilnehmer im ausgewählten Contest geändert.</p>
                        </td>
                    </tr>

                    <tr>
                        <th>E-Mail</th>
                        <td>
                            <input type="email" name="email" value="<?php echo esc_attr($band->email); ?>" style="width:400px;">
                        </td>
                    </tr>

                    <tr>
                        <th>Ansprechpartner</th>
                        <td>
                            <input type="text" name="contact" value="<?php echo esc_attr($band->contact); ?>" style="width:400px;">
                        </td>
                    </tr>

                    <tr>
                        <th>Bandinfo</th>
                        <td>
                            <textarea name="band_info" style="width:400px;height:120px;"><?php echo esc_textarea($band->band_info); ?></textarea>
                        </td>

                        <th><p>Social-Media</p><p>Instagram:</p><p>Facebook:</p><p>Website:</p></th>
                        <td>
                            <p>Links:</p>
                            <p><input type="text" name="instagram" value="<?php echo esc_attr($band->instagram); ?>" style="width:400px;"></p>
                            <p><input type="text" name="facebook" value="<?php echo esc_attr($band->facebook); ?>" style="width:400px;"></p>
                            <p><input type="text" name="website" value="<?php echo esc_attr($band->website); ?>" style="width:400px;"></p>
                        </td>
                    </tr>

                    <tr>
                        <th>Songtitel</th>
                        <td>
                            <input type="text" name="title" value="<?php echo esc_attr($band->title); ?>" style="width:400px;">
                        </td>
                    </tr>

                    <tr>
                        <th>Genre</th>
                        <td>
                            <input type="text" name="genre" value="<?php echo esc_attr($band->genre); ?>" style="width:400px;">
                        </td>

                        <th>Länge</th>
                        <td>
                            <input type="text" name="length" value="<?php echo esc_attr($band->length); ?>" style="width:400px;">
                        </td>
                    </tr>

                    <tr>
                        <th>Songinfo</th>
                        <td>
                            <textarea name="song_info" style="width:400px;height:120px;"><?php echo esc_textarea($band->song_info); ?></textarea>
                        </td>

                        <th><p>Bandbild URL</p><p>Audio URL</p></th>
                        <td>
                            <p>
                                <input type="text" id="band_image" name="image" value="<?php echo esc_attr($band->image); ?>" style="width:400px;">
                                <button class="button scp-upload-image">Mediathek wählen</button>
                            </p>
                            <p>
                                <input type="text" id="band_audio" name="audio" value="<?php echo esc_attr($band->audio); ?>" style="width:400px;">
                                <button class="button scp-upload-audio">Mediathek wählen</button>
                            </p>
                        </td>
                    </tr>

                    <?php if(function_exists('scp_marketing_status')){
                        $mk_status = scp_marketing_status($band);
                        $mk_labels = scp_marketing_status_labels();
                    ?>
                    <tr>
                        <th>Infos &amp; Angebote per E-Mail</th>
                        <td colspan="3">
                            <p><strong><?php echo esc_html($mk_labels[$mk_status]); ?></strong></p>

                            <?php if($mk_status !== 'none'){ ?>
                                <p class="description">
                                    Angefragt: <?php echo esc_html($band->marketing_consent_at ?: '–'); ?><br>
                                    Bestätigt (Double-Opt-In): <?php echo esc_html($band->marketing_confirmed_at ?: '–'); ?><br>
                                    Widerrufen: <?php echo esc_html($band->marketing_revoked_at ?: '–'); ?>
                                </p>

                                <?php if(!empty($band->marketing_consent_text)){ ?>
                                    <details>
                                        <summary>Gespeicherter Wortlaut der Einwilligung</summary>
                                        <p class="description"><?php echo nl2br(esc_html($band->marketing_consent_text)); ?></p>
                                    </details>
                                <?php } ?>

                                <?php if($mk_status !== 'revoked'){ ?>
                                    <p>
                                        <label>
                                            <input type="checkbox" name="marketing_action" value="revoke">
                                            Einwilligung widerrufen (beim Speichern; gilt für diese E-Mail-Adresse in allen Bewerbungen)
                                        </label>
                                    </p>
                                <?php } ?>
                            <?php } else { ?>
                                <p class="description">Bei der Bewerbung wurde keine Einwilligung erteilt. Eine Einwilligung kann nur die Person selbst im Formular geben.</p>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>

                </table>

                <p>
                    <button class="button button-primary" name="update_band" value="1">Speichern</button>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=songcontest-applications&contest_id='.$contest_id)); ?>">Zurück zur Übersicht</a>
                </p>

            </form>
        </div>

        <script>
        jQuery(function($){

            $('.scp-upload-image').click(function(e){
                e.preventDefault();
                var frame = wp.media({
                    title: 'Bandbild auswählen',
                    button: { text: 'Bild verwenden' },
                    multiple: false
                });
                frame.on('select',function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#band_image').val(attachment.url);
                });
                frame.open();
            });

            $('.scp-upload-audio').click(function(e){
                e.preventDefault();
                var frame = wp.media({
                    title: 'Audio auswählen',
                    button: { text: 'Audio verwenden' },
                    multiple: false
                });
                frame.on('select',function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#band_audio').val(attachment.url);
                });
                frame.open();
            });

        });
        </script>

        <?php
        return;
    }

    /* LISTE */
    $consent_filter = isset($_GET['consent']) ? sanitize_key($_GET['consent']) : '';
    $consent_where  = '';

    switch($consent_filter){
        case 'confirmed':
            $consent_where = " AND marketing_consent=1 AND marketing_confirmed_at IS NOT NULL AND marketing_revoked_at IS NULL";
            break;
        case 'pending':
            $consent_where = " AND marketing_consent=1 AND marketing_confirmed_at IS NULL AND marketing_revoked_at IS NULL";
            break;
        case 'revoked':
            $consent_where = " AND marketing_consent=1 AND marketing_revoked_at IS NOT NULL";
            break;
        case 'none':
            $consent_where = " AND marketing_consent=0";
            break;
        default:
            $consent_filter = '';
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE contest_id=%d".$consent_where." ORDER BY created_at DESC",
            $contest_id
        )
    );

    echo "<div class='wrap'>";
    echo "<h1>Bewerbungen</h1>";

    if(function_exists('scp_admin_contest_selector')){
        scp_admin_contest_selector('songcontest-applications',$contest_id);
    }

    if(function_exists('scp_marketing_status_labels')){

        $mk_filter_options = [
            ''          => 'Alle Bewerbungen',
            'confirmed' => 'Einwilligung bestätigt',
            'pending'   => 'Bestätigung offen',
            'revoked'   => 'Widerrufen',
            'none'      => 'Keine Einwilligung',
        ];

        echo "<form method='get' style='margin:12px 0;'>";
        echo "<input type='hidden' name='page' value='songcontest-applications'>";
        echo "<input type='hidden' name='contest_id' value='".intval($contest_id)."'>";
        echo "<label for='scp-consent-filter'><strong>Infos &amp; Angebote:</strong> </label> ";
        echo "<select name='consent' id='scp-consent-filter'>";
        foreach($mk_filter_options as $mk_value => $mk_label){
            echo "<option value='".esc_attr($mk_value)."' ".selected($consent_filter,$mk_value,false).">".esc_html($mk_label)."</option>";
        }
        echo "</select> ";
        echo "<button class='button'>Filtern</button> ";
        echo "<a class='button' href='".esc_url(scp_marketing_export_url())."'>Bestätigte Einwilligungen als CSV exportieren</a>";
        echo "</form>";
    }

    echo "<table class='widefat striped'>";
    echo "<tr>
        <th>ID</th>
        <th>Bandfoto</th>
        <th>Bandname</th>
        <th>Songtitel</th>
        <th>E-Mail</th>
        <th>Audio</th>
        <th>Status</th>
        <th>Infos &amp; Angebote</th>
        <th>Aktionen</th>
    </tr>";

    foreach($rows as $r){

        echo "<tr>";
        echo "<td>".intval($r->id)."</td>";

        echo "<td>";
        if($r->image){
            echo "<img src='".esc_url(scp_image_url($r->image,'250'))."' style='max-width:80px;height:auto;'>";
        }
        echo "</td>";

        echo "<td>".esc_html($r->name)."</td>";
        echo "<td>".esc_html($r->title)."</td>";
        echo "<td>".esc_html($r->email)."</td>";

        echo "<td>";
        if($r->audio){
            echo "<audio controls style='width:180px'><source src='".esc_url($r->audio)."'></audio>";
        }
        echo "</td>";

        $status_label = isset($allowed_statuses[$r->status])
            ? $allowed_statuses[$r->status]
            : $r->status;

        echo "<td>".esc_html($status_label)."<br><small>".esc_html($r->status)."</small></td>";

        if(function_exists('scp_marketing_status')){
            $mk_labels_list = scp_marketing_status_labels();
            echo "<td>".esc_html($mk_labels_list[scp_marketing_status($r)])."</td>";
        }else{
            echo "<td></td>";
        }

        echo "<td>";

        if($r->status === 'pending'){
            echo "<a class='button' href='?page=songcontest-applications&contest_id=".$contest_id."&approve=".$r->id."'>Freigeben</a> ";
        }

        echo "<a class='button' href='?page=songcontest-applications&contest_id=".$contest_id."&edit=".$r->id."'>Bearbeiten</a> ";

        echo "<a class='button' style='background:#d63638;color:white' href='?page=songcontest-applications&contest_id=".$contest_id."&delete=".$r->id."' onclick='return confirm(\"Bewerbung löschen?\")'>Löschen</a>";

        echo "</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "</div>";
}

/*
function scp_applications_page(){

global $wpdb;

$table = $wpdb->prefix.'scp_artists';

$contest_id = function_exists('scp_get_admin_selected_contest_id')
    ? scp_get_admin_selected_contest_id()
    : 1;

// FREIGEBEN
if(isset($_GET['approve'])){
$id = intval($_GET['approve']);
$wpdb->update(
$table,
['status'=>'approved'],
['id'=>$id,'contest_id'=>$contest_id]
);
}

// LÖSCHEN

if(isset($_GET['delete'])){
$id = intval($_GET['delete']);
$wpdb->delete(
$table,
['id'=>$id,'contest_id'=>$contest_id]
);
}

// BEARBEITEN
if(isset($_GET['edit'])){
$id = intval($_GET['edit']);
$band = $wpdb->get_row(
$wpdb->prepare("SELECT * FROM $table WHERE id=%d AND contest_id=%d",$id,$contest_id)
);
?>

<div class="wrap">
<h1>Bewerbung bearbeiten</h1>
<form method="post">
<input type="hidden" name="id" value="<?php echo $band->id; ?>">
<input type="hidden" name="contest_id" value="<?php echo intval($contest_id); ?>">
<table class="form-table">
<tr>
<th>Bandname</th>
<td>
<input type="text" name="name" value="<?php echo esc_attr($band->name); ?>" style="width:400px;">
</td>
<th>Band ID</th>
<td>
<input type="text" name="n_id" value="<?php echo $band->id; ?>" style="width:40px;" disabled>
</td>
</tr>
<tr>
<th>E-Mail</th>
<td>
<input type="text" name="email" value="<?php echo esc_attr($band->email); ?>" style="width:400px;">
</td>
</tr>
<tr>
<th>Ansprechpartner</th>
<td>
<input type="text" name="contact" value="<?php echo esc_attr($band->contact); ?>" style="width:400px;">
</td>
</tr>
<tr>
<th>Bandinfo</th>
<td>
<textarea name="band_info" style="width:400px;height:120px;"><?php echo esc_textarea($band->band_info); ?></textarea>
</td>
<th><p>Social-Media</p><p>Instagram:</p><p>Facebook:</p><p>Website:</p></th>
<td>
<p>Links:</p>
<p><input type="text" name="instagram" value="<?php echo esc_attr($band->instagram); ?>" style="width:400px;"></p>
<p><input type="text" name="facebook" value="<?php echo esc_attr($band->facebook); ?>" style="width:400px;"></p>
<p><input type="text" name="website" value="<?php echo esc_attr($band->website); ?>" style="width:400px;"></p>
</td>
</tr>
<tr>
<th>Songtitel</th>
<td>
<input type="text" name="title" value="<?php echo esc_attr($band->title); ?>" style="width:400px;">
</td>
</tr>
<tr>
<th>Genre</th>
<td>
<input type="text" name="genre" value="<?php echo esc_attr($band->genre); ?>" style="width:400px;">
</td>
<th>Länge</th>
<td>
<input type="text" name="length" value="<?php echo esc_attr($band->length); ?>" style="width:400px;">
</td>
</tr>
<tr>
<th>Songinfo</th>
<td>
<textarea name="song_info" style="width:400px;height:120px;"><?php echo esc_textarea($band->song_info); ?></textarea>
</td>
<th><p>Bandbild URL</p><p>Audio URL</p></th>
<td>
<p>
<input type="text" id="band_image" name="image" value="<?php echo esc_attr($band->image); ?>" style="width:400px;">
<button class="button scp-upload-image">Mediathek wählen</button>
</p>
<p>
<input type="text" id="band_audio" name="audio" value="<?php echo esc_attr($band->audio); ?>" style="width:400px;">
<button class="button scp-upload-audio">Mediathek wählen</button>
</p>
</td>
</tr>
</table>
<p>
<button class="button button-primary" name="update_band">
Speichern
</button>
</p>
</form>
</div>
<script>

jQuery(function($){
// Bild auswählen
$('.scp-upload-image').click(function(e){

e.preventDefault();
var frame = wp.media({
title: 'Bandbild auswählen',
button: { text: 'Bild verwenden' },
multiple: false
});

frame.on('select', function(){
var attachment = frame.state().get('selection').first().toJSON();

$('#band_image').val(attachment.url);
});

frame.open();
});


// Audio auswählen
$('.scp-upload-audio').click(function(e){

e.preventDefault();
var frame = wp.media({
title: 'Audio auswählen',
button: { text: 'Audio verwenden' },
multiple: false
});

frame.on('select', function(){
var attachment = frame.state().get('selection').first().toJSON();
$('#band_audio').val(attachment.url);
});

frame.open();
});

});
</script>

<?php
}

// UPDATE SPEICHERN

if(isset($_POST['update_band'])){

$wpdb->update(
$table,
[
'name'=>sanitize_text_field($_POST['name']),
'email'=>sanitize_email($_POST['email']),
'title'=>sanitize_text_field($_POST['title']),
'genre'=>sanitize_text_field($_POST['genre']),
'length'=>sanitize_text_field($_POST['length']),
'band_info'=>sanitize_textarea_field($_POST['band_info']),
'song_info'=>sanitize_textarea_field($_POST['song_info']),
'contact'=>sanitize_text_field($_POST['contact']),
'instagram'=>sanitize_text_field($_POST['instagram']),
'facebook'=>sanitize_text_field($_POST['facebook']),
'website'=>sanitize_text_field($_POST['website']),
'image'=>esc_url_raw($_POST['image']),
'audio'=>esc_url_raw($_POST['audio'])
],
['id'=>intval($_POST['id']),'contest_id'=>intval($_POST['contest_id'])]
);

echo "<div class='updated'><p>Bewerbung gespeichert.</p></div>";
}

// LISTE
$rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE contest_id=%d ORDER BY created_at DESC",$contest_id));
echo "<div class='wrap'>";
echo "<h1>Bewerbungen</h1>";
if(function_exists('scp_admin_contest_selector')){
    scp_admin_contest_selector('songcontest-applications', $contest_id);
}
echo "<table class='widefat striped'>";
echo "<tr>

<th>ID</th>
<th>Bandfoto</th>
<th>Bandname</th>
<th>Songtitel</th>
<th>E-Mail</th>
<th>Audio</th>
<th>Status</th>
<th>Aktionen</th>
</tr>";

foreach($rows as $r){

echo "<tr>";
echo "<td>".$r->id."</td>";
echo "<td>";
if($r->image){
echo "<img src='".$r->image."' style='max-width:80px;height:auto;'>";
}

echo "</td>";
echo "<td>".$r->name."</td>";
echo "<td>".$r->title."</td>";
echo "<td>".$r->email."</td>";
echo "<td>";
if($r->audio){
echo "<audio controls style='width:180px'>
<source src='".$r->audio."'>
</audio>";
}
echo "</td>";
echo "<td>".$r->status."</td>";
echo "<td>";
if($r->status == "pending"){
echo "<a class='button' href='?page=songcontest-applications&contest_id=".$contest_id."&approve=".$r->id."'>Freigeben</a> ";
}
echo "<a class='button' href='?page=songcontest-applications&contest_id=".$contest_id."&edit=".$r->id."'>Bearbeiten</a> ";
echo "<a class='button' style='background:#d63638;color:white'
href='?page=songcontest-applications&contest_id=".$contest_id."&delete=".$r->id."'
onclick='return confirm(\"Bewerbung löschen?\")'>
Löschen
</a>";
echo "</td>";
echo "</tr>";
}

echo "</table>";
echo "</div>";
}
*/
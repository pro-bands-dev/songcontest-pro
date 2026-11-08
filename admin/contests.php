<?php

if (!defined('ABSPATH')) exit;

function scp_format_datetime_local_value($value){

    if(empty($value)){
        return '';
    }

    return esc_attr(str_replace(' ', 'T', substr($value,0,16)));
}


function scp_contests_page(){

    if(!current_user_can('manage_options')){
        return;
    }

    global $wpdb;

    $table = $wpdb->prefix.'scp_contests';

    if(isset($_POST['scp_create_contest'])){

        check_admin_referer('scp_create_contest');

        $created = scp_create_contest($_POST);

        if(is_wp_error($created)){
            echo "<div class='notice notice-error'><p>".esc_html($created->get_error_message())."</p></div>";
        }else{
            echo "<div class='notice notice-success'><p>Contest wurde angelegt.</p></div>";
        }
    }


    if(isset($_POST['scp_update_contest'])){

        $contest_id = intval($_POST['contest_id'] ?? 0);

        check_admin_referer('scp_update_contest_'.$contest_id);

        $updated = scp_update_contest($contest_id, $_POST);

        if(is_wp_error($updated)){
            echo "<div class='notice notice-error'><p>".esc_html($updated->get_error_message())."</p></div>";
        }else{
            echo "<div class='notice notice-success'><p>Contest wurde gespeichert.</p></div>";
        }
    }


    if(isset($_POST['scp_send_voting_mails'])){

        $contest_id = intval($_POST['contest_id'] ?? 0);

        check_admin_referer('scp_send_voting_mails_'.$contest_id);

        $sent = scp_notify_voting_start($contest_id);

        echo "<div class='notice notice-success'><p>Voting-Mails wurden versendet: ".intval($sent)."</p></div>";
    }


    if(isset($_GET['set_current'])){

        $contest_id = intval($_GET['set_current']);

        if(check_admin_referer('scp_set_current_'.$contest_id)){
            scp_set_current_contest($contest_id);
            echo "<div class='notice notice-success'><p>Aktiver Contest wurde geändert.</p></div>";
        }
    }

    $current_id = scp_get_current_contest_id();

    $edit_id = isset($_GET['edit_contest'])
        ? intval($_GET['edit_contest'])
        : $current_id;

    $edit_contest = scp_get_contest($edit_id);

    $rows = $wpdb->get_results("
        SELECT *
        FROM $table
        ORDER BY `year` DESC, id DESC
    ");

    echo "<div class='wrap'>";

    echo "<h1>Contestverwaltung</h1>";

    echo "<p><strong>Aktiver Contest:</strong> #".intval($current_id)."</p>";

    echo "<h2>Contests</h2>";

    echo "<table class='widefat striped'>";
    echo "<tr>
        <th>ID</th>
        <th>Name</th>
        <th>Jahr</th>
        <th>Status</th>
        <th>Bewerbung</th>
        <th>Top Voting</th>
        <th>Voting Auswertung</th>
        <th>Max Voting</th>
        <th>Max Finale</th>
        <th>Finale</th>
        <th>Winner</th>
        <th>Aktion</th>
    </tr>";

    foreach($rows as $row){

        $set_url = wp_nonce_url(
            admin_url('admin.php?page=songcontest-contests&set_current='.$row->id),
            'scp_set_current_'.$row->id
        );

        $edit_url = admin_url('admin.php?page=songcontest-contests&edit_contest='.$row->id);

        echo "<tr>";
        echo "<td>".intval($row->id)."</td>";
        echo "<td>".esc_html($row->name).($row->id == $current_id ? " <strong>(aktiv)</strong>" : "")."</td>";
        echo "<td>".esc_html($row->year)."</td>";
        echo "<td>".esc_html($row->status)."</td>";
        echo "<td>".esc_html($row->application_start)." – ".esc_html($row->application_end)."</td>";
        echo "<td>".esc_html($row->top20_start)." – ".esc_html($row->top20_end)."</td>";
        echo "<td>".esc_html($row->vaus_start)." – ".esc_html($row->vaus_end)."</td>";
        echo "<td>".intval($row->max_voting_participants ?? 30)."</td>";
        echo "<td>".intval($row->max_finalists ?? 5)."</td>";
        echo "<td>".esc_html($row->final_start)." – ".esc_html($row->final_end)."</td>";
        echo "<td>".esc_html($row->winner_date)."</td>";
        echo "<td>";
        echo "<a class='button' href='".esc_url($edit_url)."'>Bearbeiten</a> ";
        if($row->id != $current_id){
            echo "<a class='button' href='".esc_url($set_url)."'>Aktiv setzen</a>";
        }else{
            echo "<span class='button disabled'>Aktiv</span>";
        }
        echo "</td>";
        echo "</tr>";
    }

    echo "</table>";


    if($edit_contest){

        echo "<hr>";
        echo "<h2>Contest bearbeiten: ".esc_html($edit_contest->name)."</h2>";

        echo "<form method='post'>";

        wp_nonce_field('scp_update_contest_'.$edit_contest->id);

        echo "<input type='hidden' name='contest_id' value='".intval($edit_contest->id)."'>";

        scp_render_contest_form_fields($edit_contest);

        echo "<p>";
        echo "<button type='submit' name='scp_update_contest' class='button button-primary'>Contest speichern</button>";
        echo "</p>";

        echo "</form>";

        echo "<h2>Manueller Mailversand</h2>";
        echo "<form method='post' onsubmit='return confirm(\"Voting-Mails für diesen Contest wirklich senden?\")'>";

        wp_nonce_field('scp_send_voting_mails_'.$edit_contest->id);

        echo "<input type='hidden' name='contest_id' value='".intval($edit_contest->id)."'>";
        echo "<p><button type='submit' name='scp_send_voting_mails' class='button button-secondary'>Voting-Mails manuell senden</button></p>";
        echo "<p class='description'>Sendet an Bands dieses Contests mit Status <strong>top20</strong>.</p>";

        echo "</form>";
    }
		echo "<hr>";

		if(
			!empty($edit_contest)
			&&
			!empty($edit_contest->id)
		){

			scp_render_contest_result_admin_message();

			scp_render_contest_result_admin_box(
				$edit_contest->id
			);
		}

    echo "<hr>";

    echo "<h2>Neuen Contest anlegen</h2>";

    echo "<form method='post'>";

    wp_nonce_field('scp_create_contest');

    $new = (object)[
        'name' => 'Song Contest '.(date('Y')+1),
        'slug' => 'song-contest-'.(date('Y')+1),
        'year' => date('Y')+1,
        'status' => 'draft',
        'application_start' => '',
        'application_end' => '',
        'top20_start' => '',
        'top20_end' => '',
        'vaus_start' => '',
        'vaus_end' => '',
        'max_voting_participants' => 30,
        'max_finalists' => 5,
        'final_start' => '',
        'final_end' => '',
        'winner_date' => ''
    ];

    scp_render_contest_form_fields($new);

    echo "<p><button type='submit' name='scp_create_contest' class='button button-primary'>Contest anlegen</button></p>";

    echo "</form>";

    echo "</div>";
}


function scp_render_contest_form_fields($contest){

    echo "<table class='form-table'>";

    echo "<tr><th>Name</th><td><input type='text' name='name' value='".esc_attr($contest->name ?? '')."' style='width:400px'></td></tr>";
    echo "<tr><th>Slug</th><td><input type='text' name='slug' value='".esc_attr($contest->slug ?? '')."' style='width:400px'></td></tr>";
    echo "<tr><th>Jahr</th><td><input type='number' name='year' value='".esc_attr($contest->year ?? date('Y'))."'></td></tr>";

    echo "<tr><th>Status</th><td><select name='status'>";
    foreach(['draft','active','archived'] as $status){
        echo "<option value='".esc_attr($status)."' ".selected($contest->status ?? '', $status, false).">".esc_html($status)."</option>";
    }
    echo "</select></td></tr>";

    $fields = [
        'application_start' => 'Bewerbung Start',
        'application_end'   => 'Bewerbung Ende',
        'top20_start'       => 'Top Voting Start',
        'top20_end'         => 'Top Voting Ende',
        'vaus_start'        => 'Voting Auswertung Start',
        'vaus_end'          => 'Voting Auswertung Ende',
        'final_start'       => 'Finale Start',
        'final_end'         => 'Finale Ende',
        'winner_date'       => 'Winner Datum'
    ];

    foreach($fields as $name => $label){
        echo "<tr><th>".esc_html($label)."</th><td><input type='datetime-local' name='".esc_attr($name)."' value='".scp_format_datetime_local_value($contest->$name ?? '')."' style='width:240px'></td></tr>";
    }

    echo "<tr><th>Maximale Voting-Teilnehmer</th><td><input type='number' min='1' name='max_voting_participants' value='".esc_attr($contest->max_voting_participants ?? 30)."'></td></tr>";
    echo "<tr><th>Maximale Finalisten</th><td><input type='number' min='1' name='max_finalists' value='".esc_attr($contest->max_finalists ?? 5)."'></td></tr>";

    echo "</table>";
}

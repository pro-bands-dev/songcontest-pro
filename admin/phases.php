<?php

function scp_phases_page(){

    if(!current_user_can('manage_options')){
        return;
    }

    wp_safe_redirect(admin_url('admin.php?page=songcontest-contests'));
    exit;
}

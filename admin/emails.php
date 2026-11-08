<?php
function scp_email_settings_page(){

if(!current_user_can('manage_options')){
wp_die('Keine Berechtigung.');
}

// Sicherheit: Speichern nur mit gültigem Sicherheitstoken (Nonce).
if(isset($_POST['save_emails']) && check_admin_referer('scp_save_emails', '_scpnonce')){
update_option('scp_mail_application', wp_kses_post(wp_unslash($_POST['mail_application'] ?? '')));
update_option('scp_mail_voting', wp_kses_post(wp_unslash($_POST['mail_voting'] ?? '')));
update_option('scp_mail_final', wp_kses_post(wp_unslash($_POST['mail_final'] ?? '')));
update_option('scp_mail_winner', wp_kses_post(wp_unslash($_POST['mail_winner'] ?? '')));
if(isset($_POST['mail_marketing_optin'])){
update_option('scp_mail_marketing_optin', wp_kses_post(wp_unslash($_POST['mail_marketing_optin'])));
}
echo "<div class='updated'><p>E-Mail Texte gespeichert.</p></div>";
}
?>
<div class="wrap">
<h1>Song Contest – E-Mail Einstellungen</h1>
<form method="post">
<?php wp_nonce_field('scp_save_emails', '_scpnonce'); ?>
<h2>Bewerbungsbestätigung</h2>
<textarea name="mail_application" rows="8" style="width:100%;">
<?php echo esc_textarea(get_option('scp_mail_application')); ?>
</textarea>

<h2>Voting Start</h2>
<textarea name="mail_voting" rows="8" style="width:100%;">
<?php echo esc_textarea(get_option('scp_mail_voting')); ?>
</textarea>

<h2>Finale</h2>
<textarea name="mail_final" rows="8" style="width:100%;">
<?php echo esc_textarea(get_option('scp_mail_final')); ?>
</textarea>

<h2>Gewinner</h2>
<textarea name="mail_winner" rows="8" style="width:100%;">
<?php echo esc_textarea(get_option('scp_mail_winner')); ?>
</textarea>

<h2>Bestätigung "Infos &amp; Angebote" (Double-Opt-In)</h2>
<p class="description">Wird nur verschickt, wenn bei der Bewerbung das Kästchen gesetzt wurde. Platzhalter: {band}, {site}, {confirm_link}. Leer lassen = Standardtext.</p>
<textarea name="mail_marketing_optin" rows="10" style="width:100%;">
<?php echo esc_textarea(get_option('scp_mail_marketing_optin') ?: (function_exists('scp_marketing_optin_default_template') ? scp_marketing_optin_default_template() : '')); ?>
</textarea>

<p>
<button class="button button-primary" name="save_emails">
Speichern
</button>
</p>
</form>
</div>
<?php
}
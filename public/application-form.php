<?php

add_shortcode("songcontest_application","scp_application");

function scp_application(){

ob_start();

?>

<form method="post" class="scp-form">

<input name="bandname" placeholder="Bandname*" required>
<input name="email" placeholder="Email*" required>
<input name="songtitle" placeholder="Songtitel">

<textarea name="band_info" placeholder="Bandinfo"></textarea>

<button name="apply">Bewerbung senden</button>

</form>

<?php

if(isset($_POST['apply'])){

global $wpdb;

$wpdb->insert(
$wpdb->prefix.'scp_artists',
[
'name'=>sanitize_text_field($_POST['bandname']),
'email'=>sanitize_email($_POST['email']),
'title'=>sanitize_text_field($_POST['songtitle']),
'band_info'=>sanitize_textarea_field($_POST['band_info'])
]);

echo "<p>Bewerbung gespeichert.</p>";

}

return ob_get_clean();

}
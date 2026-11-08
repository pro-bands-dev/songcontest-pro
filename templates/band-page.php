<?php

get_header();

global $wpdb;
$slug = get_query_var('scp_band');

$band = $wpdb->get_row($wpdb->prepare("
SELECT *
FROM {$wpdb->prefix}scp_artists
WHERE slug = %s
",$slug));

if(!$band){
echo "<h2>Band nicht gefunden</h2>";
get_footer();
exit;
}

$votes = $wpdb->get_var($wpdb->prepare("
SELECT COUNT(*)
FROM {$wpdb->prefix}scp_votes
WHERE artist_id=%d
",$band->id));
?>

<div class="scp-band-profile">
<h1><?php echo esc_html($band->name); ?></h1>
<h3><?php echo esc_html($band->title); ?></h3>
<?php if($band->image){ ?>
<img src="<?php echo esc_url($band->image); ?>" class="scp-band-image">
<?php } ?>
<p><?php echo esc_html($band->band_info); ?></p>
<?php if($band->audio){ ?>
<audio controls>
<source src="<?php echo esc_url($band->audio); ?>" type="audio/mpeg">
</audio>
<?php } ?>
<p class="scp-vote-count">
🔥 <?php echo $votes; ?> Votes
</p>
<button class="vote scp-vote-btn" data-id="<?php echo $band->id; ?>">
👍 Jetzt abstimmen
</button>
</div>
<?php
get_footer();
<?php

$id = 'onyx-poll-' . $block['id'];
if(!empty($block['anchor'])) {
	$id = $block['anchor'];
}

$className = 'onyx-poll-';
if(!empty($block['className'])) {
	$className .= '' . $block['className'];
}
if( !empty($block['align']) ) {
	$className .= 'align' . $block['align'];
}

$poll_id = (int) get_field('onyx_poll_block_id');
$poll_style = get_field('onyx_poll_block_style');

?>

<div id="<?php echo esc_attr($id); ?>" class="onyx-poll-block <?php echo esc_attr($className); ?>">
	<?php echo do_shortcode("[onyx-poll id='$poll_id' class='$poll_style']") ?>
</div>

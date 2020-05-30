<?php

// register a testimonial block.
add_action('acf/init', function() {
	acf_register_block_type(array(
		'name'              => 'acf-onyx-poll',
		'title'             => __('Onyx Poll', 'acf-onyx-poll'),
		'description'       => __('Insert a poll in the post', 'acf-onyx-poll'),
		'render_template'   => plugin_dir_path(__FILE__) . 'block/block.php',
		'category'          => 'widgets',
		'icon'              => 'chart-bar',
		'keywords'          => array('poll', 'enquete', 'onyx'),
		'mode'              => 'auto',
		'enqueue_assets'    => function() {
			add_action('admin_footer', 'OnyxPolls::add_assets');
		},
		'supports' => array(
			'mode' => true,
			'align' => true,
		)
	));
});

/* customize html return of the blocks object field*/
add_filter('acf/fields/post_object/result/key=field_5ed174c6b5a8f', 'acf_onyx_poll_object_result', 10, 4);
function acf_onyx_poll_object_result($title, $post, $field, $post_id) {
	$title = "<span class='id ref'>[$post->ID]</span> / <span class='title'>$title</span>";
	return $title;
}

/* customize query from blocks post object field */
add_filter('acf/fields/post_object/quer/key=field_5ed174c6b5a8f', 'acf_onyx_poll_post_object_query', 10, 3);
function acf_onyx_poll_post_object_query($args, $field, $post) {
	$args['order']		= 'DESC';
	$args['orderby']	= 'ID';

	// search by ID
	$search = !empty($args['s']) ? $args['s'] : false;
	if($search && is_numeric($search)) {
		$args['post__in'] = array($search);
		unset($args['s']);
	}

	return $args;
}

?>

<?php
if (!defined('ABSPATH')) { exit; }

/** Keep the serialized name so existing posts need no content rewrite. */
add_action('init', function() {
	wp_register_script('onyx-poll-block', plugins_url('assets/js/block.js', ACF_ONYX_POLL_FILE),
		array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-api-fetch', 'wp-i18n'), OnyxPolls::get_asset_vars('assets/js/block.js')->ver, true);
	wp_localize_script('onyx-poll-block', 'onyxPollBlockLabels', array(
		'Onyx Poll' => __('Onyx Poll', 'acf-onyx-poll'), 'Poll' => __('Poll', 'acf-onyx-poll'),
		'Style' => __('Style', 'acf-onyx-poll'), 'Bar style' => __('Bar style', 'acf-onyx-poll'), 'Settings' => __('Settings', 'acf-onyx-poll'),
		'Twitter' => __('Twitter', 'acf-onyx-poll'), 'votes' => __('votes', 'acf-onyx-poll'),
		'vote count singular' => _x('vote', 'vote count noun', 'acf-onyx-poll'),
		'Total votes' => __('Total votes', 'acf-onyx-poll'),
		'Add a poll to a post' => __('Add a poll to a post', 'acf-onyx-poll'),
		'Select a poll to preview it.' => __('Select a poll to preview it.', 'acf-onyx-poll')));
	wp_register_style('onyx-poll-editor', plugins_url('assets/css/block-editor.css', ACF_ONYX_POLL_FILE), array(), OnyxPolls::get_asset_vars('assets/css/block-editor.css')->ver);
	$editor_style = array('onyx-poll-editor');
	if (!OnyxPollsStorage::settings()['css']) {
		wp_register_style('onyx-poll-block', plugins_url('assets/css/onyx-poll.css', ACF_ONYX_POLL_FILE), array(), OnyxPolls::get_asset_vars('assets/css/onyx-poll.css')->ver);
		array_unshift($editor_style, 'onyx-poll-block');
	}
	register_block_type('acf/acf-onyx-poll', array(
		'api_version' => 3, 'title' => __('Onyx Poll', 'acf-onyx-poll'), 'category' => 'widgets', 'icon' => 'chart-bar',
		'editor_script' => 'onyx-poll-block', 'editor_style' => $editor_style,
		'attributes' => array('pollId' => array('type' => 'integer', 'default' => 0),
			'pollStyle' => array('type' => 'string', 'default' => ''), 'data' => array('type' => 'object', 'default' => array()),
			'id' => array('type' => 'string'), 'name' => array('type' => 'string'), 'mode' => array('type' => 'string')),
		'supports' => array('align' => true, 'anchor' => true, 'html' => false),
		'render_callback' => 'onyx_poll_render_block'));
});

function onyx_poll_render_block($attributes) {
	$data = isset($attributes['data']) && is_array($attributes['data']) ? $attributes['data'] : array();
	$id = !empty($attributes['pollId']) ? absint($attributes['pollId']) : absint($data['onyx_poll_block_id'] ?? $data['field_5ed174c6b5a8f'] ?? 0);
	$style = !empty($attributes['pollStyle']) ? $attributes['pollStyle'] : ($data['onyx_poll_block_style'] ?? $data['field_5ed17b7ef8646'] ?? 'standard');
	$style = $style === 'twitter' ? 'twitter' : 'standard';
	$classes = 'onyx-poll-block';
	if (!empty($attributes['align'])) { $classes .= ' align' . sanitize_html_class($attributes['align']); }
	if (!empty($attributes['className'])) { $classes .= ' ' . $attributes['className']; }
	$anchor = !empty($attributes['anchor']) ? ' id="' . esc_attr($attributes['anchor']) . '"' : '';
	return '<div' . $anchor . ' class="' . esc_attr($classes) . '">' . do_shortcode('[onyx-poll id="' . $id . '" class="' . $style . '"]') . '</div>';
}

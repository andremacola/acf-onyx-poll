<?php
if (!defined('ABSPATH')) { exit; }

class OnyxPollsUninstall {
	/** An exact allowlist. Never match table/option names or post content by prefix. */
	public static function run() {
		global $wpdb;
		$settings = get_option('onyx_poll_settings', array());
		if (!is_array($settings) || empty($settings['delete_on_uninstall'])) { return; }
		// Direct deletion deliberately avoids wp_delete_post's cascades into other tables,
		// revisions and attachment parent relationships. Legacy postmeta stays untouched.
		$ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_type=%s", 'onyxpolls'));
		$wpdb->query($wpdb->prepare("DELETE FROM $wpdb->posts WHERE post_type=%s", 'onyxpolls'));
		foreach ($ids as $id) {
			wp_cache_delete($id, 'posts');
			wp_cache_delete($id, 'post_meta');
		}
		wp_cache_set('last_changed', microtime(), 'posts');
		foreach (array('onyx_poll_answers', 'onyx_polls') as $name) {
			$wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', $wpdb->prefix . $name) . '`');
		}
		foreach (array('onyx_poll_settings', 'onyx_poll_db_version', 'onyx_poll_migration_complete', 'onyx_poll_cron_status', 'widget_onyx_poll',
			'options_onyx_poll_modal_time', '_options_onyx_poll_modal_time',
			'options_onyx_poll_css', '_options_onyx_poll_css',
			'options_onyx_poll_image_size', '_options_onyx_poll_image_size') as $name) {
			delete_option($name);
		}
	}
}

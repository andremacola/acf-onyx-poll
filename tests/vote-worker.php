<?php
if (!defined('WP_CLI') || !WP_CLI || !isset($args[0], $args[1], $args[2]) || !preg_match('/^onyxp_test_[a-f0-9]{10}_$/', $args[0])) { exit(1); }
global $wpdb, $wp_object_cache;
$wpdb->set_prefix($args[0]);
$wp_object_cache = new WP_Object_Cache();
for ($i = 0; $i < (int) $args[2]; $i++) {
	if (OnyxPollsStorage::vote((int) $args[1], 1) !== true) { exit(2); }
}

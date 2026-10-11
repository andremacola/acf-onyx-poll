<?php
/** One independent anonymous REST vote against the parent's isolated fixture tables. */
if (!defined('WP_CLI') || !WP_CLI || !isset($args[0], $args[1]) || !preg_match('/^onyxp_test_[a-f0-9]{10}_$/D', $args[0])) { exit(1); }
global $wpdb, $wp_object_cache;
$wpdb->set_prefix($args[0]);
$wp_object_cache = new WP_Object_Cache();
wp_set_current_user(0);
$_COOKIE = json_decode(getenv('ONYX_SECURITY_COOKIES'), true);
if (!is_array($_COOKIE)) { exit(2); }
$api = new OnyxPollsApi();
$list = new WP_REST_Request('GET');
$list->set_param('id', (int) $args[1]);
$response = $api->list($list);
if (is_wp_error($response)) { exit(3); }
$vote = new WP_REST_Request('POST');
$vote->set_param('poll', (int) $args[1]);
$vote->set_param('choice', 1);
$vote->set_header('X-Onyx-Poll-Token', $response->get_data()['vote_token']);
$response = $api->vote($vote);
if (is_wp_error($response)) { exit(4); }
echo !empty($response->get_data()['voted']) ? 'counted' : 'blocked';

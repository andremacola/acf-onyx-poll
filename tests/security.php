<?php
/** Run with wp eval-file tests/security.php. All writes use disposable fixture tables. */
if (!defined('WP_CLI') || !WP_CLI || !class_exists('OnyxPollsStorage')) { exit(1); }
global $wpdb, $wp_object_cache, $wp_rest_server, $current_user, $checks, $failures;
$original_prefix = $wpdb->prefix;
$original_cache = $wp_object_cache;
$original_rest = $wp_rest_server;
$original_user = wp_get_current_user();
$original_cookies = $_COOKIE;
$original_home = home_url();
$original_site = site_url();
$prefix = 'onyxp_test_' . substr(md5(uniqid('', true)), 0, 10) . '_';
$created = array();
$failures = array();
$checks = 0;
function onyx_security_check($condition, $message) {
	global $checks, $failures;
	$checks++;
	if (!$condition) { $failures[] = $message; }
}
function onyx_security_poll($limit = 1, $password = '') {
	$id = wp_insert_post(array('post_type' => 'onyxpolls', 'post_status' => 'publish', 'post_title' => 'Security fixture', 'post_password' => $password), true);
	if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
	update_post_meta($id, 'onyx_poll_end', '2035-01-01 00:00:00');
	update_post_meta($id, 'onyx_poll_limit_vote', $limit);
	update_post_meta($id, 'onyx_poll_results', 3);
	update_post_meta($id, 'onyx_poll_show_results', 1);
	update_post_meta($id, 'onyx_poll_answers', 2);
	foreach (array('First', 'Second') as $i => $text) {
		update_post_meta($id, 'onyx_poll_answers_' . $i . '_answer', $text);
		update_post_meta($id, 'onyx_poll_answers_' . $i . '_votes', 0);
	}
	OnyxPollsStorage::get($id);
	return $id;
}
function onyx_security_request($method, $route, $params = array(), $headers = array()) {
	$request = new WP_REST_Request($method, '/onyx/polls/' . $route);
	foreach ($params as $key => $value) { $request->set_param($key, $value); }
	foreach ($headers as $key => $value) { $request->set_header($key, $value); }
	$response = rest_do_request($request);
	return apply_filters('rest_post_dispatch', $response, rest_get_server(), $request);
}
function onyx_security_headers($id) {
	$data = onyx_security_request('GET', 'list', array('id' => $id))->get_data();
	return isset($data['vote_token']) ? array('X-Onyx-Poll-Token' => $data['vote_token']) : array();
}
try {
	foreach ($wpdb->tables('blog', true) as $name) {
		$target = $prefix . substr($name, strlen($original_prefix));
		if ($wpdb->query("CREATE TABLE `$target` LIKE `$name`") === false) { throw new RuntimeException('Cannot create security fixture tables.'); }
		$created[] = $target;
	}
	$wpdb->set_prefix($prefix);
	$wp_object_cache = new WP_Object_Cache();
	wp_set_current_user(0);
	$_COOKIE = array();
	update_option('home', $original_home);
	update_option('siteurl', $original_site);
	OnyxPollsStorage::upgrade();
	foreach (array('onyx_polls', 'onyx_poll_answers', 'onyx_poll_guards') as $name) { $created[] = $prefix . $name; }
	$wp_rest_server = new WP_REST_Server();
	do_action('rest_api_init', $wp_rest_server);
	$poll = onyx_security_poll();
	foreach (array('GET', 'HEAD', 'PUT', 'PATCH', 'DELETE') as $method) {
		$before = OnyxPollsStorage::get($poll)['total'];
		$response = onyx_security_request($method, 'vote', array('poll' => $poll, 'choice' => 1));
		onyx_security_check($response->get_status() >= 400 && OnyxPollsStorage::get($poll)['total'] === $before, $method . ' must never cast a vote.');
	}
	$before = OnyxPollsStorage::get($poll)['total'];
	$response = onyx_security_request('POST', 'vote', array('poll' => $poll, 'choice' => 1));
	onyx_security_check($response->get_status() === 403 && OnyxPollsStorage::get($poll)['total'] === $before, 'A POST without a session-bound vote token must be rejected.');
	$headers = onyx_security_headers($poll);
	$headers['Origin'] = 'https://attacker.example';
	$response = onyx_security_request('POST', 'vote', array('poll' => $poll, 'choice' => 1), $headers);
	onyx_security_check($response->get_status() === 403 && OnyxPollsStorage::get($poll)['total'] === $before, 'Foreign origins must not cast votes, even with a token.');
	$headers = onyx_security_headers($poll);
	$headers['Sec-Fetch-Site'] = 'cross-site';
	$response = onyx_security_request('POST', 'vote', array('poll' => $poll, 'choice' => 1), $headers);
	onyx_security_check($response->get_status() === 403 && OnyxPollsStorage::get($poll)['total'] === $before, 'Cross-site fetch metadata must be rejected.');
	$headers = onyx_security_headers($poll);
	$response = onyx_security_request('POST', 'vote', array('poll' => $poll, 'choice' => 1), $headers);
	onyx_security_check($response->get_status() === 200 && !empty($response->get_data()['voted']), 'A valid same-site anonymous vote remains supported.');
	$protected = onyx_security_poll(1, 'fixture-password');
	$response = onyx_security_request('GET', 'list', array('id' => $protected));
	onyx_security_check($response->get_status() >= 400 || !isset($response->get_data()['answers']), 'A password-protected poll must not expose answers without its password.');
	$response = onyx_security_request('POST', 'vote', array('poll' => $protected, 'choice' => 1), onyx_security_headers($protected));
	onyx_security_check(OnyxPollsStorage::get($protected)['total'] === 0, 'A password-protected poll must reject unauthorized votes.');
	require_once ABSPATH . WPINC . '/class-phpass.php';
	$hasher = new PasswordHash(8, true);
	$_COOKIE['wp-postpass_' . COOKIEHASH] = $hasher->HashPassword('fixture-password');
	$protected_headers = onyx_security_headers($protected);
	$response = onyx_security_request('POST', 'vote', array('poll' => $protected, 'choice' => 1), $protected_headers);
	onyx_security_check($response->get_status() === 200 && OnyxPollsStorage::get($protected)['total'] === 1, 'The standard WordPress password cookie authorizes the protected poll.');
	$_COOKIE['wp-postpass_' . COOKIEHASH] = array('malformed');
	$response = onyx_security_request('GET', 'list', array('id' => $protected));
	onyx_security_check($response->get_status() === 404, 'Malformed password cookies fail closed without a PHP fatal error.');
	unset($_COOKIE['wp-postpass_' . COOKIEHASH]);
	$limited = onyx_security_poll(30);
	$headers = onyx_security_headers($limited);
	onyx_security_request('POST', 'vote', array('poll' => $limited, 'choice' => 1), $headers);
	unset($_COOKIE['onyx_poll_limit_' . $limited]);
	onyx_security_request('POST', 'vote', array('poll' => $limited, 'choice' => 1), $headers);
	onyx_security_check(OnyxPollsStorage::get($limited)['total'] === 1, 'Deleting only the client limit cookie must not allow a second vote.');
	$wpdb->query($wpdb->prepare('UPDATE ' . OnyxPollsSecurity::table() . ' SET expires=%d WHERE poll_id=%d', time() - 1, $limited));
	onyx_security_request('POST', 'vote', array('poll' => $limited, 'choice' => 1), $headers);
	onyx_security_check(OnyxPollsStorage::get($limited)['total'] === 2, 'Interval voting resumes when the server-side interval expires.');
	$foreign = onyx_security_request('GET', 'list', array('id' => $poll), array('Origin' => 'https://attacker.example'))->get_data();
	onyx_security_check(!isset($foreign['vote_token']), 'Cross-origin public reads never issue a vote token.');
	$jsonp = onyx_security_request('GET', 'list', array('id' => $poll, '_jsonp' => 'callback'))->get_data();
	onyx_security_check(!isset($jsonp['vote_token']), 'JSONP never discloses a session-bound voting token.');
	$subdomain = onyx_security_request('GET', 'list', array('id' => $poll), array('Sec-Fetch-Site' => 'same-site'))->get_data();
	onyx_security_check(!isset($subdomain['vote_token']), 'An unverified same-site subdomain cannot obtain a voting token.');
	$second = onyx_security_poll();
	$response = onyx_security_request('POST', 'vote', array('poll' => $second, 'choice' => 1), $headers);
	onyx_security_check($response->get_status() === 403 && OnyxPollsStorage::get($second)['total'] === 0, 'A token for one poll must not authorize another poll.');
	$headers = onyx_security_headers($second);
	$old_cookie = $_COOKIE;
	$_COOKIE = array();
	$response = onyx_security_request('POST', 'vote', array('poll' => $second, 'choice' => 1), $headers);
	onyx_security_check($response->get_status() === 403 && OnyxPollsStorage::get($second)['total'] === 0, 'A token must not work in a different or missing browser session.');
	$_COOKIE = $old_cookie;
	$bad = onyx_security_headers($second);
	$bad['X-Onyx-Poll-Token'] = substr($bad['X-Onyx-Poll-Token'], 0, -1) . (substr($bad['X-Onyx-Poll-Token'], -1) === '0' ? '1' : '0');
	$response = onyx_security_request('POST', 'vote', array('poll' => $second, 'choice' => 1), $bad);
	onyx_security_check($response->get_status() === 403 && OnyxPollsStorage::get($second)['total'] === 0, 'Tampered signatures cannot authorize a vote.');
	$site_headers = onyx_security_headers($second);
	$original_blog_id = get_current_blog_id();
	$GLOBALS['blog_id'] = $original_blog_id + 1;
	try { $response = onyx_security_request('POST', 'vote', array('poll' => $second, 'choice' => 1), $site_headers); }
	finally { $GLOBALS['blog_id'] = $original_blog_id; }
	onyx_security_check($response->get_status() === 403 && OnyxPollsStorage::get($second)['total'] === 0, 'A token is also bound to its WordPress site in a shared-salt network.');
	$headers = onyx_security_headers($second);
	$snapshot = OnyxPollsStorage::get($second);
	$data = array_intersect_key($snapshot, OnyxPollsStorage::defaults());
	unset($data['version']);
	OnyxPollsStorage::save($second, $data, array_reverse($snapshot['answers']), $snapshot);
	$response = onyx_security_request('POST', 'vote', array('poll' => $second, 'choice' => 1), $headers);
	onyx_security_check($response->get_status() >= 400 && OnyxPollsStorage::get($second)['total'] === 0, 'Reordering answers must invalidate a previously issued vote token.');
	$valid = onyx_security_headers($second);
	foreach (array('null', 'https://attacker.example', $original_home . '.attacker.example') as $origin) {
		$headers = $valid;
		$headers['Origin'] = $origin;
		$response = onyx_security_request('POST', 'vote', array('poll' => $second, 'choice' => 1), $headers);
		onyx_security_check($response->get_status() === 403, 'Origin validation rejects opaque, foreign and look-alike origins.');
	}
	foreach (array(array('nested'), true, -1, 1.5, '1 OR 1=1', str_repeat('9', 100)) as $invalid) {
		$response = onyx_security_request('POST', 'vote', array('poll' => $second, 'choice' => $invalid), $valid);
		onyx_security_check($response->get_status() === 400 && OnyxPollsStorage::get($second)['total'] === 0, 'Malformed, overflowing or SQL-like answer IDs never cast votes.');
	}
	$response = onyx_security_request('GET', 'editor');
	onyx_security_check($response->get_status() >= 400, 'Anonymous visitors cannot use the editor endpoint.');
	$current_user = new WP_User();
	$current_user->ID = 999999;
	$current_user->allcaps = array('read' => true, 'edit_posts' => true);
	require_once dirname(__DIR__) . '/admin/poll-admin.php';
	$admin = new OnyxPollsAdmin();
	ob_start();
	$admin->settings_form();
	$html = ob_get_clean();
	onyx_security_check(strpos($html, 'action="' . esc_url(admin_url('admin-post.php')) . '"') === false, 'A contributor must not receive the site-wide settings form.');
	$denied = false;
	$die_handler = function() { return function($message) { throw new RuntimeException('fixture permission denial'); }; };
	add_filter('wp_die_handler', $die_handler);
	$settings_before = OnyxPollsStorage::settings();
	try { $admin->save_settings(); }
	catch (RuntimeException $error) { $denied = $error->getMessage() === 'fixture permission denial'; }
	finally { remove_filter('wp_die_handler', $die_handler); }
	onyx_security_check($denied && OnyxPollsStorage::settings() === $settings_before, 'A contributor cannot write global settings through the admin-post handler.');
	wp_set_current_user(0);
	$response = onyx_security_request('GET', 'list', array('id' => $poll));
	onyx_security_check(strpos($response->get_headers()['Cache-Control'] ?? '', 'no-store') !== false, 'Personalized voting responses must not be stored by shared caches.');
	$_COOKIE = array();
	$rate_poll = onyx_security_poll();
	$headers = onyx_security_headers($rate_poll);
	$blocked = false;
	for ($i = 0; $i < 20; $i++) {
		$response = onyx_security_request('POST', 'vote', array('poll' => $rate_poll, 'choice' => 1), $headers);
		if ($response->get_status() === 429) { $blocked = true; break; }
	}
	onyx_security_check($blocked && OnyxPollsStorage::get($rate_poll)['total'] <= 10, 'A browser session must not submit an unbounded voting burst.');
	$_COOKIE = array();
	$response = onyx_security_request('POST', 'vote', array('poll' => $rate_poll, 'choice' => 1), onyx_security_headers($rate_poll));
	onyx_security_check($response->get_status() === 200 && !empty($response->get_data()['voted']), 'A different browser sharing the connection can vote without an IP block.');
	$race_poll = onyx_security_poll(2);
	onyx_security_headers($race_poll);
	$workers = array();
	for ($i = 0; $i < 4; $i++) {
		$command = array(PHP_BINARY, '-d', 'mysqli.default_socket=' . ini_get('mysqli.default_socket'), $_SERVER['argv'][0],
			'--path=' . ABSPATH, '--skip-themes', '--skip-plugins=advanced-custom-fields-pro', 'eval-file', __DIR__ . '/security-worker.php', $prefix, (string) $race_poll);
		$pipes = array();
		$environment = array_merge(getenv(), array('ONYX_SECURITY_COOKIES' => wp_json_encode($_COOKIE)));
		$process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, $environment);
		if (!is_resource($process)) { throw new RuntimeException('Cannot launch security concurrency worker.'); }
		fclose($pipes[0]);
		$workers[] = array($process, $pipes);
	}
	$counted = 0;
	foreach ($workers as $worker) {
		$output = stream_get_contents($worker[1][1]);
		$error = stream_get_contents($worker[1][2]);
		fclose($worker[1][1]); fclose($worker[1][2]);
		onyx_security_check(proc_close($worker[0]) === 0 && $error === '', 'Independent anonymous vote workers complete without an error.');
		$counted += $output === 'counted' ? 1 : 0;
	}
	onyx_security_check($counted === 1 && OnyxPollsStorage::get($race_poll)['total'] === 1, 'Four simultaneous votes from one browser count exactly once.');
	$unavailable_poll = onyx_security_poll();
	$unavailable_headers = onyx_security_headers($unavailable_poll);
	$table = OnyxPollsSecurity::table();
	$wpdb->query("RENAME TABLE $table TO {$table}_offline");
	$suppressed = $wpdb->suppress_errors(true);
	try { $response = onyx_security_request('POST', 'vote', array('poll' => $unavailable_poll, 'choice' => 1), $unavailable_headers); }
	finally { $wpdb->query("RENAME TABLE {$table}_offline TO $table"); $wpdb->suppress_errors($suppressed); }
	onyx_security_check($response->get_status() === 503 && OnyxPollsStorage::get($unavailable_poll)['total'] === 0, 'Security storage failures reject votes instead of bypassing the limits.');
	$first_cron = onyx_security_request('GET', 'cron');
	$record = get_option(OnyxPollsCron::OPTION);
	onyx_security_request('GET', 'cron');
	onyx_security_check(get_option(OnyxPollsCron::OPTION) === $record, 'Repeated public cron requests do not rerun the task or rewrite its status.');
	$wpdb->query($wpdb->prepare('UPDATE ' . OnyxPollsSecurity::table() . ' SET expires=%d WHERE poll_id=0', time() - 1));
	OnyxPollsSecurity::cleanup();
	onyx_security_check(!(int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . OnyxPollsSecurity::table() . ' WHERE expires<=%d', time())), 'Expired security guards are pruned without touching live vote counters.');
	if ($failures) { throw new RuntimeException(count($failures) . ' of ' . $checks . ' security checks failed: ' . implode(' | ', $failures)); }
	WP_CLI::success($checks . ' security checks passed in isolated tables.');
} finally {
	$wpdb->set_prefix($original_prefix);
	$wp_object_cache = $original_cache;
	$wp_rest_server = $original_rest;
	$current_user = $original_user;
	$_COOKIE = $original_cookies;
	foreach ($created as $table) { if (strpos($table, $prefix) === 0) { $wpdb->query("DROP TABLE IF EXISTS `$table`"); } }
}

<?php
/** Run with: wp eval-file tests/integration.php (WordPress must load this plugin).
 * All mutations are confined to a fresh random table prefix; never run uninstall on live data.
 */
if (!defined('WP_CLI') || !WP_CLI || !class_exists('OnyxPollsStorage')) { exit(1); }
require_once dirname(__DIR__) . '/classes/poll-uninstall.php';
global $wpdb, $wp_object_cache, $checks;
$original_prefix = $wpdb->prefix;
$original_cache = $wp_object_cache;
$original_tables = $wpdb->tables('blog', true);
$prefix = 'onyxp_test_' . substr(md5(uniqid('', true)), 0, 10) . '_';
$created = array();
$checks = 0;
function onyx_check($condition, $message) {
	global $checks;
	if (!$condition) { throw new RuntimeException($message); }
	$checks++;
}
function onyx_fixture($title, $type = 'onyxpolls', $status = 'publish', $content = '') {
	$id = wp_insert_post(array('post_title' => $title, 'post_type' => $type, 'post_status' => $status, 'post_content' => $content), true);
	if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
	return $id;
}
function onyx_legacy($id, $votes = array(4, 7)) {
	update_post_meta($id, 'onyx_poll_answers', count($votes));
	foreach ($votes as $i => $count) {
		update_post_meta($id, 'onyx_poll_answers_' . $i . '_answer', 'Answer ' . ($i + 1));
		update_post_meta($id, 'onyx_poll_answers_' . $i . '_votes', $count);
		update_post_meta($id, 'onyx_poll_answers_' . $i . '_image', 42 + $i);
	}
	update_post_meta($id, 'onyx_poll_total', 999);
	update_post_meta($id, 'onyx_poll_end', '2035-01-01 00:00:00');
	update_post_meta($id, 'onyx_poll_limit_vote', 1);
	update_post_meta($id, 'onyx_poll_results', 3);
	update_post_meta($id, 'onyx_poll_modal', 1);
}
try {
	foreach ($original_tables as $name) {
		$target = $prefix . substr($name, strlen($original_prefix));
		if ($wpdb->query("CREATE TABLE `$target` LIKE `$name`") === false) { throw new RuntimeException('Cannot create isolated fixture tables.'); }
		$created[] = $target;
	}
	$wpdb->set_prefix($prefix);
	$wp_object_cache = new WP_Object_Cache();
	onyx_check(strpos($wpdb->posts, 'onyxp_test_') === 0, 'Refusing to mutate a live table.');
	$poll_id = onyx_fixture('Legacy poll');
	onyx_legacy($poll_id);
	update_option('options_onyx_poll_css', 1);
	update_option('options_onyx_poll_modal_time', 3);
	OnyxPollsStorage::upgrade();
	$created[] = OnyxPollsStorage::table();
	$created[] = OnyxPollsStorage::table(true);
	$poll = OnyxPollsStorage::get($poll_id);
	onyx_check($poll['total'] === 11, 'The total must be the answer sum, not legacy manual total.');
	onyx_check(array_column($poll['answers'], 'votes') === array(4, 7), 'Migration must preserve individual counters.');
	onyx_check(array_column($poll['answers'], 'image_id') === array(42, 43), 'Migration must preserve attachments and order.');
	onyx_check($poll['modal'] === 1 && $poll['end'] === '2035-01-01 00:00:00', 'Migration must preserve settings.');
	onyx_check(OnyxPollsStorage::settings()['css'] === 1 && OnyxPollsStorage::settings()['modal_time'] === 3, 'Migrate global options.');
	OnyxPollsCron::ensure_scheduled();
	onyx_check(OnyxPollsCron::status()['state'] !== 'healthy', 'Scheduling alone does not prove cron completed a run.');
	wp_clear_scheduled_hook(OnyxPollsCron::HOOK);
	wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', OnyxPollsCron::HOOK);
	onyx_check(OnyxPollsCron::status()['state'] === 'waiting', 'A new cron has no invented execution history.');
	onyx_check(OnyxPollsCron::scheduled_run() === null, 'A cron with no expired polls still completes.');
	onyx_check(OnyxPollsCron::status()['state'] === 'healthy' && get_option(OnyxPollsCron::OPTION)['source'] === 'wp-cron', 'Track successful native cron runs.');
	OnyxPollsCron::run();
	onyx_check(get_option(OnyxPollsCron::OPTION)['source'] === 'endpoint', 'Identify external endpoint runs separately.');
	wp_clear_scheduled_hook(OnyxPollsCron::HOOK);
	wp_schedule_event(time() - 2 * HOUR_IN_SECONDS, 'hourly', OnyxPollsCron::HOOK);
	onyx_check(OnyxPollsCron::status()['state'] === 'late' && OnyxPollsCron::site_health_result()['status'] === 'recommended', 'Overdue cron appears in settings and Site Health.');
	wp_clear_scheduled_hook(OnyxPollsCron::HOOK);
	onyx_check(OnyxPollsCron::status()['state'] === 'missing', 'Detect a missing cron event.');
	wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', OnyxPollsCron::HOOK);
	$cron_record = get_option(OnyxPollsCron::OPTION);
	$cron_record['last_success'] = time() - 2 * HOUR_IN_SECONDS;
	update_option(OnyxPollsCron::OPTION, $cron_record);
	onyx_check(OnyxPollsCron::status()['state'] === 'late', 'A future scheduled event cannot mask stale successful runs.');
	$wpdb->query('RENAME TABLE ' . OnyxPollsStorage::table() . ' TO ' . OnyxPollsStorage::table() . '_offline');
	$suppressed = $wpdb->suppress_errors(true);
	try { $failed_cron = OnyxPollsCron::scheduled_run(); }
	finally { $wpdb->query('RENAME TABLE ' . OnyxPollsStorage::table() . '_offline TO ' . OnyxPollsStorage::table()); $wpdb->suppress_errors($suppressed); }
	onyx_check(is_wp_error($failed_cron) && OnyxPollsCron::status()['state'] === 'error' && get_option(OnyxPollsCron::OPTION)['last_success'] === $cron_record['last_success'], 'Database failure is recorded without claiming success.');
	OnyxPollsCron::scheduled_run();
	onyx_check(OnyxPollsCron::status()['state'] === 'healthy', 'The cron status recovers after a successful retry.');
	onyx_check(OnyxPollsStorage::vote($poll_id, 1) === true, 'Accept a vote.');
	OnyxPollsStorage::migrate_poll($poll_id);
	OnyxPollsStorage::upgrade();
	onyx_check(OnyxPollsStorage::get($poll_id)['total'] === 12, 'Rerunning migration must not overwrite new votes.');
	onyx_check(is_wp_error(OnyxPollsStorage::vote($poll_id, 99)), 'Reject unknown answer.');
	$snapshot = OnyxPollsStorage::get($poll_id);
	$answers = $snapshot['answers'];
	foreach ($answers as &$answer) { $answer['image_id'] = 0; }
	unset($answer);
	OnyxPollsStorage::vote($poll_id, 1);
	$data = array_intersect_key($snapshot, OnyxPollsStorage::defaults());
	unset($data['version']);
	onyx_check(OnyxPollsStorage::save($poll_id, $data, $answers, $snapshot) === true, 'Unchanged counters allow saving while votes arrive.');
	onyx_check(OnyxPollsStorage::get($poll_id)['total'] === 13, 'Admin saves must preserve concurrent votes.');
	onyx_check(is_wp_error(OnyxPollsStorage::save($poll_id, $data, $answers, $snapshot)), 'Reject a stale administrative version.');
	$snapshot = OnyxPollsStorage::get($poll_id);
	$answers = $snapshot['answers'];
	$answers[0]['votes'] = 100;
	OnyxPollsStorage::vote($poll_id, 1);
	onyx_check(is_wp_error(OnyxPollsStorage::save($poll_id, $data, $answers, $snapshot)), 'Counter edits must reject concurrent changes.');
	onyx_check(OnyxPollsStorage::get($poll_id)['total'] === 14, 'Conflicting save rolls back all changes.');
	$snapshot = OnyxPollsStorage::get($poll_id);
	$answers = array_reverse($snapshot['answers']);
	onyx_check(OnyxPollsStorage::save($poll_id, $data, $answers, $snapshot) === true, 'Reorder answers without losing votes.');
	onyx_check(OnyxPollsStorage::get($poll_id)['answers'][0]['votes'] === 7, 'Votes travel with their answer.');
	$api = new OnyxPollsApi();
	$request = new WP_REST_Request('GET');
	$request->set_param('id', $poll_id);
	$response = $api->list($request)->get_data();
	onyx_check($response['results']['total'] === 14 && $response['answers'][0]['option'] === 1, 'Preserve public response shape and one-based choices.');
	$wpdb->update(OnyxPollsStorage::table(), array('show_results' => 0), array('poll_id' => $poll_id));
	$hidden = $api->poll_data($poll_id);
	onyx_check(!isset($hidden['results']) && $hidden['answers'][0]['votes'] === false && $hidden['answers'][0]['percent'] === false, 'Hidden results must not leak counters.');
	$wpdb->update(OnyxPollsStorage::table(), array('end' => '2000-01-01 00:00:00'), array('poll_id' => $poll_id));
	onyx_check(is_wp_error(OnyxPollsStorage::vote($poll_id, 1)), 'Expiration applies without waiting for cron.');
	onyx_check(isset($api->poll_data($poll_id)['results']), 'Expired polls reveal results even when previously hidden.');
	onyx_check(OnyxPollsCron::scheduled_run() === array($poll_id), 'Cron must persist expiration.');
	onyx_check(get_option(OnyxPollsCron::OPTION)['expired_count'] === 1, 'A completed cron records the number of expired polls.');
	onyx_check($api->expire()->get_status() === 204, 'The external cron preserves its no-content response on an empty run.');
	$draft = onyx_fixture('Draft', 'onyxpolls', 'draft');
	onyx_legacy($draft);
	OnyxPollsStorage::get($draft);
	onyx_check(is_wp_error(OnyxPollsStorage::vote($draft, 1)), 'Draft polls cannot receive public votes.');
	onyx_check(!in_array($draft, OnyxPollsStorage::ids(0, false, false, 20), true), 'Draft polls do not appear in public listings.');
	$input = array('limit_vote' => 1, 'results' => 3, 'end' => '2035-01-01T12:00', 'answers' => array(array('answer' => 'A', 'votes' => '0'), array('answer' => 'B', 'votes' => '3')));
	onyx_check(!is_wp_error(OnyxPollsAdmin::validate($input)), 'Valid native form accepted.');
	$input['answers'][0]['votes'] = '-1';
	onyx_check(is_wp_error(OnyxPollsAdmin::validate($input)), 'Reject negative counters.');
	$input['answers'][0]['votes'] = '0';
	$input['end'] = '2035-02-31T00:00';
	onyx_check(is_wp_error(OnyxPollsAdmin::validate($input)), 'Reject invalid calendar dates.');
	$wpdb->update(OnyxPollsStorage::table(), array('expired' => 0, 'end' => '2035-01-01 00:00:00', 'limit_vote' => 30), array('poll_id' => $poll_id));
	$vote = new WP_REST_Request('POST');
	$vote->set_param('poll', $poll_id);
	$vote->set_param('choice', 1);
	$_COOKIE['onyx_poll_limit_' . $poll_id] = 1;
	$before = OnyxPollsStorage::get($poll_id)['total'];
	onyx_check($api->vote($vote)->get_data()['code'] === 'not_allowed', 'Respect vote-limit cookie.');
	onyx_check(OnyxPollsStorage::get($poll_id)['total'] === $before, 'Blocked votes do not increment counts.');
	unset($_COOKIE['onyx_poll_limit_' . $poll_id]);
	onyx_check($api->vote($vote)->get_data()['voted'] === true, 'Accept a REST vote and return results.');
	// Independent PHP processes hit the same MySQL counter concurrently.
	$workers = array();
	for ($i = 0; $i < 4; $i++) {
		$command = array(PHP_BINARY, '-d', 'mysqli.default_socket=' . ini_get('mysqli.default_socket'),
			$_SERVER['argv'][0], '--path=' . ABSPATH, '--skip-themes', '--skip-plugins=advanced-custom-fields-pro',
			'eval-file', __DIR__ . '/vote-worker.php', $prefix, (string) $poll_id, '25');
		$pipes = array();
		$process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		if (!is_resource($process)) { throw new RuntimeException('Unable to launch concurrency worker.'); }
		fclose($pipes[0]);
		$workers[] = array($process, $pipes);
	}
	foreach ($workers as $worker) {
		$output = stream_get_contents($worker[1][1]) . stream_get_contents($worker[1][2]);
		fclose($worker[1][1]); fclose($worker[1][2]);
		onyx_check(proc_close($worker[0]) === 0, 'Concurrency worker failed: ' . $output);
	}
	onyx_check(OnyxPollsStorage::get($poll_id)['total'] === $before + 101, 'All 100 concurrent votes must be counted exactly once.');
	$before += 100;
	$batch_ids = array();
	for ($i = 0; $i < 51; $i++) {
		$batch_id = onyx_fixture('Batch poll ' . $i);
		onyx_legacy($batch_id, array(1, 2));
		$batch_ids[] = $batch_id;
	}
	delete_option(OnyxPollsStorage::MIGRATION);
	OnyxPollsStorage::upgrade();
	$remaining = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts p LEFT JOIN " . OnyxPollsStorage::table() . " o ON o.poll_id=p.ID WHERE p.post_type='onyxpolls' AND o.poll_id IS NULL");
	onyx_check($remaining === 1 && !get_option(OnyxPollsStorage::MIGRATION), 'Migration pauses after a bounded batch of 50.');
	$latest_batch_id = end($batch_ids);
	$wpdb->update($wpdb->posts, array('post_date' => '2034-01-01 00:00:00'), array('ID' => $latest_batch_id));
	onyx_check(OnyxPolls::has_poll() === $latest_batch_id, 'The ID-less shortcode must select the latest poll even before its migration batch.');
	$pending_modal = onyx_fixture('Pending modal');
	onyx_legacy($pending_modal);
	$wpdb->update($wpdb->posts, array('post_date' => '2034-02-01 00:00:00'), array('ID' => $pending_modal));
	$pending_inline = onyx_fixture('Pending non-modal');
	onyx_legacy($pending_inline);
	update_post_meta($pending_inline, 'onyx_poll_modal', 0);
	$wpdb->update($wpdb->posts, array('post_date' => '2034-03-01 00:00:00'), array('ID' => $pending_inline));
	$pending_expired = onyx_fixture('Pending expired modal');
	onyx_legacy($pending_expired);
	update_post_meta($pending_expired, 'onyx_poll_end', '2000-01-01 00:00:00');
	$wpdb->update($wpdb->posts, array('post_date' => '2034-04-01 00:00:00'), array('ID' => $pending_expired));
	onyx_check(OnyxPolls::has_polls(true, true) === $pending_modal, 'Modal selection must respect legacy flags and deadlines while migration is pending.');
	onyx_check(OnyxPolls::has_poll() === $pending_expired, 'The latest shortcode includes expired polls during migration, as after migration.');
	onyx_check(in_array($pending_inline, OnyxPollsStorage::ids(0, false, false, 100), true), 'Public listings must include pending legacy polls without importing unrelated drafts.');
	onyx_check(OnyxPollsStorage::get(end($batch_ids))['total'] === 3, 'Unmigrated polls are imported on demand.');
	OnyxPollsStorage::upgrade();
	onyx_check((int) get_option(OnyxPollsStorage::MIGRATION) === 1, 'Migration can resume to completion.');
	onyx_check(OnyxPollsStorage::get($poll_id)['total'] === $before + 1, 'Batch retry preserves votes in earlier imports.');
	$legacy_block = array('data' => array('onyx_poll_block_id' => $poll_id, 'onyx_poll_block_style' => 'twitter'), 'align' => 'center');
	onyx_check(strpos(onyx_poll_render_block($legacy_block), 'data-poll="' . $poll_id . '"') !== false, 'Render legacy blocks without ACF.');
	$legacy_block['data'] = array('field_5ed174c6b5a8f' => $poll_id, 'field_5ed17b7ef8646' => 'twitter');
	onyx_check(strpos(onyx_poll_render_block($legacy_block), 'twitter') !== false, 'Read legacy field-key attributes.');
	$content = '<!-- wp:acf/acf-onyx-poll ' . wp_json_encode($legacy_block) . ' /--> [onyx-poll id=' . $poll_id . ']';
	$ordinary = onyx_fixture('Post with poll references', 'post', 'publish', $content);
	$page = onyx_fixture('Page with references', 'page', 'publish', $content);
	$image = onyx_fixture('Image', 'attachment', 'inherit');
	$wpdb->update($wpdb->posts, array('post_parent' => $poll_id), array('ID' => $image));
	update_option('onyx_theme_setting', 'keep');
	update_option('onyx_poll_similar_setting', 'keep');
	$comment = wp_insert_comment(array('comment_post_ID' => $poll_id, 'comment_content' => 'Preserve this comment', 'comment_approved' => 1));
	$before_comments = $wpdb->get_results("SELECT * FROM $wpdb->comments ORDER BY comment_ID", ARRAY_A);
	$before_posts = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_type!='onyxpolls' ORDER BY ID", ARRAY_A);
	$before_meta = $wpdb->get_results("SELECT * FROM $wpdb->postmeta ORDER BY meta_id", ARRAY_A);
	OnyxPollsUninstall::run();
	onyx_check(OnyxPollsStorage::get($poll_id)['total'] === $before + 1, 'Default uninstall keeps data.');
	$settings = OnyxPollsStorage::settings();
	$settings['delete_on_uninstall'] = 1;
	update_option(OnyxPollsStorage::SETTINGS, $settings);
	if ($wpdb->prefix !== $prefix) { throw new RuntimeException('Uninstall fixture prefix guard failed.'); }
	$allowed_options = array('onyx_poll_settings', 'onyx_poll_db_version', 'onyx_poll_migration_complete', 'onyx_poll_cron_status', 'widget_onyx_poll', 'options_onyx_poll_modal_time', '_options_onyx_poll_modal_time', 'options_onyx_poll_css', '_options_onyx_poll_css', 'options_onyx_poll_image_size', '_options_onyx_poll_image_size');
	$before_options = array_values(array_filter($wpdb->get_results("SELECT * FROM $wpdb->options ORDER BY option_id", ARRAY_A), function($option) use ($allowed_options) { return !in_array($option['option_name'], $allowed_options, true); }));
	OnyxPollsUninstall::run();
	onyx_check($wpdb->get_results("SELECT * FROM $wpdb->comments ORDER BY comment_ID", ARRAY_A) === $before_comments, 'Uninstall must not cascade into comments.');
	onyx_check($wpdb->get_results("SELECT * FROM $wpdb->options ORDER BY option_id", ARRAY_A) === $before_options, 'Every option outside the allowlist stays byte-for-byte intact.');
	onyx_check((int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type='onyxpolls'") === 0, 'Purge only poll post type, including drafts.');
	onyx_check($wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_type!='onyxpolls' ORDER BY ID", ARRAY_A) === $before_posts, 'Preserve all other posts, pages, attachments, content and relationships byte-for-byte.');
	onyx_check($wpdb->get_results("SELECT * FROM $wpdb->postmeta ORDER BY meta_id", ARRAY_A) === $before_meta, 'Do not touch legacy postmeta.');
	onyx_check(get_option('onyx_theme_setting') === 'keep' && get_option('onyx_poll_similar_setting') === 'keep', 'No prefix-based option deletion.');
	onyx_check(get_option(OnyxPollsStorage::SETTINGS, null) === null && get_option('options_onyx_poll_css', null) === null, 'Delete only listed plugin options.');
	onyx_check(get_option(OnyxPollsCron::OPTION, null) === null, 'Cron diagnostics are removed only when explicit purge is enabled.');
	onyx_check(!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like(OnyxPollsStorage::table()))), 'Drop only the plugin tables.');
	WP_CLI::success($checks . ' integration checks passed in isolated tables.');
} finally {
	$wpdb->set_prefix($original_prefix);
	$wp_object_cache = $original_cache;
	foreach ($created as $table) {
		if (strpos($table, $prefix) === 0) { $wpdb->query("DROP TABLE IF EXISTS `$table`"); }
	}
}

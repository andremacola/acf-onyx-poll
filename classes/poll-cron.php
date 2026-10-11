<?php
if (!defined('ABSPATH')) { exit; }

/** Scheduling and evidence of completed runs; merely viewing status never runs the job. */
class OnyxPollsCron {
	const HOOK = 'onyx-poll-cron';
	const OPTION = 'onyx_poll_cron_status';

	public static function ensure_scheduled() {
		if (wp_next_scheduled(self::HOOK)) { return; }
		$result = wp_schedule_event(time(), 'hourly', self::HOOK, array(), true);
		$record = self::record();
		$record['schedule_error'] = is_wp_error($result) ? $result->get_error_code() : ($result ? '' : 'schedule_failed');
		update_option(self::OPTION, $record, false);
	}

	private static function record() {
		$record = get_option(self::OPTION, array());
		return is_array($record) ? $record : array();
	}

	public static function scheduled_run() { return self::run('wp-cron'); }

	public static function run($source = 'endpoint') {
		$record = self::record();
		$record['last_attempt'] = time();
		$record['source'] = $source === 'wp-cron' ? 'wp-cron' : 'endpoint';
		$record['outcome'] = 'running';
		update_option(self::OPTION, $record, false);
		try {
			$result = OnyxPolls::expire_polls();
		} catch (Throwable $error) {
			$result = new WP_Error('onyx_cron_failed', __('The poll expiration check could not be completed.', 'acf-onyx-poll'), array('status' => 500));
		}
		$record['last_finished'] = time();
		$record['outcome'] = is_wp_error($result) ? 'error' : 'success';
		$record['error'] = is_wp_error($result) ? $result->get_error_code() : '';
		if (!is_wp_error($result)) {
			$record['last_success'] = time();
			$record['expired_count'] = is_array($result) ? count($result) : 0;
		}
		update_option(self::OPTION, $record, false);
		return $result;
	}

	public static function status() {
		$record = self::record();
		$next = wp_next_scheduled(self::HOOK);
		// Match Site Health's late-event grace period when an external scheduler is used.
		$external = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
		$grace = $external ? 15 * MINUTE_IN_SECONDS : 0;
		$state = 'healthy';
		if (!$next || !empty($record['schedule_error'])) { $state = 'missing'; }
		elseif (isset($record['outcome']) && ($record['outcome'] === 'error' || ($record['outcome'] === 'running' && !empty($record['last_attempt']) && $record['last_attempt'] < time() - 5 * MINUTE_IN_SECONDS))) { $state = 'error'; }
		elseif ($next < time() - $grace || (!empty($record['last_success']) && $record['last_success'] < time() - HOUR_IN_SECONDS - 15 * MINUTE_IN_SECONDS)) { $state = 'late'; }
		elseif (empty($record['last_success'])) { $state = 'waiting'; }
		if (isset($record['outcome']) && $record['outcome'] === 'running' && !empty($record['last_attempt']) && $record['last_attempt'] >= time() - 5 * MINUTE_IN_SECONDS) { $state = 'running'; }
		$labels = array(
			'healthy' => __('Running normally', 'acf-onyx-poll'),
			'waiting' => __('Awaiting first recorded run', 'acf-onyx-poll'),
			'running' => __('Check in progress', 'acf-onyx-poll'),
			'late' => __('Scheduled check is overdue', 'acf-onyx-poll'),
			'error' => __('Last check failed', 'acf-onyx-poll'),
			'missing' => __('Event is not scheduled', 'acf-onyx-poll'),
		);
		return array('state' => $state, 'label' => $labels[$state], 'next' => $next, 'external' => $external, 'record' => $record);
	}

	public static function site_health_tests($tests) {
		$tests['direct']['onyx_poll_cron'] = array('label' => __('Onyx Poll scheduled checks', 'acf-onyx-poll'), 'test' => array(__CLASS__, 'site_health_result'));
		return $tests;
	}

	public static function site_health_result() {
		$status = self::status();
		return array('test' => 'onyx_poll_cron', 'label' => $status['label'],
			'status' => in_array($status['state'], array('healthy', 'waiting', 'running'), true) ? 'good' : 'recommended',
			'badge' => array('label' => 'Onyx Poll', 'color' => 'blue'),
			'description' => '<p>' . esc_html__('Onyx Poll checks expired polls every hour. WP-Cron depends on site visits unless your server runs it externally. Poll voting also checks the deadline immediately.', 'acf-onyx-poll') . '</p>',
			'actions' => '<a href="' . esc_url(admin_url('edit.php?post_type=onyxpolls&page=onyx-poll-settings')) . '">' . esc_html__('View poll cron status', 'acf-onyx-poll') . '</a>');
	}
}

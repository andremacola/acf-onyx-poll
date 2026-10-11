<?php
if (!defined('ABSPATH')) { exit; }

class OnyxPollsSettingsHelp {
	private static function date($timestamp) {
		return $timestamp ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : __('Not recorded yet', 'acf-onyx-poll');
	}

	public static function render() {
		$status = OnyxPollsCron::status();
		$record = $status['record'];
		echo '<section class="onyx-settings-panel" aria-labelledby="onyx-cron-title"><div class="onyx-cron-heading"><h2 id="onyx-cron-title">' . esc_html__('Poll cron status', 'acf-onyx-poll') . '</h2><span class="onyx-cron-badge onyx-cron-' . esc_attr($status['state']) . '">' . esc_html($status['label']) . '</span></div><dl class="onyx-cron-details">';
		$values = array(
			__('Frequency', 'acf-onyx-poll') => __('Every hour', 'acf-onyx-poll'),
			__('Last successful check', 'acf-onyx-poll') => self::date(isset($record['last_success']) ? $record['last_success'] : 0),
			__('Next scheduled check', 'acf-onyx-poll') => $status['next'] ? self::date($status['next']) : __('Not scheduled', 'acf-onyx-poll'),
			__('Trigger', 'acf-onyx-poll') => $status['external'] ? __('WP-Cron is not triggered by site visits. An external scheduler is required.', 'acf-onyx-poll') : __('WP-Cron, triggered by site visits', 'acf-onyx-poll'),
			__('Site timezone', 'acf-onyx-poll') => wp_timezone_string(),
		);
		if (!empty($record['last_finished'])) {
			$values[__('Last attempt', 'acf-onyx-poll')] = self::date($record['last_attempt']) . ' · ' . ($record['source'] === 'endpoint' ? __('Plugin REST endpoint', 'acf-onyx-poll') : 'WP-Cron');
		}
		foreach ($values as $label => $value) { echo '<dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd>'; }
		echo '</dl><p>' . esc_html__('Onyx Poll checks expired polls every hour. WP-Cron depends on site visits unless your server runs it externally. Poll voting also checks the deadline immediately.', 'acf-onyx-poll') . '</p>';
		if ($status['state'] === 'waiting') { echo '<p>' . esc_html__('This version starts recording executions after the update. No earlier execution history is available.', 'acf-onyx-poll') . '</p>'; }
		if (current_user_can('manage_options')) {
			echo '<a href="' . esc_url(admin_url('site-health.php')) . '">' . esc_html__('Open WordPress Site Health to check scheduled events and loopback requests.', 'acf-onyx-poll') . '</a>';
		}
		echo '</section><section class="onyx-settings-panel onyx-help" aria-labelledby="onyx-help-title"><h2 id="onyx-help-title">' . esc_html__('Help and frequently asked questions', 'acf-onyx-poll') . '</h2>';
		self::start(__('Do I need ACF or ACF PRO?', 'acf-onyx-poll'));
		self::paragraph(__('No. Onyx Poll has its own editor, settings and database tables. Existing polls are migrated automatically and keep their IDs, answers, images and votes.', 'acf-onyx-poll'));
		self::end();
		self::start(__('How does migration from 1.2.0 to 2.0 work?', 'acf-onyx-poll'));
		self::paragraph(__('Migration starts on activation or on the next WordPress request after updating an active plugin. It copies polls in resumable batches of 50 per request; a poll needed by a block, shortcode, widget or modal is imported on demand. No manual import or ACF installation is required.', 'acf-onyx-poll'));
		self::paragraph(__('Poll IDs, answer order, image references, individual vote counters, dates and settings are preserved. Repeated migration does not overwrite new votes. Old custom fields are kept, but new votes and edits are saved only in the plugin tables.', 'acf-onyx-poll'));
		self::end();
		self::start(__('What are the breaking changes in 2.0?', 'acf-onyx-poll'));
		self::paragraph(__('Version 2.0 requires WordPress 6.3 and PHP 7.4 or newer. The database user must be allowed to create tables. Back up the database and plugin files and test the update on a staging copy before updating a production site.', 'acf-onyx-poll'));
		self::paragraph(__('Existing plugin blocks, shortcodes and widgets remain compatible. Custom code that reads or writes ACF poll fields or legacy post metadata, or relies on ACF forms and hooks, must be adapted to dedicated storage and native forms. Total votes is now read-only and equals the sum of the answer vote counts; a different manually entered total will change.', 'acf-onyx-poll'));
		self::end();
		self::start(__('Can I remove ACF or downgrade after updating?', 'acf-onyx-poll'));
		self::paragraph(__('Onyx Poll and migration work without ACF. Keep ACF if your theme or other plugins need it. Downgrading to 1.2.0 does not copy new votes or edits back into legacy fields, and restoring a backup discards changes made after that backup. Keep a current database backup and test any rollback separately.', 'acf-onyx-poll'));
		self::end();
		self::start(__('How do I add a poll to a post?', 'acf-onyx-poll'));
		self::paragraph(__('Insert the Onyx Poll block, select a poll and choose its style. You can also use a shortcode: without an ID it shows the latest published poll; with an ID it shows that specific poll.', 'acf-onyx-poll'));
		self::code("[onyx-poll]\n[onyx-poll id=85]\n[onyx-poll id=85 class=\"twitter full\"]");
		self::paragraph(__('Replace 85 with your poll ID. Available alignment classes: left, right and full. Styles: twitter and standard. Copy the shortcode from the poll list.', 'acf-onyx-poll'));
		self::end();
		self::start(__('How do I add a poll to a sidebar?', 'acf-onyx-poll'));
		self::paragraph(__('If your theme supports widget areas, go to Appearance > Widgets and add the Poll widget. Select the poll and style in its settings.', 'acf-onyx-poll'));
		self::end();
		self::start(__('How do I customize the appearance?', 'acf-onyx-poll'));
		self::paragraph(__('Keep the default stylesheet enabled to customize these variables in your theme CSS. To replace all plugin styles, enable Use custom CSS and provide a complete stylesheet.', 'acf-onyx-poll'));
		self::code(".onyx-poll {\n  --pollWidgetWidth: 400px;\n  --borderColor: #dbe9f5;\n  --boxShadow: 0 4px 12px 6px rgba(31,70,88,.04);\n  --modalBorderRadius: 4px;\n  --questionColor: #333;\n  --choiceColor: #333;\n  --buttonColor: #333;\n  --choiceHoverBG: #f5f5f5;\n  --choiceBarColor: #e0e0e0;\n  --choiceBorderRadius: 100px;\n  --closeBorderRadius: 100px;\n  --loaderBorderColor: rgb(209,226,240);\n  --loaderBG: #a3caec;\n}");
		self::end();
		self::start(__('What should I do if the cron is delayed?', 'acf-onyx-poll'));
		self::paragraph(__('A site with few visits may run WP-Cron late. Check WordPress Site Health for scheduled event or loopback errors. For predictable execution, ask your host to call wp-cron.php every five minutes. The Onyx task itself remains hourly.', 'acf-onyx-poll'));
		self::paragraph(__('Replace https://example.com with your WordPress site URL in these examples.', 'acf-onyx-poll'));
		self::code("*/5 * * * * curl --fail --silent --show-error 'https://example.com/wp-cron.php?doing_wp_cron' > /dev/null 2>&1");
		self::paragraph(__('Once an external scheduler is configured and tested, you can disable the automatic page trigger in wp-config.php:', 'acf-onyx-poll'));
		self::code("define('DISABLE_WP_CRON', true);");
		self::paragraph(__('For compatibility, the plugin also keeps a direct hourly expiration endpoint. It runs only the Onyx check, not other WordPress tasks. Its executions are identified separately in the status above.', 'acf-onyx-poll'));
		self::code("0 * * * * curl --fail --silent --show-error 'https://example.com/wp-json/onyx/polls/cron' > /dev/null 2>&1");
		echo '<p><a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/" target="_blank" rel="noopener noreferrer">' . esc_html__('WordPress documentation: configuring a server scheduler', 'acf-onyx-poll') . '</a></p>';
		self::end();
		self::start(__('Where are poll and visitor data stored?', 'acf-onyx-poll'));
		self::paragraph(__('Rules, answers and vote counters use onyx_polls and onyx_poll_answers. Temporary session limits use onyx_poll_guards. All three tables use your WordPress database prefix. WordPress stores poll titles and publication status. The plugin stores no visitor IP addresses or usernames; browser cookies remember choices, identify anonymous voting sessions and remember modal dismissal.', 'acf-onyx-poll'));
		self::end();
		self::start(__('Can anonymous polls prevent all voting fraud?', 'acf-onyx-poll'));
		self::paragraph(__('Votes require a signed browser session and a short-lived token. The server enforces voting intervals and a limit of 10 requests per minute per session. IP addresses and browser fingerprints are not used. Someone who deletes every poll cookie or starts another browser session can still vote again; anonymous polls cannot prove a unique person.', 'acf-onyx-poll'));
		self::end();
		self::start(__('What happens when I delete the plugin?', 'acf-onyx-poll'));
		self::paragraph(__('By default, data is kept for reinstallation. If you explicitly select permanent deletion, uninstall removes the three plugin tables, its own listed options and posts of type onyxpolls. Other posts and pages are preserved, including those with poll blocks or shortcodes, as are media files. Legacy custom fields are preserved. Deactivation never deletes poll data.', 'acf-onyx-poll'));
		self::end();
		echo '</section>';
	}

	private static function start($question) { echo '<details><summary>' . esc_html($question) . '</summary>'; }
	private static function paragraph($text) { echo '<p>' . esc_html($text) . '</p>'; }
	private static function code($code) { echo '<pre><code>' . esc_html($code) . '</code></pre>'; }
	private static function end() { echo '</details>'; }
}

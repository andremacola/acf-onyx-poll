<?php
if (!defined('ABSPATH')) { exit; }

class OnyxPollsAdmin {

	private $error = '';

	public function __construct() {
		add_action('edit_form_after_title', array($this, 'form'));
		add_action('save_post_onyxpolls', array($this, 'save'), 10, 2);
		add_action('admin_menu', array($this, 'menu'));
		add_action('admin_post_onyx_poll_settings', array($this, 'save_settings'));
		add_action('admin_enqueue_scripts', array($this, 'assets'));
		add_action('admin_notices', array($this, 'notice'));
		add_filter('redirect_post_location', array($this, 'redirect'));
	}

	public static function limits() {
		return array(1 => __('Free Vote', 'acf-onyx-poll'), 2 => __('Per device', 'acf-onyx-poll'),
			30 => __('Every 30 minutes', 'acf-onyx-poll'), 60 => __('Every 1 hour', 'acf-onyx-poll'),
			120 => __('Every 2 hours', 'acf-onyx-poll'), 180 => __('Every 3 hours', 'acf-onyx-poll'),
			240 => __('Every 4 hours', 'acf-onyx-poll'), 300 => __('Every 5 hours', 'acf-onyx-poll'),
			720 => __('Every 12 hours', 'acf-onyx-poll'), 1440 => __('Every 24 hours', 'acf-onyx-poll'));
	}

	private function select($name, $label, $value, $choices, $description = '') {
		echo '<label class="onyx-admin-field" for="onyx-' . esc_attr($name) . '"><span>' . esc_html($label) . '</span>';
		echo '<select id="onyx-' . esc_attr($name) . '" aria-label="' . esc_attr($label) . '"' . ($description ? ' aria-describedby="onyx-' . esc_attr($name) . '-help"' : '') . ' name="onyx_poll[' . esc_attr($name) . ']">';
		foreach ($choices as $key => $text) {
			echo '<option value="' . esc_attr($key) . '" ' . selected((string) $value, (string) $key, false) . '>' . esc_html($text) . '</option>';
		}
		echo '</select>';
		$this->description($name, $description);
		echo '</label>';
	}

	private function description($name, $description) {
		if ($description) { echo '<small id="onyx-' . esc_attr($name) . '-help">' . esc_html($description) . '</small>'; }
	}

	private function toggle($name, $label, $value, $description = '') {
		echo '<label class="onyx-admin-field"><span>' . esc_html($label) . '</span><span class="onyx-toggle">';
		echo '<input type="checkbox" aria-label="' . esc_attr($label) . '"' . ($description ? ' aria-describedby="onyx-' . esc_attr($name) . '-help"' : '') . ' name="onyx_poll[' . esc_attr($name) . ']" value="1" ' . checked($value, 1, false) . '><span class="onyx-toggle-track" aria-hidden="true"></span></span>';
		$this->description($name, $description);
		echo '</label>';
	}

	public function form($post) {
		if ($post->post_type !== 'onyxpolls') { return; }
		$poll = OnyxPollsStorage::get($post->ID);
		if (!$poll) { return; }
		wp_nonce_field('onyx_poll_save', 'onyx_poll_nonce');
		$snapshot = wp_json_encode(array('version' => $poll['version'], 'answers' => $poll['answers']));
		echo '<input type="hidden" name="onyx_poll_snapshot" value="' . esc_attr(base64_encode($snapshot)) . '">';
		echo '<input type="hidden" name="onyx_poll_signature" value="' . esc_attr(hash_hmac('sha256', $post->ID . ':' . $snapshot, wp_salt('nonce'))) . '">';
		echo '<div class="onyx-admin" id="onyx-poll-editor"><p class="onyx-admin-label">' . esc_html__('Answers', 'acf-onyx-poll') . ' <span class="required">*</span></p>';
		echo '<div class="onyx-answer-table"><table class="widefat onyx-answers"><thead><tr><th class="onyx-row-number"><span class="screen-reader-text">' . esc_html__('Order', 'acf-onyx-poll') . '</span></th><th class="onyx-image-column">' . esc_html__('Image', 'acf-onyx-poll') . '</th><th>' . esc_html__('Answer', 'acf-onyx-poll') . '</th><th class="onyx-votes-column">' . esc_html__('Votes', 'acf-onyx-poll') . '</th><th class="onyx-row-actions">' . esc_html__('Actions', 'acf-onyx-poll') . '</th></tr></thead><tbody>';
		$answers = $poll['answers'];
		while (count($answers) < 2) { $answers[] = array('id' => 0, 'answer' => '', 'image_id' => 0, 'votes' => 0); }
		foreach ($answers as $i => $answer) { $this->row($answer, $i); }
		echo '</tbody></table></div><p class="onyx-add-row"><button type="button" class="button button-primary" data-onyx-add>' . esc_html__('Add Row', 'acf-onyx-poll') . '</button></p>';
		echo '<template id="onyx-answer-template">';
		$this->row(array('id' => 0, 'answer' => '', 'image_id' => 0, 'votes' => 0), '__index__');
		echo '</template><div class="onyx-admin-grid">';
		$this->select('limit_vote', __('Limit voting', 'acf-onyx-poll'), $poll['limit_vote'], self::limits(), __('Controls repeat votes using a browser cookie. Free Vote allows unlimited votes.', 'acf-onyx-poll'));
		$this->select('results', __('Results', 'acf-onyx-poll'), $poll['results'], array(1 => __('Show in percentage', 'acf-onyx-poll'), 2 => __('Show in numbers', 'acf-onyx-poll'), 3 => __('Show both', 'acf-onyx-poll')), __('Display answer totals as percentages, numbers or both.', 'acf-onyx-poll'));
		$end = $poll['end'] ? substr(str_replace(' ', 'T', $poll['end']), 0, 16) : '';
		/* translators: %s: Site timezone name or UTC offset. */
		$end_help = sprintf(__('Voting closes at this time in the site timezone (%s), even if cron is delayed.', 'acf-onyx-poll'), wp_timezone_string());
		echo '<label class="onyx-admin-field"><span>' . esc_html__('End of Poll', 'acf-onyx-poll') . '</span><input type="datetime-local" aria-label="' . esc_attr__('End of Poll', 'acf-onyx-poll') . '" aria-describedby="onyx-end-help" name="onyx_poll[end]" value="' . esc_attr($end) . '" required><small id="onyx-end-help">' . esc_html($end_help) . '</small></label>';
		echo '<label class="onyx-admin-field"><span>' . esc_html__('Total votes', 'acf-onyx-poll') . '</span><input id="onyx-total" type="number" value="' . esc_attr($poll['total']) . '" readonly aria-readonly="true"><small>' . esc_html__('Calculated from the answers.', 'acf-onyx-poll') . '</small></label>';
		$this->toggle('images', __('Enable Images', 'acf-onyx-poll'), $poll['images'], __('Show an image with each answer. Every answer needs an image when enabled.', 'acf-onyx-poll'));
		$this->toggle('modal', __('Poll Modal', 'acf-onyx-poll'), $poll['modal'], __('Allow this poll in the site modal. The latest active poll with this option is used.', 'acf-onyx-poll'));
		$this->toggle('show_results', __('Show Results', 'acf-onyx-poll'), $poll['show_results'], __('Let visitors view results before the deadline. Expired polls always show results.', 'acf-onyx-poll'));
		$this->toggle('expired', __('Expired', 'acf-onyx-poll'), $poll['expired'], __('Close voting manually. To reopen, disable this option and set a future deadline.', 'acf-onyx-poll'));
		echo '</div></div>';
	}

	private function row($answer, $i) {
		$name = 'onyx_poll[answers][' . $i . ']';
		$image_label = $answer['image_id'] ? __('Change image', 'acf-onyx-poll') : __('Select image', 'acf-onyx-poll');
		echo '<tr data-onyx-row><td class="onyx-row-number"><span class="onyx-drag-handle" title="' . esc_attr__('Drag to reorder', 'acf-onyx-poll') . '" aria-hidden="true"><span class="onyx-grip"></span></span><span data-onyx-number>' . esc_html(is_numeric($i) ? $i + 1 : '') . '</span><input type="hidden" name="' . esc_attr($name . '[id]') . '" value="' . esc_attr($answer['id']) . '"></td>';
		echo '<td class="onyx-image-column"><input type="hidden" data-onyx-image-id name="' . esc_attr($name . '[image_id]') . '" value="' . esc_attr($answer['image_id']) . '"><div class="onyx-image-control' . ($answer['image_id'] ? ' has-image' : '') . '"><button type="button" class="onyx-image-picker" data-onyx-image title="' . esc_attr($image_label) . '" aria-label="' . esc_attr($image_label) . '"><span data-onyx-image-preview>';
		if ($answer['image_id']) { echo wp_get_attachment_image($answer['image_id'], 'thumbnail'); }
		echo '</span><span class="onyx-image-placeholder dashicons dashicons-format-image" aria-hidden="true"></span><span class="onyx-image-edit dashicons dashicons-edit" aria-hidden="true"></span></button><button type="button" class="onyx-image-remove" data-onyx-remove-image title="' . esc_attr__('Remove image', 'acf-onyx-poll') . '" aria-label="' . esc_attr__('Remove image', 'acf-onyx-poll') . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div></td>';
		echo '<td><input type="text" aria-label="' . esc_attr__('Answer', 'acf-onyx-poll') . '" name="' . esc_attr($name . '[answer]') . '" value="' . esc_attr($answer['answer']) . '" required></td>';
		echo '<td class="onyx-votes-column"><input type="number" data-onyx-votes aria-label="' . esc_attr__('Votes', 'acf-onyx-poll') . '" name="' . esc_attr($name . '[votes]') . '" value="' . esc_attr($answer['votes']) . '" min="0" step="1" required></td>';
		echo '<td class="onyx-row-actions"><div class="onyx-row-tools">';
		$actions = array('up' => array(__('Move up', 'acf-onyx-poll'), 'arrow-up-alt2'),
			'down' => array(__('Move down', 'acf-onyx-poll'), 'arrow-down-alt2'),
			'remove' => array(__('Remove answer', 'acf-onyx-poll'), 'trash'));
		foreach ($actions as $action => $button) {
			echo '<button type="button" class="button onyx-icon-button onyx-action-' . esc_attr($action) . '" data-onyx-' . esc_attr($action) . ' title="' . esc_attr($button[0]) . '" aria-label="' . esc_attr($button[0]) . '"><span class="dashicons dashicons-' . esc_attr($button[1]) . '" aria-hidden="true"></span></button>';
		}
		echo '</div></td></tr>';
	}

	public function assets() {
		$screen = get_current_screen();
		if (!$screen || $screen->post_type !== 'onyxpolls') { return; }
		wp_enqueue_media();
		wp_enqueue_script('onyx-poll-admin', plugins_url('assets/js/admin.js', ACF_ONYX_POLL_FILE), array('jquery-ui-sortable'), OnyxPolls::get_asset_vars('assets/js/admin.js')->ver, true);
		wp_localize_script('onyx-poll-admin', 'onyxPollAdmin', array('imageTitle' => __('Select image', 'acf-onyx-poll'), 'imageButton' => __('Use image', 'acf-onyx-poll'), 'imageChoose' => __('Select image', 'acf-onyx-poll'), 'imageChange' => __('Change image', 'acf-onyx-poll')));
	}

	public function save($id, $post) {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision($id) || !isset($_POST['onyx_poll_nonce'])) { return; }
		if (!current_user_can('edit_post', $id) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['onyx_poll_nonce'])), 'onyx_poll_save')) { return; }
		$json = base64_decode(isset($_POST['onyx_poll_snapshot']) && is_string($_POST['onyx_poll_snapshot']) ? wp_unslash($_POST['onyx_poll_snapshot']) : '', true);
		$signature = isset($_POST['onyx_poll_signature']) ? sanitize_text_field(wp_unslash($_POST['onyx_poll_signature'])) : '';
		$snapshot = $json ? json_decode($json, true) : null;
		if (!$snapshot || !isset($snapshot['version'], $snapshot['answers']) || !hash_equals(hash_hmac('sha256', $id . ':' . $json, wp_salt('nonce')), $signature)) {
			$this->error = __('Invalid poll parameters', 'acf-onyx-poll'); return;
		}
		$input = isset($_POST['onyx_poll']) ? wp_unslash($_POST['onyx_poll']) : array();
		$validated = self::validate($input);
		if (is_wp_error($validated)) { $this->error = $validated->get_error_message(); return; }
		$result = OnyxPollsStorage::save($id, $validated['poll'], $validated['answers'], $snapshot);
		if (is_wp_error($result)) { $this->error = $result->get_error_message(); }
	}

	public static function validate($input) {
		$invalid = new WP_Error('onyx_poll_invalid', __('Please complete all required poll fields. At least two answers are required.', 'acf-onyx-poll'));
		if (!is_array($input) || !isset($input['answers']) || !is_array($input['answers']) || count($input['answers']) < 2) { return $invalid; }
		$limit = isset($input['limit_vote']) && is_scalar($input['limit_vote']) ? (int) $input['limit_vote'] : 0;
		$results = isset($input['results']) && is_scalar($input['results']) ? (int) $input['results'] : 0;
		$end = isset($input['end']) && is_string($input['end']) ? $input['end'] : '';
		$date = DateTime::createFromFormat('!Y-m-d\TH:i', $end, wp_timezone());
		if (!isset(self::limits()[$limit]) || !in_array($results, array(1, 2, 3), true) || !$date || $date->format('Y-m-d\TH:i') !== $end) { return $invalid; }
		$data = array('limit_vote' => $limit, 'results' => $results, 'end' => $date->format('Y-m-d H:i:s'));
		foreach (array('images', 'modal', 'show_results', 'expired') as $key) { $data[$key] = empty($input[$key]) ? 0 : 1; }
		$answers = array();
		foreach ($input['answers'] as $answer) {
			if (!is_array($answer) || !isset($answer['answer'], $answer['votes']) || !is_string($answer['answer']) || !is_scalar($answer['votes']) || !preg_match('/^\d+$/', (string) $answer['votes'])) { return $invalid; }
			$text = sanitize_text_field($answer['answer']);
			$image = isset($answer['image_id']) && is_scalar($answer['image_id']) ? absint($answer['image_id']) : 0;
			if ($text === '' || ($data['images'] && (!$image || !wp_attachment_is_image($image)))) { return $invalid; }
			$answers[] = array('id' => isset($answer['id']) && is_scalar($answer['id']) ? absint($answer['id']) : 0,
				'answer' => $text, 'image_id' => $image, 'votes' => (int) $answer['votes']);
		}
		return array('poll' => $data, 'answers' => $answers);
	}

	public function redirect($location) {
		return $this->error ? add_query_arg('onyx_poll_error', $this->error, $location) : $location;
	}

	public function notice() {
		$screen = get_current_screen();
		if ($screen && $screen->post_type === 'onyxpolls' && !empty($_GET['onyx_poll_error'])) {
			echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['onyx_poll_error']))) . '</p></div>';
		}
		if (get_option(OnyxPollsStorage::VERSION) !== OnyxPollsStorage::DB_VERSION || !get_option(OnyxPollsStorage::MIGRATION)) {
			echo '<div class="notice notice-warning"><p>' . esc_html__('Poll data migration is pending. It will resume automatically; existing data is preserved.', 'acf-onyx-poll') . '</p></div>';
		}
	}

	public function menu() {
		add_submenu_page('edit.php?post_type=onyxpolls', __('Poll Settings', 'acf-onyx-poll'), __('Settings', 'acf-onyx-poll'), 'edit_posts', 'onyx-poll-settings', array($this, 'settings_form'));
	}

	public function settings_form() {
		if (!current_user_can('edit_posts')) { return; }
		$settings = OnyxPollsStorage::settings();
		echo '<div class="wrap"><h1>' . esc_html__('Poll Settings', 'acf-onyx-poll') . '</h1>';
		if (isset($_GET['updated'])) { echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'acf-onyx-poll') . '</p></div>'; }
		echo '<form class="onyx-admin onyx-settings" action="' . esc_url(admin_url('admin-post.php')) . '" method="post"><input type="hidden" name="action" value="onyx_poll_settings">';
		wp_nonce_field('onyx_poll_settings');
		echo '<div class="onyx-admin-grid">';
		$intervals = array('' => __('Not set', 'acf-onyx-poll'));
		foreach (array(1, 2, 3, 4, 5, 12, 24) as $hours) {
			/* translators: %d: Number of hours between modal appearances. */
			$intervals[$hours] = sprintf(_n('Every %d hour', 'Every %d hours', $hours, 'acf-onyx-poll'), $hours);
		}
		$this->select('modal_time', __('Modal Interval for Voting', 'acf-onyx-poll'), $settings['modal_time'], $intervals, __('How often the poll modal can reappear on the same browser. Not set allows it to reappear without an interval.', 'acf-onyx-poll'));
		$sizes = array_keys(wp_get_registered_image_subsizes());
		$this->select('image_size', __('Select the thumbnail size', 'acf-onyx-poll'), $settings['image_size'], array_combine($sizes, $sizes), __('WordPress image size used for poll answers. A larger file does not change its display size; adjust your CSS if needed.', 'acf-onyx-poll'));
		$this->toggle('css', __('Use own CSS', 'acf-onyx-poll'), $settings['css'], __('Disables the default poll stylesheet so your theme can provide all styles. You can also keep it enabled and override the CSS variables shown in Help.', 'acf-onyx-poll'));
		echo '</div>';
		if (current_user_can('manage_options')) {
			echo '<hr>';
			$this->select('delete_on_uninstall', __('Data on uninstall', 'acf-onyx-poll'), $settings['delete_on_uninstall'], array(0 => __('Keep data (default)', 'acf-onyx-poll'), 1 => __('Permanently delete plugin data', 'acf-onyx-poll')), __('Applies only when the plugin is deleted, not deactivated. Keeping data lets you reinstall without losing polls and votes.', 'acf-onyx-poll'));
			echo '<div id="onyx-uninstall-warning" class="notice notice-warning inline"><p>' . esc_html__('When you delete this plugin, all Onyx polls, answers, votes and settings will be permanently deleted. This cannot be undone. Other posts, pages and media will be preserved. Deactivation does not delete data.', 'acf-onyx-poll') . '</p></div>';
			echo '<p id="onyx-uninstall-ack"><label><input type="checkbox" name="onyx_delete_confirm" value="1"> ' . esc_html__('I understand that deleting the plugin will permanently erase its data.', 'acf-onyx-poll') . '</label></p>';
		}
		submit_button();
		echo '</form>';
		require_once __DIR__ . '/poll-settings-help.php';
		OnyxPollsSettingsHelp::render();
		echo '</div>';
	}

	public function save_settings() {
		if (!current_user_can('edit_posts')) { wp_die(esc_html__('You are not allowed to change these settings.', 'acf-onyx-poll')); }
		check_admin_referer('onyx_poll_settings');
		$input = isset($_POST['onyx_poll']) ? wp_unslash($_POST['onyx_poll']) : array();
		if (!is_array($input)) { wp_die(esc_html__('Invalid poll parameters', 'acf-onyx-poll')); }
		$settings = OnyxPollsStorage::settings();
		$settings['modal_time'] = isset($input['modal_time']) && is_scalar($input['modal_time']) && in_array((int) $input['modal_time'], array(1, 2, 3, 4, 5, 12, 24), true) ? (int) $input['modal_time'] : '';
		$settings['css'] = empty($input['css']) ? 0 : 1;
		$settings['image_size'] = isset($input['image_size']) && is_string($input['image_size']) && isset(wp_get_registered_image_subsizes()[$input['image_size']]) ? $input['image_size'] : 'thumbnail';
		if (isset($input['delete_on_uninstall'])) {
			if (!current_user_can('manage_options')) { wp_die(esc_html__('You are not allowed to change these settings.', 'acf-onyx-poll')); }
			if (!empty($input['delete_on_uninstall']) && empty($_POST['onyx_delete_confirm'])) { wp_die(esc_html__('Confirm that you understand the permanent deletion warning before saving.', 'acf-onyx-poll')); }
			$settings['delete_on_uninstall'] = empty($input['delete_on_uninstall']) ? 0 : 1;
		}
		update_option(OnyxPollsStorage::SETTINGS, $settings, false);
		wp_safe_redirect(admin_url('edit.php?post_type=onyxpolls&page=onyx-poll-settings&updated=1'));
		exit;
	}
}

if (is_admin()) { new OnyxPollsAdmin(); }

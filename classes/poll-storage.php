<?php
/** Poll data belongs to this plugin; WordPress owns the publishing lifecycle. */
if (!defined('ABSPATH')) { exit; }

class OnyxPollsStorage {

	const DB_VERSION = '1';
	const SETTINGS = 'onyx_poll_settings';
	const VERSION = 'onyx_poll_db_version';
	const MIGRATION = 'onyx_poll_migration_complete';

	public static function table($answers = false) {
		global $wpdb;
		return $wpdb->prefix . ($answers ? 'onyx_poll_answers' : 'onyx_polls');
	}

	public static function defaults() {
		return array('limit_vote' => 1, 'results' => 3, 'end' => null, 'images' => 0,
			'modal' => 0, 'show_results' => 1, 'expired' => 0, 'version' => 0);
	}

	public static function settings() {
		return wp_parse_args(get_option(self::SETTINGS, array()), array(
			'modal_time' => '', 'css' => 0, 'image_size' => 'thumbnail', 'delete_on_uninstall' => 0));
	}

	public static function upgrade() {
		global $wpdb;
		if (get_option(self::VERSION) !== self::DB_VERSION) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$collate = $wpdb->get_charset_collate();
			$polls = self::table();
			$answers = self::table(true);
			dbDelta("CREATE TABLE $polls (
				poll_id bigint(20) unsigned NOT NULL,
				limit_vote int(11) unsigned NOT NULL DEFAULT 1,
				results tinyint(3) unsigned NOT NULL DEFAULT 3,
				end datetime DEFAULT NULL,
				images tinyint(1) unsigned NOT NULL DEFAULT 0,
				modal tinyint(1) unsigned NOT NULL DEFAULT 0,
				show_results tinyint(1) unsigned NOT NULL DEFAULT 1,
				expired tinyint(1) unsigned NOT NULL DEFAULT 0,
				version bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (poll_id),
				KEY expiration (expired,end),
				KEY modal (modal)
			) ENGINE=InnoDB $collate;");
			dbDelta("CREATE TABLE $answers (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				poll_id bigint(20) unsigned NOT NULL,
				position int(11) unsigned NOT NULL,
				answer text NOT NULL,
				image_id bigint(20) unsigned NOT NULL DEFAULT 0,
				votes bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY poll_position (poll_id,position)
			) ENGINE=InnoDB $collate;");
			foreach (array($polls, $answers) as $table) {
				if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
					return;
				}
			}
			update_option(self::VERSION, self::DB_VERSION, false);
		}
		// add_option keeps settings changed after migration intact.
		add_option(self::SETTINGS, array(
			'modal_time' => get_option('options_onyx_poll_modal_time', ''),
			'css' => (int) get_option('options_onyx_poll_css', 0),
			'image_size' => get_option('options_onyx_poll_image_size', 'thumbnail'),
			'delete_on_uninstall' => 0), '', false);
		if (!get_option(self::MIGRATION)) {
			$ids = $wpdb->get_col("SELECT p.ID FROM $wpdb->posts p LEFT JOIN " . self::table() .
				" o ON o.poll_id=p.ID WHERE p.post_type='onyxpolls' AND o.poll_id IS NULL ORDER BY p.ID LIMIT 50");
			foreach ($ids as $id) {
				if (is_wp_error(self::migrate_poll($id))) { return; }
			}
			if (count($ids) < 50) { update_option(self::MIGRATION, 1, false); }
		}
	}

	/** Each poll is imported in one transaction, including its answer counters. */
	public static function migrate_poll($id) {
		global $wpdb;
		$id = absint($id);
		if (get_post_type($id) !== 'onyxpolls') { return false; }
		$wpdb->query('START TRANSACTION');
		$data = self::defaults();
		foreach ($data as $key => $default) {
			if ($key !== 'version' && metadata_exists('post', $id, 'onyx_poll_' . $key)) {
				$data[$key] = get_post_meta($id, 'onyx_poll_' . $key, true);
			}
		}
		$data['end'] = $data['end'] ?: null;
		$data['poll_id'] = $id;
		// A competing import/save can win; never replace an existing poll.
		$columns = array_keys($data);
		$placeholders = array_fill(0, count($columns), '%s');
		$values = array_values($data);
		$end_index = array_search('end', $columns, true);
		if ($data['end'] === null) { $placeholders[$end_index] = 'NULL'; unset($values[$end_index]); }
		$insert = $wpdb->query($wpdb->prepare('INSERT IGNORE INTO ' . self::table() . ' (' .
			implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')', array_values($values)));
		if ($insert === false) { return self::rollback(); }
		if ($insert === 0) { $wpdb->query('COMMIT'); return true; }
		$count = max(0, (int) get_post_meta($id, 'onyx_poll_answers', true));
		for ($i = 0; $i < $count; $i++) {
			$prefix = 'onyx_poll_answers_' . $i . '_';
			if ($wpdb->insert(self::table(true), array('poll_id' => $id, 'position' => $i + 1,
				'answer' => (string) get_post_meta($id, $prefix . 'answer', true),
				'image_id' => absint(get_post_meta($id, $prefix . 'image', true)),
				'votes' => max(0, (int) get_post_meta($id, $prefix . 'votes', true)))) === false) {
				return self::rollback();
			}
		}
		$copied = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table(true) . ' WHERE poll_id=%d', $id));
		if ($copied !== $count) { return self::rollback(); }
		$wpdb->query('COMMIT');
		return true;
	}

	public static function get($id) {
		global $wpdb;
		$id = absint($id);
		$poll = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE poll_id=%d', $id), ARRAY_A);
		if (!$poll && get_post_type($id) === 'onyxpolls') {
			if (is_wp_error(self::migrate_poll($id))) { return null; }
			$poll = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE poll_id=%d', $id), ARRAY_A);
		}
		if (!$poll) { return null; }
		foreach (array('poll_id', 'limit_vote', 'results', 'images', 'modal', 'show_results', 'expired', 'version') as $key) {
			$poll[$key] = (int) $poll[$key];
		}
		$poll['answers'] = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table(true) . ' WHERE poll_id=%d ORDER BY position,id', $id), ARRAY_A);
		$poll['total'] = 0;
		foreach ($poll['answers'] as &$answer) {
			foreach (array('id', 'poll_id', 'position', 'image_id', 'votes') as $key) { $answer[$key] = (int) $answer[$key]; }
			$poll['total'] += $answer['votes'];
		}
		unset($answer);
		return $poll;
	}

	public static function expired($poll) {
		return (bool) $poll['expired'] || (!empty($poll['end']) && $poll['end'] <= current_time('mysql'));
	}

	public static function ids($id = 0, $active = false, $modal = false, $limit = 1) {
		global $wpdb;
		$where = "p.post_type='onyxpolls' AND p.post_status='publish'";
		if ($id) { $where .= $wpdb->prepare(' AND p.ID=%d', $id); }
		if ($modal) { $where .= ' AND o.modal=1'; }
		if ($active) { $where .= $wpdb->prepare(' AND o.expired=0 AND (o.end IS NULL OR o.end>%s)', current_time('mysql')); }
		return array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT p.ID FROM $wpdb->posts p JOIN " .
			self::table() . " o ON o.poll_id=p.ID WHERE $where ORDER BY p.post_date DESC,p.ID DESC LIMIT %d", $limit)));
	}

	public static function vote($id, $position) {
		global $wpdb;
		$wpdb->query('START TRANSACTION');
		$poll = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE poll_id=%d FOR UPDATE', $id), ARRAY_A);
		if (!$poll || get_post_status($id) !== 'publish' || self::expired($poll)) {
			return self::rollback(__('Non-existent or expired poll', 'acf-onyx-poll'));
		}
		$updated = $wpdb->query($wpdb->prepare('UPDATE ' . self::table(true) . ' SET votes=votes+1 WHERE poll_id=%d AND position=%d', $id, $position));
		if ($updated !== 1) { return self::rollback(__('Invalid poll parameters', 'acf-onyx-poll')); }
		$wpdb->query('COMMIT');
		return true;
	}

	/** Saving text/order preserves concurrent votes unless a counter was explicitly edited. */
	public static function save($id, $data, $answers, $snapshot) {
		global $wpdb;
		self::get($id);
		$wpdb->query('START TRANSACTION');
		$poll = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE poll_id=%d FOR UPDATE', $id), ARRAY_A);
		$conflict = __('This poll changed while you were editing it. Reload the page before saving again.', 'acf-onyx-poll');
		if (!$poll || (int) $poll['version'] !== (int) $snapshot['version']) { return self::rollback($conflict); }
		$existing = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table(true) . ' WHERE poll_id=%d', $id), OBJECT_K);
		$original = array();
		foreach ($snapshot['answers'] as $answer) { $original[(int) $answer['id']] = $answer; }
		$kept = array();
		foreach ($answers as $i => $answer) {
			$answer_id = $answer['id'];
			$row = array('position' => $i + 1, 'answer' => $answer['answer'], 'image_id' => $answer['image_id']);
			if ($answer_id) {
				if (isset($kept[$answer_id]) || !isset($existing[$answer_id], $original[$answer_id])) { return self::rollback($conflict); }
				if ($answer['votes'] !== (int) $original[$answer_id]['votes']) {
					if ((int) $existing[$answer_id]->votes !== (int) $original[$answer_id]['votes']) { return self::rollback($conflict); }
					$row['votes'] = $answer['votes'];
				}
				if ($wpdb->update(self::table(true), $row, array('id' => $answer_id, 'poll_id' => $id)) === false) { return self::rollback(); }
				$kept[$answer_id] = true;
			} else {
				$row['poll_id'] = $id;
				$row['votes'] = $answer['votes'];
				if ($wpdb->insert(self::table(true), $row) === false) { return self::rollback(); }
			}
		}
		foreach ($existing as $answer_id => $answer) {
			if (!isset($kept[$answer_id])) {
				if (!isset($original[$answer_id]) || (int) $answer->votes !== (int) $original[$answer_id]['votes']) { return self::rollback($conflict); }
				if ($wpdb->delete(self::table(true), array('id' => $answer_id, 'poll_id' => $id)) === false) { return self::rollback(); }
			}
		}
		$data['version'] = (int) $poll['version'] + 1;
		if ($wpdb->update(self::table(), $data, array('poll_id' => $id)) === false) { return self::rollback(); }
		$wpdb->query('COMMIT');
		return true;
	}

	private static function rollback($message = '') {
		global $wpdb;
		$wpdb->query('ROLLBACK');
		return new WP_Error('onyx_poll_storage', $message ?: __('Unable to save poll data. Please try again.', 'acf-onyx-poll'));
	}

	public static function delete_poll($id) {
		global $wpdb;
		if (get_post_type($id) === 'onyxpolls') {
			$wpdb->delete(self::table(true), array('poll_id' => $id));
			$wpdb->delete(self::table(), array('poll_id' => $id));
		}
	}
}

<?php
if (!defined('ABSPATH')) { exit; }
/*

	@ POLL HELPER METHODS

*/

Class OnyxPolls {

	/**
	 * Constructor
	 */
	public function __construct() {
		// empty
	}

	/**
	* Update poll status when expire
	*/
	public static function expire_polls() {
		global $wpdb;
		$table = OnyxPollsStorage::table();
		$ids = $wpdb->get_col($wpdb->prepare("SELECT o.poll_id FROM $table o JOIN $wpdb->posts p ON p.ID=o.poll_id WHERE p.post_type='onyxpolls' AND p.post_status='publish' AND o.expired=0 AND o.end IS NOT NULL AND o.end<=%s", current_time('mysql')));
		if ($wpdb->last_error) { return new WP_Error('onyx_cron_database', __('The poll expiration check could not be completed.', 'acf-onyx-poll'), array('status' => 500)); }
		if (!$ids) { return null; }
		$expired = array();
		foreach ($ids as $id) {
			$updated = $wpdb->query($wpdb->prepare("UPDATE $table SET expired=1 WHERE poll_id=%d AND expired=0 AND end IS NOT NULL AND end<=%s", $id, current_time('mysql')));
			if ($updated === false) {
				return new WP_Error('onyx_cron_database', __('The poll expiration check could not be completed.', 'acf-onyx-poll'), array('status' => 500));
			}
			if ($updated) { $expired[] = (int) $id; }
		}
		return $expired ?: null;
	}

	/**
	 * Verify if has active polls
	 * 
	 * @param bool $noexpired optional
	 * @param bool $onlymodal optional
	 * @return $post->ID
	 */
	public static function has_polls($noexpired = false, $onlymodal = false) {
		$ids = OnyxPollsStorage::ids(0, $noexpired, $onlymodal);
		return $ids ? $ids[0] : false;
	}

	/**
	 * Verify if poll id exist (even expired)
	 * 
	 * @param int $id required
	 */
	public static function has_poll($id = false) {

		if ($id) { OnyxPollsStorage::get($id); }
		$ids = OnyxPollsStorage::ids($id);
		return $ids ? $ids[0] : false;
	}

	/**
	 * Get asset variables for enqueue
	 * @param string $path required
	 */
	public static function get_asset_vars($path = null) {
		if ($path) {
			$a = new stdClass();
			$a->path = $path;
			$a->url  = plugins_url($path, ACF_ONYX_POLL_FILE);
			$a->ver  = ACF_ONYX_POLL_VERSION . '.' . filemtime(ACF_ONYX_POLL_PATH . $path);
			return $a;
		}
		return false;
	}

	/**
	 * Enqueue assets
	 */
	public static function add_assets() {
		// Include scripts on front end
		$js = self::get_asset_vars('assets/js/onyx-poll.min.js');
		wp_enqueue_script('acf-onyx-poll', $js->url, array(), $js->ver, true);

		if (!OnyxPollsStorage::settings()['css']) {
			$css = self::get_asset_vars('assets/css/onyx-poll.min.css');
			wp_enqueue_style('acf-onyx-poll', $css->url, array(), $css->ver);
		}

		wp_localize_script('acf-onyx-poll', 'onyxpoll',
			array(
				'apiurl'    => rest_url(),
				'listurl'   => rest_url('onyx/polls/list'),
				'voteurl'   => rest_url('onyx/polls/vote'),
				'modaltime' => OnyxPollsStorage::settings()['modal_time'],
				'labels' => array(
					'vote'    => __('Vote', 'acf-onyx-poll'),
					'vote_noun' => _x('vote', 'vote count noun', 'acf-onyx-poll'),
					'votes'   => __('votes', 'acf-onyx-poll'),
					'view'    => __('View results', 'acf-onyx-poll'),
					'total'   => __('Total votes', 'acf-onyx-poll'),
					'success' => __('Your vote was submitted successfully.', 'acf-onyx-poll'),
					'error'   => __('Your vote could not be submitted. Try again.', 'acf-onyx-poll'),
					'refresh' => __('Refresh the page before voting.', 'acf-onyx-poll')
				)
			)
		);
	}

}

?>

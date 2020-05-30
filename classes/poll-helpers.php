<?php
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
		$today = date_i18n('Y-m-d H:i:s');
		$query = new WP_Query([
			"post_type"         => "onyxpolls",
			"no_found_rows"     => true,
			"posts_per_page"    => 5,
			"fields"            => 'ids',
			"meta_query"        => array(
				"relation"    => "AND",
				array(
					"key"      => 'onyx_poll_end',
					"value"    => $today,
					"compare"  => "<=",
					"type"     => "DATETIME"
				),
				array(
					"key"      => 'onyx_poll_expired',
					"value"    => 1,
					"compare"  => "!="
				)
			)
		]);

		if ($query->have_posts()) {
			$response = $query->posts;
			foreach ($query->posts as $post) {
				// $post = array("ID" => $post, 'post_status' => 'draft');
				// wp_update_post($post);
				update_field('onyx_poll_expired', 1, $post);
			}
		} else {
			$response = null;
		}
		return $response;
	}

	/**
	 * Verify if has active polls
	 * 
	 * @param bool $noexpired optional
	 * @param bool $onlymodal optional
	 * @return $post->ID
	 */
	public static function has_polls($noexpired = false, $onlymodal = false) {
		$args = [
			'post_type'         => 'onyxpolls',
			'no_found_rows'     => true,
			'suppress_filters'  => true,
			'posts_per_page'    => 1,
			'fields'            => 'ids',
			'meta_query'        => array()
		];

		if ($noexpired) {
			$args['meta_query'][] = array(
				array(
					'key'   => 'onyx_poll_expired',
					'value'   => '1',
					'compare' => '!='
				)
			);
		}

		if ($onlymodal) {
			$args['meta_query'][] = array(
				array(
					'key'   => 'onyx_poll_modal',
					'value' => '1'
				)
			);
		}

		

		$query = new WP_Query($args);
		return ($query->have_posts()) ? $query->posts[0] : false;
	}

	/**
	 * Verify if poll id exist (even expired)
	 * 
	 * @param int $id required
	 */
	public static function has_poll($id = false) {

		$args = array(
			'post_type'         => 'onyxpolls',
			'no_found_rows'     => true,
			'suppress_filters'  => true,
			'posts_per_page'    => 1,
			'fields'            => 'ids'
		);

		if($id) $args['p'] = (int) $id;

		$query = new WP_Query($args);
		return ($query->have_posts()) ? $query->posts[0] : false;
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
			$a->ver  = filemtime(ACF_ONYX_POLL_PATH . $path);
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
		wp_enqueue_script('acf-onyx-poll', $js->url, array(), $js->ver, false, true);

		if (!get_field('onyx_poll_css', 'options')) {
			$css = self::get_asset_vars('assets/css/onyx-poll.min.css');
			wp_enqueue_style('acf-onyx-poll', $css->url, array(), $css->ver);
		}

		wp_localize_script('acf-onyx-poll', 'onyxpoll',
			array(
				'apiurl'    => rest_url(),
				// 'modaltime' => get_field('onyx_poll_modal_time', 'options'),
				'labels' => array(
					'vote'    => __('Vote', 'acf-onyx-poll'),
					'votes'   => __('votes', 'acf-onyx-poll'),
					'view'    => __('Views result', 'acf-onyx-poll'),
					'total'   => __('Total votes', 'acf-onyx-poll'),
					'success' => __('Vote was submitted successfully.', 'acf-onyx-poll'),
					'error'   => __('Poll vote error, try again.', 'acf-onyx-poll')
				)
			)
		);
	}

}

?>

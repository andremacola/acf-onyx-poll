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
	 * Verify if has active polls
	 * 
	 * @param bool $modal optional
	 * @return $post->ID
	 */
	public static function has_polls($modal = false) {
		$args = [
			'post_type'         => 'onyxpolls',
			'no_found_rows'     => true,
			'suppress_filters'  => true,
			'posts_per_page'    => 1,
			'fields'            => 'ids',
			'meta_query'        => array(
				array(
					'key'     => 'onyx_poll_expired',
					'value'   => '1',
					'compare' => '!='
				)
			)
		];

		if (!empty($modal)) {
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

}

?>
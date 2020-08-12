<?php
/*

	@ POLL REST API
	@ Endpoint: /wp/onyx/poll/{route}

*/

class OnyxPollsApi extends WP_REST_Controller {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'onyx';
		$this->field = array(
			'answers'      => 'onyx_poll_answers',
			'modal'        => 'onyx_poll_modal',
			'limit_vote'   => 'onyx_poll_limit_vote',
			'end'          => 'onyx_poll_end',
			'total'        => 'onyx_poll_total',
			'show_results' => 'onyx_poll_show_results',
			'results'      => 'onyx_poll_results',
			'expired'      => 'onyx_poll_expired',
			'has_image'    => 'onyx_poll_images',
		);
		$this->message = array(
			'success'    => __('Vote was submitted successfully', 'acf-onyx-poll'),
			'error'      => __('Poll vote error, try again', 'acf-onyx-poll'),
			'invalid'    => __('Invalid poll parameters', 'acf-onyx-poll'),
			'no_exist'   => __('Non-existent or expired poll', 'acf-onyx-poll'),
			'no_allowed' => __('You have already voted in this poll', 'acf-onyx-poll'),
			'no_polls'   => __('No polls founded with ID ', 'acf-onyx-poll')
		);
	}

	/**
	 * Register API Routes
	 */
	public function register_routes() {
		// list polls
		register_rest_route($this->namespace, '/polls/list', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array($this, 'list'),
			'permission_callback' => function($req) {
				return true;
			}
		));

		// compute vote
		register_rest_route($this->namespace, '/polls/vote', array(
			'methods'  => WP_REST_Server::ALLMETHODS,
			'callback' => array($this, 'vote'),
			'permission_callback' => function($req) {
				return true;
			}
		));

		// cron expiration route (add to your crontab)
		register_rest_route($this->namespace, '/polls/cron', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array($this, 'expire'),
			'permission_callback' => function($req) {
				return true;
			}
		));
	}

	/**
	 * List 10 latest polls
	 * @param int $req['poll_id'] poll ID optional
	 * @param boolean $req['modal'] optional
	 */
	public function list($req) {

		$poll_args = array(
			'post_type'        => 'onyxpolls',
			'posts_per_page'   => 10,
			'suppress_filters' => true,
			'no_found_rows'    => true
		);

		$poll_args['p'] = (!empty($req['id'])) ? $req['id'] : null;

		if (!empty($req['modal'])) {
			$poll_args['posts_per_page'] = 1;
			$poll_args['meta_query'] = array(
				array(
					'key'   => $this->field['modal'],
					'value' => "1"
				)
			);
		}

		$poll_query = new WP_Query($poll_args);
		if ($poll_query->have_posts()) {
			while ($poll_query->have_posts()):
				$poll_query->the_post();

				$poll['id']          = get_the_ID();
				$poll['title']       = get_the_title();
				$poll['limit_vote']  = get_field($this->field['limit_vote']);
				$poll                = array_merge($poll, $this->poll_data($poll['id']));

				// return response based on founded posts count
				if (count($poll_query->posts) == 1) {
					$response = $poll;
				} else {
					$response[] = $poll;
				}

			endwhile;
		} else {
			return new WP_Error('error', $this->message['no_polls'] . $req['id'], array('status' => 200));
		}

		return new WP_REST_Response($response, 200);
	}

	/**
	 * Compute vote
	 * @param int $req['poll'] poll ID required
	 * @param int $req['choice'] poll answer choice required
	 * @todo: [$poll_choice - 1] is just a temp fix. maybe acf/settings/row_index_offset filter?
	 */
	public function vote($req) {
		// params vars
		$poll_id       = $req['poll'];
		$poll_choice   = (int) $req['choice'];
		$poll_answers  = get_field($this->field['answers'], $poll_id);
		$poll_total    = get_field($this->field['total'], $poll_id);
		$poll_expired  = get_field($this->field['expired'], $poll_id);
		$poll_limit    = get_field($this->field['limit_vote'], $poll_id);

		// validate params from req
		if (!is_numeric($poll_id) || !isset($poll_choice) || empty($poll_answers[$poll_choice - 1])) {
			return new WP_Error('error', $this->message['invalid'], array('status' => 400));
		}
		if ($poll_expired || get_post_type($poll_id) != 'onyxpolls') {
			return new WP_Error('error', $this->message['no_exist'], array('status' => 400));
		}
		if (isset($_COOKIE["onyx_poll_limit_$poll_id"]) && $poll_limit != 1) {
			$response = array(
				"code"     => "not_allowed",
				"id"       => $poll_id,
				"message"  => $this->message['no_allowed'],
				"voted"    => false,
				"data"     => ["status" => 200]
			);
		} else {
			// update vote fields
			$add_vote = array(
				"votes" => $poll_answers[$poll_choice - 1]['votes']+1
			);
			$row   = update_row($this->field['answers'], $poll_choice, $add_vote, $poll_id);
			$total = update_field($this->field['total'], $poll_total+1, $poll_id);

			// set cookies
			// limit = 1 (free vote)
			// limit = 2 (per device/no expires)
			$this->setcookie($poll_id, $poll_choice);

			// return reponse
			$response = array(
				"code"     => ($row && $total) ? "success" : 'error',
				"poll"     => $poll_id,
				"choice"   => $poll_choice,
				"message"  => ($row && $total) ? $this->message['success'] : $this->message['error'],
				"voted"    => ($row && $total) ? true : false,
				"data"     => ["status" => 200]
			);
		}

		$response += $this->poll_data($poll_id);
		return new WP_REST_Response($response, 200);
	}

	/**
	* Update poll status when expire
	*/
	public function expire() {
		$response = OnyxPolls::expire_polls();
		$header_code = ($response) ? 200 : 204;
		return new WP_REST_Response($response, $header_code);
	}

	/**
	 * Return poll data after voting
	 * @param int $poll_id required
	 */
	protected function poll_data($poll_id) {
		// has image
		$response['has_image'] = get_field($this->field['has_image'], $poll_id);

		// format result
		$type = get_field($this->field['results'], $poll_id);
		$total = get_field($this->field['total'], $poll_id);

		$response['expired'] = get_field($this->field['expired'], $poll_id);
		$response['expired'] = $response['expired'] ? $response['expired'] : false;

		// @FIX: need better code here
		// All expired polls need to return the results info even show results is false
		$is_expired = $response['expired'];
		$show_results = $is_expired ? $is_expired : get_field($this->field['show_results'], $poll_id);
		if ($show_results) {
			$response['results']['type'] = $type;
			$response['results']['total'] = $total;
		}

		// format answers
		if (have_rows($this->field['answers'], $poll_id)) {
			while (have_rows($this->field['answers'], $poll_id)):
				the_row();
				$image_id = get_sub_field('image');
				$response['answers'][] = array(
					"option"  => get_row_index(),
					"image"   => ($response['has_image']) ? wp_get_attachment_image_url($image_id) : false,
					"answer"  => get_sub_field('answer'),
					"votes"   => (in_array($type, array(2,3)) && $show_results) ? get_sub_field('votes') : false,
					"percent" => ($total >= 1) ? get_sub_field('votes') * 100 / $total : "0"
				);
			endwhile;
		}

		return $response;
	}

	/**
	 * Create cookie for the expiration user time
	 * @param int $poll_id required
	 */
	protected function setcookie($poll_id, $poll_choice) {
		$poll_limit_vote = get_field($this->field['limit_vote'], $poll_id);
		$poll_limit_time = ($poll_limit_vote == 2) ? strtotime('+1 year') : (60 * $poll_limit_vote);
		setcookie("onyx_poll_choice_$poll_id", $poll_choice, time() + strtotime('+1 year'), "/");
		if ($poll_limit_vote != 1) {
			setcookie("onyx_poll_limit_$poll_id", $poll_choice, time() + $poll_limit_time, "/");
		}
	}

}


/**
 * Instantiate Onyx Poll API
 */
$onyx_poll_controller = new OnyxPollsApi();
add_action('rest_api_init', array($onyx_poll_controller, 'register_routes'));

?>

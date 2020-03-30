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
			'poll_answers'				=> 'onyx_poll_answers',
			'poll_modal'				=> 'onyx_poll_modal',
			'poll_limit_vote'			=> 'onyx_poll_limit_vote',
			'poll_end'					=> 'onyx_poll_end',
			'poll_total'				=> 'onyx_poll_total',
			'poll_show_results'		=> 'onyx_poll_show_results',
			'poll_results'				=> 'onyx_poll_results',
			'poll_expired'				=> 'onyx_poll_expired'
		);
		$this->message = array(
			'success'	=> __('Seu voto foi realizado com sucesso.', 'onyx-poll'),
			'error'		=> __('Erro na votação, tente novamente.', 'onyx-poll'),
			'invalid'	=> __('Parâmetros de votação da enquete inválidos', 'onyx-poll'),
			'no_exist'	=> __('Enquete inexistente', 'onyx-poll'),
			'no_allowed'=> __('Você já votou nesta enquete.', 'onyx-poll'),
			'no_polls'	=> __('Nenhuma enquete encontrada', 'onyx-poll')
		);
	}

	/**
	 * Register API Routes
	 */
	public function register_routes() {
		// list polls
		register_rest_route($this->namespace, '/polls/list', array(
			'methods'	=> WP_REST_Server::READABLE,
			'callback'	=> array($this, 'list'),
			'permittion_callback' => function($req) {
				return true;
			}
		));

		// compute vote
		register_rest_route($this->namespace, '/polls/vote', array(
			'methods'	=> WP_REST_Server::ALLMETHODS,
			'callback'	=> array($this, 'vote'),
			'permittion_callback' => function($req) {
				return true;
			}
		));

		// cron expiration route (add to your crontab)
		register_rest_route($this->namespace, '/polls/cron', array(
			'methods'	=> WP_REST_Server::READABLE,
			'callback'	=> array($this, 'expire'),
			'permittion_callback' => function($req) {
				return true;
			}
		));
	}

	/**
	 * List 10 latest polls
	 * @param int $req['poll_id'] poll ID optional
	 */
	public function list($req) {

		$poll_args = array(
			"post_type"			=> "onyxpolls",
			"posts_per_page"	=> 10
		);

		$poll_args['p'] = (!empty($req['id'])) ? $req['id'] : null;

		if (!empty($req['modal'])) {
			$poll_args['meta_query'] = array(
				array(
					'key'		=> $this->field['poll_modal'],
					'value'	=> "1"
				)
			);
		}

		$poll_query = new WP_Query($poll_args);
		if ($poll_query->have_posts()) {
			while ($poll_query->have_posts()):
				$poll_query->the_post();

				$poll['id']				= get_the_ID();
				$poll['title']			= get_the_title();
				$poll['limit_vote']	= get_field($this->field['poll_limit_vote']);
				$poll						= array_merge($poll, $this->poll_data($poll['id']));

				// return response based on founded posts count
				if ($poll_query->found_posts == 1) {
					$response = $poll;
				} else {
					$response[] = $poll;
				}

			endwhile;
		} else {
			return new WP_Error('error', $this->message['no_polls'], array('status' => 200));
		}

		return new WP_REST_Response($response, 200);
	}

	/**
	 * Compute vote
	 * @param int $req['poll'] poll ID required
	 * @param int $req['choice'] poll answer choice required
	 */
	public function vote($req) {
		// params vars
		$poll_id			= $req['poll'];
		$poll_choice	= (int) $req['choice'];
		$poll_answers	= get_field($this->field['poll_answers'], $poll_id);
		$poll_total		= get_field($this->field['poll_total'], $poll_id);

		// validate params from req
		if (!is_numeric($poll_id) || !isset($poll_choice) || empty($poll_answers[$poll_choice])) {
			return new WP_Error('error', $this->message['invalid'], array('status' => 200));
		}
		if (get_post_status($poll_id) != 'publish' || get_post_type($poll_id) != 'onyxpolls') {
			return new WP_Error('error', $this->message['no_exist'], array('status' => 200));
		}
		if ($_COOKIE["onyx_poll_cookie_$poll_id"] == 1) {
			$response = array(
				"code"		=> "not_allowed",
				"poll"		=> $poll_id,
				"message"	=> $this->message['no_allowed'],
				"voted" 		=> false,
				"data"		=> ["status" => 200]
			);
		} else {
			// update vote fields
			$add_vote = array(
				"votes" => $poll_answers[$poll_choice]['votes']+1
			);
			$row		= update_row($this->field['poll_answers'], $poll_choice+1, $add_vote, $poll_id);
			$total	= update_field($this->field['poll_total'], $poll_total+1, $poll_id);

			// set cookies
			// limit = 1 (free vote)
			// limit = 2 (per device/no expires)
			$this->setcookie($poll_id);

			// return reponse
			$response = array(
				"code"		=> ($row && $total) ? "success" : 'error',
				"poll"		=> $poll_id,
				"message"	=> ($row && $total) ? $this->message->success : $this->message->error,
				"voted" 		=> ($row && $total) ? true : false,
				"data"		=> ["status" => 200]
			);
		}

		$response += $this->poll_data($poll_id);
		return new WP_REST_Response($response, 200);
	}

	/**
	* Update poll status when expire
	*/
	public function expire() {
		$today = date_i18n('Y-m-d H:i:s');
		$query = new WP_Query([
			"post_type"				=> "onyxpolls",
			"no_found_rows"		=> true,
			"posts_per_page"		=> 5,
			"fields"					=> 'ids',
			"meta_query"			=> array(
				"relation"			=> "AND",
				array(
					"key"				=> $this->field['poll_end'],
					"value"			=> $today,
					"compare"		=> "<=",
					"type"			=> "DATETIME"
				),
				array(
					"key"				=> $this->field['poll_expired'],
					"value"			=> 1,
					"compare"		=> "!="
				)
			)
		]);

		if ($query->have_posts()) {
			$response = $query->posts;
			foreach ($query->posts as $post) {
				// $post = array("ID" => $post, 'post_status' => 'draft');
				// wp_update_post($post);
				update_field($this->field['poll_expired'], 1, $post);
			}
		} else {
			$response = null;
		}

		$header_code = ($query) ? 200 : 204;
		return new WP_REST_Response($response, $header_code);
	}

	/**
	 * Return poll data after voting
	 * @param int $poll_id required
	 */
	public function poll_data($poll_id) {
		// format result
		$type = get_field($this->field['poll_results'], $poll_id);
		$total = get_field($this->field['poll_total'], $poll_id);

		$response['expired'] = get_field($this->field['poll_expired'], $poll_id);
		$response['expired'] = $response['expired'] ? $response['expired'] : false;

		if ($show_results = get_field($this->field['poll_show_results'], $poll_id)) {
			$response['results']['type'] = $type;
			$response['results']['total'] = $total;
		}

		// format answers
		if (have_rows($this->field['poll_answers'], $poll_id)) {
			while (have_rows($this->field['poll_answers'], $poll_id)):
				the_row();
				$index = get_row_index();
				$response['answers']["$index"] = array(
					"image"		=> get_sub_field('image'),
					"answer"		=> get_sub_field('answer'),
					"votes"		=> (in_array($type, array(2,3)) && $show_results) ? get_sub_field('votes') : false,
					"percent"	=> (in_array($type, array(1,3)) && $show_results) ? get_sub_field('votes') * 100 / $total : false
				);
			endwhile;
		}

		return $response;
	}

	/**
	 * Create cookie for the expiration user time
	 * @param int $poll_id required
	 */
	public function setcookie($poll_id) {
		$poll_limit_vote = get_field($this->field['poll_limit_vote'], $poll_id);
		if ($poll_limit_vote != 1) {
			$poll_cookie_time = ($poll_limit_vote == 2) ? $poll_limit_vote = strtotime('+1 year') : (60 * $poll_limit_vote);
			setcookie("onyx_poll_cookie_$poll_id", '1', time() + $poll_cookie_time, "/");
		}
	}

}


/**
 * Instantiate Onyx Poll API
 */
$onyx_poll_controller = new OnyxPollsApi();
add_action('rest_api_init', array($onyx_poll_controller, 'register_routes'));

?>

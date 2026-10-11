<?php
if (!defined('ABSPATH')) { exit; }

class OnyxPollsApi extends WP_REST_Controller {
	public function __construct() {
		$this->namespace = 'onyx';
		add_action('rest_api_init', array($this, 'register_routes'));
		add_filter('rest_post_dispatch', array($this, 'no_cache'), 10, 3);
	}

	public function register_routes() {
		foreach (array('list' => WP_REST_Server::READABLE, 'vote' => WP_REST_Server::CREATABLE, 'cron' => WP_REST_Server::READABLE) as $route => $methods) {
			register_rest_route($this->namespace, '/polls/' . $route, array('methods' => $methods,
				'callback' => array($this, $route === 'cron' ? 'expire' : $route), 'permission_callback' => '__return_true'));
		}
		register_rest_route($this->namespace, '/polls/editor', array('methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'editor'), 'permission_callback' => function() { return current_user_can('edit_pages') || current_user_can('edit_posts'); }));
	}

	public function no_cache($response, $server, $request) {
		if (strpos($request->get_route(), '/onyx/polls/') === 0) {
			$response->header('Cache-Control', 'private, no-store, max-age=0');
			$response->header('Vary', 'Cookie, Origin');
			if ($response->get_status() === 429) { $response->header('Retry-After', (string) MINUTE_IN_SECONDS); }
		}
		return $response;
	}

	private function valid_id($value, $allow_zero = false) {
		if (!is_int($value) && !is_string($value)) { return false; }
		$value = (string) $value;
		if (strlen($value) > strlen((string) PHP_INT_MAX)) { return false; }
		if (!preg_match($allow_zero ? '/^[0-9]+$/D' : '/^[1-9][0-9]*$/D', $value)) { return false; }
		$value = ltrim($value, '0') ?: '0';
		$maximum = (string) PHP_INT_MAX;
		return strlen($value) < strlen($maximum) || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) <= 0);
	}

	public function list($req) {
		if (isset($req['id']) && !$this->valid_id($req['id'], true)) {
			return new WP_Error('error', __('Invalid poll parameters.', 'acf-onyx-poll'), array('status' => 400));
		}
		$id = isset($req['id']) ? absint($req['id']) : 0;
		if ($id && !OnyxPollsSecurity::public_poll($id)) { return new WP_Error('error', __('This poll does not exist or has expired.', 'acf-onyx-poll'), array('status' => 404)); }
		if ($id) { OnyxPollsStorage::get($id); }
		$ids = OnyxPollsStorage::ids($id, false, !empty($req['modal']), !empty($req['modal']) ? 1 : 10);
		if (!$ids) {
			/* translators: %d: Poll ID. */
			return new WP_Error('error', sprintf(__('No poll found with ID %d.', 'acf-onyx-poll'), $id), array('status' => 200));
		}
		$response = array();
		foreach ($ids as $poll_id) {
			$data = $this->poll_data($poll_id, $req);
			if (!is_wp_error($data)) { $response[] = $data; }
		}
		if (!$response) { return new WP_Error('error', __('This poll does not exist or has expired.', 'acf-onyx-poll'), array('status' => 404)); }
		return new WP_REST_Response(count($response) === 1 ? $response[0] : $response, 200);
	}

	public function vote($req) {
		if ($req->get_method() !== 'POST') { return new WP_Error('error', __('Invalid poll parameters.', 'acf-onyx-poll'), array('status' => 405)); }
		foreach (array('poll', 'choice') as $key) {
			if (!isset($req[$key]) || !$this->valid_id($req[$key])) {
				return new WP_Error('error', __('Invalid poll parameters.', 'acf-onyx-poll'), array('status' => 400));
			}
		}
		$id = absint($req['poll']);
		$choice = absint($req['choice']);
		if (!OnyxPollsSecurity::public_poll($id)) { return new WP_Error('error', __('This poll does not exist or has expired.', 'acf-onyx-poll'), array('status' => 404)); }
		$poll = OnyxPollsStorage::get($id);
		if (!$poll || get_post_status($id) !== 'publish' || OnyxPollsStorage::expired($poll)) {
			return new WP_Error('error', __('This poll does not exist or has expired.', 'acf-onyx-poll'), array('status' => 400));
		}
		if (!isset($poll['answers'][$choice - 1])) {
			return new WP_Error('error', __('Invalid poll parameters.', 'acf-onyx-poll'), array('status' => 400));
		}
		$context = OnyxPollsSecurity::verify($req, $poll);
		if (is_wp_error($context)) { return $context; }
		$rate = OnyxPollsSecurity::rate_limit($context['voter']);
		if (is_wp_error($rate)) { return $rate; }
		$blocked = $poll['limit_vote'] !== 1 && isset($_COOKIE['onyx_poll_limit_' . $id]);
		if (!$blocked) {
			$vote = OnyxPollsStorage::vote($id, $choice, $context);
			if (is_wp_error($vote)) {
				if ($vote->get_error_code() === 'onyx_poll_already_voted') { $blocked = true; }
				else { return new WP_Error('error', $vote->get_error_message(), $vote->get_error_data() ?: array('status' => 400)); }
			}
			if (!$blocked) { $this->cookies($id, $choice, $poll['limit_vote']); }
		}
		$data = $this->poll_data($id, $req);
		if (is_wp_error($data)) { return $data; }
		$response = new WP_REST_Response(array_merge($data, array(
			'code' => $blocked ? 'not_allowed' : 'success', 'id' => $id, 'poll' => $id, 'choice' => $choice,
			'message' => $blocked ? __('You have already voted in this poll.', 'acf-onyx-poll') : __('Your vote was submitted successfully.', 'acf-onyx-poll'),
			'voted' => !$blocked, 'data' => array('status' => 200))), 200);
		return $response;
	}

	public function expire() {
		global $wpdb;
		if ($wpdb->query('START TRANSACTION') === false) { return new WP_Error('error', __('The poll expiration check could not be completed.', 'acf-onyx-poll'), array('status' => 503)); }
		$allowed = OnyxPollsSecurity::reserve('cron-endpoint', 0, MINUTE_IN_SECONDS);
		if ($allowed !== true) {
			$wpdb->query('ROLLBACK');
			return is_wp_error($allowed) ? $allowed : new WP_REST_Response(null, 204);
		}
		if ($wpdb->query('COMMIT') === false) { $wpdb->query('ROLLBACK'); return new WP_Error('error', __('The poll expiration check could not be completed.', 'acf-onyx-poll'), array('status' => 503)); }
		$response = OnyxPollsCron::run();
		if (is_wp_error($response)) { return $response; }
		if (is_array($response)) { $response = array_values(array_filter($response, array('OnyxPollsSecurity', 'public_poll'))); }
		return new WP_REST_Response($response, $response ? 200 : 204);
	}

	public function poll_data($id, $request = null) {
		if (!OnyxPollsSecurity::public_poll($id)) { return new WP_Error('error', __('This poll does not exist or has expired.', 'acf-onyx-poll'), array('status' => 404)); }
		$poll = OnyxPollsStorage::get($id);
		if (!$poll) { return new WP_Error('error', __('This poll does not exist or has expired.', 'acf-onyx-poll'), array('status' => 404)); }
		$expired = OnyxPollsStorage::expired($poll);
		$show = $expired || $poll['show_results'];
		$response = array('id' => (int) $id, 'title' => get_the_title($id), 'limit_vote' => $poll['limit_vote'],
			'has_image' => (bool) $poll['images'], 'expired' => $expired, 'version' => $poll['version'], 'answers' => array());
		// JSONP is executable on another origin and must never carry a session token.
		if ($request && !$expired && $request->get_param('_jsonp') === null && OnyxPollsSecurity::trusted_origin($request)) { $response['vote_token'] = OnyxPollsSecurity::token($poll); }
		if ($show) { $response['results'] = array('type' => $poll['results'], 'total' => $poll['total']); }
		$size = OnyxPollsStorage::settings()['image_size'] ?: 'thumbnail';
		foreach ($poll['answers'] as $answer) {
			$response['answers'][] = array('option' => $answer['position'],
				'image' => $poll['images'] ? wp_get_attachment_image_url($answer['image_id'], $size) : false,
				'answer' => $answer['answer'], 'votes' => $show && in_array($poll['results'], array(2, 3), true) ? $answer['votes'] : false,
				'percent' => $show ? ($poll['total'] ? $answer['votes'] * 100 / $poll['total'] : 0) : false);
		}
		return $response;
	}

	public function editor($req) {
		$search = isset($req['search']) && is_scalar($req['search']) ? sanitize_text_field($req['search']) : '';
		$args = array('post_type' => 'onyxpolls', 'post_status' => 'publish', 'posts_per_page' => 30, 'orderby' => 'ID', 'order' => 'DESC', 's' => $search);
		if (ctype_digit($search)) { unset($args['s']); $args['p'] = absint($search); }
		$posts = get_posts($args);
		$selected = isset($req['selected']) && is_scalar($req['selected']) ? absint($req['selected']) : 0;
		if ($selected && get_post_type($selected) === 'onyxpolls' && get_post_status($selected) === 'publish' && !in_array($selected, wp_list_pluck($posts, 'ID'), true)) { $posts[] = get_post($selected); }
		return array_map(function($post) { return array('id' => $post->ID, 'title' => get_the_title($post)); }, $posts);
	}

	private function cookies($id, $choice, $limit) {
		$options = array('expires' => time() + YEAR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'samesite' => 'Lax');
		setcookie('onyx_poll_choice_' . $id, (string) $choice, $options);
		if ($limit !== 1) {
			$options['expires'] = time() + ($limit === 2 ? YEAR_IN_SECONDS : $limit * MINUTE_IN_SECONDS);
			setcookie('onyx_poll_limit_' . $id, (string) $choice, $options);
		}
	}
}
add_action('init', function() { new OnyxPollsApi(); });

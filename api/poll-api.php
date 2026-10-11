<?php
if (!defined('ABSPATH')) { exit; }

class OnyxPollsApi extends WP_REST_Controller {
	public function __construct() {
		$this->namespace = 'onyx';
		add_action('rest_api_init', array($this, 'register_routes'));
	}

	public function register_routes() {
		foreach (array('list' => WP_REST_Server::READABLE, 'vote' => WP_REST_Server::ALLMETHODS, 'cron' => WP_REST_Server::READABLE) as $route => $methods) {
			register_rest_route($this->namespace, '/polls/' . $route, array('methods' => $methods,
				'callback' => array($this, $route === 'cron' ? 'expire' : $route), 'permission_callback' => '__return_true'));
		}
		register_rest_route($this->namespace, '/polls/editor', array('methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'editor'), 'permission_callback' => function() { return current_user_can('edit_pages') || current_user_can('edit_posts'); }));
	}

	public function list($req) {
		if (isset($req['id']) && (!is_scalar($req['id']) || !preg_match('/^\d+$/', (string) $req['id']))) {
			return new WP_Error('error', __('Invalid poll parameters.', 'acf-onyx-poll'), array('status' => 400));
		}
		$id = isset($req['id']) ? absint($req['id']) : 0;
		if ($id) { OnyxPollsStorage::get($id); }
		$ids = OnyxPollsStorage::ids($id, false, !empty($req['modal']), !empty($req['modal']) ? 1 : 10);
		if (!$ids) {
			/* translators: %d: Poll ID. */
			return new WP_Error('error', sprintf(__('No poll found with ID %d.', 'acf-onyx-poll'), $id), array('status' => 200));
		}
		$response = array();
		foreach ($ids as $poll_id) { $response[] = $this->poll_data($poll_id); }
		return new WP_REST_Response(count($response) === 1 ? $response[0] : $response, 200);
	}

	public function vote($req) {
		foreach (array('poll', 'choice') as $key) {
			if (!isset($req[$key]) || !is_scalar($req[$key]) || !preg_match('/^[1-9]\d*$/', (string) $req[$key])) {
				return new WP_Error('error', __('Invalid poll parameters.', 'acf-onyx-poll'), array('status' => 400));
			}
		}
		$id = absint($req['poll']);
		$choice = absint($req['choice']);
		$poll = OnyxPollsStorage::get($id);
		if (!$poll || get_post_status($id) !== 'publish' || OnyxPollsStorage::expired($poll)) {
			return new WP_Error('error', __('This poll does not exist or has expired.', 'acf-onyx-poll'), array('status' => 400));
		}
		if (!isset($poll['answers'][$choice - 1])) {
			return new WP_Error('error', __('Invalid poll parameters.', 'acf-onyx-poll'), array('status' => 400));
		}
		$blocked = $poll['limit_vote'] !== 1 && isset($_COOKIE['onyx_poll_limit_' . $id]);
		if (!$blocked) {
			$vote = OnyxPollsStorage::vote($id, $choice);
			if (is_wp_error($vote)) { return new WP_Error('error', $vote->get_error_message(), array('status' => 400)); }
			$this->cookies($id, $choice, $poll['limit_vote']);
		}
		return new WP_REST_Response(array_merge($this->poll_data($id), array(
			'code' => $blocked ? 'not_allowed' : 'success', 'id' => $id, 'poll' => $id, 'choice' => $choice,
			'message' => $blocked ? __('You have already voted in this poll.', 'acf-onyx-poll') : __('Your vote was submitted successfully.', 'acf-onyx-poll'),
			'voted' => !$blocked, 'data' => array('status' => 200))), 200);
	}

	public function expire() {
		$response = OnyxPollsCron::run();
		if (is_wp_error($response)) { return $response; }
		return new WP_REST_Response($response, $response ? 200 : 204);
	}

	public function poll_data($id) {
		$poll = OnyxPollsStorage::get($id);
		$expired = OnyxPollsStorage::expired($poll);
		$show = $expired || $poll['show_results'];
		$response = array('id' => (int) $id, 'title' => get_the_title($id), 'limit_vote' => $poll['limit_vote'],
			'has_image' => (bool) $poll['images'], 'expired' => $expired, 'answers' => array());
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

<?php
if (!defined('ABSPATH')) { exit; }

/** Anonymous browser sessions and atomic limits. No IP address or fingerprint is collected. */
class OnyxPollsSecurity {
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'onyx_poll_guards';
	}

	private static function digest($value) { return hash_hmac('sha256', $value, wp_salt('nonce')); }

	private static function cookie_name() { return is_ssl() ? '__Host-onyx_poll_voter' : 'onyx_poll_voter'; }

	private static function voter($create = false) {
		$name = self::cookie_name();
		$cookie = isset($_COOKIE[$name]) && is_string($_COOKIE[$name]) ? wp_unslash($_COOKIE[$name]) : '';
		if (preg_match('/^([a-f0-9]{64})\.([a-f0-9]{64})$/D', $cookie, $parts) && hash_equals(self::digest('browser:' . $parts[1]), $parts[2])) {
			return $parts[1];
		}
		if (!$create) { return ''; }
		try { $id = bin2hex(random_bytes(32)); }
		catch (Exception $error) { return ''; }
		$value = $id . '.' . self::digest('browser:' . $id);
		if (!setcookie($name, $value, array('expires' => time() + YEAR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'))) { return ''; }
		// Subsequent polls rendered during this same request share one identity.
		$_COOKIE[$name] = $value;
		return $id;
	}

	private static function origin($url) {
		$parts = wp_parse_url($url);
		if (!$parts || empty($parts['host']) || empty($parts['scheme']) || isset($parts['user']) || isset($parts['pass'])) { return ''; }
		$scheme = strtolower($parts['scheme']);
		if (!in_array($scheme, array('https', 'http'), true)) { return ''; }
		$port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
		return $scheme . '://' . strtolower($parts['host']) . ':' . $port;
	}

	public static function trusted_origin($request) {
		if ($request->get_header('sec-fetch-site') === 'cross-site') { return false; }
		$origin = $request->get_header('origin');
		$source = $origin !== null ? $origin : $request->get_header('referer');
		// A session-bound token is still required when privacy tools omit these headers.
		if ($source === null) { return $request->get_header('sec-fetch-site') !== 'same-site'; }
		$source = self::origin($source);
		return $source !== '' && in_array($source, array(self::origin(home_url()), self::origin(site_url()), self::origin(rest_url())), true);
	}

	public static function public_poll($post) {
		if (!$post) { return false; }
		$post = get_post($post);
		if (!$post || $post->post_type !== 'onyxpolls' || $post->post_status !== 'publish') { return false; }
		if ($post->post_password !== '' && isset($_COOKIE['wp-postpass_' . COOKIEHASH]) && !is_string($_COOKIE['wp-postpass_' . COOKIEHASH])) { return false; }
		return !post_password_required($post);
	}

	public static function token($poll) {
		$voter = self::voter(true);
		if (!$voter) { return ''; }
		$expires = time() + HOUR_IN_SECONDS;
		$payload = $expires . '.' . $poll['version'];
		return $payload . '.' . self::digest('vote:' . get_current_blog_id() . ':' . $poll['poll_id'] . ':' . $voter . ':' . $payload);
	}

	public static function verify($request, $poll) {
		$voter = self::voter();
		$token = $request->get_header('x-onyx-poll-token');
		if (!self::trusted_origin($request) || !$voter || !is_string($token) || !preg_match('/^([0-9]{1,12})\.([0-9]{1,20})\.([a-f0-9]{64})$/D', $token, $parts)) {
			return new WP_Error('onyx_poll_forbidden', __('Refresh the page before voting.', 'acf-onyx-poll'), array('status' => 403));
		}
		$payload = $parts[1] . '.' . $parts[2];
		$expected = self::digest('vote:' . get_current_blog_id() . ':' . $poll['poll_id'] . ':' . $voter . ':' . $payload);
		if ((int) $parts[1] < time() || (int) $parts[1] > time() + HOUR_IN_SECONDS || $parts[2] !== (string) $poll['version'] || !hash_equals($expected, $parts[3])) {
			return new WP_Error('onyx_poll_forbidden', __('Refresh the page before voting.', 'acf-onyx-poll'), array('status' => 403));
		}
		return array('voter' => $voter, 'version' => (int) $parts[2]);
	}

	/** Call within the same transaction as the protected operation, never before it. */
	public static function reserve($key, $poll_id, $seconds, $maximum = 1) {
		global $wpdb;
		$table = self::table();
		$key = self::digest('guard:' . $key);
		$now = time();
		// Unlike INSERT IGNORE, this takes an exclusive lock on existing keys too.
		$insert = $wpdb->query($wpdb->prepare("INSERT INTO $table (guard_key,poll_id,expires,hits) VALUES (%s,%d,%d,0) ON DUPLICATE KEY UPDATE guard_key=VALUES(guard_key)", $key, $poll_id, $now + $seconds));
		if ($insert === false) { return new WP_Error('onyx_poll_guard_storage', __('Unable to save poll data. Please try again.', 'acf-onyx-poll'), array('status' => 503)); }
		$guard = $wpdb->get_row($wpdb->prepare("SELECT expires,hits FROM $table WHERE guard_key=%s FOR UPDATE", $key), ARRAY_A);
		if (!$guard) { return new WP_Error('onyx_poll_guard_storage', __('Unable to save poll data. Please try again.', 'acf-onyx-poll'), array('status' => 503)); }
		$expired = (int) $guard['expires'] <= $now;
		$hits = $expired ? 0 : (int) $guard['hits'];
		if ($hits >= $maximum) { return false; }
		return $wpdb->update($table, array('hits' => $hits + 1, 'expires' => $expired ? $now + $seconds : (int) $guard['expires']), array('guard_key' => $key)) === false
			? new WP_Error('onyx_poll_guard_storage', __('Unable to save poll data. Please try again.', 'acf-onyx-poll'), array('status' => 503)) : true;
	}

	public static function rate_limit($voter) {
		global $wpdb;
		if ($wpdb->query('START TRANSACTION') === false) { return new WP_Error('onyx_poll_guard_storage', __('Unable to save poll data. Please try again.', 'acf-onyx-poll'), array('status' => 503)); }
		$rate = self::reserve('rate:' . $voter, 0, MINUTE_IN_SECONDS, 10);
		if ($rate !== true) {
			$wpdb->query('ROLLBACK');
			return is_wp_error($rate) ? $rate : new WP_Error('onyx_poll_rate_limit', __('Too many voting requests. Try again shortly.', 'acf-onyx-poll'), array('status' => 429));
		}
		if ($wpdb->query('COMMIT') === false) {
			$wpdb->query('ROLLBACK');
			return new WP_Error('onyx_poll_guard_storage', __('Unable to save poll data. Please try again.', 'acf-onyx-poll'), array('status' => 503));
		}
		return true;
	}

	public static function reserve_vote($id, $limit, $voter) {
		if ($limit === 1) { return true; }
		$interval = $limit === 2 ? YEAR_IN_SECONDS : $limit * MINUTE_IN_SECONDS;
		$limit = self::reserve('vote:' . $id . ':' . $voter, $id, $interval);
		if (is_wp_error($limit)) { return $limit; }
		return $limit ? true : new WP_Error('onyx_poll_already_voted', __('You have already voted in this poll.', 'acf-onyx-poll'), array('status' => 200));
	}

	public static function cleanup() {
		global $wpdb;
		// Bound the housekeeping query even after a long period without WP-Cron.
		$wpdb->query($wpdb->prepare('DELETE FROM ' . self::table() . ' WHERE expires<=%d ORDER BY expires LIMIT 1000', time()));
	}
}

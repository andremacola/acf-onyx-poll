<?php
/*
Plugin Name: Onyx Poll
Version: 2.0.0
Description: Create independent polls with native WordPress administration.
Author: André Mácola Machado
Author URI: https://github.com/andremacola
Plugin URI: https://github.com/andremacola/acf-onyx-poll
Requires PHP: 7.4
Requires at least: 6.3
Text Domain: acf-onyx-poll
Domain Path: /languages/
*/

// Exit if accessed directly
if (!defined( 'ABSPATH')) {
	exit;
}

if(!class_exists('OnyxPollsInit')):

Class OnyxPollsInit {

	/**
	 * __construct
	 *
	 * A dummy constructor to ensure Acf Onyx Poll is only setup once.
	 * Set some constants
	 *
	 * @param	void
	 * @return	void
	 */
	function __construct() {
		define('ACF_ONYX_POLL_VERSION', '2.0.0');
		define('ACF_ONYX_POLL_FILE', __FILE__);
		define('ACF_ONYX_POLL_PATH', plugin_dir_path(__FILE__));
	}

	/**
	 * Add elements on footer in some conditionals
	 */
	public function add_footer_elements() {
		$poll = OnyxPolls::has_polls(true, true);
		if ($poll && !$this->is_amp()) {
			echo "<div id='onyx-poll-modal' class='onyx-poll onyx-poll-modal' data-poll='". esc_attr($poll) ."'></div>";
		}
	}

	/**
	 * Some admin styles for better view
	 */
	public function admin_styles() {
		$screen = get_current_screen();
		if ($screen && ('onyxpolls' === $screen->post_type || $screen->is_block_editor())) {
			$css = OnyxPolls::get_asset_vars('assets/css/admin.css');
			wp_enqueue_style('acf-onyx-poll-admin', $css->url, array(), $css->ver);
		}
	}

	/**
	 * Extract shortcode
	 * Just a simple shortcode method
	 */
	public function shortcode($atts) {
		$atts = shortcode_atts(array(
			'id'    => '',
			'class' => 'left',
			'style' => ''
		), $atts, 'onyx-poll');

		// Sanitize attributes to prevent XSS
		$id    = intval($atts['id']);
		$class = esc_attr($atts['class']);
		$style = esc_attr($atts['style']);

		$poll = OnyxPolls::has_poll($id);

		if ($poll) {
			$html = sprintf(
				'<div id="onyx-poll-%1$s" class="onyx-poll onyx-poll-widget active show %2$s" style="%3$s" data-poll="%1$s"></div>',
				esc_attr($poll),
				$class,
				$style
			);
		} else {
			$html = "<div id='onyx-poll-null' class='onyx-poll onyx-poll-widget show onyx-poll-invalid'>". esc_html__('Invalid poll ID', 'acf-onyx-poll') ."</div>";
		}

		return $html;
	}

	/**
	 * initialize
	 *
	 * Sets up the Onyx Poll plugin.
	 *
	 * @param	void
	 * @return	void
	 */
	 public function initialize() {
		require_once __DIR__ . '/classes/poll-storage.php';
		require_once __DIR__ . '/classes/poll-helpers.php';
		require_once __DIR__ . '/classes/poll-cron.php';
		require_once __DIR__ . '/admin/poll-type.php';
		require_once __DIR__ . '/admin/poll-admin.php';
		add_action('init', function() {
			load_plugin_textdomain('acf-onyx-poll', false, dirname(plugin_basename(__FILE__)) . '/languages');
			OnyxPollsStorage::upgrade();
			OnyxPollsCron::ensure_scheduled();
		}, 0);
		add_action('before_delete_post', array('OnyxPollsStorage', 'delete_poll'));

		// Load Gutenberg Block
		require_once(__DIR__ . '/admin/poll-block.php');

		// Load widget
		require_once(__DIR__ . '/admin/poll-widget.php');


		// Create REST API for Onyx Poll
		require_once(__DIR__ . '/api/poll-api.php');

		// Add footer html elements
		add_action('wp_footer', array($this, 'add_footer_elements'), 1);

		// Enqueue scripts and styles
		add_action('admin_enqueue_scripts', array($this, 'admin_styles'));
		add_action('wp_footer', function() {
			if (!is_admin() && !$this->is_amp()) {
				OnyxPolls::add_assets();
			}
		});
		

		// Add onyx poll shortcode
		add_shortcode("onyx-poll", array($this, 'shortcode'));

		// Define cron event for expired polls
		add_action(OnyxPollsCron::HOOK, array('OnyxPollsCron', 'scheduled_run'));
		add_filter('site_status_tests', array('OnyxPollsCron', 'site_health_tests'));
		register_activation_hook(__FILE__, function() {
			OnyxPollsStorage::upgrade();
			OnyxPollsCron::ensure_scheduled();
		});
		register_deactivation_hook(__FILE__, function() {
			wp_clear_scheduled_hook('onyx-poll-cron');
		});

	}

	public function is_amp() {
		return function_exists('is_amp_endpoint') && is_amp_endpoint();
	}

}

/**
 * Instantiate Onyx Poll
 */
$onyx_poll = new OnyxPollsInit();
$onyx_poll->initialize();

endif; // class_exists check

?>

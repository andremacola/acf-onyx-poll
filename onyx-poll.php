<?php
/*
Plugin Name: ACF Onyx Poll
Version: 1.1.1
Description: Create polls with ACF PRO
Author: André Mácola Machado
Author URI: https://github.com/andremacola
Plugin URI: https://github.com/andremacola/acf-onyx-poll
Requires PHP: 7.0
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
		define('ACF_ONYX_POLL_VERSION', '1.1.1');
		define('ACF_ONYX_POLL_FILE', __FILE__);
		define('ACF_ONYX_POLL_PATH', plugin_dir_path(__FILE__));
	}

	/**
	 * Add elements on footer in some conditionals
	 */
	public function add_footer_elements() {
		$poll = OnyxPolls::has_polls(true, true);
		if ($poll && !$this->is_amp()) {
			echo "<div id='onyx-poll-modal' class='onyx-poll onyx-poll-modal' data-poll='$poll'></div>";
		}
	}

	/**
	 * Some admin styles for better view
	 */
	public function admin_styles() {
		global $post_type;
		if ('onyxpolls' == $post_type || get_current_screen()->is_block_editor) {
			$css = OnyxPolls::get_asset_vars('assets/css/admin.min.css');
			wp_enqueue_style('acf-onyx-poll-admin', $css->url, array(), $css->ver);
		}
	}

	/**
	 * Extract shortcode
	 * Just a simple shortcode method
	 */
	public function shortcode($atts) {
		extract(shortcode_atts(array(
			'id' => '',
			'class' => 'left',
			'style' => ''
		), $atts));

		$poll = OnyxPolls::has_poll($id);

		if ($poll) {
			$html = "<div id='onyx-poll-$poll' class='onyx-poll onyx-poll-widget active show $class' style='$style' data-poll='$poll'></div>";
		} else {
			$html = "<div id='onyx-poll-null' class='onyx-poll onyx-poll-widget show onyx-poll-invalid'>". __('Invalid poll ID', 'acf-onyx-poll') ."</div>";
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
		// Change ACF Local JSON save location to /acf folder inside this plugin
		add_filter('acf/settings/save_json', function() {
			return __DIR__ . '/acf';
		});

		// Include the /acf folder in the places to look for ACF Local JSON files
		// add_filter('acf/settings/load_json', function($paths) {
		// 	$paths[] = __DIR__ . '/acf';
		// 	return $paths;
		// });

		// Load text domain language
		load_plugin_textdomain(
			'acf-onyx-poll',
			false,
			dirname(plugin_basename(__FILE__)) . '/languages'
		);

		// Verify if Advanced Custom Fields PRO is activated
		add_action('admin_init', function() {
			if (is_admin() && current_user_can('activate_plugins') && !is_plugin_active('advanced-custom-fields-pro/acf.php')) {
				add_action('admin_notices', function() {
					$notice = __('Sorry, but ACF Onyx Poll requires that ACF PRO is installed and active.', 'acf-onyx-poll');
					echo "<div class='error'><p>$notice</p></div>";
				});
				deactivate_plugins(plugin_basename(__FILE__));
				if (isset($_GET['activate'])) {
					unset($_GET['activate']);
				}
		    }
		});

		// Load ACF fields
		add_action('acf/init', function() {
			require(__DIR__ . '/acf/fields.php');
		});

		if(is_admin()) {
			// Create Poll Post Type
			require_once(__DIR__ . '/admin/poll-type.php');
		}

		// Load Gutenberg Block
		require_once(__DIR__ . '/admin/poll-block.php');

		// Load widget
		require_once(__DIR__ . '/admin/poll-widget.php');

		// Load Helper Methods
		require_once(__DIR__ . '/classes/poll-helpers.php');

		// Create REST API for Onyx Poll
		require_once(__DIR__ . '/api/poll-api.php');

		// Add footer html elements
		add_action('wp_footer', array($this, 'add_footer_elements'), 1);

		// Enqueue scripts and styles
		add_action('admin_head', array($this, 'admin_styles'));
		add_action('wp_footer', function() {
			if (!is_admin() && !$this->is_amp()) {
				OnyxPolls::add_assets();
			}
		});
		

		// Add onyx poll shortcode
		add_shortcode("onyx-poll", array($this, 'shortcode'));

		// Define cron event for expired polls
		add_action('onyx-poll-cron',  array($this, 'cron_job'));
		register_activation_hook(__FILE__, function() {
			wp_schedule_event(time(), 'hourly', 'onyx-poll-cron');
		});
		register_deactivation_hook(__FILE__, function() {
			wp_clear_scheduled_hook('onyx-poll-cron');
		});

	}

	public function cron_job() {
		OnyxPolls::expire_polls();
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

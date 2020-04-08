<?php
/*
Plugin Name: Onyx Poll
Version: 1.0
Description: Create polls with ACF PRO
Author: André Machado
Author URI: https://macola.com.br
Plugin URI: https://macola.com.br
Requires PHP: 5.4
Text Domain: onyx-poll
Domain Path: /languages/
*/

// Exit if accessed directly
if (!defined( 'ABSPATH')) {
	exit;
}

if(!class_exists('OnyxPoll')):

Class OnyxPoll {

	var $version = "1.0";

	/**
	 * __construct
	 *
	 * A dummy constructor to ensure Onyx Poll is only setup once.
	 *
	 * @date	30/03/20
	 * @since	1.0
	 *
	 * @param	void
	 * @return	void
	 */
	function __construct() {
		// Do nothing.
	}

	/**
	 * initialize
	 *
	 * Sets up the Onyx Poll plugin.
	 *
	 * @date	30/03/20
	 * @since	1.0
	 *
	 * @param	void
	 * @return	void
	 */
	function initialize() {
		// Change ACF Local JSON save location to /acf folder inside this plugin
		add_filter('acf/settings/save_json', function() {
			return __DIR__ . '/acf';
		});

		// Include the /acf folder in the places to look for ACF Local JSON files
		add_filter('acf/settings/load_json', function($paths) {
			$paths[] = __DIR__ . '/acf';
			return $paths;
		});

		// Verify if Advanced Custom Fields PRO is activated
		add_action('admin_init', function() {
			if (is_admin() && current_user_can('activate_plugins') && !is_plugin_active('advanced-custom-fields-pro/acf.php')) {
				add_action('admin_notices', function() {
					$notice = __('Desculpe, mas o Onyx Poll requer o que ACF PRO esteja instalado e ativo.');
					echo "<div class='error'><p>$notice</p></div>";
				});
				deactivate_plugins(plugin_basename( __FILE__ ));
				if (isset($_GET['activate'])) {
					unset($_GET['activate']);
				}
		    }
		});

		if(is_admin()) {
			// Create Poll Post Type
			require_once(__DIR__ . '/admin/poll-type.php');
		}

		// Create REST API for Onyx Poll
		require_once(__DIR__ . '/api/poll-api.php');

		// Enqueue scripts and styles
		add_action('wp_enqueue_scripts', array($this, 'add_assets'));
	}

	/**
	 * Enqueue assets
	 */
	function add_assets() {
		// Include scripts on front end
		if (!is_admin()) {
			$js  = $this->get_asset_vars("assets/js/onyx-poll.min.js");
			$css = $this->get_asset_vars("assets/js/onyx-poll.min.js");

			wp_enqueue_script('acf-onyx-poll', $js->url, array(), $js->ver, false, true);
			wp_enqueue_style('acf-onyx-poll', $css->url, array(), $css->ver);
		}
	}

	/**
	 * Get asset variables for enqueue
	 * @param string $path required
	 */
	function get_asset_vars($path = null) {
		if ($path) {
			$a = new stdClass();
			$a->path = $path;
			$a->url  = plugins_url($path, __FILE__);
			$a->ver  = filemtime(plugin_dir_path(__FILE__) . $path);
			return $a;
		}
		return false;
	}

}

/**
 * Instantiate Onyx Poll
 */
$onyx_poll = new OnyxPoll();
$onyx_poll->initialize();

endif; // class_exists check

?>

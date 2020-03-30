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

		// Create REST API for
		require_once(__DIR__ . '/api/poll-api.php');

	}

}

/**
 * Instantiate Onyx Poll
 */
$onyx_poll = new OnyxPoll();
$onyx_poll->initialize();

endif; // class_exists check

?>

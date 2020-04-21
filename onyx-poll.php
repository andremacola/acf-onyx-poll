<?php
/*
Plugin Name: ACF Onyx Poll
Version: 1.0
Description: Create polls with ACF PRO
Author: André Machado
Author URI: https://macola.com.br
Plugin URI: https://macola.com.br
Requires PHP: 7.2
Text Domain: acf-onyx-poll
Domain Path: /languages/
*/

// Exit if accessed directly
if (!defined( 'ABSPATH')) {
	exit;
}

if(!class_exists('OnyxPoll')):

Class OnyxPollsInit {

	var $version = "1.0";

	/**
	 * __construct
	 *
	 * A dummy constructor to ensure Acf Onyx Poll is only setup once.
	 *
	 * @param	void
	 * @return	void
	 */
	function __construct() {
		// Do nothing.
	}

	/**
	 * Add elements on footer in some conditionals
	 */
	public function add_footer_elements() {
		if ($poll = OnyxPolls::has_polls(true)) {
			echo "<div id='onyx-poll-modal' class='onyx-poll onyx-poll-modal active' data-poll='$poll'></div>";
			echo "<script>var onyxPollModal = true;</script>";
		}
	}

	/**
	 * Enqueue assets
	 */
	public function add_assets() {
		// Include scripts on front end
		if (!is_admin() && OnyxPolls::has_polls()) {
			$js = $this->get_asset_vars('assets/js/onyx-poll.min.js');
			// $js = $this->get_asset_vars('assets/js/app.js');
			wp_enqueue_script('acf-onyx-poll', $js->url, array(), $js->ver, false, true);

			if (get_field('onyx_poll_css', 'options')) {
				$css = $this->get_asset_vars('assets/css/onyx-poll.min.css');
				wp_enqueue_style('acf-onyx-poll', $css->url, array(), $css->ver);
			}

			wp_localize_script('acf-onyx-poll', 'onyxpoll',
				array(
					'apiurl'    => rest_url(),
					'modaltime' => get_field('onyx_poll_modal_time', 'options'),
					'labels' => array(
						'vote'    => __('Votar na enquete', 'acf-onyx-poll'),
						'votes'   => __('votos', 'acf-onyx-poll'),
						'view'    => __('Ver resultado', 'acf-onyx-poll'),
						'total'   => __('Total de votos', 'acf-onyx-poll'),
						'success' => __('Votação realizada com sucesso.', 'acf-onyx-poll'),
						'error'   => __('Erro na votação, tente novamante.', 'acf-onyx-poll')
					)
				)
			);
		}
	}

	/**
	 * Get asset variables for enqueue
	 * @param string $path required
	 */
	public function get_asset_vars($path = null) {
		if ($path) {
			$a = new stdClass();
			$a->path = $path;
			$a->url  = plugins_url($path, __FILE__);
			$a->ver  = filemtime(plugin_dir_path(__FILE__) . $path);
			return $a;
		}
		return false;
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
		return "<div id='onyx-poll-$id' class='onyx-poll onyx-poll-widget active show $class' style='$style' data-poll='$id'></div>";
	}

	/**
	 * initialize
	 *
	 * Sets up the Onyx Poll plugin.
	 *
	 * @param	void
	 * @return	void
	 */
	function initialize() {
		// Change ACF Local JSON save location to /acf folder inside this plugin
		// add_filter('acf/settings/save_json', function() {
		// 	return __DIR__ . '/acf';
		// });

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

		// Load Helper Methods
		require_once(__DIR__ . '/classes/poll-helpers.php');

		// Create REST API for Onyx Poll
		require_once(__DIR__ . '/api/poll-api.php');

		// Enqueue scripts and styles
		if (!(function_exists('is_amp_endpoint') && is_amp_endpoint())) {
			add_action('wp_enqueue_scripts', array($this, 'add_assets'));
		}

		// Add footer html elements
		add_action('wp_footer', array($this, 'add_footer_elements'), 1);

		// Add onyx poll shortcode
		add_shortcode("onyx-poll", array($this, 'shortcode'));
	}

}

/**
 * Instantiate Onyx Poll
 */
$onyx_poll = new OnyxPollsInit();
$onyx_poll->initialize();

endif; // class_exists check

?>

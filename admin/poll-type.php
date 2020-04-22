<?php
/*

	@ POLL ADMIN POST TYPE/CONFIGURATION

*/

class OnyxPollsCpt {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name    = __('Poll', 'acf-onyx-poll');
		$this->namep   = __('Polls', 'acf-onyx-poll');
		$this->cap     = 'onyxpoll';
		$this->caps    = 'onyxpolls';
		$this->slug    = 'onyxpolls';
		$this->icon    = 'dashicons-chart-bar';
		$this->name_l  = strtolower($this->name);
		$this->namep_l = strtolower($this->namep);
		add_action('init', array($this, 'register_cpt'));
		add_action('init', array($this, 'register_config_admin'));
	}

	/**
	 * Register Post Type
	 */
	public function register_cpt() {
		$labels = array(
			'name'               => $this->namep,
			'singular_name'      => $this->name,
			'all_items'          => __('All polls', 'acf-onyx-poll'),
			'add_new'            => __('Add new', 'acf-onyx-poll'),
			'add_new_item'       => __('Add new poll', 'acf-onyx-poll'),
			'edit_item'          => __('Edit poll', 'acf-onyx-poll'),
			'new_item'           => __('Add poll', 'acf-onyx-poll'),
			'view_item'          => __('View poll', 'acf-onyx-poll'),
			'search_items'       => __('Search poll', 'acf-onyx-poll'),
			'not_found'          => __('No polls found', 'acf-onyx-poll'),
			'not_found_in_trash' => __('No poll found in trash', 'acf-onyx-poll'),
			'parent_item_colon'  => __('Poll father', 'acf-onyx-poll'),
			'menu_name'          => $this->namep
		);

		$args = array(
			'labels'              => $labels,
			'hierarchical'        => false,
			'description'         => __('Add new polls', 'acf-onyx-poll'),
			'supports'            => array('title'),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 20,
			'menu_icon'           => $this->icon,
			'show_in_nav_menus'   => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'query_var'           => true,
			'can_export'          => true,
			'rewrite'             => false,
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
			'show_in_rest'        => false
		);

		register_post_type($this->slug, $args);
	}

	/**
	 * Register Post Type
	 */
	public function register_config_admin() {
		if(function_exists('acf_add_options_page')) {
			$options = acf_add_options_page(array(
				'page_title'  => __('Poll Settings', 'acf-onyx-poll'),
				'menu_title'  => __('Settings', 'acf-onyx-poll'),
				'menu_slug'   => 'onyx-poll-settings',
				'capability'  => 'edit_posts',
				'parent_slug' => 'edit.php?post_type=' . $this->slug,
				'redirect'    => false
			));
		}
	}

}


/**
 * Instantiate Onyx Poll Admin
 */
$onyx_poll_admin = new OnyxPollsCpt();

?>

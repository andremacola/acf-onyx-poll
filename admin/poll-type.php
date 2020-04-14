<?php
/*

	@ POLL ADMIN POST TYPE/CONFIGURATION

*/

class OnyxPollsCpt {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name    = __('Enquete', 'acf-onyx-poll');
		$this->namep   = __('Enquetes', 'acf-onyx-poll');
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
			'all_items'          => __('Todas as enquetes', 'acf-onyx-poll'),
			'add_new'            => __('Adicionar nova', 'acf-onyx-poll'),
			'add_new_item'       => __('Adicionar nova enquete', 'acf-onyx-poll'),
			'edit_item'          => __('Editar enquete', 'acf-onyx-poll'),
			'new_item'           => __('Adicionar enquete', 'acf-onyx-poll'),
			'view_item'          => __('Ver enquete', 'acf-onyx-poll'),
			'search_items'       => __('Buscar enquete', 'acf-onyx-poll'),
			'not_found'          => __('Nenhuma enquete encontrada', 'acf-onyx-poll'),
			'not_found_in_trash' => __('Nenhuma enquete encontrada na lixeira', 'acf-onyx-poll'),
			'parent_item_colon'  => __('Enquete pai', 'acf-onyx-poll'),
			'menu_name'          => $this->namep
		);

		$args = array(
			'labels'              => $labels,
			'hierarchical'        => false,
			'description'         => __('Inclusão de novas enquetes', 'acf-onyx-poll'),
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
				'page_title'  => __('Configurações das Enquetes', 'acf-onyx-poll'),
				'menu_title'  => __('Configurações', 'acf-onyx-poll'),
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

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
		add_filter("manage_{$this->slug}_posts_columns", array($this, 'manage_columns'));
		add_action("manage_{$this->slug}_posts_custom_column", array($this, 'custom_columns'), 10, 2);
		add_filter("manage_edit-{$this->slug}_sortable_columns", array($this, 'sortable_columns'));
		add_action('pre_get_posts', array($this, 'orderby_columns'));
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
	 * Create settings page
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

	/**
	 * Customize admin columns
	 */
	public function manage_columns($columns) {
		$date = $columns['date'];
		unset($columns['author']);
		unset($columns['date']);
		$columns['id'] = __('ID', 'acf-onyx-poll');
		$columns['votes'] = __('Votes', 'acf-onyx-poll');
		$columns['modal'] = __('Modal', 'acf-onyx-poll');
		$columns['status'] = __('Status', 'acf-onyx-poll');
		$columns['end'] = __('End date', 'acf-onyx-poll');
		$columns['date'] = $date;
		return $columns;
	}

	/**
	 * Edit columns output
	 */
	public function custom_columns($column, $post_id) {
		switch($column) {
			case 'id':
				echo "<span>[onyx-poll id='$post_id']</span>";
				break;
			case 'votes':
				echo get_field('onyx_poll_total', $post_id);
				break;
			case 'modal':
				$modal  = get_field('onyx_poll_modal', $post_id);
				$status = ($modal) ? "<span class='green' title='Yes'>✔</span>" : "<span class='red' title='No'>✘</span>";
				echo $status;
				break;
			case 'status':
				$expired = get_field('onyx_poll_expired', $post_id);
				$status  = (!$expired) ? "<span class='green' title='Published'>✔</span>" : "<span class='red' title='expired'>✘</span>";
				echo $status;
				break;
			case 'end':
				$date = get_field('onyx_poll_end', $post_id);
				$date = DateTime::createFromFormat('Y-m-d H:i:s', $date);
				$date = $date->format('d/m/Y');
				echo "$date";
				break;
		}
	}

	/**
	 * Sort custom columns
	 */
	public function sortable_columns($columns) {
		$columns['id']    = 'id';
		$columns['votes'] = 'votes';
		$columns['modal'] = 'modal';
		$columns['status'] = 'status';
		return $columns;
	}

	/**
	 * Sort query columns
	 */
	public function orderby_columns($query) {
		if(!is_admin())
			return;

		$orderby = $query->get('orderby');

		if ('id' == $orderby) {
			$query->set('orderby', 'ID');
  		} else if ('votes' == $orderby) {
			$query->set('meta_key', 'onyx_poll_total');
			$query->set('orderby', 'meta_value_num');
  		} else if ('modal' == $orderby) {
			$query->set('meta_key', 'onyx_poll_modal');
			$query->set('orderby', 'meta_value_num');
  		} else if ('status' == $orderby) {
			$query->set('meta_key', 'onyx_poll_expired');
			$query->set('orderby', 'meta_value_num');
  		}
	}

}


/**
 * Instantiate Onyx Poll Admin
 */
$onyx_poll_admin = new OnyxPollsCpt();

?>

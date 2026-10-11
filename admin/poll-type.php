<?php
if (!defined('ABSPATH')) { exit; }
/*

	@ POLL ADMIN POST TYPE/CONFIGURATION

*/

class OnyxPollsCpt {

	private $name;
	private $namep;
	private $cap;
	private $caps;
	private $slug;
	private $icon;
	private $name_l;
	private $namep_l;

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

		// Register post type and admin config
		$this->register_cpt();


		// Setup admin columns
		add_filter("manage_{$this->slug}_posts_columns", array($this, 'manage_columns'));
		add_action("manage_{$this->slug}_posts_custom_column", array($this, 'custom_columns'), 10, 2);
		add_filter("manage_edit-{$this->slug}_sortable_columns", array($this, 'sortable_columns'));
		add_action('pre_get_posts', array($this, 'orderby_columns'));
		add_filter('posts_clauses', array($this, 'sort_clauses'), 10, 2);
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
			'new_item'           => __('New poll', 'acf-onyx-poll'),
			'view_item'          => __('View poll', 'acf-onyx-poll'),
			'search_items'       => __('Search polls', 'acf-onyx-poll'),
			'not_found'          => __('No polls found', 'acf-onyx-poll'),
			'not_found_in_trash' => __('No polls found in Trash', 'acf-onyx-poll'),
			'parent_item_colon'  => __('Parent poll:', 'acf-onyx-poll'),
			'menu_name'          => $this->namep
		);

		$args = array(
			'labels'              => $labels,
			'hierarchical'        => false,
			'description'         => __('Create and manage polls.', 'acf-onyx-poll'),
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
		$poll = OnyxPollsStorage::get($post_id);
		if (!$poll) { return; }
		switch($column) {
			case 'id':
				echo "<span>[onyx-poll id='$post_id']</span>";
				break;
			case 'votes':
				echo $poll['total'];
				break;
			case 'modal':
				$modal  = $poll['modal'];
				$status = ($modal) ? "<span class='green' title='" . esc_attr__('Yes', 'acf-onyx-poll') . "'>✔</span>" : "<span class='red' title='" . esc_attr__('No', 'acf-onyx-poll') . "'>✘</span>";
				echo $status;
				break;
			case 'status':
				$expired = OnyxPollsStorage::expired($poll);
				$status  = (!$expired) ? "<span class='green' title='" . esc_attr__('Open', 'acf-onyx-poll') . "'>✔</span>" : "<span class='red' title='" . esc_attr__('Expired', 'acf-onyx-poll') . "'>✘</span>";
				echo $status;
				break;
			case 'end':
				$date = $poll['end'];
				echo $this->format_date($date);
				break;
		}
	}

	/**
	 * Format date
	 */
	public function format_date($date) {
		if ($date) {
			$date = DateTime::createFromFormat('Y-m-d H:i:s', $date, wp_timezone());
			if ($date) { return wp_date(get_option('date_format'), $date->getTimestamp(), wp_timezone()); }
		}

		return __('Not set', 'acf-onyx-poll');
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
		if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'onyxpolls') { return; }
		if ($query->get('orderby') === 'id') { $query->set('orderby', 'ID'); }

	}

	public function sort_clauses($clauses, $query) {
		global $wpdb;
		if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'onyxpolls') { return $clauses; }
		$orderby = $query->get('orderby');
		if (!in_array($orderby, array('votes', 'modal', 'status'), true)) { return $clauses; }
		$clauses['join'] .= ' LEFT JOIN ' . OnyxPollsStorage::table() . " onyx_sort ON onyx_sort.poll_id=$wpdb->posts.ID";
		if ($orderby === 'votes') {
			$column = '(SELECT COALESCE(SUM(votes),0) FROM ' . OnyxPollsStorage::table(true) . " WHERE poll_id=$wpdb->posts.ID)";
		} elseif ($orderby === 'status') {
			$column = $wpdb->prepare('(onyx_sort.expired=1 OR (onyx_sort.end IS NOT NULL AND onyx_sort.end<=%s))', current_time('mysql'));
		} else { $column = 'onyx_sort.modal'; }
		$order = strtoupper($query->get('order')) === 'ASC' ? 'ASC' : 'DESC';
		$clauses['orderby'] = "$column $order, $wpdb->posts.ID $order";
		return $clauses;
	}

}


/**
 * Instantiate Onyx Poll Admin on WordPress init hook
 */
function initialize_onyx_polls_cpt() {
    new OnyxPollsCpt();
}
add_action('init', 'initialize_onyx_polls_cpt');

?>

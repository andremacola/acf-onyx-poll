<?php
/*

	@ POLL CUSTOM WIDGET

*/

class OnyxPollsWidget extends WP_Widget {

// parent::__construct(
  
// // Base ID of your widget
// 'wpb_widget', 
  
// // Widget name will appear in UI
// __('WPBeginner Widget', 'wpb_widget_domain'), 
  
// // Widget description
// array( 'description' => __( 'Sample widget based on WPBeginner Tutorial', 'wpb_widget_domain' ), ) 
// );

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'onyx_poll',
			__('Poll', 'acf-onyx-poll'),
			array(
				'description' => __('Show ACF Onyx Poll', 'acf-onyx-poll')
			)
		);
	}

	/**
	 * Creating widget front-end
	 */
	public function widget($args, $instance) {
		$title = apply_filters('widget_title', $instance['title']);
		$poll = $instance['poll'];
		$css = $instance['css'];

		echo $args['before_widget'] . $args['before_title'] . $title . $args['after_title'];
		echo do_shortcode("[onyx-poll id=$poll class='$css']");
		echo $args['after_widget'];
	}

	/**
	 * Creating widget Backend 
	 */
	public function form($instance) {
		$title_id    = $this->get_field_id('title');
		$title_label = __('Title', 'acf-onyx-poll');
		$title_name  = $this->get_field_name('title');
		$title_value = !empty($instance['title']) ? $instance['title'] : '';

		$poll_id      = $this->get_field_id('poll');
		$poll_label   = __('Poll', 'acf-onyx-poll');
		$poll_name    = $this->get_field_name('poll');

		$css_id       = $this->get_field_id('css');
		$css_label    = __('Style', 'acf-onyx-poll');
		$css_name     = $this->get_field_name('css');
		$instance['css'] = $instance['css'] ?? '';

		$instance['poll'] = $instance['poll'] ?? false;
		$poll_options = $this->query_polls($instance['poll']);
		$form = "
			<p>
				<label for='$title_id'>$title_label:</label>
				<input
					class='widefat'
					type='text'
					id='$title_id'
					name='$title_name'
					value='$title_value'>
			</p>
			<p>
				<label for='$poll_id'>$poll_label:</label>
				<select
					class='widefat'
					id='$poll_id'
					name='$poll_name'>
					<option value=''>". __('Latest', 'acf-onyx-poll') ."</option>
					$poll_options
				</select>
			</p>
		";

		if (!get_field('onyx_poll_css', 'options')) {
			$form .= "
			<p>
				<label for='$css_id'>$css_label:</label>
				<select
					class='widefat'
					id='$css_id'
					name='$css_name'>
					<option value='standard' ". selected($instance['css'], 'Bar style', false) .">". __('Bar style', 'acf-onyx-poll') ."</option>
					<option value='twitter' ". selected($instance['css'], 'twitter', false) .">". __('Twitter style', 'acf-onyx-poll') ."</option>
				</select>
			</p>
			";
		}

		echo $form;
	}

	/**
	 * Updating widget replacing old instances with new
	 */
	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['poll']  = OnyxPolls::has_poll($instance['poll']) ? strip_tags($new_instance['poll']) : '';
		$instance['css'] = strip_tags($new_instance['css']);
		return $instance;
	}

	/**
	 * Query polls for widget select
	 * 
	 * @param int $option optional
	 */
	public function query_polls($option) {
		$response = null;
		$poll_query = new WP_Query(array(
			'post_type'        => 'onyxpolls',
			'posts_per_page'   => 10,
			'suppress_filters' => true,
			'no_found_rows'    => true
		));
		if ($poll_query->have_posts()) {
			foreach ($poll_query->posts as $post) {
				$select = ((int) $option === $post->ID) ? 'selected' : null;
				$response .= "<option value='$post->ID' $select>[$post->ID] / $post->post_title</option>";
			}
		}

		return $response;
	}

}

add_action('widgets_init', function() {
	register_widget('OnyxPollsWidget');
});

?>

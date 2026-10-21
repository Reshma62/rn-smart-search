<?php
/**
 * RN Results Count Elementor widget.
 *
 * Shows "Showing X–Y of Z" for the current listing/archive query and keeps it
 * in sync when JetSmartFilters re-renders the grid via AJAX. The total comes
 * from the query's already-computed found_posts (read from the grid wrapper's
 * data attributes), so updating the label is O(1) — no extra count query.
 *
 * @package RN_Smart_Search
 */

namespace RN_Smart_Search\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Results_Count_Widget
 */
class Results_Count_Widget extends Widget_Base {

	public function get_name() {
		return 'rn_results_count';
	}

	public function get_title() {
		return __( 'RN Results Count', 'rn-smart-search' );
	}

	public function get_icon() {
		return 'eicon-number-field';
	}

	public function get_categories() {
		return array( 'rn-widgets' );
	}

	public function get_keywords() {
		return array( 'rn', 'results', 'count', 'showing', 'total', 'products', 'woocommerce' );
	}

	public function get_script_depends() {
		return array( 'rn-results-count' );
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Results Count', 'rn-smart-search' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'template',
			array(
				'label'       => __( 'Template', 'rn-smart-search' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Showing %start%–%end% of %total%', 'rn-smart-search' ),
				'label_block' => true,
				'description' => __( 'Placeholders: %start% %end% %total% %page% %pages%', 'rn-smart-search' ),
			)
		);

		$this->add_control(
			'query_id',
			array(
				'label'       => __( 'Listing Grid Query ID (CSS ID)', 'rn-smart-search' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => 'shop-grid',
				'description' => __( 'The Listing Grid\'s CSS ID — the same value you use as the JSF Query ID. Recommended when the page has more than one grid.', 'rn-smart-search' ),
			)
		);

		$this->add_control(
			'grid_selector',
			array(
				'label'       => __( 'Grid items selector (fallback)', 'rn-smart-search' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '.jet-listing-grid__items',
				'description' => __( 'Used only when no Query ID is set. CSS selector of the listing grid items wrapper.', 'rn-smart-search' ),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'rn-smart-search' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array( 'title' => __( 'Left', 'rn-smart-search' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => __( 'Center', 'rn-smart-search' ), 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => __( 'Right', 'rn-smart-search' ), 'icon' => 'eicon-text-align-right' ),
				),
				'default'   => 'left',
				'selectors' => array(
					'{{WRAPPER}} .rn-results-count' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Style', 'rn-smart-search' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Text color', 'rn-smart-search' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6b7280',
				'selectors' => array(
					'{{WRAPPER}} .rn-results-count' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'text_typography',
				'selector' => '{{WRAPPER}} .rn-results-count',
			)
		);

		$this->add_control(
			'strong_color',
			array(
				'label'     => __( 'Number color', 'rn-smart-search' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1a1a1a',
				'selectors' => array(
					'{{WRAPPER}} .rn-results-count b' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Compute the initial (server-side) count from the main query.
	 *
	 * @return array [start, end, total, page, pages]
	 */
	protected function get_initial_counts() {
		global $wp_query;

		$total = ( $wp_query && isset( $wp_query->found_posts ) ) ? (int) $wp_query->found_posts : 0;
		$per   = ( $wp_query ) ? (int) $wp_query->get( 'posts_per_page' ) : 0;
		if ( $per <= 0 ) {
			$per = (int) get_option( 'posts_per_page', 10 );
		}
		$pages = ( $wp_query && isset( $wp_query->max_num_pages ) ) ? (int) $wp_query->max_num_pages : 1;
		$page  = max( 1, (int) get_query_var( 'paged' ) );

		$start = $total > 0 ? ( ( $page - 1 ) * $per + 1 ) : 0;
		$end   = min( $page * $per, $total );

		return array( $start, $end, $total, $page, max( 1, $pages ) );
	}

	/**
	 * Fill the template placeholders.
	 *
	 * @param string $tpl Template.
	 * @param array  $c   [start, end, total, page, pages].
	 * @return string
	 */
	protected function fill_template( $tpl, $c ) {
		list( $start, $end, $total, $page, $pages ) = $c;

		return str_replace(
			array( '%start%', '%end%', '%total%', '%page%', '%pages%' ),
			array(
				'<b>' . number_format_i18n( $start ) . '</b>',
				'<b>' . number_format_i18n( $end ) . '</b>',
				'<b>' . number_format_i18n( $total ) . '</b>',
				'<b>' . number_format_i18n( $page ) . '</b>',
				'<b>' . number_format_i18n( $pages ) . '</b>',
			),
			$tpl
		);
	}

	/**
	 * Render.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$template = ! empty( $settings['template'] ) ? $settings['template'] : 'Showing %start%–%end% of %total%';

		// Prefer the grid's CSS ID (= JSF Query ID) for precise targeting; the
		// count data attributes live on the .jet-listing-grid__items wrapper
		// inside it (that outer id stays put across JSF AJAX re-renders).
		$query_id = isset( $settings['query_id'] ) ? trim( $settings['query_id'] ) : '';
		if ( '' !== $query_id ) {
			$selector = '#' . $query_id . ' .jet-listing-grid__items';
		} else {
			$selector = ! empty( $settings['grid_selector'] ) ? $settings['grid_selector'] : '.jet-listing-grid__items';
		}

		$is_edit = \Elementor\Plugin::$instance->editor->is_edit_mode();

		if ( $is_edit ) {
			$counts = array( 1, 16, 100, 1, 7 ); // demo
		} else {
			$counts = $this->get_initial_counts();
		}

		printf(
			'<div class="rn-results-count" data-template="%1$s" data-grid="%2$s">%3$s</div>',
			esc_attr( $template ),
			esc_attr( $selector ),
			wp_kses( $this->fill_template( $template, $counts ), array( 'b' => array() ) )
		);
	}
}

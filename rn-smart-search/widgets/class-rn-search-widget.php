<?php
/**
 * RN Smart Search Elementor widget.
 *
 * @package RN_Smart_Search
 */

namespace RN_Smart_Search\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class Search_Widget
 */
class Search_Widget extends Widget_Base
{

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name()
	{
		return 'rn_smart_search';
	}

	/**
	 * Widget title in the panel.
	 *
	 * @return string
	 */
	public function get_title()
	{
		return __('RN Product Search', 'rn-smart-search');
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon()
	{
		return 'eicon-search';
	}

	/**
	 * Widget category (custom RN section).
	 *
	 * @return array
	 */
	public function get_categories()
	{
		return array('rn-widgets');
	}

	/**
	 * Search keywords in the panel.
	 *
	 * @return array
	 */
	public function get_keywords()
	{
		return array('rn', 'search', 'product', 'woocommerce', 'category', 'shop');
	}

	/**
	 * Front-end style dependencies.
	 *
	 * @return array
	 */
	public function get_style_depends()
	{
		return array('rn-smart-search');
	}

	/**
	 * Front-end script dependencies.
	 *
	 * @return array
	 */
	public function get_script_depends()
	{
		return array('rn-smart-search');
	}

	/**
	 * Register widget controls.
	 */
	protected function register_controls()
	{

		/* ----------------------------------------------------------------
		 * CONTENT
		 * ---------------------------------------------------------------- */
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __('Search', 'rn-smart-search'),
				'tab' => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'placeholder',
			array(
				'label' => __('Placeholder', 'rn-smart-search'),
				'type' => Controls_Manager::TEXT,
				'default' => __('Search parts, brands, accessories...', 'rn-smart-search'),
				'label_block' => true,
			)
		);

		$this->add_control(
			'show_category',
			array(
				'label' => __('Show category selector', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Yes', 'rn-smart-search'),
				'label_off' => __('No', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => 'yes',
			)
		);

		$this->add_control(
			'parent_categories_only',
			array(
				'label' => __('Only parent categories', 'rn-smart-search'),
				'description' => __('Show only top-level categories in the dropdown — sub-categories are hidden.', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Yes', 'rn-smart-search'),
				'label_off' => __('No', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => '',
				'condition' => array(
					'show_category' => 'yes',
				),
			)
		);

		$this->add_control(
			'show_uncategorized',
			array(
				'label' => __('Show "Uncategorized"', 'rn-smart-search'),
				'description' => __('Include WooCommerce\'s default "Uncategorized" category in the dropdown.', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Yes', 'rn-smart-search'),
				'label_off' => __('No', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => '',
				'condition' => array(
					'show_category' => 'yes',
				),
			)
		);

		$this->add_control(
			'all_categories_label',
			array(
				'label' => __('"All" option label', 'rn-smart-search'),
				'type' => Controls_Manager::TEXT,
				'default' => __('All Categories', 'rn-smart-search'),
				'condition' => array(
					'show_category' => 'yes',
				),
			)
		);

		$this->add_control(
			'preview_count',
			array(
				'label' => __('Live preview results', 'rn-smart-search'),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 12,
				'step' => 1,
				'default' => 5,
			)
		);

		$this->add_control(
			'show_preview',
			array(
				'label' => __('Show live preview panel', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Yes', 'rn-smart-search'),
				'label_off' => __('No', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_history',
			array(
				'label' => __('Show recent searches', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Yes', 'rn-smart-search'),
				'label_off' => __('No', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => 'yes',
				'description' => __('Remember recent searches (in the visitor’s browser) and show them on focus.', 'rn-smart-search'),
			)
		);

		$this->add_control(
			'history_title',
			array(
				'label' => __('Recent searches title', 'rn-smart-search'),
				'type' => Controls_Manager::TEXT,
				'default' => __('Recent searches', 'rn-smart-search'),
				'condition' => array('show_history' => 'yes'),
			)
		);

		$this->add_control(
			'history_clear_text',
			array(
				'label' => __('Clear button text', 'rn-smart-search'),
				'type' => Controls_Manager::TEXT,
				'default' => __('Clear', 'rn-smart-search'),
				'condition' => array('show_history' => 'yes'),
			)
		);

		$this->add_control(
			'live_filter',
			array(
				'label' => __('Live filter results on shop', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Yes', 'rn-smart-search'),
				'label_off' => __('No', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => 'yes',
				'description' => __('When the results page has a JetSmartFilters Search filter (connected to the grid), searching filters it live via AJAX instead of reloading. Falls back to opening the shop otherwise.', 'rn-smart-search'),
			)
		);

		$this->add_control(
			'grid_live_enable',
			array(
				'label' => __('Live search listing grids by CSS ID', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Yes', 'rn-smart-search'),
				'label_off' => __('No', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => '',
				'description' => __('On any page that contains JetEngine Listing Grids with these CSS IDs, typing filters every matching grid live instead of showing the preview.', 'rn-smart-search'),
			)
		);

		$this->add_control(
			'grid_live_ids',
			array(
				'label' => __('Listing grid CSS IDs', 'rn-smart-search'),
				'type' => Controls_Manager::TEXT,
				'placeholder' => 'wishlist_products, featured_grid',
				'label_block' => true,
				'description' => __('Comma-separated, as many as you need (e.g. wishlist_products, featured_grid). Set the same ID in each Listing Grid → Advanced → CSS ID.', 'rn-smart-search'),
				'condition' => array('grid_live_enable' => 'yes'),
			)
		);

		$this->add_control(
			'grid_empty_enable',
			array(
				'label' => __('No results message', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Yes', 'rn-smart-search'),
				'label_off' => __('No', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => '',
				'separator' => 'before',
				'description' => __('Shown right below the live-searched grid (shop grid and the CSS IDs above) when a search finds nothing.', 'rn-smart-search'),
			)
		);

		$this->add_control(
			'grid_empty_text',
			array(
				'label' => __('Message', 'rn-smart-search'),
				'type' => Controls_Manager::TEXTAREA,
				'rows' => 2,
				'default' => __('No results found for "{term}"', 'rn-smart-search'),
				'description' => __('{term} = what the visitor typed. Leave empty when you use your own section below.', 'rn-smart-search'),
				'condition' => array('grid_empty_enable' => 'yes'),
			)
		);

		$this->add_control(
			'grid_empty_element',
			array(
				'label' => __('Or show my own section (CSS ID)', 'rn-smart-search'),
				'type' => Controls_Manager::TEXT,
				'placeholder' => 'search-no-results',
				'label_block' => true,
				'description' => __('Design any section in Elementor, give it this CSS ID (Advanced → CSS ID). It stays hidden and appears only when a search finds nothing. {term} inside it is replaced with the search. Used instead of the message above.', 'rn-smart-search'),
				'condition' => array('grid_empty_enable' => 'yes'),
			)
		);


		$this->add_control(
			'show_total',
			array(
				'label' => __('Show total results', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Yes', 'rn-smart-search'),
				'label_off' => __('No', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => 'yes',
				'condition' => array(
					'show_preview' => 'yes',
				),
			)
		);

		$this->add_control(
			'total_text',
			array(
				'label' => __('Total results text', 'rn-smart-search'),
				'type' => Controls_Manager::TEXT,
				'default' => __('%s results found', 'rn-smart-search'),
				'description' => __('Use %s where the number should appear.', 'rn-smart-search'),
				'condition' => array(
					'show_preview' => 'yes',
					'show_total' => 'yes',
				),
			)
		);

		$this->add_control(
			'show_thumbnail',
			array(
				'label' => __('Show thumbnail', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('On', 'rn-smart-search'),
				'label_off' => __('Off', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => 'yes',
				'condition' => array('show_preview' => 'yes'),
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label' => __('Show price', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('On', 'rn-smart-search'),
				'label_off' => __('Off', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => 'yes',
				'condition' => array('show_preview' => 'yes'),
			)
		);

		$this->add_control(
			'price_logged_in_only',
			array(
				'label' => __('Show price only to logged-in users', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Yes', 'rn-smart-search'),
				'label_off' => __('No', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => 'yes',
				'description' => __('Guests see the results without a price. The price is removed on the server, so it never reaches a guest’s browser.', 'rn-smart-search'),
				'condition' => array('show_preview' => 'yes', 'show_price' => 'yes'),
			)
		);

		$this->add_control(
			'show_result_category',
			array(
				'label' => __('Show category', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('On', 'rn-smart-search'),
				'label_off' => __('Off', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => '',
				'condition' => array('show_preview' => 'yes'),
			)
		);

		if (function_exists('jet_engine')) {
			$this->add_control(
				'result_listing_id',
				array(
					'label' => __('Custom result template', 'rn-smart-search'),
					'type' => Controls_Manager::SELECT,
					'options' => array('' => __('— Default layout —', 'rn-smart-search')) + $this->get_jet_listings(),
					'default' => '',
					'description' => __('Optionally render each result with a JetEngine Listing template for a fully custom design.', 'rn-smart-search'),
					'condition' => array('show_preview' => 'yes'),
				)
			);
		}

		$this->add_control(
			'button_heading',
			array(
				'label' => __('Search Button', 'rn-smart-search'),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'button_icon',
			array(
				'label' => __('Icon', 'rn-smart-search'),
				'type' => Controls_Manager::ICONS,
				'description' => __('Pick an icon or upload your own SVG. Leave empty to use the default search icon.', 'rn-smart-search'),
				'skin' => 'inline',
				'default' => array(
					'value' => '',
					'library' => '',
				),
			)
		);

		$this->add_control(
			'show_button_text',
			array(
				'label' => __('Show button text', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('On', 'rn-smart-search'),
				'label_off' => __('Off', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => '',
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label' => __('Button text', 'rn-smart-search'),
				'type' => Controls_Manager::TEXT,
				'default' => __('Search', 'rn-smart-search'),
				'condition' => array(
					'show_button_text' => 'yes',
				),
			)
		);

		$this->add_control(
			'button_icon_position',
			array(
				'label' => __('Icon position', 'rn-smart-search'),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array('title' => __('Before text', 'rn-smart-search'), 'icon' => 'eicon-h-align-left'),
					'right' => array('title' => __('After text', 'rn-smart-search'), 'icon' => 'eicon-h-align-right'),
				),
				'default' => 'right',
				'condition' => array(
					'show_button_text' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		/* ----------------------------------------------------------------
		 * STYLE — Search bar
		 * ---------------------------------------------------------------- */
		$this->start_controls_section(
			'section_style_bar',
			array(
				'label' => __('Search Bar', 'rn-smart-search'),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'bar_max_width',
			array(
				'label' => __('Max width', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem', '%'),
				'range' => array(
					'px' => array(
						'min' => 320,
						'max' => 1200,
					),
					'%' => array(
						'min' => 30,
						'max' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .rn-search' => 'max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'bar_min_height',
			array(
				'label' => __('Min height', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem', 'vh', '%'),
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 120,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 60,
				),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__bar' => 'min-height: {{SIZE}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'bar_height',
			array(
				'label' => __('Height', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem', 'vh', '%'),
				'range' => array(
					'px' => array(
						'min' => 40,
						'max' => 120,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__bar' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'bar_bg',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__bar' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'bar_border',
				'selector' => '{{WRAPPER}} .rn-search__bar',
			)
		);

		$this->add_responsive_control(
			'bar_radius',
			array(
				'label' => __('Border radius', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em', 'rem', '%'),
				'default' => array(
					'unit' => 'px',
					'top' => '40',
					'right' => '40',
					'bottom' => '40',
					'left' => '40',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__bar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'bar_padding',
			array(
				'label' => __('Padding', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em', 'rem', '%'),
				'default' => array(
					'unit' => 'px',
					'top' => '6',
					'right' => '6',
					'bottom' => '6',
					'left' => '22',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__bar' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' => 'bar_shadow',
				'selector' => '{{WRAPPER}} .rn-search__bar',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'input_typography',
				'label' => __('Text typography', 'rn-smart-search'),
				'selector' => '{{WRAPPER}} .rn-search__input, {{WRAPPER}} .rn-search__select',
			)
		);

		$this->add_control(
			'input_color',
			array(
				'label' => __('Text color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#1a1a1a',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__input' => 'color: {{VALUE}};',
					'{{WRAPPER}} .rn-search__select' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'placeholder_color',
			array(
				'label' => __('Placeholder color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#9aa0a6',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__input::placeholder' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		/* ----------------------------------------------------------------
		 * STYLE — Button
		 * ---------------------------------------------------------------- */
		$this->start_controls_section(
			'section_style_button',
			array(
				'label' => __('Button', 'rn-smart-search'),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'button_width',
			array(
				'label' => __('Width', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem', '%'),
				'range' => array('px' => array('min' => 40, 'max' => 140)),
				'default' => array('unit' => 'px', 'size' => 76),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__button' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_radius',
			array(
				'label' => __('Border radius', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em', 'rem', '%'),
				'default' => array(
					'unit' => 'px',
					'top' => '34',
					'right' => '34',
					'bottom' => '34',
					'left' => '34',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'button_border',
				'selector' => '{{WRAPPER}} .rn-search__button',
			)
		);

		$this->add_responsive_control(
			'button_icon_size',
			array(
				'label' => __('Icon size', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem', '%'),
				'range' => array('px' => array('min' => 12, 'max' => 40)),
				'default' => array('unit' => 'px', 'size' => 22),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__button-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .rn-search__button-icon i' => 'font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'button_text_typography',
				'label' => __('Text typography', 'rn-smart-search'),
				'selector' => '{{WRAPPER}} .rn-search__button-text',
				'condition' => array('show_button_text' => 'yes'),
			)
		);

		$this->add_responsive_control(
			'button_gap',
			array(
				'label' => __('Text/Icon gap', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem'),
				'range' => array('px' => array('min' => 0, 'max' => 30)),
				'default' => array('unit' => 'px', 'size' => 8),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__button' => 'gap: {{SIZE}}{{UNIT}};',
				),
				'condition' => array('show_button_text' => 'yes'),
			)
		);

		$this->start_controls_tabs('button_tabs');

		$this->start_controls_tab('button_tab_normal', array('label' => __('Normal', 'rn-smart-search')));
		$this->add_control(
			'button_bg',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#E9A23B',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__button' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'button_icon_color',
			array(
				'label' => __('Icon color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#1a1a1a',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__button-icon' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'button_text_color',
			array(
				'label' => __('Text color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#1a1a1a',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__button-text' => 'color: {{VALUE}};',
				),
				'condition' => array('show_button_text' => 'yes'),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab('button_tab_hover', array('label' => __('Hover', 'rn-smart-search')));
		$this->add_control(
			'button_bg_hover',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#d8912c',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__button:hover' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'button_icon_color_hover',
			array(
				'label' => __('Icon color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#1a1a1a',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__button:hover .rn-search__button-icon' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'button_text_color_hover',
			array(
				'label' => __('Text color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#1a1a1a',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__button:hover .rn-search__button-text' => 'color: {{VALUE}};',
				),
				'condition' => array('show_button_text' => 'yes'),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		/* ----------------------------------------------------------------
		 * STYLE — Category dropdown
		 * ---------------------------------------------------------------- */
		$this->start_controls_section(
			'section_style_cat',
			array(
				'label' => __('Category Dropdown', 'rn-smart-search'),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_category' => 'yes',
				),
			)
		);

		$this->add_control(
			'cat_heading_trigger',
			array(
				'label' => __('Trigger', 'rn-smart-search'),
				'type' => Controls_Manager::HEADING,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'cat_trigger_typography',
				'selector' => '{{WRAPPER}} .rn-search__cat-trigger',
			)
		);

		$this->add_control(
			'cat_chevron_color',
			array(
				'label' => __('Arrow color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#444444',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__chevron' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'cat_trigger_padding',
			array(
				'label' => __('Trigger padding', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em', 'rem', '%'),
				'default' => array('unit' => 'px', 'top' => '6', 'right' => '4', 'bottom' => '6', 'left' => '0', 'isLinked' => false),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__cat-trigger' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'cat_trigger_radius',
			array(
				'label' => __('Trigger border radius', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem', '%'),
				'range' => array('px' => array('min' => 0, 'max' => 40)),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__cat-trigger' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs('cat_trigger_tabs');

		$this->start_controls_tab('cat_trigger_normal', array('label' => __('Normal', 'rn-smart-search')));
		$this->add_control(
			'cat_trigger_bg',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => 'transparent',
				'selectors' => array('{{WRAPPER}} .rn-search__cat-trigger' => 'background-color: {{VALUE}};'),
			)
		);
		$this->add_control(
			'cat_trigger_color',
			array(
				'label' => __('Text color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#1a1a1a',
				'selectors' => array('{{WRAPPER}} .rn-search__cat-label' => 'color: {{VALUE}};'),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab('cat_trigger_hover', array('label' => __('Hover', 'rn-smart-search')));
		$this->add_control(
			'cat_trigger_bg_hover',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => array('{{WRAPPER}} .rn-search__cat-trigger:hover' => 'background-color: {{VALUE}};'),
			)
		);
		$this->add_control(
			'cat_trigger_color_hover',
			array(
				'label' => __('Text color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => array('{{WRAPPER}} .rn-search__cat-trigger:hover .rn-search__cat-label' => 'color: {{VALUE}};'),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab('cat_trigger_active', array('label' => __('Active', 'rn-smart-search')));
		$this->add_control(
			'cat_trigger_bg_active',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'description' => __('Shown while the dropdown is open.', 'rn-smart-search'),
				'selectors' => array('{{WRAPPER}} .rn-search__cat[data-open="1"] .rn-search__cat-trigger' => 'background-color: {{VALUE}};'),
			)
		);
		$this->add_control(
			'cat_trigger_color_active',
			array(
				'label' => __('Text color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => array('{{WRAPPER}} .rn-search__cat[data-open="1"] .rn-search__cat-label' => 'color: {{VALUE}};'),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'cat_heading_panel',
			array(
				'label' => __('Dropdown Panel', 'rn-smart-search'),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'cat_panel_offset',
			array(
				'label' => __('Distance from search bar', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem'),
				'range' => array('px' => array('min' => 0, 'max' => 60)),
				'default' => array('unit' => 'px', 'size' => 12),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__cat-panel' => 'top: calc(100% + {{SIZE}}{{UNIT}});',
				),
			)
		);

		$this->add_responsive_control(
			'cat_panel_width',
			array(
				'label' => __('Min width', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem', '%'),
				'range' => array('px' => array('min' => 140, 'max' => 400)),
				'default' => array('unit' => 'px', 'size' => 220),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__cat-panel' => 'min-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'cat_panel_bg',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__cat-panel' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'cat_panel_border',
				'selector' => '{{WRAPPER}} .rn-search__cat-panel',
			)
		);

		$this->add_responsive_control(
			'cat_panel_radius',
			array(
				'label' => __('Border radius', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em', 'rem', '%'),
				'default' => array(
					'unit' => 'px',
					'top' => '14',
					'right' => '14',
					'bottom' => '14',
					'left' => '14',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__cat-panel' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' => 'cat_panel_shadow',
				'selector' => '{{WRAPPER}} .rn-search__cat-panel',
			)
		);

		$this->add_control(
			'cat_heading_option',
			array(
				'label' => __('Options', 'rn-smart-search'),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'cat_option_gap',
			array(
				'label' => __('Space between items', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem', '%'),
				'range' => array(
					'px' => array('min' => 0, 'max' => 30),
				),
				'default' => array('unit' => 'px', 'size' => 0),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__cat-panel' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'cat_option_padding',
			array(
				'label' => __('Item padding', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em', 'rem', '%'),
				'default' => array('unit' => 'px', 'top' => '10', 'right' => '16', 'bottom' => '10', 'left' => '16', 'isLinked' => false),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__cat-option' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'cat_option_typography',
				'label' => __('Options typography', 'rn-smart-search'),
				'selector' => '{{WRAPPER}} .rn-search__cat-option',
			)
		);

		$this->start_controls_tabs('cat_option_tabs');

		$this->start_controls_tab('cat_option_normal', array('label' => __('Normal', 'rn-smart-search')));
		$this->add_control(
			'cat_option_color',
			array(
				'label' => __('Text color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#1a1a1a',
				'selectors' => array('{{WRAPPER}} .rn-search__cat-option' => 'color: {{VALUE}};'),
			)
		);
		$this->add_control(
			'cat_option_bg',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => 'transparent',
				'selectors' => array('{{WRAPPER}} .rn-search__cat-option' => 'background-color: {{VALUE}};'),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab('cat_option_hover', array('label' => __('Hover', 'rn-smart-search')));
		$this->add_control(
			'cat_option_color_hover',
			array(
				'label' => __('Text color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#1a1a1a',
				'selectors' => array('{{WRAPPER}} .rn-search__cat-option:hover' => 'color: {{VALUE}};'),
			)
		);
		$this->add_control(
			'cat_option_bg_hover',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#f6f6f6',
				'selectors' => array('{{WRAPPER}} .rn-search__cat-option:hover' => 'background-color: {{VALUE}};'),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab('cat_option_active', array('label' => __('Active', 'rn-smart-search')));
		$this->add_control(
			'cat_option_color_active',
			array(
				'label' => __('Text color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#1a1a1a',
				'selectors' => array('{{WRAPPER}} .rn-search__cat-option.is-active' => 'color: {{VALUE}};'),
			)
		);
		$this->add_control(
			'cat_option_bg_active',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#fff6e9',
				'selectors' => array('{{WRAPPER}} .rn-search__cat-option.is-active' => 'background-color: {{VALUE}};'),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		/* ----------------------------------------------------------------
		 * STYLE — Preview panel
		 * ---------------------------------------------------------------- */
		$this->start_controls_section(
			'section_style_preview',
			array(
				'label' => __('Preview Panel', 'rn-smart-search'),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_preview' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'preview_offset',
			array(
				'label' => __('Distance from search bar', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem'),
				'range' => array('px' => array('min' => 0, 'max' => 60)),
				'default' => array('unit' => 'px', 'size' => 10),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__preview' => 'top: calc(100% + {{SIZE}}{{UNIT}});',
				),
			)
		);

		$this->add_control(
			'preview_bg',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__preview' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'total_heading',
			array(
				'label' => __('Total Results Header', 'rn-smart-search'),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'total_typography',
				'selector' => '{{WRAPPER}} .rn-search__preview-head',
			)
		);

		$this->add_control(
			'total_color',
			array(
				'label' => __('Text color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#6b7280',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__preview-head' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'total_number_color',
			array(
				'label' => __('Number color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#1a1a1a',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__preview-head b' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'preview_items_heading',
			array(
				'label' => __('Items', 'rn-smart-search'),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'result_cat_color',
			array(
				'label' => __('Category color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#9aa0a6',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__result-cat' => 'color: {{VALUE}};',
				),
				'condition' => array('show_result_category' => 'yes'),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'result_cat_typography',
				'label' => __('Category typography', 'rn-smart-search'),
				'selector' => '{{WRAPPER}} .rn-search__result-cat',
				'condition' => array('show_result_category' => 'yes'),
			)
		);

		$this->add_control(
			'preview_title_color',
			array(
				'label' => __('Product name color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#1a1a1a',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__result-title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'preview_border',
				'selector' => '{{WRAPPER}} .rn-search__preview',
			)
		);

		$this->add_responsive_control(
			'preview_radius',
			array(
				'label' => __('Border radius', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em', 'rem', '%'),
				'default' => array(
					'unit' => 'px',
					'top' => '16',
					'right' => '16',
					'bottom' => '16',
					'left' => '16',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__preview' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' => 'preview_shadow',
				'selector' => '{{WRAPPER}} .rn-search__preview',
			)
		);

		$this->add_control(
			'preview_maxheight',
			array(
				'label' => __('Max height', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem', 'vh'),
				'range' => array('px' => array('min' => 200, 'max' => 800), 'vh' => array('min' => 20, 'max' => 90)),
				'default' => array('unit' => 'px', 'size' => 420),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__preview' => 'max-height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'preview_result_heading',
			array(
				'label' => __('Result Item', 'rn-smart-search'),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'result_gap',
			array(
				'label' => __('Space between items', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem', '%'),
				'range' => array(
					'px' => array('min' => 0, 'max' => 40),
				),
				'default' => array('unit' => 'px', 'size' => 2),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__preview-list' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'result_padding',
			array(
				'label' => __('Padding', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em', 'rem', '%'),
				'default' => array('unit' => 'px', 'top' => '10', 'right' => '12', 'bottom' => '10', 'left' => '12', 'isLinked' => false),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__result' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'result_hover_bg',
			array(
				'label' => __('Item hover background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#f6f6f6',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__result:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'thumb_size',
			array(
				'label' => __('Thumbnail size', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px', 'em', 'rem', '%'),
				'range' => array('px' => array('min' => 32, 'max' => 96)),
				'default' => array('unit' => 'px', 'size' => 52),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__result-thumb' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'thumb_radius',
			array(
				'label' => __('Thumbnail radius', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em', 'rem', '%'),
				'default' => array(
					'unit' => 'px',
					'top' => '10',
					'right' => '10',
					'bottom' => '10',
					'left' => '10',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .rn-search__result-thumb' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'result_title_typography',
				'label' => __('Name typography', 'rn-smart-search'),
				'selector' => '{{WRAPPER}} .rn-search__result-title',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'result_price_typography',
				'label' => __('Price typography', 'rn-smart-search'),
				'selector' => '{{WRAPPER}} .rn-search__result-price',
			)
		);

		$this->add_control(
			'preview_extra_heading',
			array(
				'label' => __('View all / States', 'rn-smart-search'),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'viewall_color',
			array(
				'label' => __('"View all" color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#1a1a1a',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__viewall' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'viewall_color_hover',
			array(
				'label' => __('"View all" color (hover)', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#d8912c',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__viewall:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'state_color',
			array(
				'label' => __('Loading / no-results text', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'default' => '#6b7280',
				'selectors' => array(
					'{{WRAPPER}} .rn-search__state' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->register_history_style_controls();
		$this->register_empty_style_controls();
	}

	/**
	 * Style → No Results Message. The message is placed below the grid (outside
	 * this widget), so selectors target its widget-specific class via {{ID}}.
	 */
	private function register_empty_style_controls()
	{
		$sel = '.rn-grid-empty.rn-grid-empty--{{ID}}';

		$this->start_controls_section(
			'section_style_grid_empty',
			array(
				'label' => __('No Results Message', 'rn-smart-search'),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array('grid_empty_enable' => 'yes', 'grid_empty_element' => ''),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'grid_empty_typography',
				'selector' => $sel,
			)
		);

		$this->add_control(
			'grid_empty_color',
			array(
				'label' => __('Text Color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'selectors' => array($sel => 'color: {{VALUE}};'),
			)
		);

		$this->add_control(
			'grid_empty_bg',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'selectors' => array($sel => 'background-color: {{VALUE}};'),
			)
		);

		$this->add_responsive_control(
			'grid_empty_align',
			array(
				'label' => __('Alignment', 'rn-smart-search'),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array('title' => __('Left', 'rn-smart-search'), 'icon' => 'eicon-text-align-left'),
					'center' => array('title' => __('Center', 'rn-smart-search'), 'icon' => 'eicon-text-align-center'),
					'right' => array('title' => __('Right', 'rn-smart-search'), 'icon' => 'eicon-text-align-right'),
				),
				'selectors' => array($sel => 'text-align: {{VALUE}};'),
			)
		);

		$this->add_responsive_control(
			'grid_empty_padding',
			array(
				'label' => __('Padding', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em', '%'),
				'selectors' => array(
					$sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'grid_empty_margin',
			array(
				'label' => __('Margin', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em', '%'),
				'selectors' => array(
					$sel => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'grid_empty_border',
				'selector' => $sel,
			)
		);

		$this->add_responsive_control(
			'grid_empty_radius',
			array(
				'label' => __('Border Radius', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', '%'),
				'selectors' => array(
					$sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Recent Searches. Only CSS selectors (Elementor writes them to the
	 * page's generated CSS file), so no extra runtime cost. Controls without a
	 * value emit nothing — the stylesheet defaults apply.
	 */
	private function register_history_style_controls()
	{
		$wrap = '{{WRAPPER}} .rn-search__history';

		$this->start_controls_section(
			'section_style_history',
			array(
				'label' => __('Recent Searches', 'rn-smart-search'),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array('show_history' => 'yes'),
			)
		);

		// ---- Title row ----
		$this->add_control(
			'history_title_heading',
			array(
				'label' => __('Title', 'rn-smart-search'),
				'type' => Controls_Manager::HEADING,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'history_title_typography',
				'selector' => $wrap . ' .rn-search__assist-head span',
			)
		);

		$this->add_control(
			'history_title_color',
			array(
				'label' => __('Color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'selectors' => array($wrap . ' .rn-search__assist-head span' => 'color: {{VALUE}};'),
			)
		);

		$this->add_responsive_control(
			'history_title_padding',
			array(
				'label' => __('Padding', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em'),
				'selectors' => array(
					$wrap . ' .rn-search__assist-head' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		// ---- Clear button ----
		$this->add_control(
			'history_clear_heading',
			array(
				'label' => __('Clear Button', 'rn-smart-search'),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'history_clear_typography',
				'selector' => $wrap . ' .rn-search__history-clear',
			)
		);

		$this->add_control(
			'history_clear_color',
			array(
				'label' => __('Color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'selectors' => array($wrap . ' .rn-search__history-clear' => 'color: {{VALUE}};'),
			)
		);

		$this->add_control(
			'history_clear_hover_color',
			array(
				'label' => __('Hover Color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'selectors' => array($wrap . ' .rn-search__history-clear:hover' => 'color: {{VALUE}};'),
			)
		);

		// ---- Items ----
		$this->add_control(
			'history_item_heading',
			array(
				'label' => __('Items', 'rn-smart-search'),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'history_item_typography',
				'selector' => $wrap . ' .rn-search__hist-term',
			)
		);

		$this->add_responsive_control(
			'history_item_padding',
			array(
				'label' => __('Padding', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', 'em'),
				'selectors' => array(
					$wrap . ' .rn-search__hist-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'history_item_radius',
			array(
				'label' => __('Border Radius', 'rn-smart-search'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array('px', '%'),
				'selectors' => array(
					$wrap . ' .rn-search__hist-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs('history_item_tabs');

		$this->start_controls_tab('history_item_normal', array('label' => __('Normal', 'rn-smart-search')));

		$this->add_control(
			'history_item_color',
			array(
				'label' => __('Text Color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'selectors' => array($wrap . ' .rn-search__hist-term' => 'color: {{VALUE}};'),
			)
		);

		$this->add_control(
			'history_item_bg',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'selectors' => array($wrap . ' .rn-search__hist-item' => 'background-color: {{VALUE}};'),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab('history_item_hover', array('label' => __('Hover', 'rn-smart-search')));

		$this->add_control(
			'history_item_hover_color',
			array(
				'label' => __('Text Color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'selectors' => array($wrap . ' .rn-search__hist-item:hover .rn-search__hist-term' => 'color: {{VALUE}};'),
			)
		);

		$this->add_control(
			'history_item_hover_bg',
			array(
				'label' => __('Background', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'selectors' => array($wrap . ' .rn-search__hist-item:hover' => 'background-color: {{VALUE}};'),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		// ---- Icons ----
		$this->add_control(
			'history_icon_heading',
			array(
				'label' => __('Icons', 'rn-smart-search'),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'history_hide_icon',
			array(
				'label' => __('Hide history icon', 'rn-smart-search'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Yes', 'rn-smart-search'),
				'label_off' => __('No', 'rn-smart-search'),
				'return_value' => 'yes',
				'default' => '',
				// Adds the wrapper class rn-hist-icon-hide-yes only when switched on,
				// so existing widgets keep the icon.
				'prefix_class' => 'rn-hist-icon-hide-',
			)
		);

		$this->add_control(
			'history_icon_color',
			array(
				'label' => __('History icon color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'selectors' => array($wrap . ' .rn-search__hist-term::before' => 'color: {{VALUE}};'),
				'condition' => array('history_hide_icon' => ''),
			)
		);

		$this->add_control(
			'history_remove_color',
			array(
				'label' => __('Remove (×) color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'selectors' => array($wrap . ' .rn-search__hist-remove' => 'color: {{VALUE}};'),
			)
		);

		$this->add_control(
			'history_remove_hover_color',
			array(
				'label' => __('Remove (×) hover color', 'rn-smart-search'),
				'type' => Controls_Manager::COLOR,
				'selectors' => array($wrap . ' .rn-search__hist-remove:hover' => 'color: {{VALUE}};'),
			)
		);

		$this->add_responsive_control(
			'history_remove_size',
			array(
				'label' => __('Remove (×) size', 'rn-smart-search'),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array('px'),
				'range' => array('px' => array('min' => 10, 'max' => 32)),
				'selectors' => array($wrap . ' .rn-search__hist-remove' => 'font-size: {{SIZE}}{{UNIT}};'),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Get JetEngine listings for the "custom result template" select.
	 *
	 * @return array [ ID => title ]
	 */
	protected function get_jet_listings()
	{
		$out = array();
		$listings = get_posts(
			array(
				'post_type' => 'jet-engine',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby' => 'title',
				'order' => 'ASC',
			)
		);
		foreach ($listings as $l) {
			$out[$l->ID] = $l->post_title ? $l->post_title : ('#' . $l->ID);
		}
		return $out;
	}

	/**
	 * Get product categories for the dropdown.
	 *
	 * @return array Array of term objects.
	 */
	protected function get_product_categories($hide_uncategorized = false, $parents_only = false)
	{
		$args = array(
			'taxonomy' => 'product_cat',
			'hide_empty' => true,
			'orderby' => 'name',
			'order' => 'ASC',
		);

		// WooCommerce's default category ("Uncategorized") is stored in this option.
		$default_cat = (int) get_option('default_product_cat', 0);
		if ($hide_uncategorized && $default_cat) {
			$args['exclude'] = array($default_cat);
		}

		if ($parents_only) {
			$args['parent'] = 0;
		}

		$terms = get_terms($args);

		if (is_wp_error($terms)) {
			return array();
		}
		return $terms;
	}

	/**
	 * Render the widget output on the front end.
	 */
	protected function render()
	{
		$settings = $this->get_settings_for_display();

		$placeholder = isset($settings['placeholder']) ? $settings['placeholder'] : '';
		$show_category = 'yes' === ($settings['show_category'] ?? 'yes');
		$show_preview = 'yes' === ($settings['show_preview'] ?? 'yes');
		$show_history = 'yes' === ($settings['show_history'] ?? 'yes');
		$all_label = !empty($settings['all_categories_label']) ? $settings['all_categories_label'] : __('All Categories', 'rn-smart-search');
		$limit = !empty($settings['preview_count']) ? absint($settings['preview_count']) : 5;
		$show_total = 'yes' === ($settings['show_total'] ?? 'yes');
		$total_text = !empty($settings['total_text']) ? $settings['total_text'] : __('%s results found', 'rn-smart-search');
		$show_thumb = 'yes' === ($settings['show_thumbnail'] ?? 'yes');
		$show_price = 'yes' === ($settings['show_price'] ?? 'yes');
		$price_login = 'yes' === ($settings['price_logged_in_only'] ?? 'yes');
		$show_res_cat = 'yes' === ($settings['show_result_category'] ?? '');
		$listing_id = !empty($settings['result_listing_id']) ? absint($settings['result_listing_id']) : 0;
		$live_filter = 'yes' === ($settings['live_filter'] ?? 'yes');
		$empty_enable = 'yes' === ($settings['grid_empty_enable'] ?? '');
		$empty_text = $empty_enable ? (string) ($settings['grid_empty_text'] ?? '') : '';
		$empty_el = $empty_enable ? preg_replace('/[^A-Za-z0-9_\-]/', '', ltrim(trim((string) ($settings['grid_empty_element'] ?? '')), '#')) : '';

		$grid_ids = '';
		if ('yes' === ($settings['grid_live_enable'] ?? '') && !empty($settings['grid_live_ids'])) {
			$grid_ids = implode(',', array_filter(array_map(function ($id) {
				return preg_replace('/[^A-Za-z0-9_\-]/', '', ltrim(trim($id), '#'));
			}, explode(',', $settings['grid_live_ids']))));
		}

		$show_btn_text = 'yes' === ($settings['show_button_text'] ?? '');
		$btn_text = !empty($settings['button_text']) ? $settings['button_text'] : '';
		$icon_pos = ($settings['button_icon_position'] ?? 'right') === 'left' ? 'left' : 'right';
		$has_custom_icon = !empty($settings['button_icon']['value']);
		$button_classes = 'rn-search__button';
		if ($show_btn_text && '' !== $btn_text) {
			$button_classes .= ' rn-search__button--has-text rn-search__button--icon-' . $icon_pos;
		}

		// Current search state from the URL (results page: ?s=...&product_cat=...),
		// so the search box and category dropdown reflect what was searched.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_s = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_cat = isset($_GET['product_cat']) ? sanitize_title(wp_unslash($_GET['product_cat'])) : '';

		$categories = $show_category ? $this->get_product_categories('yes' !== ($settings['show_uncategorized'] ?? ''), 'yes' === ($settings['parent_categories_only'] ?? '')) : array();
		$cat_label = $all_label;
		$found_cat = false;
		foreach ($categories as $term) {
			if ('' !== $current_cat && $term->slug === $current_cat) {
				$cat_label = $term->name;
				$found_cat = true;
				break;
			}
		}
		if (!$found_cat) {
			$current_cat = '';
		}

		$instance_id = 'rn-search-' . $this->get_id();
		?>
		<div class="rn-search" id="<?php echo esc_attr($instance_id); ?>"
			data-preview="<?php echo $show_preview ? '1' : '0'; ?>" data-history="<?php echo $show_history ? '1' : '0'; ?>"
			data-limit="<?php echo esc_attr($limit); ?>" data-show-total="<?php echo $show_total ? '1' : '0'; ?>"
			data-total-text="<?php echo esc_attr($total_text); ?>" data-show-thumb="<?php echo $show_thumb ? '1' : '0'; ?>"
			data-show-price="<?php echo $show_price ? '1' : '0'; ?>" data-price-login="<?php echo $price_login ? '1' : '0'; ?>" data-show-cat="<?php echo $show_res_cat ? '1' : '0'; ?>"
			data-listing="<?php echo esc_attr($listing_id); ?>" data-live="<?php echo $live_filter ? '1' : '0'; ?>" data-grid-ids="<?php echo esc_attr($grid_ids); ?>"<?php if ($empty_enable): ?> data-empty-text="<?php echo esc_attr($empty_text); ?>" data-empty-el="<?php echo esc_attr($empty_el); ?>" data-wid="<?php echo esc_attr($this->get_id()); ?>"<?php endif; ?>>

			<form class="rn-search__bar" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
				<input type="hidden" name="post_type" value="product" />

				<?php if ($show_category): ?>
					<div class="rn-search__cat" data-open="0">
						<input type="hidden" name="product_cat" class="rn-search__cat-value"
							value="<?php echo esc_attr($current_cat); ?>" />
						<button type="button" class="rn-search__cat-trigger" aria-haspopup="listbox" aria-expanded="false">
							<span class="rn-search__cat-label"><?php echo esc_html($cat_label); ?></span>
							<span class="rn-search__chevron" aria-hidden="true">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
									stroke-linecap="round" stroke-linejoin="round">
									<polyline points="6 9 12 15 18 9"></polyline>
								</svg>
							</span>
						</button>
						<div class="rn-search__cat-panel" role="listbox" hidden>
							<button type="button"
								class="rn-search__cat-option<?php echo '' === $current_cat ? ' is-active' : ''; ?>" data-value=""
								role="option"><?php echo esc_html($all_label); ?></button>
							<?php foreach ($categories as $term): ?>
								<button type="button"
									class="rn-search__cat-option<?php echo $term->slug === $current_cat ? ' is-active' : ''; ?>"
									data-value="<?php echo esc_attr($term->slug); ?>"
									role="option"><?php echo esc_html($term->name); ?></button>
							<?php endforeach; ?>
						</div>
						<span class="rn-search__divider" aria-hidden="true"></span>
					</div>
				<?php endif; ?>

				<input type="search" class="rn-search__input" name="s" value="<?php echo esc_attr($current_s); ?>"
					autocomplete="off" placeholder="<?php echo esc_attr($placeholder); ?>"
					aria-label="<?php esc_attr_e('Search products', 'rn-smart-search'); ?>" />

				<button type="submit" class="<?php echo esc_attr($button_classes); ?>"
					aria-label="<?php esc_attr_e('Search', 'rn-smart-search'); ?>">
					<?php if ($show_btn_text && '' !== $btn_text): ?>
						<span class="rn-search__button-text"><?php echo esc_html($btn_text); ?></span>
					<?php endif; ?>
					<span class="rn-search__button-icon" aria-hidden="true">
						<?php
						if ($has_custom_icon && class_exists('\Elementor\Icons_Manager')) {
							\Elementor\Icons_Manager::render_icon($settings['button_icon'], array('aria-hidden' => 'true'));
						} else {
							?>
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
								stroke-linecap="round" stroke-linejoin="round">
								<circle cx="11" cy="11" r="7"></circle>
								<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
							</svg>
							<?php
						}
						?>
					</span>
				</button>
			</form>

			<?php if ($show_preview || $show_history): ?>
				<div class="rn-search__preview" hidden>
					<?php if ($show_history): ?>
						<div class="rn-search__history" hidden>
							<div class="rn-search__assist-head">
								<span><?php echo esc_html($settings['history_title'] ?? __('Recent searches', 'rn-smart-search')); ?></span>
								<button type="button"
									class="rn-search__history-clear"><?php echo esc_html($settings['history_clear_text'] ?? __('Clear', 'rn-smart-search')); ?></button>
							</div>
							<div class="rn-search__history-list"></div>
						</div>
					<?php endif; ?>
					<div class="rn-search__preview-list" role="listbox"></div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * The form action target (shop / search results page).
	 *
	 * @return string
	 */
	protected function get_shop_action()
	{
		if (function_exists('wc_get_page_permalink')) {
			$shop = wc_get_page_permalink('shop');
			if ($shop) {
				return $shop;
			}
		}
		return home_url('/');
	}
}
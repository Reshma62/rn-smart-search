<?php
/**
 * Live search for any JetEngine Listing Grid, targeted by its CSS ID.
 *
 * The header search sends the term + the grid's CSS ID + the Elementor
 * document that holds it; we re-render that one grid with the term applied
 * and return its HTML. Used when the page has no JetSmartFilters group for
 * the grid (with JSF, the term rides along JSF's own request instead).
 *
 * @package RN_Smart_Search
 */

namespace RN_Smart_Search;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class Grid_Search
 */
final class Grid_Search
{

	/**
	 * Singleton instance.
	 *
	 * @var Grid_Search|null
	 */
	private static $instance = null;

	/**
	 * Term applied to the grid being rendered.
	 *
	 * @var string
	 */
	private $term = '';

	/**
	 * Header category slug applied to the grid ('' = all).
	 *
	 * @var string
	 */
	private $category = '';

	/**
	 * Product ids of a browser-side data store (Local Storage / Session
	 * wishlist) sent by the JS; null when the grid isn't such a store.
	 *
	 * @var int[]|null
	 */
	private $front_ids = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Grid_Search
	 */
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		add_action('wp_ajax_rn_smart_search_grid', array($this, 'handle'));
		add_action('wp_ajax_nopriv_rn_smart_search_grid', array($this, 'handle'));
	}

	/**
	 * Render the requested grid with the search term and return its HTML.
	 */
	public function handle()
	{
		check_ajax_referer('rn_smart_search', 'nonce');

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$doc_id = isset($_POST['doc_id']) ? absint($_POST['doc_id']) : 0;
		$grid_id = isset($_POST['grid_id']) ? sanitize_text_field(wp_unslash($_POST['grid_id'])) : '';
		$this->term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
		$this->category = isset($_POST['category']) ? sanitize_title(wp_unslash($_POST['category'])) : '';
		if (!empty($_POST['front_store'])) {
			$raw = isset($_POST['front_ids']) ? sanitize_text_field(wp_unslash($_POST['front_ids'])) : '';
			$this->front_ids = array_values(array_unique(array_filter(array_map('absint', explode(',', $raw)))));
		}
		// phpcs:enable

		if (!$doc_id || '' === $grid_id || !class_exists('\Elementor\Plugin')) {
			wp_send_json_error(array('message' => 'bad request'), 400);
		}

		// Only published documents (or ones the visitor can edit).
		$status = get_post_status($doc_id);
		if ('publish' !== $status && !current_user_can('edit_post', $doc_id)) {
			wp_send_json_error(array('message' => 'not allowed'), 403);
		}

		$elementor = \Elementor\Plugin::$instance;
		$document = $elementor->documents->get($doc_id);
		if (!$document) {
			wp_send_json_error(array('message' => 'document not found'), 404);
		}

		$data = $this->find_grid($document->get_elements_data(), $grid_id);
		if (!$data) {
			wp_send_json_error(array('message' => 'grid not found'), 404);
		}

		// Listing Grid "Posts" source + Query Builder (posts) source.
		add_filter('jet-engine/listing/grid/posts-query-args', array($this, 'add_term'), 99);
		add_filter('jet-engine/query-builder/types/posts-query/args', array($this, 'add_term'), 99);

		$widget = $elementor->elements_manager->create_element_instance($data);
		if (!$widget) {
			wp_send_json_error(array('message' => 'widget not available'), 500);
		}

		ob_start();
		$widget->print_element();
		$html = ob_get_clean();

		remove_filter('jet-engine/listing/grid/posts-query-args', array($this, 'add_term'), 99);
		remove_filter('jet-engine/query-builder/types/posts-query/args', array($this, 'add_term'), 99);

		wp_send_json_success(array('html' => $html));
	}

	/**
	 * Add the search term to the grid's WP_Query args ('' = no search).
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function add_term($args)
	{
		// Browser-side store: JetEngine set post__in = ['is-front', type, slug]
		// (resolved later in the browser). Use the ids the visitor's browser
		// sent instead; published posts only (same rule JetEngine applies).
		if (null !== $this->front_ids) {
			$args['post__in'] = $this->front_ids ? $this->front_ids : array(PHP_INT_MAX);
			$args['post_status'] = 'publish';
			$args['orderby'] = isset($args['orderby']) ? $args['orderby'] : 'post__in';
		}

		if ('' === $this->term) {
			unset($args['s']);
		} else {
			$args['s'] = $this->term;
		}
		if ('' !== $this->category) {
			$args['tax_query'] = isset($args['tax_query']) && is_array($args['tax_query']) ? $args['tax_query'] : array();
			$args['tax_query']['rn_header_cat'] = array(
				'taxonomy' => 'product_cat',
				'field' => 'slug',
				'terms' => array($this->category),
			);
		}
		$args['paged'] = 1;
		return $args;
	}

	/**
	 * Depth-first search for a jet-listing-grid element with this CSS ID.
	 *
	 * @param array  $elements Elementor elements data.
	 * @param string $grid_id  CSS ID.
	 * @return array|null
	 */
	private function find_grid($elements, $grid_id)
	{
		foreach ((array) $elements as $el) {
			if (
				isset($el['widgetType']) && 'jet-listing-grid' === $el['widgetType']
				&& isset($el['settings']['_element_id']) && $el['settings']['_element_id'] === $grid_id
			) {
				return $el;
			}
			if (!empty($el['elements'])) {
				$found = $this->find_grid($el['elements'], $grid_id);
				if ($found) {
					return $found;
				}
			}
		}
		return null;
	}
}

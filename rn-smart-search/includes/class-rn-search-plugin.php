<?php
/**
 * Main plugin class.
 *
 * @package RN_Smart_Search
 */

namespace RN_Smart_Search;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class Plugin
 *
 * Registers the Elementor widget category, the widget itself,
 * the front-end assets and the AJAX search handler.
 */
final class Plugin
{

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Hook everything up.
	 */
	private function __construct()
	{
		// Register assets (registered, enqueued by the widget when used).
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));
		add_action('elementor/editor/after_enqueue_scripts', array($this, 'register_assets'));

		// Register a custom Elementor category so the widget has its own panel section.
		add_action('elementor/elements/categories_registered', array($this, 'register_category'));

		// Register the widget.
		add_action('elementor/widgets/register', array($this, 'register_widgets'));

		// Expose found_posts / per_page on the JetEngine Listing Grid wrapper so
		// the Results Count widget can show "Showing X–Y of Z" (updates on AJAX
		// too). found_posts is already computed by the query, so this is O(1).
		add_filter('jet-engine/listing/container-atts', array($this, 'add_grid_count_atts'), 10, 3);

		// Product search matches the typed phrase in the title / SKU only.
		add_filter('posts_search', array($this, 'title_sku_search'), 20, 2);

		// On a term archive (/product-category/batteries/) let the JSF taxonomy
		// filter REPLACE the archive's own term instead of being AND-ed with it.
		add_action('pre_get_posts', array($this, 'jsf_tax_overrides_archive_term'), 9999);

		// Product categories match only products DIRECTLY in that category
		// (not its subcategories). On/off: WooCommerce → Settings → Products.
		add_action('parse_tax_query', array($this, 'product_cat_direct_only'));

		// Card category label (JetEngine Dynamic Terms): show the category the
		// visitor is browsing/filtering first, not just the first one A–Z.
		add_filter('jet-engine/listings/dynamic-terms/items', array($this, 'current_cat_first'), 10, 2);
		add_action('jet-engine/listing/grid/before-render', array($this, 'shop_grid_render_start'));
		add_action('jet-engine/listing/grid/after-render', array($this, 'shop_grid_render_end'));
		add_filter('woocommerce_get_settings_products', array($this, 'add_direct_cat_setting'), 10, 2);

		// Archive-template grid reloaded with filters in the URL: JSF re-runs
		// the query but keeps the UNFILTERED found_posts/pages for the count
		// and pagination. Refresh them after JSF (priority 20) has run.
		add_filter('jet-engine/listing/grid/posts-query-args', array($this, 'sync_archive_reload_props'), 30, 2);

		// Grid fed from a JetEngine Data Store (e.g. wishlist): JSF stores the
		// grid's default query at priority 0, BEFORE the store adds post__in at
		// priority 10 — so every JSF AJAX request (header search, pagination)
		// searched ALL products. Re-store the defaults with the store's ids.
		add_filter('jet-engine/listing/grid/posts-query-args', array($this, 'keep_data_store_in_jsf_defaults'), 11, 3);

		// Header search on a product archive without a JSF search filter: the
		// JS sends the term as rn_s with JSF's own AJAX request.
		add_filter('jet-smart-filters/query/final-query', array($this, 'apply_header_term_to_jsf'));

		// Make the Listing Grid honour ?s=, ?product_cat= and the category
		// archive being viewed on first page load.
		add_filter('jet-engine/listing/grid/posts-query-args', array($this, 'apply_url_search_to_grid'), 20, 3);

		// AJAX live-search handler (logged-in and guests).
		require_once RN_SMART_SEARCH_PATH . 'includes/class-rn-search-ajax.php';
		Ajax_Search::instance();

		// Live search for any Listing Grid targeted by CSS ID (widget setting).
		require_once RN_SMART_SEARCH_PATH . 'includes/class-rn-grid-search.php';
		Grid_Search::instance();

		// ?rn_debug=1 → console report of the listing queries.
		require_once RN_SMART_SEARCH_PATH . 'includes/class-rn-debug.php';
		Debug::instance();
	}

	/**
	 * Slug of the product category archive currently being viewed
	 * (e.g. /product-category/batteries/ → "batteries"), or '' otherwise.
	 *
	 * @return string
	 */
	public function get_current_archive_cat()
	{
		if (function_exists('is_product_category') && is_product_category()) {
			$obj = get_queried_object();
			if ($obj && !empty($obj->slug)) {
				return sanitize_title($obj->slug);
			}
		}
		return '';
	}

	/**
	 * Register CSS/JS. Enqueue is triggered by the widget's get_*_depends().
	 */
	public function register_assets()
	{
		// Front end: load after the page is parsed (non render-blocking).
		// The editor preview keeps normal loading so widget re-renders hook in.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$defer = !is_admin() && !isset($_GET['elementor-preview']);
		$script_args = $defer ? array('in_footer' => true, 'strategy' => 'defer') : true;

		$css = $this->asset('css/rn-search.css');
		wp_register_style('rn-smart-search', $css['url'], array(), $css['ver']);

		$js = $this->asset('js/rn-search.js');
		wp_register_script('rn-smart-search', $js['url'], array(), $js['ver'], $script_args);

		// Results Count widget script.
		$rc = $this->asset('js/rn-results-count.js');
		wp_register_script('rn-results-count', $rc['url'], array(), $rc['ver'], $script_args);

		wp_localize_script(
			'rn-smart-search',
			'RNSmartSearch',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('rn_smart_search'),
				'shopUrl' => $this->get_shop_url(),
				'currentCat' => $this->get_current_archive_cat(),
				'productArchive' => $this->is_product_listing_page(),
				'shopGridIds' => $this->shop_grid_ids(),
				'i18n' => array(
					'noResults' => __('No products found.', 'rn-smart-search'),
					'searching' => __('Searching…', 'rn-smart-search'),
					'viewAll' => __('View all results', 'rn-smart-search'),
					'allProducts' => __('All Products', 'rn-smart-search'),
				),
			)
		);
	}

	/**
	 * URL + version for an asset: the minified build (*.min.js / *.min.css)
	 * unless debugging (SCRIPT_DEBUG or ?rn_debug=1) or the source is newer
	 * than its build (edited without rebuilding). The version includes the
	 * file time, so browsers / caches pick up every change.
	 *
	 * @param string $rel Path under assets/, e.g. 'js/rn-search.js'.
	 * @return array { url, ver }
	 */
	private function asset($rel)
	{
		$src = RN_SMART_SEARCH_PATH . 'assets/' . $rel;
		$min_rel = preg_replace('/\.(js|css)$/', '.min.$1', $rel);
		$min = RN_SMART_SEARCH_PATH . 'assets/' . $min_rel;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$debug = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) || !empty($_GET['rn_debug']);
		$use_min = !$debug && file_exists($min) && filemtime($min) >= filemtime($src);

		$file = $use_min ? $min_rel : $rel;
		return array(
			'url' => RN_SMART_SEARCH_URL . 'assets/' . $file,
			'ver' => RN_SMART_SEARCH_VERSION . '.' . filemtime(RN_SMART_SEARCH_PATH . 'assets/' . $file),
		);
	}

	/**
	 * Resolve the shop / search results base URL.
	 *
	 * @return string
	 */
	public function get_shop_url()
	{
		if (function_exists('wc_get_page_permalink')) {
			$shop = wc_get_page_permalink('shop');
			if ($shop) {
				return $shop;
			}
		}
		return home_url('/');
	}

	/**
	 * Register the Elementor widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function register_category($elements_manager)
	{
		$elements_manager->add_category(
			'rn-widgets',
			array(
				'title' => __('RN Widgets', 'rn-smart-search'),
				'icon' => 'eicon-search',
			)
		);
	}

	/**
	 * Register the widget with Elementor.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_widgets($widgets_manager)
	{
		require_once RN_SMART_SEARCH_PATH . 'widgets/class-rn-search-widget.php';
		$widgets_manager->register(new Widgets\Search_Widget());

		require_once RN_SMART_SEARCH_PATH . 'widgets/class-rn-results-count-widget.php';
		$widgets_manager->register(new Widgets\Results_Count_Widget());
	}

	/**
	 * Does the WooCommerce product lookup table exist? (checked once per request)
	 *
	 * @return bool
	 */
	private function has_sku_lookup()
	{
		static $has = null;
		if (null === $has) {
			global $wpdb;
			$table = $wpdb->prefix . 'wc_product_meta_lookup';
			$has = ($table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))));
		}
		return $has;
	}

	/**
	 * Product search: match the full typed phrase in the title (or SKU) only,
	 * instead of WP's default word-split search across title + content + excerpt.
	 * Applies to every WP_Query for the "product" post type that has an "s" arg
	 * (main shop search, live preview AJAX, JetSmartFilters search filter).
	 *
	 * @param string    $search   Existing SQL search clause.
	 * @param \WP_Query $wp_query The query.
	 * @return string
	 */
	public function title_sku_search($search, $wp_query)
	{
		global $wpdb;

		// Leave the wp-admin screens alone, but keep AJAX (JSF runs via admin-ajax).
		if (is_admin() && !wp_doing_ajax()) {
			return $search;
		}

		$term = trim((string) $wp_query->get('s'));
		if ('' === $term) {
			return $search;
		}

		// Product query: post_type product, or a WooCommerce product query
		// (archive-template grids send an EMPTY post_type in JSF AJAX but keep
		// wc_query=product_query).
		$is_product = in_array('product', (array) $wp_query->get('post_type'), true)
			|| 'product_query' === $wp_query->get('wc_query');
		if (!$is_product) {
			return $search;
		}

		$like = '%' . $wpdb->esc_like($term) . '%';
		if ($this->has_sku_lookup()) {
			// WooCommerce's product lookup table: one small table with a plain
			// sku column, much cheaper than probing the huge postmeta table.
			$sql = $wpdb->prepare(
				" AND ( {$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.ID IN ( SELECT rn_l.product_id FROM {$wpdb->prefix}wc_product_meta_lookup rn_l WHERE rn_l.sku LIKE %s ) ) ",
				$like,
				$like
			);
		} else {
			$sql = $wpdb->prepare(
				" AND ( {$wpdb->posts}.post_title LIKE %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} rn_pm WHERE rn_pm.post_id = {$wpdb->posts}.ID AND rn_pm.meta_key = '_sku' AND rn_pm.meta_value LIKE %s ) ) ",
				$like,
				$like
			);
		}

		if (!is_user_logged_in()) {
			$sql .= " AND ( {$wpdb->posts}.post_password = '' ) ";
		}

		return $sql;
	}

	/**
	 * On the results page (?post_type=product&s=...&product_cat=...) or on a
	 * product category archive, make the JetEngine Listing Grid honour that
	 * state on first load, not only after a JetSmartFilters AJAX call.
	 * Skipped for AJAX requests (JSF sends its own state).
	 *
	 * @param array  $args     WP_Query args of the grid.
	 * @param object $widget   Listing grid widget (unused).
	 * @param array  $settings Listing grid settings (unused).
	 * @return array
	 */
	public function apply_url_search_to_grid($args, $widget = null, $settings = array())
	{
		if (wp_doing_ajax()) {
			return $args;
		}
		// Only the shop grid(s) — never the mega menu or other listings.
		$grid_id = is_array($settings) && isset($settings['_element_id']) ? (string) $settings['_element_id'] : '';
		if ('' === $grid_id || !in_array($grid_id, $this->shop_grid_ids(), true)) {
			return $args;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$s = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cat = isset($_GET['product_cat']) ? sanitize_title(wp_unslash($_GET['product_cat'])) : '';

		// Category archive (/product-category/batteries/) has no query string.
		if ('' === $cat) {
			$cat = $this->get_current_archive_cat();
		}

		if ('' === $s && '' === $cat) {
			return $args;
		}

		if ('' !== $s && empty($args['s'])) {
			$args['s'] = $s;
		}

		if ('' !== $cat) {
			if (empty($args['tax_query']) || !is_array($args['tax_query'])) {
				$args['tax_query'] = array();
			}
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field' => 'slug',
				'terms' => $cat,
			);
		}

		return $args;
	}

	/**
	 * Shop / product category / product tag / product search page.
	 *
	 * @return bool
	 */
	public function is_product_listing_page()
	{
		if (function_exists('is_shop') && (is_shop() || is_product_taxonomy())) {
			return true;
		}
		return is_search() && 'product' === get_query_var('post_type');
	}

	/**
	 * Apply the header search term (rn_s) to the JetSmartFilters query. Sent
	 * even when empty, so clearing the header search clears the grid search
	 * (the '' also overrides an ?s= stored in the grid's default query).
	 *
	 * @param array $query Final JSF query args.
	 * @return array
	 */
	public function apply_header_term_to_jsf($query)
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (!isset($_REQUEST['rn_s'])) {
			return $query;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$query['s'] = sanitize_text_field(wp_unslash($_REQUEST['rn_s']));

		// Header category for a CSS-ID grid (wishlist etc.): limit to it too.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$cat = isset($_REQUEST['rn_cat']) ? sanitize_title(wp_unslash($_REQUEST['rn_cat'])) : '';
		if ('' !== $cat) {
			// JSF merges its defaults AFTER this filter with array_merge, so a
			// fresh tax_query here would drop the defaults' clauses: start from them.
			if (empty($query['tax_query'])) {
				$defaults = jet_smart_filters()->query->get_default_queries();
				$query['tax_query'] = (is_array($defaults) && !empty($defaults['tax_query']) && is_array($defaults['tax_query']))
					? $defaults['tax_query'] : array();
			}
			$query['tax_query']['rn_header_cat'] = array(
				'taxonomy' => 'product_cat',
				'field' => 'slug',
				'terms' => array($cat),
			);
		}
		return $query;
	}

	/**
	 * Preferred product_cat term ids for this request, most specific first:
	 * categories ticked in JetSmartFilters, then the archive's own category.
	 *
	 * @return int[]
	 */
	private function preferred_cat_ids()
	{
		static $ids = null;
		if (null !== $ids) {
			return $ids;
		}
		$ids = array();

		// phpcs:disable WordPress.Security.NonceVerification
		// JSF AJAX: query[_tax_query_product_cat] = id | [ids].
		if (isset($_REQUEST['query']['_tax_query_product_cat'])) {
			$raw = wp_unslash($_REQUEST['query']['_tax_query_product_cat']);
			foreach ((array) $raw as $v) {
				foreach (explode(',', (string) $v) as $id) {
					$ids[] = absint($id);
				}
			}
		}
		// JSF page reload: ?tax=product_cat:20,18;pa_color:3
		if (!$ids && isset($_GET['tax'])) {
			foreach (explode(';', sanitize_text_field(wp_unslash($_GET['tax']))) as $part) {
				$bits = explode(':', $part, 2);
				if (2 === count($bits) && 'product_cat' === $bits[0]) {
					foreach (explode(',', $bits[1]) as $id) {
						$ids[] = absint($id);
					}
				}
			}
		}
		// Archive term: page load, or JSF AJAX defaults (taxonomy/term).
		$slug = '';
		if (isset($_REQUEST['defaults']['taxonomy'], $_REQUEST['defaults']['term']) && 'product_cat' === $_REQUEST['defaults']['taxonomy']) {
			$slug = sanitize_title(wp_unslash($_REQUEST['defaults']['term']));
		} elseif (!wp_doing_ajax() && function_exists('is_product_category') && is_product_category()) {
			$obj = get_queried_object();
			if ($obj && !empty($obj->term_id)) {
				$ids[] = (int) $obj->term_id;
			}
		}
		// phpcs:enable
		if ('' !== $slug) {
			$term = get_term_by('slug', $slug, 'product_cat');
			if ($term) {
				$ids[] = (int) $term->term_id;
			}
		}

		$ids = array_values(array_unique(array_filter($ids)));
		return $ids;
	}

	/**
	 * Move the preferred categories to the front of a card's term list, so a
	 * product in "Best Sellers, Solarflex" shows "Solarflex" on the Solarflex
	 * page (with one term shown, it looked like the wrong product).
	 *
	 * @param array $terms   Terms.
	 * @param int   $post_id Post id.
	 * @return array
	 */
	public function current_cat_first($terms, $post_id = 0)
	{
		if (!$this->in_shop_grid || !is_array($terms) || count($terms) < 2) {
			return $terms;
		}
		$prefer = $this->preferred_cat_ids();
		if (!$prefer) {
			return $terms;
		}
		$rank = array_flip($prefer);
		$keyed = array();
		foreach (array_values($terms) as $i => $term) {
			$r = (is_object($term) && isset($term->term_id, $rank[$term->term_id]) && 'product_cat' === $term->taxonomy)
				? $rank[$term->term_id] : count($rank);
			$keyed[] = array($r, $i, $term);
		}
		usort($keyed, function ($a, $b) {
			return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0];
		});
		return array_map(function ($k) {
			return $k[2];
		}, $keyed);
	}

	/**
	 * CSS IDs of the shop / archive product grid(s) the header search drives
	 * (WooCommerce → Settings → Products → "Shop grid CSS IDs"). Every other
	 * listing on the page (mega menu, popups, sliders…) is left alone.
	 *
	 * @return string[]
	 */
	public function shop_grid_ids()
	{
		static $ids = null;
		if (null === $ids) {
			$raw = (string) get_option('rn_ss_shop_grid_ids', 'all-products');
			$ids = array_values(array_filter(array_map(function ($id) {
				return preg_replace('/[^A-Za-z0-9_\-]/', '', ltrim(trim($id), '#'));
			}, explode(',', $raw))));
		}
		return $ids;
	}

	/**
	 * Does this query belong to the shop grid (or the page's own product
	 * archive / our preview search)? Mega-menu and other grids return false.
	 *
	 * @param \WP_Query $query Query.
	 * @return bool
	 */
	private function is_shop_scope_query($query)
	{
		if ($query->is_main_query()) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification
		$action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
		if (wp_doing_ajax() && 'rn_smart_search' === $action) {
			return true;
		}
		$provider = (string) $query->get('jet_smart_filters');
		if ('' === $provider) {
			return false;
		}
		$parts = explode('/', $provider);
		return in_array(end($parts), $this->shop_grid_ids(), true);
	}

	/**
	 * True while a shop grid (by CSS ID) is rendering its items.
	 *
	 * @var bool
	 */
	private $in_shop_grid = false;

	/**
	 * @param object $render Listing grid render.
	 */
	public function shop_grid_render_start($render)
	{
		$settings = is_callable(array($render, 'get_settings')) ? (array) $render->get_settings() : array();
		$id = isset($settings['_element_id']) ? (string) $settings['_element_id'] : '';
		$this->in_shop_grid = '' !== $id && in_array($id, $this->shop_grid_ids(), true);
	}

	public function shop_grid_render_end()
	{
		$this->in_shop_grid = false;
	}

	/**
	 * Is "direct products only" on? (default: yes)
	 *
	 * @return bool
	 */
	public function direct_cat_only_enabled()
	{
		return 'no' !== get_option('rn_ss_direct_cat_only', 'yes');
	}

	/**
	 * Every product_cat clause of a front-end query (category archive, JSF
	 * category ticks, sorting/pagination, live search) matches only products
	 * assigned to that exact category — subcategory-only products are left
	 * out. Runs after WP built the tax query, so the archive keeps its
	 * queried term (title, breadcrumbs, Theme Builder conditions).
	 *
	 * @param \WP_Query $query The query.
	 */
	public function product_cat_direct_only($query)
	{
		if ((is_admin() && !wp_doing_ajax()) || !$this->direct_cat_only_enabled()) {
			return;
		}
		if (empty($query->tax_query) || empty($query->tax_query->queries)) {
			return;
		}
		if (!$this->is_shop_scope_query($query)) {
			return;
		}
		$query->tax_query->queries = $this->no_children($query->tax_query->queries);
	}

	/**
	 * Recursively set include_children=false on product_cat clauses.
	 *
	 * @param array $clauses Tax query clauses (may be nested).
	 * @return array
	 */
	private function no_children($clauses)
	{
		foreach ($clauses as $key => $clause) {
			if (!is_array($clause)) {
				continue;
			}
			if (isset($clause['taxonomy'])) {
				if ('product_cat' === $clause['taxonomy']) {
					$clauses[$key]['include_children'] = false;
				}
			} else {
				$clauses[$key] = $this->no_children($clause);
			}
		}
		return $clauses;
	}

	/**
	 * On/off switch in WooCommerce → Settings → Products → General.
	 *
	 * @param array  $settings   Section settings.
	 * @param string $section_id Current section ('' = General).
	 * @return array
	 */
	public function add_direct_cat_setting($settings, $section_id = '')
	{
		if ('' !== $section_id) {
			return $settings;
		}
		$settings[] = array(
			'title' => __('RN Smart Search', 'rn-smart-search'),
			'type' => 'title',
			'id' => 'rn_ss_category_options',
		);
		$settings[] = array(
			'title' => __('Category pages', 'rn-smart-search'),
			'desc' => __('Show only products directly in the category (leave out products that are only in its subcategories). Also applies to the category filters and the header search.', 'rn-smart-search'),
			'id' => 'rn_ss_direct_cat_only',
			'default' => 'yes',
			'type' => 'checkbox',
		);
		$settings[] = array(
			'title' => __('Shop grid CSS IDs', 'rn-smart-search'),
			'desc' => __('Comma-separated CSS IDs of the product Listing Grid(s) on the shop / category / search pages (Listing Grid → Advanced → CSS ID). The header search, category and filters change only these grids — mega menus and other listings are never touched.', 'rn-smart-search'),
			'id' => 'rn_ss_shop_grid_ids',
			'default' => 'all-products',
			'type' => 'text',
			'desc_tip' => false,
		);
		$settings[] = array(
			'title' => __('Fast search requests', 'rn-smart-search'),
			'desc' => __('Header search preview loads only WooCommerce + RN Smart Search for its requests (about 3x faster under load). Turn off if another plugin changes product prices or visibility and the preview must respect it.', 'rn-smart-search'),
			'id' => 'rn_ss_fast_ajax',
			'default' => 'yes',
			'type' => 'checkbox',
		);
		$settings[] = array(
			'type' => 'sectionend',
			'id' => 'rn_ss_category_options',
		);
		return $settings;
	}

	/**
	 * Re-store JSF's default query for a Data Store grid, including post__in.
	 *
	 * @param array  $args     Query args (already limited to the store by JetEngine).
	 * @param object $render   Listing grid render.
	 * @param array  $settings Listing grid settings.
	 * @return array
	 */
	public function keep_data_store_in_jsf_defaults($args, $render = null, $settings = array())
	{
		if (!function_exists('jet_smart_filters') || jet_smart_filters()->query->is_ajax_filter()) {
			return $args;
		}
		if (empty($settings['_element_id']) || empty($settings['posts_query']) || !is_array($settings['posts_query'])) {
			return $args;
		}

		$from_store = false;
		foreach ($settings['posts_query'] as $item) {
			if (!empty($item['posts_from_data_store'])) {
				$from_store = true;
			}
		}
		// Server-side stores (cookies, user meta...): real ids in post__in, or
		// array('no-posts') when the store is EMPTY (must stay empty — not "all
		// products"). Front-end stores ('is-front') are handled by JetEngine.
		if (!$from_store || empty($args['post__in']) || 'is-front' === reset($args['post__in'])) {
			return $args;
		}

		jet_smart_filters()->query->store_provider_default_query(
			'jet-engine',
			$args,
			esc_attr($settings['_element_id']),
			true
		);

		return $args;
	}

	/**
	 * After JSF replaced the global $wp_query for a filtered archive-template
	 * grid (page reload with ?jsf=...), store the new query's totals as the
	 * provider props used by the count and JSF pagination.
	 *
	 * @param array  $args   Query args.
	 * @param object $widget Listing grid widget.
	 * @return array
	 */
	public function sync_archive_reload_props($args, $widget = null)
	{
		if (
			!function_exists('jet_smart_filters') || wp_doing_ajax() || !$widget
			|| !is_callable(array($widget, 'get_settings')) || 'jet-listing-grid' !== $widget->get_name()
		) {
			return $args;
		}

		$settings = $widget->get_settings();
		if (!filter_var($settings['is_archive_template'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
			return $args;
		}

		global $wp_query, $wp_the_query;
		// Only when JSF swapped in its own filtered query.
		if (!$wp_query || $wp_query === $wp_the_query || !$wp_query->get('jet_smart_filters')) {
			return $args;
		}

		$query_id = !empty($settings['_element_id']) ? $settings['_element_id'] : 'default';
		$paged = (int) $wp_query->get('paged');

		jet_smart_filters()->query->set_props(
			'jet-engine',
			array(
				'found_posts' => (int) $wp_query->found_posts,
				'max_num_pages' => (int) $wp_query->max_num_pages,
				'page' => $paged > 0 ? $paged : 1,
			),
			$query_id
		);

		return $args;
	}

	/**
	 * JetSmartFilters adds its taxonomy filter as a tax_query clause keyed by
	 * the taxonomy name (e.g. 'product_cat'), while a listing on a term archive
	 * also carries the archive term as a numeric clause. Both are AND-ed, so
	 * ticking Batteries + Brakes + Engine Parts on the Batteries archive still
	 * returned Batteries only (and Brakes alone returned 0). When JSF filters a
	 * taxonomy, drop the archive's clause for that same taxonomy.
	 *
	 * Also runs on the MAIN query: a Listing Grid set to "Use as Archive
	 * Template" shows the main query's posts on page load, and JSF adds its
	 * clause there. The archive's queried object is cached first, so the
	 * page keeps its identity (title, breadcrumbs, Theme Builder condition).
	 *
	 * @param \WP_Query $query The query.
	 */
	public function jsf_tax_overrides_archive_term($query)
	{
		if ($query->is_main_query()) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (is_admin() || (!$query->get('jet_smart_filters') && !isset($_GET['jsf']))) {
				return;
			}
		} elseif (!$query->get('jet_smart_filters') || !$this->is_shop_scope_query($query)) {
			return;
		}

		$tax_query = $query->get('tax_query');
		if (!is_array($tax_query)) {
			return;
		}

		// Taxonomies JSF is filtering (string-keyed clauses).
		$filtered = array();
		foreach ($tax_query as $key => $clause) {
			if (is_string($key) && 'relation' !== $key && is_array($clause) && !empty($clause['taxonomy'])) {
				$filtered[$clause['taxonomy']] = true;
			}
		}
		if (!$filtered) {
			return;
		}

		// Lock in the archive's queried object (e.g. Solarflex) before its
		// query var is removed below.
		if ($query->is_main_query()) {
			foreach (array_keys($filtered) as $taxonomy) {
				$slug = $query->get($taxonomy);
				$term = is_string($slug) && '' !== $slug ? get_term_by('slug', $slug, $taxonomy) : false;
				if ($term) {
					$query->queried_object = $term;
					$query->queried_object_id = (int) $term->term_id;
					break;
				}
			}
		}

		$changed = false;
		foreach ($tax_query as $key => $clause) {
			if (is_int($key) && is_array($clause) && !empty($clause['taxonomy']) && isset($filtered[$clause['taxonomy']])) {
				unset($tax_query[$key]);
				$changed = true;
			}
		}

		foreach (array_keys($filtered) as $taxonomy) {
			if ($query->get($taxonomy)) {
				$query->set($taxonomy, '');
				$changed = true;
			}
			// Generic form: ?taxonomy=product_cat&term=solarflex.
			if ($query->get('taxonomy') === $taxonomy) {
				$query->set('taxonomy', '');
				$query->set('term', '');
				$changed = true;
			}
		}

		if ($changed) {
			$query->set('tax_query', $tax_query);
		}
	}

	/**
	 * Add data-found-posts / data-per-page / data-max-pages / data-current-page
	 * to the JetEngine Listing Grid items wrapper (for the Results Count widget).
	 * Runs only when JetEngine fires this filter, so it is inherently safe.
	 *
	 * @param array  $attrs    Existing container attributes.
	 * @param array  $settings Listing grid settings.
	 * @param object $render   The listing grid render instance.
	 * @return array
	 */
	public function add_grid_count_atts($attrs, $settings, $render)
	{
		$request = (isset($render->query_vars['request']) && is_array($render->query_vars['request']))
			? $render->query_vars['request']
			: array();

		$query_id = !empty($settings['_element_id']) ? $settings['_element_id'] : 'default';

		$found = null;
		$pages = isset($render->query_vars['pages']) ? (int) $render->query_vars['pages'] : null;

		// Preferred source: JetSmartFilters' stored props hold the FILTERED
		// found_posts for this provider/query (correct on first load and every
		// AJAX filter/search/page change).
		if (function_exists('jet_smart_filters')) {
			$props = jet_smart_filters()->query->get_query_props('jet-engine', $query_id);
			if (isset($props['found_posts'])) {
				$found = (int) $props['found_posts'];
			}
			if (isset($props['max_num_pages'])) {
				$pages = (int) $props['max_num_pages'];
			}
		}

		// Fallback: the render's default (archive) query.
		if (null === $found && isset($request['found_posts'])) {
			$found = (int) $request['found_posts'];
		}

		$per = 0;
		if (isset($request['posts_per_page']) && (int) $request['posts_per_page'] > 0) {
			$per = (int) $request['posts_per_page'];
		} elseif (!empty($settings['posts_num'])) {
			$per = (int) $settings['posts_num'];
		}

		$page = isset($render->query_vars['page']) ? (int) $render->query_vars['page'] : 1;

		if (null !== $found) {
			$attrs[] = 'data-found-posts="' . esc_attr($found) . '"';
		}
		if ($per > 0) {
			$attrs[] = 'data-per-page="' . esc_attr($per) . '"';
		}
		if (null !== $pages) {
			$attrs[] = 'data-max-pages="' . esc_attr($pages) . '"';
		}
		$attrs[] = 'data-current-page="' . esc_attr($page) . '"';

		return $attrs;
	}
}
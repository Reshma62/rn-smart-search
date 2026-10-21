<?php
/**
 * Debug mode (?rn_debug=1): report what the product listing queries really
 * returned, so the browser console can show the selected categories, the
 * product count per category and every product with its categories.
 *
 * Page load → printed as window.RNSearchDebug in the footer.
 * JetSmartFilters AJAX → added to JSF's JSON response as `rn_debug`.
 *
 * @package RN_Smart_Search
 */

namespace RN_Smart_Search;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class Debug
 */
final class Debug
{

	/**
	 * Singleton instance.
	 *
	 * @var Debug|null
	 */
	private static $instance = null;

	/**
	 * Recorded queries.
	 *
	 * @var array
	 */
	private $records = array();

	/**
	 * Re-entrancy guard for the per-category count queries.
	 *
	 * @var bool
	 */
	private $counting = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return Debug
	 */
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Is debug mode on for this request?
	 *
	 * @return bool
	 */
	public static function enabled()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return !empty($_REQUEST['rn_debug']);
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		if (!self::enabled()) {
			return;
		}
		add_filter('the_posts', array($this, 'record'), 999, 2);
		add_action('wp_footer', array($this, 'print_page_data'), 1);
		add_filter('jet-smart-filters/render/ajax/data', array($this, 'add_to_jsf_response'));
	}

	/**
	 * Record every product listing query (not the page's main query).
	 *
	 * @param \WP_Post[] $posts Posts.
	 * @param \WP_Query  $query Query.
	 * @return \WP_Post[]
	 */
	public function record($posts, $query)
	{
		if ($this->counting) {
			return $posts;
		}
		$is_main = $query->is_main_query();
		if ($is_main) {
			// Main query of a product archive (grids set to "Use as Archive
			// Template" show exactly these posts).
			if (!$query->is_post_type_archive('product') && !$query->is_tax(get_object_taxonomies('product'))) {
				return $posts;
			}
		} elseif (!in_array('product', (array) $query->get('post_type'), true)) {
			return $posts;
		}

		$tax_query = $query->get('tax_query');
		$products = array();
		foreach ((array) $posts as $post) {
			$post = get_post($post);
			if (!$post) {
				continue;
			}
			$products[] = array(
				'id' => $post->ID,
				'name' => html_entity_decode(get_the_title($post), ENT_QUOTES),
				'categories' => implode(', ', wp_get_post_terms($post->ID, 'product_cat', array('fields' => 'names'))),
			);
		}

		$this->records[] = array(
			'source' => ($is_main ? 'MAIN QUERY ' : '') . ($query->get('jet_smart_filters') ? $query->get('jet_smart_filters') : 'listing'),
			'search' => (string) $query->get('s'),
			'tax_query' => $this->describe_tax_query($tax_query),
			'found_posts' => (int) $query->found_posts,
			'per_category' => $this->count_per_category($query, $tax_query),
			'products' => $products,
		);

		return $posts;
	}

	/**
	 * Readable tax_query: [{ taxonomy, terms: [names], operator }].
	 *
	 * @param mixed $tax_query Tax query.
	 * @return array
	 */
	private function describe_tax_query($tax_query)
	{
		$out = array();
		foreach ((array) $tax_query as $key => $clause) {
			if (!is_array($clause) || empty($clause['taxonomy'])) {
				continue;
			}
			$field = isset($clause['field']) ? $clause['field'] : 'term_id';
			$names = array();
			foreach ((array) $clause['terms'] as $t) {
				$term = get_term_by('term_taxonomy_id' === $field ? 'term_taxonomy_id' : $field, $t, $clause['taxonomy']);
				$names[] = $term ? $term->name . ' (' . $term->term_id . ')' : (string) $t;
			}
			$out[] = array(
				'key' => (string) $key,
				'taxonomy' => $clause['taxonomy'],
				'terms' => $names,
				'operator' => isset($clause['operator']) ? $clause['operator'] : 'IN',
			);
		}
		return $out;
	}

	/**
	 * For each product_cat term the query filters on: how many of the
	 * query's results are in that term (all pages, not just this one).
	 *
	 * @param \WP_Query $query     Query.
	 * @param mixed     $tax_query Tax query.
	 * @return array term name => count
	 */
	private function count_per_category($query, $tax_query)
	{
		$terms = array();
		foreach ((array) $tax_query as $clause) {
			if (!is_array($clause) || empty($clause['taxonomy']) || 'product_cat' !== $clause['taxonomy']) {
				continue;
			}
			$field = isset($clause['field']) ? $clause['field'] : 'term_id';
			foreach ((array) $clause['terms'] as $t) {
				$term = get_term_by($field, $t, 'product_cat');
				if ($term) {
					$terms[$term->term_id] = $term;
				}
			}
		}
		if (!$terms) {
			return array();
		}

		$this->counting = true;
		$counts = array();
		foreach ($terms as $term) {
			$args = $query->query_vars;
			// Not a JSF query (keeps JSF / archive-override hooks away) and a
			// string key, so jsf_tax_overrides_archive_term() can't drop it.
			unset($args['jet_smart_filters']);
			$args['tax_query'] = is_array($tax_query) ? $tax_query : array();
			$args['tax_query']['rn_debug_cat'] = array(
				'taxonomy' => 'product_cat',
				'field' => 'term_id',
				'terms' => array($term->term_id),
			);
			$args['fields'] = 'ids';
			$args['posts_per_page'] = 1;
			$args['paged'] = 1;
			$args['no_found_rows'] = false;
			$q = new \WP_Query($args);
			$counts[$term->name] = (int) $q->found_posts;
		}
		$this->counting = false;

		return $counts;
	}

	/**
	 * Page load: expose the records to the JS.
	 */
	public function print_page_data()
	{
		echo '<script>window.RNSearchDebug = ' . wp_json_encode($this->records) . ';</script>' . "\n";
	}

	/**
	 * JSF AJAX: add the records to its JSON response.
	 *
	 * @param array $data JSF response.
	 * @return array
	 */
	public function add_to_jsf_response($data)
	{
		$data['rn_debug'] = $this->records;
		return $data;
	}
}

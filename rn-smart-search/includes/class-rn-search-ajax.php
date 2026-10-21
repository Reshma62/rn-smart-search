<?php
/**
 * AJAX live search handler.
 *
 * @package RN_Smart_Search
 */

namespace RN_Smart_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ajax_Search
 *
 * Returns a small set of matching WooCommerce products for the live
 * preview panel below the search bar.
 */
final class Ajax_Search {

	/**
	 * Singleton instance.
	 *
	 * @var Ajax_Search|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Ajax_Search
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wp_ajax_rn_smart_search', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_rn_smart_search', array( $this, 'handle' ) );

		// Any product / category change invalidates every cached search result.
		foreach ( array( 'save_post_product', 'woocommerce_update_product', 'woocommerce_new_product', 'woocommerce_product_set_stock_status', 'woocommerce_product_set_visibility', 'woocommerce_scheduled_sales', 'edited_product_cat', 'delete_product_cat', 'created_product_cat' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush_cache' ) );
		}
		add_action( 'set_object_terms', array( $this, 'maybe_flush_on_terms' ), 10, 4 );
		foreach ( array( 'trashed_post', 'untrashed_post', 'deleted_post' ) as $hook ) {
			add_action( $hook, array( $this, 'maybe_flush_on_post' ) );
		}
		add_action( 'update_option_rn_ss_direct_cat_only', array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Cached result sets live under a version number; bumping it makes every
	 * old entry unreachable at once (they then expire on their own).
	 */
	public static function flush_cache() {
		update_option( 'rn_ss_cache_ver', (string) microtime( true ), true );
	}

	/**
	 * Flush when a product's categories / visibility change.
	 *
	 * @param int    $object_id Object id.
	 * @param array  $terms     Terms.
	 * @param array  $tt_ids    Term taxonomy ids.
	 * @param string $taxonomy  Taxonomy.
	 */
	public function maybe_flush_on_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'product_cat', 'product_visibility' ), true ) ) {
			self::flush_cache();
		}
	}

	/**
	 * Flush when a product is trashed / restored / deleted.
	 *
	 * @param int $post_id Post id.
	 */
	public function maybe_flush_on_post( $post_id ) {
		if ( 'product' === get_post_type( $post_id ) ) {
			self::flush_cache();
		}
	}

	/**
	 * Handle the live search request and return JSON product data.
	 */
	public function handle() {
		$t_start = microtime( true );
		check_ajax_referer( 'rn_smart_search', 'nonce' );

		$term    = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$cat     = isset( $_GET['category'] ) ? sanitize_title( wp_unslash( $_GET['category'] ) ) : '';
		$limit   = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 5;
		$limit   = max( 1, min( 12, $limit ) );
		$listing = isset( $_GET['listing'] ) ? absint( $_GET['listing'] ) : 0;

		// Widget setting "Show price only to logged-in users" (default on):
		// guests get no price unless the widget sends guest_price=1. Hiding is
		// the default so an old cached copy of the JS can't leak prices.
		$hide_price = ! is_user_logged_in() && empty( $_GET['guest_price'] );

		// Should each result be rendered through a JetEngine Listing template?
		$use_listing = ( $listing > 0 && function_exists( 'jet_engine' )
			&& isset( jet_engine()->frontend )
			&& method_exists( jet_engine()->frontend, 'get_listing_item' ) );

		if ( $use_listing ) {
			jet_engine()->frontend->set_listing( $listing );
		}

		if ( '' === $term || mb_strlen( $term ) < 2 ) {
			wp_send_json_success( array( 'products' => array() ) );
		}

		// 1) Matching ids + total: cached per (term, category, limit). Prices,
		//    images and listing HTML are built fresh below, so the guest /
		//    logged-in price rule and template changes are always respected.
		$cache_key = 'rn_ss_' . md5( wp_json_encode( array( get_option( 'rn_ss_cache_ver', '1' ), get_option( 'rn_ss_direct_cat_only', 'yes' ), is_user_logged_in(), mb_strtolower( $term ), $cat, $limit ) ) );
		$cached    = mb_strlen( $term ) <= 60 ? get_transient( $cache_key ) : false;
		$cache_hit = is_array( $cached );

		if ( $cache_hit ) {
			$ids   = array_map( 'absint', (array) $cached['ids'] );
			$total = (int) $cached['total'];
		} else {
			$args = array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				's'                      => $term,
				'ignore_sticky_posts'    => true,
				'fields'                 => 'ids',
				// Compute found_posts so we can show the TOTAL number of matches.
				'no_found_rows'          => false,
				// Caches are primed in bulk below, only for the ids we return.
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			);

			// Restrict to the chosen category (empty = all categories).
			if ( '' !== $cat ) {
				$args['tax_query'] = array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => $cat,
					),
				);
			}

			// Respect WooCommerce search visibility.
			$args['tax_query'][] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-search' ),
				'operator' => 'NOT IN',
			);

			$query = new \WP_Query( $args );
			$ids   = array_map( 'absint', $query->posts );
			$total = (int) $query->found_posts;

			if ( mb_strlen( $term ) <= 60 ) {
				set_transient( $cache_key, array( 'ids' => $ids, 'total' => $total ), 10 * MINUTE_IN_SECONDS );
			}
		}
		$t_query = microtime( true );

		// 2) Load posts, meta and terms for all results in 3 queries instead of
		//    several per product.
		$products = array();
		if ( $ids ) {
			_prime_post_caches( $ids, true, true );
			$thumb_ids = array();
			foreach ( $ids as $id ) {
				$thumb_ids[] = (int) get_post_thumbnail_id( $id );
			}
			$thumb_ids = array_filter( $thumb_ids );
			if ( $thumb_ids ) {
				_prime_post_caches( $thumb_ids, false, true );
			}
		}

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}

			$item = array(
				'id'       => $product->get_id(),
				'title'    => $product->get_name(),
				'url'      => get_permalink( $product->get_id() ),
				'price'    => $hide_price ? '' : $product->get_price_html(),
				'image'    => $this->get_thumb( $product ),
				'category' => $this->get_primary_category( $product->get_id() ),
			);

			// Render the product through the chosen JetEngine Listing template.
			// get_listing_item() may also echo Elementor inline CSS, so we
			// capture everything in a buffer to keep the JSON response clean.
			if ( $use_listing ) {
				ob_start();
				$rendered = jet_engine()->frontend->get_listing_item( get_post( $id ) );
				$echoed   = ob_get_clean();
				$item['html'] = $echoed . $rendered;
			}

			$products[] = $item;
		}
		$t_items = microtime( true );

		// Visible in the browser: DevTools → Network → the request → Timing.
		if ( ! headers_sent() ) {
			header(
				sprintf(
					'Server-Timing: rn-query;desc="%s";dur=%.1f, rn-items;dur=%.1f, rn-total;dur=%.1f',
					$cache_hit ? 'cache HIT' : 'cache MISS',
					( $t_query - $t_start ) * 1000,
					( $t_items - $t_query ) * 1000,
					( microtime( true ) - $t_start ) * 1000
				)
			);
			header( 'X-RN-Cache: ' . ( $cache_hit ? 'HIT' : 'MISS' ) );
		}

		wp_send_json_success(
			array(
				'products' => $products,
				'total'    => $total,
			)
		);
	}

	/**
	 * Get the product's primary category name (plain text).
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private function get_primary_category( $product_id ) {
		$terms = get_the_terms( $product_id, 'product_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$first = reset( $terms );
			return $first->name;
		}
		return '';
	}

	/**
	 * Get a product thumbnail URL, falling back to the WooCommerce placeholder.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string
	 */
	private function get_thumb( $product ) {
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$src = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
			if ( $src ) {
				return $src;
			}
		}
		return wc_placeholder_img_src( 'woocommerce_thumbnail' );
	}
}

<?php
/**
 * Plugin Name: RN Smart Search – Fast AJAX
 * Description: For RN Smart Search preview requests only, loads just WooCommerce + RN Smart Search (skips Elementor, JetEngine, JetSmartFilters, …) so live search answers much faster. Every other request is untouched.
 * Version: 1.0.0
 *
 * Installed automatically by the RN Smart Search plugin; safe to delete.
 */

if (!defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification
if (
	defined('DOING_AJAX') && DOING_AJAX
	&& isset($_REQUEST['action']) && 'rn_smart_search' === $_REQUEST['action']
	// A JetEngine listing template needs Elementor + JetEngine → full load.
	&& empty($_REQUEST['listing'])
	&& !defined('RN_SS_FAST_AJAX_DISABLE')
) {
	$rn_ss_keep = apply_filters('rn_smart_search_fast_ajax_plugins', array(
		'woocommerce/woocommerce.php',
		'rn-smart-search/rn-smart-search.php',
	));
	add_filter('option_active_plugins', function ($plugins) use ($rn_ss_keep) {
		return array_values(array_intersect((array) $plugins, $rn_ss_keep));
	}, 1);
	// Multisite network-activated plugins.
	add_filter('site_option_active_sitewide_plugins', function ($plugins) use ($rn_ss_keep) {
		return array_intersect_key((array) $plugins, array_flip($rn_ss_keep));
	}, 1);
	define('RN_SS_FAST_AJAX', true);
}

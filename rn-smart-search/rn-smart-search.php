<?php
/**
 * Plugin Name: RN Smart Search
 * Description: A WooCommerce product search widget for Elementor — category-aware search with a live preview panel. Search button goes to the shop, clicking a preview product opens its single page.
 * Version:     1.26.1
 * Author:      Reshma Khanam
 * Author URI:  https://devreshmakhanam.com
 * Plugin URI:  https://github.com/Reshma62/rn-smart-search
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rn-smart-search
 * Requires Plugins: elementor, woocommerce
 *
 * @package RN_Smart_Search
 */

if (!defined('ABSPATH')) {
	exit; // No direct access.
}

define('RN_SMART_SEARCH_VERSION', '1.26.1');
define('RN_SMART_SEARCH_FILE', __FILE__);
define('RN_SMART_SEARCH_PATH', plugin_dir_path(__FILE__));
define('RN_SMART_SEARCH_URL', plugin_dir_url(__FILE__));

/**
 * Bootstrap the plugin once all plugins are loaded, so we can check
 * for Elementor and WooCommerce being present.
 */
function rn_smart_search_init()
{
	// Elementor must be active — except for a preview-search request served by
	// the "Fast AJAX" mu-plugin, which deliberately loads only WooCommerce +
	// this plugin (the AJAX handler doesn't need Elementor).
	if (!did_action('elementor/loaded') && !defined('RN_SS_FAST_AJAX')) {
		add_action('admin_notices', 'rn_smart_search_missing_elementor_notice');
		return;
	}

	// WooCommerce must be active.
	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', 'rn_smart_search_missing_woocommerce_notice');
		return;
	}

	require_once RN_SMART_SEARCH_PATH . 'includes/class-rn-search-plugin.php';
	\RN_Smart_Search\Plugin::instance();
}
add_action('plugins_loaded', 'rn_smart_search_init');

/**
 * "Fast AJAX" mu-plugin: preview-search requests load only WooCommerce + this
 * plugin instead of every plugin (Elementor, JetEngine, …), so they answer
 * ~3x faster under load. Installed/updated automatically from the bundled copy;
 * removed when switched off (WooCommerce → Settings → Products) or on deactivation.
 */
function rn_smart_search_mu_target()
{
	return trailingslashit(WPMU_PLUGIN_DIR) . 'rn-search-fast-ajax.php';
}

function rn_smart_search_sync_mu_plugin()
{
	$target = rn_smart_search_mu_target();
	$source = RN_SMART_SEARCH_PATH . 'mu-plugin/rn-search-fast-ajax.php';
	$wanted = 'no' !== get_option('rn_ss_fast_ajax', 'yes');

	if (!$wanted) {
		if (file_exists($target)) {
			wp_delete_file($target);
		}
		return;
	}
	if (file_exists($target) && md5_file($target) === md5_file($source)) {
		return;
	}
	if (!is_dir(WPMU_PLUGIN_DIR)) {
		wp_mkdir_p(WPMU_PLUGIN_DIR);
	}
	if (is_dir(WPMU_PLUGIN_DIR) && wp_is_writable(WPMU_PLUGIN_DIR)) {
		copy($source, $target); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
add_action('admin_init', 'rn_smart_search_sync_mu_plugin');
add_action('update_option_rn_ss_fast_ajax', 'rn_smart_search_sync_mu_plugin');
add_action('add_option_rn_ss_fast_ajax', 'rn_smart_search_sync_mu_plugin');
register_activation_hook(__FILE__, 'rn_smart_search_sync_mu_plugin');

register_deactivation_hook(__FILE__, function () {
	$target = rn_smart_search_mu_target();
	if (file_exists($target)) {
		wp_delete_file($target);
	}
});

/**
 * Admin notice: Elementor missing.
 */
function rn_smart_search_missing_elementor_notice()
{
	echo '<div class="notice notice-warning is-dismissible"><p>';
	esc_html_e('RN Smart Search requires Elementor to be installed and activated.', 'rn-smart-search');
	echo '</p></div>';
}

/**
 * Admin notice: WooCommerce missing.
 */
function rn_smart_search_missing_woocommerce_notice()
{
	echo '<div class="notice notice-warning is-dismissible"><p>';
	esc_html_e('RN Smart Search requires WooCommerce to be installed and activated.', 'rn-smart-search');
	echo '</p></div>';
}

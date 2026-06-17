<?php
/**
 * Plugin Name: PAT Product Tabs for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/pat-product-tabs/
 * Description: Add unique, per-product custom tabs to WooCommerce product pages.
 * Version: 1.2.1
 * Author: Price Action Tools
 * Author URI: https://profiles.wordpress.org/laith3/
 * License: GPL-2.0-or-later
 * Text Domain: pat-product-tabs
*/

if (!defined('ABSPATH')) {
    exit;
}

define('PAT_PRODUCT_TABS_VERSION', '1.2.1');
define('PAT_PRODUCT_TABS_FILE', __FILE__);
define('PAT_PRODUCT_TABS_PATH', plugin_dir_path(__FILE__));
define('PAT_PRODUCT_TABS_URL', plugin_dir_url(__FILE__));

require_once PAT_PRODUCT_TABS_PATH . 'includes/class-pat-product-tabs.php';

add_action('plugins_loaded', static function () {
    PAT_Product_Tabs::instance()->boot();
});

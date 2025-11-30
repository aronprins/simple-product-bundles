<?php
/**
 * Plugin Name: Simple Product Bundles for WooCommerce
 * Description: Create flexible product bundles with configurable quantities, volume discounts, and bundle-wide pricing for your WooCommerce store.
 * Version: 1.0.0
 * Author: Aron & Sharon
 * Author URI: https://aronandsharon.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-product-bundles
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SIMPLE_PRODUCT_BUNDLES_VERSION', '1.0.0');
define('SIMPLE_PRODUCT_BUNDLES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIMPLE_PRODUCT_BUNDLES_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class Simple_Product_Bundles {
    
    /**
     * Plugin instance
     *
     * @var Simple_Product_Bundles
     */
    private static $instance = null;
    
    /**
     * Admin instance
     *
     * @var Simple_Product_Bundles_Admin
     */
    public $admin;
    
    /**
     * Frontend instance
     *
     * @var Simple_Product_Bundles_Frontend
     */
    public $frontend;
    
    /**
     * Cart instance
     *
     * @var Simple_Product_Bundles_Cart
     */
    public $cart;
    
    /**
     * AJAX instance
     *
     * @var Simple_Product_Bundles_Ajax
     */
    public $ajax;
    
    /**
     * Get plugin instance
     *
     * @return Simple_Product_Bundles
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-simple-product-bundles-admin.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-simple-product-bundles-frontend.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-simple-product-bundles-cart.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-simple-product-bundles-ajax.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init() {
        $this->admin = new Simple_Product_Bundles_Admin();
        $this->frontend = new Simple_Product_Bundles_Frontend();
        $this->cart = new Simple_Product_Bundles_Cart();
        $this->ajax = new Simple_Product_Bundles_Ajax();
    }
}

/**
 * Declare HPOS compatibility (must be before plugins_loaded)
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', function() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Load plugin text domain for translations (must be before initializing classes)
    load_plugin_textdomain('simple-product-bundles', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    Simple_Product_Bundles::get_instance();
});

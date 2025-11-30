<?php
/**
 * AJAX functionality for Simple Product Bundles
 *
 * @package Simple_Product_Bundles
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_Product_Bundles_Ajax {

    /**
     * Constructor
     */
    public function __construct() {
        // AJAX for price calculation
        add_action('wp_ajax_calculate_bundle_price', [$this, 'ajax_calculate_bundle_price']);
        add_action('wp_ajax_nopriv_calculate_bundle_price', [$this, 'ajax_calculate_bundle_price']);
    }

    /**
     * AJAX handler for bundle price calculation
     */
    public function ajax_calculate_bundle_price() {
        // For future AJAX calculations if needed
        wp_die();
    }
}


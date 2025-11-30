<?php
/**
 * Cart and Order functionality for Simple Product Bundles
 *
 * @package Simple_Product_Bundles
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_Product_Bundles_Cart {

    /**
     * Constructor
     */
    public function __construct() {
        // Frontend: Add to cart validation
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_bundle_add_to_cart'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_bundle_cart_item_data'], 10, 3);
        
        // Cart display
        add_filter('woocommerce_get_item_data', [$this, 'display_bundle_cart_item_data'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_bundle_order_item_meta'], 10, 4);
        
        // Price calculation
        add_filter('woocommerce_product_get_price', [$this, 'get_bundle_price'], 10, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'get_bundle_price'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'calculate_bundle_cart_totals'], 10, 1);
    }

    /**
     * Validate bundle add to cart
     *
     * @param bool $passed     Validation passed
     * @param int  $product_id Product ID
     * @param int  $quantity   Quantity
     * @return bool
     */
    public function validate_bundle_add_to_cart($passed, $product_id, $quantity) {
        $product = wc_get_product($product_id);
        
        if (!$product || $product->get_type() !== 'bundle') {
            return $passed;
        }
        
        $bundle_items = get_post_meta($product_id, '_bundle_items', true);
        
        if (empty($bundle_items) || !is_array($bundle_items)) {
            wc_add_notice(__('This bundle has no products configured.', 'simple-product-bundles'), 'error');
            return false;
        }
        
        if (!isset($_POST['bundle_qty']) || !is_array($_POST['bundle_qty'])) {
            wc_add_notice(__('Please configure bundle quantities.', 'simple-product-bundles'), 'error');
            return false;
        }
        
        foreach ($bundle_items as $item) {
            $bundled_product = wc_get_product($item['product_id']);
            if (!$bundled_product) continue;
            
            $qty = isset($_POST['bundle_qty'][$item['product_id']]) ? intval($_POST['bundle_qty'][$item['product_id']]) : 0;
            $min_qty = intval($item['min_qty']);
            $max_qty = intval($item['max_qty']);
            
            // Check minimum quantity
            if ($qty < $min_qty) {
                wc_add_notice(
                    sprintf(
                        __('Quantity for "%s" must be at least %d.', 'simple-product-bundles'),
                        $bundled_product->get_name(),
                        $min_qty
                    ),
                    'error'
                );
                return false;
            }
            
            // Check maximum quantity (0 = unlimited)
            if ($max_qty > 0 && $qty > $max_qty) {
                wc_add_notice(
                    sprintf(
                        __('Quantity for "%s" cannot exceed %d.', 'simple-product-bundles'),
                        $bundled_product->get_name(),
                        $max_qty
                    ),
                    'error'
                );
                return false;
            }
        }
        
        return $passed;
    }

    /**
     * Add bundle cart item data
     *
     * @param array $cart_item_data Cart item data
     * @param int   $product_id     Product ID
     * @param int   $variation_id   Variation ID
     * @return array
     */
    public function add_bundle_cart_item_data($cart_item_data, $product_id, $variation_id) {
        $product = wc_get_product($product_id);
        
        if (!$product || $product->get_type() !== 'bundle') {
            return $cart_item_data;
        }
        
        if (isset($_POST['bundle_qty']) && is_array($_POST['bundle_qty'])) {
            $cart_item_data['bundle_configuration'] = [];
            $bundle_items = get_post_meta($product_id, '_bundle_items', true);
            
            $bundle_total = 0;
            
            foreach ($bundle_items as $item) {
                $qty = isset($_POST['bundle_qty'][$item['product_id']]) ? intval($_POST['bundle_qty'][$item['product_id']]) : 0;
                if ($qty > 0) {
                    $cart_item_data['bundle_configuration'][$item['product_id']] = $qty;
                    
                    // Calculate price
                    $bundled_product = wc_get_product($item['product_id']);
                    if ($bundled_product) {
                        $bundle_total += floatval($bundled_product->get_price()) * $qty;
                    }
                }
            }
            
            $cart_item_data['bundle_discount'] = isset($_POST['bundle_discount']) ? floatval($_POST['bundle_discount']) : 0;
            
            // Apply discount
            if ($cart_item_data['bundle_discount'] > 0) {
                $bundle_total = $bundle_total * (1 - ($cart_item_data['bundle_discount'] / 100));
            }
            
            $cart_item_data['bundle_price'] = $bundle_total;
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
        
        return $cart_item_data;
    }

    /**
     * Display bundle cart item data
     *
     * @param array $item_data Item data
     * @param array $cart_item Cart item
     * @return array
     */
    public function display_bundle_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['bundle_configuration']) && !empty($cart_item['bundle_configuration'])) {
            $items_display = [];
            foreach ($cart_item['bundle_configuration'] as $product_id => $qty) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $items_display[] = $product->get_name() . ' × ' . $qty;
                }
            }
            
            $item_data[] = [
                'key'   => __('Bundle Items', 'simple-product-bundles'),
                'value' => implode(', ', $items_display),
            ];
        }
        
        return $item_data;
    }

    /**
     * Add bundle order item meta
     *
     * @param WC_Order_Item_Product $item         Order item
     * @param string                 $cart_item_key Cart item key
     * @param array                  $values       Cart item values
     * @param WC_Order               $order        Order
     */
    public function add_bundle_order_item_meta($item, $cart_item_key, $values, $order) {
        if (isset($values['bundle_configuration']) && !empty($values['bundle_configuration'])) {
            $items_display = [];
            foreach ($values['bundle_configuration'] as $product_id => $qty) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $items_display[] = $product->get_name() . ' × ' . $qty;
                }
            }
            
            $item->add_meta_data(__('Bundle Items', 'simple-product-bundles'), implode(', ', $items_display));
        }
    }

    /**
     * Get bundle price
     *
     * @param float      $price   Price
     * @param WC_Product $product Product
     * @return float
     */
    public function get_bundle_price($price, $product) {
        if ($product->get_type() !== 'bundle') {
            return $price;
        }
        
        // Don't modify price during cart calculations - let calculate_bundle_cart_totals handle it
        if (doing_action('woocommerce_before_calculate_totals') || did_action('woocommerce_before_calculate_totals')) {
            return $price;
        }
        
        // Return calculated minimum bundle price for display on product page
        $bundle_items = get_post_meta($product->get_id(), '_bundle_items', true);
        $discount = floatval(get_post_meta($product->get_id(), '_bundle_discount', true));
        
        if (empty($bundle_items) || !is_array($bundle_items)) {
            return $price;
        }
        
        $total = 0;
        foreach ($bundle_items as $item) {
            $bundled_product = wc_get_product($item['product_id']);
            if (!$bundled_product) continue;
            
            $min_qty = intval($item['min_qty']);
            $total += $bundled_product->get_price() * $min_qty;
        }
        
        if ($discount > 0) {
            $total = $total * (1 - ($discount / 100));
        }
        
        return $total;
    }

    /**
     * Calculate bundle cart totals
     *
     * @param WC_Cart $cart Cart object
     */
    public function calculate_bundle_cart_totals($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['bundle_price'])) {
                $cart_item['data']->set_price(floatval($cart_item['bundle_price']));
            }
        }
    }
}


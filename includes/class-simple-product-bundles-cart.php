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
        
        // Verify nonce
        if (!isset($_POST['bundle_cart_nonce']) || !wp_verify_nonce($_POST['bundle_cart_nonce'], 'add_bundle_to_cart')) {
            wc_add_notice(__('Security check failed. Please try again.', 'simple-product-bundles'), 'error');
            return false;
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
                
                // Sanitize product ID and quantity
                $product_id_key = absint($item['product_id']);
                $qty = isset($_POST['bundle_qty'][$product_id_key]) ? absint($_POST['bundle_qty'][$product_id_key]) : 0;
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
     * Get applicable volume discount for a product quantity
     *
     * @param array $volume_discounts Volume discount tiers
     * @param int   $qty              Quantity
     * @return array Array with 'discount' value and 'type' (percentage|fixed)
     */
    private function get_volume_discount($volume_discounts, $qty) {
        $result = [
            'discount' => 0,
            'type'     => 'percentage',
        ];
        
        if (empty($volume_discounts) || !is_array($volume_discounts) || $qty <= 0) {
            return $result;
        }
        
        // Volume discounts should be sorted by min_qty ascending
        foreach ($volume_discounts as $tier) {
            $tier_min_qty = isset($tier['min_qty']) ? intval($tier['min_qty']) : 0;
            $tier_discount = isset($tier['discount']) ? floatval($tier['discount']) : 0;
            $tier_type = isset($tier['discount_type']) ? $tier['discount_type'] : 'percentage';
            
            if ($qty >= $tier_min_qty) {
                $result['discount'] = $tier_discount;
                $result['type'] = $tier_type;
            }
        }
        
        return $result;
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
            $cart_item_data['bundle_volume_discounts'] = [];
            $bundle_items = get_post_meta($product_id, '_bundle_items', true);
            
            $bundle_total = 0;
            $total_volume_savings = 0;
            
            foreach ($bundle_items as $item) {
                // Sanitize product ID and quantity
                $product_id_key = absint($item['product_id']);
                $qty = isset($_POST['bundle_qty'][$product_id_key]) ? absint($_POST['bundle_qty'][$product_id_key]) : 0;
                if ($qty > 0) {
                    $cart_item_data['bundle_configuration'][$product_id_key] = $qty;
                    
                    // Calculate price with volume discount
                    $bundled_product = wc_get_product($item['product_id']);
                    if ($bundled_product) {
                        $item_price = floatval($bundled_product->get_price());
                        $item_subtotal = $item_price * $qty;
                        
                        // Apply volume discount for this item
                        $volume_discounts = isset($item['volume_discounts']) ? $item['volume_discounts'] : [];
                        $volume_discount_data = $this->get_volume_discount($volume_discounts, $qty);
                        $volume_discount_value = $volume_discount_data['discount'];
                        $volume_discount_type = $volume_discount_data['type'];
                        
                        if ($volume_discount_value > 0) {
                            // Calculate discount amount based on type
                            if ($volume_discount_type === 'fixed') {
                                // Fixed discount per item
                                $volume_discount_amount = $volume_discount_value * $qty;
                            } else {
                                // Percentage discount
                                $volume_discount_amount = $item_subtotal * ($volume_discount_value / 100);
                            }
                            
                            $item_subtotal = $item_subtotal - $volume_discount_amount;
                            $total_volume_savings += $volume_discount_amount;
                            
                            // Store volume discount info
                            $cart_item_data['bundle_volume_discounts'][$product_id_key] = [
                                'discount_value' => $volume_discount_value,
                                'discount_type'  => $volume_discount_type,
                                'discount_amount' => $volume_discount_amount,
                            ];
                        }
                        
                        $bundle_total += $item_subtotal;
                    }
                }
            }
            
            // Sanitize bundle discount
            $cart_item_data['bundle_discount'] = isset($_POST['bundle_discount']) ? floatval(sanitize_text_field($_POST['bundle_discount'])) : 0;
            $cart_item_data['bundle_discount'] = max(0, min(100, $cart_item_data['bundle_discount'])); // Clamp between 0 and 100
            $cart_item_data['bundle_volume_savings'] = $total_volume_savings;
            
            // Apply bundle discount (after volume discounts)
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
            $volume_discounts = isset($cart_item['bundle_volume_discounts']) ? $cart_item['bundle_volume_discounts'] : [];
            
            foreach ($cart_item['bundle_configuration'] as $product_id => $qty) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $item_text = $product->get_name() . ' × ' . $qty;
                    
                    // Add volume discount indicator
                    if (isset($volume_discounts[$product_id]) && $volume_discounts[$product_id]['discount_value'] > 0) {
                        $discount_type = $volume_discounts[$product_id]['discount_type'];
                        $discount_value = $volume_discounts[$product_id]['discount_value'];
                        
                        if ($discount_type === 'fixed') {
                            $item_text .= ' (' . wp_kses_post(wc_price($discount_value)) . ' ' . esc_html__('off each', 'simple-product-bundles') . ')';
                        } else {
                            $item_text .= ' (' . esc_html($discount_value) . '% ' . esc_html__('off', 'simple-product-bundles') . ')';
                        }
                    }
                    
                    $items_display[] = $item_text;
                }
            }
            
            $item_data[] = [
                'key'   => __('Bundle Items', 'simple-product-bundles'),
                'value' => esc_html(implode(', ', $items_display)),
            ];
            
            // Show total volume savings if any
            if (isset($cart_item['bundle_volume_savings']) && $cart_item['bundle_volume_savings'] > 0) {
                $item_data[] = [
                    'key'   => __('Volume Savings', 'simple-product-bundles'),
                    'value' => wc_price($cart_item['bundle_volume_savings']),
                ];
            }
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
            $volume_discounts = isset($values['bundle_volume_discounts']) ? $values['bundle_volume_discounts'] : [];
            
            foreach ($values['bundle_configuration'] as $product_id => $qty) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $item_text = $product->get_name() . ' × ' . $qty;
                    
                    // Add volume discount indicator
                    if (isset($volume_discounts[$product_id]) && $volume_discounts[$product_id]['discount_value'] > 0) {
                        $discount_type = $volume_discounts[$product_id]['discount_type'];
                        $discount_value = $volume_discounts[$product_id]['discount_value'];
                        
                        if ($discount_type === 'fixed') {
                            $item_text .= ' (' . wp_kses_post(wc_price($discount_value)) . ' ' . esc_html__('off each', 'simple-product-bundles') . ')';
                        } else {
                            $item_text .= ' (' . esc_html($discount_value) . '% ' . esc_html__('off', 'simple-product-bundles') . ')';
                        }
                    }
                    
                    $items_display[] = $item_text;
                }
            }
            
            $item->add_meta_data(__('Bundle Items', 'simple-product-bundles'), esc_html(implode(', ', $items_display)));
            
            // Add volume savings if any
            if (isset($values['bundle_volume_savings']) && $values['bundle_volume_savings'] > 0) {
                $item->add_meta_data(__('Volume Savings', 'simple-product-bundles'), wp_kses_post(wc_price($values['bundle_volume_savings'])));
            }
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
            $item_total = $bundled_product->get_price() * $min_qty;
            
            // Apply volume discount if applicable
            $volume_discounts = isset($item['volume_discounts']) ? $item['volume_discounts'] : [];
            $volume_discount_data = $this->get_volume_discount($volume_discounts, $min_qty);
            if ($volume_discount_data['discount'] > 0) {
                if ($volume_discount_data['type'] === 'fixed') {
                    $item_total = $item_total - ($volume_discount_data['discount'] * $min_qty);
                } else {
                    $item_total = $item_total * (1 - ($volume_discount_data['discount'] / 100));
                }
            }
            
            $total += $item_total;
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


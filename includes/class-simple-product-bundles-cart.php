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
     * Store tax breakdown for display
     *
     * @var array
     */
    private static $bundle_tax_breakdown = [];

    /**
     * Track processed cart items in current calculation cycle to prevent double-counting
     *
     * @var array
     */
    private static $processed_cart_items = [];

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
        
        // Cart item price and subtotal display (high priority to run after other filters)
        add_filter('woocommerce_cart_item_price', [$this, 'filter_bundle_cart_item_price'], 999, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'filter_bundle_cart_item_subtotal'], 999, 3);
        
        // Price calculation
        add_filter('woocommerce_product_get_price', [$this, 'get_bundle_price'], 10, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'get_bundle_price'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'calculate_bundle_cart_totals'], 10, 1);
        
        // Custom tax calculation for bundles - calculate tax per bundled product
        add_filter('woocommerce_calculate_item_totals_taxes', [$this, 'calculate_bundle_item_taxes'], 10, 3);
        
        // Custom tax display - collapsible with breakdown
        // Hide individual tax rows and show combined row instead
        add_filter('woocommerce_cart_tax_totals', [$this, 'modify_cart_tax_totals'], 10, 2);
        add_action('woocommerce_cart_totals_before_order_total', [$this, 'output_tax_breakdown_html'], 10);
        add_action('woocommerce_review_order_before_order_total', [$this, 'output_tax_breakdown_html'], 10);
        
        // For WooCommerce Blocks cart - output tax data as JSON for JS to render
        add_action('wp_footer', [$this, 'output_tax_breakdown_data_for_js'], 10);
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
            $cart_item_data['bundle_tax_data'] = []; // Store tax info per bundled product
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
                        
                        // Store tax class for this bundled product
                        $cart_item_data['bundle_tax_data'][$product_id_key] = [
                            'tax_class' => $bundled_product->get_tax_class(),
                            'price'     => $item_subtotal, // Price after volume discount, before bundle discount
                            'qty'       => $qty,
                        ];
                        
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
                $discount_multiplier = 1 - ($cart_item_data['bundle_discount'] / 100);
                $bundle_total = $bundle_total * $discount_multiplier;
                
                // Also apply bundle discount to each item's tax data price
                foreach ($cart_item_data['bundle_tax_data'] as $pid => &$tax_data) {
                    $tax_data['price'] = $tax_data['price'] * $discount_multiplier;
                }
                unset($tax_data); // Break reference
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
                'value' => wp_kses_post(implode(', ', $items_display)),
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
            
            $item->add_meta_data(__('Bundle Items', 'simple-product-bundles'), wp_kses_post(implode(', ', $items_display)));
            
            // Add volume savings if any
            if (isset($values['bundle_volume_savings']) && $values['bundle_volume_savings'] > 0) {
                $item->add_meta_data(__('Volume Savings', 'simple-product-bundles'), wp_kses_post(wc_price($values['bundle_volume_savings'])));
            }
            
            // Store the tax breakdown for reference
            if (isset($values['bundle_tax_data']) && !empty($values['bundle_tax_data'])) {
                $item->add_meta_data('_bundle_tax_data', $values['bundle_tax_data'], true);
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
        
        // Prevent infinite recursion during this specific call
        static $running = false;
        if ($running) {
            return;
        }
        $running = true;
        
        // Clear tax breakdown and processed items for fresh calculation
        self::$bundle_tax_breakdown = [];
        self::$processed_cart_items = [];
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['bundle_price'])) {
                $cart_item['data']->set_price(floatval($cart_item['bundle_price']));
            }
        }
        
        $running = false;
    }

    /**
     * Calculate taxes for bundle items based on each bundled product's tax class
     *
     * This is the key method that ensures each bundled product's tax class is respected.
     * Instead of using a single tax class for the whole bundle, we calculate tax for
     * each bundled product individually and sum them up.
     *
     * @param array         $taxes       The calculated taxes
     * @param object        $item        The cart item object (stdClass with key, product, quantity, etc.)
     * @param WC_Cart_Totals $cart_totals The cart totals instance
     * @return array Modified taxes
     */
    public function calculate_bundle_item_taxes($taxes, $item, $cart_totals) {
        // Get the cart item key from the item object
        if (!isset($item->key)) {
            return $taxes;
        }
        
        // Safety: if breakdown is empty but we have processed items, we're in a new calculation cycle
        // Clear processed items to start fresh (handles edge cases where calculate_totals() is called directly)
        if (empty(self::$bundle_tax_breakdown) && !empty(self::$processed_cart_items)) {
            self::$processed_cart_items = [];
        }
        
        // Prevent double-counting: if we've already processed this cart item in this calculation cycle, skip it
        // This prevents exponential tax accumulation when calculate_totals() is called multiple times
        if (isset(self::$processed_cart_items[$item->key])) {
            return $taxes;
        }
        
        // Get the actual cart item data from the cart
        $cart = WC()->cart;
        if (!$cart) {
            return $taxes;
        }
        
        $cart_contents = $cart->get_cart();
        if (!isset($cart_contents[$item->key])) {
            return $taxes;
        }
        
        $cart_item = $cart_contents[$item->key];
        
        // Only process bundle items - check for bundle_configuration
        if (!isset($cart_item['bundle_configuration']) || empty($cart_item['bundle_configuration'])) {
            return $taxes;
        }
        
        // Mark this cart item as processed to prevent double-counting
        self::$processed_cart_items[$item->key] = true;
        
        // Get the bundle product to retrieve bundle items config
        $bundle_product_id = $cart_item['product_id'];
        $bundle_items_config = get_post_meta($bundle_product_id, '_bundle_items', true);
        
        if (empty($bundle_items_config) || !is_array($bundle_items_config)) {
            return $taxes;
        }
        
        // Create a lookup for bundle item settings by product ID
        $bundle_items_by_product = [];
        foreach ($bundle_items_config as $bundle_item) {
            $pid = absint($bundle_item['product_id']);
            $bundle_items_by_product[$pid] = $bundle_item;
        }
        
        // Get the customer's tax location
        $tax_location = WC()->customer ? WC()->customer->get_taxable_address() : [];
        if (empty($tax_location)) {
            return $taxes;
        }
        
        list($country, $state, $postcode, $city) = array_pad($tax_location, 4, '');
        
        // Get bundle discount
        $bundle_discount = isset($cart_item['bundle_discount']) ? floatval($cart_item['bundle_discount']) : 0;
        $discount_multiplier = $bundle_discount > 0 ? (1 - ($bundle_discount / 100)) : 1;
        
        // Calculate taxes for each bundled product based on its tax class
        $combined_taxes = [];
        $bundle_qty = isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 1;
        
        foreach ($cart_item['bundle_configuration'] as $product_id => $qty) {
            $product_id = absint($product_id);
            $qty = absint($qty);
            
            if ($qty <= 0) {
                continue;
            }
            
            // Get the bundled product
            $bundled_product = wc_get_product($product_id);
            if (!$bundled_product) {
                continue;
            }
            
            // Get the product's tax class
            $tax_class = $bundled_product->get_tax_class();
            
            // Calculate item price (unit price × quantity)
            $unit_price = floatval($bundled_product->get_price());
            $item_subtotal = $unit_price * $qty;
            
            // Apply volume discount if applicable
            if (isset($bundle_items_by_product[$product_id])) {
                $item_config = $bundle_items_by_product[$product_id];
                $volume_discounts = isset($item_config['volume_discounts']) ? $item_config['volume_discounts'] : [];
                $volume_discount_data = $this->get_volume_discount($volume_discounts, $qty);
                
                if ($volume_discount_data['discount'] > 0) {
                    if ($volume_discount_data['type'] === 'fixed') {
                        // Fixed discount per item
                        $item_subtotal = $item_subtotal - ($volume_discount_data['discount'] * $qty);
                    } else {
                        // Percentage discount
                        $item_subtotal = $item_subtotal * (1 - ($volume_discount_data['discount'] / 100));
                    }
                }
            }
            
            // Apply bundle discount
            $item_subtotal = $item_subtotal * $discount_multiplier;
            
            // Multiply by bundle quantity (if buying multiple bundles)
            $total_item_price = $item_subtotal * $bundle_qty;
            
            // Get tax rates for this product's tax class
            $tax_rates = WC_Tax::find_rates([
                'country'   => $country,
                'state'     => $state,
                'postcode'  => $postcode,
                'city'      => $city,
                'tax_class' => $tax_class,
            ]);
            
            if (!empty($tax_rates)) {
                // Calculate taxes for this item
                // Use WooCommerce precision (internally works in cents) for accurate calculation
                $price_in_precision = wc_add_number_precision($total_item_price);
                $item_taxes = WC_Tax::calc_tax($price_in_precision, $tax_rates, wc_prices_include_tax());
                
                // Merge into combined taxes (keep in precision format as WooCommerce expects)
                foreach ($item_taxes as $rate_id => $tax_amount) {
                    if (isset($combined_taxes[$rate_id])) {
                        $combined_taxes[$rate_id] += $tax_amount;
                    } else {
                        $combined_taxes[$rate_id] = $tax_amount;
                    }
                    
                    // Store breakdown for display (convert back from precision)
                    $tax_amount_display = wc_remove_number_precision($tax_amount);
                    $rate_info = reset($tax_rates); // Get first rate info
                    $rate_label = isset($rate_info['label']) ? $rate_info['label'] : __('Tax', 'simple-product-bundles');
                    $rate_percentage = isset($rate_info['rate']) ? $rate_info['rate'] : 0;
                    
                    // Aggregate by product_id (same product in different bundles should be aggregated)
                    // But prevent double-counting by tracking processed cart items
                    if (!isset(self::$bundle_tax_breakdown[$product_id])) {
                        self::$bundle_tax_breakdown[$product_id] = [
                            'product_name' => $bundled_product->get_name(),
                            'subtotal'     => $total_item_price,
                            'tax_amount'   => $tax_amount_display,
                            'tax_label'    => $rate_label,
                            'tax_rate'     => $rate_percentage,
                        ];
                    } else {
                        // Aggregate: same product in multiple bundles or multiple quantities
                        self::$bundle_tax_breakdown[$product_id]['tax_amount'] += $tax_amount_display;
                        self::$bundle_tax_breakdown[$product_id]['subtotal'] += $total_item_price;
                    }
                }
            }
        }
        
        // If we calculated any taxes, return them (in WooCommerce precision format)
        if (!empty($combined_taxes)) {
            return $combined_taxes;
        }
        
        return $taxes;
    }
    
    /**
     * Modify cart tax totals to hide individual rates when we have bundle breakdown
     *
     * @param array   $tax_totals Tax totals array
     * @param WC_Cart $cart       Cart object
     * @return array Modified tax totals (empty if we're showing our custom display)
     */
    public function modify_cart_tax_totals($tax_totals, $cart) {
        // Only modify if we have bundle tax breakdown data
        if (!empty(self::$bundle_tax_breakdown)) {
            // Return empty array to hide default tax rows
            return [];
        }
        
        return $tax_totals;
    }
    
    /**
     * Output the tax breakdown HTML before the order total (classic cart)
     */
    public function output_tax_breakdown_html() {
        // Recalculate tax breakdown if not already done
        if (empty(self::$bundle_tax_breakdown) && WC()->cart) {
            $this->recalculate_bundle_tax_breakdown();
        }
        
        // Only output if we have bundle tax breakdown data
        if (empty(self::$bundle_tax_breakdown)) {
            return;
        }
        
        // Calculate total tax
        $total_tax = 0;
        foreach (self::$bundle_tax_breakdown as $breakdown) {
            $total_tax += $breakdown['tax_amount'];
        }
        
        ?>
        <tr class="bundle-tax-row tax-total">
            <th><?php esc_html_e('BTW', 'simple-product-bundles'); ?></th>
            <td data-title="<?php esc_attr_e('BTW', 'simple-product-bundles'); ?>">
                <div class="bundle-tax-collapsible">
                    <button type="button" class="bundle-tax-toggle" aria-expanded="false">
                        <span class="bundle-tax-total"><?php echo wp_kses_post(wc_price($total_tax)); ?></span>
                        <span class="bundle-tax-toggle-icon" aria-hidden="true">▼</span>
                    </button>
                    <div class="bundle-tax-breakdown" style="display: none;">
                        <ul class="bundle-tax-breakdown-list">
                            <?php foreach (self::$bundle_tax_breakdown as $product_id => $breakdown) : ?>
                                <li class="bundle-tax-breakdown-item">
                                    <span class="breakdown-product"><?php echo esc_html($breakdown['product_name']); ?></span>
                                    <span class="breakdown-details">
                                        <span class="breakdown-subtotal"><?php echo wp_kses_post(wc_price($breakdown['subtotal'])); ?></span>
                                        <span class="breakdown-rate">@ <?php echo esc_html($breakdown['tax_rate']); ?>%</span>
                                        <span class="breakdown-tax"><?php echo wp_kses_post(wc_price($breakdown['tax_amount'])); ?></span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Output tax breakdown data as JSON for JavaScript (WooCommerce Blocks cart)
     */
    public function output_tax_breakdown_data_for_js() {
        // Only on cart or checkout pages
        if (!is_cart() && !is_checkout()) {
            return;
        }
        
        // Recalculate tax breakdown if not already done
        if (empty(self::$bundle_tax_breakdown) && WC()->cart) {
            $this->recalculate_bundle_tax_breakdown();
        }
        
        // Only output if we have bundle tax breakdown data
        if (empty(self::$bundle_tax_breakdown)) {
            return;
        }
        
        // Calculate total tax
        $total_tax = 0;
        foreach (self::$bundle_tax_breakdown as $breakdown) {
            $total_tax += $breakdown['tax_amount'];
        }
        
        $data = [
            'total_tax'       => $total_tax,
            'total_tax_html'  => wp_kses_post(wc_price($total_tax)),
            'breakdown'       => array_values(self::$bundle_tax_breakdown),
            'labels'          => [
                'btw'  => __('BTW', 'simple-product-bundles'),
            ],
        ];
        
        // Format prices in breakdown
        foreach ($data['breakdown'] as &$item) {
            $item['subtotal_html'] = wp_kses_post(wc_price($item['subtotal']));
            $item['tax_amount_html'] = wp_kses_post(wc_price($item['tax_amount']));
        }
        
        ?>
        <script type="text/javascript">
            var simpleBundleTaxData = <?php echo wp_json_encode($data); ?>;
        </script>
        <?php
    }
    
    /**
     * Recalculate bundle tax breakdown from cart
     */
    private function recalculate_bundle_tax_breakdown() {
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }
        
        self::$bundle_tax_breakdown = [];
        
        // Get the customer's tax location
        $tax_location = WC()->customer ? WC()->customer->get_taxable_address() : [];
        if (empty($tax_location)) {
            return;
        }
        
        list($country, $state, $postcode, $city) = array_pad($tax_location, 4, '');
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Only process bundle items
            if (!isset($cart_item['bundle_configuration']) || empty($cart_item['bundle_configuration'])) {
                continue;
            }
            
            $bundle_product_id = $cart_item['product_id'];
            $bundle_items_config = get_post_meta($bundle_product_id, '_bundle_items', true);
            
            if (empty($bundle_items_config) || !is_array($bundle_items_config)) {
                continue;
            }
            
            // Create lookup
            $bundle_items_by_product = [];
            foreach ($bundle_items_config as $bundle_item) {
                $pid = absint($bundle_item['product_id']);
                $bundle_items_by_product[$pid] = $bundle_item;
            }
            
            $bundle_discount = isset($cart_item['bundle_discount']) ? floatval($cart_item['bundle_discount']) : 0;
            $discount_multiplier = $bundle_discount > 0 ? (1 - ($bundle_discount / 100)) : 1;
            $bundle_qty = isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 1;
            
            foreach ($cart_item['bundle_configuration'] as $product_id => $qty) {
                $product_id = absint($product_id);
                $qty = absint($qty);
                
                if ($qty <= 0) continue;
                
                $bundled_product = wc_get_product($product_id);
                if (!$bundled_product) continue;
                
                $tax_class = $bundled_product->get_tax_class();
                $unit_price = floatval($bundled_product->get_price());
                $item_subtotal = $unit_price * $qty;
                
                // Apply volume discount
                if (isset($bundle_items_by_product[$product_id])) {
                    $item_config = $bundle_items_by_product[$product_id];
                    $volume_discounts = isset($item_config['volume_discounts']) ? $item_config['volume_discounts'] : [];
                    $volume_discount_data = $this->get_volume_discount($volume_discounts, $qty);
                    
                    if ($volume_discount_data['discount'] > 0) {
                        if ($volume_discount_data['type'] === 'fixed') {
                            $item_subtotal = $item_subtotal - ($volume_discount_data['discount'] * $qty);
                        } else {
                            $item_subtotal = $item_subtotal * (1 - ($volume_discount_data['discount'] / 100));
                        }
                    }
                }
                
                // Apply bundle discount
                $item_subtotal = $item_subtotal * $discount_multiplier;
                $total_item_price = $item_subtotal * $bundle_qty;
                
                // Get tax rates
                $tax_rates = WC_Tax::find_rates([
                    'country'   => $country,
                    'state'     => $state,
                    'postcode'  => $postcode,
                    'city'      => $city,
                    'tax_class' => $tax_class,
                ]);
                
                if (!empty($tax_rates)) {
                    $price_in_precision = wc_add_number_precision($total_item_price);
                    $item_taxes = WC_Tax::calc_tax($price_in_precision, $tax_rates, wc_prices_include_tax());
                    $tax_amount = wc_remove_number_precision(array_sum($item_taxes));
                    
                    $rate_info = reset($tax_rates);
                    $rate_label = isset($rate_info['label']) ? $rate_info['label'] : __('Tax', 'simple-product-bundles');
                    $rate_percentage = isset($rate_info['rate']) ? $rate_info['rate'] : 0;
                    
                    self::$bundle_tax_breakdown[$product_id] = [
                        'product_name' => $bundled_product->get_name(),
                        'subtotal'     => $total_item_price,
                        'tax_amount'   => $tax_amount,
                        'tax_label'    => $rate_label,
                        'tax_rate'     => $rate_percentage,
                    ];
                }
            }
        }
    }

    /**
     * Filter the cart item price display to show the correct bundle price
     *
     * @param string $price_html    Price HTML
     * @param array  $cart_item     Cart item data
     * @param string $cart_item_key Cart item key
     * @return string
     */
    public function filter_bundle_cart_item_price($price_html, $cart_item, $cart_item_key) {
        // Check if this is a bundle by looking for bundle_configuration (more reliable than product type)
        if (!isset($cart_item['bundle_price']) || !isset($cart_item['bundle_configuration'])) {
            return $price_html;
        }
        
        $bundle_price = floatval($cart_item['bundle_price']);
        
        return wc_price($bundle_price);
    }

    /**
     * Filter the cart item subtotal display to show the correct bundle subtotal
     *
     * @param string $subtotal_html Subtotal HTML
     * @param array  $cart_item     Cart item data
     * @param string $cart_item_key Cart item key
     * @return string
     */
    public function filter_bundle_cart_item_subtotal($subtotal_html, $cart_item, $cart_item_key) {
        // Check if this is a bundle by looking for bundle_configuration (more reliable than product type)
        if (!isset($cart_item['bundle_price']) || !isset($cart_item['bundle_configuration'])) {
            return $subtotal_html;
        }
        
        $quantity = isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 1;
        $bundle_price = floatval($cart_item['bundle_price']);
        $subtotal = $bundle_price * $quantity;
        
        return wc_price($subtotal);
    }
}

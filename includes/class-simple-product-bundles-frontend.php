<?php
/**
 * Frontend functionality for Simple Product Bundles
 *
 * @package Simple_Product_Bundles
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_Product_Bundles_Frontend {

    /**
     * Constructor
     */
    public function __construct() {
        // Frontend: Replace add to cart form for bundle products (priority 35 = after short description)
        add_action('woocommerce_single_product_summary', [$this, 'bundle_add_to_cart_template'], 35);
        
        // Remove default add to cart for bundle products
        add_action('woocommerce_single_product_summary', [$this, 'remove_default_add_to_cart'], 1);

        // Frontend scripts
        add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
    }
    
    /**
     * Remove default add to cart button and price for bundle products
     */
    public function remove_default_add_to_cart() {
        global $product;
        
        if ($product && $product->get_type() === 'bundle') {
            // Remove default add to cart button
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
            // Remove default price display (bundle shows total in the summary section)
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
        }
    }

    /**
     * Display the add to cart template for bundle products
     */
    public function bundle_add_to_cart_template() {
        global $product;
        
        if (!$product || $product->get_type() !== 'bundle') {
            return;
        }
        
        $bundle_items = get_post_meta($product->get_id(), '_bundle_items', true);
        
        if (empty($bundle_items) || !is_array($bundle_items)) {
            echo '<p>' . esc_html__('This bundle has no products configured.', 'simple-product-bundles') . '</p>';
            return;
        }
        
        // Check if bundle is purchasable
        if (!$product->is_purchasable()) {
            return;
        }
        
        echo '<form class="cart bundle-cart-form" action="' . esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())) . '" method="post" enctype="multipart/form-data">';
        
        // Add nonce field for security
        wp_nonce_field('add_bundle_to_cart', 'bundle_cart_nonce');
        
        $this->display_bundle_options();
        
        echo '<div class="bundle-add-to-cart">';
        
        // Check if bundle quantity selector is enabled
        $enable_bundle_qty = get_post_meta($product->get_id(), '_bundle_enable_qty', true);
        if ($enable_bundle_qty === 'yes') {
            woocommerce_quantity_input([
                'min_value'   => 1,
                'max_value'   => $product->get_max_purchase_quantity(),
                'input_value' => 1,
            ]);
        }
        
        echo '<button type="submit" name="add-to-cart" value="' . esc_attr($product->get_id()) . '" class="single_add_to_cart_button button alt wp-element-button">' . esc_html($product->single_add_to_cart_text()) . '</button>';
        
        echo '</div>';
        echo '</form>';
    }

    /**
     * Display bundle options
     */
    public function display_bundle_options() {
        global $product;
        
        if (!$product || $product->get_type() !== 'bundle') {
            return;
        }
        
        $bundle_items = get_post_meta($product->get_id(), '_bundle_items', true);
        $discount = floatval(get_post_meta($product->get_id(), '_bundle_discount', true));
        $hide_images = get_post_meta($product->get_id(), '_bundle_hide_images', true) === 'yes';
        $price_suffix = get_post_meta($product->get_id(), '_bundle_price_suffix', true);
        $volume_label = get_post_meta($product->get_id(), '_bundle_volume_label', true);
        
        if (empty($bundle_items) || !is_array($bundle_items)) {
            echo '<p class="bundle-empty-message">' . esc_html__('This bundle has no products configured.', 'simple-product-bundles') . '</p>';
            return;
        }
        
        $wrapper_class = 'bundle-items-wrapper';
        if ($hide_images) {
            $wrapper_class .= ' bundle-hide-images';
        }
        echo '<div class="' . esc_attr($wrapper_class) . '">';
        echo '<h3 class="bundle-heading">' . esc_html__('Bundle Contents', 'simple-product-bundles') . '</h3>';
        
        echo '<div class="bundle-items-list">';
        
        foreach ($bundle_items as $index => $item) {
            $bundled_product = wc_get_product($item['product_id']);
            if (!$bundled_product) continue;
            
            $price = $bundled_product->get_price();
            $min_qty = intval($item['min_qty']);
            $max_qty = intval($item['max_qty']); // 0 = unlimited
            $default_qty = intval($item['default_qty']);
            $volume_discounts = isset($item['volume_discounts']) ? $item['volume_discounts'] : [];
            
            // Ensure default is within range (if max is 0/unlimited, only check min)
            $default_qty = max($min_qty, $default_qty);
            if ($max_qty > 0) {
                $default_qty = min($max_qty, $default_qty);
            }
            
            $thumb_id = $bundled_product->get_image_id();
            
            // Build volume discounts data attribute
            $volume_discounts_json = !empty($volume_discounts) ? wp_json_encode($volume_discounts) : '[]';
            
            echo '<div class="bundle-item" data-product-id="' . esc_attr($item['product_id']) . '" data-price="' . esc_attr($price) . '" data-volume-discounts="' . esc_attr($volume_discounts_json) . '">';
            
            // Product image (conditionally displayed)
            if (!$hide_images) {
                echo '<div class="bundle-item-image">';
                if ($thumb_id) {
                    echo wp_get_attachment_image($thumb_id, 'woocommerce_thumbnail');
                } else {
                    echo wc_placeholder_img('woocommerce_thumbnail');
                }
                echo '</div>';
            }
            
            // Product details
            echo '<div class="bundle-item-details">';
            echo '<h4 class="bundle-item-name">' . esc_html($bundled_product->get_name()) . '</h4>';
            echo '<p class="bundle-item-price">' . wp_kses_post(wc_price($price)) . ' ' . esc_html__('each', 'simple-product-bundles') . '</p>';
            
            // Volume discount tiers display
            if (!empty($volume_discounts)) {
                $volume_label_text = !empty($volume_label) ? $volume_label : __('Volume Deals:', 'simple-product-bundles');
                echo '<div class="bundle-volume-tiers">';
                echo '<div class="volume-tiers-label">' . esc_html($volume_label_text) . '</div>';
                echo '<div class="volume-tiers-badges">';
                
                // Sort tiers by min_qty to determine ranges
                $sorted_tiers = $volume_discounts;
                usort($sorted_tiers, function($a, $b) {
                    return intval($a['min_qty']) - intval($b['min_qty']);
                });
                
                $tier_count = count($sorted_tiers);
                foreach ($sorted_tiers as $index => $tier) {
                    $tier_min = intval($tier['min_qty']);
                    $tier_discount = floatval($tier['discount']);
                    $tier_type = isset($tier['discount_type']) ? $tier['discount_type'] : 'percentage';
                    
                    // Calculate the actual price per item after discount
                    if ($tier_type === 'fixed') {
                        $tier_price = $price - $tier_discount;
                    } else {
                        $tier_price = $price * (1 - ($tier_discount / 100));
                    }
                    $tier_price = max(0, $tier_price); // Ensure price doesn't go negative
                    
                    // Determine range end: next tier's min - 1, or "+" for last tier
                    $is_last_tier = ($index === $tier_count - 1);
                    if ($is_last_tier) {
                        $range_text = $tier_min . '+';
                    } else {
                        $next_tier_min = intval($sorted_tiers[$index + 1]['min_qty']);
                        $range_text = $tier_min . ' - ' . ($next_tier_min - 1);
                    }
                    
                    echo '<span class="volume-tier-badge" data-min-qty="' . esc_attr($tier_min) . '" data-discount="' . esc_attr($tier_discount) . '" data-discount-type="' . esc_attr($tier_type) . '">';
                    
                    echo esc_html($range_text) . ' =&nbsp;' . wp_kses_post(wc_price($tier_price)) . '&nbsp;' . esc_html__('per item', 'simple-product-bundles');
                    
                    echo '</span>';
                }
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
            
            // Quantity controls
            echo '<div class="bundle-item-quantity">';
            echo '<div class="bundle-qty-control">';
            echo '<button type="button" class="bundle-qty-btn bundle-qty-minus" aria-label="' . esc_attr__('Decrease quantity', 'simple-product-bundles') . '">−</button>';
            echo '<input type="number" name="bundle_qty[' . esc_attr($item['product_id']) . ']" ';
            echo 'value="' . esc_attr($default_qty) . '" ';
            echo 'min="' . esc_attr($min_qty) . '" ';
            // Only set max attribute if there's a limit (0 = unlimited)
            if ($max_qty > 0) {
                echo 'max="' . esc_attr($max_qty) . '" ';
            }
            echo 'step="1" ';
            echo 'class="bundle-qty-input" ';
            echo 'data-min="' . esc_attr($min_qty) . '" data-max="' . esc_attr($max_qty) . '" ';
            echo 'aria-label="' . esc_attr__('Quantity', 'simple-product-bundles') . '">';
            echo '<button type="button" class="bundle-qty-btn bundle-qty-plus" aria-label="' . esc_attr__('Increase quantity', 'simple-product-bundles') . '">+</button>';
            echo '</div>';
            
            // Quantity hint
            if ($min_qty == 0 && $max_qty == 0) {
                echo '<span class="bundle-qty-hint">' . esc_html__('Optional', 'simple-product-bundles') . '</span>';
            } elseif ($min_qty == 0 && $max_qty > 0) {
                echo '<span class="bundle-qty-hint">' . sprintf(
                    /* translators: %d: maximum quantity */
                    esc_html__('Up to %d', 'simple-product-bundles'),
                    esc_html($max_qty)
                ) . '</span>';
            } elseif ($max_qty == 0) {
                echo '<span class="bundle-qty-hint">' . sprintf(
                    /* translators: %d: minimum quantity */
                    esc_html__('Min %d', 'simple-product-bundles'),
                    esc_html($min_qty)
                ) . '</span>';
            } else {
                echo '<span class="bundle-qty-hint">' . sprintf(
                    /* translators: %1$d: minimum quantity, %2$d: maximum quantity */
                    esc_html__('%1$d–%2$d qty', 'simple-product-bundles'),
                    esc_html($min_qty),
                    esc_html($max_qty)
                ) . '</span>';
            }
            
            // Subtotal with volume discount display
            echo '<div class="bundle-item-pricing">';
            echo '<span class="bundle-item-subtotal" data-subtotal="' . esc_attr($price * $default_qty) . '">' . wp_kses_post(wc_price($price * $default_qty)) . '</span>';
            echo '<span class="bundle-item-savings" style="display: none;"></span>';
            echo '</div>';
            echo '</div>';
            
            echo '</div>'; // .bundle-item
        }
        
        echo '</div>'; // .bundle-items-list
        
        // Summary section
        echo '<div class="bundle-summary">';
        
        // Always show subtotal row (for volume discounts)
        echo '<div class="bundle-summary-row bundle-subtotal-row">';
        echo '<span class="bundle-summary-label">' . esc_html__('Subtotal', 'simple-product-bundles') . '</span>';
        echo '<span class="bundle-summary-value bundle-subtotal">' . wp_kses_post(wc_price(0)) . '</span>';
        echo '</div>';
        
        // Volume savings row (hidden by default)
        echo '<div class="bundle-summary-row bundle-volume-savings-row" style="display: none;">';
        echo '<span class="bundle-summary-label">' . esc_html__('Volume Savings', 'simple-product-bundles') . '</span>';
        echo '<span class="bundle-summary-value bundle-volume-savings">−' . wp_kses_post(wc_price(0)) . '</span>';
        echo '</div>';
        
        if ($discount > 0) {
            echo '<div class="bundle-summary-row bundle-discount-row">';
            echo '<span class="bundle-summary-label">' . sprintf(
                /* translators: %s: discount percentage */
                __('Bundle Discount (%s%%)', 'simple-product-bundles'),
                esc_html($discount)
            ) . '</span>';
            echo '<span class="bundle-summary-value bundle-discount">−' . wp_kses_post(wc_price(0)) . '</span>';
            echo '</div>';
        }
        
        echo '<div class="bundle-summary-row bundle-total-row">';
        echo '<span class="bundle-summary-label">' . esc_html__('Total', 'simple-product-bundles') . '</span>';
        echo '<span class="bundle-summary-value bundle-total">' . wp_kses_post(wc_price(0));
        if (!empty($price_suffix)) {
            echo '<span class="bundle-price-suffix"> ' . esc_html($price_suffix) . '</span>';
        }
        echo '</span>';
        echo '</div>';
        
        echo '</div>'; // .bundle-summary
        
        // Sanitize discount value before output
        $discount = floatval($discount);
        $discount = max(0, min(100, $discount)); // Clamp between 0 and 100
        
        echo '<input type="hidden" name="bundle_discount" value="' . esc_attr($discount) . '">';
        echo '<input type="hidden" name="bundle_volume_discounts_data" value="">';
        echo '</div>'; // .bundle-items-wrapper
    }

    /**
     * Enqueue frontend scripts
     */
    public function frontend_scripts() {
        if (is_product()) {
            $price_suffix = '';
            $product_id = get_the_ID();
            if ($product_id) {
                $product = wc_get_product($product_id);
                if ($product && $product->get_type() === 'bundle') {
                    $price_suffix = get_post_meta($product_id, '_bundle_price_suffix', true);
                }
            }
            
            wp_enqueue_style('simple-bundle-frontend', SIMPLE_PRODUCT_BUNDLES_PLUGIN_URL . 'assets/frontend.css', [], SIMPLE_PRODUCT_BUNDLES_VERSION);
            wp_enqueue_script('simple-bundle-frontend', SIMPLE_PRODUCT_BUNDLES_PLUGIN_URL . 'assets/frontend.js', ['jquery'], SIMPLE_PRODUCT_BUNDLES_VERSION, true);
            wp_localize_script('simple-bundle-frontend', 'simpleBundleParams', [
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'currency_position' => get_option('woocommerce_currency_pos'),
                'thousand_sep' => wc_get_price_thousand_separator(),
                'decimal_sep' => wc_get_price_decimal_separator(),
                'decimals' => wc_get_price_decimals(),
                'i18n_off' => __('off', 'simple-product-bundles'),
                'i18n_each' => __('each', 'simple-product-bundles'),
                'price_suffix' => $price_suffix,
            ]);
        }
    }
}


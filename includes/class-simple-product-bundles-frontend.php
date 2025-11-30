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
        // Frontend: Display bundle options and add to cart
        add_action('woocommerce_bundle_add_to_cart', [$this, 'bundle_add_to_cart_template']);

        // Frontend scripts
        add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
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
            echo '<p>' . __('This bundle has no products configured.', 'simple-product-bundles') . '</p>';
            return;
        }
        
        // Check if bundle is purchasable
        if (!$product->is_purchasable()) {
            return;
        }
        
        echo '<form class="cart bundle-cart-form" action="' . esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())) . '" method="post" enctype="multipart/form-data">';
        
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
        
        if (empty($bundle_items) || !is_array($bundle_items)) {
            echo '<p class="bundle-empty-message">' . __('This bundle has no products configured.', 'simple-product-bundles') . '</p>';
            return;
        }
        
        echo '<div class="bundle-items-wrapper">';
        echo '<h3 class="bundle-heading">' . __('Bundle Contents', 'simple-product-bundles') . '</h3>';
        
        echo '<div class="bundle-items-list">';
        
        foreach ($bundle_items as $index => $item) {
            $bundled_product = wc_get_product($item['product_id']);
            if (!$bundled_product) continue;
            
            $price = $bundled_product->get_price();
            $min_qty = intval($item['min_qty']);
            $max_qty = intval($item['max_qty']); // 0 = unlimited
            $default_qty = intval($item['default_qty']);
            
            // Ensure default is within range (if max is 0/unlimited, only check min)
            $default_qty = max($min_qty, $default_qty);
            if ($max_qty > 0) {
                $default_qty = min($max_qty, $default_qty);
            }
            
            $thumb_id = $bundled_product->get_image_id();
            
            echo '<div class="bundle-item" data-product-id="' . esc_attr($item['product_id']) . '" data-price="' . esc_attr($price) . '">';
            
            // Product image
            echo '<div class="bundle-item-image">';
            if ($thumb_id) {
                echo wp_get_attachment_image($thumb_id, 'woocommerce_thumbnail');
            } else {
                echo wc_placeholder_img('woocommerce_thumbnail');
            }
            echo '</div>';
            
            // Product details
            echo '<div class="bundle-item-details">';
            echo '<h4 class="bundle-item-name">' . esc_html($bundled_product->get_name()) . '</h4>';
            echo '<p class="bundle-item-price">' . wc_price($price) . ' ' . __('each', 'simple-product-bundles') . '</p>';
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
                echo '<span class="bundle-qty-hint">' . __('Optional', 'simple-product-bundles') . '</span>';
            } elseif ($min_qty == 0 && $max_qty > 0) {
                echo '<span class="bundle-qty-hint">' . sprintf(__('Up to %d', 'simple-product-bundles'), $max_qty) . '</span>';
            } elseif ($max_qty == 0) {
                echo '<span class="bundle-qty-hint">' . sprintf(__('Min %d', 'simple-product-bundles'), $min_qty) . '</span>';
            } else {
                echo '<span class="bundle-qty-hint">' . sprintf(__('%d–%d qty', 'simple-product-bundles'), $min_qty, $max_qty) . '</span>';
            }
            
            // Subtotal
            echo '<span class="bundle-item-subtotal" data-subtotal="' . esc_attr($price * $default_qty) . '">' . wc_price($price * $default_qty) . '</span>';
            echo '</div>';
            
            echo '</div>'; // .bundle-item
        }
        
        echo '</div>'; // .bundle-items-list
        
        // Summary section
        echo '<div class="bundle-summary">';
        
        if ($discount > 0) {
            echo '<div class="bundle-summary-row bundle-subtotal-row">';
            echo '<span class="bundle-summary-label">' . __('Subtotal', 'simple-product-bundles') . '</span>';
            echo '<span class="bundle-summary-value bundle-subtotal">' . wc_price(0) . '</span>';
            echo '</div>';
            
            echo '<div class="bundle-summary-row bundle-discount-row">';
            echo '<span class="bundle-summary-label">' . sprintf(__('Discount (%s%%)', 'simple-product-bundles'), $discount) . '</span>';
            echo '<span class="bundle-summary-value bundle-discount">−' . wc_price(0) . '</span>';
            echo '</div>';
        }
        
        echo '<div class="bundle-summary-row bundle-total-row">';
        echo '<span class="bundle-summary-label">' . __('Total', 'simple-product-bundles') . '</span>';
        echo '<span class="bundle-summary-value bundle-total">' . wc_price(0) . '</span>';
        echo '</div>';
        
        echo '</div>'; // .bundle-summary
        
        echo '<input type="hidden" name="bundle_discount" value="' . esc_attr($discount) . '">';
        echo '</div>'; // .bundle-items-wrapper
    }

    /**
     * Enqueue frontend scripts
     */
    public function frontend_scripts() {
        if (is_product()) {
            wp_enqueue_style('simple-bundle-frontend', SIMPLE_PRODUCT_BUNDLES_PLUGIN_URL . 'assets/frontend.css', [], SIMPLE_PRODUCT_BUNDLES_VERSION);
            wp_enqueue_script('simple-bundle-frontend', SIMPLE_PRODUCT_BUNDLES_PLUGIN_URL . 'assets/frontend.js', ['jquery'], SIMPLE_PRODUCT_BUNDLES_VERSION, true);
            wp_localize_script('simple-bundle-frontend', 'simpleBundleParams', [
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'currency_position' => get_option('woocommerce_currency_pos'),
                'thousand_sep' => wc_get_price_thousand_separator(),
                'decimal_sep' => wc_get_price_decimal_separator(),
                'decimals' => wc_get_price_decimals(),
            ]);
        }
    }
}


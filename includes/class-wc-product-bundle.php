<?php
/**
 * Bundle Product Type
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Product_Bundle extends WC_Product {
    
    /**
     * Product type
     *
     * @var string
     */
    protected $product_type = 'bundle';
    
    public function __construct($product = 0) {
        parent::__construct($product);
    }
    
    public function get_type() {
        return 'bundle';
    }
    
    public function is_purchasable() {
        return true;
    }
    
    public function is_virtual() {
        return false;
    }
    
    public function is_sold_individually() {
        return false;
    }
    
    public function get_bundle_items() {
        return get_post_meta($this->get_id(), '_bundle_items', true);
    }
    
    public function get_bundle_discount() {
        return floatval(get_post_meta($this->get_id(), '_bundle_discount', true));
    }
    
    /**
     * Get the min price based on minimum quantities
     */
    public function get_bundle_min_price() {
        $bundle_items = $this->get_bundle_items();
        $discount = $this->get_bundle_discount();
        
        if (empty($bundle_items) || !is_array($bundle_items)) {
            return 0;
        }
        
        $total = 0;
        foreach ($bundle_items as $item) {
            $product = wc_get_product($item['product_id']);
            if (!$product) continue;
            
            $total += $product->get_price() * intval($item['min_qty']);
        }
        
        if ($discount > 0) {
            $total = $total * (1 - ($discount / 100));
        }
        
        return $total;
    }
    
    /**
     * Get the max price based on maximum quantities
     */
    public function get_bundle_max_price() {
        $bundle_items = $this->get_bundle_items();
        $discount = $this->get_bundle_discount();
        
        if (empty($bundle_items) || !is_array($bundle_items)) {
            return 0;
        }
        
        $total = 0;
        foreach ($bundle_items as $item) {
            $product = wc_get_product($item['product_id']);
            if (!$product) continue;
            
            $total += $product->get_price() * intval($item['max_qty']);
        }
        
        if ($discount > 0) {
            $total = $total * (1 - ($discount / 100));
        }
        
        return $total;
    }
    
    /**
     * Returns the price in html format with range if applicable
     */
    public function get_price_html($price = '') {
        $min_price = $this->get_bundle_min_price();
        $max_price = $this->get_bundle_max_price();
        
        if ($min_price === $max_price) {
            return wc_price($min_price);
        }
        
        return wc_format_price_range($min_price, $max_price);
    }
    
    /**
     * Check if bundle is in stock (all bundled items must be in stock)
     */
    public function is_in_stock() {
        $bundle_items = $this->get_bundle_items();
        
        if (empty($bundle_items) || !is_array($bundle_items)) {
            return false;
        }
        
        foreach ($bundle_items as $item) {
            $product = wc_get_product($item['product_id']);
            if (!$product || !$product->is_in_stock()) {
                return false;
            }
        }
        
        return true;
    }
}

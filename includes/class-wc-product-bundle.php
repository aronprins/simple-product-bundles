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
     * Get applicable volume discount for a quantity
     *
     * @param array $volume_discounts Volume discount tiers
     * @param int   $qty              Quantity
     * @return float Discount percentage
     */
    private function get_volume_discount_for_qty($volume_discounts, $qty) {
        if (empty($volume_discounts) || !is_array($volume_discounts) || $qty <= 0) {
            return 0;
        }
        
        $applicable_discount = 0;
        
        foreach ($volume_discounts as $tier) {
            $tier_min_qty = isset($tier['min_qty']) ? intval($tier['min_qty']) : 0;
            $tier_discount = isset($tier['discount']) ? floatval($tier['discount']) : 0;
            
            if ($qty >= $tier_min_qty) {
                $applicable_discount = $tier_discount;
            }
        }
        
        return $applicable_discount;
    }
    
    /**
     * Get the min price based on minimum quantities (with volume discounts)
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
            
            $qty = intval($item['min_qty']);
            $item_total = $product->get_price() * $qty;
            
            // Apply volume discount if applicable
            $volume_discounts = isset($item['volume_discounts']) ? $item['volume_discounts'] : [];
            $volume_discount = $this->get_volume_discount_for_qty($volume_discounts, $qty);
            if ($volume_discount > 0) {
                $item_total = $item_total * (1 - ($volume_discount / 100));
            }
            
            $total += $item_total;
        }
        
        if ($discount > 0) {
            $total = $total * (1 - ($discount / 100));
        }
        
        return $total;
    }
    
    /**
     * Get the max price based on maximum quantities (with volume discounts)
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
            
            $qty = intval($item['max_qty']);
            $item_total = $product->get_price() * $qty;
            
            // Apply volume discount if applicable
            $volume_discounts = isset($item['volume_discounts']) ? $item['volume_discounts'] : [];
            $volume_discount = $this->get_volume_discount_for_qty($volume_discounts, $qty);
            if ($volume_discount > 0) {
                $item_total = $item_total * (1 - ($volume_discount / 100));
            }
            
            $total += $item_total;
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

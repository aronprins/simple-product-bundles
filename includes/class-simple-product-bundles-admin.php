<?php
/**
 * Admin functionality for Simple Product Bundles
 *
 * @package Simple_Product_Bundles
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_Product_Bundles_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        // Register bundle product type
        add_action('init', [$this, 'register_bundle_product_type']);
        add_filter('product_type_selector', [$this, 'add_bundle_product_type']);

        // Admin: Add bundle configuration tab
        add_filter('woocommerce_product_data_tabs', [$this, 'add_bundle_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'bundle_tab_content']);
        add_action('woocommerce_process_product_meta', [$this, 'save_bundle_data']);

        // Admin scripts
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

        // Show bundle tab for bundle product type
        add_action('admin_footer', [$this, 'admin_custom_js']);
    }

    /**
     * Register bundle product type
     */
    public function register_bundle_product_type() {
        require_once SIMPLE_PRODUCT_BUNDLES_PLUGIN_DIR . 'includes/class-wc-product-bundle.php';
    }

    /**
     * Add bundle product type to selector
     *
     * @param array $types Product types
     * @return array
     */
    public function add_bundle_product_type($types) {
        $types['bundle'] = __('Product Bundle', 'simple-product-bundles');
        return $types;
    }

    /**
     * Add bundle tab to product data tabs
     *
     * @param array $tabs Product data tabs
     * @return array
     */
    public function add_bundle_tab($tabs) {
        $tabs['bundle_items'] = [
            'label'    => __('Bundle Items', 'simple-product-bundles'),
            'target'   => 'bundle_items_data',
            'class'    => ['show_if_bundle'],
            'priority' => 21,
        ];
        return $tabs;
    }

    /**
     * Display bundle tab content
     */
    public function bundle_tab_content() {
        global $post;
        $bundle_items = get_post_meta($post->ID, '_bundle_items', true);
        if (!is_array($bundle_items)) {
            $bundle_items = [];
        }
        $discount = get_post_meta($post->ID, '_bundle_discount', true);
        $enable_bundle_qty = get_post_meta($post->ID, '_bundle_enable_qty', true);
        ?>
        <div id="bundle_items_data" class="panel woocommerce_options_panel hidden">
            <div class="options_group">
                <p class="form-field">
                    <label><?php _e('Bundled Products', 'simple-product-bundles'); ?></label>
                </p>
                
                <div id="bundle_items_container">
                    <?php
                    if (!empty($bundle_items)) {
                        foreach ($bundle_items as $index => $item) {
                            $this->render_bundle_item_row($index, $item);
                        }
                    }
                    ?>
                </div>
                
                <div class="bundle-actions">
                    <button type="button" class="button" id="add_bundle_item">
                        <?php _e('Add Product', 'simple-product-bundles'); ?>
                    </button>
                </div>
                
                <div class="bundle-discount-field">
                    <label for="_bundle_discount"><?php _e('Bundle Discount', 'simple-product-bundles'); ?></label>
                    <div class="discount-input-wrap">
                        <input type="number" id="_bundle_discount" name="_bundle_discount" 
                               value="<?php echo esc_attr($discount); ?>" 
                               min="0" max="100" step="0.01">
                        <span class="discount-suffix">%</span>
                    </div>
                    <span class="description"><?php _e('Applied to the total bundle price', 'simple-product-bundles'); ?></span>
                </div>
                
                <div class="bundle-qty-toggle-field">
                    <label for="_bundle_enable_qty">
                        <input type="checkbox" id="_bundle_enable_qty" name="_bundle_enable_qty" 
                               value="yes" <?php checked($enable_bundle_qty, 'yes'); ?>>
                        <?php _e('Enable bundle quantity selector', 'simple-product-bundles'); ?>
                    </label>
                    <span class="description"><?php _e('Allow customers to order multiple bundles at once', 'simple-product-bundles'); ?></span>
                </div>
            </div>
        </div>
        
        <script type="text/template" id="bundle_item_template">
            <?php $this->render_bundle_item_row('{{INDEX}}', []); ?>
        </script>
        
        <script type="text/template" id="volume_tier_template">
            <?php $this->render_volume_tier_row('{{ITEM_INDEX}}', '{{TIER_INDEX}}', []); ?>
        </script>
        <?php
    }

    /**
     * Render bundle item row
     *
     * @param int|string $index Item index
     * @param array      $item  Item data
     */
    private function render_bundle_item_row($index, $item) {
        $product_id = isset($item['product_id']) ? $item['product_id'] : '';
        $min_qty = isset($item['min_qty']) ? $item['min_qty'] : 1;
        $max_qty = isset($item['max_qty']) ? $item['max_qty'] : 10;
        $default_qty = isset($item['default_qty']) ? $item['default_qty'] : 1;
        $volume_discounts = isset($item['volume_discounts']) ? $item['volume_discounts'] : [];
        ?>
        <div class="bundle-item-row" data-index="<?php echo esc_attr($index); ?>">
            <div class="bundle-item-header">
                <span class="bundle-item-handle" title="<?php esc_attr_e('Drag to reorder', 'simple-product-bundles'); ?>"></span>
                <select name="bundle_items[<?php echo esc_attr($index); ?>][product_id]" 
                        class="wc-product-search bundle-product-select" 
                        data-placeholder="<?php esc_attr_e('Search for a product...', 'simple-product-bundles'); ?>"
                        data-action="woocommerce_json_search_products_and_variations"
                        data-exclude_type="bundle">
                    <?php if ($product_id) : 
                        $product = wc_get_product($product_id);
                        if ($product) : ?>
                            <option value="<?php echo esc_attr($product_id); ?>" selected>
                                <?php echo esc_html($product->get_formatted_name()); ?>
                            </option>
                        <?php endif;
                    endif; ?>
                </select>
                <button type="button" class="remove-bundle-item" title="<?php esc_attr_e('Remove', 'simple-product-bundles'); ?>"></button>
            </div>
            <div class="bundle-item-config">
                <div class="bundle-config-field">
                    <label><?php _e('Min Quantity', 'simple-product-bundles'); ?></label>
                    <input type="number" name="bundle_items[<?php echo esc_attr($index); ?>][min_qty]" 
                           value="<?php echo esc_attr($min_qty); ?>" min="0" step="1">
                    <span class="field-hint"><?php _e('0 = optional', 'simple-product-bundles'); ?></span>
                </div>
                <div class="bundle-config-field">
                    <label><?php _e('Max Quantity', 'simple-product-bundles'); ?></label>
                    <input type="number" name="bundle_items[<?php echo esc_attr($index); ?>][max_qty]" 
                           value="<?php echo esc_attr($max_qty); ?>" min="0" step="1">
                    <span class="field-hint"><?php _e('0 = unlimited', 'simple-product-bundles'); ?></span>
                </div>
                <div class="bundle-config-field">
                    <label><?php _e('Default Quantity', 'simple-product-bundles'); ?></label>
                    <input type="number" name="bundle_items[<?php echo esc_attr($index); ?>][default_qty]" 
                           value="<?php echo esc_attr($default_qty); ?>" min="0" step="1">
                    <span class="field-hint"><?php _e('Pre-selected qty', 'simple-product-bundles'); ?></span>
                </div>
            </div>
            
            <!-- Volume Discounts Section -->
            <div class="bundle-volume-discounts">
                <div class="volume-discounts-header">
                    <span class="volume-discounts-icon"></span>
                    <span class="volume-discounts-title"><?php _e('Volume Discounts', 'simple-product-bundles'); ?></span>
                    <button type="button" class="volume-discounts-toggle" aria-expanded="<?php echo !empty($volume_discounts) ? 'true' : 'false'; ?>">
                        <span class="toggle-indicator"></span>
                    </button>
                </div>
                <div class="volume-discounts-content" style="<?php echo empty($volume_discounts) ? 'display: none;' : ''; ?>">
                    <div class="volume-tiers-container" data-item-index="<?php echo esc_attr($index); ?>">
                        <?php 
                        if (!empty($volume_discounts)) {
                            foreach ($volume_discounts as $tier_index => $tier) {
                                $this->render_volume_tier_row($index, $tier_index, $tier);
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="button add-volume-tier">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php _e('Add Tier', 'simple-product-bundles'); ?>
                    </button>
                    <p class="volume-discounts-help">
                        <?php _e('Set quantity thresholds with discount percentages. Higher quantities override lower tiers.', 'simple-product-bundles'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render volume discount tier row
     *
     * @param int|string $item_index Item index
     * @param int|string $tier_index Tier index
     * @param array      $tier       Tier data
     */
    private function render_volume_tier_row($item_index, $tier_index, $tier = []) {
        $min_qty = isset($tier['min_qty']) ? $tier['min_qty'] : '';
        $discount = isset($tier['discount']) ? $tier['discount'] : '';
        ?>
        <div class="volume-tier-row" data-tier-index="<?php echo esc_attr($tier_index); ?>">
            <div class="tier-field tier-qty">
                <label><?php _e('Buy', 'simple-product-bundles'); ?></label>
                <input type="number" 
                       name="bundle_items[<?php echo esc_attr($item_index); ?>][volume_discounts][<?php echo esc_attr($tier_index); ?>][min_qty]" 
                       value="<?php echo esc_attr($min_qty); ?>" 
                       min="1" 
                       step="1" 
                       placeholder="<?php esc_attr_e('qty', 'simple-product-bundles'); ?>"
                       class="tier-min-qty">
                <span class="tier-label"><?php _e('or more', 'simple-product-bundles'); ?></span>
            </div>
            <div class="tier-arrow">→</div>
            <div class="tier-field tier-discount">
                <label><?php _e('Get', 'simple-product-bundles'); ?></label>
                <input type="number" 
                       name="bundle_items[<?php echo esc_attr($item_index); ?>][volume_discounts][<?php echo esc_attr($tier_index); ?>][discount]" 
                       value="<?php echo esc_attr($discount); ?>" 
                       min="0" 
                       max="100" 
                       step="0.01" 
                       placeholder="0"
                       class="tier-discount-input">
                <span class="tier-label"><?php _e('% off', 'simple-product-bundles'); ?></span>
            </div>
            <button type="button" class="remove-volume-tier" title="<?php esc_attr_e('Remove tier', 'simple-product-bundles'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <?php
    }

    /**
     * Save bundle data
     *
     * @param int $post_id Post ID
     */
    public function save_bundle_data($post_id) {
        if (isset($_POST['bundle_items']) && is_array($_POST['bundle_items'])) {
            $bundle_items = [];
            foreach ($_POST['bundle_items'] as $item) {
                if (!empty($item['product_id'])) {
                    $bundle_item = [
                        'product_id'  => absint($item['product_id']),
                        'min_qty'     => absint($item['min_qty']),
                        'max_qty'     => absint($item['max_qty']),
                        'default_qty' => absint($item['default_qty']),
                        'volume_discounts' => [],
                    ];
                    
                    // Process volume discounts
                    if (!empty($item['volume_discounts']) && is_array($item['volume_discounts'])) {
                        $volume_discounts = [];
                        foreach ($item['volume_discounts'] as $tier) {
                            $tier_min_qty = isset($tier['min_qty']) ? absint($tier['min_qty']) : 0;
                            $tier_discount = isset($tier['discount']) ? floatval($tier['discount']) : 0;
                            
                            // Only save valid tiers (min_qty > 0 and discount > 0)
                            if ($tier_min_qty > 0 && $tier_discount > 0) {
                                $volume_discounts[] = [
                                    'min_qty'  => $tier_min_qty,
                                    'discount' => min(100, max(0, $tier_discount)),
                                ];
                            }
                        }
                        
                        // Sort by min_qty ascending
                        usort($volume_discounts, function($a, $b) {
                            return $a['min_qty'] - $b['min_qty'];
                        });
                        
                        $bundle_item['volume_discounts'] = $volume_discounts;
                    }
                    
                    $bundle_items[] = $bundle_item;
                }
            }
            update_post_meta($post_id, '_bundle_items', $bundle_items);
        } else {
            delete_post_meta($post_id, '_bundle_items');
        }
        
        if (isset($_POST['_bundle_discount'])) {
            update_post_meta($post_id, '_bundle_discount', floatval($_POST['_bundle_discount']));
        }
        
        // Save bundle quantity toggle
        $enable_bundle_qty = isset($_POST['_bundle_enable_qty']) ? 'yes' : 'no';
        update_post_meta($post_id, '_bundle_enable_qty', $enable_bundle_qty);
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook
     */
    public function admin_scripts($hook) {
        global $post;
        
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            if (isset($post) && $post->post_type === 'product') {
                wp_enqueue_style('simple-bundle-admin', SIMPLE_PRODUCT_BUNDLES_PLUGIN_URL . 'assets/admin.css', [], SIMPLE_PRODUCT_BUNDLES_VERSION);
                wp_enqueue_script('simple-bundle-admin', SIMPLE_PRODUCT_BUNDLES_PLUGIN_URL . 'assets/admin.js', ['jquery', 'wc-enhanced-select'], SIMPLE_PRODUCT_BUNDLES_VERSION, true);
            }
        }
    }

    /**
     * Admin custom JavaScript
     */
    public function admin_custom_js() {
        global $post;
        if (isset($post) && $post->post_type === 'product') : ?>
        <script type="text/javascript">
        jQuery(function($) {
            // Show/hide bundle tab based on product type
            $('body').on('woocommerce-product-type-change', function(e, type) {
                if (type === 'bundle') {
                    $('.show_if_bundle').show();
                    $('.hide_if_bundle').hide();
                } else {
                    $('.show_if_bundle').hide();
                }
            });
            
            // Trigger on page load
            $('#product-type').trigger('change');
            if ($('#product-type').val() === 'bundle') {
                $('.show_if_bundle').show();
            }
        });
        </script>
        <?php endif;
    }
}


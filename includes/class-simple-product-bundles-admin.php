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
            </div>
        </div>
        
        <script type="text/template" id="bundle_item_template">
            <?php $this->render_bundle_item_row('{{INDEX}}', []); ?>
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
                    $bundle_items[] = [
                        'product_id'  => absint($item['product_id']),
                        'min_qty'     => absint($item['min_qty']),
                        'max_qty'     => absint($item['max_qty']),
                        'default_qty' => absint($item['default_qty']),
                    ];
                }
            }
            update_post_meta($post_id, '_bundle_items', $bundle_items);
        } else {
            delete_post_meta($post_id, '_bundle_items');
        }
        
        if (isset($_POST['_bundle_discount'])) {
            update_post_meta($post_id, '_bundle_discount', floatval($_POST['_bundle_discount']));
        }
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


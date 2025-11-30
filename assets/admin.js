/**
 * Simple Product Bundles - Admin JS
 */
jQuery(function($) {
    'use strict';
    
    var bundleItemIndex = $('#bundle_items_container .bundle-item-row').length;
    
    /**
     * Initialize select2/selectWoo on a product search field
     */
    function initProductSearch($select) {
        if ($select.hasClass('select2-hidden-accessible')) {
            return;
        }
        
        $select.selectWoo({
            allowClear: true,
            placeholder: $select.data('placeholder'),
            minimumInputLength: 3,
            ajax: {
                url: woocommerce_admin_meta_boxes.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        term: params.term,
                        action: 'woocommerce_json_search_products_and_variations',
                        security: woocommerce_admin_meta_boxes.search_products_nonce,
                        exclude_type: 'bundle'
                    };
                },
                processResults: function(data) {
                    var terms = [];
                    if (data) {
                        $.each(data, function(id, text) {
                            terms.push({ id: id, text: text });
                        });
                    }
                    return { results: terms };
                },
                cache: true
            }
        });
    }
    
    /**
     * Initialize sortable functionality
     */
    function initSortable() {
        $('#bundle_items_container').sortable({
            handle: '.bundle-item-handle',
            placeholder: 'ui-sortable-placeholder',
            axis: 'y',
            cursor: 'move',
            opacity: 0.8,
            tolerance: 'pointer',
            start: function(e, ui) {
                ui.placeholder.height(ui.item.outerHeight());
            }
        });
    }
    
    // Add new bundle item
    $('#add_bundle_item').on('click', function(e) {
        e.preventDefault();
        
        var template = $('#bundle_item_template').html();
        template = template.replace(/\{\{INDEX\}\}/g, bundleItemIndex);
        
        $('#bundle_items_container').append(template);
        
        // Initialize the select2/enhanced select on the new row
        var $newRow = $('#bundle_items_container .bundle-item-row').last();
        initProductSearch($newRow.find('.wc-product-search'));
        
        bundleItemIndex++;
        
        // Refresh sortable
        if ($('#bundle_items_container').hasClass('ui-sortable')) {
            $('#bundle_items_container').sortable('refresh');
        }
    });
    
    // Remove bundle item
    $(document).on('click', '.remove-bundle-item', function(e) {
        e.preventDefault();
        
        var $row = $(this).closest('.bundle-item-row');
        
        // Fade out and remove
        $row.slideUp(200, function() {
            $(this).remove();
        });
    });
    
    // Initialize existing select2 fields
    $('.bundle-product-select').each(function() {
        initProductSearch($(this));
    });
    
    // Initialize sortable if jQuery UI is available
    if ($.fn.sortable) {
        initSortable();
    }
});

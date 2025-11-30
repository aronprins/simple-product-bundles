/**
 * Simple Product Bundles - Frontend JS
 */
jQuery(function($) {
    'use strict';
    
    var $wrapper = $('.bundle-items-wrapper');
    
    if (!$wrapper.length) {
        return;
    }
    
    var params = simpleBundleParams;
    
    /**
     * Format price according to WooCommerce settings
     */
    function formatPrice(price) {
        var formatted = price.toFixed(params.decimals);
        
        // Add thousand separator
        var parts = formatted.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, params.thousand_sep);
        formatted = parts.join(params.decimal_sep);
        
        // Add currency symbol
        switch (params.currency_position) {
            case 'left':
                return params.currency_symbol + formatted;
            case 'right':
                return formatted + params.currency_symbol;
            case 'left_space':
                return params.currency_symbol + ' ' + formatted;
            case 'right_space':
                return formatted + ' ' + params.currency_symbol;
            default:
                return params.currency_symbol + formatted;
        }
    }
    
    /**
     * Update quantity button states
     */
    function updateButtonStates($input) {
        var val = parseInt($input.val()) || 0;
        var min = parseInt($input.data('min')) || 0;
        var max = parseInt($input.data('max')) || 0; // 0 = unlimited
        var $control = $input.closest('.bundle-qty-control');
        
        $control.find('.bundle-qty-minus').prop('disabled', val <= min);
        // Only disable plus button if there's a max limit (0 = unlimited)
        $control.find('.bundle-qty-plus').prop('disabled', max > 0 && val >= max);
    }
    
    /**
     * Calculate and update all totals
     */
    function calculateTotals() {
        var subtotal = 0;
        
        // Calculate each row subtotal
        $('.bundle-item').each(function() {
            var $row = $(this);
            var price = parseFloat($row.data('price')) || 0;
            var qty = parseInt($row.find('.bundle-qty-input').val()) || 0;
            var rowSubtotal = price * qty;
            
            $row.find('.bundle-item-subtotal')
                .data('subtotal', rowSubtotal)
                .html(formatPrice(rowSubtotal));
            
            subtotal += rowSubtotal;
            
            // Update button states
            updateButtonStates($row.find('.bundle-qty-input'));
        });
        
        // Get discount
        var discount = parseFloat($('input[name="bundle_discount"]').val()) || 0;
        var discountAmount = 0;
        var total = subtotal;
        
        if (discount > 0) {
            discountAmount = subtotal * (discount / 100);
            total = subtotal - discountAmount;
            
            $('.bundle-subtotal').html(formatPrice(subtotal));
            $('.bundle-discount').html('−' + formatPrice(discountAmount));
        }
        
        $('.bundle-total').html(formatPrice(total));
    }
    
    /**
     * Handle quantity change
     */
    function handleQuantityChange($input, newVal) {
        var min = parseInt($input.data('min')) || 0;
        var max = parseInt($input.data('max')) || 0; // 0 = unlimited
        
        // Clamp value - respect min, only clamp max if it's set (> 0)
        newVal = Math.max(min, newVal);
        if (max > 0) {
            newVal = Math.min(max, newVal);
        }
        
        $input.val(newVal);
        calculateTotals();
    }
    
    // Quantity minus button
    $(document).on('click', '.bundle-qty-minus', function(e) {
        e.preventDefault();
        var $input = $(this).siblings('.bundle-qty-input');
        var currentVal = parseInt($input.val()) || 0;
        handleQuantityChange($input, currentVal - 1);
    });
    
    // Quantity plus button
    $(document).on('click', '.bundle-qty-plus', function(e) {
        e.preventDefault();
        var $input = $(this).siblings('.bundle-qty-input');
        var currentVal = parseInt($input.val()) || 0;
        handleQuantityChange($input, currentVal + 1);
    });
    
    // Direct input change
    $(document).on('change input', '.bundle-qty-input', function() {
        var $input = $(this);
        var val = parseInt($input.val()) || 0;
        handleQuantityChange($input, val);
    });
    
    // Keyboard support for quantity buttons
    $(document).on('keydown', '.bundle-qty-btn', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });
    
    // Initial calculation
    calculateTotals();
});

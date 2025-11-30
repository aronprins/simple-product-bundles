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
     * Get applicable volume discount for a quantity
     */
    function getVolumeDiscount(volumeDiscounts, qty) {
        if (!volumeDiscounts || !volumeDiscounts.length || qty <= 0) {
            return 0;
        }
        
        var applicableDiscount = 0;
        
        // Volume discounts should be sorted by min_qty ascending
        // Find the highest tier that applies
        for (var i = 0; i < volumeDiscounts.length; i++) {
            var tier = volumeDiscounts[i];
            var tierMinQty = parseInt(tier.min_qty) || 0;
            var tierDiscount = parseFloat(tier.discount) || 0;
            
            if (qty >= tierMinQty) {
                applicableDiscount = tierDiscount;
            }
        }
        
        return applicableDiscount;
    }
    
    /**
     * Update volume tier badge states based on current quantity
     */
    function updateVolumeTierBadges($row, qty) {
        $row.find('.volume-tier-badge').each(function() {
            var $badge = $(this);
            var tierMinQty = parseInt($badge.data('min-qty')) || 0;
            
            if (qty >= tierMinQty) {
                $badge.addClass('active');
            } else {
                $badge.removeClass('active');
            }
            
            // Mark the next tier to unlock
            $badge.removeClass('next-tier');
        });
        
        // Find and mark the next tier to unlock
        var $badges = $row.find('.volume-tier-badge').not('.active');
        if ($badges.length) {
            $badges.first().addClass('next-tier');
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
        var subtotalBeforeVolume = 0;
        var subtotalAfterVolume = 0;
        var totalVolumeSavings = 0;
        var volumeDiscountsApplied = {};
        
        // Calculate each row subtotal with volume discounts
        $('.bundle-item').each(function() {
            var $row = $(this);
            var productId = $row.data('product-id');
            var price = parseFloat($row.data('price')) || 0;
            var qty = parseInt($row.find('.bundle-qty-input').val()) || 0;
            var volumeDiscounts = $row.data('volume-discounts') || [];
            
            // Parse volume discounts if it's a string
            if (typeof volumeDiscounts === 'string') {
                try {
                    volumeDiscounts = JSON.parse(volumeDiscounts);
                } catch (e) {
                    volumeDiscounts = [];
                }
            }
            
            var rowSubtotalBeforeDiscount = price * qty;
            var volumeDiscountPercent = getVolumeDiscount(volumeDiscounts, qty);
            var volumeDiscountAmount = rowSubtotalBeforeDiscount * (volumeDiscountPercent / 100);
            var rowSubtotalAfterDiscount = rowSubtotalBeforeDiscount - volumeDiscountAmount;
            
            subtotalBeforeVolume += rowSubtotalBeforeDiscount;
            subtotalAfterVolume += rowSubtotalAfterDiscount;
            totalVolumeSavings += volumeDiscountAmount;
            
            // Store volume discount data for cart
            if (volumeDiscountPercent > 0) {
                volumeDiscountsApplied[productId] = volumeDiscountPercent;
            }
            
            // Update row display
            var $subtotalEl = $row.find('.bundle-item-subtotal');
            var $savingsEl = $row.find('.bundle-item-savings');
            
            $subtotalEl.data('subtotal', rowSubtotalAfterDiscount);
            
            if (volumeDiscountPercent > 0 && qty > 0) {
                // Show discounted price with strikethrough original
                $subtotalEl.html(
                    '<span class="original-price">' + formatPrice(rowSubtotalBeforeDiscount) + '</span> ' +
                    '<span class="discounted-price">' + formatPrice(rowSubtotalAfterDiscount) + '</span>'
                );
                $savingsEl.html(volumeDiscountPercent + '% ' + params.i18n_off).show();
            } else {
                $subtotalEl.html(formatPrice(rowSubtotalAfterDiscount));
                $savingsEl.hide();
            }
            
            // Update volume tier badges
            updateVolumeTierBadges($row, qty);
            
            // Update button states
            updateButtonStates($row.find('.bundle-qty-input'));
        });
        
        // Store volume discounts data for cart submission
        $('input[name="bundle_volume_discounts_data"]').val(JSON.stringify(volumeDiscountsApplied));
        
        // Get bundle discount
        var bundleDiscount = parseFloat($('input[name="bundle_discount"]').val()) || 0;
        var bundleDiscountAmount = 0;
        var total = subtotalAfterVolume;
        
        // Update summary display
        $('.bundle-subtotal').html(formatPrice(subtotalBeforeVolume));
        
        // Volume savings row
        if (totalVolumeSavings > 0) {
            $('.bundle-volume-savings-row').show();
            $('.bundle-volume-savings').html('−' + formatPrice(totalVolumeSavings));
        } else {
            $('.bundle-volume-savings-row').hide();
        }
        
        // Bundle discount (applied after volume discounts)
        if (bundleDiscount > 0) {
            bundleDiscountAmount = subtotalAfterVolume * (bundleDiscount / 100);
            total = subtotalAfterVolume - bundleDiscountAmount;
            $('.bundle-discount').html('−' + formatPrice(bundleDiscountAmount));
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

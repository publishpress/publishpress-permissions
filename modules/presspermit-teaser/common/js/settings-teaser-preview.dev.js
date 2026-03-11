/**
 * Teaser Notice Style Customization - Live Preview
 * Standalone module for real-time preview updates
 * Supports per-post-type settings
 */
jQuery(document).ready(function($) {
    function updateTeaserNoticePreview() {
        // Get the active post type container
        var $activeContainer = $('.pp-teaser-settings-container.active');
        
        if (!$activeContainer.length) {
            // Fallback: try to find any visible container
            $activeContainer = $('.pp-teaser-settings-container:visible').first();
        }
        
        if (!$activeContainer.length) {
            return; // Exit if no active container
        }
        
        // Get the preview element within the active container
        var $preview = $activeContainer.find('.pp-teaser-notice-preview');
        
        if (!$preview.length) {
            return; // Exit if preview element doesn't exist
        }
        
        // Get values from inputs within the active container
        var bgColor = $activeContainer.find('[name*="teaser_notice_bg_color"]').val() || '#f0f6fc';
        var textColor = $activeContainer.find('[name*="teaser_notice_text_color"]').val() || '#1d2327';
        var borderColor = $activeContainer.find('[name*="teaser_notice_border_color"]').val() || '#0073aa';
        var borderWidth = $activeContainer.find('[name*="teaser_notice_border_width"]').val() || '4';
        var borderPosition = $activeContainer.find('[name*="teaser_notice_border_position"]').val() || 'left';
        var padding = $activeContainer.find('[name*="teaser_notice_padding"]').val() || '15';
        var borderRadius = $activeContainer.find('[name*="teaser_notice_border_radius"]').val() || '0';
        var fontSize = $activeContainer.find('[name*="teaser_notice_font_size"]').val() || '14';
        
        // Apply styles to preview with smooth transition
        $preview.css({
            'padding': padding + 'px',
            'background': bgColor,
            'color': textColor,
            'margin': '15px 0',
            'font-size': fontSize + 'px',
            'line-height': '1.6',
            'border-radius': borderRadius + 'px',
            'transition': 'all 0.3s ease'
        });
        
        // Handle border separately based on position
        if (borderPosition === 'all') {
            // Clear individual borders and set border for all sides
            $preview.css('border-left', '');
            $preview.css('border-right', '');
            $preview.css('border-top', '');
            $preview.css('border-bottom', '');
            $preview.css('border', borderWidth + 'px solid ' + borderColor);
        } else {
            // Clear the shorthand border property first
            $preview.css('border', '');
            // Clear all individual borders
            $preview.css('border-left', '');
            $preview.css('border-right', '');
            $preview.css('border-top', '');
            $preview.css('border-bottom', '');
            // Set specific border
            $preview.css('border-' + borderPosition, borderWidth + 'px solid ' + borderColor);
        }
    }

    // Initialize color pickers after DOM is ready
    setTimeout(function() {
        if (typeof $.fn.wpColorPicker !== 'undefined') {
            $('.pp-color-picker').each(function() {
                $(this).wpColorPicker({
                    change: function(event, ui) {
                        updateTeaserNoticePreview();
                    },
                    clear: function() {
                        setTimeout(updateTeaserNoticePreview, 100);
                    }
                });
            });
        }
    }, 200);

    // Update preview when any style input changes - using event delegation
    $(document).on('input change', '.pp-style-input', function() {
        updateTeaserNoticePreview();
    });

    // Also listen for changes on color picker inputs directly
    $(document).on('input change', '.pp-color-picker', function() {
        updateTeaserNoticePreview();
    });

    // Update preview when post type is changed
    $(document).on('change', '#pp_current_post_type', function() {
        setTimeout(updateTeaserNoticePreview, 100);
    });

    // Initial preview update on page load
    setTimeout(function() {
        updateTeaserNoticePreview();
    }, 300);
});

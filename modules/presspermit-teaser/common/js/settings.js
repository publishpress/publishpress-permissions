jQuery(document).ready(function ($) {
    // Tabs
    var $tabsWrapper = $('#publishpress-permissions-teaser-tabs');
    $tabsWrapper.find('li').click(function (e) {
        e.preventDefault();
        $tabsWrapper.children('li').filter('.nav-tab-active').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        var panel = $(this).find('a').first().attr('href');

        $('section[id^="ppp-"]').hide();
        $(panel).show();

        var current_tab = $(this).find('a').attr('href').replace('#','');
        $('#current_tab').val(current_tab);
    });

    // Expand / Collapse teaser custom code
    $('.ppp-expand-code').bind('click', function(e) {
        e.preventDefault();
        var codeArea = $(this).closest('.ppp-code-sample').find('textarea');
        if( $(this).attr('data-expand') === 'closed' ) {
            codeArea.css( 'height', codeArea[0].scrollHeight );
            $(this).attr('data-expand', 'opened');
            $(this).find('.ppp-expand-msg').hide();
            $(this).find('.ppp-collapse-msg').show();
        } else {
            codeArea.css( 'height', 200 );
            $(this).attr('data-expand', 'closed');
            $(this).find('.ppp-collapse-msg').hide();
            $(this).find('.ppp-expand-msg').show();
        }
    });

    // Copy teaser custom code
    $('.ppp-copy-code').bind('click', function(e) {
        e.preventDefault();
        var codeArea = $(this).closest('.ppp-code-sample').find('textarea');
        codeArea.select();
        document.execCommand('copy');
        if( $(this).attr('data-copy') === 'uncopied' ) {
            $(this).find('.ppp-uncopied-msg').hide();
            $(this).find('.ppp-copied-msg').show();
        } else {
            $(this).attr('data-expand', 'closed');
            $(this).find('.ppp-copied-msg').hide();
            $(this).find('.ppp-uncopied-msg').show();
        }
    });

    // Search posts
    $('.permissions_select_posts').select2( {
        placeholder: presspermitTeaser.strings.select_a_page,
        allowClear: true,
        ajax: {
            url: presspermitTeaser.url,
            dataType: 'json',
            method: 'get',
            delay: 250,
            data: function (params) {

                return {
                    search: params.term,
                    action: 'pp_search_posts',
                    nonce: presspermitTeaser.nonce
                }
            },
            processResults: function( data ) {
                var options = [];
    			if ( data ) {

                    $.each( data, function( index, item ) {
    					options.push( { id: item.ID, text: item.post_title  } );
    				});

                    return {
        				results: options
        			};
    			}
            },
        }
    } );

    // Search terms
    $('.permissions_select_terms').select2( {
        placeholder: presspermitTeaser.strings.select_terms,
        ajax: {
            url: presspermitTeaser.url,
            dataType: 'json',
            method: 'get',
            delay: 250,
            data: function (params) {
                return {
                    search: params.term,
                    action: 'pp_search_terms',
                    taxonomy: $('#teaser_hide_links_taxonomy').val(),
                    nonce: presspermitTeaser.nonce
                }
            },
            processResults: function( data ) {
                var options = [];
    			if ( data ) {
                    $.each( data, function( index, item ) {
    					options.push( { id: item.term_id, text: item.name  } );
    				});

                    return {
        				results: options
        			};
    			}
            },
        }
    } );

    $('div.teaser-coverage-post select').on('change', function () {
        $(this).siblings('span.teaser-num-chars').toggle($(this).val() == 'x_chars');
    });

    $('#ppp-tab-redirect select.teaser-redirect-mode').on('change', function() {
        $(this).parent('td').siblings('td').find('div.pp-select-dynamic-wrapper').toggle($(this).val() == '(select)');
    });

    // Expandable Row Functionality
    // Handle expand/collapse icon click
    $(document).on('click', '.pp-expand-icon', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $icon = $(this);
        var $mainRow = $icon.closest('tr.pp-main-row');
        var postType = $mainRow.data('post-type');
        var $detailRow = $('.pp-detail-' + postType);

        if ($detailRow.is(':visible')) {
            // Collapse
            $icon.removeClass('pp-expanded');
            $mainRow.removeClass('pp-expanded');
            setTimeout(function() {
                $detailRow.toggle();
                $detailRow.removeClass("expanded");
            }, 150);
        } else {
            // Expand
            $icon.addClass('pp-expanded');
            $mainRow.addClass('pp-expanded');
            setTimeout(function() {
                $detailRow.toggle();
                $detailRow.addClass("expanded");
            }, 150);
        }
    });

    // Handle teaser type change - show/hide expand icon and detail row
    $(document).on('change', 'select.teaser-type-select', function () {
        var $select = $(this);
        var $mainRow = $select.closest('tr.pp-main-row');
        var postType = $mainRow.data('post-type');
        var $expandIcon = $mainRow.find('.pp-expand-icon');
        var $detailRow = $('.pp-detail-' + postType);
        var $userApplicationDiv = $mainRow.find('div.teaser_vspace');
        var $numCharsSpan = $select.siblings('span.teaser-num-chars');

        var isEnabled = $select.val() != '0';

        // Show/hide expand icon
        $expandIcon.toggle(isEnabled);

        // Show/hide user application radio buttons
        $userApplicationDiv.toggle(isEnabled);

        // Show/hide num chars input
        $numCharsSpan.toggle($select.val() == 'x_chars');

        // Update row active state
        $mainRow.attr('data-row-active', isEnabled ? '1' : '0');

        // If disabled, collapse the detail row
        if (!isEnabled) {
            $expandIcon.removeClass('pp-expanded');
            $mainRow.removeClass('pp-expanded');
            $detailRow.slideUp(200);
        }

        // Update header column visibility
        var ppAnyTeaserTypesEnabled = $('select.teaser-type-select option:selected[value!="0"]').length;
        $('th.pp-teaser-user-application span').toggle(ppAnyTeaserTypesEnabled > 0);
    });

    // Handle redirect dropdown change in the separate redirect section
    $('.teaser-redirect-section select.teaser-redirect-mode').on('change', function() {
        $(this).parent('td').siblings('td').find('div.pp-select-dynamic-wrapper').toggle($(this).val() == '(select)');
    });

    // ========================================================================
    // Progressive Disclosure UI - Teaser Settings
    // ========================================================================

    // Post type selector - switch between different post type settings
    $('#pp_current_post_type').on('change', function() {
        var selectedType = $(this).val();
        
        // Save selected post type to hidden field for persistence
        $('#selected_post_type').val(selectedType);
        
        // Hide all containers
        $('.pp-teaser-settings-container').removeClass('active').hide();
        
        // Show selected container with animation
        var $selectedContainer = $('.pp-teaser-settings-container[data-post-type="' + selectedType + '"]');
        $selectedContainer.addClass('active pp-fade-in').show();

        // Check the teaser type of the selected post type and show/hide sections
        var teaserType = $selectedContainer.find('.pp-teaser-type-select').val();
        if (teaserType == '0') {
            // No Teaser: hide everything
            $selectedContainer.find('.pp-teaser-redirect-settings').slideUp(300);
            $selectedContainer.find('.pp-teaser-application-fields').slideUp(300);
            $selectedContainer.find('.pp-teaser-text-card').slideUp(300);
        } else if (teaserType == 'redirect') {
            // Redirect: show only redirect settings and application fields
            $selectedContainer.find('.pp-teaser-redirect-settings').slideDown(300);
            $selectedContainer.find('.pp-teaser-application-fields').slideDown(300);
            $selectedContainer.find('.pp-teaser-text-card').slideUp(300);
        } else if (teaserType == '1') {
            // Teaser Text: show teaser text card and application fields, hide redirect
            $selectedContainer.find('.pp-teaser-text-card').slideDown(300);
            $selectedContainer.find('.pp-teaser-application-fields').slideDown(300);
            $selectedContainer.find('.pp-teaser-redirect-settings').slideUp(300);
        } else {
            // Other teaser types: show application fields only
            $selectedContainer.find('.pp-teaser-application-fields').slideDown(300);
            $selectedContainer.find('.pp-teaser-text-card').slideUp(300);
            $selectedContainer.find('.pp-teaser-redirect-settings').slideUp(300);
        }
    });

    // Teaser type select dropdown - show/hide conditional settings
    $(document).on('change', '.pp-teaser-type-select', function() {
        var $container = $(this).closest('.pp-teaser-settings-container');
        var selectedType = $(this).val();
        var postType = $container.data('post-type');

        // Show/hide number of characters input for x_chars type
        if (selectedType == 'x_chars') {
            $container.find('.pp-num-chars-setting').fadeIn(300);
        } else {
            $container.find('.pp-num-chars-setting').fadeOut(300);
        }

        // Show/hide sections based on teaser type
        if (selectedType == '0') {
            // No Teaser: hide everything
            $container.find('.pp-teaser-redirect-settings').slideUp(300);
            $container.find('.pp-teaser-text-card').slideUp(300);
            $container.find('.pp-read-more-notice-card').slideUp(300);
            $container.find('.pp-teaser-application-fields').slideUp(300);
        } else if (selectedType == 'redirect') {
            // console.log(selectedType, $container.find('.pp-teaser-redirect-settings'));
            // Redirect: show only redirect settings and application fields
            $container.find('.pp-teaser-redirect-settings').slideDown(300);
            $container.find('.pp-teaser-application-fields').slideDown(300);
            $container.find('.pp-teaser-text-card').slideUp(300);
            $container.find('.pp-read-more-notice-card').slideUp(300);
        } else if (selectedType == '1') {
            // Teaser Text: show teaser text card and application fields, hide redirect
            $container.find('.pp-teaser-text-card').slideDown(300);
            $container.find('.pp-teaser-application-fields').slideDown(300);
            $container.find('.pp-teaser-redirect-settings').slideUp(300);
            $container.find('.pp-read-more-notice-card').slideUp(300);
        } else if (selectedType == 'read_more') {
            // Read More: show read more notice and application fields
            $container.find('.pp-read-more-notice-card').slideDown(300);
            $container.find('.pp-teaser-application-fields').slideDown(300);
            $container.find('.pp-teaser-text-card').slideUp(300);
            $container.find('.pp-teaser-redirect-settings').slideUp(300);
        } else {
            // Other teaser types: show application fields only
            $container.find('.pp-teaser-application-fields').slideDown(300);
            $container.find('.pp-teaser-text-card').slideUp(300);
            $container.find('.pp-read-more-notice-card').slideUp(300);
            $container.find('.pp-teaser-redirect-settings').slideUp(300);
        }
    });

    // Teaser text tabs - switch between logged in and not logged in
    $(document).on('click', '.pp-teaser-text-tab', function() {
        var tab = $(this).data('tab');
        var $card = $(this).closest('.pp-teaser-card');
        
        // Update tab active state
        $card.find('.pp-teaser-text-tab').removeClass('active');
        $(this).addClass('active');
        
        // Show corresponding content
        $card.find('.pp-teaser-text-content').removeClass('active').hide();
        $card.find('.pp-teaser-text-content[data-tab-content="' + tab + '"]')
            .addClass('active')
            .fadeIn(200);
    });

    // Initialize visibility on page load for progressive disclosure UI
    function initializeProgressiveUIVisibility() {
        // Check if there's a previously selected post type
        var savedPostType = $('#selected_post_type').val();
        var $targetContainer;
        
        if (savedPostType && $('.pp-teaser-settings-container[data-post-type="' + savedPostType + '"]').length) {
            // Restore previously selected post type
            $targetContainer = $('.pp-teaser-settings-container[data-post-type="' + savedPostType + '"]');
            $('#pp_current_post_type').val(savedPostType);
        } else {
            // Show first post type by default
            $targetContainer = $('.pp-teaser-settings-container').first();
            if ($targetContainer.length) {
                var firstPostType = $targetContainer.data('post-type');
                $('#pp_current_post_type').val(firstPostType);
                $('#selected_post_type').val(firstPostType);
            }
        }
        
        if ($targetContainer && $targetContainer.length) {
            $targetContainer.addClass('active').show();

            // Check the post type's teaser setting and show/hide elements accordingly
            var teaserType = $targetContainer.find('.pp-teaser-type-select').val();
            
            if (teaserType == '0') {
                // No Teaser: hide everything
                $targetContainer.find('.pp-teaser-redirect-settings').hide();
                $targetContainer.find('.pp-teaser-text-card').hide();
                $targetContainer.find('.pp-read-more-notice-card').hide();
                $targetContainer.find('.pp-teaser-application-fields').hide();
            } else if (teaserType == 'redirect') {
                // Redirect: show only redirect settings and application fields
                $targetContainer.find('.pp-teaser-redirect-settings').show();
                $targetContainer.find('.pp-teaser-application-fields').show();
                $targetContainer.find('.pp-teaser-text-card').hide();
                $targetContainer.find('.pp-read-more-notice-card').hide();
            } else if (teaserType == '1') {
                // Teaser Text: show teaser text card and application fields, hide redirect
                $targetContainer.find('.pp-teaser-text-card').show();
                $targetContainer.find('.pp-teaser-application-fields').show();
                $targetContainer.find('.pp-teaser-redirect-settings').hide();
                $targetContainer.find('.pp-read-more-notice-card').hide();
            } else if (teaserType == 'read_more') {
                // Read More: show read more notice and application fields
                $targetContainer.find('.pp-read-more-notice-card').show();
                $targetContainer.find('.pp-teaser-application-fields').show();
                $targetContainer.find('.pp-teaser-text-card').hide();
                $targetContainer.find('.pp-teaser-redirect-settings').hide();
            } else {
                // Other teaser types: show application fields only
                $targetContainer.find('.pp-teaser-application-fields').show();
                $targetContainer.find('.pp-teaser-text-card').hide();
                $targetContainer.find('.pp-read-more-notice-card').hide();
                $targetContainer.find('.pp-teaser-redirect-settings').hide();
            }
            
            // Show/hide number input based on type
            if (teaserType == 'x_chars') {
                $targetContainer.find('.pp-num-chars-setting').show();
            }
        }
    }

    // Run progressive UI initialization
    initializeProgressiveUIVisibility();

    // Redirect settings handlers for progressive UI
    $('#teaser_redirect_anon').on('change', function() {
        var $pageSelect = $('#teaser_redirect_anon_page').closest('.pp-select-dynamic-wrapper');
        if ($(this).val() == '(select)') {
            $pageSelect.show();
        } else {
            $pageSelect.hide();
            $('#teaser_redirect_anon_page').val('');
        }
    });

    $('#teaser_redirect').on('change', function() {
        var $pageSelect = $('#teaser_redirect_page').closest('.pp-select-dynamic-wrapper');
        if ($(this).val() == '(select)') {
            $pageSelect.show();
        } else {
            $pageSelect.hide();
            $('#teaser_redirect_page').val('');
        }
    });

    // Login form shortcode insertion for progressive UI
    $('.pp-add-login-form a').on('click', function(e) {
        e.preventDefault();
        
        // Find the editor - works for both table-based (td) and div-based layouts
        var $container = $(this).closest('td, div');
        var editorId = $container.find('.wp-editor-area').attr('id');
        
        if (editorId) {
            // Check if TinyMCE is active for this editor
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId) && !tinymce.get(editorId).isHidden()) {
                var editor = tinymce.get(editorId);
                var content = editor.getContent();
                
                if (content.indexOf('[login_form]') === -1) {
                    editor.setContent(content + '[login_form]');
                }
            } else {
                // Fallback to textarea (when in Text/HTML mode)
                var $textarea = $('#' + editorId);
                if ($textarea.length && $textarea.val().indexOf('[login_form]') === -1) {
                    $textarea.val($textarea.val() + '[login_form]');
                }
            }
        }
        return false;
    });

    // PRO Feature Handling
    // Prevent selecting disabled PRO options
    $(document).on('change', 'select.pp-teaser-type-select', function() {
        var $select = $(this);
        var $selected = $select.find('option:selected');
        
        if ($selected.is(':disabled')) {
            // Revert to previous valid selection
            var $firstEnabled = $select.find('option:not(:disabled)').first();
            $select.val($firstEnabled.val());
            
            // Show upgrade notice
            alert('This feature is only available in PublishPress Permissions PRO.\n\nUpgrade now to unlock advanced teaser types including Read More links, excerpts, and redirects.');
        }
    });

    // Prevent interaction with disabled post type options
    $(document).on('change', '#pp_current_post_type', function() {
        var $select = $(this);
        var $selected = $select.find('option:selected');
        
        if ($selected.is(':disabled')) {
            // Revert to first enabled option
            var $firstEnabled = $select.find('option:not(:disabled)').first();
            $select.val($firstEnabled.val()).trigger('change');
            
            // Show upgrade notice
            alert('This post type is only available in PublishPress Permissions PRO.\n\nUpgrade now to apply teasers to Pages, WooCommerce Products, and all custom post types.');
        }
    });

    // Handle disabled radio buttons for user application
    $(document).on('click', 'input[type="radio"][name^="tease_logged_only"]:disabled', function(e) {
        e.preventDefault();
        alert('User-specific targeting is only available in PublishPress Permissions PRO.\n\nUpgrade now to show different teaser messages to logged-in vs anonymous users.');
        return false;
    });

    // PRO badge click handlers
    $(document).on('click', '.pp-pro-badge', function(e) {
        e.stopPropagation();
        var upgradeUrl = 'https://publishpress.com/links/permissions-banner';
        if (confirm('This feature is only available in PublishPress Permissions PRO.\n\nWould you like to learn more about upgrading?')) {
            window.open(upgradeUrl, '_blank');
        }
    });

    // Style disabled options
    $('select option:disabled').css({
        'color': '#999',
        'font-style': 'italic'
    });

    $('input[type="radio"]:disabled').each(function() {
        $(this).closest('label').css({
            'opacity': '0.5',
            'cursor': 'not-allowed'
        });
    });
});

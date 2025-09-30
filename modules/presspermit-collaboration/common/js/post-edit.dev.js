jQuery(document).ready(function ($) {
    $(document).on('click', '#authordiv a.pp-add-author', function () {
        $('#post_author_override').hide();
        $('#pp_author_search').show();
        $('#authordiv a.pp-add-author').hide();
        $('#authordiv a.pp-close-add-author').show();
        $('#agent_search_text_select-author').focus();
        return false;
    });

    $(document).on('click', '#authordiv a.pp-close-add-author', function () {
        $('#pp_author_search').hide();
        $('#authordiv a.pp-close-add-author').hide();
        $('#authordiv a.pp-add-author').show();
        $('#post_author_override').show();
        return false;
    });

    $(document).on('click', '#select_agents_select-author', function () {
        var selected_id = $('#agent_results_select-author').val();
        if (selected_id) {
            if (!$('#post_author_override option[value="' + selected_id + '"]').prop('selected', true).length) {
                var selected_name = $('#agent_results_select-author option:selected').html();

                $('#post_author_override').append('<option value=' + selected_id + '>' + selected_name + '</option>');
                $('#post_author_override option[value="' + selected_id + '"]').prop('selected', true);
            }
        }

        $('#authordiv a.pp-close-add-author').trigger('click');
        return false;
    });

    $(document).on('jchange', '#agent_results_select-author', function () {
        if ($('#agent_results_select-author option').length) {
            $('#agent_results_select-author').show();
            $('#select_agents_select-author').show();
        }
    });

    // Set post visibility in Gutenberg based on default_privacy and force_default_privacy
    if (typeof window.ppEditorConfig !== 'undefined' && window.ppEditorConfig.defaultPrivacy) {
        var defaultPrivacy = window.ppEditorConfig.defaultPrivacy;
        var forceDefaultPrivacy = window.ppEditorConfig.forceDefaultPrivacy;
        var visibility;
        switch (defaultPrivacy) {
            case 'private':
                visibility = 'private';
                break;
            default:
                visibility = 'draft';
                break;
            // Add more cases if needed
        }

        // For Block Editor - set initial status
        if (visibility && typeof wp !== 'undefined') {
            // Wait for Gutenberg editor to be fully ready
            var applyDefaultPrivacy = function() {
                if (!wp.data || !wp.data.select || !wp.data.dispatch) {
                    setTimeout(applyDefaultPrivacy, 100);
                    return;
                }
                
                // Check if editor is fully loaded by verifying we have a post type
                var currentPost = wp.data.select('core/editor').getCurrentPost();
                if (!currentPost || !currentPost.type) {
                    setTimeout(applyDefaultPrivacy, 100);
                    return;
                }
                
                try {
                    // Set the status to the configured default privacy
                    wp.data.dispatch('core/editor').editPost({ status: visibility });
                    
                    var currentTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
                    if (currentTitle === 'Auto Draft') {
                        wp.data.dispatch('core/editor').editPost({ title: '' });
                    }
                    if (wp.data.dispatch('core/editor').savePost) {
                        wp.data.dispatch('core/editor').savePost();
                    }
                    if (forceDefaultPrivacy) {
                        // Subscribe to post changes and revert any status changes
                        var previousStatus = visibility;
                        wp.data.subscribe(function() {
                            var currentPost = wp.data.select('core/editor').getCurrentPost();
                            if (currentPost && currentPost.status && currentPost.status !== previousStatus) {
                                // Revert the status change
                                wp.data.dispatch('core/editor').editPost({ status: previousStatus });
                            }
                        });
                        
                        // Lock visibility controls while keeping them visible in Gutenberg
                        var lockPostPanel = function() {
                            // Disable visibility controls and add informative styling
                            var style = document.createElement('style');
                            style.textContent = `
                                /* Disable visibility controls but keep them visible */
                                .editor-post-status .components-panel__row:has([aria-label*="Visibility"]) *,
                                .editor-post-status .components-panel__row:has([aria-label*="visibility"]) *,
                                .editor-post-visibility *,
                                .components-button[aria-label*="Change post visibility"],
                                .components-button[aria-label*="visibility"],
                                .editor-post-visibility__toggle,
                                .editor-post-visibility button,
                                .edit-post-post-status button,
                                .edit-post-post-status .components-button,
                                .editor-post-status button,
                                .editor-post-status .components-button,
                                .components-panel__row button[aria-expanded],
                                .edit-post-post-status .components-panel__row:nth-child(2) *,
                                .edit-post-post-status .components-panel__row:nth-child(2) button,
                                .edit-post-post-status .components-panel__row:nth-child(2) .components-button {
                                    pointer-events: none !important;
                                    opacity: 0.5 !important;
                                    cursor: not-allowed !important;
                                }
                                
                                /* Style the locked panel */
                                .editor-post-status .components-panel__row:has([aria-label*="Visibility"]),
                                .editor-post-status .components-panel__row:has([aria-label*="visibility"]),
                                .edit-post-post-status .components-panel__row:nth-child(2) {
                                    background-color: #fff3cd !important;
                                    border: 1px solid #ffeaa7 !important;
                                    border-radius: 4px !important;
                                    position: relative !important;
                                }
                                
                                /* Add lock notice */
                                .pp-visibility-lock-notice {
                                    background: #fff3cd;
                                    border: 1px solid #ffeaa7;
                                    border-radius: 4px;
                                    padding: 8px 12px;
                                    margin: 8px 0;
                                    font-size: 12px;
                                    color: #856404;
                                    position: relative;
                                }
                            `;
                            document.head.appendChild(style);
                            
                            // Add informative notice to the post status panel
                            var addLockNotice = function() {
                                if ($('.pp-visibility-lock-notice').length === 0) {
                                    // Get translated strings from window object or use defaults
                                    var translations = window.ppEditorConfig && window.ppEditorConfig.translations || {};
                                    var visibilityLockedText = translations.visibilityLocked || 'Visibility Locked:';
                                    var visibilitySetToText = translations.visibilitySetTo || 'Post visibility is set to';
                                    var cannotChangeText = translations.cannotChange || 'and cannot be changed due to admin settings.';
                                    var contactAdminText = translations.contactAdmin || 'Contact your administrator to modify this setting.';

                                    var notice = '<div class="pp-visibility-lock-notice"><span class="dashicons dashicons-lock"></span>' +
                                        '<strong>' + visibilityLockedText + '</strong> ' + visibilitySetToText + ' "' + translations[defaultPrivacy] + '" ' +
                                        cannotChangeText + ' ' +
                                        '<br><small>' + contactAdminText + '</small>' +
                                        '</div>';
                                    
                                    // Try to add the notice to different possible locations
                                    if ($('.edit-post-post-status').length) {
                                        $('.edit-post-post-status').prepend(notice);
                                    } else if ($('.editor-post-status').length) {
                                        $('.editor-post-status').prepend(notice);
                                    } else if ($('.components-panel').length) {
                                        $('.components-panel').first().prepend(notice);
                                    }
                                }
                            };
                            
                            // Add notice with intervals to catch dynamic content
                            setTimeout(addLockNotice, 500);
                            setTimeout(addLockNotice, 1500);
                            setTimeout(addLockNotice, 3000);
                            
                            // Also add notice when panels are opened
                            $(document).on('click', '.components-panel__body-toggle', function() {
                                setTimeout(addLockNotice, 100);
                            });
                            
                            // Prevent clicks on visibility buttons as extra protection
                            var preventVisibilityClicks = function() {
                                // Target all possible visibility-related buttons and prevent their clicks
                                var visibilitySelectors = [
                                    '.editor-post-visibility button',
                                    '.editor-post-visibility .components-button',
                                    '.editor-post-visibility__toggle',
                                    '.components-button[aria-label*="visibility"]',
                                    '.components-button[aria-label*="Visibility"]',
                                    '.components-button[aria-label*="Change post visibility"]',
                                    '.edit-post-post-status button',
                                    '.edit-post-post-status .components-button',
                                    '.editor-post-status button',
                                    '.editor-post-status .components-button',
                                    '.components-panel__row button[aria-expanded]'
                                ];
                                
                                visibilitySelectors.forEach(function(selector) {
                                    $(document).off('click', selector).on('click', selector, function(e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        e.stopImmediatePropagation();
                                        return false;
                                    });
                                });
                            };
                            
                            // Apply click prevention immediately and on intervals
                            preventVisibilityClicks();
                            setTimeout(preventVisibilityClicks, 1000);
                            setTimeout(preventVisibilityClicks, 3000);
                        };
                        
                        // Apply panel locking after a short delay to ensure UI is ready
                        setTimeout(lockPostPanel, 300);
                    }
                } catch (e) {
                    console.error('Error applying default privacy:', e);
                    // Retry after a delay if there's still an error
                    setTimeout(applyDefaultPrivacy, 500);
                }
            };
            
            // Start checking for editor readiness
            applyDefaultPrivacy();
        }
    }
});
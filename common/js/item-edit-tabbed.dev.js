/**
 * Tabbed Metabox JavaScript for PublishPress Permissions
 * Handles tab switching for operations in the permissions metabox
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        initTabbedMetabox();
    });

    /**
     * Initialize tabbed metabox functionality
     */
    function initTabbedMetabox() {
        // Main operation tab switching
        $('.pp-operation-tab').on('click', function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var targetPane = $tab.data('target');
            var $container = $tab.closest('.pp-tabbed-metabox');
            
            // Remove active state from all tabs and panes in this container
            $container.find('.pp-operation-tab').removeClass('active');
            $container.find('.pp-tab-pane').removeClass('active');
            
            // Add active state to clicked tab and corresponding pane
            $tab.addClass('active');
            $container.find('#' + targetPane).addClass('active');
            
            // Store active tab in localStorage for persistence
            var metaboxId = $container.closest('.postbox').attr('id');
            if (metaboxId) {
                localStorage.setItem('pp_active_tab_' + metaboxId, targetPane);
            }
        });

        // Agent type sub-tab switching (Roles & Groups / Users)
        $(document).on('click', '.pp-agent-type-tab', function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var targetContent = $tab.data('agent-target');
            var $section = $tab.closest('.pp-tab-pane');
            
            // Remove active state from all sub-tabs and content in this section
            $section.find('.pp-agent-type-tab').removeClass('active');
            $section.find('.pp-agent-type-content').removeClass('active');
            
            // Add active state to clicked tab and corresponding content
            $tab.addClass('active');
            $section.find('#' + targetContent).addClass('active');
            
            // Store in localStorage
            var metaboxId = $section.closest('.postbox').attr('id');
            var operationTab = $section.attr('id');
            if (metaboxId && operationTab) {
                localStorage.setItem('pp_active_agent_tab_' + metaboxId + '_' + operationTab, targetContent);
            }
        });
        
        // Restore active tabs from localStorage on page load
        $('.pp-tabbed-metabox').each(function() {
            var $container = $(this);
            var metaboxId = $container.closest('.postbox').attr('id');
            
            if (metaboxId) {
                // Restore main operation tab
                var savedTab = localStorage.getItem('pp_active_tab_' + metaboxId);
                
                if (savedTab && $container.find('#' + savedTab).length) {
                    // Activate saved tab
                    $container.find('.pp-operation-tab').removeClass('active');
                    $container.find('.pp-tab-pane').removeClass('active');
                    
                    $container.find('.pp-operation-tab[data-target="' + savedTab + '"]').addClass('active');
                    $container.find('#' + savedTab).addClass('active');
                }

                // Restore agent type sub-tabs for each operation
                $container.find('.pp-tab-pane').each(function() {
                    var $pane = $(this);
                    var operationTab = $pane.attr('id');
                    var savedAgentTab = localStorage.getItem('pp_active_agent_tab_' + metaboxId + '_' + operationTab);
                    
                    if (savedAgentTab && $pane.find('#' + savedAgentTab).length) {
                        $pane.find('.pp-agent-type-tab').removeClass('active');
                        $pane.find('.pp-agent-type-content').removeClass('active');
                        
                        $pane.find('.pp-agent-type-tab[data-agent-target="' + savedAgentTab + '"]').addClass('active');
                        $pane.find('#' + savedAgentTab).addClass('active');
                    }
                });
            }
        });
        
        // Handle agent type switching within tabs (preserve existing functionality)
        $('.pp-tabbed-metabox').on('click', '.agp-agent a', function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var targetClass = $link.attr('class');
            var $tabPane = $link.closest('.pp-tab-pane');
            
            // Hide all agent type divs in this tab
            $tabPane.find('[id*="-wp_role"], [id*="-pp_group"], [id*="-user"]').addClass('hide-if-js');
            
            // Show the selected agent type
            $tabPane.find('#' + targetClass).removeClass('hide-if-js');
            
            // Update active state on agent tabs
            $link.closest('ul').find('li').removeClass('agp-selected_agent').addClass('agp-unselected_agent');
            $link.closest('li').removeClass('agp-unselected_agent').addClass('agp-selected_agent');
        });

        // Handle permission select dropdown changes - update CSS class based on value
        $(document).on('change', '.pp-permission-select select', function() {
            var $select = $(this);
            var selectedValue = $select.val();
            
            // Remove all possible CSS classes
            $select.removeClass('pp-def pp-no pp-no2 pp-yes pp-yes2');
            
            // Add CSS class based on selected value
            // Map values to CSS classes (matching ItemExceptionsRenderUI logic)
            var cssClass = 'pp-def'; // default
            
            if (selectedValue === '0') {
                cssClass = 'pp-no2';
            } else if (selectedValue === '1') {
                cssClass = 'pp-yes';
            } else if (selectedValue === '2') {
                cssClass = 'pp-yes2';
            } else if (selectedValue === '') {
                cssClass = 'pp-def';
            }

            $select.addClass(cssClass);
        });

        // Bulk Actions: Select All/None functionality
        $(document).on('change', '.pp-select-all-checkbox', function() {
            var $checkbox = $(this);
            var $card = $checkbox.closest('.pp-permission-card');
            var isChecked = $checkbox.prop('checked');
            
            // Update all visible and enabled item checkboxes in this card
            $card.find('.pp-permission-list-item:visible .pp-item-checkbox:not(:disabled)').prop('checked', isChecked);
            
            // Update counter
            updateBulkCounter($card);
        });

        // Bulk Actions: Individual checkbox change
        $(document).on('change', '.pp-item-checkbox', function() {
            var $checkbox = $(this);
            var $card = $checkbox.closest('.pp-permission-card');
            
            // Update select all checkbox state (only consider visible items)
            var totalCheckboxes = $card.find('.pp-permission-list-item:visible .pp-item-checkbox:not(:disabled)').length;
            var checkedCheckboxes = $card.find('.pp-permission-list-item:visible .pp-item-checkbox:not(:disabled):checked').length;
            
            $card.find('.pp-select-all-checkbox').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
            
            // Update counter
            updateBulkCounter($card);
        });

        // Bulk Actions: Apply bulk action
        $(document).on('click', '.pp-bulk-apply', function() {
            var $button = $(this);
            var $card = $button.closest('.pp-permission-card');
            var $actionSelect = $card.find('.pp-bulk-action-select');
            var action = $actionSelect.val();
            
            if (!action) {
                alert(ppPermissions.alertSelectAction);
                return;
            }
            
            // Get all checked items
            var $checkedItems = $card.find('.pp-item-checkbox:checked');
            
            if ($checkedItems.length === 0) {
                alert(ppPermissions.alertSelectItem);
                return;
            }
            
            // Handle remove action separately
            if (action === 'remove') {
                if (confirm(ppPermissions.confirmBulkRemove)) {
                    $checkedItems.each(function() {
                        var $checkbox = $(this);
                        var $item = $checkbox.closest('.pp-permission-list-item');
                        removeUserItem($item);
                    });
                    
                    // Reset bulk action dropdown and checkboxes
                    $actionSelect.val('');
                    $card.find('.pp-select-all-checkbox').prop('checked', false);
                    
                    // Update counter
                    updateBulkCounter($card);
                }
                return;
            }
            
            // Map actions to select values
            var valueMap = {
                'enable': '2',
                'block': '0',
                'default': '',
                'unblock': '1'
            };
            
            var newValue = valueMap[action];
            
            // Apply to all checked items
            $checkedItems.each(function() {
                var $checkbox = $(this);
                var $item = $checkbox.closest('.pp-permission-list-item');
                var $select = $item.find('.pp-permission-select select');
                
                // Remove any existing bulk action warning
                $item.find('.pp-bulk-action-warning').remove();
                
                // Check if the option exists in the select
                var optionExists = $select.find('option[value="' + newValue + '"]').length > 0;
                
                if (optionExists) {
                    // Change the value
                    $select.val(newValue);
                    
                    // Trigger change event to update CSS classes
                    $select.trigger('change');
                } else {
                    // Option doesn't exist - show warning message
                    // Detect item type to show appropriate message
                    var $typeBadge = $item.find('.pp-type-badge');
                    var isRole = $typeBadge.hasClass('pp-type-role');
                    var isLoginState = $typeBadge.hasClass('pp-type-login-state');
                    
                    if ('block' === action && isRole) {
                        var firstOptionText = $select.find('option').first().text();
                        if (firstOptionText.toLowerCase().includes('block')) {
                            var firstValue = $select.find('option').first().val();

                            // Change the value to the first option (which is likely the closest to "Block" for roles)
                            $select.val(firstValue);
                            
                            // Trigger change event to update CSS classes
                            $select.trigger('change');
                        }
                    } else {
                        // Choose appropriate message based on item type
                        var warningMessage = (typeof ppPermissions !== 'undefined' && ppPermissions.bulkActionNotAvailableNonUsers) 
                            ? ppPermissions.bulkActionNotAvailableNonUsers 
                            : "Editing can't be granted to non-users.";
                        
                        // Create warning element
                        var $warning = $('<div>')
                            .addClass('pp-bulk-action-warning')
                            .append($('<span>').addClass('dashicons dashicons-warning'))
                            .append($('<span>').addClass('pp-warning-text').text(warningMessage));
                        
                        // Insert warning into permission control
                        $item.find('.pp-permission-control').append($warning);
                        
                        // Auto-remove warning after 5 seconds
                        setTimeout(function() {
                            $warning.fadeOut(300, function() {
                                $(this).remove();
                            });
                        }, 5000);
                    }   
                }
            });
            
            // Reset bulk action dropdown
            $actionSelect.val('');
            
            // Uncheck all checkboxes
            $checkedItems.prop('checked', false);
            $card.find('.pp-select-all-checkbox').prop('checked', false);
            
            // Update counter
            updateBulkCounter($card);
            
            // Regenerate dynamic filters after bulk action
            var $agentContent = $card.closest('.pp-agent-type-content');
            if ($agentContent.length) {
                setTimeout(function() {
                    generateDynamicFilters($agentContent);
                }, 100);
            }
        });

        /**
         * Update the bulk selection counter
         */
        function updateBulkCounter($card) {
            var checkedCount = $card.find('.pp-item-checkbox:checked').length;
            $card.find('.pp-selected-count').text(checkedCount);
        }

        // ============================
        // Individual Delete Button (Users Only)
        // ============================
        
        /**
         * Handle individual delete button click
         */
        $(document).on('click', '.pp-delete-item', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $button = $(this);
            var $item = $button.closest('.pp-permission-list-item');
            var userName = $item.find('.item-name label').text();
            
            if (confirm(ppPermissions.confirmDeleteItem.replace('%s', userName))) {
                removeUserItem($item);
            }
        });

        /**
         * Remove a user item from the list
         * 
         * Instead of removing from DOM, we hide it and set value to empty.
         * This ensures the form still sends the user ID with empty value,
         * so the backend knows to remove the exception (backward compatible).
         */
        function removeUserItem($item) {
            var $card = $item.closest('.pp-permission-card');
            var $list = $item.closest('.pp-permission-list');
            
            // Uncheck if checked (to update counter)
            $item.find('.pp-item-checkbox').prop('checked', false);
            
            // Set permission select to empty value (tells backend to remove exception)
            // NOTE: Do NOT disable the select - disabled fields are not submitted in forms!
            $item.find('select').val('');
            
            // Add removed class and animate fadeout
            $item.addClass('pp-item-removed');
            $item.fadeOut(300, function() {
                // Hide the item but keep it in DOM so form still submits the value
                $(this).hide();
                
                // Check if there are any visible items left
                var $visibleItems = $list.find('.pp-permission-list-item:visible');
                if ($visibleItems.length === 0) {
                    // Remove any existing empty state first
                    $list.find('.pp-empty-state').remove();
                    var emptyMessage = $list.data('empty-message') || '';
                    
                    // Show empty state message
                    var $emptyState = $('<div>').addClass('pp-empty-state')
                        .append($('<span>').addClass('dashicons dashicons-info'))
                        .append($('<p>').text(emptyMessage));

                    $list.prepend($emptyState);
                }
                
                // Update bulk counter and select-all state
                if ($card.length) {
                    updateBulkCounter($card);
                    
                    var totalCheckboxes = $card.find('.pp-permission-list-item:visible .pp-item-checkbox:not(:disabled)').length;
                    var checkedCheckboxes = $card.find('.pp-permission-list-item:visible .pp-item-checkbox:not(:disabled):checked').length;
                    $card.find('.pp-select-all-checkbox').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
                }
                
                // Update operation tab badge
                var $agentContent = $item.closest('.pp-agent-type-content');
                if ($agentContent.length) {
                    // Regenerate dynamic filters
                    setTimeout(function() {
                        generateDynamicFilters($agentContent);
                    }, 100);
                }
            });
            
            // Mark form as changed
            if (typeof markFormAsChanged === 'function') {
                markFormAsChanged();
            }
        }
    }

    // ============================
    // Search Functionality
    // ============================

    /**
     * Filter permission list items based on search query
     */
    $('.pp-search-input:not(.pp-user-search-select2)').on('input', function() {
        var $searchBox = $(this).closest('.pp-search-box');
        var $clearBtn = $searchBox.find('.pp-search-clear');
        var $contentArea = $(this).closest('.pp-agent-type-content');
        var $card = $(this).closest('.pp-permission-card, .pp-agent-type-content');
        var $list = $card.find('.pp-permission-list');
        var searchQuery = $(this).val().toLowerCase().trim();
        
        // Get active filter if any
        var activeFilter = $contentArea.data('active-filter') || 'all';

        // Show/hide clear button
        if (searchQuery.length > 0) {
            $clearBtn.show();
        } else {
            $clearBtn.hide();
        }

        // Filter list items (respecting both search and filter)
        var visibleCount = 0;
        $list.find('.pp-permission-list-item').each(function() {
            var $item = $(this);
            
            // Skip removed items
            if ($item.hasClass('pp-item-removed')) {
                return;
            }
            
            var itemName = $item.find('.item-name label').text().toLowerCase();
            var matchesSearch = (searchQuery.length === 0 || itemName.indexOf(searchQuery) !== -1);
            
            // Check if item matches active filter
            var matchesFilter = true;
            if (activeFilter !== 'all' && !$item.hasClass('pp-filtered-by-pill')) {
                // Item was already filtered out by pill filter
                matchesFilter = false;
            }

            if (matchesSearch && matchesFilter) {
                $item.show();
                visibleCount++;
            } else {
                $item.hide();
                // Uncheck hidden items
                $item.find('.pp-item-checkbox').prop('checked', false);
            }
        });

        // Show/hide "no results" message
        var $noResults = $list.find('.pp-no-search-results');
        if (searchQuery.length > 0 && visibleCount === 0) {
            if ($noResults.length === 0) {
                $list.append('<div class="pp-no-search-results"><span class="dashicons dashicons-search"></span><p>No items found</p></div>');
            } else {
                $noResults.show();
            }
        } else {
            $noResults.hide();
        }

        // Update bulk counter if it exists
        if ($card.find('.pp-bulk-counter').length) {
            var checkedCount = $list.find('.pp-item-checkbox:checked').length;
            $card.find('.pp-selected-count').text(checkedCount);
        }

        // Update select all checkbox state
        updateSelectAllState($card);
    });

    /**
     * Clear search when clear button is clicked
     */
    $('.pp-search-clear').on('click', function() {
        var $searchBox = $(this).closest('.pp-search-box');
        var $input = $searchBox.find('.pp-search-input');
        var $contentArea = $(this).closest('.pp-agent-type-content');
        var $card = $(this).closest('.pp-permission-card, .pp-agent-type-content');
        var $list = $card.find('.pp-permission-list');
        
        // Get active filter if any
        var activeFilter = $contentArea.data('active-filter') || 'all';

        // Clear input and hide clear button
        $input.val('').focus();
        $(this).hide();

        // Show items based on active filter (not all items if filter is active)
        $list.find('.pp-permission-list-item').each(function() {
            var $item = $(this);
            
            // Skip removed items
            if ($item.hasClass('pp-item-removed')) {
                return;
            }
            
            if (activeFilter === 'all' || !$item.hasClass('pp-filtered-by-pill')) {
                $item.show();
            }
        });
        
        // Hide search "no results" message
        $list.find('.pp-no-search-results').hide();

        // Update select all checkbox state
        updateSelectAllState($card);
    });

    /**
     * Update select all checkbox state based on visible items
     */
    function updateSelectAllState($card) {
        var $selectAll = $card.find('.pp-select-all-checkbox');
        var $visibleCheckboxes = $card.find('.pp-permission-list-item:visible .pp-item-checkbox:not(:disabled)');
        var $checkedVisible = $visibleCheckboxes.filter(':checked');

        if ($visibleCheckboxes.length > 0 && $checkedVisible.length === $visibleCheckboxes.length) {
            $selectAll.prop('checked', true);
        } else {
            $selectAll.prop('checked', false);
        }
    }

    // ============================
    // Select2 User Search (Users Tab)
    // ============================

    /**
     * Initialize Select2 for user search in Users tab
     */
    function initUserSearchSelect2() {
        // Check if Select2 is loaded
        if (typeof jQuery.fn.select2 === 'undefined') {
            console.warn('Select2 not loaded, retrying in 500ms...');
            setTimeout(initUserSearchSelect2, 500);
            return;
        }

        $('.pp-user-search-select2').each(function() {
            var $select = $(this);
            
            // Skip if already initialized
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            var op = $select.data('op');
            var forItemType = $select.data('for-item-type');
            var agentType = $select.data('agent-type');
            var ajaxUrl = typeof PPAgentSelect !== 'undefined' ? PPAgentSelect.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');

            if (!ajaxUrl) {
                console.error('AJAX URL not found for Select2 initialization');
                return;
            }

            var placeholderText = $select.data('placeholder') || 'Search users...';
            
            $select.select2({
                placeholder: placeholderText,
                allowClear: true,
                minimumInputLength: 0,
                width: '100%',
                dropdownParent: $select.closest('.pp-search-box-select2'),
                ajax: {
                    url: ajaxUrl,
                    dataType: 'html',
                    delay: 250,
                    data: function(params) {
                        return {
                            pp_agent_search: params.term || '',
                            pp_agent_type: agentType,
                            pp_topic: op + ':' + forItemType + ':' + agentType,
                            pp_operation: op,
                            pp_omit_admins: typeof ppListbox !== 'undefined' ? ppListbox.omit_admins : '0',
                            pp_metagroups: '0'
                        };
                    },
                    processResults: function(data) {
                        var options = [];
                        var currentValues = [];

                        // Get currently configured users
                        $select.closest('.pp-agent-type-content').find('.pp-permission-list-item').each(function() {
                            var userId = $(this).data('agent-id');
                            if (userId) {
                                currentValues.push(userId.toString());
                            }
                        });

                        // Parse AJAX response (HTML options)
                        $(data).filter('option').each(function() {
                            var id = $(this).val();
                            if (!currentValues.includes(id)) {
                                options.push({
                                    id: id,
                                    text: $(this).text()
                                });
                            }
                        });

                        return { results: options };
                    },
                    cache: true
                }
            });

            // Handle user selection
            $select.on('select2:select', function(e) {
                var userId = e.params.data.id;
                var userName = e.params.data.text;

                // Add user to the permission list
                addUserToList(userId, userName, op, forItemType, agentType, $select);

                // Clear selection (Select2 multi-select)
                $select.val(null).trigger('change');

                // Mark form as changed
                markFormAsChanged();
            });

            // Force placeholder to show on initial load
            setTimeout(function() {
                var $searchField = $select.next('.select2-container').find('.select2-search__field');
                if ($searchField.length) {
                    $searchField.attr('placeholder', placeholderText);
                }
            }, 100);
        });
    }

    /**
     * Add user to the permission list
     * 
     * If a user was previously removed (hidden but still in DOM),
     * we restore it instead of creating a duplicate.
     */
    function addUserToList(userId, userName, op, forItemType, agentType, $select) {
        var $agentContent = $select.closest('.pp-agent-type-content');
        var $list = $agentContent.find('.pp-permission-list');
        var itemIdAttr = agentType + '-' + userId;
        var defaultValue = '2'; // Enable by default
        var cssClass = 'pp-yes2'; // CSS class for enabled state
        
        // Check if hierarchical permissions are needed (look for existing items with children selects)
        var hasHierarchical = $list.find('.pp-permission-list-item .pp-children-select').length > 0;

        // Check if this user already exists in the list (possibly marked as removed)
        var $existingItem = $list.find('.pp-permission-list-item[data-agent-type="' + agentType + '"][data-agent-id="' + userId + '"]');
        
        if ($existingItem.length > 0) {
            // User exists - restore it if it was removed
            $existingItem.removeClass('pp-item-removed');
            $existingItem.find('select').val(defaultValue).removeClass().addClass(cssClass);
            $existingItem.find('.pp-item-checkbox').prop('checked', false);
            
            // Remove empty state if present
            $list.find('.pp-empty-state').remove();
            
            // Show the item with animation
            $existingItem.fadeIn(300, function() {
                $(this).addClass('pp-new-item');
                setTimeout(function() {
                    $existingItem.removeClass('pp-new-item');
                }, 500);
            });
            
            // Mark form as changed
            if (typeof markFormAsChanged === 'function') {
                markFormAsChanged();
            }
            
            return; // Exit early - item restored
        }

        // Create a new list item (user doesn't exist yet)
        var $newItem = $('<div>')
            .addClass('pp-permission-list-item pp-new-item')
            .attr('data-agent-type', agentType)
            .attr('data-agent-id', userId)
            .css('display', 'none');

        // Item checkbox
        var $checkbox = $('<div>')
            .addClass('item-checkbox')
            .append(
                $('<input>')
                    .attr('type', 'checkbox')
                    .addClass('pp-item-checkbox')
                    .attr('id', 'pp-item-' + itemIdAttr)
            );

        // Item name
        var $itemName = $('<div>')
            .addClass('item-name')
            .attr('title', userName)
            .append(
                $('<label>')
                    .attr('for', 'pp-item-' + itemIdAttr)
                    .text(userName)
            );

        // Permission control container
        var $permissionControl = $('<div>').addClass('pp-permission-control');
        
        // Create "This Category" / "item" select
        var selectNameItem = 'pp_exceptions[' + forItemType + '][' + op + '][' + agentType + '][item][' + userId + ']';
        var $itemSelect = $('<div>')
            .addClass('pp-permission-select pp-item-select')
            .append(
                hasHierarchical ? $('<label>').addClass('pp-select-label').text('This Category') : null
            )
            .append(
                $('<select>')
                    .attr('name', selectNameItem)
                    .addClass(cssClass)
                    .attr('autocomplete', 'off')
                    .append($('<option>').val('').addClass('pp-def').text('No setting'))
                    .append($('<option>').val('0').addClass('pp-no2').text('Blocked'))
                    .append($('<option>').val('2').addClass('pp-yes2').attr('selected', 'selected').text('Enabled'))
            );
        
        $permissionControl.append($itemSelect);
        
        // If hierarchical, create "Sub-Categories" / "children" select
        if (hasHierarchical) {
            var selectNameChildren = 'pp_exceptions[' + forItemType + '][' + op + '][' + agentType + '][children][' + userId + ']';
            var $childrenSelect = $('<div>')
                .addClass('pp-permission-select pp-children-select')
                .append(
                    $('<label>').addClass('pp-select-label').text('Sub-Categories')
                )
                .append(
                    $('<select>')
                        .attr('name', selectNameChildren)
                        .addClass('pp-def') // Default to "No setting" for children
                        .attr('autocomplete', 'off')
                        .append($('<option>').val('').addClass('pp-def').attr('selected', 'selected').text('No setting'))
                        .append($('<option>').val('0').addClass('pp-no2').text('Blocked'))
                        .append($('<option>').val('2').addClass('pp-yes2').text('Enabled'))
                );
            
            $permissionControl.append($childrenSelect);
        }
        
        // Add delete button
        $permissionControl.append(
            $('<button>')
                .attr('type', 'button')
                .addClass('pp-delete-item')
                .attr('title', ppPermissions.deleteItemTitle)
                .append($('<span>').addClass('dashicons dashicons-trash'))
        );

        // Assemble the item
        $newItem.append($checkbox).append($itemName).append($permissionControl);

        // Remove "no results" and empty state messages if they exist
        $list.find('.pp-no-search-results, .pp-empty-state').remove();

        // Add to beginning of list with animation
        $list.prepend($newItem);
        $newItem.slideDown(300, function() {
            // Scroll to the top to reveal the new item
            $list.animate({ scrollTop: 0 }, 300);
        });
        
        // Remove animation class after CSS animation completes (500ms)
        setTimeout(function() {
            $newItem.removeClass('pp-new-item');
        }, 600);
        
        // Regenerate dynamic filters
        setTimeout(function() {
            generateDynamicFilters($agentContent);
            
            // Reapply sort to maintain order
            reapplySortAfterAdd($agentContent);
        }, 100);
        
        // Show success notice
        showUserAddedNotice(userName, $select);
    }

    /**
     * Show notice that user was added
     */
    function showUserAddedNotice(userName, $select) {
        var $existingNotice = $select.closest('.pp-agent-type-content').find('.pp-user-added-notice');
        $existingNotice.remove();

        var $notice = $('<div>').addClass('pp-user-added-notice').hide();

        $notice
            .append($('<span>').addClass('dashicons dashicons-yes'))
            .append(document.createTextNode(ppPermissions.addedToList))
            .append($('<strong>').text(userName));

        $select.closest('.pp-search-box-select2').after($notice);
        $notice.fadeIn();

        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 4000);
    }

    /**
     * Mark form as changed (for WordPress unsaved changes warning)
     */
    function markFormAsChanged() {
        // Trigger WordPress's unsaved changes warning
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            try {
                if (wp.data.dispatch('core/editor')) {
                    wp.data.dispatch('core/editor').editPost({ modified: true });
                }
            } catch (e) {
                // Silently fail for classic editor
            }
        }

        // For classic editor - warn about unsaved changes
        window.onbeforeunload = function() {
            return 'You have unsaved changes.';
        };
    }

    /**
     * Clear the unsaved changes warning when form is submitted
     */
    function clearUnsavedWarning() {
        window.onbeforeunload = null;
    }

    // Clear warning on form submission (both term edit and post edit)
    $(document).ready(function() {
        // Term edit form (edittag form)
        $('form#edittag').on('submit', function() {
            clearUnsavedWarning();
        });
        
        // Post edit form (post form)
        $('form#post').on('submit', function() {
            clearUnsavedWarning();
        });
        
        // Also clear on Update/Publish button clicks
        $('#submit, #publish, #save-post').on('click', function() {
            clearUnsavedWarning();
        });
    });

    // Initialize on page load
    $(document).ready(function() {
        initUserSearchSelect2();
    });

    // Re-initialize when switching to Users tab (in case it was hidden during initial load)
    $(document).on('click', '.pp-agent-type-tab[data-agent-target*="pp-users-"]', function() {
        setTimeout(function() {
            initUserSearchSelect2();
        }, 100);
    });

    /**
     * Generate dynamic filter pills based on existing permission values
     * Scans all permission selects in the content area and creates filter buttons
     * only for values that actually exist in the current view
     */
    function generateDynamicFilters($contentArea) {
        var $filterContainer = $contentArea.find('.pp-permission-filters');
        
        if (!$filterContainer.length) {
            return;
        }

        // Target the pills container specifically (new structure)
        var $pillsContainer = $filterContainer.find('.pp-filter-pills-container');
        if (!$pillsContainer.length) {
            // Fallback for old structure (if any)
            $pillsContainer = $filterContainer;
        }

        // Map CSS classes to filter definitions
        var filterDefs = {
            'pp-def': { label: 'Default', icon: '' },
            'pp-no': { label: 'Blocked', icon: '' },
            'pp-no2': { label: 'Blocked', icon: '' },
            'pp-yes': { label: 'Enabled', icon: '' },
            'pp-yes2': { label: 'Enabled', icon: '' }
        };

        // Count occurrences of each permission type
        var counts = {
            'all': 0,
            'pp-def': 0,
            'pp-no': 0,
            'pp-no2': 0,
            'pp-yes': 0,
            'pp-yes2': 0,
            'role': 0,         // Count roles
            'group': 0,        // Count groups
            'login-state': 0   // Count login state badges
        };

        // Scan all permission selects (not just visible ones, so counts remain accurate during search)
        $contentArea.find('.pp-permission-list-item').each(function() {
            var $item = $(this);
            
            // Skip removed items
            if ($item.hasClass('pp-item-removed')) {
                return;
            }

            counts.all++;

            // Count by agent type
            var agentType = $item.attr('data-agent-type');
            if (agentType === 'wp_role') {
                // Exclude login state items (they have their own count)
                if (!$item.find('.pp-type-login-state').length) {
                    counts.role++;
                }
            } else if (agentType === 'pp_group') {
                counts.group++;
            }
            
            // Count by badge type
            if ($item.find('.pp-type-login-state').length) {
                counts['login-state']++;
            }

            // Count each ITEM once per permission type (not each select separately)
            // This prevents double-counting when an item has multiple selects with the same class
            $.each(['pp-def', 'pp-no', 'pp-no2', 'pp-yes', 'pp-yes2'], function(i, className) {
                if ($item.find('.pp-permission-select select.' + className).length > 0) {
                    counts[className]++;
                }
            });
        });

        // Merge duplicate filter counts (e.g., pp-no and pp-no2 both count as "Blocked")
        var mergedCounts = {
            'all': counts.all,
            'pp-def': counts['pp-def'],
            'blocked': counts['pp-no'] + counts['pp-no2'],
            'allowed': counts['pp-yes'] + counts['pp-yes2'],
            'role': counts.role,
            'group': counts.group,
            'login-state': counts['login-state']
        };

        // Clear existing filters
        $pillsContainer.empty();

        // Always add "All" filter first
        if (mergedCounts.all > 0) {
            var $allBtn = $('<button>')
                .attr('type', 'button')
                .addClass('pp-filter-btn active')
                .attr('data-filter', 'all')
                .append(document.createTextNode(ppPermissions.filterAll + ' '))
                .append($('<span>').addClass('pp-filter-count').text(mergedCounts.all));
            
            $pillsContainer.append($allBtn);
        }

        // Add filter buttons only for types that exist
        if (mergedCounts.blocked > 0) {
            var $btn = $('<button>')
                .attr('type', 'button')
                .addClass('pp-filter-btn')
                .attr('data-filter', 'blocked')
                .append(document.createTextNode(ppPermissions.filterBlocked + ' '))
                .append($('<span>').addClass('pp-filter-count').text(mergedCounts.blocked));
            
            $pillsContainer.append($btn);
        }

        if (mergedCounts.allowed > 0) {
            var $btn = $('<button>')
                .attr('type', 'button')
                .addClass('pp-filter-btn')
                .attr('data-filter', 'allowed')
                .append(document.createTextNode(ppPermissions.filterEnabled + ' '))
                .append($('<span>').addClass('pp-filter-count').text(mergedCounts.allowed));
            
            $pillsContainer.append($btn);
        }

        // Only show Default filter if it's not the same as All (i.e., there are other permission types)
        if (mergedCounts['pp-def'] > 0 && mergedCounts['pp-def'] !== mergedCounts.all) {
            var $btn = $('<button>')
                .attr('type', 'button')
                .addClass('pp-filter-btn')
                .attr('data-filter', 'pp-def')
                .append(document.createTextNode(ppPermissions.filterDefault + ' '))
                .append($('<span>').addClass('pp-filter-count').text(mergedCounts['pp-def']));
            
            $pillsContainer.append($btn);
        }

        // Add type filters (Role/Group/Login State)
        // Show individual type filters if there are multiple types
        var hasMultipleTypes = (mergedCounts.role > 0 ? 1 : 0) + (mergedCounts.group > 0 ? 1 : 0) + (mergedCounts['login-state'] > 0 ? 1 : 0) > 1;
        
        if (hasMultipleTypes) {
            // Add Role filter if roles exist
            if (mergedCounts.role > 0) {
                var $roleBtn = $('<button>')
                    .attr('type', 'button')
                    .addClass('pp-filter-btn pp-filter-type')
                    .attr('data-filter', 'role')
                    .attr('data-filter-type', 'agent-type')
                    .append(document.createTextNode(ppPermissions.filterRole + ' '))
                    .append($('<span>').addClass('pp-filter-count').text(mergedCounts.role));
                
                $pillsContainer.append($roleBtn);
            }

            // Add Group filter if groups exist
            if (mergedCounts.group > 0) {
                var $groupBtn = $('<button>')
                    .attr('type', 'button')
                    .addClass('pp-filter-btn pp-filter-type')
                    .attr('data-filter', 'group')
                    .attr('data-filter-type', 'agent-type')
                    .append(document.createTextNode(ppPermissions.filterGroup + ' '))
                    .append($('<span>').addClass('pp-filter-count').text(mergedCounts.group));
                
                $pillsContainer.append($groupBtn);
            }
            
            // Add Login State filter if login state items exist
            if (mergedCounts['login-state'] > 0) {
                var $loginStateBtn = $('<button>')
                    .attr('type', 'button')
                    .addClass('pp-filter-btn pp-filter-type')
                    .attr('data-filter', 'login-state')
                    .attr('data-filter-type', 'badge-type')
                    .append(document.createTextNode(ppPermissions.filterLoginState + ' '))
                    .append($('<span>').addClass('pp-filter-count').text(mergedCounts['login-state']));
                
                $pillsContainer.append($loginStateBtn);
            }
        }
    }

    /**
     * Handle filter button clicks
     */
    $(document).on('click', '.pp-filter-btn', function() {
        var $btn = $(this);
        var filter = $btn.attr('data-filter');
        var filterType = $btn.attr('data-filter-type'); // 'agent-type' or undefined
        var $filterContainer = $btn.closest('.pp-permission-filters');
        var $contentArea = $filterContainer.closest('.pp-agent-type-content');
        
        // Update active state
        $filterContainer.find('.pp-filter-btn').removeClass('active');
        $btn.addClass('active');
        
        // Store active filter in data attribute
        $contentArea.data('active-filter', filter);
        $contentArea.data('active-filter-type', filterType || 'permission');
        
        // Apply filter to items
        $contentArea.find('.pp-permission-list-item').each(function() {
            var $item = $(this);
            var show = false;
            
            // Skip removed items
            if ($item.hasClass('pp-item-removed')) {
                return;
            }
            
            if (filter === 'all') {
                show = true;
            } else if (filterType === 'agent-type') {
                // Filter by agent type (role or group)
                var agentType = $item.attr('data-agent-type');
                
                if (filter === 'role' && agentType === 'wp_role') {
                    // Exclude login state items (they have their own filter)
                    if (!$item.find('.pp-type-login-state').length) {
                        show = true;
                    }
                } else if (filter === 'group' && agentType === 'pp_group') {
                    show = true;
                }
            } else if (filterType === 'badge-type') {
                // Filter by badge type (login-state)
                if (filter === 'login-state' && $item.find('.pp-type-login-state').length) {
                    show = true;
                }
            } else {
                // Filter by permission status (blocked, allowed, default)
                // Check if item has any select with matching class
                $item.find('.pp-permission-select select').each(function() {
                    var $select = $(this);
                    
                    if (filter === 'blocked') {
                        if ($select.hasClass('pp-no') || $select.hasClass('pp-no2')) {
                            show = true;
                        }
                    } else if (filter === 'allowed') {
                        if ($select.hasClass('pp-yes') || $select.hasClass('pp-yes2')) {
                            show = true;
                        }
                    } else if ($select.hasClass(filter)) {
                        show = true;
                    }
                });
            }
            
            // Apply filter visibility (but respect search filter if active)
            if (show) {
                $item.removeClass('pp-filtered-by-pill');
                
                // Check if item should still be hidden by search
                var $searchInput = $contentArea.find('.pp-search-input:not(.pp-user-search-select2)');
                if ($searchInput.length && $searchInput.val().trim() !== '') {
                    // Let search handler control visibility
                    var searchTerm = $searchInput.val().toLowerCase();
                    var itemText = $item.find('.item-name label').text().toLowerCase();
                    $item.toggle(itemText.indexOf(searchTerm) > -1);
                } else {
                    $item.show();
                }
            } else {
                $item.addClass('pp-filtered-by-pill').hide();
            }
        });
        
        // Update "no results" message if all items are hidden
        var visibleCount = $contentArea.find('.pp-permission-list-item:visible').not('.pp-item-removed').length;
        var $noResults = $contentArea.find('.pp-no-filter-results');
        
        if (visibleCount === 0 && filter !== 'all') {
            if ($noResults.length === 0) {
                var filterLabel = $btn.text().replace(/\d+/, '').trim();
                $noResults = $('<div>')
                    .addClass('pp-no-filter-results')
                    .css({
                        gridColumn: '1 / -1',
                        textAlign: 'center',
                        padding: '40px 20px',
                        color: '#64748b'
                    })
                    .append(
                        $('<span>')
                            .addClass('dashicons dashicons-filter')
                            .css({
                                fontSize: '48px',
                                width: '48px',
                                height: '48px',
                                color: '#cbd5e1',
                                marginBottom: '12px'
                            })
                    )
                    .append(
                        $('<p>')
                            .css({
                                margin: 0,
                                fontSize: '14px',
                                fontWeight: 500
                            })
                            .text('No ' + filterLabel.toLowerCase() + ' items found')
                    );
                
                $contentArea.find('.pp-permission-list, .pp-permission-card-body').append($noResults);
            } else {
                $noResults.show();
            }
        } else {
            $noResults.remove();
        }
    });

    /**
     * Initialize filters when agent content becomes visible
     */
    $(document).on('click', '.pp-agent-type-tab', function() {
        var targetId = $(this).data('agent-target');
        var $contentArea = $('#' + targetId);
        
        setTimeout(function() {
            generateDynamicFilters($contentArea);
        }, 100);
    });

    /**
     * Regenerate filters when permissions change
     */
    $(document).on('change', '.pp-permission-select select', function() {
        var $select = $(this);
        var $contentArea = $select.closest('.pp-agent-type-content');
        
        // Small delay to allow CSS class update to complete
        setTimeout(function() {
            generateDynamicFilters($contentArea);
        }, 50);
    });

    /**
     * Initialize filters on page load for active tabs
     */
    $(document).ready(function() {
        $('.pp-agent-type-content.active').each(function() {
            generateDynamicFilters($(this));
        });
    });

    /**
     * Regenerate filters when switching operation tabs
     */
    $(document).on('click', '.pp-operation-tab', function() {
        var targetPane = $(this).data('target');
        var $tabPane = $('#' + targetPane);
        
        setTimeout(function() {
            $tabPane.find('.pp-agent-type-content.active').each(function() {
                generateDynamicFilters($(this));
            });
        }, 100);
    });

    /**
     * Sort permission items by specified criteria
     * 
     * @param {jQuery} $contentArea - The agent type content area containing items
     * @param {string} sortBy - Sort criteria: 'name-asc', 'name-desc', 'users-asc', 'users-desc'
     */
    function sortPermissionItems($contentArea, sortBy) {
        var $card = $contentArea.find('.pp-permission-card');
        var $list = $card.find('.pp-permission-list');
        var $items = $list.find('.pp-permission-list-item').not('.pp-item-removed');
        
        if ($items.length === 0) {
            return;
        }
        
        // Convert to array for sorting
        var items = $items.toArray();
        
        // Sort based on criteria
        items.sort(function(a, b) {
            var $a = $(a);
            var $b = $(b);
            var result = 0;
            
            if (sortBy.startsWith('name-')) {
                // Sort by name
                var nameA = ($a.attr('data-item-name') || '').toLowerCase();
                var nameB = ($b.attr('data-item-name') || '').toLowerCase();
                
                result = nameA.localeCompare(nameB);
                
                if (sortBy === 'name-desc') {
                    result = -result;
                }
            } else if (sortBy.startsWith('users-')) {
                // Sort by user count
                var countA = parseInt($a.attr('data-user-count') || '0', 10);
                var countB = parseInt($b.attr('data-user-count') || '0', 10);
                
                result = countA - countB;
                
                if (sortBy === 'users-desc') {
                    result = -result;
                }
                
                // Secondary sort by name (ascending) for equal counts
                if (result === 0) {
                    var nameA = ($a.attr('data-item-name') || '').toLowerCase();
                    var nameB = ($b.attr('data-item-name') || '').toLowerCase();
                    result = nameA.localeCompare(nameB);
                }
            }
            
            return result;
        });
        
        // Reorder DOM elements
        $.each(items, function(index, item) {
            $list.append(item);
        });
        
        // Store sort preference
        var targetId = $contentArea.attr('id');
        if (targetId) {
            localStorage.setItem('pp_sort_' + targetId, sortBy);
        }
    }
    
    /**
     * Handle sort dropdown change
     */
    $(document).on('change', '.pp-sort-select', function() {
        var $select = $(this);
        var sortBy = $select.val();
        var targetId = $select.data('target');
        var $contentArea = $('#' + targetId);
        
        if ($contentArea.length) {
            sortPermissionItems($contentArea, sortBy);
        }
    });
    
    /**
     * Initialize sorting on page load - restore saved preferences
     */
    $(document).ready(function() {
        $('.pp-agent-type-content').each(function() {
            var $contentArea = $(this);
            var targetId = $contentArea.attr('id');
            
            if (targetId) {
                var savedSort = localStorage.getItem('pp_sort_' + targetId);
                
                if (savedSort) {
                    // Set dropdown to saved value
                    var $select = $('.pp-sort-select[data-target="' + targetId + '"]');
                    $select.val(savedSort);
                    
                    // Apply sort if content area is active
                    if ($contentArea.hasClass('active')) {
                        setTimeout(function() {
                            sortPermissionItems($contentArea, savedSort);
                        }, 100);
                    }
                }
            }
        });
    });
    
    /**
     * Apply saved sort when switching to a tab
     */
    $(document).on('click', '.pp-agent-type-tab', function() {
        var targetId = $(this).data('agent-target');
        var $contentArea = $('#' + targetId);
        
        setTimeout(function() {
            var savedSort = localStorage.getItem('pp_sort_' + targetId);
            
            if (savedSort) {
                var $select = $('.pp-sort-select[data-target="' + targetId + '"]');
                $select.val(savedSort);
                sortPermissionItems($contentArea, savedSort);
            }
        }, 150);
    });
    
    /**
     * Reapply sort after adding new users (maintains sort order)
     */
    function reapplySortAfterAdd($contentArea) {
        var targetId = $contentArea.attr('id');
        if (targetId) {
            var savedSort = localStorage.getItem('pp_sort_' + targetId);
            if (savedSort) {
                setTimeout(function() {
                    sortPermissionItems($contentArea, savedSort);
                }, 200);
            }
        }
    }

})(jQuery);


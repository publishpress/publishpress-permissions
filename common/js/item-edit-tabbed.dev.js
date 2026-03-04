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

            // Update badge count on tab
            updateTabBadge($select.closest('.pp-agent-type-content'));
            
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
                alert('Please select a bulk action first.');
                return;
            }
            
            // Get all checked items
            var $checkedItems = $card.find('.pp-item-checkbox:checked');
            
            if ($checkedItems.length === 0) {
                alert('Please select at least one item.');
                return;
            }
            
            // Handle remove action separately
            if (action === 'remove') {
                if (confirm('Are you sure you want to remove the selected user(s) from exceptions? This will take effect when you click "Update".')) {
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
                
                // Change the value
                $select.val(newValue);
                
                // Trigger change event to update CSS classes
                $select.trigger('change');
            });
            
            // Reset bulk action dropdown
            $actionSelect.val('');
            
            // Uncheck all checkboxes
            $checkedItems.prop('checked', false);
            $card.find('.pp-select-all-checkbox').prop('checked', false);
            
            // Update counter
            updateBulkCounter($card);
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
            
            if (confirm('Remove "' + userName + '" from exceptions? This will take effect when you click "Update".')) {
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
                    
                    // Show empty state message
                    $list.prepend(
                        '<div class="pp-empty-state">' +
                        '<span class="dashicons dashicons-info"></span>' +
                        '<p>No user exceptions set. Use the dropdown above to add users.</p>' +
                        '</div>'
                    );
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
                    setTimeout(() => {
                        updateTabBadge($agentContent);
                    }, 50);
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
        var $card = $(this).closest('.pp-permission-card, .pp-agent-type-content');
        var $list = $card.find('.pp-permission-list');
        var searchQuery = $(this).val().toLowerCase().trim();

        // Show/hide clear button
        if (searchQuery.length > 0) {
            $clearBtn.show();
        } else {
            $clearBtn.hide();
        }

        // Filter list items
        var visibleCount = 0;
        $list.find('.pp-permission-list-item').each(function() {
            var $item = $(this);
            var itemName = $item.find('.item-name label').text().toLowerCase();

            if (itemName.indexOf(searchQuery) !== -1) {
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
        var $card = $(this).closest('.pp-permission-card, .pp-agent-type-content');
        var $list = $card.find('.pp-permission-list');

        // Clear input and hide clear button
        $input.val('').focus();
        $(this).hide();

        // Show all items
        $list.find('.pp-permission-list-item').show();

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
            
            // Update operation tab badge
            setTimeout(() => {
                updateTabBadge($agentContent);
            }, 50);
            
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
                    .attr('data-select-item', '')
            );

        // Item name
        var $itemName = $('<div>')
            .addClass('item-name')
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
                .attr('title', 'Remove user from exceptions')
                .append($('<span>').addClass('dashicons dashicons-trash'))
        );

        // Assemble the item
        $newItem.append($checkbox).append($itemName).append($permissionControl);

        // Remove "no results" and empty state messages if they exist
        $list.find('.pp-no-search-results, .pp-empty-state').remove();

        // Add to list with animation
        $list.append($newItem);
        $newItem.slideDown(300, function() {
            // Scroll to the new item after slide animation completes
            var listScrollTop = $list.scrollTop();
            var itemOffset = $newItem.position().top;
            var listHeight = $list.height();
            
            if (itemOffset > listHeight - 100) {
                $list.animate({
                    scrollTop: listScrollTop + itemOffset - listHeight + 100
                }, 300);
            }
        });
        
        // Remove animation class after CSS animation completes (500ms)
        setTimeout(function() {
            $newItem.removeClass('pp-new-item');
        }, 600);

        // Update operation tab badge
        setTimeout(() => {
            updateTabBadge($agentContent);
        }, 50);
        
        // Show success notice
        showUserAddedNotice(userName, $select);
    }


    /**
     * Update operation tab badge count
     * 
     * Counts only items with actual exception values configured (matching PHP logic).
     * Items with empty/default values are not counted.
     * 
     * @param {jQuery} $agentContent The agent content container
     */
    function updateTabBadge($agentContent) {
        var $tabPane = $agentContent.closest('.pp-tab-pane');
        var tabPaneId = $tabPane.attr('id');
        var $tab = $('.pp-operation-tab[data-target="' + tabPaneId + '"]');

        if (!$tab.length) {
            return;
        }
        
        // Count only items with actual exceptions configured (not defaults)
        // Match PHP logic: count from $current_exceptions data, not all roles/groups
        // Separate counts by agent type for detailed tooltip
        var counts = {
            roles: 0,
            groups: 0,
            users: 0,
            total: 0
        };
        
        $tabPane.find('.pp-permission-list-item').each(function() {
            var $item = $(this);

            // Skip if marked for removal
            if ($item.hasClass('pp-item-removed')) {
                return; // continue to next iteration
            }
            
            // Only count if the select has a non-empty value (not default)
            var $select = $item.find('.pp-permission-select select');
            if ($select.length && $select.val() !== '') {
                var agentType = $item.data('agent-type');
                
                if (agentType === 'wp_role') {
                    counts.roles++;
                } else if (agentType === 'pp_group') {
                    counts.groups++;
                } else if (agentType === 'user') {
                    counts.users++;
                }
                
                counts.total++;
            }
        });
        
        // Update or create badge
        var $badge = $tab.find('.pp-tab-badge');

        if (counts.total > 0) {
            if ($badge.length === 0) {
                // Create badge if it doesn't exist
                $badge = $('<span class="pp-tab-badge"></span>');
                $tab.append($badge);
            }
            
            // Update badge count
            $badge.text(counts.total);
            $tab.attr('data-exception-count', counts.total);
            
            // Build tooltip text matching PHP format: "1 role, 1 group, 2 users"
            var tooltipParts = [];
            if (counts.roles > 0) {
                tooltipParts.push(counts.roles + (counts.roles === 1 ? ' role' : ' roles'));
            }
            if (counts.groups > 0) {
                tooltipParts.push(counts.groups + (counts.groups === 1 ? ' group' : ' groups'));
            }
            if (counts.users > 0) {
                tooltipParts.push(counts.users + (counts.users === 1 ? ' user' : ' users'));
            }
            var tooltipText = tooltipParts.join(', ');
            
            $badge.attr('title', tooltipText);
        } else {
            // Remove badge if count is 0
            $badge.remove();
            $tab.attr('data-exception-count', '0');
        }
    }

    /**
     * Show notice that user was added
     */
    function showUserAddedNotice(userName, $select) {
        var $existingNotice = $select.closest('.pp-agent-type-content').find('.pp-user-added-notice');
        $existingNotice.remove();

        var $notice = $('<div class="pp-user-added-notice">')
            .html('<span class="dashicons dashicons-yes"></span> <strong>' + userName + '</strong> added to list. Configure permissions and click "Update" to save.')
            .hide();

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

})(jQuery);


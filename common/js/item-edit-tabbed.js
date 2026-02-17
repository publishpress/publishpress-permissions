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
                alert('Please select a bulk action first.');
                return;
            }
            
            // Get all checked items
            var $checkedItems = $card.find('.pp-item-checkbox:checked');
            
            if ($checkedItems.length === 0) {
                alert('Please select at least one item.');
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
    }

    // ============================
    // Search Functionality
    // ============================

    /**
     * Filter permission list items based on search query
     */
    $('.pp-search-input').on('input', function() {
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

})(jQuery);

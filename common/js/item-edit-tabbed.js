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
            var $section = $tab.closest('.pp-modern-permission-section');
            
            // Remove active state from all sub-tabs and content
            $section.find('.pp-agent-type-tab').removeClass('active');
            $section.find('.pp-agent-type-content').removeClass('active');
            
            // Add active state to clicked tab and corresponding content
            $tab.addClass('active');
            $section.find('#' + targetContent).addClass('active');
            
            // Store in localStorage
            var metaboxId = $section.closest('.postbox').attr('id');
            var operationTab = $section.closest('.pp-tab-pane').attr('id');
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
    }

})(jQuery);

<?php

namespace PublishPress\Permissions\UI\Dashboard;

class ItemExceptionsUI
{
    private $render;
    var $data;

    public function __construct()
    {
        require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsData.php');
        $this->data = new ItemExceptionsData();

        require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsRenderUI.php');
        $this->render = new ItemExceptionsRenderUI();
    }

    public function drawExceptionsUI($box, $args)
    {
        if (!isset($box['args'])) {
            return;
        }

        $pp = presspermit();
        $pp_admin = $pp->admin();
        $pp_groups = $pp->groups();

        $item_id = (isset($args['item_id'])) ? $args['item_id'] : 0;
        $for_item_type = (isset($args['for_item_type'])) ? $args['for_item_type'] : '';
        $via_item_source = (isset($args['via_item_source'])) ? $args['via_item_source'] : '';
        $via_item_type = (isset($args['via_item_type'])) ? $args['via_item_type'] : '';
        $op = (isset($box['args']['op'])) ? $box['args']['op'] : '';

        global $wp_roles;

        $is_draft_post = false;
        if ('post' == $via_item_source) {
            if ('read' == $op) {
                global $post;
                $status_obj = get_post_status_object($post->post_status);
                if (!$status_obj || (!$status_obj->public && !$status_obj->private)) {
                    $is_draft_post = true;
                }
            }

            $hierarchical = is_post_type_hierarchical($via_item_type);
        } else {
            $hierarchical = is_taxonomy_hierarchical($via_item_type);
        }

        if ($hierarchical = apply_filters('presspermit_do_assign_for_children_ui', $hierarchical, $via_item_type, $args)) {
            $type_obj = ('post' == $via_item_source) ? get_post_type_object($via_item_type) : get_taxonomy($via_item_type);
        }

        $agent_types['wp_role'] = (object)['labels' => (object)['name' => esc_html__('Roles', 'press-permit-core'), 'singular_name' => esc_html__('Role')]];

        $agent_types = apply_filters('presspermit_list_group_types', array_merge($agent_types, $pp->groups()->getGroupTypes([], 'object')));

        $agent_types['user'] = (object)['labels' => (object)['name' => esc_html__('Users'), 'singular_name' => esc_html__('User', 'press-permit-core')]];

        static $drew_itemroles_marker;
        if (empty($drew_itemroles_marker)) {
            echo "<input type='hidden' name='pp_post_exceptions' value='true' />";
            $drew_itemroles_marker = true;
        }

        $current_exceptions = (isset($this->data->current_exceptions[$for_item_type]))
            ? $this->data->current_exceptions[$for_item_type]
            : [];

        // Check for blockage of Everyone, Logged In metagroups
        $metagroup_exclude = [];
        $is_auth_metagroup = [];

        if ($current_exceptions && !empty($current_exceptions[$op]) && !empty($current_exceptions[$op]['wp_role'])) {
            foreach ($this->data->agent_info['wp_role'] as $agent_id => $role) {
                if (!empty($role->metagroup_id) && in_array($role->metagroup_id, ['wp_auth', 'wp_all', 'wp_anon'])) {
                    $is_auth_metagroup[$agent_id] = true;

                    if (in_array($role->metagroup_id, ['wp_auth', 'wp_all'])
                        && !empty($current_exceptions[$op]['wp_role'][$agent_id]) 
                        && !empty($current_exceptions[$op]['wp_role'][$agent_id]['item'])
                        && !empty($current_exceptions[$op]['wp_role'][$agent_id]['item']['exclude'])
                    ) {
                        $metagroup_exclude[$role->metagroup_id] = true;
                    }
                }
            }
        }

        // ========== OBJECT / TERM EXCEPTION DROPDOWNS ============
        $toggle_agents = count($agent_types) > 1;
        if ($toggle_agents) {
            global $is_ID;
            $class_selected = 'agp-selected_agent agp-agent';
            $class_unselected = 'agp-unselected_agent agp-agent';
            $bottom_margin = (!empty($is_IE)) ? '-0.7em' : 0;

            $default_agent_type = 'wp_role';

            echo "<div class='hide-if-not-js' style='margin:0 0 " . esc_attr($bottom_margin) . " 0'>"
                . "<ul class='pp-list_horiz' style='margin-bottom:-0.1em'>";

            foreach ($agent_types as $agent_type => $gtype_obj) {
                $label = (!empty($current_exceptions[$op][$agent_type]))
                    ? sprintf(esc_html__('%1$s (%2$s)', 'press-permit-core'), $gtype_obj->labels->name, count($current_exceptions[$op][$agent_type]))
                    : $gtype_obj->labels->name;

                $class = ($default_agent_type == $agent_type) ? $class_selected : $class_unselected;
                echo "<li class='" . esc_attr($class) . "'><a href='javascript:void(0)' class='" . esc_attr("{$op}-{$for_item_type}-{$agent_type}") . "'>" . esc_html($label) . '</a></li>';
            }

            echo '</ul></div>';
        }

        $class = "pp-agents pp-exceptions";

        //need effective line break here if not IE
        echo "<div style='clear:both;' class='" . esc_attr($class) . "'>";

        foreach (array_keys($agent_types) as $agent_type) {
            $hide_class = ($toggle_agents && ($agent_type != $default_agent_type)) ? 'hide-if-js' : '';

            echo "\r\n<div id='" . esc_attr("{$op}-{$for_item_type}-{$agent_type}") . "' class='" . esc_attr($hide_class) . "' style='overflow-x:auto'>";

            $this->render->setOptions($agent_type);

            // list all WP roles
            if ('wp_role' == $agent_type) {
                if (!isset($current_exceptions[$op][$agent_type]))
                    $current_exceptions[$op][$agent_type] = [];

                foreach ($this->data->agent_info['wp_role'] as $agent_id => $role) {
                    if (
                        in_array($role->metagroup_id, ['wp_anon', 'wp_all'], true)
                        && (!$pp->moduleActive('file-access') || 'attachment' != $for_item_type)
                        && !defined('PP_ALL_ANON_FULL_EXCEPTIONS')
                        && (('read' != $op) || $pp->getOption('anonymous_unfiltered'))
                    ) {
                        continue;
                    }

                    if (!isset($current_exceptions[$op][$agent_type][$agent_id])) {
                        $current_exceptions[$op][$agent_type][$agent_id] = [];
                    }
                }

                if (
                    !$is_draft_post && ('post' == $via_item_source) && ('attachment' != $via_item_type)
                    && in_array($op, ['read', 'edit', 'delete'], true)
                ) {
                    $reqd_caps = map_meta_cap("{$op}_post", 0, $item_id);
                } else {
                    $reqd_caps = false;
                }
            } 
            $any_stored = empty($current_exceptions[$op][$agent_type]) ? 0 : count($current_exceptions[$op][$agent_type]);
            ?>

            <table class="pp-item-exceptions-ui pp-exc-<?php echo esc_attr($agent_type); ?>" style="width:100%">
                <tr>
                    <?php if ('wp_role' != $agent_type) : ?>
                        <td class="pp-select-exception-agents">
                            <?php
                            // Select Groups / Users UI

                            echo '<div>';
                            echo '<div class="pp-agent-select">';

                            $args = array_merge($args, [
                                'suppress_extra_prefix' => true,
                                'ajax_selection' => true,
                                'display_stored_selections' => false,
                                'create_dropdowns' => true,
                                'op' => $op,
                                'via_item_type' => $via_item_type,
                            ]);

                            $pp_admin->agents()->agentsUI($agent_type, [], "{$op}:{$for_item_type}:{$agent_type}", [], $args);
                            echo '</div>';
                            echo '</div>';

                            $colspan = '2';
                            ?>
                            
                        </td>
                    <?php else :
                        $colspan = '';
                    endif; ?>
                    <td class="pp-current-item-exceptions" style="width:100%">
                        <div class="pp-exc-wrap" style="overflow:auto;">
                            <table <?php if (!$any_stored) echo 'style="display:none"'; ?>>
                                <?php if ($hierarchical) : ?>
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th><?php printf(esc_html__('This %s', 'press-permit-core'), esc_html($type_obj->labels->singular_name)); ?></th>
                                            <th><?php
                                                if ($caption = apply_filters('presspermit_item_assign_for_children_caption', '', $via_item_type))
                                                    printf(esc_html($caption));
                                                else
                                                    printf(esc_html__('Sub-%s', 'press-permit-core'), esc_html($type_obj->labels->name));
                                                ?></th>
                                        </tr>
                                    </thead>
                                <?php endif; ?>
                                <tbody>
                                    <?php // todo: why is agent_id=0 in current_exceptions array?
                                    if ($any_stored) {
                                        if ('wp_role' == $agent_type) {

                                            // Buffer original reqd_caps value
                                            $_reqd_caps = (is_array($reqd_caps)) ? array_values($reqd_caps) : $reqd_caps;

                                            foreach ($current_exceptions[$op][$agent_type] as $agent_id => $agent_exceptions) {
                                                if ($agent_id && isset($this->data->agent_info[$agent_type][$agent_id])) {
                                                    if ((false === strpos($this->data->agent_info[$agent_type][$agent_id]->name, '[WP ')) || defined('PRESSPERMIT_DELETED_ROLE_EXCEPTIONS_UI')) {
                                                        
                                                        // If Everyone / Logged In metagroup is blocked, indicate effect on other roles
                                                        if ((!empty($metagroup_exclude['wp_all']) || !empty($metagroup_exclude['wp_auth'])) && empty($is_auth_metagroup[$agent_id])) {
                                                            if (is_array($_reqd_caps)) {
                                                                $reqd_caps = array_merge($_reqd_caps, ['pp_administer_content']);
                                                            } else {
                                                                $reqd_caps = ['pp_administer_content'];
                                                            }
                                                        } else {
                                                            $reqd_caps = $_reqd_caps;
                                                        }

                                                        $this->render->drawRow(
                                                            $agent_type,
                                                            $agent_id,
                                                            $current_exceptions[$op][$agent_type][$agent_id],
                                                            $this->data->inclusions_active,
                                                            $this->data->agent_info[$agent_type][$agent_id],
                                                            compact('for_item_type', 'op', 'reqd_caps', 'hierarchical', 'item_id')
                                                        );
                                                    }
                                                }
                                            }

                                            // Restore original reqd_caps value
                                            $reqd_caps = $_reqd_caps;

                                        } else {
                                            foreach (array_keys($this->data->agent_info[$agent_type]) as $agent_id) {  // order by agent name
                                                if ($agent_id && isset($current_exceptions[$op][$agent_type][$agent_id])) {
                                                    $current_selections = $this->render->drawRow(
                                                        $agent_type,
                                                        $agent_id,
                                                        $current_exceptions[$op][$agent_type][$agent_id],
                                                        $this->data->inclusions_active,
                                                        $this->data->agent_info[$agent_type][$agent_id],
                                                        compact('for_item_type', 'op', 'reqd_caps', 'hierarchical', 'item_id')
                                                    );

                                                    // The current_selections array is just a convenient way to decide whether to show the group restrictions warning by default.
                                                    foreach ($current_selections as $current_selection) {
                                                        if (0 === $current_selection) {
                                                            $any_groups_blocked = true;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    ?>
                                </tbody>

                                <tfoot <?php if ($any_stored < 2) echo 'style="display:none;"'; ?>>
                                    <?php
                                    $link_caption = ('wp_role' == $agent_type) ? esc_html__('Default All', 'press-permit-core') : '';
                                    if(!empty($link_caption)) :
                                    ?>
                                    <tr>
                                        <td></td>
                                        <td style="text-align:center"><a
                                                href="#clear-item-exc"><?php echo esc_html($link_caption); ?></a></td>
                                        <?php if ($hierarchical) : ?>
                                            <td style="text-align:center"><a
                                                    href="#clear-sub-exc"><?php echo esc_html($link_caption); ?></a></td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endif; ?>
                                    </tfoot>

                            </table>

                        </div>

                        <?php if (('pp_group' == $agent_type) && !defined('PP_NO_GROUP_RESTRICTIONS')):?>
                        <div class="pp-group-restrictions-warning" style="margin-top: 10px;<?php if (empty($any_groups_blocked)) echo 'display:none';?>">
                        <?php 
                        printf(
                            '<i class="dashicons dashicons-warning" style="color:#b32121; font-size: 18px;width: 16px;height: 16px;margin-left: 3px;"></i> %s',
                            esc_html__("Group restrictions are not recommended.", 'press-permit-core')
                        );
                        ?>
                        </div>
                        <?php endif;?>
                    </td>
                </tr>
            </table>

            </div>
<?php
        } // end foreach group type caption

        echo '</div>'; // class pp-agents

        if (('read' == $op) && $pp->getOption('display_extension_hints')
            && (
                (('attachment' == $for_item_type) && !$pp->moduleActive('file-access'))
                || ! $pp->moduleActive('collaboration'))
        ) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/HintsItemExceptions.php');
            \PublishPress\Permissions\UI\HintsItemExceptions::itemHints($for_item_type);
        }
    }

    /**
     * Draw tabbed exceptions UI with all operations in one metabox
     * 
     * @param array $operations Array of operation data with keys: op, caption, op_obj
     * @param array $args Arguments including item_id, for_item_type, via_item_source, via_item_type
     */
    public function drawTabbedExceptionsUI($operations, $args)
    {
        if (empty($operations)) {
            return;
        }
        global $typenow;

        $for_item_type = (isset($args['for_item_type'])) ? $args['for_item_type'] : '';
        $via_item_source = (isset($args['via_item_source'])) ? $args['via_item_source'] : '';
        $via_item_type = (isset($args['via_item_type'])) ? $args['via_item_type'] : '';

        // Get type object for labels
        $type_obj = ('post' == $via_item_source) ? get_post_type_object($via_item_type) : get_taxonomy($via_item_type);
        $type_name = ($type_obj) ? $type_obj->labels->singular_name : $via_item_type;
        $post_type = (!PWP::empty_REQUEST('pp_universal')) ? '' : $typenow;
        $post_type_obj = get_post_type_object($post_type);

        static $drew_itemroles_marker;
        if (empty($drew_itemroles_marker)) {
            echo "<input type='hidden' name='pp_post_exceptions' value='true' />";
            $drew_itemroles_marker = true;
        }

        ?>
        <div class="pp-tabbed-metabox">
            <!-- Left Sidebar with Operation Tabs -->
            <div class="pp-tabbed-sidebar">
                <div class="pp-operation-tabs">
                    <?php 
                    $first = true;
                    foreach ($operations as $op_data) : 
                        $op = $op_data['op'];
                        $tab_id = "pp-tab-{$op}-{$for_item_type}";

                        // Get icon based on operation
                        $icon = $this->getOperationIcon($op);

                        $type_label = esc_html(strtolower($type_name));
                        $post_type_label = (!empty($post_type_obj) && !empty($post_type_obj->labels->name)) ? esc_html(strtolower($post_type_obj->labels->name)) : esc_html(strtolower($type_obj->labels->singular_name));
                        $tooltips = [
                            'assign'    => sprintf(esc_html__('Control who can assign terms to this %s.', 'press-permit-core'), $type_label),
                            'associate' => sprintf(esc_html__('Control who can choose the parent page for this %s.', 'press-permit-core'), $type_label),
                            'edit'      => sprintf(esc_html__('Control editing of this %s.', 'press-permit-core'), $type_label),
                            'publish'   => sprintf(esc_html__('Control publishing of this %s.', 'press-permit-core'), $type_label),
                            'delete'    => sprintf(esc_html__('Control deletion of this %s.', 'press-permit-core'), $type_label),
                            'manage'    => sprintf(esc_html__('Control term management for this %s.', 'press-permit-core'), $type_label),
                            'read'      => sprintf(esc_html__('Control frontend viewing of this %s.', 'press-permit-core'), $type_label),
                            'copy'      => sprintf(esc_html__('Control who can create a revision of this %s.', 'press-permit-core'), $type_label),
                            'revise'    => sprintf(esc_html__('Control who can submit a revision of this %s.', 'press-permit-core'), $type_label),
                        ];
                        if (!empty($type_obj->name) && in_array($type_obj->name, ['post_tag', 'category'])) {
                            $tooltips['assign'] = sprintf(esc_html__('Control who add this %s to %s.', 'press-permit-core'), $type_label, $post_type_label);
                            $tooltips['edit'] = sprintf(esc_html__('Control who can edit %s with this %s.', 'press-permit-core'), $post_type_label, $type_label);
                            $tooltips['read'] = sprintf(esc_html__('Control who can view %s with this %s.', 'press-permit-core'), $post_type_label, $type_label);
                            $tooltips['copy'] = sprintf(esc_html__('Control who can create a revision of %s with this %s.', 'press-permit-core'), $post_type_label, $type_label);
                            $tooltips['revise'] = sprintf(esc_html__('Control who can submit a revision of %s with this %s.', 'press-permit-core'), $post_type_label, $type_label);

                            // For universal post type exceptions
                            if ($post_type === '') {
                                $tooltips['assign'] = sprintf(esc_html__('Control who add this %s to all post types.', 'press-permit-core'), $post_type_label);
                                $tooltips['edit'] = sprintf(esc_html__('Control who can edit all post types in this %s.', 'press-permit-core'), $post_type_label);
                                $tooltips['read'] = sprintf(esc_html__('Control who can view all post types in this %s.', 'press-permit-core'), $post_type_label);
                                $tooltips['copy'] = sprintf(esc_html__('Control who can create a revision of all post types in this %s.', 'press-permit-core'), $post_type_label);
                                $tooltips['revise'] = sprintf(esc_html__('Control who can submit a revision of all post types in this %s.', 'press-permit-core'), $post_type_label);
                            }
                        }
                        ?>
                        <button type="button" 
                                class="pp-operation-tab <?php echo $first ? 'active' : ''; ?>" 
                                data-target="<?php echo esc_attr($tab_id); ?>">
                            <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                            <span class="pp-tab-label">
                            <?php
                            echo isset($tooltips[$op]) ? 
                            sprintf(
                                '<span data-toggle="tooltip" data-placement="right">%s<span class="tooltip-text"><span>%s</span><i></i></span></span>',
                                esc_html($op_data['caption']),
                                esc_html($tooltips[$op])
                            ) : esc_html($op_data['caption']) ; ?>
                            </span>
                        </button>
                    <?php 
                        $first = false;
                    endforeach; 
                    ?>
                </div>
            </div>

            <!-- Main Content Area with Tab Panes -->
            <div class="pp-tabbed-main">
                <div class="pp-tabbed-content">
                    <?php 
                    $first = true;
                    foreach ($operations as $op_data) : 
                        $op = $op_data['op'];
                        $tab_id = "pp-tab-{$op}-{$for_item_type}";
                        
                        // Create a box array for compatibility with existing drawExceptionsUI
                        $box = [
                            'id' => "pp_{$op}_{$for_item_type}_exceptions_tab",
                            'args' => ['op' => $op]
                        ];
                    ?>
                        <div id="<?php echo esc_attr($tab_id); ?>" class="pp-tab-pane <?php echo $first ? 'active' : ''; ?>">
                            <div class="pp-operation-content">
                                <?php
                                // Call the new tabbed operation content method
                                $this->drawTabbedOperationContent($op, $args);
                                ?>
                            </div>
                        </div>
                    <?php 
                        $first = false;
                    endforeach; 
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Draw modern tabbed operation content with sub-tabs for agent types
     * 
     * @param string $op Operation name
     * @param array $args Arguments including item_id, for_item_type, via_item_source, via_item_type
     */
    private function drawTabbedOperationContent($op, $args)
    {
        $pp = presspermit();
        $pp_admin = $pp->admin();
        
        $item_id = (isset($args['item_id'])) ? $args['item_id'] : 0;
        $for_item_type = (isset($args['for_item_type'])) ? $args['for_item_type'] : '';
        $via_item_source = (isset($args['via_item_source'])) ? $args['via_item_source'] : '';
        $via_item_type = (isset($args['via_item_type'])) ? $args['via_item_type'] : '';

        // Get type object for hierarchical check
        if ('post' == $via_item_source) {
            $hierarchical = is_post_type_hierarchical($via_item_type);
        } else {
            $hierarchical = is_taxonomy_hierarchical($via_item_type);
        }

        $type_obj = null;
        if ($hierarchical = apply_filters('presspermit_do_assign_for_children_ui', $hierarchical, $via_item_type, $args)) {
            $type_obj = ('post' == $via_item_source) ? get_post_type_object($via_item_type) : get_taxonomy($via_item_type);
        }

        $current_exceptions = (isset($this->data->current_exceptions[$for_item_type]))
            ? $this->data->current_exceptions[$for_item_type]
            : [];

        // Check for blockage of Everyone, Logged In metagroups
        $metagroup_exclude = [];
        $is_auth_metagroup = [];

        if ($current_exceptions && !empty($current_exceptions[$op]) && !empty($current_exceptions[$op]['wp_role'])) {
            foreach ($this->data->agent_info['wp_role'] as $agent_id => $role) {
                if (!empty($role->metagroup_id) && in_array($role->metagroup_id, ['wp_auth', 'wp_all', 'wp_anon'])) {
                    $is_auth_metagroup[$agent_id] = true;

                    if (in_array($role->metagroup_id, ['wp_auth', 'wp_all'])
                        && !empty($current_exceptions[$op]['wp_role'][$agent_id]) 
                        && !empty($current_exceptions[$op]['wp_role'][$agent_id]['item'])
                        && !empty($current_exceptions[$op]['wp_role'][$agent_id]['item']['exclude'])
                    ) {
                        $metagroup_exclude[$role->metagroup_id] = true;
                    }
                }
            }
        }

        $is_draft_post = false;
        if ('post' == $via_item_source && 'read' == $op) {
            global $post;
            $status_obj = get_post_status_object($post->post_status);
            if (!$status_obj || (!$status_obj->public && !$status_obj->private)) {
                $is_draft_post = true;
            }
        }

        if (!$is_draft_post && ('post' == $via_item_source) && ('attachment' != $via_item_type)
            && in_array($op, ['read', 'edit', 'delete'], true)
        ) {
            $reqd_caps = map_meta_cap("{$op}_post", 0, $item_id);
        } else {
            $reqd_caps = false;
        }

        // Check if custom groups exist to determine tab label
        $has_custom_groups = !empty($this->data->agent_info['pp_group']);
        $roles_groups_label = $has_custom_groups ? __('Roles & Groups', 'press-permit-core') : __('Roles', 'press-permit-core');
        $search_placeholder = $has_custom_groups ? __('Search roles and groups...', 'press-permit-core') : __('Search roles...', 'press-permit-core');

        ?>
        <!-- Sub-tabs for agent types -->
        <div class="pp-agent-type-tabs">
            <button type="button" class="pp-agent-type-tab active" data-agent-target="pp-roles-groups-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>">
                <span class="dashicons dashicons-groups"></span>
                <?php echo esc_html($roles_groups_label); ?>
            </button>
            <button type="button" class="pp-agent-type-tab" data-agent-target="pp-users-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>">
                <span class="dashicons dashicons-admin-users"></span>
                <?php esc_html_e('Users', 'press-permit-core'); ?>
            </button>
        </div>

        <!-- Roles & Groups Content -->
        <div id="pp-roles-groups-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>" class="pp-agent-type-content active">
            <!-- Search Box -->
            <div class="pp-search-box">
                <span class="dashicons dashicons-search"></span>
                <input type="text" class="pp-search-input" placeholder="<?php echo esc_attr($search_placeholder); ?>" />
                <button type="button" class="pp-search-clear" style="display: none;">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <!-- Filter Pills and Sort Controls (combined row) -->
            <div class="pp-permission-filters" data-filter-target="pp-roles-groups-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>">
                <div class="pp-filter-pills-container">
                    <!-- Filter pills will be dynamically inserted here by JavaScript -->
                </div>
                <div class="pp-sort-controls">
                    <label class="pp-sort-label"><?php esc_html_e('Sort by:', 'press-permit-core'); ?></label>
                    <select class="pp-sort-select" data-target="pp-roles-groups-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>">
                        <option value="name-asc"><?php esc_html_e('Name (A-Z)', 'press-permit-core'); ?></option>
                        <option value="name-desc"><?php esc_html_e('Name (Z-A)', 'press-permit-core'); ?></option>
                        <option value="users-desc" class="pp-sort-by-users"><?php esc_html_e('Most Users', 'press-permit-core'); ?></option>
                        <option value="users-asc" class="pp-sort-by-users"><?php esc_html_e('Fewest Users', 'press-permit-core'); ?></option>
                    </select>
                </div>
            </div>
            <div class="pp-permission-cards">
                <div class="pp-permission-card">
                    <!-- Bulk Actions Toolbar -->
                    <div class="pp-bulk-actions-toolbar">
                        <div class="pp-bulk-select">
                            <input type="checkbox" id="pp-select-all-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>" class="pp-select-all-checkbox" />
                            <label for="pp-select-all-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>"><?php esc_html_e('Select All', 'press-permit-core'); ?></label>
                        </div>
                        <div class="pp-bulk-actions">
                            <select class="pp-bulk-action-select" aria-label="<?php esc_attr_e('Bulk Actions', 'press-permit-core'); ?>">
                                <option value=""><?php esc_html_e('Bulk Actions', 'press-permit-core'); ?></option>
                                <option value="enable"><?php esc_html_e('Set to Enabled', 'press-permit-core'); ?></option>
                                <option value="block"><?php esc_html_e('Set to Blocked', 'press-permit-core'); ?></option>
                                <option value="default"><?php esc_html_e('Set to Default', 'press-permit-core'); ?></option>
                            </select>
                            <button type="button" class="button pp-bulk-apply"><?php esc_html_e('Apply', 'press-permit-core'); ?></button>
                        </div>
                        <div class="pp-bulk-counter">
                            <span class="pp-selected-count">0</span> <?php esc_html_e('selected', 'press-permit-core'); ?>
                        </div>
                    </div>
                    
                    <div class="pp-permission-list">
                        <!-- WordPress Roles Card -->
                        <?php $this->renderRolesCard($op, $for_item_type, $current_exceptions, $reqd_caps, $hierarchical, $type_obj, $metagroup_exclude, $is_auth_metagroup, $item_id); ?>
                        <!-- Custom Groups Card -->
                        <?php $this->renderGroupsCard($op, $for_item_type, $via_item_type, $args, $current_exceptions, $reqd_caps, $hierarchical, $type_obj, $item_id, $pp_admin); ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- Users Content -->
        <div id="pp-users-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>" class="pp-agent-type-content">
            <!-- Search Box with Select2 -->
            <div class="pp-search-box pp-search-box-select2">
                <span class="dashicons dashicons-search"></span>
                <select 
                    id="pp-user-search-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>" 
                    class="pp-search-input pp-user-search-select2" 
                    multiple="multiple"
                    data-op="<?php echo esc_attr($op); ?>"
                    data-for-item-type="<?php echo esc_attr($for_item_type); ?>"
                    data-agent-type="user"
                    data-placeholder="<?php esc_attr_e('Search and add users...', 'press-permit-core'); ?>">
                </select>
            </div>
            <!-- Filter Pills and Sort Controls (combined row) -->
            <div class="pp-permission-filters" data-filter-target="pp-users-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>">
                <div class="pp-filter-pills-container">
                    <!-- Filter pills will be dynamically inserted here by JavaScript -->
                </div>
                <div class="pp-sort-controls pp-sort-controls-users">
                    <label class="pp-sort-label"><?php esc_html_e('Sort by:', 'press-permit-core'); ?></label>
                    <select class="pp-sort-select" data-target="pp-users-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>">
                        <option value="name-asc"><?php esc_html_e('Name (A-Z)', 'press-permit-core'); ?></option>
                        <option value="name-desc"><?php esc_html_e('Name (Z-A)', 'press-permit-core'); ?></option>
                    </select>
                </div>
            </div>
            <?php $this->renderUsersCard($op, $for_item_type, $via_item_type, $args, $current_exceptions, $reqd_caps, $hierarchical, $type_obj, $item_id, $pp_admin); ?>
        </div>
        <?php
    }

    /**
     * Render WordPress Roles card
     */
    private function renderRolesCard($op, $for_item_type, $current_exceptions, $reqd_caps, $hierarchical, $type_obj, $metagroup_exclude, $is_auth_metagroup, $item_id)
    {
        $pp = presspermit();
        
        if (!isset($current_exceptions[$op]['wp_role'])) {
            $current_exceptions[$op]['wp_role'] = [];
        }

        // Get user counts for all roles
        $user_counts = [];
        if (function_exists('count_users')) {
            $_user_count = count_users();
            if (isset($_user_count['avail_roles'])) {
                $user_counts = $_user_count['avail_roles'];
            }
        }

        // Populate all WP roles
        foreach ($this->data->agent_info['wp_role'] as $agent_id => $role) {
            if (in_array($role->metagroup_id, ['wp_anon', 'wp_all'], true)
                && (!$pp->moduleActive('file-access') || 'attachment' != $for_item_type)
                && !defined('PP_ALL_ANON_FULL_EXCEPTIONS')
                && (('read' != $op) || $pp->getOption('anonymous_unfiltered'))
            ) {
                continue;
            }

            if (!isset($current_exceptions[$op]['wp_role'][$agent_id])) {
                $current_exceptions[$op]['wp_role'][$agent_id] = [];
            }
        }

        if (!empty($current_exceptions[$op]['wp_role'])) {
            // Buffer original reqd_caps value
            $_reqd_caps = (is_array($reqd_caps)) ? array_values($reqd_caps) : $reqd_caps;

            // Iterate through agent_info to maintain consistent order (configured and unconfigured roles in same order)
            foreach ($this->data->agent_info['wp_role'] as $agent_id => $role) {
                // Skip if this role is not in current_exceptions (filtered out by earlier logic)
                if (!isset($current_exceptions[$op]['wp_role'][$agent_id])) {
                    continue;
                }

                if ($agent_id && ((false === strpos($role->name, '[WP ')) || defined('PRESSPERMIT_DELETED_ROLE_EXCEPTIONS_UI'))) {
                    // If Everyone / Logged In metagroup is blocked, indicate effect on other roles
                    if ((!empty($metagroup_exclude['wp_all']) || !empty($metagroup_exclude['wp_auth'])) && empty($is_auth_metagroup[$agent_id])) {
                        if (is_array($_reqd_caps)) {
                            $reqd_caps = array_merge($_reqd_caps, ['pp_administer_content']);
                        } else {
                            $reqd_caps = ['pp_administer_content'];
                        }
                    } else {
                        $reqd_caps = $_reqd_caps;
                    }

                    // Get user count for this role
                    $user_count = 0;
                    if (!empty($role->metagroup_id) && isset($user_counts[$role->metagroup_id])) {
                        $user_count = $user_counts[$role->metagroup_id];
                    }

                    $this->renderPermissionListItem(
                        'wp_role',
                        $agent_id,
                        $current_exceptions[$op]['wp_role'][$agent_id],
                        $role,
                        compact('for_item_type', 'op', 'reqd_caps', 'hierarchical', 'item_id', 'type_obj', 'user_count')
                    );
                }
            }
        } else {
            ?>
            <div class="pp-empty-state">
                <span class="dashicons dashicons-info"></span>
                <p><?php esc_html_e('No role exceptions set', 'press-permit-core'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Render Custom Groups card
     */
    private function renderGroupsCard($op, $for_item_type, $via_item_type, $args, $current_exceptions, $reqd_caps, $hierarchical, $type_obj, $item_id, $pp_admin)
    {
        $pp = presspermit();
        
        if (!isset($current_exceptions[$op]['pp_group'])) {
            $current_exceptions[$op]['pp_group'] = [];
        }

        // Get member counts for all groups
        $member_counts = [];
        foreach ($this->data->agent_info['pp_group'] as $agent_id => $group) {
            $member_counts[$agent_id] = $pp->groups()->getGroupMembers($agent_id, 'pp_group', 'count');
        }

        // Populate all groups
        foreach ($this->data->agent_info['pp_group'] as $agent_id => $group) {
            if (!isset($current_exceptions[$op]['pp_group'][$agent_id])) {
                $current_exceptions[$op]['pp_group'][$agent_id] = [];
            }
        }

        if (!empty($current_exceptions[$op]['pp_group'])) {
            $any_groups_blocked = false;
            
            // Iterate through agent_info to maintain consistent order (configured and unconfigured groups in same order)
            foreach ($this->data->agent_info['pp_group'] as $agent_id => $group) {
                // Skip if this group is not in current_exceptions (filtered out by earlier logic)
                if (!isset($current_exceptions[$op]['pp_group'][$agent_id])) {
                    continue;
                }

                if ($agent_id) {
                    // Check if blocked
                    if (!empty($current_exceptions[$op]['pp_group'][$agent_id]['item']['exclude'])) {
                        $any_groups_blocked = true;
                    }

                    // Get member count for this group
                    $user_count = isset($member_counts[$agent_id]) ? $member_counts[$agent_id] : 0;

                    $this->renderPermissionListItem(
                        'pp_group',
                        $agent_id,
                        $current_exceptions[$op]['pp_group'][$agent_id],
                        $group,
                        compact('for_item_type', 'op', 'reqd_caps', 'hierarchical', 'item_id', 'type_obj', 'user_count')
                    );
                }
            }
        }
    }

    /**
     * Render Users card
     */
    private function renderUsersCard($op, $for_item_type, $via_item_type, $args, $current_exceptions, $reqd_caps, $hierarchical, $type_obj, $item_id, $pp_admin)
    {
        $empty_message = esc_html__('No specific user permissions. Use the search box to add users.', 'press-permit-core');
        ?>
        <div class="pp-permission-cards">
            <div class="pp-permission-card">
                <!-- Bulk Actions Toolbar -->
                <div class="pp-bulk-actions-toolbar">
                    <div class="pp-bulk-select">
                        <input type="checkbox" id="pp-select-all-users-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>" class="pp-select-all-checkbox" />
                        <label for="pp-select-all-users-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>"><?php esc_html_e('Select All', 'press-permit-core'); ?></label>
                    </div>
                    <div class="pp-bulk-actions">
                        <select class="pp-bulk-action-select" aria-label="<?php esc_attr_e('Bulk Actions', 'press-permit-core'); ?>">
                            <option value=""><?php esc_html_e('Bulk Actions', 'press-permit-core'); ?></option>
                            <option value="enable"><?php esc_html_e('Set to Enabled', 'press-permit-core'); ?></option>
                            <option value="block"><?php esc_html_e('Set to Blocked', 'press-permit-core'); ?></option>
                            <option value="default"><?php esc_html_e('Set to Default', 'press-permit-core'); ?></option>
                            <option value="remove"><?php esc_html_e('Remove Selected', 'press-permit-core'); ?></option>
                        </select>
                        <button type="button" class="button pp-bulk-apply"><?php esc_html_e('Apply', 'press-permit-core'); ?></button>
                    </div>
                    <div class="pp-bulk-counter">
                        <span class="pp-selected-count">0</span> <?php esc_html_e('selected', 'press-permit-core'); ?>
                    </div>
                </div>

                <!-- Users list -->
                <div class="pp-permission-list" data-empty-message="<?php echo esc_attr($empty_message); ?>">
                    <?php
                    if (!empty($current_exceptions[$op]['user'])) {
                        foreach (array_keys($this->data->agent_info['user']) as $agent_id) {
                            if ($agent_id && isset($current_exceptions[$op]['user'][$agent_id])) {
                                $this->renderPermissionListItem(
                                    'user',
                                    $agent_id,
                                    $current_exceptions[$op]['user'][$agent_id],
                                    $this->data->agent_info['user'][$agent_id],
                                    compact('for_item_type', 'op', 'reqd_caps', 'hierarchical', 'item_id', 'type_obj')
                                );
                            }
                        }
                    } else {
                        ?>
                        <div class="pp-empty-state">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php echo esc_html($empty_message); ?></p>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get dashicon class for an operation
     * 
     * @param string $op Operation name
     * @return string Dashicon class
     */
    private function getOperationIcon($op)
    {
        $icons = [
            'read' => 'dashicons-visibility',
            'edit' => 'dashicons-edit',
            'delete' => 'dashicons-trash',
            'publish' => 'dashicons-upload',
            'associate' => 'dashicons-networking',
            'assign' => 'dashicons-tag',
            'copy' => 'dashicons-admin-page',
            'revise' => 'dashicons-backup',
            'manage' => 'dashicons-admin-generic',
        ];

        return isset($icons[$op]) ? $icons[$op] : 'dashicons-admin-generic';
    }

    /**
     * Get exception counts for an operation
     * 
     * @param string $op Operation name
     * @param string $for_item_type Item type
     * @param array $current_exceptions Current exceptions data
     * @return array Array with keys: roles, groups, users, total
     */
    private function getExceptionCounts($op, $for_item_type, $current_exceptions)
    {
        $counts = [
            'roles' => 0,
            'groups' => 0,
            'users' => 0,
            'total' => 0,
        ];

        if (empty($current_exceptions) || empty($current_exceptions[$op])) {
            return $counts;
        }

        // Count roles (wp_role)
        if (!empty($current_exceptions[$op]['wp_role'])) {
            $counts['roles'] = count($current_exceptions[$op]['wp_role']);
        }

        // Count groups (pp_group)
        if (!empty($current_exceptions[$op]['pp_group'])) {
            $counts['groups'] = count($current_exceptions[$op]['pp_group']);
        }

        // Count users
        if (!empty($current_exceptions[$op]['user'])) {
            $counts['users'] = count($current_exceptions[$op]['user']);
        }

        $counts['total'] = $counts['roles'] + $counts['groups'] + $counts['users'];

        return $counts;
    }

    /**
     * Render a single permission list item (modern div-based structure)
     * 
     * @param string $agent_type Agent type (wp_role, pp_group, user)
     * @param string $agent_id Agent ID
     * @param array $agent_exceptions Current exception settings
     * @param object $agent_info Agent information object
     * @param array $args Additional arguments
     */
    private function renderPermissionListItem($agent_type, $agent_id, $agent_exceptions, $agent_info, $args = [])
    {
        global $wp_roles;
        
        $defaults = ['reqd_caps' => false, 'hierarchical' => false, 'for_item_type' => '', 'op' => '', 'item_id' => 0, 'type_obj' => null, 'user_count' => 0];
        $args = array_merge($defaults, $args);
        extract($args);

        $pp = presspermit();

        // Initialize option arrays using the render helper (same as legacy drawRow)
        $this->render->setOptions($agent_type);
        
        // Initialize opt_class for default option (same as drawRow does)
        $this->render->opt_class[''] = '';

        // Determine agent display name
        $_name = $agent_info->name;
        
        if ('wp_role' == $agent_type) {
            if (!empty($agent_info->metagroup_id)) {
                $_name = \PublishPress\Permissions\DB\Groups::getMetagroupName('wp_role', $agent_info->metagroup_id, $_name);
            } 
        } elseif ('user' == $agent_type) {
            // Format: Display Name (username) or just username if no display name
            if (!empty($agent_info->formatted_name)) {
                $_name = $agent_info->formatted_name;
            } else if (!empty($agent_info->display_name) && ($agent_info->display_name != $agent_info->name)) {
                $_name = $agent_info->display_name . ' (' . $agent_info->name . ')';
            }
        }

        // Determine current permission value
        $assignment_modes = ['item'];
        if ($hierarchical) {
            $assignment_modes[] = 'children';
        }

        // Check if inclusions are active for this specific agent (not global check)
        $_inclusions_active = isset($this->data->inclusions_active[$for_item_type][$op][$agent_type][$agent_id]);

        // For wp_role, calculate capability-based default option display
        $is_unfiltered = false;
        if ('wp_role' == $agent_type && (!function_exists('rvy_in_revision_workflow') || !rvy_in_revision_workflow($item_id))) {
            // Get role capabilities including supplemental roles
            static $metagroup_caps;
            if (!isset($metagroup_caps)) {
                $metagroup_caps = [];
                global $wpdb;
                
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $results = $wpdb->get_results(
                    "SELECT g.metagroup_id AS wp_rolename, r.role_name AS supplemental_role FROM $wpdb->ppc_roles AS r"
                        . " INNER JOIN $wpdb->pp_groups AS g ON g.ID = r.agent_id AND r.agent_type = 'pp_group'"
                        . " WHERE g.metagroup_type = 'wp_role'"
                );

                foreach ($results as $row) {
                    $role_specs = explode(':', $row->supplemental_role);
                    if (!empty($role_specs[2]) && ($for_item_type != $role_specs[2])) {
                        continue;
                    }

                    if (!isset($metagroup_caps[$row->wp_rolename])) {
                        $metagroup_caps[$row->wp_rolename] = [];
                    }

                    $metagroup_caps[$row->wp_rolename] = array_merge(
                        $metagroup_caps[$row->wp_rolename],
                        array_fill_keys($pp->getRoleCaps($row->supplemental_role), true)
                    );
                }
            }

            $role_obj_caps = (empty($wp_roles->role_objects[$agent_info->metagroup_id]->capabilities))
                ? []
                : $wp_roles->role_objects[$agent_info->metagroup_id]->capabilities;

            $role_caps = isset($wp_roles->role_objects[$agent_info->metagroup_id])
                ? array_intersect($role_obj_caps, [true, 1, '1'])
                : [PRESSPERMIT_READ_PUBLIC_CAP => true, 'spectate' => true];

            if (isset($metagroup_caps[$agent_info->metagroup_id])) {
                $role_caps = array_merge($role_caps, $metagroup_caps[$agent_info->metagroup_id]);
            }

            $is_unfiltered = !empty($role_caps['pp_administer_content']) || !empty($role_caps['pp_unfiltered']);

            // Update default option label based on capabilities
            if ($reqd_caps) {
                if (!array_diff($reqd_caps, array_keys($role_caps)) || $is_unfiltered) {
                    $this->render->opt_class[''] = 'pp-def';
                    $this->render->options['standard'][''] = $this->render->opt_labels['default_yes'];
                } else {
                    $this->render->opt_class[''] = 'pp-def';
                    $this->render->options['standard'][''] = $this->render->opt_labels['default_no'];
                }
            }
        }

        // Determine which option set to use (once, not in loop)
        if ($_inclusions_active) {
            $option_set = 'includes';
            $this->render->opt_class[''] = 'pp-no';
        } else {
            $option_set = 'standard';
            if (!$this->render->opt_class['']) {
                $this->render->opt_class[''] = 'pp-def';
            }
        }

        // Check if we should skip rendering this item entirely
        // (if 'item' mode is disabled for unfiltered users with no exceptions stored)
        $item_has_exception = !empty($agent_exceptions['item']['additional']) 
            || isset($agent_exceptions['item']['include']) 
            || isset($agent_exceptions['item']['exclude']);

        if (!empty($is_unfiltered) && !$item_has_exception) {
            return; // Skip rendering entirely for unfiltered users with no exceptions
        }

        // Render list item once with controls for both 'item' and 'children' modes
        $for_type = ($for_item_type) ? $for_item_type : '(all)';
        $css_class = strtolower(str_replace([' ', '_'], '-', $_name));
        $item_id_attr = esc_attr("{$agent_type}-{$agent_id}");
        ?>
                <div class="pp-permission-list-item <?php echo esc_attr($css_class); ?>" data-agent-type="<?php echo esc_attr($agent_type); ?>" data-agent-id="<?php echo esc_attr($agent_id); ?>" data-user-count="<?php echo esc_attr($user_count); ?>" data-item-name="<?php echo esc_attr(strtolower($_name)); ?>">
                    <div class="item-checkbox">
                        <input type="checkbox" class="pp-item-checkbox" id="pp-item-<?php echo esc_attr($item_id_attr); ?>" />
                    </div>
                    <div class="item-name">
                        <?php
                        // Add type badge to distinguish roles from groups
                        $type_label = '';
                        $type_class = '';
                        if ('wp_role' == $agent_type) {
                            if (!empty($agent_info->metagroup_id) && in_array($agent_info->metagroup_id, ['wp_anon', 'wp_auth', 'wp_all'])) {
                                $type_label = __('Login State', 'press-permit-core');
                                $type_class = 'pp-type-login-state';
                            } else {
                                $type_label = __('Role', 'press-permit-core');
                                $type_class = 'pp-type-role';
                            }
                        } elseif ('pp_group' == $agent_type) {
                            $type_label = __('Group', 'press-permit-core');
                            $type_class = 'pp-type-group';
                        }
                        
                        if ($type_label) :
                        ?>
                            <span class="pp-type-badge <?php echo esc_attr($type_class); ?>"><?php echo esc_html($type_label); ?></span>
                        <?php endif; ?>
                        <label for="pp-item-<?php echo esc_attr($item_id_attr); ?>" title="<?php echo esc_attr($_name); ?>"><?php echo esc_html($_name); ?></label>
                    </div>
                    <div class="pp-permission-control">
                        <?php
                        // Render controls for both 'item' and 'children' assignment modes
                        foreach ($assignment_modes as $mode) :
                            // Determine current value for this mode
                            if (!empty($agent_exceptions[$mode]['additional'])) {
                                $mode_current_val = 2;
                            } elseif (isset($agent_exceptions[$mode]['include'])) {
                                $mode_current_val = 1;
                            } elseif (isset($agent_exceptions[$mode]['exclude'])) {
                                $mode_current_val = 0;
                            } else {
                                $mode_current_val = '';
                            }
                            
                            // Get CSS class for current value
                            $mode_select_class = isset($this->render->opt_class[$mode_current_val]) 
                                ? $this->render->opt_class[$mode_current_val] 
                                : 'pp-def';
                            
                            // Determine if this mode is disabled
                            $mode_disabled = false;
                            if (!empty($is_unfiltered) && ($mode_current_val === '')) {
                                $mode_disabled = true;
                            } elseif (('children' == $mode)
                                && apply_filters('presspermit_assign_for_children_locked', false, $for_item_type, ['operation' => $op])
                            ) {
                                $mode_disabled = true;
                            }
                            
                            // Skip children column if not hierarchical
                            if ('children' == $mode && !$hierarchical) {
                                continue;
                            }
                            
                            // Build dynamic labels based on post type
                            if ($type_obj && isset($type_obj->labels->singular_name) && isset($type_obj->labels->name)) {
                                $singular = $type_obj->labels->singular_name;
                                $plural = $type_obj->labels->name;
                                $mode_label = ('children' == $mode) 
                                    ? sprintf(__('Sub-%s', 'press-permit-core'), $plural)
                                    : sprintf(__('This %s', 'press-permit-core'), $singular);
                            } else {
                                // Fallback to generic labels
                                $mode_label = ('children' == $mode) ? __('Sub-Sections', 'press-permit-core') : __('This Section', 'press-permit-core');
                            }
                            ?>
                            <div class="pp-permission-select <?php echo ('children' == $mode) ? 'pp-children-select' : 'pp-item-select'; ?>">
                                <?php if ($hierarchical && count($assignment_modes) > 1) : ?>
                                    <label class="pp-select-label"><?php echo esc_html($mode_label); ?></label>
                                <?php endif; ?>
                                <select name="pp_exceptions[<?php echo esc_attr($for_type); ?>][<?php echo esc_attr($op); ?>][<?php echo esc_attr($agent_type); ?>][<?php echo esc_attr($mode); ?>][<?php echo esc_attr($agent_id); ?>]" 
                                        class="<?php echo esc_attr($mode_select_class); ?>" 
                                        <?php echo $mode_disabled ? 'disabled="disabled"' : ''; ?>
                                        autocomplete="off">
                                    <?php 
                                    foreach ($this->render->options[$option_set] as $val => $lbl) :
                                        // Filter options for metagroups
                                        if (('wp_role' == $agent_type)
                                            && !empty($agent_info->metagroup_id)
                                            && in_array($agent_info->metagroup_id, ['wp_anon', 'wp_all'], true)
                                            && (!$pp->moduleActive('file-access') || 'attachment' != $for_type)
                                            && !defined('PP_ALL_ANON_FULL_EXCEPTIONS')
                                            && (2 == $val)
                                        ) {
                                            continue;
                                        }
                                        
                                        $option_class = isset($this->render->opt_class[$val]) ? $this->render->opt_class[$val] : '';
                                    ?>
                                        <option value="<?php echo esc_attr($val); ?>" 
                                                class="<?php echo esc_attr($option_class); ?>" 
                                                <?php selected($val, $mode_current_val); ?>>
                                            <?php echo esc_html($lbl); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($mode_disabled) : ?>
                                    <input type="hidden"
                                        name="pp_exceptions[<?php echo esc_attr($for_type); ?>][<?php echo esc_attr($op); ?>][<?php echo esc_attr($agent_type); ?>][<?php echo esc_attr($mode); ?>][<?php echo esc_attr($agent_id); ?>]"
                                        value="<?php echo esc_attr($mode_current_val); ?>" />
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ('user' == $agent_type) : ?>
                        <button type="button" class="pp-delete-item" title="<?php esc_attr_e('Remove custom permissions for user', 'press-permit-core'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
    }

    function generateTooltip($tooltip, $text = '', $position = 'top', $useIcon = true, $args = array('class' => '', 'html' => ''))
    {
        ?>
        <span data-toggle="tooltip" data-placement="<?php esc_attr_e($position); ?>" class="<?php !empty($args['class']) ? esc_attr_e($args['class']) : ''; ?>">
        <?php esc_html_e($text);?>
        <span class="tooltip-text"><span><?php esc_html_e($tooltip);?><?php !empty($args['html']) ? print wp_kses_post($args['html']) : ''; ?></span><i></i></span>
        <?php 
        if ($useIcon) : ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 50 50" style="margin-left: 4px; vertical-align: text-bottom;">
                <path d="M 25 2 C 12.264481 2 2 12.264481 2 25 C 2 37.735519 12.264481 48 25 48 C 37.735519 48 48 37.735519 48 25 C 48 12.264481 37.735519 2 25 2 z M 25 4 C 36.664481 4 46 13.335519 46 25 C 46 36.664481 36.664481 46 25 46 C 13.335519 46 4 36.664481 4 25 C 4 13.335519 13.335519 4 25 4 z M 25 11 A 3 3 0 0 0 25 17 A 3 3 0 0 0 25 11 z M 21 21 L 21 23 L 23 23 L 23 36 L 21 36 L 21 38 L 29 38 L 29 36 L 27 36 L 27 21 L 21 21 z"></path>
            </svg>
        <?php
        endif; ?>
        </span>
        <?php
    }
}

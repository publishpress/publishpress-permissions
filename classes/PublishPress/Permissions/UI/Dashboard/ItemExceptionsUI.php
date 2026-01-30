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

        $pp = presspermit();
        $item_id = (isset($args['item_id'])) ? $args['item_id'] : 0;
        $for_item_type = (isset($args['for_item_type'])) ? $args['for_item_type'] : '';
        $via_item_source = (isset($args['via_item_source'])) ? $args['via_item_source'] : '';
        $via_item_type = (isset($args['via_item_type'])) ? $args['via_item_type'] : '';

        // Get type object for labels
        $type_obj = ('post' == $via_item_source) ? get_post_type_object($via_item_type) : get_taxonomy($via_item_type);

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
                        $op_obj = $op_data['op_obj'];
                        $tab_id = "pp-tab-{$op}-{$for_item_type}";
                        
                        // Get icon based on operation
                        $icon = $this->getOperationIcon($op);
                    ?>
                        <button type="button" 
                                class="pp-operation-tab <?php echo $first ? 'active' : ''; ?>" 
                                data-target="<?php echo esc_attr($tab_id); ?>">
                            <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                            <?php echo esc_html($op_data['caption']); ?>
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

        ?>
        <div class="pp-modern-permission-section">
            <!-- Sub-tabs for agent types -->
            <div class="pp-agent-type-tabs">
                <button type="button" class="pp-agent-type-tab active" data-agent-target="pp-roles-groups-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>">
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e('Roles & Groups', 'press-permit-core'); ?>
                </button>
                <button type="button" class="pp-agent-type-tab" data-agent-target="pp-users-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e('Users', 'press-permit-core'); ?>
                </button>
            </div>

            <!-- Roles & Groups Content -->
            <div id="pp-roles-groups-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>" class="pp-agent-type-content active">
                <div class="pp-permission-cards-grid">
                    <!-- WordPress Roles Card -->
                    <?php $this->renderRolesCard($op, $for_item_type, $current_exceptions, $reqd_caps, $hierarchical, $type_obj, $metagroup_exclude, $is_auth_metagroup, $item_id); ?>

                    <!-- Custom Groups Card -->
                    <?php $this->renderGroupsCard($op, $for_item_type, $via_item_type, $args, $current_exceptions, $reqd_caps, $hierarchical, $type_obj, $item_id, $pp_admin); ?>
                </div>
            </div>

            <!-- Users Content -->
            <div id="pp-users-<?php echo esc_attr($op); ?>-<?php echo esc_attr($for_item_type); ?>" class="pp-agent-type-content">
                <?php $this->renderUsersCard($op, $for_item_type, $via_item_type, $args, $current_exceptions, $reqd_caps, $hierarchical, $type_obj, $item_id, $pp_admin); ?>
            </div>
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

        ?>
        <div class="pp-permission-card">
            <div class="pp-permission-card-header">
                <span class="pp-card-title">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e('WordPress Roles', 'press-permit-core'); ?>
                </span>
            </div>
            <div class="pp-permission-list">
                <?php
                if (!empty($current_exceptions[$op]['wp_role'])) {
                    // Buffer original reqd_caps value
                    $_reqd_caps = (is_array($reqd_caps)) ? array_values($reqd_caps) : $reqd_caps;

                    foreach ($current_exceptions[$op]['wp_role'] as $agent_id => $agent_exceptions) {
                        if ($agent_id && isset($this->data->agent_info['wp_role'][$agent_id])) {
                            $role = $this->data->agent_info['wp_role'][$agent_id];
                            
                            if ((false === strpos($role->name, '[WP ')) || defined('PRESSPERMIT_DELETED_ROLE_EXCEPTIONS_UI')) {
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

                                $this->renderPermissionListItem(
                                    'wp_role',
                                    $agent_id,
                                    $current_exceptions[$op]['wp_role'][$agent_id],
                                    $role,
                                    compact('for_item_type', 'op', 'reqd_caps', 'hierarchical', 'item_id')
                                );
                            }
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
                ?>
            </div>
        </div>
        <?php
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

        // Populate all groups
        foreach ($this->data->agent_info['pp_group'] as $agent_id => $group) {
            if (!isset($current_exceptions[$op]['pp_group'][$agent_id])) {
                $current_exceptions[$op]['pp_group'][$agent_id] = [];
            }
        }

        ?>
        <div class="pp-permission-card">
            <div class="pp-permission-card-header">
                <span class="pp-card-title">
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e('Custom Groups', 'press-permit-core'); ?>
                </span>
            </div>
            <div class="pp-permission-list">
                <?php
                if (!empty($current_exceptions[$op]['pp_group'])) {
                    $any_groups_blocked = false;
                    
                    foreach ($current_exceptions[$op]['pp_group'] as $agent_id => $agent_exceptions) {
                        if ($agent_id && isset($this->data->agent_info['pp_group'][$agent_id])) {
                            // Check if blocked
                            if (!empty($agent_exceptions['item']['exclude'])) {
                                $any_groups_blocked = true;
                            }

                            $this->renderPermissionListItem(
                                'pp_group',
                                $agent_id,
                                $agent_exceptions,
                                $this->data->agent_info['pp_group'][$agent_id],
                                compact('for_item_type', 'op', 'reqd_caps', 'hierarchical', 'item_id')
                            );
                        }
                    }

                    if ($any_groups_blocked && !defined('PP_NO_GROUP_RESTRICTIONS')) {
                        ?>
                        <div class="pp-group-restrictions-warning">
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e("Group restrictions are not recommended.", 'press-permit-core'); ?>
                        </div>
                        <?php
                    }
                } else {
                    ?>
                    <div class="pp-empty-state">
                        <span class="dashicons dashicons-info"></span>
                        <p><?php esc_html_e('No group exceptions set', 'press-permit-core'); ?></p>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Users card
     */
    private function renderUsersCard($op, $for_item_type, $via_item_type, $args, $current_exceptions, $reqd_caps, $hierarchical, $type_obj, $item_id, $pp_admin)
    {
        ?>
        <div class="pp-permission-card pp-permission-card-full">
            <div class="pp-permission-card-header">
                <span class="pp-card-title">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e('Individual Users', 'press-permit-core'); ?>
                </span>
            </div>
            <div class="pp-permission-card-body">
                <!-- User selection dropdown -->
                <div class="pp-agent-selector">
                    <?php
                    $selector_args = array_merge($args, [
                        'suppress_extra_prefix' => true,
                        'ajax_selection' => true,
                        'display_stored_selections' => false,
                        'create_dropdowns' => true,
                        'op' => $op,
                        'via_item_type' => $via_item_type,
                    ]);

                    $pp_admin->agents()->agentsUI('user', [], "{$op}:{$for_item_type}:user", [], $selector_args);
                    ?>
                </div>

                <!-- Users list -->
                <div class="pp-permission-list">
                    <?php
                    if (!empty($current_exceptions[$op]['user'])) {
                        foreach (array_keys($this->data->agent_info['user']) as $agent_id) {
                            if ($agent_id && isset($current_exceptions[$op]['user'][$agent_id])) {
                                $this->renderPermissionListItem(
                                    'user',
                                    $agent_id,
                                    $current_exceptions[$op]['user'][$agent_id],
                                    $this->data->agent_info['user'][$agent_id],
                                    compact('for_item_type', 'op', 'reqd_caps', 'hierarchical', 'item_id')
                                );
                            }
                        }
                    } else {
                        ?>
                        <div class="pp-empty-state">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php esc_html_e('No user exceptions set. Use the dropdown above to add users.', 'press-permit-core'); ?></p>
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
        $defaults = ['reqd_caps' => false, 'hierarchical' => false, 'for_item_type' => '', 'op' => '', 'item_id' => 0];
        $args = array_merge($defaults, $args);
        extract($args);

        $pp = presspermit();

        // Determine agent display name
        $_name = $agent_info->name;
        $title = '';
        
        if ('wp_role' == $agent_type) {
            if (!empty($agent_info->metagroup_id)) {
                $_name = \PublishPress\Permissions\DB\Groups::getMetagroupName('wp_role', $agent_info->metagroup_id, $_name);
            }
        } elseif (('user' == $agent_type) && !empty($agent_info->display_name) && ($agent_info->display_name != $agent_info->name)) {
            $title = $agent_info->display_name;
        }

        // Determine current permission value
        $assignment_modes = ['item'];
        if ($hierarchical) {
            $assignment_modes[] = 'children';
        }

        foreach ($assignment_modes as $assign_for) {
            $current_val = '';
            $status_class = 'status-default';
            
            if (!empty($agent_exceptions[$assign_for]['additional'])) {
                $current_val = 2;
                $status_class = 'status-enabled';
            } elseif (isset($agent_exceptions[$assign_for]['include'])) {
                $current_val = 1;
                $status_class = 'status-enabled';
            } elseif (isset($agent_exceptions[$assign_for]['exclude'])) {
                $current_val = 0;
                $status_class = 'status-blocked';
            }

            // Determine if disabled
            $disabled = false;
            if ('wp_role' == $agent_type && !empty($reqd_caps) && is_array($reqd_caps)) {
                if (property_exists($agent_info, 'capabilities') && array_intersect((array)$agent_info->capabilities, $reqd_caps)) {
                    $disabled = true;
                }
            }

            // Get options based on inclusions_active
            if ($this->data->inclusions_active) {
                $options = [
                    '' => esc_html__('(No setting)', 'press-permit-core'),
                    '1' => esc_html__('Unblocked', 'press-permit-core'),
                    '2' => esc_html__('Enabled', 'press-permit-core'),
                ];
            } else {
                $options = [
                    '' => esc_html__('Default access', 'press-permit-core'),
                    '2' => esc_html__('Enabled', 'press-permit-core'),
                    '0' => esc_html__('Blocked', 'press-permit-core'),
                ];
            }

            // Get CSS class for current value
            $select_class = '';
            if ($current_val === 0) {
                $select_class = 'pp-no';
            } elseif ($current_val === 1 || $current_val === 2) {
                $select_class = 'pp-yes';
            } else {
                $select_class = 'pp-def';
            }

            // Only render item assignment for now (not children)
            if ($assign_for === 'item') {
                $for_type = ($for_item_type) ? $for_item_type : '(all)';
                $css_class = strtolower(str_replace([' ', '_'], '-', $_name));
                ?>
                <div class="permissioner-list-item <?php echo esc_attr($css_class); ?>">
                    <div class="item-name">
                        <div class="item-icon">
                            <?php echo esc_html(strtoupper(substr($_name, 0, 1))); ?>
                        </div>
                        <span><?php echo esc_html($_name); ?></span>
                    </div>
                    <div class="permission-control">
                        <div class="permission-select">
                            <select name="pp_exceptions[<?php echo esc_attr($for_type); ?>][<?php echo esc_attr($op); ?>][<?php echo esc_attr($agent_type); ?>][<?php echo esc_attr($assign_for); ?>][<?php echo esc_attr($agent_id); ?>]" 
                                    class="<?php echo esc_attr($select_class); ?>" 
                                    <?php echo $disabled ? 'disabled="disabled"' : ''; ?>
                                    autocomplete="off">
                                <?php foreach ($options as $val => $label) : 
                                    $selected = ($val === $current_val || ('' === $val && '' === $current_val)) ? 'selected="selected"' : '';
                                ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php echo $selected; ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
    }
}

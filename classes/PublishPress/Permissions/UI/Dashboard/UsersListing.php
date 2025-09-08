<?php

namespace PublishPress\Permissions\UI\Dashboard;

class UsersListing
{
    public function __construct() {
        add_action('admin_init', function() {
            global $current_user, $pagenow, $wpdb;

            if ('users.php' == $pagenow) {
                if (empty($_POST) && !get_user_option('set_default_hidden_users_cols')) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    update_user_option($current_user->ID, 'set_default_hidden_users_cols', true);

                    if (!$hide_cols = get_user_option('manageuserscolumnshidden')) {
                        $hide_cols = [];
                    }

                    $hide_cols = array_unique(array_merge((array) $hide_cols, ['pp_exceptions']));
                    update_user_option($current_user->ID, 'manageuserscolumnshidden', $hide_cols);
                }

                // Also recover from previous storage to wrong meta_key
                if (!empty($wpdb->prefix) && get_user_option($wpdb->prefix . 'default_hide_users_cols')) {
                    delete_user_option($current_user->ID, $wpdb->prefix . 'default_hide_users_cols');
                }
            }
        });

        add_filter('manage_users_columns', [$this, 'fltUsersColumns']);
        add_filter('manage_users_custom_column', [$this, 'fltUsersCustomColumn'], 99, 4); // filter late in case other plugin filters do not retain passed value
        add_filter('manage_users_sortable_columns', [$this, 'fltUsersColumnsSortable']);
        
        add_filter('pre_user_query', [$this, 'fltUserQueryExceptions']);
        
        add_action('restrict_manage_users', [$this, 'actBulkGroupsUI']);
        
        $this->groupsBulk();
    }

    public function actBulkGroupsUI()
    {
        static $done;
        $sfx = (!empty($done)) ? '2' : '';
        $done = true;

        $pp = presspermit();

        if (!$pp->getOption('users_bulk_groups'))
            return;

        if (!$agent_type = apply_filters('presspermit_query_group_type', ''))
            $agent_type = 'pp_group';

        $groups = $pp->groups()->getGroups($agent_type, ['include_metagroups' => false]);

        if (!count($groups) || !current_user_can('list_users'))
            return;

        if (!current_user_can('pp_manage_members')) {
            if (!$editable_groups = apply_filters('presspermit_admin_groups', [])) {
                return;
            }

            $groups = Arr::subset($groups, $editable_groups);
        }
        ?>

        <label class="screen-reader-text" for="pp-add-group"><?php esc_html_e('Permissions&hellip;', 'press-permit-core') ?></label>
        <select name="pp-bulk-group<?php echo esc_attr($sfx); ?>" id="pp-bulk-group<?php echo esc_attr($sfx); ?>" class="pp-bulk-groups"
                style="display:inline-block; float:none;" autocomplete="off">
            <option value=''><?php esc_html_e('Permissions&hellip;', 'press-permit-core') ?></option>
            <?php
            foreach ($groups as $group_id => $group) : ?>
                <option value="<?php echo esc_attr($group_id); ?>"><?php echo esc_html($group->name); ?></option>
            <?php endforeach; ?>
        </select>

        <?php
        submit_button(
            esc_html__('Add', 'press-permit-core'),
            'secondary',
            'pp-add-group-members' . $sfx,
            false,
            ['title' => esc_html__('Add selected users to Permission Group', 'press-permit-core')]
        );
        ?>

        <?php
        submit_button(
            esc_html__('Remove', 'press-permit-core'),
            'secondary',
            'pp-remove-group-members' . $sfx,
            false,
            ['title' => esc_html__('Remove selected users from Permission Group', 'press-permit-core')]
        );

        wp_nonce_field( 'pp-bulk-assign-groups', 'pp-bulk-groups-nonce' );
    }

    public static function fltUsersColumns($defaults)
    {
        $title = esc_html__('Click to show only users who have no group', 'press-permit-core');

        $style = (!PWP::empty_REQUEST('pp_no_group') && !PWP::is_REQUEST('orderby', 'pp_group'))
            ? 'style="font-weight:bold; color:black"'
            : '';

        $defaults['pp_groups'] = esc_html__('Groups', 'press-permit-core');

        $title = esc_html__('Click to show only users who have extra roles', 'press-permit-core');
        $style = (!PWP::empty_REQUEST('pp_has_roles')) ? 'style="font-weight:bold; color:black"' : '';

        $defaults['pp_roles'] = esc_html__('Roles', 'press-permit-core');

        unset($defaults['role']);

        if (defined('PRESSPERMIT_USERS_HIDE_BBP_ROLE')) {
            unset($defaults['bbp_user_role']);
        }

        $title = esc_html__('Click to show only users who have Specific Permissions assigned directly', 'press-permit-core');

        $class_pp_plugin_page = '\PublishPress\Permissions\UI\PluginPage';
        $style = (class_exists($class_pp_plugin_page) && $class_pp_plugin_page::viewFilter('pp_has_exceptions')) ? 'style="font-weight:bold; color:black"' : '';

        $defaults['pp_exceptions'] = esc_html__('Specific Permissions', 'press-permit-core');

        return $defaults;
    }

    public static function fltUsersColumnsSortable($columns)
    {
        $columns['pp_groups'] = 'pp_group';
        return $columns;
    }

    public static function fltUsersCustomColumn($content, $column_name, $id, $args = [])
    {
        $pp_groups = presspermit()->groups();

        global $wp_list_table, $wp_roles;

        if (empty($args['table_obj'])) {
            $table_obj = $wp_list_table;
        } else {
            $table_obj = $args['table_obj'];
        }

        switch ($column_name) {
            case 'pp_groups':
                static $all_groups;
                static $all_group_types;

                if (!isset($all_groups)) {
                    $all_groups = [];
                    $all_group_types = $pp_groups->getGroupTypes(['editable' => true]);
                }

                $all_group_names = [];

                foreach ($all_group_types as $agent_type) {
                    if (!isset($all_groups[$agent_type]))
                        $all_groups[$agent_type] = $pp_groups->getGroups($agent_type);

                    if (empty($all_groups[$agent_type]))
                        continue;

                    // Passing WP_User objects as query_user_ids causes each of those user's wp_role metagroups to be synchronized with their WP roles
                    $group_ids = $pp_groups->getGroupsForUser(
                        new \WP_User($id),
                        $agent_type,
                        [
                            'cols' => 'id', 
                            'query_agent_ids' => !empty($args['table_obj']) ? $table_obj->user_ids : array_keys($table_obj->items)
                        ]
                    );

                    if (('pp_group' == $agent_type) && in_array('pp_net_group', $all_group_types, true)
                        && (1 == get_current_blog_id())
                    ) {
                        continue;
                    }

                    $group_names = [];

                    if ($group_ids) {
                        if ('pp_group' == $agent_type) {
                            if (!current_user_can('pp_manage_members')) {
                                $group_ids = Arr::subset($group_ids, apply_filters('presspermit_admin_groups', []));
                            }
                        }

                        foreach (array_keys($group_ids) as $group_id) {
                            if (isset($all_groups[$agent_type][$group_id])) {
                                if (
                                    empty($all_groups[$agent_type][$group_id]->metagroup_type)
                                    || ('wp_role' != $all_groups[$agent_type][$group_id]->metagroup_type)
                                ) {
                                    $group_names[$all_groups[$agent_type][$group_id]->name] = $group_id;
                                }
                            }
                        }

                        if ($group_names) {
                            uksort($group_names, "strnatcasecmp");

                            foreach ($group_names as $name => $_id) {
                                if (!empty($any_done)) {
                                    $content .= ', ';
                                }
                                
                                if (defined('PP_USERS_UI_GROUP_FILTER_LINK') && !empty($_SERVER['REQUEST_URI'])) {
                                    $url = add_query_arg('pp_group', $_id, esc_url_raw($_SERVER['REQUEST_URI']));
                                    $content .= "<a href='" . esc_url($url) . "'>" . esc_html($name) . "</a>";
                                } else {
                                    $content .= "<a href='"
                                        . esc_url("admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_type=$agent_type&amp;agent_id=$_id")
                                        . "' title='" . esc_attr__('edit group', 'press-permit-core') . "'>"
                                        . esc_html($name) . "</a>";
                                }

                                $any_done = true;
                            }
                        }
                    }
                }

                break;

            case 'pp_roles':
                static $role_info;

                $role_str = '';

                if (!isset($role_info)) {
                    $_args = ['query_agent_ids' => !empty($args['table_obj']) ? $table_obj->user_ids : array_keys($table_obj->items)];

                    if (isset($args['join_groups'])) {
                        $_args['join_groups'] = $args['join_groups'];
                    }

                    $role_info = \PublishPress\Permissions\API::countRoles('user', $_args);
                }

                $user_object = new \WP_User((int)$id);

                static $hide_roles;
                if (!isset($hide_roles)) {
                    $hide_roles = (!defined('bbp_get_version'))
                        ? ['bbp_participant', 'bbp_moderator', 'bbp_keymaster', 'bbp_blocked', 'bbp_spectator']
                        : [];

                    $hide_roles = apply_filters('presspermit_hide_roles', $hide_roles);
                }
                
                $user_object->roles = array_diff($user_object->roles, $hide_roles);

                $role_titles = [];
                foreach ($user_object->roles as $role_name) {
                    if (isset($wp_roles->role_names[$role_name]))
                        $role_titles[] = $wp_roles->role_names[$role_name];
                }

                if (isset($role_info[$id]) && isset($role_info[$id]['roles'])) {
                    $role_titles = array_merge($role_titles, array_keys($role_info[$id]['roles']));
                }

                $display_limit = 3;
                if (count($role_titles) > $display_limit) {
                    $excess = count($role_titles) - $display_limit;
                    $role_titles = array_slice($role_titles, 0, $display_limit);
                    $role_titles[] = str_replace(' ', '&nbsp;', sprintf(__('%s more', 'press-permit-core'), (int) $excess));
                }

                if ($do_edit_link = current_user_can('pp_assign_roles') && (is_multisite() || current_user_can('edit_user', $id))) {
                    $edit_link = "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$id&amp;agent_type=user";
                    $content .= "<a href='" . esc_url($edit_link) . "' ' title='" . esc_attr__('edit user permissions', 'press-permit-core') . "'>";
                }

                $content .= '<span class="pp-group-site-roles">' . implode(', ', $role_titles) . '</span>';

                if ($do_edit_link) {
                    $content .= '</a>';	
                }
				
                break;

            case 'pp_exceptions':
                $_args = [
                    'query_agent_ids' => !empty($args['table_obj']) ? $table_obj->user_ids : array_keys($table_obj->items)
                ];

                if (isset($args['join_groups'])) {
                    $_args['join_groups'] = $args['join_groups'];
                }
                
                $content .= DashboardFilters::listAgentExceptions('user', $id, $_args);
                break;
        }

        return $content;
    }

    public static function fltUserQueryExceptions($query_obj)
    {
        global $wpdb, $current_user, $pagenow;

        // Filter the Users Query to support various group filtering / sorting parameters

        // Note: View filters are NOT applied on wp-admin/users.php
        $class_pp_plugin_page = '\PublishPress\Permissions\UI\PluginPage';

        if (PWP::is_REQUEST('orderby', 'pp_group') && !defined('AC_VERSION')) {  // Admin Columns plugin conflict
            $query_obj->query_where = " INNER JOIN $wpdb->pp_group_members AS gm ON gm.user_id = $wpdb->users.ID"  // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
                . " INNER JOIN $wpdb->pp_groups as g ON gm.group_id = g.ID AND g.metagroup_id='' "
                . $query_obj->query_where;

            $order = PWP::is_REQUEST('order', 'desc') ? 'DESC' : 'ASC';

            $query_obj->query_orderby = "ORDER BY g.group_name $order, $wpdb->users.display_name";                 // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users

        } elseif (class_exists($class_pp_plugin_page) && $class_pp_plugin_page::viewFilter('pp_no_group')) {
            $query_obj->query_where .= " AND $wpdb->users.ID NOT IN ( SELECT gm.user_id FROM $wpdb->pp_group_members AS gm"  // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
                . " INNER JOIN $wpdb->pp_groups as g ON gm.group_id = g.ID AND g.metagroup_id='' )";
        }

        if (class_exists($class_pp_plugin_page) && $class_pp_plugin_page::viewFilter('pp_user_exceptions')) {
            $query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e"
                . " INNER JOIN $wpdb->ppc_exception_items AS i"
                . " ON e.exception_id = i.exception_id WHERE e.agent_type = 'user' )";
        }

        if (class_exists($class_pp_plugin_page) && $class_pp_plugin_page::viewFilter('pp_user_roles')) {
            $query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_roles WHERE agent_type = 'user' )";
        }

        if (class_exists($class_pp_plugin_page) && $class_pp_plugin_page::viewFilter('pp_user_perms')) {
            $query_obj->query_where .= " AND ( ID IN ( SELECT agent_id FROM $wpdb->ppc_roles"
                . " WHERE agent_type = 'user' ) OR ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e"
                . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                . " WHERE e.agent_type = 'user' ) )";
        }

        if (class_exists($class_pp_plugin_page) && $class_pp_plugin_page::viewFilter('pp_has_exceptions')) {
            $query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e"
                . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                . " WHERE e.agent_type = 'user' )"
                . " OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug"
                . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.agent_id = ug.group_id AND e.agent_type = 'pp_group' )";
        }

        if (class_exists($class_pp_plugin_page) && $class_pp_plugin_page::viewFilter('pp_has_roles')) {
            $query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_roles WHERE agent_type = 'user' )"
                . " OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug"
                . " INNER JOIN $wpdb->ppc_roles AS r ON r.agent_id = ug.group_id AND r.agent_type = 'pp_group' )";
        }

        if (class_exists($class_pp_plugin_page) && $class_pp_plugin_page::viewFilter('pp_has_perms')) {
            $query_obj->query_where .= " AND ( ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e"
                . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                . " WHERE e.agent_type = 'user' )"
                . " OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug"
                . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.agent_id = ug.group_id AND e.agent_type = 'pp_group' )"
                . " OR ID IN ( SELECT agent_id FROM $wpdb->ppc_roles WHERE agent_type = 'user' )"
                . " OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug"
                . " INNER JOIN $wpdb->ppc_roles AS r ON r.agent_id = ug.group_id AND r.agent_type = 'pp_group' ) )";
        }

        if ($pp_group = PWP::REQUEST_int('pp_group')) {
            $query_obj->query_where .= $wpdb->prepare(
                " AND ID IN ( SELECT user_id FROM $wpdb->pp_group_members WHERE group_id = %d )",
                $pp_group
            );
        }

        return $query_obj;
    }

    private static function groupsBulk()
    {
        $sfx = '';

        if (!PWP::empty_REQUEST('pp-bulk-group2')) {
            $sfx = '2';
        } elseif (PWP::empty_REQUEST('pp-bulk-group')) {
            return;
        }

        if (
            PWP::empty_REQUEST('users') || (PWP::empty_REQUEST('pp-add-group-members' . $sfx)
                && PWP::empty_REQUEST('pp-remove-group-members' . $sfx))
        ) {
            return;
        }

        check_admin_referer( 'pp-bulk-assign-groups', 'pp-bulk-groups-nonce' );

        if (!current_user_can('list_users'))
            return;

        if (empty($_REQUEST['pp-bulk-group' . $sfx])) {
            return;
        }

        $group_id = (int) $_REQUEST['pp-bulk-group' . $sfx];

        $users = (!empty($_REQUEST['users'])) ? array_map('intval', $_REQUEST['users']) : [];

        if (!current_user_can('pp_manage_members')) {
            if (!in_array($group_id, apply_filters('presspermit_admin_groups', []))) {
                return;
            }

            global $current_user;
            $users = array_diff($users, [$current_user->ID]);
        }

        if (!empty($_REQUEST['pp-add-group-members' . $sfx])) {
            presspermit()->groups()->addGroupUser($group_id, $users);

        } elseif (!empty($_REQUEST['pp-remove-group-members' . $sfx])) {
            presspermit()->groups()->removeGroupUser($group_id, $users);
        }
    }

}

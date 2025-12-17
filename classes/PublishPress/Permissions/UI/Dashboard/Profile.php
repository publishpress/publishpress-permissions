<?php

namespace PublishPress\Permissions\UI\Dashboard;

require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentPermissionsUI.php');
require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsMeta.php');

class Profile
{
    public static function displayUserAssignedRoles($user)
    {
        $roles = [];

        $pp = presspermit();

        $post_types = $pp->getEnabledPostTypes([], 'object');
        $taxonomies = $pp->getEnabledTaxonomies([], 'object');

        $is_administrator = current_user_can('pp_administer_content') && current_user_can('list_users');

        $edit_url = ($is_administrator)
            ? "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$user->ID&amp;agent_type=user"
            : '';

        $roles = $pp->getRoles($user->ID, 'user', ['post_types' => $post_types, 'taxonomies' => $taxonomies]);

        $has_user_roles = \PublishPress\Permissions\UI\AgentPermissionsUI::currentRolesUI(
            $roles,
            [
                'read_only' => true,
                'context' => 'user-edit',
                'caption' => sprintf(esc_html__('Extra Roles %1$s(for this user)%2$s', 'press-permit-core'), '', ''),
                'link' => $edit_url
            ]
        );

        $caption = sprintf(esc_html__('Specific Permissions %1$s(for this user)%2$s', 'press-permit-core'), '', '');
        $new_permissions_link = true;
        $maybe_display_note = !$has_user_roles;
        $display_limit = 12;
        $echo = true;

        self::abbreviatedExceptionsList(
            'user',
            $user->ID,
            compact('edit_url', 'caption', 'new_permissions_link', 'maybe_display_note', 'display_limit', 'echo')
        );
    }

    public static function displayUserRoles($user)
    {
        $roles = [];

        $pp = presspermit();

        $post_types = $pp->getEnabledPostTypes([], 'object');
        $taxonomies = $pp->getEnabledTaxonomies([], 'object');

        $user->retrieveExtraGroups();
        $user->getSiteRoles();

        foreach (array_keys($user->groups) as $agent_type) {
            foreach (array_keys($user->groups[$agent_type]) as $agent_id) {
                $roles = array_merge(
                    $roles,
                    $pp->getRoles(
                        $agent_id,
                        $agent_type,
                        ['post_types' => $post_types, 'taxonomies' => $taxonomies, 'query_agent_ids' => array_keys($user->groups[$agent_type])]
                    )
                );
            }
        }

        \PublishPress\Permissions\UI\AgentPermissionsUI::currentRolesUI(
            $roles,
            [
                'read_only' => true,
                'context' => 'user-edit',
                'link' => '',
                'caption' => sprintf(esc_html__('Extra Roles %1$s(from primary role or group membership)%2$s', 'press-permit-core'), '', '')
            ]
        );

        self::abbreviatedExceptionsList(
            'user',
            $user->ID,
            [
                'edit_url' => '',
                'caption' => esc_html__('Specific Permissions (from primary role or group membership)', 'press-permit-core'),
                'join_groups' => 'groups_only',
                'display_limit' => 12
            ]
        );
    }

    public static function displayUserGroups($user_id = 0, $args = [])
    {
        global $pagenow;

        $defaults = [
            'initial_hide' => false,
            'selected_only' => false,
            'hide_checkboxes' => false,
            'force_display' => false,
            'edit_membership_link' => false,
            'include_role_metagroups' => false,
        ];

        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();
        $pp_groups = $pp->groups();

        if (is_object($user_id)) {
            $user_id = $user_id->ID; 
        }

        $group_types = $pp_groups->getGroupTypes(['editable' => true]);

        $is_main_site = (defined('PRESSPERMIT_LEGACY_MAIN_SITE_CHECK')) ? (1 == get_current_blog_id()) : is_main_site();

        foreach ($group_types as $agent_type) {
            if (('pp_group' == $agent_type) && in_array('pp_net_group', $group_types, true) && $is_main_site) {
                continue;
            }

            if (!$all_groups = $pp_groups->getGroups($agent_type)) {
                continue;
            }

            if (!in_array($agent_type, ['pp_group', 'pp_net_group'], true)) {
                continue;
            }

            $editable_ids = (current_user_can('pp_manage_members'))
                ? array_keys($all_groups)
                : apply_filters('presspermit_admin_groups', []);

            $stored_groups = $pp_groups->getGroupsForUser($user_id, $agent_type, ['cols' => 'id']);

            $locked_ids = array_diff(array_keys($stored_groups), $editable_ids);

            // can't manually edit membership of WP Roles groups or other metagroups lacking _ed_ suffix
            $all_ids = [];
            foreach ($all_groups as $key => $group) {
                if ($selected_only && !isset($stored_groups[$group->ID])) {
                    unset($all_groups[$key]);
                    continue;
                }

                $all_ids[] = $group->ID;

                if (!$include_role_metagroups && !empty($group->metagroup_id) && ('wp_role' == $group->metagroup_type)) {
                    $editable_ids = array_diff($editable_ids, [$group->ID]);
                    unset($stored_groups[$group->ID]);
                    unset($all_groups[$key]);
                } elseif (!in_array($group->ID, $editable_ids) && !in_array($group->ID, $locked_ids)) {
                    unset($all_groups[$key]);
                }
            }

            $locked_ids = array_diff(array_keys($stored_groups), $editable_ids);

            // avoid incorrect eligible count if orphaned group roles are included in editable_ids
            $editable_ids = array_intersect($editable_ids, $all_ids);

            if (!$all_groups && !$force_display) {
                continue;
            }

            $style = ($initial_hide) ? "display:none" : '';

            echo "<div id='userprofile_groupsdiv_pp' class='pp_user_profile' style='" . esc_attr($style) . "'>";
            ?>
            <div class="permission-section">
                <div class="section-header">
                    <h2 class="section-title">
                    <?php
                    if ('pp_group' == $agent_type) {
                        if (defined('GROUPS_CAPTION_RS')) {
                            echo esc_html(GROUPS_CAPTION_RS);
                        } elseif (defined('PP_GROUPS_CAPTION')) {
                            echo esc_html(PP_GROUPS_CAPTION);
                        } else {
                            esc_html_e('Permission Groups', 'press-permit-core');
                        }
                    } else {
                        $group_type_obj = $pp_groups->getGroupTypeObject($agent_type);
                        echo esc_html($group_type_obj->labels->name);
                    } ?>
                    </h2>
                </div>
            <div class="section-content">
                <div class="permission-type">
            <?php

            // This ajax request is just to return UI
            $single_select = ('user-new.php' == $pagenow) || ('new_user_groups_ui' == PWP::REQUEST_key('pp_ajax_user'))
            ? defined('PRESSPERMIT_ADD_USER_SINGLE_GROUP_SELECT') 
            : defined('PRESSPERMIT_EDIT_USER_SINGLE_GROUP_SELECT');

            $css_id = $agent_type;
            $args = [
                'eligible_ids' => $editable_ids,
                'locked_ids' => $locked_ids,
                'show_subset_caption' => false,
                'hide_checkboxes' => $hide_checkboxes,
                'single_select' => $single_select
            ];

            $pp->admin()->agents()->agentsUI($agent_type, $all_groups, $css_id, $stored_groups, $args);
            
            if ($edit_membership_link || (!$all_groups && $force_display)) :
                ?>
                <p>
                    <?php if (!$all_groups && $force_display) :
                        esc_html_e('This user is not a member of any custom Permission Groups.', 'press-permit-core');
                        ?>&nbsp;&bull;&nbsp;
                    <?php endif; ?>

                    <?php $title = esc_attr(__("Edit this user's group membership", 'press-permit-core')); ?>
                    <a href='user-edit.php?user_id=<?php echo esc_attr($user_id); ?>#userprofile_groupsdiv_pp'
                       title='<?php echo esc_attr($title); ?>'>
                        <?php esc_html_e('add / edit membership'); ?>
                    </a>
                    &nbsp;&nbsp;
                    <span class="pp-subtext">
                    <?php
                    $note = (defined('BP_VERSION'))
                        ? __('Note: BuddyPress Groups and other externally defined groups are not listed here, even if they modify permissions', 'press-permit-core')
                        : '';

                    $note = apply_filters(
                        'presspermit_user_profile_groups_note',
                        $note,
                        $user_id,
                        $args
                    );

                    echo esc_html($note);
                    ?>
                </span>
                </p>
            <?php
            endif;

            echo '</div>';
            echo '</div>';
            echo "<input type='hidden' name='pp_editing_user_groups' value='1' />";
            wp_nonce_field('pp-user-profile-groups', '_pp_permissions_nonce');
            echo '</div>';
            echo '</div>';
        }  // end foreach agent_type
    }
    
    public static function listAgentExceptions($agent_type, $id, $args = [])
    {
        static $exception_info;

        $defaults = ['query_agent_ids' => [], 'show_link' => true, 'join_groups' => true, 'force_refresh' => false, 'display_limit' => 3, 'echo' => false];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (empty($args['query_agent_ids'])) {
            $args['query_agent_ids'] = (array)$id;
        }

        if (!isset($exception_info) || $force_refresh) {
            $exception_info = \PublishPress\Permissions\DB\PermissionsMeta::countExceptions($agent_type, $args);
        }

        $exc_str = '';

        if (isset($exception_info[$id])) {
            if (isset($exception_info[$id]['exceptions'])) {
                $any_exceptions = true;

                $exc_titles = [];
                $i = 0;
                foreach ($exception_info[$id]['exceptions'] as $exc_title => $exc_count) {
                    $i++;
                    $exc_titles[] = sprintf(esc_html__('%1$s (%2$s)', 'press-permit-core'), $exc_title, $exc_count);
                    if ($i >= $display_limit) {
                        break;
                    }
                }

                $titles_list = implode(', ', $exc_titles);

                if (count($exception_info[$id]['exceptions']) > $display_limit) {
                    $titles_list = sprintf(__('%s, more...', 'press-permit-core'), $titles_list);
                }

                if ($echo) {
                    echo '<span class="pp-group-site-roles">';

                    if ($show_link && current_user_can('pp_assign_roles') && (is_multisite() || current_user_can('edit_user', $id))) {
                        $edit_link = "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$id&amp;agent_type=user";
                        echo "<a href='" . esc_url($edit_link) . "' title='" . esc_attr__('edit user permissions', 'press-permit-core') . "'>" . esc_html($titles_list) . "</a><br />";
                    } else {
                        echo esc_html($titles_list);
                    }

                    echo '</span>';
                } else {
                    $exc_str = '<span class="pp-group-site-roles">';

                    if ($show_link && current_user_can('pp_assign_roles') && (is_multisite() || current_user_can('edit_user', $id))) {
                        $edit_link = "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$id&amp;agent_type=user";
                        $exc_str .= "<a href='" . esc_url($edit_link) . "' title='" . esc_attr__('edit user permissions', 'press-permit-core') . "'>" . esc_html($titles_list) . "</a><br />";
                    } else {
                        $exc_str .= esc_html($titles_list);
                    }

                    $exc_str .= '</span>';
                }
            }
        }

        return ($echo) ? !empty($any_exceptions) : $exc_str;
    }

    private static function abbreviatedExceptionsList($agent_type, $agent_id, $args = [])
    {
        $defaults = [
            'caption' => '',
            'edit_url' => '',
            'join_groups' => false,
            'class' => '',
            'new_permissions_link' => false,
            'maybe_display_note' => true
        ];

        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();

        $args['show_link'] = false;
        $args['force_refresh'] = true;
        $args['echo'] = true;

        $new_permissions_link = $maybe_display_note && $pp->isUserAdministrator()
            && $pp->admin()->bulkRolesEnabled() && current_user_can('list_users');

        ob_start();
        ?>
        <div class='pp_current_exceptions_profile <?php echo esc_attr($class); ?>'>
            <div class="permission-section">
            <div class="section-header">
                <h2 class="section-title">
                    <?php
                    if ($edit_url) echo "<a href='" . esc_url($edit_url) . "'>" . esc_html($caption) . "</a>"; else echo esc_html($caption);
                    ?>
                </h2>
            </div>
            <div id="pp_current_post_post_site_roles" class="section-content">
                <div class="for-type for-type-post">
                <div class="permission-type">
                    <div class="subsection-header permission-type-header">
                    <h3 class="section-title permission-type-title">
                        <?php self::listAgentExceptions($agent_type, $agent_id, $args); ?>
                    </h3>
                    </div>
                </div>
                </div>
            </div>
            </div>
        </div>
        <?php
    }
}

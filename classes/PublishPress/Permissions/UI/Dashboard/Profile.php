<?php

namespace PublishPress\Permissions\UI\Dashboard;

require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentPermissionsUI.php');
require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsMeta.php');

class Profile
{
    public static function displayUserPermissionsSummary($user, $args = [])
    {
        $defaults = [
            'show_groups' => true,
            'show_roles' => true,
        ];

        $args = array_merge($defaults, $args);
        $is_administrator = current_user_can('pp_administer_content') && current_user_can('list_users');

        $current_roles_edit_url = $is_administrator
            ? admin_url("admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$user->ID&amp;agent_type=user#pp_current_roles_1")
            : '';

        $edit_url = $is_administrator
            ? admin_url("admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$user->ID&amp;agent_type=user#pp_current_exceptions_1")
            : '';

        $group_summary = $args['show_groups'] ? self::getProfileGroupsSummary($user->ID) : ['rows' => [], 'fields' => ''];
        $rows = $group_summary['rows'];

        if ($args['show_roles']) {
            $rows = array_merge(
                $rows,
                [
                    self::getProfileRolesSummary(
                        'user',
                        $user->ID,
                        [
                            'edit_url' => $current_roles_edit_url,
                            'label' => esc_html__('Extra Roles', 'press-permit-core'),
                            'scope' => esc_html__('Assigned directly', 'press-permit-core'),
                            'display_limit' => 5,
                        ]
                    ),
                    self::getProfileExceptionsSummary(
                        'user',
                        $user->ID,
                        [
                            'edit_url' => $edit_url,
                            'label' => esc_html__('Specific Permissions', 'press-permit-core'),
                            'scope' => esc_html__('Assigned directly', 'press-permit-core'),
                            'display_limit' => 12,
                        ]
                    ),
                    self::getProfileRolesSummary(
                        'user',
                        $user->ID,
                        [
                            'edit_url' => '',
                            'label' => esc_html__('Extra Roles', 'press-permit-core'),
                            'scope' => esc_html__('From primary role or group membership', 'press-permit-core'),
                            'join_groups' => 'groups_only',
                            'display_limit' => 5,
                        ]
                    ),
                    self::getProfileExceptionsSummary(
                        'user',
                        $user->ID,
                        [
                            'edit_url' => '',
                            'label' => esc_html__('Specific Permissions', 'press-permit-core'),
                            'scope' => esc_html__('From primary role or group membership', 'press-permit-core'),
                            'join_groups' => 'groups_only',
                            'display_limit' => 12,
                        ]
                    ),
                ]
            );
        }

        if (!$rows) {
            return;
        }

        $total_count = array_sum(array_map(function ($row) {
            return (int)$row['count'];
        }, $rows));

        ?>
        <div class='pp_current_exceptions_profile pp-profile-permissions-summary'>
            <div class="permission-section">
                <div class="section-header">
                    <div class="pp-profile-summary-heading">
                        <h2 class="section-title"><?php esc_html_e('Permissions Summary', 'press-permit-core'); ?></h2>
                        <span class="badge badge-count"><span class="count-num"><?php echo (int)$total_count; ?></span> <?php esc_html_e('item(s)', 'press-permit-core'); ?></span>
                    </div>
                </div>
                <div id="pp_permissions_summary_<?php echo esc_attr((int)$user->ID); ?>" class="section-content">
                    <div class="pp-profile-summary-grid">
                        <?php foreach ($rows as $row) : ?>
                            <div id="<?php echo esc_attr($row['section_id']); ?>" class="pp-profile-summary-row <?php echo esc_attr($row['class']); ?>">
                                <div class="pp-profile-summary-row-header">
                                    <div class="pp-profile-summary-row-title">
                                        <h3><?php echo esc_html($row['label']); ?></h3>
                                        <span class="pp-profile-summary-scope"><?php echo esc_html($row['scope']); ?></span>
                                    </div>
                                    <div class="pp-profile-summary-row-actions">
                                        <span class="badge badge-count"><span class="count-num"><?php echo (int)$row['count']; ?></span> <?php esc_html_e('item(s)', 'press-permit-core'); ?></span>
                                        <?php if ($row['edit_url']) : ?>
                                            <a class="button button-small pp-profile-summary-edit" href="<?php echo esc_url($row['edit_url']); ?>">
                                                <?php esc_html_e('Edit', 'press-permit-core'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="pp-profile-summary-row-body">
                                    <?php if ($row['content']) : ?>
                                        <div class="pp-profile-summary-list">
                                            <?php
                                            if (!empty($row['allow_form_controls'])) {
                                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by the existing escaped agent membership UI.
                                                echo $row['content'];
                                            } else {
                                                echo wp_kses_post($row['content']);
                                            }
                                            ?>
                                        </div>
                                    <?php else : ?>
                                        <p class="pp-profile-summary-empty">
                                            <?php echo esc_html($row['empty_text']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php echo $group_summary['fields']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress nonce and escaped hidden field markup. ?>
        </div>
        <?php
    }

    public static function displayUserAssignedRoles($user)
    {
        self::displayUserPermissionsSummary($user);
    }

    public static function displayUserRoles($user)
    {
        self::abbreviatedRolesList(
            'user',
            $user->ID,
            [
                'edit_url' => '',
                'caption'  => sprintf(esc_html__('Extra Roles %1$s(from primary role or group membership)%2$s', 'press-permit-core'), '', ''),
                'join_groups' => 'groups_only',
                'display_limit' => 5,
            ]
        );

        self::abbreviatedExceptionsList(
            'user',
            $user->ID,
            [
                'edit_url' => '',
                'caption' => esc_html__('Specific Permissions (from primary role or group membership)', 'press-permit-core'),
                'join_groups' => 'groups_only',
                'display_limit' => 12,
            ]
        );
    }

    private static function getProfileGroupsSummary($user_id = 0, $args = [])
    {
        global $pagenow;

        $defaults = [
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

        $rows = [];
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
            $editable_ids = array_intersect($editable_ids, $all_ids);

            if (!$all_groups && !$force_display) {
                continue;
            }

            if ('pp_group' == $agent_type) {
                if (defined('GROUPS_CAPTION_RS')) {
                    $caption = GROUPS_CAPTION_RS;
                } elseif (defined('PP_GROUPS_CAPTION')) {
                    $caption = PP_GROUPS_CAPTION;
                } else {
                    $caption = esc_html__('Permission Groups', 'press-permit-core');
                }
            } else {
                $group_type_obj = $pp_groups->getGroupTypeObject($agent_type);
                $caption = $group_type_obj->labels->name;
            }

            $single_select = ('user-new.php' == $pagenow) || ('new_user_groups_ui' == PWP::REQUEST_key('pp_ajax_user'))
                ? defined('PRESSPERMIT_ADD_USER_SINGLE_GROUP_SELECT')
                : defined('PRESSPERMIT_EDIT_USER_SINGLE_GROUP_SELECT');

            $agent_ui_args = [
                'eligible_ids' => $editable_ids,
                'locked_ids' => $locked_ids,
                'show_subset_caption' => false,
                'hide_checkboxes' => $hide_checkboxes,
                'link_captions' => true,
                'current_only' => true,
                'single_select' => $single_select
            ];

            ob_start();
            $pp->admin()->agents()->agentsUI($agent_type, $all_groups, $agent_type, $stored_groups, $agent_ui_args);

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
                        $agent_ui_args
                    );

                    echo esc_html($note);
                    ?>
                </span>
                </p>
            <?php
            endif;
            $content = ob_get_clean();

            $rows[] = [
                'label' => $caption,
                'scope' => esc_html__('Group membership', 'press-permit-core'),
                'edit_url' => '',
                'count' => count($stored_groups),
                'content' => $content,
                'empty_text' => esc_html__('This user is not a member of any custom Permission Groups.', 'press-permit-core'),
                'section_id' => ('pp_group' == $agent_type) ? 'userprofile_groupsdiv_pp' : "userprofile_groupsdiv_{$agent_type}",
                'class' => 'pp-profile-summary-row-groups',
                'allow_form_controls' => true,
            ];
        }

        if (!$rows) {
            return ['rows' => [], 'fields' => ''];
        }

        ob_start();
        echo "<input type='hidden' name='pp_editing_user_groups' value='1' />";
        wp_nonce_field('pp-user-profile-groups', '_pp_permissions_nonce');

        return ['rows' => $rows, 'fields' => ob_get_clean()];
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

            echo "<div id='userprofile_groupsdiv_pp' class='pp_user_profile pp-profile-groups-summary' style='" . esc_attr($style) . "'>";
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
            echo '</div>';
            echo '</div>';
        }  // end foreach agent_type

        echo "<input type='hidden' name='pp_editing_user_groups' value='1' />";

        wp_nonce_field('pp-user-profile-groups', '_pp_permissions_nonce');
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

                    if ($show_link && presspermit()->admin()->bulkRolesEnabled() && (is_multisite() || current_user_can('edit_user', $id))) {
                        $edit_link = "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$id&amp;agent_type=user";
                        echo "<a href='" . esc_url($edit_link) . "' title='" . esc_attr__('edit user permissions', 'press-permit-core') . "'>" . esc_html($titles_list) . "</a><br />";
                    } else {
                        echo esc_html($titles_list);
                    }

                    echo '</span>';
                } else {
                    $exc_str = '<span class="pp-group-site-roles">';

                    if ($show_link && presspermit()->admin()->bulkRolesEnabled() && (is_multisite() || current_user_can('edit_user', $id))) {
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

    private static function getProfileExceptionsSummary($agent_type, $agent_id, $args = [])
    {
        $defaults = [
            'label' => '',
            'scope' => '',
            'edit_url' => '',
            'join_groups' => false,
            'display_limit' => 3,
        ];

        $args = array_merge($defaults, $args);

        $count_args = [
            'query_agent_ids' => (array)$agent_id,
            'join_groups' => $args['join_groups']
        ];

        $exc_data = \PublishPress\Permissions\DB\PermissionsMeta::countExceptions($agent_type, $count_args);
        $badge_count = $exc_data[$agent_id]['exc_count'] ?? 0;

        $list_args = array_merge($args, [
            'show_link' => false,
            'force_refresh' => true,
            'echo' => false,
        ]);

        return [
            'label' => $args['label'],
            'scope' => $args['scope'],
            'edit_url' => $args['edit_url'],
            'count' => $badge_count,
            'content' => self::listAgentExceptions($agent_type, $agent_id, $list_args),
            'empty_text' => esc_html__('No specific permissions currently apply.', 'press-permit-core'),
            'section_id' => self::getProfileSectionId('pp_current_exceptions', $agent_id, $args['join_groups']),
            'class' => '',
            'allow_form_controls' => false,
        ];
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

        $count_args = ['query_agent_ids' => (array)$agent_id, 'join_groups' => $join_groups];
        $exc_data = \PublishPress\Permissions\DB\PermissionsMeta::countExceptions($agent_type, $count_args);
        $badge_count = $exc_data[$agent_id]['exc_count'] ?? 0;

        $args['show_link'] = false;
        $args['force_refresh'] = true;
        $args['echo'] = false;
        $permissions_list = self::listAgentExceptions($agent_type, $agent_id, $args);
        $section_id = self::getProfileSectionId('pp_current_exceptions', $agent_id, $join_groups);

        ?>
        <div class='pp_current_exceptions_profile pp-profile-permissions-summary <?php echo esc_attr($class); ?>'>
            <div class="permission-section">
                <div class="section-header">
                    <div class="pp-profile-summary-heading">
                        <h2 class="section-title"><?php echo esc_html($caption); ?></h2>
                        <span class="badge badge-count"><span class="count-num"><?php echo (int)$badge_count; ?></span> <?php esc_html_e('item(s)', 'press-permit-core'); ?></span>
                    </div>
                    <?php if ($edit_url) : ?>
                        <a class="button button-small pp-profile-summary-edit" href="<?php echo esc_url($edit_url); ?>">
                            <?php esc_html_e('Edit', 'press-permit-core'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div id="<?php echo esc_attr($section_id); ?>" class="section-content">
                    <div class="permission-type">
                        <?php if ($permissions_list) : ?>
                            <div class="pp-profile-summary-list">
                                <?php echo wp_kses_post($permissions_list); ?>
                            </div>
                        <?php else : ?>
                            <p class="pp-profile-summary-empty">
                                <?php esc_html_e('No specific permissions currently apply.', 'press-permit-core'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function listAgentRoles($agent_type, $id, $args = [])
    {
        static $role_info;

        $defaults = ['query_agent_ids' => [], 'show_link' => true, 'join_groups' => true, 'force_refresh' => false, 'display_limit' => 3, 'echo' => false];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (empty($args['query_agent_ids'])) {
            $args['query_agent_ids'] = (array)$id;
        }

        if (!isset($role_info) || $force_refresh) {
            $role_info = \PublishPress\Permissions\DB\PermissionsMeta::countRoles($agent_type, $args);
        }

        $role_str = '';

        if (isset($role_info[$id])) {
            if (isset($role_info[$id]['roles'])) {
                $any_roles = true;

                $role_titles = [];
                $i = 0;
                foreach ($role_info[$id]['roles'] as $role_title => $role_count) {
                    $i++;
                    $role_titles[] = sprintf(esc_html__('%1$s (%2$s)', 'press-permit-core'), $role_title, $role_count);
                    if ($i >= $display_limit) {
                        break;
                    }
                }

                $titles_list = implode(', ', $role_titles);

                if (count($role_info[$id]['roles']) > $display_limit) {
                    $titles_list = sprintf(__('%s, more...', 'press-permit-core'), $titles_list);
                }

                if ($echo) {
                    echo '<span class="pp-group-site-roles">';

                    if ($show_link && current_user_can('pp_manage_permissions') && (is_multisite() || current_user_can('edit_user', $id))) {
                        $edit_link = "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$id&amp;agent_type=user";
                        echo "<a href='" . esc_url($edit_link) . "' title='" . esc_attr__('edit user permissions', 'press-permit-core') . "'>" . wp_kses_post($titles_list) . "</a><br />";
                    } else {
                        echo wp_kses_post($titles_list);
                    }

                    echo '</span>';
                } else {
                    $role_str = '<span class="pp-group-site-roles">';

                    if ($show_link && current_user_can('pp_manage_permissions') && (is_multisite() || current_user_can('edit_user', $id))) {
                        $edit_link = "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=$id&amp;agent_type=user";
                        $role_str .= "<a href='" . esc_url($edit_link) . "' title='" . esc_attr__('edit user permissions', 'press-permit-core') . "'>" . wp_kses_post($titles_list) . "</a><br />";
                    } else {
                        $role_str .= wp_kses_post($titles_list);
                    }

                    $role_str .= '</span>';
                }
            }
        }

        return ($echo) ? !empty($any_roles) : $role_str;
    }

    private static function getProfileRolesSummary($agent_type, $agent_id, $args = [])
    {
        $defaults = [
            'label' => '',
            'scope' => '',
            'edit_url' => '',
            'join_groups' => false,
            'display_limit' => 3,
        ];

        $args = array_merge($defaults, $args);

        $count_args = [
            'query_agent_ids' => (array)$agent_id,
            'join_groups' => $args['join_groups']
        ];

        $role_data = \PublishPress\Permissions\DB\PermissionsMeta::countRoles($agent_type, $count_args);
        $badge_count = $role_data[$agent_id]['role_count'] ?? 0;

        $list_args = array_merge($args, [
            'show_link' => false,
            'force_refresh' => true,
            'echo' => false,
        ]);

        return [
            'label' => $args['label'],
            'scope' => $args['scope'],
            'edit_url' => $args['edit_url'],
            'count' => $badge_count,
            'content' => self::listAgentRoles($agent_type, $agent_id, $list_args),
            'empty_text' => esc_html__('No extra roles currently apply.', 'press-permit-core'),
            'section_id' => self::getProfileSectionId('pp_current_roles', $agent_id, $args['join_groups']),
            'class' => '',
            'allow_form_controls' => false,
        ];
    }

    private static function abbreviatedRolesList($agent_type, $agent_id, $args = [])
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

        $count_args = ['query_agent_ids' => (array)$agent_id, 'join_groups' => $join_groups];
        $role_data = \PublishPress\Permissions\DB\PermissionsMeta::countRoles($agent_type, $count_args);
        $badge_count = $role_data[$agent_id]['role_count'] ?? 0;

        $args['show_link'] = false;
        $args['force_refresh'] = true;
        $args['echo'] = false;
        $roles_list = self::listAgentRoles($agent_type, $agent_id, $args);
        $section_id = self::getProfileSectionId('pp_current_roles', $agent_id, $join_groups);

        ?>
        <div class='pp_current_exceptions_profile pp_current_roles_profile pp-profile-permissions-summary <?php echo esc_attr($class); ?>'>
            <div class="permission-section">
                <div class="section-header">
                    <div class="pp-profile-summary-heading">
                        <h2 class="section-title"><?php echo esc_html($caption); ?></h2>
                        <span class="badge badge-count"><span class="count-num"><?php echo (int)$badge_count; ?></span> <?php esc_html_e('item(s)', 'press-permit-core'); ?></span>
                    </div>
                    <?php if ($edit_url) : ?>
                        <a class="button button-small pp-profile-summary-edit" href="<?php echo esc_url($edit_url); ?>">
                            <?php esc_html_e('Edit', 'press-permit-core'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div id="<?php echo esc_attr($section_id); ?>" class="section-content">
                    <div class="permission-type">
                        <?php if ($roles_list) : ?>
                            <div class="pp-profile-summary-list">
                                <?php echo wp_kses_post($roles_list); ?>
                            </div>
                        <?php else : ?>
                            <p class="pp-profile-summary-empty">
                                <?php esc_html_e('No extra roles currently apply.', 'press-permit-core'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function getProfileSectionId($base_id, $agent_id, $join_groups)
    {
        $scope = $join_groups ? 'inherited' : 'direct';

        return sprintf('%s_%s_%s', $base_id, $scope, (int)$agent_id);
    }
}

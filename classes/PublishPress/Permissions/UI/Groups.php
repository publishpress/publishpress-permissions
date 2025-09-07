<?php

namespace PublishPress\Permissions\UI;

class Groups
{
    public function __construct()
    {
        global $current_user;

        // called by Dashboard\DashboardFilters::actMenuHandler

        $pp = presspermit();
        $pp_admin = $pp->admin();
        $pp_groups = $pp->groups();

        /**
         * Need to add dummy constants because this data dynamically comes from the database
         * and we want to use sync tool from loco translate to get this data automatically
         */
        $dummy_const = [
            'PP_GROUP_SHOP_MANAGER' => esc_html__("Shop manager", 'press-permit-core'),
            'PP_GROUP_TRANSLATOR' => esc_html__("Translator", 'press-permit-core'),
            'PP_GROUP_CUSTOMER' => esc_html__("Customer", 'press-permit-core'),
        ];

        // Nonce verification is not needed here because this is either:
        // (a) Confirming an update that already happened
        //  - or -
        // (b) Displaying a confirmation prompt (with nonce field) for deletion request

        if (!PWP::empty_REQUEST('action2') && !is_numeric(PWP::REQUEST_key('action2'))) {
            $action = PWP::REQUEST_key('action2');
        } elseif (!PWP::empty_REQUEST('action') && !is_numeric(PWP::REQUEST_key('action'))) {
            $action = PWP::REQUEST_key('action');
        } elseif (!PWP::empty_REQUEST('pp_action')) {
            $action = PWP::REQUEST_key('pp_action');
        } else {
            $action = '';
        }

        if (!$active_tab = PluginPage::viewFilter('permissions_tab')) {
            $active_tab = 'user-group';
        }
    
        if ('users' == $active_tab) {
            $agent_type = 'user';
            $group_variant = '';
        } else {
            if (!in_array($action, ['delete', 'bulkdelete'])) {
                if (!PWP::is_REQUEST('agent_type') && !PluginPage::viewFilter('pp_has_perms') && !PluginPage::viewFilter('pp_user_perms')) {
                    $agent_type = get_user_option('pp_agent_type');
                } else {
                    if (!$agent_type = PWP::REQUEST_key('agent_type')) {
                        $agent_type = 'pp_group';
                    }

                    update_user_option($current_user->ID, 'pp_agent_type', $agent_type);
                }
            } else {
                $agent_type = '';
            }

            $agent_type = PluginPage::getAgentType($agent_type);
            $group_variant = PluginPage::getGroupVariant();
        }

        switch ($action) {

            case 'delete':
            case 'bulkdelete':
                // phpcs Note: Nonce verification unnecessary because this is only generating a confirmation message,
                // or reporting on an update operation already completed.

                // group IDs processing is only to report number of groups that were updated

                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if (!empty($_REQUEST['groups'])) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $groupids = array_map('intval', (array) $_REQUEST['groups']);
                } else {
                    $groupids = (PWP::is_REQUEST('group')) ? [PWP::REQUEST_int('group')] : [];
                } ?>
                <form action="" method="post" name="updategroups" id="updategroups">
                    <?php wp_nonce_field('pp-bulk-groups'); ?>

                    <div class="wrap pressshack-admin-wrapper" id="pp-permissions-wrapper">
                        <?php PluginPage::icon(); ?>
                        <h1><?php esc_html_e('Delete Groups'); ?></h1>
                        <p><?php echo esc_html(_n('You have specified this group for deletion:', 'You have specified these groups for deletion:', count($groupids), 'press-permit-core')); ?>
                        </p>
                        <ul>
                            <?php
                            $go_delete = 0;

                            if (!$agent_type = apply_filters('presspermit_query_group_type', ''))
                                $agent_type = 'pp_group';

                            foreach ($groupids as $id) {
                                $id = (int) $id;
                                if ($group = $pp_groups->getGroup($id, $agent_type)) {
                                    if (
                                        empty($group->metagroup_type)
                                        || ('wp_role' == $group->metagroup_type && \PublishPress\Permissions\DB\Groups::isDeletedRole($group->metagroup_id))
                                    ) {
                                        echo "<li><input type=\"hidden\" name=\"users[]\" value=\"" . esc_attr($id) . "\" />"
                                            . sprintf(esc_html__('ID #%1s: %2s'), esc_html($id), esc_html($group->name))
                                            . "</li>\n";

                                        $go_delete++;
                                    }
                                }
                            }
                            ?>
                        </ul>
                        <?php if ($go_delete): ?>
                            <input type="hidden" name="action" value="dodelete" />
                            <?php submit_button(esc_html__('Confirm Deletion'), 'secondary'); ?>
                        <?php else: ?>
                            <p><?php esc_html_e('There are no valid groups selected for deletion.', 'press-permit-core'); ?></p>
                        <?php endif; ?>
                    </div>
                </form>
                <?php

                break;

            default:
                $url = $referer = $redirect = $update = '';

                if (!empty(PluginPage::instance()->table)) {
                    $groups_list_table = PluginPage::instance()->table;
                    $groups_list_table->prepare_items();
                }
                if (!empty(PluginPage::instance()->table_user)) {
                    $users_list_table = PluginPage::instance()->table_user;
                    $users_list_table->prepare_items();
                }

                require_once(PRESSPERMIT_CLASSPATH . '/UI/GroupsHelper.php');
                GroupsHelper::getUrlProperties($url, $referer, $redirect);

                if ($update = PWP::GET_key('update')):
                    switch ($update) {
                        case 'del':
                        case 'del_many':
                            $delete_count = PWP::GET_int('delete_count');

                            echo '<div id="message" class="updated"><p>'
                                . esc_html(sprintf(_n('%s group deleted', '%s groups deleted', (int) $delete_count, 'press-permit-core'), (int) $delete_count))
                                . '</p></div>';

                            break;
                        case 'add':
                            echo '<div id="message" class="updated"><p>' . esc_html__('New group created.', 'press-permit-core') . '</p></div>';
                            break;
                    }
                endif;
                $pp = presspermit();

                if (isset($pp_admin->errors) && is_wp_error($pp_admin->errors)):
                    ?>
                    <div class="error">
                        <ul>
                            <?php
                            foreach ($pp_admin->errors->get_error_messages() as $err)
                                echo "<li>" . esc_html($err) . "</li>\n";
                            ?>
                        </ul>
                    </div>
                    <?php
                endif;

                ?>
                <div class="wrap pressshack-admin-wrapper">
                    <?php PluginPage::icon(); ?>
                    <h1 class="wp-heading-inline">
                        <?php
                        if (('pp_group' == $agent_type) || !$group_type_obj = $pp_groups->getGroupTypeObject($agent_type))
                            $groups_caption = (defined('PP_GROUPS_CAPTION')) ? PP_GROUPS_CAPTION : __('Permissions', 'press-permit-core');
                        else
                            $groups_caption = $group_type_obj->labels->name;

                        echo esc_html($groups_caption);
                        ?>
                    </h1>

                    <?php
                    $tab = PluginPage::viewFilter('permissions_tab');

                    $gvar = ($group_variant) ? $group_variant : 'pp_group';
                    if (('user' != $agent_type) && $pp_groups->groupTypeEditable($gvar) && current_user_can('pp_create_groups')):
                        $_url = admin_url('admin.php?page=presspermit-group-new');
                        if ($agent_type) {
                            $_url = add_query_arg(['agent_type' => $agent_type], $_url);
                        }
                        ?>
                        <a href="<?php echo esc_url($_url); ?>"
                            class="page-title-action"><?php esc_html_e('Add New Group', 'press-permit-core'); ?></a>
                        <hr class="wp-header-end" />
                    <?php endif; ?>
                    <ul class="nav-tab-wrapper" style="margin-bottom: -0.1em; border-bottom: unset">
                        <li
                            class="nav-tab<?php echo (!$tab || ('user-group' == $tab)) ? ' nav-tab-active' : ''; ?>">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=presspermit-groups&permissions_tab=user-group')); ?>">
                                <?php esc_html_e('User Groups', 'press-permit-core'); ?>
                            </a>
                        </li>
                        <li class="nav-tab<?php echo ('users' == $tab) ? ' nav-tab-active' : ''; ?>">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=presspermit-groups&permissions_tab=users')); ?>">
                                <?php esc_html_e('Users', 'press-permit-core'); ?>
                            </a>
                        </li>
                    </ul>

                    <div id="user-group" class="tab-content"
                        style="<?php echo ($active_tab === 'user-group') ? 'display:block;' : 'display:none;'; ?>">
                        <div class="presspermit-groups">
                            <?php

                            if ($pp->getOption('display_hints')) {
                                echo '<div class="pp-hint pp-no-hide">';

                                if (defined('PP_GROUPS_HINT')) {
                                    echo esc_html(PP_GROUPS_HINT);
                                }

                                echo '</div>';
                            }

                            $group_types = [];

                            if (current_user_can('pp_administer_content')) {
                                $group_types['wp_role'] = (object) ['labels' => (object) ['singular_name' => esc_html__('WordPress Role', 'press-permit-core'), 'plural_name' => esc_html__('WordPress Roles', 'press-permit-core')]];
                            }

                            $group_types['pp_group'] = (object) ['labels' => (object) ['singular_name' => esc_html__('Custom Group', 'press-permit-core'), 'plural_name' => esc_html__('Custom Groups', 'press-permit-core')]];

                            if (current_user_can('pp_administer_content')) {
                                $group_types['login_state'] = (object) ['labels' => (object) ['singular_name' => esc_html__('Login State', 'press-permit-core'), 'plural_name' => esc_html__('Login State', 'press-permit-core')]];
                            }

                            // currently faking WP Role as a "group type", but want it listed before BuddyPress Group
                            $group_types = apply_filters('presspermit_list_group_types', array_merge($group_types, $pp_groups->getGroupTypes([], 'object')));

                            echo '<ul class="subsubsub">';
                            printf(esc_html__('%1$sGroup Type:%2$s %3$s', 'press-permit-core'), '<li class="pp-gray">', '</li>', '');

                            $pp_has_perms = PluginPage::viewFilter('pp_has_perms');
                            $pp_has_exceptions = PluginPage::viewFilter('pp_has_exceptions');
                            $pp_has_roles = PluginPage::viewFilter('pp_has_roles');
                            $pp_user_perms = PluginPage::viewFilter('pp_user_perms');  // also retrieve this users filter so clicking Groups > All will also default Users to All

                            $class = (!$group_variant && !$pp_has_perms && !$pp_has_exceptions && !$pp_has_roles) ? 'current' : '';

                            echo "<li><a href='admin.php?page=presspermit-groups&group_variant=&pp_has_perms=0&pp_has_exceptions=0&pp_has_roles=0&pp_user_perms=0' class='" . esc_attr($class) . "'>" . esc_html__('All', 'press-permit-core') . "</a>&nbsp;|&nbsp;</li>";

                            $i = 0;
                            foreach ($group_types as $_group_type => $gtype_obj) {
                                $agent_type_str = (in_array($_group_type, ['wp_role', 'login_state'], true)) ? "&agent_type=pp_group" : "&agent_type=$_group_type";
                                $gvar_str = "&group_variant=$_group_type";
                                $class = strpos($agent_type_str, $agent_type) && ($group_variant && strpos($gvar_str, $group_variant) && empty($pp_has_perms)) ? 'current' : '';

                                $group_label = (!empty($gtype_obj->labels->plural_name)) ? $gtype_obj->labels->plural_name : $gtype_obj->labels->singular_name;

                                $i++;

                                echo "<li><a href='" . esc_url("admin.php?page=presspermit-groups&pp_has_perms=0&pp_has_exceptions=0&pp_has_roles=0{$agent_type_str}{$gvar_str}") . "' class='" . esc_attr($class) . "'>" . esc_html($group_label) . "</a>";
                                echo "&nbsp;|&nbsp;";
                                echo '</li>';
                            }

                            if (!PWP::empty_REQUEST('pp_has_perms')) {
                                $class = !empty($pp_has_perms) ? 'current' : '';
                                echo "<li><a href='" . esc_url("admin.php?page=presspermit-groups&group_variant=&pp_has_perms=1&pp_has_exceptions=0&pp_has_roles=0") . "' class='" . esc_attr($class) . "'>" . esc_html__('Has Permissions', 'press-permit-core') . "</a>&nbsp;|&nbsp;</li>";
                            }

                            $class = !empty($pp_has_exceptions) ? 'current' : '';
                            echo "<li><a href='" . esc_url("admin.php?page=presspermit-groups&group_variant=&pp_has_exceptions=1&pp_has_perms=0&pp_has_roles=0") . "' class='" . esc_attr($class) . "'>" . esc_html__('Has Specific Permissions', 'press-permit-core') . "</a>&nbsp;|&nbsp;</li>";

                            $class = !empty($pp_has_roles) ? 'current' : '';
                            echo "<li><a href='" . esc_url("admin.php?page=presspermit-groups&group_variant=&pp_has_roles=1&pp_has_exceptions=0&pp_has_perms=0") . "' class='" . esc_attr($class) . "'>" . esc_html__('Has Extra Roles', 'press-permit-core') . "</a></li>";

                            echo '</ul>';

                            if (!empty($groupsearch))
                                printf('<span class="subtitle">' . esc_html__('Search Results for &#8220;%s&#8221;', 'press-permit-core') . '</span>', esc_html($groupsearch)); ?>

                            <form action="<?php echo esc_url($url); ?>" method="get">
                                <input type="hidden" name="page" value="presspermit-groups" />
                                <input type="hidden" name="permissions_tab" value="user-group" />
                                <input type="hidden" name="agent_type" value="<?php echo esc_attr($agent_type); ?>" />
                                <input type="hidden" name="group_variant" value="<?php echo esc_attr($group_variant); ?>" />
                                <?php
                                if (isset($groups_list_table)) {
                                    $groups_list_table->search_box(esc_html__('Search Groups', 'press-permit-core'), 'group', '', 2);
                                    $groups_list_table->display();
                                }
                                ?>
                            </form>
                        </div>
                    </div>

                    <?php
                    // Save and retrieve pp_has_perms option per user (for users tab)
                    $pp_user_perms = PluginPage::viewFilter('pp_user_perms');
                    $pp_has_perms = PluginPage::viewFilter('pp_has_perms');
                    $pp_no_group = PluginPage::viewFilter('pp_no_group');
                    $pp_has_exceptions = PluginPage::viewFilter('pp_has_exceptions');
                    $pp_has_roles = PluginPage::viewFilter('pp_has_roles');
                    ?>

                    <div id="users" class="tab-content"
                        style="<?php echo ($active_tab === 'users') ? 'display:block;' : 'display:none;'; ?>">
                        <div class="presspermit-groups">
                            <div class="pp-hint pp-no-hide"></div>
                            <ul class="subsubsub">
                                <li>
                                    <?php $all_users_class = (!$pp_user_perms && !$pp_no_group && !$pp_has_perms && !$pp_has_roles && !$pp_has_exceptions) ? 'current' : ''; ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=presspermit-groups&permissions_tab=users&pp_user_perms=0&pp_no_group=0&pp_has_exceptions=0&pp_has_roles=0&pp_has_perms=0&group_variant')); ?>"
                                        class="<?php echo esc_attr($all_users_class); ?>">
                                        <?php esc_html_e('All Users', 'press-permit-core'); ?>
                                    </a>|
                                </li>
                                <li>
                                    <?php $has_perms_direct_class = ($pp_user_perms) ? 'current' : ''; ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=presspermit-groups&permissions_tab=users&pp_user_perms=1&pp_no_group=0&pp_has_exceptions=0&pp_has_roles=0&pp_has_perms=0')); ?>"
                                        class="<?php echo esc_attr($has_perms_direct_class); ?>">
                                        <?php esc_html_e('Has Permissions Set Directly', 'press-permit-core'); ?>
                                    </a>|
                                </li>
                                <li>
                                    <?php $has_perms_class = ($pp_has_exceptions) ? 'current' : ''; ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=presspermit-groups&permissions_tab=users&pp_has_exceptions=1&pp_user_perms=0&pp_no_group=0&pp_has_roles=0&pp_has_perms=0')); ?>"
                                        class="<?php echo esc_attr($has_perms_class); ?>">
                                        <?php esc_html_e('Has Specific Permissions', 'press-permit-core'); ?>
                                    </a>|
                                </li>
                                <li>
                                    <?php $extra_roles_class = ($pp_has_roles) ? 'current' : ''; ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=presspermit-groups&permissions_tab=users&pp_has_roles=1&pp_user_perms=0&pp_no_group=0&pp_has_exceptions=0&pp_has_perms=0')); ?>"
                                        class="<?php echo esc_attr($extra_roles_class); ?>">
                                        <?php esc_html_e('Has Extra Roles', 'press-permit-core'); ?>
                                    </a>|
                                </li>
                                <li>
                                    <?php $no_group_class = ($pp_no_group) ? 'current' : ''; ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=presspermit-groups&permissions_tab=users&pp_no_group=1&pp_user_perms=0&pp_has_exceptions=0&pp_has_roles=0&pp_has_perms=0')); ?>"
                                        class="<?php echo esc_attr($no_group_class); ?>">
                                        <?php esc_html_e('No Custom Group', 'press-permit-core'); ?>
                                    </a>
                                </li>
                            </ul>
                            <form method="get">
                                <input type="hidden" name="page" value="presspermit-groups" />
                                <input type="hidden" name="permissions_tab" value="users" />
                                <?php
                                if ($pp_user_perms) {
                                    echo '<input type="hidden" name="pp_user_perms" value="1" />';
                                }
                                if ($pp_no_group) {
                                    echo '<input type="hidden" name="pp_no_group" value="1" />';
                                }
                                if ($pp_has_perms) {
                                    echo '<input type="hidden" name="pp_has_perms" value="1" />';
                                }
                                if ($pp_has_exceptions) {
                                    echo '<input type="hidden" name="pp_has_exceptions" value="1" />';
                                }
                                if ($pp_has_roles) {
                                    echo '<input type="hidden" name="pp_has_roles" value="1" />';
                                }
                                if (isset($users_list_table)) {
                                    $users_list_table->search_box(__('Search Users'), 'user');
                                    $users_list_table->display();
                                }
                                ?>
                            </form>
                        </div>
                    </div>
                    <?php presspermit()->admin()->publishpressFooter(); ?>
                </div>
                <?php
                break;
        } // end of the $doaction switch
    }
}

<?php

namespace PublishPress\Permissions\UI;

class AgentPermissions
{
    public function __construct()
    {
        // called by Dashboard\DashboardFilters::actMenuHandler

        // todo: separate function for update messages 

        $pp = presspermit();
        $pp_admin = $pp->admin();
        $pp_groups = $pp->groups();

        if (!PWP::empty_REQUEST('pp_fix_child_exceptions')) {
            require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php');
            \PublishPress\Permissions\DB\PermissionsUpdate::ensureExceptionPropagation();
        }

        require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentPermissionsUI.php');

        if (!$agent_type = PWP::REQUEST_key('agent_type')) {
            $agent_type = 'pp_group';
        }

        if ($agent_id = PWP::REQUEST_int('agent_id')) {
            $agent = $pp_groups->getGroup($agent_id, $agent_type);
        } else {
            $agent_id = 0;
            $agent = (object)['metagroup_type' => ''];

            if ('user' != $agent_type) {
                wp_die(esc_html__('No user/group specified.', 'press-permit-core'));
            }
        }

        $metagroup_type = (!empty($agent->metagroup_type)) ? $agent->metagroup_type : '';

        if ('user' == $agent_type) {
            if (!current_user_can('pp_administer_content') || !current_user_can('list_users'))
                wp_die(esc_html__('You are not permitted to do that.', 'press-permit-core'));

            if ($agent_id && empty($agent->ID))
                wp_die(esc_html__('Invalid user ID.', 'press-permit-core'));
        } else {
            if (!$pp_groups->userCan('pp_edit_groups', $agent_id, $agent_type))
                wp_die(esc_html__('You are not permitted to do that.', 'press-permit-core'));

            if (('wp_role' == $metagroup_type) && !current_user_can('pp_administer_content'))
                wp_die(esc_html__('You are not permitted to do that.', 'press-permit-core'));

            if (!$agent) {
                wp_die(esc_html__('Invalid group ID.', 'press-permit-core'));
            }
        }

        if ($metagroup_type) {  // metagroups cannot have name/description manually edited
            $agent->name = \PublishPress\Permissions\DB\Groups::getMetagroupName($metagroup_type, $agent->metagroup_id, $agent->name);
            $agent->group_description = \PublishPress\Permissions\DB\Groups::getMetagroupDescript($metagroup_type, $agent->metagroup_id, $agent->group_description);
        }

        $url = apply_filters('presspermit_groups_base_url', 'admin.php');

        if ($wp_http_referer = PWP::REQUEST_url('wp_http_referer')) {
            $wp_http_referer = esc_url_raw($wp_http_referer);
        } elseif (PWP::SERVER_url('HTTP_REFERER') && !strpos(esc_url_raw(PWP::SERVER_url('HTTP_REFERER')), 'page=presspermit-group-new')) {
            $wp_http_referer = esc_url_raw(PWP::SERVER_url('HTTP_REFERER'));
        } else {
            $wp_http_referer = '';
        }

        $wp_http_referer = remove_query_arg(['update', 'delete_count'], stripslashes($wp_http_referer));

        if (!$group_variant = PWP::REQUEST_key('group_variant')) {
            $group_variant = 'pp_group';
        }

        $groups_link = ($wp_http_referer && strpos($wp_http_referer, 'presspermit-groups'))
            ? add_query_arg('group_variant', $group_variant, $wp_http_referer)
            : admin_url("admin.php?page=presspermit-groups&group_variant=$group_variant"); ?>

        <?php if (PWP::is_GET('updated')) : ?>
            <div id="message" class="updated">
                <p>

                    <?php if (!PWP::empty_REQUEST('pp_roles')) : ?>
                        <strong><?php esc_html_e('Roles updated.', 'press-permit-core') ?>&nbsp;</strong>

                    <?php elseif (!PWP::empty_REQUEST('pp_exc')) : ?>
                        <strong><?php esc_html_e('Specific Permissions updated.', 'press-permit-core') ?>&nbsp;</strong>

                    <?php elseif (!PWP::empty_REQUEST('pp_cloned')) : ?>
                        <strong><?php esc_html_e('Roles and Permissions copied.', 'press-permit-core') ?>&nbsp;</strong>

                    <?php else : ?>
                        <strong><?php esc_html_e('Group updated.', 'press-permit-core') ?>&nbsp;</strong>

                        <?php
                        if ($wp_http_referer) : ?>
                            <a href="<?php echo esc_url($groups_link); ?>"><?php esc_html_e('Back to permissions list', 'press-permit-core'); ?></a>
                        <?php endif; ?>

                    <?php endif; ?>

                </p>
            </div>
        <?php endif; ?>

        <?php
        if (!empty($pp_admin->errors) && is_wp_error($pp_admin->errors)) : ?>
            <div class="error">
                <?php
                foreach ($pp_admin->errors->get_error_messages() as $msg) {
                    echo '<p>' . esc_html($msg) . '</p>';
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="wrap pressshack-admin-wrapper" id="pp-permissions-wrapper">
            <header>
                <?php PluginPage::icon(); ?>
                <h1 class="wp-heading-inline">
                    <?php
                    if ('user' == $agent_type) {
                        ($agent_id) ? esc_html_e('Edit User Permissions', 'press-permit-core') : esc_html_e('Add User Permissions', 'press-permit-core');
                    } elseif ('wp_role' == $metagroup_type) {
                        esc_html_e('Edit Permissions', 'press-permit-core');
                    } elseif ('pp_group' == $agent_type) {
                        esc_html_e('Edit Permissions', 'press-permit-core');
                    } elseif ($group_type_obj = $pp_groups->getGroupTypeObject($agent_type)) {
                        printf(esc_html__('Edit Permissions (%s)', 'press-permit-core'), esc_html($group_type_obj->labels->singular_name));
                    }
                    ?>
                </h1>
                <?php
                $is_debug_mode = defined('PRESSPERMIT_DEBUG') && PRESSPERMIT_DEBUG;
                if($is_debug_mode){
                    if (defined('PUBLISHPRESS_CAPS_VERSION')) {
                        printf(
                            __('%sEdit WordPress Role%s', 'press-permit-core'),
                            '<a href="' . esc_url(admin_url("admin.php?page=capsman&action=edit&role={$agent->metagroup_id}")) . '" class="page-title-action" title="' . esc_attr__('Edit role capabilities directly', 'press-permit-core') . '">',
                            '</a>'
                        );
                    }
                    $gvar = ($group_variant) ? $group_variant : 'pp_group';

                    if ($pp_groups->groupTypeEditable($gvar) && current_user_can('pp_create_groups')) :
                        $_url = admin_url('admin.php?page=presspermit-group-new');
                        if ($agent_type) {
                            $_url = add_query_arg(['agent_type' => $agent_type], $_url);
                        }
                        ?>
                        <a href="<?php echo esc_url($_url); ?>" class="page-title-action"><?php esc_html_e('Add New', 'press-permit-core'); ?></a>
                    <?php endif;
                } ?>
            </header>

            <div id="pp_cred_wrap">
                <form id="agent-profile" class="pp-admin <?php echo esc_attr($agent_type) . '-profile'; ?>"
                    action="<?php echo esc_url($url); ?>" method="post" <?php do_action('presspermit_group_edit_form_tag'); ?>>
                    <?php wp_nonce_field('pp-update-group_' . $agent_id) ?>

                    <input type="hidden" name="agent_type" value="<?php echo esc_attr($agent_type); ?>" />

                    <?php if ($wp_http_referer) : ?>
                        <input type="hidden" name="wp_http_referer" value="<?php echo esc_url($wp_http_referer); ?>" />
                    <?php endif; ?>

                    <?php
                    $disabled = (!$pp_groups->groupTypeEditable($agent_type) || $agent->metagroup_id) ? ' disabled ' : '';

                    // todo: better html / css for update button pos
                    ?>
                    <table class="pp-agent-profile">
                        <tr>
                            <td>
                                <table class="form-table">
                                    <?php if (('user' == $agent_type) && $agent_id && ($agent->name != $agent->user_login)) : ?>
                                        <tr>
                                            <th><label for="user_login"><?php echo esc_html__('User Login:', 'press-permit-core'); ?></label></th>
                                            <td><?php echo esc_html($agent->user_login); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php if ($agent_id) : ?>
                                        <tr>
                                            <th><label>
                                                    <!-- <label for="group_name"> --><?php echo esc_html__('Name:', 'press-permit-core'); ?></label>
                                            </th>
                                            <td>
                                                <?php if (('user' == $agent_type)) :
                                                    echo "<a href='" . esc_url('user-edit.php?user_id=' . (int) $agent_id) . "'>" . esc_html($agent->name) . "</a>";
                                                else : ?>
                                                    <input type="text" name="group_name" id="group_name"
                                                        value="<?php echo esc_attr($agent->name); ?>"
                                                        class="regular-text" <?php echo esc_attr($disabled); ?> />
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php
                                    if (('user' == $agent_type) && $agent_id) :
                                        global $wp_roles;
                                        $user = new \WP_User($agent_id);
                                        $primary_role = reset($user->roles);
                                        if (isset($wp_roles->role_names[$primary_role])) :
                                    ?>
                                            <tr>
                                                <th><label>
                                                        <!-- <label for="user_login"> --><?php echo esc_html__('Primary Role:', 'press-permit-core'); ?></label>
                                                </th>
                                                <td><?php
                                                    if ($role_group_id = $pp_groups->getMetagroup('wp_role', $primary_role, ['cols' => 'id'])) {
                                                        echo "<a href='" . esc_url('admin.php?page=presspermit-edit-permissions&action=edit&agent_type=pp_group&agent_id="' . (int) $role_group_id) . "'>"
                                                            . esc_html($wp_roles->role_names[$primary_role]) . '</a>';
                                                    } else {
                                                        echo esc_html($wp_roles->role_names[$primary_role]);
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php
                                        endif;
                                        ?>
                                    <?php elseif ($agent_id) : ?>
                                        <tr>
                                            <th>
                                                <label for="description"><?php echo esc_html__('Description:', 'press-permit-core'); ?></label>
                                            </th>
                                            <td>
                                                <textarea name="description" id="description" rows="3" cols="40" class="regular-text <?php echo esc_attr($disabled); ?>"><?php echo esc_html($agent->group_description) ?></textarea>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </td>

                            <td style="text-align:right">
                                <?php
                                if (
                                    $pp_groups->groupTypeEditable($agent_type) && (empty($agent->metagroup_type) || !in_array($agent->metagroup_type, ['wp_role', 'meta_role'], true)
                                        || apply_filters('presspermit_metagroup_editable', false, $agent->metagroup_type, $agent_id))
                                ) {
                                ?>
                                    <input type="submit" name="submit" id="submit" class="button button-primary pp-primary-button" value="<?php esc_attr_e('Update Permissions', 'press-permit-core') ?>">
                                <?php
                                }
                                ?>
                            </td>
                        </tr>
                    </table>

                    <?php

                    do_action('presspermit_group_edit_form', $agent_type, $agent_id);

                    if ($agent_id) {
                        if (
                            $pp_groups->groupTypeEditable($agent_type)
                            && !in_array($agent->metagroup_type, ['wp_role', 'meta_role'], true)
                            && !in_array($agent_type, apply_filters('presspermit_automember_group_types', ['bp_group']), true)
                        ) {
                            $member_types = [];

                            if ($pp_groups->userCan('pp_manage_members', $agent_id, $agent_type))
                                $member_types[] = 'member';

                            if ($member_types)
                                AgentPermissionsUI::drawMemberChecklists($agent_id, $agent_type, compact('member_types'));
                        }
                    } elseif ('user' == $agent_type) {
                        echo '<br />';
                        AgentPermissionsUI::drawMemberChecklists(0, 'pp_group', ['suppress_caption' => true]);
                    }

                    do_action('presspermit_edit_group_profile', $agent_type, $agent_id);
                    ?>

                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="agent_id" id="agent_id" value="<?php echo esc_attr($agent_id); ?>" />
                    <input type="hidden" name="agent_type" id="agent_type" value="<?php echo esc_attr($agent_type); ?>" />

                </form>

                <div style='clear:both'></div>
                <?php
                if (current_user_can('pp_assign_roles') && $pp_admin->bulkRolesEnabled()) {
                    AgentPermissionsUI::drawGroupPermissions($agent_id, $agent_type, $url, $wp_http_referer, compact('agent'));
                }


                if ('user' == $agent_type) : ?>
                    <div>
                        <?php if ($agent_id) {
                            // @todo: Consider how to reinstate a read-only summary of the extra Roles and Permissions the user has from Group membership

                            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
                            /*
                            $roles = [];
                            $user = $pp->getUser($agent_id);
                            $user->retrieveExtraGroups();
                            $user->getSiteRoles();

                            $post_types = $pp->getEnabledPostTypes([], 'object');
                            $taxonomies = $pp->getEnabledTaxonomies([], 'object');

                            foreach (array_keys($user->groups) as $agent_type) {
                                foreach (array_keys($user->groups[$agent_type]) as $_agent_id) {
                                    $args = compact('post_types', 'taxonomies');
                                    $args['query_agent_ids'] = array_keys($user->groups[$agent_type]);
                                    $roles = array_merge($roles, $pp->getRoles($_agent_id, $agent_type, $args));
                                }
                            }

                            require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/Profile.php');
                            Dashboard\Profile::displayUserGroups(
                                $agent_id,
                                [
                                    'initial_hide' => true,
                                    'selected_only' => true,
                                    'force_display' => true,
                                    'edit_membership_link' => true,
                                    'hide_checkboxes' => true,
                                ]
                            );

                            $role_group_caption = sprintf(
                                esc_html__('Extra Roles %1$s(from primary role or %2$sgroup membership%3$s)%4$s', 'press-permit-core'),
                                '',
                                '',
                                '',
                                ''
                            );

                            AgentPermissionsUI::currentRolesUI($roles, ['read_only' => true, 'class' => 'pp-group-roles', 'caption' => $role_group_caption, 'show_groups_link' => true]);

                            $exceptions = [];

                            $args = ['assign_for' => '', 'inherited_from' => 0, 'post_types' => array_keys($post_types), 'taxonomies' => array_keys($taxonomies), 'return_raw_results' => true];

                            foreach (array_keys($user->groups) as $agent_type) {
                                $args['agent_type'] = $agent_type;
                                $args['ug_clause'] = " AND e.agent_type = '$agent_type' AND e.agent_id IN ('"
                                    . implode("','", array_keys($user->groups[$agent_type])) . "')";

                                $args['query_agent_ids'] = array_keys($user->groups[$agent_type]);

                                $exceptions = array_merge($exceptions, $pp->getExceptions($args));
                            }

                            $role_group_caption = sprintf(
                                esc_html__('Specific Permissions %1$s(from primary role or %2$sgroup membership%3$s)%4$s', 'press-permit-core'),
                                '',
                                '',
                                '',
                                ''
                            );

                            AgentPermissionsUI::currentExceptionsUI($exceptions, ['read_only' => true, 'class' => 'pp-group-roles', 'caption' => $role_group_caption, 'show_groups_link' => true]);
                            */
                        } else {
                        ?>
                            <h4>
                                <?php
                                $url = "users.php";
                                printf(esc_html__('View currently stored user permissions:', 'press-permit-core'));
                                ?>
                            </h4>
                            <ul class="pp-notes">
                                <li><?php printf(esc_html__('%1$sUsers who have Extra Roles assigned directly%2$s', 'press-permit-core'), "<a href='" . esc_url("$url?pp_user_roles=1") . "'>", '</a>'); ?></li>
                                <li><?php printf(esc_html__('%1$sUsers who have Specific Permissions assigned directly%2$s', 'press-permit-core'), "<a href='" . esc_url("$url?pp_user_exceptions=1") . "'>", '</a>'); ?></li>
                                <li><?php printf(esc_html__('%1$sUsers who have Extra Roles or Specific Permissions directly%2$s', 'press-permit-core'), "<a href='" . esc_url("$url?pp_user_perms=1") . "'>", '</a>'); ?></li>
                            </ul>
                        <?php
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <?php if ('user' == $agent_type) : ?>
                    <!-- </div> -->
                <?php endif; ?>

            </div>

            <?php
            presspermit()->admin()->publishpressFooter();
            ?>
        </div>
<?php
    }
}

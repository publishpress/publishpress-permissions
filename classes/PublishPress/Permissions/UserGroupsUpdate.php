<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class UserGroupsUpdate
{
    public static function addUserGroups($user_id)
    {
        global $wpdb;

        $pp = presspermit();

        $group_types = $pp->groups()->getGroupTypes(['editable' => true]);

		$is_main_site = (defined('PRESSPERMIT_LEGACY_MAIN_SITE_CHECK')) ? (1 == get_current_blog_id()) : is_main_site();
    	
        foreach ($group_types as $agent_type) {
            if (('pp_group' == $agent_type) && in_array('pp_net_group', $group_types, true) && $is_main_site) {
                continue;
            }

            if (PWP::empty_POST($agent_type)) {
                continue;
            }

            check_admin_referer('pp-user-profile-groups', '_pp_permissions_nonce');

            // by retrieving filtered groups here, user will only modify membership for groups they can administer
            $posted_groups = (!empty($_POST[$agent_type])) ? array_map('intval', $_POST[$agent_type]) : [];

            if ($posted_groups) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $metagroup_ids = $wpdb->get_col("SELECT ID FROM $wpdb->pp_groups WHERE metagroup_type = 'wp_role'");

                $posted_groups = array_diff($posted_groups, $metagroup_ids);
            }

            if (!current_user_can('pp_manage_members')) {
                $posted_groups = array_intersect($posted_groups, apply_filters('presspermit_admin_groups', []));
            }

            if ($posted_groups) {
                $status = (PWP::is_POST('pp_membership_status')) ? PWP::POST_key('pp_membership_status') : 'active';

                if ($user_id == $pp->getUser()->ID)
                    $stored_groups = (array)$pp->getUser()->groups[$agent_type];
                else {
                    $user = $pp->getUser($user_id, '', ['skip_role_merge' => 1]);
                    $stored_groups = (isset($user->groups[$agent_type])) ? (array)$user->groups[$agent_type] : [];
                }

                foreach ($posted_groups as $group_id) {
                    if (isset($stored_groups[$group_id]))
                        continue;

                    if ($pp->groups()->userCan('pp_manage_members', $group_id, $agent_type)) {
                        $args = compact('agent_type', 'status');
                        $args = apply_filters('presspermit_add_group_args', $args, $group_id);

                        $pp->groups()->addGroupUser((int)$group_id, $user_id, $args);
                    }
                }
            }
        }
    }

    public static function removeUserGroups($user_id)
    {
        $pp = presspermit();

        $group_types = $pp->groups()->getGroupTypes(['editable' => true]);

		$is_main_site = (defined('PRESSPERMIT_LEGACY_MAIN_SITE_CHECK')) ? (1 == get_current_blog_id()) : is_main_site();
    	
        foreach ($group_types as $agent_type) {
            if (!PWP::is_POST($agent_type)) {
                continue;
            }

            if (('pp_group' == $agent_type) && in_array('pp_net_group', $group_types, true) && $is_main_site) {
                continue;
			}

            check_admin_referer('pp-user-profile-groups', '_pp_permissions_nonce');

            $posted_groups = (!empty($_POST[$agent_type])) ? array_map('intval', $_POST[$agent_type]) : [];

            $stored_groups = array_keys(
                $pp->groups()->getGroupsForUser($user_id, $agent_type, ['cols' => 'id', 'metagroup_type' => ''])
            );

            if (!current_user_can('pp_manage_members'))
                $posted_groups = array_intersect($posted_groups, apply_filters('presspermit_admin_groups', []));

            $delete_groups = array_diff($stored_groups, $posted_groups);

            foreach ($delete_groups as $group_id) {
                if ($pp->groups()->userCan('pp_manage_members', $group_id, $agent_type)) {
                    $pp->groups()->removeGroupUser($group_id, $user_id, compact('agent_type'));
                }
            }
        }
    }
}

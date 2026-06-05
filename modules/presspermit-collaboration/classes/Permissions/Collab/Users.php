<?php
namespace PublishPress\Permissions\Collab;

class Users
{
    // optional filter for WP role edit based on user level
    public static function editableRoles($roles, $editing_limitation = '')
    {
        $role_levels = self::getRoleLevels();

        $current_user_level = self::currentUserLevel();

        // Bypass for true content administrators only. isUserAdministrator() merely checks edit_users,
        // which lower-level user managers hold — using it here would wrongly let an Author keep the
        // same-level "Author" option in 'lower_levels' mode.
        $is_admin = presspermit()->isContentAdministrator();

        foreach (array_keys($roles) as $role_name) {
            if (!isset($role_levels[$role_name])) continue;

            $level = $role_levels[$role_name];

            // Always remove roles strictly above the current user's level.
            if ($level > $current_user_level) {
                unset($roles[$role_name]);

            // In 'lower_levels' mode, also remove same-level roles for non-admins to prevent
            // privilege escalation. Applied to ALL role-assignment dropdowns (add user, edit user,
            // and the bulk "Change role to" control) — not a hardcoded page list. The '1'
            // (equal-or-lower) mode intentionally allows same-level assignment, and
            // PPCE_CAN_ASSIGN_OWN_ROLE overrides.
            } elseif (
                !$is_admin
                && !defined('PPCE_CAN_ASSIGN_OWN_ROLE')
                && '1' !== $editing_limitation
                && $level >= $current_user_level
            ) {
                unset($roles[$role_name]);
            }
        }
        return $roles;
    }

    // Returns role names that the current user cannot edit based on the level restriction setting.
    // Used to filter user lists so uneditable users are hidden.
    public static function getUneditableRoles($editing_limitation)
    {
        $role_levels        = self::getRoleLevels();
        $current_user_level = self::currentUserLevel();

        // Fail-safe: if the current user's level cannot be determined (0), do NOT restrict anything.
        // A level-0 user has no basis to limit others, and restricting here (especially in
        // 'lower_levels' mode, where level 0 would mark EVERY role uneditable) would wrongly empty
        // the entire users list ("No users found"). Better to show all than to hide everyone.
        if ($current_user_level <= 0) {
            return [];
        }

        $uneditable_roles = [];
        foreach ($role_levels as $role_name => $level) {
            $uneditable = ('lower_levels' === $editing_limitation)
                ? ($level >= $current_user_level)
                : ($level > $current_user_level);

            if ($uneditable) {
                $uneditable_roles[] = $role_name;
            }
        }

        return $uneditable_roles;
    }

    // Robustly determine the current user's role level, independent of the static getUserLevel cache.
    // Considers (and takes the highest of): super-admin/administrator, legacy user_level meta, the
    // levels of their assigned WP roles, and their EFFECTIVE capabilities (allcaps) — the latter
    // covers content caps granted via PublishPress permission groups rather than the WP role itself,
    // which getRoleLevels() (role-cap based) cannot see.
    public static function currentUserLevel()
    {
        global $current_user, $wpdb;

        if (current_user_can('administrator')
            || (is_multisite() && function_exists('is_super_admin') && is_super_admin())
        ) {
            return 10;
        }

        $role_levels = self::getRoleLevels();

        $level = (int) get_user_meta($current_user->ID, $wpdb->get_blog_prefix() . 'user_level', true);

        if (!empty($current_user->role_level)) {
            $level = max($level, (int) $current_user->role_level);
        }

        if (!empty($current_user->roles)) {
            foreach ($current_user->roles as $role_name) {
                if (isset($role_levels[$role_name])) {
                    $level = max($level, (int) $role_levels[$role_name]);
                }
            }
        }

        // Infer from effective capabilities. Note: edit_users is intentionally NOT treated as
        // administrator-level, because lower-level user managers legitimately hold it.
        $caps = (array) $current_user->allcaps;
        if (!empty($caps['manage_options'])) {
            $level = max($level, 10);
        } elseif (!empty($caps['edit_others_posts']) || !empty($caps['edit_pages'])) {
            $level = max($level, 7);
        } elseif (!empty($caps['publish_posts'])) {
            $level = max($level, 2);
        } elseif (!empty($caps['edit_posts'])) {
            $level = max($level, 1);
        }

        return $level;
    }

    public static function hasEditUserCap($wp_sitecaps, $orig_reqd_caps, $args, $editing_limitation)
    {
        // '0' / falsy = "any user" — no restriction.  Defensive guard in case caller passes it through.
        if (!$editing_limitation || '0' === (string) $editing_limitation) {
            return $wp_sitecaps;
        }

        // prevent anyone from editing a user whose level is higher than their own
        $levels = self::getUserLevel([(int) $args[1], (int) $args[2]]);

        // 'lower_levels': can only edit users with strictly lower role level.
        // '1' (equal or lower): can edit same and lower; block only strictly higher.
        $block = ('lower_levels' === $editing_limitation)
            ? ($levels[$args[2]] >= $levels[$args[1]])
            : ($levels[$args[2]] > $levels[$args[1]]);

        if ($block || apply_filters('presspermit_block_user_edit', false, (int) $args[2])) {
            $wp_sitecaps = array_diff_key(
                $wp_sitecaps,
                array_fill_keys(['edit_users', 'delete_users', 'remove_users', 'promote_users'], true)
            );
        }

        return $wp_sitecaps;
    }

    private static function getUserLevel($user_ids)
    {
        static $user_levels;

        $return_array = is_array($user_ids);  // if an array was passed in, return results as an array

        if (!is_array($user_ids)) {
            // mu site administrator may not be a user for the current site
            if (is_multisite() && function_exists('is_super_admin') && is_super_admin() || current_user_can('administrator'))
                return 10;

            $orig_user_id = $user_ids;
            $user_ids = (array)$user_ids;
        }

        if (!isset($user_levels))
            $user_levels = [];

        if (array_diff($user_ids, array_keys($user_levels))) {
            // one or more of the users were not already logged

            $role_levels = self::getRoleLevels(); // local buffer for performance

            // If the listed user ids were logged following a search operation, save extra DB queries by getting the levels of all those users now
            global $wp_user_search, $current_user, $wpdb;

            if ((count($user_ids) == 1) && (current($user_ids) == $current_user->ID)) {
                $level = !empty($current_user->role_level) ? (int) $current_user->role_level : 0;

                $meta_level = (int) get_user_meta($current_user->ID, $wpdb->get_blog_prefix() . 'user_level', true);
                if ($meta_level > $level) {
                    $level = $meta_level;
                }

                // Always also consider native WP roles and keep the highest level. A stale or low
                // user_level meta (common on modern WordPress) must not under-count the current user's
                // level — otherwise lower roles like Subscriber get hidden from their own list even
                // though they remain editable (the edit-cap check derives level from the WP role).
                if (!empty($current_user->roles)) {
                    foreach ($current_user->roles as $role_name) {
                        if (isset($role_levels[$role_name]) && ($role_levels[$role_name] > $level)) {
                            $level = $role_levels[$role_name];
                        }
                    }
                }

                $user_levels[$current_user->ID] = $level;
            } else {
                if (!empty($wp_user_search->results)) {
                    $query_users = $wp_user_search->results;
                    $query_users = array_unique(array_merge($query_users, $user_ids));
                } else {
                    $query_users = $user_ids;
				}

                $user_id_csv = implode("','", array_map('intval', $query_users));

                // get the WP roles for user

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $results = $wpdb->get_results(
                    "SELECT m.user_id, g.metagroup_id AS role_name FROM $wpdb->pp_groups AS g"
                    . " INNER JOIN $wpdb->pp_group_members AS m ON m.group_id = g.ID"
                    . " WHERE g.metagroup_type = 'wp_role' AND m.user_id IN ('$user_id_csv')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                );

	            // credit each user for the highest role level they have
	            foreach ($results as $row) {
	                if (!isset($role_levels[$row->role_name]))
	                    continue;
	
	                if (!isset($user_levels[$row->user_id]) || ($role_levels[$row->role_name] > $user_levels[$row->user_id]))
	                    $user_levels[$row->user_id] = $role_levels[$row->role_name];
	            }

	            // note any "No Role" users
	            if (!empty($query_users)) {
	                if ($no_role_users = array_diff($query_users, array_keys($user_levels)))
	                    $user_levels = $user_levels + array_fill_keys($no_role_users, 0);
	            }

                // For requested user IDs that are still at level 0 (not synced to pp_groups or
                // assigned a custom role without level_X caps), fall back to native WP role data.
                foreach ($user_ids as $uid) {
                    $uid = (int) $uid;
                    if (!empty($user_levels[$uid])) continue;

                    $user_obj = get_userdata($uid);
                    if ($user_obj && !empty($user_obj->roles)) {
                        foreach ($user_obj->roles as $role_name) {
                            if (!isset($role_levels[$role_name])) continue;
                            if (!isset($user_levels[$uid]) || $role_levels[$role_name] > $user_levels[$uid]) {
                                $user_levels[$uid] = $role_levels[$role_name];
                            }
                        }
                    }
                }
        	}
        }

        if ($return_array)
            $return = array_intersect_key($user_levels, array_fill_keys($user_ids, true));
        else
            $return = (isset($user_levels[$orig_user_id])) ? $user_levels[$orig_user_id] : 0;

        return $return;
    }

    // NOTE: user/role levels are used only for optional limiting of user edit - not for content filtering
    private static function getRoleLevels()
    {
        static $role_levels;

        if (isset($role_levels))
            return $role_levels;

        $role_levels = [];

        global $wp_roles;
        foreach ($wp_roles->role_objects as $role_name => $role) {
            $level = 0;
            for ($i = 0; $i <= 10; $i++)
                if (!empty($role->capabilities["level_$i"]))
                    $level = $i;

            // For roles that lack legacy level_X caps (custom roles), infer level from capabilities.
            // Only manage_options marks administrator level; edit_users/delete_users are intentionally
            // excluded so a lower-level "user manager" role is ranked by its content caps, not jumped
            // to level 10 (which would otherwise make its holders able to edit nearly everyone).
            if (0 === $level) {
                $caps = $role->capabilities;
                if (!empty($caps['manage_options'])) {
                    $level = 10;
                } elseif (!empty($caps['edit_others_posts']) || !empty($caps['edit_pages'])) {
                    $level = 7;
                } elseif (!empty($caps['publish_posts'])) {
                    $level = 2;
                } elseif (!empty($caps['edit_posts'])) {
                    $level = 1;
                }
            }

            $role_levels[$role_name] = $level;
        }

        return $role_levels;
    }

}

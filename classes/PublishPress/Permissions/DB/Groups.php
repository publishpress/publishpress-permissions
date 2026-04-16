<?php

namespace PublishPress\Permissions\DB;

/**
 * Groups class
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2024, PublishPress
 *
 */

class Groups
{
    // returns all groups
    public static function getGroups($args = [])
    {
        $defaults = [
            'agent_type' => 'pp_group',
            'ids' => [],
            'include_metagroups' => true,
            'skip_meta_types' => [],
            'or_meta_types' => '',
            'or_meta_types_except' => [],
            'search' => '',
        ];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        $where = '';

        if (!$include_metagroups) {
            $where = " AND metagroup_type = ''";
        } elseif ($skip_meta_types) {
            $metatype_csv = implode("','", array_map('\PressShack\LibWP::sanitizeEntry', (array)$skip_meta_types));
            $where = " AND metagroup_type NOT IN ('$metatype_csv')";
        }

        if ($ids) {
            $id_csv = implode("','", array_map('intval', (array)$ids));
            $where .= " AND ID IN ('$id_csv')";
        }

        if ($or_meta_types && $where) {
            $metatype_csv = implode("','", array_map('sanitize_key', (array)$or_meta_types));

            if ($or_meta_types_except) {
                $metagroup_csv = implode("','", array_map('sanitize_key', (array)$or_meta_types_except));
                $where = " AND (($where) OR (metagroup_type IN ('$metatype_csv') AND metagroup_id NOT IN ('$metagroup_csv'))";
            } else {
                $where = " AND (($where) OR (metagroup_type IN ('$metatype_csv'))";
            }
        }

        if ($search) {
            $searches = [];
            foreach (['group_name', 'group_description'] as $col) {
                $searches[] = $col . $wpdb->prepare(" LIKE %s", "%$search%");
            }

            $where .= 'AND ( ' . implode(' OR ', $searches) . ' )';
        }

        $wpdb->groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);

        // @todo: Lazy load Permissions metaboxes in Post Editor so direct queries in this function only occur as needed

        // phpcs Note: where clause constructed and sanitized above

        // Direct query of plugin tables for Permission Group management
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            "SELECT ID, group_name AS name, group_description, metagroup_type, metagroup_id"
                . " FROM $wpdb->groups_table WHERE 1=1 $where ORDER BY group_name",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            OBJECT_K
        );

        foreach (array_keys($results) as $key) {
            $results[$key]->name = stripslashes($results[$key]->name);

            if (isset($results[$key]->group_description))
                $results[$key]->group_description = stripslashes($results[$key]->group_description);

            // strip out Revisionary metagroups if we're not using them (todo: API)
            if ($results[$key]->metagroup_type) {
                if (!defined('PUBLISHPRESS_REVISIONS_VERSION') && !defined('REVISIONARY_VERSION') && ('rvy_notice' == $results[$key]->metagroup_type)) {
                    unset($results[$key]);
                }
            }
        }

        return $results;
    }

    public static function getGroupMembers($group_id, $cols = 'all', $args = [])
    {
        $defaults = ['agent_type' => 'pp_group', 'member_type' => 'member', 'status' => 'active'];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        // If $group_id is an array of group objects, extract IDs into a separate array (todo: review calling code)
        if (is_array($group_id)) {
            $first = current($group_id);

            if (is_object($first)) {
                $actual_ids = [];

                foreach ($group_id as $group)
                    $actual_ids[] = $group->ID;

                $group_id = $actual_ids;
            }
        }

        if ('any' == $status)
            $status = '';

        $wpdb->members_table = apply_filters('presspermit_use_group_members_table', $wpdb->pp_group_members, $agent_type);

        $groups_csv = implode("', '", array_map('intval', (array)$group_id));

        $status_clause = ($status) ? $wpdb->prepare("AND status = %s", $status) : '';

        // @todo: Lazy load Permissions metaboxes in Post Editor so direct queries in this function only occur as needed

        // Direct query of plugin tables for Permission Group management
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ('id' == $cols) {
            // phpcs Note: groups IN clause, status clause constructed and sanitized above.

            if (!$results = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT u2g.user_id FROM $wpdb->members_table AS u2g"
                        . " WHERE u2g.group_id IN ('$groups_csv') AND u2g.group_id > 0 AND member_type = %s $status_clause",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                    $member_type
                )
            )) {
                $results = [];
            }
        } elseif ('count' == $cols) {
            // phpcs Note: groups IN clause, status clause constructed and sanitized above.

            $results = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(u2g.user_id) FROM $wpdb->members_table AS u2g"
                        . " WHERE u2g.group_id IN ('$groups_csv') AND u2g.group_id > 0 AND member_type = %s $status_clause",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                    $member_type
                )
            );
        } else {
            switch ($cols) {
                case 'id_displayname':
                    // phpcs Note: groups IN clause, status clause constructed and sanitized above.
                    // phpcs Note: Querying plugin tables. Users join is to supply captions.

                    $results = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT u.ID, u.display_name AS display_name FROM $wpdb->users AS u"  // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users

                                . " INNER JOIN $wpdb->members_table AS u2g ON u2g.user_id = u.ID AND member_type = %s $status_clause"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                                . " AND u2g.group_id IN ('$groups_csv') ORDER BY u.display_name",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                            $member_type
                        ),
                        OBJECT_K
                    );

                    break;
                case 'id_name':
                    // calling code assumes display_name property for user or group object

                    // phpcs Note: groups IN clause, status clause constructed and sanitized above.
                    // phpcs Note: Querying plugin tables. Users join is to supply captions.

                    $results = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT u.ID, u.user_login AS name FROM $wpdb->users AS u"  // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users

                                . " INNER JOIN $wpdb->members_table AS u2g ON u2g.user_id = u.ID AND member_type = %s $status_clause"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                                . " AND u2g.group_id IN ('$groups_csv') ORDER BY u.user_login",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                            $member_type
                        ),
                        OBJECT_K
                    );

                    break;
                default:
                    $ordering_type = apply_filters('presspermit_group_members_ordering', '');

                    switch ($ordering_type) {
                        case 'status-user_login':
                            $orderby_clause = "ORDER BY FIELD(u2g.status, 'active', 'scheduled', 'expired'), u.user_login";
                            break;

                        default:
                            $orderby_clause = "ORDER BY u.user_login";
                    }

                    // phpcs Note: groups IN clause, status clause, order by clause constructed and sanitized above.
                    // phpcs Note: Querying plugin tables. Users join is to supply captions.

                    $results = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT u.*, u2g.* FROM $wpdb->users AS u"  // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users

                                . " INNER JOIN $wpdb->members_table AS u2g ON u2g.user_id = u.ID AND member_type = %s $status_clause"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                                . " AND u2g.group_id IN ('$groups_csv') $orderby_clause",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                            $member_type
                        ),
                        OBJECT_K
                    );
            }
        }

        return $results;
    }

    private static function syncUserRoleGroups($user, $user_groups)
    {
        $pp_groups = presspermit()->groups();

        $user_role_groups = [];

        foreach ($user_groups as $ug) {
            if (!empty($ug->metagroup_id) && ('wp_role' == $ug->metagroup_type) && !in_array($ug->metagroup_id, ['wp_anon', 'wp_all', 'wp_auth'])) {
                $user_role_groups[$ug->metagroup_id] = $ug->group_id;
            }
        }

        $remove_role_groups = array_diff_key($user_role_groups, array_fill_keys($user->roles, true));
        foreach ($remove_role_groups as $role_name => $group_id) {
            unset($user_groups[$group_id]);
            $pp_groups->removeGroupUser($group_id, $user->ID);
        }

        $add_role_groups = array_diff_key(array_fill_keys($user->roles, true), $user_role_groups);

        foreach (array_keys($add_role_groups) as $role_name) {
            if ($role_group = $pp_groups->getMetagroup('wp_role', $role_name)) {
                $user_groups[$role_group->ID] = $role_group;
                $pp_groups->addGroupUser($role_group->ID, $user->ID);
            }
        }

        return $user_groups;
    }

    // Passing in a WP_User object as $user will cause that user's role meta groups to be syncronized to its WP roles
    public static function getGroupsForUser($user, $args = [])
    {
        $defaults = [
            'agent_type' => 'pp_group',  // This function is currently only called for 'pp_group' agent_type
            'member_type' => 'member',
            'status' => 'active',
            'cols' => 'all',
            'metagroup_type' => null,
            'force_refresh' => false,
            'query_user_ids' => false,
        ];

        $args = array_merge($defaults, $args);

        if (isset($args['metagroup_type']) && is_null($args['metagroup_type'])) {
            unset($args['metagroup_type']);
        }

        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (is_object($user)) {
            $user_id = $user->ID;
        } else {
            $user_id = (int) $user;
        }

        static $user_groups_md;

        if (!isset($user_groups_md)) {
            $user_groups_md = [];
        }

        if (!isset($user_groups_md[$agent_type])) {
            $user_groups_md[$agent_type] = [];
        }

        // Build a cache key to disginguish results by user id and args, but don't consider cols, query_user_ids or force_refresh
        unset($args['query_user_ids']);
        unset($args['cols']);
        unset($args['force_refresh']);
        $args_key = ':' . md5(wp_json_encode($args));
        $ckey = $user_id . $args_key;

        if (!isset($user_groups_md[$agent_type][$user_id][$ckey]) && !$force_refresh) {
            // This result was not previously cached, so proceed with query
            global $wpdb;

            $wpdb->members_table = apply_filters('presspermit_use_group_members_table', $wpdb->pp_group_members, $agent_type);

            if (empty($wpdb->members_table)) {
                return [];
            }

            if (!$query_user_ids) {
                $query_user_ids = (array) $user_id;
            }

            $user_id_csv = implode("','", array_map('intval', (array) $query_user_ids));

            $status_clause = ($status && ('any' != $status)) ? $wpdb->prepare("AND status = %s", $status) : '';

            $metagroup_clause = (isset($metagroup_type)) ? $wpdb->prepare("AND g.metagroup_type = %s", $metagroup_type) : '';

            $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);

            $join = "INNER JOIN $groups_table AS g ON $wpdb->members_table.group_id = g.ID";

            if (!empty($args['circle_type'])) {
                $join .= $wpdb->prepare(
                    " INNER JOIN $wpdb->pp_circles AS c ON c.group_id = g.ID"
                        . " AND c.group_type = 'pp_group' AND c.circle_type = %s",
                    $args['circle_type']
                );
            }

            // phpcs Note: join clause, user_id IN clause, status clause, metagroup clause constructed and sanitized above

            // Direct query of plugin table
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $wpdb->members_table $join"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                        . " WHERE member_type = %s $status_clause $metagroup_clause AND user_id IN ('$user_id_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                    $member_type
                )
            );

            $new_items = [];
            $ckey_user = [];

            foreach ($results as $row) {
                $ckey = $row->user_id . $args_key;
                $ckey_user[$ckey] = $user_id;

                if (!isset($new_items[$ckey])) {
                    $new_items[$ckey] = [];
                }

                $new_items[$ckey][$row->group_id] = $row;
            }

            if ('pp_group' == $agent_type) {
                $pp = presspermit();
                $pp_groups = $pp->groups();

                // Add wp_all, wp_anon metagroups to the user's groups implicitly
                static $all_group;
                static $auth_group;

                if (!isset($all_group)) {
                    $all_group = $pp_groups->getMetagroup('wp_role', 'wp_all');
                    if ($all_group) {
                        $all_group->add_date_gmt = constant('PRESSPERMIT_MIN_DATE_STRING');
                    }
                }

                if (!isset($auth_group)) {
                    $auth_group = $pp_groups->getMetagroup('wp_role', 'wp_auth');
                    if ($auth_group) {
                        $auth_group->add_date_gmt = constant('PRESSPERMIT_MIN_DATE_STRING');
                    }
                }

                foreach (array_keys($new_items) as $ckey) {
                    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
                    //if (!$pp->isContentAdministrator($ckey_user[$ckey]) || !$pp->getOption('suppress_administrator_metagroups')) {

                    if ($all_group) {
                        $new_items[$ckey][$all_group->ID] = $all_group;
                    }

                    if ($auth_group && apply_filters('presspermit_is_authenticated_user', true, $ckey_user[$ckey])) {
                        $new_items[$ckey][$auth_group->ID] = $auth_group;
                    }
                }
            }

            // Update the static cache with new query results (possibly for multiple users)
            $user_groups_md[$agent_type] = array_merge($user_groups_md[$agent_type], $new_items);
        } // End groups query sequence

        if (('pp_group' == $agent_type) && !defined('PRESSPERMIT_DISABLE_ROLE_GROUP_SYNC') && (!defined('REST_REQUEST') || !REST_REQUEST)) {
            // If user object was passed in, synchronize this user's role metagroups to their WP roles
            if (is_object($user)) {
                $ckey = $user->ID . $args_key;
                $user_groups = (isset($user_groups_md[$agent_type][$ckey])) ? $user_groups_md[$agent_type][$ckey] : [];

                // Function updates user's stored role metagroups, but also apply any additions or removals to this array
                $user_groups_md[$agent_type][$ckey] = self::syncUserRoleGroups($user, $user_groups);
            }
        }

        $ckey = $user_id . $args_key;
        $return_groups = (isset($user_groups_md[$agent_type][$ckey])) ? $user_groups_md[$agent_type][$ckey] : [];

        if ('all' == $cols) { // Note: $cols was previously not set unless explicitly passed in function args
            // Filter results
            $return_groups = apply_filters(
                'presspermit_get_pp_groups_for_user',
                $return_groups,
                $results,
                $user_id,
                $args
            );
        } else {
            // Function call is only concerned about group ids (meaning the group IDs returned as array keys)
            // For back compat, the group's add_date is returned for use by the Membership module, but otherwise the array value is ignored
            foreach ($return_groups as $group_id => $row) {
                $return_groups[$group_id] = (is_object($row)) && isset($row->add_date_gmt) ? $row->add_date_gmt : true;
            }
        }

        return $return_groups;
    }

    public static function getMetagroupName($metagroup_type, $meta_id, $default_name = '')
    {
        global $wp_roles;

        if ('wp_auth' == $meta_id) {
            return esc_html__('Logged In', 'press-permit-core');
        } elseif ('wp_anon' == $meta_id) {
            return esc_html__('Not Logged In', 'press-permit-core');
        } elseif ('wp_all' == $meta_id) {
            return esc_html__('Everyone', 'press-permit-core');
        } elseif ('wp_role' == $metagroup_type) {
            switch ($meta_id) {
                case 'rvy_pending_rev_notice':
                    return (defined('PUBLISHPRESS_REVISIONS_VERSION')) ? esc_html__('Change Request Notifications', 'press-permit-core') : esc_html__('Submitted Revision Editors', 'press-permit-core');
                    break;

                case 'rvy_scheduled_rev_notice':
                    return (defined('PUBLISHPRESS_REVISIONS_VERSION')) ? esc_html__('Scheduled Change Notifications', 'press-permit-core') : esc_html__('Scheduled Revision Monitors', 'press-permit-core');
                    break;

                default:
                    $role_display_name = isset($wp_roles->role_names[$meta_id]) ? esc_html__($wp_roles->role_names[$meta_id]) : $meta_id;
            }

            return $role_display_name;
        } else {
            switch ($meta_id) {
                case 'rvy_pending_rev_notice':
                    return (defined('PUBLISHPRESS_REVISIONS_VERSION')) ? esc_html__('Change Request Notifications', 'press-permit-core') : esc_html__('Submitted Revision Editors', 'press-permit-core');
                    break;

                case 'rvy_scheduled_rev_notice':
                    return (defined('PUBLISHPRESS_REVISIONS_VERSION')) ? esc_html__('Scheduled Change Notifications', 'press-permit-core') : esc_html__('Scheduled Revision Monitors', 'press-permit-core');
                    break;

                default:
            }

            return $default_name;
        }
    }

    public static function getMetagroupDescript($metagroup_type, $meta_id, $default_descript = '')
    {
        if ('wp_auth' == $meta_id) {
            return esc_html__('Authenticated site users, except for Administrators', 'press-permit-core');
        } elseif ('wp_anon' == $meta_id) {
            return esc_html__('Anonymous users (not logged in)', 'press-permit-core');
        } elseif ('wp_all' == $meta_id) {
            return esc_html__('All users (including anonymous)', 'press-permit-core');
        } elseif ('wp_role' == $metagroup_type) {
            $role_display_name = self::getMetagroupName($metagroup_type, $meta_id);
            $role_display_name = str_replace('[WP ', '', $role_display_name);
            $role_display_name = str_replace(']', '', $role_display_name);
            return sprintf(esc_html__('All users with the WordPress role of %s', 'press-permit-core'), esc_html($role_display_name));
        } else {
            return $default_descript;
        }
    }

    public static function isDeletedRole($role_name)
    {
        global $wp_roles;

        return !in_array($role_name, array_keys($wp_roles->role_names), true) && !in_array($role_name, ['wp_anon', 'wp_all', 'wp_auth'], true);
    }
}

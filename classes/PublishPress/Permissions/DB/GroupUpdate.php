<?php

namespace PublishPress\Permissions\DB;

\defined('ABSPATH') || exit;

class GroupUpdate
{
    /**
     * Adds a user to a group
     * @param int $groupID - Group Identifier
     * @param int $userID - Identifier of the User to add
     **/
    public static function addGroupUser($group_id, $user_ids, $args = [])
    {
        $defaults = [
            'agent_type' => 'pp_group',
            'member_type' => 'member',
            'status' => 'active',
            'date_limited' => false,
            'start_date_gmt' => constant('PRESSPERMIT_MIN_DATE_STRING'),
            'end_date_gmt' => constant('PRESSPERMIT_MAX_DATE_STRING'),
        ];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        $wpdb->members_table = apply_filters('presspermit_use_group_members_table', $wpdb->pp_group_members, $agent_type);

        if (!presspermit()->groups()->getGroup($group_id, $agent_type)) {
            return;
        }

        $data = Arr::subset($args, ['member_type', 'status', 'start_date_gmt', 'end_date_gmt']);
        $data['date_limited'] = intval($date_limited);
        $data['group_id'] = $group_id;

        $user_ids = array_map('intval', (array) $user_ids);

        foreach ($user_ids as $user_id) {
            if (!$user_id) {
                continue;
            }

            $data['user_id'] = $user_id;

            // Direct query of plugin table on plugin admin operation
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($already_member = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM $wpdb->members_table WHERE group_id = %d AND user_id = %d",
                $group_id,
                $user_id
            ))) {
                self::updateGroupUser($group_id, $user_ids, $args);
                continue;
            } else {
                $data['add_date_gmt'] = current_time('mysql', 1);

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->insert($wpdb->members_table, $data);
            }

            do_action('presspermit_add_group_user', $group_id, $user_id, $args);
            if (PRESSPERMIT_LEGACY_HOOKS) {
                do_action('presspermit_add_group_user', $group_id, $user_id, $args);
            }
        }
    }

    public static function removeGroupUser($group_id, $user_ids, $args = [])
    {
        $defaults = ['agent_type' => 'pp_group', 'member_type' => 'member'];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        if (!presspermit()->groups()->getGroup($group_id)) {
            return;
        }

        $wpdb->members_table = apply_filters('presspermit_use_group_members_table', $wpdb->pp_group_members, $agent_type);

        $user_ids = array_map('intval', (array) $user_ids);

        $id_csv = implode("', '", array_map('intval', $user_ids));

        // Direct query of plugin table on plugin admin operation (IN clause constructed and sanitized above)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->members_table WHERE member_type = %s AND group_id = %d AND user_id IN ('$id_csv')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $member_type,
                $group_id
            )
        );

        foreach ((array)$user_ids as $user_id) {
            do_action('presspermit_delete_group_user', $group_id, $user_id, $agent_type);
            if (PRESSPERMIT_LEGACY_HOOKS) {
                do_action('presspermit_delete_group_user', $group_id, $user_id, $agent_type);
            }
        }
    }

    private static function validateDurationValue($val)
    {
        if ($val < 0) {
            $val = 0;
        }

        if ($val > PRESSPERMIT_MAX_DATE_STRING) {
            $val = PRESSPERMIT_MAX_DATE_STRING;
        }

        return $val;
    }

    public static function updateGroupUser($group_id, $user_ids, $args = [])
    {
        $defaults = ['agent_type' => 'pp_group'];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        // NOTE: no arg defaults because we only update columns that are explicitly passed
        if (!$cols = Arr::subset($args, ['status', 'date_limited', 'start_date_gmt', 'end_date_gmt'])) {
            return;
        }

        global $wpdb;

        $wpdb->members_table = apply_filters('presspermit_use_group_members_table', $wpdb->pp_group_members, $agent_type);

        if (isset($cols['date_limited'])) {
            $cols['date_limited'] = (int)$cols['date_limited'];
        }

        if (isset($cols['start_date_gmt'])) {
            $cols['start_date_gmt'] = self::validateDurationValue($cols['start_date_gmt']);
        }

        if (isset($cols['end_date_gmt'])) {
            $cols['end_date_gmt'] = self::validateDurationValue($cols['end_date_gmt']);
        }

        $status = (isset($cols['status'])) ? $cols['status'] : '';

        $prev = [];

        $user_id_csv = implode("', '", array_map('intval', (array)$user_ids));

        // Direct query of plugin table on plugin admin operation (IN clause sanitized above)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $wpdb->members_table WHERE group_id = %d AND user_id IN ('$user_id_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $group_id
            )
        );

        foreach ($results as $row) {
            $prev[$row->user_id] = $row;
        }

        foreach ((array)$user_ids as $user_id) {
            // Direct query of plugin table on plugin admin operation
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($wpdb->members_table, $cols, ['group_id' => $group_id, 'user_id' => $user_id]);

            $_prev = (isset($prev[$user_id])) ? $prev[$user_id] : '';

            do_action('presspermit_update_group_user', $group_id, $user_id, $status, $cols, $_prev);
            if (PRESSPERMIT_LEGACY_HOOKS) {
                do_action('presspermit_update_group_user', $group_id, $user_id, $status, $cols, $_prev);
            }
        }
    }

    public static function deleteUserFromGroups($user_id)
    {
        global $wpdb;

        // possible todo: pre-query user groups so we can do_action('presspermit_delete_group_user')

        // Direct query of plugin table on plugin admin operation
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($wpdb->pp_group_members, compact('user_id'));
    }

    public static function createGroup($groupdata, $agent_type = 'pp_group')
    {
        global $wpdb;

        $defaults = ['group_name' => '', 'group_description' => '', 'metagroup_type' => ''];
        $groupdata = array_merge($defaults, (array)$groupdata);
        $groupdata = array_intersect_key($groupdata, $defaults);

        $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);

        if (!self::groupNameAvailable($groupdata['group_name'], $agent_type)) {
            return false;
        }

        // Direct query of plugin table on plugin admin operation
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert($groups_table, $groupdata);

        do_action('presspermit_created_group', (int)$wpdb->insert_id, $agent_type);
        if (PRESSPERMIT_LEGACY_HOOKS) {
            do_action('presspermit_created_group', (int)$wpdb->insert_id, $agent_type);
        }

        return (int)$wpdb->insert_id;
    }

    public static function deleteGroup($group_id, $agent_type = 'pp_group')
    {
        global $wpdb;

        if (!$group_id || !presspermit()->groups()->getGroup($group_id, $agent_type)) {
            return false;
        }

        $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);
        $members_table = apply_filters('presspermit_use_group_members_table', $wpdb->pp_group_members, $agent_type);

        do_action('presspermit_delete_group', $group_id, $agent_type);

        // Direct query of plugin table on plugin admin operations
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $wpdb->delete($wpdb->ppc_roles, ['agent_type' => $agent_type, 'agent_id' => $group_id]);

        $wpdb->delete($groups_table, ['ID' => $group_id]);

        $wpdb->delete($members_table, compact('group_id'));

        do_action('presspermit_deleted_group', $group_id, $agent_type);

        return true;
    }

    /**
     * Updates an existing Group
     *
     * @param int $group_id
     * @param array $groupdata
     * @return boolean true on successful update
     **/
    public static function updateGroup($group_id, $groupdata, $agent_type = 'pp_group')
    {
        global $wpdb;

        $defaults = ['group_name' => '', 'group_description' => ''];
        $groupdata = array_merge($defaults, (array)$groupdata);
        $groupdata = array_intersect_key($groupdata, $defaults);

        $groupdata['group_description'] = wp_strip_all_tags($groupdata['group_description']);

        $wpdb->groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);

        // Direct query of plugin table on plugin admin operations
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ($prev = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $wpdb->groups_table WHERE ID = %d", 
                $group_id
            )
        )) {
            if (($prev->group_name != $groupdata['group_name']) && !self::groupNameAvailable($groupdata['group_name'], $agent_type)) {
                return false;
            }

            // don't allow updating of metagroup name / descript
            if (!empty($prev->metagroup_id)) {
                return false;
            }
        }

        $wpdb->update($wpdb->groups_table, $groupdata, ['ID' => $group_id]);

        return true;
    }

    public static function groupNameAvailable($string, $agent_type = 'pp_group')
    {
        global $wpdb;

        $wpdb->groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);

        // Direct query of plugin table on plugin admin operation
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($string && !$wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM $wpdb->groups_table WHERE group_name = %s LIMIT 1", 
                $string
            )
        )) {
            return true;
        }
    }
}

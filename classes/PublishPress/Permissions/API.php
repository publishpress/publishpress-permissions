<?php
namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

if (!defined('PRESSPERMIT_NO_LEGACY_API') && !defined('PPC_VERSION')) {
    require_once(PRESSPERMIT_ABSPATH . '/library/api-legacy.php');
}

class API
{
    // =========================== Capabilities API ===========================

    public static function getUser($user_id, $name = '', $args = [])
    {
        return presspermit()->getUser($user_id, $name, $args);
    }

    /**
     * Retrieve extra roles for a user or group
     * @param string agent_type
     * @param int agent_id
     * @param array args :
     *   - post_types (default true)
     *   - taxonomies (default true)
     *   - force_refresh (default false)
     * @return array : $roles[role_name] = role_assignment_id
     */
    public static function getRoles($agent_id, $agent_type, $args = [])
    {
        return presspermit()->getRoles($agent_id, $agent_type, $args);
    }

    /**
     * Assign extra roles for a user or group
     * @param array roles : roles[role_name][agent_id] = true
     * @param string agent_type
     */
    public static function assignRoles($group_roles, $agent_type = 'pp_group', $args = [])
    {
        return presspermit()->assignRoles($group_roles, $agent_type, $args);
    }

    public static function deleteRoles($agent_ids, $agent_type)
    {
        return presspermit()->deleteRoles($agent_ids, $agent_type);
    }

    /**
     * Retrieve exceptions for a user or group
     * @param array args :
     *  - agent_type         ('user'|'pp_group'|'pp_net_group'|'bp_group')
     *  - agent_id           (group or user ID)
     *  - operations         ('read'|'edit'|'associate'|'assign'...)
     *  - for_item_source    ('post' or 'term' - data source to which the roles may apply)
     *  - post_types         (post_types to which the roles may apply)
     *  - taxonomies         (taxonomies to which the roles may apply)
     *  - for_item_status    (status to which the roles may apply i.e. 'post_status:private'; default '' means all stati)
     *  - via_item_source    ('post' or 'term' - data source which the role is tied to)
     *  - item_id            (post ID or term_taxonomy_id)
     *  - assign_for         (default 'item'|'children'|'' means both)
     *  - inherited_from     (base exception assignment ID to retrieve propagated assignments for; default '' means N/A)
     */
    public static function getExceptions($args = [])
    {
        return presspermit()->getExceptions($args);
    }

    /**
     * Assign exceptions for a user or group
     * @param array agents : agents['item'|'children'][agent_id] = true|false
     * @param string agent_type
     * @param array args :
     *  - operation          ('read'|'edit'|'associate'|'assign'...)
     *  - mod_type           ('additional'|'exclude'|'include')
     *  - for_item_source    ('post' or 'term' - data source to which the role applies)
     *  - for_item_type      (post_type or taxonomy to which the role applies)
     *  - for_item_status    (status which the role applies to; default '' means all stati)
     *  - via_item_source    ('post' or 'term' - data source which the role is tied to)
     *  - item_id            (post ID or term_taxonomy_id)
     *  - via_item_type      (post_type or taxonomy of item which the role is tied to; default '' means unspecified when via_item_source is 'post')
     */
    public static function assignExceptions($agents, $agent_type = 'pp_group', $args = [])
    {
        return presspermit()->assignExceptions($agents, $agent_type, $args);
    }

    public static function deleteExceptions($agent_ids, $agent_type)
    {
        return presspermit()->deleteExceptions($agent_ids, $agent_type);
    }

    /*
     * args['labels']['name'] = translated caption
     * args['default_caps'] = [cap_name => true, another_cap_name => true] defines caps for pattern roles which do not have a corresponding WP role 
     */
    public static function registerPatternRole($role_name, $args = [])
    {
        return presspermit()->registerPatternRole($role_name, $args);
    }

    public static function countExceptions($agent_type, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsMeta.php');
        return \PublishPress\Permissions\DB\PermissionsMeta::countExceptions($agent_type, $args);
    }

    public static function countRoles($agent_type, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsMeta.php');
        return \PublishPress\Permissions\DB\PermissionsMeta::countRoles($agent_type, $args);
    }


    // =========================== Groups API ===========================

    /**
     * Retrieve users who are members of a specified group
     * @param int group_id
     * @param string agent_type
     * @param string cols ('all' | 'id')
     * @param array args :
     *   - status ('active' | 'scheduled' | 'expired' | 'any')
     * @return array of objects or IDs
     */
    public static function getGroupMembers($group_id, $agent_type = 'pp_group', $cols = 'all', $args = [])
    {
        return presspermit()->groups()->getGroupMembers($group_id, $agent_type, $cols, $args);
    }

    /**
     * Add User(s) to a Permission Group
     * @param int group_id
     * @param array user_ids
     * @param array args :
     *   - agent_type (default 'pp_group')
     *   - status ('active' | 'scheduled' | 'expired' | 'any')
     *   - date_limited (default false)
     *   - start_date_gmt
     *   - end_date_gmt
     */
    public static function addGroupUser($group_id, $user_ids, $args = [])
    {
        return presspermit()->groups()->addGroupUser($group_id, $user_ids, $args);
    }

    /**
     * Remove User(s) from a Permission Group
     * @param int group_id
     * @param array user_ids
     * @param array args :
     *   - group_type (default 'pp_group')
     */
    public static function removeGroupUser($group_id, $user_ids, $args = [])
    {
        return presspermit()->groups()->removeGroupUser($group_id, $user_ids, $args);
    }

    /**
     * Update Group Membership for User(s)
     * @param int group_id
     * @param array user_ids
     * @param array args :
     *   - agent_type (default 'pp_group')
     *   - status ('active' | 'scheduled' | 'expired' | 'any')
     *   - date_limited (default false)
     *   - start_date_gmt
     *   - end_date_gmt
     */
    public static function updateGroupUser($group_id, $user_ids, $args = [])
    {
        return presspermit()->groups()->updateGroupUser($group_id, $user_ids, $args);
    }

    /**
     * Retrieve groups for a specified user
     * @param int user_id
     * @param string agent_type
     * @param array args :
     *   - cols ('all' | 'id')
     *   - status ('active' | 'scheduled' | 'expired' | 'any')
     *   - metagroup_type (default null)
     *   - query_user_ids (array, default false)
     *   - force_refresh (default false)
     * @return array (object or storage date string, with group id as array key)
     */
    public static function getGroupsForUser($user_id, $agent_type = 'pp_group', $args = [])
    {
        return presspermit()->groups()->getGroupsForUser($user_id, $agent_type, $args);
    }

    public static function getGroups($agent_type = 'pp_group', $args = [])
    {
        return presspermit()->groups()->getGroups($agent_type, $args);
    }

    /**
     * Retrieve a Permission Group object
     * @param int group_id
     * @param string agent_type (pp_group, bp_group, etc.)
     * @return object Permission Group
     *  - ID
     *  - group_name
     *  - group_description
     *  - metagroup_type
     *  - metagroup_id
     */
    public static function getGroup($group_id, $agent_type = 'pp_group')
    {
        return presspermit()->groups()->getGroup($group_id, $agent_type);
    }

    /**
     * Create a new Permission Group
     * @param array group_vars_arr :
     *   - group_name
     *   - group_description (optional)
     *   - metagroup_type (optional, for internal use)
     * @return int ID of new group
     */
    public static function createGroup($group_vars_arr)
    {
        return presspermit()->groups()->createGroup($group_vars_arr);
    }

    /**
     * Delete a Permission Group
     * @param array group_id
     * @param array agent_type (pp_group, bp_group, etc.)
     */
    public static function deleteGroup($group_id, $agent_type)
    {
        return presspermit()->groups()->deleteGroup($group_id, $agent_type);
    }

    /**
     * Retrieve the Permission Group object for a WP Role or other metagroup, by providing its name
     * @param string metagroup_type
     * @param string metagroup_id
     * @param array args :
     *   - cols (return format - 'all' | 'id')
     * @return object Permission Group (unless cols = 'id')
     *  - ID
     *  - group_name
     *  - group_description
     *  - metagroup_type
     *  - metagroup_id
     */
    public static function getMetagroup($metagroup_type, $metagroup_id, $args = [])
    {
        return presspermit()->groups()->getMetagroup($metagroup_type, $metagroup_id, $args);
    }

    /**
     * Retrieve a Permission Group object by providing its name
     * @param int group_name
     * @param string agent_type (pp_group, bp_group, etc.)
     * @return object Permission Group
     *  - ID
     *  - group_name
     *  - group_description
     *  - metagroup_type
     *  - metagroup_id
     */
    public static function getGroupByName($name, $agent_type = 'pp_group')
    {
        return presspermit()->groups()->getGroupByName($name, $agent_type);
    }
}

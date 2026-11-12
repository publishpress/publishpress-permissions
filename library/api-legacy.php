<?php
// =========================== Capabilities API (Legacy Wrappers) ===========================

defined('ABSPATH') || exit;

if (!function_exists('pp_get_user')) {
    function pp_get_user($user_id, $name = '', $args = [])
    {
        return \PublishPress\Permissions\API::getUser($user_id, $name, $args);
    }
}

if (!function_exists('ppc_get_roles')) {
    function ppc_get_roles($agent_id, $agent_type = 'pp_group', $args = [])
    {
        return \PublishPress\Permissions\API::getRoles($agent_id, $agent_type, $args);
    }
}

if (!function_exists('ppc_assign_roles')) {
    function ppc_assign_roles($group_roles, $agent_type = 'pp_group', $args = [])
    {
        return \PublishPress\Permissions\API::assignRoles($group_roles, $agent_type, $args);
    }
}

if (!function_exists('ppc_get_exceptions')) {
    function ppc_get_exceptions($args = [])
    {
        return \PublishPress\Permissions\API::getExceptions($args);
    }
}

if (!function_exists('ppc_assign_exceptions')) {
    function ppc_assign_exceptions($agents, $agent_type = 'pp_group', $args = [])
    {
        return \PublishPress\Permissions\API::assignExceptions($agents, $agent_type, $args);
    }
}

if (!function_exists('ppc_delete_agent_permissions')) {
    function ppc_delete_agent_permissions($agent_ids, $agent_type = 'pp_group')
    {
        \PublishPress\Permissions\API::deleteRoles($agent_ids, $agent_type);
        return \PublishPress\Permissions\API::deleteExceptions($agent_ids, $agent_type);
    }
}

if (!function_exists('pp_register_pattern_role')) {
    function pp_register_pattern_role($role_name, $args = [])
    {
        return \PublishPress\Permissions\API::registerPatternRole($role_name, $args);
    }
}


// =========================== Groups API (Legacy Wrappers) ===========================

if (!function_exists('pp_get_group_members')) {
    function pp_get_group_members($group_id, $agent_type = 'pp_group', $cols = 'all', $args = [])
    {
        return \PublishPress\Permissions\API::getGroupMembers($group_id, $agent_type, $cols, $args);
    }
}

if (!function_exists('pp_add_group_user')) {
    function pp_add_group_user($group_id, $user_ids, $args = [])
    {
        return \PublishPress\Permissions\API::addGroupUser($group_id, $user_ids, $args);
    }
}

if (!function_exists('pp_remove_group_user')) {
    function pp_remove_group_user($group_id, $user_ids, $args = [])
    {
        return \PublishPress\Permissions\API::removeGroupUser($group_id, $user_ids, $args);
    }
}

if (!function_exists('pp_update_group_user')) {
    function pp_update_group_user($group_id, $user_ids, $args = [])
    {
        return \PublishPress\Permissions\API::updateGroupUser($group_id, $user_ids, $args);
    }
}

if (!function_exists('pp_get_groups_for_user')) {
    function pp_get_groups_for_user($user_id, $agent_type = 'pp_group', $args = [])
    {
        return \PublishPress\Permissions\API::getGroupsForUser($user_id, $agent_type, $args);
    }
}

if (!function_exists('pp_get_groups')) {
    function pp_get_groups($agent_type = 'pp_group', $args = [])
    {
        return \PublishPress\Permissions\API::getGroups($agent_type, $args);
    }
}

if (!function_exists('pp_get_group')) {
    function pp_get_group($group_id, $agent_type = 'pp_group')
    {
        return \PublishPress\Permissions\API::getGroup($group_id, $agent_type);
    }
}

if (!function_exists('pp_create_group')) {
    function pp_create_group($group_vars_arr)
    {
        return \PublishPress\Permissions\API::createGroup($group_vars_arr);
    }
}

if (!function_exists('pp_delete_group')) {
    function pp_delete_group($group_id, $agent_type)
    {
        return \PublishPress\Permissions\API::deleteGroup($group_id, $agent_type);
    }
}

if (!function_exists('pp_get_metagroup')) {
    function pp_get_metagroup($metagroup_type, $metagroup_id, $args = [])
    {
        return \PublishPress\Permissions\API::getMetagroup($metagroup_type, $metagroup_id, $args);
    }
}

if (!function_exists('pp_get_group_by_name')) {
    function pp_get_group_by_name($name, $agent_type = 'pp_group')
    {
        return \PublishPress\Permissions\API::getGroupByName($name, $agent_type);
    }
}

if (!function_exists('pp_register_group_type')) {
    function pp_register_group_type($agent_type, $args = [])
    {
        return presspermit()->groups()->registerGroupType($agent_type, $args);
    }
}


if (!function_exists('pp_load_admin_api')) {
    function pp_load_admin_api()
    {
    }
}

// called by CME < 1.8
if (!function_exists('pp_get_enabled_types')) {
    function pp_get_enabled_types( $src_name, $args = [], $output = 'names' ) {
        return ( 'post' == $src_name ) ? presspermit()->getEnabledPostTypes( $args, $output ) : [];
    }
}

// called by CME < 1.8
if (!function_exists('pp_get_option')) {
    function pp_get_option($option_basename) {
        return presspermit()->getOption($option_basename);
    }
}

// called by CME < 1.8
if (!function_exists('pp_update_option')) {
    function pp_update_option($option_basename, $val) {
        return presspermit()->updateOption($option_basename, $val);
    }
}

// called by CME < 1.8
if (!function_exists('pp_refresh_options')) {
    function pp_refresh_options() {
        return presspermit()->refreshOptions();
    }
}

// called by CME < 1.8
if (!function_exists('pp_init_cap_caster')) {
    function pp_init_cap_caster() {
        return presspermit()->capCaster();
    }
}

// called by CME < 1.8
if (!function_exists('pp_icon')) {
    function pp_icon() {
        require_once(PRESSPERMIT_CLASSPATH . '/UI/PluginPage.php');
        return \PublishPress\Permissions\UI\PluginPage::icon();
    }
}
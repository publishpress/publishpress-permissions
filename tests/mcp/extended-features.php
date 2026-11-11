<?php
/** Direct roles, native group delegation and prevalidated batches through the released Adapter. */
function permissions_mcp_extended_features($subject_id)
{
    global $wpdb;
    $saved = [];
    foreach (['role_usage', 'advanced_options', 'display_group_exceptions'] as $name) {
        $missing = new stdClass();
        $raw = get_option('presspermit_' . $name, $missing);
        $saved[$name] = ['exists' => $raw !== $missing, 'value' => $raw];
    }
    $ok = static function ($result, $message) {
        permissions_mcp_check(!is_wp_error($result), $message . (is_wp_error($result) ? ': ' . $result->get_error_message() : ''));
        return $result;
    };
    $group_id = 0;
    $posts = [];
    try {
        add_role('mcp_direct_fixture', 'MCP Direct Fixture', ['read' => true, 'edit_posts' => true, 'edit_pages' => true]);
        add_role('mcp_unsafe_fixture', 'MCP Unsafe Fixture', ['read' => true, 'manage_options' => true]);
        presspermit()->updateOption('advanced_options', '1');
        $usage = $ok(permissions_mcp_call('get-role-usage'), 'Direct Role Usage discovery');
        $ok(permissions_mcp_call('update-role-usage', ['revision' => $usage['revision'], 'changes' => [['role' => 'mcp_direct_fixture', 'usage' => 'direct']]]), 'Native direct role enable');
        $subject = ['agent_type' => 'user', 'agent_id' => $subject_id];
        $roles = $ok(permissions_mcp_call('get-extra-roles', $subject), 'Direct role discovery');
        permissions_mcp_check(in_array('mcp_direct_fixture', array_column($roles['direct_roles'], 'name'), true), 'Native direct role missing from discovery.');
        $primary_roles = get_user_meta($subject_id, $wpdb->prefix . 'capabilities', true);
        $grant = $subject + ['revision' => $roles['revision'], 'changes' => [['role' => 'mcp_direct_fixture', 'enabled' => true]]];
        $ok(permissions_mcp_call('update-extra-roles', $grant + ['dry_run' => true]), 'Direct role preview');
        permissions_mcp_check($roles['revision'] === permissions_mcp_call('get-extra-roles', $subject)['revision'], 'Direct preview saved an assignment.');
        $deny_promote = static function ($caps) { $caps['promote_users'] = false; return $caps; };
        add_filter('user_has_cap', $deny_promote, PHP_INT_MAX);
        try {
            permissions_mcp_check(is_wp_error(permissions_mcp_call('update-extra-roles', $grant)), 'Direct grant bypassed a denied promote_users capability.');
        } finally {
            remove_filter('user_has_cap', $deny_promote, PHP_INT_MAX);
        }
        $assigned = $ok(permissions_mcp_call('update-extra-roles', $grant), 'Native direct role save');
        permissions_mcp_check(in_array('mcp_direct_fixture', array_column($assigned['roles'], 'role'), true), 'Direct role failed native read-back.');
        permissions_mcp_check($primary_roles === get_user_meta($subject_id, $wpdb->prefix . 'capabilities', true), 'Supplemental role replaced primary WordPress roles.');
        $grant['revision'] = $assigned['revision'];
        $grant['changes'][0]['enabled'] = false;
        $ok(permissions_mcp_call('update-extra-roles', $grant), 'Direct role removal');
        $usage = (array) presspermit()->getOption('role_usage');
        $usage['mcp_unsafe_fixture'] = 'direct';
        presspermit()->updateRoleUsage($usage);
        $roles = permissions_mcp_call('get-extra-roles', $subject);
        permissions_mcp_check(is_wp_error(permissions_mcp_call('update-extra-roles', $subject + ['revision' => $roles['revision'], 'changes' => [['role' => 'mcp_unsafe_fixture', 'enabled' => true]]])), 'Direct role granted administrator-level capabilities.');
        foreach (['install_languages', 'manage_privacy_options', 'pp_assign_roles'] as $admin_cap) {
            $unsafe = get_role('mcp_unsafe_fixture');
            foreach (array_keys($unsafe->capabilities) as $cap) {
                $unsafe->remove_cap($cap);
            }
            $unsafe->add_cap('read');
            $unsafe->add_cap($admin_cap);
            presspermit()->updateRoleUsage((array) presspermit()->getOption('role_usage'));
            $current = permissions_mcp_call('get-extra-roles', $subject);
            permissions_mcp_check(is_wp_error(permissions_mcp_call('update-extra-roles', $subject + ['revision' => $current['revision'], 'changes' => [['role' => 'mcp_unsafe_fixture', 'enabled' => true]]])), 'Direct role granted protected native administration capability: ' . $admin_cap);
        }
        $created = $ok(permissions_mcp_call('create-group', ['name' => 'Delegation ' . wp_generate_uuid4()]), 'Delegation target creation');
        $group_id = $created['id'];
        $pro_members = $pro_circles = null;
        if (defined('PRESSPERMIT_PRO_VERSION')) {
            $members = $ok(permissions_mcp_call('update-group-members', ['id' => $group_id, 'revision' => $created['revision'], 'action' => 'add', 'user_ids' => [$subject_id]]), 'Pro group member fixture');
            if (presspermit()->moduleActive('membership')) {
                $members = $ok(permissions_mcp_call('set-group-membership', ['id' => $group_id, 'user_id' => $subject_id, 'revision' => $members['revision'],
                    'changes' => ['date_limited' => true, 'status' => 'scheduled', 'start_date_gmt' => '2030-01-01 00:00:00', 'end_date_gmt' => '2031-01-01 00:00:00']]), 'Pro membership preservation fixture');
                $pro_members = $members['members'];
            }
            if (presspermit()->moduleActive('circles')) {
                $circle = $ok(permissions_mcp_call('get-circles', ['id' => $group_id]), 'Pro circle preservation discovery');
                $circle = $ok(permissions_mcp_call('set-circles', ['id' => $group_id, 'revision' => $circle['revision'],
                    'changes' => [['operation' => 'read', 'post_type' => 'page', 'enabled' => true]]]), 'Pro circle preservation fixture');
                $pro_circles = $circle['circles'];
            }
        }
        // Same numeric ID in another agent namespace must retain its assignment.
        $wpdb->insert($wpdb->ppc_roles, ['role_name' => 'mcp_direct_fixture', 'agent_type' => 'user', 'agent_id' => $group_id, 'assigner_id' => get_current_user_id()]);
        $foreign_id = (int) $wpdb->insert_id;
        $group_roles = permissions_mcp_call('get-extra-roles', ['agent_type' => 'pp_group', 'agent_id' => $group_id]);
        $group_assigned = $ok(permissions_mcp_call('update-extra-roles', ['agent_type' => 'pp_group', 'agent_id' => $group_id, 'revision' => $group_roles['revision'], 'changes' => [['role' => 'mcp_direct_fixture', 'enabled' => true]]]), 'Direct group role save');
        permissions_mcp_check((int) $wpdb->get_var($wpdb->prepare("SELECT assignment_id FROM $wpdb->ppc_roles WHERE assignment_id = %d", $foreign_id)) === $foreign_id, 'Role insertion deleted an assignment in another agent namespace.');
        $wpdb->delete($wpdb->ppc_roles, ['assignment_id' => $foreign_id]);
        $ok(permissions_mcp_call('update-extra-roles', ['agent_type' => 'pp_group', 'agent_id' => $group_id, 'revision' => $group_assigned['revision'], 'changes' => [['role' => 'mcp_direct_fixture', 'enabled' => false]]]), 'Direct group role removal');
        $role_items = [];
        foreach ([$subject, ['agent_type' => 'pp_group', 'agent_id' => $group_id]] as $role_subject) {
            $current = permissions_mcp_call('get-extra-roles', $role_subject);
            $role_items[] = $role_subject + ['revision' => $current['revision'], 'changes' => [['role' => 'mcp_direct_fixture', 'enabled' => true]]];
        }
        $invalid_roles = $role_items;
        $invalid_roles[1]['changes'][0]['role'] = 'mcp_unsafe_fixture';
        permissions_mcp_check(is_wp_error(permissions_mcp_call('bulk-update-extra-roles', ['items' => $invalid_roles])), 'An invalid role batch was accepted.');
        permissions_mcp_check(!in_array('mcp_direct_fixture', array_column(permissions_mcp_call('get-extra-roles', $subject)['roles'], 'role'), true), 'Invalid role batch changed the first subject.');
        $role_batch = $ok(permissions_mcp_call('bulk-update-extra-roles', ['items' => $role_items]), 'Native supplemental role batch');
        permissions_mcp_check($role_batch['completed'] && $role_batch['confirmed_items'] === 2, 'Role batch failed.');
        foreach ($role_batch['results'] as $index => $row) {
            $role_items[$index]['revision'] = $row['state']['revision'];
            $role_items[$index]['changes'][0]['enabled'] = false;
        }
        $ok(permissions_mcp_call('bulk-update-extra-roles', ['items' => $role_items]), 'Native role batch removal');
        if (null !== $pro_members) {
            permissions_mcp_check($pro_members === permissions_mcp_call('get-group', ['id' => $group_id])['members'], 'Role batches changed a Pro membership schedule.');
        }
        if (null !== $pro_circles) {
            permissions_mcp_check($pro_circles === permissions_mcp_call('get-circles', ['id' => $group_id])['circles'], 'Role batches changed Pro circles.');
        }
        presspermit()->updateOption('display_group_exceptions', '1');
        $delegation_scope = $subject + ['source' => 'pp_group', 'target_source' => 'pp_group', 'object_id' => $group_id, 'operation' => 'manage'];
        $state = $ok(permissions_mcp_call('get-permissions', $delegation_scope), 'Delegation discovery');
        $delegation = $delegation_scope + ['revision' => $state['revision'], 'mod_type' => 'additional', 'assign_for' => 'item', 'enabled' => true];
        presspermit()->updateOption('display_group_exceptions', '');
        permissions_mcp_check(is_wp_error(permissions_mcp_call('get-permissions', $delegation_scope)), 'Disabled group-delegation setting was bypassed.');

        presspermit()->updateOption('display_group_exceptions', '1');
        $deny_management = static function ($caps) { $caps['pp_manage_members'] = false; return $caps; };
        add_filter('user_has_cap', $deny_management, PHP_INT_MAX);
        try {
            permissions_mcp_check(is_wp_error(permissions_mcp_call('set-permission', $delegation + ['dry_run' => true])), 'Delegation bypassed a denied management capability.');
        } finally {
            remove_filter('user_has_cap', $deny_management, PHP_INT_MAX);
        }
        $protected = wp_insert_post(['post_type' => 'page', 'post_status' => 'private', 'post_title' => 'Delegation protected scope']);
        $posts[] = $protected;
        $protected_scope = ['agent_type' => 'pp_group', 'agent_id' => $group_id, 'source' => 'post', 'object_id' => $protected, 'operation' => 'read'];
        $protected_state = permissions_mcp_call('get-permissions', $protected_scope);
        $protected_saved = $ok(permissions_mcp_call('set-permission', $protected_scope + ['revision' => $protected_state['revision'], 'mod_type' => 'additional', 'assign_for' => 'item', 'enabled' => true]), 'Protected group grant setup');
        permissions_mcp_check(is_wp_error(permissions_mcp_call('set-permission', $delegation + ['dry_run' => true])), 'Changed target-group grants accepted an old delegation revision.');
        $protected_delegation = $delegation;
        $protected_delegation['revision'] = permissions_mcp_call('get-permissions', $delegation_scope)['revision'];
        $deny_protected = static function ($caps, $cap, $user, $args) use ($protected) {
            return $cap === 'edit_post' && (int) ($args[0] ?? 0) === (int) $protected ? ['do_not_allow'] : $caps;
        };
        add_filter('map_meta_cap', $deny_protected, PHP_INT_MAX, 4);
        try {
            permissions_mcp_check(is_wp_error(permissions_mcp_call('set-permission', $protected_delegation + ['dry_run' => true])), 'Delegation redistributed a group grant over an unauthorized object.');
        } finally {
            remove_filter('map_meta_cap', $deny_protected, PHP_INT_MAX);
        }
        $ok(permissions_mcp_call('set-permission', $protected_scope + ['revision' => $protected_saved['revision'], 'mod_type' => 'additional', 'assign_for' => 'item', 'enabled' => false]), 'Protected group grant removal');
        $ok(permissions_mcp_call('set-permission', $delegation + ['dry_run' => true]), 'Delegation preview');
        $delegated = $ok(permissions_mcp_call('set-permission', $delegation), 'Native delegation save');
        permissions_mcp_check(count($delegated['rules']) === 1, 'Native delegation did not round-trip.');
        permissions_mcp_check(is_wp_error(permissions_mcp_call('get-group-deletion', ['id' => $group_id])), 'Referenced delegation target could be deleted.');
        $delegation['revision'] = $delegated['revision'];
        $delegation['enabled'] = false;
        $ok(permissions_mcp_call('set-permission', $delegation), 'Native delegation removal');
        $scopes = [];
        $posts = array_values($posts);
        $batch_posts = [];
        foreach (['A', 'B'] as $label) {
            $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'MCP Batch ' . $label]);
            $posts[] = $id;
            $batch_posts[] = $id;
            $scopes[] = $subject + ['source' => 'post', 'object_id' => $id, 'operation' => 'read'];
        }
        $matrix = $ok(permissions_mcp_call('get-permission-matrix', ['items' => $scopes]), 'Permission matrix');
        $items = [];
        foreach ($matrix['items'] as $row) {
            $items[] = $row['scope'] + ['revision' => $row['state']['revision'], 'mod_type' => 'exclude', 'assign_for' => 'item', 'enabled' => true];
        }
        $bad = $items;
        $bad[1]['object_id'] = PHP_INT_MAX;
        permissions_mcp_check(is_wp_error(permissions_mcp_call('bulk-set-permissions', ['items' => $bad])), 'Invalid second item allowed a batch.');
        permissions_mcp_check($matrix === permissions_mcp_call('get-permission-matrix', ['items' => $scopes]), 'Invalid batch partially saved.');
        $deny_object = static function ($caps, $cap, $user, $args) use ($batch_posts) {
            return $cap === 'edit_post' && (int) ($args[0] ?? 0) === (int) $batch_posts[1] ? ['do_not_allow'] : $caps;
        };
        add_filter('map_meta_cap', $deny_object, PHP_INT_MAX, 4);
        try {
            permissions_mcp_check(is_wp_error(permissions_mcp_call('bulk-set-permissions', ['items' => $items])), 'A denied second object allowed the first batch write.');
            $direct_now = permissions_mcp_call('get-extra-roles', $subject);
            permissions_mcp_check(is_wp_error(permissions_mcp_call('update-extra-roles', $subject + ['revision' => $direct_now['revision'], 'changes' => [['role' => 'mcp_direct_fixture', 'enabled' => true]], 'dry_run' => true])), 'Direct role grant ignored a denied affected post.');
        } finally {
            remove_filter('map_meta_cap', $deny_object, PHP_INT_MAX);
        }
        permissions_mcp_check($matrix === permissions_mcp_call('get-permission-matrix', ['items' => $scopes]), 'Denied batch partially saved.');
        $duplicate = [$items[0], $items[0]];
        permissions_mcp_check(is_wp_error(permissions_mcp_call('bulk-set-permissions', ['items' => $duplicate])), 'Overlapping batch targets were accepted.');
        $ok(permissions_mcp_call('bulk-set-permissions', ['items' => $items, 'dry_run' => true]), 'Bulk preview');
        permissions_mcp_check($matrix === permissions_mcp_call('get-permission-matrix', ['items' => $scopes]), 'Bulk preview saved data.');
        $bulk = $ok(permissions_mcp_call('bulk-set-permissions', ['items' => $items]), 'Native permission batch');
        permissions_mcp_check($bulk['completed'] && $bulk['confirmed_items'] === 2, 'Authorized batch failed.');
        permissions_mcp_check(is_wp_error(permissions_mcp_call('bulk-set-permissions', ['items' => $items])), 'Stale batch was accepted.');
        foreach ($bulk['results'] as $index => $row) {
            $items[$index]['revision'] = $row['state']['revision'];
            $items[$index]['enabled'] = false;
        }
        $ok(permissions_mcp_call('bulk-set-permissions', ['items' => $items]), 'Bulk permission removal');
        $matrix = permissions_mcp_call('get-permission-matrix', ['items' => $scopes]);
        foreach ($matrix['items'] as $index => $row) {
            $items[$index]['revision'] = $row['state']['revision'];
            $items[$index]['enabled'] = true;
        }
        $inserts = 0;
        $fail_second = static function ($sql) use (&$inserts, $wpdb) {
            if (stripos($sql, 'INSERT INTO `' . $wpdb->ppc_exception_items . '`') === 0 && ++$inserts === 2) {
                return 'SELECT 0';
            }
            return $sql;
        };
        add_filter('query', $fail_second, PHP_INT_MAX);
        try {
            $partial = permissions_mcp_call('bulk-set-permissions', ['items' => $items]);
            permissions_mcp_check(!is_wp_error($partial) && !$partial['completed'] && $partial['confirmed_items'] === 1 && $partial['failed_index'] === 1, 'Native batch failure concealed confirmed preceding writes.');
        } finally {
            remove_filter('query', $fail_second, PHP_INT_MAX);
        }
    } finally {
        foreach ($posts as $id) {
            wp_delete_post($id, true);
        }
        if ($group_id) {
            presspermit()->groups()->deleteGroup($group_id, 'pp_group');
        }
        foreach ($saved as $name => $row) {
            $row['exists'] ? update_option('presspermit_' . $name, $row['value']) : delete_option('presspermit_' . $name);
        }
        remove_role('mcp_direct_fixture');
        remove_role('mcp_unsafe_fixture');
        presspermit()->updateRoleUsage((array) presspermit()->getOption('role_usage'));
    }
}
permissions_mcp_extended_features($user_id);

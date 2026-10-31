<?php
/** Additional Adapter round trips for native module, teaser, deletion and Pro maintenance paths. */
require_once ABSPATH . 'wp-admin/includes/user.php';
$backups = [];
$backup = static function ($name) use (&$backups) {
    $key = 'presspermit_' . $name;
    if (!array_key_exists($key, $backups)) {
        $missing = new stdClass();
        $value = get_option($key, $missing);
        $backups[$key] = ['exists' => $value !== $missing, 'value' => $value];
    }
};
$check_result = static function ($result, $label) {
    permissions_mcp_check(!is_wp_error($result), $label . (is_wp_error($result) ? ': ' . $result->get_error_message() : ''));
    return $result;
};
$extra_group = $sync_user = $scan_post = $bad_scan_post = 0;
$sync_posts = [];
$fixture_file = null;
try {
    foreach (['', 'page'] as $scope) {
        $teaser = $check_result(permissions_mcp_call('get-teaser', ['post_type' => $scope]), 'Teaser discovery');
        foreach (array_keys((array) $teaser['settings']) as $name) {
            $backup($name);
        }
    }
    $backup('advanced_options');
    $settings = $check_result(permissions_mcp_call('get-settings'), 'Advanced settings discovery');
    $check_result(permissions_mcp_call('update-settings', ['revision' => $settings['revision'], 'changes' => (object) ['advanced_options' => true]]), 'Native advanced controls enable');
    $backup('role_usage');
    $usage_role = 'mcp_usage_fixture';
    add_role($usage_role, 'MCP usage fixture', ['read' => true]);
    $usage = $check_result(permissions_mcp_call('get-role-usage'), 'Role Usage discovery');
    $usage_patch = ['revision' => $usage['revision'], 'changes' => [(object) ['role' => $usage_role, 'usage' => 'direct']], 'dry_run' => true];
    $check_result(permissions_mcp_call('update-role-usage', $usage_patch), 'Role Usage preview');
    $usage_patch['dry_run'] = false;
    $usage_saved = $check_result(permissions_mcp_call('update-role-usage', $usage_patch), 'Native Role Usage save');
    $usage_rows = array_column($usage_saved['roles'], 'usage', 'role');
    permissions_mcp_check('direct' === $usage_rows[$usage_role], 'Role Usage native read-back differs.');
    $visibility = $check_result(permissions_mcp_call('get-content-visibility'), 'Visibility discovery');
    permissions_mcp_check('pp_restrict' === $visibility['shortcode'], 'Visibility shortcode differs from native registration.');
    $built = $check_result(permissions_mcp_call('build-content-visibility', ['conditions' => (object) ['logged' => 'in'], 'content' => '<b>Members</b>']), 'Visibility authoring');
    permissions_mcp_check(false === $built['saved'] && str_contains($built['shortcode'], 'logged="in"'), 'Visibility builder saved content or lost conditions.');
    permissions_mcp_check(is_wp_error(permissions_mcp_call('build-content-visibility', ['conditions' => (object) ['hide' => 'false'], 'content' => 'x'])), 'String visibility boolean accepted.');
    permissions_mcp_check(is_wp_error(permissions_mcp_call('build-content-visibility', ['conditions' => (object) ['logged' => 'in'], 'content' => '[/pp_restrict]public'])), 'Visibility closing-tag escape accepted.');
    $role_state = $check_result(permissions_mcp_call('get-extra-roles', ['agent_type' => 'user', 'agent_id' => $user_id]), 'Status-role discovery');
    $status_patch = ['agent_type' => 'user', 'agent_id' => $user_id, 'revision' => $role_state['revision'],
        'changes' => [(object) ['role' => 'subscriber:post:page:post_status:private', 'enabled' => true]], 'dry_run' => true];
    $check_result(permissions_mcp_call('update-extra-roles', $status_patch), 'Native private-status role preview');
    $backup('teaser_num_chars');
    presspermit()->updateOption('teaser_num_chars', ['page' => '77', 'post' => '91']);
    presspermit()->refreshOptions();
    $teaser = permissions_mcp_call('get-teaser', ['post_type' => 'page']);
    permissions_mcp_check(77 === $teaser['settings']->x_chars_num_chars['value'], 'Native legacy teaser length fallback was lost.');
    $patch = ['post_type' => 'page', 'revision' => $teaser['revision'],
        'changes' => (object) ['tease_post_types' => 'x_chars', 'x_chars_num_chars' => 80,
            'tease_prepend_content' => '<b>Reader\s choice</b><script>alert(1)</script>', 'teaser_notice_border_width' => 0], 'dry_run' => true];
    $preview = $check_result(permissions_mcp_call('update-teaser', $patch), 'Teaser preview');
    permissions_mcp_check($teaser['revision'] === permissions_mcp_call('get-teaser', ['post_type' => 'page'])['revision'], 'Teaser preview saved options.');
    $invalid = $patch;
    $invalid['changes'] = (object) ['x_chars_num_chars' => '80', 'tease_post_types' => 'excerpt'];
    permissions_mcp_check(is_wp_error(permissions_mcp_call('update-teaser', $invalid)), 'Numeric-string teaser length accepted.');
    $invalid['changes'] = (object) ['teaser_redirect_anon' => '(url)', 'teaser_redirect_anon_url' => 'javascript:alert(1)'];
    permissions_mcp_check(is_wp_error(permissions_mcp_call('update-teaser', $invalid)), 'Unsafe teaser redirect accepted.');
    $patch['dry_run'] = false;
    $saved = $check_result(permissions_mcp_call('update-teaser', $patch), 'Teaser save');
    permissions_mcp_check(80 === $saved['settings']->x_chars_num_chars['value'], 'Teaser length type changed.');
    permissions_mcp_check(0 === $saved['settings']->teaser_notice_border_width['value'], 'Zero border width was lost.');
    permissions_mcp_check('<b>Reader\s choice</b>alert(1)' === $saved['settings']->tease_prepend_content['value'], 'Teaser native sanitizer/slashing differs.');
    permissions_mcp_check(is_wp_error(permissions_mcp_call('update-teaser', $patch)), 'Stale teaser revision accepted.');
    $current = permissions_mcp_call('get-teaser', ['post_type' => 'page']);
    $block_save = static function ($new, $old) { return $old; };
    add_filter('pre_update_option_presspermit_tease_append_content', $block_save, PHP_INT_MAX, 2);
    try {
        $partial = permissions_mcp_call('update-teaser', ['post_type' => 'page', 'revision' => $current['revision'],
            'changes' => (object) ['tease_prepend_content' => 'First native step', 'tease_append_content' => 'Blocked second step']]);
        permissions_mcp_check(is_wp_error($partial) && 'presspermit_mcp_save_unconfirmed' === $partial->get_error_code(), 'Partial native option failure was not reported as unconfirmed.');
        $partial_state = permissions_mcp_call('get-teaser', ['post_type' => 'page']);
        permissions_mcp_check('First native step' === $partial_state['settings']->tease_prepend_content['value'], 'Partial native save incorrectly claimed rollback.');
    } finally {
        remove_filter('pre_update_option_presspermit_tease_append_content', $block_save, PHP_INT_MAX);
    }
    $global_teaser = permissions_mcp_call('get-teaser');
    $empty_feed = $check_result(permissions_mcp_call('update-teaser', ['revision' => $global_teaser['revision'], 'changes' => (object) ['feed_teaser' => '']]), 'Empty feed teaser save');
    permissions_mcp_check('' === $empty_feed['settings']->feed_teaser['value'], 'Explicit empty feed teaser was replaced by its default.');
    $deny = static function ($caps) { $caps['pp_manage_teaser'] = false; return $caps; };
    add_filter('user_has_cap', $deny, PHP_INT_MAX);
    try {
        permissions_mcp_check(is_wp_error(permissions_mcp_call('get-teaser')), 'Administrator bypassed teaser capability denial.');
    } finally {
        remove_filter('user_has_cap', $deny, PHP_INT_MAX);
    }
    $modules = $check_result(permissions_mcp_call('get-modules'), 'Module discovery');
    $backup('deactivated_modules');
    $required = ['revision' => $modules['revision'], 'changes' => [(object) ['module' => 'presspermit-collaboration', 'enabled' => false]]];
    permissions_mcp_check(is_wp_error(permissions_mcp_call('update-modules', $required)), 'Required Collaboration module could be disabled.');
    $switch = ['revision' => $modules['revision'], 'changes' => [(object) ['module' => 'presspermit-teaser', 'enabled' => false]], 'dry_run' => true];
    $check_result(permissions_mcp_call('update-modules', $switch), 'Module preview');
    permissions_mcp_check($modules['revision'] === permissions_mcp_call('get-modules')['revision'], 'Module preview saved options.');
    $switch['dry_run'] = false;
    $switched = $check_result(permissions_mcp_call('update-modules', $switch), 'Module switch');
    $module_rows = array_column($switched['modules'], null, 'module');
    permissions_mcp_check(!$module_rows['presspermit-teaser']['enabled'] && $switched['applies_next_request'], 'Native module switch did not persist.');
    $restore = ['revision' => $switched['revision'], 'changes' => [(object) ['module' => 'presspermit-teaser', 'enabled' => true]]];
    $check_result(permissions_mcp_call('update-modules', $restore), 'Module restore');
    $created = $check_result(permissions_mcp_call('create-group', ['name' => 'Delete fixture ' . wp_generate_uuid4()]), 'Deletion group creation');
    $extra_group = $created['id'];
    $scope = ['agent_type' => 'pp_group', 'agent_id' => $extra_group, 'source' => 'post', 'object_id' => $post_id, 'operation' => 'read'];
    $rules = $check_result(permissions_mcp_call('get-permissions', $scope), 'Deletion permission scope');
    $check_result(permissions_mcp_call('set-permission', $scope + ['revision' => $rules['revision'], 'mod_type' => 'exclude', 'assign_for' => 'item', 'enabled' => true]), 'Deletion permission fixture');
    $deletion = $check_result(permissions_mcp_call('get-group-deletion', ['id' => $extra_group]), 'Deletion preflight');
    permissions_mcp_check(1 === $deletion['permission_count'], 'Deletion preflight missed a native permission.');
    $delete = ['id' => $extra_group, 'revision' => $deletion['revision'], 'dry_run' => true];
    $check_result(permissions_mcp_call('delete-group', $delete), 'Deletion preview');
    permissions_mcp_check(!is_wp_error(permissions_mcp_call('get-group', ['id' => $extra_group])), 'Preview deleted the group.');
    $deny_delete_object = static function ($caps, $cap, $actor, $args) use ($post_id) {
        return 'edit_post' === $cap && (int) ($args[0] ?? 0) === (int) $post_id ? ['do_not_allow'] : $caps;
    };
    add_filter('map_meta_cap', $deny_delete_object, PHP_INT_MAX, 4);
    try {
        permissions_mcp_check(is_wp_error(permissions_mcp_call('delete-group', $delete)), 'Group deletion bypassed a denied cascade object.');
    } finally {
        remove_filter('map_meta_cap', $deny_delete_object, PHP_INT_MAX);
    }
    $delete['dry_run'] = false;
    $deleted = $check_result(permissions_mcp_call('delete-group', $delete), 'Group deletion');
    permissions_mcp_check($deleted['deleted'] && is_wp_error(permissions_mcp_call('get-group', ['id' => $extra_group])), 'Native group deletion failed.');
    $extra_group = 0;
    if (defined('PRESSPERMIT_PRO_VERSION') && presspermit()->moduleActive('sync')) {
        foreach (['', 'post'] as $scope) {
            $state = $check_result(permissions_mcp_call('get-sync-settings', ['post_type' => $scope]), 'Sync discovery');
            foreach (array_keys((array) $state['settings']) as $name) {
                $backup($name);
            }
        }
        $global = permissions_mcp_call('get-sync-settings');
        $check_result(permissions_mcp_call('update-sync-settings', ['revision' => $global['revision'],
            'changes' => (object) ['sync_posts_to_users_apply_permissions' => false]]), 'Disable supplemental grants for isolated sync fixture');
        $sync_user = wp_insert_user(['user_login' => 'sync-fixture-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber', 'display_name' => 'Sync Fixture ' . wp_generate_uuid4()]);
        permissions_mcp_check(false === permissions_mcp_call('get-sync-settings')['settings']->sync_posts_to_users_apply_permissions['value'], 'Explicit disabled permission grants were replaced by the true default.');
        $state = permissions_mcp_call('get-sync-settings', ['post_type' => 'post']);
        $changes = ['sync_posts_to_users_existing' => true, 'sync_posts_to_users_new' => false,
            'sync_posts_to_users_post_field' => 'post_name', 'sync_posts_to_users_user_field' => 'user_nicename',
            'sync_posts_to_users_role' => ['subscriber'], 'sync_posts_to_users_status' => 'draft', 'sync_posts_to_users_quantity' => 1];
        $patch = ['post_type' => 'post', 'revision' => $state['revision'], 'changes' => (object) $changes, 'dry_run' => true];
        $check_result(permissions_mcp_call('update-sync-settings', $patch), 'Sync settings preview');
        $bad = $patch;
        $bad['changes'] = (object) ['sync_posts_to_users_user_field' => '[session_tokens]', 'sync_posts_to_users_existing' => true];
        permissions_mcp_check(is_wp_error(permissions_mcp_call('update-sync-settings', $bad)), 'Secret template was accepted.');
        $patch['dry_run'] = false;
        $state = $check_result(permissions_mcp_call('update-sync-settings', $patch), 'Sync settings save');
        permissions_mcp_check(['subscriber'] === $state['settings']->sync_posts_to_users_role['value'], 'Native role list representation differs.');
        $global = permissions_mcp_call('get-sync-settings');
        $check_result(permissions_mcp_call('update-sync-settings', ['revision' => $global['revision'],
            'changes' => (object) ['sync_posts_to_users' => true, 'sync_posts_to_users_apply_permissions' => false]]), 'Global sync switch');
        $plan = $check_result(permissions_mcp_call('get-sync-plan', ['post_type' => 'post']), 'Sync plan');
        $run = ['post_type' => 'post', 'revision' => $plan['revision'], 'dry_run' => true];
        $check_result(permissions_mcp_call('run-sync', $run), 'Sync preview');
        $run['dry_run'] = false;
        $collect = static function ($id) use (&$sync_posts) { $sync_posts[] = $id; };
        add_action('presspermit_sync_posts_to_users_added_post', $collect);
        try {
            $result = $check_result(permissions_mcp_call('run-sync', $run), 'Native sync run');
        } finally {
            remove_action('presspermit_sync_posts_to_users_added_post', $collect);
        }
        $user_posts = get_posts(['post_type' => 'post', 'post_status' => 'any', 'author' => $sync_user, 'fields' => 'ids']);
        permissions_mcp_check(1 === count($user_posts), 'Native synchronization did not create the expected user page.');
        permissions_mcp_check('draft' === get_post_status($user_posts[0]), 'Sync ignored the native configured status.');
        $plan = $check_result(permissions_mcp_call('get-sync-plan', ['post_type' => 'post']), 'Sync read-back plan');
        $again = $check_result(permissions_mcp_call('run-sync', ['post_type' => 'post', 'revision' => $plan['revision']]), 'Sync repeat');
        permissions_mcp_check(0 === $again['created'], 'Sync created duplicate matching pages.');
        $failed_user = wp_insert_user(['user_login' => 'failed-sync-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
        try {
            $failure_plan = $check_result(permissions_mcp_call('get-sync-plan', ['post_type' => 'post']), 'Sync failure plan');
            $fail_insert = static function () { return true; };
            add_filter('wp_insert_post_empty_content', $fail_insert, PHP_INT_MAX);
            try {
                permissions_mcp_check(is_wp_error(permissions_mcp_call('run-sync', ['post_type' => 'post', 'revision' => $failure_plan['revision']])), 'Failed native insertion was reported successful.');
            } finally {
                remove_filter('wp_insert_post_empty_content', $fail_insert, PHP_INT_MAX);
            }
        } finally {
            wp_delete_user($failed_user);
        }
    }
    if (defined('PRESSPERMIT_PRO_VERSION') && presspermit()->moduleActive('file-access')) {
        $fixture_file = trailingslashit(wp_upload_dir()['basedir']) . 'mcp-fixture.pdf';
        wp_mkdir_p(dirname($fixture_file));
        file_put_contents($fixture_file, '%PDF-1.0 MCP isolated fixture');
        $scan_post = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Scan fixture',
            'post_content' => '<a href="' . esc_url(trailingslashit(wp_upload_dir()['baseurl']) . 'mcp-fixture.pdf') . '">Download</a>']);
        $bad_scan_post = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Invalid scan fixture',
            'post_content' => '<a href="' . esc_url(trailingslashit(wp_upload_dir()['baseurl']) . '../outside-probe.pdf') . '">Invalid</a>']);
        permissions_mcp_check(is_wp_error(permissions_mcp_call('get-file-maintenance', ['operation' => 'scan_attachments'])), 'Upload traversal was not rejected before checking file existence.');
        wp_delete_post($bad_scan_post, true);
        $bad_scan_post = 0;
        $plan = $check_result(permissions_mcp_call('get-file-maintenance', ['operation' => 'scan_attachments']), 'File scan plan');
        permissions_mcp_check(!str_contains(wp_json_encode($plan), '_rs_file_key') && !str_contains(wp_json_encode($plan), 'basedir'), 'File maintenance exposed private metadata.');
        $run = ['operation' => 'scan_attachments', 'revision' => $plan['revision'], 'dry_run' => true];
        $check_result(permissions_mcp_call('run-file-maintenance', $run), 'File scan preview');
        $run['dry_run'] = false;
        $scan = $check_result(permissions_mcp_call('run-file-maintenance', $run), 'Native file scan');
        permissions_mcp_check($scan['created'] >= 1, 'Native attachment scan created no records.');
        $busy_plan = $check_result(permissions_mcp_call('get-file-maintenance', ['operation' => 'rebuild']), 'Rewrite plan');
        set_transient('presspermit_flushing_file_rules', true, 60);
        try {
            permissions_mcp_check(is_wp_error(permissions_mcp_call('run-file-maintenance', ['operation' => 'rebuild', 'revision' => $busy_plan['revision']])), 'Busy native rewrite was reported successful.');
        } finally {
            delete_transient('presspermit_flushing_file_rules');
        }
    }
} finally {
    foreach ($sync_posts as $id) {
        wp_delete_post($id, true);
    }
    if ($scan_post) {
        foreach (get_posts(['post_type' => 'attachment', 'post_parent' => $scan_post, 'post_status' => 'any', 'fields' => 'ids']) as $id) {
            wp_delete_attachment($id, true);
        }
        wp_delete_post($scan_post, true);
    }
    if ($bad_scan_post) {
        wp_delete_post($bad_scan_post, true);
    }
    if ($extra_group) {
        presspermit()->groups()->deleteGroup($extra_group, 'pp_group');
    }
    if ($sync_user) {
        wp_delete_user($sync_user);
    }
    if ($fixture_file && file_exists($fixture_file)) {
        unlink($fixture_file);
    }
    if (isset($usage_role)) {
        remove_role($usage_role);
    }
    foreach ($backups as $key => $entry) {
        $entry['exists'] ? update_option($key, $entry['value']) : delete_option($key);
    }
    presspermit()->refreshOptions();
    do_action('presspermit_registrations');
    do_action('presspermit_roles_defined');
}

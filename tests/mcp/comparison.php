<?php
/** Native-format, permission-scope and shared-plugin contract regressions through the released Adapter. */
function permissions_mcp_comparison_cases($group_id, $subject_id, $page_id)
{
    $saved = [];
    $created_posts = $created_users = [];
    $capture = static function ($name) use (&$saved) {
        if (!isset($saved[$name])) {
            $sentinel = new stdClass();
            $value = get_option('presspermit_' . $name, $sentinel);
            $saved[$name] = ['exists' => $value !== $sentinel, 'value' => $value];
        }
    };
    $ok = static function ($result, $message) {
        permissions_mcp_check(!is_wp_error($result), $message . (is_wp_error($result) ? ': ' . $result->get_error_message() : ''));
        return $result;
    };
    $write_options = ['enabled_post_types', 'tease_post_types', 'pp_enable_metabox_mcp_audit', 'pp_include_permission_screen_mcp_audit'];
    foreach ($write_options as $name) {
        $capture($name);
    }
    register_post_type('mcp_audit', ['public' => true, 'show_ui' => true]);
    $term_id = 0;
    try {
        $types = $ok(permissions_mcp_call('get-content-types', ['scope' => 'post_types']), 'Late type choices');
        $ok(permissions_mcp_call('set-content-types', ['scope' => 'post_types', 'revision' => $types['revision'],
            'changes' => [(object) ['name' => 'mcp_audit', 'enabled' => true]]]), 'Late type enable');
        $editor = $ok(permissions_mcp_call('get-editor-options', ['post_type' => 'mcp_audit']), 'Editor switch discovery');
        permissions_mcp_check(true === $editor['settings']->metabox_enabled['value'] && true === $editor['settings']->permission_screen_enabled['value'], 'Absent native editor switches must be enabled.');
        permissions_mcp_check('pp_enable_metabox_mcp_audit' === $editor['settings']->metabox_enabled['native_option'], 'Editor discovery omitted the native option identifier.');
        $patch = ['post_type' => 'mcp_audit', 'revision' => $editor['revision'], 'changes' => (object) ['metabox_enabled' => false], 'dry_run' => true];
        $ok(permissions_mcp_call('update-editor-options', $patch), 'Editor preview');
        permissions_mcp_check($editor['revision'] === permissions_mcp_call('get-editor-options', ['post_type' => 'mcp_audit'])['revision'], 'Editor preview changed native storage.');
        $patch['dry_run'] = false;
        $editor = $ok(permissions_mcp_call('update-editor-options', $patch), 'Editor switch save');
        permissions_mcp_check('0' === get_option('presspermit_pp_enable_metabox_mcp_audit') && false === $editor['settings']->metabox_enabled['value'], 'Disabled native editor switch representation differs.');
        $bad = ['post_type' => 'mcp_audit', 'revision' => $editor['revision'], 'changes' => (object) ['metabox_enabled' => true, 'permission_screen_enabled' => 'false']];
        permissions_mcp_check(is_wp_error(permissions_mcp_call('update-editor-options', $bad)) && '0' === get_option('presspermit_pp_enable_metabox_mcp_audit'), 'Malformed mixed editor patch wrote a valid first field.');
        update_option('presspermit_pp_enable_metabox_mcp_audit', ['legacy' => 0]);
        presspermit()->refreshOptions();
        $editor = $ok(permissions_mcp_call('get-editor-options', ['post_type' => 'mcp_audit']), 'Legacy editor switch read');
        permissions_mcp_check(true === $editor['settings']->metabox_enabled['value'], 'Legacy native editor array must retain its native enabled interpretation.');
        $deny_editor = static function ($caps) { $caps['edit_others_posts'] = false; return $caps; };
        add_filter('user_has_cap', $deny_editor, PHP_INT_MAX);
        try {
            permissions_mcp_check(is_wp_error(permissions_mcp_call('get-editor-options', ['post_type' => 'mcp_audit'])), 'Administrator bypassed denied editor-type permission.');
        } finally {
            remove_filter('user_has_cap', $deny_editor, PHP_INT_MAX);
        }
        $force_type = static function ($values) { $values['mcp_audit'] = '0'; return $values; };
        add_filter('presspermit_enabled_post_types', $force_type, PHP_INT_MAX);
        try {
            $before = get_option('presspermit_enabled_post_types');
            $types = permissions_mcp_call('get-content-types', ['scope' => 'post_types']);
            permissions_mcp_check(is_wp_error(permissions_mcp_call('set-content-types', ['scope' => 'post_types', 'revision' => $types['revision'],
                'changes' => [(object) ['name' => 'mcp_audit', 'enabled' => true]]])), 'Code-forced filtering state was accepted.');
            permissions_mcp_check($before === get_option('presspermit_enabled_post_types'), 'Code-forced filtering failure changed storage.');
        } finally {
            remove_filter('presspermit_enabled_post_types', $force_type, PHP_INT_MAX);
        }
        $raw = (array) get_option('presspermit_tease_post_types', []);
        $raw['page'] = '0';
        $raw['temporarily_unregistered'] = 'original';
        update_option('presspermit_tease_post_types', $raw);
        $force_other = static function ($options) {
            $options['presspermit_tease_post_types']['temporarily_unregistered'] = 'forced';
            return $options;
        };
        add_filter('presspermit_options', $force_other, PHP_INT_MAX);
        try {
            $state = $ok(permissions_mcp_call('get-teaser', ['post_type' => 'page']), 'Filtered map discovery');
            $ok(permissions_mcp_call('update-teaser', ['post_type' => 'page', 'revision' => $state['revision'], 'changes' => (object) ['tease_post_types' => '1']]), 'Filtered map patch');
            permissions_mcp_check('original' === get_option('presspermit_tease_post_types')['temporarily_unregistered'], 'Patch persisted an unrelated filtered value over raw storage.');
        } finally {
            remove_filter('presspermit_options', $force_other, PHP_INT_MAX);
        }
        $hide_posts = static function ($query) { $query->set('post__in', [0]); };
        $deny_page = static function ($caps, $cap, $actor, $args) use ($page_id) {
            return 'edit_post' === $cap && (int) ($args[0] ?? 0) === (int) $page_id ? ['do_not_allow'] : $caps;
        };
        add_action('pre_get_posts', $hide_posts, PHP_INT_MAX);
        add_filter('map_meta_cap', $deny_page, PHP_INT_MAX, 4);
        try {
            permissions_mcp_check(in_array((int) $page_id, \PublishPress\Permissions\Mcp\Access::postIds(['page']), true), 'A query filter hid a native permission target.');
            $roles = permissions_mcp_call('get-extra-roles', ['agent_type' => 'pp_group', 'agent_id' => $group_id]);
            permissions_mcp_check(is_wp_error(permissions_mcp_call('update-extra-roles', ['agent_type' => 'pp_group', 'agent_id' => $group_id, 'revision' => $roles['revision'],
                'changes' => [(object) ['role' => 'subscriber:post:page', 'enabled' => true]], 'dry_run' => true])), 'Hidden denied post was omitted from role-grant preflight.');
        } finally {
            remove_action('pre_get_posts', $hide_posts, PHP_INT_MAX);
            remove_filter('map_meta_cap', $deny_page, PHP_INT_MAX);
        }
        if (defined('PRESSPERMIT_PRO_VERSION') && presspermit()->moduleActive('sync')) {
            foreach (['', 'post', 'mcp_audit'] as $scope) {
                $state = $ok(permissions_mcp_call('get-sync-settings', ['post_type' => $scope]), 'Sync comparison discovery');
                foreach (array_keys((array) $state['settings']) as $name) {
                    $capture($name);
                }
            }
            $global = permissions_mcp_call('get-sync-settings');
            $ok(permissions_mcp_call('update-sync-settings', ['revision' => $global['revision'], 'changes' => (object) ['sync_posts_to_users' => false, 'sync_posts_to_users_apply_permissions' => false]]), 'Isolate native sync configuration');
            foreach (['subscriber', 'editor'] as $i => $role) {
                $id = wp_insert_user(['user_login' => 'comparison-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => $role]);
                $created_users[] = $id;
                update_user_meta($id, 'first_name', 'MCP Comparison ' . $i . ' ' . wp_generate_uuid4());
            }
            $selected_users = $created_users;
            $user_filter = static function () use ($selected_users) {
                return array_map(static function ($id) {
                    $user = get_userdata($id);
                    return (object) ['ID' => $id, 'user_login' => $user->user_login, 'user_email' => $user->user_email, 'user_nicename' => $user->user_nicename, 'display_name' => $user->display_name];
                }, $selected_users);
            };
            add_filter('presspermit_sync_posts_to_users_user_results', $user_filter, PHP_INT_MAX);
            $collect = static function ($id) use (&$created_posts) { $created_posts[] = $id; };
            add_action('presspermit_sync_posts_to_users_added_post', $collect);
            try {
                $scope = permissions_mcp_call('get-sync-settings', ['post_type' => 'mcp_audit']);
                permissions_mcp_check(is_wp_error(permissions_mcp_call('update-sync-settings', ['post_type' => 'mcp_audit', 'revision' => $scope['revision'],
                    'changes' => (object) ['sync_posts_to_users_existing' => true, 'sync_posts_to_users_role' => ['subscriber']]])), 'Unset native match fields/status were silently enabled.');
                $scope = permissions_mcp_call('get-sync-settings', ['post_type' => 'post']);
                $ok(permissions_mcp_call('update-sync-settings', ['post_type' => 'post', 'revision' => $scope['revision'], 'changes' => (object) [
                    'sync_posts_to_users_existing' => true, 'sync_posts_to_users_new' => false, 'sync_posts_to_users_role' => ['subscriber'],
                    'sync_posts_to_users_post_field' => 'post_title', 'sync_posts_to_users_user_field' => 'first_name', 'sync_posts_to_users_quantity' => 1,
                    'sync_posts_to_users_status' => 'draft', 'sync_posts_to_users_post_parent' => 0]]), 'Native title and metadata configuration');
                $global = permissions_mcp_call('get-sync-settings');
                $ok(permissions_mcp_call('update-sync-settings', ['revision' => $global['revision'], 'changes' => (object) ['sync_posts_to_users' => true]]), 'Enable configured native sync');
                $plan = $ok(permissions_mcp_call('get-sync-plan', ['post_type' => 'post']), 'Filtered native sync plan');
                permissions_mcp_check(2 === $plan['user_count'], 'Plan did not include the actual filtered native user set.');
                $deny_user = static function ($caps, $cap, $actor, $args) use ($selected_users) {
                    return 'edit_user' === $cap && (int) ($args[0] ?? 0) === (int) $selected_users[1] ? ['do_not_allow'] : $caps;
                };
                add_filter('map_meta_cap', $deny_user, PHP_INT_MAX, 4);
                try {
                    permissions_mcp_check(is_wp_error(permissions_mcp_call('get-sync-plan', ['post_type' => 'post'])), 'Filtered unauthorized user escaped sync preflight.');
                } finally {
                    remove_filter('map_meta_cap', $deny_user, PHP_INT_MAX);
                }
                $first = $ok(permissions_mcp_call('run-sync', ['post_type' => 'post', 'revision' => $plan['revision']]), 'Native multi-user metadata sync');
                permissions_mcp_check(2 === $first['created'], 'Native metadata query did not synchronize both selected users.');
                foreach ($selected_users as $id) {
                    $posts = get_posts(['post_type' => 'post', 'post_status' => 'draft', 'author' => $id]);
                    permissions_mcp_check(1 === count($posts) && get_user_meta($id, 'first_name', true) === $posts[0]->post_title, 'Native selected match field did not round-trip.');
                }
                $plan = permissions_mcp_call('get-sync-plan', ['post_type' => 'post']);
                $again = $ok(permissions_mcp_call('run-sync', ['post_type' => 'post', 'revision' => $plan['revision']]), 'Repeated native title sync');
                permissions_mcp_check(0 === $again['created'], 'Post Title matching used the slug and created duplicates.');
                foreach ($selected_users as $selected_id) { update_user_meta($selected_id, 'first_name', get_user_meta($selected_id, 'first_name', true) . ' Quantity'); }
                $quantity = $ok(permissions_mcp_call('get-sync-settings', ['post_type' => 'post']), 'Quantity settings');
                $ok(permissions_mcp_call('update-sync-settings', ['post_type' => 'post', 'revision' => $quantity['revision'], 'changes' => ['sync_posts_to_users_quantity' => 2]]), 'Quantity setup');
                $quantity_plan = $ok(permissions_mcp_call('get-sync-plan', ['post_type' => 'post']), 'Quantity plan');
                $quantity_first = $ok(permissions_mcp_call('run-sync', ['post_type' => 'post', 'revision' => $quantity_plan['revision']]), 'Quantity synchronization');
                permissions_mcp_check(4 === $quantity_first['created'], 'Quantity synchronization did not create two posts per selected user.');
                $quantity_plan = $ok(permissions_mcp_call('get-sync-plan', ['post_type' => 'post']), 'Repeated quantity plan');
                $quantity_again = $ok(permissions_mcp_call('run-sync', ['post_type' => 'post', 'revision' => $quantity_plan['revision']]), 'Repeated quantity synchronization');
                permissions_mcp_check(0 === $quantity_again['created'], 'Repeated quantity synchronization created duplicate posts.');
                $quantity = permissions_mcp_call('get-sync-settings', ['post_type' => 'post']);
                $ok(permissions_mcp_call('update-sync-settings', ['post_type' => 'post', 'revision' => $quantity['revision'], 'changes' => ['sync_posts_to_users_quantity' => 1]]), 'Restore quantity');
                $old_name = get_user_meta($selected_users[0], 'first_name', true);
                update_user_meta($selected_users[0], 'first_name', 'Changed ' . $old_name);
                permissions_mcp_check(is_wp_error(permissions_mcp_call('run-sync', ['post_type' => 'post', 'revision' => $plan['revision']])), 'Changed match metadata accepted a stale revision.');
                $plan = permissions_mcp_call('get-sync-plan', ['post_type' => 'post']);
                $change_author = static function ($data) { $data['post_author'] = get_current_user_id(); return $data; };
                add_filter('wp_insert_post_data', $change_author, 99999);
                try {
                    permissions_mcp_check(is_wp_error(permissions_mcp_call('run-sync', ['post_type' => 'post', 'revision' => $plan['revision']])), 'A final native filter changed the approved synchronization author.');
                } finally {
                    remove_filter('wp_insert_post_data', $change_author, 99999);
                }
                update_user_meta($selected_users[0], 'first_name', $old_name);
            } finally {
                remove_filter('presspermit_sync_posts_to_users_user_results', $user_filter, PHP_INT_MAX);
                remove_action('presspermit_sync_posts_to_users_added_post', $collect);
            }
        }
        if (getenv('CAPABILITIES_MCP_SOURCE')) {
            require_once getenv('CAPABILITIES_MCP_SOURCE') . '/includes/application-passwords.php';
            $admin = wp_get_current_user();
            $scope_snapshot = [];
            foreach (['cme_capabilities_application_password_capabilities', pp_capabilities_application_password_option_name()] as $key) {
                $scope_snapshot[$key] = get_option($key, null);
            }
            $credential = WP_Application_Passwords::create_new_application_password($admin->ID, ['name' => 'Permissions isolated comparison']);
            permissions_mcp_check(!is_wp_error($credential), 'Native application-password fixture failed.');
            $entry = $credential[1];
            unset($credential);
            $scoper = PP_Capabilities_Application_Password_Capabilities::instance();
            try {
                update_option('cme_capabilities_application_password_capabilities', 1);
                do_action('application_password_did_authenticate', $admin, $entry);
                $key = pp_capabilities_application_password_storage_key($admin->ID, $entry['uuid']);
                foreach (['pp_manage_settings' => ['get-settings', []], 'pp_manage_teaser' => ['get-teaser', []],
                    'pp_manage_permissions' => ['get-extra-roles', ['agent_type' => 'user', 'agent_id' => $subject_id]],
                    'edit_others_posts' => ['get-editor-options', ['post_type' => 'mcp_audit']]] as $denied => $call) {
                    update_option(pp_capabilities_application_password_option_name(), [$key => ['user_id' => $admin->ID, 'uuid' => $entry['uuid'], 'denied' => [$denied => true]]]);
                    permissions_mcp_check(!current_user_can($denied), 'Native credential scope did not deny the capability.');
                    permissions_mcp_check(is_wp_error(permissions_mcp_call($call[0], $call[1])), 'Adapter bypassed a real application-password restriction.');
                    permissions_mcp_check(get_current_user_id() === $admin->ID, 'Scope test changed the authenticated identity.');
                }
            } finally {
                remove_filter('user_has_cap', [$scoper, 'filter_user_capabilities'], PHP_INT_MAX);
                remove_action('application_password_did_authenticate', [$scoper, 'capture_application_password'], 10);
                WP_Application_Passwords::delete_application_password($admin->ID, $entry['uuid']);
                foreach ($scope_snapshot as $key => $value) {
                    null === $value ? delete_option($key) : update_option($key, $value);
                }
            }
        }
    } finally {
        foreach (array_unique($created_posts) as $id) {
            wp_delete_post($id, true);
        }
        foreach ($created_users as $id) {
            wp_delete_user($id);
        }
        foreach ($saved as $name => $value) {
            $value['exists'] ? update_option('presspermit_' . $name, $value['value']) : delete_option('presspermit_' . $name);
        }
        unregister_post_type('mcp_audit');
        presspermit()->refreshOptions();
    }
}
permissions_mcp_comparison_cases($group_id, $user_id, $post_id);

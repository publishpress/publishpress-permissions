<?php
/** Run on an isolated permissions_mcp_* WordPress database using wp eval-file. */
use WP\MCP\Domain\Tools\McpTool;

if (!defined('ABSPATH') || !defined('DB_NAME') || 0 !== strpos(DB_NAME, 'permissions_mcp_')) {
    throw new RuntimeException('Use an isolated Permissions MCP test database.');
}
require_once getenv('MCP_ADAPTER_TEST_BOOTSTRAP');
wp_set_current_user((int) get_user_by('login', 'mcpadmin')->ID);
global $assertions;
$assertions = 0;
function permissions_mcp_check($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function permissions_mcp_call($name, $input = [])
{
    $ability = wp_get_ability('publishpress-permissions/' . $name);
    permissions_mcp_check(null !== $ability, 'Missing registered action: ' . $name);
    $tool = McpTool::fromAbility($ability);
    permissions_mcp_check(!is_wp_error($tool), 'Adapter could not create action: ' . $name);
    return $tool->execute($input);
}
$group_id = 0;
$user_id = wp_insert_user(['user_login' => 'mcp-subject-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
$option = get_option('presspermit_strip_private_caption', null);
$original_types = get_option('presspermit_enabled_post_types', null);
$post_id = 0;
try {
    $ability = wp_get_ability('publishpress-permissions/get-settings');
    permissions_mcp_check(false === $ability->get_meta()['public'], 'Ability must be private.');
    permissions_mcp_check(false === $ability->get_meta()['show_in_rest'], 'Default REST exposure must be disabled.');
    $settings = permissions_mcp_call('get-settings');
    permissions_mcp_check(!is_wp_error($settings), 'Native settings read failed: ' . (is_wp_error($settings) ? $settings->get_error_message() : ''));
    $patch = ['revision' => $settings['revision'], 'changes' => (object) ['strip_private_caption' => false], 'dry_run' => true];
    $preview = permissions_mcp_call('update-settings', $patch);
    permissions_mcp_check(!is_wp_error($preview), 'Setting preview failed.');
    permissions_mcp_check($option === get_option('presspermit_strip_private_caption', null), 'Preview changed storage.');
    $bad = $patch;
    $bad['changes'] = (object) ['strip_private_caption' => 'false', 'advanced_options' => true];
    permissions_mcp_check(is_wp_error(permissions_mcp_call('update-settings', $bad)), 'String false must be rejected.');
    permissions_mcp_check($option === get_option('presspermit_strip_private_caption', null), 'Malformed patch changed storage.');
    $patch['dry_run'] = false;
    $saved = permissions_mcp_call('update-settings', $patch);
    permissions_mcp_check(!is_wp_error($saved), 'Setting save failed.');
    permissions_mcp_check(false === $saved['settings']->strip_private_caption['value'], 'Boolean round trip failed.');
    permissions_mcp_check('' === get_option('presspermit_strip_private_caption'), 'Native unchecked storage must be empty string.');
    permissions_mcp_check(is_wp_error(permissions_mcp_call('update-settings', $patch)), 'Stale revision accepted.');
    $deny = static function ($caps) { $caps['pp_manage_settings'] = false; return $caps; };
    add_filter('user_has_cap', $deny, PHP_INT_MAX);
    try {
        permissions_mcp_check(is_wp_error(permissions_mcp_call('get-settings')), 'Denied administrator settings capability was bypassed.');
    } finally {
        remove_filter('user_has_cap', $deny, PHP_INT_MAX);
    }
    wp_get_ability('publishpress-permissions/set-content-types');
    register_post_type('mcp_fixture_type', ['public' => true, 'show_ui' => true, 'label' => 'MCP Fixture']);
    $types = permissions_mcp_call('get-content-types', ['scope' => 'post_types']);
    permissions_mcp_check(!is_wp_error($types), 'Native type discovery failed.');
    $type_patch = ['scope' => 'post_types', 'revision' => $types['revision'], 'changes' => [(object) ['name' => 'mcp_fixture_type', 'enabled' => true]]];
    $typed = permissions_mcp_call('set-content-types', $type_patch);
    permissions_mcp_check(!is_wp_error($typed), 'A type registered after ability discovery could not be saved.');
    permissions_mcp_check('1' === presspermit()->getOption('enabled_post_types')['mcp_fixture_type'], 'Native map value did not round trip.');
    $before_map = presspermit()->getOption('enabled_post_types');
    $invalid_type = $type_patch;
    $invalid_type['revision'] = $typed['revision'];
    $invalid_type['changes'] = [(object) ['name' => 'page', 'enabled' => false], (object) ['name' => 'missing_type', 'enabled' => true]];
    permissions_mcp_check(is_wp_error(permissions_mcp_call('set-content-types', $invalid_type)), 'Unknown type accepted in mixed patch.');
    permissions_mcp_check($before_map === presspermit()->getOption('enabled_post_types'), 'Mixed type patch wrote partially.');
    unregister_post_type('mcp_fixture_type');
    $name = 'MCP "quoted" group ' . wp_generate_uuid4();
    $create = permissions_mcp_call('create-group', ['name' => $name, 'description' => 'Native description', 'dry_run' => true]);
    permissions_mcp_check(!is_wp_error($create) && !empty($create['dry_run']), 'Group preview failed.');
    $created = permissions_mcp_call('create-group', ['name' => $name, 'description' => 'Native description']);
    permissions_mcp_check(!is_wp_error($created), 'Group create failed: ' . (is_wp_error($created) ? $created->get_error_message() : ''));
    $group_id = $created['id'];
    permissions_mcp_check($name === $created['name'], 'Native group quoting did not round trip.');
    $members = permissions_mcp_call('update-group-members', ['id' => $group_id, 'revision' => $created['revision'], 'user_ids' => [$user_id], 'action' => 'add']);
    permissions_mcp_check(!is_wp_error($members), 'Native member addition failed.');
    permissions_mcp_check(in_array($user_id, array_column($members['members'], 'user_id'), true), 'Member missing after native read-back.');
    permissions_mcp_check(false === strpos(wp_json_encode($members), 'user_pass'), 'Account internals exposed.');
    $roles = permissions_mcp_call('get-extra-roles', ['agent_type' => 'pp_group', 'agent_id' => $group_id]);
    permissions_mcp_check(!is_wp_error($roles), 'Supplemental-role read failed.');
    $bad_role = permissions_mcp_call('update-extra-roles', ['agent_type' => 'pp_group', 'agent_id' => $group_id,
        'revision' => $roles['revision'], 'changes' => [(object) ['role' => 'administrator', 'enabled' => true]]]);
    permissions_mcp_check(is_wp_error($bad_role), 'Administrator role grant accepted.');
    $normal_role = permissions_mcp_call('update-extra-roles', ['agent_type' => 'pp_group', 'agent_id' => $group_id,
        'revision' => $roles['revision'], 'changes' => [(object) ['role' => 'subscriber:post:page', 'enabled' => true]]]);
    permissions_mcp_check(!is_wp_error($normal_role), 'Valid scoped reading role could not be assigned.');
    permissions_mcp_check(in_array('subscriber:post:page', array_column($normal_role['roles'], 'role'), true), 'Native scoped role read-back differs.');
    $post_id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'MCP permission fixture']);
    $deny_post = static function ($caps, $cap, $actor, $args) use ($post_id) {
        return 'edit_post' === $cap && (int) ($args[0] ?? 0) === (int) $post_id ? ['do_not_allow'] : $caps;
    };
    add_filter('map_meta_cap', $deny_post, PHP_INT_MAX, 4);
    try {
        $role_state = permissions_mcp_call('get-extra-roles', ['agent_type' => 'pp_group', 'agent_id' => $group_id]);
        $denied = permissions_mcp_call('update-extra-roles', ['agent_type' => 'pp_group', 'agent_id' => $group_id,
            'revision' => $role_state['revision'], 'changes' => [(object) ['role' => 'subscriber:post:page', 'enabled' => true]], 'dry_run' => true]);
        permissions_mcp_check(is_wp_error($denied), 'Administrator bypassed a denied content object in a scoped role grant.');
    } finally {
        remove_filter('map_meta_cap', $deny_post, PHP_INT_MAX);
    }
    $scope = ['agent_type' => 'user', 'agent_id' => $user_id, 'source' => 'post', 'object_id' => $post_id, 'operation' => 'read'];
    $permission = permissions_mcp_call('get-permissions', $scope);
    permissions_mcp_check(!is_wp_error($permission), 'Native permission scope could not be read.');
    $set = $scope + ['revision' => $permission['revision'], 'mod_type' => 'additional', 'assign_for' => 'item', 'enabled' => true, 'dry_run' => true];
    $preview_permission = permissions_mcp_call('set-permission', $set);
    permissions_mcp_check(!is_wp_error($preview_permission), 'Native permission preview failed.');
    permissions_mcp_check($permission['revision'] === permissions_mcp_call('get-permissions', $scope)['revision'], 'Permission preview mutated storage.');
    $set['dry_run'] = false;
    $permission_saved = permissions_mcp_call('set-permission', $set);
    permissions_mcp_check(!is_wp_error($permission_saved), 'Native permission save failed.');
    permissions_mcp_check(in_array('additional', array_column($permission_saved['rules'], 'mod_type'), true), 'Permission was not stored natively.');
    $set['revision'] = $permission_saved['revision'];
    $set['enabled'] = false;
    $permission_removed = permissions_mcp_call('set-permission', $set);
    permissions_mcp_check(!is_wp_error($permission_removed) && !$permission_removed['rules'], 'Native permission removal failed.');
    $deny_group = static function ($allowed, $cap, $id) use ($group_id) { return $id === $group_id ? false : $allowed; };
    add_filter('presspermit_user_has_group_cap', $deny_group, PHP_INT_MAX, 3);
    try {
        permissions_mcp_check(is_wp_error(permissions_mcp_call('get-group', ['id' => $group_id])), 'Administrator bypassed native group denial.');
    } finally {
        remove_filter('presspermit_user_has_group_cap', $deny_group, PHP_INT_MAX);
    }
    $features = permissions_mcp_call('list-features');
    permissions_mcp_check(!is_wp_error($features), 'Feature discovery failed.');
    permissions_mcp_check((defined('PRESSPERMIT_PRO_VERSION') ? 'pro' : 'free') === $features['edition'], 'Incorrect active edition.');
    if (defined('PRESSPERMIT_PRO_VERSION') && presspermit()->moduleActive('membership')) {
        $current = permissions_mcp_call('get-group', ['id' => $group_id]);
        $start = gmdate('Y-m-d H:i:s', time() + 86400);
        $end = gmdate('Y-m-d H:i:s', time() + 172800);
        $schedule = permissions_mcp_call('set-group-membership', ['id' => $group_id, 'user_id' => $user_id, 'revision' => $current['revision'],
            'changes' => (object) ['date_limited' => true, 'start_date_gmt' => $start, 'end_date_gmt' => $end, 'status' => 'scheduled']]);
        permissions_mcp_check(!is_wp_error($schedule), 'Pro membership scheduling failed.');
        $members = array_column($schedule['members'], null, 'user_id');
        permissions_mcp_check(true === $members[$user_id]['date_limited'] && $start === $members[$user_id]['start_date_gmt'], 'Native schedule read-back differs.');
        $again = permissions_mcp_call('update-group-members', ['id' => $group_id, 'revision' => $schedule['revision'], 'user_ids' => [$user_id], 'action' => 'add']);
        permissions_mcp_check(!is_wp_error($again) && $again['members'] === $schedule['members'], 'Repeated add erased a native schedule.');
    }
    if (defined('PRESSPERMIT_PRO_VERSION') && presspermit()->moduleActive('circles')) {
        $circle = permissions_mcp_call('get-circles', ['id' => $group_id]);
        permissions_mcp_check(!is_wp_error($circle), 'Pro circle discovery failed.');
        $change = ['id' => $group_id, 'revision' => $circle['revision'], 'changes' => [(object) ['operation' => 'read', 'post_type' => 'page', 'enabled' => true]], 'dry_run' => true];
        $preview = permissions_mcp_call('set-circles', $change);
        permissions_mcp_check(!is_wp_error($preview), 'Pro circle preview failed.');
        permissions_mcp_check($circle['revision'] === permissions_mcp_call('get-circles', ['id' => $group_id])['revision'], 'Circle preview wrote storage.');
        $change['dry_run'] = false;
        $saved = permissions_mcp_call('set-circles', $change);
        permissions_mcp_check(!is_wp_error($saved), 'Pro circle save failed.');
        permissions_mcp_check(in_array('page', array_column($saved['circles'], 'post_type'), true), 'Native circle read-back differs.');
        permissions_mcp_check(is_wp_error(permissions_mcp_call('set-circles', $change)), 'Stale circle revision accepted.');
        $change['revision'] = $saved['revision'];
        $change['changes'][0]->enabled = false;
        $removed = permissions_mcp_call('set-circles', $change);
        permissions_mcp_check(!is_wp_error($removed) && !$removed['circles'], 'Circle removal failed.');
    }
    require PRESSPERMIT_ABSPATH . '/tests/mcp/remaining.php';
    require PRESSPERMIT_ABSPATH . '/tests/mcp/comparison.php';
    require PRESSPERMIT_ABSPATH . '/tests/mcp/security-contract.php';
    require PRESSPERMIT_ABSPATH . '/tests/mcp/extended-features.php';
    echo "Active modules: " . implode(", ", array_keys(presspermit()->getActiveModules())) . "\n";
    echo "Permissions MCP integration passed: $assertions assertions.\n";
} finally {
    if ($post_id) {
        wp_delete_post($post_id, true);
    }
    if (null === $original_types) {
        delete_option('presspermit_enabled_post_types');
    } else {
        update_option('presspermit_enabled_post_types', $original_types);
    }
    if ($group_id) {
        presspermit()->groups()->deleteGroup($group_id, 'pp_group');
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($user_id);
    if (null === $option) {
        delete_option('presspermit_strip_private_caption');
    } else {
        update_option('presspermit_strip_private_caption', $option);
    }
    presspermit()->refreshOptions();
}

<?php
/** Run in an isolated admin-context WordPress request with the released Adapter. */
if (!defined('DB_NAME') || !str_starts_with(DB_NAME, 'permissions_mcp_') || !is_admin()) {
    throw new RuntimeException('Use an isolated Permissions MCP admin-context fixture.');
}
require_once getenv('MCP_ADAPTER_TEST_BOOTSTRAP');
wp_set_current_user((int) get_user_by('login', 'mcpadmin')->ID);
foreach (['presspermit_user_has_group_cap', 'presspermit_can_set_exceptions'] as $hook) {
    $count = 0;
    foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $callbacks) {
        foreach ($callbacks as $callback) {
            $method = $callback['function'];
            if (is_array($method) && (is_a($method[0], 'PublishPress\Permissions\Collab\RoleAdmin', true)
                || is_a($method[0], 'PublishPress\Permissions\CollabHooksAdmin', true))) {
                ++$count;
            }
        }
    }
    if ($count !== 1) {
        throw new RuntimeException('Native domain authorization must run exactly once in admin context.');
    }
}
$id = presspermit()->groups()->createGroup(['group_name' => 'Isolated authorization fixture']);
if (!$id) {
    throw new RuntimeException('Could not create authorization fixture.');
}
$native_user = presspermit()->getUser();
$original = $native_user->except;
try {
    $ability = wp_get_ability('publishpress-permissions/get-group');
    $tool = \WP\MCP\Domain\Tools\McpTool::fromAbility($ability);
    if (is_wp_error($tool) || is_wp_error($tool->execute(['id' => (int) $id]))) {
        throw new RuntimeException('Authorized admin-context group read failed.');
    }
    $native_user->except['manage_pp_group'] = ['pp_group' => ['' => ['exclude' => ['pp_group' => ['' => [(int) $id]]]]]];
    if (!is_wp_error($tool->execute(['id' => (int) $id]))) {
        throw new RuntimeException('Admin context bypassed native group management restrictions.');
    }
    echo "OK (admin-context single authorization registration and native group denial through released Adapter)\n";
} finally {
    $native_user->except = $original;
    presspermit()->groups()->deleteGroup((int) $id, 'pp_group');
}

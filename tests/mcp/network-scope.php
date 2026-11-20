<?php
/** Real multisite verification of native network-option ownership. */
use WP\MCP\Domain\Tools\McpTool;
if (!defined('ABSPATH') || !str_starts_with(DB_NAME, 'permissions_mcp_') || !is_multisite()) {
    throw new RuntimeException('Use an isolated multisite Permissions database.');
}
require_once getenv('MCP_ADAPTER_TEST_BOOTSTRAP');
wp_set_current_user((int) get_user_by('login', 'mcpadmin')->ID);
$native_before = get_site_option('presspermit_netwide_groups', null);
try {
    update_site_option('presspermit_netwide_groups', true);
    delete_option('presspermit_netwide_groups');
    presspermit()->refreshOptions();
    if (!\PublishPress\Permissions\Mcp\Access::networkGroups()) {
        throw new RuntimeException('Native network-wide scope was read from a local option.');
    }
    $cases = [
        'update-group-members' => ['id' => 1, 'revision' => str_repeat('0', 64), 'user_ids' => [1], 'action' => 'add'],
        'get-group-deletion' => ['id' => 1],
    ];
    if (defined('PRESSPERMIT_PRO_VERSION')) {
        $cases['set-group-membership'] = ['id' => 1, 'user_id' => 1, 'revision' => str_repeat('0', 64), 'changes' => (object) ['status' => 'active']];
    }
    foreach ($cases as $name => $args) {
        $ability = wp_get_ability('publishpress-permissions/' . $name);
        $tool = McpTool::fromAbility($ability);
        $result = is_wp_error($tool) ? $tool : $tool->execute($args);
        if (!is_wp_error($result)) {
            throw new RuntimeException('Network-wide mutation escaped its documented native-admin boundary.');
        }
    }
    echo 'OK (' . count($cases) . " real multisite network-group guards)\n";
} finally {
    null === $native_before ? delete_site_option('presspermit_netwide_groups') : update_site_option('presspermit_netwide_groups', $native_before);
}

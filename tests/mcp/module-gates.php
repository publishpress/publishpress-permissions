<?php
/** Verify native module switches across real request boundaries. */
use WP\MCP\Domain\Tools\McpTool;
if (!defined('ABSPATH') || !str_starts_with(DB_NAME, 'permissions_mcp_')) {
    throw new RuntimeException('Use an isolated MCP test database.');
}
require_once getenv('MCP_ADAPTER_TEST_BOOTSTRAP');
wp_set_current_user((int) get_user_by('login', 'mcpadmin')->ID);
$gate_call = static function ($name, $input = []) {
    $ability = wp_get_ability('publishpress-permissions/' . $name);
    $tool = McpTool::fromAbility($ability);
    return is_wp_error($tool) ? $tool : $tool->execute($input);
};
if ('disable' === getenv('MCP_GATE_CASE')) {
    $state = $gate_call('get-modules');
    $changes = [];
    foreach ($state['modules'] as $module) {
        if (!$module['required']) {
            $changes[] = ['module' => $module['module'], 'enabled' => false];
        }
    }
    $saved = $gate_call('update-modules', ['revision' => $state['revision'], 'changes' => $changes]);
    if (is_wp_error($saved)) {
        throw new RuntimeException('Native module gate fixture could not be saved.');
    }
    echo "OK (native module deactivation request)\n";
    return;
}
$cases = ['get-teaser' => [], 'get-content-visibility' => []];
if (defined('PRESSPERMIT_PRO_VERSION')) {
    $cases += ['get-sync-settings' => [], 'get-file-maintenance' => ['operation' => 'rebuild'], 'get-circles' => ['id' => 1]];
} elseif (wp_has_ability('publishpress-permissions/get-sync-settings') || wp_has_ability('publishpress-permissions/set-circles')) {
    throw new RuntimeException('Pro actions were registered in Free.');
}
foreach ($cases as $name => $input) {
    $result = $gate_call($name, $input);
    if (!is_wp_error($result) || 'presspermit_mcp_forbidden' !== $result->get_error_code()) {
        throw new RuntimeException('Inactive native module was not denied: ' . $name);
    }
}
echo 'OK (' . count($cases) . " native inactive-module Adapter gates)\n";

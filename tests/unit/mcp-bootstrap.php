<?php
/** Optional Abilities API must not become a minimum WordPress dependency. */
define('ABSPATH', __DIR__ . '/');
$hooks = [];
function add_action($name, $callback) { global $hooks; $hooks[$name][] = $callback; }
require dirname(__DIR__, 2) . '/classes/PublishPress/Permissions/Mcp/bootstrap.php';
\PublishPress\Permissions\Mcp\Registry::register();
\PublishPress\Permissions\Mcp\Registry::category();
if (count($hooks) !== 2) {
    throw new RuntimeException('Unexpected optional integration bootstrap side effects.');
}
echo "OK (MCP missing-API bootstrap)\n";

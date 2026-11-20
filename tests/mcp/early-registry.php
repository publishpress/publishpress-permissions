<?php
/** Run the existing Adapter integration checks with registration before native init. */
if (PHP_SAPI !== 'cli' || !isset($argv[1])) {
    exit("Usage: php early-registry.php /isolated/wp-load.php\n");
}
$GLOBALS['wp_filter']['init'][0][] = ['function' => static function () {
    wp_get_abilities();
}, 'accepted_args' => 0];
require $argv[1];
require __DIR__ . '/integration.php';

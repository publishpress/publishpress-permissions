<?php
/**
 * Check the real entry point without loading WordPress or plugin dependencies.
 * Run separately: php tests/unit/minimum-version-bootstrap.php [WordPress version]
 */

define('ABSPATH', __DIR__ . '/../../');
$wp_version = isset($argv[1]) ? $argv[1] : '5.5';
$reached_wordpress = false;

function get_option($name)
{
    // The first WordPress lookup after both version guards, before any vendor includes.
    throw new RuntimeException('Reached WordPress options');
}

$included_before = get_included_files();
$entry_point = realpath(ABSPATH . 'press-permit-core.php');
try {
    require $entry_point;
} catch (RuntimeException $exception) {
    if ('Reached WordPress options' !== $exception->getMessage()) {
        throw $exception;
    }
    $reached_wordpress = true;
}

$supported = version_compare(PHP_VERSION, '8.2', '>=') && version_compare($wp_version, '5.5', '>=');
if ($supported !== $reached_wordpress) {
    throw new RuntimeException('Unexpected bootstrap behavior for PHP ' . PHP_VERSION . ' / WordPress ' . $wp_version);
}

if ([$entry_point] !== array_values(array_diff(get_included_files(), $included_before))) {
    throw new RuntimeException('Version validation loaded a plugin dependency');
}

if (defined('PRESSPERMIT_CLASSPATH') || function_exists('presspermit_load')) {
    throw new RuntimeException('The plugin initialized before version validation finished');
}

echo 'OK: PHP ' . PHP_VERSION . ' / WordPress ' . $wp_version . ($supported ? ' passes guards' : ' returns early') . "\n";

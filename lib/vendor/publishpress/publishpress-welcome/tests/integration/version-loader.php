<?php

$hooks = [];
$actions = [];
$assertions = 0;

function add_action($hook, $callback, $priority = 10, $acceptedArgs = 1)
{
    global $hooks;
    $hooks[$hook][$priority][] = $callback;
}

function do_action($hook)
{
    global $actions;
    $actions[] = $hook;
}

$assertTrue = function ($value, $message) use (&$assertions) {
    $assertions++;
    if (! $value) {
        throw new RuntimeException($message);
    }
};

require dirname(__DIR__, 2) . '/src/include.php';

$assertTrue(isset($hooks['plugins_loaded'][-200]), 'The library copy must register at plugins_loaded priority -200.');
$assertTrue(isset($hooks['plugins_loaded'][-190]), 'The newest copy must initialize at plugins_loaded priority -190.');
$assertTrue(! class_exists('PublishPress\\Welcome\\WelcomeController', false), 'Runtime classes must not load before version selection.');

ksort($hooks['plugins_loaded']);
foreach ($hooks['plugins_loaded'] as $callbacks) {
    foreach ($callbacks as $callback) {
        call_user_func($callback);
    }
}

$assertTrue(class_exists('PublishPress\\Welcome\\WelcomeController'), 'The selected copy must register its runtime autoloader.');
$assertTrue(defined('PUBLISHPRESS_WELCOME_VERSION'), 'The selected copy must define its runtime version.');
$assertTrue('1.0.0.1' === PUBLISHPRESS_WELCOME_VERSION, 'The initialized version must match the package version.');
$assertTrue(in_array('publishpress_welcome_1Dot0Dot0Dot1_initialized', $actions, true), 'The generated initialization action must fire.');

fwrite(STDOUT, sprintf("OK (%d integration assertions)\n", $assertions));

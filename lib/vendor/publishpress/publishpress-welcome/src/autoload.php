<?php

namespace PublishPress\Welcome;

if (! defined('PUBLISHPRESS_WELCOME_VERSION')) {
    define('PUBLISHPRESS_WELCOME_VERSION', '1.0.0.1');
}

if (! defined('PUBLISHPRESS_WELCOME_ROOT_FILE')) {
    define('PUBLISHPRESS_WELCOME_ROOT_FILE', dirname(__DIR__) . '/publishpress-welcome.php');
}

spl_autoload_register(
    function ($className) {
        $prefix = __NAMESPACE__ . '\\';

        if (0 !== strpos($className, $prefix)) {
            return;
        }

        $relativeClass = substr($className, strlen($prefix));
        $file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
);

if (function_exists('load_textdomain') && function_exists('determine_locale')) {
    $translationFile = dirname(__DIR__) . '/languages/publishpress-welcome-' . determine_locale() . '.mo';

    if (is_readable($translationFile)) {
        load_textdomain('publishpress-welcome', $translationFile);
    }
}

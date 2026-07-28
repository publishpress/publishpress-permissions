<?php
/**
 * Plugin Name: PublishPress Permissions Content Visibility
 * Plugin URI:  https://publishpress.com/permissions/
 * Description: Show or hide sections of content based on user access conditions.
 * Author:      PublishPress
 * Author URI:  https://publishpress.com/
 * Version:     0.1.0
 * Text Domain: presspermit-content-visibility
 * Requires at least: 5.5
 * Requires PHP: 7.2.5
 * License:     GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package PublishPress\Permissions
 */

/*
Copyright 2026 PublishPress

This file is part of PublishPress Permissions.

PublishPress Permissions is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

if (!defined('ABSPATH')) {
    exit;
}

if (defined('PRESSPERMIT_CONTENT_VISIBILITY_FILE')) {
    add_action(
        'init',
        function () {
            do_action(
                'presspermit_duplicate_module',
                'content-visibility',
                dirname(plugin_basename(__FILE__))
            );
        }
    );

    return;
}

define('PRESSPERMIT_CONTENT_VISIBILITY_FILE', __FILE__);
define('PRESSPERMIT_CONTENT_VISIBILITY_ABSPATH', __DIR__);
define(
    'PRESSPERMIT_CONTENT_VISIBILITY_CLASSPATH',
    __DIR__ . '/classes/Permissions/ContentVisibility'
);

if (!defined('PRESSPERMIT_VERSION')) {
    return;
}

$presspermit_content_visibility_module_title = 'Content Visibility';

if (presspermit()->registerModule(
    'content-visibility',
    $presspermit_content_visibility_module_title,
    dirname(plugin_basename(__FILE__)),
    PRESSPERMIT_VERSION,
    ['min_pp_version' => '4.8.2']
)) {
    define('PRESSPERMIT_CONTENT_VISIBILITY_VERSION', PRESSPERMIT_VERSION);

    require_once(__DIR__ . '/classes/Permissions/ContentVisibility.php');
    new \PublishPress\Permissions\ContentVisibility();
}

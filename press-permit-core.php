<?php

/**
 * Plugin Name: PublishPress Permissions
 * Plugin URI:  https://publishpress.com/presspermit
 * Description: Advanced yet accessible content permissions. Give users or groups type-specific roles. Enable or block access for specific posts or terms.
 * Version: 4.5.0
 * Author: PublishPress
 * Author URI:  https://publishpress.com/
 * Text Domain: press-permit-core
 * Domain Path: /languages/
 * Requires at least: 5.5
 * Requires PHP: 7.2.5
 *
 * Copyright (c) 2025 PublishPress
 *
 * GNU General Public License, Free Software Foundation <https://www.gnu.org/licenses/gpl-3.0.html>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     PublishPress Permissions
 * @author      PublishPress
 * @copyright   Copyright (c) 2025 PublishPress. All rights reserved.
 * @license		GNU General Public License version 3
 * @link		https://publishpress.com/
 *
 **/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

global $wp_version;

$min_php_version = '7.2.5';
$min_wp_version  = '5.5';

$invalid_php_version = version_compare(phpversion(), $min_php_version, '<');
$invalid_wp_version = version_compare($wp_version, $min_wp_version, '<');

// If the PHP version is not compatible, terminate the plugin execution and show an admin notice.
if (is_admin() && $invalid_php_version) {
    add_action(
        'admin_notices',
        function () use ($min_php_version) {
            if (current_user_can('activate_plugins')) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    'PublishPress Permissions Pro requires PHP version %s or higher.',
                    esc_html($min_php_version)
                );
                echo '</p></div>';
            }
        }
    );
}

// If the WP version is not compatible, terminate the plugin execution and show an admin notice.
if (is_admin() && $invalid_wp_version) {
    add_action(
        'admin_notices',
        function () use ($min_wp_version) {
            if (current_user_can('activate_plugins')) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    'PublishPress Permissions Pro requires WordPress version %s or higher.',
                    esc_html($min_wp_version)
                );
                echo '</p></div>';
            }
        }
    );
}

if ($invalid_php_version || $invalid_wp_version) {
    return;
}

$pro_active = false;

global $presspermit_loaded_by_pro;

$presspermit_loaded_by_pro = strpos(str_replace('\\', '/', __FILE__), 'vendor/publishpress/');

// Detect separate Pro plugin activation, but not self-activation (this file loaded in vendor library by Pro)
if (false === $presspermit_loaded_by_pro) {
    foreach ((array)get_option('active_plugins') as $plugin_file) {
        if (false !== strpos($plugin_file, 'presspermit-pro.php')) {
            $pro_active = true;
            break;
        }
    }

    if (!$pro_active && is_multisite()) {
        foreach (array_keys((array)get_site_option('active_sitewide_plugins')) as $plugin_file) {
            if (false !== strpos($plugin_file, 'presspermit-pro.php')) {
                $pro_active = true;
                break;
            }
        }
    }

    if ($pro_active) {
        add_filter(
            'plugin_row_meta',
            function ($links, $file) {
                if ($file == plugin_basename(__FILE__)) {
                    $links[] = __('<strong>This plugin can be deleted.</strong>', 'press-permit-core');
                }

                return $links;
            },
            10,
            2
        );
        return;
    }
}

if (! defined('PRESSPERMIT_INTERNAL_VENDORPATH')) {
    define('PRESSPERMIT_INTERNAL_VENDORPATH', __DIR__ . '/lib/vendor');
}

if (! $presspermit_loaded_by_pro) {
    $includeFileRelativePath = '/publishpress/instance-protection/include.php';
    if (file_exists(PRESSPERMIT_INTERNAL_VENDORPATH . $includeFileRelativePath)) {
        require_once PRESSPERMIT_INTERNAL_VENDORPATH . $includeFileRelativePath;
    }

    if (class_exists('PublishPressInstanceProtection\\Config')) {
        $pluginCheckerConfig = new PublishPressInstanceProtection\Config();
        $pluginCheckerConfig->pluginSlug    = 'press-permit-core';
        $pluginCheckerConfig->pluginFolder  = 'press-permit-core';
        $pluginCheckerConfig->pluginName    = 'PublishPress Permissions';

        $pluginChecker = new PublishPressInstanceProtection\InstanceChecker($pluginCheckerConfig);
    }

    if (
        ! class_exists('ComposerAutoloaderInitPressPermit')
        && file_exists(PRESSPERMIT_INTERNAL_VENDORPATH . '/autoload.php')
    ) {
        require_once PRESSPERMIT_INTERNAL_VENDORPATH . '/autoload.php';
    }

    include_once PRESSPERMIT_INTERNAL_VENDORPATH . '/publishpress/wordpress-version-notices/src/include.php';
}

if ((!defined('PRESSPERMIT_FILE') && !$pro_active) || $presspermit_loaded_by_pro) {
    define('PRESSPERMIT_FILE', __FILE__);
    define('PRESSPERMIT_ABSPATH', __DIR__);
    define('PRESSPERMIT_CLASSPATH', __DIR__ . '/classes/PublishPress/Permissions');

    if (!defined('PRESSPERMIT_CLASSPATH_COMMON')) {
        define('PRESSPERMIT_CLASSPATH_COMMON', __DIR__ . '/classes/PressShack');
    }

    define('PRESSPERMIT_DB_VERSION', '2.0.1');

    if (!defined('PRESSPERMIT_DEBUG')) {
        define('PRESSPERMIT_DEBUG', false);
    }

    include_once(constant('PRESSPERMIT_DEBUG') ? __DIR__ . '/library/debug.php' : __DIR__ . '/library/debug_shell.php');

    if (!function_exists('presspermit_err')) {
        function presspermit_err($err_slug, $args = [])
        {
            if (is_admin()) {
                require_once(PRESSPERMIT_CLASSPATH . '/ErrorNotice.php');
                return new \PublishPress\Permissions\ErrorNotice($err_slug, $args);
            }
        }
    }

    function presspermit_load()
    {
        global $presspermit_loaded_by_pro;

        $presspermit_loaded_by_pro = strpos(str_replace('\\', '/', __FILE__), 'vendor/publishpress/');

        if (!function_exists('presspermit')) {
            require_once(__DIR__ . '/functions.php');
        }

        // Critical errors that prevent initialization
        if ((defined('PPC_FOLDER') && defined('PPC_BASENAME') && function_exists('ppc_deactivate') && presspermit_err('pp_core_active'))
            || (defined('PP_VERSION') && function_exists('pp_get_otype_option') && presspermit_err('pp_legacy_active'))  // Press Permit 1.x (circa 2012) active
            || (defined('SCOPER_VERSION') && function_exists('rs_get_user') && presspermit_err('rs_active') && ! is_admin())
            || (constant('PRESSPERMIT_DEBUG') && is_admin() && presspermit_editing_plugin()) // avoid lockout in case of erroneous plugin edit via wp-admin
        ) {
            return;
        }

        global $pagenow;

        if (is_admin() && isset($pagenow) && ('customize.php' == $pagenow)) {
            return;
        }

        define('PRESSPERMIT_VERSION', '4.5.0');

        if (!defined('PRESSPERMIT_READ_PUBLIC_CAP')) {
            define('PRESSPERMIT_READ_PUBLIC_CAP', 'read');
        }

        if (!$presspermit_loaded_by_pro) {
            require_once(__DIR__ . '/includes/Core.php');
            new \PublishPress\Permissions\Core();

            if (is_admin()) {
                require_once(__DIR__ . '/includes/CoreAdmin.php');
                new \PublishPress\Permissions\CoreAdmin();
            }
        }

        if (!defined('PRESSPERMIT_LEGACY_HOOKS')) {
            define('PRESSPERMIT_LEGACY_HOOKS', false);
        }

        // Non-critical intialization errors (may prevent integration with module or external plugin, but continue with initialization)
        if (defined('RVY_VERSION') && !defined('REVISIONARY_VERSION')) {
            presspermit_err('old_extension', ['module_title' => 'Revisionary', 'min_version' => '1.3.5']);
        }

        require_once(PRESSPERMIT_CLASSPATH_COMMON . '/LibArray.php');
        class_alias('\PressShack\LibArray', '\PublishPress\Arr');
        class_alias('\PressShack\LibArray', '\PublishPress\Permissions\Arr');
        class_alias('\PressShack\LibArray', '\PublishPress\Permissions\DB\Arr');
        class_alias('\PressShack\LibArray', '\PublishPress\Permissions\UI\Arr');
        class_alias('\PressShack\LibArray', '\PublishPress\Permissions\UI\Dashboard\Arr');

        require_once(PRESSPERMIT_CLASSPATH_COMMON . '/LibWP.php');
        class_alias('\PressShack\LibWP', '\PublishPress\PWP');
        class_alias('\PressShack\LibWP', '\PublishPress\Permissions\PWP');
        class_alias('\PressShack\LibWP', '\PublishPress\Permissions\DB\PWP');
        class_alias('\PressShack\LibWP', '\PublishPress\Permissions\UI\PWP');
        class_alias('\PressShack\LibWP', '\PublishPress\Permissions\UI\Dashboard\PWP');
        class_alias('\PressShack\LibWP', '\PublishPress\Permissions\UI\Handlers\PWP');

        require_once(PRESSPERMIT_CLASSPATH . '/API.php');

        require_once(__DIR__ . '/db-config.php');

        require_once(__DIR__ . '/classes/PublishPress/Permissions.php');

        presspermit();
    }

    // negative priority to precede any default WP action handlers
    if ($presspermit_loaded_by_pro) {
        presspermit_load();    // Pro support
    } else {
        add_action('plugins_loaded', 'presspermit_load', -10);
    }

    register_activation_hook(
        __FILE__,
        function () {
            require_once(__DIR__ . '/activation.php');
        }
    );
}

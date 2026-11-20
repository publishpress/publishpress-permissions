<?php
/** Optional Permissions integration: no transport or Hub dependency. */
defined('ABSPATH') || exit;

require_once __DIR__ . '/Input.php';
require_once __DIR__ . '/Access.php';
require_once __DIR__ . '/Groups.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/ContentTypes.php';
require_once __DIR__ . '/Roles.php';
require_once __DIR__ . '/Delegation.php';
require_once __DIR__ . '/Exceptions.php';
require_once __DIR__ . '/NativeOptions.php';
require_once __DIR__ . '/EditorOptions.php';
require_once __DIR__ . '/Teaser.php';
require_once __DIR__ . '/Modules.php';
require_once __DIR__ . '/GroupDeletion.php';
require_once __DIR__ . '/RoleUsage.php';
require_once __DIR__ . '/Visibility.php';
require_once __DIR__ . '/Bulk.php';
require_once __DIR__ . '/Registry.php';

add_action('wp_abilities_api_categories_init', ['PublishPress\Permissions\Mcp\Registry', 'category']);
add_action('wp_abilities_api_init', ['PublishPress\Permissions\Mcp\Registry', 'register']);

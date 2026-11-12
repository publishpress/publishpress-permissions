<?php
defined('ABSPATH') || exit;

require(__DIR__ . '/db-config.php');

// get last database version
if ( ! $ver = get_option('presspermitpro_version') ) {
    if ( ! $ver = get_option('presspermit_version') ) {
        $ver = get_option('pp_c_version');
    }
}

$db_ver = (is_array($ver) && isset( $ver['db_version'] ) ) ? $ver['db_version'] : '';
require_once(__DIR__ . '/classes/PublishPress/Permissions/DB/DatabaseSetup.php');
new \PublishPress\Permissions\DB\DatabaseSetup($db_ver);

if (!class_exists('PublishPress\Permissions\PluginUpdated')) {
	require_once(__DIR__ . '/classes/PublishPress/Permissions/PluginUpdated.php');
}

\PublishPress\Permissions\PluginUpdated::syncWordPressRoles();

update_option('presspermit_activation', true);
update_option('presspermit_refresh_role_usage', true);

do_action('presspermit_activate');
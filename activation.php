<?php
require(__DIR__ . '/db-config.php');

// get last database version
if ( ! $ver = get_option('presspermitpro_version') ) {
    if ( ! $ver = get_option('presspermit_version') ) {
        $ver = get_option('pp_c_version');
    }
}

$db_ver = (is_array($ver) && isset( $ver['db_version'] ) ) ? $ver['db_version'] : '';

// Enroll only genuine first installations. Existing sites can still open the
// welcome page manually, but are never redirected or shown onboarding notices.
if (empty($ver)) {
    update_option('presspermit_welcome_enrolled', 1, false);
    update_option('presspermit_welcome_redirect_pending', 1, false);
}

require_once(__DIR__ . '/classes/PublishPress/Permissions/DB/DatabaseSetup.php');
new \PublishPress\Permissions\DB\DatabaseSetup($db_ver);

if (!class_exists('PublishPress\Permissions\PluginUpdated')) {
	require_once(__DIR__ . '/classes/PublishPress/Permissions/PluginUpdated.php');
}

\PublishPress\Permissions\PluginUpdated::syncWordPressRoles();

update_option('presspermit_activation', true);
update_option('presspermit_refresh_role_usage', true);

do_action('presspermit_activate');

<?php
namespace PublishPress\Permissions\Mcp;

defined('ABSPATH') || exit;

/** Native authorization and private revisions for Permissions actions. */
class Access
{
    public static function postIds(array $types, $parent = null, $include_trash = true)
    {
        require_once PRESSPERMIT_CLASSPATH . '/DB/Permissions.php';
        $ids = \PublishPress\Permissions\DB\Permissions::getPostScope($types, $parent, $include_trash);
        if (is_wp_error($ids)) {
            throw new \RuntimeException('Native permission scope could not be read.');
        }
        return $ids;
    }
    public static function termIds($taxonomy, $parent = null)
    {
        require_once PRESSPERMIT_CLASSPATH . '/DB/Permissions.php';
        $ids = \PublishPress\Permissions\DB\Permissions::getTermScope($taxonomy, $parent);
        if (is_wp_error($ids)) {
            throw new \RuntimeException('Native taxonomy scope could not be read.');
        }
        return $ids;
    }
    public static function requireCaps(array $caps)
    {
        foreach ($caps as $cap) {
            if (!current_user_can($cap)) {
                return self::denied();
            }
        }
        return true;
    }

    public static function denied()
    {
        return new \WP_Error('presspermit_mcp_forbidden', __('You cannot perform this Permissions operation.', 'press-permit-core'));
    }

    public static function revision($value)
    {
        return hash_hmac('sha256', wp_json_encode($value), wp_salt('nonce'));
    }

    public static function stale($expected, $current)
    {
        return hash_equals($expected, $current) ? true : new \WP_Error('presspermit_mcp_stale_revision', __('Permissions changed. Read the current values before saving.', 'press-permit-core'));
    }

    public static function networkGroups()
    {
        return is_multisite() && (get_site_option('presspermit_netwide_groups') || presspermit()->getOption('netwide_groups'));
    }
    public static function group($id, $cap = 'pp_edit_groups')
    {
        if ((self::networkGroups() && !current_user_can('manage_network_users'))
            || is_wp_error(self::requireCaps([$cap])) || !presspermit()->groups()->userCan($cap, $id, 'pp_group')
            || !apply_filters('presspermit_user_has_group_cap', true, $cap, $id, 'pp_group')) {
            return self::denied();
        }
        return true;
    }

    public static function subject($id, $type)
    {
        if (is_wp_error(self::requireCaps(['pp_manage_permissions']))) {
            return self::denied();
        }
        if ('user' === $type) {
            return get_userdata($id) && current_user_can('list_users') && current_user_can('edit_user', $id) ? true : self::denied();
        }
        $allowed = self::group($id);
        if (is_wp_error($allowed)) {
            return $allowed;
        }
        $groups = presspermit()->groups()->getGroups('pp_group', ['ids' => [$id]]);
        return isset($groups[$id]) ? true : self::denied();
    }

    public static function roleAllowed($role)
    {
        $caps = presspermit()->getRoleCaps($role);
        if (!$caps) {
            return false;
        }
        $blocked = ['manage_options', 'edit_users', 'promote_users', 'create_users', 'delete_users', 'remove_users',
            'activate_plugins', 'deactivate_plugins', 'install_plugins', 'update_plugins', 'delete_plugins', 'edit_plugins',
            'switch_themes', 'edit_themes', 'edit_theme_options', 'install_themes', 'update_themes', 'delete_themes',
            'update_core', 'edit_files', 'unfiltered_html', 'unfiltered_upload', 'manage_network', 'manage_network_options',
            'manage_network_users', 'manage_sites', 'setup_network', 'pp_unfiltered', 'pp_administer_content',
            'pp_manage_permissions', 'pp_manage_settings', 'pp_manage_teaser', 'pp_create_groups', 'pp_edit_groups', 'pp_delete_groups', 'pp_manage_members'];
        $blocked = array_merge($blocked, ['administrator', 'resume_plugins', 'resume_themes', 'create_sites', 'delete_sites', 'upgrade_network',
            'add_users', 'promote_user', 'edit_user', 'delete_user', 'activate_plugin', 'deactivate_plugin', 'resume_plugin', 'resume_theme',
            'upload_plugins', 'upload_themes', 'install_languages', 'update_languages', 'edit_css', 'customize', 'delete_site',
            'update_php', 'update_https', 'manage_privacy_options', 'export_others_personal_data', 'erase_others_personal_data',
            'pp_assign_roles', 'pp_assign_bulk_roles', 'pp_manage_own_groups', 'pp_create_network_groups', 'pp_manage_network_members']);
        $base = strtok($role, ':');
        if (in_array($base, $blocked, true) || str_starts_with($base, 'manage_capabilities') || str_starts_with($base, 'manage_network_')) {
            return false;
        }
        foreach ($caps as $key => $value) {
            // Native typecasting also returns alias => primitive-capability maps.
            $cap = is_string($value) ? $value : $key;
            if ((!is_int($key) && !$value) || !is_string($cap)) {
                continue;
            }
            if ((!is_int($key) && (in_array($key, $blocked, true) || str_starts_with($key, 'manage_capabilities') || str_starts_with($key, 'manage_network_')))
                || in_array($cap, $blocked, true) || 0 === strpos($cap, 'manage_capabilities')
                || 0 === strpos($cap, 'manage_network_') || !current_user_can($cap)) {
                return false;
            }
        }
        return true;
    }

    public static function unconfirmed()
    {
        return new \WP_Error('presspermit_mcp_save_unconfirmed', __('The native operation could not be confirmed. Read the current state before retrying.', 'press-permit-core'));
    }
}

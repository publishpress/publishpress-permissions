<?php

namespace PublishPress\Permissions\UI\Handlers;

class Settings
{
    public function __construct()
    {
        check_admin_referer('pp-update-options');

        if (!current_user_can('pp_manage_settings')) {
            wp_die(esc_html(PWP::__wp('Cheatin&#8217; uh?')));
        }

        $args = apply_filters('presspermit_handle_submission_args', []); // todo: is this used?

        if (PWP::is_POST('pp_submission_topic', 'options')) {

            if (isset($_POST['presspermit_submit'])) {
                $this->updateOptions($args);
                do_action('presspermit_handle_submission', 'update', $args);

            } elseif (isset($_POST['presspermit_defaults'])) {
                $this->defaultOptions($args);
                do_action('presspermit_handle_submission', 'default', $args);
            }

            presspermit()->refreshOptions();

        } elseif (isset($_POST['pp_role_usage_defaults'])) {
            delete_option('presspermit_role_usage');
            presspermit()->refreshOptions();
        }
    }

    private function updateOptions($args)
    {
        $this->updatePageOptions($args);

        global $wpdb;

        // Disable WP autoload of presspermit options because we have our own cache

        // Direct query of options table on plugin settings update, for bulk update
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            "UPDATE $wpdb->options SET autoload = 'no' WHERE option_name LIKE 'presspermit_%'"
            . " AND option_name NOT LIKE '%_version' AND option_name NOT IN ('presspermit_custom_conditions_post_status')"
        );
    }

    private function defaultOptions($args)
    {
        check_admin_referer('pp-update-options');

        $pp = presspermit();

        $reviewed_options = (!empty($_POST['all_options']))
            ? array_map('sanitize_key', explode(',', sanitize_text_field($_POST['all_options'])))
            : [];

        if (!$reviewed_options) {
            return;
        }

        $default_prefix = apply_filters('presspermit_options_apply_default_prefix', '', $args);

        $all_otype_options = (!empty($_POST['all_otype_options']))
            ? array_map('sanitize_key', explode(',', sanitize_text_field($_POST['all_otype_options'])))
            : [];

        if ($all_otype_options) {
            $reviewed_options = array_merge(
                $reviewed_options,
                $all_otype_options
            );
        }

        foreach ($reviewed_options as $option_name) {
            $pp->deleteOption($default_prefix . $option_name, $args);
        }

        require_once(PRESSPERMIT_CLASSPATH . '/PluginUpdated.php');
        \PublishPress\Permissions\PluginUpdated::deactivateModules(['current_deactivations' => []]);

        $tab = (!PWP::empty_POST('pp_tab')) ? "&pp_tab={" . PWP::POST_key('pp_tab') . "}" : '';
        wp_redirect(admin_url("admin.php?page=presspermit-settings$tab&presspermit_submit_redirect=1"));
        exit;
    }

    private function updatePageOptions($args)
    {
        check_admin_referer('pp-update-options');

        $pp = presspermit();

        $all_options = (!empty($_POST['all_options']))
            ? array_map('sanitize_text_field', explode(',', sanitize_text_field($_POST['all_options'])))
            : [];

        if (!$all_options) {
            return;
        }

        do_action('presspermit_update_options', $args);

        $default_prefix = apply_filters('presspermit_options_apply_default_prefix', '', $args);

        foreach (array_map('\PressShack\LibWP::sanitizeEntry', $all_options) as $option_basename) {
            if (!apply_filters('presspermit_custom_sanitize_setting', false, $option_basename, $default_prefix, $args)) {
                if (isset($_POST[$option_basename]) && is_array($_POST[$option_basename])) {
                    $pp->updateOption($default_prefix . $option_basename, array_map('sanitize_text_field', $_POST[$option_basename]), $args);
                } else {
                    $val = (isset($_POST[$option_basename])) ? trim(sanitize_text_field($_POST[$option_basename])) : '';

                    $pp->updateOption($default_prefix . $option_basename, $val, $args);
                }
            }
        }

        $all_otype_options = (!empty($_POST['all_otype_options']))
            ? array_map('sanitize_text_field', explode(',', sanitize_text_field($_POST['all_otype_options'])))
            : [];

        if ($all_otype_options) {
            foreach (array_map('\PressShack\LibWP::sanitizeEntry', $all_otype_options) as $option_basename) {
                // Check for custom sanitization first
                if (!apply_filters('presspermit_custom_sanitize_setting', false, $option_basename, $default_prefix, $args)) {
                    // Check if this is a simple checkbox option (pp_enable_metabox_* or pp_include_permission_screen_*)
                    if (strpos($option_basename, 'pp_enable_metabox_') === 0 || strpos($option_basename, 'pp_include_permission_screen_') === 0) {
                        // Handle as simple checkbox value
                        $val = (isset($_POST[$option_basename])) ? '1' : '0';
                        $pp->updateOption($default_prefix . $option_basename, $val, $args);
                    } else {
                        // Handle as array option (original behavior for enabled_post_types, enabled_taxonomies, etc.)
                        // support stored default values (to apply to any post type which does not have an explicit setting)
                        if (isset($_POST[$option_basename]) && is_array($_POST[$option_basename]) && isset($_POST[$option_basename][0])) {
                            $_POST[$option_basename][''] = PWP::sanitizeEntry(sanitize_text_field($_POST[$option_basename][0]));
                            unset($_POST[$option_basename][0]);
                        }

                        $value = (isset($pp->default_options[$option_basename])) ? $pp->default_options[$option_basename] : [];

                        // retain setting for any types which were previously enabled for filtering but are currently not registered
                        if ($current = $pp->getOption($option_basename)) {
                            $value = array_merge($value, $current);
                        }

                        if (isset($_POST[$option_basename]) && is_array($_POST[$option_basename])) {
                            $posted_val = array_map('sanitize_text_field', $_POST[$option_basename]);
                            $value = array_merge($value, array_map('\PressShack\LibWP::sanitizeEntry', $posted_val));
                        }

                        $pp->updateOption($default_prefix . $option_basename, $value, $args);
                    }
                }
            }
        }

        if (!empty($_POST['post_blockage_priority'])) {  // once this is switched on manually, don't ever default-disable it again
            if (get_option('presspermit_legacy_exception_handling')) {
                delete_option('presspermit_legacy_exception_handling');
            }
        }

        // =============== Module Activation ================
        if (!$_deactivated = $pp->getOption('deactivated_modules')) {
            $_deactivated = [];
        }

        $deactivated = $_deactivated;

        // add deactivations (unchecked from Active list)

        $reviewed_modules = (!empty($_POST['presspermit_reviewed_modules']))
            ? array_fill_keys(array_map('sanitize_key', explode(',', sanitize_text_field($_POST['presspermit_reviewed_modules']))), (object) [])
            : [];

        if ($reviewed_modules) {
            $deactivated = array_merge(
                $deactivated,
                array_diff_key(
                    $reviewed_modules,
                    !empty($_POST['presspermit_active_modules'])
                    ? array_filter((array) array_map('sanitize_key', (array) $_POST['presspermit_active_modules']))
                    : []
                )
            );
        }

        // remove deactivations (checked in Inactive list)
        if (!empty($_POST['presspermit_deactivated_modules'])) {
            $deactivated = array_diff_key(
                $deactivated,
                array_map('sanitize_key', (array) $_POST['presspermit_deactivated_modules'])
            );
        }

        if ($_deactivated !== $deactivated) {
            foreach (array_diff_key($deactivated, $_deactivated) as $module_name => $module) {
                do_action($module_name . '_deactivate');
            }

            foreach (array_diff_key($_deactivated, $deactivated) as $module_name => $module) {
                if (in_array($module_name, ['presspermit-file-access'])) {
                    update_option(str_replace('-', '_', $module_name) . '_deactivate', 1);
                }
            }

            $pp->updateOption('deactivated_modules', $deactivated);
            $tab = (!PWP::empty_POST('pp_tab')) ? "&pp_tab={" . PWP::POST_key('pp_tab') . "}" : '';
            wp_redirect(admin_url("admin.php?page=presspermit-settings$tab&presspermit_submit_redirect=1"));
            exit;
        }
        // =====================================================
    }
}

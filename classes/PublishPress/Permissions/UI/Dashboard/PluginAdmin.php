<?php

namespace PublishPress\Permissions\UI\Dashboard;

\defined('ABSPATH') || exit;

class PluginAdmin
{
    public function __construct()
    {
        add_filter('plugin_action_links_' . plugin_basename(PRESSPERMIT_FILE), [$this, 'fltPluginActionLinks'], 10, 2);

        if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION') && !version_compare(PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION, '3.8.0', '>=')) {
            self::authorsVersionNotice();
        }
    }

    // Deprecated in 4.0.35
    public function actCorePluginStatus($plugin_file, $plugin_data, $status)
    {
        $message = '';
        if (!$this->typeUsageStored()) {
            if (get_post_types(['public' => true, '_builtin' => false])) {
                $do_message = true;
            }
        }

        if (!empty($do_message)) {
            $wp_list_table = _get_list_table('WP_Plugins_List_Table');

            echo '<tr class="plugin-update-tr"><td colspan="' . esc_attr($wp_list_table->get_column_count())
                . '" class="plugin-update"><div class="update-message">';

            $url = admin_url('admin.php?page=presspermit-settings');

            printf(
                esc_html__('PublishPress Permissions needs directions. Please go to %1$sPermissions > Settings%2$s and indicate which Post Types and Taxonomies should be filtered.', 'press-permit-core'),
                '<a href="' . esc_url($url) . '">',
                '</a>'
            );

            if (presspermit()->isPro() && (is_network_admin() || !is_multisite())) {
                $key = presspermit()->getOption('edd_key');
                $keyStatus = isset($key['license_status']) ? $key['license_status'] : 'invalid';

                if (in_array($keyStatus, ['invalid', 'expired'])) {
                    require_once PRESSPERMIT_CLASSPATH . '/PluginStatus.php';

                    echo '<br /><br />';

                    if ('expired' == $keyStatus) {
                        \PublishPress\Permissions\PluginStatus::renewalMsg();
                    } else {
                        \PublishPress\Permissions\PluginStatus::buyMsg();
                    }
                }
            }

            echo '</div></td></tr>';
        }
    }

    // adds an Options link next to Deactivate, Edit in Plugins listing
    public function fltPluginActionLinks($links, $file)
    {
        if ($file == plugin_basename(PRESSPERMIT_FILE)) {
            if (!is_network_admin()) {
                $links[] = "<a href='admin.php?page=presspermit-settings'>" . esc_html(PWP::__wp('Settings')) . "</a>";
            }
        }

        return $links;
    }

    private function typeUsageStored()
    {
        $types_vals = get_option('presspermit_enabled_post_types');
        if (!is_array($types_vals)) {
            $txs_val = get_option('presspermit_enabled_taxonomies');
        }

        return is_array($types_vals) || is_array($txs_val);
    }

    public static function authorsVersionNotice($args = [])
    {
        $id = (!empty($args['ignore_dismissal'])) ? '' : 'authors-integration-version';

        presspermit()->admin()->notice(
            esc_html__('Please upgrade PublishPress Authors to version 3.8.0 or later for Permissions integration.', 'press-permit-core'),
            $id
        );
    }
}

<?php

namespace PublishPress\Permissions\UI;

use PublishPress\Permissions\Factory;

class SettingsTabInstall
{
    public function __construct()
    {
        // This class is called in pro version only
        if (!defined('PRESSPERMIT_PRO_VERSION')) return;
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 90);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_install_options_pre_ui', [$this, 'optionsPreUI']);
        add_action('presspermit_install_options_ui', [$this, 'optionsUI']);
    }

    public function optionTabs($tabs)
    {
        $tabs['install'] = esc_html__('License', 'press-permit-core');
        return $tabs;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'key' => esc_html__('Account', 'press-permit-core'),
            'version' => esc_html__('Version', 'press-permit-core'),
            'help' => PWP::__wp('Help'),
        ];

        $key = 'install';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'key' => esc_html__('settings', 'press-permit-core'),
            'help' => esc_html__('settings', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'key' => ['edd_key'],
            'help' => ['no_option'],
        ];

        $key = 'install';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsPreUI()
    {
        if (PWP::is_REQUEST('presspermit_refresh_done') && !!PWP::empty_POST()) : ?>
            <div id="message" class="updated">
                <p>
                    <strong><?php esc_html_e('Version info was refreshed.', 'press-permit-core'); ?>&nbsp;</strong>
                </p>
            </div>
            <?php endif;
    }

    public function optionsUI()
    {
        $pp = presspermit();

        $ui = SettingsAdmin::instance();
        $tab = 'install';

        $use_network_admin = $this->useNetworkUpdates();
        $suppress_updates = $use_network_admin && !is_super_admin();

        global $activated;

        $opt_val = is_multisite() ? get_site_option('pp_support_key') : get_option('pp_support_key');

        if (!is_array($opt_val) || count($opt_val) < 2) {
            $activated = false;
            $expired = false;
            $key = '';
        } else {
            $activated = (1 == $opt_val[0]);
            $expired = (-1 == $opt_val[0]);
            $key = $opt_val[1];
        }

        if (isset($opt_val['expire_date_gmt'])) {
            $expire_days = intval((strtotime($opt_val['expire_date_gmt']) - time()) / 86400);
            if ($expire_days < 0) {
                $expired = true;
            }
        }

        $msg = '';
        $site = preg_replace('#^https?://#i', '', untrailingslashit(site_url('')));
        $url = add_query_arg(
            [
                'pkg' => 'press-permit-pro',
                'site' => $site,
            ],
            'https://publishpress.com/pricing/'
        );

        $key_string = (is_array($opt_val) && count($opt_val) > 1) ? $opt_val[1] : '';
        $expire_date = (is_array($opt_val) && isset($opt_val['expire_date_gmt'])) ? $opt_val['expire_date_gmt'] : '';

        $modern_pro_version = get_option('presspermitpro_version');

        if ($msg || $modern_pro_version || $key_string) :
            $section = 'key'; // --- UPDATE KEY SECTION ---
            if (!empty($ui->form_options[$tab][$section]) && !$suppress_updates) : ?>
                <tr>
                    <th scope="row">
                        <span style="font-weight:bold;vertical-align:top"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></span>
                    </th>

                    <td>
                        <h4><?php esc_html_e('Further details for your installation:', 'press-permit-core'); ?></h4>
                        <ul id="presspermit-pro-install-details" class="pp-bullet-list">

                            <?php if (($expired && $expire_days < 365) || get_option('presspermitpro_version') || $activated): ?>
                                <li>
                                    <?php
                                    if ($expired) {
                                        if ($expire_days < 365) {
                                            printf(
                                                esc_html__('Your presspermit.com key has expired, but a <a href="%s">PublishPress renewal</a> discount may be available.', 'press-permit-core'),
                                                esc_url($url)
                                            );
                                        }
                                    } elseif ($modern_pro_version) {
                                        echo esc_html__('Permissions Pro was previously active. You are now running the free version, with fewer features.', 'press-permit-core');
                                    } elseif ($activated) {
                                        $url = "https://publishpress.com/contact/?pp_topic=presspermit-migration&presspermit_account=$key_string";

                                        printf(
                                            esc_html__('A presspermit.com key appears to be active. <a href="%s" target="_blank">Contact us</a> for assistance in migrating your account to publishpress.com.', 'press-permit-core'),
                                            esc_url($url)
                                        );
                                    }
                                    ?>
                                </li>
                            <?php endif; ?>

                            <?php if ($key_string): ?>
                                <li>
                                    <?php
                                    if ($expire_date)
                                        echo esc_html(sprintf(__("Original presspermit.com support key hash: <strong>%s</strong> (expires %s)"), esc_html($key_string), esc_html($expire_date)));
                                    else
                                        echo esc_html(sprintf(__("Original presspermit.com support key hash: <strong>%s</strong>"), esc_html($key_string), esc_html($expire_date)));
                                    ?>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </td>
                </tr>
        <?php
            endif; // any options accessable in this section
        endif; // any status messages to display

        $section = 'version'; // --- VERSION SECTION ---

        ?>
        <tr>
            <th scope="row">
                <span style="font-weight:bold;vertical-align:top"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></span>
            </th>

            <td>

                <?php
                $update_info = [];

                if (!$suppress_updates) {
                    $wp_plugin_updates = get_site_transient('update_plugins');
                    if (
                        $wp_plugin_updates && isset($wp_plugin_updates->response[plugin_basename(PRESSPERMIT_FILE)])
                        && !empty($wp_plugin_updates->response[plugin_basename(PRESSPERMIT_FILE)]->new_version)
                        && version_compare($wp_plugin_updates->response[plugin_basename(PRESSPERMIT_FILE)]->new_version, PRESSPERMIT_VERSION, '>')
                    ) {
                        $slug = 'press-permit-core';

                        $_url = "plugin-install.php?tab=plugin-information&plugin=$slug&section=changelog&TB_iframe=true&width=600&height=800";
                        $info_url = ($use_network_admin) ? network_admin_url($_url) : admin_url($_url);

                        $do_info_link = true;
                    }
                }

                ?>
                <p>
                    <?php
                    if (!empty($do_info_link)) {
                        printf(
                            esc_html__('PublishPress Permissions Version: %1$s %2$s', 'press-permit-core'),

                            esc_html(PRESSPERMIT_VERSION),

                            "<span class='update-message'> &bull; <a href='" . esc_url($info_url) . "' class='thickbox'>"
                                . sprintf(esc_html__('%s&nbsp;details', 'press-permit-core'), esc_html($wp_plugin_updates->response[plugin_basename(PRESSPERMIT_FILE)]->new_version))
                                . '</a></span>'
                        );
                    } else {
                        printf(
                            esc_html__('PublishPress Permissions Version: %1$s %2$s', 'press-permit-core'),
                            esc_html(PRESSPERMIT_VERSION),
                            ''
                        );
                    }
                    ?>
                </p>

                <?php
                if ($ver_history = get_option('ppperm_version_history')) : ?>
                    <br />
                    <div>
                        <?php
                        if ($ver_history = (array) json_decode($ver_history)) :
                            $ver_history = array_reverse($ver_history, true);
                        ?>
                            <div class="agp-vtight"><?php esc_html_e('Installation History', 'press-permit-core'); ?></div>
                            <?php
                            echo '<textarea id="pp_version_history" name="pp_version_history" rows="5" cols="30" style="width: 250px; height: 85px" readonly="readonly">';

                            for ($i = 0; $i < count($ver_history); $i++) {
                                if ($i) {
                                    echo "\r\n";
                                }

                                $ver_data = current($ver_history);
                                next($ver_history);

                                if (!is_object($ver_data) || empty($ver_data->version)) {
                                    continue;
                                }

                                $version = (!empty($ver_data->isPro)) ? $ver_data->version . ' Pro' : $ver_data->version;

                                if (!empty($ver_data->date)) {
                                    echo esc_html($version) . ' : ' .  esc_html($ver_data->date);
                                } else {
                                    printf(
                                        esc_html__('%s (previous install)', 'press-permit-core'),
                                        esc_html($version)
                                    );
                                }
                            }

                            echo '</textarea>';
                            ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <br />
                <p>
                    <?php

                    global $wp_version;
                    printf(esc_html__("WordPress Version: %s", 'press-permit-core'), esc_html($wp_version));
                    ?>
                </p>
                <p>
                    <?php printf(esc_html__("PHP Version: %s", 'press-permit-core'), esc_html(phpversion())); ?>
                </p>
            </td>
        </tr>
<?php
    }

    private function useNetworkUpdates()
    {
        return (is_multisite() && (is_network_admin() || PWP::isNetworkActivated() || PWP::isMuPlugin()));
    }
}

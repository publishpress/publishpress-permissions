<?php

namespace PublishPress\Permissions\UI;

class Settings
{
    public function __construct()
    {
        // called by Dashboard\DashboardFilters::actMenuHandler

        @load_plugin_textdomain('press-permit-core-hints', false, dirname(plugin_basename(PRESSPERMIT_FILE)) . '/languages');

        add_action('admin_footer', [$this, 'markActiveSubmenu'], 20);

        require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsAdmin.php');

        require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsTabModules.php');
        new SettingsTabModules();

        require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsTabCore.php');
        new SettingsTabCore();

        require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsTabIntegrations.php');
        new SettingsTabIntegrations();

        if (!presspermit()->isPro()) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsTabMembership.php');
            new SettingsTabMembership();

            require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsTabFileAccess.php');
            new SettingsTabFileAccess();

            require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsTabUserPosts.php');
            new SettingsTabUserPosts();
        }

        require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsTabAdvanced.php');
        new SettingsTabAdvanced();

        require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsTabInstall.php');
        new SettingsTabInstall();

        // enqueue JS for footer
        global $wp_scripts;
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-settings', PRESSPERMIT_URLPATH . "/common/js/settings{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION, true);
        $wp_scripts->in_footer[] = 'presspermit-settings';  // otherwise it will not be printed in footer  todo: review

        $vars = [
            'displayHints' => presspermit()->getOption('display_hints'),
            'forceDisplayHints' => presspermit()->getOption('force_display_hints'),
            'hintImg' => plugins_url('', PRESSPERMIT_FILE) . "/common/img/comment-grey-bubble.png"
        ];
        wp_localize_script('presspermit-settings', 'ppCoreSettings', $vars);

        if (presspermit()->isPro()) {
            wp_enqueue_script('presspermit-pro-settings', plugins_url('', PRESSPERMIT_PRO_FILE) . "/includes-pro/settings-pro{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_PRO_VERSION, true);
            $wp_scripts->in_footer[] = 'presspermit-pro-settings';  // otherwise it will not be printed in footer  todo: review
        }

        if (!current_user_can('pp_manage_settings'))
            wp_die(esc_html(PWP::__wp('Cheatin&#8217; uh?')));

        do_action('presspermit_options_ui');

        $ui = SettingsAdmin::instance();

        $ui->all_options = [];

        $ui->tab_captions = apply_filters('presspermit_option_tabs', []);
        $ui->tab_badges = apply_filters('presspermit_option_tab_badges', []); // Add support for tab badges
        $ui->section_captions = apply_filters('presspermit_section_captions', []);
        $ui->option_captions = apply_filters('presspermit_option_captions', []);
        $ui->form_options = apply_filters('presspermit_option_sections', []);

        $ui->display_hints = presspermit()->getOption('display_hints');
        $is_force_display_hints = presspermit()->getOption('force_display_hints') ? 'force-display-hints' : '';

        if ($_hidden = apply_filters('presspermit_hide_options', [])) {
            $hidden = [];
            foreach (array_keys($_hidden) as $option_name) {
                if (!is_array($_hidden[$option_name]) && strlen($option_name) > 3)
                    $hidden[] = substr($option_name, 3);
            }

            foreach (array_keys($ui->form_options) as $tab) {
                foreach (array_keys($ui->form_options[$tab]) as $section)
                    $ui->form_options[$tab][$section] = array_diff($ui->form_options[$tab][$section], $hidden);
            }
        } ?>
        <div class="pressshack-admin-wrapper wrap" id="pp-permissions-wrapper">
            <?php
            echo '<form id="pp_settings_form" action="" method="post" class="' . esc_attr($is_force_display_hints) . '">';
            wp_nonce_field('pp-update-options');

            do_action('presspermit_options_form');
            ?>
            <header>
                <?php PluginPage::icon(); ?>
                <h1>
                    <?php
                    echo esc_html(apply_filters('presspermit_options_form_title', __('Permissions Settings', 'press-permit-core')));
                    ?>
                </h1>

                <?php

                if ($subheading = apply_filters('presspermit_options_form_subheading', '')) {
                    echo esc_html($subheading);
                }

                $class_selected = "nav-tab nav-tab-active";
                $class_unselected = "nav-tab";
                ?>


            </header>

            <?php
            $default_tab = PWP::REQUEST_key('pp_tab');

            if (!isset($ui->tab_captions[$default_tab])) {
                $default_tab = 'core';
            }

            $default_tab = apply_filters('presspermit_options_default_tab', $default_tab);

            // todo: prevent line breaks in these links
            echo "<ul class='nav-tab-wrapper' style='margin-bottom:-0.1em;border-bottom:unset;'>";

            foreach ($ui->tab_captions as $tab => $caption) {
                if (!empty($ui->form_options[$tab])) {
                    $class = ($default_tab == $tab) ? $class_selected : $class_unselected;  // todo: return to last tab

                    // Check if this tab has a badge
                    $badge_html = '';
                    if (!empty($ui->tab_badges[$tab])) {
                        $badge = $ui->tab_badges[$tab];
                        $badge_text = isset($badge['text']) ? esc_html($badge['text']) : 'PRO';
                        $badge_color = isset($badge['color']) ? esc_attr($badge['color']) : '#8B5CF6';
                        $badge_bg_color = isset($badge['bg_color']) ? esc_attr($badge['bg_color']) : '#8B5CF6';
                        $badge_class = isset($badge['class']) ? esc_attr($badge['class']) : '';
                        
                        $badge_html = sprintf(
                            '<span class="pp-tab-badge %s" style="background: %s; color: white; font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 10px; margin-left: 6px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">%s</span>',
                            $badge_class,
                            $badge_bg_color,
                            $badge_text
                        );
                    }

                    echo "<li class='" . esc_attr($class) . "'><a href='#pp-" . esc_attr($tab) . "'>"
                        . esc_html($ui->tab_captions[$tab]) . wp_kses_post($badge_html) . '</a>';
                        
                    if (('integrations' == $tab) && !empty($ui->available_integrations)) :?>
                            <span class="pp-integrations <?php echo (defined('PRESSPERMIT_PRO_VERSION')) ? 'pp-integrations-active' : 'pp-integrations-missing';?> count-<?php echo intval(count($ui->available_integrations));?>"><span class="plugin-count"><?php echo intval(count($ui->available_integrations));?></span></span>
                    <?php endif;
                        
                    echo '</li>';
                }
            }
            echo '</ul>';
            echo '<div class="pp-group-wrapper" style="display: flex;width: 100%;flex-wrap: wrap;">';
            echo '<div class="pp-options-wrapper" style="flex-basis: calc(99% - 270px);">';
            $table_class = 'form-table pp-form-table pp-options-table';

            if (PWP::is_REQUEST('presspermit_submit') || PWP::is_REQUEST('presspermit_submit_redirect')) :
            ?>
                <div id="message" class="updated">
                    <p>
                        <strong><?php esc_html_e('All settings were updated.', 'press-permit-core'); ?>&nbsp;</strong>
                    </p>
                </div>
            <?php
            elseif (PWP::is_REQUEST('presspermit_defaults')) :
            ?>
                <div id="message" class="updated">
                    <p>
                        <strong><?php esc_html_e('All settings were reset to defaults.', 'press-permit-core'); ?>&nbsp;</strong>
                    </p>
                </div>
            <?php
            endif;

            foreach (array_keys($ui->tab_captions) as $tab) {
                $display = ($default_tab == $tab) ? '' : 'display:none';
                echo "<div id='pp-" . esc_attr($tab) . "' style='clear:both;margin:0;" . esc_attr($display) . "' class='pp-options'>";

                do_action("presspermit_" . esc_attr($tab) . "_options_pre_ui");

                echo "<table class='" . esc_attr($table_class) . "' id='pp-" . esc_attr($tab) . "_table'>";

                do_action("presspermit_" . esc_attr($tab) . "_options_ui");

                echo '</table></div>';
            }
            echo "<input type='hidden' name='all_options' value='" . esc_attr(implode(',', $ui->all_options)) . "' />";
            echo "<input type='hidden' name='all_otype_options' value='" . esc_attr(implode(',', $ui->all_otype_options)) . "' />";
            echo "<input type='hidden' name='pp_submission_topic' value='options' />";
            ?>
            <span class="submit pp-submit" style="border:none;display:block!important;">
                <input type="submit" name="presspermit_submit" class="button-primary" value="<?php esc_attr_e('Save Changes', 'press-permit-core'); ?>" />
                <input type="hidden" name="pp_tab"
                    value="<?php if ($pp_tab = PWP::REQUEST_key('pp_tab')) echo esc_attr($pp_tab); ?>" />
            </span>
            <?php
            echo '</div>'; // pp-options-wrapper
            if (!presspermit()->isPro()) {
                require_once(PRESSPERMIT_CLASSPATH . '/UI/PromoBanner.php');
                $promoBanner = new PromoBanner(array(
                    'pluginDocsUrl' => 'https://publishpress.com/docs-category/presspermit/',
                    'pluginSupportUrl' => 'https://wordpress.org/plugins/press-permit-core/',
                    'features'         => [
                        'Create personal pages for each user',
                        'Manage Media Library access',
                        'Control your content teasers',
                        'Create your own Privacy Statuses',
                        'Remove PublishPress ads and branding',
                        'Priority, personal support',
                    ]
                ));

                $promoBanner->setTitle(esc_html__('Upgrade to Permissions Pro', 'press-permit-core'));
                $promoBanner->setSupportTitle(esc_html__('Need Permissions Support?', 'press-permit-core'));

                $promoBanner->displayBanner();
            }
            echo '</div>'; // pp-group-wrapper

            $ui->filterNetworkOptions();
            ?>
            </form>
            <p style='clear:both'></p>

            <?php
            presspermit()->admin()->publishpressFooter();
            ?>
        </div>

<?php
    }

    public static function pluginInfoURL($plugin_slug)
    {
        return self_admin_url("plugin-install.php?tab=plugin-information&plugin=$plugin_slug&TB_iframe=true&width=640&height=678");
    }

    function markActiveSubmenu() {
        if (('presspermit-settings' == presspermitPluginPage()) && PWP::is_REQUEST('pp_tab', 'sync_posts')) :
        ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function ($) {
                    $('#adminmenu li.toplevel_page_presspermit-groups ul.wp-submenu li').removeClass('current');
                    $('#adminmenu li.toplevel_page_presspermit-groups ul.wp-submenu li a[href="admin.php?page=presspermit-sync"]').parent().addClass('current');
                });
                /* ]]> */
            </script>
        <?php endif;
    }
}

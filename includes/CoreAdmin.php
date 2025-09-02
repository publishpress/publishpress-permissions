<?php

namespace PublishPress\Permissions;

class CoreAdmin
{
    function __construct()
    {
        add_action('presspermit_permissions_menu', [$this, 'actAdminMenuPromos'], 12, 2);
        add_action('presspermit_menu_handler', [$this, 'menuHandler']);

        add_action('presspermit_admin_menu', [$this, 'actAdminMenu'], 999);

        add_action('admin_enqueue_scripts', function () {
            if (presspermitPluginPage()) {
                wp_enqueue_style('presspermit-settings-free', plugins_url('', PRESSPERMIT_FILE) . '/includes/css/settings.css', [], PRESSPERMIT_VERSION);
            }

            if (in_array(presspermitPluginPage(), ['presspermit-statuses', 'presspermit-visibility-statuses', 'presspermit-sync', 'presspermit-posts-teaser'])) {
                wp_enqueue_style('presspermit-admin-promo', plugins_url('', PRESSPERMIT_FILE) . '/includes/promo/admin-core.css', [], PRESSPERMIT_VERSION, 'all');
            }
        });

        add_action('admin_print_scripts', [$this, 'setUpgradeMenuLink'], 50);

        add_filter(\PPVersionNotices\Module\TopNotice\Module::SETTINGS_FILTER, function ($settings) {
            $settings['press-permit-core'] = [
                'message' => esc_html__("You're using PublishPress Permissions Free. The Pro version has more features and support. %sUpgrade to Pro%s", 'press-permit-core'),
                'link'    => 'https://publishpress.com/links/permissions-banner',
                'screens' => [
                    ['base' => 'toplevel_page_presspermit-groups'],
                    ['base' => 'permissions_page_presspermit-group-new'],
                    ['base' => 'permissions_page_presspermit-users'],
                    ['base' => 'permissions_page_presspermit-settings'],
                ]
            ];

            return $settings;
        });

        add_action('presspermit_modules_ui', [$this, 'actProModulesUI'], 10, 2);

        add_filter(
            "presspermit_unavailable_modules",
            function ($modules) {
                return array_merge(
                    $modules,
                    [
                        'presspermit-circles',
                        'presspermit-compatibility',
                        'presspermit-file-access',
                        'presspermit-membership',
                        'presspermit-sync',
                        'presspermit-status-control',
                        'presspermit-teaser'
                    ]
                );
            }
        );
    }

    function actAdminMenuPromos($pp_options_menu, $handler)
    {
        // Disable custom status promos until PublishPress Statuses and compatible version of Permissions Pro are released

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /*
        add_submenu_page(
            $pp_options_menu, 
            esc_html__('Workflow Statuses', 'press-permit-core'), 
            esc_html__('Workflow Statuses', 'press-permit-core'), 
            'read', 
            'presspermit-statuses', 
            $handler
        );

        add_submenu_page(
            $pp_options_menu, 
            esc_html__('Visibility Statuses', 'press-permit-core'), 
            esc_html__('Visibility Statuses', 'press-permit-core'), 
            'read', 
            'presspermit-visibility-statuses', 
            $handler
        );
        */

        add_submenu_page(
            $pp_options_menu,
            esc_html__('User Pages', 'press-permit-core'),
            esc_html__('User Pages', 'press-permit-core'),
            'read',
            'presspermit-sync',
            $handler
        );

        add_submenu_page(
            $pp_options_menu,
            esc_html__('Teaser', 'press-permit-core'),
            esc_html__('Teaser', 'press-permit-core'),
            'read',
            'presspermit-posts-teaser',
            $handler
        );
    }

    function menuHandler($pp_page)
    {
        if (in_array($pp_page, ['presspermit-statuses', 'presspermit-visibility-statuses', 'presspermit-sync', 'presspermit-posts-teaser'], true)) {
            $slug = str_replace('presspermit-', '', $pp_page);
            require_once(PRESSPERMIT_ABSPATH . "/includes/promo/{$slug}-promo.php");
        }
    }

    function actAdminMenu()
    {
        $pp_cred_menu = presspermit()->admin()->getMenuParams('permits');

        add_submenu_page(
            $pp_cred_menu,
            esc_html__('Upgrade to Pro', 'press-permit-core'),
            esc_html__('Upgrade to Pro', 'press-permit-core'),
            'read',
            'permissions-pro',
            ['PublishPress\Permissions\UI\Dashboard\DashboardFilters', 'actMenuHandler']
        );
    }

    function setUpgradeMenuLink()
    {
        $url = 'https://publishpress.com/links/permissions-menu';
?>
        <style type="text/css">
            #toplevel_page_presspermit-groups ul li:last-of-type a {
                font-weight: bold !important;
                color: #FEB123 !important;
            }
        </style>

        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function($) {
                $('#toplevel_page_presspermit-groups ul li:last a').attr('href', '<?php echo esc_url($url); ?>').attr('target', '_blank').css('font-weight', 'bold').css('color', '#FEB123');
            });
            /* ]]> */
        </script>
        <?php
    }

    function actProModulesUI($active_module_plugin_slugs, $inactive)
    {
        $pro_modules = array_diff(
            presspermit()->getAvailableModules(['force_all' => true]),
            $active_module_plugin_slugs,
            array_keys($inactive)
        );

        sort($pro_modules);
        if ($pro_modules) :
            $ext_info = presspermit()->admin()->getModuleInfo();
            $learn_more_urls = [
                'circles' => 'https://publishpress.com/knowledge-base/circles-visibility/',
                'collaboration' => 'https://publishpress.com/knowledge-base/content-editing-permissions/',
                'compatibility' => 'https://publishpress.com/knowledge-base/statuses-and-permissions-pro/',
                'teaser' => 'https://publishpress.com/knowledge-base/getting-started-with-teasers/',
                'status-control' => 'https://publishpress.com/knowledge-base/statuses-and-permissions-pro/',
                'file-access' => 'https://publishpress.com/knowledge-base/file-filtering-nginx/',
                'membership' => 'https://publishpress.com/knowledge-base/groups-date-limits/',
                'sync' => 'https://publishpress.com/knowledge-base/how-to-create-a-personal-page-for-each-wordpress-user/'
            ];
            
            // Dynamic icon mapping for different modules
            $module_icons = [
                'circles' => 'dashicons-groups',
                'collaboration' => 'dashicons-edit',
                'compatibility' => 'dashicons-admin-plugins',
                'teaser' => 'dashicons-visibility',
                'status-control' => 'dashicons-admin-settings',
                'file-access' => 'dashicons-media-document',
                'membership' => 'dashicons-calendar-alt',
                'sync' => 'dashicons-admin-users'
            ];
            
            foreach ($pro_modules as $plugin_slug) :
                $slug = str_replace('presspermit-', '', $plugin_slug);
                
                // Get title
                if (!empty($ext_info->title[$slug])) {
                    $title = $ext_info->title[$slug];
                } else {
                    $title = $this->prettySlug($slug);
                }
                
                // Get dynamic icon or fallback to default
                $icon_class = isset($module_icons[$slug]) ? $module_icons[$slug] : 'dashicons-admin-generic';
                ?>
                <div class="pp-integration-card pp-disabled">
                    <span class="pp-integration-icon dashicons <?php echo esc_attr($icon_class); ?>"></span>
                    <div class="pp-integration-content">
                        <h3 class="pp-integration-title" title="<?php echo esc_attr($title); ?>">
                            <?php echo esc_html($title); ?>
                            <span class="pp-badge pp-pro-badge">Pro</span>
                        </h3>

                        <p class="pp-integration-description">
                            <?php if (!empty($ext_info) && isset($ext_info->blurb[$slug])): ?>
                                <span class="pp-ext-info" title="<?php if (isset($ext_info->descript[$slug])) {
                                    echo esc_attr($ext_info->descript[$slug]);
                                }
                                ?>">
                                    <?php echo esc_html($ext_info->blurb[$slug]); ?>
                                </span>
                            <?php endif; ?>
                        </p>

                        <?php if (isset($ext_info->descript[$slug])) : ?>
                            <div class="pp-integration-features" title="<?php echo esc_attr($ext_info->descript[$slug]); ?>">
                                <?php echo esc_html($ext_info->descript[$slug]); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="pp-settings-wrapper">
                        <div class="pp-settings-toggle">
                            <?php $id = "module_pro_{$slug}"; ?>
                            <label class="pp-toggle-switch" for="<?php echo esc_attr($id); ?>">
                                <input type="checkbox" id="<?php echo esc_attr($id); ?>" disabled
                                    name="presspermit_deactivated_modules[<?php echo esc_attr($plugin_slug); ?>]"
                                    value="1" />
                                <span class="pp-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="pp-upgrade-overlay">
                        <h4><?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                        <p>
                            <?php echo esc_html__('Upgrade to Pro for unlock seamless integration.', 'press-permit-core'); ?>
                        </p>
                        <div class="pp-upgrade-buttons" style="flex-direction: row;">
                            <a href="<?php echo esc_url($learn_more_urls[$slug]); ?>" target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                            <a href="<?php echo esc_url(\PublishPress\Permissions\UI\SettingsTabIntegrations::UPGRADE_PRO_URL); ?>" target="_blank" class="pp-upgrade-btn-primary">
                                <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php
            endforeach; 
        endif;
    }

    private function prettySlug($slug)
    {
        $slug = str_replace('presspermit-', '', $slug);
        $slug = str_replace('Pp', 'PP', ucwords(str_replace('-', ' ', $slug)));
        $slug = str_replace('press', 'Press', $slug); // temp workaround
        $slug = str_replace('Wpml', 'WPML', $slug);
        return $slug;
    }
}

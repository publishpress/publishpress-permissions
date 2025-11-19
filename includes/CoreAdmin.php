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

            if (in_array(presspermitPluginPage(), ['presspermit-statuses', 'presspermit-visibility-statuses', 'presspermit-sync'], true)) {
                wp_enqueue_style('presspermit-admin-promo', plugins_url('', PRESSPERMIT_FILE) . '/includes/promo/admin-core.css', [], PRESSPERMIT_VERSION, 'all');
            }
        });

        add_action('admin_print_scripts', [$this, 'setUpgradeMenuLink'], 50);

        add_filter(\PPVersionNotices\Module\TopNotice\Module::SETTINGS_FILTER, function ($settings) {
            $settings['press-permit-core'] = [
                'message' => esc_html__("You're using PublishPress Permissions Free. The Pro version has more features and support. %sUpgrade to Pro%s", 'press-permit-core'),
                'link' => 'https://publishpress.com/links/permissions-banner',
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
                // Allow Teaser module in Free (branch: release-some-teaser-for-free). Keep other Pro modules unavailable.
                $pro_only = [
                    'presspermit-circles',
                    'presspermit-compatibility',
                    'presspermit-file-access',
                    'presspermit-membership',
                    'presspermit-sync',
                    'presspermit-status-control',
                ];

                return array_merge($modules, $pro_only);
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

        // Only show Teaser promo menu if the Teaser module is not active/available.
        if (!presspermit()->moduleActive('teaser')) {
            add_submenu_page(
                $pp_options_menu,
                esc_html__('Teaser', 'press-permit-core'),
                esc_html__('Teaser', 'press-permit-core'),
                'read',
                'presspermit-posts-teaser',
                $handler
            );
        }
    }

    function menuHandler($pp_page)
    {
        if (in_array($pp_page, ['presspermit-statuses', 'presspermit-visibility-statuses', 'presspermit-sync'], true)
            || ('presspermit-posts-teaser' === $pp_page && !presspermit()->moduleActive('teaser'))
        ) {
            $slug = str_replace('presspermit-', '', $pp_page);

            // Only redirect for 'sync'
            if ($slug === 'sync') {
                // Use JavaScript redirect to avoid header issues
                ?>
                <script type="text/javascript">
                    window.location.href = <?php echo wp_json_encode(admin_url('admin.php?page=presspermit-settings&pp_tab=sync_posts')); ?>;
                </script>
                <?php
                exit;
            }

            // For other slugs, include the promo file if it exists
            $promo_file = PRESSPERMIT_ABSPATH . "/includes/promo/{$slug}-promo.php";
            if (file_exists($promo_file)) {
                require_once($promo_file);
            } else {
                // Optionally, handle missing promo file
                wp_die(esc_html__('Promo file not found.', 'press-permit-core'));
            }
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
            jQuery(document).ready(function ($) {
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
                'circles'        => 'dashicons-groups',
                'collaboration'  => 'dashicons-edit',
                'compatibility'  => 'dashicons-admin-plugins',
                'teaser'         => 'dashicons-visibility',
                'status-control' => 'dashicons-admin-settings',
                'file-access'    => 'dashicons-media-document',
                'membership'     => 'dashicons-calendar-alt',
                'sync'           => 'dashicons-admin-users'
            ];

            $module_invitations = [
                'circles'        => 'Upgrade to Pro to access time-limited group membership.',
                'collaboration'  => 'Upgrade to Pro to gain advanced content editing permissions.',
                'compatibility'  => 'Upgrade to Pro to enjoy enhanced statuses and permissions.',
                'teaser'         => 'Upgrade to Pro to get started with teasers.',
                'status-control' => 'Upgrade to Pro to utilize advanced statuses and permissions.',
                'file-access'    => 'Upgrade to Pro to restrict direct file access.',
                'membership'     => 'Upgrade to Pro to limit access based on group membership.',
                'sync'           => 'Upgrade to Pro to create pages on sites each user automatically.'
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
                    <div class="pp-integration-content features-only">
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
                            <?php
                            if (isset($module_invitations[$slug])) {
                                echo esc_html__($module_invitations[$slug], 'press-permit-core');
                            } else {
                                echo esc_html__('Upgrade to Pro to unlock seamless integration.', 'press-permit-core');
                            }
                            ?>
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

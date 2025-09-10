<?php

namespace PublishPress\Permissions\UI;

class SettingsTabIntegrations
{
    public const UPGRADE_PRO_URL = 'https://publishpress.com/links/permissions-integrations/';

    private $ui;    // SettingsAdmin instance

    public function __construct()
    {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 90);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_integrations_options_ui', [$this, 'optionsUI']);

        $this->ui = \PublishPress\Permissions\UI\SettingsAdmin::instance(); 
    }

    public function optionTabs($tabs)
    {
        $tabs['integrations'] = esc_html__('Integrations', 'press-permit-core');
        return $tabs;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'compatibility_packs' => esc_html__('Compatibility Packs', 'press-permit-core'),
        ];

        $key = 'integrations';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'acf_compatibility' => esc_html__('Advanced Custom Fields', 'press-permit-core'),
            'bbpress_compatibility' => esc_html__('bbPress Forums', 'press-permit-core'),
            'beaver_compatibility' => esc_html__('Beaver Builder', 'press-permit-core'),
            'breakdance_compatibility' => esc_html__('Breakdance', 'press-permit-core'),
            'buddypress_compatibility' => esc_html__('BuddyPress', 'press-permit-core'),
            'cms_tree_view_compatibility' => esc_html__('CMS Tree View', 'press-permit-core'),
            'elementor_compatibility' => esc_html__('Elementor', 'press-permit-core'),
            'nested_pages_compatibility' => esc_html__('Nested Pages', 'press-permit-core'),
            'publishpress_statuses_compatibility' => esc_html__('PublishPress Statuses', 'press-permit-core'),
            'relevanssi_compatibility' => esc_html__('Relevanssi', 'press-permit-core'),
            'searchwp_compatibility' => esc_html__('SearchWP', 'press-permit-core'),
            'woocommerce_compatibility' => esc_html__('WooCommerce', 'press-permit-core'),
            'wpml_compatibility' => esc_html__('WPML', 'press-permit-core'),
            'yoast_seo_compatibility' => esc_html__('Yoast SEO', 'press-permit-core'),
            'yootheme_compatibility' => esc_html__('YooTheme', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'compatibility_packs' => [
                'acf_compatibility',
                'bbpress_compatibility',
                'beaver_compatibility',
                'breakdance_compatibility',
                'buddypress_compatibility',
                'cms_tree_view_compatibility',
                'elementor_compatibility',
                'nested_pages_compatibility',
                'publishpress_statuses_compatibility',
                'relevanssi_compatibility',
                'searchwp_compatibility',
                'woocommerce_compatibility',
                'wpml_compatibility',
                'yoast_seo_compatibility',
                'yootheme_compatibility'
            ],
        ];

        $key = 'integrations';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsUI()
    {
        $ui = SettingsAdmin::instance();
        $tab = 'integrations';

        $section = 'compatibility_packs';
        if (!empty($ui->form_options[$tab][$section])): ?>
            <tr>
                <td>
                    <?php if (!presspermit()->isPro()): ?>
                        <div class="pp-integrations-upgrade-cta">
                            <div class="pp-pro-banner">
                                <div>
                                    <h2><?php esc_html_e('Unlock Pro Integrations', 'press-permit-core'); ?></h2>
                                    <p><?php esc_html_e('Upgrade to the Pro version for optimal compatibility and prompt, professional support.', 'press-permit-core'); ?></p>
                                </div>
                                <div class="pp-pro-badge-banner no-bg">
                                    <a href="<?php echo esc_url(self::UPGRADE_PRO_URL); ?>" target="_blank" class="pp-upgrade-btn">
                                        <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="pp-integrations-container">
                        <!-- Category Filters -->
                        <div class="pp-category-labels">
                            <div class="pp-category-label active" data-category="all">
                                <?php esc_html_e('All', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="admin">
                                <?php esc_html_e('Admin', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="builder">
                                <?php esc_html_e('Builder', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="cache">
                                <?php esc_html_e('Cache', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="community">
                                <?php esc_html_e('Community', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="ecommerce">
                                <?php esc_html_e('E-Commerce', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="events">
                                <?php esc_html_e('Events', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="multilingual">
                                <?php esc_html_e('Multilingual', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="seo">
                                <?php esc_html_e('SEO', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="workflow">
                                <?php esc_html_e('Workflow', 'press-permit-core'); ?>
                            </div>
                        </div>

                        <div class="pp-integrations-grid">
                            <?php
                                $this->renderIntegrations();
                            ?>
                        </div>
                    </div>
                </td>
            </tr>
            <script type="text/javascript">
                jQuery(function ($) {
                    // Category filtering
                    $(".pp-category-label").on("click", function () {
                        $(".pp-category-label").removeClass("active");
                        $(this).addClass("active");
                        const category = $(this).data("category");
                        $(".pp-integration-card").each(function () {
                            const categories = ($(this).data("categories") || "all")
                                .toString()
                                .split(",");
                            if (category === "all" || categories.includes(category)) {
                                $(this).removeClass("pp-hidden");
                            } else {
                                $(this).addClass("pp-hidden");
                            }
                        });
                    });

                    // Disabled checkbox upgrade message
                    $('#pp-integrations .pp-integration-card.pp-disabled input[type="checkbox"]').on(
                        "click",
                        function (e) {
                            e.preventDefault();
                            const card = $(this).closest(".pp-integration-card");
                            card
                                .find(".pp-upgrade-overlay")
                                .css("opacity", "1")
                                .delay(3000)
                                .animate({ opacity: "0" }, 500);
                            if (!card.find(".pp-temp-message").length) {
                                $(
                                    '<div class="pp-temp-message" style="position:absolute;top:10px;right:10px;background:#ff5722;color:white;padding:5px 10px;border-radius:3px;font-size:12px;z-index:999;">Pro Feature</div>'
                                )
                                    .appendTo(card)
                                    .delay(2000)
                                    .fadeOut(500, function () {
                                        $(this).remove();
                                    });
                            }
                        }
                    );
                });
            </script>
        <?php endif;
    }

    private function renderCompatibilityPack($integration)
    {
        $is_pro = defined('PRESSPERMIT_PRO_VERSION');
        $is_enabled = $is_pro;
    
        $is_disabled = !$is_pro || !$integration['available'];
        $is_checked = true;
        $card_class = $is_disabled ? 'pp-integration-card pp-disabled' : 'pp-integration-card';
    
        if ($integration['available']) {
            $card_class .= ' pp-available';
        }
    
        if ($integration['free']) {
            $card_class .= ' pp-free';
        }
    
        $icon_class = 'pp-integration-icon ' . $integration['icon_class'];
        $categories_string = implode(',', $integration['categories']);
    
        // Determine category tag
        $category_tag = '';
        if (in_array('builder', $integration['categories'])) {
            $category_tag = '<span class="pp-category-tag pp-tag-builder">' . esc_html__('Builder', 'press-permit-core') . '</span>';
        } elseif (in_array('admin', $integration['categories'])) {
            $category_tag = '<span class="pp-category-tag pp-tag-admin">' . esc_html__('Admin', 'press-permit-core') . '</span>';
        } elseif (in_array('events', $integration['categories'])) {
            $category_tag = '<span class="pp-category-tag pp-tag-events">' . esc_html__('Events', 'press-permit-core') . '</span>';
        } elseif (in_array('seo', $integration['categories'])) {
            $category_tag = '<span class="pp-category-tag pp-tag-seo">' . esc_html__('SEO', 'press-permit-core') . '</span>';
        } elseif (in_array('fields', $integration['categories'])) {
            $category_tag = '<span class="pp-category-tag pp-tag-fields">' . esc_html__('Fields', 'press-permit-core') . '</span>';
        } elseif (in_array('cache', $integration['categories'])) {
            $category_tag = '<span class="pp-category-tag pp-tag-seo">' . esc_html__('Cache', 'press-permit-core') . '</span>';
        } elseif (in_array('ecommerce', $integration['categories'])) {
            $category_tag = '<span class="pp-category-tag pp-tag-ecommerce">' . esc_html__('Commerce', 'press-permit-core') . '</span>';
        } elseif (in_array('multilingual', $integration['categories'])) {
            $category_tag = '<span class="pp-category-tag pp-tag-multilingual">' . esc_html__('Multilang', 'press-permit-core') . '</span>';
        } elseif (in_array('community', $integration['categories'])) {
            $category_tag = '<span class="pp-category-tag pp-tag-community">' . esc_html__('Community', 'press-permit-core') . '</span>';
        } elseif (in_array('workflow', $integration['categories'])) {
            $category_tag = '<span class="pp-category-tag pp-tag-workflow">' . esc_html__('Workflow', 'press-permit-core') . '</span>';
        }
        ?>
        <div class="<?php echo esc_attr($card_class); ?>" data-categories="<?php echo esc_attr($categories_string); ?>">
            <div class="pp-integration-icon-wrap">
                <div class="pp-integration-icon <?php echo esc_attr($integration['icon_class']); ?>">
                </div>

                <?php echo wp_kses_post($category_tag); ?>
            </div>

            <div class="pp-integration-content">
                <h3 class="pp-integration-title">
                    <?php echo esc_html($integration['title']); ?>
                    
                    <?php if (!$is_pro && empty($integration['free'])): ?>
                        <span class="pp-badge pp-pro-badge"><?php esc_html_e('Pro', 'press-permit-core');?></span>
                    <?php endif; ?>

                    <?php if ($integration['available']): ?>
                        <span class="pp-badge"
                            style="background: #5e92c4;"><?php esc_html_e('Active Plugin', 'press-permit-core'); ?></span>
                    <?php endif; ?>
                </h3>

                <?php if (strlen($integration['description']) > 1):?>
                <p class="pp-integration-description"><?php echo esc_html($integration['description']); ?></p>
                <?php endif;?>

                <div class="pp-integration-features">
                    <ul>
                        <?php foreach ($integration['features'] as $feature): ?>
                            <li><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if (empty($integration['free']) && $integration['available']):?>
                    <div class="pp-settings-toggle">
                        <?php if ($is_pro && $is_enabled): ?>
                            <div class="pp-integration-status active"><?php esc_html_e('Integration Active', 'press-permit-core'); ?></div>
                        <?php else: ?>
                            <div class="pp-integration-status disabled"><?php esc_html_e('Upgrade to Pro to enable this integration', 'press-permit-core'); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif;?>
            </div>

            <?php if (!$is_pro && empty($integration['free'])): ?>
                <div class="pp-upgrade-overlay">
                    <h4><?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                    <p><?php echo esc_html(sprintf(__('Unlock %s integration to enhance your revisions solution.', 'press-permit-core'), $integration['title'])); ?>
                    </p>
                    <div class="pp-upgrade-buttons">
                        <?php if (!empty($integration['learn_more_url'])): ?>
                            <a href="<?php echo esc_url($integration['learn_more_url']); ?>" target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(self::UPGRADE_PRO_URL); ?>" target="_blank" class="pp-upgrade-btn-primary">
                            <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                        </a>
                    </div>
                </div>

            <?php elseif ($is_pro && $integration['available'] && !empty($integration['learn_more_url'])): ?>
                <div class="pp-upgrade-overlay">
                    <h4><?php $integration['free'] ? esc_html_e('Active Plugin', 'press-permit-core') : esc_html_e('Active Plugin Integration', 'press-permit-core'); ?></h4>
                    <div class="pp-upgrade-buttons">
                            <a href="<?php echo esc_url($integration['learn_more_url']); ?>" target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                    </div>
                </div>

            <?php elseif (empty($integration['free']) && !empty($integration['learn_more_url'])): ?>
                <div class="pp-upgrade-overlay">
                    <h4><?php esc_html_e('Supported Plugin Integration', 'press-permit-core'); ?></h4>
                    <div class="pp-upgrade-buttons">
                            <a href="<?php echo esc_url($integration['learn_more_url']); ?>" target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                    </div>
                </div>

            <?php endif; ?>
        </div>
        <?php
    }

    private function renderIntegrations()
    {
        $int = $this->ui->defined_integrations;
        
        // Sort integrations: available=true first, then by title
        usort($int, function($a, $b) {
            // Active plugins first
            if ($a['available'] !== $b['available']) {
                return $a['available'] ? -1 : 1;
            }
            
            // Then sort alphabetically by title
            return strcasecmp($a['title'], $b['title']);
        });

        // Render each integration
        foreach ($int as $integration) {
            $this->renderCompatibilityPack($integration);
        }
    }
}

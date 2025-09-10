<?php

namespace PublishPress\Permissions\UI;

/**
 * Settings Tab: File Access
 * 
 * This tab demonstrates how to add configurable badges to tab titles.
 * 
 * HOW TO ADD BADGES TO OTHER TABS:
 * ================================
 * 
 * 1. In your tab class constructor, add the badge filter:
 *    add_filter('presspermit_option_tab_badges', [$this, 'optionTabBadges'], 5);
 * 
 * 2. Create the optionTabBadges method using the helper:
 *    public function optionTabBadges($badges) {
 *        $badges['your_tab_key'] = SettingsTabFileAccess::createTabBadge('pro');
 *        return $badges;
 *    }
 * 
 * 3. Or create manually:
 *    public function optionTabBadges($badges) {
 *        $badges['your_tab_key'] = [
 *            'text' => 'PRO',           // Badge text
 *            'color' => 'white',        // Text color (optional)
 *            'bg_color' => '#8B5CF6',   // Background color (optional)
 *            'class' => 'your-class'    // CSS class (optional)
 *        ];
 *        return $badges;
 *    }
 * 
 * BADGE HELPER EXAMPLES:
 * =====================
 * createTabBadge('pro')                    // Purple PRO badge
 * createTabBadge('new')                    // Green NEW badge
 * createTabBadge('beta')                   // Orange BETA badge
 * createTabBadge('hot')                    // Red HOT badge
 * createTabBadge('premium')                // Gold PREMIUM badge
 * createTabBadge('pro', 'PLUS')            // Purple PLUS badge
 * createTabBadge('new', '', '#FF0000')     // Red NEW badge
 * 
 * MANUAL BADGE EXAMPLES:
 * =====================
 * PRO Badge (Purple):   ['text' => 'PRO', 'bg_color' => '#8B5CF6']
 * NEW Badge (Green):    ['text' => 'NEW', 'bg_color' => '#10B981']
 * BETA Badge (Orange):  ['text' => 'BETA', 'bg_color' => '#F59E0B']
 * HOT Badge (Red):      ['text' => 'HOT', 'bg_color' => '#EF4444']
 * PREMIUM Badge (Gold): ['text' => 'PREMIUM', 'bg_color' => '#D97706']
 * 
 * EXAMPLE: Adding a badge to SettingsTabModules.php
 * ================================================
 * 1. Add filter in constructor:
 *    add_filter('presspermit_option_tab_badges', [$this, 'optionTabBadges'], 5);
 * 
 * 2. Add method:
 *    public function optionTabBadges($badges) {
 *        $badges['modules'] = SettingsTabFileAccess::createTabBadge('new');
 *        return $badges;
 *    }
 * 
 * That's it! The Modules tab will now show a green "NEW" badge.
 */
class SettingsTabFileAccess
{
    public function __construct()
    {
        $order = defined('PRESSPERMIT_PRO_VERSION') ? 5 : 110;
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], $order);
        add_filter('presspermit_option_tab_badges', [$this, 'optionTabBadges'], 5);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_file_access_options_ui', [$this, 'optionsUI']);
    }

    public function optionTabs($tabs)
    {
        $tabs['file_access'] = esc_html__('File Access', 'press-permit-core');
        return $tabs;
    }

    public function optionTabBadges($badges)
    {
        /**
         * Add PRO badge to File Access tab using the helper method
         * This is configurable - you can customize:
         * - Use helper: self::createTabBadge('pro') or self::createTabBadge('new')
         * - Custom text: self::createTabBadge('pro', 'CUSTOM')
         * - Custom color: self::createTabBadge('pro', '', '#FF0000')
         * - Manual: ['text' => 'TEXT', 'bg_color' => '#COLOR', 'class' => 'css-class']
         * 
         * Examples:
         * $badges['my_tab'] = self::createTabBadge('new');           // Green NEW badge
         * $badges['my_tab'] = self::createTabBadge('beta');          // Orange BETA badge
         * $badges['my_tab'] = self::createTabBadge('hot');           // Red HOT badge
         * $badges['my_tab'] = self::createTabBadge('pro', 'PLUS');   // Purple PLUS badge
         * $badges['my_tab'] = self::createTabBadge('new', '', '#FF0000'); // Red NEW badge
         */
        
        $badges['file_access'] = self::createTabBadge('pro');
        return $badges;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'file_access' => esc_html__('File Access', 'press-permit-core'),
        ];

        $key = 'file_access';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'unattached_files_private' => esc_html__('Make Unattached Files Private', 'press-permit-core'),
            'small_thumbnails_unfiltered' => esc_html__('Small Thumbnails Unfiltered', 'press-permit-core'),
            'file_access_apply_redirect' => esc_html__('Compatibility Mode: Apply extra redirect', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'file_access' => ['unattached_files_private', 'small_thumbnails_unfiltered', 'file_access_apply_redirect'],
        ];

        $key = 'file_access';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsUI()
    {
        $pp = presspermit();
        $ui = SettingsAdmin::instance();
        $tab = 'file_access';

        $section = 'file_access';
        if (!empty($ui->form_options[$tab][$section])) : ?>
            <tr>
                <td>
                    <?php
                    $this->renderProPromo();
                    ?>
                </td>
            </tr>
        <?php endif;
    }

    private function renderProPromo()
    {
        ?>
        <div class="pp-feature-promo">
            <!-- Feature Cards Grid -->
            <div class="pp-feature-grid">
                
                <!-- Core Protection Card -->
                <div class="pp-feature-card pp-feature-card-hover">
                    <div class="pp-feature-header">
                        <div class="pp-feature-icon core-protection">&#128737;&#65039;</div>
                        <h4><?php esc_html_e('Media File Protection', 'press-permit-core'); ?></h4>
                    </div>
                    <ul class="pp-feature-list">
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Control direct access to Media files', 'press-permit-core'); ?>
                        </li>
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Automatic .htaccess file management', 'press-permit-core'); ?>
                        </li>
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('File Access key with on-demand regeneration', 'press-permit-core'); ?>
                        </li>
                    </ul>
                    
                    <!-- Upgrade Overlay -->
                    <div class="pp-upgrade-overlay">
                        <h4 class="core-protection">&#128274; <?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                        <p>
                            <?php esc_html_e('Upgrade to Pro to unlock advanced file protection capabilities', 'press-permit-core'); ?>
                        </p>
                        <div class="pp-upgrade-buttons">
                            <a href="https://publishpress.com/links/permissions-file-access" target="_blank" class="pp-upgrade-btn-primary">
                                <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                            </a>
                            <a href="https://publishpress.com/knowledge-base/regulate-file-url-access/" target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Privacy & Performance Card -->
                <div class="pp-feature-card pp-feature-card-hover">
                    <div class="pp-feature-header">
                        <div class="pp-feature-icon privacy-performance">&#9889;</div>
                        <h4><?php esc_html_e('Privacy & Performance', 'press-permit-core'); ?></h4>
                    </div>
                    <ul class="pp-feature-list">
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Limit attachment access by post permissions', 'press-permit-core'); ?>
                        </li>
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Make unattached files private', 'press-permit-core'); ?>
                        </li>
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Directly control specific files', 'press-permit-core'); ?>
                        </li>
                    </ul>
                    
                    <!-- Upgrade Overlay -->
                    <div class="pp-upgrade-overlay">
                        <h4 class="privacy-performance">&#9889; <?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                        <p>
                            <?php esc_html_e('Optimize your site with advanced privacy and performance controls', 'press-permit-core'); ?>
                        </p>
                        <div class="pp-upgrade-buttons">
                            <a href="https://publishpress.com/links/permissions-file-access" target="_blank" class="pp-upgrade-btn-primary">
                                <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                            </a>
                            <a href="https://publishpress.com/knowledge-base/file-filtering-nginx/" target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Advanced Integration Card -->
                <div class="pp-feature-card pp-feature-card-hover">
                    <div class="pp-feature-header">
                        <div class="pp-feature-icon advanced-integration">&#128295;</div>
                        <h4><?php esc_html_e('Advanced Integration', 'press-permit-core'); ?></h4>
                    </div>
                    <ul class="pp-feature-list">
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Nginx integration support', 'press-permit-core'); ?>
                        </li>
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Process externally uploaded files', 'press-permit-core'); ?>
                        </li>
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Supports network (multisite) installs', 'press-permit-core'); ?>
                        </li>
                    </ul>
                    
                    <!-- Upgrade Overlay -->
                    <div class="pp-upgrade-overlay">
                        <h4 class="advanced-integration">&#128295; <?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                        <p>
                            <?php esc_html_e('Get advanced integration features with Nginx, FTP, and more', 'press-permit-core'); ?>
                        </p>
                        <div class="pp-upgrade-buttons">
                            <a href="https://publishpress.com/links/permissions-file-access" target="_blank" class="pp-upgrade-btn-primary">
                                <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                            </a>
                            <a href="https://publishpress.com/knowledge-base/regulate-file-url-access/" target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA Section -->
            <div class="pp-cta-section">
                <h4>
                    <?php esc_html_e('Ready to secure your files?', 'press-permit-core'); ?>
                </h4>
                <p>
                    <?php esc_html_e('Upgrade to Pro and get advanced file access control with all these features and more.', 'press-permit-core'); ?>
                </p>
                <div class="pp-cta-buttons">
                    <a href="https://publishpress.com/links/permissions-file-access" 
                       class="button-primary button-large" 
                       target="_blank">
                        <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                    </a>
                    <a href="https://publishpress.com/knowledge-base/regulate-file-url-access/" 
                       target="_blank"
                       class="pp-learn-more-link">
                        <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Helper method for creating tab badges
     * Makes it easy for other developers to create consistent badges
     * 
     * @param string $type Badge type: 'pro', 'new', 'beta', 'hot', 'premium'
     * @param string $custom_text Optional custom text instead of type
     * @param string $custom_color Optional custom background color
     * @return array Badge configuration array
     */
    public static function createTabBadge($type = 'pro', $custom_text = '', $custom_color = '')
    {
        $badge_configs = [
            'pro' => [
                'text' => 'PRO',
                'bg_color' => '#8B5CF6',
                'class' => 'pp-pro-badge'
            ],
            'new' => [
                'text' => 'NEW', 
                'bg_color' => '#10B981',
                'class' => 'pp-new-badge'
            ],
            'beta' => [
                'text' => 'BETA',
                'bg_color' => '#F59E0B', 
                'class' => 'pp-beta-badge'
            ],
            'hot' => [
                'text' => 'HOT',
                'bg_color' => '#EF4444',
                'class' => 'pp-hot-badge'
            ],
            'premium' => [
                'text' => 'PREMIUM',
                'bg_color' => '#D97706',
                'class' => 'pp-premium-badge'
            ]
        ];

        $badge = isset($badge_configs[$type]) ? $badge_configs[$type] : $badge_configs['pro'];
        
        // Allow custom overrides
        if ($custom_text) {
            $badge['text'] = $custom_text;
        }
        if ($custom_color) {
            $badge['bg_color'] = $custom_color;
        }
        
        // Always include default properties
        $badge['color'] = 'white';
        
        return $badge;
    }
}

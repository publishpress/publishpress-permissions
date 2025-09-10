<?php

namespace PublishPress\Permissions\UI;

/**
 * Settings Tab: Membership
 * 
 * This tab demonstrates membership and circles features.
 * Features circles-based access control and time-limited permission group membership.
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
class SettingsTabMembership
{
    public function __construct()
    {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 100);
        add_filter('presspermit_option_tab_badges', [$this, 'optionTabBadges'], 5);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_membership_options_ui', [$this, 'optionsUI']);
    }

    public function optionTabs($tabs)
    {
        $tabs['membership'] = esc_html__('Membership', 'press-permit-core');
        return $tabs;
    }

    public function optionTabBadges($badges)
    {
        /**
         * Add PRO badge to Membership tab using the helper method
         * This is configurable - you can customize:
         * - Use helper: SettingsTabFileAccess::createTabBadge('pro') or SettingsTabFileAccess::createTabBadge('new')
         * - Custom text: SettingsTabFileAccess::createTabBadge('pro', 'CUSTOM')
         * - Custom color: SettingsTabFileAccess::createTabBadge('pro', '', '#FF0000')
         * - Manual: ['text' => 'TEXT', 'bg_color' => '#COLOR', 'class' => 'css-class']
         * 
         * Examples:
         * $badges['my_tab'] = SettingsTabFileAccess::createTabBadge('new');           // Green NEW badge
         * $badges['my_tab'] = SettingsTabFileAccess::createTabBadge('beta');          // Orange BETA badge  
         * $badges['my_tab'] = SettingsTabFileAccess::createTabBadge('hot');           // Red HOT badge
         * $badges['my_tab'] = SettingsTabFileAccess::createTabBadge('pro', 'PLUS');   // Purple PLUS badge
         * $badges['my_tab'] = SettingsTabFileAccess::createTabBadge('new', '', '#FF0000'); // Red NEW badge
         */
        
        $badges['membership'] = SettingsTabFileAccess::createTabBadge('pro');
        return $badges;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'membership' => esc_html__('Membership', 'press-permit-core'),
        ];

        $key = 'membership';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'circles_enabled' => esc_html__('Enable Circles', 'press-permit-core'),
            'time_limited_membership' => esc_html__('Time-limited Membership', 'press-permit-core'),
            'membership_notifications' => esc_html__('Membership Notifications', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'membership' => ['circles_enabled', 'time_limited_membership', 'membership_notifications'],
        ];

        $key = 'membership';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsUI()
    {
        $pp = presspermit();
        $ui = SettingsAdmin::instance();
        $tab = 'membership';

        $section = 'membership';
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
                
                <!-- Circles Card -->
                <div class="pp-feature-card pp-feature-card-hover" style="display: flex;">
                    <div class="pp-integration-icon circles">👥</div>
                    <div class="pp-integration-content">
                        <h3 class="pp-integration-title">
                            <?php esc_html_e('Access Circles', 'press-permit-core'); ?> <span class="pp-badge pp-pro-badge"><?php esc_html_e('Pro', 'press-permit-core'); ?></span>
                        </h3>
                        <p class="pp-integration-description">
                            <?php esc_html_e('Limit access based on post authorship and group membership for more granular permissions control.', 'press-permit-core'); ?>
                        </p>

                        <div class="pp-integration-features">
                            <ul>
                                <li><?php esc_html_e('Modify viewing or editing access', 'press-permit-core'); ?></li>
                                <li><?php esc_html_e('Use any Role or Permission Group', 'press-permit-core'); ?></li>
                                <li><?php esc_html_e('Circle member\'s capabilities apply only for other member\'s posts', 'press-permit-core'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <div class="pp-upgrade-overlay">
                        <h4><?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                        <p>
                            <?php esc_html_e('Upgrade to Pro to limit access based on post author relationships', 'press-permit-core'); ?>
                        </p>
                        <div class="pp-upgrade-buttons">
                          <a href="https://publishpress.com/links/permissions-membership" target="_blank" class="pp-upgrade-btn-primary">
                              <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                          </a>
                            <a href="https://publishpress.com/knowledge-base/circles-visibility/" target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Membership Card -->
                <div class="pp-feature-card pp-feature-card-hover" style="display: flex;">
                    <div class="pp-integration-icon membership">&#9200;</div>
                    <div class="pp-integration-content">
                        <h3 class="pp-integration-title">
                            <?php esc_html_e('Time-limited Membership', 'press-permit-core'); ?> <span class="pp-badge pp-pro-badge"><?php esc_html_e('Pro', 'press-permit-core'); ?></span>
                        </h3>
                        <p class="pp-integration-description">
                            <?php esc_html_e('Create time-limited permission group memberships with automatic expiration.', 'press-permit-core'); ?>
                        </p>

                        <div class="pp-integration-features">
                            <ul>
                                <li><?php esc_html_e('Delay membership start date', 'press-permit-core'); ?></li>
                                <li><?php esc_html_e('Set membership expiration date', 'press-permit-core'); ?></li>
                                <li><?php esc_html_e('Group membership grants access to content of your choice', 'press-permit-core'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <div class="pp-upgrade-overlay">
                        <h4><?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                        <p>
                            <?php esc_html_e('Go Pro for time-limited group membership', 'press-permit-core'); ?>
                        </p>
                        <div class="pp-upgrade-buttons">
                          <a href="https://publishpress.com/links/permissions-membership" target="_blank" class="pp-upgrade-btn-primary">
                              <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                          </a>
                            <a href="https://publishpress.com/knowledge-base/groups-date-limits/" target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA Section -->
            <div class="pp-cta-section">
                <h4>
                    <?php esc_html_e('Ready to enhance your membership features?', 'press-permit-core'); ?>
                </h4>
                <p>
                    <?php esc_html_e('Upgrade to Pro and get advanced membership management with circles, time-limited memberships, and more.', 'press-permit-core'); ?>
                </p>
                <div class="pp-cta-buttons">
                    <a href="https://publishpress.com/links/permissions-membership" 
                       class="button-primary button-large" 
                       target="_blank">
                        <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                    </a>
                    <a href="https://publishpress.com/knowledge-base/groups-date-limits/" 
                       target="_blank"
                       class="pp-learn-more-link">
                        <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}

<?php

namespace PublishPress\Permissions\UI;

/**
 * SettingsTabUserPosts - Promotional User Posts Tab
 *
 * This tab showcases user-post synchronization features with simplified promotional content.
 * Based on SettingsTabFileAccess.php structure but focused on user post management capabilities.
 *
 * Features promoted:
 * - User post synchronization
 * - Author permission management
 * - Bulk user post creation
 * - User-post field matching
 * - Role-based synchronization
 *
 * @package PublishPress\Permissions\UI
 */
class SettingsTabUserPosts
{

    public function __construct()
    {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 95);
        add_filter('presspermit_option_tab_badges', [$this, 'optionTabBadges'], 5);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_sync_posts_options_ui', [$this, 'optionsUI']);
    }

    public function optionTabs($tabs)
    {
        $tabs['sync_posts'] = esc_html__('User Posts', 'press-permit-core');
        return $tabs;
    }

    public function optionTabBadges($badges)
    {
        $badges['sync_posts'] = SettingsTabFileAccess::createTabBadge('pro');
        return $badges;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'user_sync' => esc_html__('User Posts Synchronization', 'press-permit-core'),
            'upgrade_notice' => '',
        ];

        $key = 'sync_posts';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'sync_user_posts' => esc_html__('Synchronize User Posts', 'press-permit-core'),
            'bulk_create_posts' => esc_html__('Bulk Create Author Posts', 'press-permit-core'),
            'match_user_fields' => esc_html__('Smart User Matching', 'press-permit-core'),
            'role_based_sync' => esc_html__('Role-Based Synchronization', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'user_sync' => ['sync_user_pages', 'bulk_create_pages', 'match_user_fields', 'role_based_sync'],
            'upgrade_notice' => ['no_option'],
        ];

        $key = 'sync_posts';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsUI()
    {
        $pp = presspermit();
        $ui = SettingsAdmin::instance();
        $tab = 'sync_posts';

        $section = 'user_sync';
        if (!empty($ui->form_options[$tab][$section])): ?>
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
                        <div class="pp-feature-icon core-protection">&#9881;&#65039;</div>
                        <h4><?php esc_html_e('Create Posts for Your Users', 'press-permit-core'); ?></h4>
                    </div>
                    <ul class="pp-feature-list">
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Automatically generate posts for users', 'press-permit-core'); ?>
                        </li>
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Set up posts for existing users', 'press-permit-core'); ?>
                        </li>
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Produce posts for users when they register', 'press-permit-core'); ?>
                        </li>
                    </ul>

                    <!-- Upgrade Overlay -->
                    <div class="pp-upgrade-overlay">
                        <h4 class="core-protection">&#128274; <?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                        <p>
                            <?php esc_html_e('Upgrade to Pro to automatically generate posts for users', 'press-permit-core'); ?>
                        </p>
                        <div class="pp-upgrade-buttons">
                            <a href="https://publishpress.com/links/permissions-user-pages" target="_blank"
                                class="pp-upgrade-btn-primary">
                                <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                            </a>
                            <a href="https://publishpress.com/knowledge-base/how-to-create-a-personal-page-for-each-wordpress-user/"
                                target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Bulk Page Creation Card -->
                <div class="pp-feature-card pp-feature-card-hover">
                    <div class="pp-feature-header">
                        <div class="pp-feature-icon bulk-action">&#128196;</div>
                        <h4><?php esc_html_e('Bulk Page Creation', 'press-permit-core'); ?></h4>
                    </div>
                    <ul class="pp-feature-list">
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Generate posts for user roles that you select', 'press-permit-core'); ?>
                        </li>
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Choose how many posts to create per user', 'press-permit-core'); ?>
                        </li>
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Decide which status to use for new posts', 'press-permit-core'); ?>
                        </li>
                    </ul>

                    <!-- Upgrade Overlay -->
                    <div class="pp-upgrade-overlay">
                        <h4 class="privacy-performance">&#9889; <?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                        <p>
                            <?php esc_html_e('Upgrade to Pro to unlock bulk page creation features', 'press-permit-core'); ?>
                        </p>
                        <div class="pp-upgrade-buttons">
                            <a href="https://publishpress.com/links/permissions-user-pages" target="_blank"
                                class="pp-upgrade-btn-primary">
                                <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                            </a>
                            <a href="https://publishpress.com/knowledge-base/how-to-create-a-personal-page-for-each-wordpress-user/"
                                target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Advanced Field Matching Card -->
                <div class="pp-feature-card pp-feature-card-hover">
                    <div class="pp-feature-header">
                        <div class="pp-feature-icon advanced-integration">&#128295;</div>
                        <h4><?php esc_html_e('Advanced Configuration', 'press-permit-core'); ?></h4>
                    </div>
                    <ul class="pp-feature-list">
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Automatically detect and avoid duplicate posts', 'press-permit-core'); ?>
                        </li>
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Add new pages to a parent page', 'press-permit-core'); ?>
                        </li>
                        <li>
                            <span class="check-icon">&check;</span>
                            <?php esc_html_e('Allow users to edit their new posts', 'press-permit-core'); ?>
                        </li>
                    </ul>

                    <!-- Upgrade Overlay -->
                    <div class="pp-upgrade-overlay">
                        <h4 class="advanced-integration">&#128295; <?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                        <p>
                            <?php esc_html_e('Unlock advanced user synchronization features', 'press-permit-core'); ?>
                        </p>
                        <div class="pp-upgrade-buttons">
                            <a href="https://publishpress.com/links/permissions-user-pages" target="_blank"
                                class="pp-upgrade-btn-primary">
                                <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                            </a>
                            <a href="https://publishpress.com/knowledge-base/how-to-create-a-personal-page-for-each-wordpress-user/"
                                target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA Section -->
            <div class="pp-cta-section">
                <h4>
                    <?php esc_html_e('Ready to automatically generate posts for your users?', 'press-permit-core'); ?>
                </h4>
                <p>
                    <?php esc_html_e('Upgrade to Pro and get advanced user post generation with all these features and more.', 'press-permit-core'); ?>
                </p>
                <div class="pp-cta-buttons">
                    <a href="https://publishpress.com/links/permissions-user-pages" class="button-primary button-large"
                        target="_blank">
                        <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                    </a>
                    <a href="https://publishpress.com/knowledge-base/how-to-create-a-personal-page-for-each-wordpress-user/"
                        target="_blank" class="pp-learn-more-link">
                        <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}
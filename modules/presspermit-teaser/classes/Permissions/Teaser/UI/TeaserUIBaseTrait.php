<?php
namespace PublishPress\Permissions\Teaser\UI;

/**
 * Base trait for Teaser UI functionality
 * Defines FREE version capabilities that can be overridden in PRO
 */
trait TeaserUIBaseTrait {
    
    /**
     * Get available post types for current version
     * 
     * FREE: Only 'post'
     * PRO: Override to return all enabled post types
     * 
     * @param object|null $pp Optional PressPermit instance (not used in FREE)
     * @return array Available post types
     */
    protected function getAvailablePostTypes($pp = null) {
        return ['post']; // FREE: Only standard posts
    }
    
    /**
     * Get available teaser types
     * 
     * FREE: 0 (no teaser), 1 (configured text)
     * PRO: Override to add read_more, excerpt, redirect, etc.
     * 
     * @return array Available teaser types with captions
     */
    protected function getAvailableTeaserTypes() {
        return [
            0 => esc_html__('No Teaser', 'presspermit'),
            1 => esc_html__('Configured Teaser Text', 'presspermit'),
        ];
    }
    
    /**
     * Get available user application options
     * 
     * FREE: Only 'both' (all users)
     * PRO: Override to add 'anon' and 'logged' separate targeting
     * 
     * @return array Available user application options
     */
    protected function getAvailableUserApplications() {
        return ['0' => 'both']; // FREE: Both users only
    }
    
    /**
     * Get available teaser text user suffixes
     * 
     * FREE: Only '_anon' (anonymous users)
     * PRO: Override to add '' (logged-in users)
     * 
     * @return array Available suffixes for teaser text fields
     */
    protected function getAvailableTeaserTextSuffixes() {
        return ['_anon']; // FREE: Anonymous users only
    }
    
    /**
     * Get available teaser text actions
     * 
     * FREE: Only 'replace' for 'content'
     * PRO: Override to add 'prepend', 'append' for both 'content' and 'name'
     * 
     * @return array Available actions keyed by item type
     */
    protected function getAvailableTeaserTextActions() {
        return [
            'content' => ['replace'], // FREE: Replace content only
        ];
    }
    
    /**
     * Check if teaser text tabs should be shown
     * 
     * FREE: No tabs (single anonymous user option)
     * PRO: Override to return true (show tabs for anon/logged)
     * 
     * @return bool True if tabs should be displayed
     */
    protected function shouldShowTeaserTextTabs() {
        return false; // FREE: No tabs
    }
    
    /**
     * Check if a specific feature is available
     * 
     * @param string $feature Feature identifier (e.g., 'post_type_page', 'teaser_type_read_more')
     * @return bool True if feature is available in current version
     */
    protected function isFeatureAvailable($feature) {
        // FREE features that are always available
        $free_features = [
            'post_type_post' => true,
            'teaser_type_none' => true,
            'teaser_type_configured' => true,
            'user_application_both' => true,
            'teaser_text_replace_content_anon' => true, // FREE: Replace content for anonymous users
        ];

        if ($this->isProVersion()) {
            return true;
        }
        
        return $free_features[$feature] ?? false;
    }
    
    /**
     * Check if PRO version is active
     * 
     * @return bool True if PRO version constant is defined
     */
    protected function isProVersion() {
        return defined('PRESSPERMIT_PRO_VERSION');
    }
    
    /**
     * Render PRO badge for locked features
     * 
     * @param string $tooltip Tooltip text for the badge
     * @return string HTML for PRO badge, empty string if PRO version
     */
    protected function renderProBadge($tooltip = '') {
        if ($this->isProVersion()) {
            return '';
        }
        
        $default_tooltip = esc_attr__('This is a PRO feature', 'presspermit');
        
        return sprintf(
            ' <span class="pp-pro-badge" title="%s">🔒 PRO</span>',
            esc_attr($tooltip ?: $default_tooltip)
        );
    }
    
    /**
     * Get upgrade URL
     * 
     * @return string URL to upgrade page
     */
    protected function getUpgradeUrl() {
        return 'https://publishpress.com/links/permissions-banner';
    }
    
    /**
     * Get feature comparison URL
     * 
     * @return string URL to pricing/comparison page
     */
    protected function getComparisonUrl() {
        return 'https://publishpress.com/permissions/pricing/';
    }
    
    /**
     * Render upgrade notice for PRO features
     * 
     * @param string $feature_name Name of the feature
     * @param string $description Feature description
     * @return void
     */
    protected function renderProFeatureNotice($feature_name, $description = '') {
        if ($this->isProVersion()) {
            return;
        }
        ?>
        <div class="pp-pro-feature-notice" style="padding: 15px; background: #f8f9fa; border-left: 4px solid #0073aa; margin-top: 20px;">
            <h4>
                <?php echo esc_html($feature_name); ?>
                <?php echo wp_kses_post($this->renderProBadge(sprintf(esc_attr__('%s is available in PRO', 'presspermit'), $feature_name))); ?>
            </h4>
            <?php if ($description) : ?>
                <p><?php echo esc_html($description); ?></p>
            <?php endif; ?>
            <p>
                <a href="<?php echo esc_url($this->getUpgradeUrl()); ?>" class="button button-primary" target="_blank">
                    <?php esc_html_e('Upgrade to PRO', 'presspermit'); ?>
                </a>
                <a href="<?php echo esc_url($this->getComparisonUrl()); ?>" class="button button-secondary" target="_blank">
                    <?php esc_html_e('Learn More', 'presspermit'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}

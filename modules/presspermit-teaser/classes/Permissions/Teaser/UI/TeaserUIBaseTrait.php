<?php
namespace PublishPress\Permissions\Teaser\UI;

/**
 * PRO trait for Teaser UI functionality
 * Overrides FREE version methods to enable all PRO features
 */
trait TeaserUIBaseTrait {
    
    /**
     * Get available post types for PRO version
     * 
     * @param object|null $pp Optional PressPermit instance
     * @return array All enabled post types
     */
    protected function getAvailablePostTypes($pp = null) {
        // First priority: use the PressPermit instance passed as parameter
        if ($pp && method_exists($pp, 'getEnabledPostTypes')) {
            return $pp->getEnabledPostTypes();
        }
        
        // Second priority: use the instance property if available
        if (isset($this->pp) && method_exists($this->pp, 'getEnabledPostTypes')) {
            return $this->pp->getEnabledPostTypes();
        }
        
        // Third priority: access via the global presspermit function
        if (function_exists('presspermit')) {
            $pp_instance = presspermit();
            if (method_exists($pp_instance, 'getEnabledPostTypes')) {
                return $pp_instance->getEnabledPostTypes();
            }
        }
        
        // Final fallback: return all public post types from WordPress
        return get_post_types(['public' => true], 'names');
    }
    
    /**
     * Get available teaser types for PRO version
     * 
     * @return array All teaser types with captions
     */
    protected function getAvailableTeaserTypes() {
        return [
            0 => esc_html__('No Teaser', 'press-permit-core'),
            1 => esc_html__('No Teaser Text', 'press-permit-core'),
            'read_more' => esc_html__('Read More Link as Teaser', 'press-permit-core'),
            'excerpt' => esc_html__('Excerpt as Teaser', 'press-permit-core'),
            'more' => esc_html__('Excerpt or pre-More as Teaser', 'press-permit-core'),
            'x_chars' => esc_html__('First X Characters as Teaser', 'press-permit-core'),
            'redirect' => esc_html__('Redirect to another post', 'press-permit-core'),
        ];
    }
    
    /**
     * Get available user application options for PRO version
     * 
     * @return array All user application options
     */
    protected function getAvailableUserApplications() {
        return [
            '0' => 'both',
            'anon' => 'not_logged_in',
            '1' => 'logged_in'
        ];
    }
    
    /**
     * Check if a specific feature is available in PRO
     * 
     * @param string $feature Feature identifier
     * @return bool Always true in PRO version
     */
    protected function isFeatureAvailable($feature) {
        return true; // PRO: All features available
    }
    
    /**
     * Check if PRO version is active
     * 
     * @return bool Always true in PRO context
     */
    protected function isProVersion() {
        return true;
    }
    
    /**
     * Render PRO badge (never shown in PRO version)
     * 
     * @param string $tooltip Tooltip text (unused)
     * @return string Empty string (no badge in PRO)
     */
    protected function renderProBadge($tooltip = '') {
        return ''; // PRO: Never show PRO badges
    }
    
    /**
     * Render upgrade notice (never shown in PRO version)
     * 
     * @param string $feature_name Feature name (unused)
     * @param string $description Description (unused)
     * @return void
     */
    protected function renderProFeatureNotice($feature_name, $description = '') {
        // PRO: Never show upgrade notices
        return;
    }
}

<?php
namespace PublishPress\Permissions\Teaser\UI;

/**
 * Base trait for Teaser UI functionality
 * Defines capabilities and methods 
 */
trait TeaserUIBaseTrait {
    
    /**
     * Get available post types
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
     * Get available teaser types
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
     * Get available user application options
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
}

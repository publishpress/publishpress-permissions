<?php
namespace PublishPress\Permissions\Compat;

use PublishPress\PWP;

/**
 * Theme Compatibility Handler
 * Centralized location for theme-specific compatibility fixes
 * 
 * @package PublishPress\Permissions
 */
class ThemeCompat
{
    private $theme_name = '';
    private $is_avada = false;

    public function __construct()
    {
        $this->detectTheme();
        $this->registerFilters();
    }

    /**
     * Detect active theme and set flags
     */
    private function detectTheme()
    {
        if ($theme = wp_get_theme()) {
            $this->theme_name = $theme->Name;
            
            // Check parent theme if child theme is active
            if ($parent_theme = $theme->parent()) {
                $parent_name = $parent_theme->Name;
                
                // Detect Avada (parent or child theme)
                if ('Avada' == $this->theme_name || 'Avada' == $parent_name) {
                    $this->is_avada = true;
                }
            } else {
                // Direct theme check
                if ('Avada' == $this->theme_name) {
                    $this->is_avada = true;
                }
            }
        }
    }

    /**
     * Register compatibility filters based on detected theme
     */
    private function registerFilters()
    {
        // Avada Theme (Fusion Builder)
        if ($this->is_avada) {
            add_filter('presspermit_unfiltered', [$this, 'fltAvadaUnfiltered'], 5, 2);
        }
    }

    /**
     * Avada: Bypass filtering to prevent WP_Error objects in Fusion Panel
     * 
     * When Fusion_Panel->get_archive_options() queries terms/posts during 'wp' action,
     * permission filtering can return WP_Error objects instead of empty arrays,
     * causing undefined property errors.
     * 
     * @param bool $is_unfiltered Current unfiltered status
     * @param array $args Additional arguments
     * @return bool Modified unfiltered status
     */
    public function fltAvadaUnfiltered($is_unfiltered, $args)
    {
        // Only bypass on front-end when Fusion Panel is active
        if ($this->is_avada 
            && PWP::isFront()
            && class_exists('Fusion_Panel')
        ) {
            return true;
        }
        
        return $is_unfiltered;
    }

    /**
     * Helper: Check if specific theme is active
     * 
     * @param string $theme_name Theme name to check
     * @return bool True if theme matches
     */
    private function isTheme($theme_name)
    {
        return $this->theme_name === $theme_name;
    }
}

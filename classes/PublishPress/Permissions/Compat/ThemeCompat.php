<?php
namespace PublishPress\Permissions\Compat;

use PublishPress\PWP;

\defined('ABSPATH') || exit;

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
     * Preserve the caller's authorization when Fusion Panel is loaded.
     * Theme availability does not authorize a visitor to read restricted content.
     * 
     * @param bool $is_unfiltered Current unfiltered status
     * @param array $args Additional arguments
     * @return bool Modified unfiltered status
     */
    public function fltAvadaUnfiltered($is_unfiltered, $args)
    {
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


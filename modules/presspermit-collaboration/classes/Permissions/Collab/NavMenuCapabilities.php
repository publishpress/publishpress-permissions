<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class NavMenuCapabilities
{
    public static function adjustCapRequirement($reqd_caps)
    {
        // this function is called only if edit_theme_options is in reqd_caps but not in current_user->allcaps
        $key = array_search('edit_theme_options', $reqd_caps);
        if (false !== $key) {
            $tx = get_taxonomy('nav_menu');
            $reqd_caps[$key] = $tx->cap->manage_terms;
        }

        return $reqd_caps;
    }
}

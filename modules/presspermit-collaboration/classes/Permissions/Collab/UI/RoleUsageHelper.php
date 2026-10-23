<?php
namespace PublishPress\Permissions\Collab\UI;

\defined('ABSPATH') || exit;

class RoleUsageHelper
{
    public static function getUrlProperties(&$url, &$referer, &$redirect)
    {
        $url = apply_filters('presspermit_role_usage_base_url', 'admin.php');

        if (PWP::empty_REQUEST() && !empty($_SERVER['REQUEST_URI'])) {
            $referer = '<input type="hidden" name="wp_http_referer" value="' . esc_attr(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))) . '" />';
            
        } elseif ($wp_http_referer = PWP::REQUEST_url('wp_http_referer')) {
            $redirect = remove_query_arg(['wp_http_referer', 'updated', 'delete_count'], esc_url_raw($wp_http_referer));
            $referer = '<input type="hidden" name="wp_http_referer" value="' . esc_attr($redirect) . '" />';
        } else {
            $redirect = "$url?page=presspermit-role-usage";
            $referer = '';
        }
    }

    public static function other_notes($title = '', $extra_items = [])
    {
        $extra_items = (array)$extra_items;

        if (!$title)
            $title = __('Notes', 'press-permit-core');

        echo '<br /><h4 style="margin-top:0;margin-bottom:0.1em">' . esc_html($title) . ':</h4><ul class="pp-notes">';

        if ($extra_items) {
            echo '<li>' . implode('</li><li>', array_map('esc_html', $extra_items)) . '</li>';
        } else
            $hint = '';

        echo '<li>'
            . esc_html__("The 'posts' capabilities in a WP role determine its function as a Pattern Role for supplemental assignment to Permission Groups. When you assign the 'Author' pattern role for Pages, edit_posts and edit_published_posts become edit_pages and edit_published_pages.", 'press-permit-core')
            . '</li>'
            . '<li>'
            . esc_html__("Role capabilities defined for other post types (i.e. 'edit_others_pages', 'edit_things') are ignored unless Role Usage is set to Direct Assignment.", 'press-permit-core')
            . '</li>'
            . '<li>'
            . esc_html__("If one of the default roles is deleted from the WP database, it will remain available as a pattern role with default WP capabilities (but can be disabled here).", 'press-permit-core')
            . '</li>'
            . '</ul><br />';
    }
}

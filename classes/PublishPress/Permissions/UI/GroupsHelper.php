<?php
namespace PublishPress\Permissions\UI;

\defined('ABSPATH') || exit;

class GroupsHelper
{
    public static function getUrlProperties(&$url, &$referer, &$redirect)
    {
        $url = apply_filters('presspermit_groups_base_url', 'admin.php');

        if (PWP::empty_REQUEST() && !empty($_SERVER['REQUEST_URI'])) {
            $referer = '<input type="hidden" name="wp_http_referer" value="' . esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) . '" />';
            
        } elseif ($wp_http_referer = PWP::REQUEST_url('wp_http_referer')) {
            $redirect = esc_url_raw(remove_query_arg(['wp_http_referer', 'updated', 'delete_count'], esc_url_raw($wp_http_referer)));
            $referer = '<input type="hidden" name="wp_http_referer" value="' . esc_url_raw($redirect) . '" />';
        } else {
            $redirect = "$url?page=presspermit-groups";
            if ($group_variant = PWP::REQUEST_key('group_variant')) {
                $redirect = add_query_arg('group_variant', sanitize_key($group_variant));
            }
            $referer = '';
        }
    }
}

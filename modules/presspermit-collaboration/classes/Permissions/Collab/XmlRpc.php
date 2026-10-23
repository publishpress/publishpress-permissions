<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class XmlRpc
{
    function __construct() {
        if (version_compare('7.0', phpversion(), '>=')) {
            // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsRemoteFile
            $raw_post_data = file_get_contents('php://input');
        } else {
            // This is only used for PHP version < 7.0

            // phpcs:ignore PHPCompatibility.Variables.RemovedPredefinedGlobalVariables.http_raw_post_dataDeprecatedRemoved
            global $HTTP_RAW_POST_DATA;

            // phpcs:ignore PHPCompatibility.Variables.RemovedPredefinedGlobalVariables.http_raw_post_dataDeprecatedRemoved
            $raw_post_data = !empty($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : '';
        }

        add_action('presspermit_user_init', [$this, 'act_wlw_on_init']);

        add_filter('xmlrpc_methods', [$this, 'flt_adjust_methods']);
        add_filter('pre_post_category', [$this, 'flt_pre_post_category']);
    }

    function flt_adjust_methods($methods)
    {
        $methods['mt.setPostCategories'] = [$this, 'mt_set_categories'];
        return $methods;
    }

    function flt_pre_post_category($catids)
    {
        return apply_filters('presspermit_pre_object_terms', $catids, 'category');
    }

    // Override default method. Otherwise categories are unfilterable.
    function mt_set_categories($args)
    {
        global $wp_xmlrpc_server;
        $wp_xmlrpc_server->escape($args);

        $post_ID = (int)$args[0];
        $username = $args[1];
        $password = $args[2];
        $categories = $args[3];

        if (!$user = $wp_xmlrpc_server->login($username, $password))
            return $wp_xmlrpc_server->error;

        if (empty($categories))
            $categories = [];

        $catids = [];
        foreach ($categories as $cat) {
            $catids [] = $cat['categoryId'];
        }

        $catids = apply_filters('presspermit_pre_object_terms', $catids, 'category');

        do_action('xmlrpc_call', 'mt.setPostCategories');

        if (!get_post($post_ID))
            return new IXR_Error(404, esc_html__('Invalid post ID.'));

        if (!current_user_can('edit_post', $post_ID))
            return new IXR_Error(401, esc_html__('Sorry, you cannot edit this post.'));

        wp_set_post_categories($post_ID, $catids);

        return true;
    }

    function act_wlw_on_init()
    {
        global $wp_xmlrpc_server;

        if (isset($wp_xmlrpc_server->message)) {
            switch ($wp_xmlrpc_server->message->methodName) {
                case 'metaWeblog.newPost':
                    if (empty($wp_xmlrpc_server->message->params[3]['categories'])) {
                        $wp_xmlrpc_server->message->params[3]['categories'] = (array)get_option('default_category');
                    }
                    break;
            } // end switch
        }
    }
}

<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class PageFilters
{
    function __construct() {
        add_filter('presspermit_get_pages_intercept', [$this, 'fltGetPagesIntercept'], 10, 3);
        add_filter('presspermit_get_pages_args', [$this, 'fltGetPagesArgs']);
    }

    function fltGetPagesArgs($args)
    {
        if (!empty($args['no_pp_filter'])) {
            return $args;
        }

        if (!empty($args['name']) && ('parent_id' == $args['name'])) {
            global $post;

            if (!empty($post) && ($args['post_type'] == $post->post_type)) {
                if ($post->post_parent && ('auto-draft' != $post->post_status))
                    $args['append_page'] = get_post($post->post_parent);

                $args['exclude_tree'] = $post->ID;
            }
        }

        return $args;
    }

    function fltGetPagesIntercept($intercept, $results, $args)
    {
        // For the page parent dropdown, return no available selections for a published main page 
        // if the logged user isn't allowed to de-associate it from Main.
        if (!empty($args['name']) && ('parent_id' == $args['name'])) {
            global $post;

            if (!empty($post) && !$post->post_parent && !Collab::userCanAssociateMain($args['post_type'])) {
                $status_obj = get_post_status_object($post->post_status);
                if ($status_obj && ($status_obj->public || $status_obj->private)) {
                    return [];
                }
            }
        }

        return $intercept;
    }
}

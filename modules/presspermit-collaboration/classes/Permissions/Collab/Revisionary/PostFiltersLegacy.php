<?php
namespace PublishPress\Permissions\Collab\Revisionary;

\defined('ABSPATH') || exit;

class PostFiltersLegacy
{
    private $filtered_post_clauses_type = '';

    function __construct()
    {
        add_filter('presspermit_main_posts_clauses_types', [$this, 'flt_posts_clauses_object_types'], 10, 2);
        add_filter('presspermit_main_posts_clauses_where', [$this, 'flt_posts_clauses_where'], 10, 1);
        add_filter('presspermit_meta_cap', [$this, 'flt_meta_cap']);
    }

    function flt_posts_clauses_object_types($object_types)
    {
        global $wp_query;

        if ($wp_query->is_preview && defined('REVISIONARY_VERSION') && !empty($wp_query->query['p'])) {
            if ($_post = get_post($wp_query->query['p'])) {
                if ('revision' == $_post->post_type) {
                    if ($_type = get_post_field('post_type', $_post->post_parent)) {
                        $object_types = $_type;
                        $this->filtered_post_clauses_type = $_type;
                    }
                }
            }
        }

        return $object_types;
    }

    function flt_posts_clauses_where($objects_where)
    {
        if ($this->filtered_post_clauses_type) {
            $objects_where = str_replace("post_type = 'post'", "post_type = '$this->filtered_post_clauses_type'", $objects_where);

            $this->filtered_post_clauses_type = '';
        }

        return $objects_where;
    }

    // this is no longer used as a filter, but still called internally
    function fltPostsWhere($where, $args)
    {
        if (defined('REVISIONARY_VERSION') && !is_admin() && PWP::is_REQUEST('post_type', 'revision') 
        && (!PWP::empty_REQUEST('preview') || !PWP::empty_REQUEST('preview_id'))) {
            $matches = [];
            if (preg_match("/post_type = '([0-9a-zA-Z_\-]+)'/", $where, $matches)) {
                if ($matches[1]) {
                    global $wpdb;
                    $where = str_replace(
                        "$wpdb->posts.post_type = '{$matches[1]}'", 

                        "( $wpdb->posts.post_type = '{$matches[1]}' OR ( $wpdb->posts.post_type = 'revision'"
                        . " AND $wpdb->posts.post_status IN ('pending','future','inherit')"
                        . " AND $wpdb->posts.post_parent IN ( SELECT ID FROM $wpdb->posts WHERE post_type = '{$matches[1]}' ) ) )", 
                        
                        $where
                    );
                }
            }
        }

        return $where;
    }

    function flt_meta_cap($meta_cap)
    {
        if (defined('REVISIONARY_VERSION') && ('read_post' == $meta_cap) && !is_admin() && PWP::is_REQUEST('post_type', 'revision') 
        && (!PWP::empty_REQUEST('preview') || !PWP::empty_REQUEST('preview_id'))
        ) {
            $meta_cap = 'edit_post';
        }
        return $meta_cap;
    }
}

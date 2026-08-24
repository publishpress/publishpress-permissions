<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

class PostsListingNonAdministrator
{
    function __construct() {
        add_filter('posts_results', [$this, 'flt_posts_results'], 50, 3);

        if (defined('PRESSPERMIT_PAGE_LISTING_FLUSH_CACHE')) { // make this an opt-in to avoid conflicts with persistent caching
        	// prevent construction of erroneous view link url when relative hierarchy is preserved by remapping pages around inaccessable ancestors
        	add_action('all_admin_notices', [$this, 'act_flush_page_cache'], 50);
        }
    }

    public function act_flush_page_cache()
    {
        if (is_post_type_hierarchical(PWP::findPostType())) {
            if (function_exists('wp_cache_supports') && wp_cache_supports('flush_group')) {
                wp_cache_flush_group('posts');
            } else {
                wp_cache_flush();
            }
        }
    }

    public function flt_posts_results($results, $query_obj)
    {
        $post_type = PWP::findPostType();
        $post_type_obj = get_post_type_object($post_type);

        if (!empty($post_type_obj->hierarchical)) {
            require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');

            // array of all ancestor IDs for keyed page_id, with direct parent first
            $ancestors = \PressShack\Ancestry::getPageAncestors(0, $post_type);
            \PressShack\Ancestry::remapTree($results, $ancestors);
        }

        return $results;
    }
} // end class

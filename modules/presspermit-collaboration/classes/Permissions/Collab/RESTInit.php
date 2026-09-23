<?php
namespace PublishPress\Permissions\Collab;

class RESTInit
{
    var $post_terms = [];
    var $post_updated = [];

    function __construct() {
        add_action('init', [$this, 'add_post_type_filters'], 99);

        add_filter("rest_post_collection_params", [$this, 'post_collection_params'], 1, 2);

        add_action('post_updated', [$this, 'actLogPostUpdated']);
    }

    function add_post_type_filters() {
        foreach (presspermit()->getEnabledPostTypes() as $post_type) {
            add_filter("rest_{$post_type}_collection_params", [$this, 'post_collection_params'], 99, 2);

            if (is_post_type_hierarchical($post_type)) {
                add_filter("rest_{$post_type}_query", [$this, 'page_parent_query_args'], 10, 2);
            }

            add_action("rest_insert_{$post_type}", [$this, 'actLogPostTerms'], 10, 2);
            add_action("rest_after_insert_{$post_type}", [$this, 'actFilterPostTerms'], 10, 2);
        }
    }

    function actLogPostUpdated($post_id) {
        $this->post_updated[$post_id] = true;
    }

    function post_collection_params($params, $post_type_obj)
    {
        if (!presspermit()->isContentAdministrator()) {
            if (PWP::is_REQUEST('context', 'edit')) {
                $params['status']['default'] = '';
            }
        }

        return $params;
    }

    // log post terms already stored prior to REST action
    function actLogPostTerms($post, $request) {
        if (!isset($this->post_terms[$post->ID])) {
            $this->post_terms[$post->ID] = [];
        }

        foreach(get_object_taxonomies($post->post_type, 'objects') as $tx) {
            $this->post_terms[$post->ID][$tx->rest_base] = wp_get_object_terms($post->ID, $tx->name, ['fields' => 'ids']);
        }
    }

    // prevent unauthorized term assignment or removal
    function actFilterPostTerms($post, $request) {
        foreach(get_object_taxonomies($post->post_type, 'objects') as $tx) {
            if (!isset($request[$tx->rest_base])) {
                continue;
            }

            if (!empty($this->post_updated[$post->ID])) {
                $stored_terms = (isset($this->post_terms[$post->ID]) && isset($this->post_terms[$post->ID][$tx->rest_base])) ? $this->post_terms[$post->ID][$tx->rest_base] : [];
                $filter_args = compact('stored_terms');
            } else {
                $filter_args = [];
            }

            $filtered_terms = apply_filters('presspermit_pre_object_terms', $request[$tx->rest_base], $tx->name, $filter_args);

            if (array_diff($filtered_terms, $request[$tx->rest_base]) || array_diff($request[$tx->rest_base], $filtered_terms)) {
                wp_set_object_terms($post->ID, $filtered_terms, $tx->name);
            }
        }
    }

    function page_parent_query_args($args, $request) {
        $params = $request->get_params();

        if (is_array($params) && !empty($params['parent_exclude']) && !empty($params['context'] && ('edit' == $params['context']))) {
            $post_statuses = apply_filters(
                'presspermit_guten_parent_statuses', 
                PWP::getPostStatuses(['internal' => false, 'post_type' => $args['post_type']], 'names'),
                $args,
                $request
            );
            
            if ($is_administrator = presspermit()->isContentAdministrator()) {
                $pages = get_pages(
                    ['post_type' => $args['post_type'], 
                    'no_pp_filter' => 1,
                    'post_status' => $post_statuses,
                    ]
                );
            } else {
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/PostEdit.php');
                new \PublishPress\Permissions\Collab\UI\Dashboard\PostEdit();
                
                $pages = get_pages(
                    ['post_type' => $args['post_type'], 
                    'exclude' => (!empty($params['exclude'])) ? $params['exclude'] : [],    // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                    'parent_exclude' => (!empty($params['parent_exclude'])) ? $params['parent_exclude'] : [],
                    'required_operation' => (presspermit()->getOption('page_parent_editable_only')) ? 'edit' : 'associate',
                    'suppress_filters' => 0,
                    'name' => 'parent_id',
                    'post_status' => $post_statuses,
                    ]
                );
            }

            $include_page_ids = [];
            foreach($pages as $page) {
            	$include_page_ids []= $page->ID;
            }

            // always include existing page parent value as a dropdown option
            if (!$post_id = PWP::getPostID()) {
                if (!empty($args['post_parent__not_in'])) {
                    $parent_not_in = (array) $args['post_parent__not_in'];
                    if (count($parent_not_in) == 1) {
                        $post_id = reset($parent_not_in);
                    }
                }
            }

            if ($post_id) {
                if ($current_parent = get_post_field('post_parent', $post_id)) {
                    $include_page_ids []= $current_parent;
                }
            }

            $args['post__in'] = $include_page_ids;
            $args['post_status'] = $post_statuses;

            if (defined('PP_PAGE_PARENT_NOPAGING')) {
            	$args['nopaging'] = 1;
			}

            $args['orderby'] = presspermit()->getOption('page_parent_order') ? 'post_title' : 'menu_order';

            // results are already filtered, no further PressPermit filtering
            do_action('presspermit_refresh_administrator_check');

            add_filter('posts_results', [$this, 'page_parent_results'], 50, 3);

            if (presspermit()->getOption('page_parent_editable_only')) {
                \PublishPress\Permissions\REST::instance()->params['getpages_filtering'] = true;
            }
        }

        return $args;
    }

    function page_parent_results($results, $query_obj) {
        if (!$results) {
            return $results;
        }

        require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');
        
        $post_type = (isset($query_obj->query_vars['post_type'])) ? $query_obj->query_vars['post_type'] : 'page';

        $ancestors = \PressShack\Ancestry::getPageAncestors(0, $post_type); // array of all ancestor IDs for keyed page_id, with direct parent first

        $orderby = presspermit()->getOption('page_parent_order') ? 'post_title' : 'menu_order';

        $exclude = (!empty($query_obj->query_vars['post__not_in'])) ? $query_obj->query_vars['post__not_in'] : [];
        $remap_args = compact('exclude', 'orderby');

        \PressShack\Ancestry::remapTree($results, $ancestors, $remap_args);

        remove_filter('posts_results', [$this, 'page_parent_results'], 50, 3);

        return $results;
    }
}

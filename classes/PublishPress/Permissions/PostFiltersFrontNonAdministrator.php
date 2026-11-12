<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class PostFiltersFrontNonAdministrator
{
    public function __construct() { // Ubermenu: intermittant failure to display top level menu items
        if (!defined('PP_DISABLE_NAV_MENU_FILTER') && !defined('SHORTCODE_IN_MENUS_RES') && (!defined('UBERMENU_VERSION') || presspermit()->getOption('force_nav_menu_filter'))) {
            if (is_user_logged_in() || !presspermit()->getOption('anonymous_unfiltered')) {
            	add_filter('wp_get_nav_menu_items', [$this, 'fltNavMenuItems'], 50, 3);
            }
        }

        global $wp_query;
        if (is_object($wp_query) && method_exists($wp_query, 'is_tax') && $wp_query->is_tax()) {
            add_filter('posts_request', [$this, 'fltP2Request'], 1);
        }

        do_action('presspermit_post_filters_front_non_administrator');

        // Slidedeck temporary workaround: can't currently filter it properly in iframe or with ress
        add_filter('widget_display_callback', [$this, 'fltSlideDeckWidget'], 10, 3);
    }

    public function fltSlideDeckWidget($instance, $widget_obj, $args)
    {
        if (is_array($instance) && isset($instance['slidedeck_id'])) {
            foreach (array_keys($instance) as $key) {
                if (!strpos($key, 'slidedeck'))
                    continue;

                if (strpos($key, 'deploy_as_iframe'))
                    unset($instance[$key]);

                if (strpos($key, 'use_ress'))
                    unset($instance[$key]);
            }
        }

        return $instance;
    }

    // force scoping filter to process the query a second time, to handle the p2 clause imposed by WP core for custom taxonomy requirements
    public function fltP2Request($request)
    {
        if (strpos($request, 'p2.post_status')) {
            $request = apply_filters('presspermit_posts_request', $request, ['source_alias' => 'p2']);
        }

        return $request;
    }

    public function fltNavMenuItems($items, $menu_name, $args)
    {
        global $wpdb;
        $item_types = [];

        foreach ($items as $key => $item) {
            if (!is_scalar($item->type) || !is_scalar($item->object))
                continue;

            $item_types[$item->type][$item->object][$key] = $item->object_id;
        }

        $teaser_enabled = apply_filters('presspermit_teaser_enabled', false, 'post');

        // remove unreadable terms
        if (isset($item_types['taxonomy'])) {
            foreach ($item_types['taxonomy'] as $taxonomy => $item_ids) {
                $hide_empty = (defined('PP_NAV_MENU_SHOW_EMPTY_TERMS')) ? '0' : '1';
                
                $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => $hide_empty]);

                $okay_ids = [];
                foreach($terms as $term) {
                    $okay_ids []= $term->term_id;
                }

                if ($remove_ids = apply_filters('presspermit_nav_menu_hide_terms', array_diff($item_ids, $okay_ids), $taxonomy)) {
                    $item_types['taxonomy'][$taxonomy] = array_diff($item_types['taxonomy'][$taxonomy], $remove_ids);

                    foreach (array_keys($items) as $key) {
                        if (
                            !empty($items[$key]->type) && ('taxonomy' == $items[$key]->type)
                            && in_array($items[$key]->object_id, $remove_ids)
                        ) {
                            unset($items[$key]);
                        }
                    }
                }
            }
        }

        // remove or tease unreadable posts
        if (isset($item_types['post_type'])) {
            $post_types = array_keys($item_types['post_type']);
            $post_ids = Arr::flatten($item_types['post_type']);
        } else {
            $post_types = $post_ids = [];
        }

		$post_types = array_diff($post_types, apply_filters('presspermit_nav_menu_ignore_post_types', []));

        $pub_stati = PWP::getPostStatuses(['public' => true, 'post_type' => $post_types]);
        $pvt_stati = PWP::getPostStatuses(['private' => true, 'post_type' => $post_types]);
        $stati = array_merge($pub_stati, $pvt_stati);

        $clauses = array_fill_keys(['distinct', 'join', 'groupby', 'orderby', 'limits'], '');
        $clauses['fields'] = 'ID';

        $clauses['where'] = "AND post_type IN ( '" . implode("','", array_map('sanitize_key', $post_types)) . "' )"
            . " AND ID IN ( '" . implode("','", array_map('intval', $post_ids)) . "')"
            . " AND post_status IN ('" . implode("','", array_map('sanitize_key', $stati)) . "')";

        $clauses = apply_filters(
            'presspermit_posts_clauses',
            $clauses,
            ['post_types' => $post_types, 'skip_teaser' => true, 'required_operation' => 'read', 'force_types' => true]
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $okay_ids = $wpdb->get_col(
            "SELECT {$clauses['distinct']} ID FROM $wpdb->posts {$clauses['join']} WHERE 1=1 {$clauses['where']}"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        foreach ($post_types as $_post_type) {
            $remove_ids = array_diff($item_types['post_type'][$_post_type], $okay_ids);
            if ($remove_ids = apply_filters('presspermit_nav_menu_hide_posts', $remove_ids, $items, $_post_type)) {
                foreach (array_keys($items) as $key) {
                    if (
                        !empty($items[$key]->type) && ('post_type' == $items[$key]->type)
                        && in_array($items[$key]->object_id, $remove_ids)
                    ) {
                        unset($items[$key]);
                    }
                }
            }
        }

        return $items;
    }
}

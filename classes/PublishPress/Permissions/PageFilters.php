<?php

namespace PublishPress\Permissions;

// note: get_pages() hook is set by Hooks

/**
 * Replace the output from WP get_pages() with this filterable equivalent,
 * since get_pages() does not yet provide arg and query hooks
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2024, PublishPress
 *
 */
class PageFilters
{
    // PP-filtered equivalent to WP core get_pages
    // Lack of query filters creates need to replicate the whole function  
    //
    public static function fltGetPages($results, $args = [])
    {
        global $wpdb;

        // === BEGIN PP ADDITION: global var; various special case exemption checks ===
        //
        global $current_user, $pagenow;

        if (!empty($args['no_pp_filter'])) {
            return $results;
        }

        $results = (array)$results;

        // buffer titles in case they were filtered previously
        $titles = Arr::getPropertyArray($results, 'ID', 'post_title');

        // depth is not really a get_pages arg, but remap exclude arg to exclude_tree if wp_list_terms called with depth=1
        if (!empty($args['exclude']) && empty($args['exclude_tree']) && !empty($args['depth']) && (1 == $args['depth'])) {
            if ((is_array($args['exclude']) && count($args['exclude']) > 1) 
            || (!is_array($args['exclude']) && (0 !== strpos($args['exclude'], ',')))
            ) {
                // work around wp_list_pages() bug of attaching leading comma if a plugin uses wp_list_pages_excludes filter
                $args['exclude_tree'] = $args['exclude'];
            }
        }
        //
        // === END PP ADDITION ===
        // =================================

        // phpcs Note: The exclude and meta_value args supported here are for WP API compat, passing on any such arguments supplied by the get_terms() or get_pages() function
        $defaults = [
            'child_of' => 0,
            'sort_order' => 'ASC',
            'sort_column' => 'post_title',
            'hierarchical' => 1,
            'exclude' => [],    // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'include' => [],
            'meta_key' => '',
            'meta_value' => '', // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'authors' => '',
            'parent' => -1,
            'exclude_tree' => '',
            'exclude_parent' => '',
            'number' => '',
            'offset' => 0,
            'post_type' => 'page',
            'post_status' => 'publish',

            'append_page' => false, // Additional arguments not present in WP get_pages()
            'depth' => 0,
            'suppress_filters' => 0,
            'required_operation' => '',
            'alternate_operation' => '',
        ];

        // ================== Begin PressPermit Addition: support front-end optimization =========================
        $post_type = (isset($args['post_type'])) ? $args['post_type'] : $defaults['post_type'];

        $enabled_post_types = presspermit()->getEnabledPostTypes();

        if (defined('PP_UNFILTERED_FRONT') && PWP::isFront()) {
            if (defined('PP_UNFILTERED_FRONT_TYPES')) {
                $unfiltered_types = str_replace(' ', '', PP_UNFILTERED_FRONT_TYPES);
                $unfiltered_types = explode(',', constant($unfiltered_types));
                $enabled_post_types = array_diff($enabled_post_types, $unfiltered_types);
            } else {
                return $results;
            }
        }

        if (!in_array($post_type, $enabled_post_types, true))
            return $results;

        $is_parent_dropdown = !empty($args['name']) && in_array($args['name'], ['parent_id', 'post_parent'], true);

        if ($is_parent_dropdown
        && !presspermit()->moduleActive('collaboration')) {
            return $results;
        }

        if (PWP::isFront()) {
            // custom types are likely to have custom fields
            if (('page' == $post_type) && (defined('PP_LEAN_PAGE_LISTING') || defined('PP_GET_PAGES_LEAN'))) {
                $defaults['fields'] =
                    "$wpdb->posts.ID, $wpdb->posts.post_title, $wpdb->posts.post_parent, $wpdb->posts.post_date,"
                    . " $wpdb->posts.post_date_gmt, $wpdb->posts.post_status, $wpdb->posts.post_name,"
                    . " $wpdb->posts.post_modified, $wpdb->posts.post_modified_gmt, $wpdb->posts.guid,"
                    . " $wpdb->posts.menu_order, $wpdb->posts.comment_count";
            } else {
                $defaults['fields'] = "$wpdb->posts.*";
            }
        } else {
            // required for xmlrpc getpagelist method
            $defaults['fields'] = "$wpdb->posts.*";
        }
        // ========================================= End PressPermit Addition ====================================

        $r = wp_parse_args($args, $defaults);

        $args['number'] = (int)$r['number'];
        $args['offset'] = absint($r['offset']);
        $args['child_of'] = (int)$r['child_of'];

        // =========== PressPermit: Avoid conflict with ACF, other plugins
        if (
            is_admin() && !$is_parent_dropdown
            && false !== strpos($args['sort_column'], 'menu_order') && ('edit.php' != $pagenow)
            && !defined('PP_GET_PAGES_LEGACY_FILTER')
        ) {
            return $results;
        }

        foreach(['include', 'exclude', 'exclude_parent', 'exclude_tree', 'authors'] as $var) {
            // PressPermit: need to maintain explicitly set array value of zero ("Only these: (none))", but ignore scalar zero passed by third party code
			if (('include' == $var) && is_scalar($r['include']) && empty($r['include'])) {
				$r['include'] = [];
			} else {
            	$r[$var] = wp_parse_id_list($r[$var]);
        	}
        }

        if ($_filtered_vars = apply_filters('presspermit_get_pages_args', $r)) {  // PPCE filter modifies append_page, exclude_tree, sort_column
            foreach (array_keys($defaults) as $var) {
                if (isset($_filtered_vars[$var])) {
                    $$var = $_filtered_vars[$var];
                }
            }
        }

        // =========== PressPermit: workaround for CMS Tree Page View
        if ('ids' == $fields && isset($args['xsuppress_filters']) && !empty($args['ignore_sticky_posts'])) {
            return $results;
        }

		// =========== PressPermit: workaround for Page List plugin
        if (defined('PAGE_LIST_PLUGIN_VERSION') && !empty($args['item_spacing']) && did_action('get_template_part') && !did_action('get_footer')) {
            return $results;
        }

        // =========== PressPermit: workaround for CMS Tree Page View (passes post_parent instead of parent)
        if ((-1 == $parent) && isset($args['post_parent'])) {
            $args['parent'] = $args['post_parent'];
            $parent = $args['post_parent'];
        }

        // Make sure the post type is hierarchical
        if (!is_post_type_hierarchical($post_type)) {
            return $results;
        }

        // =========== PressPermit todo: review
        if (is_admin() && ('any' === $post_status)) {
            $post_status = '';
        }

        // Make sure we have a valid post status
        if (is_admin() && ('any' === $post_status)) {
            $post_status = '';
        }

        if ($post_status) {
            // note: don't just cast to array because variable type is checked in downstream logic
            if (!is_array($post_status) && strpos($post_status, ',')) {
                $post_status = explode(',', $post_status);
            }
                
            if (array_diff((array)$post_status, get_post_stati())) {
                return $results;
            }
        }

        // =========== PressPermit
        $intercepted_pages = apply_filters('presspermit_get_pages_intercept', false, $results, $args);
        if (is_array($intercepted_pages)) {
            return $intercepted_pages;
        }

        // =========== PressPermit
        if ($suppress_filters) {
            return $results;
        }

        // $args can be whatever, only use the args defined in defaults to compute the key
        $key = md5(wp_json_encode(compact(array_keys($defaults))));
        $last_changed = wp_cache_get_last_changed('posts');

        $cache_key = "pp_get_pages:$key:$last_changed";
        $cache = wp_cache_get($cache_key, 'posts');
        if (false !== $cache) {
            // Convert to WP_Post instances
            $pages = array_map('get_post', $cache);

            // ========= PressPermit filter
            $pages = apply_filters('presspermit_get_pages', $pages, $r);
            return $pages;
        }

        $inclusions = '';
        if (!empty($include)) {
            $child_of = 0; //ignore child_of, parent, exclude, meta_key, and meta_value params if using include
            $parent = -1;
            $exclude = [];
            $meta_key = '';
            $meta_value = '';
            $hierarchical = false;
            $incpages = wp_parse_id_list($include);
            if (!empty($incpages)) {
                if (defined('PRESSPERMIT_GET_PAGES_DISABLE_IN_CLAUSE') && PRESSPERMIT_GET_PAGES_DISABLE_IN_CLAUSE) {
	                foreach ($incpages as $incpage) {  // todo: change to IN clause after confirming no issues with PP query parsing
	                    if ($incpage) {
	                        if (empty($inclusions))
	                            $inclusions = ' AND ( ID = ' . intval($incpage) . ' ';
	                        else
	                            $inclusions .= ' OR ID = ' . intval($incpage) . ' ';
	                    }
	                }

                    if (!empty($inclusions)) {
                        $inclusions .= ')';
                    }
                } else {
                    $incpages = array_map('intval', $incpages);
                    $inclusions = ' AND ID IN (' . implode(",", $incpages) . ')';
            	}
        	}
        }

        $exclusions = '';
        if (!empty($exclude)) {
            $expages = wp_parse_id_list($exclude);
            if (!empty($expages)) {
                if (defined('PRESSPERMIT_GET_PAGES_DISABLE_IN_CLAUSE') && PRESSPERMIT_GET_PAGES_DISABLE_IN_CLAUSE) {
	                foreach ($expages as $expage) { // todo: change to IN clause after confirming no issues with PP query parsing
	                    if (empty($exclusions))
	                        $exclusions = ' AND ( ID <> ' . intval($expage) . ' ';
	                    else
	                        $exclusions .= ' AND ID <> ' . intval($expage) . ' ';
	                }

                    if (!empty($exclusions)) {
                        $exclusions .= ')';
                    }
                } else {
                    $expages = array_map('intval', $expages);
                    $exclusions = ' AND ID NOT IN (' . implode(",", $expages) . ')';
            	}
        	}
        }

        $author_query = '';
        if (!empty($authors)) {
            $post_authors = wp_parse_id_list($authors);

            if (!empty($post_authors)) {
                foreach ($post_authors as $post_author) {
                    //Do we have an author id or an author login?
                    if (0 == intval($post_author)) {
                        $post_author = get_user_by('login', $post_author);
                        if (empty($post_author)) {
                            continue;
                        }
                        if (empty($post_author->ID)) {
                            continue;
                        }
                        $post_author = $post_author->ID;
                    }

                    if ('' == $author_query) {
                        $author_query = $wpdb->prepare(' post_author = %d ', $post_author);
                    } else {
                        $author_query .= $wpdb->prepare(' OR post_author = %d ', $post_author);
                    }
                }
                if ('' != $author_query) {
                    $author_query = " AND ($author_query)";
                }
            }
        }

        $join = '';
        $where = "$exclusions $inclusions ";

        if ('' !== $meta_key || '' !== $meta_value) {                                           // todo: review
            $join = " INNER JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id";   // PressPermit modification: was LEFT JOIN in WP core

            // meta_key and meta_value might be slashed
            $meta_key = wp_unslash($meta_key);
            $meta_value = wp_unslash($meta_value);
            if ('' !== $meta_key) {
                $where .= $wpdb->prepare(" AND $wpdb->postmeta.meta_key = %s", $meta_key);
            }
            if ('' !== $meta_value) {
                $where .= $wpdb->prepare(" AND $wpdb->postmeta.meta_value = %s", $meta_value);
            }
        }

        if (is_array($parent)) {
            $post_parent__in = implode(',', array_map('absint', (array)$parent));
            if (!empty($post_parent__in)) {
                $where .= " AND post_parent IN ($post_parent__in)";
            }
        } elseif ($parent >= 0) {  // ========= PressPermit filter
            $where .= ' AND ' 
            . apply_filters(
                'presspermit_get_pages_parent',
                $wpdb->prepare('post_parent = %d ', $parent),
                $args
            );
        }

        $orderby_array = [];
        $allowed_keys = [
            'author',
            'post_author',
            'date',
            'post_date',
            'title',
            'post_title',
            'name',
            'post_name',
            'modified',
            'post_modified',
            'modified_gmt',
            'post_modified_gmt',
            'menu_order',
            'parent',
            'post_parent',
            'ID',
            'rand',
            'comment_count',
        ];

        foreach (explode(',', $sort_column) as $orderby) {
            $orderby = trim($orderby);
            
            if (!in_array($orderby, $allowed_keys, true))
                continue;

            switch ($orderby) {
                case 'menu_order':
                    break;
                case 'ID':
                    $orderby = "$wpdb->posts.ID";
                    break;
                case 'rand':
                    $orderby = 'RAND()';
                    break;
                case 'comment_count':
                    $orderby = "$wpdb->posts.comment_count";
                    break;
                default:
                    if (0 === strpos($orderby, 'post_'))
                        $orderby = "$wpdb->posts." . $orderby;
                    else
                        $orderby = "$wpdb->posts.post_" . $orderby;
            }

            $orderby_array[] = $orderby;
        }

        $sort_column = !empty($orderby_array) ? implode(',', $orderby_array) : "$wpdb->posts.post_title";

        $sort_order = strtoupper($r['sort_order']);

        if ('' !== $sort_order && !in_array($sort_order, ['ASC', 'DESC'])) {
            $sort_order = 'ASC';
        }

        // =================================== Begin PressPermit Modification: =============================
        // Allow pages of multiple statuses to be displayed (requires default status=publish to be ignored)
        //
        $where_post_type = $wpdb->prepare("post_type = %s", $post_type);
        $where_status = '';

        $is_front = PWP::isFront();

        if ($is_front && !empty($current_user->ID))
            $frontend_list_private = !defined('PP_SUPPRESS_PRIVATE_PAGES'); // currently using Page option for all hierarchical types
        else
            $frontend_list_private = false;

        $force_publish_status = !$frontend_list_private && ('publish' == $post_status);

        if ($is_front) {
            // since we will be applying status clauses based on content-specific roles, only a sanity check safeguard is needed when post_status is unspecified or defaulted to "publish"
            $safeguard_statuses = [];
            foreach (PWP::getPostStatuses(['internal' => false, 'post_type' => $post_type], 'object') as $status_name => $status_obj) {
                if ((!$is_front) || $status_obj->private || $status_obj->public) {
                    $safeguard_statuses[] = $status_name;
                }
            }
        }

        // WP core does not include private pages in query.  Include private statuses in anticipation of user-specific filtering
        if (is_array($post_status))
            $where_status = "AND post_status IN ('" . implode("','", array_map('sanitize_key', $post_status)) . "')";

        elseif ($post_status && (('publish' != $post_status) || ($is_front && !$frontend_list_private)))
            $where_status = $wpdb->prepare("AND post_status = %s", $post_status);

        elseif ($is_front)
            $where_status = "AND post_status IN ('" . implode("','", array_map('sanitize_key', $safeguard_statuses)) . "')";
        else {
            // todo: Revisionary 2.0 : query replacement workaround due to previous clause here: AND post_status NOT IN [internal=true]
            $where_status = "AND post_status IN ('" . implode("','", array_map('sanitize_key', get_post_stati(['internal' => false]))) . "')";
        }

        $where_id = '';

        if (is_admin() && in_array($pagenow, ['post.php', 'post-new.php'])) {
            global $post;
            if ($post)
                $where_id = "AND ID != $post->ID";
        }

        $where = "AND $where_post_type AND ( 1=1 $where_status $where $author_query $where_id )";
        $orderby = "ORDER BY $sort_column $sort_order";
        $limits = (!empty($number)) ? ' LIMIT ' . $offset . ',' . $number : '';
        $query = "SELECT $fields FROM $wpdb->posts $join WHERE 1=1 $where $orderby $limits";

        if (PWP::isFront() && apply_filters('presspermit_teaser_enabled', false, 'post', $post_type) && !defined('PP_TEASER_HIDE_PAGE_LISTING')) {
            // We are in the front end and the teaser is enabled for pages

            // TODO: move to Teaser
            $query = str_replace("post_status = 'publish'", " post_status IN ('" . implode("','", array_map('sanitize_key', $safeguard_statuses)) . "')", $query);

            // phpcs Note: query is constructed and sanitized above

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $pages = $wpdb->get_results($query);  // execute unfiltered query

            // Pass results of unfiltered query through the teaser filter.
            // If listing private pages is disabled, they will be omitted completely, but restricted published pages
            // will still be teased.  This is a slight design compromise to satisfy potentially conflicting user goals without yet another option
            $pages = apply_filters('presspermit_posts_teaser', $pages, $post_type, ['request' => $query, 'force_teaser' => true]);

            $tease_all = true;
        } else {
            $_args = ['skip_teaser' => true, 'retain_status' => $force_publish_status];

            $groupby = $distinct = '';

            if ((in_array($pagenow, ['post.php', 'post-new.php']) || (defined('REST_REQUEST') && REST_REQUEST))
            ) {
                $clauses = apply_filters(
                    'presspermit_get_pages_clauses',
                    compact('distinct', 'fields', 'join', 'where', 'groupby', 'orderby', 'limits'),
                    $post_type,
                    $args
                );
            } elseif ($is_parent_dropdown) {
                $clauses = compact('distinct', 'fields', 'join', 'where', 'groupby', 'orderby', 'limits');

                $required_operation = presspermit()->getOption('page_parent_editable_only') ? 'edit' : 'associate';
                $col_id = (strpos($clauses['where'], $wpdb->posts)) ? "$wpdb->posts.ID" : 'ID';
                $col_status = (strpos($clauses['where'], $wpdb->posts)) ? "$wpdb->posts.post_status" : 'post_status';

                if ($restriction_where = self::getRestrictionClause($required_operation, $post_type, compact('col_id'))) {
                    $clauses['where'] .= $restriction_where;
                }

                if ($additional_ids = presspermit()->getUser()->getExceptionPosts($required_operation, 'additional', $post_type)) {
                    if (empty($clauses['where'])) {
                        $clauses['where'] = 'AND 1=1';
                    }

                    $clauses['where'] = " AND ( ( 1=1 {$clauses['where']} ) OR ("
                        . " $col_id IN ('" . implode("','", array_unique($additional_ids)) . "')"
                        . " AND $col_status NOT IN ('" . implode("','", get_post_stati(['internal' => true])) . "') ) )";
                }
            } else {
                // Pass query through the request filter
                $_args['post_types'] = $post_type;

                if ($required_operation)
                    $_args['required_operation'] = $required_operation;
                else
                    $_args['required_operation'] = (PWP::isFront() && !presspermit_is_preview()) ? 'read' : 'edit';

                if (('associate' == $_args['required_operation']) && presspermit()->getOption('page_parent_editable_only')) {
                    $_args['required_operation'] = 'edit';
                }

                $rest_params = (defined('REST_REQUEST') && REST_REQUEST) ? \PublishPress\Permissions\REST::instance()->params : [];

                if (((('edit' == $_args['required_operation']) && (isset($args['post_parent']) || !empty($rest_params['exclude']) || !empty($rest_params['parent_exclude']))))
                || ('associate' == $alternate_operation)
                ) {  // workaround for CMS Page View
                    if (!presspermit()->getOption('page_parent_editable_only')) {
                        $_args['alternate_required_ops'] = ['associate'];
                    }
                }

                $clauses = apply_filters(
                    'presspermit_posts_clauses',
                    compact('distinct', 'fields', 'join', 'where', 'groupby', 'orderby', 'limits'),
                    $_args
                );
            }

            switch ($fields) {
                case 'ids':  // workaround for plugins (like CMS Tree Page View) which pass get_posts() args into 'get_pages' filter
                case 'id=>parent':
                    $_fields = "$wpdb->posts.*";
                    break;
                default:
                    $_fields = $clauses['fields'];
            }

            // Execute the filtered query

            // phpcs Note: query clauses constructed and sanitized above

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $pages = $wpdb->get_results(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT {$distinct} $_fields FROM $wpdb->posts {$clauses['join']} WHERE 1=1 {$clauses['where']} {$clauses['orderby']} {$clauses['limits']}"
            );
        }

        if (empty($pages)) {
            // alternate hook name (WP core already applied get_pages filter)
            return apply_filters('presspermit_get_pages', [], $r);
        }

        if ($child_of) {  // todo: review (WP core is $child_of || $hierarchical)
            $pages = get_page_children($child_of, $pages);
        }

        // restore buffered titles in case they were filtered previously
        Arr::restorePropertyArray($pages, $titles, 'ID', 'post_title');
        //
        // ==================================== End PressPermit Modification ================================

        $num_pages = count($pages);
        for ($i = 0; $i < $num_pages; $i++) {
            $pages[$i] = sanitize_post($pages[$i], 'raw');
        }

        // PressPermit note: WP core get_pages has already updated wp_cache and pagecache with unfiltered results.
        update_post_cache($pages);

        if ($exclude_tree) {
            $exclude = wp_parse_id_list($exclude_tree);
            foreach ($exclude as $id) {
                $children = get_page_children($id, $pages);  // PP note: okay to use unfiltered WP function here since it's only used for excluding
                foreach ($children as $child) {
                    $exclude[] = $child->ID;
                }
            }

            $num_pages = count($pages);
            for ($i = 0; $i < $num_pages; $i++) {
                if (in_array($pages[$i]->ID, $exclude)) {
                    unset($pages[$i]);
                }
            }
        }

        // ==================================== Begin PressPermit Modification ===================================
        //
        // Support a disjointed pages tree with some parents hidden
        if ($child_of || empty($tease_all) && (empty($depth) || ($depth > 1))) {  // if we're including all pages with teaser, no need to continue thru tree remapping
            require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');
           
            $ancestors = \PressShack\Ancestry::getPageAncestors(0, $post_type); // array of all ancestor IDs for keyed page_id, with direct parent first

            $orderby = $sort_column;

            $remap_args = compact('child_of', 'parent', 'exclude', 'depth', 'orderby');  // one or more of these args may have been modified after extraction 

            \PressShack\Ancestry::remapTree($pages, $ancestors, $remap_args);
        }

        if (!defined('PRESSPERMIT_GET_PAGES_IGNORE_EXCLUDE_ARGS')) { // installations that rely on pre-2.8.3 behavior can enable this constant
            foreach ($pages as $key => $page) {
                if ($exclude && in_array($page->ID, $exclude)) {
                    unset($pages[$key]);
                }

                if ($exclude_parent && in_array($page->post_parent, $exclude_parent)) {
                    unset($pages[$key]);
                }
            }
        }

        if (!empty($append_page) && !empty($pages)) {
            $found = false;
            foreach (array_keys($pages) as $key) {
                if ($append_page->ID == $pages[$key]->ID) {
                    $found = true;
                    break;
                }
            }

            if (empty($found))
                $pages[] = $append_page;
        }

        // re-index the array, just in case anyone cares
        $pages = array_values($pages);
        //
        // ===================================== End PressPermit Modification ====================================

        $page_structure = [];
        foreach ($pages as $page) {
            $page_structure[] = $page->ID;
        }

        wp_cache_set($cache_key, $page_structure, 'posts');

        // Convert to WP_Post instances
        $pages = array_map('get_post', $pages);

        // alternate hook name (WP core already applied get_pages filter)
        return apply_filters('presspermit_get_pages', $pages, $r);
    }

    public static function getRestrictionClause($operation, $post_type, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/Permissions.php');
        return DB\Permissions::getExceptionsClause($operation, $post_type, $args);
    }
}

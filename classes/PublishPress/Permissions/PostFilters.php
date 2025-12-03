<?php

namespace PublishPress\Permissions;

/**
 * Primary query filtering functions for posts / terms listing and access validation
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2024, PublishPress
 *
 */
class PostFilters
{
    private static $instance;

    var $skip_teaser;  // for use by templates making a direct call to query_posts for non-teased results
    var $doing_unfiltered_shortcode = false;
    var $anon_results = [];

    public static function instance($args = []) {
        if ( ! isset(self::$instance)) {
            self::$instance = new PostFilters();
            self::$instance->init();
        }

        return self::$instance;
    }

    private function __construct()
    {
        
    }

    private function init()
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/Permissions.php');

        add_filter('posts_clauses_request', [$this, 'fltPostsClauses'], 50, 2);

        // use late-firing filter so teaser filtering is also applied to sticky posts (passthrough for logged content administrator)
        add_filter('the_posts', [$this, 'fltThePosts'], 50, 2);

        add_action('parse_query', [$this, 'actParseQueryFollowup'], 99);
        
        add_filter('presspermit_posts_clauses', [$this, 'fltDoPostsClauses'], 50, 2);
        add_filter('presspermit_posts_request', [$this, 'fltDoPostsRequest'], 2, 2);
        add_filter('presspermit_posts_where', [$this, 'fltPostsWhere'], 10, 2);
        
        add_filter('posts_distinct', [$this, 'fltPostsDistinct'], 10, 2);

        add_filter('presspermit_force_post_metacap_check', [$this, 'fltForcePostMetacapCheck'], 10, 2);

        add_filter('pre_do_shortcode_tag', function($do_tag, $tag, $attr, $m) {
			$this->doing_unfiltered_shortcode = in_array(
				$tag, 
				apply_filters('presspermit_unfiltered_shortcodes', ['fl_builder_insert_layout']),
				true
			);

            if ($this->doing_unfiltered_shortcode) {
                $this->doing_unfiltered_shortcode = apply_filters('presspermit_is_unfiltered_shortcode', $this->doing_unfiltered_shortcode, $tag, $attr, $m);
            }
			
			return $do_tag;
		}, 10, 4);
			
		add_filter('do_shortcode_tag', function($output, $tag, $attr, $m) {
			$this->doing_unfiltered_shortcode = false;
			
			return $output;
		}, 10, 4);

        do_action('presspermit_post_filters');
    }

    public function fltLogAnonResults($results)
    {
        $this->anon_results = $results;
        return $results;
    }

    // enable PP to grant read permissions to Anonymous users for private posts
    public function fltReinstateAnonResults($posts)
    {
        global $wp_query;
        if ($wp_query->is_single || $wp_query->is_page) {
            if ($this->anon_results) {
            	$posts = $this->anon_results;
			}
        }

        return $posts;
    }

    public function actParseQueryFollowup($query_obj = false)
    {
        // avoid unexpected query behavior due to external calling of $wp_query->get_queried_object()
        if ($query_obj && isset($query_obj->queried_object_id) && !$query_obj->queried_object_id) {
            unset($query_obj->queried_object);
            unset($query_obj->queried_object_id);
        }

        if (defined('PP_ALL_ANON_FULL_EXCEPTIONS') || presspermit()->moduleActive('file-access')) {
            global $current_user;
            if (empty($current_user->ID)) {
                add_filter('posts_results', [$this, 'fltLogAnonResults']);
                add_filter('the_posts', [$this, 'fltReinstateAnonResults']);
            }
        }
    }

    public function fltPostsDistinct($distinct, $query_obj) {
        if (!$distinct && defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
            $distinct = 'DISTINCT';
        }

        return $distinct;
    }

    public function fltForcePostMetacapCheck($force, $args)
    {
        $defaults = ['is_post_cap' => false, 'item_id' => 0, 'orig_cap' => '', 'item_type' => '', 'op' => '', 'orig_reqd_caps' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if ($is_post_cap && $item_id) {
            $_item_type = self::postTypeFromCaps((array)$orig_cap);
            $type_obj = get_post_type_object($_item_type);

            if (!$op) {
                $base_caps = self::getBaseCaps((array)$orig_cap, $_item_type);
                $base_cap = reset($base_caps);
                $op = apply_filters('presspermit_cap_operation', 'edit', $base_cap, $_item_type);
            }

            if ($type_obj && in_array($orig_cap, [$type_obj->cap->edit_others_posts], true)) {
                $orig_cap = "{$op}_post";  // honor literal capability check if user passes corresponding metacap check for current item
                return $orig_cap;
            }
        }

        return $force;
    }

    private function getTeaserPostTypes($post_types, $args = [])
    {
        if (
        	(is_admin() && (!defined('DOING_AJAX') || ! DOING_AJAX))
        	|| presspermit()->isContentAdministrator() || !empty($args['skip_teaser'])
            || defined('XMLRPC_REQUEST') || (defined('REST_REQUEST') && REST_REQUEST)
        ) {
            return [];
        }

        return apply_filters('presspermit_teased_post_types', [], $post_types, $args);
    }

    public function fltPostsClauses($clauses, $_wp_query = false, $args = [])
    {
        global $pagenow, $current_user;

        if ($this->doing_unfiltered_shortcode) {
			return $clauses;
		}

        if (defined('REST_REQUEST')) {
        	if (class_exists('PublishPress\Permissions\REST') && !empty(\PublishPress\Permissions\REST::instance()->params['getpages_filtering'])) {
        		$rest_getpages_filtering = true;	
        	}
        }

        // Gallery block in Gutenberg editor: error loading Image Size dropdown options
        if (defined('REST_REQUEST') && empty($rest_getpages_filtering)
        && empty($_POST) && (!isset($_SERVER['REQUEST_METHOD']) || ('GET' == $_SERVER['REQUEST_METHOD']))  // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
        && !PWP::empty_REQUEST('context') && ('edit' == PWP::REQUEST_key('context'))
        ) {
            return $clauses;
        }

        $pp = presspermit();

        if (defined('PUBLISHPRESS_VERSION') && did_action('publishpress_notifications_trigger_workflows')) {
			return $clauses;
		}

        $args['query_obj'] = $_wp_query;

        if (!empty($args['pp_unfiltered']) && !defined('PRESSPERMIT_FORCE_POST_FILTERING')) {
            return $clauses;
        }

        if ($pp->isUserUnfiltered($current_user->ID, $args) && 
            (
            !is_admin() || 
            (($pagenow != 'nav-menus.php') && (!defined('DOING_AJAX') || !DOING_AJAX || !PWP::is_REQUEST('action', ['menu-get-metabox', 'menu-quick-search'])))
            )
        ) { // need to make private items selectable for nav menus
            return $clauses;
        }

        $action = PWP::REQUEST_key('action');

        if (
            defined('PP_MEDIA_LIB_UNFILTERED') && (('upload.php' == $pagenow)
                || (defined('DOING_AJAX') && DOING_AJAX && in_array($action, ['query-attachments', 'mla-query-attachments'])))
        ) {
            return $clauses;
        }

        if (
            // This solution is deprecated in favor of capability support for list_posts, list_others_pages, etc.  
            // But with removal of settings checkbox, need to maintain support for sites that rely on this previous workaround, which was enabled by constant PP_ADMIN_READONLY_LISTABLE
            //  (1) Sites that have the admin_hide_uneditable_posts option stored with false value
            //  (2) Sites that inadvertantly set options to defaults but want to restore this workaround. Now supporting an additional constant definition (disclosed by support as needed) rather than the checkbox UI.
            is_admin() && (!$pp->moduleActive('collaboration') || defined('PP_ADMIN_READONLY_LISTABLE'))
            && (!$pp->getOption('admin_hide_uneditable_posts') || defined('PP_ADMIN_NO_FILTER'))
        ) {
            return $clauses;
        }

        if (!empty($_wp_query) && !empty($_wp_query->query_vars)) {
            $args['query_vars'] = $_wp_query->query_vars;

            // don't filter wp_add_trashed_suffix_to_post_name_for_trashed_posts()
            if (
                !empty($args['query_vars']['post_status']) && ('trash' == $args['query_vars']['post_status'])
                && !empty($args['query_vars']['name']) && !empty($args['query_vars']['post__not_in'])
            ) {
                return $clauses;
            }
        }

        if (defined('DOING_AJAX') && DOING_AJAX) { // todo: separate function to eliminate redundancy with Find::findPostType()
            if (in_array($action, (array)apply_filters('presspermit_unfiltered_ajax', ['woocommerce_load_variations', 'woocommerce_add_variation', 'woocommerce_remove_variations', 'woocommerce_save_variations', 'us_ajax_grid']), true)) {
                return $clauses;
            }

            // Advanced Custom Fields (conflict with action=acf/fields/relationship/query_posts)
            $nofilter_prefixes = (array)apply_filters('presspermit_unfiltered_ajax_prefix', ['acf/']);

            foreach ($nofilter_prefixes as $prefix) {
                if (0 === strpos($action, $prefix)) {
                    return $clauses;
                }
            }

            $ajax_post_types = apply_filters(
                'presspermit_ajax_post_types',
                ['attachment' => 'attachment', 'ai1ec_doing_ajax' => 'ai1ec_event', 'tribe_calendar' => 'tribe_events']
            );

            foreach (array_keys($ajax_post_types) as $arg) {
                if (!PWP::empty_REQUEST($arg) || ($arg == $action)) {
                    $_wp_query->post_type = $ajax_post_types[$arg];
                    break;
                }
            }

            if (empty($_wp_query->query_vars['required_operation'])) {
                $_wp_query->query_vars['required_operation'] = 'read';  // default to requiring read access for all ajax queries
            }

            $edit_actions = apply_filters('presspermit_ajax_edit_actions', ['publishpress_calendar_get_data']);

            if (in_array($action, $edit_actions, true)) {
                $_wp_query->query_vars['required_operation'] = 'edit';
            } elseif (!empty($_wp_query->post_type) && is_scalar($_wp_query->post_type)) {
                $ajax_required_operation = apply_filters('presspermit_ajax_required_operation', []);

                foreach (array_keys($ajax_required_operation) as $arg) {
                    if ($arg == $_wp_query->post_type) {
                        $_wp_query->query_vars['required_operation'] = $ajax_required_operation[$arg];
                        break;
                    }
                }
            }
        }

        if ($_clauses = apply_filters('presspermit_posts_clauses_intercept', false, $clauses, $_wp_query, $args)) {
            return $_clauses;
        }

        $post_type = '';
        if (is_object($_wp_query)) {
            if (!empty($_wp_query->post_type)) {
                $post_type = $_wp_query->post_type;
            } elseif (isset($_wp_query->query) && isset($_wp_query->query['post_type'])) {
                $post_type = $_wp_query->query['post_type'];
            }
        }

        $post_types = apply_filters('presspermit_main_posts_clauses_types', $post_type);
        $clauses['where'] = apply_filters('presspermit_main_posts_clauses_where', $clauses['where']);

        if ('any' == $post_types) {
            $post_types = '';
        }

        $args['post_types'] = $post_types;

        if (empty($args['required_operation']) && isset($_wp_query->query_vars['required_operation'])) {
            $args['required_operation'] = $_wp_query->query_vars['required_operation'];
        }

        $clauses = $this->fltDoPostsClauses($clauses, $args);

        // On one test site, WP 6.6 stripped unpublished pages out of Gutenberg Page Parent selection results.
        // This only occurred with "Page Parent selection for Editable Pages only" enabled.
        // It may have been a quirk with the test site, but this workaround is left for activation by constant if needed.
        if (!empty($rest_getpages_filtering) 
        && defined('PP_PARENT_SELECTION_STATUS_WORKAROUND')
        ) {
            add_filter('posts_results', function($results) {
                foreach ($results as $k => $row) {
                    if (!in_array($results[$k]->post_status, ['publish'])) {
                        $results[$k]->post_status = 'publish';
                    }
                }

                return $results;
            }, 999);
        }

        return $clauses;
    }

    public function fltDoPostsClauses($clauses, $args = [])
    {
        $clauses['join'] = $this->fltPostsJoin($clauses['join'], $args);
        $args['join'] = $clauses['join'];

        $args['where'] = $clauses['where'];
        $clauses['where'] = apply_filters('presspermit_posts_clauses_where', $this->fltPostsWhere($clauses['where'], $args), $clauses, $args);

        return $clauses;
    }

    public function fltDoPostsRequest($request, $args = [])
    {
        global $current_user;

        if (presspermit()->isUserUnfiltered($current_user->ID, $args)) {
            return $request;
        }

        require_once(PRESSPERMIT_CLASSPATH . '/PostFiltersExtra.php');
        return PostFiltersExtra::fltPostsRequest($request, $args);
    }

    // Filter existing where clause
    public function fltPostsWhere($where, $args = [])
    {
        $defaults = [
            'post_types' => [],
            'source_alias' => false,
            'skip_teaser' => false,
            'retain_status' => false,
            'required_operation' => '',
            'alternate_required_ops' => false,
            'include_trash' => 0,
            'query_contexts' => [],
            'force_types' => false,
            'join' => '',
        ];
        $args = array_merge($defaults, (array)$args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        $src_table = ($source_alias) ? $source_alias : $wpdb->posts;
        $args['src_table'] = $src_table;

        $limit_post_types = ($post_types) ? (array)$post_types : false;

        // need to allow ambiguous object type for special cap requirements like comment filtering
        // include all defined otypes in the query if none were specified
        $post_types = ($post_types) ? (array)$post_types : presspermit()->getEnabledPostTypes();

        if (!$required_operation) {
            if (!PWP::empty_REQUEST('preview')) {
                $required_opertion = 'edit';
            } else {
                if (!$required_operation = apply_filters('presspermit_get_posts_operation', '', $args)) {
                    if (defined('REST_REQUEST') && REST_REQUEST) {
                        if (PWP::is_REQUEST('context', 'edit')) {
                            $required_operation = (!PWP::empty_REQUEST('parent_exclude') && !presspermit()->getOption('page_parent_editable_only')) ? 'associate' : 'edit'; // todo: better criteria
                        } else {
                            $required_operation = 'read';
                        }
                    } else {
                        $required_operation = (PWP::isFront() && !presspermit_is_preview()) ? 'read' : 'edit';
                    }
                }
            }
        }

        if (('associate' == $required_operation) && !defined('PRESSPERMIT_COLLAB_VERSION')) {
            return $where;
        }

        $args['required_operation'] = $required_operation;

        // Avoid superfluous clauses by limiting object types to those already specified in the query 
        if (preg_match("/post_type\s*=/", $where) || preg_match("/post_type\s*IN/", $where)) {  // post_type clause present? 
            foreach ($post_types as $key => $type) {
                if (
                    !preg_match("/post_type\s*=\s*'$type'/", $where)
                    && !preg_match("/post_type\s*IN\s*\([^)]*'$type'[^)]*\)/", $where)
                ) {
                    unset($post_types[$key]);
                }
            }
        }

        if (!$force_types)
            $post_types = array_intersect($post_types, presspermit()->getEnabledPostTypes());

        if (
            defined('PP_UNFILTERED_FRONT') && (
                ('read' == $required_operation)
                || (!$required_operation && PWP::isFront() && !presspermit_is_preview()))
        ) {
            if (defined('PP_UNFILTERED_FRONT_TYPES')) {
                $unfiltered_types = str_replace(' ', '', PP_UNFILTERED_FRONT_TYPES);
                $unfiltered_types = explode(',', constant($unfiltered_types));
                $post_types = array_diff($post_types, $unfiltered_types);
            } else {
                return $where;
            }
        }

        if (!$post_types) {
            return $where;
        }

        $user = presspermit()->getUser();

        // Since PressPermit can restrict or expand access regardless of post_status, query must be modified such that
        //  * the default owner inclusion clause "OR post_author = [user_id] AND post_status = 'private'" is removed
        //  * all statuses are listed apart from owner inclusion clause (and each of these status clauses is subsequently filtered to impose any necessary access limits)
        //  * a new filtered owner clause is constructed where appropriate
        //
        $where = preg_replace(
            str_replace('[user_id]', $user->ID, "/OR\s*($src_table.|)post_author\s*=\s*[user_id]\s*AND\s*($src_table.|)post_status\s*=\s*'([a-z0-9_\-]*)'/"),
            str_replace('[user_id]', $user->ID, "OR $src_table.post_status = '$3'"),
            $where
        );

        // If the passed request contains a single status criteria, maintain that status exclusively (otherwise include each available status)
        // (But not if user is anon and hidden content teaser is enabled.  In that case, we need to replace the default "status=publish" clause)
        $matches = [];
        if ($num_matches = preg_match_all("/{$src_table}.post_status\s*=\s*'([^']+)'/", $where, $matches)) {
            if (PWP::isFront() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_AJAX') && DOING_AJAX && PWP::is_REQUEST('action', ['menu-get-metabox', 'menu-quick-search']))) {
                if (PWP::isFront() || 'read' == $required_operation) {
                    $valid_stati = array_merge(
                        PWP::getPostStatuses(['public' => true, 'post_type' => $post_types]),
                        PWP::getPostStatuses(['private' => true, 'post_type' => $post_types])
                    );

                    if (is_single() || !empty($args['has_cap_check']) || defined('PP_FUTURE_POSTS_BLOGROLL')) {
                        $valid_stati['future'] = 'future';
                    }
                } else {
                    $valid_stati = PWP::getPostStatuses(['internal' => false, 'post_type' => $post_types], 'names', 'and', ['context' => 'edit']);
                }

                global $wp_query;

                if (in_array('attachment', $post_types, true) && (empty($wp_query->query_vars['s']) || presspermit()->getOption('media_search_results'))) {
                    $valid_stati[] = 'inherit';
                }

                $new_status_clause = "{$src_table}.post_status IN ('" . implode("','", $valid_stati) . "')";

                foreach ($matches[0] as $status_string) {
                    $where = str_replace($status_string, $new_status_clause, $where);  // we will append our own status clauses instead
                }
            }
        }

        if (1 == $num_matches) {
            // Eliminate a primary plugin incompatibility by skipping this preservation of existing single status requirements if we're on the front end and the requirement is 'publish'.  
            // (i.e. include private posts that this user has access to via PP roles or exceptions).  
            if ((!PWP::isFront() && (!defined('REST_REQUEST') || !REST_REQUEST) && (!defined('DOING_AJAX') || !DOING_AJAX || !PWP::is_REQUEST('action', ['menu-get-metabox', 'menu-quick-search'])))
                || ('publish' != $matches[1][0]) || $retain_status || defined('PP_RETAIN_PUBLISH_FILTER')
            ) {
                $limit_statuses = [];

                if ('inherit' != $matches[1][0]) {
                    $limit_statuses[$matches[1][0]] = true;
                }

                if ($limit_statuses = apply_filters('presspermit_posts_where_limit_statuses', $limit_statuses, $post_types)) {
                    $args['limit_statuses'] = $limit_statuses;
                }
            }
        }

        $args['post_types'] = $post_types;
        $args['limit_post_types'] = $limit_post_types;

        if (is_array($alternate_required_ops)) {
            if (!$required_operation) {
                $required_operation = ((PWP::isFront() || (defined('REST_REQUEST') && REST_REQUEST)) && !presspermit_is_preview())
                    ? 'read' : 'edit';
            }

            $alternate_required_ops = array_unique(array_merge($alternate_required_ops, (array)$required_operation));

            $pp_where = [];
            foreach ($alternate_required_ops as $op) {
                $args['required_operation'] = $op;
                $pp_where[$op] = '1=1' . $this->getPostsWhere($args);
            }

            $where_prepend = 'AND ( ' . Arr::implode('OR', $pp_where) . ' ) ';
        } else {
            $where_prepend = $this->getPostsWhere($args);
        }

        // Prepend so we don't disturb any orderby/groupby/limit clauses which are along for the ride
        if ($where_prepend) {
            $where = " $where_prepend $where";
        }

        return $where;
    } // end function fltPostsWhere

    // build a new posts request
    //
    public static function constructPostsRequest($clauses = [], $args = [])
    {
        global $wpdb;

        $args = apply_filters('presspermit_construct_posts_request_args', $args);

        $defaults = array_fill_keys(['distinct', 'join', 'where', 'groupby', 'orderby', 'limits'], '');
        $defaults['fields'] = '*';
        $clauses = array_merge($defaults, $clauses);

        $clauses['join'] = self::$instance->fltPostsJoin($clauses['join'], $args);
        $args['join'] = $clauses['join'];
        $clauses['where'] .= self::$instance->getPostsWhere($args);

        $clauses = apply_filters('presspermit_construct_posts_request_clauses', $clauses, $args);

        foreach (array_keys($defaults) as $var) {
            $$var = $clauses[$var];
        }

        $found_rows = ($limits) ? 'SQL_CALC_FOUND_ROWS' : '';

        return "SELECT $found_rows $distinct $fields FROM $wpdb->posts $join WHERE 1=1 $where $groupby $orderby $limits";
    }

    public function fltPostsJoin($join, $args = []) {
        if (!defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION') 
        || !version_compare(PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION, '3.8.0', '>=') 
        || defined('PRESSPERMIT_DISABLE_AUTHORS_JOIN')
        || (!empty($args['context']) && ('tally_term_counts' == $args['context']))
        ) {
            return $join;
        }
        
        $defaults = [
            'source_alias' => false,
            'src_table' => '',
        ];
        $args = array_merge($defaults, (array)$args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        $user = presspermit()->getUser();

        if (!$src_table) {
            $src_table = ($source_alias) ? $source_alias : $wpdb->posts;
        }

        $ppma_join = 
            " LEFT JOIN $wpdb->term_relationships AS ppma_tr ON ppma_tr.object_id = $src_table.ID"
          . " LEFT JOIN $wpdb->term_taxonomy AS ppma_tt ON ppma_tt.term_taxonomy_id = ppma_tr.term_taxonomy_id AND ppma_tt.taxonomy = 'author'"
          . " LEFT JOIN $wpdb->terms AS ppma_t ON ppma_t.term_id = ppma_tt.term_id AND ppma_t.slug = '$user->user_nicename'";

        if (false === strpos($join, $ppma_join)) {
            $join .= $ppma_join;
        }

        return $join;
    }

    // determines status usage, calls generate_where_clause() for each applicable post_type and appends resulting clauses
    //
    public function getPostsWhere($args)
    {
        $defaults = [
            'has_cap_check' => false,   // TRUE: this function call is to determine per-post editing / deletion capability;  FALSE: just determining which posts to list
            'post_types' => [],
            'source_alias' => false,
            'src_table' => '',
            'apply_term_restrictions' => true,
            'include_trash' => 0,
            'required_operation' => '',
            'limit_statuses' => false,
            'skip_teaser' => false,
            'query_contexts' => [],
            'force_types' => false,
            'limit_post_types' => false,
            'join' => '',
        ];
        $args = array_merge($defaults, (array)$args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        $user = presspermit()->getUser();

        if (!$src_table) {
            $src_table = ($source_alias) ? $source_alias : $wpdb->posts;
            $args['src_table'] = $src_table;
        }

        if (!$force_types)
            $post_types = array_intersect((array)$post_types, presspermit()->getEnabledPostTypes());

        $tease_otypes = array_intersect($post_types, self::$instance->getTeaserPostTypes($post_types, $args));

        if (!$required_operation) {
            if (defined('REST_REQUEST') && REST_REQUEST) {
                if (PWP::is_REQUEST('context', 'edit')) {
                    $required_operation = (!PWP::empty_REQUEST('parent_exclude')) ? 'associate' : 'edit'; // todo: better criteria
                } else {
                    $required_operation = (presspermit_is_preview()) ? 'edit' : 'read';
                }
            } else {
                $required_operation = (PWP::isFront() && (!presspermit_is_preview() || (count($post_types) == 1 && ('attachment' == reset($post_types))) )) ? 'read' : 'edit';
            }
            
            $args['required_operation'] = $required_operation;
        }

        // Deal with case of logged user viewing a post with normal view  link, no preview argument (require read OR edit permission)
        if ($user->ID && ('read' == $required_operation) && !defined('PRESSPERMIT_SIMPLIFY_READ_PERMISSIONS')) {
            if (!empty($args['query_vars'])) {
                $post_id = (!empty($args['query_vars']['page_id'])) ? $args['query_vars']['page_id'] : $args['query_vars']['p'];
            } else {
                $post_id = PWP::getPostID();
            }
            
            if ($post_id) {
                $caps = (array) map_meta_cap('read_post', $user->ID, $post_id);

                if ($type_obj = get_post_type_object(get_post_field('post_type', $post_id))) {
                    $edit_caps = [$type_obj->cap->edit_posts];

                    if (!empty($type_obj->cap->edit_others_posts)) {
                        $edit_caps []= $type_obj->cap->edit_others_posts;
                    }

                    if (array_intersect($caps, $edit_caps)) {
                        $required_operation = 'edit';
                        $args['required_operation'] = $required_operation;
                    }
                }
            }
        }

        if ($query_contexts) {
            $query_contexts = (array)$query_contexts;
        }

        $meta_cap = "{$required_operation}_post";

        if ('read' == $required_operation) {
            $use_statuses = array_merge(
                PWP::getPostStatuses(['public' => true, 'post_type' => $post_types], 'object'),
                PWP::getPostStatuses(['private' => true, 'post_type' => $post_types], 'object')
            );

            if (is_single() || !empty($args['has_cap_check']) || defined('PP_FUTURE_POSTS_BLOGROLL')) {
                $use_statuses['future'] = 'future';
            }

            foreach ($use_statuses as $key => $obj) {
                if (!empty($obj->exclude_from_search)) {  // example usage is bbPress hidden status
                    unset($use_statuses[$key]);
                }
            }

            if ($limit_statuses) {
                // don't block author from reading their own draft post on REST permission check
                $use_statuses = array_merge($use_statuses, $limit_statuses);
            }
        } else {
            $use_statuses = PWP::getPostStatuses(['internal' => false, 'post_type' => $post_types], 'object', 'and', ['context' => 'edit']);
        }

        $use_statuses = apply_filters('presspermit_query_post_statuses', $use_statuses, $args );

        global $wp_query;

        if (in_array('attachment', $post_types, true) && (empty($wp_query->query_vars['s']) || presspermit()->getOption('media_search_results'))) {
            $use_statuses['inherit'] = (object)[];
        }

        if (is_array($limit_statuses)) {
            $use_statuses = array_intersect_key($use_statuses, $limit_statuses);
        }

        if (empty($skip_teaser) && !array_diff($post_types, $tease_otypes)) {
            // All object types potentially returned by this query will have a teaser filter applied to results, so we don't need to use further query filtering
            $status_clause = "AND $src_table.post_status IN ('" . implode("','", array_keys($use_statuses)) . "')";
            return $status_clause;
        }

        if (!is_bool($include_trash)) {
            if (PWP::is_REQUEST('post_status', 'trash')) {
                $include_trash = true;
            }
        }

        $where_arr = [];

        $caps = class_exists('\PublishPress\StatusCapabilities\CapabilityFilters') ? \PublishPress\StatusCapabilities\CapabilityFilters::instance() : false;

		// legacy support
		if (!$caps) {
			$caps = class_exists('\PublishPress\Permissions\Statuses\CapabilityFilters') ? \PublishPress\Permissions\Statuses\CapabilityFilters::instance() : false;
		}

        $flag_meta_caps = !empty($caps);

        if ('read' == $required_operation && !defined('PP_DISABLE_UNFILTERED_TYPES_CLAUSE')) {
            $all_post_types = get_post_types(['public' => true, 'show_ui' => true], 'names', 'or');

            $unfiltered_post_types = array_diff($all_post_types, $post_types);

            if ($limit_post_types) {
                $unfiltered_post_types = array_intersect($unfiltered_post_types, (array)$limit_post_types);
            }

            // This proved necessary for WPML compat.
            // It ensures a default of normal visibility for public and user-authored posts when PP Filtering is not enabled for the post type.
            foreach ($unfiltered_post_types as $_post_type) {
                $where_arr[$_post_type] = "$src_table.post_type = '$_post_type' AND $src_table.post_status = 'publish'";
            }
        }

        foreach ($post_types as $post_type) {
            if (in_array($post_type, $tease_otypes, true) && empty($skip_teaser))
                $where_arr[$post_type] = "$src_table.post_type = '$post_type' AND 1=1";
            else {
                $have_site_caps = [];

                $type_obj = get_post_type_object($post_type);

                foreach (array_keys($use_statuses) as $status) {
                    if ('private' == $status) {
                        $cap_property = "{$required_operation}_private_posts";
                        if (empty($type_obj->cap->$cap_property)) {
                            continue;
                        }
                    }

                    if ('future' == $status) {
                        $cap_property = "edit_others_posts";
                        if (empty($type_obj->cap->$cap_property)) {
                            continue;
                        }
                    }

                    if ($flag_meta_caps) {
                        $caps->do_status_cap_map = true;
                    }

                    $reqd_caps = (array) self::$instance->mapMetaCap($meta_cap, $user->ID, 0, compact('post_type', 'status', 'query_contexts'));

                    if ($flag_meta_caps) {
                        $caps->do_status_cap_map = false;
                    }

                    if ($reqd_caps) {  // note: this function is called only for listing query filters (not for user_has_cap filter)
                        if ($missing_caps = apply_filters(
                            'presspermit_query_missing_caps',
                            array_diff($reqd_caps, array_keys(array_filter($user->allcaps))),
                            $reqd_caps,
                            $post_type,
                            $meta_cap
                        )) {
                            // Support list_posts, list_others_posts, list_pitch_pages etc. for listing uneditable posts on Posts screen
                            if (('edit' == $required_operation) && empty($args['has_cap_check']) && empty(presspermit()->flags['cap_filter_in_process'])) {
                                foreach($reqd_caps as $key => $cap) {
                                    if (in_array($cap, $missing_caps)) {
                                        $list_cap = str_replace('edit_', 'list_', $cap);

                                        if (!empty($user->allcaps[$list_cap])) {
                                            $reqd_caps[$key] = $list_cap;
                                        } else {
                                            // todo: API?
                                            if (defined('PUBLISHPRESS_REVISIONS_VERSION')) {
                                                $revise_cap = str_replace('edit_', 'revise_', $cap);

                                                if (!empty($user->allcaps[$list_cap])) {
                                                    $reqd_caps[$key] = $revise_cap;
                                                }
                                            }
                                        }
                                    }
                                }

                                if (!array_diff($reqd_caps, array_keys(array_filter($user->allcaps)))) {
                                    $have_site_caps['user'][] = $status;
                                }
                            }

                            // remove "others" and "private" cap requirements for post author
                            $owner_reqd_caps = self::getBaseCaps($reqd_caps, $post_type);

                            if (($owner_reqd_caps != $reqd_caps) && $user->ID) {
                                if (!array_diff($owner_reqd_caps, array_keys(array_filter($user->allcaps)))) {
                                    $have_site_caps['owner'][] = $status;
                                }
                            }
                        } else {
                            $have_site_caps['user'][] = $status;
                        }
                    }
                }

                $have_site_caps = apply_filters('presspermit_have_site_caps', $have_site_caps, $post_type, $args);

                if ($include_trash) {
                    if ($type_obj = get_post_type_object($post_type)) {
                        if ((('edit_post' == $meta_cap) && !empty($user->allcaps[$type_obj->cap->edit_posts]))
                            || (('delete_post' == $meta_cap) && !empty($user->allcaps[$type_obj->cap->delete_posts]))
                        ) {
                            if (!isset($type_obj->cap->delete_others_posts) || !empty($user->allcaps[$type_obj->cap->delete_others_posts])) {
                                $have_site_caps['user'][] = 'trash';
                            } else {
                                $have_site_caps['owner'][] = 'trash';
                            }
                        }
                    }
                }

                $where_arr[$post_type] = [];
                if (!empty($have_site_caps['user'])) {
                    $where_arr[$post_type]['user'] = "$src_table.post_status IN ('" . implode("','", array_unique($have_site_caps['user'])) . "')";
                }

                if (!empty($have_site_caps['owner'])) {
                    $parent_clause = ''; // PPCE may be set to "ID IN (...) OR " to enable post revisors to edit their own pending revisions
                    $args['post_type'] = $post_type;
                    $_vars = apply_filters('presspermit_generate_where_clause_force_vars', null, 'post', $args);
                    if (is_array($_vars)) {
                        if (isset($_vars['parent_clause'])) {
                            $parent_clause = $_vars['parent_clause'];
                        }
                    }

                    if (
                        !empty($args['skip_stati_usage_clause']) && !$limit_statuses
                        && !array_diff_key($use_statuses, array_flip($have_site_caps['owner']))
                    ) {
                        $where_arr[$post_type]['owner'] = "$parent_clause ( " . PWP::postAuthorClause($args) . " )";
                        
                    } else {
                        $where_arr[$post_type]['owner'] = "$parent_clause ( " . PWP::postAuthorClause($args) . " )"
                            . " AND $src_table.post_status IN ('" . implode("','", array_unique($have_site_caps['owner'])) . "')";
                    }
                }

                if (is_array($where_arr[$post_type])) {
                    if ($where_arr[$post_type]) {
                        $where_arr[$post_type] = Arr::implode('OR', $where_arr[$post_type]);
                        $where_arr[$post_type] = "1=1 AND ( " . $where_arr[$post_type] . " )";

                        // if PPCE is not activated, don't filter comments
                    } elseif (!in_array('comments', $args['query_contexts'], true) || !defined('REST_REQUEST') || !REST_REQUEST) {
                        $where_arr[$post_type] = '1=2';
                    }
                }

                if ($modified = apply_filters('presspermit_adjust_posts_where_clause', false, $where_arr[$post_type], $post_type, $args))
                    $where_arr[$post_type] = $modified;

                if ('attachment' == $post_type) {
                    if (('read' == $required_operation) || apply_filters('presspermit_force_attachment_parent_clause', false, $args)) {
                        $where_arr[$post_type] = "( " . self::$instance->appendAttachmentClause("$src_table.post_type = 'attachment'", [], $args) . " )";
                    }
                }

                if ('delete' == $required_operation) {
                    $const = "PP_EDIT_EXCEPTIONS_ALLOW_" . strtoupper($post_type) . "_DELETION";
                    if (defined('PP_EDIT_EXCEPTIONS_ALLOW_DELETION') || defined($const))
                        $required_operation = 'edit';
                }

                $where_arr[$post_type] = DB\Permissions::addExceptionClauses($where_arr[$post_type], $required_operation, $post_type, $args);
            }
        } // end foreach post_type

        if (!$pp_where = Arr::implode('OR', $where_arr))
            $pp_where = '1=1';

        // term restrictions which apply to any post type
        if ($apply_term_restrictions && !did_action('presspermit_bypass_term_restrictions')) {

            // account for term additions which apply to any post type (possibly based on a different taxonomy than restrictions)
            $additional_ttids = [];

            if (!defined('PP_RESTRICTION_PRIORITY')) {
                foreach (presspermit()->getEnabledTaxonomies(['object_type' => $post_type]) as $taxonomy) {
                    $tt_ids = $user->getExceptionTerms($required_operation, 'additional', $post_type, $taxonomy, ['merge_universals' => true]);

                    // merge this taxonomy exceptions with other taxonomies
                    $additional_ttids = array_merge($additional_ttids, $tt_ids);
                }
            }

            if ($term_exc_where = DB\Permissions::addTermRestrictionsClause(
                $required_operation,
                '',
                $src_table,
                [   'merge_universals' => true, 
                    'merge_additions' => true, 
                    'exempt_post_types' => $tease_otypes, 
                    'additional_ttids' => $additional_ttids, 
                    'apply_object_additions' => defined('PP_RESTRICTION_PRIORITY') ? false : PWP::findPostType(),
                    'join' => $join,
                ]
            )) {
                $pp_where = "( $pp_where ) $term_exc_where";
            }
        }

        if ($pp_where) {
            $pp_where = " AND ( $pp_where )";
        }

        if ($extra_exception_operations = apply_filters('presspermit_posts_where_extra_exception_ops', [], $args)) {
            foreach ($extra_exception_operations as $_op) {
                if ($required_operation != $_op) {
                    $_where_arr = [];

                    foreach ($post_types as $post_type) {
                        $_where_arr[$post_type] = DB\Permissions::addExceptionClauses('', $_op, $post_type, $args);
                    }

                    if ($_where = Arr::implode('OR', $_where_arr)) {
                        $pp_where = $pp_where . " AND ($_where)";
                    }
                }
            }
        }

        return $pp_where;
    }

    public function appendAttachmentClause($where, $clauses, $args)
    {
        static $busy = false;
        if ($busy) // recursion sanity check
            return '1=2';

        $busy = true;
        require_once(PRESSPERMIT_CLASSPATH . '/MediaQuery.php');
        $return = MediaQuery::appendAttachmentClause($where, $clauses, $args);
        $busy = false;

        return $return;
    }

    // currently only used to conditionally launch teaser filtering
    public function fltThePosts($results, $query_obj)
    {
        if (empty($this->skip_teaser)) {
            // won't do anything unless teaser is enabled for object type(s)
            $results = apply_filters('presspermit_posts_teaser', $results, '', ['request' => $query_obj->request, 'force_teaser' => true]);
        }

        return $results;
    }

    // wrapper for WP map_meta_cap, for use in determining caps for a specific post_status and user class (owner/non-owner), without a specific post id
    private function mapMetaCap($cap_name, $user_id = 0, $post_id = 0, $args = [])
    {
        if ($post_id)
            return map_meta_cap($cap_name, $user_id, $post_id);

        $defaults = ['is_author' => false, 'post_type' => '', 'status' => '', 'query_contexts' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $current_user;

        if (!$post_type)  // sanity check
            return (array)$cap_name;

        if (!$user_id)
            $user_id = $current_user->ID;

        // force desired status caps and others caps by passing a fake post into map_meta_cap
        $post_author = ($is_author) ? $user_id : -1;

        if (!$status)
            $status = (in_array($cap_name, ['read_post', 'read_page'], true)) ? 'publish' : 'draft';  // default to draft editing caps, published reading caps
        elseif ('auto-draft' == $status)
            $status = 'draft';

        $_post = (object)[
            'ID' => PHP_INT_MAX,
            'post_type' => $post_type,
            'post_status' => $status,
            'filter' => 'raw',
            'post_author' => $post_author,
            'query_contexts' => $query_contexts
        ];

        wp_cache_set(PHP_INT_MAX, $_post, 'posts');  // prevent querying for fake post
        presspermit()->meta_cap_post = $_post;
        
        // Avoid conflict with the combination of PublishPress Authors and WP_Privacy_Policy_Content check
        if (is_admin() && class_exists('WP_Privacy_Policy_Content')) {
            remove_action( 'admin_init', array( 'WP_Privacy_Policy_Content', 'text_change_check' ), 100 );
        }

        $return = array_diff(map_meta_cap($cap_name, $user_id, $_post->ID), [null]);  // post types which leave some basic cap properties undefined result in nulls
        wp_cache_delete(PHP_INT_MAX, 'posts');
        presspermit()->meta_cap_post = false;

		foreach($return as $k => $val) {
			if ('read' == $val) {
				$return[$k] = PRESSPERMIT_READ_PUBLIC_CAP;
			}	
		}

        if ((1 == count($return)) && ('do_not_allow' == reset($return)) && in_array($cap_name, ['read_post', 'read_page']) && ('publish' == $status)) {
            if ($type_obj = get_post_type_object($post_type)) {
                if (!empty($type_obj->public)) {
                    return PRESSPERMIT_READ_PUBLIC_CAP;
                }
            }
        }

        return $return;
    }

    public static function postTypeFromCaps($caps)
    {
        foreach (presspermit()->getEnabledPostTypes([], 'object') as $post_type => $type_obj) {
            if ($type_obj && isset($type_obj->cap) && array_intersect((array)$type_obj->cap, $caps))
                return $post_type;
        }
    
        return false;
    }

    // converts _others caps to equivalent base cap, for specified object type
    public static function getBaseCaps($reqd_caps, $post_type, $return_op = false)
    {
        $reqd_caps = (array)$reqd_caps;

        if ($type_obj = get_post_type_object($post_type)) {
            $replace_caps = [];

            if (isset($type_obj->cap->read_private_posts))
                $replace_caps[$type_obj->cap->read_private_posts] = PRESSPERMIT_READ_PUBLIC_CAP;

            $cap_match = [
                'edit_others_posts' => 'edit_posts',
                'delete_others_posts' => 'delete_posts',
                'edit_private_posts' => 'edit_published_posts',
                'delete_private_posts' => 'delete_published_posts'
            ];

            foreach ($cap_match as $status_prop => $replacement_prop) {
                if (isset($type_obj->cap->$status_prop) && isset($type_obj->cap->$replacement_prop)) {
                    $replace_caps[$type_obj->cap->$status_prop] = $type_obj->cap->$replacement_prop;
                }
            }

            $replace_caps = apply_filters('presspermit_base_cap_replacements', $replace_caps, $reqd_caps, $post_type);

            $replace_caps['edit_others_drafts'] = PRESSPERMIT_READ_PUBLIC_CAP;

            if (!empty($type_obj->cap->edit_others_posts)) {
                global $current_user;
                $list_others_cap = str_replace('edit_', 'list_', $type_obj->cap->edit_others_posts);

                if (!empty($current_user->allcaps[$type_obj->cap->edit_posts])) {
                    $replace_caps[$list_others_cap] = $type_obj->cap->edit_posts;
                } else {
                    $require_cap = str_replace('edit_', 'list_', $type_obj->cap->edit_posts);

                    // todo: API?
                    if (defined('PUBLISHPRESS_REVISIONS_VERSION')) {
                        $revise_cap = str_replace('edit_', 'revise_', $type_obj->cap->edit_posts);
                        
                        if (!empty($current_user->allcaps[$revise_cap])) {
                            $require_cap = $revise_cap;
                        }
                    }

                    $replace_caps[$list_others_cap] = $require_cap;
                }
            }

            $copy_others_cap = str_replace('edit_', 'copy_', $type_obj->cap->edit_others_posts);

            if (function_exists('rvy_get_option') && rvy_get_option('copy_posts_capability')) {
                $replace_caps[$copy_others_cap] = str_replace('edit_', 'copy_', $type_obj->cap->edit_posts);
            } else {
                $replace_caps[$copy_others_cap] = PRESSPERMIT_READ_PUBLIC_CAP;
            }

            foreach ($replace_caps as $cap_name => $base_cap) {
                $key = array_search($cap_name, $reqd_caps);
                if (false !== $key) {
                    $reqd_caps[$key] = $base_cap;
                    $reqd_caps = array_unique($reqd_caps);
                }
            }
        }

        return $reqd_caps;
    }

} // end class PostFilters

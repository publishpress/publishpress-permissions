<?php

namespace PublishPress\Permissions;

require_once(PRESSPERMIT_CLASSPATH . '/PostFilters.php');

/**
 * CapabilityFilters class
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2024, PublishPress
 *
 */
class CapabilityFilters
{
    private $memcache = []; // keys are tested_ids, okay_ids
    private $in_process = false;
    private $flag_defaults = [];
    private $meta_caps = [];
    private $cap_data_sources = [];

    public function __construct()
    {
        $this->flag_defaults = array_fill_keys(['memcache_disabled', 'force_memcache_refresh', 'cache_key_suffix'], false);

        $pp = presspermit();
        $pp->flags = array_merge($pp->flags, $this->flag_defaults);

        $this->meta_caps = apply_filters('presspermit_meta_caps', ['read_post' => 'read', 'read_page' => 'read']);

        $this->cap_data_sources = []; // array : [$cap_name] = source_name (but default to 'post' data source if unspecified)
        $this->cap_data_sources = apply_filters('presspermit_cap_data_sources', $this->cap_data_sources);

        // Since PP activation implies that this plugin should take custody
        // of access control, set priority high so we have the final say on has_cap filtering.
        // This filter will not mess with any caps which are not part of a PP role assignment or exception.
        add_filter('user_has_cap', [$this, 'fltUserHasCap'], 99, 3); // apply PP filter last

        add_filter('user_has_cap', function($caps) {
            $this->doing_cap_check = true;
            return $caps;
        }, 1, 1);

        add_filter('user_has_cap', function($caps) {
            $this->doing_cap_check = false;
            return $caps;
        }, PHP_INT_MAX, 1);

        if (defined('PP_DISABLE_CAP_CACHE')) {
            add_action('presspermit_has_post_cap_pre', [$this, 'disableCapCache'], 10, 4);
        }

        do_action('presspermit_cap_filters');
    }

    public function clearMemcache()
    {
        $this->memcache = [];
    }

    public function disableCapCache($pp_reqd_caps, $source_name, $post_type = '', $post_id = '')
    {
        presspermit()->flags['memcache_disabled'] = true;
    }

    public function fltConfirmRestReadable($rest_response, $handler, $request)
    { // filter 'rest_request_after_callbacks'
        require_once(PRESSPERMIT_CLASSPATH . '/RESTHelper.php');
        return RESTHelper::fltConfirmRestReadable($rest_response, $handler, $request);
    }

    public function fltConfirmRestReadableLegacy($rest_response, $rest_server, $request)
    { // filter 'rest_pre_dispatch'
        require_once(PRESSPERMIT_CLASSPATH . '/RESTLegacy.php');
        return RESTLegacy::fltPreDispatch($rest_response, $rest_server, $request);
    }

    // Capability filter applied by WP_User->has_cap (usually via WP current_user_can function)
    //
    // $wp_sitecaps = current user's site-wide capabilities
    // $reqd_caps = primitive capabilities being tested / requested
    // $args = array with:
    //      $args[0] = original capability requirement passed to current_user_can (possibly a meta cap)
    //      $args[1] = user being tested
    //      $args[2] = post id (could be a post_id, link_id, term_id or something else)
    //
    public function fltUserHasCap($wp_sitecaps, $orig_reqd_caps, $args)
    {
        global $pagenow;
        
        if ($this->in_process || !isset($args[0]))
            return $wp_sitecaps;

        $pp = presspermit();

        $this->in_process = true;

        $user = $pp->getUser();

        if ($args[1] != $user->ID) {
            $this->in_process = false;
            return $wp_sitecaps;
        }

        $args = (array)$args;
        $orig_cap = (isset($args[0])) ? sanitize_key($args[0]) : '';

        if (isset($args[2])) {
            if (is_object($args[2]))
                $args[2] = (isset($args[2]->ID)) ? $args[2]->ID : 0;
        } else
            $item_id = 0;

        $item_id = (isset($args[2])) ? (int) $args[2] : 0;

        // Apply presspermit_skip_postmeta_filtering filter under limited conditions to avoid perf issues with main queries
        if ($item_id && in_array($orig_cap, ['read_post', 'read_page']) && is_admin() 
        && !empty($pagenow) && !in_array($pagenow, ['edit.php', 'post.php', 'post-new.php'])
        && (!defined('REST_REQUEST') || !REST_REQUEST)
        && (!defined('DOING_AJAX') || !DOING_AJAX)
        && apply_filters('presspermit_skip_postmeta_filtering', false, $item_id, $orig_cap)
        ) {
            return $wp_sitecaps;
        }

        if ('read_document' == $orig_cap)  // todo: api
            $orig_cap = 'read_post';

        if (defined('REST_REQUEST') && REST_REQUEST && $item_id) {
            $orig_cap = apply_filters('presspermit_rest_post_cap_requirement', $orig_cap, $item_id);
        }

        if (('read_post' == $orig_cap) && (count($orig_reqd_caps) > 1 || (PRESSPERMIT_READ_PUBLIC_CAP != reset($orig_reqd_caps)))) {
            if (!$pp->isDirectFileAccess()) {
                // deal with map_meta_cap() changing 'read_post' requirement to 'edit_post'
                $types = get_post_types(['public' => true, 'show_ui' => true], 'object', 'or');
                foreach (array_keys($types) as $_post_type) {
                    if (array_intersect(
                        Arr::subset(
                            (array)$types[$_post_type]->cap,
                            ['edit_posts', 'edit_others_posts', 'edit_published_posts', 'edit_private_posts']
                        ),
                        $orig_reqd_caps
                    )) {
                        $orig_cap = 'edit_post';
                        break;
                    }
                }
            }
        }

        if (
            defined('PP_UNFILTERED_FRONT') && $item_id
            && (('read_post' == $orig_cap) || apply_filters('presspermit_skip_cap_filtering', false, $args))
        ) {
            if (defined('PP_UNFILTERED_FRONT_TYPES')) {
                $unfiltered_types = str_replace(' ', '', PP_UNFILTERED_FRONT_TYPES);
                $unfiltered_types = explode(',', constant($unfiltered_types));

                if (in_array(get_post_field('post_type', $item_id), $unfiltered_types, true)) {
                    $this->in_process = false;
                    return $wp_sitecaps;
                }
            } else {
                $this->in_process = false;
                return $wp_sitecaps;
            }
        }

        $meta_caps = $this->meta_caps;

        // Google Analytics for WordPress by MonsterInsights
        if (isset($args[3]) && is_string($args[3]) && (false !== strpos($args[3], 'monsterinsights'))) {
            if (!apply_filters('presspermit_force_postmeta_filtering', false, $args)) {
                unset($meta_caps['edit_post_meta']);
                unset($meta_caps['delete_post_meta']);
            }
        }

        if ((is_array($orig_cap) || !isset($meta_caps[$orig_cap]))	// Revisionary may pass array into args[0]
        && (('edit_posts' != reset($orig_reqd_caps)) || !presspermit()->doingEmbed())
        ) { 
            $item_type = '';
            $item_status = '';

            // If we would fail a straight post cap check, pass it if appropriate additions stored
            if (array_diff($orig_reqd_caps, array_keys(array_filter($wp_sitecaps)))) {
                $is_post_cap = false;

                if ($type_caps = array_intersect($orig_reqd_caps, array_keys($pp->capDefs()->all_type_caps))) {
                    if (
                        in_array($orig_cap, array_keys($meta_caps), true)
                        || in_array($orig_cap, array_keys($pp->capDefs()->all_type_caps), true)
                    ) {
                        $is_post_cap = true;

                        if (!$item_id && apply_filters('presspermit_do_find_post_id', true, $orig_reqd_caps, $args)) {
                            if ($item_id = PWP::getPostID())
                                $item_id = apply_filters('presspermit_get_post_id', $item_id, $orig_reqd_caps, $args);
                        }

                        // If a post id can be determined from url or POST, will check for exceptions on that post only
                        // Otherwise, credit basic read / edit_posts / delete_posts cap if corresponding addition is stored for any post/category of this type
                        if ($item_id) {
                            if ($_post = get_post($item_id)) {
                                $item_type = $_post->post_type;
                                $item_status = $_post->post_status;
                            }
                        } else {
                            $item_type = PostFilters::postTypeFromCaps($type_caps);
                            $item_status = '';
                        }

                        if ($item_type && !in_array($item_type, $pp->getEnabledPostTypes(), true)) {
                            $this->in_process = false;
                            return $wp_sitecaps;
                        }
                    }
                }

                $params = apply_filters('presspermit_user_has_cap_params', [], $orig_reqd_caps, compact('item_id', 'orig_cap', 'item_type'));

                if ($params) {
                    foreach (['type_caps', 'item_type', 'op', 'item_id', 'required_operation', 'is_post_cap'] as $_var) {
                        if (isset($params[$_var])) {
                            $$_var = $params[$_var];
                        }
                    }
                }

                if ($type_caps) {
                    static $buffer_qualified;
                    if (!isset($buffer_qualified) || $pp->flags['memcache_disabled']) {
                        $buffer_qualified = [];
                    }
                    $bkey = $item_type . $item_id . md5(wp_json_encode($type_caps));
                    if (!empty($buffer_qualified[$bkey])) {
                        $this->in_process = false;
                        return array_merge($wp_sitecaps, array_fill_keys($orig_reqd_caps, true));
                    }

                    if ($is_post_cap && apply_filters('presspermit_use_post_exceptions', true, $item_type)) {
                        $pass = false;
                        $base_caps = PostFilters::getBaseCaps($type_caps, $item_type);

                        if (count($base_caps) == 1) {
                            // This capability check is for a post type capability, but NOT a meta cap check (read_post / edit_post / delete_post)
                            // We are going to pass the capability check if it's for a post which the user has a corresponding Specific Permission assigned for.

                            $base_cap = reset($base_caps);
                            switch ($base_cap) {
                                case PRESSPERMIT_READ_PUBLIC_CAP:
                                case 'read':
                                    $op = 'read';
                                    break;
                                default:
                                    $op = apply_filters('presspermit_cap_operation', false, $base_cap, $item_type);
                            }

                            if ($op) {
                                $_args = compact('item_type', 'orig_reqd_caps');
                                $valid_stati = apply_filters(
                                    'presspermit_exception_stati',
                                    array_fill_keys(['', "post_status:$item_status"], true),
                                    $item_status,
                                    $op,
                                    $_args
                                );

                                $exc_post_type = apply_filters('presspermit_exception_post_type', $item_type, $op, $_args);

                                if ($additional_ids = $user->getExceptionPosts($op, 'additional', $exc_post_type, ['status' => true, 'merge_related_operations' => true, 'has_cap_check' => true])) {
                                    $additional_ids = Arr::flatten(array_intersect_key($additional_ids, $valid_stati));

                                    if (defined('PP_RESTRICTION_PRIORITY') && PP_RESTRICTION_PRIORITY) {
                                        if ($exclude_ids = $user->getExceptionPosts($op, 'exclude', $exc_post_type, ['status' => true])) {
                                            $exclude_ids = Arr::flatten(array_intersect_key($exclude_ids, $valid_stati));
                                            $additional_ids = array_diff($additional_ids, $exclude_ids);
                                        }
                                    }
                                }

                                if ($additional_ids) {
                                    $has_post_additions = apply_filters(
                                        'presspermit_has_post_additions',
                                        null,
                                        $additional_ids,
                                        $item_type,
                                        $item_id,
                                        compact('op', 'orig_reqd_caps')
                                    );

                                    if (is_null($has_post_additions)) {
                                        $has_post_additions = (!$item_id || in_array($item_id, $additional_ids));
                                    }
                                } else
                                    $has_post_additions = false;

                                if ($has_post_additions) {
                                    $pass = true;
                                } else {
                                    if ($enabled_taxonomies = $pp->getEnabledTaxonomies(['object_type' => $item_type])) {
                                        // This capability check is for a post type capability, but NOT a meta cap check (read_post / edit_post / delete_post)
                                        // Either the check is not for an individual post, or the user does not have a corresponding Specific Permission for that post.
                                        
                                        global $wpdb;

                                        static $listed_post_tt_ids;

                                        if (!isset($listed_post_tt_ids)) {
                                            $listed_post_tt_ids = [];
                                        }

                                        if ($item_id) {
                                            // We are going to pass the capability check if it's for a post that has categories / terms which the user has a corresponding Specific Permission assigned for.

                                            $post_tt_ids = [];

                                            // Did WP already cache post terms cached for all enabled taxonomies?
                                            foreach ($enabled_taxonomies as $taxonomy) {
                                                $_terms = get_object_term_cache($item_id, $taxonomy);

                                                if (false === $_terms) {
                                                    // If terms need to be queried for any taxonomy, force a single query for all taxonomies.
                                                    $post_tt_ids = false;
                                                    break;
                                                } else {
                                                    foreach ($_terms as $term) {
                                                        $post_tt_ids[] = $term->term_taxonomy_id; 
                                                    }
                                                }
                                            }

                                            // If not, did this function already cache this post's terms to static variable?
                                            if (false === $post_tt_ids) {
                                                if (isset($listed_post_tt_ids[$item_id])) {
                                                    $post_tt_ids = $listed_post_tt_ids[$item_id];
                                                }
                                            }

                                            // If not, execute a query to build the static cache of terms for all currently listed posts (or at least the currently checked post)
                                            if (false === $post_tt_ids) {
                                                if (isset($pp->listed_ids[$item_type]) && (!is_admin() || ('index.php' != $pagenow))) {  // there's too much happening on the dashboard to buffer listed IDs reliably.
                                                    $listed_ids = array_keys($pp->listed_ids[$item_type]);
                                                } else {
                                                    $listed_ids = [];
                                                }

                                                $listed_ids []= $item_id;
                                                $id_csv = implode("','", array_map('intval', array_unique($listed_ids)));

                                                $taxonomies_csv = implode("','", array_map('sanitize_key', $enabled_taxonomies));

                                                // Since the object terms are not cached, one direct query for all enabled taxonomies and listed IDs to avoid a get_terms() call. get_terms() would involve multiple filters and queries.
                                                // IN clauses are constructed and sanitized above.
                                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                                                $results = $wpdb->get_results(
                                                    "SELECT tr.object_id, tr.term_taxonomy_id FROM $wpdb->term_relationships AS tr"
                                                    . " INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
                                                    . " WHERE tt.taxonomy IN ('$taxonomies_csv') AND tr.object_id IN ('$id_csv')"           // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                                );

                                                foreach ($results as $row) {
                                                    if (!isset($listed_post_tt_ids[$row->object_id])) {
                                                        $listed_post_tt_ids[$row->object_id] = [];
                                                    }

                                                    $listed_post_tt_ids[$row->object_id] []= $row->term_taxonomy_id;
                                                }

                                                if (isset($listed_post_tt_ids[$item_id])) {
                                                    $post_tt_ids = $listed_post_tt_ids[$item_id];
                                                }
                                            }
                                        }

                                        if (!$item_id || !empty($post_ttids)) {
                                            // Pass the capability check if it's for a post that has categories / terms which the user has a corresponding Specific Permission assigned for,
                                            // OR if the capability check is not for an individual post, and the user has a coresponding Specific Permissions assigned for any term related to this post type

                                            foreach ($enabled_taxonomies as $taxonomy) {
                                                if ($additional_tt_ids = $user->getExceptionTerms($op, 'additional', $item_type, $taxonomy, ['status' => true])) {
                                                    $additional_tt_ids = Arr::flatten(array_intersect_key($additional_tt_ids, $valid_stati));
                                                }

                                                // merge in 'include' terms only if user has required caps in site-wide role
                                                if (!array_diff($orig_reqd_caps, array_filter(array_keys($user->allcaps)))) {
                                                    if ($include_tt_ids = $user->getExceptionTerms($op, 'include', $item_type, $taxonomy, ['status' => true])) {
                                                        $additional_tt_ids = array_merge(
                                                            $additional_tt_ids,
                                                            Arr::flatten(array_intersect_key($include_tt_ids, $valid_stati))
                                                        );
                                                    }
                                                }

                                                if ($additional_tt_ids) {
                                                    if (!$item_id || array_intersect($additional_tt_ids, $post_ttids)) {
                                                        $pass = true;
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        if (!isset($params)) {
                            $params = apply_filters(
                                'presspermit_user_has_cap_params',
                                [],
                                $orig_reqd_caps,
                                compact('item_id', 'orig_cap', 'item_type')
                            );
                        }

                        $pass = apply_filters('presspermit_credit_cap_exception', false, array_merge($params, compact('item_id')));
                    }

                    if ($pass) {
                        $buffer_qualified[$bkey] = true;
                        $this->in_process = false;
                        return array_merge($wp_sitecaps, array_fill_keys($orig_reqd_caps, true));
                    }

                    $force_post_metacap_check = apply_filters(
                        'presspermit_force_post_metacap_check',
                        false,
                        compact('is_post_cap', 'item_id', 'orig_cap', 'item_type', 'orig_reqd_caps')
                    );
                }
            } else {
                $params = apply_filters('presspermit_user_has_cap_params', [], $orig_reqd_caps, compact('item_id', 'orig_cap', 'item_type'));

                if ($params) {
                    if (empty($params['item_id'])) {
                        $params['item_id'] = ($item_id) ? $item_id : PWP::getPostID();
                    }

                    $wp_sitecaps = apply_filters('presspermit_user_has_caps', $wp_sitecaps, $orig_reqd_caps, $params);

                    foreach (['type_caps', 'item_type', 'op', 'item_id', 'required_operation', 'is_post_cap'] as $_var) {
                        if (isset($params[$_var])) {
                            $$_var = $params[$_var];
                        }
                    }
                }
            }

            if (!empty($force_post_metacap_check)) {
                $orig_cap = $force_post_metacap_check;
            } else {
                $this->in_process = false;
                return $wp_sitecaps;
            }
        }

        $this->in_process = true;
        $pp->flags['cap_filter_in_process'] = true;

        $source_name = (isset($this->cap_data_sources[$orig_cap])) ? $this->cap_data_sources[$orig_cap] : 'post';

        switch ($source_name) {
            case 'post':
                if (!isset($required_operation) ) {
                    $required_operation = (isset($this->meta_caps[$orig_cap])) ? $this->meta_caps[$orig_cap] : '';
                }

                $return = $this->fltUserHasPostCap($wp_sitecaps, $orig_reqd_caps, $args, compact('required_operation'));
                break;
            default:
                $return = $wp_sitecaps;
        }

        $this->in_process = false;
        unset($pp->flags['cap_filter_in_process']);

        $pp->flags = $this->flag_defaults;

        return $return;
    }

    // CapabilityFilters::fltUserHasPostCap
    //
    // The intent here is to add to $reqd_caps and/or $wp_sitecaps based on PP-assigned item attributes and role assignments
    //
    public function fltUserHasPostCap($wp_sitecaps, $orig_reqd_caps, $args, $pp_args)
    {
        // ================= EARLY EXIT CHECKS (if the provided reqd_caps do not need filtering or need special case filtering ==================
        global $pagenow;

        $pp = presspermit();

        if (isset($args[1]) && ($args[1] != $pp->getUser()->ID))
            return $wp_sitecaps;
        // =================================================== (end early exit checks) ======================================================

        // ========================================== ARGUMENT TRANSLATION AND STATUS DETECTION =============================================
        $post_id = (isset($args[2])) ? (int) $args[2] : PWP::getPostID();

        $post_type = PWP::findPostType($post_id); // will be pulled from object

        $pp_reqd_caps = array_map('sanitize_key', (array)$args[0]); // already cast to array

        //=== Allow PP modules or other plugins to modify some variables
        //

        $null_vars = null;
        $post_cap_args = [
            'post_type' => $post_type,
            'post_id' => $post_id,
            'user_id' => (int) $args[1],
            'required_operation' => $pp_args['required_operation'],
            'orig_reqd_caps' => $orig_reqd_caps
        ];

        if ($_vars = apply_filters_ref_array('presspermit_has_post_cap_vars', [$null_vars, $wp_sitecaps, $pp_reqd_caps, $post_cap_args])) {
            foreach (['post_type', 'post_id', 'pp_reqd_caps', 'return_caps', 'required_operation'] as $var) {
                if (isset($_vars[$var])) {
                    $$var = $_vars[$var];
                }
            }
        }

        if (!empty($return_caps)) {
            return array_merge($wp_sitecaps, $return_caps);
        }

        if (!$post_id || !in_array($post_type, $pp->getEnabledPostTypes(), true)) {
            return $wp_sitecaps;
        }

        $required_operation = $pp_args['required_operation'];

        // Note: At this point, we have a nonzero post_id...
        do_action('presspermit_has_post_cap_pre', $pp_reqd_caps, 'post', $post_type, $post_id); // cache clearing / refresh forcing can be applied here

        $memcache_disabled = $pp->flags['memcache_disabled'];

        // skip the memcache under certain circumstances
        if (!$memcache_disabled) {
			if ( 
            (!PWP::empty_POST() && ( 'post.php' == $pagenow ) && PWP::getTypeCap( $post_type, 'edit_post' ) == reset($pp_reqd_caps) )  				   // edit_post cap check on wp-admin/post.php submission   			
            || (!PWP::empty_GET('doaction') && in_array( reset($pp_reqd_caps), ['delete_post', PWP::getTypeCap( $post_type, 'delete_post' )], true ) )  // bulk post/page deletion is broken by hascap buffering
            ) { 
                $this->memcache = [];
                $memcache_disabled = true;
            }
        }

        // ============ QUERY for required caps on object id (if other listed ids are known, query for them also).  Cache results to static var. ===============
        $query_args = ['required_operation' => $required_operation, 'post_types' => $post_type, 'skip_teaser' => true];

        // generate a string key for this set of required caps, for use below in checking, caching the filtered results
        $cap_arg = ( 'edit_page' == $args[0] ) ? 'edit_post' : sanitize_key($args[0]); // minor perf boost on uploads.php, TODO: move to PPCE
        $capreqs_key = ($memcache_disabled) ? false : $cap_arg . $pp->flags['cache_key_suffix'] . md5(wp_json_encode($query_args));

        // Check whether this object id was already tested for the same reqd_caps in a previous execution of this function within the same http request
        if (!isset($this->memcache['tested_ids'][$post_type][$capreqs_key][$post_id]) || !empty($pp->flags['force_memcache_refresh'])) {
            global $wpdb;

            $pp = presspermit();

            // Don't filter the 'edit_post' request that Gutenberg applies right after trashing a post. WP will still block editing.
            if (('edit' == $required_operation) && $pp->doingREST()) {
                $_post = get_post($post_id);

                if (is_a($_post, 'WP_Post') && ('trash' == $_post->post_status)) {
                    return $wp_sitecaps;
                }
            }

            // Gutenberg editor: allow the Trash button to be displayed right after initial post save, before the status has been refreshed
            if (('delete' == $required_operation) && $pp->doingREST()) {
                $_post = get_post($post_id);

                if (is_a($_post, 'WP_Post') && ('auto-draft' == $_post->post_status) && ($_post->post_author == $pp->getUser()->ID)) {
                    return $wp_sitecaps;
                }
            }

            // If this cap inquiry is for a single item but multiple items are being listed, we will query for the original metacap on all items (mapping it for each applicable status) and buffer the results
            //
            // (Perf enhancement when listing display will check caps for each item to determine whether to display an action link)
            //  note: don't use wp_object_cache because it includes posts not present in currently displayed resultset listing page
            if (isset($pp->listed_ids[$post_type]) && (!is_admin() || ('index.php' != $pagenow))) {  // there's too much happening on the dashboard to buffer listed IDs reliably.
                $listed_ids = array_keys($pp->listed_ids[$post_type]);
            } else {
                $listed_ids = [];
            }

            // make sure our current post_id is in the query list
            $listed_ids[] = (int)$post_id;
            $listed_ids = array_unique($listed_ids);

            if (count($listed_ids) == 1) {
                $query_args['limit_statuses'] = [get_post_field('post_status', $post_id) => true];
            }

            $query_args['limit_ids'] = $listed_ids;
            $query_args['has_cap_check'] = sanitize_key($args[0]);
            $request = PostFilters::constructPostsRequest(['fields' => "$wpdb->posts.ID"], $query_args);

            // run the query
            $id_csv = implode("', '", array_map('intval', $listed_ids));
            $request .= " AND $wpdb->posts.ID IN ('$id_csv')";
            $qkey = md5($request);

            // a different has_cap inquiry may have generated the same query, so check cache before executing the query
            if (isset($this->memcache['okay_ids'][$qkey]) && !$memcache_disabled) {
                $okay_ids = $this->memcache['okay_ids'][$qkey];
            } else {
                // phpcs Note: All clauses in this request are sanitized above and or upstream in function constructPostsRequest.
                // This shared function ensures matching filtering by the 'posts_request' and 'user_has_cap' filters.

                // Direct database query to apply all relevant Permissions configuration to the currently 
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
                $okay_ids = $wpdb->get_col($request);

                if (!$memcache_disabled) {
                    $this->memcache['okay_ids'][$qkey] = $okay_ids;
                }
            }

            // update tested_ids cache to log filtered results for this post id, and possibly also for other listed IDs
            if (!$memcache_disabled) {
                foreach ($listed_ids as $_id) {
                    $this->memcache['tested_ids'][$post_type][$capreqs_key][$_id] = in_array($_id, $okay_ids);

                    // @todo: review, follow up with reporting user
                    // Custom Post Type UI: Specific Permissions for editing do not grant access under some configurations
                    if (is_admin()) {
                        if (defined('PRESSPERMIT_EXTRA_POST_CACHE_DELETE')) {
                            wp_cache_delete( $_id, 'posts' );
                        }
                    }
                }
            }

            $this_id_okay = in_array($post_id, $okay_ids);
        } else {
            // results of this has_cap inquiry are already stored (from another call within current http request)
            $this_id_okay = $this->memcache['tested_ids'][$post_type][$capreqs_key][$post_id];
        }

        if (!$this_id_okay) {
            if ('auto-draft' == get_post_field('post_status', $post_id)) {
                return $wp_sitecaps;
            }
        }

        if ($this_id_okay) {
            return array_merge($wp_sitecaps, array_fill_keys($orig_reqd_caps, true));
        } else {
            return array_diff_key($wp_sitecaps, array_fill_keys($orig_reqd_caps, true));  // return user's sitewide caps, minus the caps we were checking for
        }
    }
}

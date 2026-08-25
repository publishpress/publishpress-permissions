<?php

namespace PublishPress\Permissions;

/**
 * TermFilters class
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2024, PublishPress
 *
 */

class TermFilters
{
    private $parent_remap_enabled = true;
    private $count_filters_obj;
    private $disable_next_count_filtering = false;
    private $hierarchy_remap_taxonomies = [];

    public function __construct()
    {
        add_filter('get_terms_args', [$this, 'fltGetTermsArgs'], 50, 2);
        add_filter('get_terms_args', [$this, 'fltGetTermsRestoreArgs'], 51, 2);

        add_filter('terms_clauses', [$this, 'fltTermsClauses'], 50, 3);
        add_filter('get_terms', [$this, 'fltRemapTermHierarchy'], 20, 3);

        if (!presspermit()->isUserUnfiltered())
            add_filter('get_the_terms', [$this, 'fltGetTheTerms'], 10, 3);

        // Avoid redundant filtering when called from within a get_posts() call
        // (PP filters already use posts_clauses filter to apply term exceptions via join)
        add_action('pre_get_posts', [$this, 'actDisableFiltering']);

        // By default, don't remap term parent when get_terms() was called by wp_get_object_terms()
        add_filter('wp_get_object_terms_args', [$this, 'fltDisableTermRemap']);
        add_filter('get_object_terms', [$this, 'fltEnableTermRemap']);

        add_filter('acf/fields/taxonomy/query', [$this, 'fltFlagACFtaxonomyQuery'], 10, 3);
    }

    public function fltFlagACFtaxonomyQuery($args, $unused = [], $also_unused = []) {
        // Term count filtering is unneccessary for ACF Taxonomy fields, and causes them to prevent the selection of subcategories
        $this->disable_next_count_filtering = true;
        return $args;
    }

    public function fltDisableTermRemap($arg) {
        $this->parent_remap_enabled = false;

        if (!empty($this->count_filters_obj)) {
            $this->count_filters_obj->parent_remap_enabled = false;
        }

        return $arg;
    }

    public function fltEnableTermRemap($arg) {
        $this->parent_remap_enabled = true;

        if (!empty($this->count_filters_obj)) {
            $this->count_filters_obj->parent_remap_enabled = true;
        }

        return $arg;
    }

    public function actDisableFiltering()
    {
        presspermit()->flags['disable_term_filtering'] = true;
        add_action('posts_selection', [$this, 'actEnableFiltering']);
    }

    public function actEnableFiltering()
    {
        unset(presspermit()->flags['disable_term_filtering']);
        remove_action('posts_selection', [$this, 'actEnableFiltering']);
    }

    public function fltGetTheTerms($terms, $post_id, $taxonomy)
    {
        static $busy;
        if (!empty($busy)) {
            return $terms;
        }
        $busy = true;  // todo: necessary?

        $user = presspermit()->getUser();

        if ($terms) {
            $args = [];
            if ($this->skipFiltering((array)$taxonomy, $args))
                return $terms;

            if (defined('PP_GET_TERMS_SHORTCUT') && !did_action('wp_head'))  // experimental theme-specific workaround
                return $terms;

            if (!$post_id) {
                return $terms;
            }

            if (!$_post = get_post($post_id))
                return $terms;

            $operation = 'read';

            // Prevent Reading exceptions from being applied for Gutenberg term assignment checkbox visibility / selection
            if (presspermit()->doing_rest) {
                $operation = REST::instance()->operation;

            } elseif (!empty($_SERVER['HTTP_REFERER']) && false !== strpos(esc_url_raw($_SERVER['HTTP_REFERER']), PWP::admin_rel_url('post'))) {
                $operation = 'edit';
            }

            if (!apply_filters('presspermit_skip_the_terms_filtering', false, $post_id)) {
                if ($restrict_ids = $user->getExceptionTerms(
                    $operation,
                    'include',
                    $_post->post_type,
                    $taxonomy,
                    ['merge_universals' => true, 'return_term_ids' => true]
                )) {
                    foreach (array_keys($terms) as $key) {
                        if (!in_array($terms[$key]->term_id, $restrict_ids)) {
                            unset($terms[$key]);
                        }
                    }
                } elseif ($restrict_ids = $user->getExceptionTerms(
                    $operation,
                    'exclude',
                    $_post->post_type,
                    $taxonomy,
                    ['merge_universals' => true, 'return_term_ids' => true]
                )) {
                    if ($add_ids = $user->getExceptionTerms(
                        $operation,
                        'additional',
                        $_post->post_type,
                        $taxonomy,
                        ['merge_universals' => true, 'return_term_ids' => true]
                    )) {
                        $restrict_ids = array_diff($restrict_ids, $add_ids);
                    }

                    foreach (array_keys($terms) as $key) {
                        if (in_array($terms[$key]->term_id, $restrict_ids)) {
                            unset($terms[$key]);
                        }
                    }
                }
            }
        }

        $busy = false;
        return $terms;
    }

    private function skipFiltering($taxonomies, $args)
    {
        if (!$taxonomies = array_intersect($taxonomies, presspermit()->getEnabledTaxonomies())) {
            return true;
        }

        if (
            !empty($args['pp_no_filter']) || !empty(presspermit()->flags['disable_term_filtering'])
            || apply_filters('presspermit_terms_skip_filtering', false, $taxonomies, $args)
        ) {
            return true;
        }

        if (!empty($args['fields']) && ('id=>parent' == $args['fields'])) { // internal storage of {$taxonomy}_children
            return true;
        }

        if (!empty($args['object_ids']) && empty($args['required_operation'])) {  // WP 4.7 pushes wp_get_object_terms call through get_terms()
            return true;
        }

        if (defined('DOING_AJAX') && DOING_AJAX && method_exists('\PressShack\LibWP', 'REQUEST_key')) {
            if ($action = PWP::REQUEST_key('action')) {
                if (in_array(
                    $action, 
                    (array) apply_filters(
                        'presspermit_unfiltered_ajax',
                        ['woocommerce_load_variations', 'woocommerce_add_variation', 'woocommerce_remove_variations', 'woocommerce_save_variations', 'us_ajax_grid']
                    ), true)
                ) {
                    return true;
                }
            }
        }

        // Kriesi Enfold theme conflict on "More Posts" query
        if (
            defined('DOING_AJAX') && DOING_AJAX
            && PWP::is_REQUEST('action', apply_filters('presspermit_unfiltered_ajax_termcount', ['avia_ajax_masonry_more']))
        ) {
            return true;
        }
    }

    public function fltGetTermsRestoreArgs($args, $taxonomies)
    {
        if (!empty($args['actual_args']) && is_array($args['actual_args'])) {
            $args = array_merge($args, $args['actual_args']);
        }

        return $args;
    }

    public function fltGetTermsArgs($args, $taxonomies)
    {
        global $pagenow;

        if ($this->skipFiltering($taxonomies, $args))
            return $args;

        $defaults = ['skip_teaser' => false, 'post_type' => '', 'required_operation' => ''];
        $args = wp_parse_args($args, $defaults);

        if (!$this->disable_next_count_filtering) {
            if (('all' == $args['fields']) || $args['hide_empty'] || $args['pad_counts']) {
                if (apply_filters('presspermit_apply_term_count_filters', true, $args, $taxonomies) 
                && (empty($pagenow) || ('edit-tags.php' != $pagenow) || defined('PRESSPERMIT_LEGACY_ADMIN_TERM_COUNT_FILTER'))
                ) {
                    require_once(PRESSPERMIT_CLASSPATH . '/TermFiltersCount.php');
                    $this->count_filters_obj = TermFiltersCount::instance(['parent_remap_enabled' => $this->parent_remap_enabled]);
                    $args = $this->count_filters_obj->fltGetTermsArgs($args);
                }
            }
        }

        if (presspermit()->doing_rest) {
            $rest = \PublishPress\Permissions\REST::instance();
            
            if ($rest->is_posts_request) {
                if (empty($args['required_operation']) || ('assign' != $args['required_operation'])) {
                    if (!empty($_SERVER['HTTP_REFERER']) && false !== strpos(esc_url_raw($_SERVER['HTTP_REFERER']), PWP::admin_rel_url('post'))) {
                        $args['required_operation'] = 'edit';
                    } else {
                        $args['required_operation'] = $rest->operation;
                    }
                }
            } elseif (empty($args['required_operation'])) {
                if (!empty($rest->operation)) {
                    $args['required_operation'] = $rest->operation;
                } else {
                    $args['required_operation'] = ('WP_REST_Posts_Controller' == $rest->endpoint_class) ? 'assign' : 'manage';
                }

                if (!defined('PRESSPERMIT_LEGACY_TERM_FILTERS_ARGS')) {
                    $args['hide_empty'] = false;
                }
            }

            if (defined('PRESSPERMIT_REST_TERM_FILTER_SHOW_EMPTY')) {
                $args['hide_empty'] = false;
            }
        }

        return $args;
    }

    public function fltTermsClauses($clauses, $taxonomies, $args)
    {
        global $pagenow;

        if ($this->skipFiltering($taxonomies, $args))
            return $clauses;

        $user = presspermit()->getUser();

        if ($this->disable_next_count_filtering) {
            $this->disable_next_count_filtering = false;
        } else {
            if (('all' == $args['fields']) || $args['hide_empty'] || $args['pad_counts']) {
                // adds get_terms filter to adjust post counts based on current user's access and pad_counts setting
                if (apply_filters('presspermit_apply_term_count_filters', true, $args, $taxonomies)) {
                    require_once(PRESSPERMIT_CLASSPATH . '/TermFiltersCount.php');
                    $this->count_filters_obj = TermFiltersCount::instance(['parent_remap_enabled' => $this->parent_remap_enabled]);
                    $clauses = $this->count_filters_obj->fltTermsClauses($clauses, $args);
                }
            }
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            $args['required_operation'] = 'assign';
            
        } elseif (empty($args['required_operation']) && !defined('PP_ADMIN_TERMS_READONLY_LISTABLE') && (!defined('PP_ADMIN_READONLY_LISTABLE') || presspermit()->getOption('admin_hide_uneditable_posts'))) {
            if (in_array($pagenow, ['edit-tags.php', 'nav-menus.php'])) {
                $args['required_operation'] = 'manage';
            } else {
	            $args['required_operation'] = apply_filters(
	                'presspermit_get_terms_operation',
	                PWP::isFront() ? 'read' : 'assign',
	                $taxonomies,
	                $args
	            );
            }
        }

        if (!empty($args['required_operation']) && in_array($args['required_operation'], ['assign', 'manage']) 
        && !defined('PRESSPERMIT_LEGACY_TERM_FILTERS_ARGS')
        ) {
            $args['hide_empty'] = false;
        }

        if (presspermit()->isUserUnfiltered() && !defined('PRESSPERMIT_ALLOW_ADMIN_TERMS_FILTER')) {
            return $clauses;
        }

        // must consider all related post types when filtering terms list
        // NOTE: If hide_empty is true, additional filtering will be applied to the results based on a full posts query.  
        // Posts may have direct restrictions which make them inaccessable regardless of term restrictions.
        $all_excluded_ttids = [];
        $filtered_taxonomies = [];

        $enabled_types = presspermit()->getEnabledPostTypes();
        $required_operation = $args['required_operation'];

        foreach ($taxonomies as $taxonomy) {
            $excluded_ttids = $included_ttids = [];
            $any_non_inclusions = false;

            if (!in_array($required_operation, ['manage', 'associate'], true)) {
                $universal = [];
                $universal['include'] = $user->getExceptionTerms($required_operation, 'include', '', $taxonomy);

                // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                $universal['exclude'] = ($universal['include']) ? [] : $user->getExceptionTerms($required_operation, 'exclude', '', $taxonomy);
                $universal['additional'] = $user->getExceptionTerms($required_operation, 'additional', '', $taxonomy);

                $universal = apply_filters('presspermit_get_terms_universal_exceptions', $universal, $required_operation, $taxonomy, $args);

                if (defined('REST_REQUEST') && REST_REQUEST && ('assign' == $args['required_operation']) && !isset($args['object_type'])) {
                    // todo: WP Trac ticket for post_id or post_type argument in terms query a better solution
                    if (!empty($_SERVER['HTTP_REFERER'])) {
                        $referer = esc_url_raw($_SERVER['HTTP_REFERER']);

                        $admin_post_rel_url = preg_quote(
                            PWP::admin_rel_url('post.php'),
                            '/'
                        );

                        $admin_post_new_rel_url = preg_quote(
                            PWP::admin_rel_url('post-new.php'),
                            '/'
                        );

                        $matches = [];
                        preg_match("/$admin_post_rel_url\?post=([0-9]+)/", $referer, $matches);

                        if (!empty($matches[1])) {
                            $args['object_type'] = get_post_field('post_type', $matches[1]);

                        } elseif (false !== strpos($referer, $admin_post_new_rel_url)) {
                            preg_match("/$admin_post_new_rel_url\?post_type=([a-zA-Z_\-0-9]+)/", $referer, $matches);
                            
                            if (!empty($matches[1])) {
                                $args['object_type'] = $matches[1];
                            } else {
                                $args['object_type'] = 'post';
                            }
                        }
                    }
                }

                if (!empty($args['object_type']))
                    $exception_types = array_intersect((array)$args['object_type'], $enabled_types);
                else {
                    $tx = get_taxonomy($taxonomy);
                    $exception_types = array_intersect((array)$tx->object_type, $enabled_types);
                }
            } else {
                $universal = array_fill_keys(['include', 'exclude', 'additional'], []);
                $exception_types = [$taxonomy];
            }

            foreach ($exception_types as $post_type) {
                $additional_tt_ids = apply_filters(
                    'presspermit_get_terms_additional',
                    array_merge(
                        $universal['additional'],
                        $user->getExceptionTerms($required_operation, 'additional', $post_type, $taxonomy)
                    ),
                    $required_operation,
                    $post_type,
                    $taxonomy,
                    $args
                );

                foreach (['include', 'exclude'] as $mod_type) {
                    $args['additional_tt_ids'] = $additional_tt_ids;

                    $tt_ids = apply_filters(
                        'presspermit_get_terms_exceptions',
                        $user->getExceptionTerms($required_operation, $mod_type, $post_type, $taxonomy),
                        $required_operation,
                        $mod_type,
                        $post_type,
                        $taxonomy,
                        $args
                    );

                    // remove type-specific inclusions from universal exclusions
                    if ('include' == $mod_type) {
                        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                        $universal['exclude'] = array_diff($universal['exclude'], $tt_ids);
                    }

                    // merge type-specific exceptions with universal
                    if ($tt_ids = array_merge($universal[$mod_type], $tt_ids)) {
                        // add additional terms to includes set, and remove from exclude set (but only if include/exclude set exists to begin with)
                        if ($additional_tt_ids) {
                            $tt_ids = ('include' == $mod_type)
                                ? array_merge($tt_ids, $additional_tt_ids)
                                : array_diff($tt_ids, $additional_tt_ids);
                        }
                    }

                    if ($tt_ids) {
                        if ('include' == $mod_type) {
                            $included_ttids = array_merge($included_ttids, $tt_ids);

                            if (count($exception_types) > 1) {
                                continue 2;  // don't support simultaneous include and exclude terms for the same taxonomy (but do support term assign exclusion even if an edit inclusion is set for the same term)
                            }
                        } else {
                            $excluded_ttids[] = $tt_ids;
                            $any_non_inclusions = true;
                        }
                    } else {
                        $any_non_inclusions = true;

                        if ('exclude' == $mod_type)
                            $excluded_ttids[] = [];
                    }
                }
            }

            if ($excluded_ttids) {
                // don't exclude a term unless it is excluded for all post types
                $exc = $excluded_ttids[0];
                for ($i = 1; $i < count($excluded_ttids); $i++) {
                    $exc = array_intersect($exc, $excluded_ttids[$i]);
                }

                if ($exc) {
                    // but don't exclude a term which is explicitly included for one or more post types
                    if (count($exception_types) > 1) {
                        $exc = array_diff($exc, $included_ttids);
                    }

                    if ($exc) {
                        $all_excluded_ttids = array_merge($all_excluded_ttids, $exc);
                        $filtered_taxonomies[$taxonomy] = true;
                    }
                }
            }

            if ($included_ttids) {
                if (empty($wrapped)) {
                    $clauses['where'] = "( {$clauses['where']} )";
                    $wrapped = true;
                }

                // include terms were specified for all post types
                if (count($taxonomies) == 1) {
                    $clauses['where'] = " ( " . $clauses['where'] . " )"
                        . " AND tt.term_taxonomy_id IN ('" . implode("','", array_unique($included_ttids)) . "')";
                } else {
                    $clauses['where'] .= " AND ( tt.taxonomy != '$taxonomy' "
                        . "OR tt.term_taxonomy_id IN ('" . implode("','", array_unique($included_ttids)) . "') )";
                }

                $filtered_taxonomies[$taxonomy] = true;
            }
        }

        if ($all_excluded_ttids) {
            if (empty($wrapped)) {
                $clauses['where'] = "( {$clauses['where']} )";
                $wrapped = true;
            }

            $clauses['where'] .= " AND tt.term_taxonomy_id NOT IN ('" . implode("','", $all_excluded_ttids) . "')";
        }

        if (('all' == ($args['get'] ?? '')) && empty($args['parent'])
        && in_array($required_operation, ['assign', 'manage'], true)
        ) {
            foreach (array_keys($filtered_taxonomies) as $taxonomy) {
                if (is_taxonomy_hierarchical($taxonomy)) {
                    $this->hierarchy_remap_taxonomies[$taxonomy] = true;
                }
            }
        }

        return $clauses;
    }

    /**
     * Restore hierarchy for a restricted wp_terms_checklist() result.
     */
    public function fltRemapTermHierarchy($terms, $taxonomies, $args)
    {
        $remap_taxonomies = $this->hierarchy_remap_taxonomies;
        $this->hierarchy_remap_taxonomies = [];
        $taxonomies = (array) $taxonomies;

        if (empty($remap_taxonomies) || empty($terms) || !is_array($terms) || (1 != count($taxonomies))) {
            return $terms;
        }

        $taxonomy = reset($taxonomies);
        if (empty($remap_taxonomies[$taxonomy]) || !is_object(reset($terms))) {
            return $terms;
        }

        require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');

        $ancestors = \PressShack\Ancestry::getTermAncestors($taxonomy);
        $remap_args = [
            'child_of' => (int) ($args['child_of'] ?? 0),
            'parent' => (int) ($args['parent'] ?? 0),
            'exclude' => $args['exclude'] ?? false, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
            'orderby' => $args['orderby'] ?? 'name',
            'col_id' => 'term_id',
            'col_parent' => 'parent',
        ];

        \PressShack\Ancestry::remapTree($terms, $ancestors, $remap_args);

        return $terms;
    }
}

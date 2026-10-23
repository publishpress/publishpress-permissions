<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class TermFiltersCount
{
    private static $instance = null;
    var $parent_remap_enabled = true;

    public static function instance($args = []) {
        if ( is_null( self::$instance ) ) {
            self::$instance = new TermFiltersCount();
            self::$instance->init();
        }

        if (isset($args['parent_remap_enabled'])) {
            self::$instance->parent_remap_enabled = $args['parent_remap_enabled'];
        }

        return self::$instance;
    }

    private function __construct()
    {

    }

    private function init() {
        require_once(PRESSPERMIT_CLASSPATH . '/TermQuery.php');

        // flt_get_terms is required on the front end (even for administrators) so private posts are included in count, as basis for display when hide_empty arg is used
        add_filter('get_terms', [$this, 'fltGetTerms'], 0, 3);   // WPML registers at priority 1
        
        add_filter('get_terms_fields', [$this, 'fltGetTermsFields'], 99, 2);
    }

    // force get_pages to return all fields without post-processing results
    public function fltGetTermsFields($fields, $args)
    {
        if ('force_all' == $args['fields']) {
            $fields = ['t.*', 'tt.*'];
        }

        return $fields;
    }

    public function fltGetTermsArgs($args)
    {
        $args['child_of'] = (int)$args['child_of'];  // null value will confuse subsequent PP checks

        // turn off core post-processing of the result set, but buffer actual arg values for equivalent PP post-processing
        $buffer_args = [];
        foreach (['child_of' => 0, 'pad_counts' => false, 'hide_empty' => false, 'number' => ''] as $arg_name => $force_val) {
            if ($args[$arg_name] != $force_val) {
                $buffer_args[$arg_name] = $args[$arg_name];
                $args[$arg_name] = $force_val;
            }
        }

        if (in_array($args['fields'], ['ids', 'names', 'id=>parent'])) {
            // flt_get_terms() needs intermediate result set to be term objects with count property, even if final result set will be ids only.  
            // We will convert result set back to expected format before returning.
            // This also prevents WP core get_terms() from padding term counts needlessly
            $buffer_args['fields'] = $args['fields'];
            $args['fields'] = 'force_all';
        }

        if ($buffer_args) {
            $args['actual_args'] = $buffer_args;
        }

        return $args;
    }

    public function fltTermsClauses($clauses, $args)
    {
        $defaults = ['hide_empty' => false, 'hierarchical' => false, 'actual_args' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        // hide_empty may have been set by fltGetTermsArgs()
        if ($hide_empty && (empty($actual_args) || !$actual_args['hide_empty']) && !$hierarchical) {
            $clauses['where'] .= ' AND tt.count > 0';
        }

        // we forced $args['fields'] to disable WP post-processing of result set. Now filter actual fields clause back to original.
        if (isset($actual_args['fields'])) {
            switch ($actual_args['fields']) {
                case 'all':
                case 'all_with_object_id':
                case 'tt_ids':
                case 'slugs':
                    $selects = array( 't.*', 'tt.*' );
                    if ( 'all_with_object_id' === $args['fields'] && ! empty( $args['object_ids'] ) ) {
                        $selects[] = 'tr.object_id';
                    }
                    break;
                case 'ids':
                case 'id=>parent':
                    $selects = array( 't.term_id', 'tt.parent', 'tt.count', 'tt.taxonomy' );
                    break;
                case 'names':
                    $selects = array( 't.term_id', 'tt.parent', 'tt.count', 't.name', 'tt.taxonomy' );
                    break;
                case 'count':
                    $selects = array( 'COUNT(*)' );
                    break;
                case 'id=>name':
                    $selects = array( 't.term_id', 't.name', 'tt.count', 'tt.taxonomy' );
                    break;
                case 'id=>slug':
                    $selects = array( 't.term_id', 't.slug', 'tt.count', 'tt.taxonomy' );
                    break;
            }

            $clauses['fields'] = implode(', ', apply_filters('get_terms_fields', $selects, $args));
        }

        return $clauses;
    }

    public function fltGetTerms($terms, $taxonomies, $args)
    {
        global $pagenow;

        // phpcs Note: The exclude and meta_value args supported here are for WP API compat, passing on any such arguments supplied by the get_terms() or get_pages() function

        $defaults = [
            'fields' => '',
            'hierarchical' => false,
            'child_of' => 0,
            'parent' => 0,
            'hide_empty' => false,
            'number' => 0,
            'offset' => 0,
            'include' => false,
            'exclude' => false,  // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
            'actual_args' => [],

            'pad_counts' => false,
            'skip_teaser' => false,
            'post_type' => '',
        ];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!is_array($terms)) {
            return $terms;
        }

        if (empty($terms)) {
            return [];
        }

        $taxonomies = (array) $taxonomies;

        if (apply_filters('presspermit_terms_skip_filtering', false, $taxonomies, $args)) {
            return $terms;
        }

        if (('id=>parent' == $fields) && !empty($pagenow) && ('edit-tags.php' == $pagenow)) {
            return $terms;
        }

        if ('ids' == $fields) {
            if ($terms && is_object($terms[0])) {
                $_terms = [];
                foreach ($terms as $term) {
                    $_terms[] = $term->term_id;
                }
                return $_terms;
            }

            return $terms;
        }

        static $busy;

        if (!empty($busy)) {
            return $terms;
        }

        $busy = true;

        // if some args were forced to prevent core post-processing, restore actual values now
        if (!empty($args['actual_args'])) {
            foreach (array_keys($defaults) as $var) {
                if (isset($args['actual_args'][$var])) {
                    $$var = $args['actual_args'][$var];
                }
            }
        }

        if ('all' == $fields) {
            // buffer term names in case they were filtered previously
            $term_names = Arr::getPropertyArray($terms, 'term_id', 'name');
        }

        if (!empty($child_of)) {
            $children = _get_term_hierarchy(reset($taxonomies));
            if (!empty($children)) {
                $terms = _get_term_children($child_of, $terms, reset($taxonomies));
            }
        }

        // Replace DB-stored term counts with actual number of posts this user can read.
        // In addition, without the TermQuery::tallyTermCounts() call, WP will hide terms that have no public posts (even if this user can read some of the pvt posts).
        // Post counts will be incremented to include child terms only if $pad_counts is true
        if (!defined('XMLRPC_REQUEST') && (1 == count($taxonomies))) {
            if ((!is_admin() || !in_array($pagenow, ['post.php', 'post-new.php']))
                && (!defined('PP_UNFILTERED_TERM_COUNTS') || is_admin())
                && (in_array($pagenow, ['edit-tags.php']) || !presspermit()->getOption('term_counts_unfiltered'))
            ) {
                if ($hide_empty || !empty($args['actual_args']['hide_empty'])) {
                    // need to tally for all terms in case some were hidden by core function due to lack of public posts
                    $all_terms = get_terms(['taxonomy' => reset($taxonomies), 'fields' => 'all', 'pp_no_filter' => true, 'hide_empty' => false]);
                    TermQuery::tallyTermCounts($all_terms, reset($taxonomies), compact('pad_counts', 'skip_teaser', 'post_type'));

                    foreach (array_keys($terms) as $k) {
                        foreach (array_keys($all_terms) as $key) {
                            if (is_object($all_terms[$key]) && is_object($terms[$k])) {
                            	if ($all_terms[$key]->term_taxonomy_id == $terms[$k]->term_taxonomy_id) {
                                	$terms[$k]->count = $all_terms[$key]->count;
                                	break;
                                }
                            }
                        }
                    }

                // Perf: If empty terms are not being hidden and show_count is set false, there is no need to filter the term counts.
                } elseif (!isset($args['actual_args']['show_count']) || !empty($args['actual_args']['show_count'])) {
                    TermQuery::tallyTermCounts($terms, reset($taxonomies), compact('pad_counts', 'skip_teaser', 'post_type'));
                }
            }
        }

        if ($hide_empty || !empty($args['actual_args']['hide_empty'])) {
            if ($hierarchical) {
                foreach ($taxonomies as $taxonomy) {
                    if (empty($all_terms) || (count($taxonomies) > 1)) {
                        $all_terms = get_terms(['taxonomy' => $taxonomy, 'fields' => 'all', 'pp_no_filter' => true, 'hide_empty' => false]);
                    }

                    // Remove empty terms, but only if their descendants are all empty too.
                    foreach ($terms as $k => $term) {
                        if (is_object($term) && !$term->count) {
                            if ($descendants = _get_term_children($term->term_id, $all_terms, $taxonomy)) {
                                foreach ($descendants as $child) {
                                    if ($child->count) {
                                        continue 2;
                                    }
                                }
                            }

                            // It really is empty
                            unset($terms[$k]);
                        }
                    }
                }
            } else {
                foreach ($terms as $key => $term) {
                    if (is_object($term) && isset($term->count) && !$term->count) {
                        unset($terms[$key]);
                    }
                }
            }
        }

        if ($hierarchical && !$parent && (count($taxonomies) == 1) && $this->parent_remap_enabled) {
            require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');

            $ancestors = \PressShack\Ancestry::getTermAncestors(reset($taxonomies)); // array of all ancestor IDs for keyed term_id, with direct parent first

            $remap_args = array_merge(
                compact('child_of', 'parent', 'exclude'),
                ['orderby' => 'name', 'col_id' => 'term_id', 'col_parent' => 'parent']
            );

            \PressShack\Ancestry::remapTree($terms, $ancestors, $remap_args);
        }

        reset($terms);

        // === Standard WP post-processing for include, fields, number args ===
        //
        $_terms = [];

        if (('id=>parent' == $fields) && apply_filters('presspermit_get_terms_filter_id_parent', true, $terms, $taxonomies, $args)) {
			foreach ( $terms as $term ) {
                if (is_object($term)) {
                	$_terms[$term->term_id] = $term->parent;
                } elseif ($_term = get_term($term, reset($taxonomies))) {
                    if (!is_wp_error($_term)) {
                        $_terms[$_term->term_id] = $_term->parent;
                    }
                }
            }
        } elseif ('ids' == $fields) {
			foreach ( $terms as $term ) {
				$_terms[] = (int) $term->term_id;
			}
		} elseif ( 'tt_ids' == $fields ) {
			foreach ( $terms as $term ) {
				$_terms[] = (is_object($term)) ? (int) $term->term_taxonomy_id : (int) $term;
            }
        } elseif ('names' == $fields) {
			foreach ( $terms as $term ) {
                // todo: track conditions, source for improper array population
                if (is_object($term)) {
                    $_terms[] = $term->name;
                } elseif (is_numeric($term) && count($taxonomies) == 1) {
                    if ($term = get_term($term, reset($taxonomies))) {
                        if (!is_wp_error($term)) {
                        	$_terms[] = $term->name;
                    	}
                    }
                } elseif (is_string($term)) {
                    $_terms[] = $term;
                }
            }
		} elseif ( 'slugs' == $fields ) {
			foreach ( $terms as $term ) {
                if (is_object($term)) {
					$_terms[] = $term->slug;
                } elseif (is_numeric($term) && count($taxonomies) == 1) {
                    if ($term = get_term($term, reset($taxonomies))) {
                        if (!is_wp_error($term)) {
                        	$_terms[] = $term->slug;
                    	}
                	}
				}
			}
		} elseif ( 'id=>name' == $fields ) {
			foreach ( $terms as $term ) {
                if (is_object($term)) {
					$_terms[ $term->term_id ] = $term->name;
                } elseif (is_numeric($term) && count($taxonomies) == 1) {
                    if ($term = get_term($term, reset($taxonomies))) {
                        if (!is_wp_error($term)) {
                        	$_terms[ $term->term_id ] = $term->name;
                    	}
                	}
				}
			}
		} elseif ( 'id=>slug' == $fields ) {
			foreach ( $terms as $term ) {
                if (is_object($term)) {
                    $_terms[ $term->term_id ] = $term->slug;
                } elseif (is_numeric($term) && count($taxonomies) == 1) {
                    if ($term = get_term($term, reset($taxonomies))) {
                        if (!is_wp_error($term)) {
							$_terms[ $term->term_id ] = $term->slug;
                        }
                    }
                }
			}
		}

        if ( ! empty( $_terms ) ) {
            $terms = $_terms;
        }

        // === end standard WP block ===

        // restore buffered term names in case they were filtered previously
        if ('all' == $fields) {
            Arr::restorePropertyArray($terms, $term_names, 'term_id', 'name');
        }

        $busy = false;
        return $terms;
    }
}

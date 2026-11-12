<?php

namespace PressShack;

\defined('ABSPATH') || exit;

class Ancestry
{
    public static function getPostPath($item_id)
    {
        $title_caption = '';

        if ($item_id) {
            if ($post = get_post($item_id)) {
                if (is_post_type_hierarchical($post->post_type) && $ancestors = self::getPageAncestors($item_id)) {
                    $arr = [];
                    foreach ($ancestors as $id) {
                        if ($_ancestor = get_post($id)) {
                            $arr[] = $_ancestor->post_title;
                        }
                    }
                    $arr = array_reverse($arr);

                    $arr[] = $post->post_title;
                    $title_caption = implode(' / ', $arr);
                } else {
                    $title_caption = $post->post_title;
                }
            }
        }
        
        return $title_caption;
    }

    public static function getTermPath($term_id, $taxonomy)
    {
        $title_caption = '';

        if ($term = get_term($term_id, $taxonomy)) {
            if (isset($term->name)) {
                if (is_taxonomy_hierarchical($taxonomy) && $ancestors = self::getTermAncestors($taxonomy, $term_id)) {
                    $arr = [];
                    foreach ($ancestors as $id) {
                        if ($_ancestor = get_term($id, $taxonomy)) {
                            $arr[] = $_ancestor->name;
                        }
                    }
                    $arr = array_reverse($arr);

                    $arr[] = $term->name;
                    $title_caption = implode(' / ', $arr);
                } else {
                    $title_caption = $term->name;
                }
            }
        }

        return $title_caption;
    }

    // recursive function
    private static function walkAncestors($child_id, $ancestors, $parents)
    {
        if (isset($parents[$child_id])) {
            if (in_array($parents[$child_id], $ancestors))  // prevent infinite recursion if a page has a descendant set as its parent page
                return $ancestors;

            $ancestors[] = $parents[$child_id];
            $ancestors = self::walkAncestors($parents[$child_id], $ancestors, $parents);
        }
        return $ancestors;
    }

    public static function getPageAncestors($object_id = 0, $post_type = '')
    {
        static $ancestors;

        if (!isset($ancestors) || !LibWP::empty_POST())
            $ancestors = false;

        if (is_array($ancestors) && !$object_id)
            return $ancestors;

        if (!$ancestors) {
            $ancestors = [];

            global $wpdb;

            $post_types = ($post_type) ? (array)$post_type : get_post_types(['hierarchical' => true]);
            $post_types = array_intersect($post_types, get_post_types(['public' => true, 'show_ui' => true], 'names', 'or'));

            $types_csv = implode("','", array_map('sanitize_key', $post_types));

            // This query is cached to a static variable to ensure just a single execution per http GET
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($pages = $wpdb->get_results(                                                                                    // phpcs Note: IN clause sanitized above
                "SELECT ID, post_parent FROM $wpdb->posts WHERE post_type IN ('$types_csv') AND post_status != 'auto-draft'"    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            )) {
                $parents = [];
                foreach ($pages as $page)
                    if ($page->post_parent)
                        $parents[$page->ID] = $page->post_parent;

                foreach ($pages as $page) {
                    $ancestors[$page->ID] = self::walkAncestors($page->ID, [], $parents);
                    if (empty($ancestors[$page->ID]))
                        unset($ancestors[$page->ID]);
                }
            }
        }

        if ($object_id) {
            return (isset($ancestors[$object_id])) ? $ancestors[$object_id] : [];
        }

        return $ancestors;
    }

    // recursive function
    private static function walkDescendants($parent_id, $descendants, $children, $max_depth)
    {
        if (isset($children[$parent_id])) {
            if (is_numeric($max_depth)) {
                if (!$max_depth)
                    return $descendants;
                else
                    $max_depth--;
            }

            if (in_array($parent_id, $descendants))  // prevent infinite recursion if a page has parent set as one of its descendants
                return $descendants;

            foreach ($children[$parent_id] as $child_id) {
                $descendants[] = $child_id;
                $grandchildren = self::walkDescendants($child_id, [], $children, $max_depth);
                $descendants = array_merge($descendants, $grandchildren);
            }
        }
        return $descendants;
    }

    public static function getPageDescendants($root_page, $args = [])
    {
        global $wpdb;

        $defaults = [
            'post_type' => '', 
            'max_depth' => false, 
            'pages' => [], 
            'post_status' => false, 
            'exclude_autodrafts' => true,
            'include_revisions' => false, 
            'include_attachments' => true
        ];

        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $descendants = [];

        if (!$pages) {
            // Back compat for previous LibWP::getDescendantIds calls, which defaulted to no post_type clause
            if (false === $post_type) {
                $type_clause = "1=1";

            } elseif ($post_type) {
                $types_csv = implode("','", array_map('sanitize_key', (array) $post_type));
                $type_clause = "post_type IN ('$types_csv')";

            } else {
                $post_types = get_post_types(['hierarchical' => true]);
                $types_csv = implode("','", array_map('sanitize_key', $post_types));
                $type_clause = "post_type IN ('$types_csv')";
            }

            if (!$include_revisions) {
                $type_clause .= " AND post_type != 'revision'";
            }

            if (!$include_attachments) {
                $type_clause .= " AND post_type != 'attachment'";
            }

            if ($post_status) {
                $status_csv = implode("','", array_map('sanitize_key', (array) $post_status));
                $status_clause = "AND post_status IN ('$status_csv')";

            } elseif ($exclude_autodrafts) {
                $status_clause = "AND post_status != 'auto-draft'";

            // Back compat for previous LibWP::getDescendantIds calls, which defaulted to not excluding autodrafts
            } else {
                $status_clause = '';
            }

            // phpcs Note: type_clause and status_clause sanitized above due to IN clause, variable request structure
            
            // This function is called only for Post update and Permissions management requests
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $pages = $wpdb->get_results(
                "SELECT ID, post_parent FROM $wpdb->posts WHERE $type_clause $status_clause" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
        }

        if ($pages) {
            $children = [];
            foreach ($pages as $page) {
                if (!$page->ID) continue;

                $children[$page->post_parent][] = $page->ID;
            }

            $descendants = self::walkDescendants($root_page, [], $children, $max_depth);
        }

        return $descendants;
    }

    public static function getTermDescendants($root_term, $args = [])
    {
        global $wpdb;

        $defaults = ['taxonomy' => '', 'max_depth' => false, 'terms' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $num_pages = 0;
        $descendants = [];

        if (!$terms) {
            // Back compat for previous LibWP::getDescendantIds calls, defaulting to no taxonomy clause
            if (false === $taxonomy) {
                // This function is called only for Term update and Permissions management requests
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $terms = $wpdb->get_results("SELECT term_id, parent FROM $wpdb->term_taxonomy");
            } else {
                $taxonomies = ($taxonomy) ? (array)$taxonomy : get_taxonomies(['hierarchical' => true]);
                $taxonomies_csv = implode("','", array_map('sanitize_key', $taxonomies));

                // This function is called only for Term update and Permissions management requests
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $terms = $wpdb->get_results(
                    "SELECT term_id, parent FROM $wpdb->term_taxonomy WHERE taxonomy IN ('$taxonomies_csv')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                );
            }
        }

        if ($terms) {
            $children = [];
            foreach ($terms as $term) {
                if (!$term->term_id) continue;

                $children[$term->parent][] = $term->term_id;
            }

            $descendants = self::walkDescendants($root_term, [], $children, $max_depth);
        }

        return $descendants;
    }

    public static function getTermAncestors($taxonomy, $term_id = 0)
    {
        static $ancestors;

        if (!isset($ancestors) || !LibWP::empty_POST())
            $ancestors = false;

        if (is_array($ancestors) && !$term_id)
            return $ancestors;

        if (!$ancestors) {
            $ancestors = [];

            $terms = get_terms(['taxonomy' => $taxonomy, 'pp_no_filter' => true, 'hide_empty' => false]);

            if ($terms) {
                $parents = [];

                foreach ($terms as $term)
                    if ($term->parent)
                        $parents[$term->term_id] = $term->parent;

                foreach ($terms as $term) {
                    $_term_id = $term->term_id;
                    $ancestors[$_term_id] = self::walkAncestors($_term_id, [], $parents);
                    if (empty($ancestors[$_term_id]))
                        unset($ancestors[$_term_id]);
                }
            }
        }

        if ($term_id) {
            return (isset($ancestors[$term_id])) ? $ancestors[$term_id] : [];
        }

        return $ancestors;
    }

    public static function remapTree(&$items, $ancestors, $args = [])
    {
        $defaults = [
            'child_of' => 0,
            'parent' => -1,
            'orderby' => 'post_title',
            'depth' => 0,
            'remap_parents' => true,
            'enforce_actual_depth' => true,         // phpcs Note: exclude arg used in same manner as WP core get_terms() / get_pages()
            'exclude' => '',                        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
            'remap_thru_excluded_parent' => false,
            'col_id' => 'ID',
            'col_parent' => 'post_parent',
        ];

        $args = wp_parse_args($args, $defaults);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        // This function is only valid for arrays of post or term objects
        if (!is_object(reset($items))) return $items;

        if ('ID' == $col_id) {
            if (($child_of && !defined('PPC_FORCE_PAGE_REMAP')) || defined('PPC_NO_PAGE_REMAP'))
                $remap_parents = false;
        } elseif (($child_of && !defined('PPC_FORCE_TERM_REMAP')) || defined('PPC_NO_TERM_REMAP')) {
            $remap_parents = false;
        }

        $remap_parents = apply_filters('presspermit_enable_parent_remap', $remap_parents, $args);

        if ($depth < 0)
            $depth = 0;

        if ($exclude)
            $exclude = wp_parse_id_list($exclude);

        $filtered_items_by_id = [];
        foreach ($items as $item)
            $filtered_items_by_id[$item->$col_id] = true;

        $remapped_items = [];

        // temporary WP bug workaround
        $first_child_of_match = -1;

        // The desired "root" is included in the ancestor array if using $child_of arg, but not if child_of = 0
        $one_if_root = ($child_of) ? 0 : 1;

        foreach ($items as $key => $item) {
            $parent_id = $item->$col_parent;

            if ($remap_parents) {
                $id = $item->$col_id;

                if ($parent_id && ($child_of != $parent_id) && isset($ancestors[$id])) {

                    // Don't use any ancestors higher than $child_of
                    if ($child_of) {
                        $max_key = array_search($child_of, $ancestors[$id]);
                        if (false !== $max_key)
                            $ancestors[$id] = array_slice($ancestors[$id], 0, $max_key + 1);
                    }

                    // Apply depth cutoff here so Walker is not thrown off by parent remapping.
                    if ($depth && $enforce_actual_depth) {
                        if (count($ancestors[$id]) > ($depth - $one_if_root))
                            unset($items[$key]);
                    }

                    if (!isset($filtered_items_by_id[$parent_id])) {
                        // Remap to a visible ancestor, if any 
                        if (!$depth || isset($items[$key])) {
                            $visible_ancestor_id = 0;

                            foreach ($ancestors[$id] as $ancestor_id) {
                                if (isset($filtered_items_by_id[$ancestor_id]) || ($ancestor_id == $child_of)) {
                                    // don't remap through a parent which was explicitly excluded
                                    if ($exclude && in_array($items[$key]->$col_parent, $exclude) && !$remap_thru_excluded_parent)
                                        break;

                                    $visible_ancestor_id = $ancestor_id;
                                    break;
                                }
                            }

                            if ($visible_ancestor_id)
                                $items[$key]->$col_parent = $visible_ancestor_id;

                            elseif (!$child_of)
                                $items[$key]->$col_parent = 0;

                            // if using custom ordering, force remapped items to the bottom
                            if (($visible_ancestor_id == $child_of) && (false !== strpos($orderby, 'order'))) {
                                $remapped_items[$key] = $items[$key];
                                unset($items[$key]);
                            }
                        }
                    }
                }
            } elseif ($parent_id && ($depth == 1) && ($parent_id != $child_of) && !isset($filtered_items_by_id[$parent_id])) { // end if not skipping page parent remap
                unset($items[$key]); // Walker will not strip this item out based on wp_list_pages depth argument if its parent is missing

                continue;
            }

            // temporary WP bug workaround: need to keep track of parent, for reasons described below
            if ($child_of && !$remapped_items) {
                if (($first_child_of_match < 0) && ($child_of == $items[$key]->$col_parent))
                    $first_child_of_match = $key;
            }
        }

        // temporary WP bug workaround
        if ($child_of && ($parent < 0) && ($first_child_of_match > 0)) {
            if ($first_item = reset($items)) {
                if ($child_of != $first_item->$col_parent) {
                    // As of WP 2.8.4, Walker class will botch this array because it assumes that the first element in the page array is a child of the display root
                    // To work around, we must move first element with the desired child_of up to the top of the array
                    $_items = [$items[$first_child_of_match]];

                    unset($items[$first_child_of_match]);
                    $items = array_merge($_items, $items);
                }
            }
        }

        if ($remapped_items)
            $items = array_merge($items, $remapped_items);
    }
}

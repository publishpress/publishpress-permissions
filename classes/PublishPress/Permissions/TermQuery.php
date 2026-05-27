<?php

namespace PublishPress\Permissions;

class TermQuery
{
    // derived from WP _pad_term_counts(), but includes private posts in counts based on current user's access
    public static function tallyTermCounts(&$terms, $taxonomy, $args = [])
    {
        global $wpdb;

        if (!$terms) {
            return;
        }

        $defaults = ['pad_counts' => true, 'post_type' => '', 'required_operation' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $term_items = [];
        $terms_by_id = [];
        $term_ids = [];

        if ($terms) {
            if (!is_object(reset($terms))) {
                return;
            }

            foreach ((array)$terms as $key => $term) {
                $terms_by_id[$term->term_id] = &$terms[$key];
                $term_ids[$term->term_taxonomy_id] = $term->term_id;
            }
        }

        // Get the object and term ids and stick them in a lookup table
        $tax_obj = get_taxonomy($taxonomy);
        
        if ($post_type || $tax_obj) {
            $object_types = ($post_type) ? (array) $post_type : (array) esc_sql($tax_obj->object_type);
        } else {
            return;
        }

        if ( class_exists('\PublishPress\Permissions\PostFilters') && ! presspermit()->isUserUnfiltered() ) {
            // will default to src_table $wpdb->posts
            $join = \PublishPress\Permissions\PostFilters::instance()->fltPostsJoin('', ['context' => 'tally_term_counts']);

            // need to apply term restrictions in case post is restricted by another taxonomy
            $type_status_clause = \PublishPress\Permissions\PostFilters::instance()->getPostsWhere(
                ['post_types' => $object_types, 'required_operation' => $required_operation, 'join' => $join, 'context' => 'tally_term_counts']
            );
        } else {
            $join = '';
            
            if ($stati = get_post_stati(['public' => true, 'private' => true], 'names', 'or')) {
                $stati_csv = implode("', '", array_map('sanitize_key', $stati));
                $status_clause = "AND post_status IN ('$stati_csv')";
            } else {
                $status_clause = '';
            }

            $types_csv = implode("', '", array_map('sanitize_key', $object_types));
            $type_status_clause = "AND post_type IN ('$types_csv') $status_clause";
        }

        if (!$required_operation) {
            $required_operation = (PWP::isFront() && !presspermit_is_preview()) ? 'read' : 'edit';
        }

        $term_id_csv = implode("','", array_map('intval', array_keys($term_ids)));

        $qry = "SELECT tr.object_id, tr.term_taxonomy_id FROM $wpdb->term_relationships AS tr"
        . " INNER JOIN $wpdb->posts ON object_id = $wpdb->posts.ID $join"
        . " WHERE tr.term_taxonomy_id IN ('$term_id_csv') $type_status_clause";

        static $cache_results;

        if (!isset($cache_results)) {
            $cache_results = [];
        }

        $ckey = md5($qry);

        if (isset($cache_results[$ckey])) {
            $results = $cache_results[$ckey];
        } else {
            // phpcs Note: query clauses constructed and sanitized above

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results(
                "SELECT tr.object_id, tr.term_taxonomy_id FROM $wpdb->term_relationships AS tr"
                . " INNER JOIN $wpdb->posts ON object_id = $wpdb->posts.ID $join"               // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                . " WHERE tr.term_taxonomy_id IN ('$term_id_csv') $type_status_clause"          // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ); 

            $cache_results[$ckey] = $results;
        }

        foreach ($results as $row) {
            $id = $term_ids[$row->term_taxonomy_id];
            $term_items[$id][$row->object_id] = 1;
        }

        $max_depth = count($terms_by_id);

        // Touch every ancestor's lookup row for each post in each term
        foreach ($term_ids as $term_id) {
            $child = $term_id;
            $seen = [];
            $depth = 0;

            while (!empty($terms_by_id[$child]) && $parent = (int) $terms_by_id[$child]->parent) {  // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
                if (
                    isset($seen[$child])
                    || isset($seen[$parent])
                    || ++$depth > $max_depth
                    || empty($terms_by_id[$parent])
                ) {
                    break;
                }

                if (!empty($term_items[$term_id])) {
                    foreach ($term_items[$term_id] as $item_id => $touches) {
                        $term_items[$parent][$item_id] = 1;
                    }
                }

                $seen[$child] = true;
                $child = $parent;
            }
        }

        foreach (array_keys($terms_by_id) as $key) {
            $terms_by_id[$key]->count = 0;
        }

        // Transfer the touched cells
        foreach ((array)$term_items as $id => $items) {
            if (isset($terms_by_id[$id])) {
                $terms_by_id[$id]->count = count($items);
            }
        }
    }
}

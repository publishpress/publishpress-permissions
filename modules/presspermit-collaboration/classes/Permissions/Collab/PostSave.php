<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class PostSave
{
    // Workaround to deal with WP core's checking of publish cap prior to storing categories
    // Store terms to DB in advance of any cap-checking query which may use those terms to qualify an operation
    public static function preAssignTerms($pp_reqd_caps, $post_type, $object_id)
    {
        // @todo: confirm this is still needed

        $set_terms = false;

        if (!$post_type_obj = get_post_type_object($post_type))
            return false;

        if (array_intersect(
            [   'edit_post', 
                'publish_posts', 
                'edit_posts', 
                $post_type_obj->cap->edit_post, 
                $post_type_obj->cap->publish_posts, 
                $post_type_obj->cap->edit_posts
            ], 
            $pp_reqd_caps)
        ) {
            static $objects_done;
            if (!isset($objects_done))
                $objects_done = [];

            if (in_array($object_id, $objects_done))
                return false;

            $objects_done[] = $object_id;

            $uses_taxonomies = presspermit()->getEnabledTaxonomies(['object_type' => $post_type]);

            static $inserted_terms;
            if (!isset($inserted_terms))
                $inserted_terms = [];

            foreach ($uses_taxonomies as $taxonomy) {
                if (isset($inserted_terms[$taxonomy][$object_id]))
                    continue;

                $inserted_terms[$taxonomy][$object_id] = true;

                $tx_obj = get_taxonomy($taxonomy);

                $stored_terms = Collab::getObjectTerms($object_id, $taxonomy, ['fields' => 'ids', 'pp_no_filter' => true]);

                if (!$selected_terms = Collab::getPostedObjectTerms($taxonomy)) {
                    continue;   // don't pre-insert if no terms selected, or selection cannot be detected
                }

                if (is_array($selected_terms)) { // non-hierarchical terms don't need to be pre-inserted
                    if ($set_terms = apply_filters('presspermit_pre_object_terms', $selected_terms, $taxonomy)) {
                        $set_terms = array_unique(array_map('intval', $set_terms));

                        // safeguard against unintended clearing of stored categories
                        if (($set_terms != $stored_terms) && $set_terms && ($set_terms != [1])) { 
                            wp_set_object_terms($object_id, $set_terms, $taxonomy);
                            $set_terms = true;
                        }
                    }
                }
            }
        }

        return $set_terms;
    }
}

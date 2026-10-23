<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class TermSave
{
    // This handler is meant to fire whenever a term is inserted or updated.
    // If the client does use such a hook, we will force it by calling internally from mnt_create and mnt_edit

    public static function actSaveItem($term_id, $tt_id, $taxonomy)
    {
        if (!in_array($taxonomy, presspermit()->getEnabledTaxonomies(), true)) {
            if (!PWP::empty_REQUEST('pp_enable_taxonomy')) {
                $enabled_taxonomies = get_option('presspermit_enabled_taxonomies');
                $enabled_taxonomies[$taxonomy] = '1';
                update_option('presspermit_enabled_taxonomies', $enabled_taxonomies);
            }
            return;
        }

        static $saved_terms;

        if (!isset($saved_terms))
            $saved_terms = [];

        // so this filter doesn't get called by hook AND internally
        if (isset($saved_terms[$taxonomy][$tt_id]))
            return;

        $parent_arg = apply_filters('presspermit_add_term_parent_arg', 'parent', $taxonomy);

        // parent settings can affect the auto-assignment of propagating exceptions
        $set_parent = (is_taxonomy_hierarchical($taxonomy) && !PWP::empty_REQUEST($parent_arg)) ? PWP::REQUEST_int($parent_arg) : 0;

        if ($set_parent < 0)
            $set_parent = 0;

        $saved_terms[$taxonomy][$tt_id] = 1;

        // Determine whether this object is new (first time this PP filter has run for it, though the object may already be inserted into db)
        $last_parent = 0;

        if (!$last_parents = get_option("pp_last_{$taxonomy}_parents"))
            $last_parents = [];

        if (!isset($last_parents[$tt_id])) {
            $is_new = true;
            $last_parents = [];
        } else
            $is_new = false;

        if (isset($last_parents[$tt_id]))
            $last_parent = $last_parents[$tt_id];

        if ((intval($set_parent) != intval($last_parent)) && ($set_parent || $last_parent)) {
            $last_parents[$tt_id] = $set_parent;
            update_option("pp_last_{$taxonomy}_parents", $last_parents);
        }

        $exceptions_customized = false;
        if (!$is_new) {
            if ($custom_exc_objects = get_option("pp_custom_{$taxonomy}"))
                $exceptions_customized = isset($custom_exc_objects[$tt_id]);
        }

        global $typenow;

        require_once(PRESSPERMIT_CLASSPATH . '/ItemSave.php');
        $args = compact('is_new', 'set_parent', 'last_parent');
        $args['via_item_type'] = $taxonomy;

        ItemSave::itemUpdateProcessExceptions('term', 'post', $tt_id, $args);

        do_action('presspermit_update_item_exceptions', 'term', $tt_id, $args);
    }
}

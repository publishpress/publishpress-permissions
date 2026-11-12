<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

\defined('ABSPATH') || exit;

class TermEdit
{
    function __construct()
    {
        add_filter('presspermit_term_exceptions_metaboxes', [$this, 'term_exceptions_metaboxes'], 10, 3);
        add_action('presspermit_prep_metaboxes', [$this, 'pp_prep_metaboxes'], 10, 3);
    }

    function update_item_exceptions($via_item_source, $item_id, $args)
    {
        if ('term' == $via_item_source) {
            ItemSave::itemUpdateProcessExceptions('term', 'term', $item_id, $args);
        }
    }

    function pp_prep_metaboxes($via_item_source, $via_item_type, $tt_id)
    {
        if ('term' == $via_item_source) {
            global $typenow;

            if (!$typenow) {
                $args = ['for_item_type' => $via_item_type];  // via_item_source, for_item_source, via_item_type, item_id
                do_action('presspermit_load_item_exceptions', 'term', 'term', $via_item_type, $tt_id, $args);
            }
        }
    }

    function term_exceptions_metaboxes($boxes, $taxonomy, $use_post_type)
    {
        if (!$use_post_type) {  // term management / association exceptions UI only displed when editing "Universal Exceptions" (empty post type)
            $tx = get_taxonomy($taxonomy);
            $is_pp_universal = !PWP::empty_REQUEST('pp_universal');
            $add_boxes = [];

            foreach (['manage', 'associate'] as $op) {
                if ($op_obj = presspermit()->admin()->getOperationObject($op, $use_post_type)) {
                    // Skip "associate" for non-hierarchical taxonomies
                    if ($is_pp_universal && $op === 'associate' && !is_taxonomy_hierarchical($taxonomy)) {
                        continue;
                    }

                    $caption = ('associate' == $op) 
                    ? sprintf(
                        esc_html__('Permissions: Select this %1$s as Parent for All Post Types', 'press-permit-core'), 
                        $tx->labels->singular_name
                    )
                    : sprintf(
                        esc_html__('Permissions: %1$s this %2$s for All Post Types', 'press-permit-core'), 
                        $op_obj->label, 
                        $tx->labels->singular_name
                    );

                    Arr::setElem($add_boxes, [$op, "pp_{$op}_{$taxonomy}_exceptions"]);
                    $add_boxes[$op]["pp_{$op}_{$taxonomy}_exceptions"]['for_item_type'] = $taxonomy;
                    $add_boxes[$op]["pp_{$op}_{$taxonomy}_exceptions"]['for_item_source'] = 'term'; // $taxonomy;
                    $add_boxes[$op]["pp_{$op}_{$taxonomy}_exceptions"]['title'] = $caption;
                }
            }

            $boxes = array_merge($add_boxes, $boxes);  // put Category Management Exceptions box at the top
        }

        return $boxes;
    }
}

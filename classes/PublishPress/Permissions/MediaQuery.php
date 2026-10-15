<?php

namespace PublishPress\Permissions;

class MediaQuery
{
    public static function appendAttachmentClause($where, $clauses, $args)
    {
        global $wpdb;

        if (!$where)
            return $where;

        if (!$args)
            $args = [];

        if (empty($args['src_table'])) {
            $src_table = (!empty($args['source_alias'])) ? $args['source_alias'] : $wpdb->posts;
            $args['src_table'] = $src_table;
        } else
            $src_table = $args['src_table'];

        $_args = (array)$args;

        if ($do_parent_subquery = !isset($args['query_contexts']) || !in_array('comments', (array)$args['query_contexts'], true)) {  // comment queries don't append parent subquery, but do enforce edit_others_media setting if PPCE is active
            // generate a subquery based on user's permissions to the post attachments are tied to
            $_post_types = array_diff(get_post_types(['public' => true, 'show_ui' => true], 'names', 'or'), ['attachment']);
            $public_types_csv = implode("','", array_map('sanitize_key', $_post_types));

            if (!empty($args['limit_ids']) && (1 == count($args['limit_ids']))) {
                if ($_post = get_post(reset($args['limit_ids']))) {
                    if ($_parent_type = array_intersect($_post_types, (array)get_post_field('post_type', $_post->post_parent)))
                        $_post_types = $_parent_type;
                }
            }
            $_args['post_types'] = $_post_types; // set for parent subquery only
            $_args['source_alias'] = 'p';

            if (!empty($args['has_cap_check']))
                unset($_args['limit_statuses']);

            if (empty($_args['limit_statuses']))
                $_args['skip_stati_usage_clause'] = true;

            if ('delete' == $_args['required_operation']) {
                if (defined('PP_EDIT_EXCEPTIONS_ALLOW_DELETION') || defined('PP_EDIT_EXCEPTIONS_ALLOW_ATTACHMENT_DELETION'))
                    $_args['required_operation'] = 'edit';
            }

            $pp_where = apply_filters('presspermit_posts_where', '', $_args);
            $type_csv = implode("','", array_map('sanitize_key', $_post_types));
            
            if (defined('PRESSPERMIT_MEDIA_IGNORE_UNREGISTERED_PARENT_TYPES')) {
                // treat media attached to unregistered post types as unattached, to avoid improper and confusing filtering
                $args['subqry'] = "SELECT ID FROM $wpdb->posts AS p WHERE 1=1 AND ( ( ( p.post_type IN ('$type_csv') ) $pp_where ) OR ( p.post_type NOT IN ('$public_types_csv') ) )";  // pass this into filter even if not applying here
            } else {
                $args['subqry'] = "SELECT ID FROM $wpdb->posts AS p WHERE 1=1 AND ( p.post_type IN ('$type_csv') ) $pp_where";  // pass this into filter even if not applying here
            }
            $args['subqry_args'] = $_args;
            $args['subqry_typecsv'] = $type_csv;
        }

        if ('read' == $args['required_operation']) {
            global $current_user;

            $attached_vis_clause = ($do_parent_subquery) ? "$src_table.post_parent IN ({$args['subqry']})" : "1=1";
            $attached_vis_clause = apply_filters('presspermit_attached_visibility_clause', $attached_vis_clause, $clauses, $_args);

            $unattached_vis_clause = apply_filters(
                'presspermit_unattached_visibility_clause', 
                "$src_table.post_parent = '0'", 
                $clauses, 
                $_args
            );

            $own_clause = (apply_filters('presspermit_read_own_attachments', false, $args)) ? PWP::postAuthorClause($args) . ' OR ' : '';

            if (apply_filters('presspermit_attachments_allow_unfiltered_parent', class_exists('SlideDeckPlugin', false))) {
                $pp_type_csv = implode("','", array_map('sanitize_key', array_merge(presspermit()->getEnabledPostTypes(), ['revision', 'attachment'])));
                $non_pp_parent_clause = " OR ( $src_table.post_parent IN ( SELECT ID FROM $wpdb->posts WHERE post_type NOT IN ('$pp_type_csv') ) )";
            } else
                $non_pp_parent_clause = '';

            $where = str_replace(
                "$src_table.post_type = 'attachment'",
                "( $src_table.post_type = 'attachment' AND ( $own_clause ( $attached_vis_clause ) OR ( $unattached_vis_clause ) $non_pp_parent_clause ) )",
                $where
            );
        }

        return apply_filters('presspermit_append_attachment_clause', $where, $clauses, $args);
    }
}

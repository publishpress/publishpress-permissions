<?php
namespace PublishPress\Permissions\Collab;

class MediaQuery
{
    public static function appendAttachmentClause($where, $clauses, $args)
    {
        $defaults = [
            'src_table' => '',
            'source_alias' => '',
            'required_operation' => 'edit',
            'limit_statuses' => false,
            'skip_stati_usage_clause' => false,
            'has_cap_check' => '',
            'query_contexts' => [],
            'subqry' => '',
            'subqry_args' => [],
            'subqry_typecsv' => '',
        ];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb, $current_user;

        $pp = presspermit();

        if ('read' == $required_operation)
            return $where;

        if (!$limit_statuses)
            $skip_stati_usage_clause = true;

        if (!$src_table)
            $src_table = ($source_alias) ? $source_alias : $wpdb->posts;

        $comment_query = isset($query_contexts) && in_array('comments', (array)$query_contexts, true);

        if ((!$has_cap_check || ('read' == $required_operation)) && !$comment_query) {
            $admin_others_attached = $pp->getOption('admin_others_attached_files');
        } else {
            if ($admin_others_attached = ('parent' == $pp->getOption('edit_others_media'))) {
                if ($comment_query && ('read' != $required_operation))
                    $admin_others_attached = !empty($current_user->allcaps['edit_others_files']);
            }
        }

        $type_obj = get_post_type_object('attachment');

        if ('delete' == $required_operation)
            $reqd_cap = ($type_obj->cap->delete_others_posts != 'edit_others_posts') ? $type_obj->cap->delete_others_posts : 'delete_others_files';
        else
            $reqd_cap = ($type_obj->cap->edit_others_posts != 'edit_others_posts') ? $type_obj->cap->edit_others_posts : 'edit_others_files';

        // edit_others_unattached_files
        $admin_others_unattached = (!$has_cap_check || !empty($current_user->allcaps[$reqd_cap])) 
        && ($pp->getOption('admin_others_unattached_files') 
            || !empty($current_user->allcaps['list_others_unattached_files']) 
            || !empty($current_user->allcaps['edit_others_files'])
        );  // PP Setting effectively eliminates cap requirement

        $can_edit_others_sitewide = false;

        if (!$admin_others_attached || !$admin_others_unattached) {
            if ($pp->isContentAdministrator())
                $can_edit_others_sitewide = true;
        }

        // optionally hide other users' unattached uploads, but not from site-wide Editors
        if ($admin_others_unattached || $can_edit_others_sitewide) {
            $author_clause = '';
        } else {
            $author_clause = "AND " . PWP::postAuthorClause($args);
        }

        if (!$subqry && $author_clause && !$admin_others_unattached && !$admin_others_attached && !$can_edit_others_sitewide) {
            $where .= " AND ( 1=1 $author_clause )";
        } else {
            $parent_subquery = ($subqry) ? "$src_table.post_parent IN ($subqry)" : "$src_table.post_parent > 0";

            $admin_others_attached_to_readable = (!$has_cap_check || ('read_post' == $has_cap_check)) && $pp->getOption('admin_others_attached_to_readable');

            // todo: leaner implementation?
            if ($subqry && $admin_others_attached_to_readable && ('edit' == $required_operation) && $subqry_args) {
                $subqry_args['required_operation'] = 'read';
                $pp_where = apply_filters('presspermit_posts_where', '', $subqry_args);
                $readable_parents_subqry = "SELECT ID FROM $wpdb->posts AS p WHERE 1=1 AND p.post_type IN ('" . $subqry_typecsv . "') $pp_where";
                $parent_subquery = "( $parent_subquery OR $src_table.post_parent IN ( $readable_parents_subqry ) )";
            }

            if (is_admin() && (!defined('PP_BLOCK_UNATTACHED_UPLOADS') || !PP_BLOCK_UNATTACHED_UPLOADS)) {
                $unattached_clause = "( $src_table.post_parent = 0 $author_clause ) OR";
            } else {
                $unattached_clause = '';
            }

            $author_clause = PWP::postAuthorClause($args);

            $attached_clause = ($admin_others_attached || $can_edit_others_sitewide || $admin_others_attached_to_readable) 
            ? '' 
            : "AND $author_clause";

            $own_clause = ($pp->getOption('own_attachments_always_editable') || !empty($current_user->allcaps['edit_own_attachments'])) 
            ? "$author_clause OR " 
            : '';
            
            $where .= " AND ( {$own_clause}$unattached_clause ( $parent_subquery $attached_clause ) )";
        }

        $where = str_replace("( $src_table.post_parent = 0 ) OR ( $src_table.post_parent > 0 )", "1=1", $where);

        return $where;
    }
}

<?php
namespace PublishPress\Permissions\Collab\Revisions;

\defined('ABSPATH') || exit;

class AdminNonAdministrator
{
    function __construct() {
        add_filter('presspermit_generate_where_clause_force_vars', [$this, 'fltWhereClauseRevisionary'], 10, 3);
    }

    function fltWhereClauseRevisionary($force_vars, $source_name, $args)
    {
        // accomodate editing of published posts/pages to revision
        if (!defined('PUBLISHPRESS_REVISIONS_VERSION')) {
            return $force_vars;
        }

        global $revisionary;
        if (!empty($revisionary->skip_revision_allowance)) {
            return $force_vars;
        }

        $return = [];

        // enable authors to view / edit / approve revisions to their published posts
        if (!defined('HIDE_REVISIONS_FROM_AUTHOR')) {
            global $wpdb;
            $src_table = ($args['source_alias']) ? $args['source_alias'] : $wpdb->posts;

            if (!empty($args['user']->ID)) {

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                if ($owner_object_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_author = %d", 
                        $args['object_type'], 
                        $args['user']->ID
                        )
                    )
                ) {
                    $revision_base_status_csv = rvy_revision_base_statuses(['return' => 'csv']);
	                $revision_status_csv = rvy_revision_statuses(['return' => 'csv']);

                    $return['parent_clause'] = "( $src_table.post_status IN ($revision_base_status_csv) AND $src_table.post_mime_type IN ($revision_status_csv) AND ( post_author = " . intval($args['user']->ID) 
                    . " OR $src_table.comment_count IN ('" . implode("','", $owner_object_ids) . "') ) ) OR ";
                }
            }
        }

        return ($return) ? array_merge((array)$force_vars, $return) : $force_vars;
    }
}

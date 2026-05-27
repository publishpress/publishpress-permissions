<?php
namespace PublishPress\Permissions\UI\Dashboard;

class PostsListing
{
    private $exceptions;

    public function __construct()
    {
        if (!$post_type = PWP::REQUEST_key('post_type')) {
            $post_type = 'post';
        }

        wp_enqueue_style('presspermit-post-listing', PRESSPERMIT_URLPATH . '/common/css/post-listing.css', [], PRESSPERMIT_VERSION);

        add_filter("manage_{$post_type}_posts_columns", [$this, 'fltManagePostsColumns'], 50); // need late priority to avoid being wiped by other plugins
        add_action("manage_{$post_type}_posts_custom_column", [$this, 'fltManagePostsCustomColumn'], 10, 2);

        add_filter('wp_count_posts', [$this, 'fltCountPosts']);
        add_filter('query', [$this, 'fltCountPostsQuery']);

        do_action('presspermit_post_listing_ui');

        add_filter('postsFields', [$this, 'fltPostsFields']); // perf
    }

    public function fltCountPosts($counts) {
        // don't count posts that are stored with a status that's no longer registered
        $counts = array_intersect_key((array) $counts, array_fill_keys(get_post_stati(), true));

        return (object) $counts;
    }

    public function fltCountPostsQuery($query) {
        global $typenow;

        if (!empty($typenow)) {
            if (strpos($query, "ELECT COUNT( 1 )")) {
                $statuses_clause = " AND post_status IN ('" . implode("','", get_post_stati()) . "')"; 
                $query = str_replace("WHERE post_type = '$typenow'", "WHERE post_type = '$typenow' $statuses_clause", $query);
            }
        }

        return $query;
    }

    public function fltManagePostsColumns($columns)
    {
        if (presspermit()->admin()->bulkRolesEnabled()) {
            if (!isset($this->exceptions)) {
                global $wpdb, $typenow;

                $pp = presspermit();

                $this->exceptions = [];

                if (!empty($pp->listed_ids[$typenow])) {
                    $agent_type_csv = implode("','", array_map('sanitize_key', array_merge(['user'], $pp->groups()->getGroupTypes())));

                    $id_csv = implode("','", array_map('intval', array_keys($pp->listed_ids[$typenow])));

                    // One locally cached direct query of plugin tables to retrieve exceptions info for all listed Posts
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $results = $wpdb->get_results(
                        "SELECT DISTINCT i.item_id, e.operation FROM $wpdb->ppc_exceptions AS e"
                        . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                        . " WHERE e.for_item_source = 'post' AND e.via_item_source = 'post'"
                        . " AND e.agent_type IN ('$agent_type_csv') AND i.item_id IN ('$id_csv')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    );

                    foreach ($results as $row) {
                        if (!isset($this->exceptions[$row->item_id])) {
                            $this->exceptions[$row->item_id] = [];
                        }

                        $this->exceptions[$row->item_id][] = $row->operation;
                    }
                }
            }

            if ($this->exceptions) {
                $columns['pp_exceptions'] = esc_html__('Permissions', 'press-permit-core');
            }
        }

        return $columns;
    }

    public function fltManagePostsCustomColumn($column_name, $id)
    {
        if ('pp_exceptions' == $column_name) {
            if (!empty($this->exceptions[$id])) {
                global $typenow;

                $op_names = [];

                foreach ($this->exceptions[$id] as $op) {
                    if ($op_obj = presspermit()->admin()->getOperationObject($op, $typenow)) {
                        $op_names[] = (isset($op_obj->abbrev)) ? $op_obj->abbrev : $op_obj->label;
                    }
                }

                uasort($op_names, 'strnatcasecmp');
                echo esc_html(implode(", ", $op_names));
            }
        }
    }

    // perf enhancement: query only fields which pertain to listing or quick edit
    public function fltPostsFields($cols)
    {
        global $typenow;

        if (defined('PP_LEAN_' . strtoupper($typenow) . '_LISTING') && presspermit()->filteringEnabled()) {
            global $wpdb;
            $p = $wpdb->posts;
            $cols = str_replace(
                "$p.*",
                "$p.ID, $p.post_author, $p.post_date, $p.post_date_gmt, $p.post_title, $p.post_status, $p.comment_status,"
                . " $p.ping_status, $p.post_password, $p.post_name, $p.to_ping, $p.pinged, $p.post_parent, $p.post_modified,"
                . " $p.post_modified_gmt, $p.guid, $p.post_type, $p.post_mime_type, $p.menu_order, $p.comment_count",
                $cols
            );
        }

        return $cols;
    }
}

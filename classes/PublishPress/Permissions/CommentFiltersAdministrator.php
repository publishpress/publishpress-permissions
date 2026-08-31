<?php

namespace PublishPress\Permissions;

class CommentFiltersAdministrator
{
    public function __construct() {
        add_filter('comments_clauses', [$this, 'fltCommentsClauses']);
        add_filter('comment_feed_join', [$this, 'fltCommentFeedJoin'], 10, 2);
        add_filter('comment_feed_where', [$this, 'fltCommentFeedWhere'], 10, 2);
    }

    public function fltCommentsClauses($clauses)
    {
        global $wpdb;

        $stati = get_post_stati(['public' => true, 'private' => true], 'names', 'or');

        if (!defined('PP_NO_ATTACHMENT_COMMENTS'))
            $stati[] = 'inherit';

        $status_csv = "'" . implode("','", array_map('sanitize_key', $stati)) . "'";
        $clauses['where'] = preg_replace(
            "/\s*AND\s*{$wpdb->posts}.post_status\s*=\s*[']?publish[']?/",
            "AND {$wpdb->posts}.post_status IN ($status_csv)",
            $clauses['where']
        );

        return $clauses;
    }

    public function fltCommentFeedWhere($where)
    {
        global $wpdb;

        $stati = get_post_stati(['public' => true, 'private' => true], 'names', 'or');

        if (!defined('PP_NO_ATTACHMENT_COMMENTS')) {
            $stati[] = 'inherit';
        }

        $status_csv = "'" . implode("','", array_map('sanitize_key', $stati)) . "'";

        return preg_replace(
            "/\s*AND\s*post_status\s*=\s*[']?publish[']?/",
            " AND {$wpdb->posts}.post_status IN ($status_csv)",
            $where
        );
    }

    public function fltCommentFeedJoin($join, $query_obj = false)
    {
        global $wpdb;

        if (false === strpos($join, $wpdb->posts)) {
            $join .= " JOIN {$wpdb->posts} ON ( {$wpdb->comments}.comment_post_ID = {$wpdb->posts}.ID )";
        }

        return $join;
    }
}

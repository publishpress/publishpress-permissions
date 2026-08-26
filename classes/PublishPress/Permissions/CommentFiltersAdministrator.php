<?php

namespace PublishPress\Permissions;

class CommentFiltersAdministrator
{
    public function __construct() {
        add_filter('comments_clauses', [$this, 'fltCommentsClauses']);
        add_filter('comment_feed_where', [$this, 'fltCommentFeedWhere']);
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
}

<?php

namespace PublishPress\Permissions;

class CommentFilters
{
    public function __construct() {
        add_filter('comments_clauses', [$this, 'fltCommentsClauses'], 10, 2);
        add_filter('comment_feed_join', [$this, 'fltCommentFeedJoin'], 10, 2);
        add_filter('comment_feed_where', [$this, 'fltCommentFeedWhere'], 10, 2);
        add_filter('comments_array', [$this, 'fltCommentResults'], 99);
        add_filter('the_comments', [$this, 'fltCommentResults'], 99);
    }

    public function fltCommentsClauses($clauses, $qry_obj = false, $args = [])
    {
        global $wpdb;

        $defaults = ['query_contexts' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $query_contexts[] = 'comments';

        if (did_action('comment_post'))  // don't filter comment retrieval for email notification
            return $clauses;

        if (is_admin() && defined('PP_NO_COMMENT_FILTERING')) {
            global $current_user;

            return $clauses;
        }

        if (empty($clauses['join']) || !strpos($clauses['join'], $wpdb->posts))
            $clauses['join'] .= " INNER JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID";

        $matches = [];
        preg_match("/ JOIN $wpdb->posts AS ([0-9a-z_$#]+) /", $clauses['join'], $matches);
        $p = (!empty($matches[1])) ? $matches[1] : $wpdb->posts;

        // (subsequent filter will expand to additional statuses as appropriate)
        $clauses['where'] = preg_replace("/ post_status\s*=\s*[']?publish[']?/", " $p.post_status = 'publish'", $clauses['where']);

        $post_type = '';
        $post_id = ($qry_obj && !empty($qry_obj->query_vars['post_id'])) ? $qry_obj->query_vars['post_id'] : 0;

        if ($post_id) {
            if ($_post = get_post($post_id))
                $post_type = $_post->post_type;
        } else {
            $post_type = ($qry_obj && isset($qry_obj->query_vars['post_type'])) ? $qry_obj->query_vars['post_type'] : '';
        }

        if ($post_type && !in_array($post_type, presspermit()->getEnabledPostTypes(), true))
            return $clauses;

        if ($p && ($p != $wpdb->posts)) {
            $args['source_alias'] = $p;
        }

        $clauses['where'] = "1=1 " . apply_filters( 'presspermit_posts_where', 
                'AND ' . $clauses['where'],
                array_merge($args, ['post_types' => $post_type, 'skip_teaser' => true, 'query_contexts' => $query_contexts])
            );

        if (!empty($clauses['groupby']) && !empty($clauses['select']) && (false !== stripos($clauses['select'], 'COUNT(')) && !defined('PRESSPERMIT_LEGACY_COMMENT_FILTERING')) {
            $clauses['orderby'] = '';
        }

        return $clauses;
    }

    public function fltCommentFeedWhere($where, $query_obj = false)
    {
        global $wpdb;

        $query_contexts = ['comments'];

        $post_type = '';
        $post_id = ($query_obj && !empty($query_obj->query_vars['p'])) ? (int) $query_obj->query_vars['p'] : 0;

        if ($post_id) {
            if ($_post = get_post($post_id)) {
                $post_type = $_post->post_type;
            }
        } else {
            $post_type = ($query_obj && !empty($query_obj->query_vars['post_type'])) ? $query_obj->query_vars['post_type'] : '';
        }

        if ($post_type && !in_array($post_type, presspermit()->getEnabledPostTypes(), true)) {
            return $where;
        }

        $where = preg_replace("/ post_status\s*=\s*[']?publish[']?/", " {$wpdb->posts}.post_status = 'publish'", $where);

        $where = preg_replace('/^\s*WHERE\s+/i', '', $where);

        if (!class_exists('\PublishPress\Permissions\PostFilters')) {
            require_once(PRESSPERMIT_CLASSPATH . '/PostFilters.php');
        }

        $feed_post_types = ($post_type) ? [$post_type] : presspermit()->getEnabledPostTypes();

        $pp_where = PostFilters::instance()->getPostsWhere(
            [
                'post_types' => $feed_post_types,
                'required_operation' => 'read',
                'skip_teaser' => true,
                'query_contexts' => $query_contexts,
                'query_vars' => ($query_obj && !empty($query_obj->query_vars)) ? $query_obj->query_vars : [],
                'src_table' => $wpdb->posts,
            ]
        );

        return "WHERE 1=1 $pp_where AND $where";
    }

    public function fltCommentFeedJoin($join, $query_obj = false)
    {
        global $wpdb;

        if (false === strpos($join, $wpdb->posts)) {
            $join .= " JOIN {$wpdb->posts} ON ( {$wpdb->comments}.comment_post_ID = {$wpdb->posts}.ID )";
        }

        return $join;
    }

    public function fltCommentResults($comments)
    {
        foreach (array_keys((array) $comments) as $key) {
            if (!empty($comments[$key]->comment_post_ID) && !$this->isCommentPostReadable((int) $comments[$key]->comment_post_ID)) {
                unset($comments[$key]);
            }
        }

        return $comments;
    }

    private function isCommentPostReadable($post_id)
    {
        static $readable = [];

        if (isset($readable[$post_id])) {
            return $readable[$post_id];
        }

        $post_type = get_post_field('post_type', $post_id);

        if (!$post_type || !in_array($post_type, presspermit()->getEnabledPostTypes(), true)) {
            return $readable[$post_id] = true;
        }

        if (!class_exists('\PublishPress\Permissions\PostFilters')) {
            require_once(PRESSPERMIT_CLASSPATH . '/PostFilters.php');
        }

        global $wpdb;

        $where = PostFilters::instance()->getPostsWhere(
            [
                'post_types' => $post_type,
                'required_operation' => 'read',
                'skip_teaser' => true,
                'query_contexts' => ['comments'],
                'src_table' => $wpdb->posts,
            ]
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT {$wpdb->posts}.ID FROM {$wpdb->posts} WHERE {$wpdb->posts}.ID = %d $where LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $post_id
            )
        );

        return $readable[$post_id] = !empty($result);
    }
}

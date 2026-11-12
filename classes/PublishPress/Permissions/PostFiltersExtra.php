<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class PostFiltersExtra
{
    // Other supported arguments, processed by downstream functions:
    //  skip_teaser         - applied by fltPostsWhere()
    //  required_operation  - ''
    //  source_alias        - ''
    //
    public static function fltPostsRequest($request, $args = [])
    {
        global $current_user;

        if (presspermit()->isUserUnfiltered($current_user->ID, $args)) {
            return $request;
        }

        $defaults = ['post_types' => [], 'source_alias' => '', 'only_append_where' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (apply_filters('presspermit_posts_request_bypass', false, $request, $args)) {
            return $request;
        }

        if (!preg_match('/\s*WHERE\s*1=1/', $request)) {
            $request = preg_replace('/\s*WHERE\s*/', ' WHERE 1=1 AND ', $request);
        }

        $clauses = [];
        $pos_where = 0;
        $pos_suffix = 0;

        // NOTE: any existing where, orderby or group by clauses remain in $where
        $clauses['where'] = self::parseAfterWhere_1_1($request, $pos_where, $pos_suffix);
        if (!$pos_where && $pos_suffix) {
            $request = substr($request, 0, $pos_suffix) . ' WHERE 1=1' . substr($request, $pos_suffix);
            $pos_where = $pos_suffix;
        }

        if (!$only_append_where) {
            if (!isset($args['source_alias'])) {
                // If the query uses an alias for the posts table, be sure to use that alias in the WHERE clause also.
                //
                // NOTE: If query refers to non-active site, this code will prevent a DB syntax error, but will not cause the correct roles / statuses to be applied.
                //       Other plugins need to use switch_to_blog() rather than just executing a query on a non-main site.
                $matches = [];
                if (preg_match('/SELECT .* FROM [^ ]+posts AS ([^ ]) .*/', $request, $matches)) {
                    $args['source_alias'] = $matches[1];
                
                } elseif ($return = preg_match('/SELECT .* FROM ([^ ]+)posts .*/', $request, $matches)) {
                    $args['source_alias'] = $matches[1] . 'posts';
                }
            }

            if (false !== strpos($request, ' COUNT( * ) AS num_posts')) {
                $args['include_trash'] = true;
            }

            // attachment filtering is applied here
            $clauses['where'] = apply_filters(
                'presspermit_posts_clauses_where', apply_filters('presspermit_posts_where', $clauses['where'], $args),
                $clauses,
                $args
            );
        }

        if ($pos_where === false) {
            $request .= " WHERE 1=1 $only_append_where " . $clauses['where'];
        } else {
            $request = substr($request, 0, $pos_where) . " WHERE 1=1 $only_append_where " . $clauses['where']; // any pre-exising join clauses remain in $request
        }

        return $request;
    } // end function fltPostsRequest

    private static function getSuffixPos($request)
    {
        $request_u = strtoupper($request);

        $pos_suffix = strlen($request) + 1;
        foreach ([' ORDER BY ', ' GROUP BY ', ' LIMIT '] as $suffix_term) {
            if ($pos = strrpos($request_u, $suffix_term)) {
                if ($pos < $pos_suffix) {
                    $pos_suffix = $pos;
                }
            }
        }

        return $pos_suffix;
    }

    private static function parseAfterWhere_1_1($request, &$pos_where, &$pos_suffix)
    {
        $request_u = strtoupper($request);
        
        if (!$pos_where = strpos($request_u, ' WHERE 1=1')) {
            $matches = [];
            $return = preg_match('/(\n[\s]+)(WHERE 1=1).*/', $request_u, $matches);

            if ($return && !empty($matches[1]) && !empty($matches[2])) {
                $pos_where = strpos($request_u, $matches[1] . $matches[2]) + strlen($matches[1]);
            }
        }

        if (!$pos_where) {
            if ($pos_suffix = self::getSuffixPos($request)) {
                $where = substr($request, $pos_suffix);
            }
        } else {
            // note: this will still also contain any orderby/limit/groupby clauses ( okay since we won't append anything to the end )
            $where = substr($request, $pos_where + strlen(' WHERE 1=1 '));
        }

        return $where;
    }

} // end class

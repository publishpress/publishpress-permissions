<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class CommentFiltersAdmin
{
    function __construct() {
        add_filter('the_comments', [$this, 'fltLogCommentPostIds']);

        add_filter('map_meta_cap', [$this, 'fltAdjustReqdCaps'], 1, 4);        
    }

    public function fltLogCommentPostIds($comments)
    {
        // buffer the listed IDs for more efficient user_has_cap calls
        $pp = presspermit();
        if (empty($pp->listed_ids)) {
            $pp->listed_ids = [];

            foreach ($comments as $row) {
                if (!empty($row->comment_post_ID)) {
                    $post_type = get_post_field('post_type', $row->comment_post_ID);
                    $pp->listed_ids[$post_type][$row->comment_post_ID] = true;
                }
            }
        }

        return $comments;
    }

    public function fltAdjustReqdCaps($reqd_caps, $orig_cap, $user_id, $args)
    {
        global $pagenow;

        // users lacking edit_posts cap may have moderate_comments capability via a supplemental role
        if (('edit-comments.php' == $pagenow) && ($key = array_search('edit_posts', $reqd_caps))) {
            global $current_user;
            if (did_action('load-edit-comments.php') && ($user_id == $current_user->ID) && empty($current_user->allcaps['edit_posts'])) {
                $reqd_caps[$key] = 'moderate_comments';
            }
        }

        return $reqd_caps;
    }
}

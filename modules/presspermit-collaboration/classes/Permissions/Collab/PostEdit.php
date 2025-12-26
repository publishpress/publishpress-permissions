<?php
namespace PublishPress\Permissions\Collab;

class PostEdit
{
    public static function fltPostStatus($status)
    {
        if (!$post_id = presspermit()->getCurrentSanitizePostID()) {
            $post_id = PWP::getPostID();
        }

        if ($post_id) {
            $post_type = get_post_field('post_type', $post_id);
        } else {
            $post_type = PWP::findPostType();
        }

        if ($type_obj = get_post_type_object($post_type)) {
            if ($type_obj->hierarchical && post_type_supports($post_type, 'page-attributes')) {
                if (!Collab::userCanAssociateMain($post_type)) {
                    require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostSaveHierarchical.php');
                    $status = PostSaveHierarchical::enforceTopPagesLock($status);
                }
            }
        }

        return $status;
    }

    public static function userCanAssociateMain($post_type)
    {
        global $current_user;

        if (presspermit()->isUserUnfiltered($current_user->ID, compact('post_type')))
            return true;

        if (!$post_type_obj = get_post_type_object($post_type))
            return true;

        if (!$post_type_obj->hierarchical)
            return true;

        $user = presspermit()->getUser();

        $required_operation = (presspermit()->getOption('page_parent_editable_only')) ? 'edit' : 'associate';

        // apply manually assigned associate exceptions even if lock_top_pages filtering is disabled
        $post_ids = $user->getExceptionPosts($required_operation, 'exclude', $post_type);
        if (in_array(0, $post_ids))
            return false;

        $post_ids = $user->getExceptionPosts($required_operation, 'include', $post_type);
        if ($post_ids && !in_array(0, $post_ids))
            return false;

        $post_ids = $user->getExceptionPosts('edit', 'include', $post_type);
        if ($post_ids) {
            global $post;

            if ($additional_post_ids = $user->getExceptionPosts('edit', 'additional', $post_type))
                $post_ids = array_merge($post_ids, $additional_post_ids);

            // cannot currently support propagation of parent exceptions to new top level pages, 
            // so don't offer (no parent) as a post parent selection if editing is limited to a subset of pages and this page is not in that subset
            $post_id = PWP::getPostID();
            if (!$post_id || !in_array($post_id, $post_ids))
                return false;
        }

        $top_pages_locked = presspermit()->getOption('lock_top_pages');

        if ('no_parent_filter' == $top_pages_locked)
            return true;

        if (('page' == $post_type) || !defined('PP_LOCK_OPTION_PAGES_ONLY')) {
            if ('1' === $top_pages_locked) {
                // only administrators can change top level structure
                return false;
            } else {
                $reqd_caps = ('author' === $top_pages_locked) 
                ? $post_type_obj->cap->publish_posts 
                : $post_type_obj->cap->edit_others_posts;

                return current_user_can($reqd_caps);
            }
        } else
            return true;
    }
}

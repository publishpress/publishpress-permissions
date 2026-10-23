<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class PostSave
{
    public static function actSaveItem($item_source, $post_id, $post)
    {
        if (!method_exists('\PressShack\LibWP', 'is_REQUEST') || PWP::is_REQUEST('action', 'untrash')) {
            return;
        }

        // WP always passes post object into do_action('save_post')
        if (!is_object($post)) {
            if (!$post_id) {
                return;
            }
            
            if (!$post = get_post($post_id)) {
                return;
            }
        }

        // operations in this function do not apply to revision save
        if ('revision' == $post->post_type) {
            return;
        }

        if (!in_array($post->post_type, presspermit()->getEnabledPostTypes(), true)) {
            if (!PWP::empty_REQUEST('pp_enable_post_type')) {
                $enabled = get_option('presspermit_enabled_post_types');
                $enabled[$post->post_type] = '1';

                update_option('presspermit_enabled_post_types', $enabled);
            }
            return;
        }

        // don't execute this action handler more than one per post save
        static $saved_items;
        if (!isset($saved_items)) {
            $saved_items = [];
        }
        if (isset($saved_items[$post_id])) {
            return;
        }

        $saved_items[$post_id] = 1;

        $is_new = self::isNewPost($post_id, $post);

        if (is_post_type_hierarchical($post->post_type)) {
            $parent_info = self::getPostParentInfo($post_id, $post, true);
            $set_parent = (isset($parent_info['set_parent'])) ? $parent_info['set_parent'] : false;
            $last_parent = (isset($parent_info['last_parent'])) ? $parent_info['last_parent'] : false;

            if (is_numeric($last_parent)) { // not theoretically necessary, but an easy safeguard to avoid re-inheriting parent roles
                $is_new = false;
            }
        } else {
            $set_parent = 0;
            $last_parent = 0;
        }

        if (!PWP::is_REQUEST('page', 'rvy-revisions')) {
            usleep(5000); // Work around intermittent failure to propagate exceptions.  Maybe storage of post row is delayed on some db servers.
            require_once(PRESSPERMIT_CLASSPATH . '/ItemSave.php');
            ItemSave::itemUpdateProcessExceptions('post', 'post', $post_id, compact('is_new', 'set_parent', 'last_parent'));
        }
    }

    public static function isNewPost($post_id, $post_obj = false)
    {
        if (!$post_obj) {
            $post_obj = get_post($post_id);
        }

        if ( empty($post_obj) ) {  
			global $wp_query;
			if ( empty($wp_query->queried_object) ) { // Revisionary: pending revision submission with WPML active
				return false;	
			} else {
                return true;
            }
		}

        if ('auto-draft' == $post_obj->post_status) {
            return true;
        }

        $last_status = presspermit()->admin()->getLastPostStatus($post_id);

        return (!$last_status || in_array($last_status, ['auto-draft', 'new'], true));
    }

    public static function getPostParentInfo($post_id, $post_obj = false, $update_meta = false)
    {
        if (!$post_obj && $post_id) {
            $post_obj = get_post($post_id);
        }

        if (!$post_obj) {
            return array_fill_keys(['last_parent', 'set_parent'], 0);
        }

        // parent settings can affect the auto-assignment of propagating roles / conditions
        $set_parent = $post_obj->post_parent;
        $last_parent = ($post_id > 0) ? get_post_meta($post_id, '_pp_last_parent', true) : 0;

        if ($update_meta) {
            if (isset($set_parent) && (intval($set_parent) != intval($last_parent)) && ($set_parent || $last_parent)) {
                update_post_meta($post_id, '_pp_last_parent', (int)$set_parent);
            }
        }

        return compact('last_parent', 'set_parent');
    }
}

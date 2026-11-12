<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class MediaFilters
{
    public function __construct() {
        global $wp_post_types;
        $wp_post_types['attachment']->map_meta_cap = false;

        add_filter('map_meta_cap', [$this,'fltMapMediaMetaCap'], 2, 4);
    }

    // Work around inappropriate edit cap requirement mapped by WP core map_meta_cap().  
    // Problem with WP core is that it uses get_post_status() in WP_Query but not in map_meta_cap().
    public function fltMapMediaMetaCap($caps, $cap, $user_id, $args)
    {
        if (!empty($args[0])) {
            $post = (is_object($args[0])) ? $args[0] : get_post((int) $args[0]);

            if ($post && ('attachment' == $post->post_type)) {
                if (!empty($post->post_parent))
                    $post_status = get_post_status($post->ID);
                elseif ('inherit' == $post->post_status)
                    $post_status = (presspermit()->getOption('unattached_files_private')) ? 'private' : 'publish';
                else
                    $post_status = $post->post_status;

                $post_type = get_post_type_object($post->post_type);
                $post_author_id = $post->post_author;

                $caps = array_diff($caps, (array)$cap);

                switch ($cap) {
                    case 'read_post':
                    case 'read_page':
                        if (!$status_obj = get_post_status_object($post_status)) {
                            $caps = array_diff($caps, [$post_type->cap->read]);
                            return $caps;
                        }

                        if ($status_obj->public || ($status_obj->private && ($user_id == $post_author_id))) {
                            if ($post_type && isset($post_type->cap) && isset($post_type->cap->read)) {
                                $caps[] = $post_type->cap->read;
                            }
                            break;
                        }

                        // If no author set yet, default to current user for cap checks.
                        if (!$post_author_id)
                            $post_author_id = $user_id;

                        if ($status_obj->private || presspermit()->moduleActive('file-access'))
                            $caps[] = $post_type->cap->read_private_posts;
                        else
                            $caps = map_meta_cap('edit_post', $user_id, $post->ID);

                        $caps = apply_filters('presspermit_map_attachment_read_caps', $caps, $post, $user_id);

                        break;
                    default:
                        if (!presspermit()->doing_rest) {
                        	require_once(PRESSPERMIT_CLASSPATH . '/MediaEdit.php');

                        	$args = array_merge($args, compact('post', 'post_status', 'post_type', 'post_author_id'));
                        	$caps = MediaEdit::mapMetaCap($caps, $cap, $user_id, $args);
                        }
                }
            }
        }

        return $caps;
    }
}

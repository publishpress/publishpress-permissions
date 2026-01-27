<?php
namespace PublishPress\Permissions\Collab;

class CapabilityFilters
{
    function __construct()
    {
        add_filter('presspermit_has_post_cap_vars', [$this, 'fltHasPostCapVars'], 10, 4);

        add_filter('presspermit_cap_operation', [$this, 'fltCapOperation'], 10, 3);
        add_filter('presspermit_exception_stati', [$this, 'fltExceptionStati'], 10, 4);

        add_filter('presspermit_user_has_cap_params', [$this, 'fltUserHasCapParams'], 10, 3);

        add_filter('publishpress_statuses_can_publish', [$this, 'fltCanPublish'], 10, 2);

        if (!defined('PRESSPERMIT_DISABLE_TERM_PREASSIGN')) {
            add_action('presspermit_has_post_cap_pre', [$this, 'actSavePostPreAssignTerms'], 10, 4);
        }
    }
    
    function fltUserHasCapParams($params, $orig_reqd_caps, $args)
    {
        $defaults = ['orig_cap' => '', 'item_id' => 0];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $return = [];

        if ('edit_comment' == $orig_cap) {
            if ($comment = get_comment($item_id)) {
                if ($return['item_id'] = $comment->comment_post_ID) {
                    if ($_post = get_post($comment->comment_post_ID)) {
                        $return['item_type'] = $_post->post_type;
                        $return['item_status'] = $_post->post_status;
                    }
                }
            } else
                $return['item_id'] = 0;
        } elseif ($post_type = PWP::findPostType()) {
            if ($type_obj = get_post_type_object($post_type)) {
                if (!empty($type_obj->cap->publish_posts) && ($orig_cap == $type_obj->cap->publish_posts)) {
                    $return['item_type'] = $post_type;
                    $return['type_caps'] = [$orig_cap];
                }
            }
        }

        if ($return)
            return (is_array($params)) ? array_merge($params, $return) : $return;
        else
            return $params;
    }

    function fltExceptionStati($stati, $item_status, $op, $args = [])
    {
        $status_obj = get_post_status_object($item_status);

        if (!empty($args['item_type']) && !empty($args['orig_reqd_caps'])) {
            // don't grant publish cap based on a status-specific term addition (such as "unpublished")
            $type_obj = get_post_type_object($args['item_type']);

            if (!presspermit()->getOption('publish_exceptions') && $type_obj && (reset($args['orig_reqd_caps']) == $type_obj->cap->publish_posts)) {
                $stati[''] = true;

                if (!$item_status || $status_obj->public) {
                    $stati['post_status:publish'] = true;
            	}

                return $stati;
            }
        }

        if (('read' != $op) && (!$item_status || (!$status_obj->public && !$status_obj->private))) {
            $stati['post_status:{unpublished}'] = true;
        }

        return $stati;
    }

    function fltCapOperation($op, $base_cap, $item_type)
    {
        if (!$type_obj = get_post_type_object($item_type))
            return '';

        switch ($base_cap) {
            case $type_obj->cap->edit_posts:
                $op = 'edit';
                break;
            case $type_obj->cap->publish_posts:
                global $pagenow;
                $op = (presspermit()->getOption('publish_exceptions') || (!empty($pagenow && ('post-new.php' == $pagenow)))) ? 'publish' : 'edit';
                break;
            case $type_obj->cap->delete_posts:
                $op = 'delete';
                break;
        }

        return $op;
    }

    function fltHasPostCapVars($force_vars, $wp_sitecaps, $pp_reqd_caps, $vars)
    {
        $defaults = ['post_type' => '', 'post_id' => 0, 'user_id' => 0, 'required_operation' => ''];
        $vars = array_merge($defaults, $vars);
        foreach (array_keys($defaults) as $var) {
            $$var = $vars[$var];
        }

        $return = [];

        // Don't block non-Administrators from editing their own auto-draft
        if ($post_id && (is_admin() || (defined('REST_REQUEST') && !defined('PP_LEGACY_REST_FILTERING')))) {
            $_post = get_post($post_id);

            if (!empty($_post) && ($_post->ID == $post_id) && ('auto-draft' == $_post->post_status)) {
                if ($type_obj = get_post_type_object($_post->post_type)) {
                    if (in_array(reset($pp_reqd_caps), ['edit_post', 'edit_page'], true)) {
                        $return['return_caps'] = [$type_obj->cap->edit_posts => true];
                    }
                }
            }
        }

        //=== For revisions, pretend questioned object is the parent post
        //
        if ($post_id && in_array($post_type, ['revision'], true)) {
            if ($_post = get_post($post_id)) {
                if ($_post->post_parent && $_parent = get_post($_post->post_parent)) {
                    global $current_user;

                    // parent is a regular post type
                    $post_type = $_parent->post_type;
                    $post_id = $_parent->ID;

                    if (($_parent->post_status == 'auto-draft') && ($_parent->post_author == $current_user->ID)) {
                        $post_id = 0;
                    }

                    if ('inherit' == $_post->post_status) {
                        $return['required_operation'] = 'edit';
                    }

                    $return['post_type'] = $post_type;
                    $return['post_id'] = $post_id;
                }

                $user = presspermit()->getUser();

                //=== Special case of Attachment uploading: uploading user should have their way with their own orphan attachments
                //
                if (!$_post->post_parent && ($_post->post_author == $user->ID)) {
                    $return['return_caps'] = array_merge($wp_sitecaps, $pp_reqd_caps);
                }
            } // endif retrieved post

        } else { // post_id is not a revision
            if (('read' == $required_operation) && (
                (isset($_SERVER['SCRIPT_NAME']) && strpos(sanitize_text_field($_SERVER['SCRIPT_NAME']), 'wp-admin/revision.php')) || (defined('DOING_AJAX') && DOING_AJAX 
                && PWP::is_REQUEST('action', 'get-revision-diffs')))
            ) {
                $return['required_operation'] = 'edit';
            }
        }

        global $pagenow;

        if (('async-upload.php' == $pagenow) || (in_array('edit_posts', $pp_reqd_caps, true) && presspermit()->doingEmbed())) {
            if ('upload_files' == reset($pp_reqd_caps)) {  // don't apply any exceptions for upload_files requirement on media upload
                $return['return_caps'] = $wp_sitecaps;
            } else {
                $require_cap = (presspermit()->doingEmbed()) ? apply_filters('presspermit_embed_capability', 'upload_files') : 'upload_files';

                if (!empty($wp_sitecaps[$require_cap])) {

                    $_post = ($post_id) ? get_post($post_id) : false;

                    if (!$_post || ('attachment' == $_post->post_type)) {
                        if (in_array('edit_posts', $pp_reqd_caps, true)) {
                            $return['return_caps'] = array_merge($wp_sitecaps, ['edit_posts' => true]);

                        } elseif (in_array('edit_post', $pp_reqd_caps, true)) {
                            $return['return_caps'] = array_merge($wp_sitecaps, ['edit_post' => true]);
                        }
                    }
                }
            }
        }

        return ($return) ? array_merge((array)$force_vars, $return) : $force_vars;

        // note: CapabilityFilters::fltUserHasCap() filters return array to allowed variables before extracting
    }

    function fltCanPublish($can_publish, $args) {
        $args = (array) $args;
        $defaults = ['post_id' => 0, 'is_published' => false, 'post_type' => ''];

        if ($can_publish) {
            return $can_publish;
        }

        foreach ($defaults as $var => $default_val) {
            $$var = (isset($args[$var])) ? $args[$var] : $default_val;
        }

        $user = presspermit()->getUser();

        $operation = (!$is_published && presspermit()->getOption('publish_exceptions')) ? 'publish' : 'edit';
        $additional_ids = $user->getExceptionPosts($operation, 'additional', $post_type);

        $can_publish = in_array($post_id, $additional_ids);

        return $can_publish;
    }

    function actSavePostPreAssignTerms($pp_reqd_caps, $source_name, $object_type, $post_id)
    {
        // Workaround to deal with WP core's checking of publish cap prior to storing categories:
        // Store terms to DB in advance of any cap-checking query which may use those terms to qualify an operation.
        if (('post' != $source_name) 
        || !is_admin() 
        || PWP::empty_REQUEST('action') 
        || !in_array(PWP::REQUEST_key('action'), ['editpost', 'autosave'])

        // Only pre-assign terms if capability check is for the original post being added or edited. But on new post creation, getPostID() could return zero
        || (($post_id != PWP::getPostID()) && (PWP::getPostID() || !presspermit()->isInsertedPost($post_id)) && !defined('PRESSPERMIT_LEGACY_PREASSIGN_TERMS'))
        ) {
            return;
        }

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostSave.php');
        PostSave::preAssignTerms($pp_reqd_caps, $object_type, $post_id);

        // assign propagating exceptions in case they are needed for a cap check at post creation
        if (
            is_post_type_hierarchical($object_type) && array_intersect($pp_reqd_caps, ['edit_post', 'edit_page'])
            && !PWP::is_REQUEST('page', 'rvy-revisions')
            && (!defined('DOING_AUTOSAVE') || !DOING_AUTOSAVE)
        ) {
            // PressPermit Core classes
            require_once(PRESSPERMIT_CLASSPATH . '/PostSave.php');
            require_once(PRESSPERMIT_CLASSPATH . '/ItemSave.php');

            if ($is_new = \PublishPress\Permissions\PostSave::isNewPost($post_id)) {
                $parent_info = \PublishPress\Permissions\PostSave::getPostParentInfo($post_id);
                $set_parent = $parent_info['set_parent'];
                $last_parent = $parent_info['last_parent'];

                // not theoretically necessary, but an easy safeguard to avoid re-inheriting parent roles
                if ($set_parent && ($set_parent != $last_parent)) {
                    $via_item_source = 'post';
                    $_args = compact('via_item_source', 'set_parent', 'last_parent', 'is_new');

                    if (\PublishPress\Permissions\ItemSave::inheritParentExceptions($post_id, $_args)) {
                        presspermit()->getUser()->except = [];
                    }
                }
            }
        }
    }
}

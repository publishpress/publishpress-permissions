<?php
namespace PublishPress\Permissions\Collab;

class PostFilters
{
    function __construct()
    {
        if (defined('PUBLISHPRESS_REVISIONS_VERSION')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Revisions/PostFilters.php');
            new Revisions\PostFilters();
        
        } elseif (defined('REVISIONARY_VERSION')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Revisionary/PostFilters.php');
            new Revisionary\PostFilters();
        }

        add_filter('presspermit_query_missing_caps', [$this, 'fltApplyListAllPosts'], 10, 4);

        add_filter('presspermit_exception_clause', [$this, 'fltAllowAutodraftEditing'], 10, 4);
    }

    // Ensure that autodrafts can be edited even if they do not yet have a required page parent or term setting (NOTE: this fix also requires PP Core 2.1.39)
    // $args = compact( 'mod', 'ids', 'src_table', 'logic' );
    function fltAllowAutodraftEditing($exception_clause, $required_operation, $post_type, $args)
    {
        if (('edit' == $required_operation) && ('include' == $args['mod']) && is_admin()) {
            global $pagenow, $current_user;

            if (in_array($pagenow, ['post-new.php', 'post.php']) && !PWP::empty_POST() && PWP::is_REQUEST('action', ['edit', 'editpost'])) {
                 if ($post_id = PWP::getPostID()) {
                    $_post = get_post($post_id);

                    if (($current_user->ID == $_post->post_author) && ($_post->post_type == $post_type) 
                    && (('auto-draft' == $_post->post_status) || (('draft' == $_post->post_status) && get_post_meta($post_id, '_pp_is_autodraft', true)))
                    ) {
                        // Ensure the current query pertains to a capability check for the autodraft itself
                        if (!empty($args['limit_ids']) && (1 == count($args['limit_ids'])) && ($_post->ID == reset($args['limit_ids']))) {
                            // The autodraft flag is required because WP autosave sets post_status to 'draft'.  
                            // But this is only an issue for initial creation before parent/terms are assigned. 
                            // Delete the flag now so editing exceptions are not permanently bypassed.
                            delete_post_meta($post_id, '_pp_is_autodraft');
                            return '1=1';
                        }
                    }
                }
            }
        }

        return $exception_clause;
    }

    function fltApplyListAllPosts($missing_caps, $reqd_caps, $post_type, $meta_cap)
    {
        if ('edit_post' == $meta_cap) {
            $user = presspermit()->getUser();

            if ($missing_caps = array_diff($reqd_caps, array_keys(array_filter($user->allcaps)))) {
                $type_obj = get_post_type_object($post_type);
                
                if ($type_obj && !empty($type_obj->cap) && !empty($type_obj->cap->edit_posts)) {
                    $list_cap = str_replace('edit_', 'list_all_', $type_obj->cap->edit_posts);

                    if (!empty($user->allcaps[$list_cap])) {
                        foreach ($missing_caps as $key => $cap_name) {
                            if (0 === strpos($cap_name, 'edit_'))
                                unset($missing_caps[$key]);
                        }
                    }
                }
            }
        }

        return $missing_caps;
    }
}

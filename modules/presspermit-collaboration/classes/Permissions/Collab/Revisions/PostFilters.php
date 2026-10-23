<?php
namespace PublishPress\Permissions\Collab\Revisions;

\defined('ABSPATH') || exit;

class PostFilters
{
    private $filtered_post_clauses_type = '';

    function __construct()
    {
        add_filter('presspermit_have_site_caps', [$this, 'fltHaveSiteCaps'], 10, 3);

        add_filter('presspermit_main_posts_clauses_types', [$this, 'flt_posts_clauses_object_types'], 10, 2);
        add_filter('presspermit_main_posts_clauses_where', [$this, 'flt_posts_clauses_where'], 10, 1);
        add_filter('presspermit_meta_cap', [$this, 'flt_meta_cap']);

        add_filter('presspermit_query_post_statuses', [$this, 'fltQueryPostStatuses'], 10, 2);

        add_filter('revisionary_require_edit_others_drafts', [$this, 'fltRequireEditOthersDrafts'], 10, 4);

        add_filter('presspermit_base_cap_replacements', [$this, 'fltBaseCapReplacements'], 10, 3);
    }

    function fltQueryPostStatuses($use_statuses, $args) {
        if (!function_exists('rvy_get_option') || !rvy_get_option('permissions_compat_mode')) {
            // Don't include revision statuses in post_status clause of Posts query
            $use_statuses = array_diff_key($use_statuses, array_fill_keys(rvy_revision_statuses(), true));
        }

        return $use_statuses;
    }

    public function fltHaveSiteCaps($have_site_caps, $post_type, $args) {
        global $current_user;

        if (!empty($args['has_cap_check']) && in_array($args['has_cap_check'], ['read_post', 'edit_post']) && !empty($args['limit_ids']) && !presspermit_is_preview()) {
            $post_id = (int) reset($args['limit_ids']);
        } elseif ('edit' == $args['required_operation']) {
            $post_id = PWP::getPostID();
        }

        if (!empty($post_id)) {
            if (rvy_in_revision_workflow($post_id)) {
                if ($revision = get_post($post_id)) {
                    $type_obj = get_post_type_object($revision->post_type);

                    if ($type_obj && !empty($type_obj->cap->edit_posts)) {
                        if (!rvy_get_option('revisor_lock_others_revisions') || (rvy_get_option('admin_revisions_to_own_posts') && current_user_can('edit_post', rvy_post_id($post_id)))) {
                            $have_site_caps['user'][] = $revision->post_status;
                        }
                    }
                }
            }
        }

        return $have_site_caps;
    }

    function fltBaseCapReplacements($replace_caps, $reqd_caps, $post_type) {
        global $current_user;
        
        if ($type_obj = get_post_type_object($post_type)) {
            if (!empty($type_obj->cap->edit_posts)) {
                $replace_caps['list_others_revisions'] = $type_obj->cap->edit_posts;
                $replace_caps['edit_others_drafts'] = $type_obj->cap->edit_posts;

                if (!empty($type_obj->cap->edit_others_posts)) {
                    $copy_others_cap = str_replace('edit_', 'copy_', $type_obj->cap->edit_others_posts);

                    $replace_caps[$copy_others_cap] = str_replace('edit_', 'copy_', $type_obj->cap->edit_posts);
                }

                // Don't block Contributors from editing their own drafts (copy_others requirement initially applied to map_meta_cap filter via Posts filtering)
                if (!empty($current_user->allcaps[$type_obj->cap->edit_posts])) {
                    $copy_cap = str_replace('edit_', 'copy_', $type_obj->cap->edit_posts);
                    $replace_caps[$copy_cap] = $type_obj->cap->edit_posts;
                }
            }
        }

        return $replace_caps;
    }

    function fltRequireEditOthersDrafts($require, $post_type, $post_status, $args) {
        $status_obj = get_post_status_object($post_status);

        if ($status_obj && !empty($status_obj->capability_status)) { // Custom statuses with non-default capability mapping won't be available to Revisors by default
            $require = false;
        }

        return $require;
    }

    function flt_posts_clauses_object_types($object_types)
    {
        global $wp_query;

        if ($wp_query->is_preview && defined('PUBLISHPRESS_REVISIONS_VERSION')) {
            if (!empty($wp_query->query['p'])) {
                $post_id = (int) $wp_query->query['p'];
            } elseif(!empty($wp_query->query['page_id'])) {
                $post_id = (int) $wp_query->query['page_id'];
            } else {
                return $object_types;
            }

            if ($_post = get_post($post_id)) {
                if ('revision' == $_post->post_type) { // for past revisions
                    if ($_type = get_post_field('post_type', $_post->post_parent)) {
                        $object_types = $_type;
                        $this->filtered_post_clauses_type = $_type;
                    }
                }
            }
        }

        return $object_types;
    }

    function flt_posts_clauses_where($objects_where)
    {
        if ($this->filtered_post_clauses_type) {
            $objects_where = str_replace("post_type = 'post'", "post_type = '$this->filtered_post_clauses_type'", $objects_where);

            $this->filtered_post_clauses_type = '';
        }

        return $objects_where;
    }

    // this is no longer used as a filter, but still called internally
    function fltPostsWhere($where, $args)
    {
        // for past revisions
        if (defined('PUBLISHPRESS_REVISIONS_VERSION') && !is_admin() && PWP::is_REQUEST('post_type', 'revision') 
        && (!PWP::empty_REQUEST('preview') || !PWP::empty_REQUEST('preview_id'))) {
            $matches = [];
            if (preg_match("/post_type = '([0-9a-zA-Z_\-]+)'/", $where, $matches)) {
                if ($matches[1]) {
                    global $wpdb;
                    $where = str_replace(
                        "$wpdb->posts.post_type = '{$matches[1]}'", 

                        "( $wpdb->posts.post_type = '{$matches[1]}' " 
                        . " OR ( $wpdb->posts.post_type = 'revision'"
                        . " AND $wpdb->posts.post_status IN ('inherit')"
                        . " AND $wpdb->posts.post_parent IN ( SELECT ID FROM $wpdb->posts WHERE post_type = '{$matches[1]}' ) ) ) ",

                       // . " OR ( $wpdb->posts.post_status IN ('pending-revision', 'future-revision') "
                       // . " AND $wpdb->posts.comment_count IN ( SELECT ID FROM $wpdb->posts WHERE post_type = '{$matches[1]}' ) ) )", 
                        
                        $where
                    );
                }
            }
        }

        return $where;
    }

    function flt_meta_cap($meta_cap) {
        if (defined('PUBLISHPRESS_REVISIONS_VERSION') && ('read_post' == $meta_cap) && !is_admin() && PWP::is_REQUEST('post_type', 'revision') 
        && (!PWP::empty_REQUEST('preview') || !PWP::empty_REQUEST('preview_id'))
        ) {
            $meta_cap = 'edit_post';
        }

        return $meta_cap;
    }
}

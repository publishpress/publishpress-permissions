<?php
namespace PublishPress\Permissions\Collab\Revisionary;

\defined('ABSPATH') || exit;

class CapabilityFilters
{
    function __construct()
    {
        add_filter('presspermit_has_post_cap_vars', [$this, 'has_post_cap_vars'], 10, 4);

        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            add_filter('map_meta_cap', [$this, 'fltAdjustReqdCaps'], 1, 4);
        }

        add_filter('presspermit_query_missing_caps', [$this, 'fltRevisionaryBlockEditorClearance'], 10, 4);

        add_filter('presspermit_query_post_statuses', [$this, 'fltPostStatuses'], 10, 2);
    }

    function fltPostStatuses($statuses, $args) {
        global $pagenow;

        if (!empty($args['has_cap_check']) || !PWP::empty_REQUEST('preview') || 'revision.php' == $pagenow 
        ) {
            if (rvy_get_option('pending_revisions')) {
                $statuses ['pending-revision']= get_post_status_object('pending-revision');
            }

            if (rvy_get_option('scheduled_revisions')) {
                $statuses ['future-revision']= get_post_status_object('future-revision');
            }
        }

        return $statuses;
    }

    function fltRevisionaryBlockEditorClearance($missing_caps, $reqd_caps, $post_type, $meta_cap)
    {
        global $revisionary;

        // Prevent improper blockage submitting pending revision from Gutenberg editor

        if (('edit_post' == $meta_cap) && defined('REST_REQUEST') && REST_REQUEST 
        && !empty($revisionary) && empty($revisionary->skip_revision_allowance)
        ) {
            if ( $type_obj = get_post_type_object($post_type) ) {
                $missing_caps = array_diff($missing_caps, (array) $type_obj->cap->edit_published_posts);
            }
        }

        return $missing_caps;
    }

    // hooks to map_meta_cap
    function fltAdjustReqdCaps($reqd_caps, $orig_cap, $user_id, $args)
    {
        global $pagenow, $current_user;

        if ($user_id != $current_user->ID) {
            return $reqd_caps;
        }

        if (!empty($args[0]) && !empty($args[0]->query_contexts) && in_array('comments', $args[0]->query_contexts, true)) {
            return $reqd_caps;
        }

        $legacy_suffix = version_compare(REVISIONARY_VERSION, '1.5-alpha', '<') ? 'Legacy' : '';

        if (('revision.php' == $pagenow) && PWP::is_REQUEST('action', 'restore')) {
            return $reqd_caps;
        }

        if (!empty($args[0]) && in_array($orig_cap, ['edit_post', 'delete_post', 'edit_page', 'delete_page'], true)) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . "/Revisionary/Admin{$legacy_suffix}.php");
            $admin_class = "\PublishPress\Permissions\Collab\Revisionary\Admin{$legacy_suffix}";

            if (!empty($_SERVER['REQUEST_URI']) && false !== strpos( urldecode(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))), 'admin.php?page=rvy-revisions') ) {
                $object_type = $this->postTypeFromCaps($reqd_caps);
            } else {
                $object_type = PWP::findPostType($args[0]); // $args[0] is object id; type property will be pulled from object
            }


            // ensure proper cap requirements when a non-Administrator Quick-Edits or Bulk-Edits Posts/Pages 
            // (which may be included in the edit listing only for revision submission)
            if (in_array($pagenow, ['edit.php', 'edit-tags.php', 'admin-ajax.php']) && !PWP::empty_REQUEST('action') 
            && (-1 != PWP::REQUEST_key('action') || (PWP::is_REQUEST('action2') && -1 != PWP::REQUEST_key('action2')))
            ) {
                $reqd_caps = $admin_class::fix_table_edit_reqd_caps($reqd_caps, $orig_cap, get_post($args[0]), get_post_type_object($object_type));
            }
        }

        if (!empty($object_type) && in_array($orig_cap, ['edit_post', 'edit_page'], true)) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . "/Revisionary/Admin{$legacy_suffix}.php");
            $admin_class = "\PublishPress\Permissions\Collab\Revisionary\Admin{$legacy_suffix}";

            $reqd_caps = $admin_class::adjust_revision_reqd_caps($reqd_caps, $object_type);
        }

        return $reqd_caps;
    }

    private function postTypeFromCaps($caps)
    {
        foreach (get_post_types(['public' => true, 'show_ui' => true], 'object', 'or') as $post_type => $type_obj) {
            $caps = array_diff($caps, ['edit_posts', 'edit_pages']); // ignore generic caps defined for extraneous properties (assign_term, etc.) 
            if (array_intersect((array)$type_obj->cap,  $caps)) {
                return $post_type;
            }
        }

        return false;
    }

    function has_post_cap_vars($force_vars, $wp_sitecaps, $pp_reqd_caps, $vars)
    {
        $return = [];

        if (('read_post' == reset($pp_reqd_caps))) {
            if (!is_admin() && PWP::is_REQUEST('post_type', 'revision') 
            && (!PWP::empty_REQUEST('preview') || !PWP::empty_REQUEST('preview_id'))) {
                $return['pp_reqd_caps'] = ['edit_post'];
            }
        }

        if (empty(presspermit()->flags['memcache_disabled'])) {
            global $revisionary;
            if (isset($revisionary) && !empty($revisionary->skip_revision_allowance)) {
                presspermit()->flags['cache_key_suffix'] .= '-skip_revision_allowance-';
            }
        }

        return ($return) ? array_merge((array)$force_vars, $return) : $force_vars;

        // note: CapabilityFilters::fltUserHasCap() filters return array to allowed variables before extracting
    }
}

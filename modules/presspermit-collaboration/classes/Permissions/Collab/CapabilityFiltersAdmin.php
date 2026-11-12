<?php

namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class CapabilityFiltersAdmin
{
    var $in_has_cap_call = false;

    function __construct()
    {
        add_filter('presspermit_do_find_post_id', [$this, 'fltDoFindPostId'], 10, 3);
        add_filter('presspermit_user_has_cap_params', [$this, 'fltUserHasCapParams'], 10, 3);
        add_filter('presspermit_credit_cap_exception', [$this, 'fltCreditTxCapException'], 10, 2);
        add_filter('presspermit_user_has_caps', [$this, 'fltUserHasCaps'], 10, 3);
        add_filter('presspermit_get_terms_exceptions', [$this, 'fltGetTermsExceptions'], 10, 6);
        add_filter('terms_clauses', [$this, 'fltGetTermsPreserveCurrentParent'], 55, 3);
        add_filter('presspermit_posts_clauses_intercept', [$this, 'fltBypassAttachmentsFiltering'], 10, 4);

        add_filter('map_meta_cap', [$this, 'fltAdjustReqdCaps'], 1, 4);

        add_filter('presspermit_adjust_posts_where_clause', [$this, 'fltAdjustPostsWhereClause'], 10, 4);
        add_filter('presspermit_force_attachment_parent_clause', [$this, 'fltForceAttachmentParentClause'], 10, 2);
        add_filter('presspermit_have_site_caps', [$this, 'fltHaveSiteCaps'], 10, 3);

        add_filter('presspermit_construct_posts_request_args', [$this, 'fltConstructPostsRequestArgs']);

        add_filter('redirect_post_location', [$this, 'fltMaybeRedirectPostEditLocation'], 10, 2);

        // prevent infinite recursion if current_user_can( 'edit_posts' ) is called from within another plugin's user_has_cap handler
        add_filter('user_has_cap', [$this, 'fltFlagHasCapCall'], 0);
        add_filter('user_has_cap', [$this, 'fltFlagHasCapDone'], 999);
    }

    function fltFlagHasCapCall($caps)
    {
        $this->in_has_cap_call = true;
        return $caps;
    }

    function fltFlagHasCapDone($caps)
    {
        $this->in_has_cap_call = false;
        return $caps;
    }

    function fltBypassAttachmentsFiltering($clauses, $orig_clauses, $_wp_query = false, $args = [])
    {
        $required_operation = (isset($args['required_operation'])) ? $args['required_operation'] : '';

        if (
            in_array($required_operation, ['', 'read'], true)
            && empty($args['pp_context']) && strpos($orig_clauses['where'], "post_type = 'attachment'")
        ) {
            if (!empty(presspermit()->getUser()->allcaps['pp_list_all_files'])) { // disable attachment filtering?
                $post_types = (isset($args['post_types'])) ? (array)$args['post_types'] : [];
                if (!$post_types || ((1 == count($post_types)) && ('attachment' == reset($post_types)))) {
                    return $orig_clauses;
                }
            }
        }

        return $clauses;
    }

    function fltAdjustPostsWhereClause($adjust, $type_where_clause, $post_type, $args)
    {
        if ('attachment' == $post_type) {
            if (
                !empty($args['has_cap_check']) && !presspermit()->getOption('own_attachments_always_editable')
                && empty(presspermit()->getUser()->allcaps['edit_own_attachments'])
            ) {  // PP setting eliminates cap requirement
                $adjust = ($adjust) ? $adjust : $type_where_clause;
                $adjust .= " AND {$args['src_table']}.post_parent = '0'";
            }
        }

        return $adjust;
    }

    function fltForceAttachmentParentClause($force, $args)
    {
        global $current_user;

        return (
            (empty($args['pp_context']) || 'count_attachments' != $args['pp_context'])
            && (presspermit()->getOption('attachment_edit_requires_parent_access'))
        );
    }

    function fltHaveSiteCaps($have_site_caps, $post_type, $args)
    {
        if ('attachment' == $post_type) {
            if (presspermit()->getOption('own_attachments_always_editable') || !empty(presspermit()->getUser()->allcaps['edit_own_attachments']))
                $have_site_caps['owner'][] = 'inherit';
        }

        return $have_site_caps;
    }

    // hooks to map_meta_cap
    function fltAdjustReqdCaps($reqd_caps, $orig_cap, $user_id, $args)
    {
        global $pagenow, $current_user;

        if ($this->in_has_cap_call || ($user_id != $current_user->ID))
            return $reqd_caps;

        $orig_reqd_caps = (array)$reqd_caps;

        if (in_array($orig_cap, ['edit_post', 'delete_post'])) {
            if (!empty($args[0]) && !presspermit()->getOption('own_attachments_always_editable') && !defined('PRESSPERMIT_MEDIA_LEGACY_EDIT_DELETE')) {
                $post = (is_object($args[0])) ? $args[0] : get_post($args[0]);

                if ('attachment' == $post->post_type) {
                    if ($post->post_parent) {
                        if ('attachment' != get_post_field('post_type', $post->post_parent)) {
                            if (!current_user_can('edit_post', $post->post_parent)) {
                                $reqd_caps[] = 'do_not_allow';
                                return $reqd_caps;
                            }
                        }
                    }
                }
            }
        }

        // for scoped menu management roles, satisfy edit_theme_options cap requirement
        if (('nav-menus.php' == $pagenow)
            || (('edit_theme_options' == reset($reqd_caps)) && ('edit_theme_options' == $orig_cap) && (PWP::doingAdminMenus() || (defined('DOING_AJAX') && DOING_AJAX)))
        ) {
            if (empty($current_user->allcaps['edit_theme_options'])) {
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/NavMenuCapabilities.php');
                $reqd_caps = NavMenuCapabilities::adjustCapRequirement($reqd_caps);
            }
        } else {
            // Work around Divi Page Builder requiring excessive or off-type capabilities
            if (defined('ET_BUILDER_PLUGIN_VERSION') && !empty($_SERVER['REQUEST_URI']) && strpos(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), 'admin-ajax.php')) {
                $alt_caps = ['edit_posts' => ['edit_pages']];

                if (
                    did_action('wp_ajax_et_fb_ajax_save')
                    || (PWP::is_REQUEST('action', 'heartbeat') && !PWP::empty_REQUEST('et_fb_autosave'))
                    || (PWP::is_REQUEST('action', 'et_pb_get_backbone_template'))
                ) {
                    $alt_caps = array_merge($alt_caps, ['publish_posts' => ['edit_published_posts', 'edit_published_pages'], 'publish_pages' => ['edit_published_pages'], 'edit_published_posts' => ['edit_published_pages']]);
                }

                foreach ($alt_caps as $divi_requirement => $alt_requirements) {
                    if ($divi_requirement == $orig_cap) {

                        foreach ($alt_requirements as $require_cap) {
                            if (!empty($current_user->allcaps[$require_cap])) {
                                return [$require_cap];
                            }
                        }
                    }
                }
            }

            // Work around WP's occasional use of literal 'cap_name' instead of $post_type_object->cap->$cap_name  todo: review
            // note: cap names for "post" type may be customized too
            //
            if (
                in_array($pagenow, ['edit.php', 'post.php', 'post-new.php', 'press-this.php', 'admin-ajax.php', 'upload.php', 'media.php'])
                && !PWP::doingAdminMenus()
            ) {
                $replace_post_caps = ['publish_posts', 'edit_others_posts', 'edit_published_posts'];

                static $did_admin_init = false;
                if (!$did_admin_init)
                    $did_admin_init = did_action('admin_init');

                if ($did_admin_init)  // otherwise extra padding between menu items due to some items populated but unpermitted
                    $replace_post_caps[] = 'edit_posts';

                if (in_array($pagenow, ['upload.php', 'media.php']))
                    $replace_post_caps = array_merge($replace_post_caps, ['delete_posts', 'delete_others_posts']);

                if (array_intersect($reqd_caps, $replace_post_caps)) {
                    if (!empty($args[0])) {
                        if (is_object($args[0])) {
                            if (property_exists($args[0], 'ID')) {
                                $item_id = $args[0]->ID;
                            } elseif (property_exists($args[0], 'post') && is_object($args[0]->post) && property_exists($args[0]->post, 'ID')) {
                                $item_id = $args[0]->post->ID;
                            } else {
                                $item_id = 0;
                            }
                        } elseif (is_numeric($args[0])) {
                            $item_id = (int) $args[0];
                        } else {
                            $item_id = 0;
                        }
                    } else {
                        $item_id = 0;
                    }

                    if ($type_obj = get_post_type_object(PWP::findPostType($item_id))) {
                        foreach ($replace_post_caps as $post_cap_name) {
                            $key = array_search($post_cap_name, $reqd_caps);
                            if (false !== $key) {
                                $reqd_caps[$key] = $type_obj->cap->$post_cap_name;
                            }
                        }
                    }
                }
            }

            // accept edit_files capability instead of upload_files in some contexts
            $key = array_search('upload_files', $reqd_caps);

            if (
                false !== $key && (PWP::doingAdminMenus() || in_array($pagenow, ['upload.php', 'post.php', 'post-new.php'])
                    || (defined('DOING_AJAX') && DOING_AJAX && PWP::is_REQUEST('action', ['query-attachments', 'mla-query-attachments'])))
            ) {
                if (empty($current_user->allcaps['upload_files']) && !empty($current_user->allcaps['edit_files']))
                    $reqd_caps[$key] = 'edit_files';
            }
        }

        //===============================

        if ($reqd_caps !== $orig_reqd_caps) {
            $reqd_caps = apply_filters('presspermit_collab_adjusted_reqd_caps', $reqd_caps, $orig_reqd_caps, $orig_cap, $user_id, $args);

            // workaround for Wiki plugin
            if (('edit_others_posts' == $orig_cap) && did_action('auth_redirect') && !did_action('_admin_menu')) {
                $reqd_caps = $orig_reqd_caps;
            }
        }

        if (
            presspermit()->isTaxonomyEnabled('post_tag')
            && in_array($orig_cap, ['manage_post_tags', 'edit_post_tags', 'delete_post_tags'], true)
            && in_array('manage_categories', $reqd_caps, true) && !defined('PP_LEGACY_POST_TAG_CAPS')
        ) {
            $reqd_caps = array_diff($reqd_caps, ['manage_categories']);
            $reqd_caps[] = 'manage_post_tags';
        }

        return $reqd_caps;
    }

    private function taxonomy_from_caps($caps)
    {
        foreach (presspermit()->getEnabledTaxonomies(['object_type' => false], 'object') as $taxonomy => $tx_obj) {
            if (array_intersect((array)$tx_obj->cap, $caps)) {
                return $taxonomy;
            }
        }

        return false;
    }

    function fltUserHasCapParams($params, $orig_reqd_caps, $args)
    {
        // todo: how can this ever execute prior to class inclusion in CollabHooks.php? (error with CAS integration)
        if (!class_exists('\PublishPress\Permissions\Collab\Capabilities')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Capabilities.php');
        }

        $caps = Capabilities::instance();

        // taxonomy caps
        if ($type_caps = array_intersect($orig_reqd_caps, array_keys($caps->all_taxonomy_caps))) {
            global $tag_ID, $taxonomy;

            if ($taxonomy) {
                // todo: put this check in presspermit()->isTaxonomyEnabled()
                $tx_name = (is_object($taxonomy) && isset($taxonomy->name)) ? $taxonomy->name : $taxonomy;

                if (!presspermit()->isTaxonomyEnabled($tx_name)) {
                    return $params;
                }
            }

            if (!array_diff($orig_reqd_caps, ['edit_posts'])) {
                return $params;
            }

            $is_term_cap = true;

            if ('assign_term' == $args['orig_cap']) {
                if (!empty($args['item_id'])) {
                    $term_obj = get_term($args['item_id']);
                    if (!empty($term_obj->taxonomy)) {
                        global $post_type;

                        $op = 'assign';
                        $return_taxonomy = $term_obj->taxonomy;
                        if (!empty($post_type))
                            $item_type = $post_type;
                    }
                } else {
                    $item_type = '';
                }

                $op = 'assign';
            } else {
                if (!$item_type = $this->taxonomy_from_caps($type_caps)) {
                    return $params;
                }

                $tx_obj = get_taxonomy($item_type);
                $return_taxonomy = $item_type;

                $base_cap = reset($type_caps);

                switch ($base_cap) {
                    case $tx_obj->cap->manage_terms:
                        $op = 'manage';
                        break;

                    default:
                        $op = false;
                }
            }

            $return = compact('type_caps', 'item_type', 'is_term_cap', 'op', 'taxonomy');

            if (!empty($return_taxonomy)) {
                $return['taxonomy'] = $return_taxonomy;
            }

            if (empty($params['item_id'])) {
                $qvar = ('nav_menu' == $item_type) ? 'menu' : 'tag_ID';

                if ($id = PWP::REQUEST_int($qvar)) {
                    $return['item_id'] = PWP::termidToTtid($id, $item_type);
                }
            }

            return (is_array($params)) ? array_merge($params, $return) : $return;
        }

        return $params;
    }

    function fltCreditTxCapException($pass, $params)
    {
        if (!empty($params['is_term_cap'])) {
            $defaults = ['op' => '', 'item_id' => 0, 'item_type' => '', 'tt_ids' => '', 'type_caps' => ''];
            $params = array_merge($defaults, $params);
            foreach (array_keys($defaults) as $var) {
                $$var = $params[$var];
            }

            if (count($type_caps) == 1) {
                if ($op) {
                    // note: item_type is taxonomy here
                    if ($tt_ids = presspermit()->getUser()->getExceptionTerms($op, 'additional', $item_type, $item_type)) {
                        if (!$item_id || in_array($item_id, $tt_ids)) {
                            $pass = true;
                        }
                    }
                }
            }
        }

        return $pass;
    }

    function fltUserHasCaps($wp_sitecaps, $orig_reqd_caps, $params)
    {
        $defaults = ['is_term_cap' => false, 'op' => '', 'item_type' => '', 'item_id' => 0, 'taxonomy' => ''];
        $params = array_merge($defaults, $params);
        foreach (array_keys($defaults) as $var) {
            $$var = $params[$var];
        }

        if (!empty($params['is_term_cap']) && (($op != 'assign') || !PWP::doingAdminMenus())) {
            if ($item_id && $op) {
                $user = presspermit()->getUser();

                $fail = false;

                $taxonomy = (!empty($params['taxonomy'])) ? $params['taxonomy'] : $item_type;

                $args = ('assign' == $op) ? ['merge_universals' => true] : [];

                $additional_tt_ids = $user->getExceptionTerms($op, 'additional', $item_type, $taxonomy, $args);

                // note: item_type is taxonomy here
                if ($tt_ids = $user->getExceptionTerms($op, 'include', $item_type, $taxonomy, $args)) {
                    if (!in_array($item_id, array_merge($tt_ids, $additional_tt_ids)))
                        $fail = true;
                } elseif ($tt_ids = $user->getExceptionTerms($op, 'exclude', $item_type, $taxonomy, $args)) {
                    $tt_ids = array_diff($tt_ids, $additional_tt_ids);
                    if (in_array($item_id, $tt_ids))
                        $fail = true;
                }

                if ($fail)
                    $wp_sitecaps = array_diff_key($wp_sitecaps, array_fill_keys($orig_reqd_caps, true));
            }
        }

        return $wp_sitecaps;
    }

    // if user lacks sitewide term management cap, make any additions double as implicit inclusions (so inaccessable terms are not listed)
    function fltGetTermsExceptions($exceptions, $taxonomy, $op, $mod_type, $post_type, $args = [])
    {
        if (('include' == $mod_type) && !$exceptions && !empty($args['additional_tt_ids'])) {
            if ('manage' == $op) {
                $tx_obj = get_taxonomy($taxonomy);
                if (empty(presspermit()->getUser()->allcaps[$tx_obj->cap->manage_terms]))
                    $exceptions = $args['additional_tt_ids'];
            }
        }

        return $exceptions;
    }

    function fltGetTermsPreserveCurrentParent($clauses, $taxonomies, $args)
    {
        global $pagenow;

        if (is_admin() && in_array($pagenow, ['edit-tags.php', 'term.php']) && !PWP::is_REQUEST('action', 'editedtag')) {
            if ($tag_id = PWP::REQUEST_int('tag_ID')) {
                $tx_obj = get_taxonomy(reset($taxonomies));
                if ($tx_obj->hierarchical) {
                    global $wpdb;
                    // don't filter current parent category out of selection UI even if current user can't manage it
                    $clauses['where'] .= $wpdb->prepare(
                        " OR t.term_id = (SELECT parent FROM $wpdb->term_taxonomy WHERE taxonomy = %s AND term_id = %d) ",
                        $tx_obj->name,
                        $tag_id
                    );
                }
            }
        }

        return $clauses;
    }

    function fltConstructPostsRequestArgs($args)
    {
        foreach (['action', 'action2'] as $var) {
            if (!PWP::empty_REQUEST($var) && in_array(PWP::REQUEST_key($var), ['trash', 'untrash', 'delete'])) {
                $args['include_trash'] = true;
            }
        }

        return $args;
    }

    function fltDoFindPostId($do, $orig_reqd_caps, $args)
    {
        if (PWP::doingAdminMenus())
            return false;

        return $do;
    }

    function fltMaybeRedirectPostEditLocation($location, $post_id)
    {
        if (!current_user_can('edit_post', $post_id)) {
            if ($type_obj = get_post_type_object(get_post_field('post_type', $post_id))) {
                if (PWP::is_POST('save') || PWP::is_POST('publish')) {

                    if (!defined('PRESSPERMIT_NO_PROCESS_BEFORE_REDIRECT')) {
                        require_once(PRESSPERMIT_CLASSPATH . '/PostSave.php');

                        if ($is_new = \PublishPress\Permissions\PostSave::isNewPost($post_id)) {
                            if ($post = get_post($post_id)) {
                                require_once(PRESSPERMIT_CLASSPATH . '/ItemSave.php');

                                $via_item_source = 'post';
                                $set_parent = $post->post_parent;
                                $_args = compact('via_item_source', 'set_parent', 'is_new');

                                \PublishPress\Permissions\ItemSave::inheritParentExceptions($post_id, $_args);

                                return $location;
                            }
                        }
                    }

                    wp_die(
                        '<p>'
                            . sprintf(
                                esc_html__('The %s was saved, but you can no longer edit it.', 'press-permit-core'),
                                esc_html(strtolower($type_obj->labels->singular_name))
                            )
                            . '</p><p>'
                            . "<a href='" . esc_url(admin_url("edit.php?post_type=$type_obj->name")) . "'>"
                            . sprintf(esc_html__('Go to %s', 'press-permit-core'), esc_html($type_obj->labels->name))
                            . '</a></p>'
                    );
                }
            } else {
                $edit_link = "<a href='" . admin_url('index.php') . "'>" . esc_html__('Dashboard') . '</a>';
            }

            wp_die(
                '<p>'
                    . esc_html__('The requested modification was processed, but you can no longer edit the post.', 'press-permit-core')
                    . '</p><p>'
                    . "<a href='" . esc_url(admin_url('index.php')) . "'>" . esc_html__('Dashboard') . '</a></p>'
            );
        }

        return $location;
    }
}

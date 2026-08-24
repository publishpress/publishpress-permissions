<?php

namespace PublishPress\Permissions\Collab;

class AdminFilters
{
    private $inserting_post = false;

    // Backend filtering which is generally enabled for all requests 
    //
    function __construct()
    {
        add_action('presspermit_init', [$this, 'actDisableForumPatternRoles']);

        add_filter('presspermit_enabled_taxonomies', [$this, 'fltGetEnabledTaxonomies'], 10, 2);
        add_filter('presspermit_enabled_taxonomies_by_key', [$this, 'fltGetEnabledTaxonomiesByKey'], 10, 2);
        add_filter('wp_dropdown_pages', [$this, 'fltDropdownPages']);

        add_filter('pre_post_parent', [$this, 'fltPageParent'], 50, 1);
        add_filter('pre_post_status', [$this, 'fltPostStatus'], 50, 1);

        add_filter('user_has_cap', [$this, 'fltHasEditUserCap'], 99, 3);

        add_filter('pre_user_query', [$this, 'fltHideUneditableUsers']);
        add_filter('views_users', [$this, 'fltHideUneditableRoleViews']);

        add_filter('presspermit_append_attachment_clause', [$this, 'fltAppendAttachmentClause'], 10, 3);

        add_filter('presspermit_operation_captions', [$this, 'fltOperationCaptions']);

        // called by permissions-ui
        add_filter('presspermit_exception_types', [$this, 'fltExceptionTypes']);
        add_filter('presspermit_append_exception_types', [$this, 'fltAppendExceptionTypes']);
        add_action('presspermit_role_types_dropdown', [$this, 'actDropdownTaxonomyTypes']);

        // called by ajax-exceptions-ui
        add_filter('presspermit_exception_operations', [$this, 'fltExceptionOperations'], 2, 3);
        add_filter('presspermit_exception_via_types', [$this, 'fltExceptionViaTypes'], 10, 5);
        add_action('presspermit_exceptions_status_ui', [$this, 'actExceptionsStatusUi'], 4, 2);

        add_filter('presspermit_ajax_role_ui_vars', [$this, 'actAjaxRoleVars'], 10, 2);
        add_filter('presspermit_get_type_roles', [$this, 'fltGetTypeRoles'], 10, 3);
        add_filter('presspermit_role_title', [$this, 'fltGetRoleTitle'], 10, 2);

        // called by agent-edit-handler
        add_filter('presspermit_add_exception', [$this, 'fltAddException']);

        // Track autodrafts by postmeta in case WP sets their post_status to draft
        add_action('save_post', [$this, 'actSavePost'], 10, 3);
        add_filter('wp_insert_post_empty_content', [$this, 'fltLogInsertPost'], 10, 2);

        add_action('save_post', [$this, 'actUnloadCurrentUserExceptions']);
        add_action('created_term', [$this, 'actUnloadCurrentUserExceptions']);

        add_filter('editable_roles', [$this, 'fltEditableRoles'], 99);

        add_action('pre_get_posts', [$this, 'actNavMenuQueryArgs'], 100);

        add_filter('option_wp_page_for_privacy_policy', [$this, 'fltEditNavMenusIgnoreImportantPages']);
        add_filter('option_show_on_front', [$this, 'fltEditNavMenusIgnoreImportantPages']);
    }

    public function actNavMenuQueryArgs($query_obj)
    {
        if (did_action('wp_ajax_menu-quick-search')) {
            // @todo: confirm this Nav Menu Editor workaround is still needed

            // phpcs:ignore WordPressVIPMinimum.Hooks.PreGetPosts.PreGetPosts
            $query_obj->query_vars['post_status'] = '';
        }
    }

    // If Pages metabox results are paged, prevent custom Front Page and Privacy Policy from being forced to the top of every page
    public function fltEditNavMenusIgnoreImportantPages($option_val)
    {
        if (did_action('wp_ajax_menu-get-metabox') && (PWP::REQUEST_int('paged') > 1)) {
            $option_val = 0;
        }

        return $option_val;
    }

    function actSavePost($post_id, $post, $update)
    {
        if (!empty(presspermit()->flags['ignore_save_post'])) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            update_post_meta($post_id, '_pp_is_autodraft', true);
        } elseif (!$update) {
            if ((PWP::isBlockEditorActive() && !defined('PRESSPERMIT_LIMIT_SAVE_POST_TERM_ASSIGNMENT')) || defined('PRESSPERMIT_LEGACY_SAVE_POST_TERM_ASSIGNMENT')) {
                // For configurations that limit access by term selection, need to default to an allowed term
                if (!presspermit()->isAdministrator()) {
                    require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostTermsSave.php');

                    foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
                        if (!$terms = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids'])) {
                            if ($terms = PostTermsSave::fltPreObjectTerms($terms, $taxonomy)) {
                                wp_set_post_terms($post->ID, $terms, $taxonomy);
                            }
                        }
                    }
                }
            }
        }
    }

    function actUnloadCurrentUserExceptions($item_id)
    {
        if (!empty(presspermit()->flags['ignore_save_post'])) {
            return;
        }

        presspermit()->getUser()->except = []; // force current user exceptions to be reloaded at relevant next capability check
    }

    function actDisableForumPatternRoles()
    {
        $pp = presspermit();
        $pp->role_defs->disabled_pattern_role_types = array_merge(
            $pp->role_defs->disabled_pattern_role_types,
            array_fill_keys(['forum', 'topic', 'reply'], true)
        );
    }

    function fltGetEnabledTaxonomies($taxonomies, $args = [])
    {
        if (is_admin() && (empty($args['object_type']) || ('nav_menu_item' == $args['object_type']))) {
            $taxonomies['nav_menu'] = 'nav_menu';
        }

        return $taxonomies;
    }

    function fltGetEnabledTaxonomiesByKey($taxonomies, $args = [])
    {
        $forced_taxonomies = $this->fltGetEnabledTaxonomies(array_keys($taxonomies), $args);
        return array_merge($taxonomies, $forced_taxonomies);
    }

    function fltAddException($exception)
    {
        if ('_term_' == $exception['for_type']) {
            $exception['for_type'] = $exception['via_type'];
        }

        return $exception;
    }

    function fltExceptionTypes($types)
    {
        if (isset($types['attachment']))
            $types['attachment'] = get_post_type_object('attachment');

        return $types;
    }

    function fltAppendExceptionTypes($types)
    {
        // Add Term option first (before Permission Group)
        $types['_term_'] = (object)[
            'name' => '_term_',
            'labels' => (object)[
                'singular_name' => esc_html__('Term', 'press-permit-core'),
                'name' => esc_html__('Terms', 'press-permit-core')
            ]
        ];

        $types['pp_group'] = (object)[
            'name' => 'pp_group',
            'labels' => (object)[
                'singular_name' => esc_html__('Permission Group', 'press-permit-core'),
                'name' => esc_html__('Permission Groups', 'press-permit-core')
            ]
        ];

        return $types;
    }

    function actDropdownTaxonomyTypes($args = [])
    {
        if (
            empty($args['agent']) || empty($args['agent']->metagroup_id)
            || !in_array($args['agent']->metagroup_id, ['wp_anon', 'wp_all'], true)
        ) {
            echo "<option value='_term_'>" . esc_html__('Term', 'press-permit-core') . '</option>';
        }
    }

    function fltOperationCaptions($op_captions)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/AjaxUI.php');
        return UI\AjaxUI::fltOperationCaptions($op_captions);
    }

    function fltExceptionOperations($ops, $for_source_name, $for_item_type)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/AjaxUI.php');
        return UI\AjaxUI::fltExceptionOperations($ops, $for_source_name, $for_item_type);
    }

    function fltExceptionViaTypes($types, $for_source_name, $for_type, $operation, $mod_type)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/AjaxUI.php');
        return UI\AjaxUI::fltExceptionViaTypes($types, $for_source_name, $for_type, $operation, $mod_type);
    }

    function actExceptionsStatusUi($for_type, $args = [])
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/AjaxUI.php');
        UI\AjaxUI::actExceptionsStatusUi($for_type, $args);
    }

    function fltGetRoleTitle($role_title, $args)
    {
        $matches = [];
        preg_match("/pp_(.*)_manager/", $role_title, $matches);

        if (!empty($matches[1])) {
            $taxonomy = $matches[1];

            if ($tx_obj = get_taxonomy($taxonomy)) {
                if ('nav_menu' == $taxonomy) {  // @todo: use labels_pp property?
                    if (in_array(get_locale(), ['en_EN', 'en_US'])) {
                        $tx_obj->labels->singular_name = __('Nav Menu (Legacy)', 'press-permit-core');
                    } else {
                        $tx_obj->labels->singular_name .= ' (' . __('Legacy', 'press-permit-core') . ')';
                    }
                }

                $role_title = sprintf(esc_html__('%s Manager', 'press-permit-core'), $tx_obj->labels->singular_name);
            }
        }

        return $role_title;
    }

    function actAjaxRoleVars($force, $args)
    {
        if (0 === strpos($args['for_item_type'], '_term_')) {
            $force = (array)$force;
            $force['for_item_source'] = 'term';
            $force['for_item_type'] = substr($args['for_item_type'], strlen('_term_'));
        }

        return $force;
    }

    function fltGetTypeRoles($type_roles, $for_item_source, $for_item_type)
    {
        if ('term' == $for_item_source) {
            $pp = presspermit();

            foreach ($pp->getEnabledTaxonomies(['object_type' => false]) as $taxonomy) {
                $type_roles["pp_{$taxonomy}_manager"] = $pp->admin()->getRoleTitle("pp_{$taxonomy}_manager");
            }
        }

        return $type_roles;
    }

    // Returns the role slugs the current user cannot edit when the level-restriction feature is
    // active, or an empty array when the feature does not apply to the current user/context.
    private function currentUserUneditableRoles()
    {
        if (!presspermit()->filteringEnabled()) return [];

        // Bypass only for true content administrators. Note: isUserAdministrator() merely checks the
        // edit_users cap, which is exactly what lower-level user-managers hold — using it here would
        // wrongly exempt the very users this feature is meant to restrict.
        if (presspermit()->isContentAdministrator()) return [];

        // Feature master switch.
        if (!presspermit()->getOption('limit_user_edit_enabled')) return [];

        $editing_limitation = presspermit()->getOption('limit_user_edit_by_level');

        // Default to '1' (equal-or-lower) when enabled but no explicit mode is saved.
        if (!$editing_limitation || '0' === (string) $editing_limitation) {
            $editing_limitation = '1';
        }

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Users.php');
        return Users::getUneditableRoles($editing_limitation);
    }

    // Hide users that the current user cannot edit from WP admin user list and plugin's own user table.
    function fltHideUneditableUsers($query_obj)
    {
        global $pagenow;

        if (!is_admin()) return $query_obj;

        $is_users_page  = ('users.php' === $pagenow);
        $is_plugin_page = ('admin.php' === $pagenow
            && in_array(presspermitPluginPage(), ['presspermit-edit-permissions', 'presspermit-users'], true));

        if (!$is_users_page && !$is_plugin_page) return $query_obj;

        $uneditable_roles = $this->currentUserUneditableRoles();
        if (!$uneditable_roles) return $query_obj;

        global $current_user, $wpdb;

        // Match the role as an ACTIVE entry in the serialized wp_capabilities meta_value, e.g.
        // "editor";b:1 — quote-anchored so "editor" can't partial-match "senior_editor", and the
        // ;b:1 suffix so a stale/disabled role (e.g. a demoted user's "editor";b:0) is NOT matched.
        // This keeps row-hiding consistent with get_userdata()->roles, which lists only active roles.
        $roles_regex = '"(' . implode('|', array_map('sanitize_key', $uneditable_roles)) . ')";b:1';

        // Always preserve the current user so they never vanish from their own list view.
        $query_obj->query_where .= $wpdb->prepare(
            " AND ({$wpdb->users}.ID = %d OR {$wpdb->users}.ID NOT IN ("
            . "SELECT user_id FROM {$wpdb->usermeta} "
            . "WHERE meta_key = %s AND meta_value REGEXP %s))",
            $current_user->ID,
            $wpdb->get_blog_prefix() . 'capabilities',
            $roles_regex
        );

        return $query_obj;
    }

    // Hide the role filter links (e.g. "Administrator (1) | Editor (1)") above the Users list for
    // any role the current user cannot edit, and recompute the remaining counts to match the
    // filtered (visible) set so they stay consistent with fltHideUneditableUsers().
    function fltHideUneditableRoleViews($views)
    {
        if (!$uneditable_roles = $this->currentUserUneditableRoles()) {
            return $views;
        }

        // Remove the links for roles that cannot be edited.
        foreach ($uneditable_roles as $role_name) {
            unset($views[$role_name]);
        }

        // The current user is always preserved in their own list (see fltHideUneditableUsers), even
        // if their own role is itself uneditable (e.g. 'lower_levels' mode). Account for that here.
        $current_user = wp_get_current_user();
        $current_user_preserved = (bool) array_intersect((array) $current_user->roles, $uneditable_roles);

        // "All" = users without any uneditable role, plus the preserved current user if applicable.
        if (isset($views['all'])) {
            $all_query = new \WP_User_Query(
                ['role__not_in' => $uneditable_roles, 'fields' => 'ID', 'number' => 1, 'count_total' => true]
            );

            $all_count = (int) $all_query->get_total();

            if ($current_user_preserved) {
                $all_count++;
            }

            $views['all'] = $this->replaceViewCount($views['all'], $all_count);
        }

        // Recompute each remaining role link: users with that role but none of the uneditable roles.
        foreach (array_keys($views) as $key) {
            if (in_array($key, ['all', 'none'], true)) {
                continue;
            }

            $role_query = new \WP_User_Query(
                ['role' => $key, 'role__not_in' => $uneditable_roles, 'fields' => 'ID', 'number' => 1, 'count_total' => true]
            );

            $views[$key] = $this->replaceViewCount($views[$key], (int) $role_query->get_total());
        }

        return $views;
    }

    // Replace the "(N)" inside a WP list-table view link's count span, preserving all other markup.
    private function replaceViewCount($view_html, $count)
    {
        return preg_replace(
            '/(<span class="count">\()[^<]*?(\)<\/span>)/',
            '${1}' . esc_html(number_format_i18n($count)) . '$2',
            $view_html,
            1
        );
    }

    // Optionally, prevent anyone from editing or deleting a user whose level is higher than their own
    function fltHasEditUserCap($wp_sitecaps, $orig_reqd_caps, $args)
    {
        if (
            presspermit()->filteringEnabled() && (
                in_array('edit_users', $orig_reqd_caps, true) || in_array('delete_users', $orig_reqd_caps, true)
                || in_array('remove_users', $orig_reqd_caps, true) || in_array('promote_users', $orig_reqd_caps, true)
            ) && !empty($args[2])
        ) {
            global $current_user;

            if ($args[1] != $current_user->ID) {
                return $wp_sitecaps;
            }

            if (presspermit()->getOption('limit_user_edit_enabled')
                && ($editing_limitation = presspermit()->getOption('limit_user_edit_by_level'))
            ) {
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Users.php');
                $wp_sitecaps = Users::hasEditUserCap($wp_sitecaps, $orig_reqd_caps, $args, $editing_limitation);
            }
        }

        return $wp_sitecaps;
    }

    function fltLogInsertPost($maybe_empty, $postarr)
    {
        $this->inserting_post = true;
        return $maybe_empty;
    }

    function fltPageParent($parent_id, $args = [])
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || !presspermit()->filteringEnabled() || ('revision' == PWP::findPostType()) || did_action('pp_disable_page_parent_filter') || ($this->inserting_post)
            || (
                defined('ELEMENTOR_VERSION') && !empty($_REQUEST['actions'])                                     // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
                && strpos($_REQUEST['actions'], '"action\":\"save_builder\",\"data\":{\"status\":\"autosave\"')  // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            )
        ) {
            return $parent_id;
        }

        // Avoid preview failure with ACF active
        if (
            PWP::is_REQUEST('wp-preview', 'dopreview')
            && PWP::is_REQUEST('action', 'editpost')
            && PWP::is_REQUEST('post_ID', $parent_id)
        ) {
            return $parent_id;
        }

        if (did_action('wp_ajax_save_attachment')) {
            return $parent_id;
        }

        if (defined('DOING_AJAX') && DOING_AJAX && !PWP::empty_REQUEST('action') && PWP::REQUEST_key_match('action', 'woocommerce_', ['match_type' => 'contains'])) {
            return $parent_id;
        }

        $orig_parent_id = $parent_id;
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostSaveHierarchical.php');
        $parent_id = PostSaveHierarchical::fltPageParent($parent_id);

        // Don't allow media attachment page to be cleared if user has editing capability (conflict with Image Source Control plugin)
        if (
            !$parent_id && $orig_parent_id
            && (
                (isset($_SERVER['SCRIPT_NAME']) && false !== strpos(sanitize_text_field($_SERVER['SCRIPT_NAME']), 'async-upload.php'))
                || ('attachment' == PWP::findPostType())
                || (isset($_SERVER['SCRIPT_NAME']) && false !== strpos(sanitize_text_field($_SERVER['SCRIPT_NAME']), 'admin-ajax.php') && PWP::is_REQUEST('action', ['save-attachment', 'save-attachment-compat']))
            )
        ) {
            if (current_user_can('edit_post', $orig_parent_id)) {
                $parent_id = $orig_parent_id;
            }
        }

        return $parent_id;
    }

    // filter page dropdown contents for Page Parent controls; leave others alone
    function fltDropdownPages($orig_options_html)
    {
        if (presspermit()->isUserUnfiltered() || (!empty($orig_options_html) && (!strpos($orig_options_html, 'parent_id') && !strpos($orig_options_html, 'post_parent')))) {
            return $orig_options_html;
        }

        global $pagenow;

        if (0 === strpos($pagenow, 'options-'))
            return $orig_options_html;

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PageHierarchyFilters.php');
        return PageHierarchyFilters::fltDropdownPages($orig_options_html);
    }

    function fltPostStatus($status)
    {
        if (presspermit()->isUserUnfiltered() || ('auto-draft' == $status) || (!empty($_SERVER['REQUEST_URI']) && strpos(sanitize_text_field($_SERVER['REQUEST_URI']), 'nav-menus.php'))) {
            return $status;
        }

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostEdit.php');
        return PostEdit::fltPostStatus($status);
    }

    function fltAppendAttachmentClause($where, $clauses, $args)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/MediaQuery.php');
        return MediaQuery::appendAttachmentClause($where, $clauses, $args);
    }

    // optional filter for WP role edit based on user level
    function fltEditableRoles($roles)
    {
        if (!presspermit()->filteringEnabled())
            return $roles;

        if (!presspermit()->getOption('limit_user_edit_enabled'))
            return $roles;

        $editing_limitation = presspermit()->getOption('limit_user_edit_by_level');
        if (!$editing_limitation || '0' === (string) $editing_limitation)
            return $roles;

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Users.php');
        return Users::editableRoles($roles, $editing_limitation);
    }
}

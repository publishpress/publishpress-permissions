<?php
namespace PublishPress\Permissions;

class CollabHooks
{
    function __construct() {
        global $pagenow;

        // Divi Page Builder
        if (PWP::is_REQUEST('action', 'editpost') && !PWP::empty_REQUEST('et_pb_use_builder') && !PWP::empty_REQUEST('auto_draft')) {
            return;
        }

        add_filter('presspermit_item_edit_exception_ops', [$this, 'fltItemEditExceptionOps'], 10, 4);

        // Divi Page Builder  todo: test whether these can be implemented with 'presspermit_unfiltered_ajax' filter in PostFilters::fltPostsClauses instead
        if (PWP::SERVER_url('REQUEST_URI') && strpos(esc_url_raw(PWP::SERVER_url('REQUEST_URI')), 'admin-ajax.php')) {
            if (in_array(
                PWP::REQUEST_key('action'), 
                apply_filters('presspermit_unfiltered_ajax_actions',
                    ['et_fb_ajax_drop_autosave',
                    'et_builder_resolve_post_content',
                    'et_fb_get_shortcode_from_fb_object',
                    'et_builder_library_get_layout',
                    'et_builder_library_get_layouts_data',
                    'et_fb_update_builder_assets',
                    ]
                )
            )) {
                return;
            }
        }

        add_action('init', [$this, 'init']);

        // Divi Page Builder
        add_filter('user_has_cap', [$this, 'fltDiviCaps'], 10, 3);

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Capabilities.php');

        add_action('presspermit_pre_init', [$this, 'actOnInit']);
        add_action('presspermit_options', [$this, 'actAdjustOptions']);
        add_filter('presspermit_role_caps', [$this, 'fltRoleCaps'], 10, 2);
        add_action('presspermit_roles_defined', [$this, 'actSetRoleUsage']);

        add_action('presspermit_maintenance_triggers', [$this, 'actLoadFilters']); // fires early if is_admin() - at bottom of AdminUI constructor
        add_action('presspermit_post_filters', [$this, 'actLoadPostFilters']);
        add_action('presspermit_cap_filters', [$this, 'actLoadCapFilters']);
        add_action('presspermit_page_filters', [$this, 'actLoadWorkaroundFilters']);

        // if PPS is active, hook into its visibility forcing mechanism and UI (applied by PPS for specific pages)
        add_filter('presspermit_getItemCondition', [$this, 'fltForceDefaultVisibility'], 10, 4);
        add_filter('presspermit_read_own_attachments', [$this, 'fltReadOwnAttachments'], 10, 2);
        add_filter('presspermit_ajax_edit_actions', [$this, 'fltAjaxEditActions']);

        add_action('attachment_updated', [$this, 'actAttachmentEnsureParentStorage'], 10, 3);

        add_action('pre_get_posts', [$this, 'actPreventTrashSuffixing']);

		add_action('wp_loaded', [$this, 'supplementUserAllcaps'], 18);
        add_filter('presspermit_meta_caps', [$this, 'fltMetaCaps']);
        add_filter('presspermit_exclude_arbitrary_caps', [$this, 'fltExcludeArbitraryCaps']);

        add_filter('presspermit_default_options', [$this, 'fltDefaultOptions']);
        add_filter('presspermit_default_advanced_options', [$this, 'fltDefaultAdvancedOptions']);

        add_action('presspermit_version_updated', [$this, 'actPluginUpdated']);

        if ( is_admin() ) {
            add_action('presspermit_init', [$this, 'actNonAdministratorEditingFilters']);  // fires after user load
        } else {
            // Also filter for Administrator, to include private and unpublished pages in Page Parent dropdown
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/RESTInit.php');
            new Collab\RESTInit();

            add_filter('rest_pre_dispatch', [$this, 'fltRestAddEditingFilters'], 99, 3);  // also add the filters on REST request
        }

        if (defined('PUBLISHPRESS_REVISIONS_VERSION')) {
            add_action('presspermit_init_rvy_interface', [$this, 'init_rvy_interface']);
    
            // also needed for Admin Bar filtering
            if ((!defined('DOING_AJAX') || !DOING_AJAX) && ('async-upload.php' != $pagenow)) {
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . "/Revisions/Admin.php");
                new Collab\Revisions\Admin();
            }

            if (is_admin() || presspermit()->isRESTurl()) {
	            if (!presspermit()->isContentAdministrator()) {
	                require_once(PRESSPERMIT_COLLAB_CLASSPATH . "/Revisions/AdminNonAdministrator.php");
	                new Collab\Revisions\AdminNonAdministrator();
                }
                
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . "/Revisions/PostFilters.php");
	            new Collab\Revisions\PostFilters();

                if (did_action('init')) {
                    $this->init_rvy_interface();
                } else {
                    add_action('init', [$this, 'init_rvy_interface'], 2);
                }
            }

        } elseif (defined('REVISIONARY_VERSION')) {
            add_action('presspermit_init_rvy_interface', [$this, 'init_rvy_interface']);
    
            $legacy_suffix = version_compare(REVISIONARY_VERSION, '1.5-alpha', '<') ? 'Legacy' : '';

            // also needed for Admin Bar filtering
            if ((!defined('DOING_AJAX') || !DOING_AJAX) && ('async-upload.php' != $pagenow)) {
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . "/Revisionary/Admin{$legacy_suffix}.php");
                ($legacy_suffix) ? new Collab\Revisionary\AdminLegacy() : new Collab\Revisionary\Admin();
            }

            if (is_admin() || presspermit()->isRESTurl()) {
	            if (!presspermit()->isContentAdministrator()) {
	                require_once(PRESSPERMIT_COLLAB_CLASSPATH . "/Revisionary/AdminNonAdministrator{$legacy_suffix}.php");
	                ($legacy_suffix) ? new Collab\Revisionary\AdminNonAdministratorLegacy() : new Collab\Revisionary\AdminNonAdministrator();
                }
                
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . "/Revisionary/PostFilters{$legacy_suffix}.php");
	            ($legacy_suffix) ? new Collab\Revisionary\PostFiltersLegacy() : new Collab\Revisionary\PostFilters();

                if (did_action('init')) {
                    $this->init_rvy_interface();
                } else {
                    add_action('init', [$this, 'init_rvy_interface'], 2);
                }
            }
        }

        if (defined('WPB_VC_VERSION')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Compat/BakeryPageBuilder.php');
            new Collab\Compat\BakeryPageBuilder();
        }

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Compat/MultipleAuthors.php'); // load this early to block rolecap addition if necessary
        new Collab\Compat\MultipleAuthors();

        // Filtering of terms selection:
        add_filter('pre_post_category', [$this, 'fltOriginalRestPostTerms'], 1, 1);  // ensure actual term selections are passed into filter

        add_filter('pre_post_tags_input', [$this, 'fltTagsInput'], 50, 1);
        add_filter('pre_post_tax_input', [$this, 'fltTaxInput'], 50, 1);
        add_filter('pre_post_category', [$this, 'fltPrePostTerms'], 50, 1);
        add_filter('presspermit_pre_object_terms', [$this, 'fltPrePostTerms'], 50, 3);
        add_filter('pre_insert_term', [$this, 'fltPreInsertTerm'], 10, 2);
    }

    function init()
    {
        add_filter('presspermit_pattern_roles', [$this, 'fltPatternRoles']);
    }

    function fltDefaultOptions($def)
    {
        $new = [
            'list_others_uneditable_posts' => 1,
            'lock_top_pages' => 0,
            'admin_others_attached_files' => 0,
            'admin_others_attached_to_readable' => 0,
            'admin_others_unattached_files' => 0,
            'edit_others_attached_files' => 0,
            'attachment_edit_requires_parent_access' => 1,
            'own_attachments_always_editable' => 1,
            'admin_nav_menu_filter_items' => 1,
            'admin_nav_menu_partial_editing' => 0,
            'admin_nav_menu_lock_custom' => 1,
            'limit_user_edit_by_level' => 1,
            'add_author_pages' => 0,
            'publish_author_pages' => 0,
            'editor_hide_html_ids' => '',
            'editor_ids_sitewide_requirement' => 0,
            'force_taxonomy_cols' => 0,
            'default_privacy' => [],
            'force_default_privacy' => [],
            'page_parent_order' => '',
            'page_parent_editable_only' => 0,

            // For legacy compat, default to auto-assigning a default term unless constant PP_NO_AUTO_DEFAULT_TERM is defined (and not overruled by constant PP_AUTO_DEFAULT_TERM)
            'auto_assign_available_term' => !defined('PP_NO_AUTO_DEFAULT_TERM') || defined('PP_AUTO_DEFAULT_TERM'), 
            'create_tag_require_edit_cap' => 0,
        ];

        return array_merge($def, $new);
    }

    function fltDefaultAdvancedOptions($def = [])
    {
        $new = [
            'role_usage' => [], // note: this stores user-defined pattern role and direct role enable
            'non_admins_set_edit_exceptions' => 0,
            'publish_exceptions' => defined('PP_PUBLISH_EXCEPTIONS') ? 1 : 0,  // this setting was previously controlled by define statement
        ];

        return array_merge($def, $new);
    }

    function supplementUserAllcaps() {
        $pp = presspermit();

        if ($pp->getOption('list_others_uneditable_posts')) {
            foreach ($pp->getEnabledPostTypes() as $post_type) {
                if ($type_obj = get_post_type_object($post_type)) {
                    if (isset($type_obj->cap->edit_posts) && !empty($user->allcaps[$type_obj->cap->edit_posts])
                    && isset($type_obj->cap->edit_others_posts) && empty($user->allcaps[$type_obj->cap->edit_others_posts])) {
                        $list_others_cap = str_replace('edit_', 'list_', $type_obj->cap->edit_others_posts);
                        $user->allcaps[$list_others_cap] = true;
                    }
                }
            }
        }
    }

    function fltItemEditExceptionOps($operations, $for_item_source, $for_item_type, $via_item_type = '')
    {
        if ('post' == $for_item_source) {
            foreach (['edit', 'copy', 'revise', 'associate', 'assign'] as $op) {
                if (('associate' == $op) && presspermit()->getOption('page_parent_editable_only')) {
                    continue;
                }
                
                if (presspermit()->admin()->canSetExceptions($op, $for_item_type, ['for_item_source' => $for_item_source])) {
                    $operations[$op] = true;
                }

                if (presspermit()->getOption('publish_exceptions') && !empty($operations['edit'])) {
                    $operations['publish'] = true;
                }
            }
        } elseif ('term' == $for_item_source) {
            foreach (['edit', 'copy', 'revise', 'assign'] as $op) {
                if (presspermit()->admin()->canSetExceptions(
                    $op, 
                    $for_item_type, 
                    ['via_item_source' => 'term', 'via_type_name' => $via_item_type, 'for_item_source' => $for_item_source]
                )) {
                    $operations[$op] = true;
                }
            }
        }

        return $operations;
    }

    function actNonAdministratorEditingFilters()
    {
        if (!presspermit()->isUserUnfiltered()) {
            require_once(__DIR__ . '/CollabHooksAdminNonAdministrator.php');
            new CollabHooksAdminNonAdministrator();

            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/CapabilityFiltersAdmin.php');
            new Collab\CapabilityFiltersAdmin();
        }
    }

    public function init_rvy_interface()
    {
        global $revisionary;

        if (class_exists('RevisionsContentRoles')) {
            if (!empty($revisionary) && method_exists($revisionary, 'set_content_roles')) {
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Revisions/ContentRoles.php');
                $revisionary->set_content_roles(new Collab\Revisions\ContentRoles());
            }

        } elseif (class_exists('RevisionaryContentRoles')) {
            if (!empty($revisionary) && method_exists($revisionary, 'set_content_roles')) {
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Revisionary/ContentRoles.php');
                $revisionary->set_content_roles(new Collab\Revisionary\ContentRoles());
            }
        }
    }

    function fltRestAddEditingFilters($rest_response, $rest_server, $request) 
    {
        $this->actNonAdministratorEditingFilters();

        return $rest_response;
    }

    // Safeguard against improper filtering (to zero) of post_parent value. Forces mirroring of postmeta _thumbnail_id <> post_id relationship 
    function actAttachmentEnsureParentStorage($post_id, $post_after, $post_before) {
        if ($post_before->post_parent && !$post_after->post_parent) {
            if ($post_id == get_post_meta($post_before->post_parent, '_thumbnail_id', true)) {
                global $wpdb;

                // @todo: still needed?

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update($wpdb->posts, ['post_parent' => $post_before->post_parent], ['ID' => $post_id]);
            }
        }
    }

    function actPreventTrashSuffixing($wp_query)
    {
        if (!empty($_SERVER['REQUEST_URI']) && false !== strpos(esc_url_raw($_SERVER['REQUEST_URI']), PWP::admin_rel_url('nav-menus.php')) 
        && PWP::is_POST('action', 'update')
        ) {
            // Workaround for Nav Menu deletion (@todo: still needed?)

            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
            $bt = debug_backtrace();

            foreach ($bt as $fcall) {
                if (!empty($fcall['function']) && 'wp_add_trashed_suffix_to_post_name_for_trashed_posts' == $fcall['function']) {
                    // phpcs:ignore WordPressVIPMinimum.Hooks.PreGetPosts.PreGetPosts
                    $wp_query->query_vars['suppress_filters'] = 1;
                    break;
                }
            }
        }
    }

    function fltDiviCaps($wp_sitecaps, $orig_reqd_caps, $args) 
    {
        global $current_user;

        // Work around Divi Page Builder requiring off-type capabilities, which prevents Specific Permissions from satisfying edit_published_pages capability requirement
        if ((is_admin() || PWP::empty_REQUEST('et_fb')) && (empty($_SERVER['REQUEST_URI']) || !strpos(esc_url_raw($_SERVER['REQUEST_URI']), 'admin-ajax.php') || !did_action('wp_ajax_et_fb_ajax_save'))) {
            return $wp_sitecaps;
        }

        if ($args[1] != $current_user->ID) {
            return $wp_sitecaps;
        }

        $post_id = PWP::getPostID();

        // Work around Divi Page Builder requiring edit_posts for other post types
        if ('edit_posts' == reset($orig_reqd_caps)) {
            if ($post_id) {
                if ($type_obj = get_post_type_object(get_post_field('post_type', $post_id))) {
                    if (!empty($type_obj->cap->edit_posts) && ('edit_posts' != $type_obj->cap->edit_posts) && current_user_can($type_obj->cap->edit_posts)) {
                        return array_merge($wp_sitecaps, ['edit_posts' => true]);
                    }
                }
            }
        }

        static $busy;

        if (!empty($busy)) {
            return $wp_sitecaps;
        }

        $orig_cap = (isset($args[0])) ? sanitize_key($args[0]) : reset($orig_reqd_caps);

        // If user can edit the current post, credit edit_published_posts, edit_published_pages, publish_posts capabilities
        if (in_array($orig_cap, ['edit_published_posts', 'edit_published_pages', 'publish_posts'])) {
            if ($post_id) {
                $busy = true;

                if (current_user_can('edit_post', $post_id)) {
                    $wp_sitecaps[$orig_cap] = true;
                }

                $busy = false;
            }
        }

        return $wp_sitecaps;
    }

    function fltMetaCaps($meta_caps)
    {
        // Divi Page Builder
		if (defined('ET_BUILDER_THEME')) {
			if (PWP::is_REQUEST('action', 'edit')) {
                if ($post_id = PWP::REQUEST_int('post')) {
                    if ($_post = get_post($post_id)) {
                        global $current_user;
                        if (in_array($_post->post_status, ['draft', 'auto-draft']) && ($_post->post_author == $current_user->ID) && !$_post->post_name) {
                            return $meta_caps;
                        }
                    }
                }
			}
		}

        return array_merge(
            $meta_caps, 
            [
                'edit_post' => 'edit', 
                'edit_page' => 'edit', 
                'delete_post' => 'delete', 
                'delete_page' => 'delete',
                'edit_post_meta' => 'edit',
                'delete_post_meta' => 'delete',
            ]
        );
    }

    function fltAjaxEditActions($actions)
    {
        if (!presspermit()->getOption('admin_others_attached_to_readable')) {
            $actions = array_merge($actions, ['query-attachments', 'mla-query-attachments']);
        }

        return $actions;
    }

    function fltReadOwnAttachments($read_own, $args = [])
    {
        if (!$read_own) {
            global $current_user;
            return presspermit()->getOption('own_attachments_always_editable') || !empty($current_user->allcaps['edit_own_attachments']);
        }

        return $read_own;
    }

    function fltForceDefaultVisibility($item_condition, $source_name, $attribute, $args = [])
    {
        // allow any existing page-specific settings to override default forcing
        if (('post' == $source_name) && ('force_visibility' == $attribute) && !$item_condition && isset($args['post_type'])) {
            if (empty($args['assign_for']) || ('item' == $args['assign_for'])) {
                if ($default_privacy = presspermit()->getTypeOption('default_privacy', $args['post_type'])) {
                    if ($force = presspermit()->getTypeOption('force_default_privacy', $args['post_type']) || PWP::isBlockEditorActive($args['post_type'])) {
                        // only apply if status is currently registered and PP-enabled for the post type
                        if (PWP::getPostStatuses(['name' => $default_privacy, 'post_type' => $args['post_type']])) {
                            if (!empty($args['return_meta']))
                                return (object)['force_status' => $default_privacy, 'force_basis' => 'default'];
                            else
                                return $default_privacy;
                        }
                    }
                }
            }
        }

        return $item_condition;
    }

    function actOnInit()
    {
        Collab\Capabilities::instance();

        // --- version check ---
        $ver = get_option('ppce_version');

        if ($ver && !empty($ver['version'])) {
            // These maintenance operations only apply when a previous version of PP was installed 
            if (version_compare(PRESSPERMIT_COLLAB_VERSION, $ver['version'], '!=')) {
                update_option('ppce_version', ['version' => PRESSPERMIT_COLLAB_VERSION, 'db_version' => 0]);
            }
        } elseif (!$ver) {
            Collab::populateRoles();
            update_option('ppce_version', ['version' => PRESSPERMIT_COLLAB_VERSION, 'db_version' => 0]);
        }
        // --- end version check ---

        if (defined('XMLRPC_REQUEST')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/XmlRpc.php');
            new Collab\XmlRpc();
        }

        if (empty($_SERVER['REQUEST_URI'])) {
            return;
        }

        if (false !== strpos(esc_url_raw($_SERVER['REQUEST_URI']), '/wp-json/wp/v2')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/REST_Workarounds.php');
            new Collab\REST_Workarounds();
        }
    }

    function actPluginUpdated($prev_pp_version) {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Updated.php');
        new Collab\Updated($prev_pp_version);
    }

    function actAdjustOptions($options)
    {
        if (isset($options['presspermit_enabled_taxonomies'])) {
            $opt_val = maybe_unserialize($options['presspermit_enabled_taxonomies']);
            
            if (is_array($opt_val)) {
                $options['presspermit_enabled_taxonomies'] = array_merge(
                    $opt_val, 
                    []
                );
            } else {
                $options['presspermit_enabled_taxonomies'] = [];
            }
        }
    	
        if (!empty($options['presspermit_default_privacy'])) {
            $disabled_types = (class_exists('bbPress', false)) ? ['forum', 'topic', 'reply'] : [];
            if ($disabled_types = apply_filters('presspermit_disabled_default_privacy_types', $disabled_types)) {
                if ($_default_privacy = maybe_unserialize($options['presspermit_default_privacy']))
                    $options['presspermit_default_privacy'] = array_diff_key($_default_privacy, array_fill_keys($disabled_types, true));
            }
        }

        return $options;
    }

    function fltPatternRoles($roles)
    {
        return array_merge($roles, ['contributor' => (object)[], 'author' => (object)[], 'editor' => (object)[]]);
    }

    function actSetRoleUsage()
    {
        global $wp_roles;

        if (empty($wp_roles))
            return;

        $pp = presspermit();

        // don't apply custom Role Usage settings if advanced options are disabled
        $stored_usage = (!defined('PRESSPERMIT_LEGACY_ROLE_USAGE') || $pp->getOption('advanced_options')) ? $pp->getOption('role_usage') : [];

        if ($stored_usage) {
            $enabled_pattern_roles = array_intersect((array)$stored_usage, ['pattern']);
            $enabled_direct_roles = array_intersect((array)$stored_usage, ['direct']);
            $no_usage_roles = array_intersect((array)$stored_usage, ['0', 0, false]);
        } else {
            $enabled_pattern_roles = $enabled_direct_roles = [];
        }

        $pp->role_defs->pattern_roles = apply_filters('presspermit_default_pattern_roles', $pp->role_defs->pattern_roles);

        if ($stored_usage) {  // if no role usage is stored, use default pattern roles
            $pp->role_defs->pattern_roles = array_diff_key($pp->role_defs->pattern_roles, $enabled_direct_roles, $no_usage_roles);

            $additional_pattern_roles = array_diff_key($enabled_pattern_roles, $pp->role_defs->pattern_roles);

            foreach (array_keys($additional_pattern_roles) as $role_name) {
                if (isset($wp_roles->role_names[$role_name]))
                    $pp->role_defs->pattern_roles[$role_name] = (object)[
                        'is_additional' => true, 
                        'labels' => (object)[
                            'name' => $wp_roles->role_names[$role_name], 
                            'singular_name' => $wp_roles->role_names[$role_name]
                            ]
                        ];
            }

            // Direct Role Usage
            $use_wp_roles = array_diff_key($wp_roles->role_names, ['administrator' => true]);
            $use_wp_roles = array_intersect_key($use_wp_roles, $enabled_direct_roles, $wp_roles->role_names);

            foreach (array_keys($use_wp_roles) as $role_name) {
                $labels = (isset($pp->role_defs->pattern_roles[$role_name])) 
                ? $pp->role_defs->pattern_roles[$role_name]->labels 
                : (object)['name' => $wp_roles->role_names[$role_name], 'singular_name' => $wp_roles->role_names[$role_name]];
                
                $pp->role_defs->direct_roles[$role_name] = (object)compact('labels');
            }
        }
    }

    function fltRoleCaps($caps, $role_name)
    {
        $matches = [];
        preg_match("/pp_(.*)_manager/", $role_name, $matches);

        if (!empty($matches[1])) {
            $taxonomy = $matches[1];
            if ($tx_obj = get_taxonomy($taxonomy)) {
                $caps = array_diff((array)$tx_obj->cap, ['edit_posts']);
            }
        }

        return $caps;
    }

    function actLoadWorkaroundFilters()
    { 
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PageFilters.php');
        new Collab\PageFilters();
    }

    function actLoadCapFilters()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/CapabilityFilters.php');
        new Collab\CapabilityFilters();

        if (defined('PUBLISHPRESS_REVISIONS_VERSION')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Revisions/CapabilityFilters.php');
            new Collab\Revisions\CapabilityFilters();
        
        } elseif (defined('REVISIONARY_VERSION')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Revisionary/CapabilityFilters.php');
            new Collab\Revisionary\CapabilityFilters();
        }
    }

    function actLoadPostFilters()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostFilters.php');
        new Collab\PostFilters();
    }

    function actLoadFilters()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/AdminFilters.php');
        new Collab\AdminFilters();
    }

    function fltExcludeArbitraryCaps($caps)
    {
        return array_merge($caps, [
            'pp_force_quick_edit', 
            'edit_own_attachments', 
            'list_others_unattached_files', 
            'admin_others_unattached_files', 
            'pp_list_all_files'
            ]
        );
    }

    function fltTagsInput($tags_input)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostTermsSave.php');
        return Collab\PostTermsSave::fltTagsInput($tags_input);
    }

    function fltTaxInput($tax_input)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostTermsSave.php');
        return Collab\PostTermsSave::fltTaxInput($tax_input);
    }

    function fltPrePostTerms($terms, $taxonomy = 'category', $args = [])
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostTermsSave.php');
        return Collab\PostTermsSave::fltPreObjectTerms($terms, $taxonomy, $args);
    }

    public function fltPreInsertTerm($term, $taxonomy) {
        if ($tx_obj = get_taxonomy($taxonomy)) {
            if (empty($tx_obj->hierarchical) && presspermit()->getOption('create_tag_require_edit_cap') && !presspermit()->isAdministrator()) {
                if (!current_user_can($tx_obj->cap->edit_terms)) {
                    $tx_label = (!empty($tx_obj->labels) && !empty($tx_obj->labels->name)) ? $tx_obj->labels->name : $taxonomy;
                    $term = new \WP_Error('unauthorized', sprintf(esc_html__('You are not allowed to create new %s', 'press-permit-core'), $tx_label));
                }
            }
        }

        return $term;
    }

    function fltOriginalRestPostTerms($terms, $taxonomy = 'category')
    {
        global $wp_version;

        if (!defined('REST_REQUEST') || !REST_REQUEST || !version_compare($wp_version, '5.6', '>=')) {
            return $terms;
        }

        // On REST post update, compensate for errant passing of currently stored post categories into pre_post_category filter instead of selected categories
        if ($tx_obj = get_taxonomy($taxonomy)) {
            $rest_base = (!empty($tx_obj->rest_base)) ? $tx_obj->rest_base : $tx_obj->name;

            // phpcs Note: this is only executed with WP < 5.6

            // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsRemoteFile
            $payload_vars = json_decode(file_get_contents('php://input'), true);

            if ($payload_vars && is_array($payload_vars) && isset($payload_vars[$rest_base]) && is_array($payload_vars[$rest_base])) {
                $terms = $payload_vars[$rest_base];
            }
        }

        return $terms;
    }
}

<?php

namespace PublishPress;

class PermissionsHooksAdmin
{
    private $config_updated = false;

    public function __construct()
    {
        add_action('presspermit_duplicate_module', [$this, 'duplicateModule'], 10, 2);

        add_filter('presspermit_pattern_roles_raw', [$this, 'fltPatternRolesRaw']);
        add_filter('presspermit_pattern_roles', [$this, 'fltPatternRoles'], 20);

        add_action('admin_menu', [$this, 'actSettingsPageMaybeRedirect'], 999);

        if (defined('SSEO_VERSION') && function_exists('sseo_register_parameter')) {
            require_once(PRESSPERMIT_CLASSPATH . '/Compat/EyesOnlyAdmin.php');
            new Permissions\Compat\EyesOnlyAdmin();
        }

        // make sure empty terms are included in quick search results in "Set Specific Permissions" term selection metaboxes

        if (!empty($_SERVER['PHP_SELF']) && in_array(basename($_SERVER['PHP_SELF']), ['admin.php', 'admin-ajax.php'])) {
            add_action('wp_ajax_pp_dismiss_msg', [$this, 'dashboardDismissMsg']);
        }
        
        // @todo: Why is hidden cols option for Users screen sometimes stored with db prefix prepended to meta_key?
        if (defined('DOING_AJAX') && DOING_AJAX && ('hidden-columns' == PWP::REQUEST_key('action'))) {
            add_action('query',
                function($query) {
                    global $wpdb;

                    if ((0 === strpos($query, 'UPDATE')) && !empty($wpdb->prefix)) {
                        $query = str_replace("`meta_key` = '{$wpdb->prefix}manageuserscolumnshidden'", "`meta_key` = 'manageuserscolumnshidden'", $query);
                    }

                    return $query;
                }
            );
        }

        if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
            add_filter('authors_default_author', [$this, 'fltAuthorsPreventPostCreationLockout'], 99, 2);
            add_action('save_post', [$this, 'actAuthorsPreventPostUpdateLockout'], 1, 3);
        }

        add_filter('presspermit_exception_item_update_hooks', function($var) {return true;});
        add_filter('presspermit_exception_item_insertion_hooks', function($var) {return true;});
        add_filter('presspermit_exception_item_deletion_hooks', function($var) {return true;});

        add_action('presspermit_exception_items_updated', [$this, 'actPluginSettingsUpdated']);
        add_action('presspermit_inserted_exception_item', [$this, 'actPluginSettingsUpdated']);
        add_action('pp_inserted_exception_item', [$this, 'actPluginSettingsUpdated']);
        add_action('presspermit_removed_exception_items', [$this, 'actPluginSettingsUpdated']);

        add_filter('presspermit_cap_descriptions', [$this, 'flt_cap_descriptions'], 3);  // priority 3 for ordering before PPS and PPCC additions in caps list
        add_filter('cme_presspermit_capabilities', [$this, 'fltFlagPermissionsCapabilities'], 3);
        add_filter('cme_presspermit_capabilities', [$this, 'fltOrderPermissionsCapabilities'], 999);
        add_filter('cme_capability_descriptions', [$this, 'fltCapabilityDescriptions']);

        add_action('presspermit_trigger_cache_flush', [$this, 'wpeCacheFlush']);
        add_action('presspermit_activate', [$this, 'actPluginSettingsUpdated']);
        add_action('shutdown', [$this, 'actConfigUpdateFollowup']);
    }

    public function init()
    {
        if (presspermit()->isPro()) {
            require_once(PRESSPERMIT_PRO_ABSPATH . '/includes-pro/pro-maint.php');
            Permissions\PressPermitMaint::adminRedirectCheck();
        }

        require_once(PRESSPERMIT_CLASSPATH . '/CapabilityFiltersAdmin.php');
        new Permissions\CapabilityFiltersAdmin();

        if (presspermitPluginPage()) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/PluginPage.php');
            Permissions\UI\PluginPage::instance();
        }

        if (!PWP::empty_POST() || !PWP::empty_REQUEST('action') || !PWP::empty_REQUEST('action2') || !PWP::empty_REQUEST('pp_action')) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Handlers/Admin.php');
            Permissions\UI\Handlers\Admin::handleRequest();
        }

        add_action('wp_loaded', [$this,'actLoadAjaxHandler'], 20);

        if (!PWP::empty_POST('presspermit_submit') || !PWP::empty_POST('presspermit_defaults') || !PWP::empty_POST('pp_role_usage_defaults')) {
            // For 'settings' admin panels, handle updated options right after current_user load (and before pp_init).
            // By then, check_admin_referer is available, but PP config and WP admin menu has not been loaded yet.
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Handlers/Settings.php');
            new Permissions\UI\Handlers\Settings();
        }

        if (!PWP::empty_REQUEST('presspermit_refresh_updates') || !PWP::empty_REQUEST('pp_renewal')) {
            if (!current_user_can('pp_manage_settings')) {
                wp_die(esc_html(PWP::__wp('Cheatin&#8217; uh?')));
            }
    
            if (!PWP::empty_REQUEST('presspermit_refresh_updates')) {
                delete_site_transient('update_plugins');
                delete_option('_site_transient_update_plugins');
                wp_update_plugins();
                wp_redirect(admin_url('admin.php?page=presspermit-settings&presspermit_refresh_done=1'));
                exit;
            }
    
            if (!PWP::empty_REQUEST('pp_renewal')) {
                if (presspermit()->isPro()) {
                    include_once(PRESSPERMIT_PRO_ABSPATH . '/includes-pro/pro-renewal-redirect.php');
                } else {
                    include_once(PRESSPERMIT_ABSPATH . '/includes/renewal-redirect.php');
                }
    
                exit;
            }
        }

        if (get_option('presspermit_refresh_role_usage')) {
            $this->actApplyDefaultRoleUsage();
            delete_option('presspermit_refresh_role_usage');
        }

        if (PWP::is_GET('pp_agent_search')) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentsAjax.php');
            new Permissions\UI\AgentsAjax();
            exit;
        }
    }

    public function actApplyDefaultRoleUsage() {
        add_action('init', function() {
            global $wp_roles;

            $pp = presspermit();

            if (!$role_usage = $pp->getOption('role_usage')) {
                $role_usage = [];
            }

            $cap_caster = $pp->capCaster();
            $cap_caster->definePatternCaps(['force_strict' => true]);

            $type_obj = get_post_type_object('post');
            $post_caps = array_diff_key(get_object_vars($type_obj->cap), array_fill_keys(['read_post', 'edit_post', 'delete_post'], true));

            foreach ($wp_roles->roles as $role_name => $_role) {
                if (isset($role_usage[$role_name]) || !empty($cap_caster->pattern_role_type_caps[$role_name])) {
                    continue;
                }

                if (!$role_post_caps = array_intersect_key(
                    $post_caps,
                    $_role['capabilities']
                )) {
                    continue;
                }

                $ignore_caps = array_fill_keys(
                    ['set_posts_status', 'create_posts', 'list_posts', 'list_published_posts', 'list_private_posts', 'list_others_posts'],
                    true
                );

                foreach ($cap_caster->pattern_role_type_caps as $pattern_role_name => $pattern_role_caps) {
                    if (!array_diff_key($pattern_role_caps, $role_post_caps, $ignore_caps)
                    && !array_diff_key($role_post_caps, $pattern_role_caps, $ignore_caps)
                    ) {
                        continue 2;
                    }
                }

                $any_updated = true;
                $role_usage[$role_name] = 'pattern';
            }

            if (!empty($any_updated)) {
                update_option('presspermit_role_usage', $role_usage);
            }
        }, 100);
    }

    public function actPluginSettingsUpdated() {
        $this->config_updated = true;
    }

    public function actConfigUpdateFollowup() {
        if ($this->config_updated) {
            $this->wpeCacheFlush();
        }
    }

    function flt_cap_descriptions($pp_caps)
    {
        require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsAdmin.php');
        return Permissions\UI\SettingsAdmin::setCapabilityDescriptions($pp_caps);
    }

    public function fltFlagPermissionsCapabilities($caps) {
        $caps = array_merge(
            $caps,
            [
                'pp_administer_content',
                'pp_assign_roles',
                'pp_assign_bulk_roles', 
                'pp_create_groups',
                'pp_delete_groups',
                'pp_edit_groups',
                'pp_manage_members',
                'pp_manage_settings',
                'pp_set_read_exceptions',
                'pp_unfiltered',
            ]
        );

        // Prevent PublishPress Capabilities from Pro listing capabilities of modules which are not enabled
        // These will be added by the related module.
        $caps = array_diff(
            $caps, 
            [
                'pp_define_moderation',             // obsolete
                'pp_manage_capabilities',           // not a real capability
                'pp_set_edit_exceptions',           // Collaboration
                'pp_set_revise_exceptions',         // Collaboration          
                'pp_set_associate_exceptions',      // Collaboration
                'pp_set_term_assign_exceptions',    // Collaboration
                'pp_set_term_manage_exceptions',    // Collaboration
                'pp_set_term_associate_exceptions', // Collaboration
                'edit_own_attachments',             // Collaboration
                'list_others_unattached_files',     // Collaboration
                'pp_associate_any_page',            // Collaboration
                'pp_list_all_files',                // Collaboration
                'list_posts',                       // Collaboration
                'list_others_posts',                // Collaboration
                'list_private_pages',               // Collaboration
                'pp_force_quick_edit',              // Collaboration
                'pp_exempt_read_circle',            // Circles module
                'pp_exempt_edit_circle',            // Circles module
                'pp_create_network_groups',         // Compatibility module
                'pp_manage_network_members',        // Compatibility module
                'set_posts_status',                 // Status Control
                'pp_moderate_any',                  // Status Control
                'pp_define_privacy',                // Status Control
                'pp_define_post_status',            // Status Control
            ]
        );
        
        return $caps;
    }

    public function fltOrderPermissionsCapabilities($caps) {
        sort($caps);
        return array_unique($caps);
    }

    public function fltCapabilityDescriptions($descripts) {
        return apply_filters('presspermit_cap_descriptions', $descripts);
    }

    /**
     * Full WP Engine cache flush (Hold for possible future use as needed)
     *
     * Based on WP Engine Cache Flush by Aaron Holbrook
     * https://github.org/a7/wpe-cache-flush/
     * http://github.org/a7/
     */
    public function wpeCacheFlush() {
        // Don't cause a fatal if there is no WpeCommon class
        if ( ! class_exists( 'WpeCommon' ) ) {
            return false;
        }

        if ( function_exists( 'WpeCommon::purge_memcached' ) ) {
            \WpeCommon::purge_memcached();
        }

        if ( function_exists( 'WpeCommon::clear_maxcdn_cache' ) ) {
            \WpeCommon::clear_maxcdn_cache();
        }

        if ( function_exists( 'WpeCommon::purge_varnish_cache' ) ) {
            \WpeCommon::purge_varnish_cache();
        }

        global $wp_object_cache;
        // Check for valid cache. Sometimes this is broken -- we don't know why! -- and it crashes when we flush.
        // If there's no cache, we don't need to flush anyway.

        if ( !empty($wp_object_cache) && is_object( $wp_object_cache ) ) {
            @wp_cache_flush();
        }
    }

    public function actLoadAjaxHandler()
    {
        foreach (['item', 'agent_roles', 'agent_exceptions', 'agent_permissions', 'user', 'settings', 'items_metabox'] as $ajax_type) { // todo: term_ui ?
            // This is for admin UI output.
            if (PWP::is_REQUEST("pp_ajax_{$ajax_type}")) {
                $class_name = str_replace('_', '', ucwords( $ajax_type, '_') ) . 'Ajax';
                
                $class_parent = ( in_array($class_name, ['ItemAjax','UserAjax']) ) ? 'Dashboard' : '';
                
                $require_path = ( $class_parent ) ? "{$class_parent}/" : '';
                require_once(PRESSPERMIT_CLASSPATH . "/UI/{$require_path}{$class_name}.php");
                
                $load_class = "\\PublishPress\Permissions\UI\\";
                $load_class .= ($class_parent) ? $class_parent . "\\" . $class_name : $class_name;

                new $load_class();

                exit;
            }
        }

        if ('pp-menu-quick-search' == PWP::REQUEST_key('action')) {
            require_once(PRESSPERMIT_CLASSPATH.'/UI/ItemsMetabox.php' );
            \PublishPress\Permissions\UI\ItemsMetabox::ajax_menu_quick_search();

            exit;
        }
    }

    public function duplicateModule($ext_slug, $ext_folder)
    {
        presspermit()->admin()->errorNotice('duplicate_module', ['module_slug' => $ext_slug, 'module_folder' => $ext_folder]);
    }

    public function fltPatternRolesRaw($roles) {
        return $this->fltPatternRoles($roles, false);
    }

    public function fltPatternRoles($roles, $set_labels = true)
    {
        foreach (['subscriber', 'contributor', 'author', 'editor'] as $role_name) {
            if (!isset($roles[$role_name])) {
                $roles[$role_name] = (object) [];
            }
        }

        if ($set_labels) {
            $roles['subscriber']->labels = (object)['name' => esc_html__('Subscribers', 'press-permit-core'), 'singular_name' => esc_html__('Subscriber')];
            $roles['contributor']->labels = (object)['name' => esc_html__('Contributors', 'press-permit-core'), 'singular_name' => esc_html__('Contributor')];
            $roles['author']->labels = (object)['name' => esc_html__('Authors', 'press-permit-core'), 'singular_name' => esc_html__('Author')];
            $roles['editor']->labels = (object)['name' => esc_html__('Editors', 'press-permit-core'), 'singular_name' => esc_html__('Editor')];
        } else {
            foreach (['subscriber', 'contributor', 'author', 'editor'] as $role_name) {
                if (!isset($roles[$role_name]->labels)) {
                    $display_name = ucwords(str_replace(['-', '_'], ' ', $role_name));
                    $roles[$role_name]->labels = (object)['name' => $display_name, 'singular_name' => $display_name];
                }
            }
        }

        return $roles;
    }

    // For old extensions linking to page=pp-settings.php, redirect to page=presspermit-settings, preserving other request args
    public function actSettingsPageMaybeRedirect()
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        foreach ([
                     'pp-settings' => 'presspermit-settings',
                     'pp-groups' => 'presspermit-groups',
                     'pp-group-new' => 'presspermit-group-new',
                     'pp-users' => 'presspermit-users',
                     'pp-edit-permissions' => 'presspermit-edit-permissions',
                 ] as $old_slug => $new_slug) {
            if (
                strpos(esc_url_raw($_SERVER['REQUEST_URI']), "page=$old_slug")
                && (false !== strpos(esc_url_raw($_SERVER['REQUEST_URI']), 'admin.php'))
            ) {
                global $submenu;

                // Don't redirect if pp-settings is registered by another plugin or theme
                foreach (array_keys($submenu) as $i) {
                    foreach (array_keys($submenu[$i]) as $j) {
                        if (isset($submenu[$i][$j][2]) && ($old_slug == $submenu[$i][$j][2])) {
                            return;
                        }
                    }
                }

                $arr_url = wp_parse_url(esc_url_raw($_SERVER['REQUEST_URI']));
                wp_redirect(admin_url('admin.php?' . str_replace("page=$old_slug", "page=$new_slug", $arr_url['query'])));
                exit;
            }
        }
    }

    public function dashboardDismissMsg()
    {
        $dismissals = get_option('presspermit_dismissals');
        if (!is_array($dismissals)) {
            $dismissals = [];
        }

        // phpcs Note: No need for nonce verification on the notice dismissal

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $msg_id = (isset($_REQUEST['msg_id'])) ? sanitize_key($_REQUEST['msg_id']) : 'post_blockage_priority';
        $dismissals[$msg_id] = true;
        update_option('presspermit_dismissals', $dismissals);
    }

    // Prevent users lacking edit_others capability from being locked out of their newly created post due to Authors' "default author for new posts" setting
    function fltAuthorsPreventPostCreationLockout($default_author, $post) {
        global $current_user;

        if (presspermit()->isAdministrator()) {
            return $default_author;
        }
        
        if ($post_type = PWP::findPostType()) {
            if ($type_obj = get_post_type_object($post_type)) {
                if (!empty($type_obj->cap->edit_others_posts) && !current_user_can($type_obj->cap->edit_others_posts)) {
                    if (apply_filters('presspermit_override_default_author', true, ['post_type' => $post_type])) {
                        return $current_user->ID;
                    }
                }
            }
        }

        return $default_author;
    }

    function actAuthorsPreventPostUpdateLockout($post_id, $post, $update) {
        global $current_user;
        
        if (!$update
        || presspermit()->isAdministrator() 
        || !function_exists('get_multiple_authors') 
        || !function_exists('is_multiple_author_for_post')
        || !PWP::is_POST('authors')
        || !apply_filters('presspermit_maybe_override_authors_change', true, $post)
        ) {
            return;
        }
        
        if ($post_type = PWP::findPostType()) {
            if ($type_obj = get_post_type_object($post_type)) {
                if (!empty($type_obj->cap->edit_others_posts) 
                && empty($current_user->allcaps[$type_obj->cap->edit_others_posts])
                && is_multiple_author_for_post($current_user, $post_id)
                && method_exists('MultipleAuthors\Classes\Objects\Author', 'get_by_user_id')
                ) {
                    if (!$current_author = \MultipleAuthors\Classes\Objects\Author::get_by_user_id($current_user->ID)) {
                        return;
                    }

                    // phpcs Note: No nonce verification because we need to apply this PublishPress Authors safeguard regardless of how the post update was initiated

                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                    $authors = (!empty($_POST['authors'])) ? array_map('sanitize_key', $_POST['authors']) : [];

                    if (!in_array($current_author->term_id, $authors)) {
                        if (apply_filters('presspermit_override_authors_change', true, $post)) {
                            $_POST['authors'] = array_merge([strval($current_author->term_id)], $authors);
                        }
                    }
                }
            }
        }
    }
}

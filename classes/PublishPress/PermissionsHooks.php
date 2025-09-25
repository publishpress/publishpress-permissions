<?php

namespace PublishPress;

class PermissionsHooks
{
    // object references
    private $admin_hooks;
    private $cap_filters;

    // status
    public $direct_file_access = false;
    private $filters_loaded = false;
    private $post_filters_loaded = false;
    private $filtering_enabled = true;

    public function __construct($args = [])
    {
        global $pagenow;

        $defaults = ['load_filters' => true];
        $args = array_merge($defaults, (array)$args);

        if (is_multisite()) {
            add_action('switch_blog', [$this, 'actSwitchBlog']);
        }

        add_filter('presspermit_options', [$this, 'fltForceAdvancedDefaults']);

        // REST logging and blockers
        add_filter('rest_pre_dispatch', [$this, 'fltRestPreDispatch'], 10, 3);

        if ($args['load_filters']) {
            $this->loadFilters();
        }

        if (
        is_multisite() && defined('PRESSPERMIT_ADMIN_QUERY_MS_PREFIX_SAFEGUARD')
        ) {
            add_filter('query', [$this, 'fltQuerySafeguard'], 99999);
        }

		if (presspermit()->isPro()) {
        	add_action('admin_init', [$this, 'loadUpdater']);
        }
        
        add_action('user_has_cap', [$this, 'fltEarlyUserHasCap'], 50, 3);

        // filter pre_option_category_children to disable/enable terms filtering
        foreach (presspermit()->getEnabledTaxonomies(['object_type' => false]) as $taxonomy) {
            add_filter("pre_option_{$taxonomy}_children", [$this, 'fltTermChildren'], 10, 3);
        }

        // Prevent Event Calendar plugin's term hierarchy array from being updated with a filtered subset
        add_filter("pre_update_option_event-categories_children", [$this, 'fltUpdateEventCategoriesChildren'], 10, 2);

        add_action("created_event-categories", function() {
            update_option('presspermit_created_event_category', true);
        });

        if (get_option('presspermit_created_event_category')
        || (is_admin() && !empty($pagenow) && ('edit-tags.php' == $pagenow) && !PWP::empty_REQUEST('taxonomy') && PWP::REQUEST_key_match('taxonomy', 'event-categories'))
        ) {
            add_action('init', [$this, 'actCreatedEventCategory'], 50);
        }


        add_filter(
            'posts_request',
            function ($request) {
                global $wpdb;

                if (strpos($request, "WHERE 1=1 $wpdb->posts")) {
                    $request = str_replace("WHERE 1=1 $wpdb->posts", "WHERE 1=1 AND $wpdb->posts", $request);
                }

                return $request;
            },
            999, 2
        );

        add_filter('presspermit_map_status_caps', [$this, 'fltMapStatusCaps'], 10, 4);
    }

    public function fltMapStatusCaps($caps, $meta_cap, $user_id, $post_id) {
        global $current_user;
        
        if (!empty(presspermit()->flags['cap_filter_in_process'])) {
            return $caps;
        }

        $post_type = get_post_field('post_type', $post_id);

        if ($type_obj = get_post_type_object($post_type)) {
            foreach ($caps as $i => $_cap_name) {
                if (!empty($current_user->allcaps[$_cap_name])) {
                    continue;
                }

                // For post listings, support "list" capabilities as an alternative to custom post status edit capabilities
                if (0 === strpos($_cap_name, 'edit_') 
                && (empty($type_obj->cap->edit_posts) || ($type_obj->cap->edit_posts != $_cap_name))
                && (empty($type_obj->cap->edit_others_posts) || ($type_obj->cap->edit_others_posts != $_cap_name))
                && (empty($type_obj->cap->edit_published_posts) || ($type_obj->cap->edit_published_posts != $_cap_name))
                && (empty($type_obj->cap->edit_private_posts) || ($type_obj->cap->edit_private_posts != $_cap_name))
                ) {
                    if ($user_id == $current_user->ID) {
                        $_cap_name = str_replace('edit_', 'list_', $type_obj->cap->edit_posts);
                    } else {
                        $_cap_name = str_replace('edit_', 'list_', $type_obj->cap->edit_others_posts);
                    }

                    $caps[$i] = $_cap_name;
                    $any_modified = true;
                }
            }

            if (!empty($any_modified)) {
                $caps = array_unique($caps);
            }
        }

        return $caps;
    }

	public function loadUpdater() {
        return presspermit()->load_updater();
    }

    public function filtersLoaded()
    {
        return $this->filters_loaded;
    }

    public function filteringEnabled()
    {
        return $this->filtering_enabled;
    }

    function fltTermChildren($option_val, $option_name, $default_val) {
        if (!empty(presspermit()->flags['disable_term_filtering'])) {
            return $option_val;
        }
        
        if ($pos = strrpos($option_name, '_children')) {
            $taxonomy = substr($option_name, 0, $pos);
        }

        $children = array();
        $terms    = get_terms(
            array(
                'taxonomy'               => $taxonomy,
                'get'                    => 'all',
                'orderby'                => 'id',
                'fields'                 => 'id=>parent',
                'update_term_meta_cache' => false,
            )
        );
        foreach ( $terms as $term_id => $parent ) {
            if ( $parent > 0 ) {
                $children[ $parent ][] = $term_id;
            }
        }

        return $children;
    }

    function fltUpdateEventCategoriesChildren($value, $old_value) {
        if (is_null($old_value) || empty($old_value) 
        || (did_action('presspermit_restore_term_children_cache') && !did_action('presspermit_restored_term_children_cache'))
        ) {
            return $value;
        }

        if (in_array('event-categories', presspermit()->getEnabledTaxonomies(), true)) {
            if ($this->filtering_enabled && empty(presspermit()->flags['disable_term_filtering'])) {
                $value = $old_value;
            }
        }
        
        return $value;
    }

    function actCreatedEventCategory() {
        global $wp_query, $wpdb;

        if (empty($wpdb)) {
            return;
        }

        if (function_exists('_get_term_hierarchy') && taxonomy_exists('event-categories')) {
            if (!$term_children = _get_term_hierarchy('event-categories')) {
                presspermit()->flags['disable_term_filtering'] = true;

                do_action('presspermit_restore_term_children_cache');
                delete_option("event-categories_children");
                $term_children = _get_term_hierarchy('event-categories');
                do_action('presspermit_restored_term_children_cache');

                presspermit()->flags['disable_term_filtering'] = false;

                if ($term_children 
                || !$wpdb->get_col("SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy = 'event-categories' AND parent > 0")   // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                ) {
                    delete_option('presspermit_created_event_category');
                }
            }
        }
    }

    // if Advanced Options are not enabled, ignore stored settings
    public function fltForceAdvancedDefaults($options)
    {
        if (!presspermit()->getOption('advanced_options')) {
            foreach (presspermit()->default_advanced_options as $option_basename => $val) {
                $options["presspermit_{$option_basename}"] = $val;
            }
        }
        return $options;
    }

    public function loadFilters()
    {
        if (apply_filters(
    		'presspermit_early_caps_init',
    		!defined('PRESSPERMIT_NO_EARLY_CAPS_INIT') 
            && (class_exists('ACF') || !defined('CPTUI_VERSION'))
    	)) {
            add_action('init', function() {presspermit()->capDefs(['force' => true]);}, 5);
        }

        add_action('set_current_user', [$this, 'actSetCurrentUser'], 99);
        add_action('init', [$this, 'actInit'], 50);
        add_action('wp_loaded', [presspermit(), 'refreshUserAllcaps'], 18);   // account for any type / condition caps adding by late registration

        if (!class_exists('\PressShack\LibArray')) {
            require_once(PRESSPERMIT_CLASSPATH_COMMON . '/LibArray.php');
            class_alias('\PressShack\LibArray', '\PublishPress\Arr');
            class_alias('\PressShack\LibArray', '\PublishPress\Permissions\Arr');
            class_alias('\PressShack\LibArray', '\PublishPress\Permissions\DB\Arr');
            class_alias('\PressShack\LibArray', '\PublishPress\Permissions\UI\Arr');
            class_alias('\PressShack\LibArray', '\PublishPress\Permissions\UI\Dashboard\Arr');
        
            require_once(PRESSPERMIT_CLASSPATH_COMMON . '/LibWP.php');
            class_alias('\PressShack\LibWP', '\PublishPress\PWP');
            class_alias('\PressShack\LibWP', '\PublishPress\Permissions\PWP');
            class_alias('\PressShack\LibWP', '\PublishPress\Permissions\DB\PWP');
            class_alias('\PressShack\LibWP', '\PublishPress\Permissions\UI\PWP');
            class_alias('\PressShack\LibWP', '\PublishPress\Permissions\UI\Dashboard\PWP');
            class_alias('\PressShack\LibWP', '\PublishPress\Permissions\UI\Handlers\PWP');
        }

        if (is_admin()) {
            require_once(PRESSPERMIT_ABSPATH . '/classes/PublishPress/PermissionsHooksAdmin.php');
            $this->admin_hooks = new PermissionsHooksAdmin();
        } else {
            add_action('presspermit_pre_init', [$this, 'actMediaFilters']);
        }

        require_once(PRESSPERMIT_CLASSPATH . '/Roles.php');
        presspermit()->role_defs = new Permissions\Roles();

        if (defined('SSEO_VERSION') && function_exists('sseo_register_parameter')) {
            require_once(PRESSPERMIT_CLASSPATH . '/Compat/EyesOnly.php');
            new Permissions\Compat\EyesOnly();
        }

        if (did_action('set_current_user')) { // sometimes third party code causes user to be loaded prematurely
            $this->actSetCurrentUser();
        }

        if (defined('PRESSPERMIT_NO_USER_LOCALE')) {
            // Prevent numerous user queries with Block Editor
            add_filter('pre_determine_locale', function($locale) {return get_locale();});
        }
    }

    // log request and handler parameters for possible reference by subsequent PP filters; block unpermitted create/edit/delete requests 
    function fltRestPreDispatch($rest_response, $rest_server, $request)
    {
        require_once(PRESSPERMIT_CLASSPATH . '/REST.php');
        return Permissions\REST::instance()->pre_dispatch($rest_response, $rest_server, $request);
    }

    public function actSwitchBlog()
    {
        require(PRESSPERMIT_ABSPATH . '/db-config.php');
    }

    public function actMediaFilters()
    {
        if (in_array('attachment', presspermit()->getEnabledPostTypes(), true)) {
            require_once(PRESSPERMIT_CLASSPATH . '/MediaFilters.php');
            new Permissions\MediaFilters();
        }
    }

    public function actSetCurrentUser()
    {
        global $current_user;

        if (presspermit()->checkInitInterrupt()) {
            return;
        }

        presspermit()->setUser($current_user->ID);

        if (isset($this->cap_filters)) {
            $this->cap_filters->clearMemcache();
        }

        if (did_action('init')) {
            // reload certain filters and configuration data on user change
            $this->actInitUser();
        } else {
            // late priority because actInit() and 3rd party filters related to type / taxonomy / cap definitions must execute first
            add_action('init', [$this, 'actNormalInitUser'], 70);
        }
    }

    public function fltEarlyUserHasCap($wp_sitecaps, $orig_reqd_caps, $args)
    {
        // Deal with plugins (WP Bakery Page Builder) that apply an 'edit_post' metacap check on the init action
        if (isset($args[0]) && in_array($args[0], ['edit_post']) && !did_action('presspermit_init_user_complete')) {
            $this->actInitUser();
        }

        return $wp_sitecaps;
    }

    public function actNormalInitUser() {
        $this->actInitUser();
        do_action('presspermit_init_user_complete');
    }

    // executes late on the 'init' action (priority 50)
    public function actInit()
    {
        static $done = null;

        if (!is_null($done)) {
            return;
        }
        $done = true;

        // @todo: determine cause of this condition
        if (is_admin() && empty($this->admin_hooks)) {
            static $busy;

            if (empty($busy)) {
                $this->loadFilters();
            }

            $busy = true;
        }

        $pp = presspermit();

        // --- version check ---
        if (!$ver = get_option('presspermit_version')) {
            $ver = get_option('pp_c_version');
        }

        $updated = false;

        $prev_core_version = ($ver && is_array($ver) && !empty($ver['version'])) ? $ver['version'] : '';

        if (version_compare(PRESSPERMIT_VERSION, $prev_core_version, '!=')) {
            require_once(PRESSPERMIT_CLASSPATH . '/PluginUpdated.php');
            new Permissions\PluginUpdated($prev_core_version);

            // Always store current PP Core version, even if loaded by Pro
            update_option('presspermit_version', ['version' => PRESSPERMIT_VERSION, 'db_version' => PRESSPERMIT_DB_VERSION]);
            $updated = true;

            if ($ver && is_multisite() && !$pp->getOption('wp_role_sync')) {
                Permissions\PluginUpdated::syncWordPressRoles();
            }
        }

        if (defined('PRESSPERMIT_PRO_VERSION')) {
            $ver_pro = get_option('presspermitpro_version');
            $prev_pro_version = ($ver_pro && is_array($ver_pro) && !empty($ver_pro['version'])) ? $ver_pro['version'] : '';

            if (version_compare(PRESSPERMIT_PRO_VERSION, $prev_pro_version, '!=')) {
                do_action('presspermit_pro_version_updated', $prev_pro_version);

                update_option('presspermitpro_version', ['version' => PRESSPERMIT_PRO_VERSION, 'db_version' => PRESSPERMIT_DB_VERSION]);
                $updated = true;
            }
        }

        if (!empty($updated)) {
            // Core and Pro intentionally share the same version history log, to capture the installation sequence of any Free or Pro package

            if ($ver_history = get_option('ppperm_version_history')) {
                $ver_history = (array) json_decode($ver_history);
            } else {
                // Initiate version history log with the last stored version (Pro or Free)
                $ver_history = [];

                if (defined('PRESSPERMIT_PRO_VERSION') && !empty($prev_pro_version)) {
                    $new_entry = (object) ['version' => $prev_pro_version, 'date' => '', 'isPro' => true];
                } 
                
                // In case last Pro version is an irrelevant old entry, also include last Core version if it's higher
                if ($prev_core_version && (!defined('PRESSPERMIT_PRO_VERSION') || version_compare($prev_core_version, $prev_pro_version, '>'))) {
                    $new_entry = (object) ['version' => $prev_core_version, 'date' => '', 'isPro' => false];
                }

                $last_entry = reset($ver_history);

                if (!empty($new_entry) && ($last_entry != $new_entry)) {
                    $ver_history []= $new_entry;
                    $updated_log = true;
                }
            }

            // In the version history, log Core version changes only if they are installed directly by Free package
            if (defined('PRESSPERMIT_PRO_VERSION')) {
                $new_entry = (object) ['version' => PRESSPERMIT_PRO_VERSION, 'date' => gmdate('m/d/Y'), 'isPro' => true];
            } else {
                $new_entry = (object) ['version' => PRESSPERMIT_VERSION, 'date' => gmdate('m/d/Y'), 'isPro' => false];
            }

            if (count($ver_history) > 1000) {
                $ver_history = [];
            }

            $last_entry = reset($ver_history);

            if ($last_entry != $new_entry) {
                $ver_history []= $new_entry;
                $updated_log = true;
            }

            if (!empty($updated_log)) {
                update_option('ppperm_version_history', wp_json_encode($ver_history));
            }
        }
        // --- end version check ---

        // already loaded these early, so apply filter again for modules
        $pp->default_options = apply_filters('presspermit_default_options', $pp->default_options);
        $pp->default_advanced_options = apply_filters('presspermit_default_advanced_options', $pp->default_advanced_options);
        $pp->default_options = array_merge($pp->default_options, $pp->default_advanced_options);

        $pp->site_options = apply_filters('presspermit_options', $pp->site_options);

        if (is_multisite() && PWP::isNetworkActivated()) {
            $opts = ['edd_key', 'beta_updates'];
            $pp->netwide_options = apply_filters('presspermit_netwide_options', $opts);
        }

        // Capabilities() instantiation forces type-specific cap names for enabled post types and taxonomies
        $pp->capDefs();

        do_action('presspermit_pre_init');

        if (is_admin()) {
            @load_plugin_textdomain('press-permit-core', false, dirname(plugin_basename(PRESSPERMIT_FILE)) . '/languages');

            $this->admin_hooks->init();
        }
    }

    // Load filters that depend on user capabilities. Executes very late on the 'init' action (priority 70)
    public function actInitUser()
    {
        $pp = presspermit();

        if (!did_action('init') || !$pp->isUserSet()) {
            return;
        }

        // determine if query filtering has been disabled by option storage or API
        if (($pp->isUserUnfiltered() && !is_user_logged_in()) || (defined('DOING_CRON') && PWP::doingCron())) {
            $this->filtering_enabled = false;
        }

        // Don't filter legacy / development versions of REST api unless constant defined
        if (
            defined('JSON_API_VERSION') && !defined('PP_FILTER_JSON_REST')
            && isset($_SERVER['REQUEST_URI'])
            && (false !== strpos(esc_url_raw($_SERVER['REQUEST_URI']), apply_filters('json_url_prefix', 'wp-json')))
        ) {
            return;
        }

        if (!$this->filters_loaded) {
            // Normal execution: loadInitFilters() will call loadContentFilters()
            $this->loadInitFilters();
        } else {
            // Support content filter reload on user change, without reloading filters that are loaded regardless of user capabilities
            $this->loadContentFilters();
        }

        // retrieve BP groups and other group types registered by 3rd party  todo: default retrieve_site_roles arg to false?
        $pp_user = $pp->getUser(false, '', ['retrieve_site_roles' => false]);
        $pp_user->retrieveExtraGroups();
        $pp_user->getSiteRoles();
        
        $pp->refreshUserAllcaps();

        do_action('presspermit_user_init');
    }

    private function loadInitFilters()
    {
        global $pagenow;

        do_action('presspermit_register_role_attributes');

        presspermit()->role_defs->defineRoles();

        do_action('presspermit_roles_defined');

        // determine if query filtering has been disabled by option storage or API
        if (defined('DOING_CRON') && PWP::doingCron()) {
            $this->filtering_enabled = false;
        }

        if ($no_filter_uris = apply_filters('presspermit_nofilter_uris', [])) {
            if (in_array($pagenow, $no_filter_uris, true) || in_array(presspermitPluginPage(), (array)$no_filter_uris, true)) {
                $this->filtering_enabled = false;
            }
        }

        if (did_action('presspermit_load_error') && defined('PRESSPERMIT_DISABLE_QUERYFILTERS')) {
            $this->filtering_enabled = false;
        }

        if (!$this->direct_file_access = PWP::is_REQUEST('pp_rewrite') && !PWP::empty_REQUEST('attachment')) {
            $this->addMaintenanceTriggers();
        }

        $this->filters_loaded = true;

        // no further filtering on update requests for other plugins 
        if (is_admin() && ('update.php' == $pagenow)) {
            // todo: review with EDD

            if (!PWP::is_REQUEST('action', 'presspermit-pro')) {
                do_action('presspermit_init');
                return;
            }
        }

        // content filters, loaded conditionally depending on whether the current user is a content administrator
        $this->loadContentFilters();

        if (is_admin() && ('async-upload.php' != $pagenow) && !defined('XMLRPC_REQUEST') 
        && (!defined('DOING_AJAX') || !DOING_AJAX || PWP::is_REQUEST('action', ['menu-get-metabox', 'menu-quick-search']))
        ) {
            // filters which are only needed for the wp-admin UI
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/DashboardFilters.php');
            new Permissions\UI\Dashboard\DashboardFilters();
        }

        add_filter('the_posts', [$this, 'fltPostsListing'], 50);
        add_action('admin_enqueue_scripts', [$this, 'fltAdminPostsListing'], 50);  // 'the_posts' filter is not applied on edit.php for hierarchical types

        if (defined('PP_LEGACY_PAGE_URI_FILTER')) {
            add_filter('get_page_uri', [$this, 'fltGetPageUri'], 5, 2);
        }

        do_action('presspermit_init');
    }

    public function addMaintenanceTriggers()
    {
        // ===== Filters which support automated role maintenance following content creation/update
        if (!defined('PP_NO_FRONTEND_ADMIN') || !PWP::isFront()) {  // advanced users can save some memory if no content/users will be edited via front end
            require_once(PRESSPERMIT_CLASSPATH . '/Triggers.php');
            new Permissions\Triggers();

            do_action('presspermit_maintenance_triggers');
        }
    }

    public function fltPostsListing($results)
    {
        $pp = presspermit();

        $default_type = PWP::findPostType();

        // buffer all IDs in the results set
        if ($results) { // JReviews plugin sets $results to null under some conditions
            foreach ($results as $row) {
                $post_type = (!isset($row->post_type) || ('revision' == $row->post_type)) ? $default_type : $row->post_type;
                $pp->listed_ids[$post_type][$row->ID] = true;
            }
        }
        
        return $results;
    }

    public function fltAdminPostsListing() {
		global $wp_query, $typenow;
		
		if ( ! empty( $wp_query->posts ) && empty( presspermit()->listed_ids[$typenow] ) ) {
			$this->fltPostsListing( $wp_query->posts );
		}
	}

    // restore pre-4.4 behavior of not requiring 'publish' status for inclusion in page uri hierarchy
    public function fltGetPageUri($uri, $page)
    {
        $page = get_post($page);

        if (!$page)
            return false;

        $uri = $page->post_name;

        foreach ($page->ancestors as $parent) {
            if ($_post = get_post($parent)) {
                $uri = $_post->post_name . '/' . $uri;
            }
        }

        return $uri;
    }

    // configuration / filter addition which depends on whether the current user is an Administrator
    private function loadContentFilters()
    {
        if (defined('DOING_AJAX') && DOING_AJAX 
        && PWP::is_REQUEST('action', ['woocommerce_load_variations', 'woocommerce_add_variation', 'woocommerce_remove_variations', 'woocommerce_save_variations'])
        ) {
			return;
		}

        $pp = presspermit();

        if (!$pp->filteringEnabled()) {
            return;
        }

        // ===== Query Filters to limit/enable the current user
        global $pagenow;

        $is_unfiltered = $pp->isUserUnfiltered();
        $is_administrator = $pp->isContentAdministrator();

        // even users who are unfiltered in terms of their own access will normally have some of these filters applied to force inclusion of readable private posts in get_pages() listing, post counts, etc.
        if ($is_front = PWP::isFront()) {
            $front_filtering = !$is_unfiltered || !defined('PP_ALLOW_UNFILTERED_FRONT');
        }

        // (also use content filters on front end to FILTER IN private content which WP inappropriately hides from administrators)
        if (($is_front && $front_filtering) 
        || !$is_unfiltered 
        || ('nav-menus.php' == $pagenow) 
        || (defined('DOING_AJAX') && DOING_AJAX && PWP::is_REQUEST('action', ['menu-get-metabox', 'menu-quick-search']))
        ) {
            if (! $this->post_filters_loaded) { // since this could possibly fire on multiple 'set_current_user' calls, avoid redundancy
                require_once(PRESSPERMIT_CLASSPATH . '/PostFilters.php');
                Permissions\PostFilters::instance(['direct_file_access' => $this->direct_file_access]);
                $this->post_filters_loaded = true;
            }
        }

        if ($is_front && $front_filtering) {
            require_once(PRESSPERMIT_CLASSPATH . '/PostFiltersFront.php');
            new Permissions\PostFiltersFront();

            require_once(PRESSPERMIT_CLASSPATH . '/FrontFilters.php');
            new Permissions\FrontFilters();

            if ($is_unfiltered && $is_administrator) {
                require_once(PRESSPERMIT_CLASSPATH . '/CommentFiltersAdministrator.php');
                new Permissions\CommentFiltersAdministrator();
            }
        }

        if (!$is_unfiltered) {
            if (!isset($this->cap_filters)) {
                require_once(PRESSPERMIT_CLASSPATH . '/CapabilityFilters.php');
                $this->cap_filters = new Permissions\CapabilityFilters();
            }

            require_once(PRESSPERMIT_CLASSPATH . '/CommentFilters.php');
            new Permissions\CommentFilters();

            // Legacy: This has never been referenced internally or by any extensions, but leave in case a custom implmentation looks at it.
            if (!defined('FILTERED_PP')) {
                define('FILTERED_PP', true);
            }
        }

        if (($is_front && $front_filtering) || (!$is_unfiltered && (!defined('DOING_AUTOSAVE') || !DOING_AUTOSAVE))) {
            // Work around unexplained issue with access to static methods of LibWP class failing if called before init action
            if ((did_action('init') || defined('PRESSPERMIT_TERM_FILTERS_LEGACY_LOAD')) 
            && !presspermit()->getOption('limit_front_end_term_filtering')
            ) {
                require_once(PRESSPERMIT_CLASSPATH . '/TermFilters.php');
                new Permissions\TermFilters();
            } else {
                add_action('init',
                    function() {
                        require_once(PRESSPERMIT_CLASSPATH . '/TermFilters.php');
                        new Permissions\TermFilters();
                    }
                );
            }
        } elseif (is_admin() && $is_unfiltered) {
            require_once(PRESSPERMIT_CLASSPATH . '/TermFiltersAdministrator.php');  // for filtering of post count
            new Permissions\TermFiltersAdministrator();
        }

        if (
            !$this->direct_file_access && (!$is_front || $front_filtering)
            && (!defined('XMLRPC_REQUEST') || !$is_administrator)
        ) {  // don't add for direct file access or administrator XML-RPC
            if (!is_admin() || !$pp->isContentAdministrator() || !defined('PP_GET_PAGES_LIMIT_ADMIN_FILTERING')) {
                $priority = (defined('PP_GET_PAGES_PRIORITY')) ? PP_GET_PAGES_PRIORITY : 1;
                add_filter('get_pages', [$this, 'fltGetPages'], $priority, 2);
            }
        }
    }

    public function fltGetPages($pages, $args)
    {
        if (!isset($args['post_type'])) {
            return $pages;
        }

        require_once(PRESSPERMIT_CLASSPATH . '/PageFilters.php');
        do_action('presspermit_page_filters');

        return Permissions\PageFilters::fltGetPages($pages, $args);
    }

    public function actClearTermChildrenCache($children, $option_val, $option_name)
    {  // fires on pre_update_option_$taxonomy filter
        if (defined('DOING_AJAX') && DOING_AJAX) {
            delete_option($option_name);
        }
    }

    // Sanity check for posts_clauses query on Multisite: force posts table name in WHERE, ORDERBY clause to match name in SELECT fields
    public function fltQuerySafeguard($query)
    {
        global $wpdb;

        $matches = [];

        // @todo: require an Advanced setting to be enabled
		
        if (preg_match('/FROM[\s\r\n]+' . $wpdb->base_prefix . '([^\s\r\n]*)posts/', $query, $matches)) {
            $posts_table = $wpdb->base_prefix . $matches[1] . 'posts';

            // Bypass the safeguard if join clause includes any other posts table
            if (defined('ADMIN_QUERY_SAFEGUARD_JOIN_PRECAUTION') && !preg_match("/JOIN (?!{$posts_table})(" . $wpdb->base_prefix . '[^\s\r\n]*posts)/', $query)) {
                $query = preg_replace("/$wpdb->base_prefix([^\s\r\n]*)posts\./", $posts_table . '.', $query);
            }
        }

        return $query;
    }

} // end class

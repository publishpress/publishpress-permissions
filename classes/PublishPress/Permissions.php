<?php

namespace PublishPress;

use PublishPress\Permissions\Factory;

/**
 * Main PressPermit class for plugin initialization and configuration routing
 * 
 * Singleton object returned by presspermit()
 * 
 * Initiates filter application by instantiating PublishPress\PermissionsHooks
 * Also provides some commonly used wrapper methods
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2024, PublishPress
 *
 */
class Permissions
{
    // object references
    private static $instance = null;
    private $hooks;
    private $groups;
    public $cap_defs;
    public $role_defs;
    private $cap_caster; // access with method getCapCaster()
    private $cap_filters;
    private $post_filters;
    private $admin;

    // plugin configuration
    private $modules = [];
    private $min_module_version = [];
    public $default_options = [];
    public $default_advanced_options = [];
    public $netwide_options = [];
    public $site_options = [];
    public $net_options = [];

    // status / memcache
    private $current_user;
    public $doing_rest = false;
    public $flags = [];
    public $listed_ids = [];               // $listed_ids[object_type][object_id] = true : avoid separate capability query for each listed item
    public $meta_cap_post = false;
    public $doing_cap_check = false;
    private $sanitizing_post_id = false;
    private $inserted_posts = [];

    public static function instance($args = [])
    {
        if (is_null(self::$instance)) {
            $defaults = ['load_filters' => true];
            $args = array_merge($defaults, (array)$args);
            self::$instance = new Permissions($args);
            self::$instance->load();
        }

        return self::$instance;
    }

    public function getCurrentSanitizePostID()
    {
        if (defined('PRESSPERMIT_LEGACY_POST_ID_DETECT')) {
            return 0;
        }

        if (!empty($this->sanitizing_post_id)) {
            $post_id = $this->sanitizing_post_id;
        }

        return 0;
    }

    private function __construct()
    {
        global $pagenow;
        add_filter('presspermit_unfiltered_content', [$this, 'fltPluginCompatUnfilteredContent'], 5, 1);

        // Log the post ID field for the sanitize_post() call by wp_insert_post(), 
        // to provide context for subsequent pre_post_status, pre_post_parent, pre_post_category, pre_post_tags_input filter applications
        add_filter(
            'pre_post_ID',
            function ($post_id) {
                $this->sanitizing_post_id = $post_id;
                return $post_id;
            }
        );

        // Use the next filter called by wp_insert_post() too mark the end of sanitize_text_field() calls for this post
        add_filter(
            'wp_insert_post_empty_content',
            function ($maybe_empty, $postarr) {
                if ($this->sanitizing_post_id && !empty($postarr['ID']) && ($postarr['ID'] == $this->sanitizing_post_id)) {
                    $this->sanitizing_post_id = false;
                }

                return $maybe_empty;
            },
            1,
            2
        );

        add_action(
            'wp_insert_post',
            function ($post_id, $post, $update) {
                if (empty($update)) {
                    $this->inserted_posts[$post_id] = true;
                }
            },
            10,
            3
        );

        if (in_array($pagenow, ['term.php'])) {
            add_filter('gettext', [$this, 'flt_edit_tag'], 99, 3);
        }
    }

    public function isInsertedPost($post_id)
    {
        return !empty($this->inserted_posts[$post_id]);
    }

    public function capDefs($args = [])
    {
        if (!isset($this->cap_defs) || !empty($args['force'])) {
            require_once(PRESSPERMIT_CLASSPATH . '/Capabilities.php');
            $this->cap_defs = new Permissions\Capabilities();
        }

        return $this->cap_defs;
    }

    public static function doingREST()
    {
        return self::instance()->doing_rest;
    }

    public function doingEmbed()
    {
        static $arr_url;

        if (!isset($_SERVER['REQUEST_URI'])) {
            return false;
        }

        if (!isset($arr_url)) {
            $arr_url = wp_parse_url(get_option('siteurl'));
        }

        if ($arr_url) {
            $path = isset($arr_url['path']) ? $arr_url['path'] : '';

            if (0 === strpos(esc_url_raw($_SERVER['REQUEST_URI']), $path . '/wp-json/oembed/')) {
                return true;
            }
        }

        return false;
    }

    public function isRESTurl()
    {
        static $arr_url;

        if (!isset($_SERVER['REQUEST_URI'])) {
            return false;
        }

        if (!isset($arr_url)) {
            $arr_url = wp_parse_url(get_option('siteurl'));
        }

        if ($arr_url) {
            $path = isset($arr_url['path']) ? $arr_url['path'] : '';

            if (0 === strpos(esc_url_raw($_SERVER['REQUEST_URI']), $path . '/wp-json/oembed/')) {
                return false;
            }

            if (0 === strpos(esc_url_raw($_SERVER['REQUEST_URI']), $path . '/wp-json/')) {
                return true;
            }
        }

        return false;
    }

    public function checkInitInterrupt()
    {
        // Image Source Control plugin compat
        if (defined('ISCVERSION') || defined('PRESSPERMIT_LIMIT_ASYNC_UPLOAD_FILTERING')) {
            if (is_admin() && isset($_SERVER['SCRIPT_NAME']) && strpos(sanitize_text_field($_SERVER['SCRIPT_NAME']), 'async-upload.php') && !PWP::empty_POST('attachment_id') && PWP::is_POST('fetch', 3)) {
                if ($att = get_post(PWP::POST_int('attachment_id'))) {
                    global $current_user;
                    if ($att->post_author == $current_user->ID && ! defined('PP_UPLOADS_FORCE_FILTERING')) {
                        return true;
                    }
                }
            }
        }

        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        // Divi Page Builder editor init
        if (
            !defined('PRESSPERMIT_DISABLE_DIVI_CLEARANCE') && !PWP::empty_REQUEST('et_fb') && !PWP::empty_REQUEST('et_bfb')
            && 0 === strpos(esc_url_raw($_SERVER['REQUEST_URI']), '/?page_id')
            && !is_admin() && !defined('DOING_AJAX') && PWP::empty_REQUEST('action')
            && PWP::empty_REQUEST('post') && PWP::empty_REQUEST('post_id') && PWP::empty_REQUEST('post_ID') && PWP::empty_REQUEST('p')
        ) {
            return true;
        }
    }

    private function load($args = [])
    {
        if ($this->checkInitInterrupt()) {
            return;
        }

        $this->dbMaint();

        $defaults = ['load_filters' => true];
        $args = array_merge($defaults, (array)$args);

        $this->default_options = [
            'enabled_taxonomies' => ['category' => true, 'post_tag' => true],
            'enabled_post_types' => array_fill_keys(['post', 'page'], true),
            'define_media_post_caps' => 0,
            'define_create_posts_cap' => 0,
            'strip_private_caption' => 1,
            'force_nav_menu_filter' => 0,
            'display_user_profile_groups' => 0,
            'display_user_profile_roles' => 0,
            'new_user_groups_ui' => 1,
            'beta_updates' => false,        // todo: EDD integration, or eliminate
            'admin_hide_uneditable_posts' => 1,
            'post_blockage_priority' => 1,
            'media_search_results' => 1,
            'term_counts_unfiltered' => 0,
            'advanced_options' => 0,
            'delete_settings_on_uninstall' => 0,
            'edd_key' => false,
            'supplemental_role_defs' => [], // stored by Capability Manager Enhanced
            'customized_roles' => [],       // stored by Capability Manager Enhanced
            'pattern_roles_include_generic_rolecaps' => 0, // This is exposed on the Advanced tab, but intentionally excluded from the default_advanced_options array
            'regulate_category_archive_page' => 0,
        ];

        // need these keyed in separate array to force defaults if advanced options are disabled
        $this->default_advanced_options = [
            'display_hints' => 1,
            'force_display_hints' => 1,
            'display_extension_hints' => 1,
            'dynamic_wp_roles' => 0,
            'non_admins_set_read_exceptions' => 1,
            'user_search_by_role' => 0,
            'anonymous_unfiltered' => 0,
            'users_bulk_groups' => 1,
            'limit_front_end_term_filtering' => 0,
            'list_all_constants' => 0,
        ];
        $this->default_advanced_options = apply_filters('presspermit_default_advanced_options', $this->default_advanced_options);

        $this->default_options = array_merge($this->default_options, $this->default_advanced_options);

        $this->min_module_version = [ // retain ability to activate modules externally, but block old versions (which won't work)
            'pp-buddypress-role-groups' => '2.7-beta',
            'circles' => '2.7-beta',
            'collaboration' => '2.7-beta',
            'compatibility' => '2.7-beta',
            'teaser' => '2.7-beta',
            'file-access' => '2.7-beta',
            'import' => '2.7-beta',
            'membership' => '2.7-beta',
            'pp-for-wpml' => '2.7-beta',
        ];

        $this->refreshOptions();  // retrieve stored options
        $this->default_options = apply_filters('presspermit_default_options', $this->default_options);

        if (is_multisite() && PWP::isNetworkActivated()) {
            $this->netwide_options = apply_filters('presspermit_netwide_options', ['edd_key', 'beta_updates']);
        }

        // Don't call translate function too early - call from getGroupObject() instead
        $this->groups()->registerGroupType(
            'pp_group',
            is_admin() ? ['labels' => (object)['name' => 'Groups', 'singular_name' => 'group']] : []
        );

        if (!defined('PRESSPERMIT_MIN_DATE_STRING')) {
            global $wpdb;

            if ($wpdb && method_exists($wpdb, 'db_edition') && empty($wpdb->use_mysqli)) {
                // Project Nami compat
                define('PRESSPERMIT_MIN_DATE_STRING', '1970-01-01 00:00:01');
            } else {
                define('PRESSPERMIT_MIN_DATE_STRING', '0000-00-00 00:00:00');
            }
        }

        if (!defined('PRESSPERMIT_MAX_DATE_STRING')) {
            define('PRESSPERMIT_MAX_DATE_STRING', '2035-01-01 00:00:00');
        }

        $this->loadModules();

        require_once(PRESSPERMIT_ABSPATH . '/classes/PublishPress/PermissionsHooks.php');
        $this->hooks = new PermissionsHooks($args);

        if (is_admin()) {
            $this->admin();
        }
    }

    private function dbMaint()
    {
        // On first-time installation and version change, early assurance that DB tables are present and role capabilities populated
        if (! $ver = get_option('presspermitpro_version')) {
            if (! $ver = get_option('presspermit_version')) {
                $check_for_rs_migration = true;

                $ver = get_option('pp_c_version');
            }
        }

        if (!$ver || !is_array($ver) || empty($ver['db_version']) || version_compare(PRESSPERMIT_DB_VERSION, $ver['db_version'], '!=')) {
            require_once(PRESSPERMIT_ABSPATH . '/db-config.php');

            $db_ver = (is_array($ver) && isset($ver['db_version'])) ? $ver['db_version'] : '';
            require_once(PRESSPERMIT_CLASSPATH . '/DB/DatabaseSetup.php');
            new Permissions\DB\DatabaseSetup($db_ver);
        }

        if ($ver) {
            if ($role = @get_role('administrator')) {
                if (empty($role->capabilities['pp_manage_settings'])) {
                    $ver = false; // repopulate roles if Administrator lacks pp_manage_settings capability
                }
            }
        }

        if (!$ver) {
            // first execution after install

            // Always force this capability into Administrator role
            if ($role = @get_role('administrator')) {
                $role->add_cap('pp_manage_settings');
            }

            if (!get_option('ppperm_added_role_caps_10beta')) {
                require_once(PRESSPERMIT_CLASSPATH . '/PluginUpdated.php');
                Permissions\PluginUpdated::populateRoles();
            }

            // sanity check, in case activation function misses
            if (!get_option('presspermit_wp_role_sync')) {
                require_once(PRESSPERMIT_CLASSPATH . '/PluginUpdated.php');
                \PublishPress\Permissions\PluginUpdated::syncWordPressRoles();
            }
        }
    }

    public function loadModules()
    {
        $inactive_modules = (array) $this->getOption('deactivated_modules');

        $dir = PRESSPERMIT_ABSPATH . '/modules/';

        $available_modules = $this->getAvailableModules();

        foreach ($available_modules as $module) {
            if (empty($inactive_modules[$module]) && file_exists("$dir/$module/$module.php")) {
                include_once("$dir/$module/$module.php");
            }
        }

        do_action('presspermit_load_modules', compact('available_modules', 'inactive_modules'));
    }

    public function getAvailableModules($args = [])
    {
        $modules = [
            'presspermit-circles',
            'presspermit-collaboration',
            'presspermit-compatibility',
            'presspermit-file-access',
            'presspermit-membership',
            'presspermit-sync',
            'presspermit-teaser',
        ];

        return (!empty($args['force_all'])) ? $modules : array_diff($modules, apply_filters('presspermit_unavailable_modules', []));
    }

    public function getSkippedModules()
    {
        $modules = [];

        return $modules;
    }

    public function moduleExists($slug)
    {
        return in_array($slug, $this->getAvailableModules());
    }

    public function getDeactivatedModules()
    {
        $modules = (array) $this->getOption('deactivated_modules');
        return array_intersect_key($modules, array_fill_keys($this->getAvailableModules(), true));
    }

    public function getActiveModules()
    {
        $available = array_map(
            function ($k) {
                return str_replace('presspermit-', '', $k);
            },
            $this->getAvailableModules()
        );

        return array_intersect_key($this->modules, array_fill_keys($available, true));
    }

    public function getAllModules()
    {
        $modules = array_merge($this->getActiveModules(), $this->getDeactivatedModules());
        ksort($modules);
        return $modules;
    }

    public function admin()
    {
        if (!isset($this->admin)) {
            require_once(PRESSPERMIT_CLASSPATH . '/Admin.php');
            $this->admin = new Permissions\Admin();
        }

        return $this->admin;
    }

    public function groups()
    {
        if (!isset($this->groups)) {
            require_once(PRESSPERMIT_CLASSPATH . '/Groups.php');
            $this->groups = new Permissions\Groups();
        }

        return $this->groups;
    }

    public function capCaster()
    {
        if (!isset($this->cap_caster)) {
            require_once(PRESSPERMIT_CLASSPATH . '/CapabilityCaster.php');
            $this->cap_caster = new Permissions\CapabilityCaster();
        }

        return $this->cap_caster;
    }

    public function getUser($user_id = false, $name = '', $args = [])
    {
        if (($user_id === false) && ! empty($this->current_user)) {
            return $this->current_user;
        } else {
            require_once(PRESSPERMIT_ABSPATH . '/classes/PublishPress/PermissionsUser.php');
            return new PermissionsUser($user_id, $name, $args);
        }
    }

    public function setUser($user_id = 0, $name = '', $args = [])
    {
        $this->current_user = $this->getUser($user_id, $name, $args);
        return $this->current_user;
    }

    public function isUserSet()
    {
        return !empty($this->current_user);
    }

    public function clearMemcache()
    {
        if (isset($this->hooks->cap_filters)) {
            $this->hooks->cap_filters->clearMemcache();
        }
    }

    public function filteringEnabled()
    {
        return $this->hooks->filteringEnabled();
    }

    public function isDirectFileAccess()
    {
        return $this->hooks->direct_file_access;
    }

    public function clearDirectFileAccess()
    {
        $this->hooks->direct_file_access = false;
    }

    public function refreshUserAllcaps()
    {
        global $current_user;

        // todo: review (Add New Media)
        if (empty($current_user) || ! isset($this->cap_defs)) {
            return;
        }

        $this->supplementUserAllcaps($this->current_user);
        $current_user->allcaps = array_merge($current_user->allcaps, $this->current_user->allcaps);  // copies above changes and any 3rd party filtering
    }

    public function supplementUserAllcaps(&$user)
    {
        global $pagenow;

        if ($this->isContentAdministrator()) {
            // give content administrators (users with pp_administer_content capability in WP role) all PP-defined caps and type-specific post caps
            $user->allcaps = apply_filters('presspermit_administrator_caps', array_merge($user->allcaps, $this->cap_defs->all_type_caps));
        } else {
            if (!$user->ID) {
                $user->allcaps = array_merge($user->allcaps, array_fill_keys($this->role_defs->anon_user_caps, true));
            } else {
                global $wp_roles, $wp_post_types, $wp_post_statuses;

                // Avoid redundant execution if no late changes were made to roles, capabilities, types or statuses 
                if (!defined('PRESSPERMIT_STATUSES_VERSION')) { // Status Control module causes late registration of statuses
                    $allcaps_hash = md5(wp_json_encode($user->allcaps));
                    $site_roles_hash = md5(wp_json_encode(array_keys($user->site_roles)));
                    $wp_roles_hash = md5(wp_json_encode(array_keys($wp_roles->role_objects)));
                    $post_types_hash = md5(wp_json_encode(array_keys($wp_post_types)));
                    $post_statuses_hash = md5(wp_json_encode(array_keys($wp_post_statuses)));

                    static $last_allcaps_hash = null;
                    static $last_site_roles_hash = null;
                    static $last_wp_roles_hash = null;
                    static $last_post_types_hash = null;
                    static $last_post_statuses_hash = null;

                    if (!is_null($last_allcaps_hash)) {
                        if (
                            ($last_post_statuses_hash == $post_statuses_hash)
                            && ($last_post_types_hash == $post_types_hash)
                            && ($last_allcaps_hash == $allcaps_hash)
                            && ($last_site_roles_hash == $site_roles_hash)
                            && ($last_wp_roles_hash == $wp_roles_hash)
                        ) {
                            return;
                        }
                    }

                    $last_allcaps_hash = $allcaps_hash;
                    $last_site_roles_hash = $site_roles_hash;
                    $last_wp_roles_hash = $wp_roles_hash;
                    $last_post_types_hash = $post_types_hash;
                    $last_post_statuses_hash = $post_statuses_hash;
                }

                // merge in caps from supplemental direct role assignments
                foreach (array_keys($user->site_roles) as $role_name) {
                    if (isset($wp_roles->role_objects[$role_name])) {
                        $user->allcaps = array_merge($user->allcaps, $wp_roles->role_objects[$role_name]->capabilities);
                    } elseif (!strpos($role_name, ':')) {
                        $caps = apply_filters('presspermit_role_caps', [], $role_name);
                        $user->allcaps = array_merge($user->allcaps, array_fill_keys($caps, true));
                    }
                }

                if (
                    (
                        (is_multisite() && !is_user_member_of_blog())
                        || (!is_admin() && !defined('PRESSPERMIT_STRICT_READ_CAP'))
                    )
                ) {
                    $user->allcaps[PRESSPERMIT_READ_PUBLIC_CAP] = true;
                }

                if ($this->getOption('list_others_uneditable_posts')) {
                    foreach ($this->getEnabledPostTypes() as $post_type) {
                        if ($type_obj = get_post_type_object($post_type)) {
                            if (
                                isset($type_obj->cap->edit_posts) && !empty($user->allcaps[$type_obj->cap->edit_posts])
                                && isset($type_obj->cap->edit_others_posts) && empty($user->allcaps[$type_obj->cap->edit_others_posts])
                            ) {
                                $list_others_cap = str_replace('edit_', 'list_', $type_obj->cap->edit_others_posts);
                                $user->allcaps[$list_others_cap] = true;
                            }
                        }
                    }
                }

                if (!empty($user->ID) && !empty($pagenow) && ('async-upload.php' == $pagenow)) {
                    if ($type_obj = get_post_type_object('attachment')) {
                        if (!empty($type_obj->cap->create_posts) && !empty($user->allcaps[$type_obj->cap->create_posts])) {
                            $add_caps = ['edit_posts' => true];

                            if (defined('PRESSPERMIT_MEDIA_UPLOAD_GRANT_PAGE_EDIT_CAPS')) {
                                $const_val = constant('PRESSPERMIT_MEDIA_UPLOAD_GRANT_PAGE_EDIT_CAPS');
                                $post_type = (post_type_exists($const_val)) ? $const_val : 'page';

                                if ($_type_obj = get_post_type_object($post_type)) {
                                    $add_caps = array_merge(
                                        $add_caps,
                                        array_fill_keys(
                                            [$_type_obj->cap->edit_posts, $_type_obj->cap->edit_others_posts, $_type_obj->cap->edit_published_posts],
                                            true
                                        )
                                    );
                                }
                            }

                            $user->allcaps = array_merge($user->allcaps, $add_caps);
                        }
                    }
                }
            }

            // merge in caps from typecast WP role assignments (and also clear false-valued allcaps entries)
            $this->capCaster();
            $user->allcaps = array_filter(array_merge(array_diff($user->allcaps, [false, 0]), $this->cap_caster->getUserTypecastCaps($user)));
        }
    }

    public function getRoleCaps($role_name)
    {
        global $wp_roles;

        $this->capCaster();

        if (isset($this->cap_caster->typecast_role_caps[$role_name])) {
            return $this->cap_caster->typecast_role_caps[$role_name];
        } elseif (strpos($role_name, ':')) {
            $arr_name = explode(':', $role_name);
            if (!empty($arr_name[2])) {
                $this->cap_caster->typecast_role_caps[$role_name] = $this->cap_caster->getTypecastCaps($role_name);
                return $this->cap_caster->typecast_role_caps[$role_name];
            }
        } elseif (isset($wp_roles->role_objects[$role_name])) {
            return array_keys($wp_roles->role_objects[$role_name]->capabilities);
        } elseif (isset($this->role_defs->dynamic_role_caps[$role_name])) {
            return $this->cap_caster->dynamic_role_caps[$role_name];
        } else {
            return apply_filters('presspermit_role_caps', [], $role_name);
        }
    }

    /*
     * USAGE: args['labels']['name'] = translationed caption
     * USAGE: args['labels']['name'] = translated caption
     * USAGE: args['default_caps'] = [cap_name => true, another_cap_name => true] defines caps for pattern roles which do not have a corresponding WP role 
     */
    public function registerPatternRole($role_name, $args = [])
    {
        $role_obj = (object)$args;
        $role_obj->name = $role_name;

        $this->role_defs->pattern_roles[$role_name] = $role_obj;
    }

    public function refreshOptions()
    {
        global $wpdb;

        do_action('presspermit_refresh_options');

        $site_options = [];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results("SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'presspermit_%'");

        foreach ($results as $row) {
            $site_options[$row->option_name] = $row->option_value;
        }

        $this->default_options['post_blockage_priority'] = !empty($site_options['presspermit_legacy_exception_handling']) ? 0 : 1;

        // this would normally be handled in PPP, but leave here so bbp roles are never listed as WP role groups
        if (function_exists('bbp_get_version') && version_compare(bbp_get_version(), '2.2', '>=')) {
            // phpcs Note: retrieved array is sanitized to rule out any vulnerabilities

            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
            $pp_only_roles = (isset($site_options['presspermit_supplemental_role_defs']))
                ? array_map('sanitize_key', (array) maybe_unserialize($site_options['presspermit_supplemental_role_defs']))
                : [];

            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
            $site_options['presspermit_supplemental_role_defs'] = serialize(
                array_merge(
                    $pp_only_roles,
                    ['bbp_participant', 'bbp_moderator', 'bbp_keymaster', 'bbp_blocked', 'bbp_spectator']
                )
            );
        }

        foreach (array_keys($site_options) as $key) {
            if (is_serialized($site_options[$key])) {
                // phpcs Note: options are sanitized on access

                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
                $site_options[$key] = @unserialize($site_options[$key]);
                if (!is_array($site_options[$key])) {
                    unset($site_options[$key]);
                }
            }
        }

        $this->site_options = apply_filters('presspermit_options', $site_options);
    }


    public function getOption($option_basename)
    {
        static $is_multisite = null;

        if (is_null($is_multisite)) { // perf
            $is_multisite = is_multisite();
        }

        if ($is_multisite) {
            if (!empty($this->netwide_options) && in_array($option_basename, (array)$this->netwide_options, true)) {

                if (!is_array($this->net_options) || !isset($this->net_options["presspermit_$option_basename"])) {  // in case PP Compatibility is not activated
                    if (in_array($option_basename, ['edd_key', 'beta_updates'], true)) {
                        $this->net_options["presspermit_$option_basename"] = get_site_option("presspermit_$option_basename");
                    }
                }

                if (isset($this->net_options["presspermit_$option_basename"])) {
                    $val = maybe_unserialize($this->net_options["presspermit_$option_basename"]);
                    if (is_string($val)) {
                        $val = stripslashes($val);
                    }
                    return $val;
                }

                if (isset($this->default_options[$option_basename])) {
                    $val = maybe_unserialize($this->default_options[$option_basename]);
                    if (is_string($val)) {
                        $val = stripslashes($val);
                    }
                    return $val;
                }
            }
        }

        if (isset($this->site_options["presspermit_$option_basename"])) {
            $val = maybe_unserialize($this->site_options["presspermit_$option_basename"]);
            if (is_string($val)) {
                $val = stripslashes($val);
            }
            return $val;
        }

        if (isset($this->default_options[$option_basename])) {
            $val = maybe_unserialize($this->default_options[$option_basename]);
            if (is_string($val)) {
                $val = stripslashes($val);
            }
            return $val;
        }

        // return null if option not set in db or defaults
    }

    public function getTypeOption($option_name, $object_type, $default_fallback = false)
    {
        if ($arr = (array)$this->getOption($option_name)) {
            if (isset($arr[$object_type])) {
                return $arr[$object_type];
            } elseif ($default_fallback && isset($arr[''])) {
                return $arr[''];
            }
        }

        return false;
    }

    public function updateOption($option_basename, $option_val, $args = [])
    {
        if (is_multisite()) {
            if (!empty($this->netwide_options) && in_array($option_basename, (array)$this->netwide_options, true)) {
                $this->net_options["presspermit_$option_basename"] = $option_val;
                update_site_option("presspermit_$option_basename", $option_val);
                return;
            }
        }

        $this->site_options["presspermit_$option_basename"] = $option_val;
        update_option("presspermit_$option_basename", $option_val);

        do_action('presspermit_update_option', $option_basename, $option_val, $args);
    }

    public function deleteOption($option_basename, $args = [])
    {
        if (is_multisite()) {
            if (!empty($this->netwide_options) && in_array($option_basename, (array)$this->netwide_options, true)) {
                delete_site_option("presspermit_{$option_basename}");
                return;
            }
        }

        delete_option("presspermit_{$option_basename}");
    }

    // Change the active value for a site option, but don't update database // todo: review
    public function setSiteOption($option_basename, $value)
    {
        $this->site_options[$option_basename] = $value;
    }

    public function checkUserRoleSync($user_id)
    {
        global $current_user;

        if (!$user = $this->getUser($user_id)) {
            return;
        }
    }

    public function isUserAdministrator($user_id = false, $args = [])
    {
        return $this->isAdministrator($user_id, 'user', $args);
    }

    public function isContentAdministrator($user_id = false, $args = [])
    {
        return $this->isAdministrator($user_id, 'content', $args);
    }

    public function fltPluginCompatUnfilteredContent($unfiltered)
    {
        // Public Post Preview: Preserve compat by dropping all Permissions filtering, unless integration is enabled through Pro plugin
        if (
            !PWP::empty_REQUEST('_ppp') && !is_admin() && PWP::empty_POST() && class_exists('DS_Public_Post_Preview') && !defined('PRESSPERMIT_DISABLE_PPP_PASSTHROUGH')
            && (!defined('PRESSPERMIT_PRO_VERSION') || !presspermit()->moduleActive('compatibility'))
        ) {
            $unfiltered = true;
        }

        return $unfiltered;
    }

    public function isUserUnfiltered($user_id = false, $args = [])
    {
        // todo: any other Gutenberg Administrator requests to filter?
        $is_unfiltered = $this->isAdministrator($user_id, 'unfiltered', $args);

        $args['user_id'] = $user_id;

        return apply_filters('presspermit_unfiltered', $is_unfiltered, $args);
    }

    public function isAdministrator($user_id = false, $admin_type = 'content', $args = [])
    {
        global $current_user;
        static $is_multisite = null;
        static $is_administrator = [];
        static $cached_user_id = [];

        if (false === $user_id) {
            $args['force_refresh'] = !empty($args['force_refresh']) || did_action('presspermit_refresh_administrator_check');

            if (
                isset($is_administrator[$admin_type])
                && !empty($current_user)
                && ($cached_user_id[$admin_type] == $current_user->ID) && empty($args['force_refresh'])
            ) {
                return $is_administrator[$admin_type];
            }
        }

        $return = false;

        if (is_null($is_multisite)) { // perf
            $is_multisite = is_multisite();
        }

        $user = (((false === $user_id) || ($user_id == $current_user->ID)) && !empty($current_user)) ? $current_user : new \WP_User($user_id);

        if ($is_multisite && $user->ID && is_super_admin($user->ID)) {
            $return = true;
        }

        $caps = [
            'content' => 'pp_administer_content',
            'user' => 'edit_users',
            'option' => 'pp_manage_settings',
            'unfiltered' => 'pp_unfiltered'
        ];

        if ('unfiltered' == $admin_type) {
            if (
                !empty($user->allcaps[$caps['unfiltered']]) || !empty($user->allcaps[$caps['content']])
                || apply_filters('presspermit_unfiltered_content', false)
            ) {  // pp_administer_content cap also grants pp_unfiltered implicitly
                $return = true;
            }
        } elseif ($user && !empty($user->ID)) {
            if (!empty($user->allcaps[$caps[$admin_type]])) {
                $return = true;
            }
        }

        if ((false === $user_id) && !empty($current_user)) {
            $is_administrator[$admin_type] = $return;
            $cached_user_id[$admin_type] = $current_user->ID;
        }

        return $return;
    }

    public function getEnabledPostTypes($args = [], $output = 'names')
    {
        $args = array_merge(['layer' => ''], $args);
        $layer = $args['layer'];
        unset($args['layer']);

        $types = get_post_types(array_merge($args, ['public' => true, 'show_ui' => true]), 'names', 'or');

        $supported_private_types = apply_filters('presspermit_supported_private_types', ['series_grouping']);

        $types = array_merge($types, array_fill_keys($supported_private_types, true));

        $omit_types = apply_filters('presspermit_unfiltered_post_types', ['wp_block']); // todo: review wp_block filtering

        $object_types = array_diff_key($types, array_fill_keys($omit_types, true));

        if ($enabled = (array)$this->getOption("enabled_post_types")) {
            $object_types = array_intersect($object_types, array_keys(array_filter($enabled)));
        }

        if ('exceptions' == $layer) {
            foreach ($object_types as $key => $_type) {
                $type_sub = strtoupper($_type);
                if (defined("PP_NO_{$type_sub}_EXCEPTIONS") && constant("PP_NO_{$type_sub}_EXCEPTIONS")) {
                    unset($object_types[$key]);
                }
            }
        }

        $object_types = apply_filters('presspermit_enabled_post_types', $object_types);

        if ('names' == $output) {
            return $object_types;
        }

        $arr = [];
        foreach ($object_types as $_object_type) {
            $arr[$_object_type] = get_post_type_object($_object_type);
        }

        return $arr;
    }

    // returns all taxonomies for specified object type(s), omitting disabled types and disabled taxonomies
    public function getEnabledTaxonomies($args = [], $output = 'names')
    {
        $taxonomies = [];
        $orig_args = $args;

        if (isset($args['object_type'])) {
            $object_type = $args['object_type'];
            unset($args['object_type']);
        } else {
            $object_type = '';
        }

        if (!defined('PRESSPERMIT_FILTER_PRIVATE_TAXONOMIES')) {
            $args['public'] = true;
        }

        if (false === $object_type) {
            $taxonomies = get_taxonomies($args);
        } else {
            $object_types = ($object_type) ? (array)$object_type : $this->getEnabledPostTypes();

            foreach (get_taxonomies($args, 'object') as $tx) {
                if (
                    array_intersect($object_types, $tx->object_type)
                    || in_array($tx->name, apply_filters('presspermit_universal_taxonomies', ['series_group']))
                ) {
                    $taxonomies[] = $tx->name;
                }
            }
        }

        $taxonomies = $this->removeDisabledTaxonomies($taxonomies);
        $taxonomies = apply_filters('presspermit_enabled_taxonomies', $taxonomies, array_merge($args, $orig_args));

        if ('names' == $output) {
            return $taxonomies;
        }

        $arr = [];
        foreach ($taxonomies as $taxonomy) {
            $arr[$taxonomy] = get_taxonomy($taxonomy);
        }

        return $arr;
    }

    private function removeDisabledTaxonomies($taxonomies)
    {
        if ($enabled = (array)$this->getOption("enabled_taxonomies")) {
            $taxonomies = array_intersect($taxonomies, array_keys(array_filter($enabled)));
        }

        if ($omit_types = $this->getUnfilteredTaxonomies()) {
            $taxonomies = array_diff($taxonomies, $omit_types);
        }

        return $taxonomies;
    }

    public function getUnfilteredTaxonomies()
    {
        return apply_filters('presspermit_unfiltered_taxonomies', ['post_status', 'post_visibility_pp', 'post_status_core_wp_pp', 'topic-tag', 'author']);
    }

    public function isTaxonomyEnabled($taxonomy)
    {
        if ($this->removeDisabledTaxonomies((array)$taxonomy)) {
            return true;
        }
    }

    public function getTypeObject($source_name, $object_type)
    {
        if ('post' == $source_name) {
            return get_post_type_object($object_type);
        } elseif ('term' == $source_name) {
            return get_taxonomy($object_type);
        } else {
            $pp = presspermit();

            if ($group_type_object = $this->groups()->getGroupTypeObject($object_type)) {
                $group_type_object->hierarchical = false;
                return $group_type_object;
            } elseif ($type_obj = apply_filters('presspermit_exception_type', null, $source_name, $object_type)) {
                return $type_obj;
            }
        }
    }

    public function getRoles($agent_id, $agent_type = 'pp_group', $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/Permissions.php');
        return Permissions\DB\Permissions::getRoles($agent_id, $agent_type, $args);
    }

    /**
     * Assign extra roles for a user or group
     * @param array roles : roles[role_name][agent_id] = true
     * @param string agent_type
     */
    public function assignRoles($group_roles, $agent_type = 'pp_group', $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php');
        return Permissions\DB\PermissionsUpdate::assignRoles($group_roles, $agent_type, $args);
    }

    public function deleteRoles($agent_id, $agent_type = 'pp_group', $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php');
        return Permissions\DB\PermissionsUpdate::deleteRoles($agent_id, $agent_type, $args);
    }

    /**
     * Retrieve exceptions for a user or group
     * @param array args :
     *  - agent_type         ('user'|'pp_group'|'pp_net_group'|'bp_group')
     *  - agent_id           (group or user ID)
     *  - operations         ('read'|'edit'|'associate'|'assign'...)
     *  - for_item_source    ('post' or 'term' - data source to which the roles may apply)
     *  - post_types         (post_types to which the roles may apply)
     *  - taxonomies         (taxonomies to which the roles may apply)
     *  - for_item_status    (status to which the roles may apply i.e. 'post_status:private'; default '' means all stati)
     *  - via_item_source    ('post' or 'term' - data source which the role is tied to)
     *  - item_id            (post ID or term_taxonomy_id)
     *  - assign_for         (default 'item'|'children'|'' means both)
     *  - inherited_from     (base exception assignment ID to retrieve propagated assignments for; default '' means N/A)
     */
    public function getExceptions($args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/Permissions.php');
        return Permissions\DB\Permissions::getExceptions($args);
    }

    /**
     * Assign exceptions for a user or group
     * @param array agents : agents['item'|'children'][agent_id] = true|false
     * @param string agent_type
     * @param array args :
     *  - operation          ('read'|'edit'|'associate'|'assign'...)
     *  - mod_type           ('additional'|'exclude'|'include')
     *  - for_item_source    ('post' or 'term' - data source to which the role applies)
     *  - for_item_type      (post_type or taxonomy to which the role applies)
     *  - for_item_status    (status which the role applies to; default '' means all stati)
     *  - via_item_source    ('post' or 'term' - data source which the role is tied to)
     *  - item_id            (post ID or term_taxonomy_id)
     *  - via_item_type      (post_type or taxonomy of item which the role is tied to; default '' means unspecified when via_item_source is 'post')
     */
    public function assignExceptions($agents, $agent_type = 'pp_group', $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php');
        return Permissions\DB\PermissionsUpdate::assignExceptions($agents, $agent_type, $args);
    }

    public function deleteExceptions($agent_ids, $agent_type = 'pp_group')
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php');
        return Permissions\DB\PermissionsUpdate::deleteExceptions($agent_ids, $agent_type);
    }

    public function getOperations()
    {
        $ops = apply_filters('presspermit_operations', ['read']);
        return array_unique($ops);
    }

    public function moduleActive($slug)
    {
        return !empty($this->modules[$slug]);
    }

    public function registerModule($slug, $label, $basename, $version, $args = [])
    {
        $defaults = [
            'min_pp_version' => '0',
            'min_php_version' => '0',
            'package' => 'presspermit',
            'plugin_slug' => '',
        ];

        $args = array_merge($defaults, (array)$args);
        foreach (array_keys($defaults) as $var) {
            $$var = (isset($args[$var])) ? $args[$var] : $defaults[$var];
        }

        $slug = sanitize_key($slug);

        // avoid lockout in case of editing plugin via wp-admin
        if (constant('PRESSPERMIT_DEBUG') && is_admin() && presspermit_editing_plugin()) {
            return false;
        }

        $register = true;
        $error = false;

        if (version_compare(PRESSPERMIT_VERSION, $min_pp_version, '<')) {
            $error = is_admin() && presspermit()->admin()->errorNotice(
                'old_pp',
                ['module_title' => $label, 'min_version' => $min_pp_version]
            );
            $register = false;
        } elseif (!empty($this->min_module_version[$slug]) && version_compare($version, $this->min_module_version[$slug], '<')) {
            if (is_admin()) {
                $error = presspermit()->admin()->errorNotice(
                    'old_extension',
                    ['module_title' => $label, 'min_version' => $this->min_module_version[$slug]]
                );
                // but still register extension so it can be updated!
            } else {
                $error = true;
                $register = false;
            }
        }

        if ($register) {
            $version = PWP::sanitizeWord($version);
            if (!$plugin_slug) {
                $plugin_slug = ($package) ? "{$package}-{$slug}" : $slug;
            }
            $this->modules[$slug] = (object)compact('slug', 'version', 'label', 'basename', 'plugin_slug');
        }

        return !$error;
    }

    public function isPro()
    {
        return defined('PRESSPERMIT_PRO_VERSION') && !class_exists('PublishPress\Permissions\Core');
    }

    /**
     * @return EDD_SL_Plugin_Updater
     */
    public function load_updater()
    {
        if ($this->isPro()) {
            require_once(PRESSPERMIT_PRO_ABSPATH . '/includes-pro/library/Factory.php');
            $container = \PublishPress\Permissions\Factory::get_container();

            if (!empty($container['edd_container'])) {
                return $container['edd_container']['update_manager'];
            } else {
                return false;
            }
        }
    }

    public function keyStatus($refresh = false)
    {
        if ($this->isPro()) {
            require_once(PRESSPERMIT_PRO_ABSPATH . '/includes-pro/pro-key.php');
            return _presspermit_key_status($refresh);
        } else {
            require_once(PRESSPERMIT_ABSPATH . '/includes/key.php');
            return _presspermit_legacy_key_status($refresh);
        }
    }

    public function keyActive($refresh = false)
    {
        return in_array($this->keyStatus($refresh), [true, 'valid', 'expired'], true);
    }

    public function addMaintenanceTriggers()
    {
        $this->hooks->addMaintenanceTriggers();
    }

    public function flt_edit_tag($translated_text, $text, $domain )
    {
        // This code is used to override the "Edit Tag" text in the admin area
        // to provide more context for the user.
        if (is_admin() 
            && PWP::is_GET('taxonomy') && PWP::is_GET('tag_ID') && isset($text) 
            && ($text === 'Edit Tag')
            && (PWP::GET_key('taxonomy') === 'post_tag') 
        ) {
            if (!PWP::empty_GET('pp_universal')) {
                return esc_html__('Edit Tag for All Post Types', 'press-permit-core');
            }
            
            if (PWP::is_GET('post_type') && (PWP::GET_key('post_type') === 'post')) {
                return esc_html__('Edit Tag for Posts', 'press-permit-core');
            }
        }
    
        return $translated_text;
    }

    public static function getDefinedIntegrations() {
        $integrations = [
            [
                'id' => 'acf_compatibility',
                'title' => esc_html__('Advanced Custom Fields', 'press-permit-core'),
                'description' => esc_html__('Control front-end access to Field Groups with advanced permission management.', 'press-permit-core'),
                'icon_class' => 'acf',
                'categories' => ['all', 'fields'],
                'features' => [
                    esc_html__('Control access to Field Groups', 'press-permit-core'),
                    esc_html__('Front-end visibility restrictions', 'press-permit-core'),
                    esc_html__('Integration with permission groups', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => function_exists('acf'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/acf-publishpress-permissions/'
            ],
            [
                'id' => 'beaver_compatibility',
                'title' => esc_html__('Beaver Builder', 'press-permit-core'),
                'description' => esc_html__('Compatibility with Edit Permissions in Beaver Builder.', 'press-permit-core'),
                'icon_class' => 'beaver',
                'categories' => ['all', 'builder'],
                'features' => [
                    esc_html__('Beaver editor integration', 'press-permit-core'),
                    esc_html__('Widget-level permission controls', 'press-permit-core'),
                    esc_html__('Template access restrictions', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => (class_exists('FLBuilder') || defined('FL_BUILDER_VERSION')),
                'learn_more_url' => ''
            ],
            [
                'id' => 'bbpress_compatibility',
                'title' => esc_html__('bbPress Forums', 'press-permit-core'),
                'description' => esc_html__('Forum-specific permissions for bbPress with detailed control over forum access and participation.', 'press-permit-core'),
                'icon_class' => 'bbpress',
                'categories' => ['all', 'community'],
                'features' => [
                    esc_html__('Forum-specific permissions', 'press-permit-core'),
                    esc_html__('Topic creation restrictions', 'press-permit-core'),
                    esc_html__('Forum access controls', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => function_exists('bbp_get_version'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/bbpress-permissions/'
            ],
            [
                'id' => 'buddypress_compatibility',
                'title' => esc_html__('BuddyPress', 'press-permit-core'),
                'description' => esc_html__('Assign post and term permissions to BuddyPress groups for enhanced content control.', 'press-permit-core'),
                'icon_class' => 'buddypress',
                'categories' => ['all', 'community'],
                'features' => [
                    esc_html__('Assign post permissions to BuddyPress groups', 'press-permit-core'),
                    esc_html__('Assign term permissions to BuddyPress groups', 'press-permit-core'),
                    esc_html__('Group-based content permissions', 'press-permit-core'),
                ],
                'enabled' => false,
                'available' => function_exists('buddypress'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/buddypress-content-permissions/'
            ],
            [
                'id' => 'cms_tree_view_compatibility',
                'title' => esc_html__('CMS Tree View', 'press-permit-core'),
                'description' => esc_html__('Compatibility with Edit Permissions in CMS Tree View.', 'press-permit-core'),
                'icon_class' => 'cms_tree_view',
                'categories' => ['all', 'admin'],
                'features' => [
                    esc_html__('Permission-aware tree view', 'press-permit-core'),
                    esc_html__('Hierarchical content controls', 'press-permit-core'),
                ],
                'enabled' => false,
                'available' => (defined('CMS_TREE_VIEW_PLUGIN_URL') || class_exists('\CMS_Tree_View\Setup')),
                'learn_more_url' => ''
            ],
            [
                'id' => 'elementor_compatibility',
                'title' => esc_html__('Elementor', 'press-permit-core'),
                'description' => esc_html__('Compatibility with Edit Permissions in Elementor.', 'press-permit-core'),
                'icon_class' => 'elementor',
                'categories' => ['all', 'builder'],
                'features' => [
                    esc_html__('Elementor editor integration', 'press-permit-core'),
                    esc_html__('Widget-level permission controls', 'press-permit-core'),
                    esc_html__('Template access restrictions', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => (defined('ELEMENTOR_VERSION') || class_exists('\Elementor\Plugin')),
                'learn_more_url' => ''
            ],
            [
                'id' => 'events_calendar_compatibility',
                'title' => esc_html__('The Events Calendar', 'press-permit-core'),
                'description' => esc_html__('Apply permission controls to events and event-related content.', 'press-permit-core'),
                'icon_class' => 'events-calendar',
                'categories' => ['all', 'events'],
                'features' => [
                    esc_html__('Event post permissions', 'press-permit-core'),
                    esc_html__('Event taxonomy controls', 'press-permit-core'),
                ],
                'enabled' => false,
                'available' => defined('EVENTS_CALENDAR_PRO_FILE') || class_exists('Tribe__Events__Pro__Main'),
                'learn_more_url' => ''
            ],
            [
                'id' => 'nested_pages_compatibility',
                'title' => esc_html__('Nested Pages', 'press-permit-core'),
                'description' => esc_html__('Compatibility with Edit Permissions in Nested Pages.', 'press-permit-core'),
                'icon_class' => 'nested_pages',
                'categories' => ['all', 'admin'],
                'features' => [
                    esc_html__('Permission-aware nested view', 'press-permit-core'),
                    esc_html__('Quick edit integration', 'press-permit-core'),
                ],
                'enabled' => false,
                'available' => (defined('NESTED_PAGES_PLUGIN_URL') || class_exists('\Nested_Pages\Setup')),
                'learn_more_url' => ''
            ],
            [
                'id' => 'publishpress_statuses_compatibility',
                'title' => esc_html__('PublishPress Statuses', 'press-permit-core'),
                'description' => esc_html__('Compatibility with PublishPress Statuses plugin.', 'press-permit-core'),
                'icon_class' => 'publishpress_statuses',
                'categories' => ['all', 'workflow'],
                'features' => [
                    esc_html__('Custom status permissions', 'press-permit-core'),
                    esc_html__('Workflow-specific access controls', 'press-permit-core'),
                    esc_html__('Status-based editing capabilities', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => (defined('PUBLISHPRESS_STATUSES_VERSION') || class_exists('PublishPress\Statuses\Factory')),
                'learn_more_url' => ''
            ],
            [
                'id' => 'relevanssi_compatibility',
                'title' => esc_html__('Relevanssi', 'press-permit-core'),
                'description' => esc_html__('Filter search results based on View Permissions for secure content discovery.', 'press-permit-core'),
                'icon_class' => 'relevanssi',
                'categories' => ['all', 'seo'],
                'features' => [
                    esc_html__('Search result filtering', 'press-permit-core'),
                    esc_html__('Permission-aware indexing', 'press-permit-core'),
                    esc_html__('Secure content discovery', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => function_exists('relevanssi_init'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/relevanssi-and-presspermit-pro/'
            ],
            [
                'id' => 'searchwp_compatibility',
                'title' => esc_html__('SearchWP', 'press-permit-core'),
                'description' => esc_html__('Compatibility with SearchWP term filtering for advanced search capabilities.', 'press-permit-core'),
                'icon_class' => 'searchwp',
                'categories' => ['all', 'seo'],
                'features' => [
                    esc_html__('Permission-aware search results', 'press-permit-core'),
                    esc_html__('Term-based filtering', 'press-permit-core'),
                    esc_html__('Advanced search controls', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => class_exists('SearchWP'),
                'learn_more_url' => ''
            ],
            [
                'id' => 'woocommerce_compatibility',
                'title' => esc_html__('WooCommerce', 'press-permit-core'),
                'description' => esc_html__('Apply permission controls to WooCommerce products and product categories.', 'press-permit-core'),
                'icon_class' => 'woocommerce',
                'categories' => ['all', 'ecommerce'],
                'features' => [
                    esc_html__('Product permissions', 'press-permit-core'),
                    esc_html__('Product category controls', 'press-permit-core'),
                ],
                'enabled' => false,
                'available' => class_exists('WooCommerce'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/woocommerce-publishpress-permissions/'
            ],
            [
                'id' => 'wpml_compatibility',
                'title' => esc_html__('WPML', 'press-permit-core'),
                'description' => esc_html__('Multilingual support with permission synchronization across content translations.', 'press-permit-core'),
                'icon_class' => 'wpml',
                'categories' => ['all', 'multilingual'],
                'features' => [
                    esc_html__('Permission synchronization across translations', 'press-permit-core'),
                    esc_html__('Language-specific content access', 'press-permit-core'),
                    esc_html__('Multilingual permission management', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => defined('ICL_SITEPRESS_VERSION'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/wpml-and-presspermit-pro/'
            ],
            [
                'id' => 'yoast_seo_compatibility',
                'title' => esc_html__('Yoast SEO', 'press-permit-core'),
                'description' => esc_html__('Exclude restricted posts from XML sitemaps to maintain proper SEO indexing.', 'press-permit-core'),
                'icon_class' => 'yoast',
                'categories' => ['all', 'seo'],
                'features' => [
                    esc_html__('Sitemap filtering for restricted content', 'press-permit-core'),
                    esc_html__('Search engine visibility controls', 'press-permit-core'),
                    esc_html__('Permission-aware XML sitemaps', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => defined('WPSEO_VERSION'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/publishpress-permissions-yoast-seo/'
            ],
            [
                'id' => 'yootheme_compatibility',
                'title' => esc_html__('YooTheme', 'press-permit-core'),
                'description' => esc_html__('Compatibility with YooTheme builder and term permissions.', 'press-permit-core'),
                'icon_class' => 'yootheme',
                'categories' => ['all', 'builder'],
                'features' => [
                    esc_html__('Builder interface compatibility', 'press-permit-core'),
                    esc_html__('Term permission integration', 'press-permit-core'),
                    esc_html__('Template access controls', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => function_exists('yootheme'),
                'learn_more_url' => ''
            ],

            /* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            [
                'id' => 'litespeed_compatibility',
                'title' => esc_html__('Litespeed Cache', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'litespeed',
                'categories' => ['all', 'cache'],
                'features' => [
                ],
                'enabled' => false,
                'available' => defined('LSCWP_V'),
                'learn_more_url' => '',
                'free' => true
            ],
            [
                'id' => 'w3tc_compatibility',
                'title' => esc_html__('W3 Total Cache', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'w3tc',
                'categories' => ['all', 'cache'],
                'features' => [
                ],
                'enabled' => false,
                'available' => defined('W3TC_VERSION'),
                'learn_more_url' => '',
                'free' => true
            ],
            [
                'id' => 'wp_optimize',
                'title' => esc_html__('WP Optimize', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'wp-optimize',
                'categories' => ['all', 'cache'],
                'features' => [
                ],
                'enabled' => false,
                'available' => defined('WPO_VERSION'),
                'learn_more_url' => '',
                'free' => true
            ],
            [
                'id' => 'wp_super_cache',
                'title' => esc_html__('WP Super Cache', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'wp-super-cache',
                'categories' => ['all', 'cache'],
                'features' => [
                ],
                'enabled' => false,
                'available' => defined('WPSC_VERSION_ID'),
                'learn_more_url' => '',
                'free' => true
            ],
            [
                'id' => 'wp_fastest_cache',
                'title' => esc_html__('WP Fastest Cache', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'wp-fastest-cache',
                'categories' => ['all', 'cache'],
                'features' => [
                ],
                'enabled' => false,
                'available' => class_exists('WpFastestCache'),
                'learn_more_url' => '',
                'free' => true
            ],
            [
                'id' => 'rank_math',
                'title' => esc_html__('Rank Math SEO', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'rank-math',
                'categories' => ['all', 'seo'],
                'features' => [
                ],
                'enabled' => false,
                'available' => class_exists('RankMath'),
                'learn_more_url' => '',
                'free' => true
            ],
            [
                'id' => 'all_in_one_seo',
                'title' => esc_html__('All in One SEO', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'all-in-one-seo',
                'categories' => ['all', 'seo'],
                'features' => [
                ],
                'enabled' => false,
                'available' => defined('AIOSEO_FILE'),
                'learn_more_url' => '',
                'free' => true
            ],
            [
                'id' => 'capabilities',
                'title' => esc_html__('PublishPress Capabilities', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'capabilities',
                'categories' => ['all', 'admin'],
                'features' => [
                ],
                'enabled' => false,
                'available' => defined('PUBLISHPRESS_CAPS_VERSION'),
                'learn_more_url' => 'https://publishpress.com/capabilities/',
                'free' => true
            ],
            [
                'id' => 'authors',
                'title' => esc_html__('PublishPress Authors', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'authors',
                'categories' => ['all', 'workflow'],
                'features' => [
                ],
                'enabled' => false,
                'available' => defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION'),
                'learn_more_url' => 'https://publishpress.com/authors/',
                'free' => true
            ],
            [
                'id' => 'revisions',
                'title' => esc_html__('PublishPress Revisions', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'revisions',
                'categories' => ['all', 'workflow'],
                'features' => [
                ],
                'enabled' => false,
                'available' => defined('PUBLISHPRESS_REVISONS_VERSION'),
                'learn_more_url' => 'https://publishpress.com/revisions/',
                'free' => true
            ],
            [
                'id' => 'planner',
                'title' => esc_html__('PublishPress Planner', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'planner',
                'categories' => ['all', 'workflow'],
                'features' => [
                ],
                'enabled' => false,
                'available' => defined('PUBLISHPRESS_VERSION'),
                'learn_more_url' => 'https://publishpress.com/planner/',
                'free' => true
            ],
            [
                'id' => 'checklists',
                'title' => esc_html__('PublishPress Checklists', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'checklists',
                'categories' => ['all', 'workflow'],
                'features' => [
                ],
                'enabled' => false,
                'available' => defined('PUBLISHPRESS_CHECKLISTS_VERSION'),
                'learn_more_url' => 'https://publishpress.com/checklists/',
                'free' => true
            ],
            [
                'id' => 'taxopress',
                'title' => esc_html__('Taxopress', 'press-permit-core'),
                'description' => esc_html__('.', 'press-permit-core'),
                'icon_class' => 'taxopress',
                'categories' => ['all', 'admin'],
                'features' => [
                ],
                'enabled' => false,
                'available' => defined('STAGS_VERSION'),
                'learn_more_url' => 'https://taxopress.com/',
                'free' => true
            ],
            */
        ];

        foreach (array_keys($integrations) as $i) {
            if (!isset($integrations[$i]['free'])) {
                $integrations[$i]['free'] = false;
            }
        }

        usort($integrations, function($a, $b) {
            return $a['title'] <=> $b['title'];
        });

        return $integrations;
    }
}

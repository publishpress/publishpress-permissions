<?php

namespace PublishPress\Permissions\UI\Dashboard;

// menu icons by Jonas Rask: https://www.jonasraskdesign.com/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

define('PRESSPERMIT_URLPATH', plugins_url('', PRESSPERMIT_FILE));

class DashboardFilters
{
    public function __construct()
    {
        global $pagenow;

        do_action('_presspermit_admin_ui');

        // ============== UI-related filters ================
        add_action('admin_menu', [$this, 'actBuildMenu'], 21);

        add_action('show_user_profile', [$this, 'actUserUi'], 2);
        add_action('edit_user_profile', [$this, 'actUserUi'], 2);
        add_action('admin_print_scripts-user-new.php', [$this, 'actInsertGroupsUi']);

        add_action('admin_menu', [$this, 'actNggUploaderWorkaround']);

        $is_post_admin = false;

        $pp_plugin_page = presspermitPluginPage();

        if (array_intersect([$pagenow], ['post-new.php', 'post.php'])) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/PostEdit.php');
            new PostEdit();
            $is_post_admin = true;

        } elseif (('term.php' == $pagenow) || (('edit-tags.php' == $pagenow)
                && PWP::is_REQUEST('action', 'edit'))
        ) {
            if (current_user_can('pp_assign_roles')) {
                require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/TermEdit.php');
                new TermEdit();
            }
        }

        if ('users.php' == $pagenow) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/UsersListing.php');
            new UsersListing();

        } elseif (('edit.php' == $pagenow) || PWP::isAjax('inline-save')) {
            if (!$post_type = PWP::REQUEST_key('post_type')) {
                $post_type = 'post';
            }

            if (in_array($post_type, presspermit()->getEnabledPostTypes(), true)) {
                require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/PostsListing.php');
                new PostsListing();
                $is_post_admin = true;
            }
        } elseif (
            in_array($pagenow, ['edit-tags.php']) || (defined('DOING_AJAX') && DOING_AJAX
                && PWP::is_REQUEST('action', ['inline-save-tax', 'add-tag']))
        ) {
            if (!PWP::empty_REQUEST('taxonomy') && presspermit()->isTaxonomyEnabled(PWP::REQUEST_key('taxonomy'))) {
                require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/TermsListing.php');
                new TermsListing();
            }
        } elseif (in_array($pagenow, ['plugins.php', 'plugin-install.php'])) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/PluginAdmin.php');
            new PluginAdmin();
        } else {
            if (PWP::SERVER_url('REQUEST_URI') && strpos(esc_url_raw(PWP::SERVER_url('REQUEST_URI')), 'page=presspermit-groups') && PWP::is_REQUEST('wp_screen_options')) {
                \PublishPress\Permissions\UI\PluginPage::handleScreenOptions();
            }

            if ('presspermit-edit-permissions' == $pp_plugin_page) {
                add_action('admin_head', [$this, 'actLoadScripts']);

            } elseif ('presspermit-settings' == $pp_plugin_page) {
                wp_enqueue_style('plugin-install');
                wp_enqueue_script('plugin-install');
                add_thickbox();

                if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION') && !version_compare(PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION, '3.8.0', '>=')) {
                    require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/PluginAdmin.php');
                    PluginAdmin::authorsVersionNotice(['ignore_dismissal' => true]);
                }
            } elseif ('plugins.php' == $pagenow) {
                add_thickbox();
            }
        }

        if ($is_post_admin) {
            do_action('presspermit_post_admin');
        }

        add_action('admin_head', [$this, 'actAdminHead']);

        wp_enqueue_style('presspermit', PRESSPERMIT_URLPATH . '/common/css/presspermit.css', [], PRESSPERMIT_VERSION);

        if ($pp_plugin_page || (!PWP::empty_REQUEST('page') && PWP::REQUEST_key_match('page', 'pp-capabilities'))) {
            wp_enqueue_style('presspermit-plugin-pages', PRESSPERMIT_URLPATH . '/common/css/plugin-pages.css', [], PRESSPERMIT_VERSION);
            wp_enqueue_style('presspermit-admin-common', PRESSPERMIT_URLPATH . '/common/css/pressshack-admin.css', [], PRESSPERMIT_VERSION);
        }

        if (in_array($pagenow, ['user-edit.php', 'user-new.php', 'profile.php'])) {
            wp_enqueue_style('presspermit-edit-permissions', PRESSPERMIT_URLPATH . '/common/css/edit-permissions.css', [], PRESSPERMIT_VERSION);
            wp_enqueue_style('presspermit-groups-checklist', PRESSPERMIT_URLPATH . '/common/css/groups-checklist.css', [], PRESSPERMIT_VERSION);
       
        } elseif (in_array($pp_plugin_page, ['presspermit-edit-permissions', 'presspermit-group-new'], true)) {
            wp_enqueue_style('presspermit-edit-permissions', PRESSPERMIT_URLPATH . '/common/css/edit-permissions.css', [], PRESSPERMIT_VERSION);
            wp_enqueue_style('presspermit-groups-checklist', PRESSPERMIT_URLPATH . '/common/css/groups-checklist.css', [], PRESSPERMIT_VERSION);
        } 
        
        if (('presspermit-settings' == $pp_plugin_page) || (('plugin-install.php' == $pagenow)
            && isset($_SERVER['HTTP_REFERER']) && strpos(esc_url_raw($_SERVER['HTTP_REFERER']), 'presspermit-settings'))
        ) {
            wp_enqueue_style('presspermit-settings', PRESSPERMIT_URLPATH . '/common/css/settings.css', [], PRESSPERMIT_VERSION);
        }

        if (in_array($pagenow, ['edit.php', 'post.php'])) {
            add_action('admin_menu', [$this, 'actReinstateSoloSubmenus']);
            add_action('network_admin_menu', [$this, 'actReinstateSoloSubmenus']);
        }

        do_action('presspermit_admin_ui');
    }

    public function actLoadScripts()
    {
        $pp = presspermit();

        if (!$agent_type = PWP::REQUEST_key('agent_type')) {
            $agent_type = 'pp_group';
        }

		$agent_id = PWP::REQUEST_int('agent_id');

        $load_role_scripts = $pp->groups()->userCan('pp_manage_members', $agent_id, $agent_type)
        || $pp->groups()->anyGroupManager() || current_user_can('pp_assign_roles')
        || $pp->admin()->bulkRolesEnabled();

        $load_exception_scripts = current_user_can('pp_assign_roles') || presspermit()->admin()->bulkRolesEnabled();

        if ( $load_role_scripts || $load_exception_scripts ) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentPermissionsUI.php');
            
            if ( $load_role_scripts ) {
                \PublishPress\Permissions\UI\AgentPermissionsUI::roleAssignmentScripts();
            }

            if ( $load_exception_scripts ) {
                \PublishPress\Permissions\UI\AgentPermissionsUI::exceptionAssignmentScripts();
            }
        }
    }

    public function actReinstateSoloSubmenus()
    {
        global $submenu;

        // Work around an issue with WP menu access handling
        // https://core.trac.wordpress.org/ticket/22895

        // Add a dummy submenu item to prevent WP from stripping out solitary submenus.  
        // Otherwise menu access loses type sensitivity and requires "edit_posts" cap for all types.
        foreach (array_keys($submenu) as $key) {
            if (1 == count($submenu[$key]) && (0 === strpos($key, 'edit.php'))) {
                $submenu[$key][999] = ['', 'read', $key];  // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            }
        }
    }

    public static function actMenuHandler()
    {
        if (!$page = PWP::GET_key('page')) {
            return;
        }

        $pp_page = sanitize_key($page);

        if (in_array($pp_page, [
            'presspermit-settings', 'presspermit-groups', 'presspermit-users',
            'presspermit-edit-permissions', 'presspermit-group-new',
        ], true)) {
            $class_name = ('presspermit-edit-permissions' == $pp_page)
            ? 'AgentPermissions' 
            : str_replace('-', '', ucwords( str_replace('presspermit-', '', $pp_page), '-') );

            require_once(PRESSPERMIT_CLASSPATH . "/UI/{$class_name}.php");
            $load_class = "\\PublishPress\Permissions\\UI\\$class_name";
            new $load_class();
        }

        do_action('presspermit_menu_handler', $pp_page);
    }

    public function actAdminHead()
    {
        global $pagenow;

        if (PWP::empty_REQUEST('noheader')) {
            global $wp_scripts;
            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
            wp_enqueue_script('presspermit-misc', PRESSPERMIT_URLPATH . "/common/js/presspermit{$suffix}.js", ['jquery'], PRESSPERMIT_VERSION, true);
            $wp_scripts->in_footer[] = 'presspermit-misc'; // otherwise it will not be printed in footer todo: review
        }

        if (('user-edit.php' == $pagenow) && presspermit()->getOption('display_user_profile_groups')) {
            add_thickbox();
        }
    }

    public function actBuildMenu()
    {
        $request_uri = esc_url_raw(PWP::SERVER_url('REQUEST_URI'));

        if ($request_uri && false !== strpos($request_uri, trailingslashit(PWP::admin_rel_url('')) . 'network/')
        ) {
            return;
        }

        $do_groups = current_user_can('pp_edit_groups') || presspermit()->groups()->anyGroupManager();
        $do_settings = current_user_can('pp_manage_settings');

        if (!$do_groups && !$do_settings) {
            return;
        }

        $pp_plugin_page = presspermitPluginPage();
        $admin = presspermit()->admin();

        $pp_cred_menu = $admin->getMenuParams('permits');
        $pp_options_menu = $admin->getMenuParams('options');

        if ('presspermit-groups' == $pp_cred_menu) {
            //  Manually set menu indexes for positioning below Users menu
            global $menu;

            $permissions_title = esc_html__('Permissions', 'press-permit-core');

            $menu_order = 72;

            if (defined('PUBLISHPRESS_PERMISSIONS_MENU_GROUPING')) {
                foreach (get_option('active_plugins') as $plugin_file) {
                    if ( false !== strpos($plugin_file, 'publishpress.php') ) {
                        $menu_order = 27;
                    }
                }
            }

            add_menu_page(
                $permissions_title,
                $permissions_title,
                'read',
                $pp_cred_menu,
                [__CLASS__, 'actMenuHandler'],
                'dashicons-unlock',
                $menu_order
            );
        }

        $handler = [__CLASS__, 'actMenuHandler'];

        if ($do_groups) {
            add_submenu_page($pp_cred_menu, esc_html__('Permissions', 'press-permit-core'), esc_html__('Permissions', 'press-permit-core'), 'read', 'presspermit-groups', $handler);

            if (current_user_can('pp_create_groups') && ('presspermit-group-new' == $pp_plugin_page)) {
                add_submenu_page(
                    $pp_cred_menu,
                    esc_html__('Add New Permission Group', 'press-permit-core'),
                    '- ' . esc_html__('Add New', 'press-permit-core'),
                    'read',
                    'presspermit-group-new',
                    $handler
                );
            }
        }

        // register plugin pages not displayed as menu items
        if (in_array($pp_plugin_page, ['presspermit-edit-permissions'], true)) {
            $titles = ['presspermit-edit-permissions' => esc_html__('Edit Permissions', 'press-permit-core')];
            add_submenu_page(
                $pp_cred_menu, 
                $titles[$pp_plugin_page], 
                '- ' . esc_html__('Edit', 'press-permit-core'),
                'read', 
                $pp_plugin_page, 
                $handler
            );
        }

        if (presspermit()->moduleActive('collaboration') && (defined('PRESSPERMIT_ROLE_USAGE_COMPAT') || !PWP::empty_REQUEST('pp_role_usage'))) {
            do_action('pp_added_role_usage_submenu');

            add_submenu_page(
                $pp_options_menu, 
                esc_html__('Role Usage', 'press-permit-core'), 
                esc_html__('Role Usage', 'press-permit-core'), 
                'read', 
                'presspermit-role-usage', 
                $handler
            );

            if ('presspermit-role-usage-edit' == $pp_plugin_page) {
                do_action('pp_added_edit_role_usage_submenu');

                add_submenu_page(
                    $pp_options_menu, 
                    esc_html__('Edit Role Usage', 'press-permit-core'), 
                    esc_html__('Edit Role Usage', 'press-permit-core'), 
                    'read', 
                    'presspermit-role-usage-edit', 
                    $handler
                );
            }
        }

        if ($do_settings) {
            do_action('presspermit_permissions_menu', $pp_options_menu, $handler);

            $settings_caption = ('presspermit-groups' == $pp_options_menu)
                ? esc_html__('Settings', 'press-permit-core')
                : $permissions_title;

            add_submenu_page($pp_options_menu, $settings_caption, $settings_caption, 'read', 'presspermit-settings', $handler);
        }

        do_action('presspermit_admin_menu');
    }

    public function actUserUi($user = false)
    {
        if (is_network_admin()) {
            return;
        }

        if (empty($user)) {
            global $profileuser;

            if (!empty($profileuser)) {
                $user = $profileuser;
            }
        } elseif (is_scalar($user)) {
            $user = new \PublishPress\PermissionsUser($user);
        }

        $logged_user = presspermit()->getUser();

        $pp_profile_user = ($user->ID == $logged_user->ID) ? $logged_user : new \PublishPress\PermissionsUser($user->ID);

        $pp = presspermit();

        $is_administrator = $pp->isUserAdministrator() && $pp->admin()->bulkRolesEnabled() && current_user_can('list_users');

        if (
            $is_administrator || $pp->getOption('display_user_profile_roles')
            || $pp->getOption('display_user_profile_groups')
        ) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/Profile.php');
            require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentPermissionsUI.php');
        }

        if ($is_administrator || $pp->getOption('display_user_profile_groups')) {
            Profile::displayUserGroups($pp_profile_user->ID);
        }

        if ($is_administrator || $pp->getOption('display_user_profile_roles')) {
            Profile::displayUserAssignedRoles($pp_profile_user);
        }

        if ($is_administrator || $pp->getOption('display_user_profile_roles')) {
            Profile::displayUserRoles($pp_profile_user);
        }
    }

    public function actInsertGroupsUi()
    {
        if (is_multisite() || !presspermit()->getOption('new_user_groups_ui')) {
            return;
        }

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-new-user', PRESSPERMIT_URLPATH . "/common/js/new-user{$suffix}.js", [], PRESSPERMIT_VERSION);
        wp_localize_script('presspermit-new-user', 'ppUser', ['ajaxurl' => wp_nonce_url(admin_url(''), 'pp-ajax')]);
    }

    // support NextGenGallery uploader and other custom jquery calls which WP treats as index.php ( otherwise user_can_access_admin_page() fails )
    public function actNggUploaderWorkaround()
    {
        global $pagenow;

        $site_url = wp_parse_url(get_option('siteurl'));

        $request_uri = esc_url_raw(PWP::SERVER_url('REQUEST_URI'));

        if (isset($site_url['path']) && $request_uri && (untrailingslashit($request_uri) == untrailingslashit(admin_url('')))) {

            return;
        }

        if (('index.php' == $pagenow) && $request_uri && strpos($request_uri, '.php') && !strpos($request_uri, 'index.php')) {
            // @todo: review for alternate solution

            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            $pagenow = '';
        }
    }

    public static function listAgentExceptions($agent_type, $id, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/Profile.php');
        return Profile::listAgentExceptions($agent_type, $id, $args);
    }
}

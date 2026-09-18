<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

class DashboardFilters
{
    function __construct()
    {
        global $pagenow;

        define('PRESSPERMIT_COLLAB_URLPATH', plugins_url('', PRESSPERMIT_COLLAB_FILE));

        if (('nav-menus.php' == $pagenow) 
        || (defined('DOING_AJAX') && DOING_AJAX && PWP::is_REQUEST('action', ['menu-get-metabox', 'menu-quick-search']))
        ) {  // Administrators also need this, to add private posts to available items list
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/NavMenuQuery.php');
            new NavMenuQuery();
        }

        if (('index.php' == $pagenow) && !defined('USE_RVY_RIGHTNOW')) {
            include_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/DashboardWidgetsFilters.php');
            new DashboardWidgetsFilters();
        }

        wp_enqueue_style('presspermit-collab', PRESSPERMIT_COLLAB_URLPATH . '/common/css/collab.css', [], PRESSPERMIT_COLLAB_VERSION);

        if (presspermitPluginPage()) {
            wp_enqueue_style('presspermit-collab-plugin-pages', PRESSPERMIT_COLLAB_URLPATH . '/common/css/plugin-pages.css', [], PRESSPERMIT_COLLAB_VERSION);
        }

        add_action('admin_head', [$this, 'actAdminHead']);
        // Run before WordPress prints queued footer scripts at priority 20.
        add_action('admin_print_footer_scripts', [$this, 'quickpress_workaround'], 19);

        add_action('presspermit_permissions_menu', [$this, 'permissions_menu'], 10, 2);
        add_action('presspermit_menu_handler', [$this, 'menu_handler']);

        add_action('presspermit_term_edit_ui', [$this, 'term_edit_ui']);
        add_action('presspermit_post_edit_ui', [$this, 'post_edit_ui']);
        add_action('presspermit_post_listing_ui', [$this, 'post_listing_ui']);
        add_action('presspermit_options_ui', [$this, 'optionsUI']);

        add_filter('presspermit_term_include_clause', [$this, 'flt_term_include_clause'], 10, 2);

		// Used only by Permissions Pro < 4.0
        add_filter('presspermit_post_status_types', [$this, 'flt_status_links'], 5);

        if (defined('PUBLISHPRESS_REVISIONS_VERSION')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Revisions/PostFilters.php');
            new \PublishPress\Permissions\Collab\Revisions\PostFilters();

        } elseif (defined('REVISIONARY_VERSION')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Revisionary/PostFilters.php');
            new \PublishPress\Permissions\Collab\Revisionary\PostFilters();
        }

        if ('users.php' == $pagenow) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/Users.php');
            new Users();
        }
    }

    function flt_term_include_clause($clause, $args = [])
    {
        if (empty($args['required_operation']) || ('read' == $args['required_operation'])) {
            $defaults = ['src_table' => '', 'required_operation' => ''];
            $args = array_merge($defaults, $args);
            foreach (array_keys($defaults) as $var) {
                $$var = $args[$var];
            }

            // Ensure that Gutenberg contributors can edit their own newly created draft
            $clause = "( ( $clause ) OR "
            . "( $src_table.post_status = 'auto-draft' AND $src_table.post_author = '" . presspermit()->getUser()->ID . "' ) )";
        }

        return $clause;
    }

    function menu_handler($pp_page)
    {
        if (in_array($pp_page, ['presspermit-role-usage', 'presspermit-role-usage-edit'], true)) {
            $class_name = str_replace('-', '', ucwords( str_replace('presspermit-', '', $pp_page), '-') );

            require_once(PRESSPERMIT_COLLAB_CLASSPATH . "/UI/{$class_name}.php");
            $load_class = "\\PublishPress\Permissions\\Collab\\UI\\$class_name";
            new $load_class();
        }
    }

    function permissions_menu($pp_options_menu, $handler)
    {
        if (presspermit()->moduleActive('collaboration')) {
	    	// Register a submenu item for these screens, but only if they are accessed
	        if (in_array(presspermitPluginPage(), ['presspermit-role-usage', 'presspermit-role-usage-edit']) && !did_action('pp_added_role_usage_submenu')) {
	            add_submenu_page(
	                $pp_options_menu,
	                esc_html__('Role Usage', 'press-permit-core'),
	                esc_html__('Role Usage', 'press-permit-core'),
	                'read',
	                'presspermit-role-usage',
	                $handler
	            );
	        }

            if ('presspermit-role-usage-edit' == presspermitPluginPage() && !did_action('pp_added_edit_role_usage_submenu')) {
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
    }

    function actAdminHead()
    {
        if (PWP::is_REQUEST('page', 'presspermit-role-usage')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/RoleUsageListTable.php');
            \PublishPress\Permissions\Collab\UI\RoleUsageListTable::instance();
        }
    }

    function quickpress_workaround()
    {  // need this for multiple qp entries by limited user
        if (!presspermit()->isUserUnfiltered() && isset($_SERVER['HTTP_USER_AGENT'])) {
            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
            wp_enqueue_script(
                'presspermit-quickpress-workaround',
                PRESSPERMIT_COLLAB_URLPATH . "/common/js/quickpress{$suffix}.js",
                [],
                PRESSPERMIT_COLLAB_VERSION,
                false
            );
            wp_print_scripts('presspermit-quickpress-workaround');
        }
    }

    function optionsUI()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/SettingsTabEditing.php');
        new \PublishPress\Permissions\Collab\UI\SettingsTabEditing();
    }

    function post_listing_ui()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/PostsListing.php');
        new PostsListing();

        if (!presspermit()->isUserUnfiltered()) {
            // listing filter used for role status indication in edit posts/pages
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/PostsListingNonAdministrator.php');
            new PostsListingNonAdministrator();
        }
    }

    function post_edit_ui()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/PostEdit.php');
        new PostEdit();

        if (PWP::isBlockEditorActive()) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Gutenberg/PostEdit.php');
            new \PublishPress\Permissions\Collab\UI\Gutenberg\PostEdit();
        }
    }

    function term_edit_ui()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/TermEdit.php');
        new TermEdit();
    }

	// Used only by Permissions Pro < 4.0
    function flt_status_links($links)
    {
        if (current_user_can('pp_define_post_status') || current_user_can('pp_define_moderation')) {
            $links = array_merge(
                [(object)[
                    'attrib_type' => 'moderation', 
                    'url' => "admin.php?page=presspermit-statuses&attrib_type=moderation", 
                    'label' => esc_html__('Workflow', 'press-permit-core')
                    ]
                ],
                $links
            );
        }
        return $links;
    }
}

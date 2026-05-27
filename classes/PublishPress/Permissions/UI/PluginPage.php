<?php

namespace PublishPress\Permissions\UI;

// Plugin admin UI function not inherently tied to WordPress Dashboard framework
class PluginPage
{
    private static $instance = null;
    var $table;
    var $table_user;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new PluginPage();
        }
        
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_head', [$this, 'actAdminHead']);
    }

    public static function handleScreenOptions()
    {
        if (PWP::is_REQUEST('wp_screen_options')) {
            check_ajax_referer( 'screen-options-nonce', 'screenoptionnonce' );

            if (isset($_REQUEST['wp_screen_options']['option']) && ('groups_per_page' == $_REQUEST['wp_screen_options']['option']) && isset($_REQUEST['wp_screen_options']['value'])) {
                global $current_user;
                update_user_option($current_user->ID, sanitize_key($_REQUEST['wp_screen_options']['option']), (int) $_REQUEST['wp_screen_options']['value']);
            }
        }
    }

    public static function icon()
    {
    }

    public function actAdminHead()
    {
        global $pagenow;

        if (('upload.php' == $pagenow) && !defined('PRESSPERMIT_FILE_ACCESS_VERSION')
            && current_user_can('pp_manage_settings') && presspermit()->getOption('display_extension_hints')
        ) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/HintsMedia.php');
            HintsMedia::fileFilteringPromo();
        }

        if ('presspermit-groups' == presspermitPluginPage()) {
            // todo: eliminate redundancy with Groups::__construct()
            if (!PWP::empty_REQUEST('action2')) {
                $action = PWP::REQUEST_key('action2');

            } elseif (!PWP::empty_REQUEST('action')) {
                $action = PWP::REQUEST_key('action');

            } elseif (!PWP::empty_REQUEST('pp_action')) {
                $action = PWP::REQUEST_key('pp_action');
            } else {
                $action = '';
            }

            if ( ! in_array($action, ['delete', 'bulkdelete'])) {
                if (!$agent_type = PWP::REQUEST_key('agent_type')) {
                    $agent_type = 'pp_group';
                }
            } else {
                $agent_type = '';
            }

            $pp_groups = presspermit()->groups();
            $agent_type = self::getAgentType($agent_type);
            $group_variant = self::getGroupVariant();

            if ( ! $this->table = apply_filters('presspermit_groups_list_table', false, $agent_type) ) {
                if (!$active_tab = PluginPage::viewFilter('permissions_tab')) {
                    if (current_user_can('pp_edit_groups') || $pp_groups->anyGroupManager()) {
                        $active_tab = 'user-group';
                    } else {
                        $active_tab = 'users';
                    }
                }

                if ($active_tab === 'users') {
                    require_once(PRESSPERMIT_CLASSPATH . '/UI/UsersListTable.php' );
                    $this->table_user = new UsersListTable();
                } else {
                    require_once(PRESSPERMIT_CLASSPATH . '/UI/GroupsListTable.php' );
                    $this->table = new GroupsListTable(compact('agent_type', 'group_variant'));
                }
            }

            add_screen_option(
                'per_page',
                
                ['label' => _x('Groups', 'groups per page (screen options)', 'press-permit-core'), 
                'default' => 20, 
                'option' => 'groups_per_page'
                ]
            );
        }

        add_action('in_admin_header', function() {do_action('presspermit_plugin_page_admin_header');}, 100);
    }

    public static function getAgentType($default_type = '') {
        if (!$_agent_type = PWP::REQUEST_key('agent_type')) {
            $_agent_type = $default_type;
        }

        if (!$agent_type = sanitize_key(apply_filters('presspermit_query_group_type', $_agent_type))) {
            $agent_type = 'pp_group';
        }

        return $agent_type;
    }

    public static function getGroupVariant() {
        if (PWP::empty_REQUEST('group_variant') && !PWP::empty_REQUEST('s')) {
            if ($wp_http_referer = PWP::REQUEST_url('_wp_http_referer')) {
                $matches = [];
                if (preg_match("/group_variant=([0-9a-zA-Z_\-]+)/", urldecode(esc_url_raw($wp_http_referer)), $matches)) {
                    if ($matches[1]) {
                        $group_variant = sanitize_key($matches[1]);
                    }
                }
            }
        } elseif (PWP::empty_REQUEST('group_variant') && !current_user_can('edit_users') && !current_user_can('pp_manage_permissions')) {
            $group_variant = 'pp_group';
        }

        if (empty($group_variant)) {
            $group_variant = self::viewFilter('group_variant');

            // Don't default to Login State, or to a group variant that could become deactivated.
            if (PWP::empty_REQUEST('group_variant') && !in_array($group_variant, ['pp_group', 'wp_role'])) {
                $group_variant = '';
            }
        }

        return sanitize_key(apply_filters('presspermit_query_group_variant', $group_variant));
    }

    public static function viewFilter($var) {
        global $current_user, $pagenow;
        
        $valid_views = ['permissions_tab', 'group_variant', 'pp_has_roles', 'pp_has_exceptions', 'pp_has_perms', 'pp_user_roles', 'pp_user_exceptions', 'pp_user_perms', 'pp_no_group'];

        $allow_multiple_retrieval = ['permissions_tab'];

        $is_string = in_array($var, ['permissions_tab', 'group_variant']);

        if ('users.php' == $pagenow) {
            return '';
        }

        if (!in_array($var, $valid_views)) {
            return '';
        }
        
        if (!PWP::is_REQUEST($var)) {
            foreach ($valid_views as $view_var) {
                if (in_array($view_var, $allow_multiple_retrieval)) {
                    continue;
                }
                
                // If another view is explicitly requested, don't default to saved value for this view.
                if (!PWP::empty_REQUEST($view_var)) {
                    return '';
                }
            }

            if ($is_string) {
                if (!$filter_val = get_user_option($var)) {
                    $filter_val = '';
                }
            } else {
                $filter_val = get_user_option($var) ? 1 : 0;
            }
        } else {
            if ($is_string) {
                $filter_val = PWP::REQUEST_key($var);
            } else {
                $filter_val = PWP::REQUEST_int($var) ? 1 : 0;
            }

            update_user_option($current_user->ID, $var, $filter_val);
        }

        return $filter_val;
    }
}

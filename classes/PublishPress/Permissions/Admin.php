<?php

namespace PublishPress\Permissions;

class Admin
{
    // object references
    private $agents;

    // status / memcache
    private $last_post_status = [];
    public $errors;

    public function __construct()
    {
    }

    public function getLastPostStatus($post_id)
    {
        return (isset($this->last_post_status[$post_id])) ? $this->last_post_status[$post_id] : false;
    }

    public function setLastPostStatus($post_id, $status)
    {
        $this->last_post_status[$post_id] = $status;
    }

    public function getMenuParams($for)
    {
        if ((defined('OZH_MENU_VER') && !defined('PP_FORCE_PLUGIN_MENU')) || defined('PP_FORCE_USERS_MENU')) {
            $arr = ['permits' => 'users.php', 'options' => 'options-general.php'];
        } else {
            $arr = ['permits' => 'presspermit-groups', 'options' => 'presspermit-groups'];
        }

        if (isset($arr[$for])) {
            return $arr[$for];
        }
    }

    public function agents()
    {
        if (!isset($this->agents)) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Agents.php');
            $this->agents = new UI\Agents();
        }

        return $this->agents;
    }

    // allow lockdown to non-Administrators (while still allowing item-specific role editing for those who have assign_roles capability)
    public function bulkRolesEnabled()
    {
        return (current_user_can('pp_assign_roles') && (current_user_can('pp_administer_content') || current_user_can('pp_assign_bulk_roles'))
            && !defined('PP_DISABLE_BULK_ROLES')) || (current_user_can('edit_users'));
    }

    public function userCanAdminRole($role_name, $post_type, $item_id = 0)
    {
        require_once(PRESSPERMIT_CLASSPATH . '/PermissionsAdmin.php');
        return PermissionsAdmin::userCanAdminRole($role_name, $post_type, $item_id);
    }

    public function canSetExceptions($operation, $for_item_type, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/PermissionsAdmin.php');
        return PermissionsAdmin::canSetExceptions($operation, $for_item_type, $args);
    }

    public function getAdministratorRoles()
    {
        // WP roles containing the 'pp_administer_content' cap are always honored regardless of object or term restritions
        global $wp_roles;
        $admin_roles = [];

        if (isset($wp_roles->role_objects)) {
            foreach (array_keys($wp_roles->role_objects) as $wp_role_name) {
                if (!empty($wp_roles->role_objects[$wp_role_name]->capabilities['pp_administer_content'])) {
                    $admin_roles[$wp_role_name] = true;
                }
            }
        }

        return $admin_roles;
    }

    public function getRoleTitle($role_name, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/PermissionsAdmin.php');
        return PermissionsAdmin::getRoleTitle($role_name, $args);
    }

    public function getOperationObject($operation, $post_type = '')
    {
        static $operations;

        if (!isset($operations)) {
            $op_captions = apply_filters(
                'presspermit_operation_captions',
                ['read' => (object)['label' => esc_html__('View'), 'noun_label' => esc_html__('Viewing', 'press-permit-core')]]
            );

            $operations = Arr::subset($op_captions, presspermit()->getOperations());
        }

        // deference op_obj from static array so type-specific filtering is not memcached
        $op_obj = (isset($operations[$operation])) ? (object)(array)$operations[$operation] : false;

        return apply_filters('presspermit_operation_object', $op_obj, $operation, $post_type);
    }

    public function orderTypes($types, $args = [])
    {
        $defaults = ['order_property' => '', 'item_type' => '', 'labels_property' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if ('post' == $item_type) {
            $post_types = get_post_types([], 'object');
        } elseif ('taxonomy' == $item_type) {
            $taxonomies = get_taxonomies([], 'object');
        }

        $ordered_types = [];
        foreach (array_keys($types) as $name) {
            if ('post' == $item_type) {
                $ordered_types[$name] = (isset($post_types[$name]->labels->singular_name))
                    ? $post_types[$name]->labels->singular_name
                    : '';
            } elseif ('taxonomy' == $item_type) {
                $ordered_types[$name] = (isset($taxonomies[$name]->labels->singular_name))
                    ? $taxonomies[$name]->labels->singular_name
                    : '';
            } else {
                if (!is_object($types[$name])) {
                    return $types;
                }

                if ($order_property) {
                    $ordered_types[$name] = (isset($types[$name]->$order_property))
                        ? $types[$name]->$order_property
                        : '';
                } else {
                    $ordered_types[$name] = (isset($types[$name]->labels->$labels_property))
                        ? $types[$name]->labels->$labels_property
                        : '';
                }
            }
        }

        asort($ordered_types);

        foreach (array_keys($ordered_types) as $name) {
            $ordered_types[$name] = $types[$name];
        }

        return $ordered_types;
    }

    public function getModuleInfo($args = [])
    {
        $title = [
            'circles'                       => esc_html__('Access Circles', 'press-permit-core'),
            'collaboration'                 => esc_html__('Editing Permissions', 'press-permit-core'),
            'compatibility'                 => esc_html__('Compatibility Pack', 'press-permit-core'),
            'teaser'                        => esc_html__('Teaser', 'press-permit-core'),
            'status-control'                => esc_html__('Status Control', 'press-permit-core'),
            'file-access'                   => esc_html__('File Access', 'press-permit-core'),
            'membership'                    => esc_html__('Membership', 'press-permit-core'),
            'sync'                          => esc_html__('User Posts', 'press-permit-core'),
        ];

        $blurb = [
            'circles'                       => esc_html__('Visibility Circles and Editorial Circles block access to content not authored by other group members.', 'press-permit-core'),
            'collaboration'                 => esc_html__('Post-specific and category-specific permissions for creation and editing.', 'press-permit-core'),
            'compatibility'                 => esc_html__('Integration with ACF, bbPress, BuddyPress, Relevanssi, WPML and other plugins; enhanced Multisite support.', 'press-permit-core'),
            'teaser'                        => esc_html__('On the site front end, display teaser text for unreadable posts instead of hiding them.', 'press-permit-core'),
            'status-control'                => esc_html__('Customize access to custom publication workflow statuses or visibility statuses.', 'press-permit-core'),
            'file-access'                   => esc_html__("Restrict direct file requests based on user's access to the page a file is attached to.", 'press-permit-core'),
            'membership'                    => esc_html__('Time-limit access customizations by delaying or expiring Permission Group membership.', 'press-permit-core'),
            'sync'                          => esc_html__('Auto-create a page for each user of specified roles. Compatible with several Team / Staff plugins.', 'press-permit-core'),
        ];

        $descript = [
            'circles'                       => esc_html__('Visibility Circles and Editorial Circles block access to content not authored by other group members. Any WP Role, BuddyPress Group or custom Group can be marked as a Circle for specified post types.', 'press-permit-core'),
            'collaboration'                 => esc_html__('Supports content-specific permissions for editing, term assignment and page parent selection. In combination with other modules, supports workflow statuses, PublishPress and PublishPress Revisions.', 'press-permit-core'),
            'compatibility'                 => esc_html__('Adds compatibility or integration with ACF, bbPress, Relevanssi, CMS Tree Page View, Custom Post Type UI, Subscribe2, WPML, various other plugins. Configure any BuddyPress Group as a Permissions Group. For multisite, provides network-wide Permission Groups.', 'press-permit-core'),
            'teaser'                        => esc_html__('On the site front end, replace non-readable content with placeholder text. Can be enabled for any post type. Custom filters are provided but no programming is required for basic usage.', 'press-permit-core'),
            'status-control'                => esc_html__('Custom post statuses: Workflow statuses allow unlimited orderable steps between pending and published, each with distinct capability requirements and role assignments. Statuses can be type-specific.', 'press-permit-core'),
            'file-access'                   => esc_html__("Filters direct file access, based on user's access to post(s) which the file is attached to. No additional configuration required. Creates/modifies .htaccess file in uploads folder (and in main folder for multisite).", 'press-permit-core'),
            'membership'                    => esc_html__('Allows Permission Group membership to be date-limited (delayed and/or scheduled for expiration). Simple date picker UI alongside group membership selection.', 'press-permit-core'),
            'sync'                          => esc_html__('Create or synchronize posts to match users. Designed for Team / Staff plugins, but with broad usage potential.', 'press-permit-core'),
        ];

        return (object) compact('title', 'blurb', 'descript');
    }

    public function isPluginAction()
    {
        return (!empty($_SERVER['REQUEST_URI']) && (false !== strpos(esc_url_raw($_SERVER['REQUEST_URI']), 'plugin-install.php')))
            || PWP::is_REQUEST('action', ['activate', 'deactivate']);
    }

    public function errorNotice($err_slug, $args)
    {
        require_once(PRESSPERMIT_CLASSPATH . '/ErrorNotice.php');
        return new \PublishPress\Permissions\ErrorNotice($err_slug, $args);
    }

    public function notice($notice, $msg_id = '')
    {
        $dismissals = (array) pp_get_option('dismissals');

        if ($msg_id && isset($dismissals[$msg_id]) && !PWP::is_REQUEST('pp_ignore_dismissal', $msg_id)) {
            return;
        }

        require_once(PRESSPERMIT_CLASSPATH . '/ErrorNotice.php');
        $err = new \PublishPress\Permissions\ErrorNotice();
        $err->addNotice($notice, ['id' => $msg_id]);
    }

    function publishpressFooter()
    {
?>
        <footer>

            <div class="pp-rating">
                <a href="https://wordpress.org/support/plugin/press-permit-core/reviews/#new-post" target="_blank" rel="noopener noreferrer">
                    <?php printf(
                        esc_html__('If you like %s, please leave us a %s rating. Thank you!', 'press-permit-core'),
                        '<strong>PublishPress Permissions</strong>',
                        '<span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span>'
                    );
                    ?>
                </a>
            </div>

            <hr>
            <nav>
                <ul>
                    <li><a href="https://publishpress.com/permissions" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e('About PublishPress Permissions', 'press-permit-core'); ?>"><?php esc_html_e('About', 'press-permit-core'); ?>
                        </a></li>
                    <li><a href="https://publishpress.com/documentation/permissions-start/" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e('Permissions Documentation', 'press-permit-core'); ?>"><?php esc_html_e('Documentation', 'press-permit-core'); ?>
                        </a></li>
                    <li><a href="https://publishpress.com/contact" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e('Contact the PublishPress team', 'press-permit-core'); ?>"><?php esc_html_e('Contact', 'press-permit-core'); ?>
                        </a></li>
                </ul>
            </nav>

            <div class="pp-pressshack-logo">
                <a href="//publishpress.com" target="_blank" rel="noopener noreferrer">
                    <img src="<?php echo esc_url(plugins_url('', PRESSPERMIT_FILE)) . '/common/img/publishpress-logo.png'; ?>" />
                </a>
            </div>

        </footer>
<?php
    }
}

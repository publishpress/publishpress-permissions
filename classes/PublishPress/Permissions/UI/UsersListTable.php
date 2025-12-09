<?php

namespace PublishPress\Permissions\UI;

class UsersListTable extends \WP_List_Table
{
    protected $_column_headers;
    public $user_ids;

    public function __construct()
    {
        global $_wp_column_headers;
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && isset($_wp_column_headers[$screen->id])) {
                unset($_wp_column_headers[$screen->id]);
            }
        }
        parent::__construct([
            'singular' => 'User',
            'plural' => 'Users',
            'ajax' => false,
        ]);

        // Add custom query filter for users
        require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/UsersListing.php');
        new Dashboard\UsersListing();

        add_filter('pre_user_query', ['\PublishPress\Permissions\UI\Dashboard\UsersListing', 'fltUserQueryExceptions']);
    }

    public function get_columns()
    {
        $any_group_perms_filter = PluginPage::viewFilter('pp_has_perms') || PluginPage::viewFilter('pp_has_exceptions') || PluginPage::viewFilter('pp_has_roles');

        $columns = [
            'cb' => '<input type = "checkbox" />',
            'user_login' => esc_html__('Username', 'press-permit-core'),
            'name' => esc_html__('Name', 'press-permit-core'),
            'user_email' => esc_html__('Email', 'press-permit-core'),
            'pp_groups' => esc_html__('Groups', 'press-permit-core'),
            'pp_roles' => esc_html__('Roles', 'press-permit-core'),
            'pp_exceptions' => sprintf(
                (!$any_group_perms_filter) ? esc_html__('Specific Permissions %1$s', 'press-permit-core') : esc_html__('Specific Permissions %1$s', 'press-permit-core'),
                (!$any_group_perms_filter) ? $this->generateTooltip(__('Specific Permissions assigned directly to the user', 'press-permit-core')) : $this->generateTooltip(__('Specific Permissions assigned to the user or one of their groups', 'press-permit-core'))
            ),
            'pp_roles' => sprintf(
                (!$any_group_perms_filter) ? esc_html__('User Roles %1$s', 'press-permit-core') : esc_html__('Roles %1$s', 'press-permit-core'),
                (!$any_group_perms_filter) ? $this->generateTooltip(__('Roles assigned directly to the user', 'press-permit-core')) : $this->generateTooltip(__('Roles assigned to the user or one of their groups', 'press-permit-core'))
            ),
        ];
        return $columns;
    }

    private function generateTooltip($tooltip, $text = '', $position = 'top', $useIcon = true)
    {
        ob_start();
        ?>
        <span data-toggle="tooltip" data-placement="<?php esc_attr_e($position); ?>">
        <?php esc_html_e($text);?>
        <span class="tooltip-text"><span><?php esc_html_e($tooltip);?></span><i></i></span>
        <?php 
        if ($useIcon) : ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 50 50" style="margin-left: 4px; vertical-align: text-bottom;">
                <path d="M 25 2 C 12.264481 2 2 12.264481 2 25 C 2 37.735519 12.264481 48 25 48 C 37.735519 48 48 37.735519 48 25 C 48 12.264481 37.735519 2 25 2 z M 25 4 C 36.664481 4 46 13.335519 46 25 C 46 36.664481 36.664481 46 25 46 C 13.335519 46 4 36.664481 4 25 C 4 13.335519 13.335519 4 25 4 z M 25 11 A 3 3 0 0 0 25 17 A 3 3 0 0 0 25 11 z M 21 21 L 21 23 L 23 23 L 23 36 L 21 36 L 21 38 L 29 38 L 29 36 L 27 36 L 27 21 L 21 21 z"></path>
            </svg>
        <?php
        endif; ?>
        </span>
        <?php

        return ob_get_clean();
    }

    public function get_sortable_columns()
    {
        return [
            'user_login' => ['user_login', false],
            'name' => ['display_name', false],
            'user_email' => ['user_email', false],
        ];
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="users[]" value="%s" />',
            esc_attr($item->ID)
        );
    }

    public function get_bulk_actions()
    {
        return [
            'delete' => esc_html__('Delete', 'press-permit-core'),
        ];
    }

    public function prepare_items()
    {
        $per_page = 10;
        $paged = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';                          // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'user_login';   // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'asc';                // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $args = [
            'number' => $per_page,
            'offset' => ($paged - 1) * $per_page,
            'search' => $search ? '*' . $search . '*' : '',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'orderby' => $orderby,
            'order' => $order,
        ];

        $users_query = new \WP_User_Query($args);
        $this->items = $users_query->get_results();

        $this->user_ids = array_map(function ($u) {
            return $u->ID;
        }, $this->items);

        $this->set_pagination_args([
            'total_items' => $users_query->get_total(),
            'per_page' => $per_page,
            'total_pages' => ceil($users_query->get_total() / $per_page),
        ]);
    }

    // Custom column: Name
    public function column_name($item)
    {
        return esc_html($item->display_name);
    }

    // Custom column: Groups
    public function column_pp_groups($item)
    {
        return apply_filters('manage_users_custom_column', '', 'pp_groups', $item->ID, ['table_obj' => $this]);
    }

    // Custom column: Roles (with anchor)
    public function column_pp_roles($item)
    {
        $join_groups = PluginPage::viewFilter('pp_has_perms') || PluginPage::viewFilter('pp_has_exceptions') || PluginPage::viewFilter('pp_has_roles');
        return apply_filters('manage_users_custom_column', '', 'pp_roles', $item->ID, ['join_groups' => $join_groups, 'table_obj' => $this]);
    }

    // Custom column: Specific Permissions
    public function column_pp_exceptions($item)
    {
        $join_groups = PluginPage::viewFilter('pp_has_perms') || PluginPage::viewFilter('pp_has_exceptions') || PluginPage::viewFilter('pp_has_roles');
        return apply_filters('manage_users_custom_column', '', 'pp_exceptions', $item->ID, ['join_groups' => $join_groups, 'table_obj' => $this]);
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'user_login':
                $edit_permissions_url = esc_url(admin_url('admin.php?page=presspermit-edit-permissions&action=edit&agent_id=' . $item->ID . '&agent_type=user'));
                $edit_user_url = get_edit_user_link($item->ID);

                $row_actions = $this->row_actions([
                    'edit-permissions' => '<a href="' . $edit_permissions_url . '">' . esc_html__('Permissions', 'press-permit-core') . '</a>',
                    'edit' => '<a href="' . esc_url($edit_user_url) . '">' . esc_html__('Edit User', 'press-permit-core') . '</a>',
                ]);

                return '<strong><a href="' . esc_url($edit_permissions_url) . '">' . esc_html($item->user_login) . '</a></strong><br>' . $row_actions;
            case 'user_email':
                return '<a href="mailto:' . esc_attr($item->user_email) . '">' . esc_html($item->user_email) . '</a>';
            default:
                return '';
        }
    }


}

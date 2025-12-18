<?php

namespace PublishPress\Permissions\UI;

require_once(PRESSPERMIT_CLASSPATH . '/UI/GroupsListTableBase.php');
require_once(PRESSPERMIT_CLASSPATH . '/UI/GroupsQuery.php');
require_once(PRESSPERMIT_CLASSPATH . '/DB/Groups.php');
require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsMeta.php');

do_action('presspermit_groups_list_table_load');

class GroupsListTable extends GroupsListTableBase
{
    public $role_info;
    private $listed_ids = [];
    private $agent_type;
    private $group_variant;

    public function __construct($args = [])
    {
        global $_wp_column_headers;

        $screen = get_current_screen();

        // clear out empty entry from initial admin_header.php execution
        if (isset($_wp_column_headers[$screen->id])) {
            unset($_wp_column_headers[$screen->id]);
        }

        parent::__construct(array(
            'singular' => 'group',
            'plural' => 'groups',
        ));

        if (isset($args['agent_type'])) {
            $this->agent_type = $args['agent_type'];
        }

        if (isset($args['group_variant'])) {
            $this->group_variant = $args['group_variant'];
        }
    }

    public function getAgentType()
    {
        return $this->agent_type;
    }

    public function ajax_user_can()
    {
        return current_user_can('pp_edit_groups') || presspermit()->groups()->anyGroupManager();
    }

    public function prepare_items()
    {
        global $groupsearch;

        // phpcs Note: nonce verification unnecessary for groups listing search / orderby / order parameters

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $groupsearch = (!empty($_REQUEST['s'])) ? sanitize_text_field($_REQUEST['s']) : '';

        $groups_per_page = $this->get_items_per_page('groups_per_page');

        $paged = $this->get_pagenum();

        $args = [
            'number' => $groups_per_page,
            'offset' => ($paged - 1) * $groups_per_page,
            'search' => $groupsearch,
        ];

        $args['search'] = '*' . $args['search'] . '*';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($orderby = (!empty($_REQUEST['orderby'])) ? sanitize_sql_orderby($_REQUEST['orderby']) : '') {
            $args['orderby'] = $orderby;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($order = (!empty($_REQUEST['order'])) ? sanitize_sql_orderby($_REQUEST['order']) : '') {
            $args['order'] = $order;
        }

        // Query the user IDs for this page
        $args['agent_type'] = $this->agent_type;
        $args['group_variant'] = $this->group_variant;
        $group_search = new GroupQuery($args);

        $this->items = $group_search->get_results();
        $this->listed_ids = [];

        foreach ($this->items as $group) {
            $this->listed_ids[] = $group->ID;
        }

        $this->role_info = \PublishPress\Permissions\DB\PermissionsMeta::countRoles(
            $this->agent_type,
            ['query_agent_ids' => $this->listed_ids]
        );

        $this->exception_info = \PublishPress\Permissions\DB\PermissionsMeta::countExceptions(
            $this->agent_type,
            ['query_agent_ids' => $this->listed_ids]
        );

        $this->set_pagination_args(array(
            'total_items' => $group_search->get_total(),
            'per_page' => $groups_per_page,
        ));
    }

    public function no_items()
    {
        esc_html_e('No matching groups were found.', 'press-permit-core');
    }

    public function get_views()
    {
        return [];
    }

    private function deleted_roles_listed()
    {
        global $wp_roles;

        if (empty($this->items)) {
            return false;
        }

        foreach ($this->items as $group) {
            if (('wp_role' == $group->metagroup_type)) {
                $role_name = $group->metagroup_id;
                if (
                    !in_array($group->metagroup_id, ['wp_anon', 'wp_auth', 'wp_all'], true)
                    && !in_array($role_name, array_keys($wp_roles->role_names), true)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function get_bulk_actions()
    {
        return [];
    }

    public function get_columns()
    {
        $c = [
            'group_name' => esc_html__('Name', 'press-permit-core'),
            'group_type' => esc_html__('Type', 'press-permit-core'),
            'num_users' => _x('Users', 'count', 'press-permit-core'),
            'exceptions' => _x('Specific Permissions', 'count', 'press-permit-core'),
            'roles' => _x('Extra Roles', 'count', 'press-permit-core'),
            'description' => esc_html__('Description', 'press-permit-core'),
        ];

        $c = apply_filters('presspermit_manage_groups_columns', $c);

        return $c;
    }

    public function get_sortable_columns()
    {
        $c = [
            'group_name' => 'group_name',
        ];

        return $c;
    }

    public function display_rows()
    {
        foreach ($this->items as $group) {
            $this->single_row($group);
        }
    }

    /**
     * Generate HTML for a single row on the PP Role Groups admin panel.
     *
     * @param object $user_object
     * @param string $style Optional. Attributes added to the TR element.  Must be sanitized.
     * @param int $num_users Optional. User count to display for this group.
     * @return string
     */
    public function single_row($group)
    {
        static $base_url;
        static $members_cap;
        static $is_administrator;

        $pp = presspermit();
        $pp_groups = $pp->groups();

        if (!isset($base_url)) {
            $base_url = apply_filters('presspermit_groups_base_url', 'admin.php'); // todo: filter based on menu usage

            $is_administrator = $pp->isUserAdministrator();
        }

        $group_id = $group->ID;

        if ($group->metagroup_id) {
            if (('rvy_notice' == $group->metagroup_type) && !defined('PUBLISHPRESS_REVISIONS_VERSION') && !defined('REVISIONARY_VERSION')) {
                return;
            }

            $group->group_name = \PublishPress\Permissions\DB\Groups::getMetagroupName(
                $group->metagroup_type,
                $group->metagroup_id,
                $group->group_name
            );

            $group->group_description = \PublishPress\Permissions\DB\Groups::getMetagroupDescript(
                $group->metagroup_type,
                $group->metagroup_id,
                $group->group_description
            );
        }

        $group->group_name = stripslashes($group->group_name);
        $group->group_description = stripslashes($group->group_description);

        // Set up the hover actions for this user
        $actions = [];

        $can_manage_group = $is_administrator || $pp_groups->userCan('pp_edit_groups', $group_id, $this->agent_type);

        $agent_type_clause = (($this->agent_type) && ('pp_group' != $this->agent_type))
            ? "&amp;agent_type=$this->agent_type"
            : '';

        // Check if the group for this row is editable
        if ($can_manage_group) {
            $edit_link = $base_url . "?page=presspermit-edit-permissions&amp;action=edit{$agent_type_clause}&amp;agent_id={$group_id}";
            $actions['edit'] = '<a href="' . esc_url($edit_link) . '">' . esc_html(PWP::__wp('Edit')) . '</a>';
        } else {
            $edit_link = '';
        }

        $can_delete_group = $is_administrator || current_user_can('pp_delete_groups', $group_id);

        // allow metagroups for deleted / inactive wp roles to be deleted
        if (
            $can_delete_group
            && (!$group->metagroup_id
                || ('wp_role' == $group->metagroup_type && \PublishPress\Permissions\DB\Groups::isDeletedRole($group->metagroup_id)))
        ) {
            $actions['delete'] = "<a class='submitdelete' href='"
                . esc_url(wp_nonce_url($base_url . "?page=presspermit-groups&amp;pp_action=delete{$agent_type_clause}&amp;group=$group_id", 'pp-bulk-groups'))
                . "'>" . esc_html__('Delete') . "</a>";
        }

        $actions = apply_filters('presspermit_group_row_actions', $actions, $group);

        echo "<tr id='group-" . (int) $group_id . "'>";

        list($columns, $hidden) = $this->get_column_info();

        foreach ($columns as $column_name => $column_display_name) {
            $class = "$column_name column-$column_name";

            $style = (in_array($column_name, $hidden, true)) ? 'display:none;' : '';

            switch ($column_name) {
                case 'cb':
                    echo "<th scope='row' class='check-column'>";

                    // Set up the checkbox ( because the group or group members are editable, otherwise it's empty )
                    if (
                        $actions && (!$group->metagroup_id || (('wp_role' == $group->metagroup_type)
                            && \PublishPress\Permissions\DB\Groups::isDeletedRole($group->metagroup_id)))
                    ) {
                        echo "<input type='checkbox' name='groups[]' id='group_" . (int) $group_id . "' value='" . (int) $group_id . "' />";
                    }

                    echo '</th>';
                    break;

                case 'group_name':
                    echo "<td class='" . esc_attr($class) . "' style='" . esc_attr($style) . "'>";

                    // Check if the group for this row is editable
                    if ($can_manage_group) {
                        echo "<strong><a href='" . esc_url($edit_link) . "'>" . esc_html($group->group_name) . "</a></strong><br />";
                    } else {
                        echo '<strong>' . esc_html($group->group_name) . '</strong>';
                    }

                    $this->row_actions($actions);

                    echo '</td>';
                    break;

                case 'group_type':
                    echo "<td class='" . esc_attr($class) . "' style='" . esc_attr($style) . "'>";

                    switch ($group->metagroup_type) {
                        case 'wp_role':
                            switch ($group->metagroup_id) {
                                case 'wp_anon':
                                case 'wp_auth':
                                case 'wp_all':
                                    esc_html_e('Login State', 'press-permit-core');
                                    break;
                                default:
                                    esc_html_e('WordPress Role', 'press-permit-core');
                            }

                            break;
                        case '':
                            esc_html_e('Custom Group', 'press-permit-core');
                            break;
                        case 'rvy_notice':
                            esc_html_e('Workflow', 'press-permit-core');
                            break;
                        default:
                            if (!$type_caption = apply_filters('presspermit_group_type_caption', '', $group->metagroup_type)) {
                                $type_caption = ucwords(str_replace('_', ' ', $group->metagroup_type));
                            }

                            echo esc_html($type_caption);
                    }

                    echo '</td>';
                    break;
                case 'num_users':
                    echo "<td class='posts column-num_users num' style='" . esc_attr($style) . "'>";

                    if ('wp_role' == $group->metagroup_type) {
                        $num_users = $this->countRoleUsers($group->metagroup_id);
                    } else {
                        $num_users = $pp_groups->getGroupMembers($group_id, $this->agent_type, 'count');
                    }

                    if ('wp_role' == $group->metagroup_type) {
                        if (!in_array($group->metagroup_id, ['wp_anon', 'wp_all', 'wp_auth'])) {
                            $user_url = admin_url("users.php?role={$group->metagroup_id}");
                            echo "<a href='" . esc_url($user_url) . "'>" . (int) $num_users . "</a>";
                        }
                    } else {
                        echo (int) $num_users;
                    }

                    echo '</td>';
                    break;
                case 'roles':
                case 'exceptions':
                    echo "<td class='" . esc_attr($class) . "' style='" . esc_attr($style) . "'>";
                    $this->single_row_role_column($column_name, $group_id, $can_manage_group, $edit_link);
                    echo '</td>';
                    break;
                case 'description':
                    echo "<td class='" . esc_attr($class) . "' style='" . esc_attr($style) . "'>";
                    echo esc_html($group->group_description);
                    echo '</td>';
                    break;

                default:
                    echo "<td class='" . esc_attr($class) . "' style='" . esc_attr($style) . "'>";
                    do_action('presspermit_manage_groups_custom_column', $column_name, $group_id, $this);
                    echo '</td>';
            }
        }
        echo '</tr>';
    }

    /**
     * Displays the search box.
     *
     * @param string $text The 'submit' button label.
     * @param string $input_id ID attribute value for the search input field.
     * @param string $type Optional. The type of button. Accepts 'primary', 'secondary',
     *                                       or 'delete'. Default 'primary large'.
     * @param array|string $other_attributes Optional. Other attributes that should be output with the button,
     *                                       mapping attributes to their values, such as `array( 'tabindex' => '1' )`.
     *                                       These attributes will be output as `attribute="value"`, such as
     *                                       `tabindex="1"`. Other attributes can also be provided as a string such
     *                                       as `tabindex="1"`, though the array format is typically cleaner.
     *                                       Default empty.
     * @since 3.1.0
     * @access public
     *
     */
    public function search_box($text, $input_id, $type = '', $tabindex = '', $unused = '')
    {
        if (PWP::empty_REQUEST('s') && !$this->has_items()) {
            return;
        }

        $input_id = $input_id . '-search-input';

        if (!is_array($type)) {
            $type = explode(' ', $type);
        }

        $classes = [];
        foreach ($type as $t) {
            $classes[] = $t;
        }
        // Remove empty items, remove duplicate items, and finally build a string.
        $class = implode(' ', array_unique(array_filter($classes)));

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($orderby = (!empty($_REQUEST['orderby'])) ? sanitize_sql_orderby($_REQUEST['orderby']) : '') {
            echo '<input type="hidden" name="orderby" value="' . esc_attr($orderby) . '" />';
        }

        if ($order = PWP::REQUEST_key('order')) {
            echo '<input type="hidden" name="order" value="' . esc_attr($order) . '" />';
        }

        if ($post_mime_type = PWP::REQUEST_key('post_mime_type')) {
            echo '<input type="hidden" name="post_mime_type" value="' . esc_attr($post_mime_type) . '" />';
        }

        if ($detached = PWP::REQUEST_int('detached')) {
            echo '<input type="hidden" name="detached" value="' . esc_attr($detached) . '" />';
        } ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($text); ?>:</label>

            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query(); ?>"
                class="<?php echo esc_attr($class); ?>" <?php if ($tabindex)
                       echo ' tabindex="' . (int) $tabindex . '"'; ?> />

            <?php
            $attribs = ($tabindex) ? ['id' => 'search-submit', 'tabindex' => $tabindex + 1] : ['id' => 'search-submit'];
            submit_button($text, '', '', false, $attribs);
            ?>
        </p>
        <?php
    }

    protected function display_tablenav($which)
    {
        wp_nonce_field('pp-bulk-groups'); ?>
        <div class="tablenav <?php echo esc_attr($which); ?>">

            <?php if ($this->has_items()): ?>
                <div class="alignleft actions bulkactions">
                    <?php $this->bulk_actions($which); ?>
                </div>
                <?php
            endif;
            $this->extra_tablenav($which);
            $this->pagination($which);
            ?>

            <br class="clear" />
        </div>
        <?php
    }

    private function countRoleUsers($role_name)
    {
        static $role_count = [];

        if (!$role_count) {
            $_user_count = count_users();
            foreach ($_user_count['avail_roles'] as $_role_name => $count) {
                $role_count[$_role_name] = $count;
            }
        }

        return (isset($role_count[$role_name])) ? $role_count[$role_name] : 0;
    }
}

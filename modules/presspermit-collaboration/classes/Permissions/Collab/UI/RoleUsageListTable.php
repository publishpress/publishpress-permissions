<?php
namespace PublishPress\Permissions\Collab\UI;

require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/RoleUsageQuery.php');

class RoleUsageListTable extends \WP_List_Table
{
    var $site_id;
    var $role_info;

    private static $instance = null;

    public static function instance() {
        if ( is_null(self::$instance) ) {
            self::$instance = new RoleUsageListTable();
        }

        return self::$instance;
    }

    public function __construct() // PHP 5.6.x and some PHP 7.x configurations prohibit restrictive subclass constructors
    {
        $screen = get_current_screen();

        // clear out empty entry from initial admin_header.php execution
        global $_wp_column_headers;
        if (isset($_wp_column_headers[$screen->id]))
            unset($_wp_column_headers[$screen->id]);

        parent::__construct([
            'singular' => 'role',
            'plural' => 'roles'
        ]);
    }

    function ajax_user_can()
    {
        return current_user_can('pp_manage_settings');
    }

    function prepare_items()
    {
        // Query the user IDs for this page
        $search = new RoleUsageQuery();

        $this->items = $search->get_results();

        $this->set_pagination_args([
            'total_items' => $search->get_total(),
        ]);
    }

    function no_items()
    {
        esc_html_e('No matching roles were found.', 'press-permit-core');
    }

    function get_views()
    {
        return [];
    }

    function get_bulk_actions()
    {
        return [];
    }

    function get_columns()
    {
        $c = [
            'role_name' => PWP::__wp('Role'),
            'usage' => esc_html__('Usage', 'press-permit-core'),
        ];

        return $c;
    }

    function get_sortable_columns()
    {
        $c = [];

        return $c;
    }

    function display_rows()
    {
        foreach ($this->items as $role_object) {
            $this->single_row($role_object);
        }
    }

    function display_tablenav($which)
    {
        
    }

    /**
     * Generate HTML for a single row on the PP Role Groups admin panel.
     *
     * @param object $user_object
     * @param string $style Optional. Attributes added to the TR element.  Must be sanitized.
     * @param int $num_users Optional. User count to display for this group.
     * @return string
     */
    function single_row($role_obj)
    {
        static $base_url;

        $role_name = $role_obj->name;

        // Set up the hover actions for this user
        $actions = [];
        $checkbox = '';

        static $can_manage;
        if (!isset($can_manage)) {
            $can_manage = current_user_can('pp_manage_settings');
        }

        echo "<tr>";

        list($columns, $hidden) = $this->get_column_info();

        foreach ($columns as $column_name => $column_display_name) {
            $class = "$column_name column-$column_name";

            $style = (in_array($column_name, $hidden, true)) ? 'display:none;' : '';

            switch ($column_name) {
                case 'role_name':
                    echo "<td class='" . esc_attr($class) . "' style='" . esc_attr($style) . "'>";
                    
                    if ($can_manage) {
                        $edit_link = $base_url . "?page=presspermit-role-usage-edit&amp;action=edit&amp;role={$role_name}";
                        echo "<strong><a href='" . esc_url($edit_link) . "'>" . esc_html($role_obj->labels->singular_name) . "</a></strong><br />";
                    } else {
                        echo '<strong>' . esc_html($role_obj->labels->name) . '</strong>';
                    }
                
                	$mode = get_user_setting( 'posts_list_mode', 'list' );
                	$class = ('excerpt' === $mode) ? 'row-actions visible' : 'row-actions';

                    echo '<div class="' . esc_attr($class) . '">';

                    // Check if the group for this row is editable
                    if ($can_manage) {
                        echo "<span class='edit'><a href='" . esc_url($edit_link) . "'>" . esc_html(PWP::__wp('Edit')) . '</a></span>';
                    } else {
                        // temp workaround to prevent shrunken row
                        echo "<span class=''>&nbsp;</span>";
                    }

                    echo '</div>';
                    
                    echo "</td>";

                    break;
                case 'usage':
                    switch ($role_obj->usage) {
                        case 'direct':
                            $caption = esc_html__('Direct Assignment', 'press-permit-core');
                            break;
                            
                        default:
                            $caption = (empty($role_obj->usage)) 
                            ? esc_html__('no supplemental assignment', 'press-permit-core') 
                            : esc_html__('Pattern Role', 'press-permit-core');
                    }
                    echo "<td class='" . esc_attr($class) . "' style='" . esc_attr($style) . "'>";
                    echo esc_html($caption);
                    echo "</td>";
                    break;
                default:
                    echo "<td class='" . esc_attr($class) . "' style='" . esc_attr($style) . "'>";
                    do_action('presspermit_manage_role_usage_custom_column', '', $column_name, $role_obj);
                    echo "</td>";
            }
        }
        echo '</tr>';
    }

    function row_actions( $actions, $always_visible = false ) {

	}
}

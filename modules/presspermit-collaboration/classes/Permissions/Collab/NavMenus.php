<?php
namespace PublishPress\Permissions\Collab;

class NavMenus
{
    function __construct() {
        add_filter('presspermit_terms_skip_filtering', [$this, 'fltTermsSkipFiltering'], 10, 3);
        add_filter('get_terms_args', [$this, 'fltTermsArgs'], 40, 2);  // pp core filtering is at priority 50
        
        add_filter('pre_update_option_nav_menu_options', [$this, 'fltUpdateNavMenuOptions'], 10, 2);

        if (!defined('PP_NAV_MENU_DISABLE_POSTMETA_FILTER') && (!class_exists('NestedPages') || defined('PP_NAV_MENU_ENABLE_POSTMETA_FILTER'))) {
        	add_filter('update_post_metadata', [$this, 'fltUpdateNavMenuItemParent'], 10, 5);        
        }

        add_filter('nav_menu_meta_box_object', [$this, 'flt_nav_menu_edit_enable_filters']);
        add_filter('get_user_option_metaboxhidden_nav-menus', [$this, 'flt_nav_menus_set_metabox_args']);

        do_action('presspermit_nav_menu_filters');
    }

    public function fltTermsSkipFiltering($skip, $taxonomies, $args)
    {
        if (('nav_menu' != reset($taxonomies)))
            $skip = true;

        return $skip;
    }

    public function fltTermsArgs($args, $taxonomies)
    {
        if (('nav_menu' != reset($taxonomies)))
            $args['hide_empty'] = true;

        return $args;
    }

    public function flt_nav_menu_edit_enable_filters($args) {
        if (is_array($args)) {
            $args['suppress_filters'] = false;
            unset($args['post_status']);
        }

        return $args;
    }

    public function flt_nav_menus_set_metabox_args($option_val) {
        global $wp_meta_boxes;

        foreach(presspermit()->getEnabledPostTypes() as $post_type) {
            foreach(['core', 'default'] as $section) {
                if (isset($wp_meta_boxes['nav-menus']['side'][$section]["add-post-type-{$post_type}"])) {
                    $wp_meta_boxes['nav-menus']['side'][$section]["add-post-type-{$post_type}"]['args']->_default_query['suppress_filters'] = false;
                    unset($wp_meta_boxes['nav-menus']['side'][$section]["add-post-type-{$post_type}"]['args']->_default_query['post_status']);
                    
                    if (defined('PRESSPERMIT_EDIT_NAV_MENUS_NO_PAGING')) {
                        // phpcs Note: Non-standard configuration: Results paging is only disabled by constant definition

                        // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
                        $wp_meta_boxes['nav-menus']['side'][$section]["add-post-type-{$post_type}"]['args']->_default_query['posts_per_page'] = 9999;
                    }
                }
            }
        }

        foreach(presspermit()->getEnabledTaxonomies() as $taxonomy) {
            foreach(['core', 'default'] as $section) {
                if (isset($wp_meta_boxes['nav-menus']['side'][$section]["add-{$taxonomy}"])) {
                    $wp_meta_boxes['nav-menus']['side'][$section]["add-{$taxonomy}"]['args']->_default_query['suppress_filters'] = false;

                    if (defined('PRESSPERMIT_EDIT_NAV_MENUS_NO_PAGING')) {
                        // phpcs Note: Non-standard configuration: Results paging is only disabled by constant definition

                        // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
                        $wp_meta_boxes['nav-menus']['side'][$section]["add-{$taxonomy}"]['args']->_default_query['posts_per_page'] = 9999;
                    }
                }
            }
        }

        return $option_val;
    }

    public static function can_edit_theme_locs()
    {
        if (presspermit()->isContentAdministrator())
            return true;

        global $current_user;

        if ($tx = get_taxonomy('nav_menu')) {
            if (!empty($tx->cap->manage_terms)) {
                $manage_cap = $tx->cap->manage_terms;
            }
        }
        
        if (empty($manage_cap)) {
            $manage_cap = 'manage_nav_menus';
        }

        return !empty($current_user->allcaps[$manage_cap]) || (
            !defined('PP_STRICT_MENU_CAPS') && (!empty($current_user->allcaps['switch_themes']) || !empty($current_user->allcaps['edit_theme_options']))
        );
    }

    public static function is_unrestricted()
    {
        if (presspermit()->isContentAdministrator())
            return true;

        global $current_user;

        return (!empty($current_user->allcaps['switch_themes']) || !empty($current_user->allcaps['edit_theme_options']));
    }

    // make sure theme locations are not wiped because logged user has editing access to a subset of menus
    public static function guard_theme_locs($referer)
    {
        if ('update-nav_menu' == $referer) {
            if (!self::can_edit_theme_locs()) {
                if ($stored_locs = get_theme_mod('nav_menu_locations'))
                    $_POST['menu-locations'] = (array)$stored_locs;
                else
                    $_POST['menu-locations'] = [];
            }
        }
    }

    public static function can_edit_menu_item($menu_item_id, $args = [])
    {
        $defaults = ['menu_item_type' => '', 'object_type' => '', 'object_id' => 0];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();

        if (empty($args['force_check'])) {
            // This option enables uneditable menu items to be re-named.  Limitations are applied elsewhere to prevent other editing.
            return true;
        }

        if (!$object_type)
            $object_type = get_post_meta($menu_item_id, '_menu_item_object', true);

        if (!$object_id)
            $object_id = get_post_meta($menu_item_id, '_menu_item_object_id', true);

        if (!$menu_item_type)
            $menu_item_type = get_post_meta($menu_item_id, '_menu_item_type', true);

        global $current_user;
        $lock_custom = !$pp->isContentAdministrator() && empty($current_user->allcaps['edit_theme_options']) && $pp->getOption('admin_nav_menu_lock_custom');

        if ((('custom' == $menu_item_type) || !in_array($menu_item_type, ['post_type', 'taxonomy'], true)) && $lock_custom) {
            return false;
        } elseif (post_type_exists($object_type)) {
            return current_user_can('edit_post', $object_id);
        } elseif (taxonomy_exists($object_type)) {
            if ($tx_obj = get_taxonomy($object_type))
                return current_user_can($tx_obj->cap->manage_terms, $object_id);
        }

        // for menu item types we don't filter
        return true;
    }

    public static function flt_pre_update_post_meta($set_value, $object_id, $meta_key, $meta_value, $old_value)
    {
        // This option enables uneditable menu items to be re-named.  Additional restrictions are then needed to prevent inconsistent menu item moving.
        if (!$old_value && presspermit()->getOption('admin_nav_menu_partial_editing') && !self::is_unrestricted())
            return $old_value;

        return $set_value;
    }

    public static function act_updated_post_meta($meta_id, $object_id, $meta_key, $meta_value)
    {
        static $filtered_meta_ids;

        if (!defined('PPCE_RESTRICT_MENU_TOP_LEVEL') && !presspermit()->getOption('admin_nav_menu_partial_editing'))
            return;

        if ('_menu_item_menu_item_parent' != $meta_key)
            return;

        if (!isset($filtered_meta_ids))
            $filtered_meta_ids = [];

        if (isset($filtered_meta_ids[$meta_id]))
            return;

        $filtered_meta_ids[$meta_id] = true;

        $menu = self::determine_selected_menu();
        $meta_value = self::flt_menu_item_parent($meta_value, $object_id, $menu);

        update_post_meta($object_id, $meta_key, $meta_value);
    }

    public static function flt_police_menu_item_parent($post_parent, $object_id, $post_arr_keys, $post_arr)
    {
        $menu = self::determine_selected_menu();
        $post_parent = self::flt_menu_item_parent($post_parent, $object_id, $menu);

        return $post_parent;
    }

    public function fltUpdateNavMenuItemParent($bypass, $object_id, $meta_key, $meta_value, $prev_value)
    {
        static $in_progress = false;

        if (('_menu_item_menu_item_parent' == $meta_key) && !$in_progress) {
            $menu = self::determine_selected_menu();
            $post_parent = self::flt_menu_item_parent($meta_value, $object_id, $menu);

            if ($post_parent && ($post_parent != $meta_value)) {
                $in_progress = true;
                update_post_meta($object_id, $meta_key, $post_parent);
                $in_progress = false;

                $bypass = true;
            }
        }

        return $bypass;
    }

    public static function flt_menu_item_parent($post_parent, $object_id, $menu)
    {
        $stored_parent = get_post_meta($object_id, '_menu_item_menu_item_parent', true);

        $pp = presspermit();

        // This option enables uneditable menu items to be re-named.  Additional restrictions are then needed to prevent inconsistent menu item moving.
        if ($pp->getOption('admin_nav_menu_partial_editing') && !self::is_unrestricted()) {
            return $stored_parent;
        }

        if ($post_parent || !defined('PPCE_RESTRICT_MENU_TOP_LEVEL') || $pp->isContentAdministrator() || current_user_can('edit_theme_options'))
            return $post_parent;

        $menu_item = get_post($object_id);

        // if this menu item is already stored to top level, don't move it
        if (!$stored_parent && (false !== $stored_parent) && !PWP::is_REQUEST('action', 'add-menu-item')) {
            return $post_parent;
        }

        // move new menu items to alongside or under an existing editable item 
        $nav_menu_items = wp_get_nav_menu_items($menu, ['post_status' => 'any']);

        $editable_items = [];
        $editable_item_ids = [];
        $item_parents = [];

        $highest_editable_items = [];

        foreach ($nav_menu_items as $item) {
            if ($item->ID == $object_id)
                continue;

            if (self::can_edit_menu_item($item->ID, ['force_check' => true]) && !empty($item->post_date_gmt)) {
                $editable_items[] = $item;
                $editable_item_ids[] = $item->ID;
                $item_parents[$item->ID] = get_post_meta($item->ID, '_menu_item_menu_item_parent', true);
            }
        }

        // (Don't do this if there's more than one editable menu item because it prevents third-level items 
        // from being moved to second level if the top level item is hidden)
        //
        // If parent is being set to zero but this menu item is already stored as a sub-item, revert it.
        if (count($editable_items) < 2) {
            if (!$post_parent && $stored_parent && !PWP::is_REQUEST('action', 'add-menu-item')) {
                return $stored_parent;
            }
        }

        foreach ($editable_items as $item) {
            if (empty($item_parents[$item->ID])) {
                // since a top level item is editable, default this new/filtered menu item to under it
                return $item->ID;

            } elseif (!in_array($item_parents[$item->ID], $editable_item_ids)) {
                $highest_editable_items[] = $item;
            }
        }

        // next best is to default this menu item to below existing items at the highest editable level
        if ($highest_editable_items && !defined('PP_NAV_MENU_DEFAULT_TO_SUBITEM')) {
            $item = reset($highest_editable_items);
            return $item_parents[$item->ID];
        }

        foreach ($editable_items as $item) {
            $post_parent = (!empty($item_parents[$item->ID]) && !defined('PP_NAV_MENU_DEFAULT_TO_SUBITEM')) 
            ? $item_parents[$item->ID] 
            : $item->ID;

            break;
        }

        return $post_parent;
    }

    public static function modify_nav_menu_item($menu_item_id, $menu_operation)
    {
        if ($menu_item = get_post($menu_item_id)) {
            if ('nav_menu_item' == $menu_item->post_type) {
                $object_type = get_post_meta($menu_item_id, '_menu_item_object', true);
                $object_id = get_post_meta($menu_item_id, '_menu_item_object_id', true);

                if (!$is_post_type = post_type_exists($object_type))
                    $is_taxonomy = taxonomy_exists($object_type);

                // WP performs update on every item even if no values have changed
                if ('edit' == $menu_operation) {
                    $posted_vals = [];
                    foreach (
                        [
                        'title' => 'menu-item-title', 
                        'attribute' => 'menu-item-attr-title', 
                        'description' => 'menu-item-description', 
                        'target' => 'menu-item-target',
                        'classes' => 'menu-item-classes', 
                        'xfn' => 'menu-item-xfn', 
                        'menu_parent' => 'menu-item-parent-id',
                        'url' => 'menu-item-url',
                        ] as $property => $col
                    ) {
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                        if (isset($_POST[$col][$menu_item_id])) {

                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                            $posted_vals[$property] = sanitize_text_field($_POST[$col][$menu_item_id]);
                        }
                    }

                    if (isset($posted_vals['classes']))
                        $posted_vals['classes'] = array_map('sanitize_html_class', explode(' ', $posted_vals['classes']));

                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $menu_data = (!empty($_REQUEST['nav-menu-data'])) ? json_decode(stripslashes($_REQUEST['nav-menu-data'])) : [];

                    foreach($menu_data as $data_obj) {
                        if (!empty($data_obj->name) && ("menu-item-url[{$menu_item_id}]" == $data_obj->name)) {
                            $posted_vals['url'] = (!empty($data_obj->value)) ? $data_obj->value : '';
                        }
                    }

                    // If this option is enabled, allow the menu item title to be edited even if the item is generally uneditable
                    if (presspermit()->getOption('admin_nav_menu_partial_editing')) unset($posted_vals['title']);

                    $stored_vals = [];

					// Note: menu order is regulated by a different mechanism, so menu items can be inserted for editable posts
                    $check_fields = [
                        'title' => 'post_title', 
                        'attribute' => 'post_excerpt', 
                        'description' => 'post_content', 
                    ];

                    foreach ($check_fields as $property => $col) {
                        $stored_vals[$property] = trim($menu_item->$col);
                    }

                    $stored_vals['menu_parent'] = get_post_meta($menu_item_id, '_menu_item_menu_item_parent', true);
                    $stored_vals['target'] = get_post_meta($menu_item_id, '_menu_item_target', true);
                    $stored_vals['classes'] = (array)get_post_meta($menu_item_id, '_menu_item_classes', true);
                    $stored_vals['xfn'] = get_post_meta($menu_item_id, '_menu_item_xfn', true);

                    if (isset($posted_vals['url'])) {
                        $stored_vals['url'] = get_post_meta($menu_item_id, '_menu_item_url', true);
                    }

                    $stored_vals['title'] = ($is_post_type || ('custom' == get_post_meta($menu_item_id, '_menu_item_type', true))) 
                    ? get_post_field('post_title', $object_id) 
                    : get_term_field('name', $object_id, $object_type);

                    $changed = false;
                    foreach (array_keys($posted_vals) as $property) {
                        if ($posted_vals[$property] != $stored_vals[$property]) {
                            $changed = true;
                            break;
                        }
                    }

                    if (!$changed)
                        return;
                }

                global $current_user;

                if ($is_post_type) {
                    $deny_menu_operation = !current_user_can('edit_post', $object_id);
                } elseif ($is_taxonomy) {
                    if ($tx_obj = get_taxonomy($object_type)) {
                        $deny_menu_operation = !current_user_can($tx_obj->cap->manage_terms, $object_id);
                    }
                } elseif (!presspermit()->isContentAdministrator() && empty($current_user->allcaps['edit_theme_options']) && presspermit()->getOption('admin_nav_menu_lock_custom')) {
                    if ('custom' == get_post_meta($menu_item_id, '_menu_item_type', true)) {
                        $stored_vals['title'] = get_post_field('post_title', $menu_item_id);
                        
                        $deny_menu_operation = true;
                    }
                }

                if (!empty($deny_menu_operation)) {
                    if (empty($stored_vals['title']))
                        $stored_vals['title'] = $menu_item->post_title;

                    if (empty($stored_vals['title'])) {
                        $stored_vals['title'] = ($is_post_type) 
                        ? get_post_field('post_title', $object_id) 
                        : get_term_field('name', $object_id, $object_type);
                    }

                    $link = admin_url('nav-menus.php');

                    switch ($menu_operation) {
                        case 'move':
                            wp_die(
                                sprintf(
                                    esc_html__('You do not have permission to move the menu item "%1$s"', 'press-permit-core'), 
                                    esc_html($stored_vals['title'])
                                ) 
                                . '<br /><br /><a href="' . esc_url($link) . '">'
                                . esc_html__('Return to Menu Editor', 'press-permit-core') . '</a>'
                            );
                            break;
                        case 'delete':
                            wp_die(
                                sprintf(
                                    esc_html__('You do not have permission to delete the menu item "%1$s"', 'press-permit-core'), 
                                    esc_html($stored_vals['title'])
                                ) 
                                . '<br /><br /><a href="' . esc_url($link) . '">'
                                . esc_html__('Return to Menu Editor', 'press-permit-core') . '</a>'
                            );
                            break;
                        default:
                            wp_die(
                                sprintf(
                                    esc_html__('You do not have permission to edit the menu item "%1$s"', 'press-permit-core'), 
                                    esc_html($stored_vals['title'])
                                ) 
                                . '<br /><br /><a href="' . esc_url($link) . '">'
                                . esc_html__('Return to Menu Editor', 'press-permit-core') . '</a>'
                            );
                    } // end switch
                }
            }
        }
    }

    // transplanted from nav-menus.php
    public static function determine_selected_menu()
    {
        $nav_menus = wp_get_nav_menus(['orderby' => 'name']);

        // Get recently edited nav menu
        $recently_edited = (int)get_user_option('nav_menu_recently_edited');

        $menu_count = count($nav_menus);

        // Are we on the add new screen?
        $add_new_screen = PWP::is_GET('menu', 0) ? true : false;

        $locations_screen = PWP::is_GET('action', 'locations') ? true : false;

        // If we have one theme location, and zero menus, we take them right into editing their first menu
        $page_count = wp_count_posts('page');

        $one_theme_location_no_menus = (1 == count(get_registered_nav_menus()) && !$add_new_screen && empty($nav_menus) && !empty($page_count->publish)) 
        ? true 
        : false;

        $nav_menu_selected_id = PWP::REQUEST_int('menu');

        if (empty($recently_edited) && is_nav_menu($nav_menu_selected_id))
            $recently_edited = $nav_menu_selected_id;

        // Use $recently_edited if none are selected
        if (empty($nav_menu_selected_id) && PWP::empty_GET('menu') && is_nav_menu($recently_edited)) {
            $nav_menu_selected_id = $recently_edited;
        }

        // On deletion of menu, if another menu exists, show it
        if (!$add_new_screen && 0 < $menu_count && PWP::is_GET('action', 'delete')) {
            $nav_menu_selected_id = $nav_menus[0]->term_id;
        }

        // Set $nav_menu_selected_id to 0 if no menus
        if ($one_theme_location_no_menus) {
            $nav_menu_selected_id = 0;
        } elseif (empty($nav_menu_selected_id) && !empty($nav_menus) && !$add_new_screen) {
            // if we have no selection yet, and we have menus, set to the first one in the list
            $nav_menu_selected_id = $nav_menus[0]->term_id;
        }

        return $nav_menu_selected_id;
    }

    public static function can_edit_menu_settings()
    {
        global $current_user;

        $page_type_obj = get_post_type_object('page');

        return presspermit()->isUserUnfiltered($current_user->ID, ['post_type' => 'page']) || defined('PP_LEGACY_MENU_SETTINGS_ACCESS') 
        || !empty($current_user->allcaps['manage_menu_settings'])
        || (!empty($current_user->allcaps[$page_type_obj->cap->edit_others_posts]) && !empty($current_user->allcaps[$page_type_obj->cap->publish_posts]));
    }

    public function fltUpdateNavMenuOptions($new_option_value, $old_option_value)
    {
        if (!self::can_edit_menu_settings()) {
            $new_option_value = $old_option_value;

            // The following sample code is left for possible future need to allow editing of some menu options while locking others
            
            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            /*
            $menu_id = self::determine_selected_menu();
            if ( ! $menu_id && isset( $_REQUEST['menu'] ) )
                $menu_id = PWP::sanitizeEntry($_REQUEST['menu']);
            
            if ( ! $menu_id )
                return $new_option_value;
            
            if ( isset( $old_option_value['auto_add'] ) ) {
                $old_key = array_search( $menu_id, $old_option_value['auto_add'] );
            }
            
            if ( isset( $new_option_value['auto_add'] ) ) {
                $new_key = array_search( $menu_id, $new_option_value['auto_add'] );
                
                if ( false !== $new_key ) {
                    if ( isset($old_key) && false !== $old_key )
                        $new_option_value['auto_add'][$new_key] = $new_option_value['auto_add'][$old_key];
                    else
                        unset( $new_option_value['auto_add'][$new_key] );
                } else {
                    if ( isset($old_key) && false !== $old_key )
                        $new_option_value['auto_add'][] = $old_option_value['auto_add'][$old_key];
                }
            }
            */
        }

        return $new_option_value;
    }
}

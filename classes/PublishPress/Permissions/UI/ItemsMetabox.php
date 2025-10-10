<?php

namespace PublishPress\Permissions\UI;

/**
 * Post / Term metabox UI, ported by Kevin Behrens from wp-admin/nav-menus.php
 *
 */

require_once ABSPATH . 'wp-admin/includes/template.php';

/**
 * Create HTML list of nav menu input items.
 *
 * Ported from Walker_Nav_Menu_Checklist to eliminate hidden inputs which are not useful to PP usage on Edit Permissions screen
 */
class ItemsMetabox extends \Walker_Nav_Menu
{
    public function __construct($fields = false)
    {
        if ($fields) {
            $this->db_fields = $fields;
        }
    }

    public function start_lvl(&$output, $depth = 0, $args = [])
    {
        $indent = str_repeat("\t", $depth);
        $output .= "\n$indent<ul class='children'>\n";
    }

    public function end_lvl(&$output, $depth = 0, $args = [])
    {
        $indent = str_repeat("\t", $depth);
        $output .= "\n$indent</ul>";
    }

    /**
     * @param string $output Passed by reference. Used to append additional content.
     * @param object $item Menu item data object.
     * @param int $depth Depth of menu item. Used for padding.
     * @param object $args
     * @see Walker::start_el()
     * @since 3.0.0
     *
     */
    public function start_el(&$output, $item, $depth = 0, $args = [], $current_object_id = 0)
    {
        $indent = ($depth) ? str_repeat("\t", $depth) : '';

        $default_depth_display = (!empty($args->default_depth_display)) ? $args->default_depth_display : 1;

        $hide = ($depth > $default_depth_display - 1) ? ' style="display:none"' : '';

        // Add custom class if input value is 0
        $custom_class = ($item->object_id == 0) ? ' class="highlight-none"' : '';

        $output .= $indent . "<li{$hide}{$custom_class}>";

        $output .= '<input type="checkbox" class="menu-item-checkbox" value="' . esc_attr($item->object_id) . '" /> ';

        $output .= ($depth || (!empty($args->hierarchical) && (1 == $default_depth_display))) ? "<span> &ndash; </span>" : '';

        if (!empty($args->is_search_result) && !empty($args->hierarchical)) {
            require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');
            $title_attrib = ' title="' . esc_attr(\PressShack\Ancestry::getPostPath($item->object_id)) . '"';
        } else {
            $title_attrib = '';
        }

        $output .= "<label {$title_attrib}>";
        $output .= esc_html($item->title);
        $output .= '</label>';
    }

    /**
     * Displays a metabox for a post type menu item.
     *
     * @param string $object Not used.
     * @param string $post_type The post type object.
     */
    public static function post_type_meta_box($object, $post_type)
    {
        global $nav_menu_selected_id;

        $post_type_name = $post_type['args']->name;

        // paginate browsing for large numbers of post objects
        if (is_post_type_hierarchical($post_type_name)) {
            $per_page = defined('PP_ITEM_MENU_HIERARCHICAL_PER_PAGE') ? PP_ITEM_MENU_HIERARCHICAL_PER_PAGE : 25;
        } else {
            $per_page = defined('PP_ITEM_MENU_PER_PAGE') ? PP_ITEM_MENU_PER_PAGE : 25;
        }

        $current_tab = PWP::REQUEST_key($post_type_name . '-tab');

        $pagenum = $current_tab && PWP::is_REQUEST('paged') ? absint(PWP::REQUEST_int('paged')) : 1;
        $offset = 0 < $pagenum ? $per_page * ($pagenum - 1) : 0;

        $args = [
            'offset' => $offset,
            'order' => 'ASC',
            'orderby' => 'title',
            'posts_per_page' => $per_page,
            'post_type' => $post_type_name,
            'suppress_filters' => true,         // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFiltersTrue
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ];

        if (defined('PUBLISHPRESS_REVISIONS_VERSION') || defined('PP_EDIT_PERMISSIONS_POSTS_METABOX_FILTER')) {
            $args['suppress_filters'] = false;
        }

        if ('attachment' == $post_type_name) {
            $args['post_status'] = 'inherit';
        }

        if (isset($post_type['args']->_default_query)) {
            $args = array_merge($args, (array)$post_type['args']->_default_query);
        }

        $get_posts = new \WP_Query;
        $posts = $get_posts->query($args);
        if (!$get_posts->post_count) {
            echo '<p>' . esc_html__('No items.') . '</p>';
            return;
        }

        $num_pages = $get_posts->max_num_pages;

        if (!$posts) {
            $error = '<li id="error">' . $post_type['args']->labels->not_found . '</li>';
        }

        $db_fields = false;
        if ($hierarchical = is_post_type_hierarchical($post_type_name)) {
            $db_fields = ['parent' => 'post_parent', 'id' => 'ID'];
        }

        $walker = new ItemsMetabox($db_fields);

        if (!in_array($current_tab, ['most-recent', 'search'])) {
            $current_tab = 'all';
        }

        if (!PWP::empty_REQUEST('quick-search-posttype-' . $post_type_name)) {
            $current_tab = 'search';
        }

        $removed_args = [
            'action',
            'customlink-tab',
            'edit-menu-item',
            'menu-item',
            'page-tab',
            '_wpnonce',
        ];

        ?>
        <div id="posttype-<?php echo esc_attr($post_type_name); ?>" class="posttypediv<?php if ($hierarchical) {
            echo ' hierarchical';
        } ?>">

            <ul id="posttype-<?php echo esc_attr($post_type_name); ?>-tabs" class="posttype-tabs add-menu-item-tabs">

                <li <?php if ('all' == $current_tab) echo ' class="tabs"'; ?>>
                    <a class="nav-tab-link" href="<?php if ($nav_menu_selected_id) {
                        echo esc_url(add_query_arg($post_type_name . '-tab', 'all', remove_query_arg($removed_args)));
                    }
                    ?>#<?php echo esc_attr($post_type_name); ?>-all"><?php esc_html_e('View All'); ?>
                    </a></li>
                
                <li <?php if ('most-recent' == $current_tab) echo ' class="tabs"'; ?>>
                    <a class="nav-tab-link" href="<?php if ($nav_menu_selected_id) {
                        echo esc_url(add_query_arg($post_type_name . '-tab', 'most-recent', remove_query_arg($removed_args)));
                    }
                    ?>#tabs-panel-posttype-<?php echo esc_attr($post_type_name); ?>-most-recent"><?php esc_html_e('Most Recent'); ?>
                    </a></li>

                <li <?php if ('search' == $current_tab) echo ' class="tabs"'; ?>>
                    <a class="nav-tab-link" href="<?php if ($nav_menu_selected_id) {
                        echo esc_url(add_query_arg($post_type_name . '-tab', 'search', remove_query_arg($removed_args)));
                    }
                    ?>#tabs-panel-posttype-<?php echo esc_attr($post_type_name); ?>-search"><?php esc_html_e('Search'); ?>
                    </a></li>

            </ul>

            <div id="tabs-panel-posttype-<?php echo esc_attr($post_type_name); ?>-most-recent" class="tabs-panel <?php
            if ('most-recent' == $current_tab) echo 'tabs-panel-active'; else echo 'tabs-panel-inactive';
            ?>">
                <ul id="<?php echo esc_attr($post_type_name); ?>checklist-most-recent" class="categorychecklist form-no-clear">

                    <?php
                    $recent_args = array_merge(
                        $args, 
                        ['orderby' => 'post_date', 'order' => 'DESC', 'posts_per_page' => 15]  // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
                    );

                    $most_recent = $get_posts->query($recent_args);
                    $args['walker'] = $walker;
                    echo walk_nav_menu_tree(array_map([__CLASS__, 'setup_nav_menu_item'], $most_recent), 0, (object)$args);
                    ?>
                </ul>
            </div><!-- /.tabs-panel -->

            <div class="tabs-panel <?php
            if ('search' == $current_tab) echo 'tabs-panel-active'; else echo 'tabs-panel-inactive';
            ?>" id="tabs-panel-posttype-<?php echo esc_attr($post_type_name); ?>-search">

                <?php
                $searched = '';
                $search_results = [];

                if (!PWP::empty_REQUEST('quick-search-posttype-' . $post_type_name)) {
                    check_ajax_referer('pp-ajax');

                    $searched = (!empty($_REQUEST['quick-search-posttype-' . $post_type_name]))
                    ? sanitize_text_field($_REQUEST['quick-search-posttype-' . $post_type_name])
                    : '';

                    if ($searched) {
                        if (function_exists('_filter_query_attachment_filenames')) {
                            add_filter( "wp_allow_query_attachment_by_filename", "__return_true" );
                        }

                        $post_status = ('attachment' == $post_type_name) ? 'inherit' : '';
                        $search_results = query_posts(['s' => $searched, 'post_type' => $post_type_name, 'fields' => 'all', 'order' => 'DESC', 'post_status' => $post_status]);

                        remove_filter( "wp_allow_query_attachment_by_filename", "__return_true" );
                    }
                }
                ?>

                <p class="quick-search-wrap">
                    <input type="search" class="pp-quick-search input-with-default-title"
                        title="<?php esc_attr_e('Search'); ?>" value="<?php echo esc_attr($searched); ?>"
                        name="quick-search-posttype-<?php echo esc_attr($post_type_name); ?>"/>
                    <img class="waiting" style="display:none"
                        src="<?php echo esc_url(admin_url('images/wpspin_light.gif')); ?>" alt=""/>
                    <?php submit_button(esc_html__('Search'), 'quick-search-submit button-secondary hide-if-js', 'submit', false, ['id' => 'submit-quick-search-posttype-' . $post_type_name]); ?>
                </p>

                <ul id="<?php echo esc_attr($post_type_name); ?>-search-checklist"
                    class="list:<?php echo esc_attr($post_type_name); ?> categorychecklist form-no-clear">
                    <?php if (!empty($search_results) && !is_wp_error($search_results)) :
                        $args['walker'] = $walker;
                        echo walk_nav_menu_tree(array_map([__CLASS__, 'setup_nav_menu_item'], $search_results), 0, (object)$args);
                        ?>
                    <?php elseif (is_wp_error($search_results)) : ?>
                        <li><?php echo esc_html($search_results->get_error_message()); ?></li>
                    <?php elseif (!empty($searched)) : ?>
                        <li><?php esc_html_e('No results found.'); ?></li>
                    <?php endif; ?>
                </ul>

            </div><!-- /.tabs-panel -->

            <?php
            $page_links = paginate_links([
                'base' => add_query_arg(
                    [
                        $post_type_name . '-tab' => 'all',
                        'paged' => '%#%',
                        'item-type' => 'post_type',
                        'item-object' => $post_type_name,
                    ]
                ),
                'format' => '',
                'prev_text' => esc_html__('&laquo;'),
                'next_text' => esc_html__('&raquo;'),
                'total' => $num_pages,
                'current' => $pagenum,
            ]);
            ?>

            <div id="<?php echo esc_attr($post_type_name); ?>-all" class="tabs-panel tabs-panel-view-all<?php
            if ('all' == $current_tab) echo ' tabs-panel-active'; else echo ' tabs-panel-inactive';
            ?>">

                <?php if (!empty($page_links)) : ?>
                    <div class="add-menu-item-pagelinks">
                        <?php 
                                           // phpcs Note: page_links sanitized above by paginate_links() function
                        echo $page_links;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </div>
                <?php endif; ?>

                <ul id="<?php echo esc_attr($post_type_name); ?>checklist"
                    class="list:<?php echo esc_attr($post_type_name); ?> categorychecklist form-no-clear">

                    <?php
                    $args['walker'] = $walker;

                    // kevinB: add "(none)" item for include exceptions
                    $override_none = sprintf(esc_html__('None. All %ss will be hidden by default.', 'press-permit-core'), ucwords($post_type_name));
                    $front_page_obj = (object)['ID' => 0, 'post_parent' => 0, 'post_content' => '', 'post_excerpt' => '', 'post_title' => esc_html__('(none)', 'press-permit-core'), 'object_id' => 0, 'title' => $override_none, 'menu_item_parent' => 0, 'db_id' => 0];
                    $front_page_obj->_add_to_top = true;
                    $front_page_obj->label = esc_html__('(none)', 'press-permit-core');
                    array_unshift($posts, $front_page_obj);

                    $posts = apply_filters('nav_menu_items_' . $post_type_name, $posts, $args, $post_type);

                    if (is_post_type_hierarchical($post_type_name)) {
                        if (defined('PP_ITEM_MENU_FORCE_DISPLAY_DEPTH')) {
                            $default_depth_display = max(1, constant('PP_ITEM_MENU_FORCE_DISPLAY_DEPTH'));
                        } else {
                            require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');

                            $max_visible_items = (defined('PP_ITEM_MENU_DEFAULT_MAX_VISIBLE')) ? PP_ITEM_MENU_DEFAULT_MAX_VISIBLE : 50;

                            for ($default_depth_display = 10; $default_depth_display > 1; $default_depth_display--) {
                                $arr = \PressShack\Ancestry::getPageDescendants(0, ['post_type' => $post_type_name, 'pages' => $posts, 'max_depth' => $default_depth_display]);

                                if (count($arr) <= $max_visible_items) {
                                    break;
                                }
                            }
                        }

                        $args['default_depth_display'] = $default_depth_display;
                        $args['hierarchical'] = true;
                    }

                    $checkbox_items = walk_nav_menu_tree(array_map([__CLASS__, 'setup_nav_menu_item'], $posts), 0, (object)$args);

                    if ('all' == $current_tab && !PWP::empty_REQUEST('selectall')) {
                        $checkbox_items = preg_replace('/(type=(.)checkbox(\2))/', '$1 checked=$2checked$2', $checkbox_items);
                    }

                    echo $checkbox_items;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </ul>
                <?php if (!empty($page_links)) : ?>
                    <div class="add-menu-item-pagelinks">
                        <?php 
                                           // phpcs Note: page_links sanitized above by paginate_links() function
                        echo $page_links;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </div>
                <?php endif; ?>

            </div><!-- /.tabs-panel -->

            <p class="button-controls">
                <span class="list-controls">

                    <a href="<?php echo esc_url(add_query_arg([$post_type_name . '-tab' => 'all', 'selectall' => 1], remove_query_arg($removed_args)));
                    ?>#posttype-<?php echo esc_attr($post_type_name); ?>" class="select-all"><?php esc_html_e('Select All'); ?></a>

                </span>

                <span class="add-to-menu">
                    <img class="waiting" style="display:none"
                        src="<?php echo esc_url(admin_url('images/wpspin_light.gif')); ?>" alt=""/>

                    <input type="submit" <?php disabled($nav_menu_selected_id, 0); ?> class="button-secondary submit-add-item-exception"
                        value="<?php
                        esc_attr_e('Add Selected Permissions', 'press-permit-core'); ?>" name="add-post-type-menu-item"
                        id="submit-posttype-<?php echo esc_attr($post_type_name); ?>"/>

                </span>
            </p>

        </div> <!-- /.posttypediv -->

        <?php
    }

    // Displays a metabox for pp_group items.
    public static function group_meta_box($object, $post_type)
    {
        global $nav_menu_selected_id;

        $pp_groups = presspermit()->groups();

        $post_type_name = $post_type['args']->name;

        $current_tab = PWP::REQUEST_key($post_type_name . '-tab');

        // paginate browsing for large numbers of post objects
        $per_page = (defined('PP_ITEM_MENU_PER_PAGE')) ? PP_ITEM_MENU_PER_PAGE : 50;
        $pagenum = $current_tab && PWP::is_REQUEST('paged') ? absint(PWP::is_REQUEST('paged')) : 1;
        $offset = 0 < $pagenum ? $per_page * ($pagenum - 1) : 0;

        $args = [
            'offset' => $offset,
            'order' => 'ASC',
            'orderby' => 'title',
            'posts_per_page' => $per_page,
            'post_type' => $post_type_name,
            'suppress_filters' => ('pp_group' == $post_type) ? false : true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ];

        if ('attachment' == $post_type_name) {
            $args['post_status'] = 'inherit';
        }

        if (!$posts = $pp_groups->getGroups($post_type_name)) {
            echo '<p>' . esc_html__('No items.') . '</p>';
            return;
        }

        $num_pages = 1;

        if (!$posts) {
            $error = '<li id="error">' . $post_type['args']->labels->not_found . '</li>';
        }

        $db_fields = false;

        $db_fields = ['parent' => 'post_parent', 'id' => 'ID'];
        $walker = new ItemsMetabox($db_fields);

        if (!in_array($current_tab, ['most-recent', 'search'])) {
            $current_tab = 'all';
        }

        $removed_args = [
            'action',
            'customlink-tab',
            'edit-menu-item',
            'menu-item',
            'page-tab',
            '_wpnonce',
        ];

        $page_links = paginate_links([
            'base' => add_query_arg(
                [
                    $post_type_name . '-tab' => 'all',
                    'paged' => '%#%',
                    'item-type' => 'post_type',
                    'item-object' => $post_type_name,
                ]
            ),
            'format' => '',
            'prev_text' => esc_html__('&laquo;'),
            'next_text' => esc_html__('&raquo;'),
            'total' => $num_pages,
            'current' => $pagenum,
        ]);

        ?>
        <div id="posttype-<?php echo esc_attr($post_type_name); ?>" class="posttypediv">

            <ul id="posttype-<?php echo esc_attr($post_type_name); ?>-tabs" class="posttype-tabs add-menu-item-tabs">
                <li <?php if ('all' == $current_tab) echo ' class="tabs"'; ?>>
                    <a class="nav-tab-link" href="<?php if ($nav_menu_selected_id) {
                        echo esc_url(add_query_arg($post_type_name . '-tab', 'all', remove_query_arg($removed_args)));
                    }
                    ?>#<?php echo esc_attr($post_type_name); ?>-all"><?php esc_html_e('View All'); ?>
                    </a></li>

                <li <?php if ('most-recent' == $current_tab) echo ' class="tabs"';?>>
                    <a class="nav-tab-link" href="<?php if ($nav_menu_selected_id) {
                        echo esc_url(add_query_arg($post_type_name . '-tab', 'most-recent', remove_query_arg($removed_args)));
                    }
                    ?>#tabs-panel-posttype-<?php echo esc_attr($post_type_name); ?>-most-recent"><?php esc_html_e('Most Recent'); ?>
                    </a></li>
            </ul>

            <div id="<?php echo esc_attr($post_type_name); ?>-all" class="tabs-panel tabs-panel-view-all<?php
            echo('all' == $current_tab ? ' tabs-panel-active' : ' tabs-panel-inactive');
            ?>">

                <?php if (!empty($page_links)) : ?>
                    <div class="add-menu-item-pagelinks">
                        <?php 
                                           // phpcs Note: page_links sanitized above by paginate_links() function
                        echo $page_links;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </div>
                <?php endif; ?>

                <ul id="<?php echo esc_attr($post_type_name); ?>checklist"
                    class="list:<?php echo esc_attr($post_type_name); ?> categorychecklist form-no-clear">
                    <?php
                    $db_fields = ['parent' => 'post_parent', 'id' => 'ID'];
                    $walker = new ItemsMetabox($db_fields);
                    $args['walker'] = $walker;

                    $_args = ['skip_meta_types' => 'wp_role'];
                    $posts = $pp_groups->getGroups($post_type_name, $_args);
                    foreach (array_keys($posts) as $key) {
                        $posts[$key]->object_id = $posts[$key]->ID;
                        $posts[$key]->title = $posts[$key]->name;
                        $posts[$key]->post_parent = 0;
                        $posts[$key]->custom_source = $post_type_name;
                    }

                    $checkbox_items = walk_nav_menu_tree(array_map([__CLASS__, 'setup_nav_menu_item'], $posts), 0, (object)$args);

                    if ('all' == $current_tab && !PWP::empty_REQUEST('selectall')) {
                        $checkbox_items = preg_replace('/(type=(.)checkbox(\2))/', '$1 checked=$2checked$2', $checkbox_items);
                    }

                    echo $checkbox_items;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </ul>
                <?php if (!empty($page_links)) : ?>
                    <div class="add-menu-item-pagelinks">
                        <?php 
                                           // phpcs Note: page_links sanitized above by paginate_links() function
                        echo $page_links;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </div>
                <?php endif; ?>

            </div><!-- /.tabs-panel -->

            <p class="button-controls">
                <span class="list-controls">

                    <a href="<?php echo esc_url(add_query_arg([$post_type_name . '-tab' => 'all', 'selectall' => 1], remove_query_arg($removed_args)));
                    ?>#posttype-<?php echo esc_attr($post_type_name); ?>" class="select-all"><?php esc_html_e('Select All'); ?></a>

                </span>

                <span class="add-to-menu">
                    <img class="waiting" style="display:none"
                        src="<?php echo esc_url(admin_url('images/wpspin_light.gif')); ?>" alt=""/>

                    <input type="submit" <?php disabled($nav_menu_selected_id, 0); ?> class="button-secondary submit-add-item-exception submit-add-<?php
                    echo esc_attr($post_type_name);
                    ?>-exception" value="<?php esc_attr_e('Add Selected Permissions', 'press-permit-core'); ?>" name="add-post-type-menu-item"
                        id="submit-posttype-<?php echo esc_attr($post_type_name); ?>"/>

                </span>

            </p>

        </div> <!-- /.posttypediv -->

        <?php
    }

    /**
     * Displays a metabox for a taxonomy menu item.
     *
     * @param string $object Not used.
     * @param string $taxonomy The taxonomy object.
     */
    public static function taxonomy_meta_box($object, $taxonomy)
    {
        global $nav_menu_selected_id;
        $taxonomy_name = $taxonomy['args']->name;

        $current_tab = PWP::REQUEST_key($taxonomy_name . '-tab');

        // paginate browsing for large numbers of objects
        $per_page = 50;
        $pagenum = $current_tab && PWP::is_REQUEST('paged') ? absint(PWP::REQUEST_int('paged')) : 1;
        $offset = 0 < $pagenum ? $per_page * ($pagenum - 1) : 0;

        $args = [
            'child_of' => 0,
            'exclude' => '',        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
            'hide_empty' => false,
            'hierarchical' => 1,
            'include' => '',
            'number' => $per_page,
            'offset' => $offset,
            'order' => 'ASC',
            'orderby' => 'name',
            'pad_counts' => false,
        ];

        $terms = get_terms($taxonomy_name, $args);

        // kevinB: add "(none)" item for include exceptions
        $none_obj = (object)[
            'term_taxonomy_id' => 0,
            'parent' => 0,
            'term_id' => 0,
            'name' => esc_html__('(none)', 'press-permit-core'),
            'object_id' => 0,
            'title' => esc_html__('(none)', 'press-permit-core'),
            'menu_item_parent' => 0,
            'db_id' => 0
        ];

        $none_obj->_add_to_top = true;
        $none_obj->label = esc_html__('(none)', 'press-permit-core');
        array_unshift($terms, $none_obj);

        if (!$terms || is_wp_error($terms)) {
            echo '<p>' . esc_html__('No items.') . '</p>';
            return;
        }

        $num_pages = ceil(wp_count_terms($taxonomy_name, array_merge($args, ['number' => '', 'offset' => ''])) / $per_page);

        $page_links = paginate_links([
            'base' => add_query_arg(
                [
                    $taxonomy_name . '-tab' => 'all',
                    'paged' => '%#%',
                    'item-type' => 'taxonomy',
                    'item-object' => $taxonomy_name,
                ]
            ),
            'format' => '',
            'prev_text' => esc_html__('&laquo;'),
            'next_text' => esc_html__('&raquo;'),
            'total' => $num_pages,
            'current' => $pagenum,
        ]);

        $db_fields = false;
        if ($hierarchical = is_taxonomy_hierarchical($taxonomy_name)) {
            $db_fields = ['parent' => 'parent', 'id' => 'term_id'];
        }

        $walker = new ItemsMetabox($db_fields);

        if (!in_array($current_tab, ['all', 'most-used', 'search'])) {
            $current_tab = 'most-used';
        }

        if (!PWP::empty_REQUEST('quick-search-taxonomy-' . $taxonomy_name)) {
            $current_tab = 'search';
        }

        $removed_args = [
            'action',
            'customlink-tab',
            'edit-menu-item',
            'menu-item',
            'page-tab',
            '_wpnonce',
        ];

        ?>
        <div id="taxonomy-<?php echo esc_attr($taxonomy_name); ?>" class="taxonomydiv<?php if ($hierarchical) {
            echo ' hierarchical';
        }
        ?>">

            <ul id="taxonomy-<?php echo esc_attr($taxonomy_name); ?>-tabs" class="taxonomy-tabs add-menu-item-tabs">
                <li <?php if ('most-used' == $current_tab) echo ' class="tabs"'; ?>>
                    <a class="nav-tab-link" href="<?php
                    if ($nav_menu_selected_id) {
                        echo esc_url(add_query_arg($taxonomy_name . '-tab', 'most-used', remove_query_arg($removed_args)));
                    }
                    ?>#tabs-panel-<?php echo esc_attr($taxonomy_name); ?>-pop"><?php esc_html_e('Most Used'); ?>
                    </a></li>

                <li <?php if ('all' == $current_tab) echo ' class="tabs"'; ?>>
                    <a class="nav-tab-link" href="<?php if ($nav_menu_selected_id) {
                        echo esc_url(add_query_arg($taxonomy_name . '-tab', 'all', remove_query_arg($removed_args)));
                    }
                    ?>#tabs-panel-<?php echo esc_attr($taxonomy_name); ?>-all"><?php esc_html_e('View All'); ?>
                    </a></li>

                <li <?php if ('search' == $current_tab) echo ' class="tabs"'; ?>>
                    <a class="nav-tab-link" href="<?php if ($nav_menu_selected_id) {
                        echo esc_url(add_query_arg($taxonomy_name . '-tab', 'search', remove_query_arg($removed_args)));
                    }
                    ?>#tabs-panel-search-taxonomy-<?php echo esc_attr($taxonomy_name); ?>"><?php esc_html_e('Search'); ?>
                    </a></li>
            </ul>

            <div id="tabs-panel-<?php echo esc_attr($taxonomy_name); ?>-pop" class="tabs-panel <?php
            if ('most-used' == $current_tab) echo 'tabs-panel-active'; else echo 'tabs-panel-inactive';
            ?>">

                <ul id="<?php echo esc_attr($taxonomy_name); ?>checklist-pop" class="categorychecklist form-no-clear">
                    <?php
                    $popular_terms = get_terms($taxonomy_name, ['orderby' => 'count', 'order' => 'DESC', 'number' => 10, 'hierarchical' => false]);
                    $args['walker'] = $walker;
                    echo walk_nav_menu_tree(array_map([__CLASS__, 'setup_nav_menu_item'], $popular_terms), 0, (object)$args);
                    ?>
                </ul>

            </div><!-- /.tabs-panel -->

            <div id="tabs-panel-<?php echo esc_attr($taxonomy_name); ?>-all" class="tabs-panel tabs-panel-view-all<?php
            if ('all' == $current_tab) echo 'tabs-panel-active'; else echo ' tabs-panel-inactive';
            ?>">

                <?php if (!empty($page_links)) : ?>
                    <div class="add-menu-item-pagelinks">
                        <?php 
                                           // phpcs Note: page_links sanitized above by paginate_links() function
                        echo $page_links;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </div>
                <?php endif; ?>

                <ul id="<?php echo esc_attr($taxonomy_name); ?>checklist"
                    class="list:<?php echo esc_attr($taxonomy_name) ?> categorychecklist form-no-clear">
                    <?php
                    if (is_taxonomy_hierarchical($taxonomy_name)) {
                        if (defined('PP_ITEM_MENU_FORCE_DISPLAY_DEPTH')) {
                            $default_depth_display = max(1, constant('PP_ITEM_MENU_FORCE_DISPLAY_DEPTH'));
                        } else {
                            require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');

                            $max_visible_items = (defined('PP_ITEM_MENU_DEFAULT_MAX_VISIBLE')) ? PP_ITEM_MENU_DEFAULT_MAX_VISIBLE : 50;

                            for ($default_depth_display = 10; $default_depth_display > 1; $default_depth_display--) {
                                $arr = \PressShack\Ancestry::getTermDescendants(
                                    0,
                                    ['taxonomy' => $taxonomy_name, 'terms' => $terms, 'max_depth' => $default_depth_display]
                                );

                                if (count($arr) <= $max_visible_items) {
                                    break;
                                }
                            }
                        }

                        $args['default_depth_display'] = $default_depth_display;
                        $args['hierarchical'] = true;
                    }

                    $args['walker'] = $walker;
                    echo walk_nav_menu_tree(array_map([__CLASS__, 'setup_nav_menu_item'], $terms), 0, (object)$args);
                    ?>
                </ul>

                <?php if (!empty($page_links)) : ?>
                    <div class="add-menu-item-pagelinks">
                        <?php 
                                           // phpcs Note: page_links sanitized above by paginate_links() function
                        echo $page_links;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </div>
                <?php endif; ?>

            </div><!-- /.tabs-panel -->

            <div class="tabs-panel <?php
            if ('search' == $current_tab) echo 'tabs-panel-active'; else 'tabs-panel-inactive';
            ?>" id="tabs-panel-search-taxonomy-<?php echo esc_attr($taxonomy_name); ?>">

                <?php
                $searched = '';
                $search_results = [];

                if (!PWP::empty_REQUEST('quick-search-taxonomy-' . $taxonomy_name)) {
                    check_ajax_referer('pp-ajax');

                    $searched = (!empty($_REQUEST['quick-search-taxonomy-' . $taxonomy_name])) ? 
                    sanitize_text_field($_REQUEST['quick-search-taxonomy-' . $taxonomy_name]) 
                    : '';

                    if ($searched) {
                        $search_results = get_terms(
                            $taxonomy_name,
                            ['name__like' => $searched, 'fields' => 'all', 'orderby' => 'count', 'order' => 'DESC', 'hierarchical' => false]
                        );
                    }
                }
                ?>
                <p class="quick-search-wrap">
                    <input type="search" class="pp-quick-search input-with-default-title"
                        title="<?php esc_attr_e('Search'); ?>" value="<?php echo esc_attr($searched); ?>"
                        name="quick-search-taxonomy-<?php echo esc_attr($taxonomy_name); ?>"/>

                    <img class="waiting" style="display:none"
                        src="<?php echo esc_url(admin_url('images/wpspin_light.gif')); ?>" alt=""/>

                    <?php submit_button(
                        esc_html__('Search'),
                        'quick-search-submit button-secondary hide-if-js',
                        'submit',
                        false,
                        ['id' => 'submit-quick-search-taxonomy-' . $taxonomy_name]
                    );
                    ?>
                </p>

                <ul id="<?php echo esc_attr($taxonomy_name); ?>-search-checklist"
                    class="list:<?php echo esc_attr($taxonomy_name); ?> categorychecklist form-no-clear">
                    <?php if (!empty($search_results) && !is_wp_error($search_results)) : ?>
                        <?php
                        $args['walker'] = $walker;
                        echo walk_nav_menu_tree(array_map([__CLASS__, 'setup_nav_menu_item'], $search_results), 0, (object)$args);
                        ?>
                    <?php elseif (is_wp_error($search_results)) : ?>
                        <li><?php echo esc_html($search_results->get_error_message()); ?></li>
                    <?php elseif (!empty($searched)) : ?>
                        <li><?php esc_html_e('No results found.'); ?></li>
                    <?php endif; ?>
                </ul>

            </div><!-- /.tabs-panel -->

            <p class="button-controls">
                <span class="list-controls">
                    <a href="<?php echo esc_url(add_query_arg([$taxonomy_name . '-tab' => 'all', 'selectall' => 1], remove_query_arg($removed_args)));
                    ?>#taxonomy-<?php echo esc_attr($taxonomy_name); ?>" class="select-all"><?php esc_html_e('Select All'); ?>
                    </a>
                </span>

                <span class="add-to-menu">
                    <img class="waiting" style="display:none"
                        src="<?php echo esc_url(admin_url('images/wpspin_light.gif')); ?>" alt=""/>

                    <input type="submit" <?php disabled($nav_menu_selected_id, 0); ?> class="button-secondary submit-add-item-exception"
                        value="<?php esc_attr_e('Add Selected Permissions', 'press-permit-core'); ?>" name="add-taxonomy-menu-item"
                        id="submit-taxonomy-<?php echo esc_attr($taxonomy_name); ?>"/>
                </span>
            </p>

        </div><!-- /.taxonomydiv -->
        <?php
    }

    public static function item_menu_search_clause($search)
    {
        if (!defined('PP_ITEM_MENU_SEARCH_CONTENT')) {
            $search = str_replace('.post_content LIKE', '.post_title LIKE', $search);
        }

        if (!defined('PP_ITEM_MENU_SEARCH_EXCERPT')) {
            $search = str_replace('.post_excerpt LIKE', '.post_title LIKE', $search);
        }

        return $search;
    }

    /**
     * Prints the appropriate response to a menu quick search.
     *
     * @param array $request The unsanitized request values.
     */
    public static function ajax_menu_quick_search()
    {
        check_ajax_referer('pp-ajax');

        $args = [];
        $type = PWP::REQUEST_key('type');

        $query = (!empty($_REQUEST['q'])) ? sanitize_text_field($_REQUEST['q']) : '';

        $args['walker'] = new ItemsMetabox;
        $args['is_search_result'] = true;

        if (preg_match('/quick-search-(posttype|taxonomy)-([a-zA-Z_-]*\b)/', $type, $matches)) {
            if ('posttype' == $matches[1] && $type_obj = get_post_type_object($matches[2])) {
                $args['hierarchical'] = $type_obj->hierarchical;

                $status = ('attachment' == $matches[2]) ? 'inherit' : '';
                add_filter('posts_search', [__CLASS__, 'item_menu_search_clause']);

                add_filter( "wp_allow_query_attachment_by_filename", "__return_true" );

                // Query posts call executed on Edit Permissions screen quick search entry
                query_posts([
                    'posts_per_page' => 999,    // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
                    'post_type' => $matches[2],
                    's' => $query,
                    'orderby' => 'title',
                    'order' => 'ASC',
                    'post_status' => $status,
                ]);

                remove_filter( "wp_allow_query_attachment_by_filename", "__return_true" );

                remove_filter('posts_search', [__CLASS__, 'item_menu_search_clause']);

                require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');

                if (!have_posts()) {
                    return;
                }

                while (have_posts()) {
                    the_post();
                    $var_by_ref = get_the_ID();
                    echo walk_nav_menu_tree(array_map([__CLASS__, 'setup_nav_menu_item'], [get_post($var_by_ref)]), 0, (object)$args);
                }
            } elseif ('taxonomy' == $matches[1]) {
                $terms = get_terms($matches[2], [
                    'name__like' => $query,
                    'hide_empty' => false,
                    'number' => 99,
                ]);

                if (empty($terms) || is_wp_error($terms)) {
                    return;
                }

                foreach ((array)$terms as $term) {
                    echo walk_nav_menu_tree(array_map([__CLASS__, 'setup_nav_menu_item'], [$term]), 0, (object)$args);
                }
            }
        }

        exit;
    }

    /**
     * Decorates a menu item object with the shared navigation menu item properties.
     *
     * Properties:
     * - ID:               The term_id if the menu item represents a taxonomy term.
     * - db_id:            The DB ID of this item as a nav_menu_item object, if it exists (0 if it doesn't exist).
     * - menu_item_parent: The DB ID of the nav_menu_item that is this item's menu parent, if any. 0 otherwise.
     * - object:           The type of object originally represented, such as "category," "post", or "attachment."
     * - object_id:        The DB ID of the original object this menu item represents, e.g. ID for posts and term_id for categories.
     * - post_parent:      The DB ID of the original object's parent object, if any (0 otherwise).
     * - post_title:       A "no title" label if menu item represents a post that lacks a title.
     * - title:            The title of this menu item.
     * - type:             The family of objects originally represented, such as "post_type" or "taxonomy."
     * - _invalid:         Whether the menu item represents an object that no longer exists.
     *
     * @param object $menu_item The menu item to modify.
     * @return object $menu_item The menu item with standard menu item properties.
     */
    private static function setup_nav_menu_item($menu_item)
    {
        if (isset($menu_item->post_type)) {
            $menu_item->db_id = 0;
            $menu_item->menu_item_parent = 0;
            $menu_item->object_id = (int)$menu_item->ID;

            if ('' === $menu_item->post_title) {
                $menu_item->post_title = sprintf(esc_html__('#%d (no title)'), $menu_item->ID);
            }

            $menu_item->title = $menu_item->post_title;
        } elseif (isset($menu_item->taxonomy)) {
            $menu_item->db_id = 0;
            $menu_item->menu_item_parent = 0;
            $menu_item->object_id = (int)$menu_item->term_id;
            $menu_item->title = $menu_item->name;
        }

        return $menu_item;
    }
}

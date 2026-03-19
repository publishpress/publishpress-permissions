<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

class PostsListing
{
    function __construct() {
        if (defined('PRESSPERMIT_STATUSES_VERSION')) {
            add_action('admin_print_footer_scripts', [$this, 'act_modify_inline_edit_ui']);
        }

        add_action('admin_print_scripts', [$this, 'act_maybe_hide_quickedit']);
        
        if (!defined('PUBLISHPRESS_STATUSES_VERSION')) {
        	add_action('admin_head', [$this, 'act_register_column_filters']);
    	}
        
        add_action('init', [$this, 'act_tax_force_show_admin_col'], 99);

        if (defined('PRESSPERMIT_POSTS_ENABLE_PERF_MODE')) {
            add_filter('posts_clauses_request', [$this, 'fltPostsClausesRequest'], 999, 2);
        }
    }

    public function fltPostsClausesRequest($clauses, $query_obj) {
        global $wpdb;

        $clauses['fields'] = str_replace(
            "$wpdb->posts.*",
            "$wpdb->posts.ID, $wpdb->posts.post_title, $wpdb->posts.post_parent, $wpdb->posts.post_date,"
                        . " $wpdb->posts.post_author, $wpdb->posts.comment_status, $wpdb->posts.ping_status,"
                        . " $wpdb->posts.post_password, $wpdb->posts.to_ping, $wpdb->posts.pinged,"
                        . " $wpdb->posts.post_date_gmt, $wpdb->posts.post_status, $wpdb->posts.post_name,"
                        . " $wpdb->posts.post_modified, $wpdb->posts.post_modified_gmt, $wpdb->posts.guid,"
                        . " $wpdb->posts.menu_order, $wpdb->posts.comment_count, $wpdb->posts.post_mime_type",
            $clauses['fields']
        );

        $clauses['fields'] = str_replace(
            "p.*",
            "p.ID, p.post_title, p.post_parent, p.post_date,"
                        . " p.post_author, p.comment_status, p.ping_status,"
                        . " p.post_password, p.to_ping, p.pinged,"
                        . " p.post_date_gmt, p.post_status, p.post_name,"
                        . " p.post_modified, p.post_modified_gmt, p.guid,"
                        . " p.menu_order, p.comment_count, p.post_mime_type",
            $clauses['fields']
        );

        return $clauses;
    }

    public function act_tax_force_show_admin_col()
    {
        if (presspermit()->getOption('force_taxonomy_cols')) {
            global $wp_taxonomies;

            foreach (presspermit()->getEnabledTaxonomies(['object_type' => PWP::findPostType()], 'names') as $taxonomy) {
                $wp_taxonomies[$taxonomy]->show_admin_column = true;
            }
        }
    }

    public function act_register_column_filters()
    {
        global $typenow;
        
        if (!defined('PUBLISHPRESS_STATUSES_VERSION')) {
        	add_action("manage_{$typenow}_posts_custom_column", [$this, 'actManagePostsCustomColumn'], 10, 2);
    	}
    }

    public function actManagePostsCustomColumn($column_name, $id)
    {
        if ('status' == $column_name) {
            global $post;

            if (!in_array($post->post_status, ['draft', 'public', 'private', 'pending'], true)) {
                if ($status_obj = get_post_status_object($post->post_status)) {
                    if (!empty($status_obj->private) || (!empty($status_obj->moderation) && ('future' != $post->post_status))) {
                        echo esc_html($status_obj->label);
                    }
                }
            }
        }
    }

    // Quick Edit provides access to some properties which some content-specific editors cannot modify (Page parent, post/page visibility and status)
    // For now, avoid this complication and filtering overhead by turning off Quick Edit for users lacking site-wide edit_others capability
    public function act_maybe_hide_quickedit()
    {
        if (Collab::isLimitedEditor() && !current_user_can('pp_force_quick_edit')) {
            // todo: better quick/bulk edit support for limited editors
            ?>
            <style type="text/css">
                .editinline, div.tablenav div.actions select option[value="edit"] {
                    display: none;
                }
            </style>
            <?php
        }
    }

    public function act_modify_inline_edit_ui()
    {
        $screen = get_current_screen();

        $post_type = (!empty($screen) && isset($screen->post_type)) ? $screen->post_type : PWP::findPostType();

        if (!$post_type || apply_filters('presspermit_disable_object_cond_metaboxes', false, 'post', 0)) {
            return;
        }

        $post_type_object = get_post_type_object($screen->post_type);
        ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function ($) {
                <?php
                $pp = presspermit();
                $moderation_statuses = [];
                foreach (PWP::getPostStatuses(
                    ['_builtin' => false, 
                    'moderation' => true, 
                    'post_type' => $screen->post_type
                    ],

                    'object'
                    ) as $status => $status_obj 
                ) {
                    $set_status_cap = "set_{$status}_posts";

                    $check_cap = (!empty($post_type_object->cap->$set_status_cap)) 
                    ? $post_type_object->cap->$set_status_cap 
                    : $post_type_object->cap->publish_posts;

                    if ($pp->isContentAdministrator() || current_user_can($check_cap)) {
                        $moderation_statuses[$status] = $status_obj;
                    }
                }

                foreach( $moderation_statuses as $status => $status_obj ) :
                ?>
                if (!$('select[name="_status"] option[value="<?php echo esc_attr($status);?>"]').length) {
                    $('<option value="<?php echo esc_attr($status);?>"><?php echo esc_html($status_obj->label);?></option>').insertBefore('select[name="_status"] option[value="pending"]');
                }
                <?php endforeach;?>
            });
            /* ]]> */
        </script>
        <?php
    } // end function add_inline_edit_ui
}


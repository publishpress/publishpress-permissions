<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

\defined('ABSPATH') || exit;

class DashboardWidgetsFilters 
{
    function __construct() {
        if (!class_exists('Glance_That')) {
            add_action('dashboard_glance_items', [$this, 'act_right_now_pending']);
        	add_action('right_now_content_table_end', [$this, 'act_right_now_pending']);
        }
    }

    function act_right_now_pending()
    {
        $post_types = array_diff_key(get_post_types(['public' => true, 'show_ui' => true], 'object', 'or'), ['attachment' => true]);

        $moderation_statuses = ['pending' => get_post_status_object('pending')];
        $post_stati = get_post_stati([], 'object');
        foreach ($post_stati as $status_name => $status_obj) {
            if (!empty($status_obj->moderation)) {
                $moderation_statuses[$status_name] = $status_obj;
            }
        }

        $moderation_statuses = apply_filters('presspermit_order_statuses', $moderation_statuses);

        foreach ($post_types as $post_type => $post_type_obj) {
            if ($num_posts = wp_count_posts($post_type)) {
                foreach ($moderation_statuses as $status_name => $status_obj) {
                    if (!empty($num_posts->$status_name) && ('pending' != $status_name)) {
                        echo "\n\t" . '<div style="padding-bottom:4px">';

                        $num = number_format_i18n($num_posts->$status_name);

                        $type_clause = ('post' == $post_type) ? '' : "&post_type=$post_type";

                        $url = "edit.php?post_status=$status_name{$type_clause}";

                        $type_class = ($post_type_obj->hierarchical) ? 'b-pages' : 'b-posts';

                        echo '<td class="first b ' . esc_attr($type_class) . ' b-waiting">' . "<a href='" . esc_url($url) . "'><span class='pending-count'>" . esc_html($num) . "</span></a> " . '</td>';
                        echo '<td class="t posts">';
                        
                        echo "<a class='waiting' href='" . esc_url($url) . "'>";

                        if (intval($num_posts->$status_name) <= 1) {
                            printf(esc_html__('%1$s %2$s', 'press-permit-core'), esc_html($status_obj->label), esc_html($post_type_obj->labels->singular_name));
                        } else {
                            printf(esc_html__('%1$s %2$s', 'press-permit-core'), esc_html($status_obj->label), esc_html($post_type_obj->labels->name));
                        }

                        echo "</a>";

                        echo '</td>';
                        echo '<td class="b"></td>';
                        echo '<td class="last t"></td>';
                        echo "</div>\n\t";
                    }
                }
            }
        }

        echo '<div style="height:5px"></div>';
    }
}
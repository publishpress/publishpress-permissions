<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

class Users
{
    function __construct() {
        if (!is_network_admin()) {
            add_action('admin_print_footer_scripts', [$this, 'act_add_member_page_js']);
        }
    }

    public function act_add_member_page_js()
    {
        if (!presspermit()->getOption('add_author_pages')) {
            return;
        }

        $message = '';
        $message_class = '';
        if (!PWP::empty_REQUEST('ppmessage')) {
            switch (PWP::REQUEST_int('ppmessage')) {
                case 1:
                    if (PWP::is_REQUEST('ppcount')) {
                        $count = PWP::REQUEST_int('ppcount');
                        $message = sprintf(
                            _n('%s author page added', '%s author pages added', $count, 'press-permit-core'),
                            $count
                        );
                    }
                    $message_class = 'updated';
                    break;
                case 2:
                    $message = __('No users selected', 'press-permit-core');
                    $message_class = 'error';
                    break;
                case 3:
                    $message = __('Selected users already have specified author page', 'press-permit-core');
                    $message_class = 'error';
                    break;
            }
        }

        $post_types = get_post_types(['public' => true, 'show_ui' => true], 'object', 'or');
        unset($post_types['attachment']);
        foreach ($post_types as $post_type => $type_obj) {
            if (!current_user_can($type_obj->cap->edit_others_posts)) {
                unset($post_types[$post_type]);
            }
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $exclude = $wpdb->get_col("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_pp_auto_inserted'");

        presspermit_enqueue_admin_script();
        ?>
        <div class="pp-member-page-adder-config" data-message="<?php echo esc_attr($message); ?>" data-message-class="<?php echo esc_attr($message_class); ?>" hidden>
            <div id="member_page_adder" class="alignleft actions" style="margin-left:5px;padding-left:5px;margin-bottom:5px">
                <label class="screen-reader-text" for="member_page_type"><?php esc_html_e('Add Author Page&hellip;', 'press-permit-core'); ?></label>
                <select name="member_page_type" id="member_page_type" title="<?php esc_attr_e('make each selected user the author of a new page', 'press-permit-core'); ?>" autocomplete="off">
                    <option value=""><?php esc_html_e('Add Author Page&hellip;', 'press-permit-core'); ?></option>
                    <?php foreach ($post_types as $post_type => $type_obj) : ?>
                        <option value="<?php echo esc_attr($post_type); ?>"><?php echo esc_html($type_obj->labels->singular_name); ?></option>
                    <?php endforeach; ?>
                </select>

                <?php foreach ($post_types as $post_type => $type_obj) :
                    $total_posts = 0;
                    if ($type_obj->hierarchical) {
                        $total_posts = array_sum((array) wp_count_posts($post_type));
                    }

                    $input_name = "member_page_pattern_$post_type";
                    if (!$type_obj->hierarchical) {
                        $title = __('pattern post sets default content and categories for author post', 'press-permit-core');
                    } elseif ('page' == $post_type) {
                        $title = __('pattern post sets parent, default content and template for author page', 'press-permit-core');
                    } else {
                        $title = __('pattern page sets parent and default content for author page', 'press-permit-core');
                    }
                    ?>
                    <label class="screen-reader-text" for="<?php echo esc_attr($input_name); ?>"><?php esc_html_e('patterned on&hellip;', 'press-permit-core'); ?></label>
                    <span id="member_page_pattern_div_<?php echo esc_attr($post_type); ?>" class="member-page-pattern" style="display:none;margin-left:10px;" title="<?php echo esc_attr($title); ?>">
                        <?php
                        if ($type_obj->hierarchical && ($total_posts < 200)) {
                            echo wp_dropdown_pages( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                [
                                    'post_type' => $post_type,
                                    'name' => $input_name,
                                    'show_option_none' => esc_html__('patterned on...', 'press-permit-core'),
                                    'sort_column' => 'menu_order, post_title',
                                    'echo' => 0,
                                    'exclude' => array_map('intval', $exclude), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                                ]
                            );
                        } else {
                            esc_html_e('Pattern ID:', 'press-permit-core');
                            ?>
                            <input type="text" name="<?php echo esc_attr($input_name); ?>" id="<?php echo esc_attr($input_name); ?>" size="20" placeholder="<?php esc_attr_e('enter post ID/slug', 'press-permit-core'); ?>" />
                            <?php
                        }
                        ?>
                    </span>
                <?php endforeach; ?>

                <span id="member_page_title" style="display:none;margin-left:10px;margin-right:5px">
                    <?php esc_html_e('Title:', 'press-permit-core'); ?>
                    <input type="text" name="member_page_title" value="[username]" title="<?php esc_attr_e('supported tags are [username] and [userid]', 'press-permit-core'); ?>" size="30" />
                </span>
                <span id="member_page_add" style="display:none"><?php submit_button(esc_html__('Add Pages', 'press-permit-core'), 'secondary', 'add_member_page', false); ?></span>
                <?php wp_nonce_field('add-author-pages', '_pp_permissions_nonce'); ?>
            </div>
        </div>
        <?php
    } // end function add_member_page_js
} // end class

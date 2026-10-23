<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

\defined('ABSPATH') || exit;

class BulkEdit
{
    public static function add_author_pages()
    {
        check_admin_referer('add-author-pages', '_pp_permissions_nonce');

        $location = 'users.php';

        if ($referer = wp_get_referer()) {
            if (false !== strpos($referer, 'users.php'))
                $location = $referer;
        }

        $post_type = PWP::REQUEST_key('member_page_type');
        $type_obj = get_post_type_object($post_type);

        $users = (!empty($_REQUEST['users'])) ? array_map('intval', $_REQUEST['users']) : [];

        if (empty($users)) {
            $location = add_query_arg('ppmessage', 2, $location);

        } elseif (post_type_exists($post_type) && current_user_can($type_obj->cap->edit_others_posts) && current_user_can('edit_users')) {
            global $wpdb;

            $meta_keys = ['_wp_page_template', '_thumbnail_id'];
            $post_meta = [];
            $terms = [];

            if (defined('PP_AUTHOR_POST_META')) {
                $meta_keys = array_merge($meta_keys, explode(",", PP_AUTHOR_POST_META));
            }

            $pattern_id = PWP::REQUEST_int("member_page_pattern_{$post_type}");
            if ($pattern_id) {
                if (!is_numeric($pattern_id)) {
                    $slug = sanitize_key($pattern_id);

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $pattern_post = $wpdb->get_row($wpdb->prepare(
                        "SELECT ID, post_content, post_parent FROM $wpdb->posts WHERE post_name = %s AND post_type = %s LIMIT 1", 
                        $slug, 
                        $post_type
                    ));
                } else {
                    $pattern_post = get_post($pattern_id);
                }

                if (get_post_meta($pattern_id, '_pp_auto_inserted', true)) {
                    unset($pattern_post);
                }
            }
            if (!empty($pattern_post) && ($post_type == $pattern_post->post_type) && current_user_can('edit_post', $pattern_post->ID)) {
                $post_content = $pattern_post->post_content;
                $post_parent = $pattern_post->post_parent;
                $pattern_post_id = $pattern_post->ID;

                foreach ($meta_keys as $meta_key) {
                    $val = get_post_meta($pattern_post->ID, $meta_key, false);
                    if (count($val)) {
                        $post_meta[$meta_key] = reset($val);
                    }
                }

                $taxonomies = get_taxonomies();

                foreach ($taxonomies as $taxonomy) {
                    if ('link_category' == $taxonomy)
                        continue;

                    $terms[$taxonomy] = Collab::getObjectTerms($pattern_post->ID, $taxonomy, ['fields' => 'ids', 'pp_no_filter' => true]);
                }
            } else {
                $post_content = '';
                $post_parent = 0;
                $pattern_post_id = 0;
                $post_meta = [];
            }

            $post_meta['_pp_auto_inserted'] = "{$pattern_post_id}:{$post_parent}";

            // wp_insert_post() expects slashed input and removes one layer when saving the title.
            $title_pattern = !empty($_REQUEST['member_page_title']) ? wp_slash(sanitize_text_field(wp_unslash($_REQUEST['member_page_title']))) : '';

            if (false === strpos($title_pattern, '[username]') && false === strpos($title_pattern, '[userid]')) {
                $title_pattern .= ' [userid]';
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $users_done = $wpdb->get_col($wpdb->prepare(
                "SELECT p.post_author FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON pm.post_id = p.ID"
                . " WHERE pm.meta_key = '_pp_auto_inserted' AND pm.meta_value = %s", 
                
                "{$pattern_post_id}:{$post_parent}"
                )
            );
            
            $users = array_diff($users, $users_done);

            if (!count($users)) {
                $location = add_query_arg('ppmessage', 3, $location);
            } else {
                $pp = presspermit();

                $added = 0;
                foreach ($users as $user_id) {
                    if (!$user = new \WP_User($user_id))
                        continue;

                    if (current_user_can('edit_user', $user_id)) {
                        $post_title = str_replace('[username]', $user->display_name, $title_pattern);
                        $post_title = str_replace('[userid]', $user_id, $post_title);
                        $post_author = $user_id;

                        $post_status = ($pp->getOption('publish_author_pages')) ? 'publish' : 'draft';

                        $post_arr = compact('post_title', 'post_parent', 'post_author', 'post_content', 'post_type', 'post_status');

                        if ($post_id = wp_insert_post($post_arr)) {
                            foreach ($post_meta as $meta_key => $meta_value) {
                                update_post_meta($post_id, $meta_key, $meta_value);
                            }

                            foreach (array_keys($terms) as $taxonomy) {
                                if ($terms[$taxonomy]) {
                                    $terms[$taxonomy] = array_map('intval', $terms[$taxonomy]);
                                    wp_set_object_terms($post_id, $terms[$taxonomy], $taxonomy);
                                }
                            }
                        }

                        $added++;
                    }
                }

                $location = add_query_arg('ppmessage', 1, $location);
                $location = add_query_arg('ppcount', $added, $location);
            }
        }

        $location = remove_query_arg('err_promote', $location);

        wp_redirect($location);
        exit;
    }
} // end class

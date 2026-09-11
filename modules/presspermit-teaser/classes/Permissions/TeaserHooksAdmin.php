<?php
namespace PublishPress\Permissions;

class TeaserHooksAdmin
{
    function __construct()
    {
        add_action('presspermit_menu_handler', [$this, 'actMenuHandler']);
        add_action('presspermit_permissions_menu', [$this, 'act_permissions_menu'], 10, 2);

        add_action( 'wp_ajax_pp_search_posts', [$this, 'searchPosts'] );
        add_action( 'wp_ajax_pp_get_teaser_preview_post', [$this, 'getTeaserPreviewPost'] );
        add_action( 'wp_ajax_pp_search_terms', [$this, 'searchTerms'] );

        if ('presspermit-posts-teaser' == presspermitPluginPage()) {
            add_action('admin_enqueue_scripts', function() {
                $urlpath = plugins_url('', PRESSPERMIT_TEASER_FILE);

                wp_enqueue_style('presspermit-settings', $urlpath . '/common/css/settings.css', [], PRESSPERMIT_VERSION);

                // Always use our own Select2 version with unique handles to avoid conflicts with other plugins (e.g., WooCommerce)
                if (!wp_style_is('presspermit-select2', 'registered')) {
                    wp_register_style('presspermit-select2', $urlpath . '/common/libs/select2/select2.min.css', array(), '4.0.13', 'screen');
                }
                if (!wp_script_is('presspermit-select2', 'registered')) {
                    wp_register_script('presspermit-select2', $urlpath . '/common/libs/select2/select2.full.min.js', ['jquery'], '4.0.13', false);
                }
                wp_enqueue_style('presspermit-select2');
                wp_enqueue_script('presspermit-select2');

                $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
                wp_enqueue_script('presspermit-teaser-settings', $urlpath . "/common/js/settings{$suffix}.js", ['jquery','presspermit-select2'], PRESSPERMIT_TEASER_VERSION, false);

                // Enqueue WordPress color picker
                wp_enqueue_style('wp-color-picker');
                wp_enqueue_script('wp-color-picker');

                // Enqueue teaser preview script for live updates
                wp_enqueue_script('presspermit-teaser-preview', $urlpath . "/common/js/settings-teaser-preview{$suffix}.js", ['jquery', 'wp-color-picker'], PRESSPERMIT_TEASER_VERSION, true);

                // Nonce for ajax requests
                wp_localize_script(
                    'presspermit-teaser-settings',
                    'presspermitTeaser',
                    [
                        'url' => admin_url( 'admin-ajax.php' ),
                        'nonce' => wp_create_nonce( 'pp_search_content' ),
                        'strings' => [
                            'select_a_page' => __( 'Select a page', 'press-permit-core' ),
                            'select_preview_content' => __( 'Search preview content', 'press-permit-core' ),
                            'select_terms' => __( 'Select terms', 'press-permit-core' )
                        ]
                    ]
                );

                wp_enqueue_style('presspermit-teaser-settings', $urlpath . '/common/css/settings.css', [], PRESSPERMIT_TEASER_VERSION);
            });
        }
    }

    function actMenuHandler($pp_page)
    {
        static $done;

        if (!empty($done)) {
            return;
        }

        $done = true;

        $pp_page = presspermitPluginPage();

        if (in_array($pp_page, ['presspermit-posts-teaser'], true)) {

            $class_name = str_replace('-', '', ucwords( str_replace('presspermit-', '', $pp_page), '-') );
            $load_class = "\\PublishPress\Permissions\\Teaser\\UI\\$class_name";

            if (!class_exists($load_class)) {
                require_once(PRESSPERMIT_TEASER_CLASSPATH . "/UI/{$class_name}.php");
            }

            new $load_class();
        }
    }

    function act_permissions_menu($options_menu, $handler)
    {
        // If we are disabling native custom statuses in favor of PublishPress,
        // but the Editing Permissions module is not active, hide this menu item.
        add_submenu_page(
            $options_menu,
            __('Teaser', 'press-permit-core'),
            __('Teaser', 'press-permit-core'),
            'pp_manage_teaser',
            'presspermit-posts-teaser',
            $handler
        );
    }

    function searchPosts()
	{

		if (!current_user_can('pp_manage_settings')) {
			wp_send_json('Error', 403);
		}

		if (!isset($_GET['nonce']) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'pp_search_content' ) ) {
	         wp_send_json( 'Error', 400 );
	    }

        $search = (isset($_GET['search'])) ? sanitize_text_field($_GET['search']) : '';
        $post_type = (isset($_GET['post_type'])) ? sanitize_key($_GET['post_type']) : 'page';
        $search_content = !empty($_GET['search_content']);
        
        // Validate post_type is public
        $public_post_types = get_post_types(['public' => true], 'names');
        if (!in_array($post_type, $public_post_types)) {
            $post_type = 'page';
        }

        global $wpdb;

        // phpcs Note: Direct query of posts table on admin query

        if ($search_content) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT ID, post_title FROM ' . $wpdb->prefix . 'posts
                    WHERE post_type = %s AND post_status = "publish"
                    AND (post_title LIKE %s OR post_content LIKE %s)
                    ORDER BY post_date DESC LIMIT 10',
                    $post_type,
                    '%' . $wpdb->esc_like($search) . '%',
                    '%' . $wpdb->esc_like($search) . '%'
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT ID, post_title FROM ' . $wpdb->prefix . 'posts
                    WHERE post_type = %s AND post_status = "publish"
                    AND post_title LIKE %s
                    ORDER BY post_title LIMIT 10',

                    $post_type,
                    '%' . $wpdb->esc_like($search) . '%'
                )
            );
        }

        foreach ($results as $result) {
            $result->preview_url = get_permalink($result->ID);
        }

        wp_send_json( $results );
	}

    function getTeaserPreviewPost()
    {
        if (!current_user_can('pp_manage_settings')) {
            wp_send_json('Error', 403);
        }

        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_key($_GET['nonce']), 'pp_search_content')) {
            wp_send_json('Error', 400);
        }

        $post_id = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post';
        $post = $post_id ? get_post($post_id) : null;

        if (!$post || 'publish' !== $post->post_status || $post->post_type !== $post_type) {
            wp_send_json('Error', 404);
        }

        if (!class_exists('PublishPress\\Permissions\\Teaser\\ReadMoreHandler')) {
            require_once(PRESSPERMIT_TEASER_CLASSPATH . '/ReadMoreHandler.php');
        }

        $post_content = wp_strip_all_tags(strip_shortcodes($post->post_content));
        $pre_more = \PublishPress\Permissions\Teaser\ReadMoreHandler::extractPreMoreContent($post);

        wp_send_json([
            'ID' => $post->ID,
            'post_title' => get_the_title($post),
            'preview_url' => get_permalink($post),
            'excerpt' => $post->post_excerpt ? wpautop($post->post_excerpt) : '',
            'pre_more' => (false !== $pre_more) ? wpautop($pre_more) : '',
            'x_chars' => wpautop($post_content),
        ]);
    }

    function searchTerms()
	{
		if (!current_user_can('pp_manage_settings')) {
			wp_send_json('Error', 403);
		}

		if (!isset($_GET['nonce']) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'pp_search_content' ) ) {
	         wp_send_json( 'Error', 400 );
        }
        
        $search = (isset($_GET['search'])) ? sanitize_text_field($_GET['search']) : '';

        $results = get_terms(
            [
                'name__like' => $search,
                'taxonomy' => (!empty($_GET['taxonomy'])) ? sanitize_key($_GET['taxonomy']) : 'category',
                'hide_empty' => false,
                'orderby' => 'name'
            ]
        );

        wp_send_json( $results );
	}
}

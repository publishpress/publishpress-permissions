<?php
namespace PublishPress\Permissions;

class TeaserHooks
{
    private static $instance = null;
    public $teaser_disabled = false; // kill switch to support universal teaser disable by API
    private $excerpt_post = false;
    private $theme_preview_title_filtered = false;
    public $teased_excerpts = [];
    public $is_archive_teaser = false;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new TeaserHooks();
        }

        return self::$instance;
    }

    function __construct() 
    {
        add_filter('presspermit_default_options', [$this, 'fltDefaultOptions']);
        add_filter('presspermit_teaser_default_options', [$this, 'fltDefaultOptions']); // used by SettingsTabTeaser

        add_action('presspermit_admin_ui', [$this, 'actAdminFilters']);
        add_action('presspermit_post_filters', [$this, 'actPostFilters']);
        add_action('presspermit_init', [$this, 'actPressPermitInit']);

        add_filter('login_redirect', [$this, 'fltEnforceTeaserLoginRedirect'], PHP_INT_MAX - 1, 3);

        add_action('template_redirect', [$this, 'actThemePreview'], 0);
        add_action('template_redirect', [$this, 'actMaybeRedirect'], 5);
        // phpcs:ignore WordPressVIPMinimum.UserExperience.AdminBarRemoval.RemovalDetected -- The admin bar is hidden only inside the isolated preview frame.
        add_filter('show_admin_bar', [$this, 'fltThemePreviewAdminBar']);
        add_filter('the_title', [$this, 'fltThemeTeaserPreviewTitle'], PHP_INT_MAX, 2);
        add_filter('the_content', [$this, 'fltThemeTeaserPreviewContent'], PHP_INT_MAX);
        add_action('wp_footer', [$this, 'actThemeTeaserPreviewScript'], PHP_INT_MAX);

        add_action('presspermit_pro_version_updated', [$this, 'pluginUpdated']);

        add_filter('presspermit_custom_sanitize_setting', [$this, 'flt_custom_sanitize_setting'], 10, 4);

        add_filter('elementor/frontend/the_content', [$this, 'fltElementorContent']);

        add_filter('get_the_excerpt', [$this, 'fltPostExcerpt'], 50, 2);
        add_action('presspermit_force_term_teaser', [$this, 'actForceTermTeaser']);
    }

    private function getThemePreviewState()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized on the next line; read-only preview request.
        $state = isset($_GET['pp_permissions_teaser_preview']) ? $_GET['pp_permissions_teaser_preview'] : '';
        $state = sanitize_key(wp_unslash($state));

        return in_array($state, ['404', 'teaser'], true) ? $state : '';
    }

    private function isThemePreviewRequest($state = '')
    {
        $preview_state = $this->getThemePreviewState();

        return $preview_state && (!$state || $state === $preview_state);
    }

    function actThemePreview()
    {
        $preview_state = $this->getThemePreviewState();

        if (!$preview_state || is_admin()) {
            return;
        }

        $this->teaser_disabled = true;

        global $wp_query;

        if (!$wp_query) {
            return;
        }

        // The default preview and the no-sample-post fallback intentionally use the theme's 404 template.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview request; no data is saved.
        $use_404_template = ('404' === $preview_state) || !empty($_GET['pp_permissions_teaser_fallback']);

        if ($use_404_template) {
            $wp_query->set_404();
            status_header(404);
        }

        nocache_headers();

        // Keep WordPress from guessing and redirecting an intentional preview URL.
        remove_action('template_redirect', 'redirect_canonical');
    }

    function fltThemePreviewAdminBar($show)
    {
        return $this->isThemePreviewRequest() ? false : $show;
    }

    private function getThemeTeaserPreviewPostType()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized on the next line; read-only preview request.
        $post_type = isset($_GET['pp_permissions_teaser_post_type']) ? $_GET['pp_permissions_teaser_post_type'] : '';
        $post_type = sanitize_key(wp_unslash($post_type));

        if ($post_type && post_type_exists($post_type)) {
            return $post_type;
        }

        $queried_object = get_queried_object();

        return ($queried_object instanceof \WP_Post) ? $queried_object->post_type : 'post';
    }

    private function isThemeTeaserPreviewMainPost($post_id = 0)
    {
        if (!$this->isThemePreviewRequest('teaser') || is_admin()) {
            return false;
        }

        $queried_post_id = (int) get_queried_object_id();

        return $queried_post_id && (!$post_id || $queried_post_id === (int) $post_id);
    }

    function fltThemeTeaserPreviewTitle($title, $post_id)
    {
        if ($this->theme_preview_title_filtered
            || !$this->isThemeTeaserPreviewMainPost($post_id)
            || !in_the_loop()
            || !is_main_query()
        ) {
            return $title;
        }

        $this->theme_preview_title_filtered = true;

        $post_type = $this->getThemeTeaserPreviewPostType();
        $type_obj = get_post_type_object($post_type);
        $singular_label = $type_obj ? $type_obj->labels->singular_name : __('Post', 'press-permit-core');
        $sample_title = sprintf(
            /* translators: %s is the singular post type label, such as Post or Page. */
            __('A Sample %s', 'press-permit-core'),
            $singular_label
        );
        $prefix = wp_strip_all_tags((string) presspermit()->getTypeOption('tease_prepend_name_anon', $post_type));
        $suffix = wp_strip_all_tags((string) presspermit()->getTypeOption('tease_append_name_anon', $post_type));

        return implode(' ', array_filter([$prefix, $sample_title, $suffix]));
    }

    function fltThemeTeaserPreviewContent($content)
    {
        global $post;

        if (!$post || !$this->isThemeTeaserPreviewMainPost($post->ID) || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post_type = $this->getThemeTeaserPreviewPostType();
        $default_message = __('You do not have permission to view the full content.', 'press-permit-core');
        $teaser_text = wp_strip_all_tags(
            (string) presspermit()->getTypeOption('tease_replace_content_anon', $post_type)
        );
        $prefix = wp_strip_all_tags(
            (string) presspermit()->getTypeOption('tease_prepend_content_anon', $post_type)
        );
        $suffix = wp_strip_all_tags(
            (string) presspermit()->getTypeOption('tease_append_content_anon', $post_type)
        );
        $preview_text = implode(' ', array_filter([$prefix, $teaser_text ?: $default_message, $suffix]));

        return sprintf(
            '<div id="pp-permissions-theme-teaser-content">%s</div>',
            wpautop(esc_html($preview_text))
        );
    }

    function actThemeTeaserPreviewScript()
    {
        if (!$this->isThemePreviewRequest('teaser') || is_admin()) {
            return;
        }
        ?>
        <script id="pp-permissions-theme-teaser-preview">
        (function () {
            'use strict';

            var messageAction = 'pp_permissions_teaser_preview_update';
            var readyAction = 'pp_permissions_teaser_preview_ready';

            function getPreviewRoot() {
                return document.querySelector('main, .site-main, #main, #primary') || document.body;
            }

            function getPreviewTitle(root) {
                return root.querySelector('.entry-title, .wp-block-post-title, .page-title, h1');
            }

            function getPreviewContent(root, title) {
                var previewContent = document.getElementById('pp-permissions-theme-teaser-content');

                if (previewContent) {
                    return previewContent;
                }

                var contentArea = root.querySelector('.entry-content, .wp-block-post-content, .page-content');
                previewContent = document.createElement('div');
                previewContent.id = 'pp-permissions-theme-teaser-content';

                if (contentArea) {
                    contentArea.textContent = '';
                    contentArea.appendChild(previewContent);
                } else if (title && title.parentNode) {
                    title.parentNode.insertBefore(previewContent, title.nextSibling);
                } else {
                    root.appendChild(previewContent);
                }

                return previewContent;
            }

            function applyPreview(payload) {
                if (!payload || typeof payload !== 'object') {
                    return;
                }

                var root = getPreviewRoot();
                var title = getPreviewTitle(root);
                var content = getPreviewContent(root, title);

                if (title && typeof payload.title === 'string') {
                    title.textContent = payload.title;
                }

                if (content && typeof payload.content === 'string') {
                    content.textContent = '';
                    var paragraph = document.createElement('p');
                    paragraph.textContent = payload.content;
                    content.appendChild(paragraph);
                }

                var images = root.querySelectorAll('.post-thumbnail, .wp-block-post-featured-image, img.wp-post-image');
                Array.prototype.forEach.call(images, function (image) {
                    image.style.display = payload.hideThumbnail ? 'none' : '';
                });

                var comments = root.querySelectorAll('.comments-area, .wp-block-comments');
                Array.prototype.forEach.call(comments, function (commentArea) {
                    commentArea.style.display = 'none';
                });
            }

            window.addEventListener('message', function (event) {
                if (event.origin !== window.location.origin || !event.data || event.data.action !== messageAction) {
                    return;
                }

                applyPreview(event.data.payload);
            });

            if (window.location.hash.indexOf('#pp_permissions_teaser=') === 0) {
                try {
                    applyPreview(JSON.parse(decodeURIComponent(window.location.hash.substring(23))));
                } catch (error) {
                    // Keep the server-rendered preview if the URL fragment is incomplete or invalid.
                }
            }

            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ action: readyAction }, window.location.origin);
            }
        }());
        </script>
        <?php
    }

    function fltDefaultOptions($defaults)
    {
        $extra = [
            'rss_private_feed_mode' => 'title_only',
            'rss_nonprivate_feed_mode' => 'full_content',
            'feed_teaser' => __("View the content of this <a href='%permalink%'>article</a>"),  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'teaser_hide_thumbnail' => [],
            'teaser_hide_custom_private_only' => false,
            'teaser_hide_links_taxonomy' => '',
            'teaser_hide_links_term' => '',
            'teaser_hide_menu_links_type' => [],
            'teaser_redirect' => [],
            'teaser_redirect_anon' => [],
            'teaser_redirect_page' => [],
            'teaser_redirect_anon_page' => [],
            'teaser_redirect_post_type' => [],
            'teaser_redirect_anon_post_type' => [],
            'teaser_redirect_custom_login_page' => [],
            'teaser_redirect_custom_login_page_anon' => [],
            'read_more_login_notice' => [],
            'excerpt_login_notice' => [],
            'x_chars_login_notice' => [],

            // object type options (support separate array element for each object type, and possible a nullstring element as default)
            'tease_post_types' => [],
            'teaser_num_chars' => [], // Legacy field - kept for backward compatibility
            'x_chars_num_chars' => [], // Separate field for x_chars teaser type
            'excerpt_num_chars' => [], // Separate field for excerpt teaser type
            'tease_logged_only' => [],
            'tease_public_posts_only' => [],
            'tease_direct_access_only' => [],
            'tease_replace_content' => [],
            'tease_replace_content_anon' => [],
            'tease_prepend_content' => [],
            'tease_prepend_content_anon' => [],
            'tease_append_content' => [],
            'tease_append_content_anon' => [],
            'tease_prepend_name' => [],
            'tease_prepend_name_anon' => [],
            'tease_append_name' => [],
            'tease_append_name_anon' => [],
            'tease_replace_excerpt' => [],
            'tease_replace_excerpt_anon' => [],
            'tease_prepend_excerpt' => [],
            'tease_prepend_excerpt_anon' => [],
            'tease_append_excerpt' => [],
            'tease_append_excerpt_anon' => [],
        ];

        return array_merge($defaults, $extra);
    }

    function actPressPermitInit()
    {
        if (!defined('DOING_CRON') && PWP::isFront()) {
            if (!presspermit()->isContentAdministrator() && !$this->teaser_disabled) {
                require_once(PRESSPERMIT_TEASER_CLASSPATH . '/PostFiltersFront.php');
                new Teaser\PostFiltersFront();
            }
        }
    }

    function actPostFilters()
    {
        require_once(PRESSPERMIT_TEASER_CLASSPATH . '/PostFilters.php');
        new Teaser\PostFilters();
    }

    function actAdminFilters()
    {
        require_once(PRESSPERMIT_TEASER_CLASSPATH . '/Admin.php');
        new Teaser\Admin();
    }

    function fltPostExcerpt($post_excerpt, $post) {
        if (isset($this->teased_excerpts[$post->ID])) {
            $post_excerpt = $this->teased_excerpts[$post->ID];
        }

        $this->excerpt_post = $post;
        add_filter('wp_trim_words', [$this, 'fltRestoreTeasedExcerpt'], 50, 4);
        
        return $post_excerpt;
    }

    // Restore any html tags in excerpt teaser
    function fltRestoreTeasedExcerpt($post_excerpt, $num_words, $more, $original_excerpt) {
        if ($this->excerpt_post && isset($this->teased_excerpts[$this->excerpt_post->ID])) {
            $teaser_text = [];
            
            foreach (['prepend', 'append', 'replace'] as $teaser_mode) {
                if ($teaser_text = \PublishPress\Permissions\Teaser\PostsTeaser::getTeaserText($teaser_mode, 'excerpt', 'post', get_post_field('post_excerpt', $this->excerpt_post->ID))) {
                    
                    $teaser_text_stripped = wp_strip_all_tags($teaser_text);

                    if (false !== strpos($post_excerpt, $teaser_text_stripped)) {
                        $post_excerpt = str_replace($teaser_text_stripped, $teaser_text, $post_excerpt);
                    }
                }
            }
        }

        remove_filter('wp_trim_words', [$this, 'fltRestoreTeasedExcerpt'], 50, 4);

        return $post_excerpt;
    }

    function actForceTermTeaser($term) {
        $this->is_archive_teaser = true;

        add_filter(
            'body_class',
            function ($classes, $css_class) {
                $classes[] = 'pp-archive-teaser';

                return $classes;
            },
            10, 2
        );
    }

    function fltEnforceTeaserLoginRedirect($redirect_to, $requested_redirect_to, $user) {
        // Parse the redirect URL to check for pp_permissions parameter
        $parsed_url = wp_parse_url($requested_redirect_to);
        $query_string = isset($parsed_url['query']) ? $parsed_url['query'] : '';
        
        // Parse query string into an array
        parse_str($query_string, $query_params);
        
        // If pp_permissions is present, extract the actual redirect_to URL
        if (!empty($query_params['pp_permissions'])) {
            // Check if there's a redirect_to parameter with the original URL
            if (!empty($query_params['redirect_to'])) {
                $redirect_to = urldecode($query_params['redirect_to']);
            }
        }

        return $redirect_to;
    }

    public function ajaxActions() {
        static $tease_ajax_actions;

        if (!isset($tease_ajax_actions)) {
            $tease_ajax_actions = apply_filters('presspermit_teaser_ajax_actions', ['ultp_next_prev']);
        }

        return $tease_ajax_actions;
    }

    public function pluginUpdated($prev_version) {
        if (version_compare($prev_version, '4.1.2-beta2', '<')) {
            if (get_option('presspermit_feed_teaser')) {
                update_option('presspermit_feed_teaser', __("View the content of this <a href='%permalink%'>article</a>"));  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }
        
        // Migration for post type redirect feature - set default to 'page' for existing redirects
        if (version_compare($prev_version, '4.2', '<')) {
            // Migrate existing redirect_page settings to have default post_type of 'page'
            if ($redirect_anon_pages = get_option('presspermit_teaser_redirect_anon_page')) {
                if (is_array($redirect_anon_pages) && !empty($redirect_anon_pages)) {
                    $redirect_post_types = [];
                    foreach (array_keys($redirect_anon_pages) as $post_type) {
                        $redirect_post_types[$post_type] = 'page';
                    }
                    update_option('presspermit_teaser_redirect_anon_post_type', $redirect_post_types);
                }
            }
            
            if ($redirect_pages = get_option('presspermit_teaser_redirect_page')) {
                if (is_array($redirect_pages) && !empty($redirect_pages)) {
                    $redirect_post_types = [];
                    foreach (array_keys($redirect_pages) as $post_type) {
                        $redirect_post_types[$post_type] = 'page';
                    }
                    update_option('presspermit_teaser_redirect_post_type', $redirect_post_types);
                }
            }
        }
        
        if (version_compare($prev_version, '3.8', '<')) {

            // If a 3.8 beta version was previously installed, just migrate these options to new option name
            // For normal updates, the option name change avoids corruption of settings stored by 3.7.x, which used page slug instead of ID
            if (version_compare($prev_version, '3.8-beta', '>')) {
                if ($redirect_post = (get_option('presspermit_teaser_redirect_anon_slug'))) {
                    update_option('presspermit_teaser_redirect_anon_page', $redirect_post);
                }

                if ($redirect_post = (get_option('presspermit_teaser_redirect_slug'))) {
                    update_option('presspermit_teaser_redirect_page', $redirect_post);
                }

                return;
            }

            if (!$post_types = (array) get_option('presspermit_enabled_post_types')) {
                return;
            }

            if ($option_val = get_option('presspermit_teaser_redirect_anon_slug')) {
                if ('(login)' != $option_val) {
                    if ($redirect_post = get_page_by_path($option_val)) {  // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_path_get_page_by_path
                        $option_val = $redirect_post->ID;
                    }
                }

                update_option('presspermit_teaser_redirect_anon_page', $option_val);
            }

            if ($option_val = get_option('presspermit_teaser_redirect_slug')) {
                if ('(login)' != $option_val) {
                    if ($redirect_post = get_page_by_path($option_val)) {  // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_path_get_page_by_path
                        $option_val = $redirect_post->ID;
                    }
                }

                update_option('presspermit_teaser_redirect_page', $option_val);
            }

            $post_types = array_filter($post_types);
            
            $tease_post_types = array_filter((array) get_option('presspermit_tease_post_types'));

            $hide_private_types = array_filter((array) get_option('presspermit_tease_public_posts_only'));


            if (get_option('presspermit_teaser_hide_custom_private_only')) {
                foreach (array_keys($hide_private_types) as $post_type) {
                    $hide_private_types[$post_type] = 'custom';
                }

                update_option('presspermit_tease_public_posts_only', $hide_private_types);
            }

            if ($hide_links_types = get_option('teaser_hide_links_type')) {
                $hide_links_types = str_replace(' ', '', $hide_links_types);
                $hide_links_types = str_replace(';', ',', $hide_links_types);
                $hide_links_types = array_map('sanitize_key', explode(',', $hide_links_types));
                $hide_links_types = array_intersect($hide_links_types, array_keys($tease_post_types));

                update_option('teaser_hide_menu_links_type', array_fill_keys($hide_links_types, 1));
            }
        }
    }

    function actMaybeRedirect()
    {
        if (defined('DOING_CRON') || !PWP::isFront() || $this->isThemePreviewRequest()) {
            return;
        }

        $pp = presspermit();

        global $wp_query;

        if (!is_single() && ! is_page() 
		&& (empty($wp_query) || empty($wp_query->queried_object))
		&& (empty($wp_query) || empty($wp_query->query) || empty($wp_query->query['attachment']))
		) {
            return;
        }

        if (!empty($wp_query->is_category) && !empty($wp_query->query_vars['cat']) 
        || (!empty($wp_query->is_tax) && !empty($wp_query->tax_query) && !empty($wp_query->tax_query->queries))
        || is_feed()
        ) {
            return;
        }

        $opt_redirect = (is_user_logged_in()) ? 'teaser_redirect' : 'teaser_redirect_anon';
        $opt_page = (is_user_logged_in()) ? 'teaser_redirect_page' : 'teaser_redirect_anon_page';

        // Ensure $wp_query->post is set and is an object before accessing post_type
        $post_type = null;
        if (!empty($wp_query->post) && is_object($wp_query->post) && isset($wp_query->post->post_type)) {
            $post_type = $wp_query->post->post_type;
        } elseif (!empty($wp_query->queried_object) && is_object($wp_query->queried_object) && isset($wp_query->queried_object->post_type)) {
            $post_type = $wp_query->queried_object->post_type;
        }

        if (!$post_type)
            return;

        if (!$option_val = $pp->getTypeOption($opt_redirect, $post_type))
            return;

        if ($pp->isContentAdministrator())
            return;

        global $wpdb;

        if (!empty($wp_query->post)) {
            $queried_object = $wp_query->post;
        } elseif (!empty($wp_query->queried_object)) {
            $queried_object = $wp_query->queried_object;
        }

        if ((!empty($queried_object) && !current_user_can('read_post', $queried_object->ID)) 
        || (!empty($wp_query) && !empty($wp_query->query) && !empty($wp_query->query['attachment']) && empty($wp_query->posts))
        ) {
            // Check if the post type's teaser type is set to 'redirect'
            if (!empty($queried_object->post_type)) {
                $teaser_type = $pp->getTypeOption('tease_post_types', $queried_object->post_type);

                // Only redirect if the teaser type is explicitly set to 'redirect'
                if ('redirect' !== $teaser_type) {
                    return;
                }
            }
            $url = '';

            if ('(login)' === $option_val) {
                $url = wp_login_url();
            } elseif ('(select)' === $option_val) {
                $option_page = $pp->getTypeOption($opt_page, $wp_query->post->post_type);

                if (is_numeric($option_page)) {
                    // Verify the redirect target still exists and is published
                    $redirect_post_type_opt = (is_user_logged_in()) ? 'teaser_redirect_post_type' : 'teaser_redirect_anon_post_type';
                    $redirect_post_type = $pp->getTypeOption($redirect_post_type_opt, $wp_query->post->post_type) ?: 'page';
                    
                    $redirect_post = get_post($option_page);
                    if ($redirect_post && 'publish' === $redirect_post->post_status && $redirect_post->post_type === $redirect_post_type) {
                        $url = get_permalink($option_page);
                    } else {
                        // Fallback to login if redirect target is invalid
                        $url = wp_login_url();
                    }
                }
            }

            if ($url) {
                $custom_login_page_option_name = is_user_logged_in() ? "teaser_redirect_custom_login_page" : "teaser_redirect_custom_login_page_anon";

                if (('(login)' === $option_val) || defined('PRESSPERMIT_TEASER_REDIRECT_ARG') || $pp->getTypeOption($custom_login_page_option_name, $queried_object->post_type)) {
                    if (!empty($wp_query) && !empty($wp_query->query) && !empty($wp_query->query['attachment']) && !empty($wp_query->query['pagename']) && false !== strpos($wp_query->query['pagename'], $wp_query->query['attachment'])) {
                        $redirect_arg = trailingslashit(site_url()) . $wp_query->query['pagename'];
                    } elseif (!empty($wp_query->queried_object)) {
                    	$redirect_arg = get_permalink($wp_query->queried_object->ID);
                    }

                    if (empty($redirect_arg)) {
						$redirect_arg = untrailingslashit(get_site_url()) . urldecode(PWP::SERVER_url('REQUEST_URI'));
                    }
                    
                    if (PWP::empty_REQUEST('redirect_to') && (false === strpos($redirect_arg, '&p='))) {
                        $redirect_var = (defined('PRESSPERMIT_TEASER_REDIRECT_VAR')) ? constant('PRESSPERMIT_TEASER_REDIRECT_VAR') : 'redirect_to';
                        
                        $url = add_query_arg(sanitize_key($redirect_var), $redirect_arg, $url);
                        
                        if (defined('PRESSPERMIT_TEASER_REDIRECT_ALTERNATE')) {
                            $alt_redirect_var = (is_string(constant('PRESSPERMIT_TEASER_REDIRECT_ALTERNATE')) && !in_array(constant('PRESSPERMIT_TEASER_REDIRECT_ALTERNATE'), ['true', '1']))
                            ? constant('PRESSPERMIT_TEASER_REDIRECT_ALTERNATE') 
                            : '_redirect_to';

                            $url = add_query_arg(sanitize_key($alt_redirect_var), $redirect_arg, $url);
                        }

	                    if (!defined('PRESSPERMIT_TEASER_LOGIN_REDIRECT_NO_PP_ARG')) {
	                        $url = add_query_arg('pp_permissions', 1, $url);
	                    }
	                }
                }

                wp_redirect($url);
                exit;
            }
        }
    }

    function flt_custom_sanitize_setting($is_custom_sanitized, $option_basename, $default_prefix, $args) {
        if (in_array(
            $option_basename, 
            ['feed_teaser', 'tease_replace_content_anon', 'tease_prepend_name_anon', 'tease_append_name_anon', 'tease_replace_content_anon', 'tease_prepend_content_anon', 'tease_append_content_anon', 
            'tease_replace_excerpt_anon', 'tease_prepend_excerpt_anon', 'tease_append_excerpt_anon', 'tease_replace_content', 'tease_prepend_name', 'tease_append_name', 'tease_replace_content', 
            'tease_prepend_content', 'tease_append_content', 'tease_replace_excerpt', 'tease_prepend_excerpt', 'tease_append_excerpt', 'read_more_login_notice', 'excerpt_login_notice', 'x_chars_login_notice']
        )) {
            // phpcs Note: this is triggered by our filter application, so additional nonce verification is unnecessary

            // phpcs Note: These teaser options cannot currently be sanitized because they support embedded html tags

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            if (isset($_POST[$option_basename])) {
                presspermit()->updateOption(
                    $default_prefix . $option_basename,
                    $_POST[$option_basename], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                    $args
                );
            }

            return true;
        }

        return $is_custom_sanitized;
    }

    function fltElementorContent($content) {
        if ($post_id = PWP::getPostID()) {
            if ($_post = get_post($post_id)) {
				if (! \PublishPress\Permissions\TeaserHooks::instance()->teaser_disabled) {
					\PublishPress\Permissions\TeaserHooks::instance()->teaser_disabled = true;
					$can_read = current_user_can('read_post', $post_id);
					\PublishPress\Permissions\TeaserHooks::instance()->teaser_disabled = false;
					
					if (!$can_read
					&& apply_filters('presspermit_teaser_enabled', false, 'post', $_post->post_type)
					) {
						\PublishPress\Permissions\Teaser\PostsTeaser::applyTeaser($_post, 'post', $_post->post_type, ['force_refresh' => true]);
						$content = $_post->post_content;
					}
				}
            }
        }

        return $content;
    }
}

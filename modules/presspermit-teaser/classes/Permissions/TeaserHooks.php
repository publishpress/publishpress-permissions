<?php
namespace PublishPress\Permissions;

class TeaserHooks
{
    private static $instance = null;
    public $teaser_disabled = false; // kill switch to support universal teaser disable by API
    private $excerpt_post = false;
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

        add_action('template_redirect', [$this, 'actMaybeRedirect'], 5);

        add_action('presspermit_pro_version_updated', [$this, 'pluginUpdated']);

        add_filter('presspermit_custom_sanitize_setting', [$this, 'flt_custom_sanitize_setting'], 10, 4);

        add_filter('elementor/frontend/the_content', [$this, 'fltElementorContent']);

        add_filter('get_the_excerpt', [$this, 'fltPostExcerpt'], 50, 2);
        add_action('presspermit_force_term_teaser', [$this, 'actForceTermTeaser']);
    }

    function fltDefaultOptions($defaults)
    {
        $extra = [
            'rss_private_feed_mode' => 'title_only',
            'rss_nonprivate_feed_mode' => 'full_content',
            'feed_teaser' => __("View the content of this <a href='%permalink%'>article</a>"),  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'teaser_hide_thumbnail' => true,
            'teaser_hide_custom_private_only' => false,
            'teaser_hide_links_taxonomy' => '',
            'teaser_hide_links_term' => '',
            'teaser_hide_menu_links_type' => [],
            'teaser_redirect_page' => '',
            'teaser_redirect_anon_page' => '',
            'teaser_redirect_custom_login_page' => '',
            'teaser_redirect_custom_login_page_anon' => '',
            'read_more_login_notice' => esc_html__('To read the full content, please log in to this site.', 'press-permit-core'),

            // object type options (support separate array element for each object type, and possible a nullstring element as default)
            'tease_post_types' => [],
            'teaser_num_chars' => [],
            'tease_logged_only' => [],
            'tease_public_posts_only' => [],
            'tease_direct_access_only' => [],
            'tease_replace_content' => esc_html__("Sorry, this content requires additional permissions.  Please contact an administrator for help.", 'press-permit-core'),
            'tease_replace_content_anon' => esc_html__("Sorry, you don't have access to this content.  Please log in or contact a site administrator for help.", 'press-permit-core'),
            'tease_prepend_name' => '',
            'tease_prepend_name_anon' => '',
            'tease_append_name' => '',
            'tease_append_name_anon' => '',
            'tease_replace_excerpt' => '',
            'tease_replace_excerpt_anon' => '',
            'tease_prepend_excerpt' => '',
            'tease_prepend_excerpt_anon' => '',
            'tease_append_excerpt' => "<br /><small>" . esc_html__("Note: This content requires a higher login level.", 'press-permit-core') . "</small>",
            'tease_append_excerpt_anon' => '',
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
        if (!PWP::empty_REQUEST('pp_redirect')) {
            $redirect_to = $requested_redirect_to;
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
                if ('[login]' != $option_val) {
                    if ($redirect_post = get_page_by_path($option_val)) {  // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_path_get_page_by_path
                        $option_val = $redirect_post->ID;
                    }
                }

                update_option('presspermit_teaser_redirect_anon_page', $option_val);
            }

            if ($option_val = get_option('presspermit_teaser_redirect_slug')) {
                if ('[login]' != $option_val) {
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
        if (defined('DOING_CRON') || !PWP::isFront()) {
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

        $opt = (is_user_logged_in()) ? 'teaser_redirect_page' : 'teaser_redirect_anon_page';

        if (!$option_val = $pp->getOption($opt))
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

            if ('[login]' === $option_val) {
                $url = wp_login_url();

            } elseif (is_numeric($option_val)) {
                $url = get_permalink($option_val);
            }

            if ($url) {
                $custom_login_page_option_name = is_user_logged_in() ? "teaser_redirect_custom_login_page" : "teaser_redirect_custom_login_page_anon";

                if (('[login]' === $option_val) || defined('PRESSPERMIT_TEASER_REDIRECT_ARG') || $pp->getOption($custom_login_page_option_name)) {
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

        if (in_array($option_basename, ['teaser_redirect_page', 'teaser_redirect_anon_page'])) {
            $compare_option = ('teaser_redirect_anon_page' == $option_basename) ? 'teaser_redirect_anon' : 'teaser_redirect';
            
            // phpcs Note: this is triggered by our filter application, so additional nonce verification is unnecessary

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            if (isset($_POST[$compare_option]) && ('[login]' == $_POST[$compare_option])) {
                presspermit()->updateOption($default_prefix . $option_basename, '[login]', $args);

                return true;  // If not set to this special case value, allow standard sanitization and option storage
            }
        }
        
        if (in_array(
            $option_basename, 
            ['feed_teaser', 'tease_replace_content_anon', 'tease_append_name_anon', 'tease_replace_content_anon', 'tease_prepend_content_anon', 'tease_append_content_anon', 
            'tease_replace_excerpt_anon', 'tease_prepend_excerpt_anon', 'tease_append_excerpt_anon', 'tease_replace_content', 'tease_append_name', 'tease_replace_content', 
            'tease_prepend_content', 'tease_append_content', 'tease_replace_excerpt', 'tease_prepend_excerpt', 'tease_append_excerpt']
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

<?php
namespace PublishPress\Permissions\Teaser;

/**
 * Handles "Read More Link as Teaser" functionality
 * 
 * This class provides methods to detect and extract content before the WordPress
 * more tag (Classic Editor, Gutenberg More block, or Gutenberg Read More block),
 * and generate appropriate "Read More" links with login redirect support.
 * 
 * @package PublishPress\Permissions\Teaser
 */
class ReadMoreHandler
{
    /**
     * Check if a post contains a more/read-more delimiter (Classic or Gutenberg)
     * 
     * @param \WP_Post $post The post object to check
     * @return bool True if more tag exists, false otherwise
     */
    public static function hasMoreTag($post)
    {
        if (empty($post->post_content)) {
            return false;
        }

        // Check for Classic Editor more tag: <!--more--> or <!--more Custom Text-->
        if (preg_match('/<!--\s*more(?:\s+.*?)?\s*-->/i', $post->post_content)) {
            return true;
        }

        // Check for Gutenberg more block: <!-- wp:more -->
        if (strpos($post->post_content, '<!-- wp:more') !== false) {
            return true;
        }

        // Check for Gutenberg read-more block: <!-- wp:read-more -->
        if (strpos($post->post_content, '<!-- wp:read-more') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Extract content before the more tag
     * 
     * Handles Classic Editor <!--more-->, Gutenberg More, and Gutenberg Read More block formats.
     * Returns content up to but not including the more tag.
     * 
     * @param \WP_Post $post The post object
     * @return string|false Content before more tag, or false if no more tag found
     */
    public static function extractPreMoreContent($post)
    {
        if (empty($post->post_content)) {
            return false;
        }

        $content = $post->post_content;
        $more_pos = false;

        // Check for Classic Editor more tag first: <!--more--> or <!--more Custom Text-->
        $classic_more_pos = preg_match('/<!--\s*more(?:\s+.*?)?\s*-->/i', $content, $classic_matches, PREG_OFFSET_CAPTURE)
            ? $classic_matches[0][1]
            : false;
        
        // Check for Gutenberg more block.
        $gutenberg_more_pos = strpos($content, '<!-- wp:more');

        // Check for Gutenberg read-more block.
        $gutenberg_read_more_pos = strpos($content, '<!-- wp:read-more');

        // Use whichever delimiter comes first.
        $positions = array_filter(
            [$classic_more_pos, $gutenberg_more_pos, $gutenberg_read_more_pos],
            function ($position) {
                return false !== $position;
            }
        );

        if ($positions) {
            $more_pos = min($positions);
        }

        if ($more_pos === false) {
            return false;
        }

        // Extract content before the more tag
        $pre_more_content = substr($content, 0, $more_pos);

        // Clean up the content
        $pre_more_content = trim($pre_more_content);

        return $pre_more_content;
    }

    /**
     * Generate a "Read More" link for teased content
     * 
     * Creates a link to the full post. For anonymous users or users without
     * read permission, can optionally redirect to login page.
     * 
     * @param \WP_Post $post The post object
     * @param array $options Optional settings:
     *                       - 'link_text': Custom link text (default: "Read More")
     *                       - 'redirect_to_login': Whether to redirect to login for unauthorized users
     *                       - 'css_class': Custom CSS class for the link
     * @return string HTML markup for the Read More link
     */
    public static function generateReadMoreLink($post, $options = [])
    {
        $defaults = [
            'link_text' => esc_html__('Read More', 'press-permit-core'),
            'redirect_to_login' => true,
            'css_class' => 'pp-read-more-link',
        ];

        $options = array_merge($defaults, $options);

        // Get the permalink to the full post
        $permalink = get_permalink($post->ID);

        // Check if we should redirect to login
        $pp = presspermit();
        $redirect_to_login = $options['redirect_to_login'];

        // Check teaser redirect settings
        $is_anonymous = !is_user_logged_in();
        $post_type = $post->post_type;
        
        if ($redirect_to_login) {
            if ($is_anonymous) {
                // Check for anonymous user redirect settings (per-post-type)
                $redirect_mode = $pp->getTypeOption('teaser_redirect_anon', $post_type);
                
                if ($redirect_mode === '(login)') {
                    // Redirect to WordPress login with return URL
                    $permalink = wp_login_url($permalink);
                } elseif ($redirect_mode === '(select)') {
                    // Redirect to custom page
                    $redirect_page_id = $pp->getTypeOption('teaser_redirect_anon_page', $post_type);
                    if ($redirect_page_id && is_numeric($redirect_page_id)) {
                        $redirect_url = get_permalink($redirect_page_id);
                        
                        // If it's a custom login page, add return URL
                        if ($pp->getTypeOption('teaser_redirect_custom_login_page_anon', $post_type)) {
                            $permalink = add_query_arg('redirect_to', urlencode($permalink), $redirect_url);
                        } else {
                            $permalink = $redirect_url;
                        }
                    }
                } elseif ($redirect_mode === '(url)') {
                    $redirect_url = esc_url_raw((string) $pp->getTypeOption('teaser_redirect_anon_url', $post_type));

                    if ($redirect_url) {
                        $permalink = $redirect_url;
                    }
                }
            } else {
                // Check for logged-in user redirect settings (per-post-type)
                $redirect_mode = $pp->getTypeOption('teaser_redirect', $post_type);
                
                if ($redirect_mode === '(login)') {
                    $permalink = wp_login_url($permalink);
                } elseif ($redirect_mode === '(select)') {
                    $redirect_page_id = $pp->getTypeOption('teaser_redirect_page', $post_type);
                    if ($redirect_page_id && is_numeric($redirect_page_id)) {
                        $redirect_url = get_permalink($redirect_page_id);
                        
                        if ($pp->getTypeOption('teaser_redirect_custom_login_page', $post_type)) {
                            $permalink = add_query_arg('redirect_to', urlencode(get_permalink($post->ID)), $redirect_url);
                        } else {
                            $permalink = $redirect_url;
                        }
                    }
                } elseif ($redirect_mode === '(url)') {
                    $redirect_url = esc_url_raw((string) $pp->getTypeOption('teaser_redirect_url', $post_type));

                    if ($redirect_url) {
                        $permalink = $redirect_url;
                    }
                }
            }
        }

        // Allow customization of link text with placeholders
        $link_text = $options['link_text'];
        $link_text = str_replace('%post_title%', get_the_title($post->ID), $link_text);
        $link_text = str_replace('%permalink%', $permalink, $link_text);
        
        // Add the shared informational message for blocked users on single post pages.
        $info_message = '';
        if (is_single() || is_page()) {
            $notice_text = presspermit()->getTypeOption('read_more_login_notice', $post_type);
            if (empty($notice_text)) {
                $notice_text = esc_html__('You do not have permission to view the full content.', 'press-permit-core');
            }
            $notice_text = do_shortcode(wp_unslash($notice_text));
            
            $info_message = sprintf(
                '<p class="pp-teaser-login-notice" style="padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa; margin: 15px 0; font-size: 14px; line-height: 1.6; overflow-wrap: anywhere; word-break: break-word; box-sizing: border-box;">%s</p>',
                $notice_text
            );
            
            $info_message = apply_filters('presspermit_read_more_login_notice', $info_message, $post);
        }
        
        $link_html = sprintf('%s', $info_message);

        return apply_filters('presspermit_read_more_link', $link_html, $post, $options);
    }

    /**
     * Get the Read More link text
     * 
     * Always returns the default "Read More" text.
     * Users should use the post's more tag custom text if they want customization.
     * 
     * @param string $post_type The post type (for potential future per-type customization)
     * @return string The link text
     */
    public static function getReadMoreLinkText($post_type = '')
    {
        // Always use default text - users can customize via the more tag in their posts
        $link_text = esc_html__('Read More', 'press-permit-core');

        return apply_filters('presspermit_read_more_link_text', $link_text, $post_type);
    }
}

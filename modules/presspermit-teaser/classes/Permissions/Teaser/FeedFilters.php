<?php
namespace PublishPress\Permissions\Teaser;

/**
 * FeedFilters PHP class for the WordPress plugin PP Teaser
 *
 * Teaser support for RSS feeds.
 *
 * Also allows replacement of readable RSS feed content with a permalink to the post.
 * This may be desirable since browsers sometimes cache the feed content after user logout.
 *
 * @author Kevin Behrens
 *
 */
class FeedFilters
{
    function __construct()
    {
        add_filter('the_content_rss', [$this, 'fltTheContentRSS']);
        add_filter('the_excerpt_rss', [$this, 'fltTheExcerptRSS']);

        if (is_feed()) {
            // Only filter the_content if we're sure this is an RSS request (TODO: is this still necessary?)
            if (!PWP::empty_GET('http_auth')) {
                add_filter('the_content', [$this, 'fltTheContentRSS']);
            }
        }
    }

    // Checks whether a logged-out visitor would be able to read this specific post, independent
    // of who is actually making the current request (a real feed reader has no session at all,
    // and even a logged-in admin testing /feed directly should see the same masking a visitor would).
    private function isReadableByAnonymous($post_id)
    {
        if (empty($post_id)) {
            return false;
        }

        $pp = presspermit();
        $original_user_id = $pp->getUser()->ID;

        $pp->setUser(0);
        $can_read = (new \WP_User(0))->has_cap('read_post', $post_id);
        $pp->setUser($original_user_id);

        return $can_read;
    }

    private function replaceFeedTeaserPlaceholder($content)
    {
        global $post;
        if (!empty($post)) {
            $search[] = '%permalink%';
            $replace[] = get_permalink($post->ID);
            $content = str_replace($search, $replace, $content);
        }

        return $content;
    }

    private function filterRSS($text, $subject = 'content')
    {
        global $post;

        if (empty($post))
            return $text;

        if (!empty($post->pp_teaser))
            return $text;

        $pp = presspermit();

        // Matches the "Settings on this tab do not apply" notice on the Options tab: RSS masking
        // only applies to post types that have Teaser enabled at all.
        if (!$pp->getTypeOption('tease_post_types', $post->post_type))
            return $text;

        // Only mask posts a logged-out visitor is actually blocked from -- not every post of a
        // teased-enabled type regardless of whether this specific one is actually restricted.
        if ($this->isReadableByAnonymous($post->ID))
            return $text;

        if ($post->post_status == 'private')
            $feed_privacy = $pp->getOption('rss_private_feed_mode');
        else
            $feed_privacy = $pp->getOption('rss_nonprivate_feed_mode');

        switch ($feed_privacy) {
            case 'full_content':
                return $text;

            case 'excerpt_only':
                if ('content' == $subject)
                    return apply_filters('the_excerpt_rss', get_the_excerpt(true));
                else
                    return $text;

            default:
                if ($msg = $pp->getOption('feed_teaser')) {
                    if (defined('PP_TRANSLATE_TEASER')) {
                        // otherwise, this is only loaded for admin
                        @load_plugin_textdomain('press-permit-core', false, dirname(plugin_basename(PRESSPERMIT_FILE)) . '/languages');
                        
                        $msg = translate($msg, 'press-permit-core');

                        if (!empty($msg) && !is_null($msg) && is_string($msg))
                            $msg = htmlspecialchars_decode($msg);
                    }

                    return $this->replaceFeedTeaserPlaceholder($msg);
                }
        } // end switch
    }

    // changes the article content for items which are not already filtered by Hidden Content Teaser
    function fltTheContentRSS($content)
    {
        return $this->filterRSS($content, 'content');
    }

    // changes the article excerpt for items which are not already filtered by Hidden Content Teaser
    function fltTheExcerptRSS($excerpt)
    {
        return $this->filterRSS($excerpt, 'excerpt');
    }
}

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

        if (!empty($post) && !empty($post->pp_teaser))
            return $text;

        $pp = presspermit();

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

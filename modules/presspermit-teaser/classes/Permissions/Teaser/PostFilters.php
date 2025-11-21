<?php
namespace PublishPress\Permissions\Teaser;

class PostFilters
{
    function __construct()
    {
        add_filter('presspermit_posts_teaser', [$this, 'fltPostsTeaser'], 50, 3);
        add_filter('presspermit_teased_post_types', [$this, 'fltGetTeaserPostTypes'], 10, 3);
        add_filter('presspermit_teaser_enabled', [$this, 'fltTeaserEnabled'], 10, 4);

        add_filter('posts_results', [$this, 'fltPostsFrontEndResults'], 99, 2);
        add_filter('the_content', [$this, 'fltTheContent'], 10, 1);
        add_action('mla_gallery_wp_query_object', [$this, 'actBlockTeaser']);

        add_filter('relevanssi_excerpt_content', [$this, 'fltRelevanssiContent'], 50, 3);
    }

    function actBlockTeaser()
    {
        \PublishPress\Permissions\TeaserHooks::instance()->teaser_disabled = true;
        add_action('posts_selection ', [$this, 'actUnblockTeaser']);
    }

    function actUnblockTeaser()
    {
        \PublishPress\Permissions\TeaserHooks::instance()->teaser_disabled = false;
    }

    function fltPostsFrontEndResults($results, $_query_obj)
    {
        if ($tease_otypes = $this->fltGetTeaserPostTypes([], '', [])) {
            $teaser_obj = Teaser::initTemplate();
            return $teaser_obj->postsTeaserPrepResults($results, $tease_otypes, ['request' => $_query_obj->request]);
        }

        return $results;
    }

    function fltTheContent($content) {
        global $wp_query, $post;

        if (!empty($wp_query->request) && !empty($post)) {
            $results = $this->fltPostsFrontEndResults([$post], $wp_query);

            $_post = reset($results);
            if (is_object($_post) && isset($_post->post_content) 
            && Teaser::instance()->isTeaser($post->ID)
            && (
                (defined('PRESSPERMIT_TEASER_LEGACY_CONTENT_CHECK') && ($_post->post_content != $post->post_content)) 
                || (!defined('PRESSPERMIT_TEASER_LEGACY_CONTENT_CHECK') && ($content != $post->post_content) && is_main_query())
            )
            ) {
                $content = $_post->post_content;
            }
        }

        return $content;
    }

    function fltRelevanssiContent($content, $post, $search_string) {
        add_filter('presspermit_unfiltered', '__return_true');

        $_query_obj = new \WP_Query(['s' => $search_string]);

        remove_filter('presspermit_unfiltered', '__return_true');

        if (!empty($_query_obj->request) && !empty($post)) {
            if ($tease_otypes = $this->fltGetTeaserPostTypes([], '', [])) {
                $teaser_obj = Teaser::initTemplate();
                $results = $teaser_obj->postsTeaserPrepResults([$post], $tease_otypes, ['request' => $_query_obj->request]);
                $results = $teaser_obj->applyPostsTeaser($results, $tease_otypes);
            }

            if (!empty($results)) {
                $_post = reset($results);

                if (is_object($_post) && isset($_post->post_content) 
                && ($_post->post_content != $post->post_content || (function_exists('relevanssi_query') && $content != $_post->post_content))
                ) {
                    $content = $_post->post_content;
                }
            }
        }

        return $content;
    }

    function fltGetTeaserPostTypes($tease_types = [], $post_types = [], $args = [])
    {
        $pp = presspermit();

        if (
        (is_admin() && (!defined('DOING_AJAX') || ! DOING_AJAX || !PWP::is_REQUEST('action', \PublishPress\Permissions\TeaserHooks::instance()->ajaxActions())))
        || $pp->isContentAdministrator() 
        || (is_feed() && defined('PP_NO_FEED_TEASER')) 
        || defined('XMLRPC_REQUEST') || defined('REST_REQUEST') 
        || \PublishPress\Permissions\TeaserHooks::instance()->teaser_disabled) {
            return [];
        }

        $args = (array)$args;

        if (!empty($args['skip_teaser'])
        || (!empty($args['required_operation']) && ('read' != $args['required_operation']))
        ) {
            return [];
        }

        if ($tease_types = array_diff((array)$pp->getOption('tease_post_types'), ['0', false])) {
            if ($post_types) {
                $tease_types = array_intersect_key($tease_types, array_flip((array)$post_types));
            }

            if ($tease_logged_only = array_intersect_key(array_diff((array)$pp->getOption('tease_logged_only'), ['0', false]), $tease_types)) {
                global $current_user;

                foreach ($tease_logged_only as $type => $logged_only) {
                    if (
                        (('anon' != $logged_only) && !$current_user->ID)
                        || (('anon' == $logged_only) && $current_user->ID)) {
                        unset($tease_types[$type]);
                    }
                }
            }

            if (!is_single() || (!empty($args['query_vars']) && (empty($args['query_vars']['p']) && empty($args['query_vars']['name']) && empty($args['query_vars']['attachment'])))) {
                $tease_direct_only_types = array_diff((array)$pp->getOption('tease_direct_access_only'), ['0', false]);
                $tease_types = array_diff_key($tease_types, $tease_direct_only_types);
            }
        }

        if ($tease_types)
            return apply_filters('presspermit_teaser_types', array_diff(array_keys($tease_types), Teaser::noTeaseTypes()), $args);
        else
            return [];
    }

    function fltTeaserEnabled($enabled, $source_name, $object_type = '', $status = '')
    {
        if ('post' != $source_name)
            return $enabled;

        if ($this->fltGetTeaserPostTypes($object_type, $object_type)) { // account for "logged only" / "anon only" settings

            $pp = presspermit();

            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			/*
            if ($hide_links_types = $pp->getOption('teaser_hide_links_type')) {
                $hide_links_types = str_replace(' ', '', $hide_links_types);
                $hide_links_types = str_replace(';', ',', $hide_links_types);
                $hide_links_types = array_map('sanitize_key', explode(',', $hide_links_types));
                if (in_array($object_type, $hide_links_types, true))
                    return false;
            }
        	*/

			if ($hide_links_types = $pp->getOption('teaser_hide_menu_links_type')) {
                if (!empty($hide_links_types[$object_type])) {
                    return false;
            	}
            }

            if ($status) {
                if ($hide_private = $pp->getTypeOption('tease_public_posts_only', $object_type)) {
                    $pvt_stati = get_post_stati(['private' => true, '_builtin' => false]);

                    if ($pp->getOption('teaser_hide_custom_private_only'))
                        $pvt_stati = array_diff($pvt_stati, ['private']);
                }

                $enabled = !$hide_private || !in_array($status, $pvt_stati, true);
            } else {
                return true;
            }
        }

        return $enabled;
    }

    function fltPostsTeaser($results, $post_types = [], $args = [])
    {
        $defaults = ['request' => '', 'force_teaser' => false, 'context' => 'query_results'];
        $args = array_merge($defaults, (array)$args);

        if (
        (is_admin() && (!defined('DOING_AJAX') || ! DOING_AJAX || !PWP::is_REQUEST('action', \PublishPress\Permissions\TeaserHooks::instance()->ajaxActions()))) 
        || defined('XMLRPC_REQUEST')
        ) {
            return $results;
        }

        if (!empty($args['required_operation']) && ('read' != $args['required_operation'])) {
            return $results;
        }

        if (!$tease_types = $this->fltGetTeaserPostTypes([], $post_types, $args)) {
            return $results;
        }

        $teaser_obj = Teaser::initTemplate();
        return $teaser_obj->applyPostsTeaser($results, $tease_types, $args);
    }
}

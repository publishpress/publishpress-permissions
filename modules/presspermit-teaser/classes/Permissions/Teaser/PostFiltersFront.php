<?php
namespace PublishPress\Permissions\Teaser;

class PostFiltersFront
{
    private $restore_post_parent = [];

    function __construct()
    {
        if (\PublishPress\Permissions\TeaserHooks::instance()->teaser_disabled) {
            return;
        }

        add_filter('user_has_cap', [$this, 'fltUserHasCap'], 50, 3);
        add_filter('presspermit_skip_the_terms_filtering', [$this, 'fltSkipTheTermsFiltering'], 10, 2);

        add_filter('the_posts', [$this, 'fltThePosts'], 1, 2);
        add_filter('presspermit_nav_menu_hide_terms', [$this, 'fltNavMenuHideTerms'], 10, 2);
        add_filter('presspermit_nav_menu_hide_posts', [$this, 'fltNavMenuHidePosts'], 10, 3);

        add_filter('wp_get_attachment_metadata', [$this, 'fltGetAttachmentMetadata'], 10, 2);
        add_filter('wp_get_attachment_url', [$this, 'fltGetAttachmentUrl'], 10, 2);
        add_filter('comments_array', [$this, 'fltCommentsResults'], 99);

        add_action('presspermit_teaser_restore_post_parent', [$this, 'actRestorePostParent'], 10, 2);
        
        // Load WooCommerce integration if WooCommerce is active
        if (class_exists('WooCommerce')) {
            require_once(PRESSPERMIT_TEASER_CLASSPATH . '/WooCommerceIntegration.php');
            new WooCommerceIntegration();
        }
    }

    function actRestorePostParent($post_id, $parent_id)
    {
        $this->restore_post_parent[$post_id] = $parent_id;
    }

    public function fltUserHasCap($wp_sitecaps, $orig_reqd_caps, $args)
    {
        if (('read_post' == $args[0]) && ('attachment' == get_post_field('post_type', $args[2]))) {
            global $current_user;
            if ($args[1] == $current_user->ID) {
                $type_obj = get_post_type_object('attachment');
                $wp_sitecaps[$type_obj->cap->edit_others_posts] = true;
            }
        }

        return $wp_sitecaps;
    }

    public function fltSkipTheTermsFiltering($skip, $post_id)
    {
        return $skip || Teaser::instance()->isTeaser($post_id);
    }

    function fltThePosts($results, $_query_obj)
    {
        if (!empty($this->restore_post_parent)) {
            foreach (array_keys($results) as $key) {
                $id = $results[$key]->ID;

                if (isset($this->restore_post_parent[$id]))
                    $results[$key]->post_parent = $this->restore_post_parent[$id];
            }
        }

        return $results;
    }

    public function fltNavMenuHidePosts($hide_ids, $items, $post_type)
    {
        if (apply_filters('presspermit_teased_post_types', [], $post_type)) {
            $pp = presspermit();

            $tease_stati = get_post_stati(['public' => true, 'private' => true], 'names', 'or');

            if ('attachment' == $post_type)
                $tease_stati[] = 'inherit';

            $tease_stati = array_diff($tease_stati, Teaser::getHiddenStatuses($post_type));

            Teaser::initTemplate();
            $teaser_prepend = PostsTeaser::getTeaserText('prepend', 'name', 'post', $post_type);
            $teaser_append = PostsTeaser::getTeaserText('append', 'name', 'post', $post_type);

            if ($hide_links_taxonomy = $pp->getOption('teaser_hide_links_taxonomy')) {
                $hide_links_terms = str_replace(' ', '', $pp->getOption('teaser_hide_links_terms'));
                $hide_links_terms = str_replace(';', ',', $hide_links_terms);
                $hide_links_terms = array_map('intval', explode(',', $hide_links_terms));
            }

            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            /*
            if ($hide_links_types = $pp->getOption('teaser_hide_links_type')) {
                $hide_links_types = str_replace(' ', '', $hide_links_types);
                $hide_links_types = str_replace(';', ',', $hide_links_types);
                $hide_links_types = array_map('sanitize_key', explode(',', $hide_links_types));
            }
            */

            $hide_links_types = array_filter((array) $pp->getOption('teaser_hide_menu_links_type'));

            foreach (array_keys($items) as $key) {
                if (!empty($items[$key]->type) && ('post_type' == $items[$key]->type) && in_array($items[$key]->object_id, $hide_ids)) {
                    if ($_post = get_post($items[$key]->object_id)) {
                        if (in_array($_post->post_status, $tease_stati, true)) {
                            if ($hide_links_types) {
                                foreach (array_keys($hide_links_types) as $_type) {
                                    if ($_post->post_type == $_type)
                                        continue 2;
                                }
                            }

                            if ($hide_links_taxonomy) {
                                $terms = wp_get_object_terms($_post->ID, $hide_links_taxonomy);  // todo: single query for all posts in result set

                                foreach ($terms as $_term) {
                                    if (in_array($_term->term_id, $hide_links_terms) || in_array($_term->slug, $hide_links_terms))
                                        continue 2;
                                }
                            }

                            $items[$key]->title = $teaser_prepend . $items[$key]->title . $teaser_append;
                            unset($hide_ids[$key]);
                        }
                    }
                }
            }
        }

        return $hide_ids;
    }

    public function fltNavMenuHideTerms($hide_ids, $taxonomy)
    {
        if ($taxonomy_obj = get_taxonomy($taxonomy)) {
            foreach ($taxonomy_obj->object_type as $post_type) {  // don't remove a term whose taxonomy is associated with a post type that's being teased
                if (presspermit()->getTypeOption('tease_post_types', $post_type))
                    return [];
            }
        }

        return $hide_ids;
    }

    function fltGetAttachmentUrl($data, $post_id)
    {
        if ($post_id && Teaser::instance()->isTeaser($post_id)) {
            $post = get_post($post_id);
            $post_type = $post ? $post->post_type : '';
            if ($post_type && presspermit()->getTypeOption('teaser_hide_thumbnail', $post_type)) {
                $data = ($post && (false !== strpos($post->post_mime_type, 'application/'))) ? '#' : '';  // avoid both "missing attachment" caption and blank image div
            }
        }

        return $data;
    }

    function fltGetAttachmentMetadata($data, $post_id)
    {
        if (Teaser::instance()->isTeaser() && is_array($data)) {
            $data['file'] = '';
            $data['sizes'] = [];
        }

        return $data;
    }

    // Strips comments from teased posts/pages
    public function fltCommentsResults($results)
    {
        if (!$results) {
            return $results;
        }
        
        if ($teased_posts = Teaser::instance()->getTeasedPosts()) {
            foreach ($results as $key => $row) {
                if (isset($row->comment_post_ID) && isset($teased_posts[$row->comment_post_ID])) {
                    unset($results[$key]);
                }
            }
        }

        return $results;
    }
}

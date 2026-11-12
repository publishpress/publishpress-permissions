<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class PostFiltersFront
{
    var $archives_where = '';

    public function __construct()
    {
        if (!presspermit()->isContentAdministrator()) {
            require_once(__DIR__ . '/PostFiltersFrontNonAdministrator.php');
            new PostFiltersFrontNonAdministrator();
        }

        add_filter('get_previous_post_where', [$this, 'fltAdjacentPostWhere']);
        add_filter('get_next_post_where', [$this, 'fltAdjacentPostWhere']);

        add_filter('getarchives_where', [$this, 'fltGetarchivesWhere']);

        add_filter('shortcode_atts_gallery', [$this, 'fltAttsGallery'], 10, 3);

        if (!PWP::empty_REQUEST('preview')) {
            add_filter('wp_link_pages_link', [$this, 'fltPagesLink']);
        }

        add_action('template_redirect', [$this, 'actRegulateTaxonomyArchivePage']);

        do_action('presspermit_post_filters_front');
    }

    function actRegulateTaxonomyArchivePage() {
        global $wp_query;

        if (empty($wp_query) || presspermit()->isContentAdministrator() || !presspermit()->getOption('regulate_category_archive_page')) {
            return;
        }

        $tax_query = [];

        if (!empty($wp_query->is_category) && !empty($wp_query->query_vars['cat']) 
        && in_array('category', presspermit()->getEnabledTaxonomies())
        ) {
            $tax_query['category'] = (array) $wp_query->query_vars['cat'];

        } elseif (!empty($wp_query->is_tax) && !empty($wp_query->tax_query) && !empty($wp_query->tax_query->queries)) {
            foreach ($wp_query->tax_query->queries as $vars) {
                if (isset($vars['taxonomy']) && isset($vars['terms'])) {
                    $tax_query[$vars['taxonomy']] = $vars['terms'];
                }
            }
        }

        foreach ($tax_query as $archive_taxonomy => $query_term_ids) {
            $user = presspermit()->getUser();
 
            foreach ($query_term_ids as $k => $_term) {
                if (!is_numeric($_term)) {
                    if ($term = get_term_by('slug', $_term, $archive_taxonomy)) {
                        $query_term_ids[$k] = $term->term_taxonomy_id;
                    } else {
                        unset($query_term_ids[$k]);
                    }
                } else {
                    $query_term_ids[$k] = PWP::termidToTtid($_term, $archive_taxonomy);
                }
            }

            // Make sure the user has term restrictions. Otherwise, treat empty result set as due to no posts being assigned to this category.
            foreach ($user->except['read_post']['term'] as $exception_taxonomy => $exceptions) {
                if ($query_term_ids && in_array($exception_taxonomy, [$archive_taxonomy, ''])) {
                    foreach ($exceptions as $modification => $exceptions_by_post_type) {
                        
                        if (in_array($modification, ['exclude'])) {
                            $matched_term = false;

                            foreach ($exceptions_by_post_type as $_post_type => $term_exceptions_by_status) {
                                if (isset($term_exceptions_by_status[''])) {
                                    if (array_intersect($query_term_ids, $term_exceptions_by_status[''])) {
                                        $any_term_exceptions = true;
                                        break 3;
                                    }
                                }
                            }
                        } elseif ('include' == $modification) {
                            $matched_term = false;

                            foreach ($exceptions_by_post_type as $_post_type => $term_exceptions_by_status) {
                                if (isset($term_exceptions_by_status[''])) {

                                    if (!array_intersect($query_term_ids, $term_exceptions_by_status[''])) {
                                        $any_term_exceptions = true;
                                        break 3;
                                    }
                                }
                            }
                        }
                    } 
                }
            }
            
            if (!empty($any_term_exceptions)) {
                if (!get_terms(
                    [
                        'taxonomy' => $archive_taxonomy,
                        'fields' => 'ids', 
                        'required_operation' => 'read', 
                        'hide_empty' => true, 
                        'term_taxonomy_id' => PWP::termidToTtid($query_term_ids, $archive_taxonomy)
                    ]
                    )
                ) {
                    if ($teased_types = apply_filters('presspermit_teased_post_types', [], ['post'], [])) {
                        $term = $wp_query->get_queried_object();
                        do_action('presspermit_force_term_teaser', $term);
                    } else {
                        $wp_query->is_404 = true;
                    }
                }
            }
        }

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        // @todo: is_tag: query_vars['post_tag'], is_tax: query_vars['tax_query']
    }

    public function fltAttsGallery($out, $pairs, $atts)
    {
        if (!empty($atts['include'])) // force subsequent get_posts() query to be filtered for PP exceptions
            add_action('pre_get_posts', [$this, 'actGetGalleryPosts']);

        return $out;
    }

    public function actGetGalleryPosts(&$query_obj)
    {
        $query_obj->query_vars['suppress_filters'] = false;
        remove_action('pre_get_posts', [$this, 'actGetGalleryPosts']);
    }

    // custom wrapper to clean up after get_previous_post_where, get_next_post_where nonstandard arg syntax 
    // (uses alias p for post table, passes "WHERE post_type=...)
    public function fltAdjacentPostWhere($where)
    {
        global $wpdb, $current_user;

        $post_type = PWP::findPostType();

        $limit_statuses = array_merge(
            PWP::getPostStatuses(['public' => true, 'post_type' => $post_type]),
            PWP::getPostStatuses(['private' => true, 'post_type' => $post_type])
        );

        if (!empty($current_user->ID)) {
            $where = str_replace(" AND p.post_status = 'publish'", " AND p.post_status IN ('" . implode("','", $limit_statuses) . "')", $where);
        }

        // get_adjacent_post() function includes 'WHERE ' at beginning of $where
        $where = str_replace('WHERE ', 'AND ', $where);

        if ($limit_statuses) {
            $limit_statuses = array_fill_keys($limit_statuses, true);
        }

        $args = ['post_types' => $post_type, 'source_alias' => 'p', 'skip_teaser' => true, 'limit_statuses' => $limit_statuses];

        $where = 'WHERE 1=1 ' . apply_filters('presspermit_posts_where', $where, $args);

        return $where;
    }

    public function fltGetarchivesWhere($where)
    {
        global $current_user, $wpdb;

        // possible todo: implement in any other PP filters?
        require_once(PRESSPERMIT_CLASSPATH_COMMON . '/SqlTokenizer.php');
        $parser = new \PressShack\SqlTokenizer();
        $post_type = $parser->ParseArg($where, 'post_type');

        $where = str_replace("WHERE post_type", "WHERE $wpdb->posts.post_date > 0 AND post_type", $where);

        $stati = array_merge(
            PWP::getPostStatuses(['public' => true, 'post_type' => $post_type]),
            PWP::getPostStatuses(['private' => true, 'post_type' => $post_type])
        );

        if (!empty($current_user->ID)) {
            $where = str_replace("AND post_status = 'publish'", "AND post_status IN ('" . implode("','", $stati) . "')", $where);
        }

        $where = str_replace("WHERE $wpdb->posts.post_date > 0", "AND $wpdb->posts.post_date > 0", $where);

        $where = apply_filters('presspermit_posts_where', $where, ['skip_teaser' => true, 'post_type' => $post_type]);

        $where = 'WHERE 1=1 ' . $where;

        $this->archives_where = $where;

        return $where;
    }

    public function fltGetArchivesRequest($request)
    {
        if (0 === strpos($request, "SELECT YEAR(post_date) AS")) {
            $request = str_replace("SELECT YEAR(post_date) AS", "SELECT DISTINCT YEAR(post_date) AS", $request);
        } else {
            global $wpdb;
            $request = str_replace("SELECT * FROM $wpdb->posts", "SELECT DISTINCT * FROM $wpdb->posts", $request);
        }

        remove_filter('query', ['PostFiltersFront', 'fltGetArchivesRequest'], 50);
        return $request;
    }

    public function fltPagesLink($pagenum_link)
    {
        $matches = [];

        if (preg_match_all('/href="([^"]*)"/', $pagenum_link, $matches)) {
            $append_arg = (strpos($matches[1][0], '?p=') || strpos($matches[1][0], '&p=')) ? '&preview=true' : '?preview=true';

            // only do magic if the link does not already contain preview directive
            if (!strpos($matches[1][0], 'preview=1') && !strpos($matches[1][0], 'preview=true')) {
                $pagenum_link = str_replace($matches[1][0], $matches[1][0] . $append_arg, $pagenum_link);
            }
        }

        return $pagenum_link;
    }
}

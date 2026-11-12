<?php
namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class CollabHooksAdminNonAdministrator
{
    function __construct()
    {
        add_filter('get_terms_args', [$this, 'fltGetTermsArgs'], 50, 2);
        add_filter('terms_clauses', [$this, 'fltGetTermsClauses'], 2, 3);
        add_filter('presspermit_get_terms_operation', [$this, 'fltGetTermsOperation'], 10, 3);
        add_filter('presspermit_get_terms_universal_exceptions', [$this, 'fltGetTermsUniversalExceptions'], 10, 4);
        add_filter('presspermit_get_terms_exceptions', [$this, 'fltGetTermsExceptions'], 10, 6);
        add_filter('presspermit_get_terms_additional', [$this, 'fltGetTermsAdditional'], 10, 5);

        add_filter('presspermit_ajax_edit_actions', [$this, 'fltAjaxEditActions']);
        add_filter('presspermit_get_posts_operation', [$this, 'fltGetPostsOperation'], 10, 2);
        add_filter('presspermit_is_front', [$this, 'fltIsFront']);

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/CommentFiltersAdmin.php');
        new Collab\CommentFiltersAdmin();
    }

    function fltIsFront($is_front)
    {
        if (defined('REST_REQUEST') && presspermit()->doingREST()) {
            // rest_pre_dispatch filter only allows with matching rest method (GET, etc)
            if ('read' == \PublishPress\Permissions\REST::instance()->operation)
                return true;
        }

        return $is_front;
    }

    function fltAjaxEditActions($actions)
    { 
        $actions = array_merge($actions, ['query-attachments', 'mla-query-attachments']);
        return $actions;
    }

    function fltGetPostsOperation($required_operation, $args)
    {
        if (defined('REST_REQUEST') && presspermit()->doingREST()) {
            // rest_pre_dispatch filter only allows with matching rest method (GET, etc)
            if ('read' == \PublishPress\Permissions\REST::instance()->operation) {
                return 'read';
            }
        }

        return $required_operation;
    }

    function fltGetTermsOperation($required_operation, $taxonomies, $args)
    {
        global $pagenow;

        if (defined('REST_REQUEST') && presspermit()->doingREST()) {
            $rest = \PublishPress\Permissions\REST::instance();

            // Terms listing
            if ('WP_REST_Terms_Controller' == $rest->endpoint_class) {
                return ('edit' == $rest->operation) ? 'manage' : $rest->operation;
            }
        } elseif (in_array($pagenow, ['edit-tags.php', 'nav-menus.php'])) {
            $required_operation = (!PWP::empty_REQUEST('tag_ID') && (empty($args['name']) || ('parent' != $args['name']))) 
            ? 'manage' 
            : 'associate';
        }

        return $required_operation;
    }

    function fltGetTermsArgs($args, $taxonomies)
    {
        // terms query should be limited to a single object type for post.php, post-new.php, so only return caps for that object type
        global $pagenow;

        if (defined('REST_REQUEST') && presspermit()->doingREST()) {
            if (empty($args['object_type']) && \PublishPress\Permissions\REST::getPostType()) {
                $args['object_type'] = \PublishPress\Permissions\REST::getPostType();
            }

            // Force term retrieval for Gutenberg UI construction to be filtered by 'assign' exceptions, not 'read' exceptions
            if ((empty($args['required_operation']) || ($args['required_operation'] == 'read'))) {
                if (!empty($_SERVER['HTTP_REFERER']) && false !== strpos(esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])), PWP::admin_rel_url('post'))) {
                    $args['required_operation'] = 'assign';
                }
            }
        } elseif (in_array($pagenow, ['post.php', 'post-new.php', 'press-this.php']))
            $args['object_type'] = PWP::findPostType();

        return $args;
    }

    function fltGetTermsClauses($clauses, $taxonomies, $args)
    {
        // If we are dealing with a post taxonomy on a Post Edit Form, include currently stored terms.  
        // User will still not be able to remove them without proper editing roles for object.
        global $pagenow;

        if ('post.php' == $pagenow) {
            if ($object_id = PWP::getPostID()) {
                // --------------- Polylang workaround -------------------
                if (count($taxonomies) > 0 || !isset($taxonomies[0])) {
                    return $clauses;
                }

                $tx_name = (is_object($taxonomies[0]) && isset($taxonomies[0]->name)) ? $taxonomies[0]->name : $taxonomies[0];

                if (!presspermit()->isTaxonomyEnabled($tx_name))
                    return $clauses;
                //---------------------------------------------------------

                // Don't filter get_terms() call in edit_post(), which invalidates entry term selection if existing term is detected
                if (!empty($args['name']) && !PWP::empty_POST())
                    return $clauses;

                if ($tt_ids = Collab::getObjectTerms($object_id, $taxonomies[0], ['fields' => 'tt_ids', 'pp_no_filter' => true])) {
                    $clauses['where'] = "( {$clauses['where']} OR tt.term_taxonomy_id IN ('" . implode("','", $tt_ids) . "') )";
                }
            }
        }

        return $clauses;
    }

    function fltGetTermsUniversalExceptions($universal, $required_operation, $taxonomy, $args)
    {
        if ('assign' == $required_operation) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/TermFiltersAdmin.php');
            return Collab\TermFiltersAdmin::fltGetTermsUniversalExceptions($universal, $required_operation, $taxonomy, $args);
        }

        return $universal;
    }

    function fltGetTermsExceptions($tt_ids, $required_operation, $mod_type, $post_type, $taxonomy, $args = [])
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/TermFiltersAdmin.php');
        return Collab\TermFiltersAdmin::fltGetTermsExceptions($tt_ids, $required_operation, $mod_type, $post_type, $taxonomy, $args);

        return $tt_ids;
    }

    function fltGetTermsAdditional($additional_tt_ids, $required_operation, $post_type, $taxonomy, $args)
    {
        if ('assign' == $required_operation) {
            if ($_edit_tt_ids = presspermit()->getUser()->getExceptionTerms('edit', 'additional', $post_type, $taxonomy, ['status' => true]))
                $additional_tt_ids = array_merge($additional_tt_ids, Arr::flatten($_edit_tt_ids));
        }

        return $additional_tt_ids;
    }
}

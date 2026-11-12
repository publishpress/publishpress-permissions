<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class REST_Workarounds
{
    var $buffer_taxonomies = [];

    function __construct() {
        add_action('presspermit_user_init', [$this, 'actHandleRestTermAssignment'], 50);

        add_action('plugins_loaded', function() {
            foreach (presspermit()->getEnabledPostTypes() as $post_type) { 
                add_action("rest_insert_{$post_type}", [$this, 'actRestDisableDirectTermAssignment']);
                add_action("rest_after_insert_{$post_type}", [$this, 'actRestorePostTypeTaxonomies'], 1);  // early execution
            }
        });
    }

    function actRestDisableDirectTermAssignment($post) {
        global $wp_taxonomies;

		// Prevent WP_REST_Posts_Controller::handle_terms from making a redundant, unfilterable wp_set_object_terms() call
		// todo: WP Trac ticket

        if ($type_obj = get_post_type_object($post->post_type)) {
            $this->buffer_taxonomies = $wp_taxonomies;

            foreach((array) $wp_taxonomies as $tax_name => $tax_obj) {
                $wp_taxonomies[$tax_name]->object_type = array_diff((array) $wp_taxonomies[$tax_name]->object_type, [$post->post_type]);
            }
        }
    }

    function actRestorePostTypeTaxonomies($post) {
        global $wp_taxonomies;

        // @todo: alternate method to regulate term assignment

        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $wp_taxonomies = $this->buffer_taxonomies;
    }

    function actHandleRestTermAssignment()
    {
        if (empty($_SERVER['REQUEST_URI'])) {
            return;
        }

        if (false === strpos(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), '/wp-json/wp/v2'))
            return;

        $request_uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));

        $pp = presspermit();

        foreach ($pp->getEnabledPostTypes([], 'object') as $type_obj) {
            if (empty($type_obj) || (!isset($type_obj->name) && !isset($type_obj->rest_base))) {
                continue;
            }
            $type_rest_base = (!empty($type_obj->rest_base)) ? $type_obj->rest_base : $type_obj->name;

            if (false === strpos($request_uri, "/wp-json/wp/v2/$type_rest_base/"))
                continue;

            $matches = [];
            preg_match("/wp-json\/wp\/v2\/" . $type_rest_base . "\/([0-9]+)/", $request_uri, $matches);

            if (empty($matches[1])) {
                continue;
            }

            $post_id = $matches[1];

            // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsRemoteFile
            $payload_vars = json_decode(file_get_contents('php://input'), true);

            $enabled_taxonomies = $pp->getEnabledTaxonomies([], 'object');

            foreach ($enabled_taxonomies as $tx_obj) {
                $rest_base = (!empty($tx_obj->rest_base)) ? $tx_obj->rest_base : $tx_obj->name;

                if (is_array($payload_vars) && isset($payload_vars[$rest_base])) {
                    global $typenow;

                    // @todo: alternate method to regulate term assignment

                    // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                    $typenow = $type_rest_base;

                    $_REQUEST['post_type'] = $type_rest_base;
                    $_POST['post_type'] = $type_rest_base;
                    break;

                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                } elseif (!empty($_REQUEST[$rest_base])) { // legacy Gutenberg versions
                    
                    // No nonce verification because we need to deal with the term assignment attempt in any case

                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $selected_terms = array_intersect(array_map('intval', (array) $_REQUEST[$rest_base]), $user_terms);
                    
                    $taxonomy = $tx_obj->name;

                    $user_terms = get_terms(
                        ['taxonomy' => $taxonomy, 'required_operation' => 'assign', 'hide_empty' => 0, 'fields' => 'ids', 'post_type' => $type_obj->name]
                    );

                    $stored_terms = Collab::getObjectTerms($post_id, $taxonomy, ['fields' => 'ids']);

                    if (!defined('PPCE_DISABLE_' . strtoupper($taxonomy) . '_RETENTION')) {
                        if ($deselected_terms = array_diff($stored_terms, $selected_terms)) {
                            if ($unremovable_terms = array_diff($deselected_terms, $user_terms)) {
                                $selected_terms = array_map('strval', array_merge($selected_terms, $unremovable_terms));
                            }
                        }
                    }

                    $_REQUEST[$rest_base] = $selected_terms;
                    $_POST[$rest_base] = $selected_terms;
                }
            }

            break; // post type was matched to REST request 
        } // end foreach type
    }
}

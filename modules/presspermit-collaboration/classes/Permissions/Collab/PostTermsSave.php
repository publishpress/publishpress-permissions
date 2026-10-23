<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class PostTermsSave
{
    public static function getObjectTerms($object_ids, $taxonomy, $args = [])
    {
        global $wpdb;

        if (empty($object_ids) || !$taxonomy)
            return [];

        if (!is_array($object_ids))
            $object_ids = [$object_ids];
        $object_ids = array_map('intval', $object_ids);

        $defaults = [
            'fields' => 'all',
            'parent' => '',
        ];
        $args = wp_parse_args($args, $defaults);

        $terms = [];

        $t = get_taxonomy($taxonomy);
        if (isset($t->args) && is_array($t->args))
            $args = array_merge($args, $t->args);

        $fields = $args['fields'];

        $select_this = '';
        if ('all' == $fields) {
            $select_this = 't.*, tt.*';
        } elseif ('ids' == $fields) {
            $select_this = 't.term_id';
        } elseif ('names' == $fields) {
            $select_this = 't.name';
        }

        $object_id_array = $object_ids;
        $object_id_csv = implode("','", array_map('intval', $object_ids));

        $objects = false;
        if ('all' == $fields) {
            // phpcs Note: Permissions handles low-level filtering, so sometimes we need a direct terms query without any external filtering

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $_terms = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT $select_this FROM $wpdb->terms AS t"                          // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    . " INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = t.term_id"
                    . " INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id"
                    . " WHERE tt.taxonomy = %s AND tr.object_id IN ('$object_id_csv')",   // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    
                    $taxonomy
                )
            );

            $object_id_index = [];
            foreach ($_terms as $key => $term) {
                $term = sanitize_term($term, $taxonomy, 'raw');
                $_terms[$key] = $term;

                if (isset($term->object_id)) {
                    $object_id_index[$key] = $term->object_id;
                }
            }

            $terms = array_merge($terms, $_terms);
            $objects = true;

        } elseif ('ids' == $fields || 'names' == $fields || 'slugs' == $fields) {
            // phpcs Note: Permissions handles low-level filtering, so sometimes we need a direct terms query without any external filtering
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $_terms = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT $select_this FROM $wpdb->terms AS t"                          // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    . " INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = t.term_id"
                    . " INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id"
                    . " WHERE tt.taxonomy = %s AND tr.object_id IN ('$object_id_csv')",   // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    
                    $taxonomy
                )
            );

            $_field = ('ids' == $fields) ? 'term_id' : 'name';
            foreach ($_terms as $key => $term) {
                $_terms[$key] = sanitize_term_field($_field, $term, $term, $taxonomy, 'raw');
            }
            $terms = array_merge($terms, $_terms);

        } elseif ('tt_ids' == $fields) {
            // phpcs Note: Permissions handles low-level filtering, so sometimes we need a direct terms query without any external filtering
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $terms = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT tr.term_taxonomy_id FROM $wpdb->term_relationships AS tr"
                    . " INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id"
                    . " WHERE tr.object_id IN ('$object_id_csv') AND tt.taxonomy = %s",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                    $taxonomy
                )
            );
        }

        if (!$terms) {
            $terms = [];
        } elseif ($objects) {
            $_tt_ids = [];
            $_terms = [];
            foreach ($terms as $term) {
                if (in_array($term->term_taxonomy_id, $_tt_ids)) {
                    continue;
                }

                $_tt_ids[] = $term->term_taxonomy_id;
                $_terms[] = $term;
            }
            $terms = $_terms;
        } else {
            $terms = array_values(array_unique($terms));
        }

        return $terms;
    }

    public static function getPostedObjectTerms($taxonomy)
    {
        // @todo: confirm this is still needed

        if (defined('XMLRPC_REQUEST')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostTermsSaveXmlRpc.php');
            return PostTermsSaveXmlRpc::getPostedXmlrpcTerms($taxonomy);
        }

        // phpcs Note: Sometimes we need to check the terms submission directly to work around chicken/egg situations with permissions dependent on post terms

        // phpcs Note: Nonce verification is unnecessary here because the term submission check is needed regardless of how the post save is initiated

        if ('category' == $taxonomy) {
            $post_category = (!empty($_POST['post_category']))                                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            ? array_map('intval', (array) $_POST['post_category'])                                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            : [];

            if ($post_category) {
				return array_map('intval', self::fltPreObjectTerms($post_category, $taxonomy));
            }
        } else {
            $tx_obj = get_taxonomy($taxonomy);
            if ($tx_obj && !empty($tx_obj->object_terms_post_var)) {
                $post_terms = (!empty($_POST[$tx_obj->object_terms_post_var]))                     // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                ? array_map('intval', (array) $_POST[$tx_obj->object_terms_post_var])              // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                : [];
                
                return array_map('intval', $post_terms);

            } elseif (!empty($_POST['tax_input'][$taxonomy])) {                                         // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                if (is_taxonomy_hierarchical($taxonomy) && is_array($_POST['tax_input'][$taxonomy])) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                    return array_map('intval', $_POST['tax_input'][$taxonomy]);                         // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                } else {
                    // phpcs note: the input is actually sanitized; we just need to check whether it's an array

                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $terms = (is_array($_POST['tax_input'][$taxonomy]))                                 // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                    ? array_map('sanitize_key', $_POST['tax_input'][$taxonomy])                         // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                    : array_map('sanitize_key', explode(',', sanitize_text_field(wp_unslash($_POST['tax_input'][$taxonomy]))));  // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

                    $term_info = self::parseTermNames($terms, $taxonomy);
                    return array_map('intval', self::fltPreObjectTerms($term_info['terms'], $taxonomy));
                }
            } elseif ('post_tag' == $taxonomy && !empty($_POST['tags_input'])) {                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                // phpcs note: the input is actually sanitized; we just need to check whether it's an array

                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $terms = (is_array($_POST['tags_input']))                                                  // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                    ? array_map('sanitize_key', $_POST['tags_input'])                                      // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
                    : array_map('sanitize_key', explode(',', sanitize_text_field(wp_unslash($_POST['tags_input']))));  // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

                $term_info = self::parseTermNames($terms, $taxonomy);
                return array_map('intval', self::fltPreObjectTerms($term_info['terms'], $taxonomy));
            }
        }

        return [];
    }

    public static function fltTagsInput($tags_input)
    {
        $arr = self::fltTaxInput(
            ['post_tag' => (array) $tags_input]
        );

        return (isset($arr['post_tag'])) ? $arr['post_tag'] : [];
    }

    public static function fltTaxInput($tax_input)
    {
        if (!is_array($tax_input)) {
            return $tax_input;
        }

        foreach ((array)$tax_input as $taxonomy => $terms) {
            if (is_string($taxonomy) && !is_numeric($taxonomy)) {
            	$tax_input[$taxonomy] = self::fltTermsInput($terms, $taxonomy);
            }
        }

        return $tax_input;
    }

    public static function fltTermsInput($terms, $taxonomy, $args = [])
    {
        $pp = presspermit();

        $enabled_taxonomies = $pp->getEnabledTaxonomies();

        if (!in_array($taxonomy, $enabled_taxonomies, true)) {
            return $terms;
        }

        if (null === $terms) {
            $terms = [];
        }

        if (is_string($terms) || (!is_taxonomy_hierarchical($taxonomy))) {  // non-hierarchical taxonomy (tags)
            if (is_string($terms)) {
                $term_info = self::parseTermNames($terms, $taxonomy);
                foreach (['terms', 'names_by_id', 'new_terms'] as $var) {
                    $$var = $term_info[$var];
                }
            } else {
                // WP tax_input['post_tag'] is an array, but with existing terms as numeric IDs and new terms as submitted names
                $term_ids = [];
                $names_by_id = [];
                $new_terms = [];
                foreach ($terms as $_term) {
                    if (is_string($_term)) {
                        $term_info = self::parseTermNames((array)$_term, $taxonomy);

                        if (!empty($term_info['terms'])) {
                            $term_id = reset($term_info['terms']);
                            $term_ids[] = $term_id;

                            if (!empty($term_info['names_by_id']))
                                $names_by_id[$term_id] = reset($term_info['names_by_id']);
                        } else {
                            $new_terms [] = reset($term_info['new_terms']);
                        }
                    } else {
                        $term_ids[] = $_term;
                    }
                }

                $terms = $term_ids;
            }

            $user = presspermit()->getUser();

            // if term assignment is limited to a fixed set, ignore any attempt to assign a newly created term
            if ($user->getExceptionTerms('assign', 'include', PWP::findPostType(), $taxonomy) 
            || $user->getExceptionTerms('assign', 'include', '', $taxonomy)
            ) {
                $new_terms = [];
            }

            $filtered_terms = self::fltPreObjectTerms($terms, $taxonomy, $args);

            // names_by_id returned from parseTermNames() includes only selected terms, not default or alternate terms which may have been filtered in
            foreach ($filtered_terms as $term_id) {
                if (!isset($names_by_id[$term_id])) {
                    if ($term = get_term_by('id', $term_id, $taxonomy))
                        $names_by_id[$term->term_id] = $term->name;
                }
            }

            $_terms = array_merge(array_intersect_key($names_by_id, array_flip($filtered_terms)), $new_terms);

            $terms = (is_string($terms)) ? implode(",", $_terms) : $_terms;
        } else {
            $term_ids = [];

            foreach ($terms as $_term) {
                if (is_object($_term)) {
                    $term_ids[] = $_term->term_id;
                    $pass_objects = true;
                } else {
                    $term_ids[] = $_term;
                }
            }

            $term_ids = self::fltPreObjectTerms($term_ids, $taxonomy);

            if (!empty($pass_objects)) {
                $_terms = [];

                foreach($term_ids as $term_id) {
                    $_terms []= get_term($term_id, $taxonomy);
                }

                return $_terms;
            } else {
                $terms = $term_ids;
            }
        }

        return $terms;
    }

    private static function parseTermNames($names, $taxonomy)
    {
        $arr_names = (is_array($names)) ? $names : explode(",", $names);

        $names_by_id = $terms = [];
        $new_terms = [];

        // convert tag names to ids for filtering
        foreach ($arr_names as $term_name) {
            if ($term = get_term_by('name', $term_name, $taxonomy)) {
                $terms [] = $term->term_id;
                $names_by_id[$term->term_id] = $term_name;
            } else {
                $new_terms [] = $term_name;
            }
        }

        return compact(['terms', 'names_by_id', 'new_terms']);
    }

    public static function fltPreObjectTerms($selected_terms, $taxonomy, $args = [])
    {
        $pp = presspermit();

        if (!$pp->filteringEnabled() || !$pp->isTaxonomyEnabled($taxonomy)) {
            if (empty($args['force_filtering'])) {
                return $selected_terms;
            }
        }

        if (!empty($args['object_id'])) {
            $object_id = $args['object_id'];
        } else {
            $editing_post_id = PWP::getPostID();
            $sanitizing_post_id = presspermit()->getCurrentSanitizePostID();

            // Don't auto-assign terms if this post is not the one being edited. But new posts will be initially sanitized prior to ID assignment
            if ($sanitizing_post_id && ($sanitizing_post_id != $editing_post_id)) {
                return $selected_terms;
            }

            $object_id = ($sanitizing_post_id) ? $sanitizing_post_id : $editing_post_id;
        }

        // strip out fake term_id -1 (if applied)
        if ($selected_terms && is_array($selected_terms)) {
            // not sure who is changing empty $_POST['post_category'] array to an array with nullstring element, but we have to deal with that
            $selected_terms = array_diff($selected_terms, [-1, 0, '0', '-1', '']);
        }

        if (defined('REVISIONARY_VERSION')) {
            global $revisionary;
            if (!empty($revisionary->admin->impose_pending_rev))
                return $selected_terms;
        }

        if (!empty($args['stored_terms'])) {
            $stored_terms = $args['stored_terms'];
        }

        $post_type = (!empty($args['post_type'])) ? $args['post_type'] : PWP::findPostType();

        // don't filter selected terms for content administrator, but still need to apply default term as needed when none were selected
        if ($pp->isUserUnfiltered() && empty($args['force_filtering'])) {
            $user_terms = $selected_terms;
        } else {
            if (!is_array($selected_terms))
                $selected_terms = [];

            $user_terms = get_terms(
                ['taxonomy' => $taxonomy, 'fields' => 'ids', 'hide_empty' => false, 'required_operation' => 'assign', 'object_type' => $post_type]
            );

            $user = presspermit()->getUser();

            // If restrictive editing exceptions based on term assignment are set but term assignment exceptions are not explicitly set,
            // store a default term from user's set of editable terms
            if ($user->getExceptionTerms('edit', 'include', $post_type, $taxonomy) 
                || $user->getExceptionTerms('edit', 'include', '', $taxonomy)
                || $user->getExceptionTerms('edit', 'exclude', $post_type, $taxonomy)
                || $user->getExceptionTerms('edit', 'exclude', '', $taxonomy)
            ) {
                $select_default_term = true;

                if (!$user->getExceptionTerms('assign', 'include', $post_type, $taxonomy) 
                    && !$user->getExceptionTerms('assign', 'include', '', $taxonomy)
                    && !$user->getExceptionTerms('assign', 'exclude', $post_type, $taxonomy)
                    && !$user->getExceptionTerms('assign', 'exclude', '', $taxonomy)
                    && !$user->getExceptionTerms('assign', 'additional', '', $taxonomy)
                    && !$user->getExceptionTerms('assign', 'additional', '', $taxonomy)
                ) {
                    $user_edit_terms = get_terms(
                        ['taxonomy' => $taxonomy, 'fields' => 'ids', 'hide_empty' => false, 'required_operation' => 'edit', 'object_type' => $post_type]
                    );

                    // This should be redundant, but be sure that editing lockout on new post creation does not occur unless term exceptions are manually configured to allow it (pre-publication workflow submission)
                    $user_terms = array_intersect($user_terms, $user_edit_terms);
                }
            }

            $selected_terms = array_intersect($selected_terms, $user_terms);

            if ($object_id) {
                if (!isset($stored_terms)) {
                	$stored_terms = Collab::getObjectTerms($object_id, $taxonomy, ['fields' => 'ids', 'pp_no_filter' => true]);
                }

                if (!defined('PPCE_DISABLE_' . strtoupper($taxonomy) . '_RETENTION')) {
                    if ($deselected_terms = array_diff($stored_terms, $selected_terms)) {
                        if ($unremovable_terms = array_diff($deselected_terms, $user_terms))
                            $selected_terms = array_merge($selected_terms, $unremovable_terms);
                    }
                }
            }
        }
		
        if (empty($selected_terms) && ((is_taxonomy_hierarchical($taxonomy) 
        && ('post_tag' != $taxonomy)) || self::userHasTermLimitations($taxonomy))
        ) {
            if ($tx_obj = get_taxonomy($taxonomy)) {
                // For now, always check the DB for default terms.  todo: only if the default_term_option property is set
                if (isset($tx_obj->default_term_option))
                    $default_term_option = $tx_obj->default_term_option;
                else
                    $default_term_option = "default_{$taxonomy}";

                $default_terms = (array) get_option($default_term_option);
            } else {
                $default_terms = [];
            }

            // But if the default term is not defined or is not in user's subset of usable terms, substitute first available
            if ($user_terms) {
                if (true === $user_terms)
                    $filtered_default_terms = $default_terms;
                else
                    $filtered_default_terms = array_intersect($default_terms, $user_terms);

                if ($filtered_default_terms) {
                    $default_terms = $filtered_default_terms;

                } elseif (is_array($user_terms)) {
					// This excecutes only if no default terms are user-assignable
					
                    sort($user_terms); // default to lowest ID term

                    // If user has any "include" or "additional" term exceptions, substitute 1st available term, contingent on certain conditions or constant definitions. 
                    // (Previously assigned regardless of user's term exceptions)
                    // (Even earlier, always assigned regardless of $select_default_term flag or $user_terms count)
                    if ((
                        presspermit()->getOption('auto_assign_available_term')
                        && ((!defined('PP_AUTO_DEFAULT_SINGLE_TERM_ONLY') && empty($args['is_auto_draft'])) || (!empty($select_default_term) && empty($args['is_auto_draft'])) || (count($user_terms) == 1))
						&& !defined('PP_NO_AUTO_DEFAULT_' . strtoupper($taxonomy))
                        && (defined('PP_AUTO_DEFAULT_TERM_EXCEPTIONS_NOT_REQUIRED') || self::userHasTermLimitations($taxonomy, ['include', 'additional'], $post_type))
                        )
					|| defined('PP_AUTO_DEFAULT_' . strtoupper($taxonomy))
					) {
                        if (!$object_id || // Never auto-assign terms to the front page or posts
                        (
                        	((int) $object_id !== (int) get_option('page_on_front')) 
                        	&& ((int) $object_id !== (int) get_option('page_for_posts'))
                        )) {
                            $default_terms = apply_filters('presspermit_auto_assign_terms', (array) $user_terms[0], $taxonomy, $object_id, $args, $user_terms);
                        }
                    } else {
                    	$default_terms = [];
                    }
                }

                $selected_terms = array_unique(array_map('intval', $default_terms));

            } elseif (!empty($stored_terms)) {
                $selected_terms = array_unique(array_map('intval', $stored_terms)); // fallback is to currently stored terms
            }
        }

        if ($selected_terms && !is_taxonomy_hierarchical($taxonomy) && ('post_tag' != $taxonomy) && !empty($object_id)) {
            wp_set_object_terms($object_id, $selected_terms, $taxonomy);
        }

        return $selected_terms;
    }

    static function userHasTermLimitations($taxonomy, $mod_types = ['include'], $current_post_type = '')
    {
        if (!$current_post_type)
            $current_post_type = PWP::findPostType();

        $user = presspermit()->getUser();

        foreach (array_keys($user->except) as $for_op) {
            // only concerned about edit_post, revise_post, etc.
            if (in_array($for_op, ['read_post', 'edit_term', 'manage_term'], true))
                continue;

            foreach (array_keys($user->except[$for_op]) as $via_src) {
                // only consider exceptions assigned via specified taxonomy
                if (('term' != $via_src) || !isset($user->except[$for_op][$via_src][$taxonomy]))
                    continue;

                foreach (array_keys($user->except[$for_op][$via_src][$taxonomy]) as $mod_type) {
                     // only consider specified mod type(s)
                    if (in_array($mod_type, $mod_types, true)) {
                        foreach (array_keys($user->except[$for_op][$via_src][$taxonomy][$mod_type]) as $for_item_type) {
                            // only consider exceptions for current/specified post type
                            if (!in_array($for_item_type, [$current_post_type, ''], true)) 
                                continue;

                            foreach (array_keys($user->except[$for_op][$via_src][$taxonomy][$mod_type]) as $for_item_status) {
                                if (!empty($user->except[$for_op][$via_src][$taxonomy][$mod_type][$for_item_status])) {
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        }

        return false;
    }
}

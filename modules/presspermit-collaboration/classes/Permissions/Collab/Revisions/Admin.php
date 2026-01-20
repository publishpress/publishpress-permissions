<?php
namespace PublishPress\Permissions\Collab\Revisions;

class Admin
{
    function __construct() {
        add_filter('map_meta_cap', [$this, 'flt_mapMetaCap'], 3, 4);

        add_filter('presspermit_get_exception_items', [$this, 'flt_get_exception_items'], 10, 5);
        add_filter('presspermit_term_restrictions_clause', [$this, 'fltTermRestrictionsClause'], 10, 2);

        add_filter('presspermit_administrator_caps', [$this, 'flt_pp_administrator_caps'], 5);
    }

    public function flt_pp_administrator_caps($caps)
    {
        // todo: why is edit_others_revisions cap required for Administrators in Edit Posts listing (but not Edit Pages) ?

        $caps['edit_revisions'] = true;
        $caps['edit_others_revisions'] = true;
        $caps['list_others_revisions'] = true;
        $caps['delete_revisions'] = true;
        $caps['delete_others_revisions'] = true;

        return $caps;
    }

    public function flt_mapMetaCap($caps, $meta_cap, $user_id, $wp_args)
    {
        global $current_user;

        if ($user_id && in_array($meta_cap, ['edit_post', 'edit_page'], true) && !empty($wp_args[0]) 
			&& function_exists('rvy_get_option') && function_exists('rvy_default_options') // Revisions plugin does not initialize on plugins.php URL
		) {
            if ($user_id != $current_user->ID)
                return $caps;

            if (isset($_SERVER['SCRIPT_NAME']) && false !== strpos(sanitize_text_field($_SERVER['SCRIPT_NAME']), 'update.php')) { // Revisionary does not load on update.php
                return $caps;
            }

            $_post = (is_object($wp_args[0])) ? $wp_args[0] : get_post($wp_args[0]);

            if (!$_post) {
                return $caps;
            }

            if (rvy_get_option('require_edit_others_drafts') && apply_filters('revisionary_require_edit_others_drafts', true, $_post->post_type, $_post->post_status, $wp_args)) {
                if ($current_user->ID == $_post->post_author) {
                    return $caps;
                }

                $status_obj = get_post_status_object($_post->post_status);
                if (($status_obj && ($status_obj->public || $status_obj->private)) || rvy_in_revision_workflow($_post) || ('revision' == $_post->post_type)) {
                    return $caps;
                }

                $post_type_obj = get_post_type_object($_post->post_type);

                if (!$post_type_obj) {
                    return $caps;
                }

                if (isset($post_type_obj->cap->edit_others_posts) && !in_array($post_type_obj->cap->edit_others_posts, $caps)) {
                    return $caps;
                }

                // don't require any additional caps for sitewide Editors
                if (!empty($current_user->allcaps[$post_type_obj->cap->edit_published_posts])) {
                    return $caps;
                }

                if (!presspermit()->doing_cap_check) {
                    if (!empty($post_type_obj->cap->edit_others_posts) && empty($current_user->allcaps[$post_type_obj->cap->edit_others_posts])) {
                        $revise_cap = str_replace('edit_', 'revise_', $post_type_obj->cap->edit_others_posts);
                        
                        if (!empty($current_user->allcaps[$revise_cap])) {
                            $caps[]= $revise_cap;
                        } else {
                            $caps []= str_replace('edit_', 'list_', $post_type_obj->cap->edit_others_posts);
                        }

                        if (rvy_get_option('copy_posts_capability')) {
                            $copy_cap = str_replace('edit_', 'copy_', $post_type_obj->cap->edit_others_posts);
                            
                            if (!empty($current_user->allcaps[$copy_cap])) {
                                $caps[]= $copy_cap;
                            } else { 
                                $caps []= str_replace('edit_', 'copy_', $post_type_obj->cap->edit_others_posts);
                            }
                        }
                    }
                }

                if (empty($current_user->allcaps['edit_others_drafts'])) {
                	$caps[] = "edit_others_drafts";
                }
            }
        }

        return $caps;
    }

    // merge revise exceptions into edit exceptions
    public function flt_get_exception_items($exception_items, $operation, $mod_type, $for_item_type, $args = [])
    {
        global $revisionary;

        if (('edit' != $operation) || ('include' == $mod_type)) {
            return $exception_items;
        }

        // Modify Posts listing, but not 'edit_post' capability check
        if ((presspermit()->doing_cap_check && empty($args['merge_related_operations'])) || (!empty($args['has_cap_check']) && !defined('PRESSPERMIT_NO_REVISIONS_EXCEPTION_BYPASS'))) {
            return $exception_items;
        }

        // No need to do this if listing is being enabled by plugin setting
        if (presspermit()->getOption('list_others_uneditable_posts')) {
            return $exception_items;
        }

        $user = presspermit()->getUser();

        // No need to do this if listing is being enabled by capability assignment
        if ($type_obj = get_post_type_object($for_item_type)) {
            if (!empty($type_obj->cap->edit_others_posts)) {
                $cap_name = str_replace('edit_', 'list_', $type_obj->cap->edit_others_posts);

                if (!empty($user) && !empty($user->allcaps[$cap_name])) {
                    return $exception_items;
                }
            }
        }

        $defaults = ['via_item_source' => 'post', 'via_item_type' => '', 'status' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if ('term' == $via_item_source) {
            // Don't implement term exceptions by merging with edit_post exceptions, due to complication of applying revision exceptions for published posts only
            return $exception_items;
        }

        if (!isset($user->except['revise_post'])) {
            $user->retrieveExceptions('revise', 'post');
        }

        if (!isset($user->except['copy_post'])) {
            $user->retrieveExceptions('copy', 'post');
        }

        if (!isset($user->except['revise_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type])
        && !isset($user->except['copy_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type])
        ) {
            return $exception_items;
        }

        $exception_items = (isset($user->except['edit_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type])) 
        ? $user->except['edit_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type] 
        : [];

        foreach (array_keys($user->except['revise_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type]) as $_status) {
            Arr::setElem($exception_items, [$_status]);

            $exception_items[$_status] = array_merge(
                $exception_items[$_status], 
                $user->except['revise_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type][$_status]
            );
        }

        foreach (array_keys($user->except['copy_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type]) as $_status) {
            Arr::setElem($exception_items, [$_status]);

            $exception_items[$_status] = array_merge(
                $exception_items[$_status], 
                $user->except['copy_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type][$_status]
            );
        }

        if (true === $status) {
            return $exception_items;
        } else {
            return Arr::flatten(array_intersect_key($exception_items, [$status => true]));
        }

        return $exception_items;
    }

    // Apply term revision restrictions separately with status clause to avoid removing unpublished posts from the listing 
    function fltTermRestrictionsClause($where, $args) {
        global $wpdb;

        // Modify Posts listing, but not 'edit_post' capability check
        if (presspermit()->doing_cap_check) {
            return $where;
        }

        $defaults = array_fill_keys(
            ['required_operation',
            'post_type', 
            'src_table', 
            'merge_additions', 
            'exempt_post_types', 
            'mod_types', 
            'tx_args',
            'additional_ttids', 
            'apply_object_additions', 
            'term_additions_clause', 
            'post_additions_clause', 
            'type_exemption_clause'
            ], ''
        );

        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if ('read' == $required_operation) {
			return $where;
		}

        $user = presspermit()->getUser();

        $excluded_ttids_published = [];

        foreach (presspermit()->getEnabledTaxonomies($tx_args) as $taxonomy) {
            foreach ($mod_types as $mod) {
                if ($tt_ids = $user->getExceptionTerms('revise', $mod, $post_type, $taxonomy, ['status' => '', 'merge_universals' => true])) {    
                    if ($merge_additions) {
                        $tx_additional_ids = array_merge(
                            $user->getExceptionTerms('revise', 'additional', $post_type, $taxonomy, ['status' => '', 'merge_universals' => true])
                        );
                    } else {
                        $tx_additional_ids = [];
                    }
                    
                    $published_stati_csv = implode("','", get_post_stati(['public' => true, 'private' => true], 'names', 'OR' ));

                    if ('include' == $mod) {
                        if ($tx_additional_ids) {
                            $tt_ids = array_merge($tt_ids, $tx_additional_ids);
                        }

                        $tt_ids = array_map('intval', $tt_ids);

                        $term_include_clause = apply_filters(
                            'presspermit_term_include_clause',
                            "( $src_table.post_status NOT IN ('$published_stati_csv') OR $src_table.ID IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('" . implode("','", $tt_ids) . "') ) )",
                            compact('tt_ids', 'src_table')
                        );

                        $where .= " AND ( $term_include_clause $term_additions_clause $post_additions_clause $type_exemption_clause )";
                        continue 2;

                    } else {
                        if ($tx_additional_ids) {
                            $tt_ids = array_diff($tt_ids, $tx_additional_ids);
                        }

                        $excluded_ttids_published = array_merge($excluded_ttids_published, $tt_ids);
                    }
                }
            }
        }

        foreach (presspermit()->getEnabledTaxonomies($tx_args) as $taxonomy) {
            foreach ($mod_types as $mod) {
                if ($tt_ids = $user->getExceptionTerms('copy', $mod, $post_type, $taxonomy, ['status' => '', 'merge_universals' => true])) {    
                    if ($merge_additions) {
                        $tx_additional_ids = array_merge(
                            $user->getExceptionTerms('copy', 'additional', $post_type, $taxonomy, ['status' => '', 'merge_universals' => true])
                        );
                    } else {
                        $tx_additional_ids = [];
                    }
                    
                    $published_stati_csv = implode("','", get_post_stati(['public' => true, 'private' => true], 'names', 'OR' ));

                    if ('include' == $mod) {
                        if ($tx_additional_ids) {
                            $tt_ids = array_merge($tt_ids, $tx_additional_ids);
                        }

                        $tt_ids = array_map('intval', $tt_ids);

                        $term_include_clause = apply_filters(
                            'presspermit_term_include_clause',
                            "( $src_table.post_status NOT IN ('$published_stati_csv') OR $src_table.ID IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('" . implode("','", $tt_ids) . "') ) )",
                            compact('tt_ids', 'src_table')
                        );
                
                        $where .= " AND ( $term_include_clause $term_additions_clause $post_additions_clause $type_exemption_clause )";
                        continue 2;

                    } else {
                        if ($tx_additional_ids) {
                            $tt_ids = array_diff($tt_ids, $tx_additional_ids);
                        }

                        $excluded_ttids_published = array_merge($excluded_ttids_published, $tt_ids);
                    }
                }
            }
        }

        if ($excluded_ttids_published) {
            $where .= " AND ( ($src_table.post_status NOT IN ('$published_stati_csv') OR $src_table.ID NOT IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('"
                . implode("','", $excluded_ttids_published)
                . "') ) $type_exemption_clause ) $term_additions_clause $post_additions_clause )";
        }

        return $where;
    }
}

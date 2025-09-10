<?php

namespace PublishPress\Permissions\DB;

class Permissions
{
    // Variable to store hidden exceptions
    static $hidden_exceptions;

    public static function getRoles($agent_id, $agent_type = 'pp_group', $args = [])
    {
        global $wpdb;

        $pp = presspermit();

        $query_agent_ids = (isset($args['query_agent_ids'])) ? $args['query_agent_ids'] : false;

        $roles = [];

        $agent_type = sanitize_key($agent_type);

        if ($query_agent_ids) {
            static $agent_roles;
            static $last_query_key;
            if (!isset($agent_roles)) {
                $agent_roles = [];
                $last_query_agent_ids = false;
            }
            $query_key = md5(wp_json_encode($query_agent_ids)) . $agent_type;
        } else {
            $agent_roles = [];
            $query_agent_ids = (array)$agent_id;
            $query_key = false;
        }

        if (('pp_group' == $agent_type) && (!defined('PP_ALL_ANON_ROLES'))) {
            if ($anon_group = $pp->groups()->getMetagroup('wp_role', 'wp_anon')) {
                $query_agent_ids = array_diff($query_agent_ids, (array)$anon_group->ID);
            }

            if ($all_group = $pp->groups()->getMetagroup('wp_role', 'wp_all')) {
                $query_agent_ids = array_diff($query_agent_ids, (array)$all_group->ID);
            }
        }

        if (!isset($agent_roles[$agent_id]) || ($last_query_key !== $query_key)) {
            foreach ($query_agent_ids as $_id)
                $agent_roles[$_id] = [];

            $agent_id_csv = implode("','", array_map('intval', $query_agent_ids));

            // Direct query of plugin table (IN clause sanitized above)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT assignment_id, role_name, agent_id FROM $wpdb->ppc_roles WHERE agent_type = %s"
                    . " AND agent_id IN ('$agent_id_csv') ORDER BY role_name",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                    $agent_type
                )
            );

            $no_ext = !$pp->moduleActive('collaboration') && !$pp->moduleActive('status-control');
            $no_custom_stati = !$pp->moduleActive('status-control');

            foreach ($results as $row) {
                // roles for these post statuses will not be applied if corresponding modules are inactive, so do not indicate in users/groups listing or profile
                if ($no_ext && strpos($row->role_name, ':post_status:') && !strpos($row->role_name, ':post_status:private')) {
                    continue;
                } elseif (
                    $no_custom_stati && strpos($row->role_name, ':post_status:')
                    && !strpos($row->role_name, ':post_status:private') && !strpos($row->role_name, ':post_status:draft')
                ) {
                    continue;
                }

                $agent_roles[$row->agent_id][$row->role_name] = $row->assignment_id;
            }

            $last_query_key = $query_key;
        }

        return isset($agent_roles[$agent_id]) ? $agent_roles[$agent_id] : [];
    }

    public static function getExceptions($args = [])
    {
        $defaults = [
            'operations' => [],
            'inherited_from' => '',
            'for_item_source' => false,
            'via_item_source' => false,
            'assign_for' => 'item',
            'for_item_status' => false,
            'post_types' => true,
            'taxonomies' => true,
            'item_id' => false,
            'agent_type' => '',
            'agent_id' => 0,
            'query_agent_ids' => [],
            'ug_clause' => '',
            'return_raw_results' => false,
            'cols' => '*'
        ];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        // todo: debug usage w/ switch_to_blog() on a blog with PressPermit activated
        if (empty($wpdb->ppc_exceptions)) {
            return [];
        }

        $pp = presspermit();

        $except = [];

        $operations = (array)$operations;

        $page_parent_editable_only = $pp->getOption('page_parent_editable_only');

        if ($operations) {
            if ($page_parent_editable_only && in_array('associate', $operations)) {
                $operations = array_merge($operations, ['edit']);
            }

            // avoid application of exceptions which are disabled due to plugin deactivation
            $operations = array_intersect($operations, $pp->getOperations());
        } else {
            $operations = $pp->getOperations();
        }

        if (!$operations) {
            return [];
        }

        if (!is_array($post_types)) {
            $post_types = $pp->getEnabledPostTypes(['layer' => 'exceptions']);
        }

        if (!is_array($taxonomies)) {
            $taxonomies = $pp->getEnabledTaxonomies();
        }

        $default_arr = ['include', 'exclude'];
        if (!defined('PP_NO_ADDITIONAL_ACCESS')) {
            $default_arr[] = 'additional';
        }

        foreach ($post_types as $_type) {
            $type_sub = strtoupper($_type);
            if (defined("PP_NO_{$type_sub}_EXCEPTIONS") && constant("PP_NO_{$type_sub}_EXCEPTIONS")) {
                $post_types = array_diff($post_types, (array)$_type);
            }
        }

        $valid_src_types = [
            'post' => [ // post exceptions can come from posts or terms
                'post' => ['' => array_fill_keys($default_arr, array_fill_keys($post_types, []))],
                'term' => [],
            ],
            'term' => [ // term exceptions only come from terms
                'term' => [],
            ],
            'pp_group' => [ // pp group management exceptions only come from groups
                'pp_group' => [],
            ],
        ];

        if ($add_source_types = apply_filters('presspermit_add_exception_source_types', [])) {
            $valid_src_types = array_merge($valid_src_types, $add_source_types);
        }

        if ($for_item_source) {
            $for_item_source = array_flip((array)$for_item_source);

            if (!$for_item_sources = array_intersect_key($for_item_source, $valid_src_types)) {
                return [];
            }
        } else {
            $for_item_sources = $valid_src_types;
        }

        $for_item_clauses = [];

        foreach (array_keys($for_item_sources) as $for_source_name) {
            if (isset($valid_src_types[$for_source_name])) {

                foreach ($operations as $op) {
                    $except["{$op}_{$for_source_name}"] = $valid_src_types[$for_source_name];
                }

                $for_types = [];

                foreach (array_keys($valid_src_types[$for_source_name]) as $via_source_name) {
                    if ('post' == $via_source_name) {
                        foreach (array_keys($valid_src_types[$for_source_name][$via_source_name]) as $via_type) {
                            foreach (array_keys($valid_src_types[$for_source_name][$via_source_name][$via_type]) as $mod_type) {
                                $for_types = array_merge($for_types, array_keys($valid_src_types[$for_source_name][$via_source_name][$via_type][$mod_type]));
                            }
                        }

                        $for_types = array_unique($for_types);

                    } elseif ('term' == $via_source_name) {
                        $for_types = ('term' == $for_source_name) ? $taxonomies : $post_types;
                    } else {
                        $for_types = false;
                    }
                }

                if (false === $for_types) {
                    $for_item_clauses[] = $wpdb->prepare("e.for_item_source = %s", $for_source_name);
                } else {
                    $type_csv = implode("','", array_map('sanitize_key', array_unique($for_types)));

                    $for_item_clauses[] = $wpdb->prepare(
                        "e.for_item_source = %s AND e.for_item_type IN ('', '$type_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $for_source_name
                    );
                }
            }
        }

        if ($type_clause = Arr::implode('OR', $for_item_clauses)) {
            $type_clause = "AND ( $type_clause )";
        }

        if ($via_item_source) {
            $type_clause .= $wpdb->prepare("AND e.via_item_source = %s", $via_item_source);
        }

        if ($agent_type && !$ug_clause) {
            $agent_id_csv = implode("','", array_map('intval', (array)$agent_id));

            // IN clause constructed and sanitized above
            $ug_clause = $wpdb->prepare(
                " AND e.agent_type = %s AND e.agent_id IN ('$agent_id_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $agent_type
            );
        }

        $operations_csv = implode("','", array_map('sanitize_key', $operations));

        $mod_clause = '';

        $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, 'pp_group');
        // Get all group IDs with metagroup_type = 'wp_role'
        $wp_role_group_ids = $wpdb->get_col("SELECT ID FROM $groups_table WHERE metagroup_type = 'wp_role'");   // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $assign_for_clause = ($assign_for) ? $wpdb->prepare("AND i.assign_for = %s", $assign_for) : '';
        $inherited_from_clause = ($inherited_from !== '') ? $wpdb->prepare("AND i.inherited_from = %d", $inherited_from) : '';
        $status_clause = (false !== $for_item_status) ? $wpdb->prepare("AND e.for_item_status = %s", $for_item_status) : '';

        static $busy;

        if (!$status_clause && !defined('PRESSPERMIT_NO_STATUS_EXCEPTIONS') && empty($busy)) {
            $busy = true;
            
            $stati = ['', 'post_status:private', 'post_status:draft'];
            if ($pp->moduleActive('collaboration')) {
                $stati[] = 'post_status:{unpublished}';
            }

            $statuses = PWP::getPostStatuses(['private' => true, 'moderation' => true, 'for_revision' => true], 'names', 'or');

            foreach ($statuses as $status_name) {
                $stati[] = "post_status:{$status_name}";
            }

            // exceptions for other statuses will not be applied correctly without status control module
            $status_clause = "AND e.for_item_status IN ('" . implode("','", $stati) . "')";

            $busy = false;
        }

        if ('eitem_ids' == $cols) {
            $cols = "e.agent_type, e.agent_id, e.operation, e.mod_type, i.eitem_id, i.assign_for, e.for_item_type";

        } else {
            $cols = "*";
        }

        $id_clause = (false !== $item_id) ? $wpdb->prepare("AND i.item_id = %d", $item_id) : '';

        // phpcs Note: clauses constructed and sanitized above

        // Direct query of plugin table (Once on each non-Administrator request after loading user)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            "SELECT $cols FROM $wpdb->ppc_exceptions AS e"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
            . " WHERE ( 1=1 AND e.operation IN ('$operations_csv') $assign_for_clause $inherited_from_clause $mod_clause $type_clause $status_clause $id_clause ) $ug_clause"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        // Determine if group restrictions are enabled
        $group_restrictions = !defined('PP_NO_GROUP_RESTRICTIONS');
        $show_hidden_exceptions = PWP::REQUEST_key('show_hidden_exceptions');

        // Count and filter hidden exceptions if group restrictions are not enabled
        if (!$group_restrictions) {
            $count_excluded = 0;
            $results = array_filter($results, function($row) use ($wp_role_group_ids, $show_hidden_exceptions, &$count_excluded) {
                $is_hidden = (
                    !$show_hidden_exceptions
                    && $row->mod_type === 'exclude'
                    && $row->agent_type !== 'user'
                    && ($row->agent_type !== 'pp_group' || !in_array($row->agent_id, $wp_role_group_ids))
                );
                if ($is_hidden) {
                    $count_excluded++;
                    return false;
                }
                return true;
            });
            self::$hidden_exceptions = $count_excluded;
        }

        // Filter out 'additional' mod_type if PP_NO_ADDITIONAL_ACCESS is defined
        if (defined('PP_NO_ADDITIONAL_ACCESS')) {
            $results = array_filter($results, function($row) {
                return $row->mod_type !== 'additional';
            });
        }

        if ($return_raw_results) {
            return $results;
        }

        foreach ($results as $row) {
            // note: currently only additional access can be status-specific
            $except["{$row->operation}_{$row->for_item_source}"][$row->via_item_source][$row->via_item_type][$row->mod_type][$row->for_item_type][$row->for_item_status][] = $row->item_id;
        
            // Mirror Edit exceptions for page parent association
            if ($page_parent_editable_only && ('edit' == $row->operation)) {
                $except["associate_{$row->for_item_source}"][$row->via_item_source][$row->via_item_type][$row->mod_type][$row->for_item_type][$row->for_item_status][] = $row->item_id;
            }
        }

        return $except;
    }

    public static function getExceptionsClause($operation, $post_type, $args = [])
    {
        $defaults = ['col_id' => 'ID', 'user' => 0];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!$user) {
            $user = presspermit()->getUser();
        }

        // TODO: why is this needed on some installations?
        $user->retrieveExceptions($operation, 'post');

        // Note: this does not apply term exceptions (not needed for current implementation, which only uses this function for 'associate' op )

        $additional_ids = $user->getExceptionPosts($operation, 'additional', $post_type);

        if ($include_ids = $user->getExceptionPosts($operation, 'include', $post_type)) {
            if ($additional_ids)
                $include_ids = array_unique(array_merge($include_ids, $additional_ids));

            // todo: how can this ever have array elements (PHP error log from one user)
            $include_ids = array_filter($include_ids, 'is_scalar');

            $where = " AND $col_id IN ('" . implode("','", array_unique($include_ids)) . "')";
        } elseif ($exclude_ids = array_diff($user->getExceptionPosts($operation, 'exclude', $post_type), $additional_ids)) {
            $where = " AND $col_id NOT IN ('" . implode("','", $exclude_ids) . "')";
        } else
            $where = '';

        return $where;
    }

    public static function addExceptionClauses($where, $required_operation, $post_type, $args = [])
    {
        $defaults = ['src_table' => '', 'source_alias' => '', 'apply_term_restrictions' => true, 'append_post_type_clause' => true, 'additions_only' => false, 'query_contexts' => [], 'join' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        $user = presspermit()->getUser();

        if (!$src_table) {
            $src_table = ($source_alias) ? $source_alias : $wpdb->posts;
        }

        $exc_post_type = apply_filters('presspermit_exception_post_type', $post_type, $required_operation, $args);

        $additions = [ 
            'post' => [], 
            'term' => []
        ];

        $additional_ids = $user->getExceptionPosts($required_operation, 'additional', $exc_post_type, ['status' => true]);

        foreach ($additional_ids as $_status => $_ids) {
            if ($_status) {  // db storage is with "post_status:" prefix to allow for implementation of other attributes
                if (0 === strpos($_status, 'post_status:'))
                    $_status = str_replace('post_status:', '', $_status);
                else
                    continue;
            }

            // facilitates user-add on post edit form without dealing with status caps
            $in_clause = "IN ('" . implode("','", $_ids) . "')";
            $additions['post'][$_status][] = apply_filters(
                'presspermit_additions_clause',
                "$src_table.ID $in_clause",
                $required_operation,
                $post_type,
                [
                    'via_item_source' => 'post', 
                    'via_item_type' => $exc_post_type,
                    'status' => $_status, 
                    'in_clause' => $in_clause, 
                    'src_table' => $src_table,
                    'ids' => $_ids
                ]
            );
        }

        if (!$additions_only && !did_action('presspermit_bypass_term_restrictions')) {
            if ($where) {  // where clause already indicates sitewide caps for one or more statuses (or just want the exceptions clause generated)
                if ($append_clause = apply_filters('presspermit_append_query_clause', '', $post_type, $required_operation, $args)) {
                    $where .= $append_clause;
                }

                $post_blockage_priority = presspermit()->getOption('post_blockage_priority');
                $post_blockage_clause = '';

                // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                foreach (['include' => 'IN', 'exclude' => 'NOT IN'] as $mod => $logic) {
                    if ($ids = $user->getExceptionPosts($required_operation, $mod, $exc_post_type)) {
                        if (!defined('PP_RESTRICTION_PRIORITY') && !empty($additional_ids['']) && !defined('PP_LEGACY_POST_BLOCKAGE')) {
                        	$ids = array_diff($ids, $additional_ids['']);
                    	}
                        
                        $_args = array_merge($args, compact('mod', 'ids', 'src_table', 'logic'));

                        $clause_var = ($post_blockage_priority) ? 'post_blockage_clause' : 'where';

                        $$clause_var .= " AND " . apply_filters(
                                'presspermit_exception_clause',
                                "$src_table.ID $logic ('" . implode("','", $ids) . "')",
                                $required_operation,
                                $post_type,
                                $_args
                            );

                        break;  // don't use both include and exclude clauses
                    }
                }

                // term restrictions which apply only to this post type
                if ($apply_term_restrictions) {
                    $where .= self::addTermRestrictionsClause($required_operation, $post_type, $src_table);
                }
            } elseif (in_array('comments', $args['query_contexts'], true) && defined('REST_REQUEST') && REST_REQUEST) {
                // if PPCE is not activated, don't filter comments
                $where = '1=1';
            } else {
                $where = '1=2';
            }
        }

        $additional_ttids = [];

        $revise_status_key = (defined('PRESSPERMIT_REVISE_TERMS_FOR_UNPUBLISHED') && function_exists('rvy_get_option') && rvy_get_option('pending_revision_unpublished')) ? '' : '{published}';
        $revise_ttids = [$revise_status_key => []];

        global $revisionary, $pagenow;  // todo: API


        // todo: remove revise_post merging with Revisions 3

        
        foreach (presspermit()->getEnabledTaxonomies(['object_type' => $post_type]) as $taxonomy) {
            $tt_ids = $user->getExceptionTerms($required_operation, 'additional', $post_type, $taxonomy, ['status' => true, 'merge_universals' => true]);

            $type_obj = get_post_type_object($post_type);

            if (('edit' == $required_operation) 
            && ((!$type_obj || empty($type_obj->cap->edit_published_posts) || empty($user->allcaps[$type_obj->cap->edit_published_posts]))                                         // prevent Revise exceptions from allowing Authors to restore revisions
                || ('revision.php' != $pagenow) && (!defined('DOING_AJAX') || ! DOING_AJAX || !PWP::is_REQUEST('action', 'get-revision-diffs'))
                )
            ) {
                if (empty($args['has_cap_check'])) {
                    if (!empty($user->except['revise_post']['term'][$taxonomy]['additional'][$post_type][''])) {
                        if (!empty($revisionary) && empty($revisionary->skip_revision_allowance)) {
                            $revise_ttids[$revise_status_key] = array_merge($revise_ttids[$revise_status_key], $user->except['revise_post']['term'][$taxonomy]['additional'][$post_type]['']);
                        }
                    }
                    
                    if (!empty($user->except['copy_post']['term'][$taxonomy]['additional'][$post_type][''])) {
                        if (!empty($revisionary) && empty($revisionary->skip_revision_allowance)) {
                            $revise_ttids[$revise_status_key] = array_merge($revise_ttids[$revise_status_key], $user->except['copy_post']['term'][$taxonomy]['additional'][$post_type]['']);
                        }
                    }
                }
            }

            // merge this taxonomy exceptions with other taxonomies
            foreach (array_keys($tt_ids) as $_status) {
                if (!isset($additional_ttids[$_status])) {
                    $additional_ttids[$_status] = [];
                }

                $additional_ttids[$_status] = array_merge($additional_ttids[$_status], $tt_ids[$_status]);
            }
        }

        if (('edit' == $required_operation) && !empty($revise_ttids[$revise_status_key])) {
            $ttid_csv = implode("','", array_map('intval', $revise_ttids[$revise_status_key]));
            $in_clause = "IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('$ttid_csv') )";

            $key = (!empty($post_blockage_clause) && !defined('PP_LEGACY_POST_BLOCKAGE')) ? 'term' : 'post'; // perf enhancement (cosolidate query clauses that don't need to be separated)
            $additions[$key][$revise_status_key][] = apply_filters(
                'presspermit_additions_clause',
                "$src_table.ID $in_clause",
                'revise',
                $post_type,
                [   'via_item_source' => 'term', 
                    'status' => $revise_status_key, 
                    'in_clause' => $in_clause, 
                    'src_table' => $src_table,
                    'ids' => $revise_ttids[$revise_status_key],
                    'join' => $join,
                ]
            );
        }

        if ($additional_ttids) {
            foreach ($additional_ttids as $_status => $_ttids) {
                if ($_status) {
                    if (0 === strpos($_status, 'post_status:'))
                        $_status = str_replace('post_status:', '', $_status);
                    else
                        continue;
                }

                $ttid_csv = implode("','", array_map('intval', $_ttids));
                $in_clause = "IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('$ttid_csv') )";

                $key = (!empty($post_blockage_clause) && !defined('PP_LEGACY_POST_BLOCKAGE')) ? 'term' : 'post'; // perf enhancement (cosolidate query clauses that don't need to be separated)
                $additions[$key][$_status][] = apply_filters(
                    'presspermit_additions_clause',
                    "$src_table.ID $in_clause",
                    $required_operation,
                    $post_type,
                    [   'via_item_source' => 'term', 
                        'status' => $_status, 
                        'in_clause' => $in_clause, 
                        'src_table' => $src_table,
                        'ids' => $_ttids,
                        'join' => $join,
                    ]
                );
            }
        }

        foreach (array_keys($additions) as $via_item_source) {
            foreach (array_keys($additions[$via_item_source]) as $_status) {
                switch ($_status) {
                    case '{published}':
                        $_stati = array_merge(
                            PWP::getPostStatuses(['public' => true, 'post_type' => $post_type]),
                            PWP::getPostStatuses(['private' => true, 'post_type' => $post_type])
                        );

                        $_status_clause = "$src_table.post_status IN ('" . implode("','", $_stati) . "') AND ";
                        break;
                    
                    case '':
                        $_status_clause = '';
                        break;

                    case '{unpublished}':
                        if ('read' != $required_operation) { // sanity check
                            $_stati = array_merge(
                                PWP::getPostStatuses(['public' => true, 'post_type' => $post_type]),
                                PWP::getPostStatuses(['private' => true, 'post_type' => $post_type])
                            );

                            $_status_clause = "$src_table.post_status NOT IN ('" . implode("','", $_stati) . "') AND ";
                            break;
                        }
                    default:
                        $_status_clause = "$src_table.post_status = '$_status' AND ";
                        break;
                }

                $additions[$via_item_source][$_status] = $_status_clause . Arr::implode(' OR ', $additions[$via_item_source][$_status]);
            }
        }

        // Note: this is a legacy filter used only for Post Forking integration
        $additions['post'] = apply_filters('presspermit_apply_additions', $additions['post'], $where, $required_operation, $post_type, $args);

        if (!empty($additions['post']) || !empty($additions['term'])) {
            if (false === strpos(trim($where), '(')) {
                $where = "( $where )";
            }

			if (!empty($additions['post'])) {
				$where .= " OR ( " . Arr::implode(' OR ', $additions['post']) . " )";
			}
		
			if (!empty($additions['term'])) {
				$where .= " OR ( " . Arr::implode(' OR ', $additions['term']) . " )";
			}

            if (defined('PP_RESTRICTION_PRIORITY') && PP_RESTRICTION_PRIORITY) {  // this constant forces exclusions to take priority over additions
                if ($ids = $user->getExceptionPosts($required_operation, 'exclude', $exc_post_type)) {
                    $_args = array_merge($args, ['mod' => 'exclude', 'ids' => $ids, 'src_table' => $src_table, 'logic' => "NOT IN"]);

                    $restriction_clause = apply_filters(
                        'presspermit_exception_clause',
                        "$src_table.ID NOT IN ('" . implode("','", $ids) . "')",
                        $required_operation,
                        $post_type,
                        $_args
                    );
                } else {
                    $restriction_clause = '1=1';
            	}

                if ($apply_term_restrictions) {
                	$restriction_clause .= self::addTermRestrictionsClause($required_operation, $post_type, $src_table, ['mod_types' => 'exclude']);
            	}

	            if ($restriction_clause != '1=1') {
	                $where = "( $where ) AND ( $restriction_clause )";
	            }

            }

            if (!empty($post_blockage_clause)) {
            	if (!empty($additions['post']) || defined('PP_LEGACY_POST_BLOCKAGE')) {
                    $post_blockage_clause = "AND ( ( 1=1 $post_blockage_clause ) OR ( " . Arr::implode(' OR ', $additions['post']) . " ) )";

            	} else {
                	$post_blockage_clause = "AND ( 1=1 $post_blockage_clause )";
            	}
            }
        }

        if (!empty($post_blockage_clause)) {
            $where = "( $where ) $post_blockage_clause";
		}

        if ($append_post_type_clause) {
            $ipos = strpos(trim($where), 'AND');

            if ($ipos === 0) {
                $where = "$src_table.post_type = '$post_type' AND ( 1=1 $where )";
            } else {
                $where = "$src_table.post_type = '$post_type' AND ( $where )";
            }
		}

        return $where;
    }

    public static function addTermRestrictionsClause($required_operation, $post_type, $src_table, $args = [])
    {
        global $wpdb;

        $defaults = ['merge_additions' => false, 'exempt_post_types' => [], 'mod_types' => ['exclude', 'include'], 'additional_ttids' => [], 'apply_object_additions' => false, 'join' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $user = presspermit()->getUser();

        $mod_types = (array)$mod_types;

        $where = '';
        $excluded_ttids = [];

        $type_exemption_clause = ($exempt_post_types) ? " OR $src_table.post_type IN ('" . implode("','", $exempt_post_types) . "')" : '';

        $tx_args = ($post_type) ? ['object_type' => $post_type] : [];

        // This argument is passed by PostFilters::getPostsWhere() to allow universal term restrictions to be overridden 
        // by other taxonomy exceptions (possibly based on a different taxonomy than the restrictions).
        //
        // Type-specific term restrictions are offset by additions applied by Permissions::addExceptionClauses()
        if ($additional_ttids) {
            $id_csv = implode("','", array_map('intval', $additional_ttids));
            $in_clause = "IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('$id_csv') )";
            $term_additions_clause = apply_filters(
                'presspermit_additions_clause',
                "$src_table.ID $in_clause",
                $required_operation,
                $post_type,
                [   'via_item_source' => 'term', 
                    'status' => '', 
                    'in_clause' => $in_clause, 
                    'src_table' => $src_table,
                    'ids' => $additional_ttids,
                    'join' => $join,
                ]
            );

            if ($term_additions_clause) {
                $term_additions_clause = "OR ( $term_additions_clause )";
            }
        } else {
            $term_additions_clause = '';
        }

        // This argument is passed by PostFilters::getPostsWhere() to allow universal term restrictions to be overridden by post exceptions.
        //
        // Type-specific term restrictions are offset by additions applied by Permissions::addExceptionClauses()
        $post_additions_clause = '';
        if ($apply_object_additions) {
            if ($post_additions_clause = self::addExceptionClauses(
                '1=2',
                $required_operation, 
                $apply_object_additions, 
                ['additions_only' => true, 'apply_term_restrictions' => false, 'src_table' => $src_table, 'join' => $join]) 
            ) {
                $post_additions_clause = "OR ( $post_additions_clause )";
            }
        }

        foreach (presspermit()->getEnabledTaxonomies($tx_args) as $taxonomy) {
            $tx_additional_ids = ($merge_additions)
                ? $user->getExceptionTerms($required_operation, 'additional', $post_type, $taxonomy, ['status' => '', 'merge_universals' => true])
                : [];

            // post may be required to be IN a term set for one taxonomy, and NOT IN a term set for another taxonomy
            foreach ($mod_types as $mod) {
                if ($tt_ids = $user->getExceptionTerms($required_operation, $mod, $post_type, $taxonomy, array_merge($args, ['merge_universals' => true]))) {
                    if ('include' == $mod) {
                        if (!empty($excluded_ttids) && defined('PP_RESTRICTION_PRIORITY')) {
                            $tt_ids = array_diff($tt_ids, $excluded_ttids);
                        }
                        
                        if ($tx_additional_ids) {
                            $tt_ids = array_merge($tt_ids, $tx_additional_ids);
                        }

                        $ttid_csv = implode("','", array_map('intval', $tt_ids));

                        $term_include_clause = apply_filters(
                            'presspermit_term_include_clause',
                            "$src_table.ID IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('$ttid_csv') )",
                            compact('tt_ids', 'src_table')
                        );

                        $where .= " AND ( $term_include_clause $term_additions_clause $post_additions_clause $type_exemption_clause )";

                        continue 2;
                    } else {
                        if ($tx_additional_ids)
                            $tt_ids = array_diff($tt_ids, $tx_additional_ids);

                        $excluded_ttids = array_merge($excluded_ttids, $tt_ids);
                    }
                }
            }
        }

        if ($excluded_ttids) {
            $ttid_csv = implode("','", array_map('intval', $excluded_ttids));

            $where .= " AND ( "
                        . "( $src_table.ID NOT IN ( "
                            . " SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('$ttid_csv') "
                        . ") $type_exemption_clause ) "
                    . "$term_additions_clause $post_additions_clause )";
        }

        $args = compact(
            'required_operation', 
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
        );
       
        return apply_filters('presspermit_term_restrictions_clause', $where, $args);
    }

    // returns propagated exceptions items for which (a) the base eitem no longer exists, or (b) the base eitem was changed to "item only"
    private static function get_orphaned_exception_items()
    {
        global $wpdb;

        // Direct query of plugin table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_col(
            "SELECT eitem_id FROM $wpdb->ppc_exception_items WHERE inherited_from > 0"
            . " AND inherited_from NOT IN ( SELECT eitem_id FROM $wpdb->ppc_exception_items WHERE assign_for = 'children' )"
        );
    }

    public static function expose_orphaned_exception_items()
    {
        global $wpdb;

        if ($eitem_ids = self::get_orphaned_exception_items()) {
            $eitem_id_csv = implode("','", array_map('intval', $eitem_ids));

            // Direct query of plugin table (IN clause constructed and sanitized above)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                "UPDATE $wpdb->ppc_exception_items SET inherited_from = 0 WHERE eitem_id IN ('$eitem_id_csv')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );

            // keep a log in case questions arise
            if (!$arr = get_option('ppc_exposed_eitem_orphans'))
                $arr = [];

            $arr = array_merge($arr, $eitem_ids);
            update_option('ppc_exposed_eitem_orphans', $arr);
        }
    }
}

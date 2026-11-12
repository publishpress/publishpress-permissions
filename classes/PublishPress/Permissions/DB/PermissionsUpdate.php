<?php

namespace PublishPress\Permissions\DB;

// Direct query of plugin tables for admin operations
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

\defined('ABSPATH') || exit;

class PermissionsUpdateHelper
{
    var $insertion_action_enabled = true;

    public function disableActions($unused_new = '', $unused_old = '')
    {
        $this->insertion_action_enabled = false;
    }
}

class PermissionsUpdate
{
    // additional arguments recognized by insertRoleAssignments():
    // is_auto_insertion = false  (if true, skips logging the item as having a manually modified role assignment)
    public static function assignRoles($assignments, $agent_type = 'pp_group', $args = [])
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT agent_id, assignment_id, role_name FROM $wpdb->ppc_roles WHERE agent_type = %s",
                $agent_type
            )
        );

        $stored_assignments = [];

        foreach ($results as $key => $ass) {
            // Note: each role should have at most one stored assignment for the item/role/agent combination.  But stored_assignments is keyed by assignment_id to deal with any redundant assignments
            $stored_assignments[$ass->role_name][$ass->agent_id][$ass->assignment_id] = true;
        }

        $any_changes = false;

        foreach (array_keys($assignments) as $role_name) {
            if (
                isset($stored_assignments[$role_name])
                && !array_diff_key($assignments[$role_name], $stored_assignments[$role_name])
                && !array_diff_key($stored_assignments[$role_name], $assignments[$role_name])
            ) {
                // no changes for this role
                continue;
            }

            if (isset($stored_assignments[$role_name])) {
                $assignments[$role_name] = array_diff_key($assignments[$role_name], $stored_assignments[$role_name]);
            }

            if ($assignments[$role_name]) {
                self::insertRoleAssignments($role_name, $agent_type, $assignments[$role_name], $args);
                $any_changes = true;
            }
        }

        // return true if added, deleted or modified any assignments
        return $any_changes;
    }

    private static function insertRoleAssignments($role_name, $agent_type, $agents, $args = [])
    {
        if (!$agents) {
            return;
        }

        global $wpdb, $current_user;

        $assigner_id = $current_user->ID;
        $insert_data = compact('role_name', 'agent_type', 'assigner_id');

        // Before inserting a role, delete any overlooked old assignment.
        foreach (array_keys($agents) as $agent_id) {
            if (!$agent_id) {
                continue;
            }
            
            if ($ass_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT assignment_id FROM $wpdb->ppc_roles WHERE role_name = %s AND agent_id = %d",
                $role_name,
                $agent_id
            ))) {
                PermissionsUpdate::removeRolesById($ass_ids);
            }

            // insert role for specified object and group(s)
            $insert_data['agent_id'] = $agent_id;
            $wpdb->insert($wpdb->ppc_roles, $insert_data);
            $assignment_id = $wpdb->insert_id;

            do_action('presspermit_assigned_sitewide_role', $assignment_id, compact('role_name', 'agent_type', 'agent_id', 'assigner_id'));
        }
    }

    public static function removeRolesById($delete_assignments)
    {
        $delete_assignments = (array)$delete_assignments;

        if (!count($delete_assignments)) {
            return;
        }

        global $wpdb;

        $id_csv = implode("', '", array_map('intval', $delete_assignments));

        // deleted role data will be passed through action
        $roles = $wpdb->get_results(
            "SELECT * FROM $wpdb->ppc_roles WHERE assignment_id IN ('$id_csv')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        $wpdb->query(
            "DELETE FROM $wpdb->ppc_roles WHERE assignment_id IN ('$id_csv')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        foreach ($roles as $role) {
            do_action('presspermit_removed_sitewide_role', $role->assignment_id, (array)$role);
        }

        do_action('presspermit_removed_roles', $delete_assignments);
        if (PRESSPERMIT_LEGACY_HOOKS) {
            do_action('presspermit_removed_roles', $delete_assignments);
        }
    }

    public static function deleteRoles($agent_ids, $agent_type = 'pp_group') {
        global $wpdb;
	
        $agent_id_csv = implode( "','", array_map( 'intval', (array) $agent_ids ) );
        
        $wpdb->query( 
            $wpdb->prepare( 
                "DELETE FROM $wpdb->ppc_roles WHERE agent_type = %s AND agent_id IN ('$agent_id_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $agent_type 
            )
        );
    }

    /*
     * See function doc for presspermit_assign_exceptions
     * 
     * Additional arguments recognized by self::insertExceptions():
     *    is_auto_insertion = false  (if true, skips logging the item as having a manually modified role assignment)
     *    agents[assign_for][agent_id] = has_access 
     */
    public static function assignExceptions($agents, $agent_type = 'pp_group', $args = [])
    {
        $defaults = [
            'operation' => '',
            'mod_type' => '',
            'for_item_source' => '',
            'for_item_type' => '',
            'for_item_status' => '',
            'via_item_source' => '',
            'item_id' => 0,
            'via_item_type' => ''
        ];

        $args = array_merge($defaults, (array)$args);
        foreach (array_keys($defaults) as $var) {
            $$var = (is_string($args[$var])) ? sanitize_key($args[$var]) : $args[$var];
        }

        $item_id = (int)$args['item_id'];
        $agent_type = sanitize_key($agent_type);
        $for_item_status = (isset($args['for_item_status'])) ? PWP::sanitizeCSV($for_item_status) : '';

        // temp workaround for Revisionary (otherwise lose page-assigned roles on revision approval)
        if (PWP::is_REQUEST('page', 'rvy-revisions')) {
            return;
        }

        if ('wp_role' == $agent_type) {
            $agent_type = 'pp_group';
        }

        global $wpdb;

        if (!$via_item_type && ('term' == $via_item_source)) {  // separate sets of include/exclude exceptions for each taxonomy
            if ($for_item_source == $via_item_source) {
                $via_item_type = $for_item_type;
            }
        }

        $agent_ids = [];
        foreach (array_keys($agents) as $_assign_for) {
            $agent_ids = array_merge($agent_ids, array_keys($agents[$_assign_for]));
        }

        $agent_id_csv = implode("','", array_map('intval', $agent_ids));

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.agent_id, e.exception_id, e.for_item_status, e.mod_type, i.assign_for, i.inherited_from"
                . " FROM $wpdb->ppc_exceptions AS e INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                . " WHERE i.item_id = %d AND e.agent_type = %s AND e.agent_id IN ('$agent_id_csv') AND e.operation = %s"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                . " AND e.via_item_source = %s AND e.via_item_type = %s"
                . " AND e.for_item_source = %s AND e.for_item_type = %s AND e.for_item_status = %s",

                $item_id,
                $agent_type,
                $operation,
                $via_item_source,
                $via_item_type,
                $for_item_source,
                $for_item_type,
                $for_item_status
            )
        );

        $stored_assignments = [];

        foreach ($results as $key => $ass) {
			$_assign_for = trim($ass->assign_for);
            $stored_assignments[$ass->mod_type][$_assign_for][$ass->agent_id] = $ass->exception_id;
        }

        $delete_agents_from_eitem = ['item' => [], 'children' => [], '' => []];
        $delete_eids_from_eitem = ['item' => [], 'children' => [], '' => []];

        $assigned_items = []; // for use with 'item_roles_updated' action

        $any_changes = false;

        if ($mod_type) {
            foreach (array_keys($agents) as $assign_for) {
                if (!isset($delete_agents_from_eitem[$assign_for])) {
                    $delete_agents_from_eitem[$assign_for] = [];
                }

                // insert any exceptions which are not stored already
                if ($has_access = array_intersect($agents[$assign_for], [true])) {
                    $_stored = (isset($stored_assignments[$mod_type][$assign_for])) ? $stored_assignments[$mod_type][$assign_for] : [];
                    if ($insert_agents = array_diff_key($has_access, $_stored)) {
                        $args['assign_for'] = $assign_for;

                        $_assigned_items = self::insertExceptions(
                            $mod_type,
                            $operation,
                            $via_item_source,
                            $via_item_type,
                            $for_item_source,
                            $for_item_type,
                            $item_id,
                            $agent_type,
                            $insert_agents,
                            $args
                        );

                        $assigned_items = array_merge($assigned_items, $_assigned_items);

                        $any_changes = true;
                    }
                }

                // if an include exception is passed in with value=false, remove corresponding stored exception
                if ($no_access = array_intersect($agents[$assign_for], [false])) {
                    if (isset($stored_assignments[$mod_type][$assign_for])) {
                        if ($no_access = array_intersect_key($stored_assignments[$mod_type][$assign_for], $no_access)) {
                            $delete_agents_from_eitem[$assign_for] = array_merge($delete_agents_from_eitem[$assign_for], array_keys($no_access));
                            $any_changes = true;
                        }
                    }
                }
            }
        } else {
            $coded_mod_type = ['0' => 'exclude', '1' => 'include', '2' => 'additional'];

            // usage from item edit UI
            foreach (array_keys($agents) as $assign_for) {
                // if an agent has been set to default access, delete stored exceptions
                if ($default_access = array_intersect($agents[$assign_for], [''])) {
                    foreach (array_keys($stored_assignments) as $_mod_type) {
                        if (isset($stored_assignments[$_mod_type][$assign_for])) {
                            if ($_default_access = array_intersect_key($stored_assignments[$_mod_type][$assign_for], $default_access)) {
                                $delete_agents_from_eitem[$assign_for] = array_merge($delete_agents_from_eitem[$assign_for], array_keys($_default_access));
                                $any_changes = true;
                            }
                        }
                    }

                    $agents[$assign_for] = array_diff($agents[$assign_for], ['']);
                }

                foreach ($agents[$assign_for] as $agent_id => $exc_code) {
                    if (is_numeric($exc_code) && in_array($exc_code, array_keys($coded_mod_type))) {
                        // delete exceptions stored for different mod type
                        foreach (array_keys($stored_assignments) as $_mod_type) {
                            if (($_mod_type != $coded_mod_type[$exc_code]) && isset($stored_assignments[$_mod_type][$assign_for][$agent_id])) {
                                $delete_eids_from_eitem[$assign_for][] = $stored_assignments[$_mod_type][$assign_for][$agent_id];
                                $any_changes = true;
                            }
                        }

                        // disregard exceptions which are already stored
                        if (isset($stored_assignments[$coded_mod_type[$exc_code]][$assign_for][$agent_id])) {
                            unset($agents[$assign_for][$agent_id]);
                        }
                    }
                }

                // any remaining posted exeptions (with exclude/include/additional setting) need to be inserted
                if ($agents[$assign_for]) {
                    $args['assign_for'] = $assign_for;

                    foreach ($coded_mod_type as $exc_code => $_mod_type) {
                        if ($insert_agents = array_intersect($agents[$assign_for], ["$exc_code"])) {
                            $_assigned_items = self::insertExceptions(
                                $_mod_type,
                                $operation,
                                $via_item_source,
                                $via_item_type,
                                $for_item_source,
                                $for_item_type,
                                $item_id,
                                $agent_type,
                                $insert_agents,
                                $args
                            );

                            $assigned_items = array_merge($assigned_items, $_assigned_items);
                            $any_changes = true;
                        }
                    }
                }
            }

            foreach (array_keys($stored_assignments) as $_mod_type) {
                if (!empty($stored_assignments[$_mod_type][''])) { // delete any erroneously stored exceptions (nullstring assign_for)
                    $delete_agents_from_eitem[''] = array_merge($delete_agents_from_eitem[''], array_keys($stored_assignments[$_mod_type]['']));
                    $any_changes = true;
                }
            }
        }

        foreach ($delete_eids_from_eitem as $assign_for => $delete_exception_ids) {
            if ($delete_exception_ids) {
                $delete_exc_id_csv = implode("','", array_map('intval', $delete_exception_ids));

                if ($delete_eitem_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT eitem_id FROM $wpdb->ppc_exception_items WHERE assign_for = %s"
                        . " AND item_id = %d AND exception_id IN ('$delete_exc_id_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                        $assign_for,
                        $item_id
                    )
                )) {
                    self::removeExceptionItemsById($delete_eitem_ids);
                }
            }
        }

        if ($delete_agents_from_eitem['item'] || $delete_agents_from_eitem['children'] || $delete_agents_from_eitem['']) {
            // first retrieve ppc_exceptions records for each agent
            
            $agent_id_csv = implode("','", array_map('intval', $agent_ids));

            $stored_e = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT e.agent_id, e.exception_id FROM $wpdb->ppc_exceptions AS e"
                    . " WHERE e.agent_type = %s AND e.agent_id IN ('$agent_id_csv') AND e.operation = %s"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    . " AND e.via_item_source = %s AND e.via_item_type = %s"
                    . " AND e.for_item_source = %s AND e.for_item_type = %s AND e.for_item_status = %s",
            
                    $agent_type,
                    $operation,
                    $via_item_source,
                    $via_item_type,
                    $for_item_source,
                    $for_item_type,
                    $for_item_status
                )
            );

            foreach ($delete_agents_from_eitem as $assign_for => $delete_agent_exceptions) {
                if (!$assign_for && !$delete_agent_exceptions) {
                    continue;
                }

                $exception_ids = [];
                foreach ($stored_e as $row) {
                    if (in_array($row->agent_id, $delete_agent_exceptions)) {
                        $exception_ids[] = $row->exception_id;
                    }
                }

                if ($exception_ids) {
                    $assign_for = sanitize_key($assign_for);

                    $exception_id_csv = implode("','", array_map('intval', $exception_ids));

                    if ($delete_eitem_ids = $wpdb->get_col(
                        $wpdb->prepare(
                            "SELECT eitem_id FROM $wpdb->ppc_exception_items WHERE assign_for = %s"
                            . " AND item_id = %d AND exception_id IN ('$exception_id_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            
                            $assign_for,
                            $item_id
                        )
                    )) {
                        self::removeExceptionItemsById($delete_eitem_ids);
                    }

                    if (('children' == $assign_for) && !defined('PP_NO_PROPAGATING_EXCEPTION_DELETION')) {
                        $descendant_ids = [];

                        if ('term' == $via_item_source) {
                            if ($_term = $wpdb->get_row(
                                $wpdb->prepare(
                                    "SELECT term_id, taxonomy FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d LIMIT 1",
                                    $item_id
                                )
                            )) {                                            // any_type_or_taxonomy arg retains previous query construction (no post_type or taxonomy clause)
                                if ($_term_ids = PWP::getDescendantIds('term', $_term->term_id, ['any_type_or_taxonomy' => true])) {
                                    $descendant_ids = PWP::termidToTtid($_term_ids, $_term->taxonomy);
                                }
                            }
                        } else {
                            $propagate_post_types = apply_filters(
                                'presspermit_propagate_exception_types',
                                [$for_item_type],
                                compact('mod_type', 'operation', 'for_item_source', 'for_item_type', 'via_item_source', 'via_item_type', 'item_id')
                            );

                            $descendant_ids = PWP::getDescendantIds(
                                $via_item_source,
                                $item_id,
                                ['include_attachments' => false, 'post_types' => $propagate_post_types, 'exclude_autodrafts' => false]
                            );  // normally propagate only to matching post_types
                        }

                        if ($descendant_ids) {
                            $id_csv = implode("','", array_map('intval', $descendant_ids));

                            // phpcs Note: id and exception_id IN clauses constructed and sanitized above
                            
                            if ($delete_eitem_ids = $wpdb->get_col(
                                "SELECT eitem_id FROM $wpdb->ppc_exception_items WHERE inherited_from != '0'"
                                . " AND item_id IN ('$id_csv') AND exception_id IN ('$exception_id_csv')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            )) {
                                self::removeExceptionItemsById($delete_eitem_ids);
                            }
                        }
                    }
                }
            }
        }

        if (apply_filters('presspermit_exception_item_update_hooks', false)) {
            $assigned_items = array_unique($assigned_items);
            foreach ($assigned_items as $item_id) {
                do_action('presspermit_exception_items_updated', $via_item_source, $item_id);  // called once per item (even if multiple role assignments / removals for that item)
            }
        }

        // return true if added, deleted or modified any assignments
        return $any_changes;
    }

    private static function countHooks($tag)
    {
        global $wp_filter;

        $count = 0;

        if (isset($wp_filter[$tag])) {
            $hooked = (array)$wp_filter[$tag];
            if (isset($hooked['callbacks'])) {
                foreach (array_keys($hooked['callbacks']) as $priority) {
                    $count += count($hooked['callbacks'][$priority]);
                }
            }
        }

        return $count;
    }

    private static function insertExceptions(
        $mod_type,
        $operation,
        $via_item_source,
        $via_item_type,
        $for_item_source,
        $for_item_type,
        $item_id,
        $agent_type,
        $agents,
        $args
    )
    {
        $defaults = ['assign_for' => 'item', 'for_item_status' => '', 'inherited_from' => [], 'is_auto_insertion' => false];  // auto_insertion arg set for propagation from parent objects
        $args = array_merge($defaults, (array)$args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!$agents) {
            return;
        }

        global $wpdb, $current_user;

        $helper = new PermissionsUpdateHelper();
        if (!$enable_actions = apply_filters('presspermit_exception_item_insertion_hooks', false)) {
            if (has_action('pp_inserted_exception_item')) {  // for perf, don't fire an action for each insertion unless it's been hooked into
                $enable_actions = true;

                if (presspermit()->moduleActive('file-access') && (self::countHooks('pp_inserted_exception_item') < 2)) {  // if not explicitly enabled and PP File Filtering is the only API user, stop doing the action after file rules have been expired
                    add_action('update_option_presspermit_file_rules_expired', [&$helper, 'disableActions']);
                }
            }
        }

        if ($enable_actions) {
            $helper->insertion_action_enabled = true;
        }

        $updated_items = []; // for use with do_action hook
        $updated_items[] = $item_id;

        $assigner_id = $current_user->ID;

        $operation = sanitize_key($operation);
        $via_item_source = sanitize_key($via_item_source);
        $for_item_source = sanitize_key($for_item_source);
        $for_item_type = sanitize_key($for_item_type);
        $item_id = (int)$item_id;
        $agent_type = sanitize_key($agent_type);
        $mod_type = sanitize_key($mod_type);
        $via_item_type = sanitize_key($via_item_type);
        $for_item_status = PWP::sanitizeCSV($for_item_status);
        $assign_for = sanitize_key($assign_for);

        if ('children' == $assign_for) {
            if ('term' == $via_item_source) {
                $descendant_ids = [];

                if ($_term = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT term_id, taxonomy FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d LIMIT 1",
                            $item_id
                        )
                    )
                ) {                                         // any_type_or_taxonomy arg retains previous query construction (no post_type or taxonomy clause)
                    if ($_term_ids = PWP::getDescendantIds('term', $_term->term_id, ['any_type_or_taxonomy' => true])) {
                        $descendant_ids = PWP::termidToTtid($_term_ids, $_term->taxonomy);
                    }
                }
            } else {
                $propagate_post_types = apply_filters(
                    'presspermit_propagate_exception_types',
                    [$for_item_type],
                    compact('mod_type', 'operation', 'for_item_source', 'for_item_type', 'via_item_source', 'via_item_type', 'item_id')
                );

                $descendant_ids = PWP::getDescendantIds(
                    $via_item_source,
                    $item_id,
                    ['include_attachments' => false, 'post_types' => $propagate_post_types, 'exclude_autodrafts' => false]
                );  // normally propagate only to matching post_types
            }

            if ($descendant_ids) {
                $descendant_id_csv = implode("','", $descendant_ids);
            }
        }

        // Before inserting an exception, delete any overlooked old exceptions for the same src/type/status.

        $insert_exc_data = compact('mod_type', 'for_item_source', 'for_item_status', 'operation', 'agent_type', 'via_item_source', 'via_item_type', 'assigner_id');

        if ($optimized_insertions = (('post' != $via_item_source) || (!empty($propagate_post_types) && (1 == count($propagate_post_types))))
            && !defined('PP_DISABLE_OPTIMIZED_INSERTIONS')
        ) {
            $qry_insert_base = "INSERT INTO $wpdb->ppc_exception_items (item_id, assign_for, exception_id, inherited_from) VALUES ";
            $qry_insert = $qry_insert_base;
            $insert_row_count = 0;
            $max_insert_rows = (defined('PP_EXCEPTIONS_MAX_INSERT_ROWS')) ? PP_EXCEPTIONS_MAX_INSERT_ROWS : 2;
        }

        foreach (array_keys($agents) as $agent_id) {
            $agent_id = (int)$agent_id;

            // first, retrieve or create the pp_exceptions record for this user/group and src,type,status
            if (!$exc = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $wpdb->ppc_exceptions WHERE"
                    . " mod_type = %s AND for_item_source = %s AND for_item_status = %s AND operation = %s AND agent_type = %s AND via_item_source = %s AND via_item_type = %s"
                    . " AND for_item_type = %s AND agent_id = %d",

                    $mod_type, $for_item_source, $for_item_status, $operation, $agent_type, $via_item_source, $via_item_type,
                    $for_item_type, $agent_id
                )
            )) {
                $insert_exc_data['agent_id'] = $agent_id;
                $insert_exc_data['for_item_type'] = $for_item_type;
                $wpdb->insert($wpdb->ppc_exceptions, $insert_exc_data);
                $exc = (object) $insert_exc_data;
                $exception_id = $wpdb->insert_id;
            } else {
                $exception_id = $exc->exception_id;
            }

            $this_inherited_from = (isset($inherited_from[$agent_id])) ? $inherited_from[$agent_id] : 0;

            // delete any existing items for this exception_id
            if ($eitem_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT eitem_id FROM $wpdb->ppc_exception_items WHERE assign_for = %s AND item_id = %d AND exception_id = %d",
                        $assign_for,
                        $item_id,
                        $exception_id
                    )
                )
            ) {
                self::removeExceptionItemsById($eitem_ids);
            }

            // insert exception items
            $item_data = compact('item_id', 'assign_for', 'exception_id', 'assigner_id');
            $item_data['inherited_from'] = $this_inherited_from;
            $wpdb->insert($wpdb->ppc_exception_items, $item_data);

            if ($helper->insertion_action_enabled) {
                do_action('presspermit_inserted_exception_item', array_merge((array)$exc, $item_data));
            }

            $assignment_id = $wpdb->insert_id;

            // insert exception for all descendant items
            if (('children' == $assign_for) && $descendant_ids) {
                if (!$this_inherited_from) {
                    $this_inherited_from = (int)$assignment_id;
                }

                if ($optimized_insertions) {
                    $child_exception_id = $exception_id;

                    if (!defined('PP_FORCE_EXCEPTION_OVERWRITE') || !PP_FORCE_EXCEPTION_OVERWRITE) {
                        // phpcs Note: IN clause constructed and sanitized above
                        $have_direct_assignments = $wpdb->get_col(
                            $wpdb->prepare(
                                "SELECT item_id FROM $wpdb->ppc_exception_items WHERE exception_id = %d"
                                . " AND inherited_from = '0' AND item_id IN ('$descendant_id_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                                $child_exception_id
                            )
                        );

                        $direct_assignment_csv = implode("','", array_map('intval', $have_direct_assignments));
                    } else {
                        $direct_assignment_csv = '';
                    }

                    // phpcs Note: IN clauses constructed and sanitized above
                    if ($eitem_ids = $wpdb->get_col(
                        $wpdb->prepare(
                            "SELECT eitem_id FROM $wpdb->ppc_exception_items WHERE 1=1 AND exception_id = %d"
                            . " AND item_id IN ('$descendant_id_csv') AND item_id NOT IN ('$direct_assignment_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            
                            $child_exception_id
                        )
                    )) {
                        self::removeExceptionItemsById($eitem_ids);
                    }
                }

                $exceptions_by_type = [];

                $_results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT for_item_type, exception_id FROM $wpdb->ppc_exceptions WHERE" 
                        . " mod_type = %s AND for_item_source = %s AND for_item_status = %s AND operation = %s AND agent_type = %s AND via_item_source = %s AND via_item_type = %s"
                        . " AND for_item_type = %s AND agent_id = %d",

                        $mod_type, $for_item_source, $for_item_status, $operation, $agent_type, $via_item_source, $via_item_type,
                        $for_item_type, $agent_id
                    )
                );
                
                foreach ($_results as $row) {
                    $exceptions_by_type[$row->for_item_type] = $row->exception_id;
                }

                if (('term' == $via_item_source) && taxonomy_exists($for_item_type)) {  // need to allow for descendants of a different post type than parent
                    // phpcs Note: IN clause constructed and sanitized above
                    $descendant_types = $wpdb->get_results(
                        "SELECT term_taxonomy_id, taxonomy AS for_item_type FROM $wpdb->term_taxonomy"
                        . " WHERE term_taxonomy_id IN ('$descendant_id_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        OBJECT_K
                    );
                } elseif ('post' == $via_item_source) {
                    if ($optimized_insertions) {
                        // phpcs Note: IN clause constructed and sanitized above
                        $descendant_types = $wpdb->get_results(
                            $wpdb->prepare(
                                "SELECT ID, post_type AS for_item_type FROM $wpdb->posts"
                                . " WHERE post_type = %s AND ID IN ('$descendant_id_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                $for_item_type
                            ),
                            OBJECT_K
                        );
                    } else {
                        // phpcs Note: IN clause constructed and sanitized above
                        $descendant_types = $wpdb->get_results(
                            "SELECT ID, post_type AS for_item_type FROM $wpdb->posts WHERE ID IN ('$descendant_id_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            OBJECT_K
                        );
                    }
                } else {
                    $descendant_types = [];
                }

                foreach ($descendant_ids as $id) {
                    if ($optimized_insertions) {
                        $child_for_item_type = $for_item_type;
                    } elseif ($for_item_type) {
                        // allow for descendants with post type different from parent
                        if (!isset($descendant_types[$id])) {
                            $child_for_item_type = $for_item_type;  // if child type could not be determined, assume parent type

                        } elseif ('revision' == $descendant_types[$id]->for_item_type) {
                            continue;
                        } else {
                            $child_for_item_type = $descendant_types[$id]->for_item_type;
                        }
                    } else {
                        $child_for_item_type = '';
                    }

                    if (!$optimized_insertions) {
                        if (!isset($exceptions_by_type[$child_for_item_type])) {
                            $insert_exc_data['agent_id'] = $agent_id;
                            $insert_exc_data['for_item_type'] = $child_for_item_type;
                            $wpdb->insert($wpdb->ppc_exceptions, $insert_exc_data);
                            $exc = (object) $insert_exc_data;
                            $exceptions_by_type[$child_for_item_type] = $wpdb->insert_id;
                        }

                        $child_exception_id = $exceptions_by_type[$child_for_item_type];

                        // Don't overwrite an explicitly assigned exception with a propagated exception
                        if (!defined('PP_FORCE_EXCEPTION_OVERWRITE') || !PP_FORCE_EXCEPTION_OVERWRITE) {
                            // phpcs Note: IN clause constructed and sanitized above
                            $have_direct_assignments = $wpdb->get_col(
                                $wpdb->prepare(
                                    "SELECT item_id FROM $wpdb->ppc_exception_items WHERE exception_id = %d"
                                    . " AND inherited_from = '0' AND item_id IN ('$descendant_id_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                                    $child_exception_id
                                )
                            );
                        }
                    }

                    if (in_array($id, $have_direct_assignments)) {
                        continue;
                    }

                    // note: Propagated roles will be converted to direct-assigned roles if the parent object/term is deleted.

                    if ($optimized_insertions) {
                        if ($insert_row_count) $qry_insert .= ', ';

                        $qry_insert .= $wpdb->prepare(
                            "(%d,'item',%d,%d)",
                            $id,
                            $child_exception_id,
                            $this_inherited_from
                        );
                        
                        $insert_row_count++;

                        if ($helper->insertion_action_enabled) {
                            do_action(
                                'pp_inserted_exception_item',
                                array_merge(
                                    (array)$exc,
                                    [
                                        'item_id' => $id,
                                        'assign_for' => 'item',
                                        'exception_id' => $child_exception_id,
                                        'inherited_from' => $this_inherited_from,
                                        'assigner_id' => $assigner_id
                                    ]
                                )
                            );
                        }
                    } else {
                        if ($eitem_ids = $wpdb->get_col(
                            $wpdb->prepare(
                                "SELECT eitem_id FROM $wpdb->ppc_exception_items WHERE 1=1 AND exception_id = %d AND item_id = %d",
                                $child_exception_id,
                                $id
                            )
                        )) {
                            self::removeExceptionItemsById($eitem_ids);
                        }

                        $item_data = [
                            'item_id' => $id,
                            'assign_for' => 'item',
                            'exception_id' => $child_exception_id,
                            'inherited_from' => $this_inherited_from,
                            'assigner_id' => $assigner_id
                        ];

                        $wpdb->insert($wpdb->ppc_exception_items, $item_data);

                        if ($helper->insertion_action_enabled) {
                            do_action('presspermit_inserted_exception_item', array_merge((array)$exc, $item_data));
                        }
                    }

                    if ($optimized_insertions) {
                        if ($insert_row_count) $qry_insert .= ', ';

                        $qry_insert .= $wpdb->prepare(
                            "(%d,'children',%d,%d)",
                            $id,
                            $child_exception_id,
                            $this_inherited_from
                        );

                        $insert_row_count++;

                        if ($helper->insertion_action_enabled) {
                            do_action(
                                'pp_inserted_exception_item',
                                array_merge(
                                    (array)$exc,
                                    [
                                        'item_id' => $id,
                                        'assign_for' => 'children',
                                        'exception_id' => $child_exception_id,
                                        'inherited_from' => $this_inherited_from,
                                        'assigner_id' => $assigner_id
                                    ]
                                )
                            );
                        }

                        if ($insert_row_count == $max_insert_rows) {
                            // phpcs Note: The query is constructed and sanitized above
                            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                            $wpdb->query($qry_insert);
                            $qry_insert = $qry_insert_base;
                            $insert_row_count = 0;
                        }
                    } else {
                        $item_data['assign_for'] = 'children';
                        $wpdb->insert($wpdb->ppc_exception_items, $item_data);

                        if ($helper->insertion_action_enabled) {
                            do_action('presspermit_inserted_exception_item', array_merge((array)$exc, $item_data));
                        }
                    }

                    $updated_items[] = $id;
                }
            }
        } // end foreach agent_id

        if ($optimized_insertions && $insert_row_count) {
            // phpcs Note: The query is constructed and sanitized above
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query($qry_insert);
        }

        return $updated_items;
    }

    public static function removeExceptionItemsById($eitem_ids)
    {
        $eitem_ids = (array)$eitem_ids;

        if (!count($eitem_ids))
            return;

        global $wpdb;

        // Propagated roles will be deleted only if the original progenetor goes away.  Removal of a "link" in the parent/child propagation chain has no effect.
        $eitem_id_csv = implode("', '", array_map('intval', $eitem_ids));

        $exc_items = $wpdb->get_results(
            "SELECT e.agent_type, e.agent_id, e.for_item_source, e.for_item_type, e.for_item_status, e.operation,"
            . " e.mod_type, e.via_item_source, e.via_item_type, i.eitem_id, i.exception_id, i.item_id"
            . " FROM $wpdb->ppc_exception_items AS i"
            . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id"
            . " WHERE i.eitem_id IN ('$eitem_id_csv') OR (i.inherited_from IN ('$eitem_id_csv') AND i.inherited_from != '0')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );  // deleted entries will be returned

        $eitem_ids = [];
        foreach ($exc_items as $row) {
            $eitem_ids[] = $row->eitem_id;
        }

        $eitem_id_csv = implode("','", array_map('intval', $eitem_ids));

        $wpdb->query(
            "DELETE FROM $wpdb->ppc_exception_items WHERE eitem_id IN ('$eitem_id_csv')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        $deleted = [];

        static $do_item_actions;
        if (!isset($do_item_actions)) {
            $do_item_actions = apply_filters('presspermit_exception_item_deletion_hooks', false);
        }

        foreach ($exc_items as $row) {
            $deleted[$row->eitem_id] = true;

            if ($do_item_actions) {
                do_action('presspermit_removed_exception_item', $row->eitem_id, (array) $row);    // called once per removed role (potentially multiple per item)
            }
        }

        do_action('presspermit_removed_exception_items', $eitem_ids);

        return $deleted;
    }

    public static function deleteExceptions($agent_ids, $agent_type = 'pp_group')
    {
        global $wpdb;

        $agent_id_csv = implode("','", array_map('intval', (array)$agent_ids));

        if ($exc_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT exception_id FROM $wpdb->ppc_exceptions WHERE agent_type = %s AND agent_id IN ('$agent_id_csv')",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $agent_type
        ))) {
            $exc_id_csv = implode("','", array_map('intval', $exc_ids));

            $wpdb->query(
                "DELETE FROM $wpdb->ppc_exception_items WHERE exception_id IN ('$exc_id_csv')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );

            $wpdb->query(
                "DELETE FROM $wpdb->ppc_exceptions WHERE exception_id IN ('$exc_id_csv')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
        }
    }

    public static function clearItemExceptions($via_item_source, $item_id, $args = [])
    {
        $defaults = ['inherited_only' => false];
        $args = array_merge($defaults, (array)$args);

        global $wpdb;

        if (!$item_id) {
            return;
        }

        $inherited_clause = ($args['inherited_only']) ? "AND inherited_from > 0" : '';

        if (is_array($item_id)) {
            $id_csv = implode("','", array_map('intval', $item_id));
            $id_clause = "AND i.item_id IN ('$id_csv')";
        } else {
            $id_clause = $wpdb->prepare("AND i.item_id = %d", $item_id);
        }

        if ($ass_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT i.eitem_id FROM $wpdb->ppc_exception_items AS i"
            . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id"
            . " WHERE e.via_item_source = %s $inherited_clause $id_clause",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $via_item_source
        ))) {
            self::removeExceptionItemsById($ass_ids);
        }
    }

    public static function getParentExceptions($via_item_source, $item_id, $parent_id)
    {
        global $wpdb;

        // Since this is a new object, propagate roles from parent (if any are marked for propagation)
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $wpdb->ppc_exception_items AS i"
                . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id"
                . " WHERE e.via_item_source = %s AND i.assign_for = 'children' AND i.item_id = %d",
                $via_item_source,
                $parent_id
            )
        );
    }

    public static function inheritParentExceptions($via_item_source, $item_id, $parent_id, $args = [])
    {
        $defaults = ['parent_exceptions' => [], 'retain_exceptions' => [], 'force_for_item_type' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!$parent_exceptions) {
            $parent_exceptions = self::getParentExceptions($via_item_source, $item_id, $parent_id);
        }

        foreach ($retain_exceptions as $r) {  // can't just compare exception_id because want to avoid inheriting an include exception if an exclude is manually stored, etc.
            foreach ($parent_exceptions as $ekey => $e) {
                if (
                    $r->item_id == $item_id
                    && $r->via_item_source == $via_item_source
                    && $r->agent_type == $e->agent_type
                    && $r->agent_id == $e->agent_id
                    && $r->for_item_source == $e->for_item_source
                    && $r->for_item_type == $e->for_item_type
                    && $r->operation == $e->operation
                    && $r->for_item_status == $e->for_item_status
                ) {
                    unset($parent_exceptions[$ekey]);
                }
            }
        }

        if ($parent_exceptions) {
            if ('post' == $via_item_source) {
                $item_type = get_post_field('post_type', $item_id);
            }

            foreach ($parent_exceptions as $exc) {
                $insert_agents = [$exc->agent_id => true];
                $args = ['for_item_status' => $exc->for_item_status, 'inherited_from' => [$exc->agent_id => $exc->eitem_id]];

                if ($force_for_item_type) {
                    $for_item_type = $force_for_item_type;
                } elseif ('post' != $via_item_source) {
                    $for_item_type = $exc->for_item_type;
                } else {
                    $for_item_type = ('revision' == $item_type) ? $exc->for_item_type : $item_type;
                }

                foreach (['item', 'children'] as $assign_for) {
                    $args['assign_for'] = $assign_for;

                    if (self::insertExceptions(
                        $exc->mod_type,
                        $exc->operation,
                        $exc->via_item_source,
                        $exc->via_item_type,
                        $exc->for_item_source,
                        $for_item_type,
                        $item_id,
                        $exc->agent_type,
                        $insert_agents,
                        $args
                    )) {
                        $any_inserts = true;
                    }
                }
            }
        }

        return !empty($any_inserts);
    }

    /*
    * Custom function to force child page exceptions to be regenerated
    */
    public static function ensureExceptionPropagation() {
        global $wpdb;

        $exceptions = $wpdb->get_results(
            "SELECT * FROM $wpdb->ppc_exceptions WHERE for_item_source = 'post' AND via_item_source = 'post'"
        );
        
        foreach($exceptions as $exc) {
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $wpdb->ppc_exception_items WHERE exception_id = %d AND assign_for = 'children' AND inherited_from = 0",
                    $exc->exception_id
                )
            );

            foreach($items as $exc_item) {
                $child_items = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $wpdb->ppc_exception_items WHERE inherited_from = %d",
                        $exc_item->eitem_id
                    )
                );

                if (!$child_items) {
                    $insert_agents = [];
                    $insert_agents[$exc->agent_id] = true;

                    $args = ['assign_for' => 'children'];
                    $_assigned_items = self::insertExceptions(
                        $exc->mod_type,
                        $exc->operation,
                        $exc->via_item_source,
                        $exc->via_item_type,
                        $exc->for_item_source,
                        $exc->for_item_type,
                        $exc_item->item_id,
                        $exc->agent_type,
                        $insert_agents,
                        $args
                    );
                }
            }
        }
    }
}

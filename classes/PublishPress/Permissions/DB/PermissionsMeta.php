<?php

namespace PublishPress\Permissions\DB;

class PermissionsMeta
{
    public static function countExceptions($agent_type, $args = [])
    {
        global $wpdb;

        $defaults = ['query_agent_ids' => false, 'join_groups' => true];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();

        $count = [];

        $item_types = array_merge(
            $pp->getEnabledPostTypes(),
            $pp->getEnabledTaxonomies(),
            $pp->groups()->getGroupTypes(['editable' => true]),
            ['']
        );

        $types_csv = implode("','", array_map('sanitize_key', $item_types));

        $ops_csv = implode("','", array_map('sanitize_key', $pp->getOperations()));

        // Project Nami compat
        $count_clause = ($wpdb && method_exists($wpdb, 'db_edition') && empty($wpdb->use_mysqli))
        ? "COUNT(i.item_id)" 
        : "COUNT(DISTINCT i.exception_id, i.item_id)";

        if (('user' == $agent_type) && $join_groups) {
            $results = [];

            foreach ($pp->groups()->getGroupTypes([], 'object') as $group_type => $gtype_obj) {
                if (!empty($gtype_obj->schema['members'])) {
                    $sm = PWP::sanitizeEntry($gtype_obj->schema['members']);
                    $wpdb->members_table = PWP::sanitizeEntry($sm['members_table']);
                    $col_member_group = PWP::sanitizeEntry($sm['col_member_group']);
                    $col_member_user = PWP::sanitizeEntry($sm['col_member_user']);
                } else {
                    $wpdb->members_table = PWP::sanitizeEntry($wpdb->pp_group_members);
                    $col_member_group = 'group_id';
                    $col_member_user = 'user_id';
                }

                if ($query_agent_ids) {
                    $agent_id_csv = implode("','", array_map('intval', (array) $query_agent_ids));
                    $agent_clause = "AND gm.$col_member_user IN ('$agent_id_csv')";
                } else {
                    $agent_clause = '';
                }

                if (('groups_only' === $join_groups) || ('pp_group' != $group_type)) {
                    $agent_type_clause = $wpdb->prepare(
                        "( e.agent_type = %s AND gm.$col_member_group = e.agent_id )",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $group_type
                    );  // NOTE: every site user has at least one record in pp_group_members (for primary WP site role)
                } else {
                    $agent_type_clause = "( e.agent_type = 'user' AND gm.user_id = e.agent_id )"
                        . " OR ( e.agent_type = 'pp_group' AND gm.group_id = e.agent_id )";
                }

                // Direct query of plugin tables for admin query (clauses sanitized above)
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $_results = $wpdb->get_results(
                    "SELECT gm.$col_member_user as qry_agent_id, e.exception_id, e.for_item_source, e.for_item_type," // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    . " e.via_item_type, e.operation, $count_clause AS exc_count"                                   // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    . " FROM $wpdb->ppc_exception_items AS i"
                    . " INNER JOIN $wpdb->ppc_exceptions AS e ON i.exception_id = e.exception_id"
                    . " INNER JOIN $wpdb->members_table AS gm ON ( $agent_type_clause )"                            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    . " WHERE i.inherited_from = '0' AND operation IN ('$ops_csv')"                                 // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    . " AND e.for_item_type IN ('$types_csv') AND e.via_item_type IN ('$types_csv') $agent_clause"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    . " GROUP BY gm.$col_member_user, e.for_item_source, e.for_item_type, e.operation"              // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                );

                $results = array_merge($results, $_results);
            }
        } else {
            if ($query_agent_ids) {
                $agent_id_csv = implode("','", array_map('intval', (array) $query_agent_ids));
                $agent_clause = "AND e.agent_id IN ('$agent_id_csv')";
            } else {
                $agent_clause = '';
            }

            $mod_clause = (defined('PP_NO_ADDITIONAL_ACCESS')) ? "AND e.mod_type != 'additional'" : '';

            if (defined('PP_NO_GROUP_RESTRICTIONS')) {
                $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, 'pp_group');
                $mod_clause .= "AND ( e.mod_type != 'exclude' OR e.agent_type = 'user' OR ( e.agent_type = 'pp_group' AND e.agent_id IN (SELECT ID FROM $groups_table WHERE metagroup_type = 'wp_role') ) )";
            }

            // Direct query of plugin tables for admin query (clauses sanitized above)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT e.agent_id AS qry_agent_id, e.exception_id, e.for_item_source, e.for_item_type, e.operation,"
                    . " e.via_item_type, $count_clause AS exc_count"                                                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    . " FROM $wpdb->ppc_exception_items AS i"
                    . " INNER JOIN $wpdb->ppc_exceptions AS e ON i.exception_id = e.exception_id"
                    . " WHERE i.inherited_from = '0' AND e.agent_type = %s AND operation IN ('$ops_csv')"           // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    . " $mod_clause "                                                                               // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    . " AND e.for_item_type IN ('$types_csv') AND e.via_item_type IN ('$types_csv') $agent_clause"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    . " GROUP BY e.agent_id, e.for_item_source, e.for_item_type, e.operation",
    
                    $agent_type
                )
            );
        }

        foreach ($results as $row) {
            if (!$row->for_item_type)
                $type_label = '';
            else {
                if (!$type_obj = $pp->getTypeObject($row->for_item_source, $row->for_item_type))
                    continue;

                $type_label = $type_obj->labels->singular_name;
            }

            if ($op_obj = $pp->admin()->getOperationObject($row->operation, $row->for_item_type)) {
                if ('assign' == $row->operation) {
                    if ($tx_obj = get_taxonomy($row->via_item_type))
                        $lbl = str_replace('Term', $tx_obj->labels->singular_name, $op_obj->label);  // todo: better i8n
                    else
                        $lbl = $op_obj->label;

                } elseif (isset($op_obj->abbrev))
                    $lbl = $op_obj->abbrev;
                else
                    $lbl = sprintf(esc_html__('%1$s %2$s', 'press-permit-core'), $op_obj->label, $type_label);
            } else {
                $lbl = $type_label;
            }

            if (!isset($count[$row->qry_agent_id]['exceptions'][$lbl]))
                $count[$row->qry_agent_id]['exceptions'][$lbl] = 0;

            $count[$row->qry_agent_id]['exceptions'][$lbl] += $row->exc_count;

            if (!isset($count[$row->qry_agent_id]['exc_count']))
                $count[$row->qry_agent_id]['exc_count'] = 0;

            $count[$row->qry_agent_id]['exc_count'] += $row->exc_count;
        }

        return $count;
    }

    public static function countRoles($agent_type, $args = [])
    {
        global $wpdb;

        $defaults = ['query_agent_ids' => false, 'join_groups' => true];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();

        $count = [];

        if (('user' == $agent_type) && $join_groups) {
            if ($query_agent_ids) {
                $agent_id_csv = implode("','", array_map('intval', (array)$query_agent_ids));
                $agent_clause = "AND gm.user_id IN ('$agent_id_csv')";
            } else {
                $agent_clause = '';
            }

            // Direct query of plugin tables for admin query, joining users table for labels (clauses sanitized above)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results(
                "SELECT u.ID AS agent_id, r.role_name, COUNT(*) AS rolecount FROM $wpdb->users AS u"  // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
                . " INNER JOIN $wpdb->pp_group_members AS gm ON ( gm.user_id = u.ID $agent_clause )"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                . " INNER JOIN $wpdb->ppc_roles AS r"
                . " ON ( ( r.agent_type = 'user' AND r.agent_id = gm.user_id ) OR ( r.agent_type = 'pp_group' AND r.agent_id = gm.group_id ) )"
                . " GROUP BY u.ID, r.role_name"
            );
        } else {
            if ($query_agent_ids) {
                $agent_id_csv = implode("','", array_map('intval', (array)$query_agent_ids));

                // Direct query of plugin tables for admin query (IN clause sanitized above)
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT agent_id, role_name, COUNT(*) AS rolecount FROM $wpdb->ppc_roles"
                        . " WHERE agent_type = %s AND agent_id IN ('$agent_id_csv')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        . " GROUP BY agent_id, role_name",
    
                        $agent_type
                    )
                );

            } else {
                // Direct query of plugin tables for admin query
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT agent_id, role_name, COUNT(*) AS rolecount FROM $wpdb->ppc_roles WHERE agent_type = %s"
                        . " GROUP BY agent_id, role_name",
    
                        $agent_type
                    )
                );
            }
        }

        $item_types = array_merge($pp->getEnabledPostTypes(), $pp->getEnabledTaxonomies());

        foreach ($results as $row) {
            $arr_role = explode(':', $row->role_name);

            $no_ext = !$pp->moduleActive('collaboration') && !class_exists('PublishPress\Statuses\StatusControl');
            $no_custom_stati = !class_exists('PublishPress\Statuses\StatusControl');

            if (isset($arr_role[2])) {
                if (!in_array($arr_role[2], $item_types, true)) {
                    continue;
                }

                // roles for these post statuses will not be applied if corresponding modules are inactive, so do not indicate in users/groups listing or profile
                if ($no_ext && strpos($row->role_name, ':post_status:') && !strpos($row->role_name, ':post_status:private')) {
                    continue;
                } elseif (
                    $no_custom_stati && strpos($row->role_name, ':post_status:')
                    && !strpos($row->role_name, ':post_status:private') && !strpos($row->role_name, ':post_status:draft')
                ) {
                    continue;
                }
            }

            // Note: If a post type was not parsed out of the role_name, also support display of direct-assigned roles or spcially-defined roles (Nav Menu Manager (Legacy))

            if ($role_title = $pp->admin()->getRoleTitle($row->role_name, ['slug_fallback' => false, 'show_disabled' => false])) {
                $count[$row->agent_id]['roles'][$role_title] = $row->rolecount;

                if (!isset($count[$row->agent_id]['role_count'])) {
                    $count[$row->agent_id]['role_count'] = 0;
                }

                $count[$row->agent_id]['role_count'] += $row->rolecount;
            }
        }

        return $count;
    }
}

<?php

namespace PublishPress\Permissions\UI\Dashboard;

\defined('ABSPATH') || exit;

class ItemExceptionsData
{
    public $agent_info = [];
    public $current_exceptions;
    public $inclusions_active = [];

    public function __construct()
    {
        add_action('presspermit_load_item_exceptions', [$this, 'loadExceptions'], 10, 5);
    }

    public function loadExceptions($via_item_source, $for_item_source, $via_item_type, $item_id, $args = [])
    {
        global $wpdb, $wp_roles;

        if (!isset($this->current_exceptions)) {
            $this->current_exceptions = [];
            $this->agent_info = [];
        }

        $defaults = array_merge(
            compact('for_item_source', 'item_id'),
            [
                'agent_type' => '',
                'agent_id' => [],
                'for_item_type' => '',
                'for_item_status' => '',
                'via_item_source' => $via_item_source
            ]
        );

        $force_vars = [
            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            'cols' => 'eitem_ids',  // causes query cols to be e.agent_type, e.agent_id, e.operation, e.mod_type, i.eitem_id, i.assign_for, e.for_item_type
            'return_raw_results' => true,
            'inherited_from' => '',
            'assign_for' => '',
            'hierarchical' => false,
        ];

        $args = array_merge($defaults, $args, $force_vars);

        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        foreach (array_keys($force_vars) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();

        $for_item_type = (isset($args['for_item_type'])) ? $args['for_item_type'] : $via_item_type;

        if (('term' == $via_item_source) && !$for_item_type) {
            unset($args['for_item_source']);
            $args['post_types'] = [''];
        }

        if (!empty($agent_type)) {
            $agent_type = $agent_type;
            $agents_by_type = [$agent_type => []];  // need this for ajax when an agent type has no current stored
        } else {
            $agents_by_type = [];
        }

        // prevent loading post ID 0 exception for "(none)" as an explicit assignment
        if (empty($args['item_id'])) {
            $args['item_id'] = -1;
        }

        $exc = $pp->getExceptions($args);

        foreach ($exc as $row) {
            $row->assign_for = trim($row->assign_for);  // work around Project Nami issue (enum column values get padded with trailing spaces)

            Arr::setElem(
                $this->current_exceptions,
                [$row->for_item_type, $row->operation, $row->agent_type, $row->agent_id, $row->assign_for, $row->mod_type]
            );

            $this->current_exceptions[$row->for_item_type][$row->operation][$row->agent_type][$row->agent_id][$row->assign_for][$row->mod_type] = $row->eitem_id;

            $agents_by_type[$row->agent_type][] = $row->agent_id;
        }

        foreach (array_keys($agents_by_type) as $agent_type) {
            $agents_by_type[$agent_type] = array_unique($agents_by_type[$agent_type]);

            $ids = $agents_by_type[$agent_type];
            if (!empty($agent_id)) {
                $ids = array_merge($ids, (array)$agent_id);  // ajax passes in specific id(s)
            }

            $id_csv = implode("','", array_map('intval', $ids));

            if ('user' == $agent_type) {
                $user_search = new \WP_User_Query(
                    [
                    'fields' => ['ID', 'user_login', 'display_name'],
                    'include' => array_map('intval', $ids),
                    'orderby' => 'user_login'
                    ]
                );
    
                $this->agent_info['user'] = [];

                if ($results = $user_search->get_results()) {
                    foreach ($results as $row) {
                        $first_name = get_user_meta($row->ID, 'first_name', true);
                        $last_name = get_user_meta($row->ID, 'last_name', true);
                        $formatted_name = trim($first_name . ' ' . $last_name. ' (' . $row->user_login . ')');

                        $this->agent_info['user'][$row->ID] = $row;
                        $this->agent_info['user'][$row->ID]->name = $row->user_login;
                        $this->agent_info['user'][$row->ID]->formatted_name = $formatted_name;
                    }
                }

            } elseif ('pp_group' != $agent_type) {
                $_args = ['ids' => $ids];
                $this->agent_info[$agent_type] = $pp->groups()->getGroups($agent_type, $_args);
            }
        }

        $_args = [];

        if (!empty($agent_id)) {  // ajax usage
            $_args['ids'] = (array)$agent_id;
        } else {
            if (isset($agents_by_type['pp_group'])) {
                $args['ids'] = $agents_by_type['pp_group'];
            } else {
                // Include all groups except wp_role metagroups. Those will be included by OR clause, possibly excluding some groups.
                $args['skip_meta_types'] = 'wp_role';
            }

            // retrieve info for all WP roles regardless of exception storage
            $args['or_meta_types'] = 'wp_role';

            if ($pp_only_roles = $pp->getOption('supplemental_role_defs')) {
                $args['or_meta_types_except'] = $pp_only_roles;
            }
        }

        $this->agent_info['pp_group'] = $pp->groups()->getGroups('pp_group', $_args);

        $this->agent_info['wp_role'] = [];

        // rekey WP role exceptions
        foreach ($this->agent_info['pp_group'] as $agent_id => $group) {
            if ('wp_role' == $group->metagroup_type) {
                $this->agent_info['wp_role'][$agent_id] = (object)$this->agent_info['pp_group'][$agent_id];

                if ($role_exists = isset($wp_roles->role_names[$group->metagroup_id])) {
                    $this->agent_info['wp_role'][$agent_id]->name = $wp_roles->role_names[$group->metagroup_id];
                }

                unset($this->agent_info['pp_group'][$agent_id]);

                if ($role_exists || in_array($group->metagroup_id, ['wp_anon', 'wp_all', 'wp_auth'], true)) {
                    foreach (array_keys($this->current_exceptions) as $for_item_type) {
                        foreach (array_keys($this->current_exceptions[$for_item_type]) as $op) {
                            if (isset($this->current_exceptions[$for_item_type][$op]['pp_group'][$agent_id])) {

                                $this->current_exceptions[$for_item_type][$op]['wp_role'][$agent_id]
                                    = (array)$this->current_exceptions[$for_item_type][$op]['pp_group'][$agent_id];

                                unset($this->current_exceptions[$for_item_type][$op]['pp_group'][$agent_id]);
                            }
                        }
                    }
                }
            }
        }

        // don't include orphaned assignments in metabox tab count
        foreach (array_keys($this->current_exceptions) as $for_item_type) {
            foreach (array_keys($this->current_exceptions[$for_item_type]) as $op) {
                foreach (array_keys($this->current_exceptions[$for_item_type][$op]) as $agent_type) {

                    $this->current_exceptions[$for_item_type][$op][$agent_type]
                        = array_intersect_key($this->current_exceptions[$for_item_type][$op][$agent_type], $this->agent_info[$agent_type]);
                }
            }
        }

        $query_users = (isset($this->agent_info['user'])) ? array_keys($this->agent_info['user']) : [];
        if (!empty($args['agent_type']) && ('user' == $args['agent_type']) && !empty($args['agent_id']))
            $query_users = array_merge($query_users, (array)$args['agent_id']);

        $agents_clause = [];

        if (!empty($args['agent_type']) && ('user' != $args['agent_type']))
            $agents_clause[] = $wpdb->prepare("( e.agent_type = %s )", $args['agent_type']);

        if ($query_users)
            $agents_clause[] = "( e.agent_type != 'user' OR e.agent_id IN ('" . implode("','", array_map('intval', $query_users)) . "') )";

        $agents_clause = ($agents_clause) ? Arr::implode(' OR ', $agents_clause) : '1=1';

        // determine if inclusions are set for any agents
        if ('term' == $via_item_source) {
            $where = $wpdb->prepare(" AND e.via_item_type = %s", $via_item_type);
        } else {
            $where = $wpdb->prepare(" AND e.for_item_source = %s", $for_item_source);
        }

        // This function is not currently used to retrieve propagation records 
        //$_assignment_modes = ($hierarchical) ? [ 'item', 'children' ] : [ 'item' ];   // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        $_assignment_modes = ['item'];

        // Populate only for wp roles, groups and users with stored exceptions.  Will query for additional individual users as needed.
        foreach ($_assignment_modes as $_assign_for) {
            // phpcs Note: agents_clause, where clause constructed and sanitized above

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT e.agent_type, e.agent_id, e.operation, e.for_item_type FROM $wpdb->ppc_exceptions AS e"
                    . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                    . " WHERE $agents_clause AND i.assign_for = %s AND e.mod_type = 'include' $where",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                    $_assign_for
                )
            );

            foreach ($results as $row) {
                if (('pp_group' == $row->agent_type) && in_array($row->agent_id, array_keys($this->agent_info['wp_role'])))
                    $_agent_type = 'wp_role';
                else
                    $_agent_type = $row->agent_type;

                Arr::setElem(
                    $this->inclusions_active,
                    [$row->for_item_type, $row->operation, $_agent_type, $row->agent_id, $_assign_for]
                );

                $this->inclusions_active[$row->for_item_type][$row->operation][$_agent_type][$row->agent_id][$_assign_for] = true;
            }
        }
    }
}

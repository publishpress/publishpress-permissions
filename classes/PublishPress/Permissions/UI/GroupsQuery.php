<?php

namespace PublishPress\Permissions\UI;

class GroupQuery
{
    /**
     * List of found group ids
     *
     * @access private
     * @var array
     */
    private $results;

    /**
     * Total number of found groups for the current query
     *
     * @access private
     * @var int
     */
    private $total_groups = 0;

    private $agent_type = 'pp_group';
    private $group_variant = '';

    // SQL clauses
    private $query_fields;
    private $query_from;
    private $query_join = '';
    private $query_where;
    private $query_orderby;
    private $query_limit;

    public $query_vars;

    public $request;

    /**
     *
     * @param string|array $args The query variables
     * @return WP_Group_Query
     */
    public function __construct($query = null)
    {
        // phpcs Note: This exclude argument has not relation to the WP Users table

        if (!empty($query)) {
            $this->query_vars = wp_parse_args($query, [
                'blog_id' => get_current_blog_id(),
                'include' => [],
                'exclude' => [],                    // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                'search' => '',
                'orderby' => 'login',
                'order' => 'ASC',
                'offset' => '', 'number' => '',
                'count_total' => true,
                'fields' => 'all',
                'agent_type' => '',
                'group_variant' => '',
            ]);

            if (!empty($query['agent_type'])) {
                $this->agent_type = $query['agent_type'];
            }

            if (!empty($query['group_variant'])) {
                $this->group_variant = $query['group_variant'];
            }

            $this->prepare_query();
            $this->query();
        }
    }

    /**
     * Prepare the query variables
     *
     * @access private
     */
    private function prepare_query()
    {
        global $wpdb;

        $pp = presspermit();

        $qv = $this->query_vars;

        $groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $this->agent_type);

        if (is_array($qv['fields'])) {
            $qv['fields'] = array_unique($qv['fields']);

            $this->query_fields = [];

            foreach ($qv['fields'] as $field) {
                $this->query_fields[] = $groups_table . '.' . esc_sql($field);
            }

            $this->query_fields = implode(',', $this->query_fields);

        } elseif ('all' == $qv['fields']) {
            $this->query_fields = "$groups_table.*";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            $this->query_fields = "$groups_table.ID";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $this->query_from = "FROM $groups_table";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $this->query_where = "WHERE 1=1";

        if ('wp_role' == $this->group_variant) {
            $this->query_where .= " AND $groups_table.metagroup_type IN ('wp_role') AND $groups_table.metagroup_id NOT IN ('wp_anon', 'wp_auth', 'wp_all')";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else if ('login_state' == $this->group_variant) {
            $this->query_where .= " AND $groups_table.metagroup_type IN ('wp_role') AND $groups_table.metagroup_id IN ('wp_anon', 'wp_auth', 'wp_all')";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        if (!defined('PUBLISHPRESS_REVISIONS_VERSION')) {
            $this->query_where .= " AND $groups_table.metagroup_type != 'rvy_notice'";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        if (PluginPage::viewFilter('pp_has_exceptions')) {
            $this->query_where .= " AND ( ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e"
                . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                . " WHERE e.agent_type = 'user' )"
                . " OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug"
                . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.agent_id = ug.group_id AND e.agent_type = 'pp_group' ) )";
        
        } elseif (PluginPage::viewFilter('pp_has_roles')) {
            $this->query_where .= " AND ( ID IN ( SELECT agent_id FROM $wpdb->ppc_roles WHERE agent_type = 'user' )"
            . " OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug"
            . " INNER JOIN $wpdb->ppc_roles AS r ON r.agent_id = ug.group_id AND r.agent_type = 'pp_group' ) )";

        } elseif (PluginPage::viewFilter('pp_has_perms')) {
            $this->query_where .= " AND ( ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e"
                . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                . " WHERE e.agent_type = 'user' )"
                . " OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug"
                . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.agent_id = ug.group_id AND e.agent_type = 'pp_group' )"
                . " OR ID IN ( SELECT agent_id FROM $wpdb->ppc_roles WHERE agent_type = 'user' )"
                . " OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug"
                . " INNER JOIN $wpdb->ppc_roles AS r ON r.agent_id = ug.group_id AND r.agent_type = 'pp_group' ) )";
        }

        $skip_meta_types = [];
        if ($this->group_variant && !in_array($this->group_variant, ['pp_net_group', 'wp_role', 'login_state'], true)) {
            $skip_meta_types[] = 'wp_role';
        } else {
            $pp_only_roles = (array) $pp->getOption('supplemental_role_defs');

            // version 1.4.9 and earlier stored redundant elements
            if (defined('CAPSMAN_ENH_VERSION') && version_compare(CAPSMAN_ENH_VERSION, '1.4.10', '<')) {
                $_pp_only_roles = (array)$pp_only_roles;
                $pp_only_roles = array_unique($pp_only_roles);
                if (count($pp_only_roles) != count($_pp_only_roles)) {
                    $pp->updateOption('supplemental_role_defs', $pp_only_roles);
                }
            }

            if ($pp->getOption('anonymous_unfiltered')) {
                $pp_only_roles = array_merge($pp_only_roles, ['wp_anon', 'wp_all']);
            }

            $meta_id_csv = implode("','", array_map('sanitize_key', $pp_only_roles));

            $this->query_where .= " AND ( ( $groups_table.metagroup_type != 'wp_role' )"             // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                . " OR ( $groups_table.metagroup_id NOT IN ( '$meta_id_csv' ) ) )";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        if ($skip_meta_types) {
            $meta_type_csv = implode("','", array_map('sanitize_key', $skip_meta_types)) ;
            $this->query_where .= " AND $groups_table.metagroup_type NOT IN ('$meta_type_csv')";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        global $wp_roles;
        $admin_roles = [];

        if (isset($wp_roles->role_objects)) {
            foreach (array_keys($wp_roles->role_objects) as $wp_role_name) {
                if (
                    !empty($wp_roles->role_objects[$wp_role_name]->capabilities['pp_administer_content'])
                    || !empty($wp_roles->role_objects[$wp_role_name]->capabilities['pp_unfiltered'])
                ) {
                    $admin_roles[$wp_role_name] = true;
                }
            }
        }

        if ($admin_roles) {
            $meta_id_csv = implode("','", array_keys($admin_roles));
            $this->query_where .= " AND $groups_table.metagroup_id NOT IN ('$meta_id_csv')";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $skip_meta_ids = [];

        if ((!defined('PUBLISHPRESS_REVISIONS_VERSION') && !defined('REVISIONARY_VERSION')) || defined('SCOPER_DEFAULT_MONITOR_GROUPS') || defined('PP_DEFAULT_MONITOR_GROUPS')) {
            $skip_meta_ids = array_merge($skip_meta_ids, ['rv_pending_rev_notice_ed_nr_', 'rv_scheduled_rev_notice_ed_nr_']);
        }

        if ($skip_meta_ids) {
            $meta_id_csv = implode("','", array_map('sanitize_key', $skip_meta_ids));
            $this->query_where .= " AND $groups_table.metagroup_id NOT IN ('$meta_id_csv')";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        // sorting
        $qv['order'] = strtoupper($qv['order']);
        if ('ASC' == $qv['order']) {
            $order = 'ASC';
        } else {
            $order = 'DESC';
        }

        // Handle different orderby columns
        switch (strtolower($qv['orderby'])) {
            case 'id':
                $orderby = 'ID';
                $this->query_orderby = "ORDER BY $orderby $order";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                break;

            case 'group_name':
                $orderby = 'group_name';
                $this->query_orderby = "ORDER BY $orderby $order";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                break;

            case 'group_type':
                $this->query_orderby = "ORDER BY metagroup_type $order, group_name ASC";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                break;

            case 'num_users':
                // Add LEFT JOIN to count group members
                $this->query_join .= " LEFT JOIN ( SELECT group_id, COUNT(*) as member_count FROM $wpdb->pp_group_members GROUP BY group_id ) AS gm ON $groups_table.ID = gm.group_id";
                $this->query_orderby = "ORDER BY COALESCE(gm.member_count, 0) $order, group_name ASC";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                break;

            case 'exceptions':
                // Add LEFT JOIN to count exceptions
                $this->query_join .= " LEFT JOIN ( SELECT e.agent_id, COUNT(DISTINCT i.item_id) as exception_count FROM $wpdb->ppc_exceptions AS e INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id WHERE e.agent_type = '$this->agent_type' GROUP BY e.agent_id ) AS exc ON $groups_table.ID = exc.agent_id";
                $this->query_orderby = "ORDER BY COALESCE(exc.exception_count, 0) $order, group_name ASC";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                break;

            case 'roles':
                // Add LEFT JOIN to count roles
                $this->query_join .= " LEFT JOIN ( SELECT agent_id, COUNT(*) as role_count FROM $wpdb->ppc_roles WHERE agent_type = '$this->agent_type' GROUP BY agent_id ) AS r ON $groups_table.ID = r.agent_id";
                $this->query_orderby = "ORDER BY COALESCE(r.role_count, 0) $order, group_name ASC";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                break;

            default:
                $orderby = 'group_name';
                $this->query_orderby = "ORDER BY metagroup_type ASC, $orderby $order";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                break;
        }

        // limit
        if ($qv['number']) {
            if ($qv['offset']) {
                $this->query_limit = $wpdb->prepare("LIMIT %d, %d", $qv['offset'], $qv['number']);
            } else {
                $this->query_limit = $wpdb->prepare("LIMIT %d", $qv['number']);
            }
        }

        $search = trim($qv['search']);
        if ($search) {
            $leading_wild = (ltrim($search, '*') != $search);
            $trailing_wild = (rtrim($search, '*') != $search);
            if ($leading_wild && $trailing_wild) {
                $wild = 'both';
            } elseif ($leading_wild) {
                $wild = 'leading';
            } elseif ($trailing_wild) {
                $wild = 'trailing';
            } else {
                $wild = false;
            }

            if ($wild) {
                $search = trim($search, '*');
            }

            if (is_numeric($search)) {
                $search_columns = ['ID'];
            } else {
                $search_columns = ['group_name'];
            }

            $this->query_where .= $this->get_search_sql($search, $search_columns, $wild);
        }

        // if user cannot edit all groups, filter displayed groups based on group-specific role assignments
        if (!current_user_can('pp_edit_groups')) {
            $reqd_caps = apply_filters('presspermit_edit_groups_reqd_caps', 'pp_manage_members', 'edit-group');

            if (!current_user_can($reqd_caps)) {
                $exc_agent_type = (in_array($this->agent_type, ['pp_group', 'pp_net_group'], true)) ? 'pp_group' : $this->agent_type;

                $user = presspermit()->getUser();

                $group_ids = (isset($user->except['manage_' . $exc_agent_type][$exc_agent_type]['']['additional'][$exc_agent_type]['']))
                    ? $user->except['manage_' . $exc_agent_type][$exc_agent_type]['']['additional'][$exc_agent_type]['']
                    : [];

                $id_csv = implode("','", array_map('intval', $group_ids));

                $this->query_where .= " AND $groups_table.ID IN ('$id_csv')";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
        }

        $blog_id = absint($qv['blog_id']);

        if (!empty($qv['include'])) {
            $id_csv = implode("','", wp_parse_id_list($qv['include']));
            $this->query_where .= " AND $groups_table.ID IN ('$id_csv')";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            
        } elseif (!empty($qv['exclude'])) {
            $id_csv = implode("','", wp_parse_id_list($qv['exclude']));
            $this->query_where .= " AND $groups_table.ID NOT IN ('$id_csv')";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    /**
     * Execute the query, with the current variables
     *
     * @access private
     */
    private function query()
    {
        global $wpdb;

        // Build the SQL string
        $sql = "SELECT $this->query_fields $this->query_from $this->query_join $this->query_where $this->query_orderby $this->query_limit";

        // Store for debugging
        $this->request = $sql;

        // phpcs Note: Query clauses constructed and sanitized in prepare_query()

        if (is_array($this->query_vars['fields']) || 'all' == $this->query_vars['fields']) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $this->results = $wpdb->get_results($sql);

        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $this->results = $wpdb->get_col($sql);
        }

        foreach($this->results as $k => $row) {
            if (empty($row->ID) && !empty($row->id)) {
                $this->results[$k]->ID = $this->results[$k]->id;
            }
        }

        if ($this->query_vars['count_total']) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->total_groups = $wpdb->get_var(
                "SELECT COUNT(*) $this->query_from $this->query_join $this->query_where"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
        }

        return $this->results;
    }

    /*
     * Used internally to generate an SQL string for searching across multiple columns
     *
     * @access protected
     *
     * @param string $string
     * @param array $cols
     * @param bool $wild Whether to allow wildcard searches. Default is false for Network Admin, true for
     *  single site. Single site allows leading and trailing wildcards, Network Admin only trailing.
     * @return string
     */
    private function get_search_sql($string, $cols, $wild = false)
    {
        $string = esc_sql($string);

        $searches = [];
        $leading_wild = ('leading' == $wild || 'both' == $wild) ? '%' : '';
        $trailing_wild = ('trailing' == $wild || 'both' == $wild) ? '%' : '';
        foreach ($cols as $col) {
            if ('ID' == $col) {
                $searches[] = "$col = '$string'";
            } else {
                global $wpdb;

                $searches[] = "$col LIKE '$leading_wild" . $wpdb->esc_like($string) . "$trailing_wild'";
            }
        }

        return ' AND (' . implode(' OR ', $searches) . ')';
    }

    /**
     * Return the list of groups
     *
     * @access public
     *
     * @return array
     */
    public function get_results()
    {
        return $this->results;
    }

    /**
     * Return the total number of groups for the current query
     *
     * @access public
     *
     * @return array
     */
    public function get_total()
    {
        return $this->total_groups;
    }
}

<?php

namespace PublishPress;

/**
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2024, PublishPress
 *
 */
class PermissionsUser extends \WP_User
{
    var $groups = [];       // USAGE: groups [agent_type] [group id] = 1
    var $site_roles = [];

                            // note: nullstring for_item_type means all post types
                            
                            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
    var $except = [];       // USAGE: except [{operation}_{for_item_source}] [via_item_source] [via_item_type] ['include' or 'exclude'] [for_item_type] [for_item_status] = array of stored IDs / term_taxonomy_ids
    var $cfg = [];

    public function __construct($id = 0, $name = '', $args = [])
    {
        $defaults = ['retrieve_site_roles' => true];
        $args = array_merge($defaults, (array)$args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        parent::__construct($id, $name);

        // without this, logged in users have no read access to sites they're not registered for
        if (is_multisite() && $id && !is_admin()) {
            $this->caps[PRESSPERMIT_READ_PUBLIC_CAP] = true;
        }

        $agent_type = 'pp_group';
        $this->groups[$agent_type] = $this->getGroups(compact('agent_type'));

        // TODO: ensure current WP roles are included in pp_groups

        if (!empty($args['filter_usergroups']) && !empty($args['filter_usergroups'][$agent_type])) {  // assist group admin
            $this->groups[$agent_type] = array_intersect_key($this->groups[$agent_type], $args['filter_usergroups'][$agent_type]);
        }

        if ($retrieve_site_roles) {
            $this->getSiteRoles();
        }

        global $current_user;
        
        if ($id == $current_user->ID) {
            add_filter('map_meta_cap', [$this, 'reinstateCaps'], 99, 3);
        }
    }

    public function retrieveExtraGroups($args = [])
    {
        $custom_group_type = false;

        $pp_groups = presspermit()->groups();

        foreach ($pp_groups->getGroupTypes([], 'names') as $agent_type) {
            if ('pp_group' != $agent_type) {
                $this->groups[$agent_type] = $pp_groups->getGroupsForUser($this->ID, $agent_type);
                $custom_group_type = true;

                if (is_array($this->groups[$agent_type]) && !empty($this->groups['pp_group'])) {
                    foreach (array_keys($this->groups[$agent_type]) as $_id) {
                        if (isset($this->groups['pp_group'][$_id]) && empty($this->groups['pp_group'][$_id]->metagroup_id)) {
                            unset($this->groups['pp_group'][$_id]);
                        }
                    }
                }
            }
        }

        return $custom_group_type;
    }

    public function getUsergroupsClause($table_alias, $args = [])
    {
        global $wpdb;
        
        $args = array_merge(['context' => '', $args]);

        $table_alias = ($table_alias) ? sanitize_key($table_alias) . '.' : '';

        $pp = presspermit();
        $pp_groups = $pp->groups();

        $arr = [$wpdb->prepare(
                    "{$table_alias}agent_type = 'user' AND {$table_alias}agent_id = %d",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $this->ID
                )];

        foreach ($pp_groups->getGroupTypes([], 'names') as $agent_type) {
            if (('pp_net_group' == $agent_type) && !get_site_option('presspermit_netwide_groups')) {
                continue;
            }

            $apply_groups = (isset($this->groups[$agent_type])) ? $this->groups[$agent_type] : [];

            if (('pp_group' == $agent_type) && is_multisite() && get_site_option('presspermit_netwide_groups')) {
                foreach (array_keys($apply_groups) as $k) {
                    if (empty($apply_groups[$k]->metagroup_type)) {
                        unset($apply_groups[$k]);
                    }
                }
            }

            if (('pp_group' == $agent_type) && ('roles' == $args['context']) && !defined('PP_ALL_ANON_ROLES')) {
                if ($anon_group = $pp_groups->getMetagroup('wp_role', 'wp_anon')) {
                    unset($apply_groups[$anon_group->ID]);
                }

                if ($all_group = $pp_groups->getMetagroup('wp_role', 'wp_all')) {
                    unset($apply_groups[$all_group->ID]);
                }
            }

            if (!empty($apply_groups)) {
                $id_csv = implode("', '", array_map('intval', array_keys($apply_groups)));

                $arr[] = $wpdb->prepare(
                    "{$table_alias}agent_type = %s",             // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $agent_type
                )
                . " AND {$table_alias}agent_id IN ('$id_csv')";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
        }

        if (!empty($args['user_clause'])) {
            $arr[] = "{$table_alias}agent_type = 'user' AND {$table_alias}agent_id = '$this->ID'";  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $clause = Arr::implode(' OR ', $arr);

        if (count($arr) > 1) {
            $clause = "( $clause )";
        }

        if ($clause) {
            return " AND $clause";
        } else {
            return ' AND 1=2';
        }
    }

    // return group_id as array keys
    private function getGroups($args = [])
    {
        $args = (array)$args;

        $pp = presspermit();
        $pp_groups = $pp->groups();

        if (!$this->ID) {
            $user_groups = [];

            if ($pp->getOption('anonymous_unfiltered')) {
                $this->allcaps['pp_unfiltered'] = true;
            } else {
                if ($anon_group = $pp_groups->getMetagroup('wp_role', 'wp_anon')) {
                    $user_groups[$anon_group->ID] = $anon_group;
                }

                if ($all_group = $pp_groups->getMetagroup('wp_role', 'wp_all')) {
                    $user_groups[$all_group->ID] = $all_group;
                }
            }
        } else {
            // Passing user object causes this user's wp_role metagroups to be synchronized with their WP roles
            $user_groups = $pp_groups->getGroupsForUser($this, $args['agent_type'], $args);
            
            if (isset($this->roles)) {
                if ($pp->getOption('dynamic_wp_roles') || defined('PP_FORCE_DYNAMIC_ROLES')) {
                    $have_role_group_names = [];
                    foreach ($user_groups as $group) {
                        if ('wp_role' == $group->metagroup_type) {
                            $have_role_group_names[] = $group->metagroup_id;
                        }
                    }

                    if ($missing_role_group_names = array_diff($this->roles, $have_role_group_names)) {
                        global $wpdb;

                        $wpdb->groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups);

                        $id_csv = implode("','", array_map('sanitize_key', $missing_role_group_names));

                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $add_metagroups = $wpdb->get_results(
                            "SELECT * FROM $wpdb->groups_table"
                            . " WHERE metagroup_type = 'wp_role' AND metagroup_id IN ('$id_csv')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        );

                        foreach ($add_metagroups as $row) {
                            $row->group_id = $row->ID;
                            $row->status = 'active';
                            $user_groups[$row->ID] = $row;
                        }
                    }
                }
            }
        }

        return $user_groups;
    }

    public function getSiteRoles($args = [])
    {
        global $wpdb;

        static $roles_retrieved;

        if (!isset($roles_retrieved)) {
            $roles_retrieved = [];
        }

        $u_g_clause = $this->getUsergroupsClause('uro', ['context' => 'roles']);

        if (isset($roles_retrieved[$u_g_clause])) {
            return;
        } else {
            $roles_retrieved[$u_g_clause] = true;
        }

        // phpcs Note: ug_clause constructed and sanitized by getUsergroupsClause()

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($results = $wpdb->get_results(
            "SELECT role_name FROM $wpdb->ppc_roles AS uro WHERE 1=1 $u_g_clause"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        )) {
            foreach ($results as $row) {
                $this->site_roles[$row->role_name] = true;
            }
        } else {
            $this->site_roles = [];
        }

        global $wp_roles;
        foreach (array_keys($this->site_roles) as $role_name) {
            if (!strpos($role_name, ':') && $wp_roles->is_role($role_name)) {
                $this->roles[] = $role_name;
            }
        }

        $this->roles = array_unique($this->roles);
    }

    public function retrieveExceptions($operations = [], $for_item_sources = [], $args = [])
    {
        $pp = presspermit();

        // note: Custom Group and User assignments are only for additions
        $args['ug_clause'] = $this->getUsergroupsClause('e', ['user_adjust' => $pp->getOption('user_exceptions')]);
        $args['operations'] = $operations;
        $args['for_item_sources'] = $for_item_sources;
        $this->except = array_merge($this->except, $pp->getExceptions($args));
    }

    public function getExceptionPosts($operation, $mod_type, $post_type, $args = [])
    {
        if (!isset($this->except["{$operation}_post"])) {
            $this->except["{$operation}_post"] = [];  // prevent redundant queries when no results
            $this->retrieveExceptions($operation, 'post');
        }

        $return = apply_filters('presspermit_get_exception_items', false, $operation, $mod_type, $post_type, $args);
        if (false !== $return) {
            return $return;
        }

        $exceptions = [];
        if (isset($this->except["{$operation}_post"]['post'][''][$mod_type]) 
            && is_array($this->except["{$operation}_post"]['post'][''][$mod_type])
            && isset($this->except["{$operation}_post"]['post'][''][$mod_type][$post_type])) {
            $exceptions = $this->except["{$operation}_post"]['post'][''][$mod_type][$post_type];
        }

        $exceptions = apply_filters('presspermit_get_exception_items', $exceptions, $operation, $mod_type, $post_type, $args);

        $status = (isset($args['status'])) ? $args['status'] : '';

        if (empty($exceptions)) {
            return [];
        }

        if (true === $status) {
            return $exceptions;
        } else {
            return Arr::flatten(array_intersect_key($exceptions, [$status => true]));
        }
    }

    public function getExceptionTerms($operation, $mod_type, $post_type, $taxonomy, $args = [])
    {
        $status = (isset($args['status'])) ? $args['status'] : '';

        if ($post_type) {
            $for_item_src = post_type_exists($post_type) ? 'post' : 'term';

            if (('post' == $for_item_src) && $taxonomy
                && !in_array($taxonomy, presspermit()->getEnabledTaxonomies(['object_type' => $post_type]), true)
            ) {
                return [];
            }
        } else {
            $for_item_src = 'post'; // nullstring post_type means all post types
        }

        if (!isset($this->except["{$operation}_{$for_item_src}"])) {
            $this->retrieveExceptions($operation, $for_item_src);
        }

        $args['via_item_source'] = 'term';
        $args['via_item_type'] = $taxonomy;
        $args['status'] = true; // prevent filter from flattening exceptions array, since we will do it below
        $type_restricts = apply_filters('presspermit_get_exception_items', false, $operation, $mod_type, $post_type, $args);

        if (false === $type_restricts) {
            $type_restricts = (isset($this->except["{$operation}_{$for_item_src}"]['term'][$taxonomy][$mod_type][$post_type]))
                ? $this->except["{$operation}_{$for_item_src}"]['term'][$taxonomy][$mod_type][$post_type]
                : [];
        }

        if ($post_type && !empty($args['merge_universals'])) {
            $universal_restricts = apply_filters('presspermit_get_exception_items', false, $operation, $mod_type, '', $args);
            if (false === $universal_restricts) {
                $universal_restricts = (isset($this->except["{$operation}_{$for_item_src}"]['term'][$taxonomy][$mod_type]['']))
                    ? $this->except["{$operation}_{$for_item_src}"]['term'][$taxonomy][$mod_type]['']
                    : [];
            }

            foreach (array_keys($universal_restricts) as $_status) {
                Arr::setElem($type_restricts, [$_status]);
                $type_restricts[$_status] = array_unique(array_merge($type_restricts[$_status], $universal_restricts[$_status]));
            }
        }

        if (!$type_restricts) {
            return [];
        }

        if (true === $status) {
            return $type_restricts;
        } else {
            $tt_ids = Arr::flatten(array_intersect_key($type_restricts, [$status => true]));
        }

        if (!empty($args['return_term_ids'])) {
            return PWP::ttidToTermid($tt_ids, $taxonomy);
        } else {
            return array_unique($tt_ids);
        }
    }

    public function reinstateCaps($wp_blogcaps, $orig_reqd_caps, $args)
    {
        global $current_user;
        							// todo: review (Add New Media)
        if (empty($current_user) || !did_action('presspermit_init') || did_action('presspermit_user_reload')) {
            return $wp_blogcaps;
        }

        $user = presspermit()->getUser();

        if ($user->ID == $current_user->ID) {
            if ((!isset($args[1]) || $args[1] == $user->ID) && array_diff_key(array_filter($user->allcaps), array_filter($current_user->allcaps))) {
            	if (defined('PRESSPERMIT_LEGACY_USER_CAPS')) {
            		$current_user->allcaps = array_merge(array_filter($current_user->allcaps), array_filter($user->allcaps));
            	} else {
            		$current_user->allcaps = array_merge(array_filter($current_user->allcaps), array_filter($user->allcaps, function ($x) {return $x === true;}));
            	}
            }
        }

        return $wp_blogcaps;
    }
}

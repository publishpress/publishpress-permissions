<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class Groups
{
    private $group_types = [];

    public function registerGroupType($agent_type, $args = [])
    {
        $defaults = ['labels' => [], 'schema' => []];
        $args = (object)array_merge($defaults, (array)$args);

        $args->labels = (object)$args->labels;

        if (empty($args->labels->name)) {
            $args->labels->name = $agent_type;
        }

        if (empty($args->labels->singular_name)) {
            $args->labels->singular_name = $agent_type;
        }

        $this->group_types[$agent_type] = (object)$args;
    }

    public function getGroupTypeObject($agent_type)
    {
        $this->initGroupLabels();

        return (isset($this->group_types[$agent_type])) ? $this->group_types[$agent_type] : false;
    }

    public function getGroupTypes($args = [], $return = 'name')
    {  // todo: handle $args
        if (!isset($this->group_types)) {
            return [];
        }

        if ('object' == $return) {
            $this->initGroupLabels();
        }

        if (!empty($args['editable'])) {
            $editable_group_types = apply_filters('presspermit_editable_group_types', ['pp_group']);
            return ('object' == $return) ? Arr::subset($this->group_types, $editable_group_types) : $editable_group_types;
        } else
            return ('object' == $return) ? $this->group_types : array_keys($this->group_types);
    }

    private function initGroupLabels($args = [])
    {
        if (isset($this->group_types['pp_group']) && ('group' == $this->group_types['pp_group']->labels->singular_name)) {
            $this->group_types['pp_group']->labels->singular_name = esc_html__('Custom Group', 'press-permit-core');
            $this->group_types['pp_group']->labels->plural_name = esc_html__('Custom Groups', 'press-permit-core');
            $this->group_types['pp_group']->labels->name = esc_html__('Groups', 'press-permit-core');
        }
    }

    public function groupTypeExists($agent_type)
    {
        return isset($this->group_types[$agent_type]);
    }

    public function groupTypeEditable($agent_type)
    {
        return in_array($agent_type, $this->getGroupTypes(['editable' => true]), true);
    }

    /**
     * Retrieve users who are members of a specified group
     * @param int group_id
     * @param string agent_type
     * @param string cols ('all' | 'id')
     * @param array args :
     *   - status ('active' | 'scheduled' | 'expired' | 'any')
     * @return array of objects or IDs
     */
    public function getGroupMembers($group_id, $agent_type = 'pp_group', $cols = 'all', $args = [])
    {
        if ('pp_group' == $agent_type) {
            require_once(PRESSPERMIT_CLASSPATH . '/DB/Groups.php');
            return DB\Groups::getGroupMembers($group_id, $cols, $args);
        } else {
            $members = ('count' == $cols) ? 0 : [];

            $members = apply_filters('presspermit_get_group_members', $members, $group_id, $agent_type, $cols, $args);

            return $members;
        }
    }

    /**
     * Add User(s) to a Permission Group
     * @param int group_id
     * @param array user_ids
     * @param array args :
     *   - agent_type (default 'pp_group')
     *   - status ('active' | 'scheduled' | 'expired' | 'any')
     *   - date_limited (default false)
     *   - start_date_gmt
     *   - end_date_gmt
     */
    public function addGroupUser($group_id, $user_ids, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/GroupUpdate.php');
        return DB\GroupUpdate::addGroupUser($group_id, $user_ids, $args);
    }

    /**
     * Remove User(s) from a Permission Group
     * @param int group_id
     * @param array user_ids
     * @param array args :
     *   - group_type (default 'pp_group')
     */
    public function removeGroupUser($group_id, $user_ids, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/GroupUpdate.php');
        return DB\GroupUpdate::removeGroupUser($group_id, $user_ids, $args);
    }

    /**
     * Update Group Membership for User(s)
     * @param int group_id
     * @param array user_ids
     * @param array args :
     *   - agent_type (default 'pp_group')
     *   - status ('active' | 'scheduled' | 'expired' | 'any')
     *   - date_limited (default false)
     *   - start_date_gmt
     *   - end_date_gmt
     */
    public function updateGroupUser($group_id, $user_ids, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/GroupUpdate.php');
        return DB\GroupUpdate::updateGroupUser($group_id, $user_ids, $args);
    }

    /**
     * Retrieve groups for a specified user
     * @param int user_id or WP_User object
     * @param string agent_type
     * @param array args :
     *   - cols ('all' | 'id')
     *   - status ('active' | 'scheduled' | 'expired' | 'any')
     *   - metagroup_type (default null)
     *   - query_user_ids (array, default false)
     *   - force_refresh (default false)
     * @return array (object or storage date string, with group id as array key)
     */
    public function getGroupsForUser($user, $agent_type = 'pp_group', $args = [])
    {
        if ('pp_group' == $agent_type) {
            $args['agent_type'] = $agent_type;

            require_once(PRESSPERMIT_CLASSPATH . '/DB/Groups.php');
            return DB\Groups::getGroupsForUser($user, $args);
        }

        $user_id = (is_object($user)) ? $user->ID : (int) $user;
        return apply_filters('presspermit_get_groups_for_user', [], $user_id, $agent_type, $args);
    }

    public function getGroups($agent_type = 'pp_group', $args = [])
    {
        if ('pp_group' == $agent_type) {
            require_once(PRESSPERMIT_CLASSPATH . '/DB/Groups.php');
            return DB\Groups::getGroups($args);
        } else {
            return apply_filters('presspermit_get_groups', [], $agent_type, $args);
        }
    }

    public function getAgent($agent_id, $agent_type = 'pp_group')
    {
        static $cache = [];

        $cache_key = $agent_type . ':' . $agent_id;
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        if ('pp_group' == $agent_type) {
            global $wpdb;

            // Direct query of plugin table for this function, which supports various plugin admin operations
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($result = $wpdb->get_row($wpdb->prepare(
                "SELECT ID, group_name AS name, group_description, metagroup_type, metagroup_id FROM $wpdb->pp_groups WHERE ID = %d",
                $agent_id
            ))) {
                $result->name = stripslashes($result->name);
                $result->group_description = stripslashes($result->group_description);
                $result->group_name = $result->name;  // todo: review usage of these properties
            }
        } elseif ('user' == $agent_type) {
            if ($result = new \WP_User($agent_id)) {
                $result->name = $result->display_name;
            }
        } else
            $result = null;

        $result = apply_filters('presspermit_get_group', $result, $agent_id, $agent_type);

        $cache[$cache_key] = $result;
        return $result;
    }

    /**
     * Retrieve a Permission Group object
     * @param int group_id
     * @param string agent_type (pp_group, bp_group, etc.)
     * @return object Permission Group
     *  - ID
     *  - group_name
     *  - group_description
     *  - metagroup_type
     *  - metagroup_id
     */
    public function getGroup($group_id, $agent_type = 'pp_group')
    {
        return $this->getAgent($group_id, $agent_type);
    }

    /**
     * Create a new Permission Group
     * @param array group_vars_arr :
     *   - group_name
     *   - group_description (optional)
     *   - metagroup_type (optional, for internal use)
     * @return int ID of new group
     */
    public function createGroup($group_vars_arr)
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/GroupUpdate.php');
        return DB\GroupUpdate::createGroup($group_vars_arr);
    }

    /**
     * Delete a Permission Group
     * @param array group_id
     * @param array agent_type (pp_group, bp_group, etc.)
     */
    public function deleteGroup($group_id, $agent_type)
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/GroupUpdate.php');
        return DB\GroupUpdate::deleteGroup($group_id, $agent_type);
    }

    /**
     * Retrieve the Permission Group object for a WP Role or other metagroup, by providing its name
     * @param string metagroup_type
     * @param string metagroup_id
     * @param array args :
     *   - cols (return format - 'all' | 'id')
     * @return object Permission Group (unless cols = 'id')
     *  - ID
     *  - group_name
     *  - group_description
     *  - metagroup_type
     *  - metagroup_id
     */
    public function getMetagroup($metagroup_type, $metagroup_id, $args = [])
    {
        global $wpdb;

        $defaults = ['cols' => 'all'];
        $args = array_merge($defaults, $args);

        // guard against groups table being imported into a different database (with mismatching options table)
        $dbname = defined( 'DB_NAME' ) ? DB_NAME : '';
        $site_key = md5(get_option('site_url') . $dbname . $wpdb->prefix);

        if (!$buffered_groups = presspermit()->getOption("buffer_metagroup_id_{$site_key}")) {
            $buffered_groups = [];
        }

        $key = $metagroup_id . ':' . $wpdb->pp_groups;  // PP setting may change to/from netwide groups after buffering
        if (!isset($buffered_groups[$key])) {
            // Direct query of plugin table to populate the metagroup cache
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if (!$group = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $wpdb->pp_groups WHERE metagroup_type = %s AND metagroup_id = %s LIMIT 1",
                        $metagroup_type,
                        $metagroup_id
                    )
                )
            ) {
                // Groups table not created early enough on some multisite installations when third party code triggers early set_current_user action. 
                // TODO: Identify indicators to call dbSetup() pre-emptively.
                if (!empty($wpdb->last_error) && is_string($wpdb->last_error) && strpos($wpdb->last_error, ' exist')) {
                    require(PRESSPERMIT_ABSPATH . '/db-config.php');
                    
                    require_once(PRESSPERMIT_CLASSPATH . '/DB/DatabaseSetup.php');
                    new DB\DatabaseSetup();

                    $group = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM $wpdb->pp_groups WHERE metagroup_type = %s AND metagroup_id = %s LIMIT 1",
                            $metagroup_type,
                            $metagroup_id
                        )
                    );
                }
            }

            if ($group) {
                $group->group_id = $group->ID;
                $group->status = 'active';
                $buffered_groups[$key] = $group;
                presspermit()->updateOption("buffer_metagroup_id_{$site_key}", $buffered_groups);
            }
        }

        if ('id' == $args['cols']) {
            return (isset($buffered_groups[$key])) ? $buffered_groups[$key]->ID : false;
        } else {
            return (isset($buffered_groups[$key])) ? $buffered_groups[$key] : false;
        }
    }

    public function isMetagroup($metagroup_type, $group_id)
    {
        $group = $this->getGroup($group_id, 'pp_group');
        
        return ($group && ! empty($group->metagroup_type) && ($metagroup_type == $group->metagroup_type))
        ? $group
        : false;
    }

    /**
     * Retrieve a Permission Group object by providing its name
     * @param int group_name
     * @param string agent_type (pp_group, bp_group, etc.)
     * @return object Permission Group
     *  - ID
     *  - group_name
     *  - group_description
     *  - metagroup_type
     *  - metagroup_id
     */
    public function getGroupByName($name, $agent_type = 'pp_group')
    {
        global $wpdb;
        $wpdb->groups_table = apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, $agent_type);

        // Direct query of plugin table on plugin admin operation
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID, group_name AS name, group_description FROM $wpdb->groups_table WHERE group_name = %s",
                $name
            )
        );

        return $result;
    }

    public function userCan($cap_name, $group_id, $group_type)
    {
        $has_sitewide = current_user_can($cap_name);

        if ($group_id && !$has_sitewide) {
            $operation = ('pp_manage_members' == $cap_name) ? 'manage' : 'edit';
            if (in_array($group_id, apply_filters('presspermit_admin_groups', [], $operation))) {
                $has_sitewide = true;
            }
        }

        if ($has_sitewide && !is_multisite() && presspermit()->isUserAdministrator()) {
            return true;
        } else {
            return apply_filters('presspermit_user_has_group_cap', $has_sitewide, $cap_name, $group_id, $group_type);
        }
    }

    public function anyGroupManager()
    {
        $has_sitewide = current_user_can('pp_edit_groups');

        if ($has_sitewide && presspermit()->isUserAdministrator()) {
            return true;
        } else {
            return apply_filters('presspermit_user_has_group_cap', $has_sitewide, 'pp_edit_groups', false, false);
        }
    }
}

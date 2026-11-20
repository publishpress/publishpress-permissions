<?php
namespace PublishPress\Permissions\Mcp;

\defined('ABSPATH') || exit;

/** Typed supplemental-role patches through the native assignment lifecycle. */
class Roles
{
    public function schema($write)
    {
        $fields = ['agent_type' => ['type' => 'string', 'enum' => ['user', 'pp_group']], 'agent_id' => ['type' => 'integer', 'minimum' => 1]];
        $required = ['agent_type', 'agent_id'];
        if ($write) {
            $fields['changes'] = ['type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => Input::schema([
                'role' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200], 'enabled' => ['type' => 'boolean']], ['role', 'enabled'])];
            $fields['revision'] = ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'];
            $fields['dry_run'] = ['type' => 'boolean'];
            $required = array_merge($required, ['changes', 'revision']);
        }
        return Input::schema($fields, $required);
    }

    public function read($input)
    {
        $input = Input::validate($input, $this->schema(false));
        if (is_wp_error($input)) {
            return $input;
        }
        $access = Access::subject($input['agent_id'], $input['agent_type']);
        if (is_wp_error($access)) {
            return $access;
        }
        $group = null;
        if ('pp_group' === $input['agent_type']) {
            $group = (new Groups())->read(['id' => $input['agent_id']]);
            if (is_wp_error($group)) {
                return $group;
            }
        }
        $rows = presspermit()->getRoles($input['agent_id'], $input['agent_type']);
        $roles = [];
        foreach ($rows as $name => $id) {
            $roles[] = ['role' => $name, 'assignment_id' => (int) $id];
        }
        $pp = presspermit();
        $patterns = [];
        foreach ($pp->role_defs->pattern_roles as $name => $definition) {
            $patterns[] = ['name' => $name, 'label' => $definition->labels->name ?? $name];
        }
        $statuses = [];
        foreach ($pp->getEnabledPostTypes() as $type) {
            $statuses[$type] = array_keys((array) \PublishPress\Permissions\PWP::getPostStatuses(['post_type' => $type]));
        }
        return ['agent_type' => $input['agent_type'], 'agent_id' => $input['agent_id'], 'roles' => $roles,
            'direct_role_capabilities' => ['pp_manage_permissions', 'promote_users'], 'direct_roles' => $this->directChoices(), 'pattern_roles' => $patterns, 'post_statuses' => (object) $statuses, 'post_types' => array_values($pp->getEnabledPostTypes()),
            'taxonomies' => array_values($pp->getEnabledTaxonomies()),
            'revision' => Access::revision([$rows, $group ? $group['revision'] : null, $patterns, $pp->role_defs->direct_roles, (new Settings())->snapshot('role_usage'), $statuses, wp_roles()->roles, $pp->getEnabledPostTypes(), $pp->getEnabledTaxonomies()])];
    }

    public function update($input)
    {
        $input = Input::validate($input, $this->schema(true));
        if (is_wp_error($input)) {
            return $input;
        }
        $subject = ['agent_type' => $input['agent_type'], 'agent_id' => $input['agent_id']];
        $before = $this->read($subject);
        if (is_wp_error($before)) {
            return $before;
        }
        $stale = Access::stale($input['revision'], $before['revision']);
        if (is_wp_error($stale)) {
            return $stale;
        }
        $pp = presspermit();
        $existing = array_column($before['roles'], 'assignment_id', 'role');
        $changes = [];
        foreach ($input['changes'] as $row) {
            if (isset($changes[$row['role']])) {
                return Input::error();
            }
            $changes[$row['role']] = $row;
            if (!$row['enabled']) {
                if (!isset($existing[$row['role']])) {
                    return Input::error();
                }
                continue;
            }
            $parts = explode(':', $row['role']);
            if (count($parts) === 1) {
                $direct = $this->authorizeDirect($row['role'], $subject);
                if (is_wp_error($direct)) {
                    return $direct;
                }
                continue;
            }
            if (!in_array(count($parts), [3, 5], true) || !isset($pp->role_defs->pattern_roles[$parts[0]])
                || !in_array($parts[1], ['post', 'term'], true)
                || (count($parts) === 5 && ('post' !== $parts[1] || 'post_status' !== $parts[3]
                    || !\PublishPress\Permissions\PWP::getPostStatuses(['name' => $parts[4], 'post_type' => $parts[2]])))) {
                return Input::error();
            }
            $types = 'post' === $parts[1] ? $pp->getEnabledPostTypes() : $pp->getEnabledTaxonomies();
            if (!in_array($parts[2], $types, true) || !Access::roleAllowed($row['role'])
                || !$pp->admin()->userCanAdminRole($parts[0], $parts[2])) {
                return Access::denied();
            }
            if ('post' === $parts[1]) {
                $type = get_post_type_object($parts[2]);
                if (!$type || !current_user_can($type->cap->edit_others_posts) || !current_user_can($type->cap->read_private_posts)) {
                    return Access::denied();
                }
                $ids = Access::postIds([$parts[2]]);
                if (count($ids) > 1000) {
                    return Input::error();
                }
                foreach ($ids as $id) {
                    if (!current_user_can('read_post', $id) || !current_user_can('edit_post', $id)) {
                        return Access::denied();
                    }
                }
            } else {
                $type = get_taxonomy($parts[2]);
                if (!$type || !current_user_can($type->cap->manage_terms)) {
                    return Access::denied();
                }
                $terms = Access::termIds($parts[2]);
                if (is_wp_error($terms) || count($terms) > 1000) {
                    return Input::error();
                }
                foreach ($terms as $id) {
                    if (!current_user_can('edit_term', $id)) {
                        return Access::denied();
                    }
                }
            }
        }
        if ('pp_group' === $input['agent_type']) {
            $group = (new Groups())->read(['id' => $input['agent_id']]);
            if (is_wp_error($group)) {
                return $group;
            }
            if (!$group['custom_group']) {
                if ('wp_role' !== $group['metagroup_type']) {
                    return Access::denied();
                }
                $query = ['fields' => 'ID', 'number' => 1001];
                if (!in_array($group['metagroup_id'], ['wp_all', 'wp_anon'], true)) {
                    if (!get_role($group['metagroup_id'])) {
                        return Access::denied();
                    }
                    $query['role'] = $group['metagroup_id'];
                }
                $members = 'wp_anon' === $group['metagroup_id'] ? [] : get_users($query);
                if (count($members) > 1000) {
                    return Input::error();
                }
                foreach ($members as $id) {
                    if (!current_user_can('edit_user', (int) $id)) {
                        return Access::denied();
                    }
                }
            }
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'changes' => array_values($changes), 'revision' => $before['revision']];
        }
        require_once PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php';
        foreach ($changes as $row) {
            if ($row['enabled'] && !isset($existing[$row['role']])) {
                $pp->assignRoles([$row['role'] => [$input['agent_id'] => true]], $input['agent_type']);
            } elseif (!$row['enabled']) {
                \PublishPress\Permissions\DB\PermissionsUpdate::removeRolesById([$existing[$row['role']]]);
            }
        }
        do_action('presspermit_trigger_cache_flush');
        $after = $this->read($subject);
        if (is_wp_error($after)) {
            return Access::unconfirmed();
        }
        $present = array_column($after['roles'], 'role');
        foreach ($changes as $row) {
            if (in_array($row['role'], $present, true) !== $row['enabled']) {
                return Access::unconfirmed();
            }
        }
        return $after;
    }

    private function directChoices()
    {
        if (!presspermit()->moduleActive('collaboration')) {
            return [];
        }
        $rows = [];
        foreach (presspermit()->role_defs->direct_roles as $name => $definition) {
            $rows[] = ['name' => $name, 'label' => wp_roles()->role_names[$name] ?? $name,
                'capabilities_allowed' => Access::roleAllowed($name)];
        }
        return $rows;
    }

    private function authorizeDirect($name, $subject)
    {
        $pp = presspermit();
        if (!$pp->moduleActive('collaboration') || !isset($pp->role_defs->direct_roles[$name])
            || !get_role($name) || !Access::roleAllowed($name) || !current_user_can('promote_users')
            || !$pp->admin()->userCanAdminRole($name, 'wp_role')) {
            return Access::denied();
        }
        if (!function_exists('get_editable_roles')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        if (!isset(get_editable_roles()[$name])) {
            return Access::denied();
        }
        if ('pp_group' === $subject['agent_type']) {
            $group = (new Groups())->read(['id' => $subject['agent_id']]);
            if (is_wp_error($group) || ('wp_role' === $group['metagroup_type'] && in_array($group['metagroup_id'], ['wp_all', 'wp_anon'], true))) {
                return Access::denied();
            }
        }
        $caps = array_filter(get_role($name)->capabilities);
        $checked = 0;
        foreach (get_post_types([], 'objects') as $type) {
            $operations = array_intersect(['edit_posts', 'edit_others_posts', 'delete_posts', 'delete_others_posts', 'publish_posts', 'read_private_posts', 'create_posts'], array_keys((array) $type->cap));
            $required = [];
            foreach ($operations as $operation) {
                $required[$operation] = $type->cap->$operation;
            }
            if (!array_intersect_key($caps, array_flip($required))) {
                continue;
            }
            $ids = Access::postIds([$type->name]);
            $checked += count($ids);
            if ($checked > 1000) {
                return Input::error();
            }
            $deletes = isset($caps[$type->cap->delete_posts]) || isset($caps[$type->cap->delete_others_posts]);
            foreach ($ids as $id) {
                if (!current_user_can('read_post', $id) || !current_user_can('edit_post', $id)
                    || ($deletes && !current_user_can('delete_post', $id))) {
                    return Access::denied();
                }
            }
        }
        foreach (get_taxonomies([], 'objects') as $taxonomy) {
            if (!array_intersect_key($caps, array_flip((array) $taxonomy->cap))) {
                continue;
            }
            $ids = Access::termIds($taxonomy->name);
            $checked += count($ids);
            if ($checked > 1000) {
                return Input::error();
            }
            foreach ($ids as $id) {
                if (!current_user_can('edit_term', $id)
                    || (isset($caps[$taxonomy->cap->delete_terms]) && !current_user_can('delete_term', $id))) {
                    return Access::denied();
                }
            }
        }
        return true;
    }
}

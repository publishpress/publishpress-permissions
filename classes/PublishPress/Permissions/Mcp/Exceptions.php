<?php
namespace PublishPress\Permissions\Mcp;

use PublishPress\Permissions\DB\PermissionsUpdate;

defined('ABSPATH') || exit;

/** Object-bound native permissions, including inheritance and exact removal scope. */
class Exceptions
{
    public function schema($write)
    {
        $fields = ['agent_type' => ['type' => 'string', 'enum' => ['user', 'pp_group']], 'agent_id' => ['type' => 'integer', 'minimum' => 1],
            'source' => ['type' => 'string', 'enum' => ['post', 'term', 'pp_group']], 'object_id' => ['type' => 'integer', 'minimum' => 1],
            'taxonomy' => ['type' => 'string', 'maxLength' => 100], 'post_type' => ['type' => 'string', 'maxLength' => 100],
            'operation' => ['type' => 'string', 'enum' => ['read', 'edit', 'publish', 'associate', 'assign', 'manage', 'copy', 'revise']],
            'target_source' => ['type' => 'string', 'enum' => ['post', 'term', 'pp_group']],
            'status' => ['type' => 'string', 'maxLength' => 100]];
        $required = ['agent_type', 'agent_id', 'source', 'object_id', 'operation'];
        if ($write) {
            $fields['mod_type'] = ['type' => 'string', 'enum' => ['additional', 'include', 'exclude']];
            $fields['assign_for'] = ['type' => 'string', 'enum' => ['item', 'children', 'both']];
            $fields['enabled'] = ['type' => 'boolean'];
            $fields['revision'] = ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'];
            $fields['dry_run'] = ['type' => 'boolean'];
            $required = array_merge($required, ['mod_type', 'assign_for', 'enabled', 'revision']);
        }
        return Input::schema($fields, $required);
    }

    private function objectAccess($source, $id, $taxonomy = '')
    {
        if ('pp_group' === $source) {
            return Delegation::target($id);
        }
        if ('post' === $source) {
            $object = get_post($id);
            return $object && current_user_can('read_post', $id) && current_user_can('edit_post', $id) ? $object : Access::denied();
        }
        if ('term' !== $source) {
            return Access::denied();
        }
        $term = get_term($id, $taxonomy);
        $tx = get_taxonomy($taxonomy);
        return $term && !is_wp_error($term) && $tx && current_user_can($tx->cap->manage_terms) && current_user_can('edit_term', $id) ? $term : Access::denied();
    }

    private function context($input)
    {
        $access = Access::subject($input['agent_id'], $input['agent_type']);
        if (is_wp_error($access)) {
            return $access;
        }
        if ('pp_group' === $input['source']) {
            if ('manage' !== $input['operation'] || !empty($input['taxonomy']) || !empty($input['post_type']) || !empty($input['status'])
                || (isset($input['target_source']) && 'pp_group' !== $input['target_source'])) {
                return Input::error();
            }
            $target = Delegation::target($input['object_id']);
            if (is_wp_error($target)) {
                return $target;
            }
            $args = ['agent_type' => $input['agent_type'], 'agent_id' => $input['agent_id'], 'operation' => 'manage',
                'for_item_source' => 'pp_group', 'for_item_type' => 'pp_group', 'for_item_status' => '',
                'via_item_source' => 'pp_group', 'via_item_type' => '', 'item_id' => $input['object_id']];
            return presspermit()->admin()->canSetExceptions('manage', 'pp_group', $args) ? $args : Access::denied();
        }
        $taxonomy = $input['taxonomy'] ?? '';
        $object = $this->objectAccess($input['source'], $input['object_id'], $taxonomy);
        if (is_wp_error($object)) {
            return $object;
        }
        $target_source = $input['target_source'] ?? 'post';
        if ('post' === $input['source']) {
            if ('post' !== $target_source || (isset($input['post_type']) && $input['post_type'] !== $object->post_type) || $taxonomy) {
                return Input::error();
            }
            $target = $object->post_type;
            $id = $object->ID;
        } else {
            $target = 'term' === $target_source ? $taxonomy : ($input['post_type'] ?? '');
            if (!$taxonomy || ('post' === $target_source && (!$target || !in_array($target, get_taxonomy($taxonomy)->object_type, true)))) {
                return Input::error();
            }
            $id = (int) $object->term_taxonomy_id;
        }
        $pp = presspermit();
        $types = 'post' === $target_source ? $pp->getEnabledPostTypes() : $pp->getEnabledTaxonomies();
        if (!in_array($target, $types, true) || !in_array($input['operation'], $pp->getOperations(), true)) {
            return Input::error();
        }
        $caps = ['read' => 'pp_set_view_permissions', 'edit' => 'pp_set_edit_permissions', 'publish' => 'pp_set_edit_permissions',
            'copy' => 'pp_set_copy_permissions', 'revise' => 'pp_set_revise_permissions',
            'assign' => 'pp_set_term_assign_permissions', 'manage' => 'pp_set_term_manage_permissions',
            'associate' => 'term' === $input['source'] ? 'pp_set_term_associate_permissions' : 'pp_set_associate_permissions'];
        if (!current_user_can($caps[$input['operation']])) {
            return Access::denied();
        }
        $args = ['agent_type' => $input['agent_type'], 'agent_id' => $input['agent_id'], 'operation' => $input['operation'],
            'for_item_source' => $target_source, 'for_item_type' => $target,
            'for_item_status' => $input['status'] ?? '', 'via_item_source' => $input['source'],
            'via_item_type' => 'term' === $input['source'] ? $taxonomy : '', 'item_id' => $id];
        if ($args['for_item_status'] && (!preg_match('/^post_status:([a-z0-9_-]+)$/D', $args['for_item_status'], $match)
            || !get_post_status_object($match[1]))) {
            return Input::error();
        }
        if (!$pp->admin()->canSetExceptions($input['operation'], $target, $args)) {
            return Access::denied();
        }
        return $args;
    }

    private function rows($args)
    {
        $query = ['agent_type' => $args['agent_type'], 'agent_id' => $args['agent_id'], 'operations' => [$args['operation']],
            'for_item_source' => $args['for_item_source'], 'via_item_source' => $args['via_item_source'],
            'item_id' => $args['item_id'], 'for_item_status' => $args['for_item_status'], 'assign_for' => '',
            'post_types' => 'post' === $args['for_item_source'] ? [$args['for_item_type']] : [],
            'taxonomies' => 'term' === $args['for_item_source'] ? [$args['for_item_type']] : [], 'return_raw_results' => true];
        $rows = presspermit()->getExceptions($query);
        if (!is_array($rows) || count($rows) > 1000) {
            return Access::unconfirmed();
        }
        return $rows;
    }

    private function project($row)
    {
        return ['id' => (int) $row->eitem_id, 'mod_type' => $row->mod_type,
            'assign_for' => $row->assign_for ?: 'both', 'inherited_from' => (int) $row->inherited_from,
            'status' => $row->for_item_status];
    }

    private function tree($input, $args)
    {
        if ('pp_group' === $input['source']) {
            $group = Delegation::target($input['object_id']);
            if (is_wp_error($group)) {
                return $group;
            }
            require_once PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php';
            $permissions = PermissionsUpdate::getAgentRemovalItems($input['object_id'], 'pp_group');
            if (!is_array($permissions) || count($permissions) > 1000) {
                return Access::unconfirmed();
            }
            return [$input['object_id'] => [$group['revision'], $permissions, wp_roles()->roles]];
        }
        $pending = [$input['object_id']];
        $visited = $snapshot = [];
        while ($pending) {
            $id = (int) array_shift($pending);
            if (isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;
            if (count($visited) > 1000) {
                return new \WP_Error('presspermit_mcp_scope_limit', __('Use native administration for permission scopes exceeding 1,000 objects.', 'press-permit-core'));
            }
            $object = $this->objectAccess($input['source'], $id, $input['taxonomy'] ?? '');
            if (is_wp_error($object)) {
                return $object;
            }
            if ('post' === $input['source']) {
                $snapshot[$id] = [$object->post_parent, $object->post_type, $object->post_status, $object->post_modified_gmt];
                $children = Access::postIds([], $id);
            } else {
                $snapshot[$id] = [$object->parent, $object->taxonomy, $object->term_taxonomy_id];
                $children = Access::termIds($input['taxonomy'], $id);
                if (is_wp_error($children)) {
                    return $children;
                }
            }
            $pending = array_merge($pending, $children);
            if (count($pending) + count($visited) > 1001) {
                return new \WP_Error('presspermit_mcp_scope_limit', __('The affected permission tree is too large.', 'press-permit-core'));
            }
        }
        return $snapshot;
    }

    public function read($input)
    {
        $input = Input::validate($input, $this->schema(false));
        if (is_wp_error($input)) {
            return $input;
        }
        $args = $this->context($input);
        if (is_wp_error($args)) {
            return $args;
        }
        $rows = $this->rows($args);
        if (is_wp_error($rows)) {
            return $rows;
        }
        $tree = $this->tree($input, $args);
        if (is_wp_error($tree)) {
            return $tree;
        }
        return ['rules' => array_map([$this, 'project'], array_values($rows)), 'affected_objects' => count($tree),
            'revision' => Access::revision([$args, $rows, $tree])];
    }

    /** Validated native descendant scope for batch-overlap detection. */
    public function scopeObjects($input)
    {
        $args = $this->context($input);
        if (is_wp_error($args)) {
            return $args;
        }
        $tree = $this->tree($input, $args);
        return is_wp_error($tree) ? $tree : array_keys($tree);
    }

    public function update($input)
    {
        $input = Input::validate($input, $this->schema(true));
        if (is_wp_error($input)) {
            return $input;
        }
        $scope = array_intersect_key($input, $this->schema(false)['properties']);
        $before = $this->read($scope);
        if (is_wp_error($before)) {
            return $before;
        }
        $stale = Access::stale($input['revision'], $before['revision']);
        if (is_wp_error($stale)) {
            return $stale;
        }
        $args = $this->context($scope);
        if (is_wp_error($args)) {
            return $args;
        }
        if (('additional' === $input['mod_type'] && defined('PP_NO_ADDITIONAL_ACCESS'))
            || ('exclude' === $input['mod_type'] && 'pp_group' === $input['agent_type'] && defined('PP_NO_GROUP_RESTRICTIONS'))) {
            return new \WP_Error('presspermit_mcp_configuration_locked', __('This permission mode is disabled by native configuration.', 'press-permit-core'));
        }
        if ('include' === $input['mod_type'] && 'post' === $args['for_item_source']) {
            $all = Access::postIds([$args['for_item_type']]);
            if (count($all) > 1000) {
                return Input::error();
            }
            foreach ($all as $id) {
                if (is_wp_error($this->objectAccess('post', $id))) {
                    return Access::denied();
                }
            }
        }
        if ('include' === $input['mod_type'] && 'term' === $args['for_item_source']) {
            $all = Access::termIds($args['for_item_type']);
            if (count($all) > 1000) {
                return Input::error();
            }
            foreach ($all as $id) {
                if (is_wp_error($this->objectAccess('term', $id, $args['for_item_type']))) {
                    return Access::denied();
                }
            }
        }
        if ('pp_group' === $input['source']) {
            $delegation = Delegation::authorizeChange($input);
            if (is_wp_error($delegation)) {
                return $delegation;
            }
        }
        $ids = [];
        foreach ($before['rules'] as $row) {
            if ($row['mod_type'] === $input['mod_type'] && $row['assign_for'] === $input['assign_for']) {
                if ($row['inherited_from'] && !$input['enabled']) {
                    return Input::error();
                }
                $ids[] = $row['id'];
            }
        }
        require_once PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php';
        if (!$input['enabled'] && $ids) {
            foreach (PermissionsUpdate::getExceptionRemovalItems($ids) as $row) {
                $object_id = (int) $row->item_id;
                if ('term' === $row->via_item_source) {
                    global $wpdb;
                    $term = get_term_by('term_taxonomy_id', $object_id, $row->via_item_type);
                    if (!$term) {
                        return Access::denied();
                    }
                    $object_id = (int) $term->term_id;
                }
                if ($row->agent_type !== $input['agent_type'] || (int) $row->agent_id !== $input['agent_id']
                    || is_wp_error($this->objectAccess($row->via_item_source, $object_id, $row->via_item_type))) {
                    return Access::denied();
                }
            }
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'enabled' => $input['enabled'], 'mod_type' => $input['mod_type'],
                'assign_for' => $input['assign_for'], 'affected_objects' => $before['affected_objects']];
        }
        if ($input['enabled']) {
            $args['mod_type'] = $input['mod_type'];
            $assign_for = 'both' === $input['assign_for'] ? '' : $input['assign_for'];
            presspermit()->assignExceptions([$assign_for => [$input['agent_id'] => true]], $input['agent_type'], $args);
        } elseif ($ids) {
            PermissionsUpdate::removeExceptionItemsById($ids);
        }
        do_action('presspermit_trigger_cache_flush');
        $after = $this->read($scope);
        if (is_wp_error($after)) {
            return Access::unconfirmed();
        }
        $present = false;
        foreach ($after['rules'] as $row) {
            if ($row['mod_type'] === $input['mod_type'] && $row['assign_for'] === $input['assign_for']) {
                $present = true;
            }
        }
        return $present === $input['enabled'] ? $after : Access::unconfirmed();
    }
}

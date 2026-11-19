<?php
namespace PublishPress\Permissions\Mcp;

use PublishPress\Permissions\DB\PermissionsUpdate;
use PublishPress\Permissions\UI\AgentExceptionsAjax;

\defined('ABSPATH') || exit;

/** Object-bound native permissions, including inheritance and exact removal scope. */
class Exceptions
{
    public function schema($write, $exact = false)
    {
        $fields = ['agent_type' => ['type' => 'string', 'enum' => ['user', 'pp_group']], 'agent_id' => ['type' => 'integer', 'minimum' => 1],
            'source' => ['type' => 'string', 'enum' => ['post', 'term', 'pp_group']], 'object_id' => ['type' => 'integer', 'minimum' => 1],
            'taxonomy' => ['type' => 'string', 'maxLength' => 100], 'post_type' => ['type' => 'string', 'maxLength' => 100],
            'operation' => ['type' => 'string', 'enum' => ['read', 'edit', 'publish', 'associate', 'assign', 'manage', 'copy', 'revise']],
            'target_source' => ['type' => 'string', 'enum' => ['post', 'term', 'pp_group']],
            'status' => ['type' => 'string', 'maxLength' => 100]];
        $required = ['agent_type', 'source', 'operation'];
        if ($write || $exact) {
            $required = array_merge($required, ['agent_id', 'object_id']);
        }
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
            if (!in_array($target_source, ['post', 'term'], true) || !$taxonomy || ('post' === $target_source && (!$target || !in_array($target, get_taxonomy($taxonomy)->object_type, true)))) {
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

    private function project($row, $object_id)
    {
        return ['id' => (int) $row->eitem_id, 'agent_type' => $row->agent_type, 'agent_id' => (int) $row->agent_id,
            'object_id' => $object_id, 'mod_type' => $row->mod_type,
            'assign_for' => $row->assign_for ?: 'both', 'inherited_from' => (int) $row->inherited_from,
            'status' => $row->for_item_status];
    }

    private function tree($input, $args, $authorize_descendants = false)
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
            if ($id === (int) $input['object_id'] || $authorize_descendants) {
                $object = $this->objectAccess($input['source'], $id, $input['taxonomy'] ?? '');
                if (is_wp_error($object)) {
                    return $object;
                }
            } else {
                // Read only identifiers and hierarchy metadata for unrelated descendants; their content and permissions are not exposed.
                $object = 'post' === $input['source']
                    ? get_post($id)
                    : get_term($id, $input['taxonomy'] ?? '');
                if (!$object || is_wp_error($object)) {
                    return Access::denied();
                }
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
        $has_agent = isset($input['agent_id']);
        $has_object = isset($input['object_id']);
        if ($has_agent === $has_object || (!$has_object && !isset($input['post_type']) && !isset($input['taxonomy']))) {
            return Input::error();
        }

        $pp = presspermit();
        $source = $input['source'];
        $target = $input['target_source'] ?? ('pp_group' === $source ? 'pp_group' : 'post');
        $post_type = $input['post_type'] ?? '';
        $taxonomy = $input['taxonomy'] ?? '';
        if (!in_array($input['operation'], $pp->getOperations(), true) && 'pp_group' !== $source) {
            return Input::error();
        }
        if (($post_type && !in_array($post_type, $pp->getEnabledPostTypes(), true))
            || ($taxonomy && !taxonomy_exists($taxonomy))
            || ('post' === $source && ('post' !== $target || $taxonomy))
            || ('term' === $source && !in_array($target, ['post', 'term'], true))
            || ('pp_group' === $source && ('pp_group' !== $target || 'manage' !== $input['operation'] || $post_type || $taxonomy))) {
            return Input::error();
        }

        $item_id = false;
        if ($has_agent) {
            $access = Access::subject($input['agent_id'], $input['agent_type']);
            if (is_wp_error($access)) {
                return $access;
            }
        } else {
            if ('term' === $source && !$taxonomy) {
                $term = get_term($input['object_id']);
                if (!$term || is_wp_error($term)) {
                    return Access::denied();
                }
                $taxonomy = $term->taxonomy;
            }
            $object = $this->objectAccess($source, $input['object_id'], $taxonomy);
            if (is_wp_error($object)) {
                return $object;
            }
            if ('post' === $source && $post_type && $post_type !== $object->post_type) {
                return Input::error();
            }
            $item_id = 'term' === $source ? (int) $object->term_taxonomy_id : $input['object_id'];
        }

        global $wpdb;
        $query = [
            'agent_type' => $input['agent_type'], 'agent_id' => $input['agent_id'] ?? 0,
            'operations' => [$input['operation']], 'for_item_source' => $target, 'via_item_source' => $source,
            'item_id' => $item_id, 'for_item_status' => $input['status'] ?? '', 'assign_for' => '',
            'post_types' => $post_type ? [$post_type] : true,
            'taxonomies' => $taxonomy ? [$taxonomy] : true,
            'return_raw_results' => true,
        ];
        if (!$has_agent) {
            $query['ug_clause'] = $wpdb->prepare(' AND e.agent_type = %s', $input['agent_type']);
        }
        $rows = $pp->getExceptions($query);
        if ($wpdb->last_error || !is_array($rows) || count($rows) > 1000) {
            return Access::unconfirmed();
        }

        $rules = $objects = [];
        foreach ($rows as $row) {
            if ($row->operation !== $input['operation'] || ($taxonomy && $row->via_item_type !== $taxonomy)) {
                continue;
            }
            $scope = $input;
            $scope['agent_id'] = (int) $row->agent_id;
            $scope['object_id'] = (int) $row->item_id;
            if ('term' === $source) {
                $term = get_term_by('term_taxonomy_id', $row->item_id);
                if (!$term || $term->taxonomy !== $row->via_item_type) {
                    continue;
                }
                $scope['object_id'] = (int) $term->term_id;
                $scope['taxonomy'] = $term->taxonomy;
            }
            if ('post' === $target) {
                unset($scope['post_type']);
                $types = $post_type ? [$post_type] : [$row->for_item_type];
                if ('' === $types[0] && 'term' === $source) {
                    $tx = get_taxonomy($term->taxonomy);
                    $types = $tx ? array_intersect($tx->object_type, $pp->getEnabledPostTypes()) : [];
                }
            } else {
                $types = [''];
            }
            if (!$types) {
                continue;
            }
            foreach ($types as $type) {
                if ($type) {
                    $scope['post_type'] = $type;
                }
                if (is_wp_error($this->context($scope))) {
                    continue 2;
                }
            }
            $rules[] = $this->project($row, $scope['object_id']);
            $objects[$scope['object_id']] = true;
        }
        return ['rules' => $rules, 'affected_objects' => count($objects), 'revision' => Access::revision([$input, $rules])];
    }

    public function readExact($input)
    {
        $input = Input::validate($input, $this->schema(false, true));
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
        return ['rules' => array_map(function ($row) use ($input) {
            return $this->project($row, $input['object_id']);
        }, array_values($rows)), 'affected_objects' => count($tree),
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

    private function validateChoice($input, $args)
    {
        require_once PRESSPERMIT_CLASSPATH . '/UI/AgentExceptionsAjax.php';
        $pp = presspermit();
        $source = 'term' === $args['for_item_source'] ? '_term_' : $args['for_item_source'];
        $type = '_term_' === $source ? '_term_' : $args['for_item_type'];
        $operation = $args['operation'];
        $mode = $input['mod_type'];
        $group = 'pp_group' === $input['agent_type'] ? $pp->groups()->getGroup($input['agent_id']) : null;
        $metagroup = $group->metagroup_id ?? '';

        $types = apply_filters('presspermit_append_exception_types',
            $pp->admin()->orderTypes(apply_filters('presspermit_exception_types', $pp->getEnabledPostTypes([], 'object'))));
        if (!isset($types[$type])
            || ('attachment' === $type && 'wp_anon' === $metagroup)
            || ('_term_' === $type && in_array($metagroup, ['wp_anon', 'wp_all'], true))
            || !isset(AgentExceptionsAjax::operationChoices($source, $type, $input['agent_type'], $input['agent_id'])[$operation])
            || !isset(AgentExceptionsAjax::modeChoices($source, $type, $operation, $input['agent_type'], $input['agent_id'])[$mode])) {
            return Input::error();
        }

        $via_type = 'post' === $input['source'] ? $args['for_item_type'] : ($input['taxonomy'] ?? 'pp_group');
        if ('term' === $input['source'] && 'post' === $args['for_item_source']
            && !in_array($via_type, $pp->getEnabledTaxonomies(['object_type' => $args['for_item_type']]), true)) {
            return Input::error();
        }
        $choices = AgentExceptionsAjax::qualificationChoices($source, $type, $operation, $mode);
        $qualification = 'post' === $input['source'] && 'associate' !== $operation ? '' : $via_type;
        $constant = strtoupper($via_type);
        if (!isset($choices[$qualification]) || defined("PP_{$constant}_NO_EXCEPTIONS")
            || defined("PP_NO{$constant}_EXCEPTIONS") || defined("PP_NO_{$constant}_EXCEPTIONS")) {
            return Input::error();
        }

        $via_object = $pp->getTypeObject($input['source'], $via_type);
        $children = in_array($input['assign_for'], ['children', 'both'], true);
        $scope = AgentExceptionsAjax::childScope($via_object, $type, $operation, $mode);
        if (($children && !$scope['shown']) || ($scope['locked'] && $children !== $scope['checked'])) {
            return Input::error();
        }

        if ($args['for_item_status'] && !isset(AgentExceptionsAjax::statusChoices(
            $source, $type, $input['source'], $operation, $mode
        )[$args['for_item_status']])) {
            return Input::error();
        }
        return true;
    }

    public function update($input)
    {
        $input = Input::validate($input, $this->schema(true));
        if (is_wp_error($input)) {
            return $input;
        }
        $scope = array_intersect_key($input, $this->schema(false)['properties']);
        $before = $this->readExact($scope);
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
        if ($input['enabled']) {
            $choice = $this->validateChoice($input, $args);
            if (is_wp_error($choice)) {
                return $choice;
            }
        }
        if (in_array($input['assign_for'], ['children', 'both'], true)) {
            $affected = $this->tree($scope, $args, true);
            if (is_wp_error($affected)) {
                return $affected;
            }
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
            if (is_wp_error($all) || count($all) > 1000) {
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
            $removing = PermissionsUpdate::getExceptionRemovalItems($ids);
            if (!is_array($removing)) {
                return Access::unconfirmed();
            }
            foreach ($removing as $row) {
                $active = PermissionsUpdate::exceptionItemExists($row);
                if (null === $active) {
                    return Access::unconfirmed();
                }
                if (!$active) {
                    continue;
                }
                $object_id = (int) $row->item_id;
                if ('term' === $row->via_item_source) {
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
        $after = $this->readExact($scope);
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

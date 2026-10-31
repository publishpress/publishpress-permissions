<?php
namespace PublishPress\Permissions\Mcp;

use PublishPress\Permissions\DB\PermissionsUpdate;

defined('ABSPATH') || exit;

/** Group deletion validates the complete native permission cascade before writing. */
class GroupDeletion
{
    public function schema($write)
    {
        $fields = ['id' => ['type' => 'integer', 'minimum' => 1]];
        $required = ['id'];
        if ($write) {
            $fields['revision'] = ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'];
            $fields['dry_run'] = ['type' => 'boolean'];
            $required[] = 'revision';
        }
        return Input::schema($fields, $required);
    }

    public function read($input)
    {
        $input = Input::validate($input, $this->schema(false));
        if (is_wp_error($input)) {
            return $input;
        }
        if (Access::networkGroups()) {
            return Access::denied();
        }
        $id = $input['id'];
        if (is_wp_error(Access::group($id, 'pp_delete_groups'))
            || is_wp_error(Access::requireCaps(['pp_manage_permissions', 'pp_manage_members']))) {
            return Access::denied();
        }
        if (Delegation::referenced($id)) {
            return new \WP_Error('presspermit_mcp_group_referenced', __('Remove incoming group delegation rules before deleting this group.', 'press-permit-core'));
        }
        $group = (new Groups())->read(['id' => $id]);
        if (is_wp_error($group)) {
            return $group;
        }
        if (!$group['custom_group']) {
            return Access::denied();
        }
        require_once PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php';
        $rows = PermissionsUpdate::getAgentRemovalItems($id, 'pp_group');
        if (!is_array($rows) || count($rows) > 1000) {
            return Input::error();
        }
        $scopes = [];
        foreach ($rows as $row) {
            $object_id = (int) $row->item_id;
            if (!in_array($row->via_item_source, ['post', 'term', 'pp_group'], true) || !$object_id) {
                return Access::denied();
            }
            if ('term' === $row->via_item_source) {
                $term = get_term_by('term_taxonomy_id', $object_id, $row->via_item_type);
                if (!$term) {
                    return Access::denied();
                }
                $object_id = (int) $term->term_id;
            }
            $scope = ['agent_type' => 'pp_group', 'agent_id' => $id, 'source' => $row->via_item_source,
                'object_id' => $object_id, 'operation' => $row->operation, 'status' => $row->for_item_status,
                'target_source' => $row->for_item_source, 'post_type' => 'post' === $row->for_item_source ? $row->for_item_type : ''];
            if ('term' === $row->via_item_source) {
                $scope['taxonomy'] = $row->via_item_type;
            }
            if ('include' === $row->mod_type && 'post' === $row->for_item_source) {
                $affected = Access::postIds([$row->for_item_type]);
                if (count($affected) > 1000) {
                    return Input::error();
                }
                foreach ($affected as $post) {
                    if (!current_user_can('read_post', $post) || !current_user_can('edit_post', $post)) {
                        return Access::denied();
                    }
                }
            }
            if ('include' === $row->mod_type && 'term' === $row->for_item_source) {
                $affected = Access::termIds($row->for_item_type);
                if (count($affected) > 1000) {
                    return Input::error();
                }
                foreach ($affected as $term) {
                    if (!current_user_can('edit_term', $term)) {
                        return Access::denied();
                    }
                }
            }
            if ('include' === $row->mod_type && 'pp_group' === $row->for_item_source) {
                $checked = Delegation::authorizeChange(['object_id' => $object_id, 'assign_for' => 'item', 'mod_type' => 'include', 'enabled' => false]);
                if (is_wp_error($checked)) {
                    return $checked;
                }
            }
            $key = wp_json_encode($scope);
            if (!isset($scopes[$key])) {
                $state = (new Exceptions())->read($scope);
                if (is_wp_error($state)) {
                    return $state;
                }
                $scopes[$key] = $state['revision'];
            }
        }
        $extra = apply_filters('presspermit_mcp_group_deletion_scope', [], $id);
        if (is_wp_error($extra)) {
            return $extra;
        }
        return ['id' => $id, 'name' => $group['name'], 'member_count' => count($group['members']),
            'permission_count' => count($rows), 'role_count' => count(presspermit()->getRoles($id, 'pp_group')),
            'revision' => Access::revision([$group, $rows, $scopes, $extra])];
    }

    public function delete($input)
    {
        $input = Input::validate($input, $this->schema(true));
        if (is_wp_error($input)) {
            return $input;
        }
        $before = $this->read(['id' => $input['id']]);
        if (is_wp_error($before)) {
            return $before;
        }
        $stale = Access::stale($input['revision'], $before['revision']);
        if (is_wp_error($stale)) {
            return $stale;
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true] + $before;
        }
        presspermit()->groups()->deleteGroup($input['id'], 'pp_group');
        do_action('presspermit_trigger_cache_flush');
        $groups = presspermit()->groups()->getGroups('pp_group', ['ids' => [$input['id']]]);
        if ($groups || PermissionsUpdate::getAgentRemovalItems($input['id'], 'pp_group')
            || presspermit()->getRoles($input['id'], 'pp_group')
            || presspermit()->groups()->getGroupMembers($input['id'], 'pp_group', 'count', ['status' => 'any'])) {
            return Access::unconfirmed();
        }
        return ['id' => $input['id'], 'deleted' => true];
    }
}

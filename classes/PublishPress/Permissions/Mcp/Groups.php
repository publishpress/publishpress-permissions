<?php
namespace PublishPress\Permissions\Mcp;

use PublishPress\Permissions\DB\GroupUpdate;

\defined('ABSPATH') || exit;

/** Native custom groups and explicit membership patches; never exposes WP_User internals. */
class Groups
{
    public function schema($operation)
    {
        $id = ['type' => 'integer', 'minimum' => 1];
        if ('list' === $operation) {
            return Input::schema(['page' => $id, 'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'search' => ['type' => 'string', 'maxLength' => 100]]);
        }
        $fields = ['id' => $id];
        $required = ['id'];
        if (in_array($operation, ['create', 'update'], true)) {
            $fields['name'] = ['type' => 'string', 'minLength' => 1, 'maxLength' => 255];
            $fields['description'] = ['type' => 'string', 'maxLength' => 4000];
            if ('create' === $operation) {
                unset($fields['id']);
                $required = ['name'];
            }
        }
        if ('members' === $operation) {
            $fields['user_ids'] = ['type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => $id];
            $fields['action'] = ['type' => 'string', 'enum' => ['add', 'remove']];
            $required = ['id', 'user_ids', 'action'];
        }
        if (!in_array($operation, ['list', 'read'], true)) {
            $fields['dry_run'] = ['type' => 'boolean'];
            if ('create' !== $operation) {
                $fields['revision'] = ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'];
                $required[] = 'revision';
            }
        }
        return Input::schema($fields, $required);
    }

    public function listing($input)
    {
        $input = Input::validate($input, $this->schema('list'));
        if (is_wp_error($input)) {
            return $input;
        }
        if (is_wp_error(Access::requireCaps(['pp_edit_groups']))) {
            return Access::denied();
        }
        $size = $input['per_page'] ?? 50;
        $page = $input['page'] ?? 1;
        if ($page > 10000) {
            return Input::error();
        }
        $native = presspermit()->groups()->getGroups('pp_group', ['search' => $input['search'] ?? '',
            'limit' => $size, 'offset' => ($page - 1) * $size]);
        $rows = [];
        foreach ($native as $id => $row) {
            if (!is_wp_error(Access::group((int) $id))) {
                $rows[] = $this->project($row);
            }
        }
        return ['groups' => $rows, 'page' => $page, 'per_page' => $size];
    }

    private function project($row)
    {
        return ['id' => (int) $row->ID, 'name' => $row->name, 'description' => $row->group_description,
            'metagroup_type' => $row->metagroup_type, 'metagroup_id' => $row->metagroup_id,
            'custom_group' => !$row->metagroup_type && !$row->metagroup_id];
    }

    public function read($input)
    {
        $input = Input::validate($input, $this->schema('read'));
        if (is_wp_error($input)) {
            return $input;
        }
        $id = $input['id'];
        if (is_wp_error(Access::group($id)) || !current_user_can('list_users')) {
            return Access::denied();
        }
        $rows = presspermit()->groups()->getGroups('pp_group', ['ids' => [$id]]);
        if (empty($rows[$id])) {
            return Input::error();
        }
        $count = presspermit()->groups()->getGroupMembers($id, 'pp_group', 'count', ['status' => 'any']);
        if ($count > 1000) {
            return new \WP_Error('presspermit_mcp_scope_limit', __('Use native administration for groups with more than 1,000 members.', 'press-permit-core'));
        }
        $members = [];
        foreach (presspermit()->groups()->getGroupMembers($id, 'pp_group', 'all', ['status' => 'any']) as $member) {
            $user = (int) $member->user_id;
            if (!current_user_can('edit_user', $user)) {
                return Access::denied();
            }
            $members[] = ['user_id' => $user, 'display_name' => $member->display_name, 'status' => $member->status,
                'date_limited' => (bool) $member->date_limited, 'start_date_gmt' => $member->start_date_gmt, 'end_date_gmt' => $member->end_date_gmt];
        }
        $result = $this->project($rows[$id]) + ['members' => $members];
        $result['revision'] = Access::revision([$result, presspermit()->getRoles($id, 'pp_group')]);
        return $result;
    }

    public function create($input)
    {
        $input = Input::validate($input, $this->schema('create'));
        if (is_wp_error($input)) {
            return $input;
        }
        if (is_wp_error(Access::requireCaps(['pp_create_groups', 'pp_edit_groups', 'list_users']))) {
            return Access::denied();
        }
        $name = sanitize_text_field($input['name']);
        $description = sanitize_textarea_field($input['description'] ?? '');
        require_once PRESSPERMIT_CLASSPATH . '/DB/GroupUpdate.php';
        if ('' === trim($name) || !GroupUpdate::groupNameAvailable(wp_slash($name))) {
            return Input::error();
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'name' => $name, 'description' => $description];
        }
        $id = presspermit()->groups()->createGroup(['group_name' => wp_slash($name), 'group_description' => wp_slash($description)]);
        do_action('presspermit_trigger_cache_flush');
        return $id ? $this->read(['id' => (int) $id]) : Access::unconfirmed();
    }

    public function update($input)
    {
        $input = Input::validate($input, $this->schema('update'));
        if (is_wp_error($input)) {
            return $input;
        }
        $before = $this->read(['id' => $input['id']]);
        if (is_wp_error($before)) {
            return $before;
        }
        if (!$before['custom_group'] || (!isset($input['name']) && !isset($input['description']))) {
            return Input::error();
        }
        $stale = Access::stale($input['revision'], $before['revision']);
        if (is_wp_error($stale)) {
            return $stale;
        }
        $name = sanitize_text_field($input['name'] ?? $before['name']);
        $description = sanitize_textarea_field($input['description'] ?? $before['description']);
        require_once PRESSPERMIT_CLASSPATH . '/DB/GroupUpdate.php';
        if ('' === trim($name) || ($name !== $before['name'] && !GroupUpdate::groupNameAvailable(wp_slash($name)))) {
            return Input::error();
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'name' => $name, 'description' => $description];
        }
        GroupUpdate::updateGroup($input['id'], ['group_name' => wp_slash($name), 'group_description' => wp_slash($description)]);
        do_action('presspermit_trigger_cache_flush');
        $after = $this->read(['id' => $input['id']]);
        return !is_wp_error($after) && $after['name'] === $name && $after['description'] === $description ? $after : Access::unconfirmed();
    }

    public function members($input)
    {
        $input = Input::validate($input, $this->schema('members'));
        if (is_wp_error($input)) {
            return $input;
        }
        if (Access::networkGroups()) {
            return new \WP_Error('presspermit_mcp_network_scope', __('Use network administration for network-wide group membership changes.', 'press-permit-core'));
        }
        $id = $input['id'];
        $before = $this->read(['id' => $id]);
        if (is_wp_error($before)) {
            return $before;
        }
        if (!$before['custom_group'] || is_wp_error(Access::group($id, 'pp_manage_members'))) {
            return Access::denied();
        }
        $stale = Access::stale($input['revision'], $before['revision']);
        if (is_wp_error($stale)) {
            return $stale;
        }
        foreach (array_unique($input['user_ids']) as $user) {
            if (!get_userdata($user) || !current_user_can('edit_user', $user)) {
                return Access::denied();
            }
        }
        if ('add' === $input['action']) {
            foreach (array_keys(presspermit()->getRoles($id, 'pp_group')) as $role) {
                if (!Access::roleAllowed($role)) {
                    return Access::denied();
                }
            }
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'id' => $id, 'action' => $input['action'], 'user_ids' => $input['user_ids']];
        }
        if ('add' === $input['action']) {
            $new_users = array_values(array_diff($input['user_ids'], array_column($before['members'], 'user_id')));
            if ($new_users) {
                presspermit()->groups()->addGroupUser($id, $new_users);
            }
        } else {
            presspermit()->groups()->removeGroupUser($id, $input['user_ids']);
        }
        do_action('presspermit_trigger_cache_flush');
        $after = $this->read(['id' => $id]);
        if (is_wp_error($after)) {
            return Access::unconfirmed();
        }
        $present = array_column($after['members'], 'user_id');
        foreach ($input['user_ids'] as $user) {
            if (in_array($user, $present, true) !== ('add' === $input['action'])) {
                return Access::unconfirmed();
            }
        }
        return $after;
    }
}

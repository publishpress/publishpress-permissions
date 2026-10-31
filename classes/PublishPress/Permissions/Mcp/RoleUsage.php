<?php
namespace PublishPress\Permissions\Mcp;

use PublishPress\Permissions\DB\PermissionsUpdate;

defined('ABSPATH') || exit;

/** Native Role Usage controls with full existing-assignment preflight. */
class RoleUsage
{
    public function schema($write)
    {
        if (!$write) {
            return Input::schema([]);
        }
        return Input::schema(['changes' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 100,
            'items' => Input::schema(['role' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100],
                'usage' => ['type' => 'string', 'enum' => ['0', 'pattern', 'direct']]], ['role', 'usage'])],
            'revision' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'], 'dry_run' => ['type' => 'boolean']], ['changes', 'revision']);
    }

    public function read($input)
    {
        $input = Input::validate($input, $this->schema(false));
        if (is_wp_error($input)) {
            return $input;
        }
        if (!presspermit()->moduleActive('collaboration') || is_wp_error(Access::requireCaps(['pp_manage_settings', 'pp_manage_permissions']))) {
            return Access::denied();
        }
        require_once PRESSPERMIT_COLLAB_CLASSPATH . '/UI/RoleUsageQuery.php';
        presspermit()->refreshOptions();
        $names = array_unique(array_merge(array_keys(presspermit()->role_defs->pattern_roles), array_keys(wp_roles()->roles)));
        $rows = [];
        foreach ($names as $role) {
            if ('administrator' !== $role) {
                $rows[] = ['role' => $role, 'label' => wp_roles()->role_names[$role] ?? $role,
                    'usage' => (string) (\PublishPress\Permissions\Collab\UI\RoleUsageQuery::get_role_usage($role) ?: '0')];
            }
        }
        return ['roles' => $rows, 'writable' => (bool) presspermit()->getOption('advanced_options'), 'revision' => Access::revision([$rows, (new Settings())->snapshot('role_usage'), wp_roles()->roles])];
    }

    public function update($input)
    {
        $input = Input::validate($input, $this->schema(true));
        if (is_wp_error($input)) {
            return $input;
        }
        $before = $this->read([]);
        if (is_wp_error($before)) {
            return $before;
        }
        $stale = Access::stale($input['revision'], $before['revision']);
        if (is_wp_error($stale)) {
            return $stale;
        }
        if (!$before['writable']) {
            return new \WP_Error('presspermit_mcp_configuration_locked', __('Enable native Advanced Settings before changing Role Usage.', 'press-permit-core'));
        }
        $known = array_column($before['roles'], 'usage', 'role');
        $usage = presspermit()->getOption('role_usage');
        if (!is_array($usage)) {
            $usage = array_fill_keys(array_keys(presspermit()->role_defs->pattern_roles), 'pattern')
                + array_fill_keys(array_keys(presspermit()->role_defs->direct_roles), 'direct');
        }
        require_once PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php';
        $seen = [];
        foreach ($input['changes'] as $change) {
            $role = $change['role'];
            if (!isset($known[$role]) || isset($seen[$role])) {
                return Input::error();
            }
            if ('0' !== $change['usage'] && !Access::roleAllowed($role)) {
                return new \WP_Error('presspermit_mcp_role_grant_denied', __('The native role capabilities cannot be granted by this session.', 'press-permit-core'));
            }
            $seen[$role] = true;
            $assignments = PermissionsUpdate::getUsageAssignments($role);
            if (!is_array($assignments) || count($assignments) > 1000) {
                return Input::error();
            }
            foreach ($assignments as $assignment) {
                $state = (new Roles())->read(['agent_type' => $assignment->agent_type, 'agent_id' => (int) $assignment->agent_id]);
                if (is_wp_error($state)) {
                    return $state;
                }
                // Changing an assigned role's interpretation requires reviewing its native grants.
                if ($known[$role] !== $change['usage'] && '0' !== $change['usage']) {
                    return new \WP_Error('presspermit_mcp_usage_assigned', __('Remove existing supplemental assignments before changing this role interpretation.', 'press-permit-core'));
                }
            }
            $usage[$role] = $change['usage'];
        }
        $candidate = presspermit()->site_options;
        $candidate['presspermit_role_usage'] = $usage;
        $filtered = apply_filters('presspermit_options', $candidate);
        if (($filtered['presspermit_role_usage'] ?? null) !== $usage) {
            return new \WP_Error('presspermit_mcp_configuration_locked', __('Native configuration controls Role Usage.', 'press-permit-core'));
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'changes' => $input['changes']];
        }
        presspermit()->updateRoleUsage($usage);
        return (new Settings())->snapshot('role_usage')['value'] === $usage ? $this->read([]) : Access::unconfirmed();
    }
}

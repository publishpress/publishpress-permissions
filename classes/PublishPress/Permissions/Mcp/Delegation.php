<?php
namespace PublishPress\Permissions\Mcp;

\defined('ABSPATH') || exit;

/** Native custom-group management delegation and its inverse scope. */
class Delegation
{
    private static function local()
    {
        global $wpdb;
        return !Access::networkGroups() && apply_filters('presspermit_use_groups_table', $wpdb->pp_groups, 'pp_group') === $wpdb->pp_groups;
    }

    public static function ids()
    {
        global $wpdb;
        if (!self::local()) {
            return Access::denied();
        }
        $ids = $wpdb->get_col("SELECT ID FROM $wpdb->pp_groups ORDER BY ID LIMIT 1001");
        return $wpdb->last_error ? Access::unconfirmed() : array_map('intval', $ids);
    }

    public static function target($id, $custom_only = true)
    {
        if (!presspermit()->moduleActive('collaboration') || !presspermit()->getOption('display_group_exceptions')
            || !self::local() || is_wp_error(Access::requireCaps(['pp_manage_permissions', 'pp_edit_groups', 'pp_manage_members', 'pp_delete_groups', 'list_users']))
            || is_wp_error(Access::group($id, 'pp_manage_members')) || is_wp_error(Access::group($id, 'pp_delete_groups'))) {
            return Access::denied();
        }
        $group = (new Groups())->read(['id' => $id]);
        if (is_wp_error($group) || ($custom_only && !$group['custom_group'])) {
            return Access::denied();
        }
        return $group;
    }

    public static function authorizeChange($input)
    {
        if ('item' !== $input['assign_for']) {
            return Input::error();
        }
        $ids = 'include' === $input['mod_type'] ? self::ids() : [$input['object_id']];
        if (is_wp_error($ids) || count($ids) > 1000) {
            return is_wp_error($ids) ? $ids : Input::error();
        }
        foreach ($ids as $id) {
            $target = self::target($id, false);
            if (is_wp_error($target)) {
                return $target;
            }
            // Delegating management must not provide a route to privileged group roles.
            if (!(($input['enabled'] && 'exclude' === $input['mod_type']) || (!$input['enabled'] && 'additional' === $input['mod_type']))) {
                $checked = self::grantScope($id);
                if (is_wp_error($checked)) {
                    return $checked;
                }
                foreach (array_keys(presspermit()->getRoles($id, 'pp_group')) as $role) {
                    if (!Access::roleAllowed($role)) {
                        return Access::denied();
                    }
                }
            }
        }
        return true;
    }

    /** Authorize the existing content access that a new group manager could redistribute. */
    private static function grantScope($id)
    {
        require_once PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php';
        $rows = \PublishPress\Permissions\DB\PermissionsUpdate::getAgentRemovalItems($id, 'pp_group');
        if (!is_array($rows) || count($rows) > 1000) {
            return Access::unconfirmed();
        }
        foreach ($rows as $row) {
            // Transitive delegation graphs require native administration.
            if (!in_array($row->via_item_source, ['post', 'term'], true)) {
                return Access::denied();
            }
            $object_id = (int) $row->item_id;
            if ('term' === $row->via_item_source) {
                $term = get_term_by('term_taxonomy_id', $object_id, $row->via_item_type);
                if (!$term) {
                    return Access::denied();
                }
                $object_id = (int) $term->term_id;
            }
            $scope = ['agent_type' => 'pp_group', 'agent_id' => $id, 'source' => $row->via_item_source,
                'object_id' => $object_id, 'operation' => $row->operation, 'status' => $row->for_item_status,
                'target_source' => $row->for_item_source];
            if ('term' === $row->via_item_source) {
                $scope['taxonomy'] = $row->via_item_type;
            }
            if ('post' === $row->for_item_source) {
                $scope['post_type'] = $row->for_item_type;
            }
            $service = new Exceptions();
            $state = $service->read($scope);
            if (is_wp_error($state)) {
                return $state;
            }
            $check = $service->update($scope + ['revision' => $state['revision'], 'mod_type' => $row->mod_type,
                'assign_for' => $row->assign_for ?: 'both', 'enabled' => true, 'dry_run' => true]);
            if (is_wp_error($check)) {
                return $check;
            }
        }
        return true;
    }

    public static function referenced($id)
    {
        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare("SELECT i.eitem_id FROM $wpdb->ppc_exception_items i INNER JOIN $wpdb->ppc_exceptions e ON e.exception_id = i.exception_id WHERE e.via_item_source = %s AND i.item_id = %d LIMIT 1", 'pp_group', $id));
        return $wpdb->last_error || $value;
    }
}

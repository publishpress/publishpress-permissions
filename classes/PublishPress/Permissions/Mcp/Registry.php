<?php
namespace PublishPress\Permissions\Mcp;

\defined('ABSPATH') || exit;

/** Private Abilities API registration; Hub supplies transport and action selection. */
class Registry
{
    public static function category()
    {
        if (function_exists('wp_has_ability_category') && !wp_has_ability_category('publishpress')) {
            wp_register_ability_category('publishpress', ['label' => __('PublishPress', 'press-permit-core'),
                'description' => __('PublishPress plugin actions.', 'press-permit-core')]);
        }
    }

    public static function register()
    {
        if (!function_exists('wp_register_ability') || !function_exists('presspermit')) {
            return;
        }
        $registry = new self();
        $groups = new Groups();
        $settings = new Settings();
        $roles = new Roles();
        $registry->action('list-features', __('Discover Permissions features', 'press-permit-core'),
            __('Read active native modules, supported operations and permission requirements. Discovery does not grant access. Credentials and administrator-level grants are not exposed.', 'press-permit-core'),
            Input::schema([]), [$registry, 'features'], ['pp_manage_settings'], true);
        foreach (['get-settings' => ['read', false], 'update-settings' => ['update', true]] as $name => $operation) {
            $registry->action($name, __('Permissions settings', 'press-permit-core'),
                __('Read native setting identifiers, exact JSON types, defaults, stored values and revision before patching. Checkbox inputs must be JSON booleans. Omitted options remain unchanged; unknown fields and locked settings are rejected. dry_run validates without saving.', 'press-permit-core'),
                $settings->schema($operation[1]), [$settings, $operation[0]], ['pp_manage_settings'], !$operation[1]);
        }
        $types = new ContentTypes();
        foreach (['get-content-types' => false, 'set-content-types' => true] as $name => $write) {
            $registry->action($name, __('Permissions post types and taxonomies', 'press-permit-core'),
                __('Read native General-tab filtering choices and locks. Patch explicit {name, enabled} rows with the current revision. Checkbox values must be JSON booleans. Omitted and temporarily unregistered types remain unchanged. Locked controls cannot be changed. dry_run saves nothing.', 'press-permit-core'),
                $types->schema($write), [$types, $write ? 'update' : 'read'], ['pp_manage_settings'], !$write);
        }
        $operations = [
            'list-groups' => ['list', 'listing', ['pp_edit_groups'], true],
            'get-group' => ['read', 'read', ['pp_edit_groups', 'list_users'], true],
            'create-group' => ['create', 'create', ['pp_create_groups', 'pp_edit_groups', 'list_users'], false],
            'update-group' => ['update', 'update', ['pp_edit_groups', 'list_users'], false],
            'update-group-members' => ['members', 'members', ['pp_edit_groups', 'pp_manage_members', 'list_users'], false],
        ];
        foreach ($operations as $name => $operation) {
            $registry->action($name, __('Permissions groups and members', 'press-permit-core'),
                __('Manage native custom permission groups. Lists are paginated; reads exclude account passwords and email addresses. Read get-group before updates and supply its revision. Membership actions explicitly add or remove user_ids, preserve other members and existing schedules, and require edit_user for every affected user. Built-in role/metagroups cannot be renamed or joined. dry_run saves nothing.', 'press-permit-core'),
                $groups->schema($operation[0]), [$groups, $operation[1]], $operation[2], $operation[3], 'create-group' !== $name);
        }
        foreach (['get-extra-roles' => false, 'update-extra-roles' => true] as $name => $write) {
            $registry->action($name, __('Permissions supplemental roles', 'press-permit-core'),
                __('Read supplemental roles for a user, custom group or WordPress role metagroup. Changes are explicit {role, enabled} patches with a current revision; other roles remain unchanged. Direct grants use a discovered native direct_roles name, require promote_users and reject administrator-level capabilities; other grants use a discovered pattern role in base:post:post_type or base:term:taxonomy format; status scopes use base:post:post_type:post_status:status with a native active status. Native object-type permissions apply. Administrator-level capabilities are rejected even for administrator sessions. dry_run saves nothing.', 'press-permit-core'),
                $roles->schema($write), [$roles, $write ? 'update' : 'read'], ['pp_manage_permissions'], !$write);
        }
        $exceptions = new Exceptions();
        foreach (['get-permissions' => false, 'set-permission' => true] as $name => $write) {
            $registry->action($name, __('Permissions for posts and terms', 'press-permit-core'),
                __('Read an agent/object/operation scope and its revision before adding or removing a native permission. source=pp_group with operation=manage and assign_for=item manages native custom-group delegation when Collaboration and Display group permissions are enabled. Group management, membership and deletion capabilities are required, and incoming delegation references must be removed before target-group deletion. source=term uses WordPress term IDs and a taxonomy; post_type selects the affected post type for category-based rules. mod_type is additional, include or exclude; assign_for is item, children or both. Native operation, object and descendant permissions apply. Omitted rules are preserved. Inherited rules must be changed at their origin. dry_run writes nothing. Large scopes use native administration.', 'press-permit-core'),
                $exceptions->schema($write), [$exceptions, $write ? 'update' : 'read'], ['pp_manage_permissions'], !$write);
        }
        foreach (['teaser' => new Teaser(), 'modules' => new Modules()] as $feature => $service) {
            foreach (['get-' . $feature => false, 'update-' . $feature => true] as $name => $write) {
                $caps = 'teaser' === $feature ? ['pp_manage_teaser', 'pp_manage_settings'] : ['pp_manage_settings', 'pp_manage_permissions', 'pp_manage_teaser', 'pp_manage_members'];
                $registry->action($name, 'teaser' === $feature ? __('Permissions teaser settings', 'press-permit-core') : __('Permissions module activation', 'press-permit-core'),
                    'teaser' === $feature
                        ? __('Read native teaser modes, messages, redirects, RSS and notice styles. Omit post_type for global options; pass a discovered filtered post type for per-type settings. Patch exact native option names with JSON types and revision. Rich text uses native sanitization. Redirect destinations require object permissions. dry_run saves nothing.', 'press-permit-core')
                        : __('Discover installed, required and active native modules. Patch explicit module/enabled rows with a current revision. Required modules cannot be disabled. Native lifecycle hooks run; activation changes load on the next request. Network changes require network administration. dry_run saves nothing.', 'press-permit-core'),
                    $service->schema($write), [$service, $write ? 'update' : 'read'], $caps, !$write);
            }
        }
        $deletion = new GroupDeletion();
        foreach (['get-group-deletion' => false, 'delete-group' => true] as $name => $write) {
            $registry->action($name, __('Delete a custom permission group', 'press-permit-core'),
                __('Read get-group-deletion for the full native deletion scope and revision. Only custom groups can be deleted. All members, roles, rules and inherited permission targets are checked before cleanup. dry_run previews without deleting. Unknown or oversized permission scopes require native administration.', 'press-permit-core'),
                $deletion->schema($write), [$deletion, $write ? 'delete' : 'read'], ['pp_delete_groups', 'pp_edit_groups', 'pp_manage_members', 'pp_manage_permissions', 'list_users'], !$write);
        }
        $usage = new RoleUsage();
        foreach (['get-role-usage' => false, 'update-role-usage' => true] as $name => $write) {
            $registry->action($name, __('Permissions Role Usage', 'press-permit-core'),
                __('Read native Role Usage choices: 0 (no supplemental assignment), pattern, or direct. Patch discovered roles with a current revision; omitted roles remain unchanged. Administrator-level roles cannot be enabled. Existing assignments must be removed before changing an assigned role interpretation. dry_run saves nothing.', 'press-permit-core'),
                $usage->schema($write), [$usage, $write ? 'update' : 'read'], ['pp_manage_settings', 'pp_manage_permissions'], !$write);
        }
        $visibility = new Visibility();
        foreach (['get-content-visibility' => false, 'build-content-visibility' => true] as $name => $build) {
            $registry->action($name, __('Permissions content visibility shortcode', 'press-permit-core'),
                __('Discover or build the native pp_restrict shortcode with typed logged-in/out, role, capability, username and group conditions. relation is all or any; hide is a JSON boolean. The builder sanitizes HTML and never saves a post. Use native content-editing tools with object permissions to place its result. Nested visibility shortcodes require native editing.', 'press-permit-core'),
                $visibility->schema($build), [$visibility, $build ? 'build' : 'read'], ['pp_manage_permissions'], true);
        }
        $editor = new EditorOptions();
        foreach (['get-editor-options' => false, 'update-editor-options' => true] as $name => $write) {
            $registry->action($name, __('Permissions editor and screen controls', 'press-permit-core'),
                __('Read native per-post-type Permissions metabox and Permissions-screen switches, including their exact native option names and stored values. Only the native string 0 disables them; absent and legacy values default enabled. Writes require JSON booleans, the current revision, native settings and post-type capabilities. Omitted controls stay unchanged; dry_run saves nothing.', 'press-permit-core'),
                $editor->schema($write), [$editor, $write ? 'update' : 'read'], ['pp_manage_settings'], !$write);
        }
        $bulk = new Bulk();
        foreach (['bulk-set-permissions' => false, 'bulk-update-extra-roles' => true] as $name => $role_batch) {
            $registry->action($name, __('Permissions validated batch', 'press-permit-core'),
                __('Supply up to 25 explicit items, each with its own current revision. Every item is validated and authorized before writes; malformed, denied, stale or overlapping permission scopes reject the whole preview. dry_run writes nothing. Native execution is not transactional: inspect completed, confirmed_items and failed_index and re-read all scopes after a partial failure.', 'press-permit-core'),
                $bulk->schema($role_batch), [$bulk, $role_batch ? 'roles' : 'permissions'], ['pp_manage_permissions'], false, false);
        }
        $registry->action('get-permission-matrix', __('Read explicit permission scopes', 'press-permit-core'),
            __('Read up to 25 explicit authorized post, term or custom-group delegation scopes with their native rules and individual revisions. This does not enumerate private objects or grant access. Results are bounded to 1,000 rules; use the normal single-scope getters or smaller batches for larger inventories.', 'press-permit-core'),
            $bulk->schema(false, true), [$bulk, 'matrix'], ['pp_manage_permissions'], true);
        do_action('presspermit_mcp_register_actions', $registry);
    }

    public function action($name, $label, $description, $schema, $callback, $caps, $read_only, $idempotent = true)
    {
        $name = 'publishpress-permissions/' . $name;
        if (function_exists('wp_has_ability') && wp_has_ability($name)) {
            return;
        }
        wp_register_ability($name, ['label' => $label, 'description' => $description, 'category' => 'publishpress',
            'input_schema' => $schema, 'output_schema' => ['type' => 'object', 'additionalProperties' => true],
            'permission_callback' => static function () use ($caps) {
                return true === Access::requireCaps($caps);
            },
            'execute_callback' => static function ($input = []) use ($schema, $callback, $caps) {
                $valid = Input::validate($input, $schema);
                if (is_wp_error($valid)) {
                    return $valid;
                }
                $allowed = Access::requireCaps($caps);
                if (is_wp_error($allowed)) {
                    return $allowed;
                }
                try {
                    return call_user_func($callback, $valid);
                } catch (\Throwable $error) {
                    return Access::unconfirmed();
                }
            },
            'meta' => ['public' => false, 'show_in_rest' => false, 'mcp' => ['public' => false],
                'annotations' => ['readonly' => $read_only, 'destructive' => !$read_only, 'idempotent' => $read_only || $idempotent]]]);
    }

    public function features($input)
    {
        $valid = Input::validate($input, Input::schema([]));
        if (is_wp_error($valid)) {
            return $valid;
        }
        if (is_wp_error(Access::requireCaps(['pp_manage_settings']))) {
            return Access::denied();
        }
        $pp = presspermit();
        return ['edition' => defined('PRESSPERMIT_PRO_VERSION') ? 'pro' : 'free',
            'active_modules' => array_keys($pp->getActiveModules()),
            'operations' => array_values($pp->getOperations()),
            'features' => apply_filters('presspermit_mcp_features', [
                'settings' => ['reads' => ['get-settings'], 'writes' => ['update-settings'], 'capabilities' => ['pp_manage_settings']],
                'groups' => ['reads' => ['list-groups', 'get-group'], 'writes' => ['create-group', 'update-group', 'update-group-members'],
                    'capabilities' => ['pp_edit_groups', 'pp_manage_members', 'list_users']],
                'teaser' => ['reads' => ['get-teaser'], 'writes' => ['update-teaser'], 'active' => $pp->moduleActive('teaser')],
                'content-visibility' => ['reads' => ['get-content-visibility', 'build-content-visibility'], 'writes' => []],
                'role-usage' => ['reads' => ['get-role-usage'], 'writes' => ['update-role-usage']],
                'modules' => ['reads' => ['get-modules'], 'writes' => ['update-modules']],
                'group-deletion' => ['reads' => ['get-group-deletion'], 'writes' => ['delete-group']],
                'editor-options' => ['reads' => ['get-editor-options'], 'writes' => ['update-editor-options']],
                'filtering' => ['reads' => ['get-content-types'], 'writes' => ['set-content-types'], 'capabilities' => ['pp_manage_settings']],
                'content-permissions' => ['reads' => ['get-permissions', 'get-permission-matrix'], 'writes' => ['set-permission', 'bulk-set-permissions'], 'capabilities' => ['pp_manage_permissions']],
                'group-delegation' => ['reads' => ['get-permissions'], 'writes' => ['set-permission', 'bulk-set-permissions'], 'active' => $pp->moduleActive('collaboration') && (bool) $pp->getOption('display_group_exceptions')],
                'supplemental-roles' => ['reads' => ['get-extra-roles'], 'writes' => ['update-extra-roles', 'bulk-update-extra-roles'], 'capabilities' => ['pp_manage_permissions']],
            ])];
    }
}

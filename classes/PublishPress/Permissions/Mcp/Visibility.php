<?php
namespace PublishPress\Permissions\Mcp;

\defined('ABSPATH') || exit;

/** Typed authoring of the native visibility shortcode; saving content uses native post tools. */
class Visibility
{
    private function conditions()
    {
        $list = ['type' => 'array', 'maxItems' => 100, 'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100]];
        return Input::schema(['logged' => ['type' => 'string', 'enum' => ['in', 'out']], 'roles' => $list,
            'capabilities' => $list, 'usernames' => $list, 'groups' => ['type' => 'array', 'maxItems' => 100, 'items' => ['type' => 'integer', 'minimum' => 1]],
            'relation' => ['type' => 'string', 'enum' => ['all', 'any']], 'hide' => ['type' => 'boolean']]);
    }

    public function schema($build)
    {
        return $build ? Input::schema(['conditions' => $this->conditions(), 'content' => ['type' => 'string', 'maxLength' => 100000]], ['conditions', 'content']) : Input::schema([]);
    }

    public function read($input)
    {
        $input = Input::validate($input, $this->schema(false));
        if (is_wp_error($input)) {
            return $input;
        }
        if (!presspermit()->moduleActive('content-visibility') || is_wp_error(Access::requireCaps(['pp_manage_permissions']))) {
            return Access::denied();
        }
        return ['shortcode' => \PublishPress\Permissions\ContentVisibility::SHORTCODE,
            'conditions' => $this->conditions(), 'native_defaults' => (object) \PublishPress\Permissions\ContentVisibility::getShortcodeDefaults(),
            'roles' => array_keys(wp_roles()->roles)];
    }

    public function build($input)
    {
        $input = Input::validate($input, $this->schema(true));
        if (is_wp_error($input)) {
            return $input;
        }
        $state = $this->read([]);
        if (is_wp_error($state)) {
            return $state;
        }
        $conditions = $input['conditions'];
        if (!array_filter(array_intersect_key($conditions, array_flip(['logged', 'roles', 'capabilities', 'usernames', 'groups'])))
            || preg_match('/\[\/?pp_restrict(?:\s|\])/i', $input['content'])) {
            return Input::error();
        }
        foreach ($conditions['roles'] ?? [] as $role) {
            if (!get_role($role)) {
                return Input::error();
            }
        }
        foreach ($conditions['capabilities'] ?? [] as $cap) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $cap) || !current_user_can($cap)) {
                return Access::denied();
            }
        }
        foreach ($conditions['usernames'] ?? [] as $name) {
            $user = get_user_by('login', $name);
            if (!$user || !current_user_can('list_users') || !current_user_can('edit_user', $user->ID)) {
                return Access::denied();
            }
        }
        foreach ($conditions['groups'] ?? [] as $id) {
            $group = (new Groups())->read(['id' => $id]);
            if (is_wp_error($group)) {
                return $group;
            }
        }
        $attributes = [];
        foreach ($conditions as $key => $value) {
            if (is_array($value)) {
                $value = implode(',', $value);
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            if ('' !== $value) {
                $attributes[] = $key . '="' . esc_attr($value) . '"';
            }
        }
        return ['shortcode' => '[' . $state['shortcode'] . ' ' . implode(' ', $attributes) . ']'
            . wp_kses_post($input['content']) . '[/' . $state['shortcode'] . ']', 'saved' => false];
    }
}

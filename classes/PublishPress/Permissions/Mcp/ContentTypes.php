<?php
namespace PublishPress\Permissions\Mcp;

\defined('ABSPATH') || exit;

/** Native General-tab post-type and taxonomy filtering controls. */
class ContentTypes
{
    public function schema($write)
    {
        $fields = ['scope' => ['type' => 'string', 'enum' => ['post_types', 'taxonomies']]];
        $required = ['scope'];
        if ($write) {
            $fields['changes'] = ['type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => Input::schema([
                'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100], 'enabled' => ['type' => 'boolean']], ['name', 'enabled'])];
            $fields['revision'] = ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'];
            $fields['dry_run'] = ['type' => 'boolean'];
            $required = array_merge($required, ['changes', 'revision']);
        }
        return Input::schema($fields, $required);
    }

    private function choices($scope)
    {
        require_once PRESSPERMIT_CLASSPATH . '/UI/SettingsTabCore.php';
        return (new \PublishPress\Permissions\UI\SettingsTabCore(false))->getFilteringChoices('taxonomies' === $scope ? 'term' : 'post');
    }

    private function effective($option, array $values)
    {
        $candidate = presspermit()->site_options;
        $candidate['presspermit_' . $option] = $values;
        $filtered = apply_filters('presspermit_options', $candidate);
        $values = $filtered['presspermit_' . $option] ?? [];
        $hook = 'enabled_taxonomies' === $option ? 'presspermit_enabled_taxonomies_by_key' : 'presspermit_enabled_post_types';
        return (array) apply_filters($hook, $values);
    }
    public function read($input)
    {
        $input = Input::validate($input, $this->schema(false));
        if (is_wp_error($input)) {
            return $input;
        }
        $option = 'enabled_' . $input['scope'];
        if (is_wp_error(Access::requireCaps(['pp_manage_settings']))
            || (in_array($option, presspermit()->netwide_options, true) && !current_user_can('manage_network_options'))) {
            return Access::denied();
        }
        presspermit()->refreshOptions();
        $choices = $this->choices($input['scope']);
        $values = presspermit()->getOption($option);
        if (!is_array($values)) {
            return Input::error();
        }
        $filter = 'taxonomies' === $input['scope'] ? 'presspermit_enabled_taxonomies_by_key' : 'presspermit_enabled_post_types';
        $effective = apply_filters($filter, $values);
        $rows = [];
        foreach ($choices['types'] as $name => $object) {
            $enabled = !empty($effective[$name]);
            $locked = !empty($choices['locked_types'][$name]) || isset($choices['hidden_types'][$name]);
            $probe = $values;
            $probe[$name] = $enabled ? '0' : '1';
            $forced = $this->effective($option, $probe);
            $locked = $locked || (!empty($forced[$name]) === $enabled);
            $rows[] = ['name' => $name, 'label' => is_object($object) ? $object->labels->name : $name,
                'enabled' => $enabled, 'locked' => $locked];
        }
        $snapshot = (new Settings())->snapshot($option);
        return ['scope' => $input['scope'], 'types' => $rows, 'revision' => Access::revision([$rows, $snapshot])];
    }

    public function update($input)
    {
        $input = Input::validate($input, $this->schema(true));
        if (is_wp_error($input)) {
            return $input;
        }
        $before = $this->read(['scope' => $input['scope']]);
        if (is_wp_error($before)) {
            return $before;
        }
        $stale = Access::stale($input['revision'], $before['revision']);
        if (is_wp_error($stale)) {
            return $stale;
        }
        $choices = array_column($before['types'], null, 'name');
        $changes = [];
        foreach ($input['changes'] as $row) {
            if (isset($changes[$row['name']]) || !isset($choices[$row['name']])) {
                return Input::error();
            }
            if ($choices[$row['name']]['locked']) {
                return new \WP_Error('presspermit_mcp_configuration_locked', __('The native control is locked.', 'press-permit-core'));
            }
            $changes[$row['name']] = $row['enabled'] ? '1' : '0';
        }
        $option = 'enabled_' . $input['scope'];
        $pp = presspermit();
        $snapshot = (new Settings())->snapshot($option);
        $stored = $snapshot['exists'] ? $snapshot['value'] : [];
        if (!is_array($stored)) {
            return Input::error();
        }
        $values = array_merge((array) ($pp->default_options[$option] ?? []), $stored, $changes);
        $effective = $this->effective($option, $values);
        foreach ($changes as $name => $value) {
            if (!empty($effective[$name]) !== ('1' === $value)) {
                return new \WP_Error('presspermit_mcp_configuration_locked', __('This filtering choice is controlled by native configuration.', 'press-permit-core'));
            }
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'changes' => (object) $changes, 'revision' => $before['revision']];
        }
        $pp->updateOption($option, $values);
        $snapshot = (new Settings())->snapshot($option);
        if (!$snapshot['exists'] || $snapshot['value'] !== $values) {
            return Access::unconfirmed();
        }
        $pp->refreshOptions();
        do_action('presspermit_trigger_cache_flush');
        return $this->read(['scope' => $input['scope']]);
    }
}

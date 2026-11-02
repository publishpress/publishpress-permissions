<?php
namespace PublishPress\Permissions\Mcp;

\defined('ABSPATH') || exit;

/** Shared typed patches for native scalar options and per-post-type option maps. */
abstract class NativeOptions
{
    abstract protected function definitions($scope);
    abstract protected function authorize($scope);

    protected function optionName($name, $scope)
    {
        return $name;
    }
    public function schema($write)
    {
        $fields = ['post_type' => ['type' => 'string', 'maxLength' => 100]];
        $required = [];
        if ($write) {
            $properties = [];
            foreach ($this->definitions('') + $this->definitions('post') as $name => $definition) {
                $properties[$name] = $definition['schema'];
            }
            $fields['changes'] = Input::schema($properties);
            $fields['revision'] = ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'];
            $fields['dry_run'] = ['type' => 'boolean'];
            $required = ['changes', 'revision'];
        }
        return Input::schema($fields, $required);
    }

    protected function normalize($name, $value, $definition)
    {
        if (!empty($definition['rich'])) {
            $value = \PublishPress\Permissions\TeaserHooks::sanitizeTeaserRichText($value);
            if (\PublishPress\Permissions\TeaserHooks::isDefaultTeaserText($value)) {
                $value = '';
            }
            return wp_slash($value);
        }
        if ('boolean' === $definition['schema']['type']) {
            return $value ? '1' : ($definition['false'] ?? '0');
        }
        if (is_array($value)) {
            return $value;
        }
        return sanitize_text_field((string) $value);
    }

    protected function checkPatch($scope, $values, $changes)
    {
        return true;
    }

    private function decode($value, $definition)
    {
        $schema = $definition['schema'];
        if (!empty($definition['explicit_zero'])) {
            return '0' !== $value;
        }
        if (null === $value
            || ('integer' === $schema['type'] && in_array($value, ['', false], true))
            || ('string' === $schema['type'] && (false === $value || ('' === $value && isset($schema['enum']) && !in_array('', $schema['enum'], true))))
            || (!empty($definition['empty_default']) && in_array($value, ['', false], true))) {
            $value = $definition['default'];
        }
        if ('boolean' === $schema['type']) {
            return Input::from_native($value, $schema);
        }
        if ('integer' === $schema['type'] && is_string($value) && preg_match('/^[0-9]+$/D', $value)) {
            $value = (int) $value;
        }
        if ('string' === $schema['type'] && is_int($value)) {
            $value = (string) $value;
        }
        if ('array' === $schema['type'] && !is_array($value)) {
            $value = $value ? [$value] : [];
        }
        return is_wp_error(Input::validate($value, $schema)) ? Input::error() : $value;
    }

    protected function writable($name, $native, $scope = null)
    {
        $pp = presspermit();
        if (isset($pp->default_advanced_options[$name]) && !$pp->getOption('advanced_options')) {
            return false;
        }
        $candidate = $pp->site_options;
        $candidate['presspermit_' . $name] = $native;
        $filtered = apply_filters('presspermit_options', $candidate);
        if (!array_key_exists('presspermit_' . $name, $filtered)) {
            return false;
        }
        $effective = $filtered['presspermit_' . $name];
        if (null !== $scope && is_array($native) && is_array($effective)) {
            return ($effective[$scope] ?? $effective[''] ?? null) === ($native[$scope] ?? $native[''] ?? null);
        }
        return $effective === $native;
    }

    public function read($input)
    {
        $input = Input::validate($input, $this->schema(false));
        if (is_wp_error($input)) {
            return $input;
        }
        $scope = $input['post_type'] ?? '';
        $allowed = $this->authorize($scope);
        if (is_wp_error($allowed)) {
            return $allowed;
        }
        presspermit()->refreshOptions();
        $rows = $snapshots = [];
        foreach ($this->definitions($scope) as $name => $definition) {
            $option = $this->optionName($name, $scope);
            $mapped = '' !== $scope && empty($definition['scalar']);
            if (is_multisite() && in_array($option, presspermit()->netwide_options, true)
                && !current_user_can('manage_network_options')) {
                return Access::denied();
            }
            $snapshot = (new Settings())->snapshot($option);
            $snapshots[$name] = $snapshot;
            $native = presspermit()->getOption($option);
            $raw = $mapped ? (is_array($native) ? ($native[$scope] ?? $native[''] ?? null) : null) : $native;
            if ($mapped && !empty($definition['rich']) && is_string($raw)) {
                $raw = wp_unslash($raw);
            }
            $value = $this->decode($raw, $definition);
            if (is_wp_error($value)) {
                return $value;
            }
            $writable = $this->writable($option, $native, $mapped ? $scope : null);
            if ('boolean' === $definition['schema']['type']) {
                $probe = $this->normalize($name, !$value, $definition);
                if ($mapped) {
                    $probe = array_replace((array) $native, [$scope => $probe]);
                }
                $writable = $writable && $this->writable($option, $probe, $mapped ? $scope : null);
            }
            $rows[$name] = ['label' => wp_strip_all_tags($definition['label'] ?? $name), 'schema' => $definition['schema'],
                'default' => $definition['default'], 'value' => $value,
                'native_option' => $option, 'stored_value' => $mapped ? ($snapshot['value'][$scope] ?? null) : $snapshot['value'],
                'writable' => $writable];
        }
        return ['post_type' => $scope, 'settings' => (object) $rows,
            'revision' => Access::revision([$scope, $rows, $snapshots])];
    }

    public function update($input)
    {
        $input = Input::validate($input, $this->schema(true));
        if (is_wp_error($input) || !$input['changes']) {
            return is_wp_error($input) ? $input : Input::error();
        }
        $scope = $input['post_type'] ?? '';
        $before = $this->read(['post_type' => $scope]);
        if (is_wp_error($before)) {
            return $before;
        }
        $stale = Access::stale($input['revision'], $before['revision']);
        if (is_wp_error($stale)) {
            return $stale;
        }
        $definitions = $this->definitions($scope);
        $native = $values = [];
        foreach ((array) $before['settings'] as $name => $row) {
            $values[$name] = $row['value'];
        }
        foreach ($input['changes'] as $name => $value) {
            if (!isset($definitions[$name]) || is_wp_error(Input::validate($value, $definitions[$name]['schema']))) {
                return Input::error();
            }
            $option = $this->optionName($name, $scope);
            $values[$name] = $value;
            $sanitized = $this->normalize($name, $value, $definitions[$name]);
            if (is_wp_error($sanitized)) {
                return $sanitized;
            }
            if ('' !== $scope && empty($definitions[$name]['scalar'])) {
                $snapshot = (new Settings())->snapshot($option);
                $existing = $snapshot['exists'] ? $snapshot['value'] : presspermit()->getOption($option);
                if ($existing && !is_array($existing)) {
                    return Input::error();
                }
                $sanitized = array_replace((array) $existing, [$scope => $sanitized]);
            }
            if (!$before['settings']->{$name}['writable'] || !$this->writable($option, $sanitized, '' !== $scope && empty($definitions[$name]['scalar']) ? $scope : null)) {
                return new \WP_Error('presspermit_mcp_configuration_locked', __('This native setting is controlled by configuration:', 'press-permit-core') . ' ' . $name);
            }
            $native[$option] = $sanitized;
        }
        $checked = $this->checkPatch($scope, $values, $input['changes']);
        if (is_wp_error($checked)) {
            return $checked;
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'post_type' => $scope, 'changes' => (object) $input['changes']];
        }
        foreach ($native as $name => $value) {
            presspermit()->updateOption($name, $value);
            if ((new Settings())->snapshot($name)['value'] !== $value) {
                presspermit()->refreshOptions();
                return Access::unconfirmed();
            }
        }
        presspermit()->refreshOptions();
        do_action('presspermit_trigger_cache_flush');
        return $this->read(['post_type' => $scope]);
    }
}

<?php
namespace PublishPress\Permissions\Mcp;

\defined('ABSPATH') || exit;

/** Reviewed native setting controls and exact form storage representations. */
class Settings
{
    public function definitions()
    {
        $pp = presspermit();
        $names = ['display_group_exceptions', 'define_media_post_caps', 'define_create_posts_cap',
            'delete_settings_on_uninstall', 'display_extension_hints', 'strip_private_caption', 'force_nav_menu_filter', 'display_user_profile_groups', 'display_user_profile_roles',
            'new_user_groups_ui', 'admin_hide_uneditable_posts', 'post_blockage_priority', 'media_search_results',
            'term_counts_unfiltered', 'advanced_options', 'regulate_category_archive_page', 'display_hints',
            'force_display_hints', 'dynamic_wp_roles', 'non_admins_set_read_exceptions', 'non_admins_set_edit_exceptions',
            'user_search_by_role', 'users_bulk_groups', 'limit_front_end_term_filtering', 'use_tabbed_metabox'];
        if ($pp->moduleActive('collaboration')) {
            $names = array_merge($names, ['list_others_uneditable_posts', 'lock_top_pages', 'admin_others_attached_files',
                'admin_others_attached_to_readable', 'admin_others_unattached_files', 'edit_others_attached_files',
                'attachment_edit_requires_parent_access', 'own_attachments_always_editable', 'admin_nav_menu_filter_items',
                'admin_nav_menu_partial_editing', 'admin_nav_menu_lock_custom', 'limit_user_edit_enabled', 'limit_user_edit_by_level',
                'page_parent_order', 'add_author_pages', 'publish_author_pages', 'force_taxonomy_cols', 'page_parent_editable_only',
                'auto_assign_available_term', 'create_tag_require_edit_cap', 'publish_exceptions']);
        }
        require_once PRESSPERMIT_CLASSPATH . '/UI/SettingsTabCore.php';
        require_once PRESSPERMIT_CLASSPATH . '/UI/SettingsTabAdvanced.php';
        $captions = (new \PublishPress\Permissions\UI\SettingsTabCore(false))->optionCaptions([]);
        $advanced = new \PublishPress\Permissions\UI\SettingsTabAdvanced(false);
        $captions = $advanced->optionCaptions($captions);
        $captions += (array) ($advanced->advanced_option_captions ?? []);
        $captions = apply_filters('presspermit_option_captions', $captions);
        $definitions = [];
        foreach ($names as $name) {
            if (array_key_exists($name, $pp->default_options)) {
                $definitions[$name] = ['schema' => ['type' => 'boolean'], 'label' => $captions[$name] ?? $name,
                    'default' => $pp->default_options[$name], 'capability' => 'pp_manage_settings'];
            }
        }
        return apply_filters('presspermit_mcp_setting_definitions', $definitions, $captions);
    }

    public function schema($write)
    {
        if (!$write) {
            return Input::schema([]);
        }
        $fields = [];
        foreach ($this->definitions() as $name => $definition) {
            $fields[$name] = $definition['schema'];
        }
        return Input::schema(['changes' => Input::schema($fields), 'revision' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
            'dry_run' => ['type' => 'boolean']], ['changes', 'revision']);
    }

    private function permitted($definitions)
    {
        $caps = ['pp_manage_settings'];
        foreach ($definitions as $name => $definition) {
            $caps[] = $definition['capability'];
            if (in_array($name, presspermit()->netwide_options, true)) {
                $caps[] = 'manage_network_options';
            }
        }
        return Access::requireCaps(array_unique($caps));
    }

    public function snapshot($name)
    {
        $network = is_multisite() && in_array($name, presspermit()->netwide_options, true);
        $missing = new \stdClass();
        $raw = $network ? get_site_option('presspermit_' . $name, $missing) : get_option('presspermit_' . $name, $missing);
        return ['exists' => $raw !== $missing, 'value' => $raw === $missing ? null : $raw, 'network' => $network];
    }

    private function writable($name, $value)
    {
        $pp = presspermit();
        if (isset($pp->default_advanced_options[$name]) && !$pp->getOption('advanced_options')) {
            return false;
        }
        $candidate = $pp->site_options;
        $candidate['presspermit_' . $name] = $value;
        $filtered = apply_filters('presspermit_options', $candidate);
        return array_key_exists('presspermit_' . $name, $filtered)
            && (string) $filtered['presspermit_' . $name] === (string) $value;
    }

    public function read($input)
    {
        $input = Input::validate($input, $this->schema(false));
        if (is_wp_error($input)) {
            return $input;
        }
        $definitions = $this->definitions();
        if (is_wp_error($this->permitted($definitions))) {
            return Access::denied();
        }
        presspermit()->refreshOptions();
        $values = $snapshots = [];
        foreach ($definitions as $name => $definition) {
            $snapshot = $this->snapshot($name);
            $snapshots[$name] = $snapshot;
            $native = presspermit()->getOption($name);
            $value = Input::from_native($native, $definition['schema']);
            if (is_wp_error($value)) {
                return $value;
            }
            $values[$name] = ['label' => wp_strip_all_tags($definition['label']), 'schema' => $definition['schema'],
                'default' => Input::from_native($definition['default'], $definition['schema']), 'value' => $value,
                'stored_value' => $snapshot['value'], 'exists' => $snapshot['exists'], 'network' => $snapshot['network'],
                'writable' => $this->writable($name, '1') && $this->writable($name, '')];
        }
        return ['settings' => (object) $values, 'revision' => Access::revision([$values, $snapshots])];
    }

    public function update($input)
    {
        $input = Input::validate($input, $this->schema(true));
        if (is_wp_error($input) || !$input['changes'] || count($input['changes']) > 50) {
            return is_wp_error($input) ? $input : Input::error();
        }
        $definitions = $this->definitions();
        if (is_wp_error($this->permitted($definitions))) {
            return Access::denied();
        }
        $before = $this->read([]);
        if (is_wp_error($before)) {
            return $before;
        }
        $stale = Access::stale($input['revision'], $before['revision']);
        if (is_wp_error($stale)) {
            return $stale;
        }
        $changes = [];
        foreach ($input['changes'] as $name => $value) {
            $changes[$name] = $value ? '1' : '';
            if (!$before['settings']->{$name}['writable'] || !$this->writable($name, $changes[$name])) {
                return new \WP_Error('presspermit_mcp_configuration_locked', __('This setting is controlled by native configuration.', 'press-permit-core'));
            }
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'changes' => (object) $changes, 'revision' => $before['revision']];
        }
        foreach ($changes as $name => $value) {
            presspermit()->updateOption($name, $value);
            $actual = $this->snapshot($name);
            if (!$actual['exists'] || (string) $actual['value'] !== $value) {
                presspermit()->refreshOptions();
                return Access::unconfirmed();
            }
        }
        presspermit()->refreshOptions();
        do_action('presspermit_trigger_cache_flush');
        return $this->read([]);
    }
}

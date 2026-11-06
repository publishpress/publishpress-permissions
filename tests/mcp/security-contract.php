<?php
/** Exact native formats and inverse-scope authorization through the released Adapter. */
function permissions_mcp_security_contract($group_id, $subject_id, $post_id)
{
    $saved = [];
    $terms = [];
    $actor = wp_get_current_user();
    $had_assign_cap = array_key_exists('pp_set_term_assign_permissions', $actor->caps);
    $assign_cap = $actor->caps['pp_set_term_assign_permissions'] ?? null;
    $actor->add_cap('pp_set_term_assign_permissions');
    $capture = static function ($name) use (&$saved) {
        if (!array_key_exists($name, $saved)) {
            $missing = new stdClass();
            $raw = get_option('presspermit_' . $name, $missing);
            $saved[$name] = ['exists' => $raw !== $missing, 'value' => $raw];
        }
    };
    $ok = static function ($result, $label) {
        permissions_mcp_check(!is_wp_error($result), $label . (is_wp_error($result) ? ': ' . $result->get_error_message() : ''));
        return $result;
    };
    try {
        $capture('advanced_options');
        presspermit()->updateOption('advanced_options', '1');
        $discovery = $ok(permissions_mcp_call('get-settings'), 'Full native settings discovery');
        foreach ((array) $discovery['settings'] as $name => $field) {
            if (!$field['writable'] || $name === 'advanced_options') {
                continue;
            }
            $capture($name);
            if ($name === 'post_blockage_priority') {
                $capture('legacy_exception_handling');
            }
            $before = $ok(permissions_mcp_call('get-settings'), 'Current checkbox state');
            $field = $before['settings']->{$name};
            if (!$field['writable']) {
                continue;
            }
            $patch = ['revision' => $before['revision'], 'changes' => [$name => !$field['value']]];
            $raw = get_option('presspermit_' . $name, null);
            $ok(permissions_mcp_call('update-settings', $patch + ['dry_run' => true]), 'Checkbox preview ' . $name);
            permissions_mcp_check($raw === get_option('presspermit_' . $name, null), 'Checkbox preview wrote native storage.');
            $after = $ok(permissions_mcp_call('update-settings', $patch), 'Checkbox save ' . $name);
            permissions_mcp_check($after['settings']->{$name}['value'] === !$field['value'], 'Checkbox typed read-back mismatch: ' . $name);
            permissions_mcp_check(get_option('presspermit_' . $name) === (!$field['value'] ? '1' : ''), 'Checkbox native storage mismatch: ' . $name);
            $bad = ['revision' => $after['revision'], 'changes' => [$name => 'false', ($name === 'display_hints' ? 'strip_private_caption' : 'display_hints') => true]];
            permissions_mcp_check(is_wp_error(permissions_mcp_call('update-settings', $bad)), 'String boolean accepted: ' . $name);
            permissions_mcp_check(get_option('presspermit_' . $name) === (!$field['value'] ? '1' : ''), 'Malformed mixed patch wrote storage: ' . $name);
            $old = $saved[$name];
            $old['exists'] ? update_option('presspermit_' . $name, $old['value']) : delete_option('presspermit_' . $name);
            presspermit()->refreshOptions();
        }
        foreach (['', 'page'] as $teaser_type) {
            $teaser = $ok(permissions_mcp_call('get-teaser', ['post_type' => $teaser_type]), 'Every teaser field discovery');
            $changes = [];
            foreach ((array) $teaser['settings'] as $name => $field) {
                $capture($name);
                if (!$field['writable']) {
                    continue;
                }
                $changes[$name] = $field['value'];
                if ($name === 'feed_teaser' || str_starts_with($name, 'tease_replace_') || str_starts_with($name, 'tease_prepend_') || str_starts_with($name, 'tease_append_')) {
                    $changes[$name] = '<strong>Reader\'s "notice"</strong>';
                } elseif ($field['schema']['type'] === 'boolean') {
                    $changes[$name] = !$field['value'];
                }
            }
            $patch = ['post_type' => $teaser_type, 'revision' => $teaser['revision'], 'changes' => $changes];
            $ok(permissions_mcp_call('update-teaser', $patch + ['dry_run' => true]), 'All teaser fields preview');
            $preview_state = $ok(permissions_mcp_call('get-teaser', ['post_type' => $teaser_type]), 'Teaser preview unchanged state');
            permissions_mcp_check($preview_state['revision'] === $teaser['revision'], 'Teaser preview persisted data.');
            $saved_teaser = $ok(permissions_mcp_call('update-teaser', $patch), 'All teaser fields save');
            foreach ($changes as $name => $value) {
                permissions_mcp_check($saved_teaser['settings']->{$name}['value'] === $value, 'Teaser field round-trip mismatch: ' . $name);
                $native = $teaser_type ? presspermit()->getTypeOption($name, $teaser_type) : presspermit()->getOption($name);
                if ($saved_teaser['settings']->{$name}['schema']['type'] === 'boolean') {
                    permissions_mcp_check((bool) $native === $value, 'Native teaser checkbox mismatch: ' . $name);
                } elseif (is_string($value) && str_contains($value, '<strong>')) {
                    permissions_mcp_check(($teaser_type ? wp_unslash($native) : $native) === $value, 'Native teaser rich-text/slashing mismatch: ' . $name);
                } else {
                    permissions_mcp_check((string) $native === (string) $value, 'Native teaser value mismatch: ' . $name);
                }
            }
            $malformed = $changes;
            $malformed['unknown_native_field'] = true;
            permissions_mcp_check(is_wp_error(permissions_mcp_call('update-teaser', ['post_type' => $teaser_type, 'revision' => $saved_teaser['revision'], 'changes' => $malformed])), 'Unknown teaser field accepted.');
            $unchanged_teaser = $ok(permissions_mcp_call('get-teaser', ['post_type' => $teaser_type]), 'Teaser state after malformed patch');
            permissions_mcp_check($saved_teaser['revision'] === $unchanged_teaser['revision'], 'Malformed teaser patch partially wrote.');
        }
        $colors = (array) get_option('presspermit_teaser_notice_bg_color', []);
        $colors['page'] = '';
        update_option('presspermit_teaser_notice_bg_color', $colors);
        $empty_color = $ok(permissions_mcp_call('get-teaser', ['post_type' => 'page']), 'Native empty color inheritance');
        permissions_mcp_check($empty_color['settings']->teaser_notice_bg_color['value'] === '#f0f6fc', 'Empty native color did not inherit its native default.');
        presspermit()->updateOption('advanced_options', '');
        $usage = $ok(permissions_mcp_call('get-role-usage'), 'Disabled Advanced Settings discovery');
        $blocked = permissions_mcp_call('update-role-usage', ['revision' => $usage['revision'], 'changes' => [['role' => 'contributor', 'usage' => '0']], 'dry_run' => true]);
        permissions_mcp_check(is_wp_error($blocked) && $blocked->get_error_code() === 'presspermit_mcp_configuration_locked', 'Disabled Advanced Settings allowed a Role Usage patch.');
        presspermit()->updateOption('advanced_options', '1');
        $capture('publish_exceptions');
        presspermit()->updateOption('publish_exceptions', '1');
        $had_edit_cap = array_key_exists('pp_set_edit_permissions', $actor->caps);
        $edit_cap = $actor->caps['pp_set_edit_permissions'] ?? null;
        $actor->add_cap('pp_set_edit_permissions');
        try {
            $publish_scope = ['agent_type' => 'pp_group', 'agent_id' => $group_id, 'source' => 'post', 'object_id' => $post_id, 'operation' => 'publish'];
            $publish_state = $ok(permissions_mcp_call('get-permissions', $publish_scope), 'Native Publish permission discovery');
            $patch = $publish_scope + ['revision' => $publish_state['revision'], 'mod_type' => 'exclude', 'assign_for' => 'item', 'enabled' => true];
            $ok(permissions_mcp_call('set-permission', $patch + ['dry_run' => true]), 'Publish permission preview');
            $published = $ok(permissions_mcp_call('set-permission', $patch), 'Publish permission save');
            permissions_mcp_check(count($published['rules']) === 1, 'Publish permission native read-back failed.');
            $deny_publish = static function ($caps) { $caps['pp_set_edit_permissions'] = false; return $caps; };
            add_filter('user_has_cap', $deny_publish, PHP_INT_MAX);
            try {
                permissions_mcp_check(is_wp_error(permissions_mcp_call('get-permissions', $publish_scope)), 'Denied administrator editing-permissions capability was bypassed.');
            } finally {
                remove_filter('user_has_cap', $deny_publish, PHP_INT_MAX);
            }
            $patch['revision'] = $published['revision'];
            $patch['enabled'] = false;
            $ok(permissions_mcp_call('set-permission', $patch), 'Publish permission removal');
        } finally {
            $had_edit_cap ? $actor->add_cap('pp_set_edit_permissions', $edit_cap) : $actor->remove_cap('pp_set_edit_permissions');
        }
        if (defined('PRESSPERMIT_PRO_VERSION') && presspermit()->moduleActive('membership')) {
            $group = $ok(permissions_mcp_call('get-group', ['id' => $group_id]), 'Membership format discovery');
            $bad_date = permissions_mcp_call('set-group-membership', ['id' => $group_id, 'user_id' => $subject_id, 'revision' => $group['revision'],
                'changes' => ['date_limited' => false, 'start_date_gmt' => '2026-99-45 00:00:00'], 'dry_run' => true]);
            permissions_mcp_check(is_wp_error($bad_date), 'Invalid date was accepted while the date limit was disabled.');
            $group_after = $ok(permissions_mcp_call('get-group', ['id' => $group_id]), 'Unchanged membership state');
            permissions_mcp_check($group['revision'] === $group_after['revision'], 'Invalid membership date changed native storage.');
        }
        $capture('strip_private_caption');
        $capture('force_nav_menu_filter');
        presspermit()->updateOption('strip_private_caption', '');
        presspermit()->updateOption('force_nav_menu_filter', '');
        $partial_state = $ok(permissions_mcp_call('get-settings'), 'Partial failure setup');
        $fail_second = static function ($value, $old) { return $old; };
        add_filter('pre_update_option_presspermit_force_nav_menu_filter', $fail_second, PHP_INT_MAX, 2);
        try {
            $partial = permissions_mcp_call('update-settings', ['revision' => $partial_state['revision'], 'changes' => ['strip_private_caption' => true, 'force_nav_menu_filter' => true]]);
            permissions_mcp_check(is_wp_error($partial) && $partial->get_error_code() === 'presspermit_mcp_save_unconfirmed', 'Native partial write was reported as confirmed or rolled back.');
            permissions_mcp_check(get_option('presspermit_strip_private_caption') === '1' && get_option('presspermit_force_nav_menu_filter') === '', 'Native partial-failure fixture did not retain the actual first-write outcome.');
        } finally {
            remove_filter('pre_update_option_presspermit_force_nav_menu_filter', $fail_second, PHP_INT_MAX);
            presspermit()->refreshOptions();
        }
        $native_user = presspermit()->getUser();
        $prior_restrictions = $native_user->except;
        $native_user->except['manage_pp_group'] = ['pp_group' => ['' => ['exclude' => ['pp_group' => ['' => [$group_id]]]]]];
        try {
            permissions_mcp_check(is_wp_error(permissions_mcp_call('get-group', ['id' => $group_id])), 'Native group restrictions were bypassed outside wp-admin.');
        } finally {
            $native_user->except = $prior_restrictions;
        }
        register_taxonomy('mcp_security_tax', 'post', ['public' => true, 'hierarchical' => true]);
        $capture('enabled_taxonomies');
        $types = (array) presspermit()->getOption('enabled_taxonomies');
        $types['mcp_security_tax'] = '1';
        presspermit()->updateOption('enabled_taxonomies', $types);
        foreach (['Allowed', 'Denied'] as $label) {
            $term = wp_insert_term($label, 'mcp_security_tax');
            permissions_mcp_check(!is_wp_error($term), 'Term fixture creation failed.');
            $terms[] = (int) $term['term_id'];
        }
        $scope = ['agent_type' => 'pp_group', 'agent_id' => $group_id, 'source' => 'term', 'object_id' => $terms[0],
            'taxonomy' => 'mcp_security_tax', 'target_source' => 'term', 'operation' => 'assign'];
        $state = $ok(permissions_mcp_call('get-permissions', $scope), 'Native term permission discovery');
        $deny = static function ($caps, $cap, $user_id, $args) use ($terms) {
            if ($cap === 'edit_term' && (int) ($args[0] ?? 0) === $terms[1]) {
                return ['do_not_allow'];
            }
            return $caps;
        };
        add_filter('map_meta_cap', $deny, PHP_INT_MAX, 4);
        try {
            $patch = $scope + ['revision' => $state['revision'], 'mod_type' => 'include', 'assign_for' => 'item', 'enabled' => true, 'dry_run' => true];
            permissions_mcp_check(is_wp_error(permissions_mcp_call('set-permission', $patch)), 'Inverse term scope bypassed an explicitly denied term.');
        } finally {
            remove_filter('map_meta_cap', $deny, PHP_INT_MAX);
        }
        $state = $ok(permissions_mcp_call('get-permissions', $scope), 'Fresh term permission state');
        $term_patch = $scope + ['revision' => $state['revision'], 'mod_type' => 'include', 'assign_for' => 'item', 'enabled' => true];
        $saved_term = $ok(permissions_mcp_call('set-permission', $term_patch), 'Native term inclusion save');
        permissions_mcp_check(count($saved_term['rules']) === 1, 'Native term inclusion did not round-trip.');
        permissions_mcp_check(is_wp_error(permissions_mcp_call('get-permissions', array_replace($scope, ['object_id' => (string) $terms[0]]))), 'Numeric-string term ID accepted.');
        add_filter('map_meta_cap', $deny, PHP_INT_MAX, 4);
        try {
            permissions_mcp_check(is_wp_error(permissions_mcp_call('get-group-deletion', ['id' => $group_id])), 'Group deletion ignored an inverse term permission scope.');
        } finally {
            remove_filter('map_meta_cap', $deny, PHP_INT_MAX);
        }
        $term_patch['revision'] = $saved_term['revision'];
        $term_patch['enabled'] = false;
        $ok(permissions_mcp_call('set-permission', $term_patch), 'Native term inclusion removal');
    } finally {
        foreach ($terms as $id) {
            wp_delete_term($id, 'mcp_security_tax');
        }
        if (taxonomy_exists('mcp_security_tax')) {
            unregister_taxonomy('mcp_security_tax');
        }
        foreach ($saved as $name => $old) {
            $old['exists'] ? update_option('presspermit_' . $name, $old['value']) : delete_option('presspermit_' . $name);
        }
        $had_assign_cap ? $actor->add_cap('pp_set_term_assign_permissions', $assign_cap) : $actor->remove_cap('pp_set_term_assign_permissions');
        presspermit()->refreshOptions();
    }
}
permissions_mcp_security_contract($group_id, $user_id, $post_id);

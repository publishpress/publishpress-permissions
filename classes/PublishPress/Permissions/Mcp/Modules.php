<?php
namespace PublishPress\Permissions\Mcp;

\defined('ABSPATH') || exit;

/** Module switches preserve native required modules and deferred loading. */
class Modules
{
    public function schema($write)
    {
        if (!$write) {
            return Input::schema([]);
        }
        return Input::schema(['changes' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 20,
            'items' => Input::schema(['module' => ['type' => 'string', 'maxLength' => 100], 'enabled' => ['type' => 'boolean']], ['module', 'enabled'])],
            'revision' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'], 'dry_run' => ['type' => 'boolean']], ['changes', 'revision']);
    }

    private function access()
    {
        $caps = ['pp_manage_settings', 'pp_manage_permissions', 'pp_manage_teaser', 'pp_manage_members'];
        if (is_multisite()) {
            $caps[] = 'manage_network_options';
        }
        return Access::requireCaps($caps);
    }

    public function read($input)
    {
        $input = Input::validate($input, $this->schema(false));
        if (is_wp_error($input)) {
            return $input;
        }
        if (is_wp_error($this->access())) {
            return Access::denied();
        }
        $pp = presspermit();
        $pp->refreshOptions();
        $disabled = (array) $pp->getOption('deactivated_modules');
        $rows = [];
        foreach ($pp->getAvailableModules() as $module) {
            $installed = file_exists(PRESSPERMIT_ABSPATH . '/modules/' . $module . '/' . $module . '.php')
                || (defined('PRESSPERMIT_PRO_ABSPATH') && file_exists(PRESSPERMIT_PRO_ABSPATH . '/modules/' . $module . '/' . $module . '.php'));
            if (!$installed) {
                continue;
            }
            $required = in_array($module, $pp->getRequiredModules(), true);
            $rows[] = ['module' => $module, 'enabled' => $required || empty($disabled[$module]),
                'loaded' => $pp->moduleActive(str_replace('presspermit-', '', $module)), 'required' => $required];
        }
        return ['modules' => $rows, 'revision' => Access::revision([$rows, $disabled, (new Settings())->snapshot('deactivated_modules')])];
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
        $known = array_column($before['modules'], null, 'module');
        $deactivated = (array) presspermit()->getOption('deactivated_modules');
        $seen = [];
        foreach ($input['changes'] as $change) {
            $module = $change['module'];
            if (!isset($known[$module]) || isset($seen[$module]) || (!$change['enabled'] && $known[$module]['required'])) {
                return Input::error();
            }
            $seen[$module] = true;
            if ($change['enabled']) {
                unset($deactivated[$module]);
            } else {
                $deactivated[$module] = (object) [];
            }
        }
        $candidate = presspermit()->site_options;
        $candidate['presspermit_deactivated_modules'] = $deactivated;
        $filtered = apply_filters('presspermit_options', $candidate);
        if (!isset($filtered['presspermit_deactivated_modules'])
            || serialize($filtered['presspermit_deactivated_modules']) !== serialize($deactivated)) {
            return Access::denied();
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'changes' => $input['changes'], 'applies_next_request' => true];
        }
        presspermit()->updateModules($deactivated);
        if (serialize((new Settings())->snapshot('deactivated_modules')['value']) !== serialize($deactivated)) {
            return Access::unconfirmed();
        }
        $after = $this->read([]);
        return is_wp_error($after) ? Access::unconfirmed() : $after + ['applies_next_request' => true];
    }
}

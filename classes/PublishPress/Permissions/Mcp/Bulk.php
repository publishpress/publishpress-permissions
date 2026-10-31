<?php
namespace PublishPress\Permissions\Mcp;

defined('ABSPATH') || exit;

/** Prevalidated bounded batches; native failures report confirmed preceding writes. */
class Bulk
{
    public function schema($roles = false, $read = false)
    {
        $service = $roles ? new Roles() : new Exceptions();
        $item = $service->schema(!$read);
        unset($item['properties']['dry_run']);
        $fields = ['items' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 25, 'items' => $item]];
        if (!$read) {
            $fields['dry_run'] = ['type' => 'boolean'];
        }
        return Input::schema($fields, ['items']);
    }

    public function matrix($input)
    {
        $input = Input::validate($input, $this->schema(false, true));
        if (is_wp_error($input)) {
            return $input;
        }
        $rows = [];
        $count = 0;
        foreach ($input['items'] as $scope) {
            $result = (new Exceptions())->read($scope);
            if (is_wp_error($result)) {
                return $result;
            }
            $count += count($result['rules']);
            if ($count > 1000) {
                return Input::error();
            }
            $rows[] = ['scope' => $scope, 'state' => $result];
        }
        return ['items' => $rows];
    }

    public function permissions($input)
    {
        return $this->update($input, false);
    }

    public function roles($input)
    {
        return $this->update($input, true);
    }

    private function update($input, $roles)
    {
        $input = Input::validate($input, $this->schema($roles));
        if (is_wp_error($input)) {
            return $input;
        }
        $service = $roles ? new Roles() : new Exceptions();
        $seen = [];
        $scope_count = 0;
        foreach ($input['items'] as $item) {
            $preview = $service->update($item + ['dry_run' => true]);
            if (is_wp_error($preview)) {
                return $preview;
            }
            $subject = $item['agent_type'] . ':' . $item['agent_id'];
            if ($roles) {
                if (isset($seen[$subject])) {
                    return Input::error();
                }
                $seen[$subject] = true;
                continue;
            }
            $objects = $service->scopeObjects($item);
            if (is_wp_error($objects)) {
                return $objects;
            }
            $scope_count += count($objects);
            if ($scope_count > 1000) {
                return Input::error();
            }
            $target_source = $item['target_source'] ?? ('pp_group' === $item['source'] ? 'pp_group' : 'post');
            $target = 'post' === $item['source'] ? get_post_type($item['object_id']) : ($item['post_type'] ?? $item['taxonomy'] ?? 'pp_group');
            $channel = $subject . ':' . $item['operation'] . ':' . $target_source . ':' . $target . ':' . ($item['status'] ?? '');
            foreach ($seen[$channel] ?? [] as $prior) {
                if (($item['source'] === $prior['source'] && ($item['taxonomy'] ?? '') === $prior['taxonomy'] && array_intersect($objects, $prior['objects']))) {
                    return new \WP_Error('presspermit_mcp_overlapping_batch', __('Use separate requests for overlapping permission scopes.', 'press-permit-core'));
                }
            }
            $seen[$channel][] = ['mod_type' => $item['mod_type'], 'source' => $item['source'], 'taxonomy' => $item['taxonomy'] ?? '', 'objects' => $objects];
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'validated_items' => count($input['items'])];
        }
        $results = [];
        foreach ($input['items'] as $index => $item) {
            try {
                $saved = $service->update($item);
            } catch (\Throwable $error) {
                $saved = Access::unconfirmed();
            }
            if (is_wp_error($saved)) {
                return ['completed' => false, 'confirmed_items' => count($results), 'failed_index' => $index,
                    'failed_item_state' => 'unconfirmed', 'error_code' => $saved->get_error_code(),
                    'message' => __('Some native writes may have completed. Read every affected scope before retrying.', 'press-permit-core'), 'results' => $results];
            }
            $results[] = ['index' => $index, 'state' => $saved];
        }
        return ['completed' => true, 'confirmed_items' => count($results), 'results' => $results];
    }
}

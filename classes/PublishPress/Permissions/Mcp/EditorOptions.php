<?php
namespace PublishPress\Permissions\Mcp;

\defined('ABSPATH') || exit;

/** Native General-tab per-type metabox and Permissions-screen switches. */
class EditorOptions extends NativeOptions
{
    protected function definitions($scope)
    {
        return [
            'metabox_enabled' => ['schema' => ['type' => 'boolean'], 'default' => true, 'scalar' => true, 'explicit_zero' => true,
                'label' => __('Enable Permissions metabox', 'press-permit-core')],
            'permission_screen_enabled' => ['schema' => ['type' => 'boolean'], 'default' => true, 'scalar' => true, 'explicit_zero' => true,
                'label' => __('Include in Permissions screens', 'press-permit-core')],
        ];
    }

    protected function optionName($name, $scope)
    {
        return ('metabox_enabled' === $name ? 'pp_enable_metabox_' : 'pp_include_permission_screen_') . $scope;
    }

    public function schema($write)
    {
        $schema = parent::schema($write);
        $schema['properties']['post_type']['minLength'] = 1;
        $schema['required'] = array_merge($schema['required'] ?? [], ['post_type']);
        return $schema;
    }

    protected function authorize($scope)
    {
        if (is_wp_error(Access::requireCaps(['pp_manage_settings']))) {
            return Access::denied();
        }
        $choices = (new ContentTypes())->read(['scope' => 'post_types']);
        if (is_wp_error($choices)) {
            return $choices;
        }
        $types = array_column($choices['types'], null, 'name');
        $type = get_post_type_object($scope);
        if (!$type || empty($types[$scope]['enabled']) || !current_user_can($type->cap->edit_others_posts)) {
            return Access::denied();
        }
        return true;
    }
}

<?php

namespace PublishPress\Permissions;

class Roles
{
    var $anon_user_caps = [];
    var $pattern_roles = [];
    var $direct_roles = [];
    var $disabled_pattern_role_types = [];

    public function __construct()
    {  // note: PressPermit::registerPatternRole() also available
        $this->pattern_roles = ['subscriber' => (object)[], 'contributor' => (object)[], 'author' => (object)[], 'editor' => (object)[]];

        add_action('plugins_loaded', [$this, 'actPluginsLoaded'], 15);
        add_action('init', [$this, 'actLoadPatternRoleLabels'], 5);
    }

    public function defineRoles()
    {
        $read_public_cap = (defined('PRESSPERMIT_READ_PUBLIC_CAP')) ? PRESSPERMIT_READ_PUBLIC_CAP : 'read';
        $this->anon_user_caps = apply_filters('presspermit_anon_user_caps', [$read_public_cap]);

        if ($direct_roles = apply_filters('presspermit_default_direct_roles', [])) {
            global $wp_roles;

            if (!empty($wp_roles)) {
                $direct_roles = array_intersect($direct_roles, array_keys($wp_roles->role_names));

                foreach ($direct_roles as $role_name) {
                    $caption = $wp_roles->role_names[$role_name];
                    $this->direct_roles[$role_name] = (object)['labels' => (object)['name' => $caption, 'singular_name' => $caption]];
                }
            }
        } else {
            $this->direct_roles = [];
        }
    }

    public function actPluginsLoaded()
    {
        if (!class_exists('PublishPress\Statuses\StatusControl') 
        || !presspermit()->moduleActive('collaboration')) {
            add_filter('presspermit_unfiltered_post_types', [$this, 'fltPostTypesNoBBpress'], 1);  // requires additional code, avoid appearance of support
        } else {
            add_filter('presspermit_unfiltered_post_types', [$this, 'fltPostTypesBBpressForumOnly'], 1);  // requires additional code, avoid appearance of support
        }
        
        $this->pattern_roles = apply_filters('presspermit_pattern_roles_raw', $this->pattern_roles);
    }

    public function actLoadPatternRoleLabels() {
        $this->pattern_roles = apply_filters('presspermit_pattern_roles', $this->pattern_roles);
    }

    // bbPress permisisons require additional code in PP Pro modules
    public function fltPostTypesNoBBpress($unfiltered_types)
    {
        return array_merge($unfiltered_types, ['forum', 'topic', 'reply', 'wp_block']);
    }

    public function fltPostTypesBBpressForumOnly($unfiltered_types)
    {
        return array_merge($unfiltered_types, ['topic', 'reply']);
    }
}

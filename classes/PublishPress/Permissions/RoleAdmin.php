<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class RoleAdmin
{
    public static function getTypeRoles($for_item_source, $for_item_type, $require_caps = false)
    {
        $pp = presspermit();

        $type_roles = [];

        if (empty($pp->role_defs->disabled_pattern_role_types[$for_item_type]) && post_type_exists($for_item_type)) {
            if ($require_caps) {
                $cap_caster = $pp->capCaster();
                if (empty($cap_caster->pattern_role_type_caps)) {
                    $cap_caster->definePatternCaps();
                }

                $check_prop = (taxonomy_exists($for_item_type)) ? $cap_caster->pattern_role_taxonomy_caps : $cap_caster->pattern_role_type_caps;
            } else {
                $check_prop = [];
            }

            foreach ($pp->role_defs->pattern_roles as $role_name => $pattern_role) {
                if (!$check_prop || !empty($check_prop[$role_name])) {
                    $type_roles["{$role_name}:{$for_item_source}:{$for_item_type}"] = $pattern_role->labels->singular_name;
                }
            }
        }

        // Consider WP roles which are enabled for direct supplemental assignment,
        // but only if they have at least one type-defined capability and are not disabled for the object type
        static $wp_type_roles = [];
        if (!isset($wp_type_roles[$for_item_source]) || !isset($wp_type_roles[$for_item_source][$for_item_type])) {

            $wp_type_roles[$for_item_source][$for_item_type] = [];

            if ($pp->role_defs->direct_roles) {
                global $wp_roles;

                if ('-1' === $for_item_type) {
                    foreach (array_keys($pp->role_defs->direct_roles) as $role_name) {
                        $wp_type_roles[$for_item_source][$for_item_type][$role_name] = $pp->role_defs->direct_roles[$role_name]->labels->singular_name;
                    }
                } elseif ($type_obj = $pp->getTypeObject($for_item_source, $for_item_type)) {
                    $type_caps = (array)$type_obj->cap;

                    $check_type_caps = array_diff_key(array_fill_keys($type_caps, true), [PRESSPERMIT_READ_PUBLIC_CAP => true]);

                    $cap_caster = $pp->capCaster();
                    $cap_caster->definePatternCaps();

                    foreach (array_keys($pp->role_defs->direct_roles) as $role_name) {
                        if (
                            array_intersect_key($check_type_caps, $wp_roles->role_objects[$role_name]->capabilities)
                            || !empty($cap_caster->pattern_role_arbitrary_caps[$role_name])
                        ) {
                            $wp_type_roles[$for_item_source][$for_item_type][$role_name] = $pp->role_defs->direct_roles[$role_name]->labels->singular_name;
                        }
                    }
                }
            }
        }

        $type_roles = array_merge($type_roles, $wp_type_roles[$for_item_source][$for_item_type]);

        return apply_filters('presspermit_get_type_roles', $type_roles, $for_item_source, $for_item_type);
    }
}

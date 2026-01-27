<?php

namespace PublishPress\Permissions;

/**
 * CapabilityCaster class
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2024, PublishPress
 *
 */

class CapabilityCaster
{
    var $pattern_role_type_caps = [];
    var $pattern_role_taxonomy_caps = [];
    var $pattern_role_cond_caps = [];
    var $pattern_role_arbitrary_caps = [];
    var $typecast_role_caps = [];

    public function isValidPatternRole($wp_role_name, $role_caps = false)
    {
        if ('subscriber' != $wp_role_name) {
            global $wp_roles;

            if (false === $role_caps) {
                $role_caps = (isset($wp_roles->role_objects[$wp_role_name])) ? $wp_roles->role_objects[$wp_role_name]->capabilities : [];
            }

            $type_obj = get_post_type_object('post');
            return isset($wp_roles->role_objects[$wp_role_name]) && !empty($role_caps[$type_obj->cap->edit_posts]);
        } else
            return true;
    }

    // If one of the standard WP roles is missing, define it for use as a template for type-specific role assignments
    public function definePatternCaps($args = [])
    {
        global $wp_roles;

        $pp = presspermit();

        $caps = [];

        foreach (array_keys($pp->role_defs->pattern_roles) as $role_name) {
            if (isset($wp_roles->role_objects[$role_name]->capabilities)) {
                $caps[$role_name] = array_filter($wp_roles->role_objects[$role_name]->capabilities);

                // if a standard WP role has been deleted, revert to default rolecaps
                if (
                    in_array($role_name, ['subscriber', 'contributor', 'author', 'editor'])
                    && (empty($caps[$role_name]) || !$this->isValidPatternRole($role_name, $caps[$role_name]))
                ) {
                    require_once(PRESSPERMIT_CLASSPATH . '/RoleDefaults.php');
                    $caps[$role_name] = RoleDefaults::getDefaultRolecaps($role_name);
                }
            }
        }

        $caps = apply_filters('presspermit_pattern_role_caps', $caps);

        $type_obj = get_post_type_object('post');

        $type_caps = [];
        $type_caps['post'] = array_diff_key(get_object_vars($type_obj->cap), array_fill_keys(['read_post', 'edit_post', 'delete_post'], true));

        $use_strict_rolecaps = !empty($args['force_strict']) || !presspermit()->getOption('pattern_roles_include_generic_rolecaps');

        if ($use_strict_rolecaps) {
            $include_caps = array_fill_keys(
                apply_filters(
                    'presspermit_include_arbitrary_caps',
                    [
                        'read', 'unfiltered_html', 'upload_files', 'edit_files',
                    ]
                ),
                true
            );
        } else {
            $exclude_caps = array_fill_keys(
                apply_filters(
                    'presspermit_exclude_arbitrary_caps',
                    [
                        'level_0', 'level_1', 'level_2', 'level_3', 'level_4', 'level_5', 'level_6', 'level_7', 'level_8', 'level_9',
                        'level_10', 'edit_dashboard', 'add_users', 'create_users', 'edit_users', 'list_users', 'promote_users',
                        'remove_users', 'activate_plugins', 'delete_plugins', 'edit_plugins', 'install_plugins', 'update_plugins',
                        'delete_themes', 'edit_theme_options', 'edit_themes', 'install_themes', 'switch_themes', 'update_themes',
                        'export', 'import', 'manage_links', 'manage_categories', 'manage_options', 'update_core', 'pp_manage_settings',
                        'pp_administer_content', 'pp_unfiltered', 'pp_create_groups', 'pp_delete_groups', 'pp_edit_groups',
                        'pp_manage_members', 'manage_capabilities_dashboard',
                        'manage_capabilities_roles',
                        'manage_capabilities',
                        'manage_capabilities_editor_features',
                        'manage_capabilities_admin_features',
                        'manage_capabilities_admin_menus',
                        'manage_capabilities_frontend_features',
                        'manage_capabilities_profile_features',
                        'manage_capabilities_nav_menus',
                        'manage_capabilities_user_testing',
                        'manage_capabilities_backup',
                        'manage_capabilities_settings',
                    ]
                ),
                true
            );

            foreach ($pp->getOperations() as $op) {
                $exclude_caps["pp_set_{$op}_exceptions"] = true;
                $exclude_caps["pp_set_term_{$op}_exceptions"] = true;
            }
        }

        foreach (array_keys($caps) as $role_name) {
            // log caps defined for the "post" type
            // intersect with values of $post_type_obj->cap to account for possible customization of "post" type capabilities
            $this->pattern_role_type_caps[$role_name] = array_intersect($type_caps['post'], array_keys($caps[$role_name]));

            if (!$this->isValidPatternRole($role_name, $this->pattern_role_type_caps[$role_name])) {
                // role has no edit_posts cap stored, so use default rolecaps instead
                require_once(PRESSPERMIT_CLASSPATH . '/RoleDefaults.php');
                if ($def_caps = RoleDefaults::getDefaultRolecaps($role_name, false)) {
                    $this->pattern_role_type_caps[$role_name] = array_intersect_key(
                        $type_caps['post'],
                        array_combine(array_keys($def_caps), array_keys($def_caps))
                    );
                }
            }

            // log caps not defined for any post type or status
            if ($use_strict_rolecaps) {
                $misc_caps = array_intersect_key($caps[$role_name], $pp->capDefs()->all_type_caps, $include_caps);
            } else {
                $misc_caps = array_diff_key($caps[$role_name], $pp->capDefs()->all_type_caps, $exclude_caps);
            }

            if ($misc_caps) {
               $this->pattern_role_arbitrary_caps[$role_name] = array_combine(array_keys($misc_caps), array_keys($misc_caps));
            }
        }

        do_action('presspermit_define_pattern_caps', $caps);
    }

    // $role_name : "baserolename:srcname:objtype:attribute:condition"
    // should only be called if $this->typecast_role_caps[$role_name] is not already set
    public function getTypecastCaps($role_name)
    {
        $arr_name = explode(':', $role_name);
        if (empty($arr_name[2]))
            return [];

        $pp = presspermit();

        // this role typecast is not db-defined, so generate it
        $base_role_name = $arr_name[0];
        $source_name = $arr_name[1];
        $object_type = $arr_name[2];

        // typecast role assignment stored, but undefined source name or object_type
        if (!$type_obj = $pp->getTypeObject($source_name, $object_type))
            return [];

        if (!empty($type_obj->cap->read) && ('read' == $type_obj->cap->read)) {
            $type_obj->cap->read = PRESSPERMIT_READ_PUBLIC_CAP;
        } 

        // disregard stored Extra Roles for Media when Media is no longer enabled for PP filtering (otherwise Post editing caps are granted)
        if (('attachment' == $object_type)) {
            static $media_filtering_enabled;

            if (!isset($media_filtering_enabled))
                $media_filtering_enabled = $pp->getEnabledPostTypes(['name' => 'attachment']);

            if (!$media_filtering_enabled) {
                return [];
            }
        }

        if (empty($this->pattern_role_type_caps))
            $this->definePatternCaps();

        $pattern_role_caps = ('term' == $source_name) ? $this->pattern_role_taxonomy_caps : $this->pattern_role_type_caps;

        // if the role definition is not currently configured for Pattern Role usage, disregard the assignment
        if (empty($pattern_role_caps[$base_role_name])) {
            return [];
        }

        if (!class_exists('PublishPress\Statuses\StatusControl') && strpos($role_name, 'post_status:private')
        && (isset($pattern_role_caps[$base_role_name]['read']) || isset($pattern_role_caps[$base_role_name][PRESSPERMIT_READ_PUBLIC_CAP]))
        ) {
            $pattern_role_caps[$base_role_name]['read_private_posts'] = true;
        }

        if (!empty($arr_name[3])) {
            if (empty($arr_name[4])) { // disregard stored roles with invalid status
                return [];
            } elseif ('post_status' == $arr_name[3]) {  // ignore extra roles for statuses which are no longer active for this post type
                if (!PWP::getPostStatuses(['name' => $arr_name[4], 'post_type' => $object_type]))
                    return [];
            }
        }

        // add all type-specific caps whose base property cap is included in this pattern role
        // i.e. If 'edit_posts' is in the pattern role, grant $type_obj->cap->edit_posts
        //
        if ($caps = array_intersect_key((array)get_object_vars($type_obj->cap), $pattern_role_caps[$base_role_name])) {
            // At least one type-defined cap is being cast from this pattern role for specified object_type
            $status_caps = apply_filters('presspermit_get_typecast_caps', $caps, $arr_name, $type_obj);

            if (('attachment' == $object_type) && !$pp->getOption('define_media_post_caps')) {
                $status_caps = array_intersect_key($status_caps, ['create_posts' => true]);
            }

            // if stored status value is invalid, don't credit user for "default statuses" type caps (but allow for subscriber-private if PPS inactive
            if (!empty($arr_name[3]) && (false === strpos($role_name, 'post_status:private')) && ($caps == $status_caps)) {
                return [];
            } else {
                return apply_filters('presspermit_apply_arbitrary_caps', $status_caps, $arr_name, $type_obj);
            }
        }

        return $caps;
    }

    // back compat for CME < 1.8
    public function get_typecast_caps($role_name)
    {
        return $this->getTypecastCaps($role_name);
    }

    // pulls user's typecast site roles and merges those caps into $user->allcaps
    public function getUserTypecastCaps($user)
    {
        $add_caps = [];

        foreach (array_keys($user->site_roles) as $role_name) {
            // add all type-specific caps whose base property cap is included in this pattern role
            // i.e. If 'edit_posts' is in the pattern role, grant $type_obj->cap->edit_posts
            //
            // note: getTypecastCaps() returns arr[pattern_cap_name] = type_cap_name
            //   but we need to return arr[type_cap_name] = true
            $add_caps = array_merge($add_caps, array_fill_keys($this->getTypecastCaps($role_name), true));
    
            if (!presspermit()->getOption('pattern_roles_include_generic_rolecaps')) {
                $arr_name = explode(':', $role_name);
                if (!empty($arr_name[3]) && ('post_status' == $arr_name[3])) {
                    foreach (array_keys($add_caps) as $cap_name) {
                        if (0 === strpos($cap_name, 'status_change_')) {
                            unset($add_caps[$cap_name]);
                        }
                    }
                }
            }
        }

        return $add_caps;
    }
}

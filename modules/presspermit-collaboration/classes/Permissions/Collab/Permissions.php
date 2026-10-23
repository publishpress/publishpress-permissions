<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class Permissions
{
    public static function userCanAdminRole($role_name, $object_type, $item_id = 0)
    {
        $pp = presspermit();

        if ($pp->isUserAdministrator())
            return true;

        static $require_sitewide_editor;

        if (!isset($require_sitewide_editor))
            $require_sitewide_editor = $pp->getOption('role_admin_sitewide_editor_only');

        if ('admin' == $require_sitewide_editor)
            return false;  // User Admins already returned true

        if (('admin_content' == $require_sitewide_editor) && !$pp->isContentAdministrator())
            return false;

        // User can't view or edit role assignments unless they have all rolecaps
        if ($item_id) {
            static $reqd_caps;

            if (!isset($reqd_caps))
                $reqd_caps = [];

            $reqd_caps[$role_name] = presspermit()->capCaster()->getTypecastCaps($role_name, 'object');

            // temp workaround
            foreach ($reqd_caps[$role_name] as $key => $cap_name) {
                if (0 === strpos($key, 'create_child_')) {
                    $cap_name = str_replace('create_child_', 'edit_', $cap_name);
                }

                if (!current_user_can($cap_name, $item_id))
                    return false;
            }

            // Are we also applying the additional requirement (based on RS Option setting) that the user is a site-wide editor?
            if ($require_sitewide_editor) {
                static $can_edit_sitewide;

                if (!isset($can_edit_sitewide))
                    $can_edit_sitewide = [];

                if (!isset($can_edit_sitewide[$object_type])) {
                    if ($type_obj = get_post_type_object($object_type)) {
                        $can_edit_sitewide[$object_type] = current_user_can($type_obj->cap->edit_posts);
                    }
                }

                if (!$can_edit_sitewide[$object_type]) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function supplementModerateAnyCap()
    {
        global $wp_roles;
        $post_types = presspermit()->getEnabledPostTypes();

        $user = presspermit()->getUser();

        foreach (array_keys($user->site_roles) as $pp_role_name) {
            $wp_role_name = '';
            $arr_role = explode(':', $pp_role_name);

            if (count($arr_role) == 1) {  // direct-assigned role
                $wp_role_name = $pp_role_name;
            } elseif (count($arr_role) == 3) {
                if (('post' == $arr_role[1]) && in_array($arr_role[2], $post_types, true))
                    $wp_role_name = $arr_role[0];
            }

            if ($wp_role_name && !empty($wp_roles->role_objects[$wp_role_name]->capabilities['pp_moderate_any'])) {
                global $current_user;
                $user->allcaps['pp_moderate_any'] = true;
                $current_user->allcaps['pp_moderate_any'] = true;
                break;
            }
        }
    }

}

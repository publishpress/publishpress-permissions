<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class CapabilityFiltersAdmin
{
    private $in_has_cap_call = false;

    public function __construct()
    {
        add_filter('map_meta_cap', [$this, 'fltAdjustReqdCaps'], 1, 4);

        // prevent infinite recursion if current_user_can( 'edit_posts' ) is called from within another plugin's user_has_cap handler
        add_filter('user_has_cap', [$this, 'fltFlagHasCapCall'], 0);
        add_filter('user_has_cap', [$this, 'fltFlagHasCapDone'], 999);
    }

    public function fltFlagHasCapCall($caps)
    {
        $this->in_has_cap_call = true;
        return $caps;
    }

    public function fltFlagHasCapDone($caps)
    {
        $this->in_has_cap_call = false;
        return $caps;
    }

    // hooks to map_meta_cap
    public function fltAdjustReqdCaps($reqd_caps, $orig_cap, $user_id, $args)
    {
        global $pagenow, $current_user;

        if ($this->in_has_cap_call) {
            return $reqd_caps;
        }

        // Work around WP's occasional use of literal 'cap_name' instead of $post_type_object->cap->$cap_name
        // note: cap names for "post" type may be customized too
        if (
            in_array($pagenow, ['edit.php', 'post.php', 'post-new.php', 'press-this.php', 'admin-ajax.php', 'upload.php', 'media.php'])
            && in_array('edit_posts', $reqd_caps, true) && ($user_id == $current_user->ID) && !PWP::doingAdminMenus()
        ) {
            static $did_admin_init = false;
            if (!$did_admin_init) {
                $did_admin_init = did_action('admin_init');
            }

            if ($did_admin_init) {
                if (!empty($args[0]) && (is_scalar($args[0]) || !empty($args[0]->ID))) {
                    $item_id = (is_object($args[0])) ? $args[0]->ID : (int) $args[0];
                } else {
                    $item_id = 0;
                }

                if ($type_obj = get_post_type_object(PWP::findPostType($item_id))) {
                    $key = array_search('edit_posts', $reqd_caps);
                    if (false !== $key) {
                        $reqd_caps[$key] = $type_obj->cap->edit_posts;
                    }
                }
            }
        }

        //===============================

        return $reqd_caps;
    }
}

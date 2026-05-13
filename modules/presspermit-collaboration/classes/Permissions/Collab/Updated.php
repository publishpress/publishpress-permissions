<?php
namespace PublishPress\Permissions\Collab;

class Updated
{
    public function __construct($prev_pp_version)
    {
        // single-pass do loop to easily skip unnecessary version checks
        do {
            if (!$prev_pp_version) {
                break;  // no need to run through version comparisons if no previous version
            }

            if (version_compare($prev_pp_version, '3.8-beta4', '<')) {
                if (null === get_option('presspermit_list_others_uneditable_posts', null)) {
                    // If a previous version was installed, default to requiring list_others_posts capability
                    update_option('presspermit_list_others_uneditable_posts', 0);
                }
            } else break;

        } while (0); // end single-pass version check loop
    }

    public static function populateRoles()
    {
        if ($role = @get_role('administrator')) {
            $role->add_cap('pp_set_edit_permissions');
            $role->add_cap('pp_set_associate_permissions');
            $role->add_cap('pp_set_term_assign_permissions');
            $role->add_cap('pp_set_term_manage_permissions');
            $role->add_cap('pp_set_term_associate_permissions');
        }

        if ($role = @get_role('editor')) {
            $role->add_cap('pp_set_edit_permissions');
            $role->add_cap('pp_set_associate_permissions');
            $role->add_cap('pp_set_term_assign_permissions');
            $role->add_cap('pp_set_term_manage_permissions');
            $role->add_cap('pp_set_term_associate_permissions');
        }

        update_option('ppce_added_role_caps_21beta', true);
    }
}

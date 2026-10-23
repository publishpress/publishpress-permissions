<?php

namespace PublishPress\Permissions\UI;

\defined('ABSPATH') || exit;

class AgentRolesAjax
{
    public function __construct()
    {
        check_ajax_referer('pp-ajax');

        if (!$for_item_source = PWP::GET_key('pp_source_name')) {
            exit;
        }

        if (!$for_item_type = PWP::GET_key('pp_object_type')) {
            exit;
        }

        if (!$pp_ajax_agent_roles = PWP::GET_key('pp_ajax_agent_roles')) {
            exit;
        }

        $pp = presspermit();
        $pp_admin = $pp->admin();

        if (!$pp->admin()->bulkRolesEnabled()) {
            exit;
        }

        $role_name = !empty($_GET['pp_role_name']) ? PWP::sanitizeEntry(sanitize_text_field(wp_unslash($_GET['pp_role_name']))) : '';

        $filterable_vars = ['for_item_source', 'for_item_type', 'role_name'];
        if ($force_vars = apply_filters('presspermit_ajax_role_ui_vars', [], compact($filterable_vars))) {
            $_vars = Arr::subset($force_vars, $filterable_vars);
            foreach (array_keys($_vars) as $var) {
                $$var = $_vars[$var];
            }
        }

        switch ($pp_ajax_agent_roles) {

            case 'get_role_options':
                if (!is_user_logged_in()) {
                    echo '<option>' . esc_html__('(login timed out)', 'press-permit-core') . '</option>';
                    exit;
                }

                global $wp_roles;

                require_once(PRESSPERMIT_CLASSPATH.'/RoleAdmin.php');

                if ($roles = \PublishPress\Permissions\RoleAdmin::getTypeRoles($for_item_source, $for_item_type)) {
                    foreach ($roles as $_role_name => $role_title) {
                        if ($pp_admin->userCanAdminRole($_role_name, $for_item_type)) {
                            $selected = ($_role_name == $role_name) ? ' selected ' : '';
                            echo "<option value='" . esc_attr($_role_name) . "'" . esc_attr($selected) . ">". esc_html($role_title) . "</option>";
                        }
                    }
                } else {
                    echo "<option value='' " . esc_attr($selected) . ">" .  esc_html__('(invalid role definition)', 'press-permit-core') . "</option>";
                }
                break;

            case 'get_conditions_ui':
                if (!is_user_logged_in()) {
                    echo '<p>' . esc_html__('(login timed out)', 'press-permit-core') . '</p><div class="pp-checkbox">'
                        . '<input type="checkbox" name="pp_select_for_item" style="display:none">'
                        . '<input type="checkbox" name="pp_select_for_item" style="display:none"></div>';

                    exit;
                }

                $checked = (!empty($pp->role_defs->direct_roles[$role_name])) ? ' checked ' : '';

                if (('post' != $for_item_source) || ('attachment' == $for_item_type)) {
                    echo '<p class="pp-checkbox">'
                    . '<input type="checkbox" id="pp_select_cond_" name="pp_select_cond[]" value=""' . esc_attr($checked) . ' /> '
                    . '<label id="lbl_pp_select_cond_" for="pp_select_cond_">' . esc_html__('Standard Statuses', 'press-permit-core') . '</label>'
                    . '</p>';
                } elseif ($role_name) {
                    $type_obj = $pp->getTypeObject($for_item_source, $for_item_type);
                    $type_caps = $pp->getRoleCaps($role_name);

                    $direct_assignment = (false === strpos($role_name, ':'));

                    if (!empty($type_caps['edit_posts']) || $direct_assignment) {
                        $do_standard_statuses_ui = true;
                    } else {
                        if (empty($type_caps)) {
                            $arr_role_name = explode(':', $role_name);
                            if (in_array($arr_role_name[0], ['contributor', 'author', 'editor', 'revisor'], true)) {
                                $do_standard_statuses_ui = true;
                            }
                        }
                    }

                    if (!empty($do_standard_statuses_ui)) {
                        echo '<p class="pp-checkbox">'
                        . '<input type="checkbox" id="pp_select_cond_" name="pp_select_cond[]" value=""' . esc_attr($checked) . ' /> '
                        . '<label id="lbl_pp_select_cond_" for="pp_select_cond_">' . esc_html__('Standard Statuses', 'press-permit-core') . '</label>'
                        . '</p>';
                    }

                    // edit_private, delete_private caps are normally cast from pattern role
                    if ((isset($type_caps['read']) || isset($type_caps[PRESSPERMIT_READ_PUBLIC_CAP])) && (empty($type_caps['edit_posts']) || $direct_assignment)) {
                        $pvt_obj = get_post_status_object('private');

                        echo '<p class="pp-checkbox pp_select_private_status">'
                            . '<input type="checkbox" id="pp_select_cond_post_status_private" name="pp_select_cond[]" value="post_status:private" />'
                            . '<label for="pp_select_cond_post_status_private"> ' . sprintf(esc_html__('%s Visibility', 'press-permit-core'), esc_html($pvt_obj->label)) . '</label>'
                            . '</p>';
                    }

                    if ($direct_assignment) {
                        break;
                    }

                    do_action('presspermit_permission_status_ui_done', $for_item_type, $type_caps, $role_name);
                }

                break;
        } // end switch
    }
}

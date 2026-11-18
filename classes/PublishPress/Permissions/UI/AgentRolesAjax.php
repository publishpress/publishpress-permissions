<?php

namespace PublishPress\Permissions\UI;

\defined('ABSPATH') || exit;

class AgentRolesAjax
{
    public static function showsStandardStatus($type_caps, $role_name)
    {
        if (!empty($type_caps['edit_posts']) || false === strpos($role_name, ':')) {
            return true;
        }
        $base = explode(':', $role_name)[0];
        return !$type_caps && in_array($base, ['contributor', 'author', 'editor', 'revisor'], true);
    }

    public static function showsPrivateStatus($type_caps, $role_name)
    {
        return (isset($type_caps['read']) || isset($type_caps[PRESSPERMIT_READ_PUBLIC_CAP]))
            && (empty($type_caps['edit_posts']) || false === strpos($role_name, ':'));
    }

    public static function statusChoices($type, $type_caps, $role_name)
    {
        $choices = self::showsStandardStatus($type_caps, $role_name)
            ? ['' => __('Standard Statuses', 'press-permit-core')] : [];
        if (self::showsPrivateStatus($type_caps, $role_name)) {
            $choices['private'] = __('Status: Private', 'press-permit-core');
        }
        return apply_filters('presspermit_role_status_choices', $choices, $type, $type_caps, $role_name);
    }

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

                    foreach (self::statusChoices($for_item_type, $type_caps, $role_name) as $status => $label) {
                        $value = $status ? 'post_status:' . $status : '';
                        $id = 'pp_select_cond_' . str_replace(':', '_', $value);
                        $class = 'private' === $status ? ' pp_select_private_status' : '';
                        echo '<p class="pp-checkbox' . esc_attr($class) . '">'
                            . '<input type="checkbox" id="' . esc_attr($id) . '" name="pp_select_cond[]" value="'
                            . esc_attr($value) . '"' . esc_attr($status ? '' : $checked) . ' /> '
                            . '<label id="' . esc_attr('lbl_' . $id) . '" for="' . esc_attr($id) . '"> ' . esc_html($label) . '</label>'
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

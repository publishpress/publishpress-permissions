<?php

namespace PublishPress\Permissions\UI\Dashboard;

class ItemExceptionsRenderUI
{
    public $opt_labels = [];
    public $opt_class = [];
    public $options = [];
    private $base_url = '';

    public function __construct()
    {
        $this->opt_labels = [
            'default' => esc_html__('(Default access: Enabled)', 'press-permit-core'),
            'default_yes' => esc_html__('(Default access: Enabled)', 'press-permit-core'),
            'default_no' => esc_html__('(Default access: Blocked)', 'press-permit-core'),
            'no_setting' => esc_html__('(No setting)', 'press-permit-core'),
            'enabled' => esc_html__('Enabled', 'press-permit-core'),
            'blocked' => esc_html__('Blocked', 'press-permit-core'),
            'default_blocked' => esc_html__('(Blocked)', 'press-permit-core'),
            'unblocked' => esc_html__('Unblocked', 'press-permit-core'),
        ];

        foreach ($this->opt_labels as $k => $val) {
            $this->opt_labels[$k] = str_replace(['(', ')'], '', $val);
        }

        $this->opt_class = ['' => "pp-def", 0 => "pp-no2", 1 => "pp-yes", 2 => "pp-yes2"];
    }

    public function setOptions($agent_type)
    {
        global $pagenow;

        $this->options = ['includes' => [], 'standard' => []];

        $this->options['includes'][''] = $this->opt_labels['default_blocked'];
        $this->options['includes'][1] = $this->opt_labels['unblocked'];
        $this->options['includes'][2] = $this->opt_labels['enabled'];

        if (in_array($agent_type, ['wp_role', 'user'], true) || !defined('PP_NO_GROUP_RESTRICTIONS')) {
            $this->options['standard'][''] = (('user' == $agent_type) || in_array($pagenow, ['edit-tags.php', 'term.php']))
                ? $this->opt_labels['no_setting']
                : $this->opt_labels['default'];

            $this->options['standard'][0] = $this->opt_labels['blocked'];

            if (!defined('PP_NO_ADDITIONAL_ACCESS')) $this->options['standard'][2] = $this->opt_labels['enabled'];
        } else {
            $this->options['standard'][''] = $this->opt_labels['no_setting'];
            if (!defined('PP_NO_ADDITIONAL_ACCESS')) $this->options['standard'][2] = $this->opt_labels['enabled'];
        }

        switch ($agent_type) {
            case 'wp_role':
            case 'pp_group':
                $this->base_url = "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_id=";
                break;

            default:
                $this->base_url = "admin.php?page=presspermit-edit-permissions&amp;action=edit&amp;agent_type=$agent_type&amp;agent_id=";
        }
    }

    public function drawRow($agent_type, $agent_id, $agent_exceptions, $inclusions_active, $agent_info, $args = [])
    {
        global $wp_roles;

        $defaults = ['reqd_caps' => false, 'hierarchical' => false, 'for_item_type' => '', 'op' => '', 'default_select' => false, 'item_id' => 0];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();

        $assignment_modes = ['item'];
        if ($hierarchical) {
            $assignment_modes[] = 'children';
        }

        $current_vals = [];

        $this->opt_class[''] = '';
        $disabled = '';

        if (('wp_role' == $agent_type) && (!function_exists('rvy_in_revision_workflow') || !rvy_in_revision_workflow($item_id))) {
        
            // also credit sitewide caps attached via supplemental role assignment to WP Role metagroup
            static $metagroup_caps;
            if (!isset($metagroup_caps)) {
                $metagroup_caps = [];

                global $wpdb;

                // One locally cached direct query of plugin tables to retrieve extra roles for all WP metagroups
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $results = $wpdb->get_results(
                    "SELECT g.metagroup_id AS wp_rolename, r.role_name AS supplemental_role FROM $wpdb->ppc_roles AS r"
                        . " INNER JOIN $wpdb->pp_groups AS g ON g.ID = r.agent_id AND r.agent_type = 'pp_group'"
                        . " WHERE g.metagroup_type = 'wp_role'"
                );

                foreach ($results as $row) {
                    $role_specs = explode(':', $row->supplemental_role);
                    if (!empty($role_specs[2]) && ($for_item_type != $role_specs[2])) {
                        continue;
                    }

                    if (!isset($metagroup_caps[$row->wp_rolename])) {
                        $metagroup_caps[$row->wp_rolename] = [];
                    }

                    $metagroup_caps[$row->wp_rolename] = array_merge(
                        $metagroup_caps[$row->wp_rolename],
                        array_fill_keys($pp->getRoleCaps($row->supplemental_role), true)
                    );
                }
            }

            $role_obj_caps = (empty($wp_roles->role_objects[$agent_info->metagroup_id]->capabilities))
                ? []
                : $wp_roles->role_objects[$agent_info->metagroup_id]->capabilities;

            $role_caps = isset($wp_roles->role_objects[$agent_info->metagroup_id])
                ? array_intersect($role_obj_caps, [true, 1, '1'])
                : [PRESSPERMIT_READ_PUBLIC_CAP => true, 'spectate' => true];

            if (isset($metagroup_caps[$agent_info->metagroup_id])) {
                $role_caps = array_merge($role_caps, $metagroup_caps[$agent_info->metagroup_id]);
            }

            $is_unfiltered = !empty($role_caps['pp_administer_content']) || !empty($role_caps['pp_unfiltered']);

            if ($reqd_caps) {
                if (!array_diff($reqd_caps, array_keys($role_caps)) || $is_unfiltered) {
                    $this->opt_class[''] = 'pp-yes';
                    $this->options['standard'][''] = $this->opt_labels['default_yes'];
                } else {
                    $this->opt_class[''] = 'pp-no';
                    $this->options['standard'][''] = $this->opt_labels['default_no'];
                }
            }
        } else {
            $this->options['standard'][''] = (in_array($agent_type, ['wp_role', 'user'], true))
                ? $this->opt_labels['default']
                : $this->opt_labels['no_setting'];
        }

        $_inclusions_active = isset($inclusions_active[$for_item_type][$op][$agent_type][$agent_id]);

        $_name = $agent_info->name;
        if('user' == $agent_type) {
            if (!empty($agent_info->formatted_name)) {
                // formatted_name contains "First Name Last Name (user_login)"
                $_name = $agent_info->formatted_name;
            }
            if(defined('PP_USER_RESULTS_DISPLAY_NAME')) {
                $_name = $agent_info->display_name;
            }
        }

        if ('wp_role' == $agent_type) {
            require_once(PRESSPERMIT_CLASSPATH . '/DB/Groups.php');
            $title = " title='" . \PublishPress\Permissions\DB\Groups::getMetagroupDescript('wp_role', $agent_info->metagroup_id, '') . "'";

            if (!empty($agent_info->metagroup_id)) {
                $_name = \PublishPress\Permissions\DB\Groups::getMetagroupName('wp_role', $agent_info->metagroup_id, $_name);
            }
        } elseif (('user' == $agent_type) && !empty($agent_info->display_name) && ($agent_info->display_name != $agent_info->name)) {
            $title = $agent_info->display_name;
        } else {
            $title = '';
        } ?>
        <tr>
            <td class='pp-exc-agent'><input type='hidden' value='<?php echo esc_attr($agent_id); ?>' />
                <a href='<?php echo esc_url("{$this->base_url}$agent_id"); ?>' title='<?php echo esc_attr($title); ?>'
                    target='_blank'><?php echo esc_html($_name); ?></a>
            </td>
            <?php
            foreach ($assignment_modes as $assign_for) {
                if (!empty($agent_exceptions[$assign_for]['additional'])) {
                    $current_val = 2;
                } elseif (isset($agent_exceptions[$assign_for]['include'])) {
                    $current_val = 1;
                } else {
                    if ($default_select) {
                        $current_val = ($_inclusions_active) ? '1' : '2';  // default to "Unblocked" if available, otherwise Enabled
                    } else {
                        $current_val = (isset($agent_exceptions[$assign_for]['exclude'])) ? 0 : '';
                    }
                }

                if ($_inclusions_active) {
                    $option_set = 'includes';
                    $this->opt_class[''] = 'pp-no';
                } else {
                    $option_set = 'standard';

                    if (!$this->opt_class['']) {
                        $this->opt_class[''] = 'pp-def';
                    }
                }

                if (!empty($is_unfiltered) && ($current_val === '')) {  // Disable UI for unfiltered users unless an (ineffective) exception is already stored
                    $disabled = ' disabled ';
                } else {
                    $disabled = (('children' == $assign_for)
                        && apply_filters(
                            'presspermit_assign_for_children_locked',
                            false,
                            $for_item_type,
                            ['operation' => $op]
                        ))
                        ? ' disabled ' : '';
                }

                $for_type = ($for_item_type) ? $for_item_type : '(all)';
            ?>
                <td class="<?php echo ('children' == $assign_for) ? 'pp-exc-children' : 'pp-exc-item'; ?>">
                    <select name='pp_exceptions<?php echo esc_attr("[$for_type][$op][$agent_type][$assign_for][$agent_id]") . "' class='" . esc_attr($this->opt_class[$current_val]) . "'"; ?><?php echo esc_attr($disabled); ?> autocomplete="off">
                    <?php
                    foreach ($this->options[$option_set] as $val => $lbl) :
                        if (('wp_role' == $agent_type)
                            && in_array($agent_info->metagroup_id, ['wp_anon', 'wp_all'], true)
                            && (!$pp->moduleActive('file-access') || 'attachment' != $for_type)
                            && !defined('PP_ALL_ANON_FULL_EXCEPTIONS')
                            && (2 == $val)
                        ) {
                            continue;
                        }
                    ?>
                    <option value='<?php echo esc_attr("$val") . "' class='" . esc_attr($this->opt_class[$val]) . "' ";
                                    selected($val, $current_val); ?>>
                        <?php echo esc_html($lbl); ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
                    <?php if ($disabled) : ?>
                        <input type="hidden"
                            name='pp_exceptions<?php echo esc_attr("[$for_type][$op][$agent_type][$assign_for][$agent_id]"); ?>'
                            value="<?php echo esc_attr($current_val); ?>" />
                    <?php endif; ?>

                </td>
            <?php
                $current_vals[$assign_for] = $current_val;
            }
            ?>
        </tr>
<?php
        // return the each select element's current value, for downstream UI logic
        return $current_vals;
    } // end function 
}

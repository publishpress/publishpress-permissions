<?php

namespace PublishPress\Permissions\UI;

/**
 * AgentsChecklist class
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2024, PublishPress
 *
 */
class AgentsChecklist
{
    // for group selection only
    public static function display($agents_subset, $agent_type, $all_agents, $name_attrib, $item_assignments, $args)
    {
        $defaults = ['eligible_ids' => [], 'locked_ids' => [], 'show_subset_caption' => true, 'hide_checkboxes' => false];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $caption_length_limit = (defined('PP_AGENTS_CAPTION_LIMIT')) ? PP_AGENTS_CAPTION_LIMIT : 100;
        $emsize_threshold = (defined('PP_AGENTS_EMSIZE_THRESHOLD')) ? PP_AGENTS_EMSIZE_THRESHOLD : 4;

        static $exec_count = 0;
        $exec_count++;  // support abbreviated checkbox id for label association

        if ('eligible' == $agents_subset) {
            $item_assignments = array_intersect_key($item_assignments, $all_agents);

            if (!$agent_count = count($all_agents) - count($item_assignments))
                return;
        } else {
            $agent_count = count($item_assignments);
        }
?>
<style>
.checkbox-container {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.checkbox-item {
  display: flex;
  align-items: center;
  padding: 0.75rem;
  border-radius: 8px;
  transition: all 0.2s ease;
  cursor: pointer;
  position: relative;
}
.checkbox-item:hover {
  background-color: #f5f7ff;
}
.checkbox-item.selected {
  background-color: #e0e7ff;
  border-left: 3px solid #4f46e5;
}
.checkbox-input {
  position: absolute;
  opacity: 0;
  height: 0;
  width: 0;
}
.checkbox-custom {
  position: relative;
  display: inline-block;
  width: 20px;
  height: 20px;
  min-width: 20px;
  border: 2px solid #e2e8f0;
  border-radius: 4px;
  margin-right: 1rem;
  transition: all 0.2s ease;
}
.checkbox-input:checked ~ .checkbox-custom {
  background-color: #4f46e5;
  border-color: #4f46e5;
}
.checkbox-input:focus ~ .checkbox-custom {
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
}
.checkbox-custom::after {
  content: "";
  position: absolute;
  display: none;
  left: 6px;
  top: 2px;
  width: 5px;
  height: 10px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}
.checkbox-input:checked ~ .checkbox-custom::after {
  display: block;
}
.checkbox-label {
  font-weight: 500;
  flex-grow: 1;
  padding-right: 1rem;
}
@media (max-width: 768px) {
  .item-actions {
    opacity: 1;
  }
}
</style>
<?php
        echo "<div>";
        echo "<ul class='pp-list_horiz'><li>";
        if ($show_subset_caption) {
            echo "<div class='pp-agents_caption'><strong>";

            if ('eligible' == $agents_subset) {
                printf(esc_html__('eligible (%d):', 'press-permit-core'), (int) $agent_count);
            } else {
                printf(esc_html__('current (%d):', 'press-permit-core'), (int) $agent_count);
            }

            echo "</strong></div>";
        }

        echo '</li>';

        echo '</ul>';

        // -------- construct captions and determine required list item width -----------
        $captions = $full_captions = $draw_agents = [];

        global $wp_locale;
        $rtl = (isset($wp_locale) && ('rtl' == $wp_locale->text_direction));

        $longest_caption_length = 10;

        foreach ($all_agents as $agent) {
            $id = $agent->ID;
            $skip = false;

            switch ($agents_subset) {
                case 'current':
                    if (!isset($item_assignments[$id])) $skip = true;
                    break;
                default: //'eligible'
                    if (isset($item_assignments[$id])) $skip = true;
                    if ($eligible_ids && !in_array($id, $eligible_ids)) $skip = true;
            }

            if ($skip) {
                unset($all_agents[$id]);
                continue;
            }

            if (('pp_group' == $agent_type) && $agent->metagroup_id)
                $caption = \PublishPress\Permissions\DB\Groups::getMetagroupName($agent->metagroup_type, $agent->metagroup_id, $agent->name);
            else
                $caption = $agent->name;

            if (strlen($caption) > $caption_length_limit) {
                $full_captions[$id] = $caption;

                if ($rtl)
                    $caption = '...' . substr($caption, strlen($caption) - $caption_length_limit);
                else
                    $caption = substr($caption, 0, $caption_length_limit) . '...';
            }

            if (strlen($caption) > $longest_caption_length) {
                $longest_caption_length = (strlen($caption) >= $caption_length_limit) ? $caption_length_limit + 2 : strlen($caption);
            }

            $captions[$id] = $caption;
        } //-------- end caption construction --------------

        if ($agent_count > $emsize_threshold) {
            $ems_per_character = (defined('PP_UI_EMS_PER_CHARACTER')) ? PP_UI_EMS_PER_CHARACTER : 0.85;
            $list_width_ems = $ems_per_character * $longest_caption_length;

            $ul_class = 'pp-agents-list_' . intval($list_width_ems);

            echo "<div id='div_" . esc_attr("{$agents_subset}_{$name_attrib}") . "' class='" . esc_attr("pp-{$agent_type} pp-{$agents_subset}") . "'>"
                . "<div class='pp-agents_emsized'>"
                . "<ul class='pp-agents-list " . esc_attr($ul_class) . "' id='" . esc_attr("list_{$agents_subset}_{$name_attrib}") . "'>";
        } else {
            $ul_class = "pp-agents-list_auto";
            echo "<div class='pp-" . esc_attr($agent_type) . "'>"
                . "<ul class='pp-agents-list " . esc_attr($ul_class) . "' id='" . esc_attr("list_{$agents_subset}_{$name_attrib}") . "'>";
        }

        if (presspermit()->groups()->groupTypeEditable($agent_type)) {
            $edit_link_base = apply_filters('presspermit_groups_base_url', 'admin.php')
                . "?page=presspermit-edit-permissions&amp;action=edit&amp;agent_type=$agent_type&amp;agent_id=";

            $edit_title_text = esc_attr__('view / edit group', 'press-permit-core');
            $edit_caption = esc_html__('edit', 'press-permit-core');
        } else
            $edit_link_base = '';

        foreach ($all_agents as $agent) {
            $id = $agent->ID;

            if (!empty($agent->metagroup_id)) {
                $display_name = (isset($agent->display_name)) ? $agent->display_name : '';
                $li_title =  \PublishPress\Permissions\DB\Groups::getMetagroupDescript($agent->metagroup_type, $agent->metagroup_id, $display_name);
            } elseif (isset($full_captions[$id])) {
                $li_title = $full_captions[$id];
            } else {
                $li_title = $captions[$id];
            }

            $checked = (isset($item_assignments[$id])) ? 'checked' : '';
            $disabled = ($locked_ids && in_array($id, $locked_ids)) ? 'disabled' : '';
            $selected = $checked ? 'selected' : '';

            echo "<li title='" . esc_attr($li_title) . "'>";
            if ($hide_checkboxes) {
                echo '&bull; ' . esc_html($captions[$id]);
            } else {
                // Modern checkbox markup
                echo '<label class="checkbox-item ' . esc_attr($selected) . '">';
                echo '<input type="checkbox" class="checkbox-input" name="' . esc_attr($name_attrib) . '[]" value="' . esc_attr($id) . '" id="r' . esc_attr("{$exec_count}_{$id}") . '" ' . $checked . ' ' . $disabled . ' />';
                echo '<span class="checkbox-custom"></span>';
                echo '<span class="checkbox-label">' . esc_html($captions[$id]) . '</span>';
                echo '</label>';
                if ($edit_link_base && presspermit()->groups()->userCan('pp_edit_groups', $id, $agent_type)) {
                    echo ' <a href="' . esc_url($edit_link_base . $id) . '" target="_blank" title="' . esc_attr($edit_title_text) . '">' . esc_html($edit_caption) . '</a>';
                }
            }
            echo '</li>';
        }

        echo "<li></li></ul>"; // prevent invalid markup if no other li's

        if ($agent_count > $emsize_threshold)
            echo '</div>';

        echo '</div></div>';
    }
}

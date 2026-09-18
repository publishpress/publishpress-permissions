<?php

namespace PublishPress\Permissions\UI;

/**
 * Agents class
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2024, PublishPress
 *
 */
class Agents
{
    private $agents_ajax;

    public function agentsUI($agent_type, $all_agents, $id_suffix = '', $item_assignments = [], $args = [])
    {
        $defaults = [
            'role_name' => '',
            'ajax_selection' => false,
            'width' => '',
            'hide_checkboxes' => false,
            'single_select' => false,
            'current_only' => false
        ];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if ($single_select) {
            $ajax_selection = false;
            presspermit_enqueue_admin_script();
        }

        echo '<div class="pp_agents_wrapper' . ($single_select ? ' pp-single-select' : '') . '">';

        if ($ajax_selection) {
            $agents_ajax = $this->agentsDynamicUI();

            echo '<div class="pp_agents_ajax_wrapper">';
            if ('presspermit-edit-permissions' == presspermitPluginPage()) {
                $args['width'] = 180;
            }
            $agents_ajax->display_select2($agent_type, $id_suffix, $item_assignments, $args);
            echo '</div>';
        } else {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentsChecklist.php');

            echo "<div class='pp_agents_ui_wrapper'>";

            echo "<input type='hidden' name='" . esc_attr($agent_type) . "[]' value='' />";

            if ($item_assignments) {
                AgentsChecklist::display('current', $agent_type, $all_agents, $id_suffix, $item_assignments, $args);
            }

            if (!$current_only) {
                AgentsChecklist::display('eligible', $agent_type, $all_agents, $id_suffix, $item_assignments, $args);
            }

            echo '<div style="clear:both; height:1px; margin:0">&nbsp;</div>';

            echo '</div>'; // pp_agents_ui_wrapper
        }

        echo '</div>'; // pp_agents_wrapper
    }

    private function agentsDynamicUI()
    {
        if (!isset($this->agents_ajax)) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentsDynamicUI.php');
            $this->agents_ajax = new AgentsDynamicUI();
        }

        return $this->agents_ajax;
    }
}

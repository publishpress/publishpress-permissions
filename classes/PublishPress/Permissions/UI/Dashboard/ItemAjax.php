<?php

namespace PublishPress\Permissions\UI\Dashboard;

class ItemAjax
{
    public function __construct() {
        check_ajax_referer('pp-ajax');

        if (!$via_item_source = PWP::GET_key('via_item_source')) {
            exit;
		}
        
        if (!$pp_ajax_item = PWP::GET_key('pp_ajax_item')) {
            exit;
        }
        
        $item_id = PWP::GET_int('item_id');

        $html = '';

        switch ($pp_ajax_item) {

            case 'get_agent_exception_ui':
                if (!is_user_logged_in()) {
                    echo '<option>' . esc_html__('(login timed out)', 'press-permit-core') . '</option>';
                    exit;
                }

                $id_sfx = (!empty($_GET['id_sfx'])) ? sanitize_text_field($_GET['id_sfx']) : '';

                if (!$id_sfx) {
                    exit;
                }

                if (!$arr_sfx = explode(':', PWP::sanitizeCSV($id_sfx))) {
                    return '';
                }

                $op = $arr_sfx[0];
                $for_item_type = $arr_sfx[1];
                $agent_type = $arr_sfx[2];
                $for_item_source = (taxonomy_exists($for_item_type)) ? 'term' : 'post';

                $via_item_type = PWP::GET_key('via_item_type');
                
                $agent_ids = (!empty($_GET['agent_ids'])) ? array_map('intval', explode(',', sanitize_text_field($_GET['agent_ids']))) : [];

                if (('post' == $via_item_source) && $item_id && !current_user_can('edit_post', $item_id)) {
                    exit;
                }

                if (('term' == $via_item_source) && $item_id && !current_user_can('edit_term', $item_id)) {
                    exit;
                }

                if ('term' == $for_item_source) {
                    $ops = presspermit()->admin()->canSetExceptions('read', $for_item_type, compact('via_item_source', 'for_item_source', 'via_item_type'))
                    ? ['read' => true] : [];

                    $operations = apply_filters('presspermit_item_edit_exception_ops', $ops, 'term', $for_item_type);
                } else {
                    $ops = presspermit()->admin()->canSetExceptions('read', $for_item_type, compact('via_item_source', 'for_item_source'))
                    ? ['read' => true] : [];

                    $operations = apply_filters('presspermit_item_edit_exception_ops', $ops, 'post', $for_item_type);
                }

                if (empty($operations[$op])) {
                    exit;
                }

                echo "<!--ppSfx-->" . esc_html("$op|$for_item_type|$agent_type") . "<--ppSfx-->"
                    . "<!--ppResponse-->";

                require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsData.php');
                $exc_data = new ItemExceptionsData();

                $args = ['post_types' => (array)$for_item_type, 'agent_type' => $agent_type, 'operations' => $op, 'agent_id' => $agent_ids];

                $exc_data->loadExceptions(
                    $via_item_source,
                    $for_item_source,
                    $via_item_type,
                    $item_id,
                    $args
                );

                require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsRenderUI.php');
                $exc_render = new ItemExceptionsRenderUI();

                $echo = false;
                $reqd_caps = false;
                $hierarchical = (PWP::is_GET('via_item_source', 'term'))
                    ? is_taxonomy_hierarchical($via_item_type)
                    : is_post_type_hierarchical($via_item_type);

                $hierarchical = apply_filters('presspermit_do_assign_for_children_ui', $hierarchical, $via_item_type, $args);
                $default_select = true;

                $exc_render->setOptions($agent_type);

                foreach ($agent_ids as $agent_id) {
                    if (!$agent_id)
                        continue;

                    $exc_render->drawRow(
                        $agent_type,
                        $agent_id,
                        [],
                        $exc_data->inclusions_active,
                        $exc_data->agent_info[$agent_type][$agent_id],
                        compact('echo', 'default_select', 'for_item_type', 'op', 'reqd_caps', 'hierarchical', 'item_id')
                    );
                }

                echo "<--ppResponse-->";

                break;
        } // end switch
    }
}

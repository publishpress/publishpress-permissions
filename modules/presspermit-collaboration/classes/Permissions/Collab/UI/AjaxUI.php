<?php
namespace PublishPress\Permissions\Collab\UI;

class AjaxUI
{
    public static function fltOperationCaptions($op_captions)
    {
        $op_captions['edit'] = (object)['label' => esc_html__('Edit'), 'noun_label' => esc_html__('Editing', 'press-permit-core')];

        if (presspermit()->getOption('publish_exceptions'))
            $op_captions['publish'] = (object)['label' => esc_html__('Publish'), 'noun_label' => esc_html__('Publishing', 'press-permit-core')];

        if (defined('PUBLISHPRESS_REVISIONS_VERSION')) {
            if (rvy_get_option('revision_statuses_noun_labels')) {
            	$op_captions['copy'] = (object)['label' => esc_html__('Working Copy of', 'press-permit-core'), 'abbrev' => esc_html__('Working Copy', 'press-permit-core'), 'noun_label' => esc_html__('Copy', 'press-permit-core')];
            	$op_captions['revise'] = (object)['label' => esc_html__('Submit Changes to', 'press-permit-core'), 'abbrev' => esc_html__('Submit Changes', 'press-permit-core'), 'noun_label' => esc_html__('Request', 'press-permit-core')];
            } else {
                $op_captions['copy'] = (object)['label' => esc_html__('Create Revision of', 'press-permit-core'), 'abbrev' => esc_html__('Create Revision', 'press-permit-core'), 'noun_label' => esc_html__('New Revision', 'press-permit-core')];
                $op_captions['revise'] = (object)['label' => esc_html__('Submit Revision of', 'press-permit-core'), 'abbrev' => esc_html__('Submit Revision', 'press-permit-core'), 'noun_label' => esc_html__('Submitted Revision', 'press-permit-core')];
            }

            foreach(['label', 'abbrev', 'noun_label'] as $prop) {
                $op_captions['copy']->$prop = str_replace(' ', '&nbsp;', $op_captions['copy']->$prop);
                $op_captions['revise']->$prop = str_replace(' ', '&nbsp;', $op_captions['revise']->$prop);
            }
        } elseif (defined('REVISIONARY_VERSION')) {
            $op_captions['revise'] = (object)['label' => esc_html__('Revise', 'press-permit-core'), 'noun_label' => esc_html__('Revision', 'press-permit-core')];
        }

        $op_captions = array_merge($op_captions, [
            'associate' => (object)[
                'label' => esc_html__('Set as Parent', 'press-permit-core'), 
                'noun_label' => esc_html__('Set as Parent', 'press-permit-core'), 
                'agent_label' => esc_html__('Set as Parent', 'press-permit-core')
            ],
            
            'assign' => (object)[
                'label' => esc_html__('Assign Term', 'press-permit-core'), 
                'noun_label' => esc_html__('Assignment', 'press-permit-core')
            ],

            'manage' => (object)[
                'label' => esc_html__('Manage'), 
                'noun_label' => esc_html__('Management', 'press-permit-core')
            ],
        ]);

        return $op_captions;
    }

    public static function fltExceptionOperations($ops, $for_item_source, $for_item_type)
    {
        $pp = presspermit();

        if ('post' == $for_item_source) {
            $op_obj = $pp->admin()->getOperationObject('edit', $for_item_type);
            $ops['edit'] = $op_obj->label;

            if (presspermit()->getOption('publish_exceptions')) {
                $op_obj = $pp->admin()->getOperationObject('publish', $for_item_type);
                $ops['publish'] = $op_obj->label;
            }

            if (defined('PUBLISHPRESS_REVISIONS_VERSION') && !in_array($for_item_type, ['forum'], true)) {
                $op_obj = $pp->admin()->getOperationObject('copy', $for_item_type);
                $ops['copy'] = $op_obj->abbrev;

                $op_obj = $pp->admin()->getOperationObject('revise', $for_item_type);
                $ops['revise'] = $op_obj->abbrev;
            }

            if (defined('REVISIONARY_VERSION') && !in_array($for_item_type, ['forum'], true)) {
                $op_obj = $pp->admin()->getOperationObject('revise', $for_item_type);
                $ops['revise'] = $op_obj->label;
            }

            if ($for_item_type && is_post_type_hierarchical($for_item_type) && !presspermit()->getOption('page_parent_editable_only')) {
                $op_obj = $pp->admin()->getOperationObject('associate', $for_item_type);
                $ops['associate'] = $op_obj->agent_label;
            }

            $type_arg = ($for_item_type) ? ['object_type' => $for_item_type] : [];
            if ($pp->getEnabledTaxonomies($type_arg)) {
                $op_obj = $pp->admin()->getOperationObject('assign', $for_item_type);
                $ops['assign'] = $op_obj->label;
            }

        } elseif ('_term_' == $for_item_source) {
            $op_obj = $pp->admin()->getOperationObject('manage');
            $ops['manage'] = $op_obj->label;

            $op_obj = $pp->admin()->getOperationObject('associate');
            $ops['associate'] = $op_obj->agent_label;

        } elseif (in_array($for_item_source, ['pp_group', 'pp_net_group'], true)) {
            $op_obj = $pp->admin()->getOperationObject('manage', $for_item_type);
            $ops['manage'] = $op_obj->label;
        }

        return $ops;
    }

    public static function actExceptionsStatusUi($for_type, $args = [])
    {
        $defaults = ['operation' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!in_array($operation, ['read', 'publish_topics', 'publish_replies'], true) && ('attachment' != $for_type)) {
            echo '<p class="pp-checkbox" style="white-space:nowrap">'
                . '<input type="checkbox" id="pp_select_cond_post_status_unpub" name="pp_select_x_cond[]" value="post_status:{unpublished}"> '
                . '<label for="pp_select_cond_post_status_unpub">' . esc_html__('(unpublished)', 'press-permit-core') . '</label>'
                . '</p>';
        }
    }

    public static function fltExceptionViaTypes($types, $for_item_source, $for_type, $operation, $mod_type)
    {
        if ('_term_' == $for_item_source) {
            foreach (presspermit()->getEnabledTaxonomies(['object_type' => false], 'object') as $taxonomy => $tx_obj) {
                if ('nav_menu' == $taxonomy) {    // @todo: use labels_pp property?
                    if (in_array(get_locale(), ['en_EN', 'en_US'])) {
                        $tx_obj->labels->name = __('Nav Menus (Legacy)', 'press-permit-core');
                    } else {
                        $tx_obj->labels->name .= ' (' . __('Legacy', 'press-permit-core') . ')';
                    }
                }
            
                $types[$taxonomy] = $tx_obj->labels->name;
            }

            if ('manage' != $operation)
                unset($types['nav_menu']);
        }

        return $types;
    }
}

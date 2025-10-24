<?php

namespace PublishPress\Permissions\UI;

/**
 * AgentsDynamicUI class
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2024, PublishPress
 *
 */
class AgentsDynamicUI
{
    private $agents_js_queue = false;

    // output seach textbox & button, results list, "Add All" button
    public function display($agent_type, $id_suffix, $current_selections = [], $args = [])
    {
        $defaults = [
            'agent_id' => 0,
            'context' => '',
            'label_select' => _x('Select &gt;', 'user', 'press-permit-core'),
            'label_unselect' => _x('&lt; Unselect', 'user', 'press-permit-core'),
            'display_stored_selections' => true,
            'create_dropdowns' => false,
            'width' => '',
            'label_headline' => true,
            'multi_select' => true,
            'use_selection_js' => true,
        ];

        $args = apply_filters('presspermit_agents_selection_ui_args', array_merge($defaults, $args), $agent_type, $id_suffix);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();
        
        $width = ($width) ? "width:{$width}px;" : '';

        $this->registerAjaxScripts($agent_type, $id_suffix, $context, $agent_id, $args);

        if ('user' == $agent_type) {
            if (defined('PP_USER_LASTNAME_SEARCH') && !defined('PP_USER_SEARCH_FIELD')) {
                $default_search_field = 'last_name';
            } elseif (defined('PP_USER_SEARCH_FIELD')) {
                $default_search_field = PP_USER_SEARCH_FIELD;
            } else {
                $default_search_field = '';
            }
        }

        if (true === $label_headline) {
            if ('user' == $agent_type) {
                if ($default_search_field) {
                    $search_caption = esc_html__(ucwords(str_replace('_', ' ', $default_search_field)), 'press-permit-core');
                    $label_headline = sprintf(esc_html__('Find Users by %s', 'press-permit-core'), $search_caption);
                } else {
                    $label_headline = esc_html__('Search Users', 'press-permit-core');
                }
            } else {
                $label_headline = esc_html__('Search Custom Groups', 'press-permit-core');
            }
        }
        ?>
        <div>

        </div>
        <table id="pp-agent-selection_<?php echo esc_attr($id_suffix); ?>-wrapper" class="pp-agents-selection">
            <tr>
                <td id="pp-agent-selection_<?php echo esc_attr($id_suffix); ?> " style="vertical-align:top">
                    <div class="pp-search-box-with-icon-wrapper">
                        <input id="agent_search_text_<?php echo esc_attr($id_suffix); ?>" placeholder="<?php echo esc_attr($label_headline); ?>" type="text" size="18"/>
                        <i class="dashicons dashicons-search"></i>
                    </div>

                    <?php if (('user' == $agent_type)) : ?>
                        <br/>
                        <?php
                        $title = (!defined('PP_USER_SEARCH_META_FIELDS') && $pp->isUserAdministrator()
                            && $pp->getOption('advanced_options') && $pp->getOption('display_hints'))
                            ? esc_html__('For additional fields, define constant PP_USER_SEARCH_META_FIELDS', 'press-permit-core') : '';

                        $fields = ['first_name' => __('First Name', 'press-permit-core'), 'last_name' => __('Last Name', 'press-permit-core'), 'nickname' => __('Nickname', 'press-permit-core')];

                        if (defined('PP_USER_SEARCH_META_FIELDS')) {
                            $custom_fields = str_replace(' ', '', PP_USER_SEARCH_META_FIELDS);
                            $custom_fields = explode(',', $custom_fields);

                            foreach ($custom_fields as $cfield) {
                                $fields[$cfield] = esc_html__(ucwords(str_replace('_', ' ', $cfield)), 'press-permit-core');
                            }
                        }

                        if (isset($fields[$default_search_field])) {
                            unset($fields[$default_search_field]);
                        }

                        $ilim = (defined('PP_USER_SEARCH_META_FIELDS')) ? 6 : 3;

                        for ($i = 0; $i < $ilim; $i++) :
                            ?>
                            <div class="pp-user-meta-search" <?php
                            if ($i > 0 && PWP::empty_GET("pp_search_user_meta_key_{$i}_{$id_suffix}")) {
                                echo ' style="display:none;"';
                            }
                            ?>>
                                <select id="pp_search_user_meta_key_<?php echo esc_attr($i); ?>_<?php echo esc_attr($id_suffix); ?>" autocomplete="off">
                                    <option value=""><?php esc_html_e('(user field)', 'press-permit-core'); ?></option>

                                    <?php foreach ($fields as $field => $lbl) : ?>
                                        <option value="<?php echo esc_attr($field); ?>"><?php echo esc_html($lbl); ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <input id="pp_search_user_meta_val_<?php echo esc_attr($i); ?>_<?php echo esc_attr($id_suffix); ?>" class="pp-user-meta-field" 
                                       type="text" <?php
                                if (PWP::empty_GET("pp_search_user_meta_key_{$i}_{$id_suffix}")) {
                                    echo 'style="display:none"';
                                }
                                ?> title="<?php echo esc_attr($title); ?>" size="8"/>

                                <?php if ($i < $ilim - 1) : ?>
                                    &nbsp;<span class="pp-usermeta-field-more" <?php
                                    if (PWP::empty_GET("pp_search_user_meta_key_{$i}_{$id_suffix}")) {
                                        echo 'style="display:none"';
                                    }
                                    ?>>+</span>

                                <?php endif; ?>

                            </div>
                        <?php endfor; ?>

                    <?php endif; ?>

                    <?php if (('user' == $agent_type) && $pp->getOption('user_search_by_role')) : ?>
                        <select id="pp_search_role_<?php echo esc_attr($id_suffix); ?>" class="pp-search-role" autocomplete="off">
                            <option value=""><?php esc_html_e('(any WP role)', 'press-permit-core'); ?></option>
                            <?php wp_dropdown_roles(); ?>
                        </select>
                    <?php endif; ?>
                </td>

                <?php if ($display_stored_selections) : ?>
                    <td style="vertical-align:top" class="pp-members-current">
                    </td>
                <?php endif; ?>

            </tr>

            <tr>
                <td>
                    <h4>
                        <?php esc_html_e('Search Results:', 'press-permit-core'); ?>
                        <img class="waiting" style="display:none;float:right"
                             src="<?php echo esc_url(admin_url('images/wpspin_light.gif')) ?>" alt=""/>
                    </h4>

                    <select id="agent_results_<?php echo esc_attr($id_suffix); ?>" class="pp_agent_results" <?php
                    if ($multi_select) : ?>multiple="multiple" style="height:160px;"<?php else : ?>style="display:none;"<?php endif; ?> autocomplete="off">
                    </select>

                    <span id="agent_msg_<?php echo esc_attr($id_suffix); ?>"></span>
                </td>

                <?php
                if ($display_stored_selections) : ?>
                    <td class="pp-members-current">
                        <h4>
                        <?php if (!apply_filters('presspermit_suppress_agents_selection_label', false, $id_suffix, $args)):
                            esc_html_e('Current Selections:', 'press-permit-core');
                        endif;?>
                        </h4>

                        <select id='<?php echo esc_attr($id_suffix); ?>' name='<?php echo esc_attr($id_suffix); ?>[]' multiple='multiple'
                                style='height:160px;<?php echo esc_attr($width); ?>'>

                            <?php
                            if ('user' == $agent_type) {
                                $display_property = (defined('PP_USER_RESULTS_DISPLAY_NAME')) ? 'display_name' : 'user_login';
                            } else {
                                $display_property = 'display_name';
                            }

                            foreach ($current_selections as $agent) : ?>
                                <?php
                                $title = (isset($agent->display_name) && ($agent->user_login != $agent->display_name))
                                    ? esc_attr($agent->display_name)
                                    : '';

                                $data = apply_filters(
                                    'presspermit_agents_selection_ui_attribs',
                                    ['title' => $title, 'user_caption' => $agent->$display_property],
                                    $agent_type,
                                    $id_suffix,
                                    $agent
                                );
                                ?>

                                <option value="<?php echo esc_attr($agent->ID); ?>" title="<?php echo esc_attr($data['title']); ?>" 
                                <?php if (!empty($data['class']))           echo ' class="' . esc_attr($data['class']) . '"'; ?>
                                <?php if (!empty($data['data-startdate']))  echo ' data-startdate="' . esc_attr($data['data-startdate']) . '"'; ?>
                                <?php if (!empty($data['data-enddate']))    echo ' data-enddate="' . esc_attr($data['data-enddate']) . '"'; ?>
                                >
                                <?php echo esc_html($data['user_caption']); ?>
                                </option>
                            <?php endforeach; ?>

                        </select><br/>
                    </td>

                <?php endif; ?>
            </tr>

            <?php do_action('_presspermit_agents_selection_ui_select_pre', $id_suffix); ?>
            <tr>
                <?php do_action('presspermit_agents_selection_ui_select_pre', $id_suffix); ?>

                <td>
                    <button type="button" id="select_agents_<?php echo esc_attr($id_suffix); ?>" class="pp_add button pp-default-button"
                            style="<?php if (!$multi_select) : ?>display:none;<?php endif; ?>">

                        <?php echo esc_html($label_select); ?>
                    </button>
                </td>

                <?php if ($display_stored_selections) : ?>
                    <td class="pp-members-current">
                        <button type="button" id="unselect_agents_<?php echo esc_attr($id_suffix); ?>" class="pp_remove button pp-default-button"
                            style="margin-left: 24px;">
                            <?php echo esc_html($label_unselect); ?></button>
                    </td>
                <?php endif; ?>

            </tr>
        </table>
        <?php
        $csv = ($current_selections) ? implode(',', array_keys($current_selections)) : '';
        $csv = apply_filters('presspermit_agents_selection_ui_csv', $csv, $id_suffix, $current_selections);
        ?>
        <input type="hidden" id="<?php echo esc_attr($id_suffix); ?>_csv" name="<?php echo esc_attr($id_suffix); ?>_csv"
               value="<?php echo esc_attr($csv); ?>"/>
        <?php
    } // end function ajax_selection_ui

    public function display_select2($agent_type, $id_suffix, $current_selections = [], $args = [])
    {
        $defaults = [
            'agent_id' => 0,
            'context' => '',
            'label_select' => _x('Select &gt;', 'user', 'press-permit-core'),
            'label_unselect' => _x('&lt; Unselect', 'user', 'press-permit-core'),
            'display_stored_selections' => true,
            'create_dropdowns' => false,
            'width' => '',
            'label_headline' => true,
            'multi_select' => true,
            'use_selection_js' => true,
        ];

        [$op, $for_item_type] = array_pad(explode(':', $id_suffix, 3), 3, null);
        $args = apply_filters('presspermit_agents_selection_ui_args', array_merge($defaults, $args), $agent_type, $id_suffix);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();
        $pp_plugin_page = presspermitPluginPage();

        $width = ($width) ? "width:{$width}px;" : '';

        $this->registerAjaxScripts($agent_type, $id_suffix, $context, $agent_id, $args);

        if ('user' == $agent_type) {
            if (defined('PP_USER_LASTNAME_SEARCH') && !defined('PP_USER_SEARCH_FIELD')) {
                $default_search_field = 'last_name';
            } elseif (defined('PP_USER_SEARCH_FIELD')) {
                $default_search_field = PP_USER_SEARCH_FIELD;
            } else {
                $default_search_field = '';
            }
        }

        if (true === $label_headline) {
            if ('user' == $agent_type) {
                if ($default_search_field) {
                    $search_caption = esc_html__(ucwords(str_replace('_', ' ', $default_search_field)), 'press-permit-core');
                    $label_headline = sprintf(esc_html__('Find Users by %s', 'press-permit-core'), $search_caption);
                } else {
                    $label_headline = esc_html__('Search Users', 'press-permit-core');
                }
            } else {
                $label_headline = esc_html__('Search Custom Groups', 'press-permit-core');
            }
        }
        ?>
        <table id="pp-agent-selection_<?php echo esc_attr($id_suffix); ?>-wrapper" class="pp-agents-selection">
            <tr style="display: none;">
                <td id="pp-agent-selection_<?php echo esc_attr($id_suffix); ?> " style="vertical-align:top;">
                    <div class="pp-search-box-with-icon-wrapper">
                        <input id="agent_search_text_<?php echo esc_attr($id_suffix); ?>" placeholder="<?php echo esc_attr($label_headline); ?>" type="text" size="18" />
                        <i class="dashicons dashicons-search"></i>
                    </div>

                    <?php if (('user' == $agent_type)) : ?>
                        <br />
                        <?php
                        $title = (!defined('PP_USER_SEARCH_META_FIELDS') && $pp->isUserAdministrator()
                            && $pp->getOption('advanced_options') && $pp->getOption('display_hints'))
                            ? esc_html__('For additional fields, define constant PP_USER_SEARCH_META_FIELDS', 'press-permit-core') : '';

                        $fields = ['first_name' => __('First Name', 'press-permit-core'), 'last_name' => __('Last Name', 'press-permit-core'), 'nickname' => __('Nickname', 'press-permit-core')];

                        if (defined('PP_USER_SEARCH_META_FIELDS')) {
                            $custom_fields = str_replace(' ', '', PP_USER_SEARCH_META_FIELDS);
                            $custom_fields = explode(',', $custom_fields);

                            foreach ($custom_fields as $cfield) {
                                $fields[$cfield] = esc_html__(ucwords(str_replace('_', ' ', $cfield)), 'press-permit-core');
                            }
                        }

                        if (isset($fields[$default_search_field])) {
                            unset($fields[$default_search_field]);
                        }

                        $ilim = (defined('PP_USER_SEARCH_META_FIELDS')) ? 6 : 3;

                        for ($i = 0; $i < $ilim; $i++) :
                        ?>
                            <div class="pp-user-meta-search" <?php
                                                                if ($i > 0 && PWP::empty_GET("pp_search_user_meta_key_{$i}_{$id_suffix}")) {
                                                                    echo ' style="display:none;"';
                                                                }
                                                                ?>>
                                <select id="pp_search_user_meta_key_<?php echo esc_attr($i); ?>_<?php echo esc_attr($id_suffix); ?>" autocomplete="off">
                                    <option value=""><?php esc_html_e('(user field)', 'press-permit-core'); ?></option>

                                    <?php foreach ($fields as $field => $lbl) : ?>
                                        <option value="<?php echo esc_attr($field); ?>"><?php echo esc_html($lbl); ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <input id="pp_search_user_meta_val_<?php echo esc_attr($i); ?>_<?php echo esc_attr($id_suffix); ?>" class="pp-user-meta-field"
                                    type="text" <?php
                                                if (PWP::empty_GET("pp_search_user_meta_key_{$i}_{$id_suffix}")) {
                                                    echo 'style="display:none"';
                                                }
                                                ?> title="<?php echo esc_attr($title); ?>" size="8" />

                                <?php if ($i < $ilim - 1) : ?>
                                    &nbsp;<span class="pp-usermeta-field-more" <?php
                                                                                if (PWP::empty_GET("pp_search_user_meta_key_{$i}_{$id_suffix}")) {
                                                                                    echo 'style="display:none"';
                                                                                }
                                                                                ?>>+</span>

                                <?php endif; ?>

                            </div>
                        <?php endfor; ?>

                    <?php endif; ?>

                    <?php if (('user' == $agent_type) && $pp->getOption('user_search_by_role')) : ?>
                        <select id="pp_search_role_<?php echo esc_attr($id_suffix); ?>" class="pp-search-role" autocomplete="off">
                            <option value=""><?php esc_html_e('(any WP role)', 'press-permit-core'); ?></option>
                            <?php wp_dropdown_roles(); ?>
                        </select>
                    <?php endif; ?>
                </td>

                <?php if ($display_stored_selections) : ?>
                    <td style="vertical-align:top" class="pp-members-current">
                    </td>
                <?php endif; ?>

            </tr>
            <tr>
                <td style="padding-top: <?php echo $display_stored_selections ? '3em' : '0';?>;">
                    <select <?php if ($multi_select):?>multiple="multiple"<?php endif;?> id="v2_agent_search_text_<?php echo esc_attr("{$op}:{$for_item_type}:{$agent_type}"); ?>" name="_select-<?php echo esc_attr("$op-$for_item_type-$agent_type"); ?>[]">
                        <?php
                        // Show the option if user has current selections and not active membership feature
                        if ($display_stored_selections 
                            && !defined('PRESSPERMIT_MEMBERSHIP_VERSION') 
                            && in_array($pp_plugin_page, ['presspermit-edit-permissions', 'presspermit-group-new'], true)) :
                            foreach ($current_selections as $agent) :
                                $first_name = get_user_meta($agent->ID, 'first_name', true);
                                $last_name = get_user_meta($agent->ID, 'last_name', true);
                                $formatted_name = trim($first_name . ' ' . $last_name. ' (' . $agent->user_login . ')');
                                $title = (isset($agent->display_name) && ($agent->user_login != $agent->display_name))
                                    ? esc_attr($agent->display_name)
                                    : '';

                                $data = apply_filters(
                                    'presspermit_agents_selection_ui_attribs',
                                    ['title' => $title, 'user_caption' => $formatted_name],
                                    $agent_type,
                                    $id_suffix,
                                    $agent
                                );
                                ?>
                                <option value="<?php echo esc_attr($agent->ID); ?>" title="<?php echo esc_attr($data['title']); ?>" selected
                                    <?php if (!empty($data['class']))           echo ' class="' . esc_attr($data['class']) . '"'; ?>
                                    <?php if (!empty($data['data-startdate']))  echo ' data-startdate="' . esc_attr($data['data-startdate']) . '"'; ?>
                                    <?php if (!empty($data['data-enddate']))    echo ' data-enddate="' . esc_attr($data['data-enddate']) . '"'; ?>>
                                    <?php echo esc_html($data['user_caption']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <br>
                    <br>
                    <?php do_action('presspermit_agents_selection_ui_select_pre', $id_suffix); ?>
                    <?php if (defined('PRESSPERMIT_MEMBERSHIP_VERSION') && in_array($pp_plugin_page, ['presspermit-edit-permissions', 'presspermit-group-new'], true)) : ?>
                    <br>
                    <button type="button" id="select_agents_<?php echo esc_attr($id_suffix); ?>" class="pp_add button pp-default-button"
                        style="<?php if (!$multi_select) : ?>display:none;<?php endif; ?>margin-top:8px;">

                        <?php echo esc_html($label_select); ?>
                    </button>
                    <?php endif; ?>
                </td>
                <td style="display: none;">
                    <h4>
                        <?php esc_html_e('Search Results:', 'press-permit-core'); ?>
                        <img class="waiting" style="display:none;float:right"
                            src="<?php echo esc_url(admin_url('images/wpspin_light.gif')) ?>" alt="" />
                    </h4>

                    <select id="agent_results_<?php echo esc_attr($id_suffix); ?>" class="pp_agent_results" <?php
                                                                                                            if ($multi_select) : ?>multiple="multiple" style="height:160px;" <?php else : ?>style="display:none;" <?php endif; ?> autocomplete="off">
                    </select>

                    <span id="agent_msg_<?php echo esc_attr($id_suffix); ?>"></span>
                </td>

                <?php
                if ($display_stored_selections) :
                    // Hide current selections if user not active membership feature
                    $is_show_current_selection = !defined('PRESSPERMIT_MEMBERSHIP_VERSION') && in_array($pp_plugin_page, ['presspermit-edit-permissions', 'presspermit-group-new'], true) ? 'display:none;' : '';
                    ?>
                    <td class="pp-members-current" style="<?php echo esc_attr($is_show_current_selection); ?>">
                        <h4>
                            <?php if (!apply_filters('presspermit_suppress_agents_selection_label', false, $id_suffix, $args)):
                                esc_html_e('Current Selections:', 'press-permit-core');
                            endif; ?>
                        </h4>

                        <select id='<?php echo esc_attr($id_suffix); ?>' name='<?php echo esc_attr($id_suffix); ?>[]' multiple='multiple'
                            style='height:160px;<?php echo esc_attr($width); ?>'>

                            <?php
                            if ('user' == $agent_type) {
                                $display_property = (defined('PP_USER_RESULTS_DISPLAY_NAME')) ? 'display_name' : 'user_login';
                            } else {
                                $display_property = 'display_name';
                            }

                            foreach ($current_selections as $agent) : ?>
                                <?php
                                $first_name = get_user_meta($agent->ID, 'first_name', true);
                                $last_name = get_user_meta($agent->ID, 'last_name', true);
                                $agent->$display_property = trim($first_name . ' ' . $last_name. ' (' . $agent->user_login . ')');
                                $title = (isset($agent->display_name) && ($agent->user_login != $agent->display_name))
                                    ? esc_attr($agent->display_name)
                                    : '';

                                $data = apply_filters(
                                    'presspermit_agents_selection_ui_attribs',
                                    ['title' => $title, 'user_caption' => $agent->$display_property],
                                    $agent_type,
                                    $id_suffix,
                                    $agent
                                );
                                ?>

                                <option value="<?php echo esc_attr($agent->ID); ?>" title="<?php echo esc_attr($data['title']); ?>"
                                    <?php if (!empty($data['class']))           echo ' class="' . esc_attr($data['class']) . '"'; ?>
                                    <?php if (!empty($data['data-startdate']))  echo ' data-startdate="' . esc_attr($data['data-startdate']) . '"'; ?>
                                    <?php if (!empty($data['data-enddate']))    echo ' data-enddate="' . esc_attr($data['data-enddate']) . '"'; ?>>
                                    <?php echo esc_html($data['user_caption']); ?>
                                </option>
                            <?php endforeach; ?>

                        </select><br />
                        <button type="button" id="unselect_agents_<?php echo esc_attr($id_suffix); ?>" class="pp_remove button pp-default-button"
                            style="margin: 8px 0 0 8px;"><?php echo esc_html($label_unselect); ?></button>
                    </td>

                <?php endif; ?>
            </tr>
        </table>
        <?php
        $csv = ($current_selections) ? implode(',', array_keys($current_selections)) : '';
        $csv = apply_filters('presspermit_agents_selection_ui_csv', $csv, $id_suffix, $current_selections);
        ?>
        <input type="hidden" id="<?php echo esc_attr($id_suffix); ?>_csv" name="<?php echo esc_attr($id_suffix); ?>_csv"
            value="<?php echo esc_attr($csv); ?>" />
        <?php
    }

    private function registerAjaxScripts($agent_type, $id_sfx, $context = '', $agent_id = 0, $args = [])
    {
        global $pagenow, $wp_scripts;

        // Only load Select2 assets on the presspermit admin page or not registered
        // Always load Select2 assets on term.php page, or if not registered, or on presspermit admin pages
        if (!wp_script_is('select2', 'registered') 
            || false !== strpos(presspermitPluginPage(), 'presspermit')
            || in_array($pagenow, ['term.php', 'edit-tags.php'], true)
        ) {
            wp_enqueue_style('presspermit-select2-css', PRESSPERMIT_URLPATH . "/common/lib/select2-4.0.13/css/select2.min.css", array(), PRESSPERMIT_VERSION, 'screen');
            wp_enqueue_script('presspermit-select2-js', PRESSPERMIT_URLPATH . "/common/lib/select2-4.0.13/js/select2.full.min.js", ['jquery'], PRESSPERMIT_VERSION);
        }

        // note: this is also done in AdminFiltersItemUI() constructor
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-listbox', PRESSPERMIT_URLPATH . "/common/js/listbox{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION, true);
        $wp_scripts->in_footer[] = 'presspermit-listbox'; // otherwise it will not be printed in footer

        if ('user' == $agent_type) {
            // note: agent_id in this context is the group ID for which we are querying users for possible group membership
            $allow_administrator_members = !apply_filters('presspermit_group_omit_administrators', !defined('PP_ADMINS_IN_PERMISSION_GROUPS') || !PP_ADMINS_IN_PERMISSION_GROUPS, $agent_id);
        }

        if (!empty($args['create_dropdowns'])) {
            wp_localize_script(
                'presspermit-listbox', 
                'ppListbox', 
                [
                    'omit_admins' => !empty($allow_administrator_members) ? '0' : '1', 
                    'metagroups' => 1
                ]
            );

            wp_enqueue_script('presspermit-agent-select', PRESSPERMIT_URLPATH . "/common/js/agent-exception-select{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION, true);

            $arr = array_merge($args, ['agent_type' => $agent_type, 'ajaxurl' => wp_nonce_url(admin_url(''), 'pp-ajax')]);
            wp_localize_script('presspermit-agent-select', 'ppException', $arr);
        } else {
            // @todo: API
            $_args = ['omit_admins' => $allow_administrator_members ? '0' : '1', 'metagroups' => 0];

            if (!PWP::empty_REQUEST('page') && PWP::REQUEST_key_match('page', 'presspermit-edit-permissions')) {
                if ($group = presspermit()->groups()->getGroupByName('[Pending Revision Monitors]')) {
                    if ($group->ID == $agent_id) {
                        $_args['omit_admins'] = 0;
                    }
                }

                if ($group = presspermit()->groups()->getGroupByName('Pending Revision Monitors')) {
                    if ($group->ID == $agent_id) {
                        $_args['omit_admins'] = 0;
                    }
                }

                if ($group = presspermit()->groups()->getGroupByName('[Change Request Notifications]')) {
                    if ($group->ID == $agent_id) {
                        $_args['omit_admins'] = 0;
                    }
                }

                if ($group = presspermit()->groups()->getGroupByName('Change Request Notifications')) {
                    if ($group->ID == $agent_id) {
                        $_args['omit_admins'] = 0;
                    }
                }

                if ($group = presspermit()->groups()->getGroupByName('Scheduled Revision Monitors')) {
                    if ($group->ID == $agent_id) {
                        $_args['omit_admins'] = 0;
                    }
                }

                if ($group = presspermit()->groups()->getGroupByName('[Scheduled Revision Monitors]')) {
                    if ($group->ID == $agent_id) {
                        $_args['omit_admins'] = 0;
                    }
                }
            }

            wp_localize_script('presspermit-listbox', 'ppListbox', $_args);

            if (!apply_filters('presspermit_override_agent_select_js', false)) {
                wp_enqueue_script('presspermit-agent-select', PRESSPERMIT_URLPATH . "/common/js/agent-select{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION, true);
            }
        }

        $wp_scripts->in_footer[] = 'presspermit-agent-select'; // otherwise it will not be printed in footer, as of WP 3.2.1

        $ajaxhandler = (!empty($args['create_dropdowns'])) ? 'got_ajax_dropdowns' : 'got_ajax_listbox';
        wp_localize_script('presspermit-agent-select', 'PPAgentSelect', ['ajaxurl' => wp_nonce_url(admin_url(''), 'pp-ajax'), 'ajaxhandler' => $ajaxhandler]);

        if (!$this->agents_js_queue) {
            $this->agents_js_queue = [];
            add_action('admin_print_footer_scripts', [$this, 'actAjaxSelectionScripts'], 30);
        }

        $suppress_selection_js = !empty($args['suppress_selection_js']);
        $this->agents_js_queue[] = compact('agent_type', 'id_sfx', 'context', 'agent_id', 'suppress_selection_js');
    }

    public function actAjaxSelectionScripts()
    {
        global $pagenow;
        $is_post_page = in_array($pagenow, ['post.php', 'post-new.php']);
        $is_membership_activated = defined('PRESSPERMIT_MEMBERSHIP_VERSION') && in_array(presspermitPluginPage(), ['presspermit-edit-permissions', 'presspermit-group-new'], true);

        // todo: clean up js loading logic
        if ($this->agents_js_queue) {
            $author_selection_only = false;

            if (!apply_filters('presspermit_override_agent_select_js', false) && !wp_script_is('pp_agent_select', 'done')) {
                global $wp_scripts;
                $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
                wp_enqueue_script('presspermit-agent-select', PRESSPERMIT_URLPATH . "/common/js/agent-select{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION, true);
                $wp_scripts->do_item('presspermit-agent-select');
                $author_selection_only = true;
            }

        ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                <?php foreach ($this->agents_js_queue as $args) : ?>
                    presspermitLoadAgentsJS('<?php echo esc_attr($args['id_sfx']); ?>', '<?php echo esc_attr($args['agent_type']); ?>', '<?php echo esc_attr($args['context']); ?>', '<?php echo esc_attr($args['agent_id']); ?>', '<?php echo esc_attr($args['suppress_selection_js']); ?>', <?php if ($author_selection_only) echo 'true'; else echo 'false'; ?>);
                    
                    <?php
                    if (($is_post_page && ('select-author' != $args['id_sfx'])) || !$is_membership_activated) :?>
                        presspermitLoadSelect2AgentsJS('<?php echo esc_attr($args['id_sfx']); ?>', '<?php echo esc_attr($args['agent_type']); ?>', '<?php echo esc_attr($args['context']); ?>', '<?php echo esc_attr($args['agent_id']); ?>', '<?php echo esc_attr($args['suppress_selection_js']); ?>', <?php if ($author_selection_only) echo 'true';                                                                                                                                                                                                                                                                  else echo 'false'; ?>);
                    <?php endif;?>
                <?php endforeach; ?>
                /* ]]> */
            </script>
<?php
        }
    }
}

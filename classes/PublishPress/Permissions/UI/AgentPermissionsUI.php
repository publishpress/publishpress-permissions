<?php

namespace PublishPress\Permissions\UI;

class AgentPermissionsUI
{
    public static function roleAssignmentScripts()
    {
        if (!$agent_type = PWP::REQUEST_key('agent_type')) {
            $agent_type = 'pp_group';
        }

        $agent_id = PWP::REQUEST_int('agent_id');

        $vars = [
            'addRoles' => esc_html__('Add Roles', 'press-permit-core'),
            'clearRole' => esc_html__('Clear', 'press-permit-core'),
            'noConditions' => esc_html__('No statuses selected!', 'press-permit-core'),
            'pleaseReview' => esc_html__('Review the selection below, and then click <strong>Save Roles</strong>.', 'press-permit-core'),
            'alreadyRole' => esc_html__('Role already selected!', 'press-permit-core'),
            'noAction' => esc_html__('No Action selected!', 'press-permit-core'),
            'submissionMsg' => esc_html__('Saving Roles...', 'press-permit-core'),
            'reloadRequired' => esc_html__('Reload form for further changes to this role', 'press-permit-core'),
            'ajaxurl' => wp_nonce_url(admin_url(''), 'pp-ajax'),
        ];

        $vars['agentType'] = $agent_type;
        $vars['agentID'] = $agent_id;

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-role-edit', PRESSPERMIT_URLPATH . "/common/js/role-edit{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION);
        wp_localize_script('presspermit-role-edit', 'ppCred', $vars);

        return $vars;
    }

    public static function exceptionAssignmentScripts()
    {
        $vars = [
            'addExceptions' => esc_html__('Add Specific Permissions', 'press-permit-core'),
            'clearException' => esc_html__('Remove', 'press-permit-core'),
            'pleaseReview' => esc_html__('Review the selection below, and then click <strong>Save Permissions</strong>.', 'press-permit-core'),
            'alreadyException' => esc_html__('Permission already selected!', 'press-permit-core'),
            'noAction' => esc_html__('No Action selected!', 'press-permit-core'),
            'submissionMsg' => esc_html__('Saving Permissions...', 'press-permit-core'),
            'reloadRequired' => esc_html__('Reload form for further changes to this permission', 'press-permit-core'),
            'mirrorDone' => esc_html__('Permissions mirrored. Reload form to view newly saved permissions.', 'press-permit-core'),
            'convertDone' => esc_html__('Permissions converted. Reload form to view newly saved permissions.', 'press-permit-core'),
            'noMode' => esc_html__('No Qualification selected!', 'press-permit-core'),
            'ajaxurl' => wp_nonce_url(admin_url(''), 'pp-ajax'),
        ];

        if (!$vars['agentType'] = PWP::REQUEST_key('agent_type')) {
            $vars['agentType'] = 'pp_group';
        }

        $vars['agentID'] = PWP::REQUEST_int('agent_id');

        // Simulate Nav Menu setup
        require_once(PRESSPERMIT_CLASSPATH . '/UI/ItemsMetabox.php');

        $vars['noItems'] = esc_html__('No items selected!', 'press-permit-core');
        $vars['noParent'] = esc_html__('(no parent)', 'press-permit-core');
        $vars['none'] = esc_html__('(none)', 'press-permit-core');

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

        wp_enqueue_script('presspermit-item-metabox', PRESSPERMIT_URLPATH . "/common/js/item-metabox{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION);
        wp_localize_script('presspermit-item-metabox', 'ppItems', ['ajaxurl' => wp_nonce_url(admin_url(''), 'pp-ajax'), 'noResultsFound' => esc_html__('No results found.', 'press-permit-core')]);

        wp_enqueue_script('presspermit-exception-edit', PRESSPERMIT_URLPATH . "/common/js/exception-edit{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION);
        wp_localize_script('presspermit-exception-edit', 'ppRestrict', $vars);

        wp_enqueue_script('common');
        wp_enqueue_script('postbox');

        return $vars;
    }

    private static function drawTypeOptions($type_objects, $args = [])
    {
        $defaults = ['option_any' => false, 'option_na' => false];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!$type_objects) {
            return;
        }

        echo "<option class='pp-opt-none' value=''>" . esc_html__('select...', 'press-permit-core') . '</option>';

        foreach ($type_objects as $_type => $type_obj) {
            if ('wp_navigation' == $_type) {    // @todo: use labels_pp property?
                if (in_array(get_locale(), ['en_EN', 'en_US'])) {
                    $type_obj->labels->singular_name = __('Nav Menu (Block)', 'press-permit-core');
                } else {
                    $type_obj->labels->singular_name .= ' (' . __('Block', 'press-permit-core') . ')';
                }
            }

            echo "<option value='" . esc_attr($type_obj->name) . "'>" . esc_html($type_obj->labels->singular_name) . '</option>';
        }

        if ($option_na) {
            if (presspermit()->role_defs->direct_roles) {
                echo "<option value='-1'>" . esc_html__('n/a', 'press-permit-core') . '</option>';
            }
        }
    }

    private static function selectExceptionsUi($type_objects, $taxonomy_objects, $args = [])
    {
        $pp = presspermit();

        // Discourage anon/all metagroups having read exceptions for specific posts. Normally, that's what post visibility is for.
        $is_all_anon = (isset($args['agent']) && !empty($args['agent']->metagroup_id) && in_array($args['agent']->metagroup_id, ['wp_anon', 'wp_all']));
        $is_anon = (isset($args['agent']) && !empty($args['agent']->metagroup_id) && $args['agent']->metagroup_id === 'wp_anon');
        ?>
        <img id="pp_add_exception_waiting" class="waiting" style="display:none;position:absolute" src="<?php echo esc_url(admin_url('images/wpspin_light.gif')) ?>" alt="" />
        <table id="pp_add_exception">
            <thead>
                <tr>
                    <th><?php esc_html_e('Post Type', 'press-permit-core'); ?></th>
                    <th class="pp-select-x-operation" style="display:none"><?php esc_html_e('Operation', 'press-permit-core'); ?></th>
                    <th class="pp-select-x-mod-type" style="display:none"><?php esc_html_e('Adjustment', 'press-permit-core'); ?></th>
                    <th class="pp-select-x-via-type" style="display:none"><?php esc_html_e('Qualification', 'press-permit-core'); ?></th>
                    <th class="pp-select-x-status" style="display:none"><?php esc_html_e('Statuses', 'press-permit-core'); ?></th>
                    <th class="pp-add-exception" style="display:none"></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>

                        <select name="pp_select_x_for_type" autocomplete="off">
                            <?php
                            $type_objects = apply_filters('presspermit_append_exception_types', $pp->admin()->orderTypes(apply_filters('presspermit_exception_types', $type_objects)));

                            if (!empty($args['external']))
                                $type_objects = array_merge($type_objects, $args['external']);

                            if ($is_anon)
                                unset($type_objects['attachment']);

                            // Remove Term option for anonymous/all metagroups if it was added
                            if (isset($args['agent']) && !empty($args['agent']->metagroup_id) 
                                && in_array($args['agent']->metagroup_id, ['wp_anon', 'wp_all'], true)) {
                                unset($type_objects['_term_']);
                            }

                            self::drawTypeOptions($type_objects, ['option_any' => true]);
                            do_action('presspermit_exception_types_dropdown', $args);

                            ?></select>
                    </td>

                    <td class="pp-select-x-operation" style="display:none"></td>
                    <td class="pp-select-x-mod-type" style="display:none"></td>

                    <td class="pp-select-x-via-type" style="display:none"><select name="pp_select_x_via_type" autocomplete="off"></select>

                        <div class="pp-select-x-assign-for" id="pp_select_x_assign_for" style="display:none">
                            <p class="pp-checkbox">
                                <input type="checkbox" name="pp_select_x_item_assign" id="pp_select_x_item_assign" checked="checked" value="1">
                                <label for="pp_select_x_item_assign"><?php esc_html_e('for item', 'press-permit-core'); ?></label>
                            </p>

                        </div>
                    </td>

                    <td class="pp-select-x-status" style="display:none">
                        <p class="pp-checkbox">
                            <input type="checkbox" id="pp_select_x_cond_" name="pp_select_x_cond[]" checked="checked" value="" />
                            <label id="lbl_pp_select_x_cond_" for="pp_select_x_cond_"> <?php esc_html_e('All Statuses', 'press-permit-core'); ?></label>
                        </p>
                    </td>

                    <td class="pp-select-items" style="display:none;padding-right:0">
                        <?php self::itemSelectUI(array_merge($type_objects, $taxonomy_objects)); ?>
                    </td>

                </tr>
            </tbody>
        </table>

        <?php if ($is_all_anon) : ?>
            <div id="pp-all-anon-warning" class="pp-red" style="display:none;">
                <?php esc_html_e('Warning: Content hidden by these Permissions will be displayed if PP is deactivated. Consider setting a private Visibility on Edit Post screen instead.', 'press-permit-core'); ?>
            </div>

            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function($) {
                    $(document).on('change', 'select[name="pp_select_x_for_type"]', function() {
                        $('#pp-all-anon-warning').hide();
                    });

                    var handle_anon_warning = function() {
                        if (('read' == $('input[name="pp_select_x_operation"]').val()) &&
                            ('additional' != $('input[name="pp_select_x_mod_type"]').val()) &&
                            ('pp-post-object' == $('select[name="pp_select_x_via_type"] option:selected').attr('class'))) {
                            $('#pp-all-anon-warning').show();
                        } else {
                            $('#pp-all-anon-warning').hide();
                        }
                    }

                    $(document).on('pp_exceptions_ui', handle_anon_warning);
                    $(document).on('change', 'select[name="pp_select_x_via_type"]', handle_anon_warning);
                });
                /* ]]> */
            </script>
        <?php endif; ?>

        <div class='pp-ext-promo'>
            <?php
            if (!$pp->moduleActive('collaboration') && $pp->getOption('display_extension_hints')) : ?>
                <div>
                    <?php
                    esc_html_e('To assign page-specific Permissions for editing, parent selection or term assignment, enable the Editing Permissions feature.', 'press-permit-core');
                    ?>
                </div>
            <?php endif;

            if (
                function_exists('bbp_get_version') && !$pp->moduleActive('compatibility')
                && $pp->getOption('display_extension_hints')
            ) : ?>
                <div>
                    <?php
                    if (presspermit()->isPro())
                        esc_html_e('To assign forum-specific Permissions for bbPress, activate the Compatibility Pack feature.', 'press-permit-core');
                    else
                        printf(
                            esc_html__('To assign forum-specific Permissions for bbPress, %1$supgrade to Permissions Pro%2$s and enable the Compatibility Pack feature.', 'press-permit-core'),
                            '<a href="https://publishpress.com/pricing/">',
                            '</a>'
                        );
                    ?>
                </div>
            <?php endif; ?>
        </div><?php
            }

            private static function selectRolesUI($type_objects, $taxonomy_objects)
            {
                $pp = presspermit();

                ?>
        <img id="pp_add_role_waiting" class="waiting" style="display:none;position:absolute" src="<?php echo esc_url(admin_url('images/wpspin_light.gif')) ?>" alt="" />
        <table id="pp_add_role">
            <thead>
                <tr>
                    <th><?php esc_html_e('Post Type', 'press-permit-core'); ?></th>
                    <th class="pp-select-role" style="display:none"><?php echo esc_html__('Role', 'press-permit-core'); ?></th>
                    <th class="pp-select-cond" style="display:none"><?php esc_html_e('For Statuses', 'press-permit-core'); ?></th>
                    <th class="pp-add-site-role" style="display:none"></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <select name="pp_select_type" autocomplete="off">
                            <?php
                            self::drawTypeOptions($type_objects, ['option_na' => true]);
                            do_action('presspermit_role_types_dropdown');
                            ?></select>
                    </td>

                    <td class="pp-select-role" style="display:none"><select name="pp_select_role" autocomplete="off"></select></td>

                    <td class="pp-select-cond" id="pp_cond_ui" style="display:none">
                        <p class="pp-checkbox">
                            <input type="checkbox" id="pp_select_cond_" name="pp_select_cond[]" checked="checked" value="" />
                            <label id="lbl_pp_select_cond_" for="pp_select_cond_"> <?php esc_html_e('Standard statuses', 'press-permit-core'); ?>
                            </label>
                        </p>
                        <p class="pp-checkbox pp_select_private_status" style="display:none">
                            <input type="checkbox" id="pp_select_cond_post_status_private" name="pp_select_cond[]" value="post_status:private" style="display:none" />
                            <label for="pp_select_cond_post_status_private"> <?php esc_html_e('Status: Private', 'press-permit-core'); ?></label>
                        </p>
                    </td>

                    <td class="pp-add-site-role" style="display:none">
                        <input id="pp_add_site_role" class="button-secondary pp-default-button" type="submit" name="pp_add_site_role" value="<?php esc_attr_e('Add Role', 'press-permit-core'); ?>" />
                    </td>

                </tr>
            </tbody>
        </table>

        <div class='pp-ext-promo'>
                <div>
                    <?php
                    if (function_exists('bbp_get_version') && !$pp->moduleActive('compatibility') && $pp->getOption('display_extension_hints')) {
                        if (presspermit()->isPro()) {
                            esc_html_e('To assign roles for bbPress forums, activate the Compatibility Pack feature.', 'press-permit-core');
                        } else {
                            printf(
                                esc_html__('To assign roles for bbPress forums, %1$supgrade to Permissions Pro%2$s and enable the Compatibility Pack feature.', 'press-permit-core'),
                                '<a href="https://publishpress.com/pricing/">',
                                '</a>'
                            );
                        }
                    }
                    ?>
                </div>

                <?php if ((defined('PUBLISHPRESS_REVISIONS_VERSION') || defined('REVISIONARY_VERSION')) && !$pp->moduleActive('collaboration') && $pp->getOption('display_extension_hints')) : ?>
                <div>
                    <?php esc_html_e('To assign page-specific PublishPress Revision permissions, enable the Editing Permissions feature.', 'press-permit-core'); ?>
                </div>
            <?php endif; ?>
        </div><?php
            }

            private static function itemSelectUI($type_objects)
            {
                require_once(PRESSPERMIT_CLASSPATH . '/UI/ItemsMetabox.php');

                add_filter('get_terms_args', [__CLASS__, 'fltTermSelectNoPaging'], 50, 2);

                foreach ($type_objects as $type_obj) {
                    if (defined('PP_' . strtoupper($type_obj->name) . '_NO_EXCEPTIONS') || defined('PP_NO' . strtoupper($type_obj->name) . '_EXCEPTIONS')) {
                        continue;
                    }

                    $type_obj = apply_filters('presspermit_permit_items_meta_box_object', $type_obj);

                    if (post_type_exists($type_obj->name))
                        $metabox_function = ['\PublishPress\Permissions\UI\ItemsMetabox', 'post_type_meta_box'];

                    elseif (taxonomy_exists($type_obj->name))
                        $metabox_function = ['\PublishPress\Permissions\UI\ItemsMetabox', 'taxonomy_meta_box'];

                    elseif (in_array($type_obj->name, ['pp_group', 'pp_net_group'], true))
                        $metabox_function = ['\PublishPress\Permissions\UI\ItemsMetabox', 'group_meta_box'];

                    elseif (!$metabox_function = apply_filters('presspermit_item_select_metabox_function', '', $type_obj))
                        continue;

                    add_meta_box("select-exception-{$type_obj->name}", sprintf(esc_html__('Select %s', 'press-permit-core'), $type_obj->labels->name), $metabox_function, 'edit-exceptions', 'side', 'default', $type_obj);
                }
                ?>
        <div id="nav-menus-frame">
            <div id="menu-settings-column" class="metabox-holder pp-menu-settings-column">
                <form id="nav-menu-meta" class="nav-menu-meta" method="post" enctype="multipart/form-data">
                    <?php
                    wp_nonce_field('add-exception_item', 'menu-settings-column-nonce');
                    ?>
                    <?php do_meta_boxes('edit-exceptions', 'side', null); ?>
                </form>
            </div><?php
                    ?>
            <div class="nav-tabs-wrapper" style="display:n-one">
                <div class="nav-tabs">
                    <span class="nav-tab menu-add-new nav-tab-active"></span>
                    <ul id="menu-to-edit" class="menu ui-sortable"> </ul>
                </div><?php
                        ?>
            </div><?php
                    ?>
        </div><?php
            }

            private static function confirmRolesUI()
            {
                ?>
        <div id="pp_site_selection_msg" class="pp-error-note pp-edit-msg" style="display:none"></div>

        <div id="pp_review_roles" class="pp-save-roles" style="display:none">

            <table id="pp_tbl_role_selections" class="table table-responsive">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Post Type', 'press-permit-core'); ?></th>
                        <th><?php echo esc_html__('Role', 'press-permit-core'); ?></th>
                        <th><?php esc_html_e('Status', 'press-permit-core'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>

            <div id="pp_save_roles">
                <p class="submit">
                    <input id="submit_roles" class="button button-primary pp-button" type="submit" value="<?php esc_attr_e('Save Roles', 'press-permit-core'); ?>" name="submit">
                </p>
            </div>
        </div>
        <?php
            }

            private static function confirmExceptionsUI()
            {
                ?>
        <div id="pp_item_selection_msg" class="pp-error-note pp-edit-msg" style="display:none"></div>

        <div id="pp_review_exceptions" class="pp-save-exceptions" style="display:none">
            <table id="pp_tbl_exception_selections" class="table table-responsive">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Post Type', 'press-permit-core'); ?></th>
                        <th><?php esc_html_e('Operation', 'press-permit-core'); ?></th>
                        <th><?php esc_html_e('Adjustment', 'press-permit-core'); ?></th>
                        <th><?php esc_html_e('Qualification', 'press-permit-core'); ?></th>
                        <th></th>
                        <th><?php esc_html_e('Status', 'press-permit-core'); ?></th>
                        <th><a class="pp_clear_all" href="javascript:void(0)"><?php esc_html_e('Remove', 'press-permit-core'); ?></a></th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>

            <div id="pp_save_exceptions">
                <p class="submit">
                    <input id="submit_exc" class="button button-primary pp-button" type="submit" value="<?php esc_attr_e('Save Permissions', 'press-permit-core'); ?>" name="submit">
                </p>

            </div>
        </div>
        <?php
            }

            public static function drawGroupPermissions($agent_id, $agent_type, $url, $wp_http_referer = '', $args = [])
            {
                $pp = presspermit();

                $post_types = $pp->admin()->orderTypes($pp->getEnabledPostTypes([], 'object'));
                $taxonomies = $pp->admin()->orderTypes($pp->getEnabledTaxonomies(['object_type' => false], 'object'));

                // Filter post types based on pp_include_permission_screen setting
                foreach ($post_types as $post_type => $post_type_obj) {
                    $include_in_permission = $pp->getOption("pp_include_permission_screen_{$post_type}");
                    // If explicitly set to '0', exclude this post type from permission screens
                    if ($include_in_permission === '0') {
                        unset($post_types[$post_type]);
                    }
                    // If not set or set to '1', keep it (default is enabled)
                }

                $perms = [];

                if (('pp_group' == $agent_type) && ($group = $pp->groups()->getGroup($agent_id)))
                    $is_wp_role = ('wp_role' == $group->metagroup_type);

                $perms['exceptions'] = esc_html__('Add Specific Permissions', 'press-permit-core');

                if (empty($group) || !in_array($group->metagroup_id, ['wp_anon', 'wp_all']) || defined('PP_ALL_ANON_ROLES'))
                    $perms['roles'] = esc_html__('Add Extra Roles', 'press-permit-core');

                if (!isset($perms['roles']))
                    $current_tab = 'pp-add-exceptions';
                elseif (!isset($perms['roles']))
                    $current_tab = 'pp-add-roles';
                elseif (!$current_tab = get_user_option('pp-permissions-tab'))
                    $current_tab = (!isset($perms['roles'])) ? 'pp-add-roles' : 'pp-add-exceptions';

                if (('user' != $agent_type) && (($args['agent']->metagroup_type != 'wp_role') || !in_array($args['agent']->metagroup_id, ['wp_anon', 'wp_all']))) {
                    $perms['clone'] = esc_html__('Copy', 'press-permit-core');
                }

                // --- add permission tabs ---
                echo "<ul id='pp_add_permission_tabs' class='pp-list_horiz'>";
                foreach ($perms as $perm_type => $_caption) {
                    $class = ("pp-add-$perm_type" == $current_tab) ? 'agp-selected_agent' : 'agp-unselected_agent';

                    echo "<li class='agp-agent pp-add-" . esc_attr($perm_type) . " pp-add-permissions " . esc_attr($class) . "'><a class='pp-add-" . esc_attr($perm_type) . "' href='javascript:void(0)'>"
                        . esc_html($_caption) . '</a></li>';
                }
                echo '</ul>';

                // --- divs for add Roles / Exceptions ---
                $arr = array_keys($perms);
                $first_perm_type = reset($arr);
                foreach (array_keys($perms) as $perm_type) {
                    $display_style = ("pp-add-$perm_type" == $current_tab) ? '' : ';display:none';
                    echo "<div class='pp-agents pp-group-box pp-add-permissions pp-add-" . esc_attr($perm_type) . "' style='clear:both" . esc_attr($display_style) . "'>";
                    echo '<div>';

                    if ('roles' == $perm_type) {
                        // temp workaround for bbPress
                        self::selectRolesUI(array_diff_key($post_types, array_fill_keys(['topic', 'reply'], true)), $taxonomies);
                    } elseif ('exceptions' == $perm_type) {
                        if (!isset($args['external']))
                            $args['external'] = [];

                        $post_types = Arr::subset($post_types, $pp->getEnabledPostTypes(['layer' => 'exceptions']));
                        self::selectExceptionsUi(array_diff_key($post_types, array_fill_keys(['topic', 'reply'], true)), $taxonomies, $args);
                    }
                ?>

            <form id="group-<?php echo esc_attr($perm_type); ?>-selections" action="<?php echo esc_url($url); ?>" method="post" <?php do_action('presspermit_group_edit_form_tag'); ?>>
                <?php wp_nonce_field("pp-update-{$perm_type}_" . $agent_id, "_pp_nonce_$perm_type");

                    if (in_array($agent_type, ['pp_group', 'pp_net_group'])) {
                        wp_nonce_field('pp-update-group_' . $agent_id);
                    }
                ?>
                <?php
                    if ('clone' == $perm_type) {
                        self::selectCloneUI($args['agent']);
                    }

                    if ($wp_http_referer) : ?>
                    <input type="hidden" name="wp_http_referer" value="<?php echo esc_url($wp_http_referer); ?>" />
                <?php endif; ?>
                <input type="hidden" name="action" value="pp_update<?php echo esc_attr($perm_type); ?>" />
                <input type="hidden" name="agent_id" value="<?php echo esc_attr($agent_id); ?>" />
                <input type="hidden" name="agent_type" value="<?php echo esc_attr($agent_type); ?>" />
                <input type="hidden" name="member_csv" value="-1" />
                <input type="hidden" name="group_name" value="-1" />
                <input type="hidden" name="description" value="-1" />

                <?php
                    if ('roles' == $perm_type) {
                        self::confirmRolesUI();
                    } elseif ('exceptions' == $perm_type) {
                        self::confirmExceptionsUI();
                    }

                    // form close tag output by php due to code autoformatting issue
                    echo '</form>';

                    echo '</div></div>';
                } // end foreach perm_type (roles, exceptions)

                $args['agent_type'] = $agent_type;
                $roles = $pp->getRoles($agent_id, $agent_type, ['post_types' => $post_types, 'taxonomies' => $taxonomies]);

                $args['class'] = ('user' == $agent_type) ? 'pp-user-roles' : 'pp-group-roles';
                $args['agent_type'] = $agent_type;
                self::currentRolesUI($roles, $args);

                $post_types[''] = ''; // also retrieve exceptions for (all) post type

                $_args = [
                    'assign_for' => '',
                    'agent_type' => $agent_type,
                    'agent_id' => $agent_id,
                    'post_types' => array_keys($post_types),
                    'taxonomies' => array_keys($taxonomies),
                    'return_raw_results' => true
                ];

                if (PWP::empty_REQUEST('show_propagated')) {
                    $_args['inherited_from'] = 0;
                }

                // todo: Determine how exception items can become orphaned, eliminate this routine check
                require_once(PRESSPERMIT_CLASSPATH . '/DB/Permissions.php');
                \PublishPress\Permissions\DB\Permissions::expose_orphaned_exception_items();

                $exc = $pp->getExceptions($_args);
                $args['class'] = ('user' == $agent_type) ? 'pp-user-roles' : 'pp-group-roles';
                $args['hidden_exceptions'] = \PublishPress\Permissions\DB\Permissions::$hidden_exceptions;

                self::currentExceptionsUI($exc, $args);

                do_action('presspermit_group_roles_ui', $agent_type, $agent_id);
                ?>
                <script type="text/javascript">
                    /* <![CDATA[ */
                    jQuery(document).ready(function ($) {
                        // Store original order for each table
                        $('table.table-sortable').each(function () {
                            var $tbody = $(this).find('tbody');
                            $tbody.data('original-order', $tbody.children('tr').toArray());
                        });

                        $('table.table-sortable th.sortable').css('cursor', 'pointer').on('click', function () {
                            var $th = $(this);
                            var $table = $th.closest('table.table-sortable');
                            var colIndex = $th.index();
                            if (!$th.data('sort-state')) {
                                if ($th.hasClass('asc')) {
                                    $th.data('sort-state', 'asc');
                                } else if ($th.hasClass('desc')) {
                                    $th.data('sort-state', 'desc');
                                }
                            }
                            var state = $th.data('sort-state') || 'none'; // none, asc, desc

                            // Cycle state: none -> asc -> desc -> none
                            if (state === 'none') {
                                state = 'asc';
                            } else if (state === 'asc') {
                                state = 'desc';
                            } else {
                                state = 'none';
                            }
                            $th.data('sort-state', state);

                            // Remove sort classes and data from other headers
                            $table.find('th.sortable').not($th).removeClass('asc desc').data('sort-state', 'none');
                            $th.removeClass('asc desc');
                            if (state === 'asc') {
                                $th.addClass('asc');
                            } else if (state === 'desc') {
                                $th.addClass('desc');
                            }

                            var $tbody = $table.find('tbody');
                            var $rows;
                            if (state === 'none') {
                                // Restore original order from data
                                var originalRows = $tbody.data('original-order');
                                if (originalRows) {
                                    $tbody.empty();
                                    $.each(originalRows, function (idx, row) {
                                        $tbody.append(row);
                                    });
                                }
                            } else {
                                var asc = (state === 'asc');
                                $rows = $tbody.children('tr').get().sort(function (a, b) {
                                    var aCol = $(a).children('td').eq(colIndex);
                                    var bCol = $(b).children('td').eq(colIndex);
                                    var aText = aCol.text().toLowerCase();
                                    var bText = bCol.text().toLowerCase();
                                    if (aCol.data('sort')) aText = aCol.data('sort').toLowerCase();
                                    if (bCol.data('sort')) bText = bCol.data('sort').toLowerCase();
                                    if (aText < bText) return asc ? -1 : 1;
                                    if (aText > bText) return asc ? 1 : -1;
                                    return 0;
                                });
                                $tbody.empty();
                                $.each($rows, function (idx, row) {
                                    $tbody.append(row);
                                });
                            }
                        });
                    });
                    /* ]]> */
                </script>
                <?php
            }

            public static function currentRolesUI($roles, $args = [])
            {
                $defaults = ['read_only' => false, 'caption' => '', 'class' => 'pp-group-roles', 'link' => '', 'agent_type' => '', 'show_groups_link' => false];
                $args = array_merge($defaults, $args);
                foreach (array_keys($defaults) as $var) {
                    $$var = $args[$var];
                }

                if (!$caption) {
                    $caption = (('user' == $agent_type) && (!empty($args['context']) && ('edit-user' == $args['context'])))
                        ? sprintf(esc_html__('Extra Roles %1$s(for this user)%2$s', 'press-permit-core'), '', '')
                        : esc_html__('Extra Roles', 'press-permit-core');
                }

                $type_roles = [];

                if (!$roles)
                    return;

                // todo: still necessary?
                $has_roles = false;
                foreach (array_keys($roles) as $key) {
                    if (!empty($roles[$key]))
                        $has_roles = true;
                }

                if (!$has_roles)
                    return;

                $pp = presspermit();
                $pp_admin = $pp->admin();

                echo '<div id="pp_current_roles" class="container">';

                if ($show_groups_link) : ?>
                &nbsp;&bull;&nbsp;<small><a class='pp-show-groups' href='#'><?php _e('Show Groups', 'press-permit-core'); ?></a></small>
                <?php endif;

                $_class = ($read_only) ? 'pp-readonly' : '';

                foreach (array_keys($roles) as $role_name) {
                    if (strpos($role_name, ':')) {
                        $arr = explode(':', $role_name);
                        $source_name = $arr[1];
                        $object_type = $arr[2];
                    } else {
                        $object_type = '';

                        $source_name = (0 === strpos($role_name, 'pp_') && strpos($role_name, '_manager')) ? 'term' : 'post';
                    }

                    $type_roles[$source_name][$object_type][$role_name] = true;
                }

                $show_controls = empty($args['context']) || ('edit-permissions' == $args['context']);

                echo "<div class='permission-section'>";
                echo '<div class="section-header">';
                echo '<h2 class="section-title">';
                if ($link) {
                    echo "<a href='" . esc_url($link) . "'>" . esc_html($caption) . "</a>";
                } else {
                    esc_html_e($caption);
                }
                echo ' <span class="badge badge-count" style="display:none"><span class="count-num">0</span> ' . esc_html__('item(s)', 'press-permit-core') . '</span>';
                echo '</h2>';
                echo '<div class="section-controls">';
                if ($show_controls) echo '<span class="expand-icon">▼</span>';
                echo '</div>';
                echo '</div>'; // end section-header
                $section_item_count = 0;
                foreach (array_keys($type_roles) as $source_name) {
                    ksort($type_roles[$source_name]);

                    foreach (array_keys($type_roles[$source_name]) as $object_type) {
                        $any_done = false;
                        $item_count = 0;

                        if ($type_obj = $pp->getTypeObject($source_name, $object_type)) {
                            $type_caption = $type_obj->labels->singular_name;
                        } elseif ('term' == $source_name) {
                            // term management roles will not be applied without editing permissions module, so do not display
                            if (!$pp->moduleActive('collaboration')) {
                                continue;
                            }

                            $type_caption = esc_html__('Term Management', 'press-permit-core');
                        } else {
                            $_role_name = key($type_roles[$source_name][$object_type]);
                            if (false === strpos($_role_name, ':')) {
                                $type_obj = (object)['labels' => (object)['name' => esc_html__('objects', 'press-permit-core'), 'singular_name' => esc_html__('objects', 'press-permit-core')]];
                                $type_caption = esc_html__('Direct-Assigned', 'press-permit-core');
                            } else {
                                if (!defined('PRESSPERMIT_SHOW_DISABLED_TYPE_ROLES')) {
                                    continue;
                                } else {
                                    $type_obj = (object)['labels' => (object)['name' => esc_html__('objects', 'press-permit-core'), 'singular_name' => esc_html__('objects', 'press-permit-core')]];
                                    $type_caption = esc_html__('Disabled Type', 'press-permit-core');
                                }
                            }
                        }

                        if (!empty($type_roles[$source_name][$object_type])) {
                            foreach (array_keys($type_roles[$source_name][$object_type]) as $role_name) {
                                $arr_role_name = explode(':', $role_name);

                                if (!empty($arr_role_name[3]) && ('post_status' == $arr_role_name[3]) && !empty($arr_role_name[4])) {
                                    if (!$status_obj = get_post_status_object($arr_role_name[4])) {
                                        unset($type_roles[$source_name][$object_type][$role_name]);
                                    }
                                }
                            }
                        }

                        // site roles
                        if (!empty($type_roles[$source_name][$object_type])) {
                            $permissions_section_id = 'pp_current_' . esc_attr($source_name) . "_" . esc_attr($object_type) . '_site_roles';
                            echo '<div id="' . esc_attr($permissions_section_id) . '" class="section-content">';
                            ?>
                            <div class="for-type for-type-<?php echo esc_attr($object_type);?>">
                            <div class="permission-type">
                            <div class="subsection-header permission-type-header">
                            <h3 class="section-title permission-type-title">
                                <?php echo esc_html(sprintf(__('%s Roles', 'press-permit-core'), $type_caption)); ?>
                                <span class="badge badge-count" style=""><span class="count-num">0</span> <?php esc_html_e('item(s)', 'press-permit-core');?></span>
                            </h3>
                            <div class="section-controls">
                            <?php if ($show_controls) echo '<span class="expand-icon">▼</span>';?>
                            </div>
                            </div>
                            <?php
                            echo '<div class="subsection-content">';
                            echo '<table class="table table-responsive table-sortable">';
                            echo '<thead>';
                            echo '<tr>';
                            echo '<th class="checkbox-column">';
                            if (!$read_only) {
                                echo '<input id="cb-select-all-' . esc_attr($source_name . '_' . $object_type) . '" type="checkbox" />';
                            }
                            echo '</th>';
                            echo '<th class="role-column sortable asc">' . esc_html__('Role', 'press-permit-core') . '</th>';
                            echo '<th class="status-column sortable">' . esc_html__('Status', 'press-permit-core') . '</th>';
                            echo '<th class="edit-column"></th>';
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';

                            $_arr = $type_roles[$source_name][$object_type];
                            ksort($_arr);
                            foreach (array_keys($_arr) as $role_name) {
                                $arr_role_name = explode(':', $role_name);

                                if (!empty($arr_role_name[3]) && ('post_status' == $arr_role_name[3]) && !empty($arr_role_name[4])) {
                                    if (!$status_obj = get_post_status_object($arr_role_name[4])) {
                                        continue;
                                    }
                                }

                                echo '<tr class="checkbox-row">';
                                echo '<td class="checkbox-column">';
                                if ($read_only) {
                                    if (!empty($any_done)) echo ',&nbsp; ';
                                    $any_done = true;
                                } else {
                                    $ass_id = $roles[$role_name];
                                    $cb_id = 'pp_edit_role_' . str_replace(',', '_', $ass_id);
                                    echo '<input id="' . esc_attr($cb_id) . '" type="checkbox" name="pp_edit_role[]" value="' . esc_attr($ass_id) . '">';
                                }
                                echo '</td>';
                                echo '<td>';
                                $pp_admin->getRoleTitle($role_name, ['include_warnings' => true, 'echo' => true, 'status_suffix' => false]);
                                echo '</td>';
                                echo '<td>' . esc_html(self::getRoleStatusLabel($role_name)) . ' </td>';
                                echo '<td class="edit-column">';
                                if (!$read_only) {
                                    echo '<a href="javascript:void(0)" class="pp_clear" onclick="event.stopPropagation();">' . esc_html__('Delete') . '</a>';
                                }
                                echo '</td>';
                                echo '</tr>';
                                $item_count++;
                                $section_item_count++;
                            }

                            echo '</div>';
                            echo '</tbody>';
                            echo '</table>';
                            echo '<div class="pp-role-bulk-edit" style="margin-top:12px;display:none">';
                            echo "<select><option value=''>" . esc_html(PWP::__wp('Bulk Actions')) . "</option><option value='remove'>"
                                . esc_html(PWP::__wp('Delete')) . '</option></select>'; 
                            echo '<input type="submit" name="" class="button submit-edit-item-role" value="' . esc_attr__('Apply', 'press-permit-core') . '" />';
                            echo '<img class="waiting" style="display:none;" src="' . esc_url(admin_url('images/wpspin_light.gif')) . '" alt="" />';
                            echo '</div>'; // end pp-role-bulk-edit
                            echo '</div>'; // end subsection-content
                            ?>
                            <script type="text/javascript">
                                /* <![CDATA[ */
                                jQuery(document).ready(function ($) {
                                    $('#<?php echo esc_attr($permissions_section_id);?> div.for-type-<?php echo esc_attr($object_type);?> h3 span.count-num').html('<?php echo esc_attr($item_count);?>');
                                    $('#<?php echo esc_attr($permissions_section_id);?> div.for-type-<?php echo esc_attr($object_type);?> span.badge-count').show();
                                });
                                /* ]]> */
                            </script>
                            <?php
                            echo '</div>'; // end permission-type
                            echo '</div>'; // end for-type
                            echo '</div>'; // end section-content
                        }
                    } // end foreach object_type
                } // end foreach source_name
                echo '</div>'; // end permission-section
                echo '</div>'; // end container

                ?>
                <script type="text/javascript">
                    /* <![CDATA[ */
                    jQuery(document).ready(function ($) {
                        $('#pp_current_roles h2 span.count-num').html('<?php echo esc_attr($section_item_count);?>');
                        $('#pp_current_roles h2 span.badge-count').show();
                    });
                    /* ]]> */
                </script>
                <?php

                return true;
            }

            public static function currentExceptionsUI($exc_results, $args = [])
            {
                $defaults = [
                    'read_only'         => false,
                    'class'             => 'pp-group-roles',
                    'item_links'        => false,
                    'caption'           => '',
                    'show_groups_link'  => false,
                    'link'              => '',
                    'agent_type'        => '',
                    'hidden_exceptions' => 0,
                ];

                $args = array_merge($defaults, $args);
                foreach (array_keys($defaults) as $var) {
                    $$var = $args[$var];
                }

                if (!$exc_results)
                    return;

                $pp = presspermit();
                $pp_admin = $pp->admin();
                $pp_groups = $pp->groups();

                require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');

                $exceptions = array_fill_keys(array_merge(['term', 'post'], $pp_groups->getGroupTypes()), []);

                // support imported include exception with no items included
                $item_paths = array_fill_keys(array_keys($exceptions), [esc_html__('(none)', 'press-permit-core')]);

                $post_types = $pp->getEnabledPostTypes([], 'names');
                $taxonomies = $pp->getEnabledTaxonomies(['object_type' => false], 'names');

                // object_type not strictly necessary here, included for consistency with term role array
                foreach ($exc_results as $row) {
                    switch ($row->via_item_source) {
                        case 'term':
                            if ($row->item_id) {
                                $taxonomy = '';
                                $term_id = (int)PWP::ttidToTermid($row->item_id, $taxonomy);

                                if ($row->item_id)
                                    $item_paths['term'][$row->item_id] = \PressShack\Ancestry::getTermPath($term_id, $taxonomy);

                                $via_type = $taxonomy;
                            } else
                                $via_type = $row->via_item_type;

                            break;

                        case 'post':
                            if ($row->item_id)
                                $item_paths['post'][$row->item_id] = \PressShack\Ancestry::getPostPath($row->item_id);
                            // no break

                        default:
                            if ($pp_groups->groupTypeExists($row->via_item_source)) {
                                static $groups_by_id;

                                if (!isset($groups_by_id)) {
                                    $groups_by_id = [];
                                }

                                if (!isset($groups_by_id[$row->via_item_source])) {
                                    $groups_by_id[$row->via_item_source] = [];

                                    foreach ($pp_groups->getGroups($row->via_item_source, ['skip_meta_types' => 'wp_role']) as $group) {
                                        $groups_by_id[$row->via_item_source][$group->ID] = $group->name;
                                    }
                                }

                                if (isset($groups_by_id[$row->via_item_source][$row->item_id])) {
                                    $item_paths[$row->via_item_source][$row->item_id] = $groups_by_id[$row->via_item_source][$row->item_id];
                                }

                                $via_type = $row->via_item_source;
                            } else
                                $via_type = ($row->via_item_type) ? $row->via_item_type : $row->for_item_type;
                    }

                    $_assign_for = trim($row->assign_for);
                    $exceptions[$row->via_item_source][$via_type][$row->for_item_type][$row->operation][$row->mod_type][$row->for_item_status][$row->item_id][$_assign_for] = $row->eitem_id;

                    if (!empty($row->inherited_from)) {
                        $exceptions[$row->via_item_source][$via_type][$row->for_item_type][$row->operation][$row->mod_type][$row->for_item_status][$row->item_id]['inherited_from'] = $row->inherited_from;
                    }
                }

                echo "<div id='pp_current_exceptions' class='container'>"; // wrapper div for all exceptions

                if (PWP::empty_REQUEST('all_types') && !empty($exceptions['post'])) {
                    $all_types = array_fill_keys(array_merge($post_types, $taxonomies, ['']), true);

                    // hide topic, reply assignments even if they are somehow saved/imported without inherited_from value
                    $all_types = array_diff_key($all_types, ['topic' => true, 'reply' => true]);

                    $exceptions['post'] = array_intersect_key($exceptions['post'], $all_types);

                    foreach (array_keys($exceptions['post']) as $key) {
                        $exceptions['post'][$key] = array_intersect_key($exceptions['post'][$key], $all_types);
                    }
                }

                foreach (array_keys($exceptions) as $via_src) {
                    ksort($exceptions[$via_src]);

                    foreach (array_keys($exceptions[$via_src]) as $via_type) {
                        $have_mod_type = [];

                        $section_item_count = 0;

                        if ($via_type_obj = $pp->getTypeObject($via_src, $via_type)) {
                            $via_type_caption = $via_type_obj->labels->singular_name;

                            if ('wp_navigation' == $via_type) {    // @todo: use labels_pp property?
                                if (in_array(get_locale(), ['en_EN', 'en_US'])) {
                                    $via_type_caption = __('Nav Menu (Block)', 'press-permit-core');
                                } else {
                                    $via_type_caption .= ' (' . __('Block', 'press-permit-core') . ')';
                                }
                            } elseif ('nav_menu' == $via_type) {    // @todo: use labels_pp property?
                                if (in_array(get_locale(), ['en_EN', 'en_US'])) {
                                    $via_type_caption = __('Nav Menu (Legacy)', 'press-permit-core');
                                } else {
                                    $via_type_caption .= ' (' . __('Legacy', 'press-permit-core') . ')';
                                }
                            }
                        } else {
                            continue;
                        }

                        $any_redundant = false;

                        $permissions_section_id = 'pp_current_' . esc_attr($via_src) . "_" . esc_attr($via_type) . '_roles';

                        ?>
                        <div id='<?php echo esc_attr($permissions_section_id);?>' class='permission-section'>
                        <?php

                        // @todo: plural solution for js-based count refresh

                        echo '<div class="section-header">';
                        echo '<h2 class="section-title">' . sprintf(esc_html__('%s Permissions', 'press-permit-core'), esc_html($via_type_obj->labels->singular_name)) . ' <span class="badge badge-count" style="display:none"><span class="count-num">0</span> ' . esc_html__('item(s)', 'press-permit-core') . '</span></h2>';
                        echo '<div class="section-controls">';
                        echo '<span class="expand-icon">▼</span>';
                        echo '</div>';
                        echo '</div>';
                        echo "<div class='section-content'>";

                        ksort($exceptions[$via_src][$via_type]);
                        foreach (array_keys($exceptions[$via_src][$via_type]) as $for_type) {
                            $any_both = false;
                            $any_child_only = false;
                            
                            if ($pp_groups->groupTypeExists($for_type))
                                $for_src = $for_type;
                            else
                                $for_src = (taxonomy_exists($for_type) || !$for_type) ? 'term' : 'post';

                            if (!$for_type) {
                                $for_type_obj = (object)['labels' => (object)['singular_name' => esc_html__('(all post types)', 'press-permit-core')]];
                                $for_type_obj->labels->name = $for_type_obj->labels->singular_name;

                            } elseif (!$for_type_obj = $pp->getTypeObject($for_src, $for_type)) {
                                continue;
                            }

                            ?>
                            <div class='for-type for-type-<?php echo esc_attr($for_type);?>'>
                            <?php

                            foreach (array_keys($exceptions[$via_src][$via_type][$for_type]) as $operation) {
                                $item_count = 0;

                                if (!$operation_obj = $pp_admin->getOperationObject($operation, $for_type))
                                    continue;

                                $op_label = (!empty($operation_obj->abbrev)) ? $operation_obj->abbrev : $operation_obj->label;

                                if ('assign' == $operation) {
                                    $op_caption = ($for_type)
                                        ? sprintf(esc_html__('%1$s (%2$s: %3$s)', 'press-permit-core'), $op_label, $for_type_obj->labels->singular_name, $via_type_caption)
                                        : sprintf(esc_html__('%1$s %2$s %3$s', 'press-permit-core'), $op_label, $via_type_caption, $for_type_obj->labels->singular_name);

                                } elseif (in_array($operation, ['manage', 'associate'], true)) {
                                    $op_caption = sprintf(esc_html__('%1$s %2$s', 'press-permit-core'), $op_label, $via_type_caption);

                                } elseif (!empty($for_type_obj->labels->name)) {
                                    $op_caption = sprintf(esc_html__('%1$s %2$s', 'press-permit-core'), $op_label, $for_type_obj->labels->name);

                                } else {
                                    $op_caption = sprintf(esc_html__('%1$s %2$s', 'press-permit-core'), $op_label, $for_type_obj->label);
                                }

                                $tx_caption = '';

                                if (('term' == $via_src) && !in_array($operation, ['manage', 'associate'], true)) {
                                    if (taxonomy_exists($via_type)) {
                                        // "Categories:"
                                        $tx_obj = get_taxonomy($via_type);
                                        $tx_caption = $tx_obj->labels->name;
                                    } else
                                        $tx_caption = '';
                                }

                                $item_label = $item_count === 1 ? __('item', 'press-permit-core') : __('items', 'press-permit-core');

                                $any_status_captions = false;

                                foreach (array_keys($exceptions[$via_src][$via_type][$for_type][$operation]) as $mod_type) {
                                    if (!$mod_type_obj = self::getModificationObject($mod_type))
                                        continue;

                                    foreach (array_keys($exceptions[$via_src][$via_type][$for_type][$operation][$mod_type]) as $status) {
                                        if ($status) {
                                            $_status = explode(':', $status);

                                            $attrib = (count($_status) > 1) ? $_status[0] : 'post_status';

                                            if ('post_status' == $attrib) {
                                                $any_status_captions = true;
                                            }
                                        }
                                    }
                                }

                                ?>
                                <div class='permission-type op-<?php echo esc_attr($operation);?>'>
                                <?php
                                echo '<div class="subsection-header permission-type-header">';
                                echo '<h3 class="section-title permission-type-title">' . esc_html($op_caption) . ' <span class="badge badge-count" style="display:none"><span class="count-num">0</span> ' . esc_html__('item(s)', 'press-permit-core') . '</span></h3>';
                                echo '<div class="section-controls">';
                                echo '<span class="expand-icon">▼</span>';
                                echo '</div>';
                                echo '</div>';
                                echo "<div class='subsection-content'>";

                                echo '<table class="table table-responsive table-sortable">';
                                echo '<thead>';
                                echo '<tr>';
                                echo '<th class="checkbox-column">';
                                echo '<input id="cb-select-all-' . esc_attr($operation) . '_' . esc_attr($for_src) . '_' . esc_attr($via_src) . '_' . esc_attr($via_type) . '" type="checkbox" />';
                                echo '</th>';
                                echo '<th class="icon-column sortable"></th>';
                                
                                if (!empty($any_status_captions)) {
                                    echo '<th class="status-column sortable">' . esc_html__('Status', 'press-permit-core') . '</th>';
                                }
                                
                                echo '<th class="assign-for-column"></th>';

                                echo '<th class="sortable">';
                                echo esc_html($via_type_obj->labels->name);
                                echo '</th>';

                                echo '<th class="edit-column"></th>';
                                echo '</tr>';
                                echo '</thead>';
                                echo '<tbody>';

                                foreach (array_keys($exceptions[$via_src][$via_type][$for_type][$operation]) as $mod_type) {
                                    if (!$mod_type_obj = self::getModificationObject($mod_type))
                                        continue;

                                    $have_mod_type[$mod_type] = true;

                                    foreach (array_keys($exceptions[$via_src][$via_type][$for_type][$operation][$mod_type]) as $status) {
                                        $status_label = '';
                                        if ($status) {
                                            $_status = explode(':', $status);
                                            if (count($_status) > 1) {
                                                $attrib = $_status[0];
                                                $_status = $_status[1];
                                            } else {
                                                $attrib = 'post_status';
                                                $_status = $status;
                                            }
                                            if ('post_status' == $attrib) {
                                                if ($status_obj = get_post_status_object($_status)) {
                                                    $status_label = $status_obj->label;
                                                } elseif ('{unpublished}' == $_status) {  // todo: API
                                                    $status_label = esc_html__('unpublished', 'press-permit-core');
                                                } else {
                                                    $status_label = $status;
                                                }
                                            } else
                                                $status_label = $status;

                                            $mod_caption = sprintf(esc_html__('%1$s (%2$s)', 'press-permit-core'), $mod_type_obj->label, $status_label);
                                        } else {
                                            $mod_caption = $mod_type_obj->label;
                                        }

                                        if (('exclude' == $mod_type) && !empty($exceptions[$via_src][$via_type][$for_type][$operation]['include'])) {
                                            $tr_class = 'pp_faded';
                                            $is_redundant = true;
                                            $any_redundant = true;
                                        } else {
                                            $is_redundant = false;
                                            $tr_class = '';
                                        }

                                        $tx_item_paths = array_intersect_key($item_paths[$via_src], $exceptions[$via_src][$via_type][$for_type][$operation][$mod_type][$status]);

                                        uasort($tx_item_paths, 'strnatcasecmp');  // sort by array values, but maintain keys );

                                        foreach ($tx_item_paths as $item_id => $item_path) {
                                            $assignment = $exceptions[$via_src][$via_type][$for_type][$operation][$mod_type][$status][$item_id];
                                            $classes = [];

                                            $assign_child_only = false;
                                            $assign_both = false;

                                            if (isset($assignment['children'])) {
                                                if (isset($assignment['item'])) {
                                                    $ass_id = $assignment['item'] . ',' . $assignment['children'];
                                                    $classes[] = 'role_both';
                                                    $assign_both = true;
                                                    $any_both = true;
                                                } else {
                                                    $ass_id = '0,' . $assignment['children'];
                                                    $classes[] = 'role_ch';
                                                    $assign_child_only = true;
                                                    $any_child_only = true;
                                                }
                                            } else {
                                                $ass_id = $assignment['item'];
                                            }

                                            $class = ($classes) ? implode(' ', $classes) : '';

                                            $tooltip_text = '';

                                            if (!$assign_child_only && !$assign_both) {
                                                $via_caption = sprintf(
                                                    esc_html__('this %1$s', 'press-permit-core'),
                                                    strtolower($via_type_obj->labels->singular_name)
                                                );
                                            } elseif ($assign_child_only) {
                                                $via_caption = sprintf(
                                                    esc_html__('sub-%1$s of this %2$s', 'press-permit-core'),
                                                    strtolower($via_type_obj->labels->name),
                                                    strtolower($via_type_obj->labels->singular_name)
                                                );
                                            } elseif ($assign_both) {
                                                $via_caption = sprintf(
                                                    esc_html__('this %1$s and its sub-%2$s', 'press-permit-core'),
                                                    strtolower($via_type_obj->labels->singular_name),
                                                    strtolower($via_type_obj->labels->name)
                                                );
                                            }

                                            if ('term' == $via_src) {
                                                if ('additional' == $mod_type) {
                                                    $tooltip_text = sprintf(
                                                        esc_html__('%1$s access for %2$s is ENABLED within %3$s, regardless of role capabilities.', 'press-permit-core'), 
                                                        $op_label,
                                                        $for_type_obj->labels->name, 
                                                        $via_caption
                                                    );
                                                
                                                } elseif ('exclude' == $mod_type) {
                                                    $tooltip_text = sprintf(
                                                        esc_html__('%1$s access for %2$s is BLOCKED within %3$s, unless enabled by another Permission.', 'press-permit-core'),
                                                        $op_label,
                                                        $for_type_obj->labels->name,
                                                        $via_caption
                                                    );
                                                
                                                } elseif ('include' == $mod_type) {
                                                    $tooltip_text = sprintf(
                                                        esc_html__('Any role-based %1$s access for %2$s is LIMITED. It applies only within %3$s, along with other specified %4$s.', 'press-permit-core'),
                                                        $op_label, 
                                                        $for_type_obj->labels->name,
                                                        $via_caption,
                                                        strtolower($via_type_obj->labels->name)
                                                    );
                                                }
                                            } else {
                                                if ('additional' == $mod_type) {
                                                    $tooltip_text = sprintf(
                                                        esc_html__('%1$s access for %2$s is ENABLED, regardless of role capabilities.', 'press-permit-core'), 
                                                        $op_label, 
                                                        $via_caption
                                                    );
                                                
                                                } elseif ('exclude' == $mod_type) {
                                                    $tooltip_text = sprintf(
                                                        esc_html__('%1$s access for %2$s is BLOCKED, unless enabled by another Permission.', 'press-permit-core'),
                                                        $op_label, 
                                                        $via_caption
                                                    );
                                                
                                                } elseif ('include' == $mod_type) {
                                                    $tooltip_text = sprintf(
                                                        esc_html__('Any role-based %1$s access for %2$s is LIMITED. It applies to only %3$s, along with other specified %4$s.', 'press-permit-core'),
                                                        $op_label, 
                                                        $for_type_obj->labels->name,
                                                        $via_caption,
                                                        strtolower($for_type_obj->labels->name)
                                                    );
                                                }
                                            }
                                            
                                            if (!$item_id && ('associate' == $operation)) {
                                                $item_path = esc_html__('(no parent)', 'press-permit-core');
                                            }

                                            $item_label = sprintf(
                                                '<span data-toggle="tooltip" data-placement="top">%s<span class="tooltip-text"><span>%s</span><i></i></span></span>',
                                                esc_html($item_path),
                                                esc_html($tooltip_text)
                                            );

                                            if ($read_only) {
                                                if ($item_links) {
                                                    $item_edit_url = '';
                                                    echo "<div><a href='" . esc_url($item_edit_url) . "' class='" . esc_attr($class) . "'>" . esc_url($item_path) . "</a></div>";
                                                } else
                                                    echo "<div><span class='" . esc_attr($class) . "'>" . esc_html($item_path) . "</span></div>";
                                            } else {
                                                $cb_id = 'pp_edit_exception_' . str_replace(',', '_', $ass_id);

                                                if (!empty($assignment['inherited_from'])) {
                                                    $classes[] = 'inherited';
                                                    $classes[] = "from_{$assignment['inherited_from']}";
                                                }

                                                if ($tr_class) // apply fading for redundantly stored exclusions
                                                    $classes[] = $tr_class;

                                                $lbl_class = ($classes) ? implode(' ', $classes) : '';

                                                if ('term' == $via_src) {
                                                    $term_id = PWP::ttidToTermid($item_id, $via_type);

                                                    $edit_url = ('nav_menu' == $via_type)
                                                        ? admin_url("nav-menus.php?action=edit&menu=172")
                                                        : admin_url("term.php?taxonomy={$via_type}&tag_ID=$term_id&post_type=$for_type");
                                                } else {
                                                    $edit_url = admin_url("post.php?post=$item_id&action=edit");
                                                }

                                                echo "<tr class='checkbox-row " . esc_attr($tr_class) . "'>";

                                                echo "<td class='checkbox-column'><input id='" . esc_attr($cb_id) . "' type='checkbox' name='pp_edit_exception[]' value='" . esc_attr($ass_id) . "' class='" . esc_attr($class) . "' autocomplete='off'></td> ";
                                                
                                                echo "<td class='icon-column' data-sort='" . esc_attr($mod_type) . "'>";
                                                echo '<span data-toggle="tooltip" data-placement="top">';
                                                
                                                if ('additional' == $mod_type) {
                                                    echo '<i class="dashicons dashicons-yes-alt" style="color:#10b981;"></i>';

                                                } elseif ('exclude' == $mod_type) {
                                                    echo '<i class="dashicons dashicons-no-alt" style="color:#ef4444;"></i>';

                                                } elseif ('include' == $mod_type) {
                                                    echo '<i class="dashicons dashicons-warning" style="color:#f59e0b;"></i>';
                                                }

                                                echo '<span class="tooltip-text"><span>';
                                                echo esc_html($tooltip_text);
                                                echo '</span><i></i></span></span>';

                                                echo "</td>";
                                                
                                                if (!empty($any_status_captions)) {
                                                    echo "<td>" . esc_html($status_label) . "</td>";
                                                }

                                                echo '<td class="assign-for-column">';
                                                if ($assign_child_only) {
                                                    ?>
                                                    <span data-toggle="tooltip" data-placement="top">
                                                    <i class="dashicons dashicons-networking assign-child"></i> 
                                                    <span class="tooltip-text"><span>
                                                    <?php printf(esc_html__('Assigned for sub-%s only.', 'press-permit-core'), esc_html($via_type_obj->labels->name));?>
                                                    </span><i></i></span></span>
                                                    <?php
                                                } elseif ($assign_both) {
                                                    ?>
                                                    <span data-toggle="tooltip" data-placement="top">
                                                    <i class="dashicons dashicons-networking assign-both"></i> 
                                                    <span class="tooltip-text"><span>
                                                    <?php printf(esc_html__('Assigned for %s and sub-%s.', 'press-permit-core'), esc_html($via_type_obj->labels->singular_name), esc_html($via_type_obj->labels->name));?>
                                                    </span><i></i></span></span>
                                                    <?php
                                                }
                                                echo '</td>';

                                                echo "<td>";

                                                $allowed_html = [
                                                    'i' => [],
                                                    'span' => [
                                                        'data-toggle'    => true,
                                                        'data-placement' => true,
                                                        'class'          => true,
                                                    ],
                                                ];
                                                
                                                echo wp_kses($item_label, $allowed_html);

                                                if ($is_redundant) {
                                                    ?>
                                                    <span data-toggle="tooltip" data-placement="top">
                                                    <i class="dashicons dashicons-welcome-comments" style="color:#a00000;"></i> 
                                                    <span class="tooltip-text"><span>
                                                    <?php esc_html_e('This Permission is redundant due to a corresponding Limitation.', 'press-permit-core');?>
                                                    </span><i></i></span></span>
                                                    <?php
                                                }

                                                echo '</td>';
                                            
                                                echo '<td class="edit-column"><a href="' . esc_url($edit_url) . '">' . sprintf(esc_html__('Edit %s', 'press-permit-core'), esc_html($via_type_obj->labels->singular_name)) . '</a></td>';

                                                $item_count++;
                                                $section_item_count++;
                                            }
                                        } // end foreach item

                                        echo '</tr>';
                                    } // end foreach status
                                } // end foreach mod_type

                                echo '</tbody>';
                                echo '</table>';

                                echo '<div class="pp-exception-bulk-edit" style="margin-top:12px;display:none">';

                                echo "<select autocomplete='off'>"
                                    . "<option value='' autocomplete='off'>" . esc_html(PWP::__wp('Bulk Actions')) . "</option>"
                                    . "<option value='remove'>" . esc_html__('Remove', 'press-permit-core') . '</option>';

                                if (('post' == $via_src) && (!$via_type || $via_type_obj->hierarchical)) {
                                    echo "<option value='propagate'>"
                                        . esc_html(sprintf(__('Assign for selected and sub-%s', 'press-permit-core'), $via_type_obj->labels->name))
                                        . '</option>';

                                    echo "<option value='unpropagate'>"
                                        . esc_html(sprintf(__('Assign for selected %s only', 'press-permit-core'), $via_type_obj->labels->singular_name))
                                        . '</option>';

                                    echo "<option value='children_only'>"
                                        . esc_html(sprintf(__('Assign for sub-%s only', 'press-permit-core'), $via_type_obj->labels->name))
                                        . '</option>';

                                } elseif ('term' == $via_src && $via_type_obj->hierarchical) {
                                    echo "<option value='propagate'>"
                                        . esc_html__('Assign for selected and sub-terms', 'press-permit-core')
                                        . '</option>';

                                    echo "<option value='unpropagate'>"
                                        . esc_html__('Assign for selected term only', 'press-permit-core')
                                        . '</option>';

                                    echo "<option value='children_only'>"
                                        . esc_html__('Assign for sub-terms only', 'press-permit-core')
                                        . '</option>';
                                }

                                if ('associate' == $operation) {
                                    $_op = ('term' == $via_src) ? 'term_associate' : 'post_associate';
                                } else {
                                    $_op = $operation;
                                }

                                $convert_caption = [
                                    'additional' => __('Convert to "Enabled"', 'press-permit-core'),
                                    'exclude' => __('Convert to "Blocked"', 'press-permit-core'),   // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                                    'include' => __('Convert to "Limit to"', 'press-permit-core'),
                                ];

                                if (in_array($via_src, ['post', 'term'])) {
                                    switch ($_op) {
                                        case 'read':
                                        case 'edit':
                                        case 'copy':
                                        case 'revise':
                                        case 'publish':
                                        case 'post_associate':
                                        case 'assign':
                                            $mirror_ops = ['read', 'edit'];

                                            if (defined('PUBLISHPRESS_REVISIONS_VERSION')) {
                                                $mirror_ops[] = 'copy';
                                            }

                                            if (defined('PUBLISHPRESS_REVISIONS_VERSION') || defined('REVISIONARY_VERSION')) {
                                                $mirror_ops[] = 'revise';
                                            }

                                            if ($pp->getOption('publish_exceptions')) {
                                                $mirror_ops[] = 'publish';
                                            }

                                            if ('term' == $via_src) {
                                                $mirror_ops[] = 'assign';
                                            }

                                            if (isset($for_type_obj->hierarchical)) {
                                                $mirror_ops[] = 'associate';
                                            }

                                            break;

                                        case 'manage':
                                        case 'term_associate':
                                            $mirror_ops = ['manage'];

                                            if ($for_type_obj->hierarchical) {
                                                $mirror_ops[] = 'associate';
                                            }

                                            break;

                                        default:
                                            $mirror_ops = apply_filters('presspermit_available_mirror_ops', [], $op, $for_type);
                                    }

                                    $mirror_ops = array_diff($mirror_ops, [$operation]);

                                    foreach ($mirror_ops as $op) {
                                        $op_obj = $pp_admin->getOperationObject($op);

                                        $caption = (('assign' == $op) || !$for_type || ('term' == $for_type))
                                            ? sprintf(
                                                esc_html__('Mirror to %s', 'press-permit-core'),
                                                $op_obj->label
                                            )
                                            : sprintf(
                                                esc_html__('Mirror to %s %s', 'press-permit-core'),
                                                $op_obj->label,
                                                $for_type_obj->labels->singular_name
                                            );

                                        echo "<option value='mirror_" . esc_attr($op) . "'>"
                                            . esc_html($caption)
                                            . '</option>';
                                    }

                                    foreach (['additional', 'exclude', 'include'] as $_mod_type) {
                                        echo "<option value='convert_" . esc_attr($_mod_type) . "'>"
                                            . esc_html($convert_caption[$_mod_type])
                                            . '</option>';
                                    }
                                }

                                echo '</select>';
                ?>

                            <input type="submit" name="" class="button submit-edit-item-exception" value="<?php esc_attr_e('Apply', 'press-permit-core'); ?>" />
                            <?php
                                echo '<img class="waiting" style="display:none;" src="'
                                    . esc_url(admin_url('images/wpspin_light.gif'))
                                    . '" alt="" />';

                            ?>
                            <div class="mirror-confirm" style="display:none"></div>
                        <?php

                                echo '</div>';  // pp-exception-bulk-edit
                                echo '</div>';  // end subsection-content

                                ?>
                                <script type="text/javascript">
                                    /* <![CDATA[ */
                                    jQuery(document).ready(function ($) {
                                        $('#<?php echo esc_attr($permissions_section_id);?> div.for-type-<?php echo esc_attr($for_type);?> div.op-<?php echo esc_attr($operation);?> h3 span.count-num').html('<?php echo esc_attr($item_count);?>');
                                        $('#<?php echo esc_attr($permissions_section_id);?> div.for-type-<?php echo esc_attr($for_type);?> div.op-<?php echo esc_attr($operation);?> span.badge-count').show();
                                    });
                                    /* ]]> */
                                </script>
                                <?php

                                echo '</div>';  // end section-content (within for_type operation)

                            } // end foreach operation

                            echo '</div>';  // for-type

                        } // end foreach for_type

                        ?>
                        <div class="notes notes-main"><span class="mod-type-key">
                        <?php

                        if (!empty($have_mod_type['additional'])) {
                            echo '<span class="mod-additional">'
                            . '<i class="dashicons dashicons-yes-alt" style="color:#10b981;"></i>'
                            . esc_html__('= Enabled', 'press-permit-core')
                            . '</span>';
                        } 
                        
                        if (!empty($have_mod_type['exclude'])) {
                            echo '<span class="mod-exclude">'
                            . '<i class="dashicons dashicons-no-alt" style="color:#ef4444;"></i>'
                            . esc_html__('= Blocked', 'press-permit-core')
                            . '</span>';
                        } 
                        
                        if (!empty($have_mod_type['include'])) {
                            echo '<span class="mod-include">'
                            . '<i class="dashicons dashicons-warning" style="color:#f59e0b;"></i>'
                            . esc_html__('= Limitation', 'press-permit-core')
                            . '</span>';
                        }

                        echo '</span>';

                        if (!empty($via_type_obj->hierarchical)) {
                            $_caption = strtolower($via_type_obj->labels->name);

                            if (!empty($any_both) || !empty($any_child_only)) : ?>
                                <?php
                                if (!empty($any_both)) {
                                    echo '<span class="role_both">'
                                        . '<i class="dashicons dashicons-networking assign-both"></i>'
                                        . esc_html(sprintf(__('= %1$s and sub-%1$s', 'press-permit-core'), $_caption))
                                        . '</span>';
                                }
                                if (!empty($any_child_only)) {
                                    echo '<span>' 
                                    . '<i class="dashicons dashicons-networking assign-child"></i>'
                                    . esc_html(sprintf(__('= sub-%s only', 'press-permit-core'), $_caption)) . '</span>';
                                }
                                ?>
                            <?php
                            endif;
                        }

                        echo '</div>';  // notes

                        if (!empty($via_type_obj->hierarchical) && (!empty($any_child_only) || !empty($any_both)) && !empty($_SERVER['REQUEST_URI'])) {
                            $show_all_url = add_query_arg('show_propagated', '1', esc_url_raw($_SERVER['REQUEST_URI']));
                            $_caption = $via_type_obj->labels->singular_name;

                            if ('term' == $via_src) {
                                if (PWP::empty_REQUEST('show_propagated')) {
                                    echo '<div class="notes">'
                                        . sprintf(
                                            '<strong>%s</strong> %s <a href="%s" class="btn btn-link">%s</a>',
                                            esc_html__('Note:', 'press-permit-core'),
                                            sprintf(
                                                esc_html__('Sub-%1$s Permissions are not separately listed.', 'press-permit-core'),
                                                esc_html($_caption)
                                            ),
                                            esc_url($show_all_url),
                                            esc_html__('Show All', 'press-permit-core')
                                        )
                                        . '</div>';
                                } else {
                                    $back_to_normal_url = remove_query_arg('show_propagated', esc_url_raw($_SERVER['REQUEST_URI']));
                                    echo '<div class="notes">'
                                        . sprintf(
                                            ' <a href="%s" class="btn btn-link">%s</a>',
                                            esc_url($back_to_normal_url),
                                            sprintf(esc_html__('Hide auto-assigned Sub-%s Permissions', 'press-permit-core'), esc_html($_caption))
                                        )
                                        . '</div>';
                                }
                            } else {
                                if (PWP::empty_REQUEST('show_propagated')) {
                                    echo '<div class="notes">'
                                        . sprintf(
                                        '<strong>%s</strong> %s <a href="%s" class="btn btn-link">%s</a>',
                                        esc_html__('Note:', 'press-permit-core'),
                                        sprintf(
                                            esc_html__('Sub-%1$s Permissions are not separately listed.', 'press-permit-core'),
                                            esc_html($_caption)
                                        ),
                                        esc_url($show_all_url),
                                        esc_html__('Show All', 'press-permit-core')
                                    );
                                } else {
                                    $back_to_normal_url = remove_query_arg('show_propagated', esc_url_raw($_SERVER['REQUEST_URI']));
                                    echo '<div class="notes">'
                                        . sprintf(
                                            ' <a href="%s" class="btn btn-link">%s</a>',
                                            esc_url($back_to_normal_url),
                                            sprintf(esc_html__('Hide auto-assigned Sub-%s Permissions', 'press-permit-core'), esc_html($_caption))
                                        );
                                }

                                if (defined('WP_DEBUG') || defined('PRESSPERMIT_DEBUG')) {

                                    $fix_child_url = add_query_arg('pp_fix_child_exceptions', '1', esc_url_raw($_SERVER['REQUEST_URI']));

                                    if (PWP::empty_REQUEST('show_propagated')) {
                                        echo '&nbsp;&nbsp;&bull;';
                                    }

                                    // Add tooltip for "Fix Sub" link
                                    $fix_sub_tooltip = esc_html__('Other plugins (or deactivation of PublishPress Permissions) can prevent sub-page permissions from being properly applied when a new sub-page is created.', 'press-permit-core');
                                    printf(
                                        '<span data-toggle="tooltip" data-placement="top">%1$s<span class="tooltip-text"><span style="white-space: normal;">%2$s</span><i></i></span><i class="dashicons dashicons-info-outline" style="font-size: 18px;width: 16px;height: 16px;padding-top:2px"></i></span>',
                                        sprintf(
                                            esc_html__(' %1$sFix Sub-%2$s Permissions%3$s', 'press-permit-core'),
                                        "&nbsp;<a href='" . esc_url($fix_child_url) . "' class='btn btn-link' style='padding-right:4px'>",
                                            esc_html($via_type_obj->labels->singular_name),
                                            '</a>'
                                        ),
                                        esc_html($fix_sub_tooltip)
                                    );
                                }

                                if (PWP::empty_REQUEST('show_propagated') || defined('WP_DEBUG') || defined('PRESSPERMIT_DEBUG')) {
                                    echo '</div>';
                                }
                            }
                        }

                        echo '</div>';  // section-content
                        echo '</div>';  // permission-section
                        ?>

                        <script type="text/javascript">
                            /* <![CDATA[ */
                            jQuery(document).ready(function ($) {
                                $('#<?php echo esc_attr($permissions_section_id);?> h2 span.count-num').html('<?php echo esc_attr($section_item_count);?>');
                                $('#<?php echo esc_attr($permissions_section_id);?> h2 span.badge-count').show();
                            });
                            /* ]]> */
                        </script>
                        
                        <?php
                    } // end foreach via_type

                } // end foreach via_src

                if (!empty($hidden_exceptions) && (defined('PP_NO_GROUP_RESTRICTIONS') || defined('PP_NO_ADDITIONAL_ACCESS'))) : ?>
                <div class="alert alert-secondary" role="alert">
                    <?php if (defined('PP_NO_GROUP_RESTRICTIONS')):?>
                    <div style="display:table; <?php if (defined('PP_NO_ADDITIONAL_ACCESS')) echo 'margin-bottom: 12px';?>">
                        <div style="display:table-cell">
                            <i class="dashicons dashicons-bell" style="color:#f59e0b; font-size: 24px;"></i>
                        </div>
                        <div style="display:table-cell; vertical-align:bottom; padding-left: 5px">
                            <?php
                            printf(
                                '%s <strong>(%s)</strong>.',
                                esc_html__('Group Restrictions are not available because of a constant definition', 'press-permit-core'),
                                esc_html('PP_NO_GROUP_RESTRICTIONS')
                            );
                            ?>
                        </div>
                    </div>
                    <?php endif;?>

                    <?php if (defined('PP_NO_ADDITIONAL_ACCESS')):?>
                    <div style="display:table">
                        <div style="display:table-cell">
                            <i class="dashicons dashicons-bell" style="color:#f59e0b; font-size: 24px;"></i>
                        </div>
                        <div style="display:table-cell; vertical-align:bottom; padding-left: 5px">
                            <?php
                            printf(
                                '%s <strong>(%s)</strong>.',
                                esc_html__('Permissions to enable access are not available because of a constant definition', 'press-permit-core'),
                                esc_html('PP_NO_ADDITIONAL_ACCESS')
                            );
                            ?>
                        </div>
                    </div>
                    <?php endif;?>
                </div>
                <?php endif;

                echo '</div>';  // pp_current_exceptions
            }
            
            // Called once each for members checklist, managers checklist in admin UI.
            // In either case, current (checked) members are at the top of the list.
            private static function userSelectionUI($group_id, $agent_type, $user_class = 'member', $all_users = '')
            {
                // This is only needed for checkbox selection
                if (!$all_users) {
                    $all_users = get_users(
                        [
                            'fields' => ['ID', 'user_login'],
                            'orderby' => 'user_login'
                        ]
                    );

                    foreach (array_keys($all_users) as $k) {
                        $all_users[$k]->display_name = $all_users[$k]->user_login;
                    }
                }

                $current_users = ($group_id)
                    ? presspermit()->groups()->getGroupMembers($group_id, $agent_type, 'all', ['member_type' => $user_class, 'status' => 'any'])
                    : [];

                $args = [
                    'suppress_extra_prefix' => true,
                    'ajax_selection' => true,
                    'agent_id' => $group_id,
                ];

                echo '<div>';

                echo '<div class="pp-agent-select">';

                $agents = presspermit()->admin()->agents();
                $agents->agentsUI('user', $all_users, $user_class, $current_users, $args);
                echo '</div>';

                echo '</div>';
            }

            public static function drawMemberChecklists($group_id, $agent_type, $args = [])
            {
                $defaults = ['member_types' => ['member'], 'suppress_caption' => false];
                $args = array_merge($defaults, $args);
                foreach (array_keys($defaults) as $var) {
                    $$var = $args[$var];
                }

                $captions['member'] = apply_filters('presspermit_group_members_caption', esc_html__('Group Members', 'press-permit-core'));

                echo '<div class="pp-group-box pp-group_members" style="display:none;">';

                if (!$suppress_caption) {
                    echo '<h3>';

                    // note: member_type other than 'member' is never invoked as of PP Core 2.1-beta

                    $i = 0;
                    foreach ($member_types as $member_type) {
                        $link_class = ($i) ? 'agp-unselected_agent' : 'agp-selected_agent';

                    ?>
                    <span class="<?php echo esc_attr($link_class); ?> pp-member-type pp-$member_type">
                        <?php echo esc_html($captions[$member_type]); ?>
                    </span>
                <?php

                        $i++;
                        if ($i < count($member_types)) {
                            echo '<span> | </span>';
                        }
                    }

                    echo '</h3>';
                }

                $i = 0;
                foreach ($member_types as $member_type) {
                    $style = ($i) ? 'display:none' : '';
                ?>
                <div class="<?php echo "pp-member-type pp-" . esc_attr($member_type); ?>" style="<?php echo esc_attr($style); ?>">
                    <?php
                    self::userSelectionUI($group_id, $agent_type, $member_type);
                    ?>
                </div>
            <?php
                    $i++;
                }

                echo '</div>';

                do_action('presspermit_group_members_ui', $group_id, $agent_type);
            }

            private static function selectCloneUI($agent)
            {
                global $wp_roles;
                esc_html_e('Copy Roles and Permissions from:', 'press-permit-core');

                $pp_only = (array) presspermit()->getOption('supplemental_role_defs');
            ?>
            <select name="pp_select_role" autocomplete="off">

                <?php
                foreach ($wp_roles->role_names as $role_name => $role_caption) {
                    if (
                        !in_array($role_name, $pp_only, true) && ($role_name != $agent->metagroup_id)
                        && empty($wp_roles->role_objects[$role_name]->capabilities['activate_plugins'])
                        && empty($wp_roles->role_objects[$role_name]->capabilities['pp_administer_content'])
                        && empty($wp_roles->role_objects[$role_name]->capabilities['pp_unfiltered'])
                    ) {
                        echo "<option value='" . esc_attr($role_name) . "'>" . esc_html($role_caption) . "</option>";
                    }
                }
                ?>

            </select>

            <br />
            <div>
                <input id="pp_clone_permissions" class="button button-primary pp-button" type="submit" name="pp_clone_permissions" value="<?php esc_attr_e('Copy Roles and Permissions', 'press-permit-core'); ?>">
            </div>
        <?php
            }

            public static function fltTermSelectNoPaging($args, $taxonomies)
            {
                $args['number'] = 999;
                return $args;
            }

                    private static function getModificationObject($mod_type)
                    {
                        static $mod_types;

                        if (!isset($mod_types)) {
                            $mod_types = [
                                'include' => (object)['label' => esc_html__('Limit to:', 'press-permit-core')],

                                // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                                'exclude' => (object)['label' => esc_html__('Blocked:', 'press-permit-core')],
                            ];

                            if (!defined('PP_NO_ADDITIONAL_ACCESS')) {
                                $mod_types['additional'] = (object)['label' => esc_html__('Enabled:', 'press-permit-core')];
                            }
                        }
                        return (isset($mod_types[$mod_type])) ? $mod_types[$mod_type] : (object)[];
                    }

                    private static function getRoleStatusLabel($role_name)
                    {
                        $lbl_status = '';

                        if (false === strpos($role_name, ':')) {
                            $lbl_status = esc_html__('Direct-assigned', 'press-permit-core');
                        } else {
                            $arr_role_name = explode(':', $role_name);

                            if (!empty($arr_role_name[3]) && ('post_status' == $arr_role_name[3]) && !empty($arr_role_name[4])) {
                                if ($status_obj = get_post_status_object($arr_role_name[4])) {
                                    if (!empty($status_obj->private)) {
                                        $lbl_status = sprintf(esc_html__('%s Visibility', 'press-permit-core'), esc_html($status_obj->label));
                                    } else {
                                        $lbl_status = $status_obj->label;
                                    }
                                }
                            } else {
                                $lbl_status = esc_html__('Standard Statuses', 'press-permit-core');
                            }
                        }

                        return $lbl_status;
                    }
                }

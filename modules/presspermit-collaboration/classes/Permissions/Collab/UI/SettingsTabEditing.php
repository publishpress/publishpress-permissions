<?php

namespace PublishPress\Permissions\Collab\UI;

use \PublishPress\Permissions\UI\SettingsAdmin as SettingsAdmin;

class SettingsTabEditing
{
    function __construct()
    {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 4);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections'], 15);

        add_action('presspermit_editing_options_ui', [$this, 'optionsUI']);
        add_action('presspermit_media_library_options_ui', [$this, 'optionsUIMediaLibrary']);
    }

    function optionTabs($tabs)
    {
        $tabs['editing'] = esc_html__('Editing', 'press-permit-core');
        $tabs['media_library'] = esc_html__('Media Library', 'press-permit-core'); // Add new tab
        return $tabs;
    }

    function sectionCaptions($sections)
    {
        $new_editing = [
            'post_editor'              => esc_html__('Editor Options', 'press-permit-core'),
            'content_management'       => esc_html__('Posts / Pages Listing', 'press-permit-core'),
        ];

        $new_media_library = [
            'media_library'            => esc_html__('Media Library', 'press-permit-core'),
        ];

        $sections['editing'] = (isset($sections['editing'])) ? array_merge($sections['editing'], $new_editing) : $new_editing;
        $sections['media_library'] = (isset($sections['media_library'])) ? array_merge($sections['media_library'], $new_media_library) : $new_media_library;

        return $sections;
    }

    function optionCaptions($captions)
    {
        $opt = [
            'admin_others_attached_files'            => esc_html__("List other users' files if attached to a editable post", 'press-permit-core'),
            'admin_others_attached_to_readable'      => esc_html__("List other users' files if attached to a viewable post", 'press-permit-core'),
            'admin_others_unattached_files'          => esc_html__("List other users' unattached files by default", 'press-permit-core'),
            'edit_others_attached_files'             => esc_html__("Edit other users' files if attached to an editable post", 'press-permit-core'),
            'attachment_edit_requires_parent_access' => esc_html__('Prevent editing files if attached to a non-editable post', 'press-permit-core'),
            'own_attachments_always_editable'        => esc_html__('Users can always edit their own files', 'press-permit-core'),
            'default_privacy'                        => esc_html__('Default visibility for new posts                               : ', 'press-permit-core'),
            'list_others_uneditable_posts'           => esc_html__('List other user\'s uneditable posts', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    function optionSections($sections)
    {
        // Editing tab
        $new_editing = [
            'post_editor'         => ['default_privacy', 'force_default_privacy'],
            'content_management'  => ['list_others_uneditable_posts'],
        ];

        // Note: Limited Editing Elements feature has been moved to PublishPress Capabilities > Editor Features
        // The feature is no longer available in this settings interface

        // Media Library tab
        $new_media_library = [
            'media_library'       => ['admin_others_attached_files', 'admin_others_attached_to_readable', 'admin_others_unattached_files', 'edit_others_attached_files', 'attachment_edit_requires_parent_access', 'own_attachments_always_editable'],
        ];

        $sections['editing'] = (isset($sections['editing'])) ? array_merge($sections['editing'], $new_editing) : $new_editing;
        $sections['media_library'] = (isset($sections['media_library'])) ? array_merge($sections['media_library'], $new_media_library) : $new_media_library;

        return $sections;
    }

    function optionsUI()
    {
        $pp = presspermit();

        $ui = \PublishPress\Permissions\UI\SettingsAdmin::instance();
        $tab = 'editing';
        $section = 'post_editor';                        // --- EDITOR OPTIONS SECTION ---
        if (!empty($ui->form_options[$tab][$section])) :
        ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>

                    <span><?php echo esc_html($ui->option_captions['default_privacy']); ?></span>
                    <br />

                    <div class="agp-vspaced_input default_privacy" style="margin-left: 2em;">
                        <?php
                        $option_name = 'default_privacy';
                        $ui->all_otype_options[] = $option_name;

                        $opt_values = array_merge(array_fill_keys($pp->getEnabledPostTypes(), 0), $ui->getOptionArray($option_name));  // add enabled types whose settings have never been stored
                        $opt_values = array_intersect_key($opt_values, array_fill_keys($pp->getEnabledPostTypes(), 0));  // skip stored types that are not enabled
                        $opt_values = array_diff_key($opt_values, array_fill_keys(apply_filters('presspermit_disabled_default_privacy_types', ['forum', 'topic', 'reply']), true));

                        // todo: force default status in Gutenberg
                        if (defined('PRESSPERMIT_STATUSES_VERSION')) {
                            $do_force_option = true;
                            $ui->all_otype_options[] = 'force_default_privacy';
                            $force_values = array_merge(array_fill_keys($pp->getEnabledPostTypes(), 0), $ui->getOptionArray('force_default_privacy'));  // add enabled types whose settings have never been stored
                        } else
                            $do_force_option = false;
                        ?>
                        <table class='agp-vtight_input agp-rlabel'>
                            <?php

                            foreach ($opt_values as $object_type => $setting) :
                                if ('attachment' == $object_type) continue;

                                $id = $option_name . '-' . $object_type;
                                $name = "{$option_name}[$object_type]";
                            ?>
                                <tr>
                                    <td class="rlabel">
                                        <input name='<?php echo esc_attr($name); ?>' type='hidden' value='' />
                                        <label for='<?php echo esc_attr($id); ?>'><?php if ($type_obj = get_post_type_object($object_type)) echo esc_html($type_obj->labels->name);
                                                                                    else echo esc_html($object_type); ?></label>
                                    </td>

                                    <td><select name='<?php echo esc_attr($name); ?>' id='<?php echo esc_attr($id); ?>' autocomplete='off'>
                                            <option value=''><?php esc_html_e('Public'); ?></option>
                                            <?php foreach (get_post_stati(['private' => true], 'object') as $status_obj) :
                                                $selected = ($setting === $status_obj->name) ? ' selected ' : '';
                                            ?>
                                                <option value='<?php echo esc_attr($status_obj->name); ?>' <?php echo esc_attr($selected); ?>><?php echo esc_html($status_obj->label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php
                                        if ($do_force_option) :
                                            $id = 'force_default_privacy-' . $object_type;
                                            $name = "force_default_privacy[$object_type]";
                                            $style = ($setting) ? '' : 'display:none';
                                            $checked = (!empty($force_values[$object_type]) || PWP::isBlockEditorActive($object_type)) ? ' checked ' : '';
                                            $disabled = (PWP::isBlockEditorActive($object_type)) ? " disabled " : '';
                                        ?>
                                            <input name='<?php echo esc_attr($name); ?>' type='hidden' value='0' />
                                            &nbsp;<label style='<?php echo esc_attr($style); ?>' for="<?php echo esc_attr($id); ?>"><input
                                                    type="checkbox" <?php echo esc_attr($checked); ?><?php echo esc_attr($disabled); ?>id="<?php echo esc_attr($id); ?>"
                                                    name="<?php echo esc_attr($name); ?>"
                                                    value="1" /><?php if ($do_force_option) : ?>&nbsp;<?php esc_html_e('lock', 'press-permit-core'); ?><?php endif; ?>
                                            </label>
                                        <?php endif; ?>

                                    </td>
                                </tr>
                            <?php endforeach;
                            ?>
                        </table>
                    </div>

                    <script type="text/javascript">
                        /* <![CDATA[ */
                        jQuery(document).ready(function($) {
                            $('div.default_privacy select').on('click', function() {
                                $(this).parent().find('label').toggle($(this).val() != '');
                            });

                            $('#add_author_pages').on('click', function() {
                                $('div.publish_author_pages').toggle($(this).is(':checked'));
                            });
                        });
                        /* ]]> */
                    </script>
                    <?php
                    $ui->optionCheckbox('page_parent_editable_only', $tab, $section);
                    $ui->optionCheckbox('page_parent_order', $tab, $section);

                    $hint = esc_html__("When saving a post, if the default term is not selectable, substitute first available.", 'press-permit-core')
                        . ' ' . esc_html__('Some term-limited editing configurations require this.', 'press-permit-core');

                    $ui->optionCheckbox('auto_assign_available_term', $tab, $section, $hint, '', ['hint_class' => 'pp-subtext-show']);
                    ?>
                </td>
            </tr>
        <?php endif; // any options accessable in this section

        $section = 'content_management';                        // --- POSTS / PAGES LISTING SECTION ---
        if (!empty($ui->form_options[$tab][$section])) :
        ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    if (!defined('PP_ADMIN_READONLY_LISTABLE') || ($pp->getOption('admin_hide_uneditable_posts') && !defined('PP_ADMIN_POSTS_NO_FILTER'))) {
                        $ui->optionCheckbox('list_others_uneditable_posts', $tab, $section, true);
                    }
                    ?>
                </td>
            </tr>
        <?php endif; // any options accessable in this section

        // Add notice about Limited Editing Elements feature relocation
        if (presspermit()->getOption('advanced_options') && !PWP::isBlockEditorActive()) :
            $has_existing_settings = presspermit()->getOption('editor_hide_html_ids') || presspermit()->getOption('editor_ids_sitewide_requirement');
            if ($has_existing_settings) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('Limited Editing Elements', 'press-permit-core'); ?></th>
                <td>
                    <style>
                        .pp-notice {
                            background: #fff;
                            border: 1px solid #c3c4c7;
                            border-left-width: 4px;
                            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
                            margin: 5px 15px 2px;
                            padding: 1px 12px;
                            position: relative;
                        }
                        .pp-notice.pp-notice-info {
                            border-left-color: #72aee6;
                        }
                        .pp-notice p {
                            margin: 0.5em 0;
                            padding: 2px;
                        }
                        .pp-notice code {
                            background: #f0f0f1;
                            color: #50575e;
                            font-family: Consolas, Monaco, monospace;
                            font-size: 13px;
                            padding: 2px 4px;
                        }
                    </style>
                    <div class="pp-notice pp-notice-info">
                        <p><strong><?php esc_html_e('Feature Moved', 'press-permit-core'); ?></strong></p>
                        <p>
                            <?php 
                            if (defined('PUBLISHPRESS_CAPS_VERSION')) {
                                $capabilities_url = admin_url('admin.php?page=pp-capabilities-editor-features');
                                printf(
                                    esc_html__('The Limited Editing Elements feature has been moved to %1$sCapabilities > Editor Features > Element IDs%2$s for better organization and enhanced functionality.', 'press-permit-core'),
                                    '<a href="' . esc_url($capabilities_url) . '">',
                                    '</a>'
                                );
                            } else {
                                printf(
                                    esc_html__('The Limited Editing Elements feature has been moved to the PublishPress Capabilities plugin. Please install %1$sPublishPress Capabilities%2$s to access this functionality.', 'press-permit-core'),
                                    '<a href="' . esc_url(admin_url('plugin-install.php?tab=plugin-information&plugin=capability-manager-enhanced')) . '" target="_blank">',
                                    '</a>'
                                );
                            }
                            ?>
                        </p>
                        <?php if (presspermit()->getOption('editor_hide_html_ids')) : ?>
                            <p>
                                <strong><?php esc_html_e('Current Settings:', 'press-permit-core'); ?></strong><br>
                                <?php esc_html_e('HTML Element IDs: ', 'press-permit-core'); ?>
                                <code><?php echo esc_html(presspermit()->getOption('editor_hide_html_ids')); ?></code>
                            </p>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endif;
        endif;
    }

    // i need move code media_library from optionsUI to optionsUIMediaLibrary
    function optionsUIMediaLibrary()
    {
        $pp = presspermit();

        $ui = \PublishPress\Permissions\UI\SettingsAdmin::instance();
        $tab = 'media_library';
        $section = 'media_library';                                        // --- MEDIA LIBRARY SECTION ---
        if (!empty($ui->form_options[$tab][$section])) :
        ?>
            <tr>
                <th scope="row"><?php echo esc_html__('List Files', 'press-permit-core'); ?></th>
                <td>
                    <?php

                    if (defined('PP_MEDIA_LIB_UNFILTERED')) :
                    ?>
                        <div><span class="pp-important">
                                <?php SettingsAdmin::echoStr('media_lib_unfiltered'); ?>
                            </span></div><br />
                    <?php else : ?>
                        <div><span style="font-weight:bold">
                                <?php esc_html_e('The following settings apply to users who are able to access the Media Library. Normally this requires the upload_files or edit_files capability.', 'press-permit-core'); ?>
                            </span></div><br />
                    <?php endif;

                    $ret = $ui->optionCheckbox('admin_others_unattached_files', $tab, $section, true, '');

                    $ret = $ui->optionCheckbox('admin_others_attached_to_readable', $tab, $section, true, '');

                    $ret = $ui->optionCheckbox('admin_others_attached_files', $tab, $section, true, '');
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Edit Files', 'press-permit-core'); ?></th>
                <td>
                    <?php
                    $ret = $ui->optionCheckbox('edit_others_attached_files', $tab, $section, true, '');

                    $ret = $ui->optionCheckbox('attachment_edit_requires_parent_access', $tab, $section, true, '');

                    $ret = $ui->optionCheckbox('own_attachments_always_editable', $tab, $section, true, '');
                    ?>
                </td>
            </tr>
        <?php endif; // any options accessable in this section
    }
}

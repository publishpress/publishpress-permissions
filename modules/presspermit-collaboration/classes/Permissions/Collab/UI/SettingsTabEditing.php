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
            'list_others_uneditable_posts'           => esc_html__('List other user\'s uneditable posts', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    function optionSections($sections)
    {
        // Editing tab
        $new_editing = [
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

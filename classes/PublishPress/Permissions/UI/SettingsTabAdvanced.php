<?php

namespace PublishPress\Permissions\UI;

class SettingsTabAdvanced
{
    private $enabled;
    private $advanced_option_captions = [];

    public function __construct()
    {
        // if disabled, will show only available option will be "enable"
        $this->enabled = presspermit()->getOption('advanced_options');

        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 95);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_advanced_options_pre_ui', [$this, 'optionsPreUI']);
        add_action('presspermit_advanced_options_ui', [$this, 'optionsUI']);
    }

    public function optionTabs($tabs)
    {
        $tabs['advanced'] = esc_html__('Advanced', 'press-permit-core');
        return $tabs;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'enable'         => esc_html__('Advanced Settings', 'press-permit-core'),
            'file_filtering' => esc_html__('File Access', 'press-permit-core'),
            'network'        => esc_html__('Network-Wide Settings', 'press-permit-core'),
            'post_editor'         => esc_html__('Editor Options', 'press-permit-core'),
            'statuses'            => esc_html__('Statuses', 'press-permit-core'),
            'page_structure'      => esc_html__('Page Structure', 'press-permit-core'),
            'permissions'         => esc_html__('Permissions', 'press-permit-core'),
            'capabilities'        => esc_html__('Permissions Capabilities', 'press-permit-core'),
            'front_end'           => esc_html__('Front End', 'press-permit-core'),
            'user_management'     => esc_html__('User Management', 'press-permit-core'),
            'constants'           => esc_html__('Constants', 'press-permit-core'),
            'role_integration'    => esc_html__('Role Integration', 'press-permit-core'),
            'nav_menu_management' => esc_html__('Nav Menu Editing', 'press-permit-core'),
            'misc'                => esc_html__('Miscellaneous', 'press-permit-core'),
        ];

        $key = 'advanced';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionCaptions($captions)
    {
        $pp = presspermit();

        // Settings that are always displayed
        $opt = [
            'advanced_options'                       => esc_html__('Display all advanced settings', 'press-permit-core'),
            'delete_settings_on_uninstall'           => esc_html__('Delete settings on plugin deletion', 'press-permit-core'),
            'post_blockage_priority'                 => esc_html__('Post-specific Permissions take priority', 'press-permit-core'),
            'media_search_results'                   => esc_html__('Search Results include Media', 'press-permit-core'),
            'regulate_category_archive_page'         => esc_html__('Regulate access to Category archive pages', 'press-permit-core'),
            'term_counts_unfiltered'                 => esc_html__("Performance: Don't filter category / tag counts", 'press-permit-core'),
            'force_nav_menu_filter'                  => esc_html__('Filter Menu Items', 'press-permit-core'),
            'page_parent_editable_only'              => esc_html__('Page Parent selection for editable pages only', 'press-permit-core'),
            'auto_assign_available_term'             => esc_html__("Auto-assign available term if default term is unavailable", 'press-permit-core'),
            'list_others_uneditable_posts'           => esc_html__('List other user\'s uneditable posts', 'press-permit-core'),
            'lock_top_pages'                         => esc_html__('Pages can be set or removed from Top Level by: ', 'press-permit-core'),
            'create_tag_require_edit_cap'            => esc_html__('Tag creation requires Tag edit capability', 'press-permit-core'),
        ];

        // Settings that are displayed if already set to a non-default value, or if "Display all" is enabled
        $de_emphasized_settings = [
            'pattern_roles_include_generic_rolecaps' => esc_html__('Type-specific Extra Roles grant all capabilities in Pattern Role', 'press-permit-core'),
            'strip_private_caption'                  => esc_html__('Suppress "Private: " Caption', 'press-permit-core'),
            'new_user_groups_ui'                     => esc_html__('Select Permission Groups at User creation', 'press-permit-core'),
            'display_user_profile_groups'            => esc_html__('Permission Groups on User Profile', 'press-permit-core'),
            'display_user_profile_roles'             => esc_html__('Extra Roles on User Profile', 'press-permit-core'),
            'page_parent_order'                      => esc_html__('Order Page Parent dropdown by Title', 'press-permit-core'),
            'force_taxonomy_cols'                    => esc_html__('Add taxonomy columns to Edit Posts screen', 'press-permit-core'),
            'admin_nav_menu_partial_editing'         => esc_html__('Allow Renaming of uneditable Items', 'press-permit-core'),
            'admin_nav_menu_lock_custom'             => esc_html__('Lock custom menu items', 'press-permit-core'),
            'add_author_pages'                       => esc_html__('Bulk Add Author Pages on Users screen', 'press-permit-core'),
        ];

        foreach ($de_emphasized_settings as $option_name => $option_caption) {
            if ($this->enabled || !isset($pp->default_options[$option_name])) {
                $opt[$option_name] = $option_caption;
            } else {
                $option_val = $pp->getOption($option_name);

                if ($option_val != $pp->default_options[$option_name]) {
                    // For this setting display decision, treat a nullstring option value as equal to a zero-valued or false-valued default option
                    if (('' === $option_val) && is_scalar($pp->default_options[$option_name]) && !$pp->default_options[$option_name]) {
                        continue;
                    } else {
                        $opt[$option_name] = $option_caption;
                    }
                }
            }
        }

        // Settings that are displayed only if "Display all" is enabled 
        $this->advanced_option_captions = [
            'anonymous_unfiltered'                   => sprintf(esc_html__('%1$sDisable%2$s all filtering for anonymous users', 'press-permit-core'), '', ''),
            'suppress_administrator_metagroups'      => sprintf(esc_html__('%1$sDo not apply%2$s metagroup permissions for Administrators', 'press-permit-core'), '', ''),
            'limit_front_end_term_filtering'         => sprintf(esc_html__('Limit front-end category / term filtering', 'press-permit-core')),
            'user_search_by_role'                    => esc_html__('User Search: Filter by WP role', 'press-permit-core'),
            'display_hints'                          => esc_html__('Display Administrative Hints', 'press-permit-core'),
            'display_extension_hints'                => esc_html__('Display Module Hints', 'press-permit-core'),
            'dynamic_wp_roles'                       => esc_html__('Detect Dynamically Mapped WP Roles', 'press-permit-core'),
            'non_admins_set_read_exceptions'         => esc_html__('Non-Administrators can set Reading Permissions for their editable posts', 'press-permit-core'),
            'users_bulk_groups'                      => esc_html__('Bulk Add / Remove Groups on Users Screen', 'press-permit-core'),
            'list_all_constants'                     => esc_html__('Display all available constant definitions'),
            'non_admins_set_edit_exceptions'         => esc_html__('Non-Administrators can set Editing Permissions for their editable posts', 'press-permit-core'),
            'publish_exceptions'                     => esc_html__('Assign Publish Permissions separate from Edit Permissions', 'press-permit-core'),
            'limit_user_edit_by_level'               => 'limit_user_edit_by_level', // not actually displayed; include to regulate display of setting
            'user_permissions'                       => 'user_permissions'          // not actually displayed; include to regulate display of setting
        ];

        if ($this->enabled) {
            $opt = array_merge($opt, $this->advanced_option_captions);
        }

        // Suppress the display of settings specific to a module which is disabled
        if (!$pp->moduleActive('collaboration')) {
            $opt = array_diff_key(
                $opt,
                array_fill_keys(
                [
                    'page_parent_editable_only',
                    'auto_assign_available_term',
                    'list_others_uneditable_posts',
                    'lock_top_pages',
                    'page_parent_order',
                    'force_taxonomy_cols',
                    'admin_nav_menu_filter_items',
                    'admin_nav_menu_partial_editing',
                    'admin_nav_menu_lock_custom',
                    'add_author_pages',
                    'limit_user_edit_by_level',
                    'non_admins_set_edit_exceptions',
                    'publish_exceptions',
                ], true)
            );
        }

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'enable' => ['advanced_options', 'delete_settings_on_uninstall'],
            'post_editor'       => ['lock_top_pages', 'page_parent_order', 'page_parent_editable_only', 'auto_assign_available_term', 'create_tag_require_edit_cap'],
            'permissions'       => ['post_blockage_priority', 'suppress_administrator_metagroups', 'publish_exceptions', 'non_admins_set_read_exceptions', 'non_admins_set_edit_exceptions'],
            'user_management'   => ['new_user_groups_ui', 'display_user_profile_groups', 'display_user_profile_roles', 'users_bulk_groups', 'add_author_pages', 'publish_author_pages'],
            'front_end'         => ['media_search_results', 'anonymous_unfiltered', 'regulate_category_archive_page', 'limit_front_end_term_filtering', 'term_counts_unfiltered', 'strip_private_caption', 'force_nav_menu_filter'],
            'role_integration'  =>  ['pattern_roles_include_generic_rolecaps', 'dynamic_wp_roles'],
            'nav_menu_management' => ['admin_nav_menu_partial_editing', 'admin_nav_menu_lock_custom'],
            'misc'              => ['force_taxonomy_cols'],
            'constants'         => [],
        ];


        // Advanced tab (populated here because they do not apply if Editing Permissions module is disabled)
        if ($this->enabled) {
            $additional = [
                'user_management'   =>  ['limit_user_edit_by_level', 'user_permissions'],
                'misc'              =>  ['users_bulk_groups', 'user_search_by_role', 'display_hints', 'display_extension_hints'],
                'constants'         =>  ['list_all_constants'],
            ];

            foreach ($additional as $section => $options) {
                $new[$section] = array_merge($new[$section], $additional[$section]);
            }
        }

        $key = 'advanced';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsPreUI()
    {
        // This is intentionally left as an example for future usage

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /*
        if (SettingsAdmin::instance()->display_hints) {
            echo '<div class="pp-hint pp-optionhint">';

            if (presspermit()->getOption('advanced_options')) {
                if (presspermit()->moduleActive('collaboration')) {
                    SettingsAdmin::echoStr('advanced_options_enabled');
                }
            } else {
                SettingsAdmin::echoStr('advanced_options_disabled');
            }
            echo '</div>';
        }
        */
    }

    public function optionsUI()
    {
        $pp = presspermit();

        $ui = SettingsAdmin::instance();
        $tab = 'advanced';

        foreach ($ui->form_options['advanced'] as $section => $section_options) {
            $ui->form_options['advanced'][$section] = array_intersect($ui->form_options['advanced'][$section], array_keys($ui->option_captions));
        }

        $section = 'enable'; // --- ENABLE SECTION ---
        if (!empty($ui->form_options[$tab][$section])) : ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <div>
                        <?php
                        $hint = esc_html__('note: Plugin settings and configuration data will be deleted, but only after the last copy of Permissions / Permissions Pro is deleted.', 'press-permit-core');
                        $ui->optionCheckbox('delete_settings_on_uninstall', $tab, $section, $hint);
                        ?>
                    </div>
                    <?php
                    $hint = '';
                    $ui->optionCheckbox('advanced_options', $tab, $section, $hint);

                    $caution_option_names = [];

                    $option_captions = $ui->option_captions;

                    if (!$this->enabled) {
                        $option_captions = array_merge($option_captions, $this->advanced_option_captions);
                    }

                    // Use option_captions array for ordering
                    $advanced_options = array_merge (
                        array_intersect_key($option_captions, $pp->default_advanced_options),
                        array_diff_key($pp->default_advanced_options, $option_captions)         // uncaptioned advanced options
                    );

                    foreach (array_keys($advanced_options) as $option_name) {
                        $default_val = (isset($pp->default_advanced_options[$option_name])) ? $pp->default_advanced_options[$option_name] : '';
                        $stored_val = get_option("presspermit_{$option_name}", $default_val);

                        if (($stored_val != $default_val) 
                        && (!is_scalar($stored_val) 
                            || !is_scalar($default_val)
                            || (is_numeric($default_val) && (is_numeric($stored_val) || ('' === $stored_val)) && (intval($stored_val) != intval($default_val)))
                            || (!is_numeric($default_val) && (string) $default_val != (string) $stored_val))
                        ) {
                            if ('role_usage' == $option_name) {
                                continue;
                            } elseif (isset($option_captions[$option_name])) {
                                $caution_option_names []= $option_captions[$option_name];
                            } else {
                                $caution_option_names []= ucwords(str_replace('_', ' ', $option_name));
                            }
                        }
                    }
                    ?>

                    <?php if ($caution_option_names) :?>
                        <div class="pp-advanced-caution" style="display:none">
                        <span class="pp-caution">
                        <?php
                        if ($this->enabled) {
                            esc_html_e('The following would revert to default settings:', 'press-permit-core');
                        } else {
                            esc_html_e('The following would change from defaults to previously stored settings:', 'press-permit-core');
                        }
                        ?>
                        </span>

                        <ul>
                            <?php foreach ($caution_option_names as $_opt_name) :?>
                                <li>
                                <?php esc_html_e($_opt_name);?>
                                </li>
                            <?php endforeach;?>
                        </ul>
                        </div>

                        <script type="text/javascript">
                            /* <![CDATA[ */
                            jQuery(document).ready(function ($) {
                                $('input#advanced_options').on('click', function() {
                                    <?php if ($this->enabled) :?>
                                        $(this).closest('td').find('div.pp-advanced-caution').slideToggle($(this).prop('checked'));
                                    <?php else:?>
                                        $(this).closest('td').find('div.pp-advanced-caution').slideToggle(!$(this).prop('checked'));
                                    <?php endif;?>
                                });
                            });
                            /* ]]> */
                        </script>

                    <?php endif;?>

                    <?php
                    do_action('presspermit_options_ui_insertion', $tab, $section, $ui);
                    ?>
                </td>
            </tr>
        <?php endif; // any options accessable in this section

        do_action('presspermit_custom_advanced_options_ui', $tab);

        $section = 'post_editor';                        // --- EDITOR OPTIONS SECTION ---
        if (!empty($ui->form_options[$tab][$section])) :
        ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php if (in_array('lock_top_pages', $ui->form_options[$tab][$section])) :
                        $id = 'lock_top_pages';
                        $ui->all_options[] = $id;
                        $current_setting = strval($ui->getOption($id));  // force setting and corresponding keys to string, to avoid quirks with integer keys

                        echo '<div style="margin-bottom: 5px">';
                        echo esc_html($ui->option_captions['lock_top_pages']);
                        echo '</div>';

                        $captions = ['no_parent_filter' => '(' . esc_html__('no Page Parent filter', 'press-permit-core') . ')', 'author' => esc_html__('Page Authors, Editors and Administrators', 'press-permit-core'), '' => esc_html__('Page Editors and Administrators', 'press-permit-core'), '1' => esc_html__('Administrators', 'press-permit-core')];

                        foreach ($captions as $key => $value) {
                            $key = strval($key);
                            echo "<div style='margin: 0 0 0.5em 2em;'><label for='" . esc_attr("{$id}_{$key}") . "'>";
                            $checked = ($current_setting === $key) ? ' checked ' : '';

                            echo "<input name='" . esc_attr($id) . "' type='radio' id='" . esc_attr("{$id}_{$key}") . "' value='" . esc_attr($key) . "' " . esc_attr($checked) . " /> ";
                            echo esc_html($value);
                            echo '</label></div>';
                        }

                        echo '<div class="pp-subtext">';
                        if ($ui->display_hints) {
                            SettingsAdmin::echoStr('lock_top_pages');
                        }

                        echo '</div><br>';
                    endif; ?>

                    <?php
                    $ui->optionCheckbox('page_parent_editable_only', $tab, $section);

                    $ui->optionCheckbox('page_parent_order', $tab, $section);
                    ?>

                    <br />
                    <?php
                    $hint = esc_html__("When saving a post, if the default term is not selectable, substitute first available.", 'press-permit-core')
                        . ' ' . esc_html__('Some term-limited editing configurations require this.', 'press-permit-core');

                    $ui->optionCheckbox('auto_assign_available_term', $tab, $section, $hint);

                    if (defined('PRESSPERMIT_COLLAB_VERSION')) {
                        $url = Settings::pluginInfoURL('capability-manager-enhanced');
    
                        $hint = esc_html__('If enabled, users cannot create new tags for a post unless they have the edit_tags capability. You can manage this with a WordPress role editor like PublishPress Capabilities.', 'press-permit-core');
                        if (defined('PUBLISHPRESS_CAPS_VERSION')) {
                            $url = admin_url('admin.php?page=pp-capabilities');
    
                            $hint = esc_html__('If enabled, users cannot create new tags for a post unless they have the edit_tags capability. You can manage this with Capabilities > Capabilities > Taxonomies for any role.', 'press-permit-core');
                        }
                        $ui->optionCheckbox('create_tag_require_edit_cap', $tab, $section, $hint);
                    }

                    do_action('presspermit_options_ui_insertion', $tab, $section, $ui);
                    ?>
                </td>
            </tr>
        <?php endif; // any options accessable in this section

        $section = 'permissions'; // --- PERMISSIONS SECTION ---
        if (!empty($ui->form_options[$tab][$section])) :
        ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    $hint = SettingsAdmin::getStr('post_blockage_priority');
                    $ui->optionCheckbox('post_blockage_priority', $tab, $section, $hint);

                    $ui->optionCheckbox('publish_exceptions', $tab, $section, '');

                    ?>
                    <div style="margin-top:30px">
                    <?php
                    $ui->optionCheckbox('non_admins_set_read_exceptions', $tab, $section, true);
                    $ui->optionCheckbox('non_admins_set_edit_exceptions', $tab, $section, true);
                    ?>
                    </div>

                    <?php
                    do_action('presspermit_options_ui_insertion', $tab, $section, $ui);

                    if (defined('PP_ADMIN_READONLY_LISTABLE') && (!$pp->getOption('admin_hide_uneditable_posts') || defined('PP_ADMIN_POSTS_NO_FILTER'))) {
                        $hint = SettingsAdmin::getStr('posts_listing_unmodified');
                    } else {
                        $hint = '';
                    }
                    
                    if ($hint):?>
                    <br />
                    <div class="pp-subtext pp-subtext-show">
                    <?php
                    printf(esc_html__('%sPosts / Pages Listing:%s %s', 'press-permit-core'), '<b>', '</b>', esc_html($hint));
                    ?>
                    </div>
                    <?php endif;?>
                </td>
            </tr>
            <?php
        endif; // any options accessable in this section

        $section = 'front_end'; // --- FRONT END SECTION ---
        if (!empty($ui->form_options[$tab][$section])) :
        ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    $ui->optionCheckbox('media_search_results', $tab, $section, '');

                    $hint = SettingsAdmin::getStr('strip_private_caption');
                    $ui->optionCheckbox('strip_private_caption', $tab, $section, $hint);

                    ?>
                    <div style="margin-top:30px">
                    <?php
                    $ui->optionCheckbox('anonymous_unfiltered', $tab, $section, true);

                    $ui->optionCheckbox('regulate_category_archive_page', $tab, $section, true);

                    $ui->optionCheckbox('limit_front_end_term_filtering', $tab, $section, true);

                    $ui->optionCheckbox('term_counts_unfiltered', $tab, $section, '');

                    if (defined('UBERMENU_VERSION')) {
                        $hint = SettingsAdmin::getStr('force_nav_menu_filter');
                        $ui->optionCheckbox('force_nav_menu_filter', $tab, $section, $hint);
                    }

                    do_action('presspermit_options_ui_insertion', $tab, $section, $ui);
                    ?>
                    </div>
                </td>
            </tr>
        <?php
        endif; // any options accessable in this section

        $section = 'user_management';                                    // --- USER MANAGEMENT SECTION ---
        if (!empty($ui->form_options[$tab][$section])) : 
        ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    $hint = '';

                    if (!defined('is_multisite()')) {
                        $ui->optionCheckbox('new_user_groups_ui', $tab, $section, $hint, '<br />');
                    }

                    $hint = SettingsAdmin::getStr('display_user_profile_roles');
                    $ui->optionCheckbox('display_user_profile_groups', $tab, $section);
                    $ui->optionCheckbox('display_user_profile_roles', $tab, $section, $hint);

                    $ui->optionCheckbox('user_search_by_role', $tab, $section, true);

                    ?>
                    <div style="margin-top:30px">
                    <?php
                    $ui->optionCheckbox('users_bulk_groups', $tab, $section, '');
                    $ui->optionCheckbox('add_author_pages', $tab, $section, true, '');

                    $div_style = ($pp->getOption('add_author_pages')) ? '' : 'display:none';
                    $ui->optionCheckbox('publish_author_pages', $tab, $section, '', '', compact('div_style'));
                    ?>
                    </div>

                    <?php if (in_array('limit_user_edit_by_level', $ui->form_options[$tab][$section])):
                        $option_name = 'limit_user_edit_by_level';
                        $ui->all_options[] = $option_name;
                        if (!$option_val = $ui->getOption($option_name)) {
                            $option_val = '0';
                        }
                        ?>
                        <div style="margin-top:30px">
                        <?php
                        esc_html_e('User editing capabilities apply for', 'press-permit-core');
                        echo "&nbsp;<select name='" . esc_attr($option_name) . "' id='" . esc_attr($option_name) . "' autocomplete='off'>";

                        $captions = ['0' => esc_html__("any user", 'press-permit-core'), '1' => esc_html__("equal or lower role levels", 'press-permit-core'), 'lower_levels' => esc_html__("lower role levels", 'press-permit-core')];
                        foreach ($captions as $key => $value) {
                            $selected = ($option_val == $key) ? 'selected="' : '';
                            echo "\n\t<option value='" . esc_attr($key) . "' " . esc_attr($selected) . ">" . esc_html($captions[$key]) . "</option>";
                        }
                        ?>
                        </select></div>

                        <div class='pp-subtext'>
                            <?php
                            SettingsAdmin::echoStr('limit_user_edit_by_level');
                            ?>
                        </div>
                    <?php endif;?>

                    <?php
                    do_action('presspermit_options_ui_insertion', $tab, $section, $ui);
                    ?>
                </td>
            </tr>
        <?php endif;

        $section = 'nav_menu_management'; // --- NAV MENU MANAGEMENT SECTION ---
        if (!empty($ui->form_options[$tab][$section])) : ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    $ui->optionCheckbox('admin_nav_menu_partial_editing', $tab, $section, true, '');

                    $ui->optionCheckbox('admin_nav_menu_lock_custom', $tab, $section, true, '');
                    ?>
                </td>
            </tr>
        <?php endif; // any options accessable in this section

        $section = 'role_integration'; // --- ROLE INTEGRATION SECTION ---
        ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    $ui->optionCheckbox('pattern_roles_include_generic_rolecaps', $tab, $section, true, '');

                    do_action('presspermit_options_ui_insertion', $tab, $section, $ui);
                    ?>

                    <div style="font-style: italic">
                        <?php printf(
                            esc_html__('To control how Extra Roles are applied, see %1$sRole Usage%2$s.', 'press-permit-core'),
                            '<strong><a href="' . esc_url(admin_url('admin.php?page=presspermit-role-usage')) . '">',
                            '</a></strong>'
                        );
                        ?>
                    </div>
                    <br />

                    <div>
                        <?php
                        $args = (defined('PP_FORCE_DYNAMIC_ROLES')) ? ['val' => 1, 'no_storage' => true, 'disabled' => true] : [];
                        $ui->optionCheckbox('dynamic_wp_roles', $tab, $section, true, '', $args);
                        ?>
                    </div>
                </td>
            </tr>
        <?php

        $section = 'misc'; // --- MISC SECTION ---
        if (!empty($ui->form_options[$tab][$section])) : ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    $ui->optionCheckbox('display_hints', $tab, $section, true);

                    if (presspermit()->isPro()) {
                        $ui->optionCheckbox('display_extension_hints', $tab, $section, true);
                    }

                    do_action('presspermit_options_ui_insertion', $tab, $section, $ui);
                    ?>
                </td>
            </tr>
        <?php endif; // any options accessable in this section

        $section = 'capabilities'; // --- PP CAPABILITIES SECTION ---
        ?>
        <tr>
            <td scope="row" colspan="2"><span
                    style="font-weight:bold"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></span>
                <span class="pp-capabilities-caption">
                    <span class="pp-subtext pp-no-hide">
                        <?php
                        if (defined('PUBLISHPRESS_CAPS_VERSION')) {
                            $url = admin_url('admin.php?page=pp-capabilities&pp_caps_tab=publishpress-permissions');
                            printf(
                                esc_html(SettingsAdmin::getStr('pp_capabilities')),
                                '<a href="' . esc_url($url) . '">',
                                '</a>'
                            );
                        } else {
                            printf(
                                esc_html(SettingsAdmin::getStr('pp_capabilities_install_prompt')),
                                '<span class="plugins update-message"><a href="' . esc_url(Settings::pluginInfoURL('capability-manager-enhanced'))
                                    . '" class="thickbox" title=" PublishPress Capabilities">PublishPress&nbsp;Capabilities</a></span>'
                            );
                        }
                        ?>
                    </span>
                </span>
            </td>
        </tr>
        <?php

        $section = 'constants'; // --- CONSTANTS SECTION ---

        // don't display the section unless constants are defined or debug / constant display option enabled
        require_once(PRESSPERMIT_CLASSPATH . '/Constants.php');
        $ppc = new \PublishPress\Permissions\Constants();

        $defined_constant_types = [];

        foreach ($ppc->constants_by_type as $const_type => $constants) {
            foreach ($constants as $const_name) {
                if (defined($const_name)            // disregard constants that are internally set to a default value
                && (!in_array($const_name, ['PRESSPERMIT_DEBUG', 'PRESSPERMIT_LEGACY_HOOKS']) || constant($const_name))
                && (('PUBLISHPRESS_ACTION_PRIORITY_INIT' != $const_name) || (10 != constant($const_name)))
                ) {
                    $defined_constant_types[$const_type] = true;
                    break;
                }
            }
        }

        if ($this->enabled || $defined_constant_types) :?>
            <tr>
                <td scope="row" colspan="2">
                    <span style="font-weight:bold"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></span>
                    <?php if ($this->enabled):?>
                    <br /><br />
                    <?php
                    $ui->optionCheckbox('list_all_constants', $tab, $section, true, '');
                    endif; ?>

                    <?php if ($defined_constant_types || presspermit()->getOption('list_all_constants') || (defined('PRESSPERMIT_DEBUG') && PRESSPERMIT_DEBUG)) : ?>

                        <?php if ($defined_constant_types): ?>
                            <table id="pp_defined_constants" class="pp_cap_descripts" style="width: 99%">
                                <thead>
                                    <tr>
                                        <th class="cap-name"><?php esc_html_e('Defined Constant', 'press-permit-core'); ?></th>
                                        <th class="const-value"><?php echo esc_html__('Setting', 'press-permit-core'); ?></th>
                                        <th><?php echo esc_html__('Description', 'press-permit-core'); ?></th>
                                    </tr>
                                </thead>

                                <colgroup>
                                    <col span="1" style="width: 30%;">
                                    <col span="1" style="width: 5%;">
                                    <col span="1" style="width: 65%;">
                                </colgroup>

                                <tbody>

                                    <?php
                                    foreach (array_keys($defined_constant_types) as $const_type) :
                                    ?>
                                        <?php
                                        foreach ($ppc->constants_by_type[$const_type] as $const_name) :
                                            if (
                                                !defined($const_name) || !isset($ppc->constants[$const_name])
                                                || !empty($ppc->constants[$const_name]->suppress_display)
                                            ) {
                                                continue;
                                            }
                                        ?>
                                            <tr>
                                                <td class="cap-name"><?php echo esc_html($const_name); ?></td>
                                                <td><?php
                                                    $const_val = constant($const_name);

                                                    if (false === $const_val) {
                                                        $const_val = 'false';
                                                    } elseif (true === $const_val) {
                                                        $const_val = 'true';
                                                    }

                                                    echo esc_html(strval($const_val));
                                                    ?>
                                                </td>
                                                <td><?php echo esc_html($ppc->constants[$const_name]->descript); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if ($this->enabled) : // Unless debugging, only list defined constants and available constants in the same section ?>
                        <br />
                        <table id="pp_available_constants" <?php if (!presspermit()->getOption('list_all_constants')) echo 'style="display:none;"';?> class="pp_cap_descripts<?php if (!presspermit()->getOption('list_all_constants') && (!defined('PRESSPERMIT_DEBUG') || ! PRESSPERMIT_DEBUG)) echo ' pp-hint'; ?>" style="width: 99%">
                            <thead>
                                <tr>
                                    <th class="cap-name" style="width:40%"><?php esc_html_e('Available Constant', 'press-permit-core'); ?></th>
                                    <th class="const-value"><?php echo esc_html__('Setting', 'press-permit-core'); ?></th>
                                    <th style="width:55%"><?php echo esc_html__('Description', 'press-permit-core'); ?></th>
                                </tr>
                            </thead>

                            <colgroup>
                                <col span="1" style="width: 30%;">
                                <col span="1" style="width: 5%;">
                                <col span="1" style="width: 65%;">
                            </colgroup>

                            <tbody>

                                <?php
                                foreach ($ppc->constants_by_type as $const_type => $constants) :
                                    // Unless debugging, only list constants in sections which already have a constant defined
                                    if (!isset($defined_constant_types[$const_type]) && (!presspermit()->getOption('list_all_constants') && (!defined('PRESSPERMIT_DEBUG') || ! PRESSPERMIT_DEBUG))) {
                                        continue;
                                    }
                                ?>
                                    <?php if (isset($ppc->constant_types[$const_type])) : ?>
                                        <tr class="const-section">
                                            <td>--- <?php echo esc_html($ppc->constant_types[$const_type]); ?> ---</td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php
                                    foreach ($constants as $const_name) :
                                        if (!isset($ppc->constants[$const_name]) || !empty($ppc->constants[$const_name]->suppress_display)) {
                                            continue;
                                        }
                                        $class = (defined($const_name)) ? ' defined' : '';
                                    ?>
                                        <tr>
                                            <td class="cap-name<?php echo esc_attr($class); ?>"><?php echo esc_html($const_name); ?></td>

                                            <td class="<?php echo esc_attr($class); ?>">
                                                <?php
                                                if (defined($const_name)) {
                                                    $const_val = constant($const_name);

                                                    if (false === $const_val) {
                                                        $const_val = 'false';
                                                    } elseif (true === $const_val) {
                                                        $const_val = 'true';
                                                    }

                                                    echo esc_html(strval($const_val));
                                                }
                                                ?>
                                            </td>

                                            <td class="<?php echo esc_attr($class); ?>"><?php echo esc_html($ppc->constants[$const_name]->descript); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif;?>
                    <?php endif; ?>
                </td>
            </tr>

            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function ($) {
                    $('input#list_all_constants').on('click', function() {
                        $('#pp_available_constants').toggle($(this).prop('checked'));
                    });
                });
                /* ]]> */
            </script>
        <?php endif;

        if (is_multisite()) {
            $section = 'network';
        ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>

                    <div id="pp_modify_default_settings" class="pp-settings-code">
                        <?php
                        $msg = esc_html__("To modify one or more default settings network-wide, <strong>copy</strong> the following code into your theme's <strong>functions.php</strong> file (or some other file which is always executed and not auto-updated) and modify as desired:", 'press-permit-core');
                        $msg = str_replace(['&lt;strong&gt;', '&lt;/strong&gt;'], '', $msg);
                        _e($msg);
                        ?>
                        <textarea rows='10' cols='150' readonly='readonly'>
    // Use this filter if you want to change the default, but still allow manual setting
    add_filter( 'presspermit_default_options', 'my_presspermit_default_options', 99 );

    public function my_presspermit_default_options( $def_options ) {
        // Array key corresponds to name attributes of checkboxes, dropdowns and input boxes. Modify for desired default settings.

        $def_options['new_user_groups_ui'] = 0;

        return $def_options;
    }
                                                            </textarea>
                    </div>
                    <br />

                    <div id="pp_force_settings" class="pp-settings-code">
                        <?php
                        $msg  = esc_html__("To force the value of one or more settings network-wide, <strong>copy</strong> the following code into your theme's <strong>functions.php</strong> file (or some other file which is always executed and not auto-updated) and modify as desired:", 'press-permit-core');
                        $msg = str_replace(['&lt;strong&gt;', '&lt;/strong&gt;'], '', $msg);
                        _e($msg);
                        ?>
                        <textarea rows='13' cols='150' readonly='readonly'>
    // Use this filter if you want to force an option, blocking/disregarding manual setting
    add_filter( 'presspermit_options', 'my_presspermit_options', 99 );

    // Use this filter if you also want to hide an option from the PP settings screen (works for most options)
    add_filter( 'presspermit_hide_options', 'my_presspermit_options', 99 );

    public function my_presspermit_options( $options ) {
        // Array key corresponds to pp_prefixed name attributes of checkboxes, dropdowns and input boxes. 
        // Modify for desired settings.

        // note: advanced options can be forced here even if advanced settings are disabled
        $options['presspermit_new_user_groups_ui'] = 1;
        $options['presspermit_display_hints'] = 0;

        return $options;
    }
                                                            </textarea>
                    </div>

                </td>
            </tr>
        <?php
        }

        ?>
        <tr>
            <th></th>
            <td>

                <?php
                $msg = esc_html__("All settings in this form (including those on undisplayed tabs) will be reset to DEFAULTS.  Are you sure?", 'press-permit-core');
                ?>
                <p class="submit pp-submit-alternate" style="border:none;float:right">
                    <input type="submit" name="presspermit_defaults" value="<?php esc_attr_e('Revert to Defaults', 'press-permit-core') ?>"
                        onclick="<?php echo "javascript:if (confirm('" . esc_attr($msg) . "')) {return true;} else {return false;}"; ?>" />
                </p>

            </td>
        </tr>
<?php
    }
}

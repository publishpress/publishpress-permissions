<?php

namespace PublishPress\Permissions\UI;

class SettingsTabCore
{
    public function __construct()
    {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 2);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_core_options_pre_ui', [$this, 'optionsPreUI']);
        add_action('presspermit_core_options_ui', [$this, 'optionsUI']);
    }

    public function optionTabs($tabs)
    {
        $tabs['core'] = esc_html__('General', 'press-permit-core');
        return $tabs;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'taxonomies' => esc_html__('Filtered Taxonomies', 'press-permit-core'),
            'post_types' => esc_html__('Filtered Post Types', 'press-permit-core'),
            'db_maint' => esc_html__('Database Maintenance', 'press-permit-core'),
        ];

        $key = 'core';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;

        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'enabled_taxonomies' => esc_html__('Filtered Taxonomies', 'press-permit-core'),
            'enabled_post_types' => esc_html__('Filtered Post Types', 'press-permit-core'),
            'define_media_post_caps' => esc_html__('Enforce distinct edit, delete capability requirements for Media', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'taxonomies' => ['enabled_taxonomies'],
            'post_types' => ['enabled_post_types', 'define_media_post_caps'],
        ];

        $key = 'core';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsPreUI() {}

    public function optionsUI()
    {
        $pp = presspermit();

        $ui = SettingsAdmin::instance();
        $tab = 'core';

        // --- FILTERED TAXONOMIES / POST TYPES SECTION ---
        foreach (['object' => 'post_types', 'term' => 'taxonomies'] as $scope => $section) {
            if (empty($ui->form_options[$tab][$section])) {
                continue;
            } ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    if ('term' == $scope) {
                        $option_name = 'enabled_taxonomies';
                        esc_html_e('Modify permissions for these Taxonomies:', 'press-permit-core');
                        echo '<br />';

                        $_args = (defined('PRESSPERMIT_FILTER_PRIVATE_TAXONOMIES')) ? [] : ['public' => true];
                        $types = get_taxonomies($_args, 'object');

                        if (taxonomy_exists('nav_menu')) {
                            $types['nav_menu'] = get_taxonomy('nav_menu');
                        }

                        if ($omit_types = $pp->getUnfilteredTaxonomies()) // avoid confusion with PublishPress administrative taxonomy
                        {
                            if (!defined('PRESSPERMIT_FILTER_PRIVATE_TAXONOMIES')) {
                                $types = array_diff_key($types, array_fill_keys((array)$omit_types, true));
                            }
                        }

                        $hidden_types = apply_filters('presspermit_hidden_taxonomies', array_fill_keys(['nav_menu', 'post_status_core_wp_pp', 'pseudo_status_pp', 'post_visibility_pp'], true));

                        // Legacy Nav Menu filtering is currently locked on by a filter in the Editing Permissions module, so reflect that in option storage
                        $locked_types = apply_filters('presspermit_locked_taxonomies', defined('PRESSPERMIT_COLLAB_VERSION') ? ['nav_menu' => true] : []);

                        if (defined('PRESSPERMIT_FILTER_PRIVATE_TAXONOMIES')) {
                            $hidden_types = [];
                        } else {
                            $types = $pp->admin()->orderTypes($types);
                        }
                    } else {
                        $option_name = 'enabled_post_types';
                        esc_html_e('Modify permissions for these Post Types:', 'press-permit-core');
                        $types = get_post_types(['public' => true, 'show_ui' => true], 'object', 'or');
                        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
                        $supported_private_types = apply_filters('presspermit_supported_private_types', []);    // ['series_grouping']);

                        $types = array_merge($types, array_fill_keys($supported_private_types, true));

                        // todo: review wp_block permissions filtering
                        $omit_types = apply_filters('presspermit_unfiltered_post_types', ['wp_block']);

                        if ($omit_types) {
                            $types = array_diff_key($types, array_fill_keys((array)$omit_types, true));
                        }

                        $hidden_types = apply_filters('presspermit_hidden_post_types', []);

                        $locked_types = apply_filters('presspermit_locked_post_types', ['nav_menu' => 'nav_menu']);

                        $types = $pp->admin()->orderTypes($types);
                    }

                    $ui->all_otype_options[] = $option_name;

                    if (isset($pp->default_options[$option_name])) {
                        $filter_name = ('term' == $scope) ? 'presspermit_enabled_taxonomies_by_key' : 'presspermit_enabled_post_types';

                        if (!$enabled = apply_filters($filter_name, $ui->getOption($option_name))) {
                            $enabled = [];
                        }

                        foreach ($types as $key => $obj) {
                            if (!$key) {
                                continue;
                            }

                            $id = $option_name . '-' . $key;
                            $name = $option_name . "[$key]";
                    ?>

                            <?php if (isset($hidden_types[$key])) : ?>
                                <input name="<?php echo esc_attr($name); ?>" type="hidden" value="<?php echo esc_attr($hidden_types[$key]); ?>" />
                            <?php else :
                                $locked = (!empty($locked_types[$key])) ? ' disabled ' : '';
                            ?>
                                <div class="agp-vtight_input">
                                    <input name="<?php echo esc_attr($name); ?>" type="hidden" value="<?php echo (empty($locked_types[$key])) ? '0' : '1'; ?>" />
                                    <label for="<?php echo esc_attr($id); ?>" title="<?php echo esc_attr($key); ?>">
                                        <input name="<?php if (empty($locked_types[$key])) echo esc_attr($name); ?>" type="checkbox" id="<?php echo esc_attr($id); ?>"
                                            value="1" <?php checked('1', !empty($enabled[$key]));
                                                        echo esc_attr($locked); ?> />

                                        <?php
                                        if (isset($obj->labels_pp)) {
                                            echo esc_html($obj->labels_pp->name);
                                        } elseif ('nav_menu' == $key) {    // @todo: use labels_pp property?
                                            if (in_array(get_locale(), ['en_EN', 'en_US'])) {
                                                esc_html_e('Nav Menus (Legacy)', 'press-permit-core');
                                            } else {
                                                echo esc_html($obj->labels->singular_name) . ' (' . esc_html__('Legacy', 'press-permit-core') . ')';
                                            }
                                        } elseif ('wp_navigation' == $key) {    // @todo: use labels_pp property?
                                            if (in_array(get_locale(), ['en_EN', 'en_US'])) {
                                                esc_html_e('Nav Menus (Block)', 'press-permit-core');
                                            } else {
                                                echo esc_html($obj->labels->singular_name) . ' (' . esc_html__('Block', 'press-permit-core') . ')';
                                            }
                                        } elseif (isset($obj->labels->name)) {
                                            echo esc_html($obj->labels->name);
                                        } else {
                                            echo esc_html($key);
                                        }

                                        echo '</label>';

                                        if (!empty($locked_types[$key]) && isset($key) && $key == 'attachment') {
                                            $this->generateTooltip(esc_html__('This feature allows you to restrict access to Media Library files when they are accessed via WordPress. To restrict viewing of files when they are visited directly via the URL, use the "File Access" feature.', 'press-permit-core'),'','top');
                                        }

                                        if (!empty($enabled[$key]) && isset($obj->capability_type) && !in_array($obj->capability_type, [$obj->name, 'post', 'page'])) {
                                            if ($cap_type_obj = get_post_type_object($obj->capability_type)) {
                                                echo '&nbsp;(' . esc_html(sprintf(__('%s capabilities'), $cap_type_obj->labels->singular_name)) . ')';
                                            }
                                        }

                                        echo '</div>';
                                    endif; // displaying checkbox UI

                                    if ('nav_menu' == $key) : ?>
                                        <!-- <input name="<?php echo esc_attr($name); ?>" type="hidden" id="<?php echo esc_attr($id); ?>" value="1"/> -->
                                    <?php endif;
                                } // end foreach src_otype
                            } // endif default option isset  // phpcs:ignore Squiz.PHP.CommentedOutCode.Found

                            if ('object' == $scope) {
                                if ($pp->getOption('display_hints')) {
                                    $define_create_posts_cap = get_option('presspermit_define_create_posts_cap');

                                    ?>
                                    <div class="pp-subtext pp-no-hide" style="margin-top: 15px">
                                        <?php
                                        printf(
                                            esc_html__('%1$sNote%2$s: This causes type-specific capabilities to be required for editing ("edit_things" instead of "edit_posts").', 'press-permit-core'),
                                            '<span class="pp-important">',
                                            '</span>',
                                            "<a href='" . esc_url(admin_url('?page=presspermit-groups')) . "'>",
                                            '</a>'
                                        );
                                        ?>
                                    </div>

                                    <?php if (
                                        in_array('forum', $types, true) && !$pp->moduleActive('compatibility')
                                        && $pp->getOption('display_extension_hints')
                                    ) : ?>
                                        <div class="pp-subtext pp-settings-caption">
                                            <?php
                                            if ($pp->keyActive()) {
                                                SettingsAdmin::echoStr('bbp_compat_prompt');
                                            } else {
                                                SettingsAdmin::echoStr('bbp_pro_prompt');
                                            }

                                            ?>
                                        </div>
                            <?php
                                    endif;
                                }

                                if (in_array('attachment', presspermit()->getEnabledPostTypes(), true)) {
                                    echo '<br><div>';

                                    $hint = defined('PRESSPERMIT_COLLAB_VERSION')
                                        ? SettingsAdmin::getStr('define_media_post_caps')
                                        : SettingsAdmin::getStr('define_media_post_caps_collab_prompt');

                                    $ret = $ui->optionCheckbox('define_media_post_caps', $tab, $section, $hint, '');

                                    echo '</div>';
                                }
                            }
                            ?>
                </td>
            </tr>
        <?php
        } // end foreach scope
    }

    function generateTooltip($tooltip, $text = '', $position = 'top', $useIcon = true)
    {
        ?>
        <span data-toggle="tooltip" data-placement="<?php esc_attr_e($position); ?>">
        <?php esc_html_e($text);?>
        <span class="tooltip-text"><span><?php esc_html_e($tooltip);?></span><i></i></span>
        <?php 
        if ($useIcon) : ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 50 50" style="margin-left: 4px; vertical-align: text-bottom;">
                <path d="M 25 2 C 12.264481 2 2 12.264481 2 25 C 2 37.735519 12.264481 48 25 48 C 37.735519 48 48 37.735519 48 25 C 48 12.264481 37.735519 2 25 2 z M 25 4 C 36.664481 4 46 13.335519 46 25 C 46 36.664481 36.664481 46 25 46 C 13.335519 46 4 36.664481 4 25 C 4 13.335519 13.335519 4 25 4 z M 25 11 A 3 3 0 0 0 25 17 A 3 3 0 0 0 25 11 z M 21 21 L 21 23 L 23 23 L 23 36 L 21 36 L 21 38 L 29 38 L 29 36 L 27 36 L 27 21 L 21 21 z"></path>
            </svg>
        <?php
        endif; ?>
        </span>
        <?php
    }
}

<?php

namespace PublishPress\Permissions\UI;

use PublishPress\Permissions\Factory;

class SettingsTabModules
{
    public function __construct()
    {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 80);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_modules_options_ui', [$this, 'optionsUI']);
    }

    public function optionTabs($tabs)
    {
        $tabs['modules'] = esc_html__('Features', 'press-permit-core');
        return $tabs;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'modules' => '',
            'help' => PWP::__wp('Help'),
        ];

        $key = 'modules';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'help' => esc_html__('settings', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'help' => ['no_option'],
            'modules' => ['no_option'],
        ];

        $key = 'modules';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsUI()
    {
        $pp = presspermit();

        $ui = SettingsAdmin::instance();
        $tab = 'modules';

        $section = 'modules'; // --- EXTENSIONS SECTION ---
        if (!empty($ui->form_options[$tab][$section])): ?>
            <tr>
                <td>
                    <div class="pp-modules-settings">
                        <?php
                        $ext_info = $pp->admin()->getModuleInfo();
                        $pp_modules = presspermit()->getActiveModules();
                        $inactive = $pp->getDeactivatedModules();
                        $active_module_plugin_slugs = [];

                        // Combine active and inactive modules into single array
                        $all_modules = [];

                        // Add active modules
                        if ($pp_modules) {
                            foreach ($pp_modules as $slug => $plugin_info) {
                                $active_module_plugin_slugs[] = $plugin_info->plugin_slug;
                                $all_modules[] = [
                                    'slug' => $slug,
                                    'plugin_slug' => $plugin_info->plugin_slug,
                                    'plugin_info' => $plugin_info,
                                    'is_active' => true
                                ];
                            }
                        }

                        // Add inactive modules
                        if ($inactive) {
                            foreach ($inactive as $plugin_slug => $module_info) {
                                $slug = str_replace('presspermit-', '', $plugin_slug);
                                $all_modules[] = [
                                    'slug' => $slug,
                                    'plugin_slug' => $plugin_slug,
                                    'module_info' => $module_info,
                                    'is_active' => false
                                ];
                            }
                        }

                        if (!empty($all_modules)): ?>
                            <div class="pp-integrations-container">
                                <div class="pp-integrations-grid">
                                    <?php foreach ($all_modules as $module):
                                        $slug = $module['slug'];
                                        $is_active = $module['is_active'];
                                        $plugin_slug = $module['plugin_slug'];

                                        // Get title and info
                                        if ($is_active) {
                                            $title = (!empty($ext_info->title[$slug])) ? $ext_info->title[$slug] : $module['plugin_info']->label;
                                        } else {
                                            $title = (!empty($ext_info->title[$slug])) ? $ext_info->title[$slug] : $this->prettySlug($slug);
                                        }

                                        $card_classes = 'pp-integration-card pp-disabled';
                                        if ($is_active) {
                                            $card_classes .= ' pp-available';
                                        }
                                        ?>
                                        <div class="<?php echo esc_attr($card_classes); ?>">
                                            <span class="pp-integration-icon dashicons dashicons-edit"></span>
                                            <div class="pp-integration-content">
                                                <h3 class="pp-integration-title" title="<?php echo esc_attr($title); ?>">
                                                    <?php echo esc_html($title); ?>
                                                    <?php if ($is_active): ?>
                                                        <span class="pp-badge"
                                                            style="background: #5e92c4"><?php echo esc_html__('Active', 'press-permit-core'); ?></span>
                                                    <?php else: ?>
                                                        <span class="pp-badge"
                                                            style="background: #b0b0b0"><?php echo esc_html__('Inactive', 'press-permit-core'); ?></span>
                                                    <?php endif; ?>
                                                </h3>

                                                <p class="pp-integration-description">
                                                    <?php if (!empty($ext_info) && isset($ext_info->blurb[$slug])): ?>
                                                        <span class="pp-ext-info" title="<?php if (isset($ext_info->descript[$slug])) {
                                                            echo esc_attr($ext_info->descript[$slug]);
                                                        }
                                                        ?>">
                                                            <?php echo esc_html($ext_info->blurb[$slug]); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </p>

                                                <?php if (isset($ext_info->descript[$slug])) :?>
                                                    <div class="pp-integration-features" title="<?php echo esc_attr($ext_info->descript[$slug]); ?>">
                                                        <?php echo esc_html($ext_info->descript[$slug]); ?>
                                                    </div>
                                                <?php endif; ?>

                                            </div>
                                            <div class="pp-settings-wrapper">
                                                <div class="pp-settings-toggle">
                                                    <?php $id = "module_{$slug}"; ?>
                                                    <label class="pp-toggle-switch" for="<?php echo esc_attr($id); ?>">
                                                        <input type="checkbox" id="<?php echo esc_attr($id); ?>"
                                                            name="<?php echo $is_active ? 'presspermit_active_modules' : 'presspermit_deactivated_modules'; ?>[<?php echo esc_attr($plugin_slug); ?>]"
                                                            value="1" <?php echo $is_active ? 'checked="checked"' : ''; ?> />
                                                        <span class="pp-slider"></span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php do_action('presspermit_modules_ui', $active_module_plugin_slugs, $inactive); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php
                        $modules_csv = implode(',', $active_module_plugin_slugs);
                        echo "<input type='hidden' name='presspermit_reviewed_modules' value='" . esc_attr($modules_csv) . "' />";
                        ?>
                    </div>
                    </div>
                </td>
            </tr>
            <?php
        endif; // any options accessable in this section
    }

    private function prettySlug($slug)
    {
        $slug = str_replace('presspermit-', '', $slug);
        $slug = str_replace('Pp', 'PP', ucwords(str_replace('-', ' ', $slug)));
        $slug = str_replace('press', 'Press', $slug); // temp workaround
        $slug = str_replace('Wpml', 'WPML', $slug);
        return $slug;
    }
}

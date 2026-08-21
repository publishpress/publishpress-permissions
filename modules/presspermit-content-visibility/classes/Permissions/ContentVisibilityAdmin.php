<?php

namespace PublishPress\Permissions;

use PublishPress\Permissions\UI\SettingsAdmin;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Bundled module strings share the parent plugin translation catalog.

/**
 * Adds Content Visibility documentation to the Permissions settings screen.
 */
class ContentVisibilityAdmin
{
    public function __construct()
    {
        add_action('presspermit_options_ui', [$this, 'registerSettingsHooks']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Registers tab hooks only while the main Permissions settings UI loads.
     */
    public function registerSettingsHooks()
    {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 70);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);
        add_action('presspermit_shortcodes_options_ui', [$this, 'optionsUI']);
    }

    /**
     * Registers the module-owned settings tab.
     *
     * @param array $tabs Settings tab captions.
     * @return array
     */
    public function optionTabs($tabs)
    {
        $tabs['shortcodes'] = esc_html__('Shortcodes', 'press-permit-core');

        return $tabs;
    }

    /**
     * Registers the left-column section captions.
     *
     * @param array $sections Settings section captions.
     * @return array
     */
    public function sectionCaptions($sections)
    {
        $new = [
            'content_visibility' => esc_html__('Content Visibility', 'press-permit-core'),
            'legacy_compatibility' => esc_html__('Legacy Compatibility', 'press-permit-core'),
        ];

        $key = 'shortcodes';
        $sections[$key] = isset($sections[$key])
            ? array_merge($sections[$key], $new)
            : $new;

        return $sections;
    }

    /**
     * Adds non-persistent sections so the settings screen renders the tab.
     *
     * @param array $sections Settings option sections.
     * @return array
     */
    public function optionSections($sections)
    {
        $new = [
            'content_visibility' => ['no_option'],
            'legacy_compatibility' => ['no_option'],
        ];

        $key = 'shortcodes';
        $sections[$key] = isset($sections[$key])
            ? array_merge($sections[$key], $new)
            : $new;

        return $sections;
    }

    /**
     * Loads the copy control only on the Permissions settings screen.
     */
    public function enqueueAssets()
    {
        if ('presspermit-settings' !== presspermitPluginPage()) {
            return;
        }

        $urlpath = plugins_url('', PRESSPERMIT_CONTENT_VISIBILITY_FILE);

        wp_enqueue_style(
            'presspermit-content-visibility-settings',
            $urlpath . '/common/css/settings.css',
            [],
            PRESSPERMIT_CONTENT_VISIBILITY_VERSION
        );

        wp_enqueue_script(
            'presspermit-content-visibility-settings',
            $urlpath . '/common/js/settings.js',
            ['jquery'],
            PRESSPERMIT_CONTENT_VISIBILITY_VERSION,
            true
        );
    }

    /**
     * Renders the shortcode reference.
     */
    public function optionsUI()
    {
        $ui = SettingsAdmin::instance();
        $tab = 'shortcodes';
        $sections = $this->getShortcodeSections();

        foreach ($sections as $section => $details) {
            if (empty($ui->form_options[$tab][$section])) {
                continue;
            }
            ?>
            <tr class="pp-content-visibility-shortcodes-row">
                <th scope="row">
                    <?php echo esc_html($ui->section_captions[$tab][$section]); ?>
                </th>
                <td>
                    <p class="pp-content-visibility-shortcodes-intro">
                        <?php echo esc_html($details['description']); ?>
                    </p>

                    <?php foreach ($details['examples'] as $example) : ?>
                        <div class="pp-content-visibility-shortcode-example">
                            <p class="pp-content-visibility-shortcode-description">
                                <?php echo esc_html($example['description']); ?>
                            </p>
                            <?php $this->renderShortcodeField($example['shortcode']); ?>
                        </div>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php
        }
    }

    /**
     * Returns the documented native and compatibility shortcode examples.
     *
     * @return array
     */
    private function getShortcodeSections()
    {
        return [
            'content_visibility' => [
                'description' => __(
                    'Use the pp_restrict shortcode to show or hide enclosed content based on the current visitor.',
                    'press-permit-core'
                ),
                'examples' => [
                    [
                        'description' => __('Show content only to logged-in visitors.', 'press-permit-core'),
                        'shortcode' => '[pp_restrict logged="in"]Members-only content.[/pp_restrict]',
                    ],
                    [
                        'description' => __('Show content only to logged-out visitors.', 'press-permit-core'),
                        'shortcode' => '[pp_restrict logged="out"]Guest content.[/pp_restrict]',
                    ],
                    [
                        'description' => __('Allow visitors with any listed WordPress role.', 'press-permit-core'),
                        'shortcode' => '[pp_restrict roles="editor,author"]Editorial content.[/pp_restrict]',
                    ],
                    [
                        'description' => __('Allow visitors with any listed WordPress capability.', 'press-permit-core'),
                        'shortcode' => '[pp_restrict capabilities="manage_options"]Site managers only.[/pp_restrict]',
                    ],
                    [
                        'description' => __('Allow visitors with any listed WordPress username.', 'press-permit-core'),
                        'shortcode' => '[pp_restrict usernames="sam,taylor"]Named users only.[/pp_restrict]',
                    ],
                    [
                        'description' => __('Allow members of any listed PublishPress Permission Group.', 'press-permit-core'),
                        'shortcode' => '[pp_restrict groups="12,18"]Permission Group members only.[/pp_restrict]',
                    ],
                    [
                        'description' => __('Use relation="any" when any populated condition may grant access.', 'press-permit-core'),
                        'shortcode' => '[pp_restrict roles="editor" groups="12" relation="any"]Editors or Group 12 members.[/pp_restrict]',
                    ],
                    [
                        'description' => __('Use hide="yes" to invert the result of the configured conditions.', 'press-permit-core'),
                        'shortcode' => '[pp_restrict logged="in" hide="yes"]Logged-out visitors only.[/pp_restrict]',
                    ],
                ],
            ],
            'legacy_compatibility' => [
                'description' => __(
                    'These migration aliases are available when another active plugin has not already registered the same shortcode. Legacy conditions use OR matching.',
                    'press-permit-core'
                ),
                'examples' => [
                    [
                        'description' => __('Restrict legacy content by login status.', 'press-permit-core'),
                        'shortcode' => '[eyesonly logged="in"]Members-only content.[/eyesonly]',
                    ],
                    [
                        'description' => __('Restrict legacy content by role or capability.', 'press-permit-core'),
                        'shortcode' => '[eyesonlier level="editor"]Editors only.[/eyesonlier]',
                    ],
                    [
                        'description' => __('Restrict legacy content by PublishPress Permission Group.', 'press-permit-core'),
                        'shortcode' => '[eyesonliest pp_group="12"]Group 12 members only.[/eyesonliest]',
                    ],
                ],
            ],
        ];
    }

    /**
     * Renders a readonly shortcode field and accessible copy button.
     *
     * @param string $shortcode Shortcode example.
     */
    private function renderShortcodeField($shortcode)
    {
        ?>
        <div class="pp-content-visibility-shortcode-control">
            <input
                class="pp-content-visibility-shortcode-field"
                type="text"
                value="<?php echo esc_attr($shortcode); ?>"
                aria-label="<?php esc_attr_e('Shortcode example', 'press-permit-core'); ?>"
                readonly
            />
            <button
                class="pp-content-visibility-copy-shortcode"
                type="button"
                aria-label="<?php esc_attr_e('Copy shortcode', 'press-permit-core'); ?>"
                data-copy-label="<?php esc_attr_e('Copy shortcode', 'press-permit-core'); ?>"
                data-copied-label="<?php esc_attr_e('Copied!', 'press-permit-core'); ?>"
            >
                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                <span class="pp-content-visibility-copy-tooltip" aria-hidden="true">
                    <?php esc_html_e('Copy shortcode', 'press-permit-core'); ?>
                </span>
            </button>
            <span
                class="screen-reader-text pp-content-visibility-copy-status"
                aria-live="polite"
            ></span>
        </div>
        <?php
    }
}

// phpcs:enable WordPress.WP.I18n.TextDomainMismatch

<?php
namespace PublishPress\Permissions\Teaser\UI;

/**
 * Progressive Disclosure UI for Teaser Settings
 * Implements modern card-based interface with step-by-step configuration
 */
class TeaserProgressiveUI {
    use TeaserUIBaseTrait;
    
    private $pp;
    private $ui;
    private $use_teaser;
    private $logged_only;
    private $hide_private;
    private $direct_only;
    private $hide_links;
    private $arr_num_chars;
    private $hide_thumbnail;
    private $blockEditorActive;

    public function __construct($pp, $ui, $use_teaser, $options_data, $blockEditorActive = true) {
        $this->pp = $pp;
        $this->ui = $ui;
        $this->use_teaser = $use_teaser;
        $this->logged_only = $options_data['logged_only'];
        $this->hide_private = $options_data['hide_private'];
        $this->direct_only = $options_data['direct_only'];
        $this->hide_links = $options_data['hide_links'];
        $this->arr_num_chars = $options_data['arr_num_chars'];
        $this->hide_thumbnail = $options_data['hide_thumbnail'];
        $this->blockEditorActive = $blockEditorActive;
    }

    public function render() {
        ?>
        <div id='teaser_usage-post' class="pp-teaser-progressive-ui">
            
            <!-- STEP 1: Select Post Type -->
            <?php $this->renderPostTypeSelector(); ?>

            <!-- Settings for each post type (shown/hidden based on selection) -->
            <?php foreach ($this->use_teaser as $object_type => $teaser_setting) : ?>
                <?php
                if (!in_array($object_type, $this->getAvailablePostTypes(), true)) {
                    // Skip unavailable post types
                    continue;
                }
                $this->renderPostTypeSettings($object_type, $teaser_setting); ?>
            <?php endforeach; ?>

        </div>
        <?php
    }

    private function renderPostTypeSelector() {
        $default_post_type = $this->isFeatureAvailable('post_type_' . array_key_first($this->use_teaser)) ? array_key_first($this->use_teaser) : 'post';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- POST data used only for display state, not saved
        $current_post_type = isset($_POST['selected_post_type']) ? sanitize_key($_POST['selected_post_type']) : $default_post_type;
        
        // Get available post types (filtered by trait in FREE, all in PRO)
        $available_post_types = $this->getAvailablePostTypes();
        
        ?>
        <!-- DEBUG: Available post types: <?php echo esc_html(implode(', ', $available_post_types)); ?> | use_teaser: <?php echo esc_html(implode(', ', array_keys($this->use_teaser))); ?> -->
        <div class="pp-post-type-selector">
            <table class="widefat fixed striped teaser-table">
                <thead>
                    <tr>
                        <th colspan="2">
                            <strong><?php esc_html_e('Select Post Type to Configure', 'press-permit-core'); ?></strong>
                            <?php $this->generateTooltip(esc_html__('Choose which post type you want to configure teaser settings for.', 'press-permit-core')); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="2">
                            <div class="pp-field-group">
                                <select id="pp_current_post_type" class="regular-text">
                                    <?php foreach ($this->use_teaser as $object_type => $teaser_setting) :                                         
                                        $type_obj = get_post_type_object($object_type);
                                        $item_label = $type_obj ? $type_obj->labels->name : $object_type;

                                        // Check if this post type is available in current version
                                        $is_available = $this->isFeatureAvailable('post_type_' . $object_type);
                                        $disabled = $is_available ? '' : ' disabled';
                                    ?>
                                        <option value="<?php echo esc_attr($object_type); ?>"<?php selected($object_type, $current_post_type); ?><?php echo esc_attr($disabled); ?>>
                                            <?php echo esc_html($item_label); ?><?php if (!$is_available) echo ' [PRO]'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!$this->isProVersion()) : ?>
                                <p class="description">
                                    <?php 
                                    printf(
                                        esc_html__('Pages and custom post types are available in %sPRO%s', 'press-permit-core'),
                                        '<a href="https://publishpress.com/links/permissions-banner" target="_blank" rel="noopener noreferrer">',
                                        '</a>'
                                    ); 
                                    ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderPostTypeSettings($object_type, $teaser_setting) {
        $type_obj = get_post_type_object($object_type);
        $item_label = $type_obj ? $type_obj->labels->name : $object_type;
        if (is_bool($teaser_setting) || is_numeric($teaser_setting)) {
            $teaser_setting = intval($teaser_setting);
        }
        ?>
        <?php
        // Get current post type from POST or use first one as default
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- POST data used only for display state, not saved
        $current_post_type = isset($_POST['selected_post_type']) ? sanitize_key($_POST['selected_post_type']) : array_key_first($this->use_teaser);
        $is_current = ($object_type === $current_post_type);
        $display_style = $is_current ? '' : 'display:none;';
        ?>
        <div class="pp-teaser-settings-container<?php echo $is_current ? ' active' : ''; ?>" data-post-type="<?php echo esc_attr($object_type); ?>" style="<?php echo esc_attr($display_style); ?>">
            
            <!-- Combined Settings Table -->
            <?php $this->renderCombinedSettingsTable($object_type, $item_label, $teaser_setting); ?>

            <!-- Read More Notice (shown only when read_more is selected) -->
            <div class="pp-conditional-settings pp-read-more-notice-card">
            <?php $this->renderReadMoreNoticeCard($object_type); ?>
            </div>

            <!-- Excerpt Notice (shown only when excerpt is selected) -->
            <div class="pp-conditional-settings pp-excerpt-notice-card">
            <?php $this->renderExcerptNoticeCard($object_type); ?>
            </div>

            <!-- X Chars Notice (shown only when x_chars is selected) -->
            <div class="pp-conditional-settings pp-x-chars-notice-card">
            <?php $this->renderXCharsNoticeCard($object_type); ?>
            </div>

            <!-- Teaser Message (always visible when teaser type = 1) -->
            <div class="pp-conditional-settings pp-teaser-text-card">
            <?php $this->renderTeaserMessage($object_type); ?>
            </div>

            <!-- Teaser Notice Style Settings (per post type) -->
            <div class="pp-conditional-settings pp-teaser-notice-style-settings">
            <?php $this->renderTeaserNoticeStyleSettings($object_type); ?>
            </div>

            <!-- Teaser Text Configuration -->
            <div class="pp-conditional-settings pp-teaser-text-card">
            <?php $this->renderTeaserTextCard($object_type); ?>
            </div>

            <!-- Redirect Settings (shown only when redirect is selected) -->
            <div class="pp-conditional-settings pp-teaser-redirect-settings">
            <?php $this->renderRedirectSection($object_type); ?>
            </div>

        </div>
        <?php
    }

    private function renderCombinedSettingsTable($object_type, $item_label, $teaser_setting) {
        $name = "tease_post_types[$object_type]";
        
        // Get available teaser types (filtered by trait - FREE has limited, PRO has all)
        $base_captions = [
            0 => esc_html__('WordPress default. Show "Page not found" screen', 'press-permit-core'),
            1 => esc_html__("Teaser text", 'press-permit-core'),
            'read_more' => esc_html__("Use content before Read More link as teaser text", 'press-permit-core'),
            'excerpt' => esc_html__("Use Excerpt as teaser text", 'press-permit-core'),
            'more' => esc_html__("Use Excerpt or pre-More as teaser text", 'press-permit-core'),
            'x_chars' => esc_html__("Use Excerpt, pre-More or First X Characters as teaser text", 'press-permit-core'),
            'redirect' => esc_html__("Redirect to another post", 'press-permit-core')
        ];

        $captions = apply_filters(
            'presspermit_teaser_enable_options',
            $base_captions,
            $object_type,
            $teaser_setting
        );

        $descriptions = [
            0 => esc_html__("No teaser will be applied", 'press-permit-core'),
            1 => esc_html__("Use configured teaser text to replace or supplement content", 'press-permit-core'),
            'read_more' => esc_html__("Show a \"Read More\" link that requires login", 'press-permit-core'),
            'excerpt' => esc_html__("Use the post excerpt as teaser content", 'press-permit-core'),
            'more' => esc_html__("Use excerpt or content before More tag", 'press-permit-core'),
            'x_chars' => esc_html__("Show the first X characters of the post content", 'press-permit-core'),
            'redirect' => esc_html__("Redirect users to another page when they try to access restricted content", 'press-permit-core')
        ];

        if ($this->blockEditorActive) {
            unset($captions['more']);
            unset($descriptions['more']);
            $captions['x_chars'] = esc_html__("Use first X characters of content as teaser text", 'press-permit-core');
            $descriptions['x_chars'] = esc_html__("Use excerpt or first X characters of content", 'press-permit-core');

            if ('more' === $teaser_setting) {
                $teaser_setting = 'x_chars';
            }
        }

        // User Application values
        $name_logged = "tease_logged_only[$object_type]";
        $logged_val = !empty($this->logged_only[$object_type]) ? $this->logged_only[$object_type] : '0';

        // Coverage values
        $direct_only_val = isset($this->direct_only[$object_type]) ? $this->direct_only[$object_type] : 0;
        $hide_links_val = !empty($this->hide_links[$object_type]) ? $this->hide_links[$object_type] : 0;
        $hide_private_val = isset($this->hide_private[$object_type]) ? $this->hide_private[$object_type] : '0';
        ?>
        <table class="widefat fixed striped teaser-table">
            <colgroup>
                <col style="width: 25%;">
                <col style="width: 75%">
            </colgroup>
            <thead>
                <tr>
                    <th colspan="2">
                        <strong><?php printf(esc_html__('Teaser Settings for %s', 'press-permit-core'), esc_html($item_label)); ?></strong>
                    </th>
                </tr>
            </thead>
            <tbody>
                <!-- Teaser Type Section -->
                <?php
                // Register the new options for saving
                $this->ui->all_otype_options[] = 'x_chars_num_chars';
                $this->ui->all_otype_options[] = 'excerpt_num_chars';
                
                // X Chars setting
                $id_x_chars = 'x_chars_num_chars-' . $object_type;
                $name_x_chars = "x_chars_num_chars[$object_type]";
                
                // Excerpt setting
                $id_excerpt = 'excerpt_num_chars-' . $object_type;
                $name_excerpt = "excerpt_num_chars[$object_type]";
                
                $default_num_chars = (defined('PP_TEASER_NUM_CHARS')) ? PP_TEASER_NUM_CHARS : 50;
                
                // Get values from new fields or fallback to legacy field for backward compatibility
                $x_chars_value = $this->pp->getTypeOption('x_chars_num_chars', $object_type);
                $excerpt_value = $this->pp->getTypeOption('excerpt_num_chars', $object_type);
                
                // Backward compatibility: if new fields are empty (but not '0'), use legacy field
                if (($x_chars_value === false || $x_chars_value === '') && !empty($this->arr_num_chars[$object_type])) {
                    $x_chars_value = $this->arr_num_chars[$object_type];
                }
                if (($excerpt_value === false || $excerpt_value === '') && !empty($this->arr_num_chars[$object_type])) {
                    $excerpt_value = $this->arr_num_chars[$object_type];
                }
                
                // Fallback to default if still empty
                $x_chars_value = ($x_chars_value !== false && $x_chars_value !== '') ? $x_chars_value : $default_num_chars;
                $excerpt_value = ($excerpt_value !== false && $excerpt_value !== '') ? $excerpt_value : $default_num_chars;
                $num_style = ('x_chars' !== $teaser_setting) ? 'display:none;' : '';
                $excerpt_num_style = ('excerpt' !== $teaser_setting) ? 'display:none;' : '';
                ?>
                <tr>
                    <th style="width: 30%;"><?php esc_html_e('Teaser Type:', 'press-permit-core'); ?></th>
                    <td>
                        <select name="<?php echo esc_attr($name); ?>" class="regular-text pp-teaser-type-select">
                            <?php foreach ($captions as $teaser_option_val => $teaser_caption) : 
                                $selected = ($teaser_setting === $teaser_option_val) ? ' selected' : '';

                                // Check if this teaser type is available
                                $teaser_type_key = is_numeric($teaser_option_val) 
                                    ? ($teaser_option_val == 0 ? 'teaser_type_none' : 'teaser_type_configured')
                                    : 'teaser_type_' . $teaser_option_val;
                                $is_available = $this->isFeatureAvailable($teaser_type_key);
                                $disabled = $is_available ? '' : ' disabled';
                            ?>
                                <option value="<?php echo esc_attr($teaser_option_val); ?>"<?php echo esc_attr($selected . $disabled); ?>>
                                    <?php echo esc_html($teaser_caption); ?><?php if (!$is_available) echo ' [PRO]'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$this->isProVersion()) : ?>
                        <p class="description" style="margin-top: 8px;">
                            <?php 
                            printf(
                                esc_html__('Read More links, excerpts, and redirects are available in %sPRO%s', 'press-permit-core'),
                                '<a href="https://publishpress.com/links/permissions-banner" target="_blank" rel="noopener noreferrer">',
                                '</a>'
                            ); 
                            ?>
                        </p>
                        <?php endif; ?>
                        
                        <span class="pp-num-chars-setting" style="<?php echo esc_attr($num_style); ?>; margin-left: 10px;">
                            <span><?php esc_html_e('Show only the first', 'press-permit-core'); ?></span>
                            <input type="number" id="<?php echo esc_attr($id_x_chars); ?>" name="<?php echo esc_attr($name_x_chars); ?>" value="<?php echo esc_attr($x_chars_value); ?>" min="10" max="1000" class="small-text" placeholder="<?php esc_attr_e('Chars', 'press-permit-core'); ?>">
                            <span><?php esc_html_e('characters', 'press-permit-core'); ?></span>
                        </span>
                        
                        <span class="pp-excerpt-chars-setting" style="<?php echo esc_attr($excerpt_num_style); ?>; margin-left: 10px;">
                            <span><?php esc_html_e('Max', 'press-permit-core'); ?></span>
                            <input type="number" id="<?php echo esc_attr($id_excerpt); ?>" name="<?php echo esc_attr($name_excerpt); ?>" value="<?php echo esc_attr($excerpt_value); ?>" min="10" max="1000" class="small-text" placeholder="<?php esc_attr_e('Chars', 'press-permit-core'); ?>">
                            <span><?php esc_html_e('characters (leave empty for full excerpt)', 'press-permit-core'); ?></span>
                        </span>
                    </td>
                </tr>
                </tbody>

                <!-- Application Fields - Hidden when No Teaser is selected -->
                <tbody class="pp-teaser-application-fields">
                <!-- Teaser Application Section -->
                <tr>
                    <th style="width: 30%;"><?php esc_html_e('Teaser Application:', 'press-permit-core'); ?></th>
                    <td>
                        <label style="margin-right: 20px;">
                            <input type="radio" name="tease_direct_access_only[<?php echo esc_attr($object_type); ?>]" value="0"<?php checked($direct_only_val, 0); ?>>
                            <?php esc_html_e("List and Single view", 'press-permit-core'); ?>
                        </label>
                        <label>
                            <input type="radio" name="tease_direct_access_only[<?php echo esc_attr($object_type); ?>]" value="1"<?php checked($direct_only_val, 1); ?>>
                            <?php esc_html_e("Single view only", 'press-permit-core'); ?>
                        </label>
                    </td>
                </tr>

                <!-- User Application Section -->
                <tr>
                    <th style="width: 30%;"><?php esc_html_e('User Application:', 'press-permit-core'); ?></th>
                    <td>
                        <?php $user_apps_available = $this->isFeatureAvailable('user_application'); ?>
                        <label style="margin-right: 20px;">
                            <input type="radio" name="<?php echo esc_attr($name_logged); ?>" value="0"<?php checked($logged_val == '0' || empty($logged_val), true); ?> <?php echo !$user_apps_available ? 'disabled' : ''; ?>>
                            <?php esc_html_e('Both', 'press-permit-core'); ?>
                        </label>
                        <label style="margin-right: 20px;">
                            <input type="radio" name="<?php echo esc_attr($name_logged); ?>" value="anon"<?php checked($logged_val, 'anon'); ?> <?php echo !$user_apps_available ? 'disabled' : ''; ?>>
                            <?php esc_html_e('Not Logged In Users', 'press-permit-core'); ?>
                        </label>
                        <label>
                            <input type="radio" name="<?php echo esc_attr($name_logged); ?>" value="1"<?php checked($logged_val, '1'); ?> <?php echo !$user_apps_available ? 'disabled' : ''; ?>>
                            <?php esc_html_e('Logged In Users', 'press-permit-core'); ?>
                        </label>
                    </td>
                </tr>

                <!-- Navigation Menus Section -->
                <tr>
                    <th><?php esc_html_e('Navigation Menus:', 'press-permit-core'); ?></th>
                    <td>
                        <label style="margin-right: 20px;">
                            <input type="radio" name="teaser_hide_menu_links_type[<?php echo esc_attr($object_type); ?>]" value="0"<?php checked($hide_links_val, 0); ?>>
                            <?php esc_html_e("Display links if user doesn't have access", 'press-permit-core'); ?>
                        </label>
                        <label>
                            <input type="radio" name="teaser_hide_menu_links_type[<?php echo esc_attr($object_type); ?>]" value="1"<?php checked($hide_links_val, 1); ?>>
                            <?php esc_html_e("Hide links if user doesn't have access", 'press-permit-core'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Private Posts:', 'press-permit-core'); ?></th>
                    <td>
                        <label style="margin-right: 20px;">
                            <input type="radio" name="tease_public_posts_only[<?php echo esc_attr($object_type); ?>]" value="0"<?php checked($hide_private_val, '0'); ?>>
                            <?php esc_html_e("Apply Teaser to Private Posts", 'press-permit-core'); ?>
                        </label>
                        <label style="margin-right: 20px;">
                            <input type="radio" name="tease_public_posts_only[<?php echo esc_attr($object_type); ?>]" value="1"<?php checked($hide_private_val, '1'); ?>>
                            <?php esc_html_e("Hide Private Posts if user doesn't have access", 'press-permit-core'); ?>
                        </label>
                        <?php if ((defined('PUBLISHPRESS_STATUSES_VERSION') || class_exists('PublishPress\Statuses\Factory'))) : ?>
                        <label>
                            <input type="radio" name="tease_public_posts_only[<?php echo esc_attr($object_type); ?>]" value="custom"<?php checked($hide_private_val, 'custom'); ?>>
                            <?php esc_html_e("Hide for Custom Visibility", 'press-permit-core'); ?>
                        </label>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Featured Image:', 'press-permit-core'); ?></th>
                    <td>
                        <?php
                        $hide_thumbnail_val = !empty($this->hide_thumbnail[$object_type]) ? $this->hide_thumbnail[$object_type] : 0;
                        ?>
                        <label style="margin-right: 20px;">
                            <input type="radio" name="teaser_hide_thumbnail[<?php echo esc_attr($object_type); ?>]" value="0"<?php checked($hide_thumbnail_val, 0); ?>>
                            <?php esc_html_e('Show featured image', 'press-permit-core'); ?>
                        </label>
                        <label>
                            <input type="radio" name="teaser_hide_thumbnail[<?php echo esc_attr($object_type); ?>]" value="1"<?php checked($hide_thumbnail_val, 1); ?>>
                            <?php esc_html_e('Hide featured image when teaser is applied', 'press-permit-core'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Teaser Message Style:', 'press-permit-core'); ?></th>
                    <td>
                        <?php
                        $teaser_notice_mode = $this->pp->getTypeOption('teaser_notice_style_mode', $object_type) ?: 'default';
                        $this->ui->all_options[] = 'teaser_notice_style_mode';
                        ?>
                        <select name="teaser_notice_style_mode[<?php echo esc_attr($object_type); ?>]" class="regular-text pp-teaser-notice-style-select">
                            <option value="default" <?php selected($teaser_notice_mode, 'default'); ?>>
                                <?php esc_html_e('Use Default Teaser Message Style', 'press-permit-core'); ?>
                            </option>
                            <option value="custom" <?php selected($teaser_notice_mode, 'custom'); ?>>
                                <?php esc_html_e('Use Custom Teaser Message Style', 'press-permit-core'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Choose whether to use the default message style or customize the appearance of teaser messages.', 'press-permit-core'); ?>
                        </p>
                    </td>
                </tr>
                </tbody>
            </tbody>
        </table>
        <?php
    }

    private function renderTeaserMessage($object_type) {
        // Get Teaser Text mode content (HTML content from editors) - remove slashes added by WordPress
        $teaser_text_anon = wp_unslash($this->pp->getTypeOption('tease_replace_content_anon', $object_type) ?: '');
        $teaser_text_logged = wp_unslash($this->pp->getTypeOption('tease_replace_content', $object_type) ?: '');
        ?>
        <div class="teaser-message-section" style="margin-top: 20px;">
            <table class="widefat">
                <thead>
                    <tr>
                        <th colspan="2">
                            <strong><?php esc_html_e('Teaser Message', 'press-permit-core'); ?></strong>
                            <?php $this->generateTooltip(esc_html__('Replace the post content entirely with custom content for users who don\'t have access.', 'press-permit-core')) ?>
                        </th>
                    </tr>
                </thead>
            </table>
            
            <div class="pp-teaser-text-container">
                <!-- Tabs for Not Logged In / Logged In -->
                <div class="pp-teaser-text-tabs">
                    <button type="button" class="pp-teaser-text-tab active" data-tab="anon-content">
                        <?php esc_html_e('Not Logged In Users', 'press-permit-core'); ?>
                    </button>
                    <button type="button" class="pp-teaser-text-tab" data-tab="logged-content">
                        <?php esc_html_e('Logged In Users', 'press-permit-core'); ?>
                    </button>
                </div>

                <!-- Tab Content: Not Logged In -->
                <div class="pp-teaser-text-content active" data-tab-content="anon-content">
                    <div class="pp-field-row pp-required-field" data-field-action="replace" data-field-item="content" data-error-message="<?php echo esc_attr(esc_html__('This field is required.', 'press-permit-core')); ?>">
                        <h4 style="margin-bottom: 10px; font-weight: 600;">
                            <?php esc_html_e('Replace Post Content With:', 'press-permit-core'); ?>
                            <span class="pp-required-indicator" style="color: red;">*</span>
                        </h4>
                        <div>
                            <?php
                            $option_basename_anon = "tease_replace_content_anon";
                            $id_anon = $object_type . '_' . $option_basename_anon;
                            $name_anon = "{$option_basename_anon}[{$object_type}]";
                            
                            $editor_settings_anon = [
                                'textarea_name' => $name_anon,
                                'textarea_rows' => 5,
                                'media_buttons' => false,
                                'teeny' => true,
                                'quicktags' => ['buttons' => 'strong,em,link,ul,ol,li'],
                                'tinymce' => [
                                    'toolbar1' => 'bold,italic,underline,strikethrough,link,unlink,bullist,numlist,blockquote,undo,redo',
                                    'toolbar2' => '',
                                    'toolbar3' => '',
                                ]
                            ];
                            wp_editor($teaser_text_anon, $id_anon, $editor_settings_anon);
                            ?>
                            <p class="pp-add-login-form">
                                <?php
                                printf(
                                    esc_html__( 'Insert a login form by using %s[login_form]%s shortcode.', 'press-permit-core' ),
                                    '<a href="#">',
                                    '</a>'
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tab Content: Logged In -->
                <div class="pp-teaser-text-content" data-tab-content="logged-content" style="display:none;">
                    <div class="pp-field-row pp-required-field" data-field-action="replace" data-field-item="content" data-error-message="<?php echo esc_attr(esc_html__('This field is required.', 'press-permit-core')); ?>">
                        <h4 style="margin-bottom: 10px; font-weight: 600;">
                            <?php esc_html_e('Replace Post Content With:', 'press-permit-core'); ?>
                            <span class="pp-required-indicator" style="color: red;">*</span>
                        </h4>
                        <div>
                            <?php
                            $option_basename_logged = "tease_replace_content";
                            $id_logged = $object_type . '_' . $option_basename_logged;
                            $name_logged = "{$option_basename_logged}[{$object_type}]";
                            
                            $editor_settings_logged = [
                                'textarea_name' => $name_logged,
                                'textarea_rows' => 5,
                                'media_buttons' => false,
                                'teeny' => true,
                                'quicktags' => ['buttons' => 'strong,em,link,ul,ol,li'],
                                'tinymce' => [
                                    'toolbar1' => 'bold,italic,underline,strikethrough,link,unlink,bullist,numlist,blockquote,undo,redo',
                                    'toolbar2' => '',
                                    'toolbar3' => '',
                                ]
                            ];
                            wp_editor($teaser_text_logged, $id_logged, $editor_settings_logged);
                            ?>
                            <p class="pp-add-login-form">
                                <?php
                                printf(
                                    esc_html__( 'Insert a login form by using %s[login_form]%s shortcode.', 'press-permit-core' ),
                                    '<a href="#">',
                                    '</a>'
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function renderTeaserTextCard($object_type) {
        ?>
        <div class="">
            <table class="widefat">
                <thead>
                    <tr>
                        <th colspan="2">
                            <strong><?php esc_html_e('Teaser Text Configuration', 'press-permit-core'); ?></strong>
                            <?php $this->generateTooltip(esc_html__('Configure custom teaser text for this post type.', 'press-permit-core')) ?>
                        </th>
                    </tr>
                </thead>
            </table>
            
            <div class="pp-teaser-text-container">
                <!-- Tabs for Logged In Users / Not Logged In Users -->
                <div class="pp-teaser-text-tabs">
                    <button type="button" class="pp-teaser-text-tab active" data-tab="anon">
                        <?php esc_html_e('Not Logged In Users', 'press-permit-core'); ?>
                    </button>
                    <button type="button" class="pp-teaser-text-tab" data-tab="logged">
                        <?php esc_html_e('Logged In Users', 'press-permit-core'); ?>
                    </button>
                </div>
    
                <!-- Tab Contents -->
                <div class="pp-teaser-text-content active" data-tab-content="anon">
                    <?php $this->renderTeaserTextFields($object_type, '_anon'); ?>
                </div>
    
                <div class="pp-teaser-text-content" data-tab-content="logged" style="display:none;">
                    <?php $this->renderTeaserTextFields($object_type, ''); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function renderTeaserTextFields($object_type, $suffix) {
        $user_label = ($suffix == '_anon') ? esc_html__('Not Logged In Users', 'press-permit-core') : esc_html__('Logged In Users', 'press-permit-core');

        // Prepare teaser text options - EXCLUDE content/replace
        $item_actions = [
            'content' => ['prepend', 'append'],
            'name' => ['prepend', 'append'],
        ];

        // Register all options
        $trans_headings = [
            'name' => esc_html__('post title', 'press-permit-core'),
            'content' => esc_html__('post content', 'press-permit-core'),
        ];

        foreach ($item_actions as $item => $actions) {
            $item_heading = ucfirst($item);

            $actions_display = [
                'prepend' => sprintf(esc_html__('Before %s:', 'press-permit-core'), $trans_headings[$item]),
                'append' => sprintf(esc_html__('After %s:', 'press-permit-core'), $trans_headings[$item])
            ];
            ?>
            <div class="pp-teaser-text-section">
                <?php foreach ($actions as $action) : 
                    $option_basename = "tease_{$action}_{$item}{$suffix}";
                    
                    // Get per-post-type value
                    $opt_val = $this->pp->getTypeOption($option_basename, $object_type);
                    
                    // Remove slashes that WordPress adds automatically
                    if ($opt_val) {
                        $opt_val = wp_unslash($opt_val);
                    } else {
                        $opt_val = '';
                    }
                    
                    $id = $object_type . '_' . $option_basename;
                    $name = "{$option_basename}[{$object_type}]";
                    ?>
                    <div class="pp-field-row" data-field-action="<?php echo esc_attr($action); ?>" data-field-item="<?php echo esc_attr($item); ?>">
                        <div>
                            <label for="<?php echo esc_attr($id); ?>">
                                <strong><?php echo esc_html($actions_display[$action]); ?></strong>
                            </label>
                        </div>
                        <div>
                            <?php if ('content' == $item) : 
                                $editor_settings = [
                                    'textarea_name' => $name,
                                    'textarea_rows' => 5,
                                    'media_buttons' => false,
                                    'teeny' => true,
                                    'quicktags' => ['buttons' => 'strong,em,link,ul,ol,li'],
                                    'tinymce' => [
                                        'toolbar1' => 'bold,italic,underline,strikethrough,link,unlink,bullist,numlist,blockquote,undo,redo',
                                        'toolbar2' => '',
                                        'toolbar3' => '',
                                    ]
                                ];
                                wp_editor($opt_val, $id, $editor_settings);
                                ?>
                                <?php else : /* Use wp_editor for name and excerpt fields as well */ ?>
                                    <?php
                                    // Use a compact editor for these fields
                                    $editor_settings = [
                                        'textarea_name' => $name,
                                        'textarea_rows' => 3,
                                        'media_buttons' => false,
                                        'teeny' => true,
                                        'quicktags' => ['buttons' => 'strong,em,link'],
                                        'tinymce' => [
                                            'toolbar1' => 'bold,italic,link,unlink,undo,redo',
                                            'toolbar2' => '',
                                            'toolbar3' => ''
                                        ]
                                    ];
                                    wp_editor($opt_val, $id, $editor_settings);
                                    ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
        }
    }

    private function renderRedirectSection($object_type = '') {
        // Redirect is PRO-only feature
        if (!$this->isProVersion()) {
            ?>
            <div class="teaser-redirect-section pp-pro-feature-notice" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #0073aa;">
                <h4><?php _e('Redirect Settings', 'press-permit-core');?> <?php echo wp_kses_post($this->renderProBadge(__('Redirect functionality is available in PRO', 'press-permit-core'))); ?></h4>
                <p><?php _e('Automatically redirect users to a login page or custom page when they try to access restricted content.', 'press-permit-core'); ?></p>
                <p>
                    <a href="https://publishpress.com/links/permissions-banner" class="button button-primary" target="_blank">
                        <?php _e('Upgrade to PRO', 'press-permit-core'); ?>
                    </a>
                    <a href="https://publishpress.com/permissions/pricing/" class="button button-secondary" target="_blank">
                        <?php _e('Learn More', 'press-permit-core'); ?>
                    </a>
                </p>
            </div>
            <?php
            return;
        }
        ?>
        <div class="teaser-redirect-section" style="margin-top: 20px;">
            <table class="widefat fixed striped teaser-table pp-teaser-redirect">
                <colgroup>
                    <col style="width: 20%;">
                    <col style="width: 20%;">
                    <col style="width: 15%;">
                    <col style="width: 45%;">
                </colgroup>
                <thead>
                    <tr>
                        <th colspan="4">
                            <strong><?php _e('Redirect Settings', 'press-permit-core');?></strong>
                            <?php if ($this->ui->display_hints) : 
                                $tooltip_text = \PublishPress\Permissions\UI\SettingsAdmin::getStr('teaser_redirect_page');
                                if ($tooltip_text) {
                                    $this->generateTooltip($tooltip_text);
                                }
                            endif; ?>
                        </th>
                    </tr>
                    <tr>
                        <th></th>
                        <th><?php _e('Redirection', 'press-permit-core') ?></th>
                        <th><?php _e('Target Post Type', 'press-permit-core') ?></th>
                        <th><?php _e('Select Post', 'press-permit-core') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Not Logged In redirect
                $id_basename = "teaser_redirect_anon";
                $id = "{$id_basename}_{$object_type}";
                $id_slug_basename = "teaser_redirect_anon_page";
                $id_slug = "{$id_slug_basename}_{$object_type}";
                $name = "{$id_basename}[{$object_type}]";
                $name_slug = "{$id_slug_basename}[{$object_type}]";

                // Get per-post-type redirect mode value
                $redirect_mode = $this->pp->getTypeOption($id_basename, $object_type);
                
                // Get per-post-type redirect page value
                $redirect_page_id = $this->pp->getTypeOption($id_slug_basename, $object_type);
                
                // Get redirect post type (default to 'page' for backward compatibility)
                $redirect_post_type_basename = "teaser_redirect_anon_post_type";
                $redirect_post_type_name = "{$redirect_post_type_basename}[{$object_type}]";
                $redirect_post_type = $this->pp->getTypeOption($redirect_post_type_basename, $object_type) ?: 'page';
                
                // Register option for saving
                $this->ui->all_otype_options[] = $redirect_post_type_basename;
                
                // Get available public post types
                $public_post_types = get_post_types(['public' => true], 'objects');
                // Exclude attachment type
                unset($public_post_types['attachment']);

                ?>
                <tr>
                    <th>
                        <label for='<?php echo esc_attr($id); ?>'>
                        <?php esc_html_e('Not Logged In:', 'press-permit-core');
                        ?>
                        </label>
                    </th>
                    <td>
                        <?php
                        echo "<select name='" . esc_attr($name) . "' id='" . esc_attr($id) . "' class='teaser-redirect-mode' autocomplete='off'>";
                        $captions = [
                            0 => esc_html__("No redirect", 'press-permit-core'),
                            '(login)' => esc_html__("Redirect to WordPress login", 'press-permit-core'),
                            '(select)' => esc_html__("Redirect to a custom post", 'press-permit-core'),
                        ];

                        foreach ($captions as $teaser_option_val => $teaser_caption) {
                            $selected = ($redirect_mode == $teaser_option_val) ? ' selected ' : '';
                            echo "\n\t<option value='" . esc_attr($teaser_option_val) . "'" . esc_attr($selected) . ">" . esc_html($teaser_caption) . "</option>";
                        }

                        echo '</select>';
                        ?>
                    </td>
                    <td>
                        <?php
                        $style = ('(select)' === $redirect_mode) ? '' : "display:none;";
                        ?>
                        <div class="pp-select-dynamic-wrapper" style="<?php echo esc_attr($style);?>">
                            <!-- Post Type Selector -->
                            <select class="teaser-redirect-post-type" 
                                    name="<?php echo esc_attr($redirect_post_type_name); ?>"
                                    id="<?php echo esc_attr($redirect_post_type_basename . '_' . $object_type); ?>"
                                    data-target-select="<?php echo esc_attr($id_slug); ?>"
                                    style="width: 100%;">
                                <?php foreach ($public_post_types as $post_type_key => $post_type_obj) : ?>
                                    <option value="<?php echo esc_attr($post_type_key); ?>" <?php selected($redirect_post_type, $post_type_key); ?>>
                                        <?php echo esc_html($post_type_obj->labels->singular_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </td>
                    <td>
                        <?php
                        $style = ('(select)' === $redirect_mode) ? '' : "display:none;";
                        ?>
                        <div class="pp-select-dynamic-wrapper" style="<?php echo esc_attr($style);?>">
                            <!-- Item Selector -->
                            <select class="permissions_select_posts"
                                    name="<?php echo esc_attr($name_slug); ?>"
                                    id="<?php echo esc_attr($id_slug); ?>"
                                    data-post-type="<?php echo esc_attr($redirect_post_type); ?>"
                            >
                                <?php if( isset( $redirect_page_id ) && ! empty( $redirect_page_id ) && is_numeric($redirect_page_id) ) : ?>
                                    <option value="<?php echo (int) $redirect_page_id ?>" selected="selected"><?php echo esc_html(get_the_title( (int) $redirect_page_id ))?></option>
                                <?php endif; ?>
                            </select>

                            <?php
                            $custom_login_basename = "teaser_redirect_custom_login_page_anon";
                            $custom_login_id = "{$custom_login_basename}_{$object_type}";
                            $custom_login_name = "{$custom_login_basename}[{$object_type}]";
                            $custom_login_val = $this->pp->getTypeOption($custom_login_basename, $object_type);
                            ?>
                            <input type="hidden" name="<?php echo esc_attr($custom_login_name);?>" value="0" />
                            &nbsp;<label style="white-space:nowrap"><input type="checkbox" name="<?php echo esc_attr($custom_login_name);?>" value="1" <?php if ($custom_login_val) echo 'checked';?> /><?php _e('This is a custom login page', 'press-permit-core');?><?php $this->generateTooltip(esc_html__('After the user logs in, they will be redirected back to the original post they were viewing.', 'press-permit-core')); ?></label>
                        </div>
                    </td>
                </tr>

                <?php
                // Logged in redirect
                $id_basename = "teaser_redirect";
                $id = "{$id_basename}_{$object_type}";
                $id_slug_basename = "teaser_redirect_page";
                $id_slug = "{$id_slug_basename}_{$object_type}";
                $name = "{$id_basename}[{$object_type}]";
                $name_slug = "{$id_slug_basename}[{$object_type}]";

                // Get per-post-type redirect mode value
                $redirect_mode = $this->pp->getTypeOption($id_basename, $object_type);
                
                // Get per-post-type redirect page value
                $redirect_page_id = $this->pp->getTypeOption($id_slug_basename, $object_type);
                
                // Get redirect post type (default to 'page' for backward compatibility)
                $redirect_post_type_basename = "teaser_redirect_post_type";
                $redirect_post_type_name = "{$redirect_post_type_basename}[{$object_type}]";
                $redirect_post_type = $this->pp->getTypeOption($redirect_post_type_basename, $object_type) ?: 'page';
                
                // Register option for saving
                $this->ui->all_otype_options[] = $redirect_post_type_basename;
                
                ?>
                <tr>
                    <th>
                        <label for='<?php echo esc_attr($id); ?>'>
                        <?php esc_html_e('Logged in Users:', 'press-permit-core');
                        ?>
                        </label>
                    </th>
                    <td>
                        <?php
                        echo "<select name='" . esc_attr($name) . "' id='" . esc_attr($id) . "' class='teaser-redirect-mode' autocomplete='off'>";
                        $captions = [
                            0 => esc_html__("No redirect", 'press-permit-core'),
                            '(login)' => esc_html__("Redirect to WordPress login", 'press-permit-core'),
                            '(select)' => esc_html__("Redirect to a custom post", 'press-permit-core'),
                        ];

                        foreach ($captions as $teaser_option_val => $teaser_caption) {
                            $selected = ($redirect_mode == $teaser_option_val) ? ' selected ' : '';
                            echo "\n\t<option value='" . esc_attr($teaser_option_val) . "'" . esc_attr($selected) . ">" . esc_html($teaser_caption) . "</option>";
                        }

                        echo '</select>';
                        ?>
                    </td>
                    <td>
                        <?php
                        $style = ('(select)' === $redirect_mode) ? '' : "display:none;";
                        ?>
                        <div class="pp-select-dynamic-wrapper" style="<?php echo esc_attr($style);?>">
                            <!-- Post Type Selector -->
                            <select class="teaser-redirect-post-type" 
                                    name="<?php echo esc_attr($redirect_post_type_name); ?>"
                                    id="<?php echo esc_attr($redirect_post_type_basename . '_' . $object_type); ?>"
                                    data-target-select="<?php echo esc_attr($id_slug); ?>"
                                    style="width: 100%;">
                                <?php foreach ($public_post_types as $post_type_key => $post_type_obj) : ?>
                                    <option value="<?php echo esc_attr($post_type_key); ?>" <?php selected($redirect_post_type, $post_type_key); ?>>
                                        <?php echo esc_html($post_type_obj->labels->singular_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </td>
                    <td>
                        <?php
                        $style = ('(select)' === $redirect_mode) ? '' : "display:none;";
                        ?>
                        <div class="pp-select-dynamic-wrapper" style="<?php echo esc_attr($style);?>">
                            <!-- Item Selector -->
                            <select class="permissions_select_posts"
                                    name="<?php echo esc_attr($name_slug); ?>"
                                    id="<?php echo esc_attr($id_slug); ?>"
                                    data-post-type="<?php echo esc_attr($redirect_post_type); ?>"
                            >
                                <?php if( isset( $redirect_page_id ) && ! empty( $redirect_page_id ) && is_numeric($redirect_page_id) ) : ?>
                                    <option value="<?php echo (int) $redirect_page_id ?>" selected="selected"><?php echo esc_html(get_the_title( (int) $redirect_page_id ))?></option>
                                <?php endif; ?>
                            </select>
                            
                            <?php
                            $custom_login_basename = "teaser_redirect_custom_login_page";
                            $custom_login_id = "{$custom_login_basename}_{$object_type}";
                            $custom_login_name = "{$custom_login_basename}[{$object_type}]";
                            $custom_login_val = $this->pp->getTypeOption($custom_login_basename, $object_type);
                            ?>
                            <input type="hidden" name="<?php echo esc_attr($custom_login_name);?>" value="0" />
                            &nbsp;<label style="white-space:nowrap"><input type="checkbox" name="<?php echo esc_attr($custom_login_name);?>" value="1" <?php if ($custom_login_val) echo 'checked';?> /><?php _e('This is a custom login page', 'press-permit-core');?></label>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderReadMoreNoticeCard($object_type) {
        $id = 'read_more_login_notice';
        $this->ui->all_otype_options[] = $id;
        $default_message = esc_html__('To read the full content, please log in to this site.', 'press-permit-core');
        $_setting = $this->pp->getTypeOption($id, $object_type);
        if (empty($_setting)) {
            $_setting = $default_message;
        }
        
        // Remove slashes that WordPress adds automatically
        $_setting = wp_unslash($_setting);
        
        $editor_id = $object_type . '_' . $id;
        $editor_name = $id . '[' . $object_type . ']';
        ?>
        <div class="pp-read-more-notice-section" style="margin-top: 20px;">
            <table class="widefat">
                <thead>
                    <tr>
                        <th>
                            <strong><?php esc_html_e('Message for Blocked Users', 'press-permit-core'); ?></strong>
                            <?php $this->generateTooltip(esc_html__('Customize the message shown to users who are blocked from viewing the full content when using the "Read More" teaser type.', 'press-permit-core')) ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <?php
                            $editor_settings = [
                                'textarea_name' => $editor_name,
                                'textarea_rows' => 5,
                                'media_buttons' => false,
                                'teeny'         => true,
                                'quicktags'     => ['buttons' => 'strong,em,link,ul,ol,li'],
                                'tinymce' => [
                                    'toolbar1' => 'bold,italic,underline,link,unlink,bullist,numlist,undo,redo',
                                    'toolbar2' => '',
                                    'toolbar3' => '',
                                ]
                            ];
                            wp_editor($_setting, $editor_id, $editor_settings);
                            ?>
                            <p class="description">
                                <?php esc_html_e('This message will be displayed in a styled notice box above the teaser content for users who are not logged in.', 'press-permit-core'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderExcerptNoticeCard($object_type) {
        $id = 'excerpt_login_notice';
        $this->ui->all_otype_options[] = $id;
        $default_message = esc_html__('To read the full content, please log in to this site.', 'press-permit-core');
        $_setting = $this->pp->getTypeOption($id, $object_type);
        if (empty($_setting)) {
            $_setting = $default_message;
        }
        
        // Remove slashes that WordPress adds automatically
        $_setting = wp_unslash($_setting);
        
        $editor_id = $object_type . '_' . $id;
        $editor_name = $id . '[' . $object_type . ']';
        ?>
        <div class="pp-excerpt-notice-section" style="margin-top: 20px;">
            <table class="widefat">
                <thead>
                    <tr>
                        <th>
                            <strong><?php esc_html_e('Message for Blocked Users', 'press-permit-core'); ?></strong>
                            <?php $this->generateTooltip(esc_html__('Customize the login notice message shown to non-logged-in users when using the "Excerpt" teaser type.', 'press-permit-core')) ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <?php
                            $editor_settings = [
                                'textarea_name' => $editor_name,
                                'textarea_rows' => 6,
                                'media_buttons' => false,
                                'teeny' => true,
                                'quicktags' => ['buttons' => 'strong,em,link,ul,ol,li'],
                                'tinymce' => [
                                    'toolbar1' => 'bold,italic,underline,link,unlink,bullist,numlist,undo,redo',
                                    'toolbar2' => '',
                                    'toolbar3' => '',
                                ]
                            ];
                            wp_editor($_setting, $editor_id, $editor_settings);
                            ?>
                            <p class="description">
                                <?php esc_html_e('This message will be displayed in a styled notice box below the excerpt teaser content for users who are not logged in.', 'press-permit-core'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderXCharsNoticeCard($object_type) {
        $id = 'x_chars_login_notice';
        $this->ui->all_otype_options[] = $id;
        $default_message = esc_html__('To read the full content, please log in to this site.', 'press-permit-core');
        $_setting = $this->pp->getTypeOption($id, $object_type);
        if (empty($_setting)) {
            $_setting = $default_message;
        }
        
        // Remove slashes that WordPress adds automatically
        $_setting = wp_unslash($_setting);
        
        $editor_id = $object_type . '_' . $id;
        $editor_name = $id . '[' . $object_type . ']';
        ?>
        <div class="pp-x-chars-notice-section" style="margin-top: 20px;">
            <table class="widefat">
                <thead>
                    <tr>
                        <th>
                            <strong><?php esc_html_e('Message for Blocked Users', 'press-permit-core'); ?></strong>
                            <?php $this->generateTooltip(esc_html__('Customize the login notice message shown to non-logged-in users when using the "First X Characters" teaser type.', 'press-permit-core')) ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <?php
                            $editor_settings = [
                                'textarea_name' => $editor_name,
                                'textarea_rows' => 6,
                                'media_buttons' => false,
                                'teeny' => true,
                                'quicktags' => ['buttons' => 'strong,em,link,ul,ol,li'],
                                'tinymce' => [
                                    'toolbar1' => 'bold,italic,underline,link,unlink,bullist,numlist,undo,redo',
                                    'toolbar2' => '',
                                    'toolbar3' => '',
                                ]
                            ];
                            wp_editor($_setting, $editor_id, $editor_settings);
                            ?>
                            <p class="description">
                                <?php esc_html_e('This message will be displayed in a styled notice box below the truncated content teaser for users who are not logged in.', 'press-permit-core'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderTeaserNoticeStyleSettings($object_type) {
        // Get current style settings with defaults (per post type)
        $bg_color = $this->pp->getTypeOption('teaser_notice_bg_color', $object_type) ?: '#f0f6fc';
        $text_color = $this->pp->getTypeOption('teaser_notice_text_color', $object_type) ?: '#1d2327';
        $border_color = $this->pp->getTypeOption('teaser_notice_border_color', $object_type) ?: '#0073aa';
        $border_width = $this->pp->getTypeOption('teaser_notice_border_width', $object_type) ?: '4';
        $border_position = $this->pp->getTypeOption('teaser_notice_border_position', $object_type) ?: 'left';
        $padding = $this->pp->getTypeOption('teaser_notice_padding', $object_type) ?: '15';
        $border_radius = $this->pp->getTypeOption('teaser_notice_border_radius', $object_type) ?: '0';
        $font_size = $this->pp->getTypeOption('teaser_notice_font_size', $object_type) ?: '14';
        // Validate border position to prevent XSS - whitelist allowed values
        $allowed_border_positions = ['left', 'right', 'top', 'bottom', 'all'];
        if (!in_array($border_position, $allowed_border_positions, true)) {
            $border_position = 'left'; // Default to safe value if invalid
        }

        // Get message values for preview
        $default_message = esc_html__('To read the full content, please log in to this site.', 'press-permit-core');
        
        // Teaser Text mode content (HTML content from editors) - remove slashes added by WordPress
        $teaser_text_anon = wp_unslash($this->pp->getTypeOption('tease_replace_content_anon', $object_type) ?: '');
        $teaser_text_logged = wp_unslash($this->pp->getTypeOption('tease_replace_content', $object_type) ?: '');
        
        // Notice messages for other modes - remove slashes added by WordPress
        $read_more_msg = wp_unslash($this->pp->getTypeOption('read_more_login_notice', $object_type) ?: $default_message);
        $excerpt_msg = wp_unslash($this->pp->getTypeOption('excerpt_login_notice', $object_type) ?: $default_message);
        $x_chars_msg = wp_unslash($this->pp->getTypeOption('x_chars_login_notice', $object_type) ?: $default_message);
        
        // Get current teaser type to determine initial display
        $teaser_type = $this->pp->getTypeOption('tease_post_types', $object_type);
        if (is_bool($teaser_type) || is_numeric($teaser_type)) {
            $teaser_type = intval($teaser_type);
        }
        
        // Determine initial preview text based on current teaser type
        $preview_text = $default_message;
        if ($teaser_type == '1') {
            // Teaser Text mode - default to anon content
            $preview_text = wp_strip_all_tags($teaser_text_anon) ?: $default_message;
        } elseif ($teaser_type == 'read_more') {
            $preview_text = $read_more_msg;
        } elseif ($teaser_type == 'excerpt') {
            $preview_text = $excerpt_msg;
        } elseif ($teaser_type == 'x_chars' || $teaser_type == 'more') {
            $preview_text = $x_chars_msg;
        }

        // Register options
        $style_options = [
            'teaser_notice_bg_color',
            'teaser_notice_text_color', 
            'teaser_notice_border_color',
            'teaser_notice_border_width',
            'teaser_notice_border_position',
            'teaser_notice_padding',
            'teaser_notice_border_radius',
            'teaser_notice_font_size'
        ];
        
        foreach ($style_options as $option) {
            $this->ui->all_options[] = $option;
        }
        ?>
        <div class="teaser-notice-style-section" style="margin-top: 20px;">
            <table class="widefat">
                <thead>
                    <tr>
                        <th colspan="2">
                            <strong><?php esc_html_e('Teaser Message Style Customization', 'press-permit-core'); ?></strong>
                            <?php $this->generateTooltip(esc_html__('Customize the appearance of teaser message displayed to blocked users.', 'press-permit-core')) ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="2">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <!-- Left Column: Settings -->
                                <div>
                                    <div class="pp-field-row" style="margin-bottom: 15px;">
                                        <label for="teaser_notice_bg_color_<?php echo esc_attr($object_type); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                            <?php esc_html_e('Background Color:', 'press-permit-core'); ?>
                                        </label>
                                        <input type="text" 
                                               name="teaser_notice_bg_color[<?php echo esc_attr($object_type); ?>]" 
                                               id="teaser_notice_bg_color_<?php echo esc_attr($object_type); ?>" 
                                               value="<?php echo esc_attr($bg_color); ?>" 
                                               class="pp-color-picker"
                                               data-default-color="#f0f6fc">
                                    </div>

                                    <div class="pp-field-row" style="margin-bottom: 15px;">
                                        <label for="teaser_notice_text_color_<?php echo esc_attr($object_type); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                            <?php esc_html_e('Text Color:', 'press-permit-core'); ?>
                                        </label>
                                        <input type="text" 
                                               name="teaser_notice_text_color[<?php echo esc_attr($object_type); ?>]" 
                                               id="teaser_notice_text_color_<?php echo esc_attr($object_type); ?>" 
                                               value="<?php echo esc_attr($text_color); ?>" 
                                               class="pp-color-picker"
                                               data-default-color="#1d2327">
                                    </div>

                                    <div class="pp-field-row" style="margin-bottom: 15px;">
                                        <label for="teaser_notice_border_color_<?php echo esc_attr($object_type); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                            <?php esc_html_e('Border Color:', 'press-permit-core'); ?>
                                        </label>
                                        <input type="text" 
                                               name="teaser_notice_border_color[<?php echo esc_attr($object_type); ?>]" 
                                               id="teaser_notice_border_color_<?php echo esc_attr($object_type); ?>" 
                                               value="<?php echo esc_attr($border_color); ?>" 
                                               class="pp-color-picker"
                                               data-default-color="#0073aa">
                                    </div>

                                    <div class="pp-field-row" style="margin-bottom: 15px;">
                                        <label for="teaser_notice_border_width_<?php echo esc_attr($object_type); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                            <?php esc_html_e('Border Width (px):', 'press-permit-core'); ?>
                                        </label>
                                        <input type="number" 
                                               name="teaser_notice_border_width[<?php echo esc_attr($object_type); ?>]" 
                                               id="teaser_notice_border_width_<?php echo esc_attr($object_type); ?>" 
                                               value="<?php echo esc_attr($border_width); ?>" 
                                               min="0" 
                                               max="20" 
                                               class="small-text pp-style-input">
                                    </div>

                                    <div class="pp-field-row" style="margin-bottom: 15px;">
                                        <label for="teaser_notice_border_position_<?php echo esc_attr($object_type); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                            <?php esc_html_e('Border Position:', 'press-permit-core'); ?>
                                        </label>
                                        <select name="teaser_notice_border_position[<?php echo esc_attr($object_type); ?>]" 
                                                id="teaser_notice_border_position_<?php echo esc_attr($object_type); ?>" 
                                                class="regular-text pp-style-input">
                                            <option value="left" <?php selected($border_position, 'left'); ?>><?php esc_html_e('Left', 'press-permit-core'); ?></option>
                                            <option value="right" <?php selected($border_position, 'right'); ?>><?php esc_html_e('Right', 'press-permit-core'); ?></option>
                                            <option value="top" <?php selected($border_position, 'top'); ?>><?php esc_html_e('Top', 'press-permit-core'); ?></option>
                                            <option value="bottom" <?php selected($border_position, 'bottom'); ?>><?php esc_html_e('Bottom', 'press-permit-core'); ?></option>
                                            <option value="all" <?php selected($border_position, 'all'); ?>><?php esc_html_e('All Sides', 'press-permit-core'); ?></option>
                                        </select>
                                    </div>

                                    <div class="pp-field-row" style="margin-bottom: 15px;">
                                        <label for="teaser_notice_padding_<?php echo esc_attr($object_type); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                            <?php esc_html_e('Padding (px):', 'press-permit-core'); ?>
                                        </label>
                                        <input type="number" 
                                               name="teaser_notice_padding[<?php echo esc_attr($object_type); ?>]" 
                                               id="teaser_notice_padding_<?php echo esc_attr($object_type); ?>" 
                                               value="<?php echo esc_attr($padding); ?>" 
                                               min="0" 
                                               max="50" 
                                               class="small-text pp-style-input">
                                    </div>

                                    <div class="pp-field-row" style="margin-bottom: 15px;">
                                        <label for="teaser_notice_border_radius_<?php echo esc_attr($object_type); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                            <?php esc_html_e('Border Radius (px):', 'press-permit-core'); ?>
                                        </label>
                                        <input type="number" 
                                               name="teaser_notice_border_radius[<?php echo esc_attr($object_type); ?>]" 
                                               id="teaser_notice_border_radius_<?php echo esc_attr($object_type); ?>" 
                                               value="<?php echo esc_attr($border_radius); ?>" 
                                               min="0" 
                                               max="50" 
                                               class="small-text pp-style-input">
                                    </div>

                                    <div class="pp-field-row" style="margin-bottom: 15px;">
                                        <label for="teaser_notice_font_size_<?php echo esc_attr($object_type); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                            <?php esc_html_e('Font Size (px):', 'press-permit-core'); ?>
                                        </label>
                                        <input type="number" 
                                               name="teaser_notice_font_size[<?php echo esc_attr($object_type); ?>]" 
                                               id="teaser_notice_font_size_<?php echo esc_attr($object_type); ?>" 
                                               value="<?php echo esc_attr($font_size); ?>" 
                                               min="10" 
                                               max="30" 
                                               class="small-text pp-style-input">
                                    </div>
                                </div>

                                <!-- Right Column: Live Preview -->
                                <div>
                                    <div style="position: sticky; top: 20px;">
                                        <h4 style="margin-top: 0; margin-bottom: 10px; font-weight: 600;">
                                            <?php esc_html_e('Live Preview', 'press-permit-core'); ?>
                                        </h4>
                                        <div id="pp-teaser-notice-preview-<?php echo esc_attr($object_type); ?>" class="pp-teaser-notice-preview" 
                                            data-post-type="<?php echo esc_attr($object_type); ?>"
                                            data-teaser-text-default="<?php echo esc_attr($default_message); ?>"
                                            data-teaser-text-anon="<?php echo esc_attr(wp_strip_all_tags($teaser_text_anon)); ?>"
                                            data-teaser-text-logged="<?php echo esc_attr(wp_strip_all_tags($teaser_text_logged)); ?>"
                                            data-read-more-msg="<?php echo esc_attr($read_more_msg); ?>"
                                            data-excerpt-msg="<?php echo esc_attr($excerpt_msg); ?>"
                                            data-x-chars-msg="<?php echo esc_attr($x_chars_msg); ?>"
                                            data-current-teaser-type="<?php echo esc_attr($teaser_type); ?>"
                                            style="
                                            padding: <?php echo esc_attr($padding); ?>px;
                                            background: <?php echo esc_attr($bg_color); ?>;
                                            color: <?php echo esc_attr($text_color); ?>;
                                            <?php if ($border_position === 'all'): ?>
                                                border: <?php echo esc_attr($border_width); ?>px solid <?php echo esc_attr($border_color); ?>;
                                            <?php else: ?>
                                                border-<?php echo esc_attr($border_position); ?>: <?php echo esc_attr($border_width); ?>px solid <?php echo esc_attr($border_color); ?>;
                                            <?php endif; ?>
                                            margin: 15px 0;
                                            font-size: <?php echo esc_attr($font_size); ?>px;
                                            line-height: 1.6;
                                            border-radius: <?php echo esc_attr($border_radius); ?>px;
                                        ">
                                            <?php echo esc_html($preview_text); ?>
                                        </div>
                                        <p class="description">
                                            <?php esc_html_e('This is how the teaser message will appear on your site. Changes update in real-time.', 'press-permit-core'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function generateTooltip($tooltip, $text = '', $position = 'top', $useIcon = true)
    {
        if (!$tooltip) return;
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

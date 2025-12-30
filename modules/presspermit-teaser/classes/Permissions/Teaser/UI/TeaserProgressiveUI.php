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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is for rendering UI state, not processing form submission
        $current_post_type = !empty($_POST['selected_post_type']) ? sanitize_key($_POST['selected_post_type']) : $default_post_type;
        ?>
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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is for rendering UI state, not processing form submission
        $current_post_type = isset($_POST['selected_post_type']) ? sanitize_key($_POST['selected_post_type']) : array_key_first($this->use_teaser);
        $is_current = ($object_type === $current_post_type);
        $display_style = $is_current ? '' : 'display:none;';
        ?>
        <div class="pp-teaser-settings-container<?php echo $is_current ? ' active' : ''; ?>" data-post-type="<?php echo esc_attr($object_type); ?>" style="<?php echo esc_attr($display_style); ?>">
            
            <!-- Combined Settings Table -->
            <?php $this->renderCombinedSettingsTable($object_type, $item_label, $teaser_setting); ?>

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

        $base_captions = [
            0 => esc_html__('No Teaser. Show "Page not found" message', 'press-permit-core'),
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
                $id_num = 'teaser_num_chars-' . $object_type;
                $name_num = "teaser_num_chars[$object_type]";
                $default_num_chars = (defined('PP_TEASER_NUM_CHARS')) ? PP_TEASER_NUM_CHARS : 50;
                $_num_setting = (!empty($this->arr_num_chars[$object_type])) ? $this->arr_num_chars[$object_type] : $default_num_chars;
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
                            <input type="number" id="<?php echo esc_attr($id_num); ?>" name="<?php echo esc_attr($name_num); ?>" value="<?php echo esc_attr($_num_setting); ?>" min="10" max="1000" class="small-text" placeholder="<?php esc_attr_e('Chars', 'press-permit-core'); ?>">
                            <span><?php esc_html_e('characters', 'press-permit-core'); ?></span>
                        </span>

                        <span class="pp-excerpt-chars-setting" style="<?php echo esc_attr($excerpt_num_style); ?>; margin-left: 10px;">
                            <span><?php esc_html_e('Max', 'press-permit-core'); ?></span>
                            <input type="number" id="<?php echo esc_attr($id_num); ?>-excerpt" name="<?php echo esc_attr($name_num); ?>" value="<?php echo esc_attr($_num_setting); ?>" min="10" max="1000" class="small-text" placeholder="<?php esc_attr_e('Chars', 'press-permit-core'); ?>">
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
                        <label style="margin-right: 20px;">
                            <input type="radio" name="<?php echo esc_attr($name_logged); ?>" value="0"<?php checked($logged_val == '0' || empty($logged_val), true); ?>>
                            <?php esc_html_e('Both', 'press-permit-core'); ?>
                        </label>
                        <?php 
                        $anon_disabled = !$this->isFeatureAvailable('user_application_anon');
                        $logged_disabled = !$this->isFeatureAvailable('user_application_logged');
                        ?>
                        <label style="margin-right: 20px;">
                            <input type="radio" name="<?php echo esc_attr($name_logged); ?>" value="anon"<?php checked($logged_val, 'anon'); ?><?php if ($anon_disabled) echo ' disabled'; ?>>
                            <?php esc_html_e('Not Logged In Users', 'press-permit-core'); ?>
                        </label>
                        <?php if ($anon_disabled) echo wp_kses_post($this->renderProBadge(__('Separate settings for anonymous users is a PRO feature', 'press-permit-core'))); ?>
                        <label>
                            <input type="radio" name="<?php echo esc_attr($name_logged); ?>" value="1"<?php checked($logged_val, '1'); ?><?php if ($logged_disabled) echo ' disabled'; ?>>
                            <?php esc_html_e('Logged In Users', 'press-permit-core'); ?>
                        </label>
                        <?php if ($logged_disabled) echo wp_kses_post($this->renderProBadge(__('Separate settings for logged-in users is a PRO feature', 'press-permit-core'))); ?>
                        <?php if (!$this->isProVersion()) : ?>
                        <p class="description" style="margin-top: 8px;">
                            <?php 
                            printf(
                                esc_html__('User-specific targeting is available in %sPRO%s', 'press-permit-core'),
                                '<a href="https://publishpress.com/links/permissions-banner" target="_blank" rel="noopener noreferrer">',
                                '</a>'
                            ); 
                            ?>
                        </p>
                        <?php endif; ?>
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
                        <select name="tease_public_posts_only[<?php echo esc_attr($object_type); ?>]" class="regular-text">
                            <option value="0"<?php selected($hide_private_val, '0'); ?>><?php esc_html_e("Apply Teaser to Private Posts", 'press-permit-core'); ?></option>
                            <option value="1"<?php selected($hide_private_val, '1'); ?>><?php esc_html_e("Hide Private Posts if user doesn't have access", 'press-permit-core'); ?></option>
                            <?php if ((defined('PUBLISHPRESS_STATUSES_VERSION') || class_exists('PublishPress\Statuses\Factory'))) : ?>
                            <option value="custom"<?php selected($hide_private_val, 'custom'); ?>><?php esc_html_e("Hide for Custom Visibility", 'press-permit-core'); ?></option>
                            <?php endif; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Hide Featured Image:', 'press-permit-core'); ?></th>
                    <td>
                        <?php
                        $hide_thumbnail_val = !empty($this->hide_thumbnail[$object_type]) ? $this->hide_thumbnail[$object_type] : 0;
                        ?>
                        <input type="hidden" name="teaser_hide_thumbnail[<?php echo esc_attr($object_type); ?>]" value="0">
                        <label>
                            <input type="checkbox" name="teaser_hide_thumbnail[<?php echo esc_attr($object_type); ?>]" value="1"<?php checked($hide_thumbnail_val, 1); ?>>
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
                        <select name="teaser_notice_style_mode[<?php echo esc_attr($object_type); ?>]" class="regular-text pp-teaser-notice-style-select" disabled>
                            <option value="default" <?php selected($teaser_notice_mode, 'default'); ?>>
                                <?php esc_html_e('Use Default Teaser Message Style', 'press-permit-core'); ?>
                            </option>
                            <option value="custom" <?php selected($teaser_notice_mode, 'custom'); ?>>
                                <?php esc_html_e('Use Custom Teaser Message Style', 'press-permit-core'); ?>
                            </option>
                        </select>
                        <?php if (!$this->isProVersion()) : echo wp_kses_post($this->renderProBadge(__('Separate settings for custom teaser message style is a PRO feature', 'press-permit-core'))); ?>
                        <p class="description">
                            <?php
                            printf(
                                esc_html__('Custom Teaser Message styling is available in %sPRO%s', 'press-permit-core'),
                                '<a href="https://publishpress.com/links/permissions-banner" target="_blank" rel="noopener noreferrer">',
                                '</a>'
                            ); ?>
                        </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <!-- Read More Notice (shown only when read_more is selected) -->
                <?php $this->renderReadMoreNoticeCard($object_type); ?>

                <!-- Excerpt Notice (shown only when excerpt is selected) -->
                <?php $this->renderExcerptNoticeCard($object_type); ?>

                <!-- X Chars Notice (shown only when x_chars is selected) -->
                <?php $this->renderXCharsNoticeCard($object_type); ?>

                </tbody>
            </tbody>
        </table>
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
                <span style="margin: 8px 8px 8px -12px;">
                    <?php if (!$this->isProVersion()) echo wp_kses_post($this->renderProBadge(__('Separate settings for logged-in users is a PRO feature', 'press-permit-core'))); ?>
                </span>
            </div>

            <!-- Tab Contents -->
            <div class="pp-teaser-text-content active" data-tab-content="anon">
                <?php $this->renderTeaserTextFields($object_type, '_anon'); ?>
            </div>

            <div class="pp-teaser-text-content" data-tab-content="logged" style="display:none;<?php if (!$this->isProVersion()) echo ' position: relative;'; ?>">
                <?php if (!$this->isProVersion()) : ?>
                    <div class="pp-pro-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(248, 249, 250, 0.95); z-index: 10; display: flex; align-items: center; justify-content: center; padding: 20px;">
                        <div style="text-align: center; max-width: 500px;">
                            <h4><?php esc_html_e('Logged In Users Settings', 'press-permit-core'); ?> <?php echo wp_kses_post($this->renderProBadge()); ?></h4>
                            <p><?php esc_html_e('Configure separate teaser text for logged-in users with different content replacement options.', 'press-permit-core'); ?></p>
                            <p>
                                <a href="<?php echo esc_url($this->getUpgradeUrl()); ?>" class="button button-primary" target="_blank">
                                    <?php esc_html_e('Upgrade to PRO', 'press-permit-core'); ?>
                                </a>
                                <a href="<?php echo esc_url($this->getComparisonUrl()); ?>" class="button button-secondary" target="_blank">
                                    <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
                <div style="<?php if (!$this->isProVersion()) echo 'pointer-events: none; opacity: 0.4;'; ?>">
                    <?php $this->renderTeaserTextFields($object_type, ''); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function renderTeaserTextFields($object_type, $suffix) {
        $user_label = ($suffix == '_anon') ? esc_html__('Not Logged In Users', 'press-permit-core') : esc_html__('Logged In Users', 'press-permit-core');

        // Prepare teaser text options
        $item_actions = [
            'content' => ['replace', 'prepend', 'append'],
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
                'replace' => sprintf(esc_html__('Replace %s with:', 'press-permit-core'), $trans_headings[$item]),
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
                    if ('post' == $object_type &&
                        'content' == $item && 
                        'replace' == $action &&
                        '_anon' == $suffix) $is_required = true;
                    else $is_required = false; 
                    ?>
                    <div class="pp-field-row <?php echo $is_required ? 'pp-required-field' : ''; ?>" data-field-action="<?php echo esc_attr($action); ?>" data-field-item="<?php echo esc_attr($item); ?>">
                        <div>
                            <label for="<?php echo esc_attr($id); ?>">
                                <strong><?php echo esc_html($actions_display[$action]); ?><?php echo (('content' == $item) && ('replace' == $action)) ? ' <span class="pp-required-indicator" style="color: red;">*</span>' : ''; ?></strong>
                            </label>
                        </div>
                        <div>
                            <?php if ('content' == $item) : 
                                $editor_settings = [
                                    'textarea_name' => $name,
                                    'textarea_rows' => 10,
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
                                <?php if (('content' == $item) && ('replace' == $action)) { ?>
                                    <p class="pp-add-login-form">
                                        <?php
                                        printf(
                                            esc_html__( 'Insert a login form by using %s[login_form]%s shortcode.', 'press-permit-core' ),
                                            '<a href="#">',
                                            '</a>'
                                        );
                                        ?>
                                    </p>
                                <?php } ?>
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
            <h4><?php _e('Redirect Settings', 'press-permit-core');?></h4>

            <?php if ($this->ui->display_hints) :?>
                <p>
                <?php \PublishPress\Permissions\UI\SettingsAdmin::echoStr('teaser_redirect_page');?>
                </p>
            <?php endif;?>

            <table class="widefat fixed striped teaser-table pp-teaser-redirect">
                <thead>
                    <tr>
                        <th colspan="3">
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
                        <th><?php _e('Page Selection', 'press-permit-core') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Not Logged In redirect
                $id = "teaser_redirect_anon";
                $id_slug = "teaser_redirect_anon_page";

                $this->ui->all_options[] = $id;
                if ($_setting = $this->pp->getOption($id_slug)) {
                    if (is_numeric($_setting)) {
                        $_setting = '(select)';
                    }
                }

                $this->ui->all_options[] = $id_slug;
                $redirect_page_id = $this->pp->getOption($id_slug);

                if ('[login]' == $redirect_page_id) {
                    $_setting = '[login]';
                }
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
                        echo "<select name='" . esc_attr($id) . "' id='" . esc_attr($id) . "' class='teaser-redirect-mode' autocomplete='off'>";
                        $captions = [
                            0 => esc_html__("No redirect", 'press-permit-core'),
                            '[login]' => esc_html__("Redirect to WordPress login", 'press-permit-core'),
                            '(select)' => esc_html__("Redirect to a custom post", 'press-permit-core'),
                        ];

                        foreach ($captions as $teaser_option_val => $teaser_caption) {
                            $selected = ($_setting == $teaser_option_val) ? ' selected ' : '';
                            echo "\n\t<option value='" . esc_attr($teaser_option_val) . "'" . esc_attr($selected) . ">" . esc_html($teaser_caption) . "</option>";
                        }

                        echo '</select>';
                        ?>
                    </td>
                    <td>
                        <?php
                        $style = ('(select)' === $_setting) ? '' : "display:none;";

                        $id = "teaser_redirect_anon_page";
                        ?>
                        <div class="pp-select-dynamic-wrapper" style="<?php echo esc_attr($style);?>">
                            <select class="permissions_select_posts"
                                    name="<?php esc_attr_e( $id_slug ) ?>"
                                    id="<?php esc_attr_e( $id_slug ) ?>"
                            >
                                <?php if( isset( $redirect_page_id ) && ! empty( $redirect_page_id ) ) : ?>
                                    <option value="<?php echo (int) $redirect_page_id ?>" selected="selected"><?php echo esc_html(get_the_title( (int) $redirect_page_id ))?></option>
                                <?php endif; ?>
                            </select>

                            <?php
                            $id = "teaser_redirect_custom_login_page_anon";
                            $this->ui->all_options[] = $id;
                            $_setting = $this->pp->getOption($id);
                            ?>
                            &nbsp;<label style="white-space:nowrap"><input type="checkbox" name="<?php echo esc_attr($id);?>" <?php if ($_setting) echo 'checked';?> /><?php _e('This is a custom login page', 'press-permit-core');?></label>
                        </div>
                    </td>
                </tr>

                <?php
                // Logged in redirect
                $id = "teaser_redirect";
                $id_slug = "teaser_redirect_page";

                $this->ui->all_options[] = $id;
                if ($_setting = $this->pp->getOption($id_slug)) {
                    if (is_numeric($_setting)) {
                        $_setting = '(select)';
                    }
                }

                $this->ui->all_options[] = $id_slug;
                $redirect_page_id = $this->pp->getOption($id_slug);

                if ('[login]' == $redirect_page_id) {
                    $_setting = '[login]';
                }
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
                        echo "<select name='" . esc_attr($id) . "' id='" . esc_attr($id) . "' class='teaser-redirect-mode' autocomplete='off'>";
                        $captions = [
                            0 => esc_html__("No redirect", 'press-permit-core'),
                            '[login]' => esc_html__("Redirect to WordPress login", 'press-permit-core'),
                            '(select)' => esc_html__("Redirect to a custom post", 'press-permit-core'),
                        ];

                        foreach ($captions as $teaser_option_val => $teaser_caption) {
                            $selected = ($_setting == $teaser_option_val) ? ' selected ' : '';
                            echo "\n\t<option value='" . esc_attr($teaser_option_val) . "'" . esc_attr($selected) . ">" . esc_html($teaser_caption) . "</option>";
                        }

                        echo '</select>';
                        ?>
                    </td>
                    <td>
                        <?php
                        $style = ('(select)' === $_setting) ? '' : "display:none;";

                        $id_slug = "teaser_redirect_page";
                        ?>
                        <div class="pp-select-dynamic-wrapper" style="<?php echo esc_attr($style);?>">
                            <select class="permissions_select_posts"
                                    name="<?php esc_attr_e( $id_slug ) ?>"
                                    id="<?php esc_attr_e( $id_slug ) ?>"
                            >
                                <?php if( isset( $redirect_page_id ) && ! empty( $redirect_page_id ) ) : ?>
                                    <option value="<?php echo (int) $redirect_page_id ?>" selected="selected"><?php echo esc_html(get_the_title( (int) $redirect_page_id ))?></option>
                                <?php endif; ?>
                            </select>
                            
                            <?php
                            $id = "teaser_redirect_custom_login_page";
                            $this->ui->all_options[] = $id;
                            $_setting = $this->pp->getOption($id);
                            ?>
                            &nbsp;<label style="white-space:nowrap"><input type="checkbox" name="<?php echo esc_attr($id);?>" <?php if ($_setting) echo 'checked';?> /><?php _e('This is a custom login page', 'press-permit-core');?></label>
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
        $this->ui->all_options[] = $id;
        $default_message = esc_html__('To read the full content, please log in to this site.', 'press-permit-core');
        $_setting = $this->pp->getOption($id);
        if (empty($_setting)) {
            $_setting = $default_message;
        }
        ?>
        <tr class="pp-conditional-settings pp-read-more-notice-card">
            <th scope="row" style="width: 30%;">
                <label for="<?php echo esc_attr($id . '_' . $object_type); ?>">
                    <?php esc_html_e('Message for Blocked Users', 'press-permit-core'); ?>
                    <?php $this->generateTooltip(esc_html__('Customize the message shown to users who are blocked from viewing the full content when using the "Read More" teaser type.', 'press-permit-core')) ?>
                </label>
            </th>
            <td>
                <input type="text" 
                        name="<?php echo esc_attr($id); ?>" 
                        id="<?php echo esc_attr($id . '_' . $object_type); ?>" 
                        value="<?php echo esc_attr($_setting); ?>" 
                        class="large-text" 
                        placeholder="<?php echo esc_attr($default_message); ?>"
                        style="width: 50%;">
                <?php $this->generateTooltip(esc_html__('This message will be displayed in a styled notice box above the teaser content for users who are not logged in.', 'press-permit-core')) ?>
            </td>
        </tr>
        <?php
    }

    private function renderExcerptNoticeCard($object_type) {
        $id = 'excerpt_login_notice';
        $this->ui->all_options[] = $id;
        $default_message = esc_html__('To read the full content, please log in to this site.', 'press-permit-core');
        $_setting = $this->pp->getOption($id);
        if (empty($_setting)) {
            $_setting = $default_message;
        }
        ?>
        <tr class="pp-conditional-settings pp-excerpt-notice-card">
            <th scope="row" style="width: 30%;">
                <label for="<?php echo esc_attr($id . '_' . $object_type); ?>">
                    <?php esc_html_e('Message for Blocked Users', 'press-permit-core'); ?>
                    <?php $this->generateTooltip(esc_html__('Customize the login notice message shown to non-logged-in users when using the "Excerpt" teaser type.', 'press-permit-core')) ?>
                </label>
            </th>
            <td>
                <?php
                
                ?>
                <input type="text" 
                        name="<?php echo esc_attr($id); ?>" 
                        id="<?php echo esc_attr($id . '_' . $object_type); ?>" 
                        value="<?php echo esc_attr($_setting); ?>" 
                        class="large-text" 
                        placeholder="<?php echo esc_attr($default_message); ?>"
                        style="width: 50%;">
                <?php $this->generateTooltip(esc_html__('This message will be displayed in a styled notice box below the excerpt teaser content for users who are not logged in.', 'press-permit-core')) ?>
            </td>
        </tr>
        <?php
    }

    private function renderXCharsNoticeCard($object_type) {
        $id = 'x_chars_login_notice';
        $this->ui->all_options[] = $id;
        $default_message = esc_html__('To read the full content, please log in to this site.', 'press-permit-core');
        $_setting = $this->pp->getOption($id);
        if (empty($_setting)) {
            $_setting = $default_message;
        }
        ?>
        <tr class="pp-conditional-settings pp-x-chars-notice-card">
            <th scope="row" style="width: 30%;">
                <label for="<?php echo esc_attr($id . '_' . $object_type); ?>">
                    <?php esc_html_e('Message for Blocked Users', 'press-permit-core'); ?>
                    <?php $this->generateTooltip(esc_html__('Customize the login notice message shown to non-logged-in users when using the "First X Characters" teaser type.', 'press-permit-core')) ?>
                </label>
            </th>
            <td>
                <input type="text" 
                        name="<?php echo esc_attr($id); ?>" 
                        id="<?php echo esc_attr($id . '_' . $object_type); ?>" 
                        value="<?php echo esc_attr($_setting); ?>" 
                        class="large-text" 
                        placeholder="<?php echo esc_attr($default_message); ?>"
                        style="width: 50%;">
                <?php $this->generateTooltip(esc_html__('This message will be displayed in a styled notice box below the truncated content teaser for users who are not logged in.', 'press-permit-core')) ?>
            </td>
        </tr>
        <?php
    }

    function generateTooltip($tooltip, $text = '', $position = 'top', $useIcon = true)
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

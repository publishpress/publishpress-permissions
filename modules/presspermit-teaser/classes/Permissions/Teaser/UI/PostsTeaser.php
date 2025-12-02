<?php
namespace PublishPress\Permissions\Teaser\UI;

use \PublishPress\Permissions\UI\SettingsAdmin as SettingsAdmin;

/**
 * PressPermit Custom Post Statuses administration panel.
 *
 */

class PostsTeaser
{
	var $blockEditorActive = true;

    function __construct() {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 7);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

		$this->blockEditorActive = PWP::isBlockEditorActive();

        // This script executes on admin.php plugin page load (called by Dashboard\DashboardFilters::actMenuHandler)
        //
        $this->display();
    }

    function optionTabs($tabs)
    {
        $tabs['teaser'] = esc_html__('Teaser', 'press-permit-core');
        return $tabs;
    }

    function sectionCaptions($sections)
    {
        $new = [
            'teaser_type' => esc_html__('Teaser Type', 'press-permit-core'),
            'coverage' => esc_html__('Coverage', 'press-permit-core'),
            'teaser_text' => esc_html__('Teaser Text', 'press-permit-core'),
            'read_more_notice' => esc_html__('Read More Notice', 'press-permit-core'),
            'redirect' => esc_html__('Redirect', 'press-permit-core'),
            'options' => esc_html__('Options', 'press-permit-core'),

            'hidden_content_teaser' => esc_html__('Hidden Content Teaser', 'press-permit-core'),
        ];
        $key = 'teaser';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    function optionCaptions($captions)
    {
        $opt = [
            'rss_private_feed_mode' => esc_html__('Display mode for readable private posts', 'press-permit-core'),
            'rss_nonprivate_feed_mode' => esc_html__('Display mode for readable non-private posts', 'press-permit-core'),
            'feed_teaser' => esc_html__('Feed Replacement Text (use %permalink% for post URL)', 'press-permit-core'),
            'read_more_login_notice' => esc_html__('Login Notice Message', 'press-permit-core'),
            'teaser_hide_thumbnail' => esc_html__('Hide Featured Image when Teaser is applied', 'press-permit-core'),
            'teaser_hide_custom_private_only' => esc_html__('"Hide Private" settings only apply to custom privacy (Member, Premium, Staff, etc.)', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    function optionSections($sections)
    {
        $new = [
            'teaser_type' => ['use_teaser', 'tease_logged_only'],
            'coverage' => ['teaser_hide_custom_private_only', 'tease_public_posts_only', 'tease_direct_access_only', 'teaser_hide_thumbnail'],
            'menu' => [''],
            'redirect' => ['teaser_redirect_anon', 'teaser_redirect_anon_page', 'teaser_redirect', 'teaser_redirect_page', 'teaser_redirect_custom_login_page_anon', 'teaser_redirect_custom_login_page'],
            'teaser_text' => ['tease_replace_content', 'tease_replace_content_anon', 'tease_prepend_content', 'tease_prepend_content_anon',
                              'tease_append_content', 'tease_append_content_anon', 'tease_prepend_name', 'tease_prepend_name_anon',
                              'tease_append_name', 'tease_append_name_anon', 'tease_replace_excerpt', 'tease_replace_excerpt_anon',
                              'tease_prepend_excerpt', 'tease_prepend_excerpt_anon', 'tease_append_excerpt', 'tease_append_excerpt_anon'],
            'read_more_notice' => ['read_more_login_notice'],
            'hidden_content_teaser' => ['teaser_hide_custom_private_only'],
            'options' => ['rss_private_feed_mode', 'rss_nonprivate_feed_mode', 'feed_teaser'],
        ];

        $key = 'teaser';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    function getStr($code) {
        return apply_filters('presspermit_admin_get_string', '', $code);
    }

    /**
     * Check if current version is PRO
     * 
     * @return bool True if PRO version is active
     */
    private function isProVersion() {
        return defined('PRESSPERMIT_PRO_VERSION');
    }

    /**
     * Check if a feature is available in current version
     * 
     * @param string $feature_key Feature identifier
     * @return bool True if feature is available
     */
    private function isFeatureAvailable($feature_key) {
        // Free version features
        $free_features = [
            'post_type_post' => true,              // Posts only
            'teaser_type_none' => true,            // No teaser option
            'teaser_type_configured' => true,      // Configured teaser text
            'user_application_both' => true,       // Both users option
            'teaser_text_replace_anon' => true,    // Replace content for anonymous
            'coverage_basic' => true,              // Basic coverage
            'hide_thumbnail' => true,              // Hide featured image
        ];
        
        // In PRO version, all features are available
        if ($this->isProVersion()) {
            return true;
        }
        
        return isset($free_features[$feature_key]) && $free_features[$feature_key];
    }

    /**
     * Render PRO badge for locked features
     * 
     * @param string $feature_name Display name of the feature
     * @param string $tooltip Optional tooltip text
     * @return string HTML for PRO badge
     */
    private function renderProBadge($feature_name = '', $tooltip = '') {
        if ($this->isProVersion()) {
            return '';
        }
        
        if (empty($tooltip) && !empty($feature_name)) {
            $tooltip = sprintf(
                esc_attr__('%s is a PRO feature', 'presspermit'),
                $feature_name
            );
        }
        
        $feature_slug = !empty($feature_name) ? sanitize_title($feature_name) : '';
        
        return sprintf(
            ' <span class="pp-pro-badge" title="%s" data-feature="%s">🔒 PRO</span>',
            esc_attr($tooltip),
            esc_attr($feature_slug)
        );
    }

    /**
     * Get upgrade URL
     * 
     * @return string Upgrade page URL
     */
    private function getUpgradeUrl() {
        return 'https://publishpress.com/links/permissions-banner';
    }

    /**
     * Get comparison URL
     * 
     * @return string Feature comparison page URL
     */
    private function getComparisonUrl() {
        return 'https://publishpress.com/permissions/pricing/';
    }

    /**
     * Render upgrade modal for locked features
     * 
     * @param string $feature_name Feature display name
     * @param array $benefits List of feature benefits
     * @return string HTML for upgrade modal
     */
    private function renderUpgradeModal($feature_name, $benefits = []) {
        if ($this->isProVersion()) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="pp-upgrade-modal" data-feature="<?php echo esc_attr(sanitize_title($feature_name)); ?>" style="display:none;">
            <div class="pp-modal-overlay"></div>
            <div class="pp-modal-content">
                <div class="pp-modal-header">
                    <h2><?php echo esc_html(sprintf(__('🔓 Unlock %s', 'presspermit'), $feature_name)); ?></h2>
                    <button class="pp-modal-close" type="button">×</button>
                </div>
                <div class="pp-modal-body">
                    <p><?php esc_html_e('This feature is available in PublishPress Permissions PRO', 'presspermit'); ?></p>
                    
                    <?php if (!empty($benefits)) : ?>
                    <ul class="pp-benefits-list">
                        <?php foreach ($benefits as $benefit) : ?>
                        <li>✓ <?php echo esc_html($benefit); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <div class="pp-modal-footer">
                    <a href="<?php echo esc_url($this->getUpgradeUrl()); ?>" class="button button-primary" target="_blank">
                        <?php esc_html_e('Upgrade to PRO', 'presspermit'); ?>
                    </a>
                    <a href="<?php echo esc_url($this->getComparisonUrl()); ?>" class="button button-secondary" target="_blank">
                        <?php esc_html_e('Compare Features', 'presspermit'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function display() {
        $pp = presspermit();
        
        echo '<form id="pp_settings_form" action="" method="post">';
        wp_nonce_field('pp-update-options');
        
        // Default active tab
        if (PWP::is_REQUEST('presspermit_submit')) {
            $current_tab = sanitize_text_field(PWP::REQUEST_key('current_tab'));
            $selected_post_type = PWP::is_REQUEST('selected_post_type') ? sanitize_text_field(PWP::REQUEST_key('selected_post_type')) : '';
        } else {
            $current_tab = 'ppp-tab-teaser-settings';
            $selected_post_type = '';
        }
        ?>
        <input type="hidden" value="<?php echo esc_attr($current_tab);?>" id="current_tab" name="current_tab">
        <input type="hidden" value="<?php echo esc_attr($selected_post_type);?>" id="selected_post_type" name="selected_post_type">
        <div class="wrap pressshack-admin-wrapper pp-conditions pp-teaser-redesign">
            <header>
                <h1 class="wp-heading-inline">
                    <?php echo esc_html(__('Posts Teaser', 'press-permit-core')); ?>
                </h1>
            </header>

			<?php
			if ( PWP::is_REQUEST( 'presspermit_submit' ) || PWP::is_REQUEST( 'presspermit_submit_redirect') ) :
				$current_tab = sanitize_text_field(PWP::REQUEST_key('current_tab'));
				?>
                <div id="message" class="updated">
                    <p>
                        <?php esc_html_e( 'All post teaser settings were updated.', 'press-permit-core' ); ?>
                    </p>
                </div>
			<?php
			elseif ( PWP::is_REQUEST( 'presspermit_defaults' ) ) :
                ?>
                <div id="message" class="updated">
                    <p>
                        <?php esc_html_e( 'All post teaser settings were reset to defaults.', 'press-permit-core' ); ?>
                    </p>
                </div>
         		<?php
			endif;
			?>

            <ul id="publishpress-permissions-teaser-tabs" class="nav-tab-wrapper">
                <li class="nav-tab<?php if ($current_tab === 'ppp-tab-teaser-settings') echo ' nav-tab-active';?>">
                  <a href="#ppp-tab-teaser-settings">
                      <?php _e('Teaser Settings', 'press-permit-core') ?>
                  </a>
                </li>

                <li class="nav-tab<?php if ($current_tab === 'ppp-tab-options') echo ' nav-tab-active';?>">
                  <a href="#ppp-tab-options">
                      <?php _e('Options', 'press-permit-core') ?>
                  </a>
                </li>
            </ul>

            <div id="pp-teaser">

            <?php
            do_action('presspermit_teaser_settings_ui');

            require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsAdmin.php');
            $ui = SettingsAdmin::instance();
            $tab = 'teaser';

            $ui->all_options = [];
            $ui->all_otype_options = [];

            $ui->tab_captions = apply_filters('presspermit_option_tabs', []);
            $ui->section_captions = apply_filters('presspermit_section_captions', []);
            $ui->option_captions = apply_filters('presspermit_option_captions', []);
            $ui->form_options = apply_filters('presspermit_option_sections', []);

            $ui->display_hints = presspermit()->getOption('display_hints');

            if ($_hidden = apply_filters('presspermit_hide_options', [])) {
                $hidden = [];
                foreach (array_keys($_hidden) as $option_name) {
                    if (!is_array($_hidden[$option_name]) && strlen($option_name) > 3)
                        $hidden[] = substr($option_name, 3);
                }

                foreach (array_keys($ui->form_options) as $tab_key) {
                    foreach (array_keys($ui->form_options[$tab_key]) as $section)
                        $ui->form_options[$tab_key][$section] = array_diff($ui->form_options[$tab_key][$section], $hidden);
                }
            }

            // Prepare data for new UI
            $default_options = apply_filters('presspermit_teaser_default_options', []);
            
            // Use trait method to get available post types (FREE: only 'post', PRO: all enabled)
            $available_post_types = $pp->getEnabledPostTypes();
            $opt_available = array_fill_keys($available_post_types, 0);
            $no_tease_types = \PublishPress\Permissions\Teaser::noTeaseTypes();

            $option_use_teaser = 'tease_post_types';
            $ui->all_otype_options[] = $option_use_teaser;
            $opt_vals = $ui->getOptionArray($option_use_teaser);
            $use_teaser = array_diff_key(array_merge($opt_available, $default_options[$option_use_teaser], $opt_vals), $no_tease_types);
            $use_teaser = array_intersect_key($use_teaser, array_fill_keys($available_post_types, true));
            $use_teaser = $pp->admin()->orderTypes($use_teaser, ['item_type' => 'post']);

            $option_num_chars = 'teaser_num_chars';
            $ui->all_otype_options[] = $option_num_chars;
            $arr_num_chars = $ui->getOptionArray($option_num_chars);

            $option_logged_only = 'tease_logged_only';
            $ui->all_otype_options[] = $option_logged_only;
            $opt_vals = $ui->getOptionArray($option_logged_only);
            $logged_only = array_diff_key(array_merge($opt_available, $default_options[$option_logged_only] ?? [], $opt_vals), $no_tease_types);

            $option_hide_private = 'tease_public_posts_only';
            $ui->all_otype_options[] = $option_hide_private;
            $opt_vals = $ui->getOptionArray($option_hide_private);
            $hide_private = array_diff_key(array_merge($opt_available, $default_options[$option_hide_private] ?? [], $opt_vals), $no_tease_types);
            $hide_private = array_intersect_key($hide_private, array_fill_keys($available_post_types, true));

            $option_direct_only = 'tease_direct_access_only';
            $ui->all_otype_options[] = $option_direct_only;
            $opt_vals = $ui->getOptionArray($option_direct_only);
            $direct_only = array_diff_key(array_merge($opt_available, $default_options[$option_direct_only] ?? [], $opt_vals), $no_tease_types);

            $option_hide_links = 'teaser_hide_menu_links_type';
            $ui->all_otype_options[] = $option_hide_links;
            $opt_vals = $ui->getOptionArray($option_hide_links);
            $defaults = (isset($default_options[$option_hide_links])) ? (array) $default_options[$option_hide_links] : [];
            $hide_links = array_diff_key(array_merge($opt_available, $defaults, $opt_vals), $no_tease_types);
            $hide_links = array_intersect_key($hide_links, array_fill_keys($available_post_types, true));

            $option_hide_thumbnail = 'teaser_hide_thumbnail';
            $ui->all_otype_options[] = $option_hide_thumbnail;
            $opt_vals = $ui->getOptionArray($option_hide_thumbnail);
            $hide_thumbnail = array_diff_key(array_merge($opt_available, $default_options[$option_hide_thumbnail] ?? [], $opt_vals), $no_tease_types);

            $section = 'teaser_type';

        $default_options = apply_filters('presspermit_teaser_default_options', []);

        // Use trait method to get available post types (FREE: only 'post', PRO: all enabled)
        $available_post_types = $pp->getEnabledPostTypes();
        $opt_available = array_fill_keys($available_post_types, 0);
        $no_tease_types = \PublishPress\Permissions\Teaser::noTeaseTypes();

        $option_use_teaser = 'tease_post_types';
        $ui->all_otype_options[] = $option_use_teaser;
        $opt_vals = $ui->getOptionArray($option_use_teaser);
        $use_teaser = array_diff_key(array_merge($opt_available, $default_options[$option_use_teaser], $opt_vals), $no_tease_types);

        $option_num_chars = 'teaser_num_chars';
        $ui->all_otype_options[] = $option_num_chars;
        $arr_num_chars = $ui->getOptionArray($option_num_chars);

        // Prepare Coverage options
        $option_hide_private = 'tease_public_posts_only';
        $ui->all_otype_options[] = $option_hide_private;
        $opt_vals = $ui->getOptionArray($option_hide_private);
        $hide_private = array_diff_key(array_merge($opt_available, $default_options[$option_hide_private] ?? [], $opt_vals), $no_tease_types);
        $hide_private = array_intersect_key($hide_private, array_fill_keys($available_post_types, true));

        $option_direct_only = 'tease_direct_access_only';
        $ui->all_otype_options[] = $option_direct_only;
        $opt_vals = $ui->getOptionArray($option_direct_only);
        $direct_only = array_diff_key(array_merge($opt_available, $default_options[$option_direct_only] ?? [], $opt_vals), $no_tease_types);

        $option_hide_links = 'teaser_hide_menu_links_type';
        $ui->all_otype_options[] = $option_hide_links;
        $opt_vals = $ui->getOptionArray($option_hide_links);

        $defaults = (isset($default_options[$option_hide_links])) ? (array) $default_options[$option_hide_links] : [];
        $hide_links = array_diff_key(array_merge($opt_available, $defaults, $opt_vals), $no_tease_types);
        $hide_links = array_intersect_key($hide_links, array_fill_keys($available_post_types, true));

        if (!empty($ui->form_options[$tab][$section])) : ?>
            <section id="ppp-tab-teaser-settings" style="display:<?php if ($current_tab === 'ppp-tab-teaser-settings') echo 'block'; else echo 'none'; ?>;">
			<p>
            <?php
			if (empty($displayed_teaser_caption)) {
                if ($ui->display_hints) {
                    SettingsAdmin::echoStr('display_teaser');
                }

                $displayed_teaser_caption = true;
            }
			?>
			</p>

			<?php
            $option_logged_only = 'tease_logged_only';
            $ui->all_otype_options[] = $option_logged_only;
            $opt_vals = $ui->getOptionArray($option_logged_only);
            $logged_only = array_diff_key(array_merge($opt_available, $default_options[$option_logged_only] ?? [], $opt_vals), $no_tease_types);

            $use_teaser = array_intersect_key($use_teaser, array_fill_keys($available_post_types, true));
            $use_teaser = $pp->admin()->orderTypes($use_teaser, ['item_type' => 'post']);
            
            // Sort array to ensure 'post' appears first
            if (isset($use_teaser['post'])) {
                $post_value = $use_teaser['post'];
                unset($use_teaser['post']);
                $use_teaser = array_merge(['post' => $post_value], $use_teaser);
            }

            $any_teased_types = array_filter($use_teaser);

            // Render new Progressive Disclosure UI
            require_once(__DIR__ . '/TeaserUIBaseTrait.php');
            require_once(__DIR__ . '/TeaserProgressiveUI.php');

            $options_data = [
                'logged_only' => $logged_only,
                'hide_private' => $hide_private,
                'direct_only' => $direct_only,
                'hide_links' => $hide_links,
                'arr_num_chars' => $arr_num_chars,
                'hide_thumbnail' => $hide_thumbnail
            ];
            
            $progressive_ui = new TeaserProgressiveUI($pp, $ui, $use_teaser, $options_data, $this->blockEditorActive);
            $progressive_ui->render();
            ?>

            </section>
        <?php
        endif; // any options accessable in this section


        $section = 'options';                                // --- OPTIONS SECTION ---
        if (!empty($ui->form_options[$tab][$section])) : ?>
            <section id="ppp-tab-options" style="display:<?php if ($current_tab === 'ppp-tab-options') echo 'block'; else echo 'none'; ?>;">
            
            <?php
            $style = ($any_teased_types) ? "display:none" : '';
            ?>
            <p class="pp-teaser-settings-na" style="<?php echo esc_attr($style);?>">
            <?php
            SettingsAdmin::echoStr('teaser_settings_not_applicable');
			?>
            </p>

            <?php
            $style = (!$any_teased_types) ? "display:none" : '';
            ?>

            <div class="pp-teaser-options" style="<?php echo esc_attr($style);?>">
            <h2 class="title">
				<?php esc_html_e( 'RSS', 'press-permit-core' ); ?>
			</h2>
			<p>
				<?php
				if ( $ui->display_hints ) {
					SettingsAdmin::echoStr( 'teaser_block_all_rss' );
				}
				?>
			</p>
			<table class="form-table">

				<?php
				// Display for readable private posts
				if ( in_array( 'rss_private_feed_mode', $ui->form_options[$tab][$section], true ) ) :
					$ui->all_options[] = 'rss_private_feed_mode';
					?>
					<tr>
						<th>
							<?php
							esc_html_e( 'Display for readable private posts:', 'press-permit-core' );
		                    ?>
						</th>
						<td>
							<?php
							echo '<select name="rss_private_feed_mode" id="rss_private_feed_mode" autocomplete="off">';
							$captions = ['full_content' => esc_html__("Full Content", 'press-permit-core'), 'excerpt_only' => esc_html__("Excerpt Only", 'press-permit-core'), 'title_only' => esc_html__("Title Only", 'press-permit-core')];
							foreach ($captions as $key => $value) {
								$selected = ($ui->getOption('rss_private_feed_mode') == $key) ? ' selected ' : '';
								echo "\n\t<option value='" . esc_attr($key) . "' " . esc_attr($selected) . ">" . esc_html($captions[$key]) . "</option>";
							}
							echo '</select>';
							?>
						</td>
					</tr>
					<?php
				endif;

				// Display for readable non-private posts
				if ( in_array( 'rss_nonprivate_feed_mode', $ui->form_options[$tab][$section], true ) ) :
					$ui->all_options[] = 'rss_nonprivate_feed_mode';
					?>
					<tr>
						<th>
							<?php
							esc_html_e( 'Display for readable non-private posts:', 'press-permit-core' );
		                    ?>
						</th>
						<td>
							<?php
							echo '<select name="rss_nonprivate_feed_mode" id="rss_nonprivate_feed_mode" autocomplete="off">';
	                        $captions = ['full_content' => esc_html__("Full Content", 'press-permit-core'), 'excerpt_only' => esc_html__("Excerpt Only", 'press-permit-core'), 'title_only' => esc_html__("Title Only", 'press-permit-core')];
	                        foreach ($captions as $key => $value) {
	                            $selected = ($ui->getOption('rss_nonprivate_feed_mode') == $key) ? ' selected ' : '';
	                            echo "\n\t<option value='" . esc_attr($key) . "' " . esc_attr($selected) . ">" . esc_html($captions[$key]) . "</option>";
	                        }
	                        echo '</select>';
							?>
						</td>
					</tr>
					<?php
				endif;

				// Feed Replacement Text
				if ( in_array( 'feed_teaser', $ui->form_options[$tab][$section], true ) ) :
					$id = 'feed_teaser';
					$ui->all_options[] = $id;
					$val = htmlspecialchars($ui->getOption($id));
					?>
					<tr>
						<th>
							<?php
							esc_html_e( 'Feed Replacement Text:', 'press-permit-core' );
							?>
						</th>
						<td>
							<?php
                            // phpcs Note: This option cannot currently be escaped because it supports embedded html
                            $editor_settings = [
                                'textarea_name' => $id,
                                'textarea_rows' => 6,
                                'media_buttons' => false,
                                'teeny' => true,
                                'quicktags' => ['buttons' => 'strong,em,link'],
                                'tinymce' => [
                                    'toolbar1' => 'bold,italic,link,unlink,undo,redo',
                                    'toolbar2' => '',
                                    'toolbar3' => '',
                                ]
                            ];
                            
                            // Decode HTML entities for the editor
                            $editor_value = html_entity_decode($val, ENT_QUOTES, 'UTF-8');
                            wp_editor($editor_value, $id, $editor_settings);
							?>
							<p class="description">
								<?php printf(
									esc_html__( 'Use %s for post URL', 'press-permit-core' ),
									'<code>%permalink%</code>'
								); ?>
							</p>
						</td>
					</tr>
				<?php endif; ?>
			</table>
            
            </div>
            </section>
        <?php
        endif; // any options accessable in this section

        echo "<input type='hidden' name='all_options' value='" . esc_attr(implode(',', $ui->all_options)) . "' />";
        echo "<input type='hidden' name='all_otype_options' value='" . esc_attr(implode(',', $ui->all_otype_options)) . "' />";

        echo "<input type='hidden' name='pp_submission_topic' value='options' />";
        ?>

            <p>
                <input type="submit" name="presspermit_submit" class="button button-primary" value="<?php _e('Save Changes', 'press-permit-core') ?>">
                <input type="submit" name="presspermit_defaults" class="button button-secondary" value="<?php _e('Revert to Defaults', 'press-permit-core') ?>" style="float:right;">
            </p>
        </div>
        <?php
        presspermit()->admin()->publishpressFooter();
        ?>
    </div>

    </form>
    <?php

    }
}


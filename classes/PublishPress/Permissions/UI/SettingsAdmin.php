<?php

namespace PublishPress\Permissions\UI;

class SettingsAdmin
{
    private static $instance;

    var $form_options;
    var $tab_captions;
    var $tab_badges;
    var $section_captions;
    var $option_captions;
    var $all_options;
    var $all_otype_options;
    var $display_hints;

    var $defined_integrations = [];
	var $available_integrations = [];

    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new SettingsAdmin();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->loadIntegrations();
    }

    private function loadIntegrations() {
        $this->defined_integrations = \PublishPress\Permissions::getDefinedIntegrations();
        
        $this->available_integrations = [];
    
        foreach ($this->defined_integrations as $i => $integration) {
            if ($this->defined_integrations[$i]['available']) {
                $this->available_integrations[$integration['id']] = true;
            }
        }
    }

    public static function echoStr($string_id) {
        if ($custom_echo = apply_filters('presspermit_admin_echo_string', false, $string_id)) {
            return;
        } else {
            echo esc_html(self::getStr($string_id));
        }
    }

    public static function getStr($string_id) {
        switch ($string_id) {

        // Core
        case 'post_blockage_priority':
        return __('If disabled, manually "blocked" posts can be unblocked by specific Category / Term Permissions.', 'press-permit-core-hints');

        case 'define_media_post_caps' :
        return __('If enabled, corresponding Media edit and delete capabilities must be added to existing roles.', 'press-permit-core-hints');

        case 'define_media_post_caps_collab_prompt' :
        return __('For specialized Media Library permissions, enable the Editing Permissions feature.', 'press-permit-core-hints');

        case 'bbp_compat_prompt' :
        return __('To customize bbPress forum permissions, activate the Compatibility Pack feature.', 'press-permit-core-hints');

        case 'bbp_pro_prompt' :
        return __('To customize bbPress forum permissions, activate your Permissions Pro license key.', 'press-permit-core-hints');

        case 'strip_private_caption' :
        return __('Remove the "Private:" and "Protected" prefix from Post, Page titles.', 'press-permit-core-hints');

        case 'regulate_category_archive_page' :
        return __('If the user is blocked from reading posts in a category, also block access to category archive page.', 'press-permit-core-hints');

        case 'force_nav_menu_filter' :
        return __('Remove unreadable Menu Items. If menu rendering problems occur with a third party plugin, disable this setting.', 'press-permit-core-hints');

        case 'posts_listing_unmodified' :
        return __('Unmodified from WordPress default behavior. To enable filtering, remove constant definition PP_ADMIN_READONLY_LISTABLE.', 'press-permit-core-hints');

        case 'display_user_profile_roles' :
        return __('Note: Groups and Roles are always displayed on the Edit User screen.', 'press-permit-core-hints');


        // Advanced
        case 'anonymous_unfiltered' :
        return __('Disable Permissions filtering for users who are not logged in.', 'press-permit-core-hints');

        case 'limit_front_end_term_filtering' :
        return __('Legacy compatibility: front end term filters are not applied on some sites.', 'press-permit-core-hints');

        case 'suppress_administrator_metagroups' :
        return __('If checked, pages blocked from the "All" or "Logged In" groups will still be listed to Administrators.', 'press-permit-core-hints');

        case 'suppress_administrator_metagroups' :
        return __('If enabled, users with the pp_set_read_exceptions capability in the WP role can set reading permissions for their editable posts.', 'press-permit-core-hints');

        case 'user_search_by_role' :
        return __('Display a role dropdown alongside the user search input box to narrow results.', 'press-permit-core-hints');

        case 'display_hints' :
        return __('Display descriptive captions on Permissions screens.', 'press-permit-core-hints');

        case 'display_extension_hints' :
        return __('Display descriptive captions for features available in missing or deactivated modules.', 'press-permit-core-hints');

        case 'pattern_roles_include_generic_rolecaps':
        return __('Extra Roles cause "_posts" capabilities in the Pattern Role (Author, Editor, etc.) to be assigned for the selected post type. Enable this setting to also include all other capabilities in the Pattern Role.', 'press-permit-core-hints');

        case 'dynamic_wp_roles' :
        return __('Detect user roles which are appended dynamically but not stored to the WP database. May be useful for sites that sync with Active Directory or other external user registration systems.', 'press-permit-core-hints');

        case 'pp_capabilities' :
        return __('You can adjust some Permissions behavior by %1$s adding capabilities to a WordPress role%2$s.', 'press-permit-core-hints');

        case 'pp_capabilities_install_prompt' :
        return __('You can add Permissions capabilities to a role using the %1$s plugin.', 'press-permit-core-hints');

        case 'non_admins_set_read_exceptions' :
        return __('Regardless of this setting, the pp_set_read_exceptions capability is also required.', 'press-permit-core-hints');

        case 'non_admins_set_edit_exceptions' :
        return __('Regardless of this setting, the pp_set_edit_exceptions capability is also required.', 'press-permit-core-hints');

        // Editing
        case 'collaborative-publishing' :
        return sprintf(__('Settings related to content editing permissions, provided by the %s module.', 'press-permit-core-hints'), __('Editing Permissions', 'press-permit-core-hints'));

        case 'list_others_uneditable_posts' :
        return __('If this setting is disabled, you can enable a role by giving it capabilities: list_others_posts, list_others_published_pages, etc.', 'press-permit-core-hints');

        case 'force_taxonomy_cols' :
        return __('Display a custom column on Edit Posts screen for all related taxonomies which are enabled for Permissions filtering.', 'press-permit-core-hints');

        case 'add_author_pages' :
        return __('Allows creation of a new post (of any type) for each selected user, using an existing post as the pattern.', 'press-permit-core-hints');

        case 'lock_top_pages' :
        return __('Other users will be unable to publish new top-level pages (Parent = "Main Page"), or move a published page from "Main Page" to a different Parent.', 'press-permit-core-hints');

        case 'limited_editing_elements' :
        return __('Remove Edit Form elements with these (comma-separated) html IDs from users who do not have full editing capabilities for the post/page.', 'press-permit-core-hints');

        case 'media_lib_unfiltered' :
        return __('The following settings are currently overridden by the constant PP_MEDIA_LIB_UNFILTERED (defined in wp-config.php or some other file you maintain). Media Library access will not be altered by Permissions.', 'press-permit-core-hints');

        case 'admin_others_attached_to_readable' :
        return __('Show files uploaded by others users if they are attached to a post the user is allowed to view.', 'press-permit-core-hints');

        case 'admin_others_attached_files' :
        return __('Show files uploaded by other users if they are attached to a post the user is allowed to edit.', 'press-permit-core-hints');

        case 'edit_others_attached_files' :
        return __('Allow users to edit files uploaded by other users if they are attached to a post the user is allowed to edit.', 'press-permit-core-hints');

        case 'admin_others_unattached_files' :
        return __('Show users all the files in the Media Library. Unchecking this box will hide media files that are uploaded by other users.', 'press-permit-core-hints');

        case 'attachment_edit_requires_parent_access':
        return __('Stop users from editing files uploaded by other users unless they are attached to a post the user is allowed to edit.', 'press-permit-core-hints');

        case 'own_attachments_always_editable' :
        return __('Allow users to edit files they have uploaded, regardless of other restrictions.', 'press-permit-core-hints');

        case 'admin_nav_menu_partial_editing' :
        return __('Allow non-Administrators to rename menu items they cannot fully edit. Menu items will be locked into current positions.', 'press-permit-core-hints');

        case 'admin_nav_menu_lock_custom' :
        return __('Prevent creation or editing of custom items for non-Administrators who lack edit_theme_options capability.', 'press-permit-core-hints');

        case 'limit_user_edit_by_level' :
        return __('Prevent non-Administrators with user editing permissions from editing a higher-level user or assigning a role higher than their own.', 'press-permit-core-hints');

        case 'non_admins_set_edit_exceptions' :
        return __('If enabled, the capabilities pp_set_edit_exceptions, pp_set_associate_exceptions, etc. will be honored. See list of capabilities below.', 'press-permit-core-hints');

        // Circles
        case 'access_circles_limit_revisions':
        return __('Circle members will be blocked from accessing Revisions authored by non-members.', 'presspermit-pro-hints');

        default:
        }

        return apply_filters('presspermit_admin_get_string', '', $string_id);
    }

    public static function getConstantStr($constant) {
        switch ($constant) {

        case 'PP_NON_EDITORS_SET_EDIT_EXCEPTIONS' :
		return esc_html__("Enable post contributors or authors with pp_set_edit_exceptions capability to set editing Permissions on posts authored by others", 'press-permit-core-hints');

        // 'editing'
        case 'PP_LOCK_OPTION_PAGES_ONLY' :
		return esc_html__("Permissions setting 'Pages can be set or removed from Top Level by' applies to 'page' type only", 'press-permit-core-hints');

        case 'PPCE_LIMITED_EDITORS_TOP_LEVEL_PUBLISH' :
		return esc_html__("If user cannot generally save pages to top level but a page they are editing is already there, allow it to stay at top level even if not yet published ", 'press-permit-core-hints');

        case 'PPC_ASSOCIATION_NOFILTER' :
		return esc_html__("Circle membership does not limit page association (page parent setting) ability", 'press-permit-core-hints');

        // auto-default-term-setting-override
        case 'PP_AUTO_DEFAULT_TERM' :
		return esc_html__("Determines the default value of Permissions > Settings > Editing > Auto-assign default term", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_CATEGORY' :
        return esc_html__("Always auto-assign a category if none are selected and user lacks permission for default category", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_POST_TAG' :
        return esc_html__("Always auto-assign a post tag if none are selected", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_CUSTOM_TAXOMY_NAME_HERE' :
        return esc_html__("Always auto-assign a term of this taxonomy if none are selected", 'press-permit-core-hints');

        case 'PP_NO_AUTO_DEFAULT_TERM' :
		return esc_html__("Determines the default setting of Permissions > Settings > Editing > Auto-assign default terms", 'press-permit-core-hints');

        case 'PP_NO_AUTO_DEFAULT_CATEGORY' :
        return esc_html__("Do not auto-assign a category (except standard default category if user has permission)", 'press-permit-core-hints');

        case 'PP_NO_AUTO_DEFAULT_POST_TAG' :
        return esc_html__("Do not auto-assign a post tag", 'press-permit-core-hints');

        case 'PP_NO_AUTO_DEFAULT_CUSTOM_TAXOMY_NAME_HERE' :
        return esc_html__("Do not auto-assign a term of this taxonomy", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_SINGLE_TERM_ONLY' :
        return esc_html__("Do not auto-assign a term if the user has permission for more than one term in the taxonomy", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_TERM_EXCEPTIONS_NOT_REQUIRED' :
        return esc_html__("If auto-assign term setting is enabled, apply it even if user does not have term assignment limitations", 'press-permit-core-hints');

        case 'PP_NO_MODERATION' :
		return esc_html__("Don't define an 'Approved' status, even if Status Control feature is active", 'press-permit-core-hints');


        // 'nav-menu-manage'
        case 'PP_SUPPRESS_APPEARANCE_LINK' :
		return esc_html__("If user has Nav Menu management capabilities but can't 'edit_theme_options', strip link out of wp-admin Appearance Menu instead of linking it to nav-menus", 'press-permit-core-hints');

        case 'PP_STRICT_MENU_CAPS' :
		return esc_html__("Don't credit implicit 'manage_nav_menus' capability to users who have 'edit_theme_options' or 'switch_themes' capability", 'press-permit-core-hints');

        case 'PPCE_RESTRICT_MENU_TOP_LEVEL' :
		return esc_html__("Prevent non-Administrators from adding new Nav Menu items to top level (add below existing editable items instead)", 'press-permit-core-hints');

        case 'PP_NAV_MENU_DEFAULT_TO_SUBITEM' :
		return esc_html__("For non-Administrators, new Nav Menu items default to being a child of first editable item ", 'press-permit-core-hints');

        case 'PP_LEGACY_MENU_SETTINGS_ACCESS' :
		return esc_html__("Don't require any additional capabilities for management of Nav Menu settings (normally require 'manage_menu_settings', 'edit_others_pages' or 'publish_pages') ", 'press-permit-core-hints');

        case 'PPCE_DISABLE_NAV_MENU_UPDATE_FILTERS' :
		return esc_html__("Eliminate extra filtering queries on Nav Menu update, even for non-Administrators", 'press-permit-core-hints');


        // 'media'
        case 'PP_BLOCK_UNATTACHED_UPLOADS' :
		return esc_html__("Don't allow non-Administrators to see others' unattached uploads, regardless of Permissions settings.  Their own unattached uploads are still accessible unless option 'own_attachments_always_editable' is set false", 'press-permit-core-hints');


        // 'admin'
        case 'PPCE_CAN_ASSIGN_OWN_ROLE' :
		return esc_html__("Limited User Editors can assign their own role", 'press-permit-core-hints');

        case 'PP_AUTHOR_POST_META' :
		return esc_html__("Post Meta fields to copy when using 'Add Author Page' dropdown on Users screen", 'press-permit-core-hints');

        default:
        }

        return apply_filters('presspermit_get_constant_descript', '', $constant);
    }

    static function setCapabilityDescriptions($pp_caps) {
        $pp_caps['pp_manage_settings'] = esc_html__('Manage PublishPress Permissions plugin settings.', 'press-permit-core-hints');
        $pp_caps['pp_unfiltered'] = esc_html__('PublishPress Permissions will not apply any Extra Roles or Permissions to limit or expand viewing or editing access.', 'press-permit-core-hints');
        $pp_caps['pp_administer_content'] = esc_html__('Manage other user\'s Permissions. Also grants capabilities for all post types and statuses.', 'press-permit-core-hints');
        $pp_caps['pp_create_groups'] = esc_html__('Create new Permission Groups and set the name and description.', 'press-permit-core-hints');
        $pp_caps['pp_edit_groups'] = esc_html__('Edit the name and description of existing Permission Groups.', 'press-permit-core-hints');
        $pp_caps['pp_delete_groups'] = esc_html__('Delete Permission Groups.', 'press-permit-core-hints');
        $pp_caps['pp_manage_members'] = esc_html__('If group editing is allowed, can also modify group membership.', 'press-permit-core-hints');
        $pp_caps['pp_assign_roles'] = esc_html__('Assign Extra Roles or Permissions.', 'press-permit-core-hints');
        $pp_caps['pp_assign_bulk_roles'] = esc_html__('Assign Extra Roles or Permissions on the Edit Permissions screen.', 'press-permit-core-hints');
        $pp_caps['pp_set_read_exceptions'] = esc_html__('Set Viewing Permissions for specific Posts, Categories or Terms.', 'press-permit-core-hints');

		if (presspermit()->moduleActive('collaboration')) {
            $pp_caps['pp_set_edit_exceptions'] =            esc_html__('Set Editing Permissions for specific Posts, Categories or Terms.', 'press-permit-core-hints');

            if ((defined('PUBLISHPRESS_REVISIONS_VERSION') || defined('REVISIONARY_VERSION'))) {
	            $pp_caps['pp_set_revise_exceptions'] = esc_html__('Set Revision Permissions for specific Posts, Categories or Terms.', 'press-permit-core-hints');
	        }
	
	        $pp_caps['pp_set_associate_exceptions'] =       esc_html__('Set "Set as Parent" Permissions for specific Posts.', 'press-permit-core-hints');
	        $pp_caps['pp_set_term_assign_exceptions'] =     esc_html__('Set Term Assignment Permissions.', 'press-permit-core-hints');
	        $pp_caps['pp_set_term_manage_exceptions'] =     esc_html__('Set Term Management Permissions.', 'press-permit-core-hints');
	        $pp_caps['pp_set_term_associate_exceptions'] =  esc_html__('Set "Set as Parent" Permissions for specific Categories or Terms.', 'press-permit-core-hints');
	
	        $pp_caps['edit_own_attachments'] =          esc_html__('Edit own file uploads, even if they become attached to an uneditable post.', 'press-permit-core-hints');
	        $pp_caps['list_others_unattached_files'] =  esc_html__("See other user's unattached file uploads in Media Library.", 'press-permit-core-hints');
	        $pp_caps['pp_associate_any_page'] =         esc_html__('Can bypass restrictive "Set as Parent" Permissions (for all hierarchical post types).', 'press-permit-core-hints');
	
	        $pp_caps['pp_list_all_files'] =     esc_html__('Do not alter the Media Library listing provided by WordPress.', 'press-permit-core-hints');
	        $pp_caps['list_posts'] =            esc_html__('On the Posts screen, satisfy a missing edit_posts capability by listing uneditable drafts.', 'press-permit-core-hints');
	        $pp_caps['list_others_posts'] =     esc_html__("On the Posts screen, satisfy a missing edit_others_posts capability by listing other user's uneditable posts.", 'press-permit-core-hints');
	        $pp_caps['list_private_pages'] =    esc_html__('On the Pages screen, satisfy a missing edit_private_pages capability by listing uneditable private pages.', 'press-permit-core-hints');
	        $pp_caps['pp_force_quick_edit'] =   esc_html__('Allow non-Administrators to Quick Edit or Bulk Edit (possibly allowing them to bypass restrictions).', 'press-permit-core-hints');
		}

        if (!defined('PRESSPERMIT_PRO_VERSION') && !presspermit()->moduleActive('status-control') && !presspermit()->keyActive()) {
            $pp_caps = array_merge(
                $pp_caps,
                [
                    'pp_define_post_status' => esc_html__('(Permissions Pro capability)', 'press-permit-core-hints'),
                    'pp_define_privacy' => esc_html__('(Permissions Pro capability)', 'press-permit-core-hints'),
                    'set_posts_status' => esc_html__('(Permissions Pro capability)', 'press-permit-core-hints'),
                    'pp_moderate_any' => esc_html__('(Permissions Pro capability)', 'press-permit-core-hints'),
                ]
            );
        }

        return $pp_caps;
    }

    public function getOption($option_basename)
    {
        return presspermit()->getOption($option_basename);
    }

    public function getOptionArray($option_basename)
    {
        $val = presspermit()->getOption($option_basename);

        if (!$val || !is_array($val)) {
            $val = [];
        }

        return $val;
    }

    public function optionCheckbox($option_name, $tab_name, $section_name, $hint_text = '', $trailing_break = '', $args = [])
    {
        $return = ['in_scope' => false, 'no_storage' => false, 'disabled' => false, 'title' => '', 'style' => '', 'div_style' => ''];

        if (isset($this->form_options[$tab_name][$section_name]) 
        && in_array($option_name, $this->form_options[$tab_name][$section_name], true) 
        && isset($this->option_captions[$option_name])
        ) {
            $display_label = (!isset($args['display_label'])) ? true : $args['display_label'];
            
            if (empty($args['no_storage']))
                $this->all_options[] = $option_name;

            if (isset($args['val']))
                $return['val'] = $args['val'];
            else
                $return['val'] = (!empty($args['no_storage'])) ? 0 : presspermit()->getOption($option_name);

            $disabled = (!empty($args['disabled']) || $this->hideNetworkOption($option_name)) ? " disabled " : '';
            $style = (!empty($args['style'])) ? $args['style'] : '';
            $div_style = (!empty($args['div_style'])) ? $args['div_style'] : '';

            $title = (!empty($args['title'])) ? $args['title'] : '';

            echo "<div class='agp-opt-checkbox " . esc_attr($option_name) . "' style='" . esc_attr($div_style) . "'>"
                . "<label for='" . esc_attr($option_name) . "' title='" . esc_attr($title) . "'>"
                . "<input name='" . esc_attr($option_name) . "' type='checkbox' " . esc_attr($disabled) . " style='" . esc_attr($style) . "' id='" . esc_attr($option_name) . "' value='1' " . esc_attr(checked('1', $return['val'], false)) . " autocomplete='off' /> ";
                
            if ($display_label && isset($this->option_captions[$option_name])) {
                esc_html_e($this->option_captions[$option_name]);
            }
            
            echo "</label>";

            if ($hint_text && $this->display_hints) {
                $hint_class = 'pp-subtext';
                $hint_class .= (!empty($args['hint_class'])) ? ' ' . $args['hint_class'] : '';

                if (true === $hint_text) :?>
                    <?php if (self::getStr($option_name)):?>
                        <div class='<?php echo esc_attr($hint_class); ?>'><?php self::echoStr($option_name);?></div>
                    <?php endif;?>
                <?php else :?>
                    <div class='<?php echo esc_attr($hint_class); ?>'><?php echo(esc_html($hint_text));?></div>
                <?php endif;
            }

            echo "</div>";

            if ($trailing_break)
                echo '<br />';

            $return['in_scope'] = true;
        }

        return $return;
    }

    private function hideNetworkOption($option_name)
    {
                                                                        // Precautionary exception: don't change storage location for license key
    	$is_main_site = (defined('PRESSPERMIT_LEGACY_MAIN_SITE_CHECK') || (('edd_key' == $option_name) && !defined('PRESSPERMIT_EDD_KEY_SITEMETA_STORAGE'))) ? (1 == get_current_blog_id()) : is_main_site();
    	
        if (is_multisite()) {
            return (in_array($option_name, presspermit()->netwide_options, true) && PWP::isNetworkActivated()
                && !is_network_admin() && !$is_main_site);
        } else
            return false;
    }

    public function filterNetworkOptions()
    {
    	$is_main_site = (defined('PRESSPERMIT_LEGACY_MAIN_SITE_CHECK')) ? (1 == get_current_blog_id()) : is_main_site();
    	
        if (is_multisite() && !is_network_admin() && !$is_main_site) {
            $pp = presspermit();
            $this->all_options = array_diff($this->all_options, $pp->netwide_options);
            $this->all_otype_options = array_diff($this->all_otype_options, $pp->netwide_options);
        }
    }
}

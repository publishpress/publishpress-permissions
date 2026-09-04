<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

class DashboardFiltersNonAdministrator
{
    function __construct()
    {
        global $pagenow;

        // ============== UI-related filters ================
        if ('nav-menus.php' == $pagenow) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/NavMenu.php');
            new NavMenu();
        }

        add_action('admin_print_scripts', [$this, 'header_scripts']);
        add_action('admin_print_footer_scripts', [$this, 'footer_scripts']);

        add_filter('presspermit_posts_request_bypass', [$this, 'flt_posts_request_bypass'], 10, 3);

        add_action('admin_print_scripts', [$this, 'filter_add_new_button']);
    }

    function header_scripts()
    {
        global $current_user, $pagenow;
        // if user is allowed to view and attach files, but not upload them...
        if (empty($current_user->allcaps['upload_files']) && !empty($current_user->allcaps['edit_files'])) : ?>
            <style type="text/css">
                #adminmenu ul.wp-submenu li a[href="media-new.php"] {
                    display: none;
                }
            </style>
        <?php
        endif;


        // In case we are fudging the edit_theme_options cap for nav menu management, keep other Appearance menu items hidden
        if (empty($current_user->allcaps['edit_theme_options']) && ('nav-menus.php' == $pagenow)) : ?>
            <style type="text/css">
                #nav-menu-header .delete-action a {
                    display: none;
                }
            </style>
        <?php
        endif;
    }

    function footer_scripts()
    {
        global $current_user, $pagenow;

        // In case we are fudging the edit_theme_options cap for nav menu management, keep other Appearance menu items hidden
        if (empty($current_user->allcaps['edit_theme_options'])) :
            presspermit_enqueue_admin_script(); ?>
            <span class="pp-appearance-menu-config" data-suppress-link="<?php echo defined('PP_SUPPRESS_APPEARANCE_LINK') ? '1' : '0'; ?>" hidden></span>
            <?php
        endif;  // limited nav menu manager?
    } // end function footer_scripts

    function flt_posts_request_bypass($bypass, $request, $args)
    {
        // if Media Library filtering is disabled, don't filter listing for TinyMCE popup either
        if (defined('PP_MEDIA_LIB_UNFILTERED') && (isset($_SERVER['SCRIPT_NAME']) && strpos(sanitize_text_field($_SERVER['SCRIPT_NAME']), 'wp-admin/media-upload.php'))) {
            return true;
        }

        return $bypass;
    }

    function filter_add_new_button()
    {
        global $pagenow;

        if (in_array($pagenow, ['edit.php', 'post.php', 'post-new.php', 'upload.php'])) {
            if (!$_post_type = PWP::REQUEST_key('post_type')) {
                $_post_type = 'post';
            }

            if (!in_array($_post_type, presspermit()->getEnabledPostTypes(), true)) {
                return;
            }

            if ($wp_type = get_post_type_object($_post_type)) {
                $cap_check = (isset($wp_type->cap->create_posts)) ? $wp_type->cap->create_posts : $wp_type->cap->edit_posts;

                if (!current_user_can($cap_check)) : ?>
                    <style type="text/css">
                        .add-new-h2 {
                            display: none;
                        }
                    </style>
                <?php
                endif;
            }
        }
    }
}

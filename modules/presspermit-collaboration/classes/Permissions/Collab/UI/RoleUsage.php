<?php
namespace PublishPress\Permissions\Collab\UI;

/**
 * Users administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

class RoleUsage 
{
    function __construct() 
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/RoleUsageHelper.php');
        $this->display();
    }

    private function display() {
        if (!current_user_can('pp_manage_settings'))
            wp_die(esc_html__('You are not permitted to do that.', 'press-permit-core'));

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/RoleUsageListTable.php');
        $role_usage_table = RoleUsageListTable::instance();

        $url = $referer = $redirect = $update = '';
        RoleUsageHelper::getUrlProperties($url, $referer, $redirect);

        $role_usage_table->prepare_items();
        $total_pages = $role_usage_table->get_pagination_arg('total_pages');

        $messages = [];
        if ($update = PWP::GET_key('update')) :
            switch ($update) {
                case 'edit':
                    $messages[] = '<div id="message" class="updated"><p>' . esc_html__('Role Usage edited.', 'press-permit-core') . '</p></div>';
                    break;
            }
        endif;
        ?>

        <?php
        $admin = presspermit()->admin();

        if (isset($admin->errors) && is_wp_error($admin->errors)) :
            ?>
            <div class="error">
                <ul>
                    <?php
                    foreach ($admin->errors->get_error_messages() as $err)
                        echo "<li>" . esc_html($err) . "</li>\n";
                    ?>
                </ul>
            </div>
        <?php
        endif;
        ?>

        <div class="wrap pressshack-admin-wrapper presspermit-role-usage" id="pp-permissions-wrapper">
            <header>
            <?php \PublishPress\Permissions\UI\PluginPage::icon(); ?>
            <h1>
                <?php
                esc_html_e('Edit Role Usage', 'press-permit-core');
                ?>
            </h1>

            <?php
            if (presspermit()->getOption('display_hints')) {
                echo '<div class="pp-hint pp-no-hide">';
                esc_html_e("These optional settings customize how PublishPress Permissions applies extra roles. Your existing WP Role Definitions can be applied in two different ways:", 'press-permit-core');
                
                echo '<ul style="list-style-type:disc;list-style-position:outside;margin:1em 0 0 2em"><li>' 
                . esc_html__("Pattern Roles convert 'post' capabilities to the corresponding type-specific capability.  In a normal WP installation, this is the easiest solution.", 'press-permit-core') 
                . '</li>';
                
                echo '<li>' 
                . esc_html__("With Direct Assignment, capabilities are applied without modification.", 'press-permit-core') 
                . '</li></ul>';
                
                echo '</div>';
            }
            ?>
            </header>

            <?php
            $role_usage_table->views();
            $role_usage_table->display();
            ?>
            <form method="post" action="">
                <?php
                $msg = esc_html__("All Role Usage settings will be reset to DEFAULTS.  Are you sure?", 'press-permit-core');
                ?>
                <p class="submit" style="border:none;float:left">
                    <input type="submit" name="pp_role_usage_defaults" value="<?php esc_attr_e('Revert to Defaults', 'press-permit-core') ?>"
                        onclick="<?php echo "javascript:if (confirm('" . esc_attr($msg) . "')) {return true;} else {return false;}"; ?>"/>
                </p>
                <br style="clear:both"/>
            </form>
            <?php

            if (presspermit()->getOption('display_hints')) {
                RoleUsageHelper::other_notes();
            }

            presspermit()->admin()->publishpressFooter();
            ?>
        </div>
    <?php
    }
}

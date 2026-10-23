<?php

namespace PublishPress\Permissions\UI;

\defined('ABSPATH') || exit;

class GroupNew
{
    public function __construct()
    {
        // called by Dashboard\DashboardFilters::actMenuHandler

        require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentPermissionsUI.php');

        $url = apply_filters('presspermit_groups_base_url', 'admin.php');

        if ($wp_http_referer = PWP::REQUEST_url('wp_http_referer')) {
            $wp_http_referer = esc_url_raw($wp_http_referer);
        } elseif ($http_referer = PWP::SERVER_url('HTTP_REFERER')) {
            if (!strpos(esc_url_raw($http_referer), 'page=presspermit-group-new')) {
                $wp_http_referer = esc_url_raw($http_referer);
            } else {
                $wp_http_referer = '';
            }

            $wp_http_referer = remove_query_arg(['update', 'delete_count'], stripslashes($wp_http_referer));
        } else
            $wp_http_referer = '';

        if (!current_user_can('pp_create_groups'))
            wp_die(esc_html__('You are not permitted to do that.', 'press-permit-core'));
?>

        <?php
        $pp = presspermit();
        $pp_admin = $pp->admin();
        $pp_groups = $pp->groups();

        if (!PWP::empty_GET('update') && empty($pp_admin->errors)) : ?>
            <div id="message" class="updated">
                <p><strong><?php esc_html_e('Group created.', 'press-permit-core') ?>&nbsp;</strong>
                    <?php
                    if (!$group_variant = PWP::REQUEST_key('group_variant')) {
                        $group_variant = 'pp_group';
                    }

                    $groups_link = ($wp_http_referer && strpos($wp_http_referer, 'presspermit-groups'))
                        ? $wp_http_referer
                        : admin_url("admin.php?page=presspermit-groups&group_variant=$group_variant");
                    ?>

                    <a href="<?php echo esc_url($groups_link); ?>"><?php esc_html_e('Back to permissions list', 'press-permit-core'); ?></a>
                </p>
            </div>
        <?php endif; ?>

        <?php
        if (!empty($pp_admin->errors) && is_wp_error($pp_admin->errors)) : ?>
            <div class="error">
                <?php
                foreach ($pp_admin->errors->get_error_messages() as $msg) {
                    echo '<p>' . wp_kses_post($msg) . '</p>';
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="wrap pressshack-admin-wrapper" id="pp-permissions-wrapper">
            <header>
                <?php
                PluginPage::icon();
                ?>
                <h1><?php
                    $agent_type = PWP::REQUEST_key('agent_type');

                    if (!$pp_groups->groupTypeEditable($agent_type)) {
                        $agent_type = 'pp_group';
                    }

                    if (('pp_group' == $agent_type) || !$group_type_obj = $pp_groups->getGroupTypeObject($agent_type))
                        esc_html_e('Create New Permission Group', 'press-permit-core');
                    else
                        printf(esc_html__('Create New %s', 'press-permit-core'), esc_html($group_type_obj->labels->singular_name));
                    ?></h1>
            </header>

            <form action="" method="post" id="creategroup" name="creategroup" class="pp-admin">
                <?php wp_nonce_field('pp-update-group_0') ?>
                <input name="action" type="hidden" value="creategroup" />
                <input name="agent_type" type="hidden" value="<?php echo esc_attr($agent_type); ?>" />
                <?php wp_nonce_field('pp-create-group', '_wpnonce_pp-create-group') ?>

                <?php if ($wp_http_referer) : ?>
                    <input type="hidden" name="wp_http_referer" value="<?php echo esc_url($wp_http_referer); ?>" />
                <?php endif; ?>

                <table class="form-table">
                    <tr class="form-field form-required">
                        <th scope="row"><label for="group_name"><?php echo esc_html__('Name', 'press-permit-core'); ?></label></th>
                        <td><input type="text" name="group_name" id="group_name" value="" class="regular-text"
                                tabindex="1" /></td>
                    </tr>

                    <tr class="form-field">
                        <th><label for="description"><?php echo esc_html__('Description', 'press-permit-core'); ?></label></th>
                        <td><input type="text" name="description" id="description" value="" class="regular-text" size="80"
                                tabindex="2" /></td>
                    </tr>
                </table>

                <?php
                if ($pp_groups->userCan('pp_manage_members', 0, $agent_type)) {
                    AgentPermissionsUI::drawMemberChecklists(0, $agent_type);
                }

                echo '<div class="pp-settings-caption" style="clear:both;"><br />';
                esc_html_e('Note: Groups are a more flexible alternative to user roles. You can create groups of users and give or deny them access to areas of your site.', 'press-permit-core');
                echo '</div>';

                do_action('presspermit_new_group_ui');
                ?>

                <?php
                submit_button(esc_html__('Create Group', 'press-permit-core'), 'primary large pp-submit', '', true, 'tabindex="3"');
                ?>

            </form>

            <?php
            presspermit()->admin()->publishpressFooter();
            ?>
        </div>
<?php
    }
}

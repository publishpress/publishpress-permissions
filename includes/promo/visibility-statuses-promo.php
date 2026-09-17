<div class="wrap publishpress-caps-manage pressshack-admin-wrapper pp-conditions pp-permissions-menus-wrapper-promo">
    <header>
    <div class="pp-icon"><?php echo '<img src="' . esc_url(PRESSPERMIT_URLPATH . '/common/img/publishpress-logo-icon.png') . '" alt="" />';?></div>
    <h1>
    <?php esc_html_e('Post Visibility Statuses', 'press-permit-core');?>
    </h1>
    </header>

    <table id="akmin">
        <tr>
            <td class="content">
                <div id="pp-permissions-menu-wrapper" class="postbox" style="box-shadow: none; background: none;">
                    <div class="pp-permissions-menus-promo">
                        <div class="pp-permissions-menus-promo-inner">
                            <img src="<?php echo esc_url(PRESSPERMIT_URLPATH . '/includes/promo/permissions-visibility-statuses-desktop.jpg');?>" class="pp-permissions-desktop" alt="<?php esc_attr_e('Post Visibility Statuses preview', 'press-permit-core'); ?>" />
                            <img src="<?php echo esc_url(PRESSPERMIT_URLPATH . '/includes/promo/permissions-visibility-statuses-mobile.jpg');?>" class="pp-permissions-mobile" alt="<?php esc_attr_e('Post Visibility Statuses preview', 'press-permit-core'); ?>" />
                            <div class="pp-permissions-menus-promo-content">
                                <p>
                                    <?php esc_html_e('Control access to post visibility statuses. This workflow feature is available in PublishPress Permissions Pro.', 'press-permit-core'); ?>
                                </p>
                                <p>
                                    <a href="https://publishpress.com/links/permissions-statuses-screen" target="_blank">
                                        <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                                    </a>
                                </p>
                            </div>
                            <div class="pp-permissions-menus-promo-gradient"></div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <?php
    presspermit()->admin()->publishpressFooter();
    ?>
</div>

<?php

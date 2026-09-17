<div class="wrap publishpress-caps-manage pressshack-admin-wrapper pp-conditions pp-permissions-menus-wrapper-promo">
    <header>
        <h1>
            <?php esc_html_e('Posts Teaser', 'press-permit-core'); ?>
        </h1>
    </header>

    <table id="akmin">
        <tr>
            <td class="content">
                <div id="pp-permissions-menu-wrapper" class="postbox" style="box-shadow: none; background: none;">
                    <div class="pp-permissions-menus-promo">
                        <div class="pp-permissions-menus-promo-inner">
                            <img src="<?php echo esc_url(PRESSPERMIT_URLPATH . '/includes/promo/permissions-teaser-desktop.jpg'); ?>" class="pp-permissions-desktop" alt="<?php esc_attr_e('Posts Teaser preview', 'press-permit-core'); ?>" />
                            <img src="<?php echo esc_url(PRESSPERMIT_URLPATH . '/includes/promo/permissions-teaser-mobile.jpg'); ?>" class="pp-permissions-mobile" alt="<?php esc_attr_e('Posts Teaser preview', 'press-permit-core'); ?>" />
                            <div class="pp-permissions-menus-promo-content">
                                <p>
                                    <?php esc_html_e('Show a teaser message or login prompt for protected content. Available in PublishPress Permissions Pro.', 'press-permit-core'); ?>
                                </p>
                                <p>
                                    <a href="https://publishpress.com/links/permissions-teaser-screen" target="_blank">
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

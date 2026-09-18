<?php

namespace PublishPress\Permissions\UI;

class HintsMedia
{
    public static function fileFilteringPromo()
    {
        presspermit_enqueue_admin_script();
        ?>
        <span class="pp-file-filtering-promo-config" hidden>
            <a href="#" class="pp-file-filtering-promo"><?php esc_html_e('Block URL access', 'press-permit-core'); ?></a>
            <span class="pp-ext-promo" style="display:none;">
                <?php
                if (presspermit()->isPro()) {
                    esc_html_e('To block direct URL access to attachments of unreadable posts, activate the File Access feature.', 'press-permit-core');
                } else {
                    printf(
                        esc_html__('To block direct URL access to attachments of unreadable posts, %1$supgrade to Permissions Pro%2$s and enable the File Access feature.', 'press-permit-core'),
                        '<a href="https://publishpress.com/pricing/">',
                        '</a>'
                    );
                }
                ?>
            </span>
        </span>
        <?php
    }
}

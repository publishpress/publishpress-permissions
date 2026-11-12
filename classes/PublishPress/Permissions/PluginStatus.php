<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class PluginStatus
{
    public static function renewalMsg()
    {
        // todo: replace these string with EDD integration equivalents

        printf(
            esc_html__('Your license key has expired. For updates to PublishPress Permissions Pro and priority support, %splease renew%s.', 'press-permit-core'),
            "<a href='" . esc_url(admin_url('admin.php?page=presspermit-settings&pp_tab=install')) . "' target='_blank'>",
            '</a>'
        );
    }

    public static function buyMsg()
    {
        printf(
            'Activate your %slicense key%s for PublishPress Permissions Pro downloads and priority support.',
            "<a href='" . esc_url(admin_url('admin.php?page=presspermit-settings&pp_tab=install')) . "' target='_blank'>",
            '</a>'
        );
    }
}

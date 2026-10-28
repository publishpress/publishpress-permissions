<?php

namespace PublishPress\Permissions\UI;

\defined('ABSPATH') || exit;

class HintsItemExceptions
{
    public static function permissionPriorityTooltip()
    {
        $hint = __('Permissions are combined across a user, their roles, and their groups. Adding more Enabled entries does not increase their priority.', 'press-permit-core');

        if (defined('PP_RESTRICTION_PRIORITY') && PP_RESTRICTION_PRIORITY) {
            $hint .= ' ' . __('On this site, Blocked restrictions take priority over Enabled permissions.', 'press-permit-core');
        } else {
            $hint .= ' ' . __('An Enabled permission for an item can override a Blocked permission for that same item.', 'press-permit-core');
            $hint .= ' ' . (presspermit()->getOption('post_blockage_priority')
                ? __('Post-specific permissions take priority over category or term permissions.', 'press-permit-core')
                : __('Enabled category or term permissions can also override a post-specific block.', 'press-permit-core'));
        }

        echo '<span data-toggle="tooltip" data-placement="top">';
        echo '<button type="button" class="button-link" style="color:inherit;" aria-label="' . esc_attr($hint) . '">';
        echo '<span class="dashicons dashicons-info-outline" style="font-size:18px;width:16px;height:16px;margin-left:5px;" aria-hidden="true"></span></button>';
        echo '<span class="tooltip-text" aria-hidden="true"><span>' . esc_html($hint) . '</span><i></i></span></span>';
    }

    public static function itemHints($for_item_type)
    {
        $pp = presspermit();

        if (('attachment' == $for_item_type) && !$pp->moduleActive('file-access')) {
            echo "<div class='pp-ext-promo' style='padding:0.5em'>";

            if (presspermit()->isPro()) {
                esc_html_e('To block direct access to unreadable files, activate the File Access feature.', 'press-permit-core');
            } else {
                printf(
                    esc_html__('To block direct access to unreadable files, %1$supgrade to Permissions Pro%2$s and enable the File Access feature.', 'press-permit-core'),
                    '<a href="https://publishpress.com/pricing/">',
                    '</a>'
                );
            }

            echo "</div>";
        }

        if (!$pp->moduleActive('collaboration')) {
            echo "<div class='pp-ext-promo' style='padding:0.5em;margin-top:0'>";
            esc_html_e('To customize editing permissions, enable the Editing Permissions feature.', 'press-permit-core');
            echo "</div>";
        }
    }
}

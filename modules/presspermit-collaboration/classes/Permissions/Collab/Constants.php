<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class Constants
{
    function __construct()
    {
        add_filter('presspermit_constants', [$this, 'flt_pp_constants']);

    }

    function flt_pp_constants($pp_constants)
    {

        $type = 'permissions-admin';
        $consts = [
            'PP_NON_EDITORS_SET_EDIT_EXCEPTIONS',
        ];
        foreach ($consts as $k) $pp_constants[$k] = (object)['descript' => \PublishPress\Permissions\UI\SettingsAdmin::getConstantStr($k), 'type' => $type];


        $type = 'post-editing';
        $consts = [
            'PP_LOCK_OPTION_PAGES_ONLY',
            'PPCE_LIMITED_EDITORS_TOP_LEVEL_PUBLISH',
            //'PP_NO_MODERATION',
        ];

        if (defined('PRESSPERMIT_CIRCLES_VERSION')) {
            $consts[] = 'PPC_ASSOCIATION_NOFILTER';
        }
        foreach ($consts as $k) $pp_constants[$k] = (object)['descript' => \PublishPress\Permissions\UI\SettingsAdmin::getConstantStr($k), 'type' => $type];


        $type = 'auto-default-term-setting-override';
        $consts = [
            'PP_AUTO_DEFAULT_TERM',
            'PP_AUTO_DEFAULT_CATEGORY',
            'PP_AUTO_DEFAULT_POST_TAG',
            'PP_AUTO_DEFAULT_CUSTOM_TAXOMY_NAME_HERE',
            'PP_NO_AUTO_DEFAULT_TERM',
            'PP_NO_AUTO_DEFAULT_CATEGORY',
            'PP_NO_AUTO_DEFAULT_POST_TAG',
            'PP_NO_AUTO_DEFAULT_CUSTOM_TAXOMY_NAME_HERE',
            'PP_AUTO_DEFAULT_SINGLE_TERM_ONLY',
            'PP_AUTO_DEFAULT_TERM_EXCEPTIONS_NOT_REQUIRED',
        ];
        foreach ($consts as $k) $pp_constants[$k] = (object)['descript' => \PublishPress\Permissions\UI\SettingsAdmin::getConstantStr($k), 'type' => $type];


        $type = 'nav-menu-manage';
        $consts = [
            'PP_SUPPRESS_APPEARANCE_LINK',
            'PP_STRICT_MENU_CAPS',
            'PPCE_RESTRICT_MENU_TOP_LEVEL',
            'PP_NAV_MENU_DEFAULT_TO_SUBITEM',
            'PP_LEGACY_MENU_SETTINGS_ACCESS',
            'PPCE_DISABLE_NAV_MENU_UPDATE_FILTERS',
        ];
        foreach ($consts as $k) $pp_constants[$k] = (object)['descript' => \PublishPress\Permissions\UI\SettingsAdmin::getConstantStr($k), 'type' => $type];


        $type = 'media';
        $consts = [
            'PP_BLOCK_UNATTACHED_UPLOADS',
        ];
        foreach ($consts as $k) $pp_constants[$k] = (object)['descript' => \PublishPress\Permissions\UI\SettingsAdmin::getConstantStr($k), 'type' => $type];


        $type = 'admin';
        $consts = [
            'PPCE_CAN_ASSIGN_OWN_ROLE',
            'PP_AUTHOR_POST_META',
        ];
        foreach ($consts as $k) $pp_constants[$k] = (object)['descript' => \PublishPress\Permissions\UI\SettingsAdmin::getConstantStr($k), 'type' => $type];


        return $pp_constants;
    }

}

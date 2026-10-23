<?php

namespace PublishPress\Permissions\UI\Dashboard;

\defined('ABSPATH') || exit;

class UserAjax
{
    public function __construct() 
    {
        if (!current_user_can('create_users') || !current_user_can('pp_manage_members')) {
            return;
        }

        if (!$pp_ajax_user = PWP::GET_key('pp_ajax_user')) {
            return;
        }

        check_ajax_referer('pp-ajax');

        switch ($pp_ajax_user) {
            case 'new_user_groups_ui':
                require_once( PRESSPERMIT_CLASSPATH . '/UI/Dashboard/Profile.php' );
                Profile::displayUserGroups();
                break;
        }
    }
}

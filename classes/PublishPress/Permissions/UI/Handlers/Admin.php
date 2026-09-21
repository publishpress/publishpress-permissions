<?php

namespace PublishPress\Permissions\UI\Handlers;

class Admin
{
    public static function handleRequest() {
        do_action('presspermit_admin_handlers');

        if (!PWP::empty_POST()) {
            $pp_plugin_page = presspermitPluginPage();

            if (('presspermit-edit-permissions' == $pp_plugin_page)
                || PWP::is_POST('action', ['pp_updateroles', 'pp_updateexceptions', 'pp_updateclone', 'pp_updategroups'])
                || ((PWP::is_REQUEST('_wp_http_referer') && strpos(esc_url_raw(PWP::REQUEST_url('_wp_http_referer')), 'presspermit-edit-permissions'))
                || ('presspermit-group-new' == $pp_plugin_page)
            )) {
                add_action(
                    'presspermit_user_init', 
                    function() {
                    	require_once(PRESSPERMIT_CLASSPATH . '/UI/Handlers/AgentEdit.php');
                    	new AgentEdit();
                    	do_action('presspermit_trigger_cache_flush');
                    }
                );
            }
        }

        if (!PWP::empty_REQUEST('action') || !PWP::empty_REQUEST('action2') || !PWP::empty_REQUEST('pp_action')) {
            if (('presspermit-groups' == presspermitPluginPage()) || (!PWP::empty_REQUEST('wp_http_referer')
                    && (strpos(esc_url_raw(PWP::REQUEST_url('wp_http_referer')), 'page=presspermit-groups'))
            )) {
                add_action('presspermit_user_init', function()
                {
                    require_once(PRESSPERMIT_CLASSPATH . '/UI/Handlers/Groups.php');
                    Groups::handleRequest();
                });
            }
        }
    }
}

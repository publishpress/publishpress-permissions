<?php
namespace PublishPress\Permissions\Teaser;

class Admin
{
    function __construct()
    {
    	if ('presspermit-posts-teaser' == presspermitPluginPage()) {
            add_action('admin_enqueue_scripts', function() {
                wp_enqueue_style('presspermit-settings', PRESSPERMIT_URLPATH . '/common/css/settings.css', [], PRESSPERMIT_VERSION);

                $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
                wp_enqueue_script('presspermit-teaser-settings', PRESSPERMIT_URLPATH . "/common/js/settings{$suffix}.js", ['jquery'], PRESSPERMIT_TEASER_VERSION, false);


                $urlpath = plugins_url('', PRESSPERMIT_TEASER_FILE);
                wp_enqueue_style('presspermit-teaser-settings', PRESSPERMIT_URLPATH . '/common/css/settings.css', [], PRESSPERMIT_TEASER_VERSION);
            });
        }
    }
}

<?php
function presspermit() {
    require_once(__DIR__ . '/classes/PublishPress/Permissions.php');
    return \PublishPress\Permissions::instance();
}

/**
 * Enqueue shared admin behavior.
 *
 * Dynamic values are exposed by the rendering PHP as data attributes. Keeping
 * executable code in this file makes CSP enforcement and browser debugging
 * straightforward.
 */
function presspermit_enqueue_admin_script()
{
    $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

    wp_enqueue_script(
        'presspermit-admin',
        plugins_url("common/js/admin{$suffix}.js", __FILE__),
        ['jquery'],
        PRESSPERMIT_VERSION,
        true
    );
}

/**
 * Enqueue shared front-end behavior.
 */
function presspermit_enqueue_front_script()
{
    $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

    wp_enqueue_script(
        'presspermit-front',
        plugins_url("common/js/frontend{$suffix}.js", __FILE__),
        [],
        PRESSPERMIT_VERSION,
        true
    );
}

function presspermitPluginPage()
{
    static $pp_plugin_page = null;

    if (is_null($pp_plugin_page)) {
        $pp_plugin_page = (is_admin() && \PressShack\LibWP::REQUEST_key_match('page', 'presspermit-'))
            ? \PressShack\LibWP::REQUEST_key('page')
            : false;
    }

    return $pp_plugin_page;
}

function presspermit_is_preview() {
    global $wp_query;

	if (isset($wp_query)) {
        $is_preview = is_preview();
    } else {
        $is_preview = !\PressShack\LibWP::empty_REQUEST('preview');
    }

    if (!$is_preview) {
        if (defined('ELEMENTOR_VERSION')) {
           $is_preview = !\PressShack\LibWP::empty_REQUEST('elementor-preview');
        } elseif (defined('ET_CORE')) {
            $is_preview = !\PressShack\LibWP::empty_REQUEST('et_fb');
        }
    }

    return apply_filters('presspermit_is_preview', $is_preview);
}

function publishpress_is_post_teaser($post_id = 0) {
    return class_exists('PublishPress\Permissions\Teaser') && \PublishPress\Permissions\Teaser::isTeasedPost($post_id);
}

function publishpress_is_archive_teaser() {
    return class_exists('PublishPress\Permissions\Teaser') && \PublishPress\Permissions\Teaser::isArchiveTeaser();
}

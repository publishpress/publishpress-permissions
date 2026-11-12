<?php
defined('ABSPATH') || exit;

// phpcs Note: This file is only loaded if WP_DEBUG is enabled

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize, WordPress.PHP.DevelopmentFunctions.error_log_error_log
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_dump, WordPress.PHP.DevelopmentFunctions.error_log_var_dump
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
// phpcs:disable PublishPressStandards.Debug.DisallowDebugFunctions.FoundVarDumpFunction, 

add_action('admin_footer', 'presspermit_echo_usage_message', 50);

function presspermit_usage_message($translate = true)
{
    if (function_exists('memory_get_usage')) {
        if ($translate)
            return sprintf(esc_html__('%1$s queries in %2$s seconds. %3$s MB used.', 'press-permit-core'), get_num_queries(), timer_stop(0, 2), round(memory_get_usage() / (1024 * 1024), 3), 'press-permit-core') . ' ';
        else
            return get_num_queries() . ' queries in ' . timer_stop(0, 2) . ' seconds. ' . round(memory_get_usage() / (1024 * 1024), 3) . ' MB used. ';
    }
}

function presspermit_echo_usage_message($translate = true)
{
    if (!defined('PUBLISHPRESS_REVISIONS_VERSION') && !defined('REVISIONARY_VERSION') && !defined('PRESSPERMIT_USAGE_MESSAGE_DONE') && !defined('AGP_NO_USAGE_MSG')) {  // Revisionary outputs its own message
        echo '<p style="text-align:center">' . esc_html(presspermit_usage_message($translate)) . '</p>';
        define('PRESSPERMIT_USAGE_MESSAGE_DONE', true);
    }
}

function presspermit_editing_plugin()
{
    // avoid lockout in case of erroneous plugin edit via wp-admin
    global $pagenow;

    if (is_admin() && isset($pagenow) && ('plugin-editor.php' == $pagenow)) {
        require_once(PRESSPERMIT_ABSPATH . '/functions.php');

        if (!PWP::is_REQUEST('action', ['activate', 'deactivate'])) {
            return true;
    	}
    }

    return false;
}

if (!function_exists('pp_debug_echo')) {
    function pp_debug_echo($str)
    {
        if (!constant('PRESSPERMIT_DEBUG'))
            return;
    }
}

if (!function_exists('pp_errlog')) {
    function pp_errlog($msg, $line_break = true)
    {
        if (!constant('PRESSPERMIT_DEBUG'))
            return;

        if (is_array($msg) || is_object($msg))
            $msg = serialize($msg);

        $append = ($line_break) ? "\r\n" : '';

        if (defined('PRESSPERMIT_DEBUG_LOGFILE'))
            error_log($msg . $append, 3, PRESSPERMIT_DEBUG_LOGFILE);
    }
}

if (!function_exists('pp_backtrace_dump')) {
    function pp_backtrace_dump($die = true)
    {
        if (!constant('PRESSPERMIT_DEBUG'))
            return;

        $bt = debug_backtrace();
        var_dump($bt);

        if ($die)
            die;
    }
}


if (!function_exists('_pp_memory_new_usage')) {
    function _pp_memory_new_usage()
    {
        if (!constant('PRESSPERMIT_DEBUG') || !defined('PRESSPERMIT_MEMORY_LOG') || !PRESSPERMIT_MEMORY_LOG)
            return;

        static $last_mem_usage;

        if (!isset($last_mem_usage))
            $last_mem_usage = 0;

        $current_mem_usage = memory_get_usage(true);
        $new_mem_usage = $current_mem_usage - $last_mem_usage;
        $last_mem_usage = $current_mem_usage;

        return $new_mem_usage;
    }
}

if (!function_exists('pp_log_mem_usage')) {
    function pp_log_mem_usage($label, $display_total = true)
    {
        if (!constant('PRESSPERMIT_DEBUG') || !defined('PRESSPERMIT_MEMORY_LOG') || !PRESSPERMIT_MEMORY_LOG)
            return;

        $total = $display_total ? " (" . memory_get_usage(true) . ")" : '';

        pp_errlog($label);
        pp_errlog(_pp_memory_new_usage() . $total);
        pp_errlog('');
    }
}

if (!function_exists('pp_dump')) {
    function pp_dump(&$var, $info = FALSE, $display_objects = true)
    {
        var_dump($var);
    }
}

if (!function_exists('do_dump')) {
    function do_pp_dump(&$var, $display_objects = true, $var_name = NULL, $indent = NULL, $reference = NULL)
    {
        var_dump($var);
    }
}

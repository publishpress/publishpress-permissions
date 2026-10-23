<?php // avoid bombing out if the actual debug file is not loaded
defined('ABSPATH') || exit;

function presspermit_editing_plugin()
{
}

if (!function_exists('pp_debug_echo')) {
    function pp_debug_echo($str)
    {
    }
}

if (!function_exists('pp_errlog')) {
    function pp_errlog($message, $line_break = true)
    {
    }
}

if (!function_exists('pp_backtrace_dump')) {
    function pp_backtrace_dump()
    {
    }
}

if (!function_exists('pp_log_mem_usage')) {
    function pp_log_mem_usage($label, $display_total = true)
    {
    }
}

if (!function_exists('pp_dump')) {
    function pp_dump(&$var, $info = FALSE, $display_objects = true)
    {
    }
}

<?php

defined('ABSPATH') || exit;

function presspermit_delete_option($option_basename, $args = [])
{
    return presspermit()->deleteOption($option_basename, $args);
}

function presspermit_update_option($option_basename, $option_val, $args = [])
{
    return presspermit()->updateOption($option_basename, $option_val, $args);
}

function presspermit_get_option($option_basename)
{
    return presspermit()->getOption($option_basename);
}

function presspermit_get_type_option($option_name, $object_type, $default_fallback = false)
{
    return presspermit()->getTypeOption($option_name, $object_type, $default_fallback);
}

function presspermit_is_administrator($user_id = false, $admin_type = 'content', $args = [])
{
    return presspermit()->isAdministrator($user_id, $admin_type, $args);
}

function presspermit_isUserAdministrator($user_id = false, $args = [])
{
    return presspermit()->isAdministrator($user_id, 'user', $args);
}

function presspermit_isContentAdministrator($user_id = false, $args = [])
{
    return presspermit()->isAdministrator($user_id, 'content', $args);
}

function presspermit_isUserUnfiltered($user_id = false, $args = [])
{
    return presspermit()->isAdministrator($user_id, 'unfiltered', $args);
}

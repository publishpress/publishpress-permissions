<?php

function sanitize_key($value)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}

function __($value)
{
    return $value;
}

function admin_url($path = '')
{
    return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function esc_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_attr($value)
{
    return esc_html($value);
}

function esc_url($value)
{
    return (string) $value;
}

function sanitize_html_class($value)
{
    return sanitize_key($value);
}

function add_query_arg($key, $value = null, $url = null)
{
    if (is_array($key)) {
        $url = (string) $value;
        $args = $key;
    } else {
        $args = [$key => $value];
        $url = (string) $url;
    }

    return $url . (false === strpos($url, '?') ? '?' : '&') . http_build_query($args);
}

function wp_json_encode($value)
{
    return json_encode($value);
}

function is_wp_error($value)
{
    return $value instanceof WP_Error;
}

class WP_Error
{
    public $code;
    public $message;
    public $data;

    public function __construct($code = '', $message = '', $data = null)
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_data()
    {
        return $this->data;
    }
}

function wp_remote_post($url, $args)
{
    $GLOBALS['ppw_last_http'] = [$url, $args];
    return $GLOBALS['ppw_http_response'];
}

function wp_remote_retrieve_response_code($response)
{
    return $response['status'];
}

function wp_remote_retrieve_header($response, $header)
{
    return isset($response['headers'][$header]) ? $response['headers'][$header] : '';
}

$GLOBALS['ppw_test_user_options'] = [];

function get_user_option($name, $userId)
{
    return isset($GLOBALS['ppw_test_user_options'][$userId][$name]) ? $GLOBALS['ppw_test_user_options'][$userId][$name] : false;
}

function update_user_option($userId, $name, $value)
{
    $GLOBALS['ppw_test_user_options'][$userId][$name] = $value;

    return true;
}

function delete_user_option($userId, $name)
{
    unset($GLOBALS['ppw_test_user_options'][$userId][$name]);

    return true;
}

function add_filter()
{
    return true;
}

function add_action()
{
    return true;
}

$GLOBALS['ppw_test_options'] = [];

function get_option($name, $default = false)
{
    return array_key_exists($name, $GLOBALS['ppw_test_options']) ? $GLOBALS['ppw_test_options'][$name] : $default;
}

function update_option($name, $value)
{
    $GLOBALS['ppw_test_options'][$name] = $value;

    return true;
}

function delete_option($name)
{
    unset($GLOBALS['ppw_test_options'][$name]);

    return true;
}

function wp_next_scheduled()
{
    return false;
}

function wp_schedule_single_event()
{
    return true;
}

function __return_false()
{
    return false;
}

function __return_null()
{
    return null;
}

require_once dirname(__DIR__) . '/src/VersionLoader.php';
require_once dirname(__DIR__) . '/src/autoload.php';

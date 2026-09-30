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

function __return_null()
{
    return null;
}

require_once dirname(__DIR__) . '/src/VersionLoader.php';
require_once dirname(__DIR__) . '/src/autoload.php';

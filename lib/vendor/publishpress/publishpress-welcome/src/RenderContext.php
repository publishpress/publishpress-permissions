<?php

namespace PublishPress\Welcome;

class RenderContext
{
    private $controller;
    private $config;

    public function __construct(WelcomeController $controller, ExperienceConfig $config)
    {
        $this->controller = $controller;
        $this->config = $config;
    }

    public function config()
    {
        return $this->config;
    }

    public function stepUrl($step)
    {
        return $this->controller->stepUrl($step);
    }

    public function stepCount()
    {
        return $this->controller->stepCount();
    }

    public function figure(array $asset, $alt = '', $pinCount = 0, $slug = '')
    {
        $url = isset($asset['url']) ? $asset['url'] : '';
        $path = isset($asset['path']) ? $asset['path'] : '';

        if ($url && $path && is_readable($path)) {
            $version = md5_file($path);

            if ($version) {
                $url = add_query_arg('ver', substr($version, 0, 12), $url);
            }
        }

        $classes = 'ppw-figure';
        if ($slug) {
            $classes .= ' ppw-figure-' . sanitize_html_class($slug);
        }

        printf('<div class="%s">', esc_attr($classes));

        if ($url) {
            printf('<img src="%s" alt="%s" />', esc_url($url), esc_attr($alt));
        } else {
            printf('<div class="ppw-figure-placeholder"><span>%s</span></div>', esc_html($alt));
        }

        for ($index = 1; $index <= (int) $pinCount; $index++) {
            printf('<span class="ppw-pin ppw-pin-%1$d">%1$d</span>', (int) $index);
        }

        echo '</div>';
    }
}

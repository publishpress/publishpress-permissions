<?php

namespace PublishPress\Welcome;

/**
 * Release highlights shown once after an update, on the plugin's own screens.
 */
class WhatsNew
{
    const MAX_ITEMS = 3;

    /**
     * Validate consumer data. Returns null when the feature is off or the data is unusable.
     *
     * Expected keys: version (string), title (string), items (array of title/body), url, url_label.
     *
     * @param mixed $data
     * @return array|null
     */
    public static function normalize($data)
    {
        if (! is_array($data) || empty($data['version']) || ! is_string($data['version']) || empty($data['title'])) {
            return null;
        }

        $version = preg_replace('/[^0-9a-zA-Z.\-+]/', '', $data['version']);
        $items = [];

        foreach (isset($data['items']) ? (array) $data['items'] : [] as $item) {
            if (is_array($item) && ! empty($item['title'])) {
                $items[] = ['title' => (string) $item['title'], 'body' => isset($item['body']) ? (string) $item['body'] : ''];
            }
        }

        $items = array_slice($items, 0, self::MAX_ITEMS);

        if ('' === $version || ! $items) {
            return null;
        }

        return [
            'version' => $version,
            'title' => (string) $data['title'],
            'items' => $items,
            'url' => isset($data['url']) ? (string) $data['url'] : '',
            'url_label' => isset($data['url_label']) ? (string) $data['url_label'] : '',
        ];
    }

    /**
     * @param string $seenVersion Last version the user dismissed or finished the guide at; empty when none.
     * @param string $version     Version the highlights describe.
     * @return bool
     */
    public static function isNewerThan($seenVersion, $version)
    {
        return version_compare($seenVersion === '' ? '0' : (string) $seenVersion, (string) $version, '<');
    }
}

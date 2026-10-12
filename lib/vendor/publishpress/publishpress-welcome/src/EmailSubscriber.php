<?php

namespace PublishPress\Welcome;

/**
 * Delivers a consented subscription to the configured destination.
 *
 * The default destination is the PublishPress connector. An empty or placeholder endpoint is a dry run.
 * Consumers can replace delivery with the "handler" option. A successful request means intake accepted,
 * not that MailerPress has confirmed the user's address.
 */
class EmailSubscriber
{
    const PLACEHOLDER_ENDPOINT = 'https://example.invalid/publishpress/v1/subscribe';
    const DEFAULT_ENDPOINT = 'https://connect.publishpress.com/v1/subscribe';

    /** @var array */
    private $settings;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param array $payload
     * @return true|\WP_Error
     */
    public function send(array $payload)
    {
        $settings = ExperienceConfig::mergeEmailCapture($this->settings);
        if (! empty($settings['configuration_error']) || empty($settings['enabled'])) {
            return new \WP_Error('publishpress_welcome_subscribe_failed', 'Email capture configuration is invalid or disabled.', ['http_status' => 400]);
        }
        if (! isset($payload['email']) || ! is_string($payload['email']) || true !== (isset($payload['consent']) ? $payload['consent'] : null)
            || ! isset($payload['consent_statement']) || $settings['consent_statement'] !== $payload['consent_statement']) {
            return new \WP_Error('publishpress_welcome_subscribe_failed', 'Subscription payload is missing valid email, consent or statement ID.', ['http_status' => 400]);
        }
        // Keep legacy client text/time and unknown filter values out of requests and retry storage.
        $payload = self::contractPayload($payload);

        if (! empty($this->settings['handler']) && is_callable($this->settings['handler'])) {
            $result = call_user_func($this->settings['handler'], $payload);

            return $this->normalize($result);
        }

        $endpoint = isset($this->settings['endpoint']) ? (string) $this->settings['endpoint'] : '';

        if ('' === $endpoint || self::PLACEHOLDER_ENDPOINT === $endpoint) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // The address is deliberately not logged.
                error_log('[publishpress-welcome] Dry run: no subscription endpoint is configured, nothing was sent.');
            }

            return true;
        }

        $response = wp_remote_post(
            $endpoint,
            [
                'timeout' => 5,
                'redirection' => 0,
                'limit_response_size' => 4096,
                'headers' => ['Content-Type' => 'application/json', 'User-Agent' => 'PublishPress-Welcome/1.0'],
                'body' => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300) {
            $retryAfter = wp_remote_retrieve_header($response, 'retry-after');
            $delay = 0;
            if (is_string($retryAfter) && strlen($retryAfter) <= 128 && in_array($code, [429, 503], true)) {
                $delay = ctype_digit($retryAfter) ? (float) $retryAfter : (float) strtotime($retryAfter) - time();
                $delay = (int) max(0, min(86400, $delay));
            }
            return new \WP_Error('publishpress_welcome_subscribe_failed', sprintf('Subscription endpoint returned HTTP %d.', $code), ['http_status' => $code, 'retry_after' => $delay]);
        }

        return true;
    }

    public static function contractPayload(array $payload)
    {
        return array_intersect_key($payload, array_flip(['email', 'consent', 'consent_statement', 'plugin', 'persona', 'locale', 'plan_claim']));
    }

    private function normalize($result)
    {
        if (true === $result) {
            return true;
        }

        if (is_wp_error($result)) {
            return $result;
        }

        return new \WP_Error('publishpress_welcome_subscribe_failed', 'The subscription handler reported a failure.');
    }
}

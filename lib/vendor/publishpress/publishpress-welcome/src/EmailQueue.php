<?php

namespace PublishPress\Welcome;

/**
 * Retries subscriptions whose delivery failed, so a temporary outage never loses a consented signup.
 *
 * Failed payloads are kept in one shared, non-autoloaded option and retried with a growing delay through
 * WP-Cron. Because a plugin may register its experience only in the admin, due items are also processed
 * (throttled) on admin requests. An item is deleted once delivered, after MAX_ATTEMPTS or after MAX_AGE.
 */
class EmailQueue
{
    const OPTION = 'publishpress_welcome_email_queue';
    const CRON_HOOK = 'publishpress_welcome_process_queue';
    const LOCK_TRANSIENT = 'publishpress_welcome_queue_lock';

    const MAX_ATTEMPTS = 5;
    const MAX_AGE = 604800;
    const MAX_ITEMS = 200;
    const BACKOFF = [300, 1800, 7200, 43200, 86400];

    /** @var array<string,callable> */
    private static $senders = [];

    /** @var bool */
    private static $hooked = false;

    /**
     * Register how to deliver for an experience. Items of unregistered experiences are left untouched.
     */
    public static function registerSender($experience, callable $sender)
    {
        self::$senders[(string) $experience] = $sender;

        if (self::$hooked) {
            return;
        }

        self::$hooked = true;

        add_action(self::CRON_HOOK, [__CLASS__, 'process']);
        add_action('admin_init', [__CLASS__, 'maybeProcess']);
    }

    /**
     * Queue a payload whose first delivery attempt has just failed.
     */
    public static function enqueue($experience, array $payload, $now = null, $result = null)
    {
        if (! self::retryable($result)) {
            return;
        }
        $now = null === $now ? time() : (int) $now;
        $queue = self::load();

        $queue[] = [
            'experience' => (string) $experience,
            'payload' => EmailSubscriber::contractPayload($payload),
            'attempts' => 1,
            'created' => $now,
            'next' => $now + self::retryDelay(1, $result),
        ];

        // Keep the newest entries if the queue ever grows unreasonably.
        $queue = array_slice($queue, -self::MAX_ITEMS);

        self::save($queue);
        self::schedule($queue);
    }

    /** @return int Seconds to wait after the given number of failed attempts. */
    public static function backoffSeconds($attempts)
    {
        $index = min(max((int) $attempts, 1), count(self::BACKOFF)) - 1;

        return self::BACKOFF[$index];
    }

    /** Permanent client errors cannot be fixed by retrying. */
    public static function retryable($result)
    {
        $data = is_wp_error($result) ? $result->get_error_data() : null;
        $status = is_array($data) && isset($data['http_status']) ? (int) $data['http_status'] : 0;
        return $status < 400 || $status >= 500 || in_array($status, [408, 429], true);
    }

    private static function retryDelay($attempts, $result)
    {
        $data = is_wp_error($result) ? $result->get_error_data() : null;
        $retry = is_array($data) && isset($data['retry_after']) ? (int) $data['retry_after'] : 0;
        return max(self::backoffSeconds($attempts), min(86400, max(0, $retry)));
    }

    /** @return array */
    public static function pending()
    {
        return self::load();
    }

    /** Throttled entry point for admin requests. */
    public static function maybeProcess()
    {
        if (! self::load() || get_transient(self::LOCK_TRANSIENT)) {
            return;
        }

        set_transient(self::LOCK_TRANSIENT, 1, 300);
        self::process();
    }

    /**
     * Attempt every due item once.
     *
     * @param int|null $now Unix time, injectable for tests.
     * @return int Number of items delivered.
     */
    public static function process($now = null)
    {
        $now = null === $now ? time() : (int) $now;
        $queue = self::load();
        $kept = [];
        $delivered = 0;

        foreach ($queue as $item) {
            if (($now - (int) $item['created']) >= self::MAX_AGE || (int) $item['attempts'] >= self::MAX_ATTEMPTS) {
                continue;
            }

            if (! isset(self::$senders[$item['experience']]) || (int) $item['next'] > $now) {
                $kept[] = $item;
                continue;
            }

            $result = call_user_func(self::$senders[$item['experience']], EmailSubscriber::contractPayload($item['payload']));

            if (true === $result) {
                $delivered++;
                continue;
            }

            if (! self::retryable($result)) {
                continue;
            }

            $item['attempts'] = (int) $item['attempts'] + 1;

            if ($item['attempts'] >= self::MAX_ATTEMPTS) {
                continue;
            }

            $item['payload'] = EmailSubscriber::contractPayload($item['payload']);
            $item['next'] = $now + self::retryDelay($item['attempts'], $result);
            $kept[] = $item;
        }

        self::save($kept);
        self::schedule($kept);

        return $delivered;
    }

    /** @return array Queued payloads that belong to the address. */
    public static function itemsForEmail($email)
    {
        return array_values(array_filter(self::load(), function ($item) use ($email) {
            return isset($item['payload']['email']) && 0 === strcasecmp((string) $item['payload']['email'], (string) $email);
        }));
    }

    /** @return bool Whether anything was removed. */
    public static function eraseByEmail($email)
    {
        $queue = self::load();
        $kept = array_values(array_filter($queue, function ($item) use ($email) {
            return ! (isset($item['payload']['email']) && 0 === strcasecmp((string) $item['payload']['email'], (string) $email));
        }));

        if (count($kept) === count($queue)) {
            return false;
        }

        self::save($kept);

        return true;
    }

    private static function schedule(array $queue)
    {
        if (! $queue) {
            return;
        }

        $next = min(array_map(function ($item) {
            return (int) $item['next'];
        }, $queue));

        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(max($next, time() + 60), self::CRON_HOOK);
        }
    }

    private static function load()
    {
        $queue = get_option(self::OPTION, []);

        return is_array($queue) ? $queue : [];
    }

    private static function save(array $queue)
    {
        if (! $queue) {
            delete_option(self::OPTION);
            return;
        }

        update_option(self::OPTION, array_values($queue), false);
    }
}

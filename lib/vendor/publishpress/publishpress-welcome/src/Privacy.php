<?php

namespace PublishPress\Welcome;

/**
 * Integrates the per-user data this library stores with WordPress's personal data export and erasure tools.
 */
class Privacy
{
    /** Per-experience user option suffixes, see WelcomeController::userOption(). */
    const EXPERIENCE_SUFFIXES = ['step', 'complete', 'notice_dismissed', 'card_dismissed', 'notice_first_seen', 'whats_new_seen', 'whats_new_first_seen'];

    /** @var array<string,string> Experience ID => user option prefix. */
    private static $experiences = [];

    /** @var bool */
    private static $hooked = false;

    /**
     * Register an experience so its stored data is included in exports and erasures.
     */
    public static function track($id, $userOptionPrefix)
    {
        self::$experiences[(string) $id] = (string) $userOptionPrefix;

        if (self::$hooked) {
            return;
        }

        self::$hooked = true;

        add_filter('wp_privacy_personal_data_exporters', [__CLASS__, 'registerExporter']);
        add_filter('wp_privacy_personal_data_erasers', [__CLASS__, 'registerEraser']);
        add_action('admin_init', [__CLASS__, 'addPolicyContent']);
    }

    public static function registerExporter(array $exporters)
    {
        $exporters['publishpress-welcome'] = [
            'exporter_friendly_name' => __('PublishPress welcome guide', 'publishpress-welcome'),
            'callback' => [__CLASS__, 'exporter'],
        ];

        return $exporters;
    }

    public static function registerEraser(array $erasers)
    {
        $erasers['publishpress-welcome'] = [
            'eraser_friendly_name' => __('PublishPress welcome guide', 'publishpress-welcome'),
            'callback' => [__CLASS__, 'eraser'],
        ];

        return $erasers;
    }

    public static function exporter($emailAddress)
    {
        $user = get_user_by('email', $emailAddress);
        $items = $user ? self::exportItems((int) $user->ID) : [];

        foreach (EmailQueue::itemsForEmail($emailAddress) as $index => $queued) {
            $items[] = [
                'group_id' => 'publishpress-welcome',
                'group_label' => __('PublishPress welcome guide', 'publishpress-welcome'),
                'item_id' => 'publishpress-welcome-pending-' . $index,
                'data' => [
                    ['name' => __('Pending subscription (delivery will be retried)', 'publishpress-welcome'), 'value' => (string) $queued['payload']['email']],
                ],
            ];
        }

        return ['data' => $items, 'done' => true];
    }

    public static function eraser($emailAddress)
    {
        $user = get_user_by('email', $emailAddress);
        $removed = $user ? self::erase((int) $user->ID) : false;
        $removed = EmailQueue::eraseByEmail($emailAddress) || $removed;

        return ['items_removed' => $removed, 'items_retained' => false, 'messages' => [], 'done' => true];
    }

    /**
     * @param int $userId
     * @return array Export groups in the format WordPress expects.
     */
    public static function exportItems($userId)
    {
        $items = [];
        $shared = [];

        $status = SharedProfile::status($userId);
        if ('' !== $status) {
            $shared[] = ['name' => __('Email subscription choice', 'publishpress-welcome'), 'value' => $status];
        }

        $persona = SharedProfile::persona($userId);
        if ('' !== $persona) {
            $shared[] = ['name' => __('Answer to "what best describes you?"', 'publishpress-welcome'), 'value' => $persona];
        }

        if ($shared) {
            $items[] = [
                'group_id' => 'publishpress-welcome',
                'group_label' => __('PublishPress welcome guide', 'publishpress-welcome'),
                'item_id' => 'publishpress-welcome-profile-' . $userId,
                'data' => $shared,
            ];
        }

        foreach (self::$experiences as $id => $prefix) {
            $data = [];

            foreach (self::EXPERIENCE_SUFFIXES as $suffix) {
                $value = get_user_option($prefix . '_' . $suffix, $userId);

                if (false !== $value && '' !== $value && null !== $value) {
                    $data[] = ['name' => $id . ' ' . $suffix, 'value' => (string) $value];
                }
            }

            if ($data) {
                $items[] = [
                    'group_id' => 'publishpress-welcome',
                    'group_label' => __('PublishPress welcome guide', 'publishpress-welcome'),
                    'item_id' => 'publishpress-welcome-' . $id . '-' . $userId,
                    'data' => $data,
                ];
            }
        }

        return $items;
    }

    /**
     * Remove everything this library stores for the user.
     *
     * @param int $userId
     * @return bool Whether anything was removed.
     */
    public static function erase($userId)
    {
        $removed = false;

        foreach ([SharedProfile::STATUS_OPTION, SharedProfile::PERSONA_OPTION, SharedProfile::DECLINED_AT_OPTION] as $option) {
            if (false !== get_user_option($option, $userId)) {
                delete_user_option($userId, $option);
                $removed = true;
            }
        }

        foreach (self::$experiences as $prefix) {
            foreach (self::EXPERIENCE_SUFFIXES as $suffix) {
                $option = $prefix . '_' . $suffix;

                if (false !== get_user_option($option, $userId)) {
                    delete_user_option($userId, $option);
                    $removed = true;
                }
            }
        }

        return $removed;
    }

    public static function addPolicyContent()
    {
        if (! function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = '<p class="privacy-policy-tutorial">' . esc_html__('Suggested text for the optional PublishPress welcome guide:', 'publishpress-welcome') . '</p>'
            . '<p>' . esc_html__('If you opt in on the welcome guide, we receive your email address, your answer to "what best describes you?" and an identifier for the consent statement shown to you. PublishPress records the receive time and asks you to confirm your email before sending tips and offers. Nothing is sent unless you tick the consent box and choose Subscribe. The guide also stores your progress and your choice on this site; both are included in the personal data export and removed by the erasure tool, and you can unsubscribe at any time.', 'publishpress-welcome') . '</p>';

        wp_add_privacy_policy_content(__('PublishPress welcome guide', 'publishpress-welcome'), $content);
    }
}

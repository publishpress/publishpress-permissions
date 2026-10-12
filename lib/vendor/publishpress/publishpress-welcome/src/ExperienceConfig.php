<?php

namespace PublishPress\Welcome;

use InvalidArgumentException;

class ExperienceConfig
{
    const MODE_SCREEN = 'screen';
    const MODE_FULLSCREEN = 'fullscreen';
    const DEFAULT_ACCENT = '#655997';

    /** @var array */
    private $values;

    public function __construct(array $values)
    {
        foreach (['id', 'page_slug', 'parent_slug', 'capability', 'page_title'] as $required) {
            if (empty($values[$required]) || ! is_string($values[$required])) {
                throw new InvalidArgumentException(sprintf('Welcome experience requires a non-empty "%s" value.', $required));
            }
        }

        $id = sanitize_key($values['id']);

        if ($id !== $values['id']) {
            throw new InvalidArgumentException('Welcome experience ID must already be a sanitized key.');
        }

        $defaults = [
            'menu_title' => $values['page_title'],
            'show_in_menu' => false,
            'mode' => self::MODE_SCREEN,
            'allow_mode_override' => false,
            'brand_name' => '',
            'logo' => [],
            'accent_color' => self::DEFAULT_ACCENT,
            'styles' => [],
            'enrollment_option' => $id . '_welcome_enrolled',
            'redirect_option' => $id . '_welcome_redirect_pending',
            'user_option_prefix' => $id . '_welcome',
            'notice_enabled' => true,
            'notice_icon_url' => '',
            // Screens (?page= slugs) the notice may appear on. Empty means the checklist pages, i.e. the plugin's own screens.
            'notice_pages' => [],
            // Also allow the main Dashboard. Off by default: WordPress.org asks that promotional notices stay limited in scope.
            'notice_on_dashboard' => false,
            // The notice stops appearing this many days after it was first shown. 0 keeps it until dismissed.
            'notice_days' => 14,
            // Release highlights shown once per version: ['version', 'title', 'items' => [['title', 'body']], 'url', 'url_label'].
            'whats_new' => false,
            'email_capture' => [],
            'notice_title' => __('Welcome! Would you like a quick tour?', 'publishpress-welcome'),
            'notice_body' => __('We will show you where the important settings are. Nothing is changed while you read.', 'publishpress-welcome'),
            'checklist_enabled' => true,
            'checklist_pages' => [],
            'checklist_title' => __('Finish setting up the plugin', 'publishpress-welcome'),
            'checklist_complete_title' => __('Setup is complete', 'publishpress-welcome'),
            'exit_url' => admin_url(),
            'labels' => [],
        ];

        $values['id'] = $id;
        $values['page_slug'] = sanitize_key($values['page_slug']);
        $values['labels'] = array_merge($this->defaultLabels(), isset($values['labels']) ? $values['labels'] : []);
        $values['checklist_pages'] = array_map('sanitize_key', isset($values['checklist_pages']) ? (array) $values['checklist_pages'] : []);
        $values['notice_pages'] = array_map('sanitize_key', isset($values['notice_pages']) ? (array) $values['notice_pages'] : []);
        if (! $values['notice_pages']) {
            $values['notice_pages'] = $values['checklist_pages'];
        }
        $values['styles'] = isset($values['styles']) ? (array) $values['styles'] : [];
        $values['mode'] = self::normalizeMode(isset($values['mode']) ? $values['mode'] : self::MODE_SCREEN);
        $values['logo'] = isset($values['logo']) ? (array) $values['logo'] : [];
        $values['email_capture'] = self::mergeEmailCapture(isset($values['email_capture']) ? $values['email_capture'] : []);

        $this->values = array_merge($defaults, $values);
    }

    /** @return mixed */
    public function get($key)
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : null;
    }

    /** @return string */
    public function label($key)
    {
        $labels = $this->values['labels'];

        return isset($labels[$key]) ? (string) $labels[$key] : '';
    }

    /**
     * Accepts only the two supported display modes and falls back to the in-screen layout.
     *
     * @return string
     */
    public static function normalizeMode($mode)
    {
        return self::MODE_FULLSCREEN === $mode ? self::MODE_FULLSCREEN : self::MODE_SCREEN;
    }

    /**
     * CSS custom properties derived from the accent colour. Invalid colours fall back to the default.
     *
     * @return string
     */
    public function styleVariables()
    {
        $hex = self::normalizeHex($this->values['accent_color']);
        list($r, $g, $b) = self::hexToRgb($hex);

        $dark = sprintf('#%02x%02x%02x', (int) ($r * .78), (int) ($g * .78), (int) ($b * .78));

        return sprintf('--ppw-accent:%1$s;--ppw-accent-rgb:%2$d,%3$d,%4$d;--ppw-accent-dark:%5$s;', $hex, $r, $g, $b, $dark);
    }

    /**
     * Defaults for the optional email step. Pass `false` to disable it, or an array of overrides.
     * A consumer can also adjust the final array through the "publishpress_welcome_{id}_email_capture" filter.
     *
     * @param array|bool $overrides
     * @return array
     */
    public static function mergeEmailCapture($overrides)
    {
        if (false === $overrides) {
            $overrides = ['enabled' => false];
        }

        $defaults = [
            'enabled' => true,
            // "before_last", "after_first", or a 1-based step position.
            'position' => 'before_last',
            // Days before asking again after "No thanks". 0 means never ask again (in any bundled plugin).
            'decline_snooze_days' => 0,
            // Callable receiving the payload array; returns true, false or WP_Error. Replaces the HTTP request.
            'handler' => null,
            'endpoint' => EmailSubscriber::DEFAULT_ENDPOINT,
            'consent_statement' => 'welcome-email-v1',
            'recipient_name' => 'PublishPress',
            'privacy_url' => '',
            'personas' => [
                'site-owner' => __('Site owner', 'publishpress-welcome'),
                'content-team' => __('Writer or editor', 'publishpress-welcome'),
                'agency' => __('Agency or freelancer', 'publishpress-welcome'),
                'developer' => __('Developer', 'publishpress-welcome'),
                'other' => __('Something else', 'publishpress-welcome'),
            ],
            'copy' => [
                'title' => __('Stay in the loop', 'publishpress-welcome'),
                'lede' => __('Tell us a little about how you use WordPress and we will send tips and offers that fit. This is optional, and everything works if you skip it.', 'publishpress-welcome'),
                'persona_label' => __('What best describes you?', 'publishpress-welcome'),
                'email_label' => __('Email address', 'publishpress-welcome'),
                'consent_label' => __('Yes, send me tips, product news and offers tailored to how I use this plugin. I can unsubscribe at any time.', 'publishpress-welcome'),
                'privacy_label' => __('Privacy policy', 'publishpress-welcome'),
                'disclosure' => __('Your email address and your answer are sent to %s when you subscribe. Nothing is sent if you skip.', 'publishpress-welcome'),
                'submit' => __('Subscribe', 'publishpress-welcome'),
                'decline' => __('No thanks', 'publishpress-welcome'),
                'continue' => __('Continue', 'publishpress-welcome'),
                'success_title' => __('Check your inbox', 'publishpress-welcome'),
                'success_body' => __('Please confirm your email using the link from PublishPress before receiving tips and offers.', 'publishpress-welcome'),
                'confirmation_note' => __('Check your inbox for a confirmation link after subscribing. Confirm your email before receiving tips and offers.', 'publishpress-welcome'),
                'error_delivery' => __('We could not accept this subscription. Please try again later or choose "No thanks".', 'publishpress-welcome'),
                'error_email' => __('Please enter a valid email address.', 'publishpress-welcome'),
                'error_consent' => __('Please tick the box to confirm you want to hear from us, or choose "No thanks".', 'publishpress-welcome'),
            ],
        ];

        $overrides = is_array($overrides) ? $overrides : [];
        $merged = array_merge($defaults, $overrides);
        $merged['copy'] = array_merge($defaults['copy'], isset($overrides['copy']) ? (array) $overrides['copy'] : []);
        $merged['personas'] = isset($overrides['personas']) ? (array) $overrides['personas'] : $defaults['personas'];

        $statement = $merged['consent_statement'];
        $customWording = $merged['copy']['consent_label'] !== $defaults['copy']['consent_label'];
        if (! is_string($statement) || ! preg_match('/^[a-z0-9-]{1,32}$/D', $statement)
            || ($customWording && 'welcome-email-v1' === $statement)) {
            $merged['enabled'] = false;
            $merged['configuration_error'] = 'invalid_consent_statement';
        }

        return $merged;
    }

    /** @return string */
    private static function normalizeHex($color)
    {
        $color = is_string($color) ? strtolower(trim($color)) : '';

        if (! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $color)) {
            return self::DEFAULT_ACCENT;
        }

        if (4 === strlen($color)) {
            $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
        }

        return $color;
    }

    /** @return int[] */
    private static function hexToRgb($hex)
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    /** @return array */
    public function all()
    {
        return $this->values;
    }

    private function defaultLabels()
    {
        return [
            'back' => __('Back', 'publishpress-welcome'),
            'next' => __('Next', 'publishpress-welcome'),
            'skip' => __('Skip', 'publishpress-welcome'),
            'exit' => __('Exit guide', 'publishpress-welcome'),
            'step_nav' => __('Guide steps', 'publishpress-welcome'),
            'guide_nav' => __('Guide navigation', 'publishpress-welcome'),
            'skip_content' => __('Skip to the guide content', 'publishpress-welcome'),
            'new_tab' => __('(opens in a new tab)', 'publishpress-welcome'),
            'task_done' => __('Completed:', 'publishpress-welcome'),
            'task_todo' => __('Not completed:', 'publishpress-welcome'),
            'changelog' => __('See the full changelog', 'publishpress-welcome'),
            'finish' => __('Finish', 'publishpress-welcome'),
            'start' => __('Start the tour', 'publishpress-welcome'),
            'continue' => __('Continue the tour', 'publishpress-welcome'),
            'dismiss' => __('Dismiss', 'publishpress-welcome'),
            'hide' => __('Hide', 'publishpress-welcome'),
            'do_it' => __('Do it', 'publishpress-welcome'),
            'view' => __('View', 'publishpress-welcome'),
            'replay' => __('Replay the guide', 'publishpress-welcome'),
            'step_format' => __('Step %1$s of %2$s', 'publishpress-welcome'),
            'reassurance' => __('This guide does not change any setting on your site.', 'publishpress-welcome'),
        ];
    }
}

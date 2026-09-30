<?php

namespace PublishPress\Welcome;

use InvalidArgumentException;

class ExperienceConfig
{
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
            'accent_color' => '#655997',
            'styles' => [],
            'enrollment_option' => $id . '_welcome_enrolled',
            'redirect_option' => $id . '_welcome_redirect_pending',
            'user_option_prefix' => $id . '_welcome',
            'notice_enabled' => true,
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
        $values['styles'] = isset($values['styles']) ? (array) $values['styles'] : [];

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

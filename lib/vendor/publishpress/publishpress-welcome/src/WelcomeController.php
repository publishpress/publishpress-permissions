<?php

namespace PublishPress\Welcome;

use InvalidArgumentException;
use PublishPress\Welcome\Contract\ChecklistTaskInterface;
use PublishPress\Welcome\Contract\StepInterface;
use PublishPress\Welcome\Step\EmailCaptureStep;

class WelcomeController
{
    /** @var array<string,bool> */
    private static $registeredExperiences = [];

    /** @var ExperienceConfig */
    private $config;

    /** @var StepInterface[] */
    private $steps;

    /** @var ChecklistTaskInterface[] */
    private $tasks;

    /** @var array|null Resolved email step settings; null when the step is disabled. */
    private $emailSettings = null;

    /** @var array|null Last rejected email submission (error and the values to redisplay). */
    private $submission = null;

    public function __construct(ExperienceConfig $config, array $steps, array $tasks = [])
    {
        if (! $steps) {
            throw new InvalidArgumentException('Welcome experience requires at least one step.');
        }

        foreach ($steps as $step) {
            if (! $step instanceof StepInterface) {
                throw new InvalidArgumentException('Every welcome step must implement StepInterface.');
            }
        }

        foreach ($tasks as $task) {
            if (! $task instanceof ChecklistTaskInterface) {
                throw new InvalidArgumentException('Every checklist task must implement ChecklistTaskInterface.');
            }
        }

        $this->config = $config;
        $this->steps = array_values($steps);
        $this->tasks = array_values($tasks);
    }

    public function register()
    {
        $id = $this->config->get('id');

        if (isset(self::$registeredExperiences[$id])) {
            _doing_it_wrong(__METHOD__, esc_html(sprintf('Welcome experience "%s" is already registered.', $id)), esc_html(PUBLISHPRESS_WELCOME_VERSION));
            return false;
        }

        $steps = apply_filters('publishpress_welcome_' . $id . '_steps', $this->steps);
        if (empty($steps)) {
            _doing_it_wrong(__METHOD__, esc_html__('A welcome experience must retain at least one step.', 'publishpress-welcome'), esc_html(PUBLISHPRESS_WELCOME_VERSION));
            return false;
        }

        foreach ((array) $steps as $step) {
            if (! $step instanceof StepInterface) {
                _doing_it_wrong(__METHOD__, esc_html__('Filtered welcome steps must implement StepInterface.', 'publishpress-welcome'), esc_html(PUBLISHPRESS_WELCOME_VERSION));
                return false;
            }
        }
        $this->steps = $this->withEmailCapture(array_values($steps));

        self::$registeredExperiences[$id] = true;

        Privacy::track($id, $this->config->get('user_option_prefix'));

        add_action('admin_menu', [$this, 'registerAdminPage'], 99);
        add_action('admin_init', [$this, 'handleExit'], 5);
        add_action('admin_init', [$this, 'handleDismissal']);
        add_action('admin_init', [$this, 'handleEmailCapture']);
        add_action('admin_init', [$this, 'maybeRedirectAfterActivation'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('admin_body_class', [$this, 'filterBodyClass']);
        add_filter('debug_information', [$this, 'addSiteHealthInfo']);
        add_action('admin_notices', [$this, 'renderNotice']);
        add_action('admin_notices', [$this, 'renderWhatsNew'], 15);
        add_action('admin_notices', [$this, 'renderChecklist'], 20);

        return true;
    }

    public function registerAdminPage()
    {
        // A null parent registers a page that WordPress can route to but never lists in the menu.
        // Removing a listed submenu instead would make WordPress deny access to the page.
        add_submenu_page(
            $this->config->get('show_in_menu') ? $this->config->get('parent_slug') : null,
            $this->config->get('page_title'),
            $this->config->get('menu_title'),
            $this->config->get('capability'),
            $this->config->get('page_slug'),
            [$this, 'render']
        );
    }

    public function enqueueAssets()
    {
        if (! $this->isWelcomePage() && ! $this->noticeIsDue() && ! $this->checklistIsDue() && ! $this->whatsNewIsDue()) {
            return;
        }

        wp_enqueue_style(
            'publishpress-welcome',
            plugins_url('assets/css/welcome.css', PUBLISHPRESS_WELCOME_ROOT_FILE),
            [],
            PUBLISHPRESS_WELCOME_VERSION
        );

        foreach ($this->config->get('styles') as $style) {
            if (empty($style['handle']) || empty($style['url'])) {
                continue;
            }

            wp_enqueue_style(
                sanitize_key($style['handle']),
                esc_url_raw($style['url']),
                isset($style['dependencies']) ? (array) $style['dependencies'] : ['publishpress-welcome'],
                isset($style['version']) ? $style['version'] : null
            );
        }
    }

    /**
     * The layout in use for the current request: "screen" (inside the admin chrome) or "fullscreen".
     *
     * @return string
     */
    public function mode()
    {
        $mode = ExperienceConfig::normalizeMode($this->config->get('mode'));

        if ($this->config->get('allow_mode_override') && isset($_GET['ppw_mode'])) {
            $mode = ExperienceConfig::normalizeMode(sanitize_key(wp_unslash($_GET['ppw_mode'])));
        }

        return $mode;
    }

    public function filterBodyClass($classes)
    {
        if ($this->isWelcomePage()) {
            $classes .= ' ppw-mode-' . $this->mode();
            $classes .= ExperienceConfig::MODE_FULLSCREEN === $this->mode() ? ' ppw-is-fullscreen' : '';
        }

        return $classes;
    }

    public function render()
    {
        if (! current_user_can($this->config->get('capability'))) {
            wp_die(esc_html__('You are not allowed to access this page.', 'publishpress-welcome'));
        }

        $stepNumber = isset($_GET['ppw_step']) ? absint(wp_unslash($_GET['ppw_step'])) : 1;
        $total = count($this->steps);

        if ($stepNumber < 1 || $stepNumber > $total) {
            $stepNumber = 1;
        }

        $this->rememberStep($stepNumber);
        Stats::increment($this->config->get('id'), Stats::VIEWS, $this->steps[$stepNumber - 1]->getId());

        $mode = $this->mode();
        $fullscreen = ExperienceConfig::MODE_FULLSCREEN === $mode;
        $context = new RenderContext($this, $this->config);
        $step = $this->steps[$stepNumber - 1];
        $percent = (int) round(($stepNumber / $total) * 100);
        $isLast = $stepNumber === $total;
        $ownNavigation = method_exists($step, 'hasOwnNavigation') && $step->hasOwnNavigation();
        ?>
        <div class="<?php echo esc_attr($fullscreen ? 'ppw-root ppw-mode-fullscreen' : 'wrap ppw-root ppw-mode-screen'); ?>" style="<?php echo esc_attr($this->config->styleVariables()); ?>">
            <a class="ppw-skip-link" href="#ppw-main"><?php echo esc_html($this->config->label('skip_content')); ?></a>
            <div class="ppw-frame">
                <div class="ppw-topbar">
                    <?php $this->renderBrand(); ?>
                    <nav class="ppw-steps" aria-label="<?php echo esc_attr($this->config->label('step_nav')); ?>">
                        <?php for ($index = 1; $index <= $total; $index++) : ?>
                            <a class="ppw-step-dot<?php echo $index < $stepNumber ? ' is-done' : ''; ?><?php echo $index === $stepNumber ? ' is-current' : ''; ?>"
                               href="<?php echo esc_url($this->stepUrl($index)); ?>"
                               <?php echo $index === $stepNumber ? 'aria-current="step"' : ''; ?>
                               aria-label="<?php echo esc_attr(sprintf($this->config->label('step_format'), number_format_i18n($index), number_format_i18n($total))); ?>"></a>
                        <?php endfor; ?>
                    </nav>
                    <span class="ppw-step-label">
                        <?php printf(esc_html($this->config->label('step_format')), esc_html(number_format_i18n($stepNumber)), esc_html(number_format_i18n($total))); ?>
                    </span>
                    <a class="ppw-exit" href="<?php echo esc_url($this->exitUrl($stepNumber)); ?>">
                        <span class="ppw-exit-label"><?php echo esc_html($this->config->label('exit')); ?></span>
                        <span class="ppw-exit-icon" aria-hidden="true">&times;</span>
                    </a>
                </div>
                <div class="ppw-bar" role="progressbar" aria-label="<?php echo esc_attr($this->config->get('page_title')); ?>" aria-valuetext="<?php echo esc_attr(sprintf($this->config->label('step_format'), number_format_i18n($stepNumber), number_format_i18n($total))); ?>" aria-valuemin="1" aria-valuemax="<?php echo esc_attr($total); ?>" aria-valuenow="<?php echo esc_attr($stepNumber); ?>">
                    <span class="ppw-bar-fill" style="width:<?php echo esc_attr($percent); ?>%"></span>
                </div>
                <div class="ppw-stage" id="ppw-main" tabindex="-1" role="region" aria-label="<?php echo esc_attr($this->config->get('page_title')); ?>">
                    <div class="ppw-body ppw-step-<?php echo esc_attr($step->getId()); ?>">
                        <?php $step->render($context); ?>
                    </div>
                </div>
                <nav class="ppw-navigation" aria-label="<?php echo esc_attr($this->config->label('guide_nav')); ?>">
                    <div class="ppw-nav-start">
                        <?php if ($stepNumber > 1) : ?>
                            <a class="ppw-back" href="<?php echo esc_url($this->stepUrl($stepNumber - 1)); ?>"><?php echo esc_html($this->config->label('back')); ?></a>
                        <?php endif; ?>
                    </div>
                    <?php if ($this->config->label('reassurance')) : ?>
                        <p class="ppw-reassurance"><?php echo esc_html($this->config->label('reassurance')); ?></p>
                    <?php endif; ?>
                    <div class="ppw-nav-end">
                        <?php if (! $isLast) : ?>
                            <a class="ppw-skip" href="<?php echo esc_url($this->exitUrl($stepNumber)); ?>"><?php echo esc_html($this->config->label('skip')); ?></a>
                            <?php if (! $ownNavigation) : ?>
                                <a class="ppw-btn ppw-btn-primary" href="<?php echo esc_url($this->stepUrl($stepNumber + 1)); ?>"><?php echo esc_html($this->config->label('next')); ?></a>
                            <?php endif; ?>
                        <?php else : ?>
                            <a class="ppw-btn ppw-btn-primary" href="<?php echo esc_url($this->config->get('exit_url')); ?>"><?php echo esc_html($this->config->label('finish')); ?></a>
                        <?php endif; ?>
                    </div>
                </nav>
            </div>
        </div>
        <?php
    }

    private function renderBrand()
    {
        $logo = $this->config->get('logo');
        $name = (string) $this->config->get('brand_name');

        echo '<div class="ppw-brand">';

        if (! empty($logo['url'])) {
            printf(
                '<img class="ppw-brand-logo" src="%s" alt="%s" />',
                esc_url($logo['url']),
                esc_attr(isset($logo['alt']) ? $logo['alt'] : $name)
            );
        } elseif (! empty($logo['dashicon'])) {
            printf('<span class="ppw-brand-icon dashicons %s" aria-hidden="true"></span>', esc_attr(sanitize_html_class($logo['dashicon'])));
        }

        if ($name && (empty($logo['url']) || ! empty($logo['show_name']))) {
            printf('<span class="ppw-brand-name">%s</span>', esc_html($name));
        }

        echo '</div>';
    }

    public function stepUrl($step = 1)
    {
        $args = ['page' => $this->config->get('page_slug'), 'ppw_step' => (int) $step];

        if ($this->config->get('allow_mode_override') && isset($_GET['ppw_mode'])) {
            $args['ppw_mode'] = $this->mode();
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * Insert the optional email step according to the (filterable) "email_capture" settings.
     *
     * @param StepInterface[] $steps
     * @return StepInterface[]
     */
    private function withEmailCapture(array $steps)
    {
        $settings = apply_filters('publishpress_welcome_' . $this->config->get('id') . '_email_capture', $this->config->get('email_capture'));
        $settings = ExperienceConfig::mergeEmailCapture($settings);

        if (empty($settings['enabled'])) {
            $this->emailSettings = null;
            return $steps;
        }

        // The choice is shared by every bundled plugin, so a user who already subscribed or declined
        // anywhere on this site is never asked again. Register on "init" so the current user is known.
        $userId = get_current_user_id();
        if ($userId && ! SharedProfile::shouldAsk($userId, (int) $settings['decline_snooze_days'])) {
            $this->emailSettings = null;
            return $steps;
        }

        $this->emailSettings = $settings;
        $this->registerQueueSender();
        $count = count($steps);

        if ('after_first' === $settings['position']) {
            $index = min(1, $count);
        } elseif (is_numeric($settings['position'])) {
            $index = max(0, min($count, (int) $settings['position'] - 1));
        } else {
            $index = $count > 1 ? $count - 1 : $count;
        }

        array_splice($steps, $index, 0, [new EmailCaptureStep($settings)]);

        return $steps;
    }

    private function registerQueueSender()
    {
        $settings = $this->emailSettings;

        EmailQueue::registerSender(
            $this->config->get('id'),
            function (array $payload) use ($settings) {
                return (new EmailSubscriber($settings))->send($payload);
            }
        );
    }

    private function emailStepNumber()
    {
        if (! $this->emailSettings) {
            return 0;
        }

        foreach ($this->steps as $index => $step) {
            if ($step instanceof EmailCaptureStep) {
                return $index + 1;
            }
        }

        return 0;
    }

    /**
     * State the email step needs to render: saved status, any validation error and the form values.
     *
     * @return array
     */
    public function emailCaptureState()
    {
        $number = $this->emailStepNumber();
        $user = wp_get_current_user();
        $values = $this->submission ? $this->submission['values'] : [
            'email' => $user->user_email,
            'persona' => SharedProfile::persona($user->ID),
            'consent' => false,
        ];

        return [
            'status' => SharedProfile::status($user->ID),
            'error' => $this->submission ? $this->submission['error'] : '',
            'values' => $values,
            'action_url' => $this->stepUrl($number),
            'next_url' => $this->stepUrl(min($number + 1, count($this->steps))),
            'nonce_action' => $this->emailNonceAction(),
        ];
    }

    public function handleEmailCapture()
    {
        if (! $this->emailSettings || ! $this->isWelcomePage()) {
            return;
        }

        if (! isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }

        $action = isset($_POST['ppw_email_action']) ? sanitize_key(wp_unslash($_POST['ppw_email_action'])) : '';

        if (! in_array($action, ['subscribe', 'decline'], true) || ! current_user_can($this->config->get('capability'))) {
            return;
        }

        check_admin_referer($this->emailNonceAction());

        $userId = get_current_user_id();

        // Once the choice is saved the step is no longer offered, so the following step takes its number.
        $nextUrl = $this->stepUrl(min($this->emailStepNumber(), count($this->steps) - 1));

        if ('decline' === $action) {
            SharedProfile::recordDeclined($userId);
            Stats::increment($this->config->get('id'), Stats::COUNTERS, Stats::EMAIL_DECLINED);
            wp_safe_redirect($nextUrl);
            exit;
        }

        $copy = $this->emailSettings['copy'];
        $email = isset($_POST['ppw_email']) ? sanitize_email(wp_unslash($_POST['ppw_email'])) : '';
        $persona = isset($_POST['ppw_persona']) ? sanitize_key(wp_unslash($_POST['ppw_persona'])) : '';
        $consent = ! empty($_POST['ppw_consent']);

        if (! array_key_exists($persona, $this->emailSettings['personas'])) {
            $persona = '';
        }

        $values = ['email' => $email, 'persona' => $persona, 'consent' => $consent];

        if (! is_email($email)) {
            $this->submission = ['error' => $copy['error_email'], 'values' => $values];
            return;
        }

        if (! $consent) {
            $this->submission = ['error' => $copy['error_consent'], 'values' => $values];
            return;
        }

        $payload = apply_filters(
            'publishpress_welcome_' . $this->config->get('id') . '_subscribe_payload',
            [
                'plugin' => $this->config->get('id'),
                'email' => $email,
                'persona' => $persona,
                'consent' => true,
                'consent_statement' => $this->emailSettings['consent_statement'],
                'locale' => get_user_locale(),
            ],
            $this->emailSettings
        );

        $result = (new EmailSubscriber($this->emailSettings))->send((array) $payload);

        if (true !== $result && ! EmailQueue::retryable($result)) {
            $this->submission = ['error' => $copy['error_delivery'], 'values' => $values];
            return;
        }

        // The user has consented, so a failed delivery is queued and retried in the background rather than
        // shown as an error they cannot fix.
        if (true !== $result && EmailQueue::retryable($result)) {
            EmailQueue::enqueue($this->config->get('id'), (array) $payload, null, $result);
        }

        // The persona is only stored locally after the user has consented.
        SharedProfile::recordSubscribed($userId, $persona);
        Stats::increment($this->config->get('id'), Stats::COUNTERS, Stats::EMAIL_SUBSCRIBED);

        wp_safe_redirect($nextUrl);
        exit;
    }

    private function emailNonceAction()
    {
        return 'publishpress_welcome_email_' . $this->config->get('id');
    }

    public function stepCount()
    {
        return count($this->steps);
    }

    public function maybeRedirectAfterActivation()
    {
        if (! $this->isEnrolled() || ! get_option($this->config->get('redirect_option'))) {
            return;
        }

        $requestAction = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

        if (wp_doing_ajax()) {
            return;
        }

        if (is_network_admin() || 'activate-multi' === $requestAction) {
            delete_option($this->config->get('redirect_option'));
            return;
        }

        if (! current_user_can($this->config->get('capability'))) {
            return;
        }

        delete_option($this->config->get('redirect_option'));

        if (! $this->isWelcomePage()) {
            wp_safe_redirect($this->stepUrl(1));
            exit;
        }
    }

    /**
     * Link that records where the user left the guide before sending them to the exit URL.
     *
     * @param int $fromStep
     * @return string
     */
    private function exitUrl($fromStep)
    {
        return add_query_arg(
            ['page' => $this->config->get('page_slug'), 'ppw_exit' => (int) $fromStep],
            admin_url('admin.php')
        );
    }

    public function handleExit()
    {
        if (! $this->isWelcomePage() || ! isset($_GET['ppw_exit']) || ! current_user_can($this->config->get('capability'))) {
            return;
        }

        $number = absint(wp_unslash($_GET['ppw_exit']));

        if ($number >= 1 && $number <= count($this->steps)) {
            Stats::increment($this->config->get('id'), Stats::EXITS, $this->steps[$number - 1]->getId());
        }

        wp_safe_redirect($this->config->get('exit_url'));
        exit;
    }

    /**
     * Show the local funnel under Site Health > Info. Nothing is transmitted.
     */
    public function addSiteHealthInfo($info)
    {
        $stepIds = array_map(function (StepInterface $step) {
            return $step->getId();
        }, $this->steps);

        $info['publishpress-welcome-' . $this->config->get('id')] = [
            'label' => sprintf(
                /* translators: %s: experience ID */
                __('PublishPress welcome guide: %s', 'publishpress-welcome'),
                $this->config->get('id')
            ),
            'fields' => Stats::siteHealthFields($this->config->get('id'), $stepIds),
        ];

        return $info;
    }

    public function handleDismissal()
    {
        $value = isset($_GET['ppw_dismiss']) ? sanitize_text_field(wp_unslash($_GET['ppw_dismiss'])) : '';

        if (! $value || 0 !== strpos($value, $this->config->get('id') . ':')) {
            return;
        }

        if (! current_user_can($this->config->get('capability'))) {
            return;
        }

        check_admin_referer($this->dismissNonceAction());

        $target = substr($value, strlen($this->config->get('id')) + 1);
        if (in_array($target, ['notice', 'card'], true)) {
            update_user_option(get_current_user_id(), $this->userOption($target . '_dismissed'), 1);
            $this->markWhatsNewSeen();
        }

        if ('whats_new' === $target) {
            $this->markWhatsNewSeen();
        }

        wp_safe_redirect(remove_query_arg(['ppw_dismiss', '_wpnonce']));
        exit;
    }

    public function renderNotice()
    {
        if (! $this->noticeIsDue()) {
            return;
        }

        // The notice's lifetime starts the first time the user actually sees it.
        if (! $this->noticeFirstSeen()) {
            update_user_option(get_current_user_id(), $this->userOption('notice_first_seen'), time());
        }

        $lastStep = $this->lastStep();
        $label = $lastStep > 1 ? $this->config->label('continue') : $this->config->label('start');
        $logo = $this->config->get('logo');
        $iconUrl = (string) $this->config->get('notice_icon_url');
        ?>
        <div class="notice ppw-notice" style="<?php echo esc_attr($this->config->styleVariables()); ?>">
            <div class="ppw-notice-inner">
                <span class="ppw-notice-icon" aria-hidden="true">
                    <?php if ($iconUrl) : ?>
                        <img src="<?php echo esc_url($iconUrl); ?>" alt="" />
                    <?php else : ?>
                        <span class="dashicons <?php echo esc_attr(! empty($logo['dashicon']) ? sanitize_html_class($logo['dashicon']) : 'dashicons-megaphone'); ?>"></span>
                    <?php endif; ?>
                </span>
                <div class="ppw-notice-copy">
                    <strong><?php echo esc_html($this->config->get('notice_title')); ?></strong>
                    <span><?php echo esc_html($this->config->get('notice_body')); ?></span>
                </div>
                <div class="ppw-notice-actions">
                    <a class="ppw-btn ppw-btn-primary" href="<?php echo esc_url($this->stepUrl(max(1, $lastStep))); ?>"><?php echo esc_html($label); ?></a>
                    <a class="ppw-text-link" href="<?php echo esc_url($this->dismissUrl('notice')); ?>"><?php echo esc_html($this->config->label('dismiss')); ?></a>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderChecklist()
    {
        if (! $this->checklistIsDue()) {
            return;
        }

        $tasks = apply_filters('publishpress_welcome_' . $this->config->get('id') . '_tasks', $this->tasks);
        $states = [];
        $done = 0;

        foreach ($tasks as $task) {
            if (! $task instanceof ChecklistTaskInterface) {
                continue;
            }
            $complete = $task->isComplete();
            $states[] = [$task, $complete];
            $done += $complete ? 1 : 0;
        }

        $total = count($states);
        if (! $total) {
            return;
        }

        $percent = (int) round(($done / $total) * 100);
        $allDone = $done === $total;
        ?>
        <div class="ppw-checklist<?php echo $allDone ? ' is-complete' : ''; ?>" style="<?php echo esc_attr($this->config->styleVariables()); ?>">
            <div class="ppw-checklist-head">
                <span class="ppw-ring" style="--ppw-percent:<?php echo esc_attr($percent); ?>" aria-hidden="true">
                    <span class="ppw-ring-value"><?php echo esc_html(number_format_i18n($done) . '/' . number_format_i18n($total)); ?></span>
                </span>
                <div class="ppw-checklist-title">
                    <h2><?php echo esc_html($allDone ? $this->config->get('checklist_complete_title') : $this->config->get('checklist_title')); ?></h2>
                    <span class="ppw-bar" role="progressbar" aria-label="<?php echo esc_attr($allDone ? $this->config->get('checklist_complete_title') : $this->config->get('checklist_title')); ?>" aria-valuetext="<?php echo esc_attr(number_format_i18n($done) . ' / ' . number_format_i18n($total)); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr($total); ?>" aria-valuenow="<?php echo esc_attr($done); ?>">
                        <span class="ppw-bar-fill" style="width:<?php echo esc_attr($percent); ?>%"></span>
                    </span>
                </div>
                <a class="ppw-text-link" href="<?php echo esc_url($this->stepUrl(1)); ?>"><?php echo esc_html($this->config->label('replay')); ?></a>
                <a class="ppw-text-link" href="<?php echo esc_url($this->dismissUrl('card')); ?>"><?php echo esc_html($this->config->label($allDone ? 'dismiss' : 'hide')); ?></a>
            </div>
            <?php if (! $allDone) : ?>
                <ul class="ppw-tasks">
                    <?php foreach ($states as $state) : list($task, $complete) = $state; ?>
                        <li class="<?php echo $complete ? 'is-complete' : ''; ?>">
                            <span class="ppw-checkmark" aria-hidden="true"></span>
                            <span class="ppw-task-copy">
                                <span class="ppw-task-label"><span class="ppw-sr-only"><?php echo esc_html($this->config->label($complete ? 'task_done' : 'task_todo')); ?> </span><?php echo esc_html($task->getLabel()); ?></span>
                                <?php if ($complete && $task->getDetail()) : ?><span class="ppw-task-detail"><?php echo esc_html($task->getDetail()); ?></span><?php endif; ?>
                            </span>
                            <a class="ppw-btn <?php echo $complete ? 'ppw-btn-ghost' : 'ppw-btn-outline'; ?>" href="<?php echo esc_url($task->getUrl()); ?>"><?php echo esc_html($this->config->label($complete ? 'view' : 'do_it')); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    public function renderWhatsNew()
    {
        if (! $this->whatsNewIsDue()) {
            return;
        }

        $data = WhatsNew::normalize($this->config->get('whats_new'));
        $label = $data['url_label'] ? $data['url_label'] : $this->config->label('changelog');

        // The highlights' lifetime starts the first time this version's card is shown.
        $firstSeen = $this->whatsNewFirstSeen($data['version']);
        if (! $firstSeen) {
            update_user_option(get_current_user_id(), $this->userOption('whats_new_first_seen'), $data['version'] . '|' . time());
        }
        ?>
        <div class="notice ppw-notice ppw-whatsnew" style="<?php echo esc_attr($this->config->styleVariables()); ?>">
            <div class="ppw-notice-inner">
                <span class="ppw-notice-icon" aria-hidden="true"><span class="dashicons dashicons-star-filled"></span></span>
                <div class="ppw-notice-copy">
                    <strong><?php echo esc_html($data['title']); ?></strong>
                    <ul class="ppw-whatsnew-list">
                        <?php foreach ($data['items'] as $item) : ?>
                            <li>
                                <strong><?php echo esc_html($item['title']); ?></strong>
                                <?php if ($item['body']) : ?><span><?php echo esc_html($item['body']); ?></span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="ppw-notice-actions">
                    <?php if ($data['url']) : ?>
                        <a class="ppw-btn ppw-btn-outline" href="<?php echo esc_url($data['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label); ?><span class="ppw-sr-only"> <?php echo esc_html($this->config->label('new_tab')); ?></span></a>
                    <?php endif; ?>
                    <a class="ppw-text-link" href="<?php echo esc_url($this->dismissUrl('whats_new')); ?>"><?php echo esc_html($this->config->label('dismiss')); ?></a>
                </div>
            </div>
        </div>
        <?php
    }

    private function whatsNewIsDue()
    {
        $data = WhatsNew::normalize($this->config->get('whats_new'));

        if (! $data || ! current_user_can($this->config->get('capability')) || $this->isWelcomePage()) {
            return false;
        }

        // One notice at a time: the welcome notice takes priority for people still on the guide.
        if ($this->noticeIsDue()) {
            return false;
        }

        if (! NoticePolicy::allowsScreen($this->currentPage(), $this->config->get('notice_pages'), (bool) $this->config->get('notice_on_dashboard'), $this->isDashboard())) {
            return false;
        }

        $seen = (string) get_user_option($this->userOption('whats_new_seen'), get_current_user_id());

        return WhatsNew::isNewerThan($seen, $data['version'])
            && ! NoticePolicy::isExpired($this->whatsNewFirstSeen($data['version']), (int) $this->config->get('notice_days'));
    }

    /** @return int Unix time the given version's card was first shown to this user, or 0. */
    private function whatsNewFirstSeen($version)
    {
        $stored = (string) get_user_option($this->userOption('whats_new_first_seen'), get_current_user_id());
        $parts = explode('|', $stored);

        return (2 === count($parts) && $parts[0] === $version) ? (int) $parts[1] : 0;
    }

    private function markWhatsNewSeen()
    {
        $data = WhatsNew::normalize($this->config->get('whats_new'));

        if ($data) {
            update_user_option(get_current_user_id(), $this->userOption('whats_new_seen'), $data['version']);
        }
    }

    private function noticeIsDue()
    {
        return $this->config->get('notice_enabled')
            && $this->isEnrolled()
            && current_user_can($this->config->get('capability'))
            && ! $this->isWelcomePage()
            && NoticePolicy::allowsScreen($this->currentPage(), $this->config->get('notice_pages'), (bool) $this->config->get('notice_on_dashboard'), $this->isDashboard())
            && ! $this->isComplete()
            && ! NoticePolicy::isExpired($this->noticeFirstSeen(), (int) $this->config->get('notice_days'))
            && ! get_user_option($this->userOption('notice_dismissed'), get_current_user_id());
    }

    private function noticeFirstSeen()
    {
        return (int) get_user_option($this->userOption('notice_first_seen'), get_current_user_id());
    }

    private function isDashboard()
    {
        global $pagenow;

        return 'index.php' === $pagenow;
    }

    private function checklistIsDue()
    {
        return $this->config->get('checklist_enabled')
            && $this->isEnrolled()
            && current_user_can($this->config->get('capability'))
            && in_array($this->currentPage(), $this->config->get('checklist_pages'), true)
            && ! get_user_option($this->userOption('card_dismissed'), get_current_user_id());
    }

    private function isEnrolled()
    {
        return (bool) get_option($this->config->get('enrollment_option'));
    }

    private function rememberStep($step)
    {
        update_user_option(get_current_user_id(), $this->userOption('step'), (int) $step);

        if ((int) $step === count($this->steps)) {
            if (! $this->isComplete()) {
                Stats::increment($this->config->get('id'), Stats::COUNTERS, Stats::COMPLETED);
            }

            update_user_option(get_current_user_id(), $this->userOption('complete'), 1);

            // Someone who just finished the guide has no use for the release highlights of this version.
            $this->markWhatsNewSeen();
        }
    }

    private function lastStep()
    {
        $step = (int) get_user_option($this->userOption('step'), get_current_user_id());
        return ($step >= 1 && $step <= count($this->steps)) ? $step : 0;
    }

    private function isComplete()
    {
        return (bool) get_user_option($this->userOption('complete'), get_current_user_id());
    }

    private function currentPage()
    {
        return isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    }

    private function isWelcomePage()
    {
        return $this->currentPage() === $this->config->get('page_slug');
    }

    private function userOption($suffix)
    {
        return $this->config->get('user_option_prefix') . '_' . $suffix;
    }

    private function dismissNonceAction()
    {
        return 'publishpress_welcome_dismiss_' . $this->config->get('id');
    }

    private function dismissUrl($target)
    {
        return wp_nonce_url(
            add_query_arg('ppw_dismiss', $this->config->get('id') . ':' . $target),
            $this->dismissNonceAction()
        );
    }
}

<?php

namespace PublishPress\Welcome;

use InvalidArgumentException;
use PublishPress\Welcome\Contract\ChecklistTaskInterface;
use PublishPress\Welcome\Contract\StepInterface;

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
        $this->steps = array_values($steps);

        self::$registeredExperiences[$id] = true;

        add_action('admin_menu', [$this, 'registerAdminPage'], 99);
        add_action('admin_menu', [$this, 'hideAdminPage'], 999);
        add_action('admin_init', [$this, 'handleDismissal']);
        add_action('admin_init', [$this, 'maybeRedirectAfterActivation'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_notices', [$this, 'renderNotice']);
        add_action('admin_notices', [$this, 'renderChecklist'], 20);

        return true;
    }

    public function registerAdminPage()
    {
        add_submenu_page(
            $this->config->get('parent_slug'),
            $this->config->get('page_title'),
            $this->config->get('menu_title'),
            $this->config->get('capability'),
            $this->config->get('page_slug'),
            [$this, 'render']
        );
    }

    public function hideAdminPage()
    {
        if (! $this->config->get('show_in_menu')) {
            remove_submenu_page($this->config->get('parent_slug'), $this->config->get('page_slug'));
        }
    }

    public function enqueueAssets()
    {
        if (! $this->isWelcomePage() && ! $this->noticeIsDue() && ! $this->checklistIsDue()) {
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

        $context = new RenderContext($this, $this->config);
        $step = $this->steps[$stepNumber - 1];
        $percent = (int) round(($stepNumber / $total) * 100);
        ?>
        <div class="wrap ppw-welcome" style="--ppw-accent:<?php echo esc_attr($this->config->get('accent_color')); ?>">
            <div class="ppw-frame">
                <div class="ppw-progress">
                    <span class="ppw-step-label">
                        <?php printf(esc_html($this->config->label('step_format')), esc_html(number_format_i18n($stepNumber)), esc_html(number_format_i18n($total))); ?>
                    </span>
                    <span class="ppw-bar" role="progressbar" aria-valuemin="1" aria-valuemax="<?php echo esc_attr($total); ?>" aria-valuenow="<?php echo esc_attr($stepNumber); ?>">
                        <span class="ppw-bar-fill" style="width:<?php echo esc_attr($percent); ?>%"></span>
                    </span>
                    <?php if ($stepNumber < $total) : ?><a class="ppw-text-link" href="<?php echo esc_url($this->config->get('exit_url')); ?>"><?php echo esc_html($this->config->label('skip')); ?></a><?php endif; ?>
                </div>
                <div class="ppw-body ppw-step-<?php echo esc_attr($step->getId()); ?>">
                    <?php $step->render($context); ?>
                </div>
                <div class="ppw-navigation">
                    <?php if ($stepNumber > 1) : ?><a class="ppw-back" href="<?php echo esc_url($this->stepUrl($stepNumber - 1)); ?>"><?php echo esc_html($this->config->label('back')); ?></a><?php else : ?><span></span><?php endif; ?>
                    <?php if ($stepNumber < $total) : ?><a class="button button-primary" href="<?php echo esc_url($this->stepUrl($stepNumber + 1)); ?>"><?php echo esc_html($this->config->label('next')); ?></a><?php endif; ?>
                </div>
            </div>
            <?php if ($this->config->label('reassurance')) : ?><p class="ppw-reassurance"><?php echo esc_html($this->config->label('reassurance')); ?></p><?php endif; ?>
        </div>
        <?php
    }

    public function stepUrl($step = 1)
    {
        return add_query_arg(
            ['page' => $this->config->get('page_slug'), 'ppw_step' => (int) $step],
            admin_url('admin.php')
        );
    }

    public function maybeRedirectAfterActivation()
    {
        if (! $this->isEnrolled() || ! get_option($this->config->get('redirect_option'))) {
            return;
        }

        $requestAction = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

        if (wp_doing_ajax() || is_network_admin() || 'activate-multi' === $requestAction) {
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
        }

        wp_safe_redirect(remove_query_arg(['ppw_dismiss', '_wpnonce']));
        exit;
    }

    public function renderNotice()
    {
        if (! $this->noticeIsDue()) {
            return;
        }

        $lastStep = $this->lastStep();
        $label = $lastStep > 1 ? $this->config->label('continue') : $this->config->label('start');
        ?>
        <div class="notice notice-info ppw-notice" style="--ppw-accent:<?php echo esc_attr($this->config->get('accent_color')); ?>">
            <div class="ppw-notice-inner">
                <div><strong><?php echo esc_html($this->config->get('notice_title')); ?></strong><span><?php echo esc_html($this->config->get('notice_body')); ?></span></div>
                <a class="button button-primary" href="<?php echo esc_url($this->stepUrl(max(1, $lastStep))); ?>"><?php echo esc_html($label); ?></a>
                <a class="ppw-text-link" href="<?php echo esc_url($this->dismissUrl('notice')); ?>"><?php echo esc_html($this->config->label('dismiss')); ?></a>
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
        ?>
        <div class="ppw-checklist" style="--ppw-accent:<?php echo esc_attr($this->config->get('accent_color')); ?>">
            <div class="ppw-checklist-head">
                <h2><?php echo esc_html($done === $total ? $this->config->get('checklist_complete_title') : $this->config->get('checklist_title')); ?></h2>
                <span><?php echo esc_html(number_format_i18n($done) . ' / ' . number_format_i18n($total)); ?></span>
                <span class="ppw-bar"><span class="ppw-bar-fill" style="width:<?php echo esc_attr($percent); ?>%"></span></span>
                <a class="ppw-text-link" href="<?php echo esc_url($this->dismissUrl('card')); ?>"><?php echo esc_html($this->config->label($done === $total ? 'dismiss' : 'hide')); ?></a>
            </div>
            <?php if ($done !== $total) : ?>
                <ul>
                    <?php foreach ($states as $state) : list($task, $complete) = $state; ?>
                        <li class="<?php echo $complete ? 'is-complete' : ''; ?>">
                            <span class="ppw-checkmark"><?php echo $complete ? '&#10003;' : ''; ?></span>
                            <span class="ppw-task-label"><?php echo esc_html($task->getLabel()); ?></span>
                            <?php if ($complete && $task->getDetail()) : ?><span class="ppw-task-detail"><?php echo esc_html($task->getDetail()); ?></span><?php endif; ?>
                            <a href="<?php echo esc_url($task->getUrl()); ?>"><?php echo esc_html($this->config->label($complete ? 'view' : 'do_it')); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p><a href="<?php echo esc_url($this->stepUrl(1)); ?>"><?php echo esc_html($this->config->label('replay')); ?></a></p>
        </div>
        <?php
    }

    private function noticeIsDue()
    {
        return $this->config->get('notice_enabled')
            && $this->isEnrolled()
            && current_user_can($this->config->get('capability'))
            && ! $this->isWelcomePage()
            && ! $this->isComplete()
            && ! get_user_option($this->userOption('notice_dismissed'), get_current_user_id());
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
            update_user_option(get_current_user_id(), $this->userOption('complete'), 1);
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

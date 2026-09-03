<?php

namespace PublishPress\Permissions\UI;

/**
 * Everything around the onboarding wizard: stylesheet loading, the
 * "take the tour" admin notice, and the progress card on the Permissions
 * screen.
 *
 * The stylesheet loads on the welcome page, on the two screens that show the
 * progress card, and wherever the activation notice is due — nowhere else.
 *
 * Instantiated once from PermissionsHooksAdmin::__construct().
 */
class WelcomeOnboarding
{
    const USER_OPTION_NOTICE_DISMISSED = 'presspermit_welcome_notice_dismissed';
    const USER_OPTION_CARD_DISMISSED   = 'presspermit_welcome_card_dismissed';

    /** Screens that render the wizard, the progress card, or both. */
    private static $asset_pages = ['presspermit-welcome', 'presspermit-groups', 'presspermit-settings'];

    /** Screens that render the progress card. */
    private static $card_pages = ['presspermit-groups', 'presspermit-settings'];

    public function __construct()
    {
        require_once(PRESSPERMIT_CLASSPATH . '/UI/Welcome.php');

        add_action('admin_enqueue_scripts', [$this, 'actEnqueueAssets']);
        add_action('admin_init', [$this, 'actHandleDismiss']);
        add_action('admin_notices', [$this, 'actNotice']);
        add_action('admin_notices', [$this, 'actProgressCard'], 20);
    }

    /* -------------------------------------------------------------- assets */

    public function actEnqueueAssets()
    {
        // The notice can appear on any admin screen; everything else is scoped
        // to the plugin's own pages.
        if (!in_array(presspermitPluginPage(), self::$asset_pages, true) && !$this->noticeIsDue()) {
            return;
        }

        wp_enqueue_style(
            'presspermit-welcome',
            PRESSPERMIT_URLPATH . '/common/css/welcome.css',
            [],
            PRESSPERMIT_VERSION
        );
    }

    /* ------------------------------------------------------------ dismissal */

    public function actHandleDismiss()
    {
        $target = PWP::GET_key('pp_onboard_dismiss');

        if (!$target) {
            return;
        }

        if (!check_admin_referer('pp_onboard_dismiss')) {
            return;
        }

        global $current_user;

        if ('notice' == $target) {
            update_user_option($current_user->ID, self::USER_OPTION_NOTICE_DISMISSED, 1);
        } elseif ('card' == $target) {
            update_user_option($current_user->ID, self::USER_OPTION_CARD_DISMISSED, 1);
        }

        wp_safe_redirect(remove_query_arg(['pp_onboard_dismiss', '_wpnonce']));
        exit;
    }

    private function dismissUrl($target)
    {
        return wp_nonce_url(
            add_query_arg('pp_onboard_dismiss', $target),
            'pp_onboard_dismiss'
        );
    }

    /* --------------------------------------------------------------- notice */

    /**
     * Capability and user-option checks only — no database queries, since this
     * runs on every admin request.
     */
    private function noticeIsDue()
    {
        if (!current_user_can('pp_manage_settings')) {
            return false;
        }

        if ('presspermit-welcome' == presspermitPluginPage()) {
            return false;
        }

        return !Welcome::isComplete() && !get_user_option(self::USER_OPTION_NOTICE_DISMISSED);
    }

    /**
     * Shown until the user finishes or dismisses the guide. This covers the
     * cases where the activation redirect does not run: network activation,
     * bulk activation, or a user who navigated away mid-flow.
     */
    public function actNotice()
    {
        if (!$this->noticeIsDue()) {
            return;
        }

        $resume = Welcome::lastStep();
        $step   = ($resume > 1) ? $resume : 1;

        $label = ($resume > 1)
            ? __('Continue the tour', 'press-permit-core')
            : __('Start the tour', 'press-permit-core');
        ?>
        <div class="notice notice-info pp-onboard-notice">
            <div class="pp-onboard-notice-inner">
                <div class="pp-onboard-notice-text">
                    <strong><?php esc_html_e('Permissions is active. Want a two minute tour?', 'press-permit-core'); ?></strong>
                    <span><?php esc_html_e('We will show you where to control who reads and edits your content. Nothing gets changed.', 'press-permit-core'); ?></span>
                </div>

                <a class="button button-primary" href="<?php echo esc_url(Welcome::url($step)); ?>">
                    <?php echo esc_html($label); ?>
                </a>

                <a class="pp-wc-textlink" href="<?php echo esc_url($this->dismissUrl('notice')); ?>">
                    <?php esc_html_e('Dismiss', 'press-permit-core'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /* -------------------------------------------------------- progress card */

    /**
     * Sits at the top of the Permissions screen. It counts real configuration
     * state, not pages read, so it cannot be completed by clicking Next.
     */
    public function actProgressCard()
    {
        return;
        if (!in_array(presspermitPluginPage(), self::$card_pages, true)) {
            return;
        }

        if (get_user_option(self::USER_OPTION_CARD_DISMISSED)) {
            return;
        }

        $tasks = $this->tasks();

        $done = 0;

        foreach ($tasks as $task) {
            if ($task['done']) {
                $done++;
            }
        }

        $total = count($tasks);

        if ($done >= $total) {
            ?>
            <div class="pp-onboard-card">
                <div class="pp-onboard-card-head">
                    <span class="pp-onboard-tick is-done">&#10003;</span>
                    <h2><?php esc_html_e('Permissions is set up', 'press-permit-core'); ?></h2>
                    <a class="pp-wc-textlink" href="<?php echo esc_url($this->dismissUrl('card')); ?>">
                        <?php esc_html_e('Dismiss', 'press-permit-core'); ?>
                    </a>
                </div>
            </div>
            <?php
            return;
        }

        $pct = round(($done / $total) * 100);
        ?>
        <div class="pp-onboard-card">
            <div class="pp-onboard-card-head">
                <h2><?php esc_html_e('Finish setting up Permissions', 'press-permit-core'); ?></h2>

                <span class="pp-onboard-card-count">
                    <?php
                    printf(
                        /* translators: %1$s: number of completed tasks, %2$s: total number of tasks */
                        esc_html__('%1$s of %2$s', 'press-permit-core'),
                        esc_html(number_format_i18n($done)),
                        esc_html(number_format_i18n($total))
                    );
                    ?>
                </span>

                <span class="pp-wc-bar">
                    <span class="pp-wc-bar-fill" style="width:<?php echo esc_attr($pct); ?>%"></span>
                </span>

                <a class="pp-wc-textlink" href="<?php echo esc_url($this->dismissUrl('card')); ?>">
                    <?php esc_html_e('Hide', 'press-permit-core'); ?>
                </a>
            </div>

            <ul>
                <?php foreach ($tasks as $task) : ?>
                    <li class="<?php echo $task['done'] ? 'is-done' : ''; ?>">
                        <span class="pp-onboard-tick <?php echo $task['done'] ? 'is-done' : ''; ?>">
                            <?php echo $task['done'] ? '&#10003;' : ''; ?>
                        </span>

                        <span class="pp-onboard-label"><?php echo esc_html($task['label']); ?></span>

                        <?php if (!$task['done']) : ?>
                            <a href="<?php echo esc_url($task['url']); ?>"><?php esc_html_e('Do it', 'press-permit-core'); ?></a>
                        <?php else : ?>
                            <span class="pp-onboard-card-count"><?php echo esc_html($task['detail']); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="pp-onboard-card-foot">
                <a href="<?php echo esc_url(Welcome::url(1)); ?>"><?php esc_html_e('Replay the guide', 'press-permit-core'); ?></a>
            </p>
        </div>
        <?php
    }

    /* ---------------------------------------------------------- state checks */

    private function tasks()
    {
        $types = $this->enabledPostTypeCount();
        $groups = $this->customGroupCount();
        $exceptions = $this->exceptionCount();

        $tasks = [
            'post_types' => [
                'label'  => __('Choose the content you want to control', 'press-permit-core'),
                'done'   => $this->postTypesConfigured(),
                'detail' => sprintf(
                    /* translators: %s: number of enabled post types */
                    _n('%s type on', '%s types on', $types, 'press-permit-core'),
                    number_format_i18n($types)
                ),
                'url'    => admin_url('admin.php?page=presspermit-settings&pp_tab=core'),
            ],

            'group' => [
                'label'  => __('Create your first permission group', 'press-permit-core'),
                'done'   => ($groups > 0),
                'detail' => sprintf(
                    /* translators: %s: number of groups */
                    _n('%s group', '%s groups', $groups, 'press-permit-core'),
                    number_format_i18n($groups)
                ),
                'url'    => admin_url('admin.php?page=presspermit-group-new'),
            ],

            'exception' => [
                'label'  => __('Set permissions on one post or page', 'press-permit-core'),
                'done'   => ($exceptions > 0),
                'detail' => __('done', 'press-permit-core'),
                'url'    => admin_url('edit.php?post_type=page'),
            ],
        ];

        return apply_filters('presspermit_welcome_tasks', $tasks);
    }

    private function enabledPostTypeCount()
    {
        $enabled = presspermit()->getEnabledPostTypes();

        return is_array($enabled) ? count($enabled) : 0;
    }

    /**
     * Treated as configured once the enabled set differs from the install
     * default of post + page, i.e. the user has actually been through the
     * Post Types setting.
     */
    private function postTypesConfigured()
    {
        $enabled = (array) get_option('presspermit_enabled_post_types', []);

        $enabled = array_keys(array_filter($enabled));

        sort($enabled);

        return (['page', 'post'] !== $enabled);
    }

    private function customGroupCount()
    {
        global $wpdb;

        if (empty($wpdb->pp_groups)) {
            return 0;
        }

        // metagroup_id is set for the groups Permissions maintains for WP roles.
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->pp_groups WHERE metagroup_id = ''");
    }

    private function exceptionCount()
    {
        global $wpdb;

        if (empty($wpdb->ppc_exceptions)) {
            return 0;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->ppc_exceptions");
    }
}

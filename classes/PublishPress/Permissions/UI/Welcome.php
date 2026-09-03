<?php

namespace PublishPress\Permissions\UI;

/**
 * Onboarding wizard shown after activation (wireframe 1a).
 *
 * Six steps, education only: nothing on this screen writes a plugin setting.
 * Navigation is plain links (?page=presspermit-welcome&pp_step=N) so the flow
 * works without JavaScript. Progress is remembered per user.
 */
class Welcome
{
    const STEP_COUNT = 6;

    const USER_OPTION_STEP = 'presspermit_welcome_step';
    const USER_OPTION_DONE = 'presspermit_welcome_complete';

    public function __construct()
    {
        if (!current_user_can('pp_manage_settings')) {
            wp_die(esc_html(PWP::__wp('Cheatin&#8217; uh?')));
        }

        $step = (int) PWP::REQUEST_int('pp_step');

        if ($step < 1 || $step > self::STEP_COUNT) {
            $step = 1;
        }

        self::rememberStep($step);

        $this->render($step);
    }

    /* ---------------------------------------------------------------- state */

    public static function rememberStep($step)
    {
        global $current_user;

        update_user_option($current_user->ID, self::USER_OPTION_STEP, (int) $step);

        if (self::STEP_COUNT == (int) $step) {
            update_user_option($current_user->ID, self::USER_OPTION_DONE, 1);
        }
    }

    public static function lastStep()
    {
        $step = (int) get_user_option(self::USER_OPTION_STEP);

        return ($step >= 1 && $step <= self::STEP_COUNT) ? $step : 0;
    }

    public static function isComplete()
    {
        return (bool) get_user_option(self::USER_OPTION_DONE);
    }

    public static function url($step = 1)
    {
        return add_query_arg(
            ['page' => 'presspermit-welcome', 'pp_step' => (int) $step],
            admin_url('admin.php')
        );
    }

    /* ----------------------------------------------------------------- copy */

    private function canManageGroups()
    {
        return current_user_can('pp_manage_permissions')
            || current_user_can('pp_edit_groups')
            || presspermit()->groups()->anyGroupManager();
    }

    /**
     * The three concepts on step 2.
     */
    private function concepts()
    {
        return [
            [
                'name' => __('Roles', 'press-permit-core'),
                'body' => __('What a person can do everywhere on the site. Roles come from WordPress.', 'press-permit-core'),
                'art'  => 'concept-roles',
            ],
            [
                'name' => __('Groups', 'press-permit-core'),
                'body' => __('A bundle of people you give the same access to. Easier than editing every user.', 'press-permit-core'),
                'art'  => 'concept-groups',
            ],
            [
                'name' => __('Exceptions', 'press-permit-core'),
                'body' => __('An override for one post, page or category. An exception beats the role.', 'press-permit-core'),
                'art'  => 'concept-exceptions',
            ],
        ];
    }

    /**
     * Steps 3, 4 and 5: a screenshot with numbered callouts plus a link out.
     */
    private function lessons()
    {
        $lessons = [
            3 => [
                'title'    => __('Choose the content you want to control', 'press-permit-core'),
                'sub'      => __('Permissions only filters the post types and taxonomies you switch on. Everything else behaves like standard WordPress.', 'press-permit-core'),
                'art'      => 'step-features',
                'art_alt'  => __('The Features tab of the Permissions settings screen', 'press-permit-core'),
                'callouts' => [
                    __('Open Permissions, then Settings.', 'press-permit-core'),
                    __('The Features tab lists every module you can turn on.', 'press-permit-core'),
                    __('Tick the post types and taxonomies you want to manage.', 'press-permit-core'),
                ],
                'link_label' => __('Open Settings', 'press-permit-core'),
                'link_url'   => admin_url('admin.php?page=presspermit-settings&pp_tab=modules'),
            ],

            4 => [
                'title'    => __('Put people into a group', 'press-permit-core'),
                'sub'      => __('A group hands the same access to several users at once. Name it after the job it does, not the person doing it.', 'press-permit-core'),
                'art'      => 'step-groups',
                'art_alt'  => __('The Permissions groups screen', 'press-permit-core'),
                'callouts' => [
                    __('Go to Permissions and choose Add New.', 'press-permit-core'),
                    __('Add members from your existing user list.', 'press-permit-core'),
                    __('Give the group the access it needs.', 'press-permit-core'),
                ],
                'link_label' => $this->canManageGroups() ? __('Open Permissions', 'press-permit-core') : '',
                'link_url'   => admin_url('admin.php?page=presspermit-groups'),
            ],

            5 => [
                'title'    => __('Set who can read one page', 'press-permit-core'),
                'sub'      => __('Every post and page has a Permissions panel for read and edit access. What you set there overrides the role.', 'press-permit-core'),
                'art'      => 'step-post-edit',
                'art_alt'  => __('The Permissions panel on the post editing screen', 'press-permit-core'),
                'callouts' => [
                    __('Edit any post or page.', 'press-permit-core'),
                    __('Find the Permissions panel.', 'press-permit-core'),
                    __('Enable or block a group or a role.', 'press-permit-core'),
                ],
                'link_label' => __('Open a page to try it', 'press-permit-core'),
                'link_url'   => admin_url('edit.php?post_type=page'),
            ],
        ];

        return apply_filters('presspermit_welcome_lessons', $lessons);
    }

    private function proFeatures()
    {
        return [
            __('Editing permissions per post type', 'press-permit-core'),
            __('Media and file access control', 'press-permit-core'),
            __('Membership plugin integrations', 'press-permit-core'),
            __('Custom post status permissions', 'press-permit-core'),
            __('Sync user posts', 'press-permit-core'),
            __('Priority support', 'press-permit-core'),
        ];
    }

    /* --------------------------------------------------------------- helpers */

    /**
     * Artwork lives in common/img/welcome/{slug}.svg (diagrams) or .png
     * (screenshots). When neither exists a labelled placeholder is drawn, so
     * the flow never shows a broken image.
     */
    private function figure($slug, $alt = '', $callout_count = 0)
    {
        $found = '';

        foreach (['svg', 'png'] as $ext) {
            $rel = '/common/img/welcome/' . $slug . '.' . $ext;

            if (file_exists(PRESSPERMIT_ABSPATH . $rel)) {
                $found = $rel;
                break;
            }
        }

        // Diagrams are drawn edge to edge; screenshots get a frame.
        $class = ('svg' == pathinfo($found, PATHINFO_EXTENSION))
            ? 'pp-wc-figure pp-wc-figure-diagram'
            : 'pp-wc-figure';

        printf('<div class="%s">', esc_attr($class));

        if ($found) {
            printf(
                '<img src="%s" alt="%s" />',
                esc_url(PRESSPERMIT_URLPATH . $found),
                esc_attr($alt)
            );
        } else {
            printf(
                '<div class="pp-wc-figure-placeholder"><span>%s</span></div>',
                esc_html($alt ? $alt : $slug)
            );
        }

        for ($i = 1; $i <= (int) $callout_count; $i++) {
            printf('<span class="pp-wc-pin pp-wc-pin-%1$d">%1$d</span>', (int) $i);
        }

        echo '</div>';
    }

    /**
     * Step 1 artwork: a padlock that unlocks, lets the bars below run, then
     * locks again. Inline SVG rather than the dashicons glyph, because the
     * shackle has to move independently of the body. Colour comes from CSS
     * via currentColor.
     */
    private function heroArt()
    {
        ?>
        <div class="pp-wc-hero-badge" aria-hidden="true">
            <svg class="pp-wc-lock" viewBox="0 0 64 64" width="64" height="64" focusable="false">
                <path class="pp-wc-shackle" d="M22 32V22a10 10 0 0 1 20 0v10" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" />
                <rect x="13" y="30" width="38" height="26" rx="5" fill="currentColor" />
                <circle cx="32" cy="41" r="3.2" fill="#f6f5fa" />
                <rect x="30.6" y="42.6" width="2.8" height="7" rx="1.4" fill="#f6f5fa" />
            </svg>

            <span class="pp-wc-hero-bars">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </div>
        <?php
    }

    private function progress($step)
    {
        $pct = round(($step / self::STEP_COUNT) * 100);
        ?>
        <div class="pp-wc-progress">
            <span class="pp-wc-step-label">
                <?php
                printf(
                    /* translators: %1$s: current step number, %2$s: total number of steps */
                    esc_html__('Step %1$s of %2$s', 'press-permit-core'),
                    esc_html(number_format_i18n($step)),
                    esc_html(number_format_i18n(self::STEP_COUNT))
                );
                ?>
            </span>

            <span class="pp-wc-bar" role="progressbar" aria-valuemin="1" aria-valuemax="<?php echo esc_attr(self::STEP_COUNT); ?>" aria-valuenow="<?php echo esc_attr($step); ?>">
                <span class="pp-wc-bar-fill" style="width:<?php echo esc_attr($pct); ?>%"></span>
            </span>

            <?php if (self::STEP_COUNT != $step) : ?>
                <a class="pp-wc-skip" href="<?php echo esc_url(admin_url('admin.php?page=presspermit-settings')); ?>">
                    <?php esc_html_e('Skip', 'press-permit-core'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    private function footerNav($step)
    {
        ?>
        <div class="pp-wc-nav">
            <?php if ($step > 1) : ?>
                <a class="pp-wc-back" href="<?php echo esc_url(self::url($step - 1)); ?>">
                    <?php esc_html_e('Back', 'press-permit-core'); ?>
                </a>
            <?php else : ?>
                <span></span>
            <?php endif; ?>

            <?php if ($step < self::STEP_COUNT) : ?>
                <a class="button button-primary pp-wc-next" href="<?php echo esc_url(self::url($step + 1)); ?>">
                    <?php esc_html_e('Next', 'press-permit-core'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------- render */

    private function render($step)
    {
        ?>
        <div class="pressshack-admin-wrapper wrap pp-welcome-wizard" id="pp-welcome-wrapper">
            <div class="pp-wc-frame">
                <?php
                $this->progress($step);

                echo '<div class="pp-wc-body">';

                switch ($step) {
                    case 1:
                        $this->stepWelcome();
                        break;

                    case 2:
                        $this->stepConcepts();
                        break;

                    case 6:
                        $this->stepPro();
                        break;

                    default:
                        $lessons = $this->lessons();

                        if (!empty($lessons[$step])) {
                            $this->stepLesson($lessons[$step]);
                        }
                }

                echo '</div>';

                $this->footerNav($step);
                ?>
            </div>

            <p class="pp-wc-reassure">
                <?php esc_html_e('This guide does not change any setting on your site.', 'press-permit-core'); ?>
            </p>
        </div>
        <?php
    }

    private function stepWelcome()
    {
        ?>
        <div class="pp-wc-hero">
            <div class="pp-wc-hero-text">
                <h1><?php esc_html_e('Welcome to PublishPress Permissions', 'press-permit-core'); ?></h1>

                <p class="pp-wc-lede">
                    <?php esc_html_e('Control who can read and edit posts, pages, categories, media, and other content across your WordPress site.', 'press-permit-core'); ?>
                </p>

                <p>
                    <?php esc_html_e('Six short screens show you where everything lives. Nothing changes on your site while you read.', 'press-permit-core'); ?>
                </p>

                <div class="pp-wc-hero-actions">
                    <a class="button button-primary button-hero" href="<?php echo esc_url(self::url(2)); ?>">
                        <?php esc_html_e('Start the tour', 'press-permit-core'); ?>
                    </a>

                    <a class="pp-wc-textlink" href="<?php echo esc_url(admin_url('admin.php?page=presspermit-settings')); ?>">
                        <?php esc_html_e('Skip, take me to Settings', 'press-permit-core'); ?>
                    </a>
                </div>
            </div>

            <div class="pp-wc-hero-art">
                <?php $this->heroArt(); ?>
            </div>
        </div>
        <?php
    }

    private function stepConcepts()
    {
        ?>
        <h1><?php esc_html_e('Three ideas to know', 'press-permit-core'); ?></h1>
        <p class="pp-wc-lede"><?php esc_html_e('Get these right and the rest of the plugin makes sense.', 'press-permit-core'); ?></p>

        <div class="pp-wc-concepts">
            <?php foreach ($this->concepts() as $concept) : ?>
                <div class="pp-wc-concept">
                    <?php $this->figure($concept['art'], $concept['name']); ?>
                    <h2><?php echo esc_html($concept['name']); ?></h2>
                    <p><?php echo esc_html($concept['body']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="pp-wc-aside">
            <?php esc_html_e('Rule of thumb: roles say what a person can do everywhere. Exceptions override that for one post or one category.', 'press-permit-core'); ?>
        </p>
        <?php
    }

    private function stepLesson($lesson)
    {
        ?>
        <h1><?php echo esc_html($lesson['title']); ?></h1>
        <p class="pp-wc-lede"><?php echo esc_html($lesson['sub']); ?></p>

        <div class="pp-wc-lesson">
            <?php $this->figure($lesson['art'], $lesson['art_alt'], count($lesson['callouts'])); ?>

            <ol class="pp-wc-callouts">
                <?php foreach ($lesson['callouts'] as $callout) : ?>
                    <li><?php echo esc_html($callout); ?></li>
                <?php endforeach; ?>
            </ol>
        </div>

        <?php if (!empty($lesson['link_label'])) : ?>
            <p class="pp-wc-linkout">
                <a href="<?php echo esc_url($lesson['link_url']); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html($lesson['link_label']); ?>
                </a>
                <span class="pp-wc-linkout-note"><?php esc_html_e('opens in a new tab, this guide stays here', 'press-permit-core'); ?></span>
            </p>
        <?php endif; ?>
        <?php
    }

    private function stepPro()
    {
        $groups_url = admin_url('admin.php?page=presspermit-groups');
        ?>
        <h1><?php esc_html_e('That is the whole plugin', 'press-permit-core'); ?></h1>
        <p class="pp-wc-lede"><?php esc_html_e('Nothing on your site changed. Here is what to do first:', 'press-permit-core'); ?></p>

        <ul class="pp-wc-nextsteps">
            <li><?php esc_html_e('Create the group you have in mind for your team.', 'press-permit-core'); ?></li>
            <li><?php esc_html_e('Hide a page from logged out visitors.', 'press-permit-core'); ?></li>
            <li><?php esc_html_e('Check the Features tab for modules you skipped.', 'press-permit-core'); ?></li>
        </ul>

        <div class="pp-wc-finish">
            <a class="button button-primary" href="<?php echo esc_url($this->canManageGroups() ? $groups_url : admin_url('admin.php?page=presspermit-settings')); ?>">
                <?php esc_html_e('Go to Permissions', 'press-permit-core'); ?>
            </a>

            <a class="pp-wc-textlink" href="<?php echo esc_url(self::url(1)); ?>">
                <?php esc_html_e('Replay the guide', 'press-permit-core'); ?>
            </a>
        </div>

        <?php if (!presspermit()->isPro()) : ?>
            <div class="pp-wc-pro">
                <div class="pp-wc-pro-head">
                    <span class="pp-wc-pro-badge"><?php esc_html_e('Pro', 'press-permit-core'); ?></span>
                    <h2><?php esc_html_e('If you need more control', 'press-permit-core'); ?></h2>
                </div>

                <ul class="pp-wc-pro-list">
                    <?php foreach ($this->proFeatures() as $feature) : ?>
                        <li><?php echo esc_html($feature); ?></li>
                    <?php endforeach; ?>
                </ul>

                <a class="button pp-wc-pro-button" href="https://publishpress.com/permissions/" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('See Pro features', 'press-permit-core'); ?>
                </a>
            </div>
        <?php endif; ?>
        <?php
    }
}

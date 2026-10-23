<?php

namespace PublishPress\Permissions\UI;

use PublishPress\Welcome\ChecklistTask;
use PublishPress\Welcome\ExperienceConfig;
use PublishPress\Welcome\RenderContext;
use PublishPress\Welcome\Step\ConceptsStep;
use PublishPress\Welcome\Step\FinishStep;
use PublishPress\Welcome\Step\HeroStep;
use PublishPress\Welcome\WelcomeController;

\defined('ABSPATH') || exit;

/**
 * Permissions-specific content and completion rules for the shared welcome UI.
 */
class WelcomeExperience
{
    const POST_TYPES_ACTION = 'presspermit_welcome_post_types';
    const POST_TYPES_NONCE = 'presspermit-welcome-post-types';

    /** @var WelcomeController|null */
    private $controller;

    /** @var int|null Checklist count, refreshed on each request. */
    private $exceptionCount;

    public function __construct()
    {
        if (! class_exists(WelcomeController::class)) {
            return;
        }

        require_once __DIR__ . '/WelcomePostTypes.php';
        require_once __DIR__ . '/WelcomeCallbackStep.php';

        $config = new ExperienceConfig(
            [
                'id' => 'permissions',
                'page_slug' => 'presspermit-welcome',
                'parent_slug' => 'presspermit-groups',
                'page_title' => __('Welcome to PublishPress Permissions', 'press-permit-core'),
                'menu_title' => __('Welcome', 'press-permit-core'),
                'capability' => 'pp_manage_settings',
                'accent_color' => '#655997',
                'mode' => $this->displayMode(),
                'allow_mode_override' => defined('WP_DEBUG') && WP_DEBUG,
                'brand_name' => __('Permissions', 'press-permit-core'),
                'logo' => [
                    'url' => $this->urlPath() . '/common/img/publishpress-logo.png',
                    'alt' => 'PublishPress',
                    'show_name' => true,
                ],
                // Optional, skippable email step from the shared library. Override or disable it with the
                // "publishpress_welcome_permissions_email_capture" filter. Delivery uses the shared connector endpoint once deployed.
                'email_capture' => [
                    'personas' => [],
                    'asset' => $this->asset('penguins.png'),
                    'after_form_callback' => [$this, 'renderCapabilities'],
                    'consent_statement' => 'permissions-email-v1',
                    'consent_prechecked' => true,
                    'privacy_url' => 'https://publishpress.com/privacy-policy/',
                    'copy' => [
                        'lede' => __('Get Permissions tips and product news in your inbox. This is optional, and everything works if you skip it.', 'press-permit-core'),
                        'disclosure' => __('Your email address is sent to %s when you subscribe. Nothing is sent if you skip.', 'press-permit-core'),
                        'confirmation_note' => '',
                        'consent_label' => __('Yes, send me tips, product news and offers tailored to how I use PublishPress Permissions. I can unsubscribe at any time.', 'press-permit-core'),
                    ],
                ],
                // Sample release highlights, shown once per version after an update. Replace with real release notes.
                'whats_new' => [
                    'version' => PRESSPERMIT_VERSION,
                    'title' => sprintf(__('What is new in Permissions %s', 'press-permit-core'), PRESSPERMIT_VERSION),
                    'items' => [
                        ['title' => __('Guided tour', 'press-permit-core'), 'body' => __('A short walkthrough of roles, exceptions and the Teaser.', 'press-permit-core')],
                        ['title' => __('Setup checklist', 'press-permit-core'), 'body' => __('See at a glance what is left to configure.', 'press-permit-core')],
                    ],
                    'url' => 'https://publishpress.com/blog/',
                ],
                'notice_icon_url' => $this->urlPath() . '/common/img/publishpress-logo-icon.png',
                'styles' => [
                    [
                        'handle' => 'presspermit-welcome',
                        'url' => $this->urlPath() . '/common/css/welcome-permissions.css',
                        'version' => PRESSPERMIT_VERSION,
                    ],
                ],
                'enrollment_option' => 'presspermit_welcome_enrolled',
                'redirect_option' => 'presspermit_welcome_redirect_pending',
                'user_option_prefix' => 'presspermit_welcome',
                'notice_title' => __('Permissions is active. Want a two minute tour?', 'press-permit-core'),
                'notice_body' => __('Choose your content, explore the Teaser, and set up access for your users.', 'press-permit-core'),
                'checklist_pages' => ['presspermit-groups'],
                'notice_pages' => ['presspermit-groups', 'presspermit-settings'],
                'checklist_title' => __('Finish setting up Permissions', 'press-permit-core'),
                'checklist_complete_title' => __('Permissions is set up', 'press-permit-core'),
                'exit_url' => admin_url('admin.php?page=presspermit-settings'),
                'labels' => [
                    'back' => __('Back', 'press-permit-core'),
                    'next' => __('Next', 'press-permit-core'),
                    'skip' => __('Skip', 'press-permit-core'),
                    'start' => __('Start the tour', 'press-permit-core'),
                    'continue' => __('Continue the tour', 'press-permit-core'),
                    'dismiss' => __('Dismiss', 'press-permit-core'),
                    'hide' => __('Hide', 'press-permit-core'),
                    'do_it' => __('Do it', 'press-permit-core'),
                    'view' => __('View', 'press-permit-core'),
                    'replay' => __('Replay the guide', 'press-permit-core'),
                    'step_format' => __('Step %1$s of %2$s', 'press-permit-core'),
                    'reassurance' => __('Your settings change only when you choose Save and continue.', 'press-permit-core'),
                ],
            ]
        );

        $this->controller = new WelcomeController($config, $this->steps(), $this->tasks());
        $this->controller->register();

        add_action('admin_post_' . self::POST_TYPES_ACTION, [$this, 'savePostTypes']);
    }

    /**
     * "fullscreen" covers the admin chrome like a setup wizard; "screen" keeps the guide inside it.
     *
     * @return string
     */
    private function displayMode()
    {
        $mode = apply_filters('presspermit_welcome_mode', 'fullscreen');

        return in_array($mode, ['screen', 'fullscreen'], true) ? $mode : 'fullscreen';
    }

    private function steps()
    {
        return [
            new HeroStep(
                'welcome',
                [
                    'eyebrow' => __('Welcome to PublishPress Permissions', 'press-permit-core'),
                    'title' => __('Control who can read and edit your content.', 'press-permit-core'),
                    'lede' => __('Choose the content to manage, set up the Teaser for restricted content, and decide who can read or edit what. It takes about two minutes.', 'press-permit-core'),
                    'asset' => $this->asset('penguins.png'),
                    'secondary_action' => [
                        'label' => __('Skip, take me to Settings', 'press-permit-core'),
                        'url' => admin_url('admin.php?page=presspermit-settings'),
                    ],
                ]
            ),
            new ConceptsStep(
                'concepts',
                [
                    'title' => __('Two ideas to know', 'press-permit-core'),
                    'lede' => __('Get these right and the rest of the plugin makes sense.', 'press-permit-core'),
                    'concepts' => [
                        [
                            'name' => __('Roles', 'press-permit-core'),
                            'body' => __('What a person can do everywhere on the site. Roles come from WordPress.', 'press-permit-core'),
                            'slug' => 'concept-roles',
                            'asset' => $this->asset('concept-roles.svg'),
                            'art_callback' => [$this, 'renderRolesArt'],
                        ],
                        [
                            'name' => __('Exceptions', 'press-permit-core'),
                            'slug' => 'concept-exceptions',
                            'body' => __('An override for one post, page or category. An exception beats the role.', 'press-permit-core'),
                            'asset' => $this->asset('concept-exceptions.svg'),
                            'art_callback' => [$this, 'renderExceptionsArt'],
                        ],
                    ],
                ]
            ),
            new WelcomeCallbackStep('content', [$this, 'renderPostTypes'], true),
            new WelcomeCallbackStep('teaser', [$this, 'renderTeaser']),
            new FinishStep(
                'finish',
                [
                    'title' => __('You are ready to get started', 'press-permit-core'),
                    'lede' => __('Keep setting up Permissions with these next steps.', 'press-permit-core'),
                    'next_steps_title' => __('What to do next', 'press-permit-core'),
                    'next_steps' => [
                        ['label' => __('Choose the content you want to control.', 'press-permit-core'), 'url' => $this->postTypesSettingsUrl()],
                        ['label' => __('Configure the Teaser for restricted content.', 'press-permit-core'), 'url' => $this->teaserUrl()],
                        ['label' => __('Set permissions on one post or page.', 'press-permit-core'), 'url' => $this->postPermissionsUrl()],
                    ],
                    'primary_action' => [
                        'label' => __('Go to Permissions', 'press-permit-core'),
                        'url' => admin_url('admin.php?page=' . ($this->canManageGroups() ? 'presspermit-groups' : 'presspermit-settings')),
                    ],
                    // Mirrors the Pro-only modules listed in Admin::getModuleInfo(). Editing Permissions, Teaser and
                    // Content Visibility are bundled with the free plugin, so they are not promoted here.
                    'promo' => presspermit()->isPro() ? [] : [
                        'title' => __('If you need more control', 'press-permit-core'),
                        'features' => [
                            __('File Access: protect direct file URLs', 'press-permit-core'),
                            __('Access Circles: limit access by author group', 'press-permit-core'),
                            __('Membership: date-limited group access', 'press-permit-core'),
                            __('User Posts: auto-create a page per user', 'press-permit-core'),
                            __('Compatibility: ACF, WPML and more', 'press-permit-core'),
                            __('Priority support', 'press-permit-core'),
                        ],
                        'url' => 'https://publishpress.com/permissions/',
                        'label' => __('See Pro features', 'press-permit-core'),
                    ],
                ]
            ),
        ];
    }

    private function tasks()
    {
        return [
            new ChecklistTask(
                'post-types',
                __('Choose the content you want to control', 'press-permit-core'),
                $this->postTypesSettingsUrl(),
                function () {
                    return $this->enabledPostTypeCount() > 0;
                },
                function () {
                    $count = $this->enabledPostTypeCount();
                    return sprintf(
                        _n('%s type on', '%s types on', $count, 'press-permit-core'),
                        number_format_i18n($count)
                    );
                }
            ),
            new ChecklistTask(
                'teaser',
                __('Configure the Teaser for restricted content', 'press-permit-core'),
                $this->teaserUrl(),
                function () {
                    return (bool) array_filter((array) presspermit()->getOption('tease_post_types'));
                },
                function () {
                    return __('on', 'press-permit-core');
                }
            ),
            new ChecklistTask(
                'exception',
                __('Open a post or page and use its Permissions panel', 'press-permit-core'),
                $this->postPermissionsUrl(),
                function () {
                    return $this->exceptionCount() > 0;
                },
                function () {
                    return __('done', 'press-permit-core');
                }
            ),
        ];
    }

    /* ------------------------------------------------------------------ concepts */

    public function renderRolesArt(RenderContext $context)
    {
        $people = [
            ['label' => __('Editor', 'press-permit-core'), 'face' => 'long', 'bg' => '#ece8f8', 'skin' => '#e0a779', 'shade' => '#cc9161', 'hair' => '#2b2033', 'shirt' => '#655997'],
            ['label' => __('Author', 'press-permit-core'), 'face' => 'short', 'bg' => '#e0eff2', 'skin' => '#f2c9a0', 'shade' => '#e2b387', 'hair' => '#7b4a2a', 'shirt' => '#3f8f9c'],
            ['label' => __('Subscriber', 'press-permit-core'), 'face' => 'curly', 'bg' => '#fbebdd', 'skin' => '#8d5a3b', 'shade' => '#7a4a2f', 'hair' => '#1f1a24', 'shirt' => '#e59a4b'],
        ];
        ?>
        <div class="ppw-roles-art">
            <?php foreach ($people as $person) : ?>
                <div class="ppw-person">
                    <?php $this->renderAvatar($person); ?>
                    <span class="ppw-person-role"><?php echo esc_html($person['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * A friendly flat face. Drawn inline so it looks the same on every operating system.
     *
     * @param array $person
     */
    private function renderAvatar(array $person)
    {
        $hairBack = '';
        $hairFront = '';

        if ('long' === $person['face']) {
            $hairBack = '<path d="M19 38c-3-17 6-27 17-27s20 10 17 27c-.2 8 .8 14 3 18H16c2.2-4 3.2-10 3-18z" fill="' . $person['hair'] . '"/>';
            $hairFront = '<path d="M22 32c.8-9.5 7-14.5 14-14.5S49.2 22.5 50 32c-4.2-3.4-9-6-14-6s-9.800 2.600-14 6z" fill="' . $person['hair'] . '"/>';
        } elseif ('curly' === $person['face']) {
            $hairBack = '<circle cx="36" cy="27" r="19" fill="' . $person['hair'] . '"/>';
            $hairFront = '<path d="M23.500 29c1-6 6-10 12.500-10s11.500 4 12.500 10c-3.500-2.800-7.500-4.200-12.500-4.200S27 26.200 23.500 29z" fill="' . $person['hair'] . '"/>';
        } else {
            $hairFront = '<path d="M22.500 31c-.8-10 5-16.500 13.500-16.500S49.800 21 49.500 31c-3.200-4.600-7.300-7-13.500-7s-10.300 2.400-13.500 7z" fill="' . $person['hair'] . '"/>';
        }
        ?>
        <svg class="ppw-avatar" viewBox="0 0 72 72" width="72" height="72" aria-hidden="true" focusable="false">
            <circle cx="36" cy="36" r="36" fill="<?php echo esc_attr($person['bg']); ?>"/>
            <?php echo $hairBack; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup, colours are fixed in this class. ?>
            <path d="M12 72c0-14 10.500-22 24-22s24 8 24 22z" fill="<?php echo esc_attr($person['shirt']); ?>"/>
            <path d="M30.500 46h11v7.500c0 3-2.500 5-5.500 5s-5.500-2-5.500-5z" fill="<?php echo esc_attr($person['shade']); ?>"/>
            <ellipse cx="36" cy="33.500" rx="13" ry="14.500" fill="<?php echo esc_attr($person['skin']); ?>"/>
            <?php echo $hairFront; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup, colours are fixed in this class. ?>
            <circle cx="31" cy="35" r="1.700" fill="#2d2a3e"/>
            <circle cx="41" cy="35" r="1.700" fill="#2d2a3e"/>
            <path d="M31 41c1.800 2.600 8.200 2.600 10 0" fill="none" stroke="#2d2a3e" stroke-width="1.600" stroke-linecap="round"/>
        </svg>
        <?php
    }

    public function renderExceptionsArt(RenderContext $context)
    {
        $rows = [
            ['label' => __('Editors', 'press-permit-core'), 'access' => 'on'],
            ['label' => __('Contributors', 'press-permit-core'), 'access' => 'on'],
            ['label' => __('Guests', 'press-permit-core'), 'access' => 'off'],
        ];
        ?>
        <div class="ppw-permissions-card">
            <div class="ppw-permissions-card-head">
                <span class="ppw-permissions-lock dashicons dashicons-lock"></span>
                <span class="ppw-permissions-card-title">
                    <strong><?php esc_html_e('Members area', 'press-permit-core'); ?></strong>
                    <span><?php esc_html_e('Who can read this page', 'press-permit-core'); ?></span>
                </span>
            </div>
            <?php foreach ($rows as $row) : ?>
                <div class="ppw-permissions-row">
                    <span class="ppw-permissions-avatar"></span>
                    <span class="ppw-permissions-name"><?php echo esc_html($row['label']); ?></span>
                    <span class="ppw-permissions-switch is-<?php echo esc_attr($row['access']); ?>"></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------- post types */

    public function renderPostTypes(RenderContext $context)
    {
        $types = WelcomePostTypes::choices();
        $locked = WelcomePostTypes::locked();
        $enabled = WelcomePostTypes::enabled();
        $nextUrl = $this->stepUrlFor('teaser', $context);
        ?>
        <div class="ppw-heading">
            <h1><?php esc_html_e('Choose the content you want to control', 'press-permit-core'); ?></h1>
            <p class="ppw-lede"><?php esc_html_e('Select the post types Permissions should manage. You can change this later in Settings.', 'press-permit-core'); ?></p>
        </div>
        <?php if (! WelcomePostTypes::canSave()) : ?>
            <div class="ppw-setup-actions is-centered">
                <p><?php esc_html_e('A network administrator manages this setting.', 'press-permit-core'); ?></p>
                <a class="ppw-btn ppw-btn-primary ppw-btn-lg" href="<?php echo esc_url($nextUrl); ?>"><?php esc_html_e('Continue', 'press-permit-core'); ?> <span class="ppw-btn-arrow" aria-hidden="true">&rarr;</span></a>
            </div>
        <?php else : ?>
            <form class="ppw-post-types" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::POST_TYPES_ACTION); ?>" />
                <?php wp_nonce_field(self::POST_TYPES_NONCE); ?>
                <fieldset>
                    <legend class="ppw-sr-only"><?php esc_html_e('Post types to manage', 'press-permit-core'); ?></legend>
                    <div class="ppw-type-grid">
                        <?php foreach ($types as $name => $type) : ?>
                            <?php
                            $isLocked = ! empty($locked[$name]);
                            $hint = $this->postTypeHint($name);
                            ?>
                            <label class="ppw-type-choice">
                                <input type="checkbox" name="ppw_post_types[<?php echo esc_attr($name); ?>]" value="1" <?php checked($isLocked || ! empty($enabled[$name])); ?> <?php disabled($isLocked); ?> />
                                <span class="ppw-type-card">
                                    <span class="ppw-type-icon dashicons <?php echo esc_attr($this->postTypeIcon($type)); ?>" aria-hidden="true"></span>
                                    <span class="ppw-type-copy">
                                        <strong><?php echo esc_html(WelcomePostTypes::label($type)); ?></strong>
                                        <?php if ($isLocked) : ?>
                                            <small><?php esc_html_e('Always on', 'press-permit-core'); ?></small>
                                        <?php elseif ($hint) : ?>
                                            <small><?php echo esc_html($hint); ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <span class="ppw-type-check" aria-hidden="true"></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <p class="ppw-setup-note"><?php esc_html_e('Saving applies this setting now. Taxonomies are not changed.', 'press-permit-core'); ?></p>
                <div class="ppw-setup-actions">
                    <button class="ppw-btn ppw-btn-primary ppw-btn-lg" type="submit"><?php esc_html_e('Save and continue', 'press-permit-core'); ?> <span class="ppw-btn-arrow" aria-hidden="true">&rarr;</span></button>
                    <a class="ppw-text-link" href="<?php echo esc_url($nextUrl); ?>"><?php esc_html_e('Continue without saving', 'press-permit-core'); ?></a>
                </div>
            </form>
        <?php endif; ?>
        <?php
    }

    public function savePostTypes()
    {
        if (! isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
            wp_die(esc_html__('Invalid request method.', 'press-permit-core'), '', ['response' => 405]);
        }

        check_admin_referer(self::POST_TYPES_NONCE);

        if (! current_user_can('pp_manage_settings')) {
            wp_die(esc_html__('You are not allowed to access this page.', 'press-permit-core'), '', ['response' => 403]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- nonce checked above; each name is validated against the offered post types.
        $posted = isset($_POST['ppw_post_types']) && is_array($_POST['ppw_post_types']) ? wp_unslash($_POST['ppw_post_types']) : [];

        WelcomePostTypes::save($posted);

        wp_safe_redirect($this->stepUrlFor('teaser'));
        exit;
    }

    private function postTypeIcon($type)
    {
        $icons = ['post' => 'dashicons-admin-post', 'page' => 'dashicons-admin-page', 'attachment' => 'dashicons-admin-media'];

        if (isset($icons[$type->name])) {
            return $icons[$type->name];
        }

        return (is_string($type->menu_icon) && 0 === strpos($type->menu_icon, 'dashicons-'))
            ? sanitize_html_class($type->menu_icon)
            : 'dashicons-admin-post';
    }

    private function postTypeHint($name)
    {
        $hints = [
            'post' => __('Articles and news', 'press-permit-core'),
            'page' => __('Static pages', 'press-permit-core'),
            'attachment' => __('Images and files', 'press-permit-core'),
        ];

        return isset($hints[$name]) ? $hints[$name] : '';
    }

    /* -------------------------------------------------------------------- teaser */

    public function renderTeaser(RenderContext $context)
    {
        $canConfigure = current_user_can('pp_manage_teaser') && presspermit()->moduleActive('teaser');
        $openInNewTab = __('opens in a new tab, this guide stays here', 'press-permit-core');
        ?>
        <div class="ppw-lesson">
            <div class="ppw-lesson-copy">
                <h1><?php esc_html_e('Give visitors a preview with the Teaser', 'press-permit-core'); ?></h1>
                <p class="ppw-lede"><?php esc_html_e('When someone cannot read a post, show an excerpt or your own message instead of hiding it.', 'press-permit-core'); ?></p>
                <ol class="ppw-callouts">
                    <li><?php esc_html_e('Show a short excerpt or a custom message.', 'press-permit-core'); ?></li>
                    <li><?php esc_html_e('Invite visitors to log in or request access.', 'press-permit-core'); ?></li>
                    <li><?php esc_html_e('Choose which post types use the Teaser.', 'press-permit-core'); ?></li>
                </ol>
                <p class="ppw-link-out">
                    <?php if ($canConfigure) : ?>
                        <a class="ppw-btn ppw-btn-outline" href="<?php echo esc_url($this->teaserUrl()); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Configure the Teaser', 'press-permit-core'); ?> <span aria-hidden="true">&nearr;</span><span class="ppw-sr-only"> <?php echo esc_html($context->config()->label('new_tab')); ?></span></a>
                        <span class="ppw-link-note"><?php echo esc_html($openInNewTab); ?></span>
                    <?php else : ?>
                        <span class="ppw-link-note"><?php esc_html_e('The Teaser module is switched off. You can turn it on under Settings, in the Features tab.', 'press-permit-core'); ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="ppw-lesson-art">
                <div class="ppw-teaser-mock" aria-hidden="true">
                    <div class="ppw-mock-bar"><i></i><i></i><i></i><span><?php esc_html_e('yoursite.com/members-guide', 'press-permit-core'); ?></span></div>
                    <div class="ppw-mock-page">
                        <span class="ppw-mock-tag"><?php esc_html_e('Members only', 'press-permit-core'); ?></span>
                        <strong class="ppw-mock-title"><?php esc_html_e('The complete member guide', 'press-permit-core'); ?></strong>
                        <p class="ppw-mock-excerpt"><?php esc_html_e('A short excerpt appears here, so visitors can see what they are missing.', 'press-permit-core'); ?></p>
                        <span class="ppw-mock-lines"><i></i><i></i><i></i></span>
                        <div class="ppw-mock-gate">
                            <span class="ppw-mock-lock dashicons dashicons-lock"></span>
                            <span class="ppw-mock-gate-copy">
                                <strong><?php esc_html_e('This content is for members.', 'press-permit-core'); ?></strong>
                                <span><?php esc_html_e('Log in to keep reading.', 'press-permit-core'); ?></span>
                            </span>
                            <span class="ppw-mock-button"><?php esc_html_e('Log in', 'press-permit-core'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------- capabilities */

    /**
     * Offers the role editor beside the email form: manage roles when it is active, otherwise install or activate it.
     */
    public function renderCapabilities(RenderContext $context)
    {
        $url = '';
        $label = '';
        $body = __('Install the PublishPress Capabilities plugin to create new roles for your users.', 'press-permit-core');

        if (defined('PUBLISHPRESS_CAPS_VERSION')) {
            if (current_user_can('manage_capabilities')) {
                $url = admin_url('admin.php?page=pp-capabilities-roles');
                $label = __('Manage roles', 'press-permit-core');
                $body = __('Use the PublishPress Capabilities plugin to create new roles for your users.', 'press-permit-core');
            }
        } else {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';

            foreach (array_keys(get_plugins()) as $file) {
                if ('capsman-enhanced.php' === basename($file) && current_user_can('activate_plugins')) {
                    $url = wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin=' . rawurlencode($file)), 'activate-plugin_' . $file);
                    $label = __('Activate PublishPress Capabilities', 'press-permit-core');
                    $body = __('PublishPress Capabilities is installed. Activate it to create new roles for your users.', 'press-permit-core');
                    break;
                }
            }

            if (! $url && current_user_can('install_plugins') && wp_is_file_mod_allowed('plugin_install')) {
                $url = self_admin_url('plugin-install.php?tab=plugin-information&plugin=capability-manager-enhanced');
                $label = __('Install PublishPress Capabilities', 'press-permit-core');
            }
        }

        if (! $url) {
            return;
        }
        ?>
        <aside class="ppw-capabilities-promo">
            <span class="ppw-capabilities-icon dashicons dashicons-groups" aria-hidden="true"></span>
            <div class="ppw-capabilities-copy">
                <strong><?php esc_html_e('Need to create new roles?', 'press-permit-core'); ?></strong>
                <span><?php echo esc_html($body); ?></span>
            </div>
            <a class="ppw-btn ppw-btn-outline" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label); ?><span class="ppw-sr-only"> <?php echo esc_html($context->config()->label('new_tab')); ?></span></a>
        </aside>
        <?php
    }

    /* ------------------------------------------------------------------- helpers */

    /**
     * URL of a guide step by ID. Falls back to counting our own steps when the loaded library predates stepUrlForId().
     *
     * @param string             $id
     * @param RenderContext|null $context Omit when called outside a render, such as from the save handler.
     * @return string
     */
    private function stepUrlFor($id, $context = null)
    {
        if ($context && method_exists($context, 'stepUrlForId')) {
            return $context->stepUrlForId($id);
        }

        if ($this->controller && method_exists($this->controller, 'stepUrlForId')) {
            return $this->controller->stepUrlForId($id);
        }

        $position = array_search($id, ['welcome', 'concepts', 'content', 'teaser', 'finish'], true);

        return $this->controller->stepUrl(false === $position ? 1 : $position + 1);
    }

    private function postTypesSettingsUrl()
    {
        return admin_url('admin.php?page=presspermit-settings&pp_tab=core');
    }

    private function teaserUrl()
    {
        if (current_user_can('pp_manage_teaser') && presspermit()->moduleActive('teaser')) {
            return admin_url('admin.php?page=presspermit-posts-teaser');
        }

        return admin_url('admin.php?page=presspermit-settings&pp_tab=modules');
    }

    private function urlPath()
    {
        return defined('PRESSPERMIT_URLPATH') ? PRESSPERMIT_URLPATH : plugins_url('', PRESSPERMIT_FILE);
    }

    private function asset($file)
    {
        return [
            'path' => PRESSPERMIT_ABSPATH . '/common/img/welcome/' . $file,
            'url' => $this->urlPath() . '/common/img/welcome/' . $file,
        ];
    }

    private function canManageGroups()
    {
        return current_user_can('pp_manage_permissions')
            || current_user_can('pp_edit_groups')
            || presspermit()->groups()->anyGroupManager();
    }

    private function enabledPostTypeCount()
    {
        $enabled = presspermit()->getEnabledPostTypes();
        return is_array($enabled) ? count($enabled) : 0;
    }

    private function exceptionCount()
    {
        global $wpdb;

        if (null !== $this->exceptionCount) {
            return $this->exceptionCount;
        }

        if (empty($wpdb->ppc_exceptions) || empty($wpdb->ppc_exception_items)) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom Permissions tables; memoized per request to avoid stale checklist state after permission changes.
        $this->exceptionCount = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT i.item_id) FROM $wpdb->ppc_exception_items AS i"
            . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id"
            . " WHERE e.for_item_source = 'post' AND e.via_item_source = 'post'"
            . ' AND i.item_id > 0'
        );

        return $this->exceptionCount;
    }

    private function postPermissionsUrl()
    {
        $enabled = (array) presspermit()->getEnabledPostTypes();
        $postTypes = [];

        foreach ($enabled as $key => $value) {
            $postType = is_string($value) ? $value : (is_string($key) ? $key : '');

            if ($postType && ! in_array($postType, $postTypes, true)) {
                $postTypes[] = $postType;
            }
        }

        foreach (['post', 'page'] as $preferred) {
            if (in_array($preferred, $postTypes, true)) {
                return 'post' === $preferred
                    ? admin_url('edit.php')
                    : add_query_arg('post_type', 'page', admin_url('edit.php'));
            }
        }

        foreach ($postTypes as $postType) {
            if (post_type_exists($postType)) {
                return add_query_arg('post_type', sanitize_key($postType), admin_url('edit.php'));
            }
        }

        return $this->postTypesSettingsUrl();
    }
}

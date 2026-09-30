<?php

namespace PublishPress\Permissions\UI;

use PublishPress\Welcome\ChecklistTask;
use PublishPress\Welcome\ExperienceConfig;
use PublishPress\Welcome\RenderContext;
use PublishPress\Welcome\Step\ConceptsStep;
use PublishPress\Welcome\Step\FinishStep;
use PublishPress\Welcome\Step\HeroStep;
use PublishPress\Welcome\Step\LessonStep;
use PublishPress\Welcome\WelcomeController;

/**
 * Permissions-specific content and completion rules for the shared welcome UI.
 */
class WelcomeExperience
{
    /** @var WelcomeController|null */
    private $controller;

    public function __construct()
    {
        if (! class_exists(WelcomeController::class)) {
            return;
        }

        $config = new ExperienceConfig(
            [
                'id' => 'presspermit',
                'page_slug' => 'presspermit-welcome',
                'parent_slug' => 'presspermit-groups',
                'page_title' => __('Welcome to PublishPress Permissions', 'press-permit-core'),
                'menu_title' => __('Welcome', 'press-permit-core'),
                'capability' => 'pp_manage_settings',
                'accent_color' => '#655997',
                'styles' => [
                    [
                        'handle' => 'presspermit-welcome',
                        'url' => PRESSPERMIT_URLPATH . '/common/css/welcome-permissions.css',
                        'version' => PRESSPERMIT_VERSION,
                    ],
                ],
                'enrollment_option' => 'presspermit_welcome_enrolled',
                'redirect_option' => 'presspermit_welcome_redirect_pending',
                'user_option_prefix' => 'presspermit_welcome',
                'notice_title' => __('Permissions is active. Want a two minute tour?', 'press-permit-core'),
                'notice_body' => __('We will show you where to control who reads and edits your content. Nothing gets changed.', 'press-permit-core'),
                'checklist_pages' => ['presspermit-groups'],
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
                    'reassurance' => __('This guide does not change any setting on your site.', 'press-permit-core'),
                ],
            ]
        );

        $this->controller = new WelcomeController($config, $this->steps(), $this->tasks());
        $this->controller->register();
    }

    private function steps()
    {
        $openInNewTab = __('opens in a new tab, this guide stays here', 'press-permit-core');

        $steps = [
            new HeroStep(
                'welcome',
                [
                    'title' => __('Welcome to PublishPress Permissions', 'press-permit-core'),
                    'lede' => __('Control who can read and edit posts, pages, categories, media, and other content across your WordPress site.', 'press-permit-core'),
                    'body' => __('Six short screens show you where everything lives. Nothing changes on your site while you read.', 'press-permit-core'),
                    'art_callback' => [$this, 'renderHeroArt'],
                    'secondary_action' => [
                        'label' => __('Skip, take me to Settings', 'press-permit-core'),
                        'url' => admin_url('admin.php?page=presspermit-settings'),
                    ],
                ]
            ),
            new ConceptsStep(
                'concepts',
                [
                    'title' => __('Three ideas to know', 'press-permit-core'),
                    'lede' => __('Get these right and the rest of the plugin makes sense.', 'press-permit-core'),
                    'concepts' => [
                        [
                            'name' => __('Roles', 'press-permit-core'),
                            'body' => __('What a person can do everywhere on the site. Roles come from WordPress.', 'press-permit-core'),
                            'slug' => 'concept-roles',
                            'asset' => $this->asset('concept-roles.svg'),
                        ],
                        [
                            'name' => __('Groups', 'press-permit-core'),
                            'body' => __('A bundle of people you give the same access to. Easier than editing every user.', 'press-permit-core'),
                            'slug' => 'concept-groups',
                            'asset' => $this->asset('concept-groups.svg'),
                        ],
                        [
                            'name' => __('Exceptions', 'press-permit-core'),
                            'body' => __('An override for one post, page or category. An exception beats the role.', 'press-permit-core'),
                            'slug' => 'concept-exceptions',
                            'asset' => $this->asset('concept-exceptions.svg'),
                        ],
                    ],
                    'aside' => __('Rule of thumb: roles say what a person can do everywhere. Exceptions override that for one post or one category.', 'press-permit-core'),
                ]
            ),
            new LessonStep(
                'content',
                [
                    'title' => __('Choose the content you want to control', 'press-permit-core'),
                    'lede' => __('Permissions only filters the post types and taxonomies you switch on. Everything else behaves like standard WordPress.', 'press-permit-core'),
                    'asset' => $this->asset('step-general.svg'),
                    'asset_alt' => __('The General tab of the Permissions settings screen', 'press-permit-core'),
                    'callouts' => [
                        __('Open Permissions, then Settings.', 'press-permit-core'),
                        __('On the General tab, find Post Types and Taxonomies.', 'press-permit-core'),
                        __('Tick the post types and taxonomies you want to manage.', 'press-permit-core'),
                    ],
                    'action' => [
                        'label' => __('Open Settings', 'press-permit-core'),
                        'url' => admin_url('admin.php?page=presspermit-settings&pp_tab=core'),
                        'note' => $openInNewTab,
                    ],
                ]
            ),
            new LessonStep(
                'groups',
                [
                    'title' => __('Create a Permission Group', 'press-permit-core'),
                    'lede' => __('A Permission Group lets you give the same access to several users at once. Start with a clear name and description.', 'press-permit-core'),
                    'asset' => $this->asset('step-groups.svg'),
                    'asset_alt' => __('The Permissions User Groups screen', 'press-permit-core'),
                    'callouts' => [
                        __('Open Permissions to reach User Groups.', 'press-permit-core'),
                        __('Click Add New Group.', 'press-permit-core'),
                        __('Name and describe the group on the next screen, then create it.', 'press-permit-core'),
                    ],
                    'action' => [
                        'label' => $this->canManageGroups() ? __('Open Permissions', 'press-permit-core') : '',
                        'url' => admin_url('admin.php?page=presspermit-groups'),
                        'note' => $openInNewTab,
                    ],
                ]
            ),
            new LessonStep(
                'access',
                [
                    'title' => __('Add members and fine-tune access', 'press-permit-core'),
                    'lede' => __('Add users to your Permission Group, set specific access for a post type, and use a post or page Permissions panel when one item needs an override.', 'press-permit-core'),
                    'asset' => $this->asset('step-post-edit.svg'),
                    'asset_alt' => __('Group members, specific permissions, and the post Permissions panel', 'press-permit-core'),
                    'callouts' => [
                        __('Add users in the Group Members box.', 'press-permit-core'),
                        __('Add Specific Permissions for a post type.', 'press-permit-core'),
                        __('Use the post/page Permissions panel for a one-off override.', 'press-permit-core'),
                    ],
                    'action' => [
                        'label' => __('Open a page to try it', 'press-permit-core'),
                        'url' => admin_url('edit.php?post_type=page'),
                        'note' => $openInNewTab,
                    ],
                ]
            ),
            new FinishStep(
                'finish',
                [
                    'title' => __('That is the whole plugin', 'press-permit-core'),
                    'lede' => __('Nothing on your site changed. Here is what to do next:', 'press-permit-core'),
                    'next_steps_title' => __('What to do next', 'press-permit-core'),
                    'next_steps' => [
                        ['label' => __('Choose the content you want to control.', 'press-permit-core'), 'url' => admin_url('admin.php?page=presspermit-settings&pp_tab=core')],
                        ['label' => __('Create your first permission group.', 'press-permit-core'), 'url' => admin_url('admin.php?page=presspermit-group-new')],
                        ['label' => __('Set permissions on one post or page.', 'press-permit-core'), 'url' => $this->postPermissionsUrl()],
                    ],
                    'primary_action' => [
                        'label' => __('Go to Permissions', 'press-permit-core'),
                        'url' => admin_url('admin.php?page=' . ($this->canManageGroups() ? 'presspermit-groups' : 'presspermit-settings')),
                    ],
                    'promo' => presspermit()->isPro() ? [] : [
                        'title' => __('If you need more control', 'press-permit-core'),
                        'features' => [
                            __('Editing permissions per post type', 'press-permit-core'),
                            __('Media and file access control', 'press-permit-core'),
                            __('Membership plugin integrations', 'press-permit-core'),
                            __('Custom post status permissions', 'press-permit-core'),
                            __('Sync user posts', 'press-permit-core'),
                            __('Priority support', 'press-permit-core'),
                        ],
                        'url' => 'https://publishpress.com/permissions/',
                        'label' => __('See Pro features', 'press-permit-core'),
                    ],
                ]
            ),
        ];

        return $steps;
    }

    private function tasks()
    {
        return [
            new ChecklistTask(
                'post-types',
                __('Choose the content you want to control', 'press-permit-core'),
                admin_url('admin.php?page=presspermit-settings&pp_tab=core'),
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
                'group',
                __('Create your first permission group', 'press-permit-core'),
                admin_url('admin.php?page=presspermit-groups'),
                function () {
                    return $this->customGroupCount() > 0;
                },
                function () {
                    $count = $this->customGroupCount();
                    return sprintf(
                        _n('%s group', '%s groups', $count, 'press-permit-core'),
                        number_format_i18n($count)
                    );
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

    public function renderHeroArt(RenderContext $context)
    {
        ?>
        <div class="ppw-permissions-hero-badge">
            <span class="dashicons dashicons-unlock"></span>
            <span class="ppw-permissions-hero-bars"><span></span><span></span><span></span></span>
        </div>
        <?php
    }

    private function asset($file)
    {
        return [
            'path' => PRESSPERMIT_ABSPATH . '/common/img/welcome/' . $file,
            'url' => PRESSPERMIT_URLPATH . '/common/img/welcome/' . $file,
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

    private function customGroupCount()
    {
        global $wpdb;

        if (empty($wpdb->pp_groups)) {
            return 0;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->pp_groups WHERE metagroup_id = ''");
    }

    private function exceptionCount()
    {
        global $wpdb;

        if (empty($wpdb->ppc_exceptions) || empty($wpdb->ppc_exception_items)) {
            return 0;
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT i.item_id) FROM $wpdb->ppc_exception_items AS i"
            . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id"
            . " WHERE e.for_item_source = 'post' AND e.via_item_source = 'post'"
            . ' AND i.item_id > 0'
        );
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

        return admin_url('admin.php?page=presspermit-settings&pp_tab=core');
    }
}

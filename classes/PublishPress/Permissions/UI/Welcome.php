<?php

namespace PublishPress\Permissions\UI;

class Welcome
{
    public function __construct()
    {
        if (!current_user_can('pp_manage_settings')) {
            wp_die(esc_html(PWP::__wp('Cheatin&#8217; uh?')));
        }

        $can_manage_groups = current_user_can('pp_manage_permissions') || current_user_can('pp_edit_groups') || presspermit()->groups()->anyGroupManager();
        $settings_url = admin_url('admin.php?page=presspermit-settings');
        $groups_url = admin_url('admin.php?page=presspermit-groups');
        $post_edit_url = admin_url('post-new.php');
        ?>
        <div class="pressshack-admin-wrapper wrap" id="pp-welcome-wrapper">
            <style>
                #pp-welcome-wrapper .pp-welcome-grid {display:grid;gap:20px;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));margin-top:24px;}
                #pp-welcome-wrapper .pp-welcome-hero {background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:32px;box-shadow:0 1px 2px rgba(0,0,0,.04);}
                #pp-welcome-wrapper .pp-welcome-actions {display:flex;flex-wrap:wrap;gap:12px;margin-top:20px;}
                #pp-welcome-wrapper .pp-welcome-card {background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:24px;box-shadow:0 1px 2px rgba(0,0,0,.04);}
                #pp-welcome-wrapper .pp-welcome-card h2 {margin-top:0;}
                #pp-welcome-wrapper .pp-welcome-card ul {list-style:disc;margin:12px 0 0 20px;}
                #pp-welcome-wrapper .pp-welcome-card li {margin-bottom:10px;}
            </style>

            <div class="pp-welcome-hero">
                <h1><?php esc_html_e('Welcome to PublishPress Permissions', 'press-permit-core'); ?></h1>
                <p><?php esc_html_e('Control who can read and edit posts, pages, categories, media, and other content across your WordPress site.', 'press-permit-core'); ?></p>

                <div class="pp-welcome-actions">
                    <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary"><?php esc_html_e('Open Settings', 'press-permit-core'); ?></a>
                    <?php if ($can_manage_groups) : ?>
                    <a href="<?php echo esc_url($groups_url); ?>" class="button"><?php esc_html_e('Create a Group', 'press-permit-core'); ?></a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($post_edit_url); ?>" class="button"><?php esc_html_e('Edit Post Permissions', 'press-permit-core'); ?></a>
                    <a href="<?php echo esc_url($settings_url); ?>" class="button-link"><?php esc_html_e('Skip onboarding', 'press-permit-core'); ?></a>
                </div>
            </div>

            <div class="pp-welcome-grid">
                <div class="pp-welcome-card">
                    <h2><?php esc_html_e('What you can do', 'press-permit-core'); ?></h2>
                    <ul>
                        <li><?php esc_html_e('Control who can view posts, pages, categories, tags, and custom content.', 'press-permit-core'); ?></li>
                        <li><?php esc_html_e('Limit who can edit content, media, and other users\' work.', 'press-permit-core'); ?></li>
                        <li><?php esc_html_e('Organize access with roles, individual users, and custom groups.', 'press-permit-core'); ?></li>
                    </ul>
                </div>

                <div class="pp-welcome-card">
                    <h2><?php esc_html_e('Recommended first steps', 'press-permit-core'); ?></h2>
                    <ul>
                        <li><?php esc_html_e('Open Settings and enable the post types or features you want to manage.', 'press-permit-core'); ?></li>
                        <li><?php esc_html_e('Create a permission group for the people who need similar access.', 'press-permit-core'); ?></li>
                        <li><?php esc_html_e('Open a post or page and set its read or edit permissions.', 'press-permit-core'); ?></li>
                    </ul>
                </div>

                <div class="pp-welcome-card">
                    <h2><?php esc_html_e('Common setup goals', 'press-permit-core'); ?></h2>
                    <ul>
                        <li><?php esc_html_e('Hide selected content from logged out visitors.', 'press-permit-core'); ?></li>
                        <li><?php esc_html_e('Prevent authors and editors from seeing or editing other users\' content.', 'press-permit-core'); ?></li>
                        <li><?php esc_html_e('Control which files appear in the Media Library.', 'press-permit-core'); ?></li>
                    </ul>
                </div>

                <div class="pp-welcome-card">
                    <h2><?php esc_html_e('Helpful resources', 'press-permit-core'); ?></h2>
                    <ul>
                        <li><a href="https://publishpress.com/knowledge-base/viewing-permissions/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('How to control viewing permissions', 'press-permit-core'); ?></a></li>
                        <li><a href="https://publishpress.com/knowledge-base/groups/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('How to use permission groups', 'press-permit-core'); ?></a></li>
                        <li><a href="https://publishpress.com/knowledge-base/permissions-media-files/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('How to manage media access', 'press-permit-core'); ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
}

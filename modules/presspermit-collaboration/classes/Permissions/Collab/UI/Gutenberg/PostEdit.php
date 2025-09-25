<?php
namespace PublishPress\Permissions\Collab\UI\Gutenberg;

use PublishPress\Permissions\Collab;
use PublishPress\PWP;

class PostEdit
{
    function __construct()
    {
        // Gutenberg Block Editor support
        //
        // This script executes on the 'init' action if is_admin() and $pagenow is 'post-new.php' or 'post.php' and the block editor is active.
        //

        add_action('enqueue_block_editor_assets', [$this, 'act_object_guten_scripts']);
    }

    public function act_object_guten_scripts()
    {
        // Administrators don't need this script
        if (presspermit()->isContentAdministrator()) {
            return;
        }

        $args = [];
        $post_type = PWP::findPostType();

        if (!Collab::userCanAssociateMain($post_type)) {
            if ($post_id = PWP::getPostID()) {
                if (!get_post_field('post_parent', $post_id)) {
                    return;
                }
            }

            $args['blockMainPage'] = true;
            $args['selectCaption'] = esc_html__('(select...)', 'press-permit-core');
        } else {
            return;
        }

        $args['disableRecaption'] = is_plugin_active('gutenberg/gutenberg.php');

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-object-edit', PRESSPERMIT_COLLAB_URLPATH . "/common/js/post-block-edit{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_COLLAB_VERSION, true);
        wp_localize_script('presspermit-object-edit', 'ppCollabEdit', $args);

        global $pagenow;
        if (in_array($pagenow, ['post-new.php'])) {
            // Pass default_privacy setting to JavaScript for Gutenberg
            $default_privacy = presspermit()->getTypeOption('default_privacy', $post_type);
            $force_default_privacy = presspermit()->getTypeOption('force_default_privacy', $post_type) == '1' ? true : false;
            
            // Prepare translation strings
            $translations = [
                'visibilityLocked' => esc_html__('Visibility Locked:', 'press-permit-core'),
                'visibilitySetTo' => esc_html__('Post visibility is set to', 'press-permit-core'),
                'cannotChange' => esc_html__('and cannot be changed due to admin settings.', 'press-permit-core'),
                'contactAdmin' => esc_html__('Contact your administrator to modify this setting.', 'press-permit-core'),
                'postVisibilityLocked' => esc_html__('Post Visibility Locked', 'press-permit-core'),
                'visibilityLockedTo' => esc_html__('This post\'s visibility is locked to:', 'press-permit-core'),
                'adminConfigured' => esc_html__('The administrator has configured this post type to enforce a specific visibility setting.', 'press-permit-core'),
                'lockedByAdmin' => esc_html__('LOCKED BY ADMIN', 'press-permit-core'),
                'tooltipLocked' => esc_html__('This setting is locked by administrator configuration and cannot be changed.', 'press-permit-core'),
            ];
            
            wp_localize_script('presspermit-object-edit', 'ppEditorConfig', [
                'defaultPrivacy' => $default_privacy, 
                'forceDefaultPrivacy' => $force_default_privacy,
                'translations' => $translations
            ]);
        }
    }
}

<?php

namespace PublishPress\Permissions\UI\Dashboard;

require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemEdit.php');

class PostEdit
{
    var $item_exceptions_ui = false;

    public function __construct()
    {
        wp_enqueue_style('presspermit-item-edit', PRESSPERMIT_URLPATH . '/common/css/item-edit.css', [], PRESSPERMIT_VERSION);

        // Enqueue tabbed metabox styles and scripts if enabled
        if (presspermit()->getOption('use_tabbed_metabox')) {
            // Always use our own Select2 version with unique handles to avoid conflicts with other plugins (e.g., WooCommerce)
            if (!wp_style_is('presspermit-select2-css', 'registered')) {
                wp_register_style('presspermit-select2-css', PRESSPERMIT_URLPATH . '/common/lib/select2-4.0.13/css/select2.min.css', array(), '4.0.13', 'screen');
            }
            if (!wp_script_is('presspermit-select2-js', 'registered')) {
                wp_register_script('presspermit-select2-js', PRESSPERMIT_URLPATH . '/common/lib/select2-4.0.13/js/select2.full.min.js', ['jquery'], '4.0.13', true);
            }
            wp_enqueue_style('presspermit-select2-css');
            wp_enqueue_script('presspermit-select2-js');
            
            wp_enqueue_style('presspermit-item-edit-tabbed', PRESSPERMIT_URLPATH . '/common/css/item-edit-tabbed.css', ['presspermit-select2-css'], PRESSPERMIT_VERSION);
            
            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
            wp_enqueue_script('presspermit-item-edit-tabbed', PRESSPERMIT_URLPATH . "/common/js/item-edit-tabbed{$suffix}.js", ['jquery', 'presspermit-select2-js'], PRESSPERMIT_VERSION, true);
            
            // Localize script with AJAX URL and nonce for user search
            wp_localize_script('presspermit-item-edit-tabbed', 'PPAgentSelect', [
                'ajaxurl' => wp_nonce_url(admin_url(''), 'pp-ajax'),
                'ajaxhandler' => 'got_ajax_listbox'
            ]);
            
            // Localize script with translated messages
            wp_localize_script('presspermit-item-edit-tabbed', 'ppPermissions', [
                'bulkActionNotAvailableNonUsers' => esc_html__("Editing can't be granted to non-users.", 'press-permit-core')
            ]);
        }

        add_action('admin_head', [$this, 'actAdminHead']);

        add_action('admin_menu', [$this, 'actAddMetaBoxes']);
        add_action('do_meta_boxes', [$this, 'actPrepMetaboxes']);

        add_action('admin_print_scripts', ['\PublishPress\Permissions\UI\Dashboard\ItemEdit', 'scriptItemEdit']);

        add_action('admin_print_footer_scripts', [$this, 'actScriptEditParentLink']);
        add_action('admin_print_footer_scripts', [$this, 'actScriptForceAutosaveBeforeUpload']);

        do_action('presspermit_post_edit_ui');
    }

    public function initItemExceptionsUI()
    {
        if (empty($this->item_exceptions_ui)) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsUI.php');
            $this->item_exceptions_ui = new ItemExceptionsUI();
        }
    }

    public function actAdminHead()
    {
        $pp = presspermit();

        if (
            current_user_can('pp_manage_settings')
            && (!$pp->moduleActive('collaboration') || !class_exists('PublishPress\Statuses\StatusControl'))
            && $pp->getOption('display_extension_hints')
        ) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/HintsPostEdit.php');
            \PublishPress\Permissions\UI\HintsPostEdit::postStatusPromo();
        }
    }

    public function actAddMetaBoxes()
    {
        // ========= register WP-rendered metaboxes ============
        $post_type = PWP::findPostType();

        if (!current_user_can('pp_assign_roles') || apply_filters('presspermit_disable_exception_ui', false, 'post', PWP::getPostID(), $post_type)) {
            return;
        }

        $hidden_types = apply_filters('presspermit_hidden_post_types', []);

        if (!empty($hidden_types[$post_type])) {
            return;
        }

        $pp = presspermit();

        // Check if metabox is enabled for this post type
        $metabox_enabled = $pp->getOption("pp_enable_metabox_{$post_type}");
        if ($metabox_enabled === '0') {
            return; // Metabox is explicitly disabled
        }
        // If not set or set to '1', continue (default is enabled)

        $type_obj = get_post_type_object($post_type);

        if (!in_array($post_type, $pp->getEnabledPostTypes(['layer' => 'exceptions']), true)) {
            if (defined('PRESSPERMIT_LEGACY_POST_TYPE_ENABLE_METABOX') && !in_array($post_type, ['revision']) && $pp->getOption('display_hints')) {
                if ($type_obj->public) {
                    $omit_types = apply_filters('presspermit_unfiltered_post_types', ['wp_block']);

                    if (!in_array($post_type, $omit_types, true) && !defined("PP_NO_" . strtoupper($post_type) . "_EXCEPTIONS")) {
                        add_meta_box(
                            "pp_enable_type",
                            esc_html__('Permissions Settings', 'press-permit-core'),
                            [$this, 'drawSettingsUI'],
                            $post_type,
                            'advanced',
                            'default',
                            []
                        );
                    }
                }
            }
            return;
        }

        $ops = $pp->admin()->canSetExceptions('read', $post_type, ['via_item_source' => 'post', 'for_item_source' => 'post'])
            ? ['read' => true] : [];

        $operations = apply_filters('presspermit_item_edit_exception_ops', $ops, 'post', $post_type);

        // Check if tabbed metabox is enabled
        if ($pp->getOption('use_tabbed_metabox')) {
            // Register single tabbed metabox for all operations
            if (!empty($operations)) {
                $caption = sprintf(
                    esc_html__('Permissions: %s', 'press-permit-core'),
                    $type_obj->labels->singular_name
                );

                add_meta_box(
                    "pp_all_{$post_type}_exceptions",
                    $caption,
                    [$this, 'drawTabbedExceptionsUI'],
                    $post_type,
                    'advanced',
                    'default',
                    ['operations' => $operations]
                );
            }
        } else {
            // Original behavior: Register separate metabox for each operation
            foreach (array_keys($operations) as $op) {
                if ($op_obj = $pp->admin()->getOperationObject($op, $post_type)) {
                    switch ($op) {
                        case 'associate':
                            $caption = sprintf(
                                esc_html__('Permissions: Select this %s as Parent', 'press-permit-core'),
                                $type_obj->labels->singular_name
                            );

                            break;

                        case 'assign':
                            $caption = sprintf(
                                esc_html__('Permissions: Assign Terms to this %s', 'press-permit-core'),
                                $type_obj->labels->singular_name
                            );

                            break;

                        default:
                            $caption = sprintf(
                                esc_html__('Permissions: %s this %s', 'press-permit-core'),
                                esc_html($op_obj->label),
                                $type_obj->labels->singular_name
                            );
                    }

                    add_meta_box(
                        "pp_{$op}_{$post_type}_exceptions",
                        $caption,
                        [$this, 'drawExceptionsUI'],
                        $post_type,
                        'advanced',
                        'default',
                        ['op' => $op]
                    );
                }
            }
        }
    }

    public function actPrepMetaboxes()
    {
        global $pagenow;

        if ('edit.php' == $pagenow)
            return;

        static $been_here;
        if (isset($been_here)) return;
        $been_here = true;

        global $typenow;

        if (!in_array($typenow, presspermit()->getEnabledPostTypes(), true) || in_array($typenow, ['revision']))
            return;

        if (current_user_can('pp_assign_roles')) {
            $this->initItemExceptionsUI();

            $args = ['post_types' => (array)$typenow, 'hierarchical' => is_post_type_hierarchical($typenow)];  // via_src, for_src, via_type, item_id, args
            $this->item_exceptions_ui->data->loadExceptions('post', 'post', $typenow, PWP::getPostID(), $args);
        }
    }

    public function drawSettingsUI($object, $box)
    {
        if ($type_obj = get_post_type_object($object->post_type)) :
?>
            <label for="pp_enable_post_type"><input type="checkbox" name="pp_enable_post_type"
                    id="pp_enable_post_type" />
                <?php printf(esc_html__('enable custom permissions for %s', 'press-permit-core'), esc_html($type_obj->labels->name)); ?>
            </label>
        <?php
        endif;
    }

    // wrapper function so we don't have to load item_roles_ui class just to register the metabox
    public function drawExceptionsUI($object, $box)
    {
        if (empty($box['id']))
            return;

        $item_id = (!empty($object) && ('auto-draft' == $object->post_status)) ? 0 : $object->ID;

        $this->initItemExceptionsUI();
        $post_type = PWP::findPostType();  // $object->post_type gets reset to 'post' on some installations
        $args = [
            'via_item_source' => 'post',
            'for_item_source' => 'post',
            'for_item_type' => $post_type,
            'via_item_type' => $post_type,
            'item_id' => $item_id
        ];

        $this->item_exceptions_ui->drawExceptionsUI($box, $args);
    }

    /**
     * Draw tabbed exceptions UI with all operations in one metabox
     */
    public function drawTabbedExceptionsUI($object, $box)
    {
        if (empty($box['id']) || empty($box['args']['operations']))
            return;

        $item_id = (!empty($object) && ('auto-draft' == $object->post_status)) ? 0 : $object->ID;

        $this->initItemExceptionsUI();
        $post_type = PWP::findPostType();
        $pp = presspermit();
        
        // Build operations data array with captions
        $operations_data = [];
        $type_obj = get_post_type_object($post_type);
        
        foreach (array_keys($box['args']['operations']) as $op) {
            if ($op_obj = $pp->admin()->getOperationObject($op, $post_type)) {
                // Generate caption based on operation type
                switch ($op) {
                    case 'associate':
                        $caption = sprintf(
                            esc_html__('Select as Parent', 'press-permit-core')
                        );
                        break;

                    case 'assign':
                        $caption = esc_html__('Assign Terms', 'press-permit-core');
                        break;

                    default:
                        $caption = esc_html($op_obj->label);
                }

                $operations_data[] = [
                    'op' => $op,
                    'op_obj' => $op_obj,
                    'caption' => $caption
                ];
            }
        }
        
        $args = [
            'via_item_source' => 'post',
            'for_item_source' => 'post',
            'for_item_type' => $post_type,
            'via_item_type' => $post_type,
            'item_id' => $item_id
        ];

        $this->item_exceptions_ui->drawTabbedExceptionsUI($operations_data, $args);
    }

    public function actScriptEditParentLink()
    {
        global $post;

        if (
            empty($post) || !is_post_type_hierarchical($post->post_type) || !$post->post_parent
            || !current_user_can('edit_post', $post->post_parent)
        ) {
            return;
        }
        ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function($) {
                $('#pageparentdiv div.inside p').first().wrapInner('<a href="post.php?post=<?php echo esc_attr($post->post_parent); ?>&amp;action=edit">');
            });
            /* ]]> */
        </script>
        <?php
    } // end function

    public function actScriptForceAutosaveBeforeUpload()
    {  // under some configuration, it is necessary to pre-assign categories. Autosave accomplishes this by triggering save_post action handlers.
        if (!presspermit()->isUserUnfiltered()) : ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function($) {
                    $('#wp-content-media-buttons a').on('click', function() {
                        if ($('#post-status-info span.autosave-message').html() == '&nbsp;') {
                            autosave();
                        }
                    });
                });
                /* ]]> */
            </script>
<?php
        endif;
    } // end function
}

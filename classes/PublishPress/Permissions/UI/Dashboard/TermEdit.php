<?php

namespace PublishPress\Permissions\UI\Dashboard;

require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemEdit.php');

class TermEdit
{
    public $item_exceptions_ui = false;
    private $icon = '<svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="100" height="100" viewBox="0 0 50 50"><path d="M 25 2 C 12.264481 2 2 12.264481 2 25 C 2 37.735519 12.264481 48 25 48 C 37.735519 48 48 37.735519 48 25 C 48 12.264481 37.735519 2 25 2 z M 25 4 C 36.664481 4 46 13.335519 46 25 C 46 36.664481 36.664481 46 25 46 C 13.335519 46 4 36.664481 4 25 C 4 13.335519 13.335519 4 25 4 z M 25 11 A 3 3 0 0 0 25 17 A 3 3 0 0 0 25 11 z M 21 21 L 21 23 L 23 23 L 23 36 L 21 36 L 21 38 L 29 38 L 29 36 L 27 36 L 27 21 L 21 21 z"></path></svg>';

    public function __construct()
    {
        if (!did_action('presspermit_term_edit_ui')) {
	        wp_enqueue_script('post');
	        wp_enqueue_script('postbox');
	
	        wp_enqueue_style('presspermit-item-edit', PRESSPERMIT_URLPATH . '/common/css/item-edit.css', [], PRESSPERMIT_VERSION);
	        wp_enqueue_style('presspermit-term-edit', PRESSPERMIT_URLPATH . '/common/css/term-edit.css', [], PRESSPERMIT_VERSION);
	
	        add_action('admin_print_scripts', ['\PublishPress\Permissions\UI\Dashboard\ItemEdit', 'scriptItemEdit']);
            add_action('admin_print_scripts', [$this, 'compatStyles']);

	        add_action('admin_menu', [$this, 'actAddMetaBoxes']);
	
	        if ($taxonomy = PWP::REQUEST_key('taxonomy')) {
	            if (presspermit()->isTaxonomyEnabled($taxonomy)) {
	                add_action('admin_head', [$this, 'actScriptsWP']);

	                add_action("{$taxonomy}_edit_form", [$this, 'actExceptionEditUI']);

	            } elseif (defined('PRESSPERMIT_LEGACY_TAXONOMY_ENABLE_METABOX')) {
	                add_action("{$taxonomy}_edit_form", [$this, 'actTaxonomyEnableUI']);
	            }
	
	            if (!PWP::empty_REQUEST('pp_universal')) {
	                add_action("{$taxonomy}_edit_form", [$this, 'actUniversalExceptionsUIsupport']);
	            }
	        }
	
	        do_action('presspermit_term_edit_ui');
	    }
    }

    public function initItemExceptionsUI()
    {
        if (empty($this->item_exceptions_ui)) {
            include_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsUI.php');
            $this->item_exceptions_ui = new ItemExceptionsUI();
        }
    }

    // wrapper function so we don't have to load item_roles_ui class just to register the metabox
    public function drawExceptionsUI($term, $box)
    {
        static $done;

		if (empty($done)) {
			$done = [];
		}
	
        if (empty($box['id']) || !empty($done[$box['id']])) {
            return;
        }

        $done[$box['id']] = true;

        $this->initItemExceptionsUI();

        $for_item_type = (in_array($box['args']['op'], ['manage', 'associate'], true))
            ? $term->taxonomy
            : $box['args']['for_item_type'];

        $args = [
            'via_item_source' => 'term',
            'for_item_source' => 'post',
            'for_item_type' => $for_item_type,
            'via_item_type' => $term->taxonomy,
            'item_id' => $term->term_taxonomy_id
        ];

        $this->item_exceptions_ui->drawExceptionsUI($box, $args);
    }

    public function actAddMetaBoxes()
    {
        // ========= register WP-rendered metaboxes ============
        global $typenow;

        static $done;

        if (!empty($done)) {
            return;
        }

        $pp = presspermit();

        $taxonomy = PWP::REQUEST_key('taxonomy');

        if (!in_array($taxonomy, $pp->getEnabledTaxonomies(), true)) {
            return;
        }

        if ($tag_id = PWP::REQUEST_int('tag_ID')) {
            $tt_id = PWP::termidToTtid($tag_id, $taxonomy);
        } else {
            $tt_id = 0;
        }

        $post_type = (!PWP::empty_REQUEST('pp_universal')) ? '' : $typenow;

        $hidden_types = apply_filters('presspermit_hidden_post_types', []);
        $hidden_taxonomies = apply_filters('presspermit_hidden_taxonomies', ['post_status_core_wp_pp', 'pseudo_status_pp', 'post_visibility_pp']);

        if (!empty($hidden_taxonomies[$taxonomy]) || ($post_type && !empty($hidden_types[$post_type]))) {
            return;
        }

        if (
            !current_user_can('pp_assign_roles')
            || apply_filters('presspermit_disable_exception_ui', false, 'term', $tt_id, $post_type)
        ) {
            return;
        }

        $done = true;

        $tx = get_taxonomy($taxonomy);
        $type_obj = get_post_type_object($post_type);
        $register_type = ($post_type) ? $post_type : 'post';

        $ops = $pp->admin()->canSetExceptions(
            'read',
            $post_type,
            ['via_item_source' => 'term', 'via_item_type' => $taxonomy, 'for_item_source' => 'post']
        )
            ? ['read' => true] : [];

        $operations = apply_filters('presspermit_item_edit_exception_ops', $ops, 'post', $taxonomy, $post_type);

        $boxes = [];

        foreach (array_keys($operations) as $op) {
            if ($op_obj = $pp->admin()->getOperationObject($op, $post_type)) {
                if ('assign' == $op) {
                    $title = ($post_type)
                        ? sprintf(
                            esc_html__('Permissions: Assign this %2$s to %3$s', 'press-permit-core'),
                            $op_obj->label,
                            $tx->labels->singular_name,
                            $type_obj->labels->name
                        )
                        : sprintf(
                            esc_html__('Permissions: Assign this %2$s for All Post Types', 'press-permit-core'),
                            $op_obj->label,
                            $tx->labels->singular_name
                        );
                } elseif (in_array($op, ['read', 'edit'], true)) {
                    $title = ($post_type)
                        ? sprintf(
                            esc_html__('Permissions: %1$s %2$s in this %3$s', 'press-permit-core'),
                            $op_obj->label,
                            $type_obj->labels->name,
                            $tx->labels->singular_name
                        )
                        : sprintf(
                            esc_html__('Permissions: %1$s all content in this %2$s for All Post Types', 'press-permit-core'),
                            $op_obj->label,
                            $tx->labels->singular_name
                        );
                } else {
                    $title = ($post_type)
                        ? sprintf(
                            esc_html__('Permissions: %1$s %2$s in this %3$s', 'press-permit-core'),
                            $op_obj->label,
                            $type_obj->labels->name,
                            $tx->labels->singular_name
                        )
                        : sprintf(
                            esc_html__('Permissions: %1$s this %2$s', 'press-permit-core'),
                            $op_obj->label,
                            $tx->labels->singular_name
                        );
                }

                if (!$referer = wp_get_original_referer()) $referer = wp_get_referer();
    
                $url = esc_url_raw(
                    add_query_arg(
                        '_wp_original_http_referer',
                        urlencode($referer),
                        "term.php?taxonomy=$taxonomy&amp;tag_ID=$tag_id&amp;pp_universal=1"
                    )
                );
                $title_with_icon = $title;
                if(!empty($type_obj) && !empty($tx)) {
                    $title_with_icon = sprintf(
                        '<div>%s&nbsp;<div data-toggle="tooltip" class="click"><span class="dashicons dashicons-info"></span><div class="tooltip-text"><span>%s</span><i></i></div></div></div>',
                        esc_attr($title),
                        sprintf(
                            esc_html__('Displayed permissions are those assigned for the "%1$s" type. You can also %2$sdefine universal %3$s permissions which apply to all related post types%4$s.', 'press-permit-core'),
                            esc_html($type_obj->labels->singular_name),
                            '<a href="' . esc_url($url) . '"><strong>',
                            esc_html($tx->labels->singular_name),
                            '</strong></a>'
                        )
                    );
                }

                Arr::setElem($boxes, [$op, "pp_{$op}_{$post_type}_exceptions"]);
                $boxes[$op]["pp_{$op}_{$post_type}_exceptions"]['for_item_type'] = $post_type;
                $boxes[$op]["pp_{$op}_{$post_type}_exceptions"]['title'] = $title_with_icon;
            }
        }

        $boxes = apply_filters('presspermit_term_exceptions_metaboxes', $boxes, $taxonomy, $post_type);

        foreach ($boxes as $op => $boxes) {
            foreach ($boxes as $box_id => $_box) {
                add_meta_box(
                    $box_id,
                    $_box['title'],
                    [$this, 'drawExceptionsUI'],
                    $register_type,
                    'advanced',
                    'default',
                    ['for_item_type' => $_box['for_item_type'], 'op' => $op]
                );
            }
        }
    }

    private function prepMetaboxes()
    {
        global $tag_ID;
        if (empty($tag_ID)) {
            return;
        }

        static $been_here;
        if (isset($been_here)) {
            return;
        }

        $been_here = true;

        global $typenow;

        $post_type = (!PWP::empty_REQUEST('pp_universal')) ? '' : $typenow;
        $taxonomy = PWP::REQUEST_key('taxonomy');

        if (current_user_can('pp_assign_roles')) {
            $this->initItemExceptionsUI();

            $tt_id = PWP::termidToTtid($tag_ID, $taxonomy);

            $args = ['for_item_type' => $post_type, 'hierarchical' => is_taxonomy_hierarchical($post_type)];  // via_src, for_src, via_type, item_id, args
            $this->item_exceptions_ui->data->loadExceptions('term', 'post', $taxonomy, $tt_id, $args);

            do_action('presspermit_prep_metaboxes', 'term', $taxonomy, $tt_id);
        }
    }

    public function actExceptionEditUI($tag)
    {
        global $taxonomy, $typenow;  // only deal with post type which edit form was linked from?

        static $been_here;
        if (isset($been_here)) {
            return;
        }

        $been_here = true;

        if ($typenow && !in_array($typenow, presspermit()->getEnabledPostTypes(), true)) {
            return;
        }

        $post_type = (!PWP::empty_REQUEST('pp_universal')) ? '' : $typenow;

        if (
            !current_user_can('pp_assign_roles')
            || apply_filters('presspermit_disable_exception_ui', false, 'term', $tag->term_taxonomy_id, $post_type)
        ) {
            return;
        }
        
        // Create compatibility wrapper for third-party metaboxes
        $this->wrapIncompatibleMetaboxes($post_type, $tag);
        
        ?>
        <div id="poststuff" class="metabox-holder">
            <div id="post-body">
                <div id="post-body-content" style="position: relative;z-index: 1;">
                    <?php

                    require_once(ABSPATH . 'wp-admin/includes/meta-boxes.php');

                    $this->prepMetaboxes();

                    $type = ($post_type) ? $post_type : 'post';
                    do_meta_boxes($type, 'advanced', $tag);

                    ?>
                </div> <!-- post-body-content -->
            </div> <!-- post-body -->
        </div> <!-- poststuff -->
        <?php
    }

    public function actTaxonomyEnableUI($tag)
    {
        global $taxonomy, $typenow;

        if ($typenow && !in_array($typenow, presspermit()->getEnabledPostTypes(), true)) {
            return;
        }

        // Create compatibility wrapper for third-party metaboxes
        $this->wrapIncompatibleMetaboxes($typenow, $tag);

        ?>
        <br/><br/>
        <div id="poststuff" class="metabox-holder">
            <div id="post-body">
                <div id="post-body-content" style="position: relative;z-index: 1;">
                    <?php

                    require_once(ABSPATH . 'wp-admin/includes/meta-boxes.php');

                    add_meta_box(
                        "pp_enable_taxonomy",
                        esc_html__('Permissions Settings', 'press-permit-core'),
                        [$this, 'drawSettingsUI'],
                        $taxonomy,
                        'advanced',
                        'default',
                        []
                    );

                    do_meta_boxes($taxonomy, 'advanced', $tag);

                    ?>
                </div> <!-- post-body-content -->
            </div> <!-- post-body -->
        </div> <!-- poststuff -->
        <?php

        echo '<div style="clear:both">&nbsp;</div>';
    }

    public function drawSettingsUI($term, $box)
    {
        if ($tx = get_taxonomy($term->taxonomy)) :
            ?>
            <label for="pp_enable_taxonomy"><input type="checkbox" name="pp_enable_taxonomy"/>
                <?php printf(esc_html__('enable custom permissions for %s', 'press-permit-core'), esc_html($tx->labels->name)); ?>
            </label>
        <?php
        endif;
    }

    public function actUniversalExceptionsUIsupport()
    {
        ?>
        <input type="hidden" name="pp_universal" value="1"/>
        <?php
    }

    private function universalExceptionsNote($tag, $taxonomy, $post_type)
    {
        $tx_obj = get_taxonomy($taxonomy);
        $type_obj = get_post_type_object($post_type);
        ?>
        <div class="alert alert-secondary" role="alert">
            <?php
            // if _wp_original_http_referer is not passed, redirect will be from universal exceptions edit form to type-specific exceptions edit form
            if (!$referer = wp_get_original_referer()) {
                $referer = wp_get_referer();
            }

            $url = esc_url_raw(
                add_query_arg(
                    '_wp_original_http_referer',
                    urlencode($referer),
                    "term.php?taxonomy=$taxonomy&amp;tag_ID={$tag->term_id}&amp;pp_universal=1"
                )
            );

            printf(
                esc_html__('Displayed permissions are those assigned for the "%1$s" type.%2$sYou can also %3$sdefine universal %4$s permissions which apply to all related post types%5$s.', 'press-permit-core'),
                esc_html($type_obj->labels->singular_name),
                '<br>',
                "<a href='" . esc_url($url) . "' class='alert-link'>",
                esc_html($tx_obj->labels->singular_name),
                '</a>'
            );
            ?>
        </div>
        <?php
    }

    public function compatStyles() {
        // Hide invalid Simple WP Membership Protection metabox which is triggered due to existence of add_meta_box() function.
        if (defined('SIMPLE_WP_MEMBERSHIP_VER') && !defined('PRESSPERMIT_ALLOW_SIMPLE_MEMBERSHIP_METABOX')):
        ?>
        <style type="text/css">
            #swpm_sectionid {
                display: none;
            }
        </style>
        <?php 
        endif;
    }

    /**
     * Wrap incompatible metaboxes that expect WP_Post objects to work with WP_Term objects
     * This method automatically detects incompatible metaboxes using reflection and type checking
     * 
     * @param string $post_type The post type being used for metabox registration
     * @param WP_Term $term The term object that will be passed to metaboxes
     */
    private function wrapIncompatibleMetaboxes($post_type, $term) {
        global $wp_meta_boxes;
        
        $screen_id = $post_type ?: 'post';
        
        if (!isset($wp_meta_boxes[$screen_id])) {
            return;
        }
        
        $wrapped_metaboxes = [];
        
        foreach (['normal', 'side', 'advanced'] as $context) {
            if (!isset($wp_meta_boxes[$screen_id][$context])) {
                continue;
            }
            
            foreach (['high', 'core', 'default', 'low'] as $priority) {
                if (!isset($wp_meta_boxes[$screen_id][$context][$priority])) {
                    continue;
                }
                
                foreach ($wp_meta_boxes[$screen_id][$context][$priority] as $metabox_id => $metabox) {
                    // Skip our own metaboxes to avoid wrapping them
                    if (strpos($metabox_id, 'pp_') === 0) {
                        continue;
                    }
                    
                    // Allow developers to force exclude certain metaboxes from wrapping
                    $excluded_metaboxes = apply_filters('presspermit_exclude_metabox_wrapping', [], $metabox_id, $metabox);
                    if (in_array($metabox_id, $excluded_metaboxes, true)) {
                        continue;
                    }
                    
                    // Allow developers to force include certain metaboxes for wrapping
                    $forced_wrap_metaboxes = apply_filters('presspermit_force_metabox_wrapping', [], $metabox_id, $metabox);
                    $should_wrap = in_array($metabox_id, $forced_wrap_metaboxes, true);
                    
                    // If not forced, use automatic detection
                    if (!$should_wrap) {
                        $should_wrap = $this->shouldWrapMetabox($metabox, $term);
                    }
                    
                    if ($should_wrap) {
                        // Store original callback
                        $original_callback = $metabox['callback'];
                        
                        // Create a wrapper callback that creates a mock post from the term
                        $wp_meta_boxes[$screen_id][$context][$priority][$metabox_id]['callback'] = [$this, 'wrapMetaboxCallback']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                        
                        // Store original callback and term in metabox args for the wrapper
                        $wp_meta_boxes[$screen_id][$context][$priority][$metabox_id]['args']['_original_callback'] = $original_callback; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                        $wp_meta_boxes[$screen_id][$context][$priority][$metabox_id]['args']['_term_object'] = $term; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                        
                        $wrapped_metaboxes[] = $metabox_id . ' (' . 
                            (is_array($original_callback) ? 
                                (is_object($original_callback[0]) ? get_class($original_callback[0]) : $original_callback[0]) . '::' . $original_callback[1] :
                                $original_callback) . ')';
                    }
                }
            }
        }
    }
    
    /**
     * Automatically determine if a metabox should be wrapped using reflection and type checking
     * 
     * @param array $metabox The metabox configuration
     * @param WP_Term $term The term object
     * @return bool Whether the metabox should be wrapped
     */
    private function shouldWrapMetabox($metabox, $term) {
        if (!isset($metabox['callback']) || !is_callable($metabox['callback'])) {
            return false;
        }
        
        try {
            // Use reflection to check the callback's parameter types
            if (is_array($metabox['callback'])) {
                $reflection = new \ReflectionMethod($metabox['callback'][0], $metabox['callback'][1]);
            } else {
                $reflection = new \ReflectionFunction($metabox['callback']);
            }
            
            $parameters = $reflection->getParameters();
            
            // Check the first parameter (the object parameter)
            if (!empty($parameters)) {
                $first_param = $parameters[0];
                
                // Check if the parameter has a type declaration
                if ($first_param->hasType()) {
                    $type = $first_param->getType();
                    
                    // Handle different PHP versions and type objects
                    $type_name = '';
                    if (method_exists($type, 'getName')) {
                        $type_name = $type->getName();
                    } elseif (method_exists($type, '__toString')) {
                        $type_name = (string) $type;
                    }
                    
                    // Handle union types (PHP 8+)
                    if (method_exists($type, 'getTypes')) {
                        $types = $type->getTypes();
                        foreach ($types as $single_type) {
                            $single_type_name = method_exists($single_type, 'getName') 
                                ? $single_type->getName() 
                                : (string) $single_type;
                            if ($single_type_name === 'WP_Post') {
                                return true;
                            }
                        }
                    } elseif ($type_name === 'WP_Post') {
                        return true;
                    }
                }
                
                // Check parameter name patterns that suggest it expects a post
                $param_name = $first_param->getName();
                if (in_array($param_name, ['post', 'the_post', 'post_obj', 'wp_post'], true)) {
                    return true;
                }
            }
            
        } catch (\Exception $e) {
            // If reflection fails, err on the side of caution and wrap it
            return true;
        }
        
        return false;
    }
    
    /**
     * Wrapper callback for incompatible metaboxes that creates a mock post from term data
     * 
     * @param mixed $object The object passed to the metabox (will be a term)
     * @param array $box The metabox configuration
     */
    public function wrapMetaboxCallback($object, $box) {
        if (!isset($box['args']['_original_callback']) || !isset($box['args']['_term_object'])) {
            return;
        }
        
        $original_callback = $box['args']['_original_callback'];
        $term = $box['args']['_term_object'];
        
        // Create a mock post object from the term inline
        $post_data = [
            'ID' => $term->term_id,
            'post_author' => get_current_user_id(),
            'post_date' => current_time('mysql'),
            'post_date_gmt' => current_time('mysql', 1),
            'post_content' => $term->description ?: '',
            'post_title' => $term->name,
            'post_excerpt' => '',
            'post_status' => 'publish',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_password' => '',
            'post_name' => $term->slug,
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1),
            'post_content_filtered' => '',
            'post_parent' => $term->parent ?: 0,
            'guid' => get_term_link($term),
            'menu_order' => 0,
            'post_type' => 'product', // Assume product for WooCommerce compatibility
            'post_mime_type' => '',
            'comment_count' => 0,
            'filter' => 'raw',
        ];
        
        $mock_post = new \WP_Post((object) $post_data);
        
        // Call the original callback with the mock post
        try {
            if (is_callable($original_callback)) {
                call_user_func($original_callback, $mock_post, $box);
            }
        } catch (\Exception $e) {
            // Log the error but don't break the page
            echo '<div class="notice notice-warning"><p>Metabox compatibility issue: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    public function actScriptsWP()
    {
        wp_enqueue_script('common');
        wp_enqueue_script('postbox');
        add_thickbox();
        wp_enqueue_script('media-upload');

        $taxonomy = PWP::REQUEST_key('taxonomy');
        ItemEdit::scriptItemEdit($taxonomy);
    }
}

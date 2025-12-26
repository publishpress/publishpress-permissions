<?php

namespace PublishPress\Permissions\Collab\UI\Dashboard;

use PublishPress\PWP;

class PostEdit
{
    function __construct()
    {
        add_action('admin_head', [$this, 'ui_hide_admin_divs']);
        add_action('admin_print_scripts', [$this, 'ui_add_js']);
        add_action('admin_print_footer_scripts', [$this, 'ui_add_author_link']);
        add_action('admin_print_footer_scripts', [$this, 'suppress_upload_ui']);
        add_action('admin_print_footer_scripts', [$this, 'suppress_add_category_ui']);

        if (PWP::is_REQUEST('message', 6)) {
            add_filter('post_updated_messages', [$this, 'flt_post_updated_messages']);
        }

        add_filter('presspermit_get_pages_clauses', [$this, 'fltGetPages_clauses'], 10, 3);
    }

    function fltGetPages_clauses($clauses, $post_type, $args)
    {
        global $wpdb, $post;

        $col_id = (strpos($clauses['where'], $wpdb->posts)) ? "$wpdb->posts.ID" : "ID";
        $col_status = (strpos($clauses['where'], $wpdb->posts)) ? "$wpdb->posts.post_status" : "post_status";

        // never offer to set a descendant as parent
        if (!empty($post) && !empty($post->ID)) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostSaveHierarchical.php');
            $descendants = \PublishPress\Permissions\Collab\PostSaveHierarchical::getPageDescendantIds($post->ID);
            $descendants[] = $post->ID;
            $clauses['where'] .= " AND $col_id NOT IN ('" . implode("','", $descendants) . "')";
        } else {
            $descendants = [];
        }

        $required_operation = null;
        if (!current_user_can('pp_associate_any_page')) {
            require_once(PRESSPERMIT_CLASSPATH . '/PageFilters.php');

            $required_operation = (presspermit()->getOption('page_parent_editable_only')) ? 'edit' : 'associate';

            if ($restriction_where = \PublishPress\Permissions\PageFilters::getRestrictionClause(
                $required_operation,
                $post_type,
                compact('col_id')
            )) {
                $clauses['where'] .= $restriction_where;
            }

            $user = presspermit()->getUser();

            // If all included parent IDs are descendants (or the page itself), avoid treating it as unrestricted
            if ($include_ids = $user->getExceptionPosts($required_operation, 'include', $post_type)) {
                if (!array_diff($include_ids, $descendants)) {
                    $clauses['where'] .= " AND 1=2";
                }
            }
        }

        if ($additional_ids = presspermit()->getUser()->getExceptionPosts($required_operation, 'additional', $post_type)) {
            if (empty($clauses['where'])) {
                $clauses['where'] = 'AND 1=1';
            }

            $clauses['where'] = " AND ( ( 1=1 {$clauses['where']} ) OR ("
                . " $col_id IN ('" . implode("','", array_unique($additional_ids)) . "')"
                . " AND $col_status NOT IN ('" . implode("','", get_post_stati(['internal' => true])) . "') ) )";
        }

        return $clauses;
    }

    function flt_post_updated_messages($messages)
    {
        if (!presspermit()->isUserUnfiltered()) {
            if ($type_obj = presspermit()->getTypeObject('post', PWP::findPostType())) {
                if (!current_user_can($type_obj->cap->publish_posts)) {
                    $messages['post'][6] = esc_html__('Post Approved', 'press-permit-core');
                    $messages['page'][6] = esc_html__('Page Approved', 'press-permit-core');
                }
            }
        }

        return $messages;
    }

    function ui_hide_admin_divs()
    {
        global $pagenow;
        if (!in_array($pagenow, ['post.php', 'post-new.php'])) {
            return;
        }

        if (!$object_type = PWP::findPostType()) {
            return;
        }

        // For this data source, is there any html content to hide from non-administrators?
        // Note: Limited Editing Elements feature has been moved to PublishPress Capabilities > Editor Features
        if ($hide_ids = presspermit()->getOption('editor_hide_html_ids')) {
            // Feature deprecated - redirect users to PublishPress Capabilities
            add_action('admin_notices', function() {
                if (defined('PUBLISHPRESS_CAPS_VERSION')) {
                    $capabilities_url = admin_url('admin.php?page=pp-capabilities-editor-features');
                    $message = sprintf(
                        __('Limited Editing Elements feature has moved to <a href="%s">Capabilities > Editor Features</a>.', 'press-permit-core'),
                        esc_url($capabilities_url)
                    );
                } else {
                    $install_url = admin_url('plugin-install.php?tab=plugin-information&plugin=capability-manager-enhanced');
                    $message = sprintf(
                        __('Limited Editing Elements feature requires <a href="%s" target="_blank">PublishPress Capabilities</a> plugin.', 'press-permit-core'),
                        esc_url($install_url)
                    );
                }
                echo '<div class="notice notice-warning"><p>' . wp_kses_post($message) . '</p></div>';
            });
        }
    }

    function ui_add_js()
    {
        global $wp_scripts;

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-listbox', PRESSPERMIT_URLPATH . "/common/js/listbox{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION, true);
        $wp_scripts->in_footer[] = 'presspermit-listbox';
        wp_localize_script(
            'presspermit-listbox', 
            'ppListbox', 
            [
                'omit_admins' => !defined('PP_ADMINS_IN_PERMISSION_GROUPS') || !PP_ADMINS_IN_PERMISSION_GROUPS ? '1' : 0, 
                'metagroups' => 1
            ]
        );

        wp_enqueue_script('presspermit-agent-select', PRESSPERMIT_URLPATH . "/common/js/agent-exception-select{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION, true);
        $wp_scripts->in_footer[] = 'presspermit-agent-select';
        wp_localize_script('presspermit-agent-select', 'PPAgentSelect', ['ajaxurl' => wp_nonce_url(admin_url(''), 'pp-ajax'), 'ajaxhandler' => 'got_ajax_listbox']);

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-collab-post-edit', PRESSPERMIT_COLLAB_URLPATH . "/common/js/post-edit{$suffix}.js", [], PRESSPERMIT_COLLAB_VERSION);
    }

    function suppress_upload_ui()
    {
        $user = presspermit()->getUser();

        if (empty($user->allcaps['upload_files']) && !empty($user->allcaps['edit_files'])) : ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function($) {
                    $(document).on('focus', 'div.supports-drag-drop', function() {
                        $('div.media-router a:first').hide();
                        $('div.media-router a:nth-child(2)').click();
                    });
                    $(document).on('mouseover', 'div.supports-drag-drop', function() {
                        $('div.media-menu a:nth-child(2)').hide();
                        $('div.media-menu a:nth-child(5)').hide();
                    });
                });
                //]]>
            </script>
        <?php
        endif;

        if (empty($user->allcaps['upload_files']) && !empty($user->allcaps['edit_files'])) : ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function($) {
                    $(document).on('focus', 'div.supports-drag-drop', function() {
                        $('div.media-router a:first').hide();
                        $('div.media-router a:nth-child(2)').click();
                    });
                    $(document).on('mouseover', 'div.supports-drag-drop', function() {
                        $('div.media-menu a:nth-child(2)').hide();
                        $('div.media-menu a:nth-child(5)').hide();
                    });
                });
                //]]>
            </script>
            <?php
        endif;
    }

    function suppress_add_category_ui()
    {
        if (presspermit()->isContentAdministrator()) {
            return;
        }

        $user = presspermit()->getUser();

        $post_type = PWP::findPostType();

        // WP add category JS for Edit Post form does not tolerate absence of some categories from "All Categories" tab
        foreach (get_taxonomies(['hierarchical' => true], 'object') as $taxonomy => $tx) {
            $disallow_add_term = false;
            $additional_tt_ids = array_merge(
                $user->getExceptionTerms('assign', 'additional', $post_type, $taxonomy, ['merge_universals' => true]),
                $user->getExceptionTerms('edit', 'additional', $post_type, $taxonomy, ['merge_universals' => true])
            );

            if (
                $user->getExceptionTerms('assign', 'include', $post_type, $taxonomy, ['merge_universals' => true])
                || $user->getExceptionTerms('edit', 'include', $post_type, $taxonomy, ['merge_universals' => true])
            ) {
                $disallow_add_term = true;
            } elseif ($tt_ids = array_merge(
                $user->getExceptionTerms('assign', 'exclude', $post_type, $taxonomy, ['merge_universals' => true]),
                $user->getExceptionTerms('edit', 'exclude', $post_type, $taxonomy, ['merge_universals' => true])
            )) {
                $tt_ids = array_diff($tt_ids, $additional_tt_ids);
                if (count($tt_ids)) {
                    $disallow_add_term = true;
                }
            } elseif ($additional_tt_ids) {
                $cap_check = (isset($tx->cap->manage_terms)) ? $tx->cap->manage_terms : 'manage_categories';

                if (!current_user_can($cap_check)) {
                    $disallow_add_term = true;
                }
            }

            if ($disallow_add_term) :
            ?>
                <style type="text/css">
                    #<?php echo esc_attr($taxonomy); ?>-adder {
                        display: none;
                    }
                </style>
            <?php
            endif;
        }
    }

    function ui_add_author_link()
    {
        static $done;
        if (!empty($done)) return;
        $done = true;

        global $post;
        if (empty($post)) {
            return;
        }

        $type_obj = get_post_type_object($post->post_type);

        if (current_user_can($type_obj->cap->edit_others_posts)) :
            $title = esc_html__('Author Search / Select', 'press-permit-core');

            $args = [
                'suppress_extra_prefix' => true,
                'ajax_selection' => true,
                'display_stored_selections' => false,
                'label_headline' => '',
                'multi_select' => false,
                'suppress_selection_js' => true,
                'context' => $post->post_type,
            ];

            $agents = presspermit()->admin()->agents();
            ?>

            <div id="pp_author_search_ui_base" style="display:none">
                <div class="pp-agent-select pp-agents-selection"><?php $agents->agentsUI('user', [], 'select-author', [], $args); ?></div>
            </div>

            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function($) {
                    var author_el = $('#pp_author_search_ui_base').html();
                    $('#pp_author_search_ui_base').remove();
                    $("#post_author_override").after(
                        '<div id="pp_author_search" class="pp-select-author" style="display:none">' +
                        author_el +
                        '</div>&nbsp;' +
                        '<a href="#" class="pp-add-author" style="margin-left:8px" title="<?php echo esc_attr($title); ?>"><?php esc_html_e('select other', 'press-permit-core'); ?></a>' +
                        '<a class="pp-close-add-author" href="#" style="display:none;"><?php esc_html_e('close', 'press-permit-core'); ?></a>'
                    );
                });
                /* ]]> */
            </script>
<?php
        endif;
    }
}

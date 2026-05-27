<?php

namespace PublishPress\Permissions\UI;

class AgentsAjax
{
    public function __construct() 
    {
        global $wpdb, $current_blog;

        require_once(ABSPATH . '/wp-admin/includes/user.php');

        if (!PWP::is_GET('pp_agent_search')) {
            return;
        }

        check_ajax_referer('pp-ajax');

        if (!$agent_type = PWP::sanitizeEntry(PWP::GET_key('pp_agent_type'))) {
            return;
        }

        if (!$topic = PWP::sanitizeEntry(PWP::GET_key('pp_topic'))) {
            return;
        }

        $pp = presspermit();
        $pp_admin = $pp->admin();
        $pp_groups = $pp->groups();

        $include_ids = [];
        $meta_search = [];

        $search_str = !empty($_GET['pp_agent_search']) ? sanitize_text_field($_GET['pp_agent_search']) : '';
        $orig_search_str = strval($search_str);
        
        $agent_id = PWP::GET_int('pp_agent_id');
        $topic = str_replace(':', ',', $topic);

        $omit_admins = apply_filters(
            'presspermit_group_omit_administrators',
            !PWP::empty_GET('pp_omit_admins') && (!defined('PP_ADMINS_IN_PERMISSION_GROUPS') || !PP_ADMINS_IN_PERMISSION_GROUPS),
            $agent_id, 
            $topic
        );

        $context = PWP::GET_key('pp_context');
        
        if (strpos($topic, ',')) {
            $arr_topic = explode(',', $topic);
            if (isset($arr_topic[1])) {
                if (taxonomy_exists($context)) {
                    $verified = true;
                    $ops = $pp_admin->canSetExceptions(
                        $arr_topic[0],
                        $arr_topic[1],
                        ['via_item_source' => 'term', 'via_item_type' => $context, 'for_item_source' => 'post']
                    ) ? ['read' => true] : [];

                    $operations = apply_filters('presspermit_item_edit_exception_ops', $ops, 'post', $context, $arr_topic[1]);

                    if (!in_array($arr_topic[0], $operations, true)) {
                        die(-1);
                    }
                } elseif (post_type_exists($arr_topic[1])) {
                    $verified = true;
                    $ops = $pp_admin->canSetExceptions(
                        $arr_topic[0],
                        $arr_topic[1],
                        ['via_item_source' => 'post', 'for_item_source' => 'post']
                    ) ? ['read' => true] : [];

                    $operations = apply_filters('presspermit_item_edit_exception_ops', $ops, 'post', $arr_topic[1]);

                    if (empty($operations[$arr_topic[0]])) {
                        die(-1);
                    }
                }
            }
        } elseif ('member' == $topic) {
            $verified = true;
            $group_type = $pp_groups->groupTypeExists($context) ? $context : 'pp_group';
            if (!$pp_groups->userCan('pp_manage_members', $agent_id, $group_type)) {
                die(-1);
            }
        } elseif ('select-author' == $topic) {
            $verified = true;

            $post_type = (post_type_exists($context)) ? $context : 'page';

            $include_ids = apply_filters('presspermit_select_author_ids', false, $post_type);

            $type_obj = get_post_type_object($post_type);
            if (!current_user_can($type_obj->cap->edit_others_posts)) {
                die(-1);
            }
        }

        if ('user' == $agent_type) {
            if (empty($verified)) {
                    if (!current_user_can('pp_manage_members')) {
                    die(-1);
                }
            }

            if (0 === strpos($orig_search_str, ' ')) {
                $orderby = 'user_login';
                $order = 'ASC';
            } else {
                $orderby = 'user_registered';
                $order = 'DESC';
            };

            $um_keys = (!empty($_GET['pp_usermeta_key'])) ? array_map('sanitize_text_field', $_GET['pp_usermeta_key']) : [];
            $um_keys = array_map('\PressShack\LibWP::sanitizeEntry', $um_keys);

            $um_vals = (!empty($_GET['pp_usermeta_val'])) ? array_map('sanitize_text_field', $_GET['pp_usermeta_val']) : [];

            if (defined('PP_USER_LASTNAME_SEARCH') && !defined('PP_USER_SEARCH_FIELD')) {
                $default_search_field = 'last_name';
            } elseif (defined('PP_USER_SEARCH_FIELD')) {
                $default_search_field = PWP::sanitizeEntry(constant('PP_USER_SEARCH_FIELD'));
            } else {
                $default_search_field = '';
            }

            if ($search_str && $default_search_field) {
                $um_keys[] = $default_search_field;
                $um_vals[] = $search_str;

                $search_str = '';
            }

            // discard duplicate selections
            $used_keys = [];
            foreach ($um_keys as $i => $keyname) {
                if (!$keyname || in_array($keyname, $used_keys, true)) {
                    unset($um_keys[$i]);
                    unset($um_vals[$i]);
                } else {
                    $used_keys[] = $keyname;
                }
            }

            $um_keys = array_values($um_keys);
            $um_vals = array_values($um_vals);

            $role_filter = !empty($_GET['pp_role_search']) ? sanitize_text_field($_GET['pp_role_search']) : '';

            // append where clause for meta value criteria
            if (!empty($um_keys)) {
                // force search values to be cast as numeric or boolean
                $force_numeric_keys = (defined('PP_USER_SEARCH_NUMERIC_FIELDS')) ? explode(',', PP_USER_SEARCH_NUMERIC_FIELDS) : [];
                $force_boolean_keys = (defined('PP_USER_SEARCH_BOOLEAN_FIELDS')) ? explode(',', PP_USER_SEARCH_BOOLEAN_FIELDS) : [];
                
                $meta_search = [];

                for ($i = 0; $i < count($um_keys); $i++) {
                    $val = trim($um_vals[$i]);

                    if (in_array($um_keys[$i], $force_numeric_keys)) {
                        if (in_array($val, ['true', 'false', 'yes', 'no'])) {
                            $val = (bool)$val;
                        }

                        $val = (int)$val;
                    } elseif (in_array($val, $force_boolean_keys)) {
                        $val = strval((bool)$val);
                    }

                    $meta_search[] = ['key' => $um_keys[$i], 'value' => $val, 'compare' => 'LIKE'];
                }
            }

            $limit = (defined('PP_USER_SEARCH_LIMIT')) ? abs(intval(constant('PP_USER_SEARCH_LIMIT'))) : 0;

            $args = [
                'fields' => ['ID', 'user_login', 'display_name'],
                'search' => '*' . $search_str . '*',
                'search_columns' => ['user_login', 'user_nicename', 'display_name', 'user_email'],
                'include' => $include_ids,
                'role' => $role_filter,
                'orderby' => $orderby,
                'order' => $order,
                'number' => $limit,
            ];

            if ($meta_search) {
                $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_search);  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            }

            $omit_users = [];

            // determine all current users for group in question
            if (!empty($agent_id)) {
                $topic = isset($topic) ? $topic : '';
                $group_type = ($context && $pp_groups->groupTypeExists($context)) ? $context : 'pp_group';
                $omit_users = $pp_groups->getGroupMembers($agent_id, $group_type, 'id', ['member_type' => $topic, 'status' => 'any']);
            }

            if ($omit_admins) {
                if ($admin_roles = $pp_admin->getAdministratorRoles()) {  // Administrators can't be excluded; no need to include or enable them
                    $args['role__not_in'] = array_map('sanitize_key', array_keys($admin_roles));
                }
            }

            $user_search = new \WP_User_Query(
                $args
            );

	        $results = $user_search->get_results();

            if ($results) {
                foreach ($results as $row) {
                    if (!in_array($row->ID, $omit_users)) {
                        $first_name = get_user_meta($row->ID, 'first_name', true);
                        $last_name = get_user_meta($row->ID, 'last_name', true);
                        
                        if (defined('PP_USER_RESULTS_DISPLAY_NAME')) {
                            $title = ($row->user_login != $row->display_name) ? $row->user_login : '';
                            $formatted_name = trim($first_name . ' ' . $last_name. ' (' . $row->display_name . ')');
                            echo "<option value='" . esc_attr($row->ID) . "' class='pp-new-selection' title='" . esc_attr($title) . "'>" . esc_html($formatted_name) . "</option>";
                        } else {
                            $title = ($row->user_login != $row->display_name) ? $row->display_name : '';
                            $formatted_name = trim($first_name . ' ' . $last_name. ' (' . $row->user_login . ')');
                            echo "<option value='" . esc_attr($row->ID) . "' class='pp-new-selection title='" . esc_attr($title) . "'>" . esc_html($formatted_name) . "</option>";
                        }
                    }
                }
            }
        } else {
            // determine all currently stored groups (of any status) for user in question (not necessarily logged user)
            if (!empty($agent_id)) {
                $omit_groups = $pp_groups->getGroupsForUser($agent_id, $agent_type, ['status' => 'any']);
            } else {
                $omit_groups = [];
            }

            if ($groups = $pp_groups->getGroups(
                $agent_type,
                ['search' => $search_str]
            )) {
                foreach ($groups as $row) {
                    if ((empty($row->metagroup_id) || is_null($row->metagroup_id)) && !isset($omit_groups[$row->ID])) {
                        echo "<option value='" . esc_attr($row->ID) . "'>". esc_html($row->name) . "</option>";
                    }
                }
            }
        }
    }
}

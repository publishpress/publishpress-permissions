<?php

namespace PublishPress\Permissions\UI\Handlers;

\defined('ABSPATH') || exit;

require_once(PRESSPERMIT_CLASSPATH . '/UI/Groups.php');

class Groups
{
    public static function handleRequest()
    {
        $pp_groups = presspermit()->groups();

        $url = $referer = $redirect = $update = '';

        require_once(PRESSPERMIT_CLASSPATH . '/UI/GroupsHelper.php');
        \PublishPress\Permissions\UI\GroupsHelper::getUrlProperties($url, $referer, $redirect);

        if (!$agent_type = apply_filters('presspermit_query_group_type', ''))
            $agent_type = 'pp_group';

        if (!PWP::empty_REQUEST('action2')) {
            $action = PWP::REQUEST_key('action2');

        } elseif (!PWP::empty_REQUEST('action')) {
            $action = PWP::REQUEST_key('action');

        } elseif (!$action = PWP::REQUEST_key('pp_action')) {
            $action = '';
        }

        switch ($action) {

            case 'dodelete':
                check_admin_referer('pp-bulk-groups');

                if (!current_user_can('pp_delete_groups'))
                    wp_die(esc_html__('You are not permitted to do that.', 'press-permit-core'));

                $group_variant = (! empty($_REQUEST['group_variant'])) ? sanitize_key($_REQUEST['group_variant']) : 'pp_group';
                $redirect = add_query_arg('group_variant', $group_variant, $redirect);

                if (empty($_REQUEST['groups']) && empty($_REQUEST['group'])) {
                    wp_redirect($redirect);
                    exit();
                }

                if (empty($_REQUEST['groups'])) {
                    $groupids = [intval($_REQUEST['group'])];
                } else {
                    $groupids = array_map('intval', (array)$_REQUEST['groups']);
                }

                $update = 'del';
                $delete_ids = [];
                $wp_role_count = 0;

                foreach ((array)$groupids as $id) {
                    if ($group_obj = $pp_groups->getGroup($id, $agent_type)) {
                        if (!empty($group_obj->metagroup_id) || ('wp_role' == $group_obj->metagroup_type)) {
                            if (!\PublishPress\Permissions\DB\Groups::isDeletedRole($group_obj->metagroup_id)) {
                                continue;
                            } else {
                                $wp_role_count++;
                            }
                        }
                    }

                    $pp_groups->deleteGroup($id, $agent_type);
                    $delete_ids[] = $id;
                }

                if (!$delete_ids)
                    wp_die(esc_html__('You can&#8217;t delete that group.', 'press-permit-core'));

                $redirect = add_query_arg(['delete_count' => count($delete_ids), 'update' => $update], $redirect);

                if ($wp_role_count == count($delete_ids)) {
                    $redirect = remove_query_arg('group_variant', $redirect);
                }

                do_action('presspermit_trigger_cache_flush');

                wp_redirect($redirect);
                exit();

                break;

            case 'delete':
                check_admin_referer('pp-bulk-groups');

                if (!current_user_can('pp_delete_groups'))
                    wp_die(esc_html__('You are not permitted to do that.', 'press-permit-core'));

                if (!empty($_REQUEST['groups'])) {
                    $redirect = esc_url_raw(add_query_arg([
                        'pp_action' => 'bulkdelete',
                        'agent_type' => $agent_type,
                        'wp_http_referer' => PWP::is_REQUEST('wp_http_referer') ? esc_url_raw(PWP::REQUEST_url('wp_http_referer')) : '',
                        'groups' => array_map('intval', $_REQUEST['groups'])
                    ], $redirect));

                    wp_redirect($redirect);
                    exit();
                }

                if (PWP::empty_REQUEST('group')) {
                    wp_redirect($redirect);
                    exit();
                }

                break;

            default:
        } // end switch
    }
}

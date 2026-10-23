<?php
namespace PublishPress\Permissions\Collab\Revisionary;

\defined('ABSPATH') || exit;

class ContentRoles extends \RevisionaryContentRoles
{
    function filter_object_terms($terms, $taxonomy)
    {
        return apply_filters('presspermit_pre_object_terms', $terms, $taxonomy);
    }

    function get_metagroup_edit_link($metagroup_name)
    {
        if ($group = presspermit()->groups()->getGroupByName('[' . $metagroup_name . ']')) {
            return "admin.php?page=presspermit-edit-permissions&action=edit&agent_id=$group->ID";
        
        } elseif ($group = presspermit()->groups()->getGroupByName($metagroup_name)) {
            return "admin.php?page=presspermit-edit-permissions&action=edit&agent_id=$group->ID";
        }
    
        return '';
    }

    function get_metagroup_members($metagroup_name, $args = [])
    {
        if ($group = presspermit()->groups()->getGroupByName('[' . $metagroup_name . ']')) {
            return presspermit()->groups()->getGroupMembers($group->ID, 'pp_group', 'id', ['maybe_metagroup' => true]);
        
        } elseif ($group = presspermit()->groups()->getGroupByName($metagroup_name)) {
            return presspermit()->groups()->getGroupMembers($group->ID, 'pp_group', 'id', ['maybe_metagroup' => true]);
        }
    
        return [];
    }

    function users_who_can($reqd_caps, $object_id = 0, $args = [])
    {
        $pp = presspermit();

        $defaults = ['cols' => 'id', 'user_ids' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!empty($pp)) {
            global $current_user;
            $buffer_user_id = $current_user->ID;

            $ok_users = [];

            // Prime the set user pump. Without this, first switched user inherits capabilities of current logged user on some installations.
			wp_set_current_user(1);
			do_action('presspermit_user_reload');

            foreach ($user_ids as $user_id) {
                wp_set_current_user($user_id);
                do_action('presspermit_user_reload');

                $pp->clearMemcache();
                $pp->flags['memcache_disabled'] = true;

                if (current_user_can('edit_post', $object_id)) {
                    if ('id' == $cols) {
                        $ok_users[] = $current_user->ID;
                    } else {
                        $ok_users[] = $current_user;
                    }
                }
            }

            $pp->flags['memcache_disabled'] = false;
            wp_set_current_user($buffer_user_id);
            do_action('presspermit_user_reload');

            return $ok_users;
        }
        return [];
    }

    function add_listed_ids($source_name, $object_type, $id) // retain $source_name arg for Revisionary API
    {
        presspermit()->listed_ids[$object_type][$id] = true;
    }

    function set_hascap_flags($flags)
    {
        // no longer applicable (retain API for Revisionary)
    }

    function is_direct_file_access()
    {
        return presspermit()->isDirectFileAccess();
    }
}

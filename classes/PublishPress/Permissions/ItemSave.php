<?php

namespace PublishPress\Permissions;

add_filter('presspermit_do_inherit_parent_exceptions', [__NAMESPACE__.'\ItemSave', 'fltDefaultDisableParentExceptions'], 5, 3);

class ItemSave
{
    public static function itemUpdateProcessExceptions($via_item_source, $for_item_source, $item_id, $args = [])
    {
        $defaults = [
            'via_item_type' => '',
            'is_new' => false,
            'set_parent' => 0,
            'last_parent' => 0,
            'disallow_manual_entry' => false,
        ];

        $args = apply_filters(
            'presspermit_item_update_process_roles_args',
            array_merge(
                $defaults,
                ['for_item_status' => '', 'force_for_item_type' => false],
                (array)$args,
                compact('via_item_source', 'for_item_source', 'item_id')
            ),
            $via_item_source,
            $for_item_source,
            $item_id
        );

        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();
        $pp_admin = $pp->admin();

        do_action("presspermit_process_exceptions_{$via_item_source}_{$item_id}");

        if ('term' == $via_item_source) {
            $can_assign_roles = presspermit()->admin()->canSetAnyTermPermissions('', $via_item_type);
        } else {
            $can_assign_roles = presspermit()->admin()->canSetAnyPostPermissions();
        }

        if ($can_assign_roles) {
            if (apply_filters('presspermit_disable_exception_edit', false, $via_item_source, $item_id) 
            || apply_filters('presspermit_disable_exception_ui', false, $via_item_source, $item_id, '') 
            ) {
                $can_assign_roles = false;
            }
        }

        if (empty($disallow_manual_entry)) {
            $disallow_manual_entry = defined('XMLRPC_REQUEST');
        }

        // This function is triggered by the save_post hook, no nonce needed
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if (!empty($_POST['pp_exceptions']) && !$disallow_manual_entry && $can_assign_roles) {

            // validate posted exceptions array

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $pe = (array) $_POST['pp_exceptions']; // explicitly validated below

            if (isset($pe['(all)'])) {
                $pe[''] = $pe['(all)'];
            }

            foreach(array_keys($pe) as $item_type) {
                if (!is_array($pe[$item_type]) || ($item_type != sanitize_key($item_type))) {
                    unset($pe[$item_type]);
                    continue;
                }

                foreach(array_keys($pe[$item_type]) as $operation) {
                    if (!is_array($pe[$item_type][$operation]) || ($operation != sanitize_key($operation))) {
                        unset($pe[$item_type][$operation]);
                        continue;
                    }

                    foreach(array_keys($pe[$item_type][$operation]) as $agent_type) {
                        if (!is_array($pe[$item_type][$operation][$agent_type]) || ($agent_type != sanitize_key($agent_type))) {
                            unset($pe[$item_type][$operation][$agent_type]);
                            continue;
                        }

                        foreach(array_keys($pe[$item_type][$operation][$agent_type]) as $assign_for) {
                            if (!is_array($pe[$item_type][$operation][$agent_type][$assign_for]) || ($assign_for != sanitize_key($assign_for))) {
                                unset($pe[$item_type][$operation][$agent_type][$assign_for]);
                                continue;
                            }

                            foreach(array_keys($pe[$item_type][$operation][$agent_type][$assign_for]) as $agent_id) {
                                if ($agent_id != (int) $agent_id) {
                                    unset($pe[$item_type][$operation][$agent_type][$assign_for][$agent_id]);
                                    continue;
                                }

                                $pe[$item_type][$operation][$agent_type][$assign_for][$agent_id] = sanitize_key($pe[$item_type][$operation][$agent_type][$assign_for][$agent_id]);
                            }
                        }
                    }
                }
            }

            $posted_exceptions = $pe;

            foreach (array_keys($posted_exceptions) as $for_item_type) {
                $_for_type = sanitize_key($for_item_type);

                foreach (array_keys($posted_exceptions[$for_item_type]) as $op) {
                    $op = sanitize_key($op);
                    $_for_item_source = $for_item_source;
                    
                    if (('term' == $for_item_source) || (('term' == $via_item_source) && in_array($op, ['manage', 'associate'] ) ) ) {
                        $_for_item_source = 'term';
                        
                        if (!taxonomy_exists($_for_type)) {
                            continue;
                        }
                    } elseif ($_for_type && ('post' == $for_item_source) && !post_type_exists($_for_type)) {
                        continue;
                    }

                    $_args = compact('via_item_source', 'via_item_type', 'item_id');
                    $_args['for_item_source'] = $_for_item_source;

                    if (!$pp_admin->canSetExceptions($op, $for_item_type, $_args)) {
                        continue;
                    }

                    foreach (array_keys($posted_exceptions[$for_item_type][$op]) as $agent_type) {
                        $agent_type = sanitize_key($agent_type);

                        $args['for_item_type'] = $_for_type;
                        $args['for_item_source'] = $_for_item_source;
                        $args['operation'] = $op;
                        $args['agent_type'] = $agent_type;

                        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
                        // posted_exceptions [for_item_type] [op] [agent_type] [assign_for] [agent_id] = has_access 
                        $pp->assignExceptions($posted_exceptions[$for_item_type][$op][$agent_type], $agent_type, $args);
                    }
                }
            }
        }

        if (('post' == $via_item_source) && ('post' == $for_item_source) && $item_id) {
            if ($post = get_post($item_id)) {
                if ('attachment' == $post->post_type) {  // don't propagate page exceptions to attachments
                    return;
                }
            }
        }

        self::inheritParentExceptions($item_id, compact('via_item_source', 'via_item_type', 'set_parent', 'last_parent', 'is_new'));

        do_action('presspermit_processed_exceptions', $via_item_source, $item_id);
    } // end function

    public static function inheritParentExceptions($item_id, $args = [])
    {
        $defaults = ['via_item_source' => '', 'via_item_type' => '', 'set_parent' => '', 'last_parent' => '', 'is_new' => true, 'force_for_item_type' => false];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $is_new_term = ('term' != $via_item_source) ? false : PWP::is_REQUEST('action', 'add-tag');

        // don't execute this action handler more than one per post save (may be called directly on pre-save cap check)
        static $did_items;
        if ('post' == $via_item_source) {
            if (!isset($did_items)) {
                $did_items = [];
            }
            if (isset($did_items[$item_id])) {
                return;
            }
            $did_items[$item_id] = 1;
        }

        if (!apply_filters('presspermit_do_inherit_parent_exceptions', true, $item_id, $args)) {
            return;
        }

        // Inherit exceptions from new parent post/term, but only for new items or if parent is changed
        if ((intval($set_parent) != intval($last_parent)) || $is_new_term || $is_new) {

            // retain all explicitly selected exceptions
            global $wpdb;                               // any_type_or_taxonomy arg retains previous query construction (no post_type or taxonomy clause)
            $descendant_ids = PWP::getDescendantIds($via_item_source, $item_id, ['any_type_or_taxonomy' => true, 'exclude_autodrafts' => false]);  
            if ($descendant_ids && ('term' == $via_item_source)) {
                $descendant_ids = PWP::termidToTtid($descendant_ids, $via_item_type);
            }

            // clear previously propagated role assignments for this item and its branch of sub-items

            if (!$is_new) {
                require_once(PRESSPERMIT_CLASSPATH.'/DB/PermissionsUpdate.php');

                DB\PermissionsUpdate::clearItemExceptions($via_item_source, $item_id, ['inherited_only' => true]);
                DB\PermissionsUpdate::clearItemExceptions($via_item_source, $descendant_ids, ['inherited_only' => true]);
            }

            // assign propagating exceptions from new parent
            if ($set_parent) {
                $descendent_id_csv = implode("','", array_map('intval', array_merge($descendant_ids, (array) $item_id)));

                // Query plugin tables on post / term update (IN clause constructed and sanitized above)
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $retain_exceptions = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $wpdb->ppc_exception_items AS i"
                        . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id"
                        . " WHERE i.assign_for = 'item' AND i.inherited_from = '0' AND e.via_item_source = %s AND i.item_id IN ('$descendent_id_csv')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $via_item_source
                    )
                );

                if ('term' == $via_item_source) {
                    $parent_term = get_term($set_parent, $via_item_type);
                    $set_parent = $parent_term->term_taxonomy_id;
                }

                // propagate exception from new parent to this item and its branch of sub-items
                require_once(PRESSPERMIT_CLASSPATH.'/DB/PermissionsUpdate.php');
                
                $force_for_item_type = (isset($args['force_for_item_type'])) ? $args['force_for_item_type'] : false; // todo: why is this variable not already set?
                $_args = compact('retain_exceptions', 'force_for_item_type');

                $_args['parent_exceptions'] = DB\PermissionsUpdate::getParentExceptions(
                    $via_item_source, 
                    $item_id, 
                    $set_parent
                );

                $any_inserts = DB\PermissionsUpdate::inheritParentExceptions(
                    $via_item_source, 
                    $item_id, 
                    $set_parent, 
                    $_args
                );

                foreach ($descendant_ids as $_descendant_id) {
                    $any_inserts = $any_inserts 
                    || DB\PermissionsUpdate::inheritParentExceptions(
                        $via_item_source, 
                        $_descendant_id, 
                        $set_parent, 
                        $_args
                    );
                }
            }
        }

        return !empty($any_inserts);
    }

    // PP Pro does not currently handle bbPress exceptions on individual topics and replies, so make sure those are not propagated
    public static function fltDefaultDisableParentExceptions($inherit_parent_exceptions, $item_id, $args)
    {
        if ($inherit_parent_exceptions && in_array(get_post_field('post_type', $item_id), ['topic', 'reply'])) {
            return false;
        }

        return $inherit_parent_exceptions;
    }
}

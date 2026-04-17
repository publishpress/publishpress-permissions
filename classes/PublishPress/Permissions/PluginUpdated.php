<?php

namespace PublishPress\Permissions;

class PluginUpdated
{
    public function __construct($prev_version)
    {
        global $wpdb;

        // single-pass do loop to easily skip unnecessary version checks
        do {
            if (!$prev_version) {
                if (false === get_option('presspermit_deactivated_modules')) {
                    self::deactivateModules(['current_deactivations' => []]);
                }

                if (get_option('pp_version') && !get_option('presspermit_group_index_drop_done')) {  // previous installation of PP < 2.0 ?
                    update_option('presspermit_need_group_index_drop', true); // flag groups index drop to be launched from PP Options
                }

                break;  // no need to run through version comparisons if no previous version
            }

            // Direct query on options table to use LIKE clause for this plugin update operation
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'buffer_metagroup_id_%'");

            if (version_compare($prev_version, '2.7-beta', '>=')
            && version_compare($prev_version, '2.7-beta3', '<')
            ) {
                // Previous 2.7 betas added wrong capabilities
                // (todo: possibly migrate all capabilities, but not yet)
                if ($role = @get_role('administrator')) {
                    $role->remove_cap('presspermit_create_groups');
                    $role->remove_cap('presspermit_delete_groups');
                    $role->add_cap('pp_create_groups');
                    $role->add_cap('pp_delete_groups');
                }
        
                if ($role = @get_role('editor')) {
                    $role->remove_cap('presspermit_create_groups');
                    $role->remove_cap('presspermit_delete_groups');
                    $role->add_cap('pp_create_groups');
                    $role->add_cap('pp_delete_groups');
                }

                // Delete any invalid user metagroup relationships from bad buffer_metagroup_id values
                self::syncWordPressRoles();
            }
        
        	do_action('presspermit_version_updated', $prev_version);

            if (version_compare($prev_version, '3.11.3', '<')) {
                if (false === get_option('presspermit_pattern_roles_include_generic_rolecaps')) {
                    // If any type-specific extra roles are already stored, default to previous behavior of including many generic capabilities from Pattern Role
                    
                    // Direct query on plugin table for version update operation
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    if ($wpdb->get_row("SELECT assignment_id FROM $wpdb->ppc_roles WHERE role_name LIKE '%:%'")) {
                        update_option('presspermit_pattern_roles_include_generic_rolecaps', 1);
                    }
                }
            } else break;

            if (version_compare($prev_version, '3.8-beta2', '<')) {
                // Restore taxonomy_children option arrays now that an alternate filtering mechanism is in place
                presspermit()->flags['disable_term_filtering'] = true;

                if (function_exists('_get_term_hierarchy')) {
                    foreach (presspermit()->getEnabledTaxonomies(['object_type' => false]) as $taxonomy) {
                        delete_option("{$taxonomy}_children");
                        _get_term_hierarchy($taxonomy);
                    }
                }

                presspermit()->flags['disable_term_filtering'] = false;
            } else break;

            if (version_compare($prev_version, '3.3.3', '<')) {
                // Activation of invalid "Custom permissions for Authors" setting on Edit Author screen broke Authors > Authors listing and editing access
                if ($taxs = get_option('presspermit_enabled_taxonomies')) {
                    if (!empty($taxs['author'])) {
                        unset($taxs['author']);
                        update_option('presspermit_enabled_taxonomies', $taxs);
                    }
                }
            } else break;

            if (version_compare($prev_version, '2.7-beta', '<')) {
                require_once(PRESSPERMIT_CLASSPATH . '/DB/Migration.php');
                DB\Migration::migrateOptions();
            } else break;

            if (version_compare($prev_version, '2.3.19-dev', '<')) {
                require_once(PRESSPERMIT_CLASSPATH . '/DB/Migration.php');
                DB\Migration::remove_group_members_pk();
            } else break;

            if (version_compare($prev_version, '2.1.47-dev', '<')) {
                if (!get_option('presspermit_post_blockage_priority')) {
                    // previously, post-assigned reading/editing blockages could be overriden by category-assigned additions
                    update_option('presspermit_legacy_exception_handling', true);
                }
            } else break;

            if (version_compare($prev_version, '2.1.35', '<')) {
                require_once(PRESSPERMIT_CLASSPATH . '/DB/Migration.php');

                // Previously, page exceptions were propagated to all descendents, including attachments (but this is unnecessary and potentially undesirable)
                DB\Migration::delete_propagated_attachment_exceptions();
                DB\Migration::expose_attachment_exception_items();  // "include" exceptions for Read/Edit operations are exposed but not deleted, since they affect access to other media 

                // Previously, propagated exceptions were not removed when parent exception assign_for was changed to item only.  Expose them by setting inherited_from to 0
                //DB\Migration::expose_orphaned_exception_items();

                require_once(PRESSPERMIT_CLASSPATH . '/DB/Permissions.php');
                \PublishPress\Permissions\DB\Permissions::expose_orphaned_exception_items();
            } else break;

            if (version_compare($prev_version, '2.1.33', '<')) {
                if ($enabled_taxonomies = get_option('presspermit_enabled_taxonomies')) {
                    // previously, post_tag was disabled by default but implicitly enabled for front-end filtering
                    $enabled_taxonomies['post_tag'] = true;
                    update_option('presspermit_enabled_taxonomies', $enabled_taxonomies);
                }
            } else break;

            if (version_compare($prev_version, '2.1.16-beta', '<')) {
                // Direct query on plugin table for version update operation
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query("UPDATE $wpdb->ppc_exceptions SET for_item_source = 'post' WHERE for_item_source = 'all'");
            } else break;
        } while (0); // end single-pass version check loop
    }

    public static function deactivateModules($args = []) {
        $deactivated = (isset($args['current_deactivations'])) 
        ? $args['current_deactivations'] 
        : get_option('presspermit_deactivated_modules');
        
        if (!$deactivated) {
            $deactivations = [];
        }

        if (!empty($args['deactivate'])) {
            $new_deactivations = array_intersect($args['deactivate'], presspermit()->getAvailableModules());
        } else {
            // default deactivations
            $new_deactivations = [
                'presspermit-circles', 
                'presspermit-file-access', 
                'presspermit-membership'
            ];
        }

        if (!empty($args['activate'])) {
            $new_deactivations = array_diff($new_deactivations, $args['activate']);
        }

        if (!is_array($deactivated)) {
            $deactivated = [];
        }

        if (!is_array($new_deactivations)) {
            $new_deactivations = [];
        }

        $deactivated = array_merge(
            $deactivated, 
            array_fill_keys($new_deactivations, (object)[])
        );
        
        update_option('presspermit_deactivated_modules', $deactivated);
    }

    public static function populateRoles($reload_user = false)
    {
        $administrator_caps = array_fill_keys(
            [
            'pp_manage_settings', 
            'pp_administer_content', 
            'pp_create_groups', 
            'pp_edit_groups', 
            'pp_delete_groups', 
            'pp_manage_members', 
            'pp_assign_roles', 
            'pp_set_read_exceptions'
            ], 
            true
        );

        if ($role = @get_role('administrator')) {
            $role->remove_cap('pp_bulk_assign_roles');

            foreach (array_keys($administrator_caps) as $cap) {
                $role->add_cap($cap);
            }
        }

        if ($role = @get_role('editor')) {
            $role->remove_cap('pp_bulk_assign_roles');
            $role->add_cap('pp_create_groups');
            $role->add_cap('pp_edit_groups');
            $role->add_cap('pp_delete_groups');
            $role->add_cap('pp_manage_members');
            $role->add_cap('pp_assign_roles');
            $role->add_cap('pp_set_read_exceptions');
            $role->add_cap('pp_moderate_any');
        }

        update_option('ppperm_added_role_caps_10beta', true);

        if (is_super_admin() || current_user_can('administrator')) {
            global $current_user;

            $current_user->allcaps = array_merge($current_user->allcaps, $administrator_caps);

            $pp = presspermit();

            if ($pp->isUserSet()) {
                $pp->getUser()->allcaps = array_merge($pp->getUser()->allcaps, $current_user->allcaps);
            }
        }
    }

    public static function deleteOrphanedExceptions()
    {
        global $wpdb;

        // Direct query on plugin table for version update operation
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($orphan_ids = $wpdb->get_col(
            "SELECT eitem_id FROM $wpdb->ppc_exception_items AS i INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id "
            . "WHERE ( e.via_item_source = 'post' AND i.item_id NOT IN ( SELECT ID FROM $wpdb->posts ) )"
            . " OR ( e.via_item_source = 'term' AND i.item_id NOT IN ( SELECT term_taxonomy_id FROM $wpdb->term_taxonomy ) )"
        )) {
            $orphan_id_csv = implode("','", array_map('intval', $orphan_ids));

            $wpdb->query(
                "DELETE FROM $wpdb->ppc_exception_items WHERE eitem_id IN ('$orphan_id_csv')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
        }
    }

    public static function dropGroupIndexesSql()
    {
        // obsolete
    }

    public static function doIndexDrop($arr_sql, $option_key, $prevent_retry = true)
    {
        // obsolete
    }

    public static function syncWordPressRoles()
    {
        global $wpdb, $wp_roles;

        if (empty($wp_roles->role_objects)) {
            return;
        }

        // Prevent concurrent redundant execution
        if (did_action('presspermit_activate')) {
            return;
        }

        // Direct query on options table to use LIKE clause for this plugin update operation
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'presspermit_buffer_metagroup%'");

        $metagroups = [];
        $stored_metagroups = [];
        $all_role_metagroups = [];

        $wpdb->members_table = $wpdb->pp_group_members;

        $length_limit = apply_filters('presspermit_rolename_max_length', 40);
        if ($length_limit < 40) {
            $length_limit = 40;  // pp_groups:metagroup_id db column width is 64, but default to limit of 40 to avoid forcing change to any existing stored truncations
        }

        // sync WP Role metagroups
        foreach (array_keys($wp_roles->role_objects) as $role_name) {
            $metagroup_id = trim(substr($role_name, 0, $length_limit));

            // if the name is too long and its truncated ID already taken, just exclude it from eligible metagroups
            if (!isset($metagroups[$metagroup_id])) {
                $metagroups[$metagroup_id] = (object)[
                    'type' => 'wp_role',
                    'name' => sprintf(__('[WP %s]', 'press-permit-core'), $role_name),
                    'descript' => sprintf(__('All users with a WordPress %s role', 'press-permit-core'), $role_name)
                ];
            }
        }

        // add a metagroup for anonymous users
        $metagroups['wp_anon'] = (object)[
            'type' => 'wp_role',
            'name' => __('Not Logged In', 'press-permit-core'),
            'descript' => __('Anonymous users (not logged in)', 'press-permit-core')
        ];

        // add a metagroup for authenticated users
        $metagroups['wp_auth'] = (object)[
            'type' => 'wp_role',
            'name' => __('Logged In', 'press-permit-core'),
            'descript' => __('All users who are logged in and have a role on the site', 'press-permit-core')
        ];

        // add a metagroup for all users
        $metagroups['wp_all'] = (object)[
            'type' => 'wp_role',
            'name' => __('Everyone', 'press-permit-core'),
            'descript' => __('All users (including anonymous)', 'press-permit-core')
        ];

        // add metagroups for Revisionary notification recipients
        $metagroups['rvy_pending_rev_notice'] = (object)[
            'type' => 'rvy_notice',
            'name' => __('Submitted Revision Editors', 'press-permit-core'),
            'descript' => __('Users to notify when revisions are submitted', 'press-permit-core')
        ];

        $metagroups['rvy_scheduled_rev_notice'] = (object)[
            'type' => 'rvy_notice',
            'name' => __('Scheduled Revision Monitors', 'press-permit-core'),
            'descript' => __('Users to notify when scheduled revisions are published', 'press-permit-core')
        ];

        $do_group_deletions = defined('PP_AUTODELETE_ROLE_METAGROUPS') && PP_AUTODELETE_ROLE_METAGROUPS;

        // Direct queries on plugin tables for plugin maintenance operation
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($results = $wpdb->get_results("SELECT * FROM $wpdb->pp_groups WHERE metagroup_id != ''")) {
            $delete_metagroup_ids = [];

            foreach ($results as $row) {
                if (!isset($metagroups[$row->metagroup_id]) && $do_group_deletions) {
                    $delete_metagroup_ids[] = $row->ID;
                } else {
                    $stored_metagroups[$row->metagroup_id] = true;

                    if ('wp_role' == $row->metagroup_type) {
                        $all_role_metagroups[$row->metagroup_id] = $row->ID;
                    }
                }
            }

            if ($delete_metagroup_ids) {
                $id_csv = implode("','", array_map('intval', $delete_metagroup_ids));

                $wpdb->query(
                    "DELETE FROM $wpdb->pp_groups WHERE ID IN ('$id_csv')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                );

                $wpdb->query(
                    "DELETE FROM $wpdb->members_table WHERE group_id IN ('$id_csv')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                );
            }
        }

        if ($insert_metagroups = array_diff_key($metagroups, $stored_metagroups)) {
            foreach ($insert_metagroups as $metagroup_id => $metagroup) {
                $wpdb->insert(
                    $wpdb->pp_groups,
                    [
                        'metagroup_id' => $metagroup_id,
                        'metagroup_type' => $metagroup->type,
                        'group_name' => $metagroup->name,
                        'group_description' => $metagroup->descript
                    ]
                );

                if (('wp_role' == $metagroup->type) && $wpdb->insert_id) {
                    $all_role_metagroups[$metagroup_id] = $wpdb->insert_id;
                }
            }
        }

        update_option('presspermit_wp_role_sync', true);
    }
}

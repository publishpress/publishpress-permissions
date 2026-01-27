<?php

namespace PublishPress\Permissions\DB;

class Migration
{
    public static function migrateOptions()
    {
        global $wpdb;

        // Direct query of plugin table on plugin version update operation
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $options = $wpdb->get_results("SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'pp\_%'");
        foreach ($options as $row) {
            update_option('presspermit_' . substr($row->option_name, 3), maybe_unserialize($row->option_value));
        }

        $wpdb->query(
            "UPDATE $wpdb->options SET autoload = 'no' WHERE option_name LIKE 'presspermit_%'"
            . " AND option_name NOT LIKE '%_version' AND option_name NOT IN ('presspermit_custom_conditions_post_status')"
        );

        // migrate PP Extension activation status to deactived_modules option
        if ((false === get_option('presspermit_deactivated_modules')) && get_option("pp_c_version")) {
            $ext_map = [
                'presspermit-circles'       => 'pp-circles',
                'presspermit-collaboration' => 'pp-collaborative-editing',
                'presspermit-compatibility' => 'pp-compatibility',
                'presspermit-file-access'   => 'pp-file-url-filter',
                'presspermit-membership'    => 'pp-membership', 
                'presspermit-teaser'        => 'pp-content-teaser',
            ];

            $ext_option_name = [
                'pp-circles'                => 'ppcc_version',
                'pp-collaborative-editing'  => 'ppce_version',
                'pp-compatibility'          => 'ppp_version',
                'pp-file-url-filter'        => 'pp_unattached_files_private',
                'pp-membership'             => 'ppm_version',
                'pp-custom-post-statuses'   => 'pps_version',
                'pp-content-teaser'         => 'pp_tease_post_types',
            ];

            $any_activated = false;

            $deactivate_modules = [];
            foreach($ext_map as $module_plugin_slug => $legacy_slug) {
                if (isset($ext_option_name[$legacy_slug])) {
                    $deactivate = ('presspermit-file-access' == $module_plugin_slug) 
                    ? false === get_option($ext_option_name[$legacy_slug])  // may be set to "0", with file filtering still active
                    : !get_option($ext_option_name[$legacy_slug]);

                    if ($deactivate) {
                        // Don't default-disable the compat module, which has functionality previously contained in BuddyPress Role Groups, WPML extensions.
                        if ('presspermit-compatibility' != $module_plugin_slug) {
                            $deactivate_modules[$module_plugin_slug] = (object)[];
                        }
                    } else {
                        $any_activated = true;
                    }
                }
            }

            if (!$any_activated) {
                $deactivate_modules = array_fill_keys(
                    ['presspermit-circles', 'presspermit-file-access', 'presspermit-membership'], 
                    (object)[]
                );
            }

            update_option('presspermit_deactivated_modules', $deactivate_modules);
        }
    }

    // ===== begin 2.1.35 cleanup =====

    // returns propagated exceptions items for which the affected item is an attachment
    private static function get_propagated_attachment_exceptions()
    {
        global $wpdb;

        // Direct query of plugin table on plugin version update operation
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_col(
            "SELECT eitem_id FROM $wpdb->ppc_exception_items AS i INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id"
            . " WHERE e.for_item_source = 'post' AND i.inherited_from > 0 AND e.for_item_type = 'attachment'"
        );
    }

    public static function expose_attachment_exception_items()
    {
        global $wpdb;

        if ($eitem_ids = self::get_propagated_attachment_exceptions()) {
            $eitem_id_csv = implode("','", array_map('intval', $eitem_ids));

            // Direct query of plugin table on plugin admin operation (IN clause constructed and sanitized above)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                "UPDATE $wpdb->ppc_exception_items SET inherited_from = 0 WHERE eitem_id IN ('$eitem_id_csv')"  //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );

            // keep a log in case questions arise
            if (!$arr = get_option('ppc_exposed_attachment_eitems'))
                $arr = [];

            $arr = array_merge($arr, $eitem_ids);
            update_option('ppc_exposed_attachment_eitems', $arr);
        }
    }

    public static function delete_propagated_attachment_exceptions()
    {
        global $wpdb;

        // first, delete any attachment exception with assign_for = 'children'

        // Direct query of plugin table on plugin admin operation
        // phpcs:delete WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($eitem_ids = $wpdb->get_col(
            "SELECT eitem_id FROM $wpdb->ppc_exception_items AS i INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id"
            . " WHERE e.for_item_source = 'post' AND e.for_item_type = 'attachment' AND i.assign_for = 'children'"
        )) {
            $eitem_id_csv = implode("','", array_map('intval', $eitem_ids));

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("DELETE FROM $wpdb->ppc_exception_items WHERE eitem_id IN ('$eitem_id_csv')");
        }

        // page exceptions should not be propagated to attachments (but were prior to 2.1.35)
        foreach (['read', 'associate'] as $operation) { // retain editing exceptions - to be exposed by self::get_propagated_attachment_exceptions()
            $mod_type_clause = ('associate' == $operation) ? '' : "AND e.mod_type != 'include'"; // keep include exceptions in case they are being relied upon to modify access to other media.  They will be exposed by self::get_propagated_attachment_exceptions().

            if ($results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT eitem_id, item_id FROM $wpdb->ppc_exception_items AS i"
                        . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id"
                        . " WHERE i.inherited_from > 0 AND e.for_item_source = 'post' AND e.for_item_type = 'attachment'"
                        . " AND e.operation = %s $mod_type_clause", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                        $operation
                    )
                )
            ) {
                $eitem_ids = [];
                $item_ids = [];

                foreach ($results as $row) {
                    $eitem_ids[] = $row->eitem_id;
                    $item_ids[] = $row->item_id;
                }

                $eitem_id_csv = implode("','", array_map('intval', $eitem_ids));

                $wpdb->query(
                    "DELETE FROM $wpdb->ppc_exception_items WHERE eitem_id IN ('$eitem_id_csv')"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                );

                // keep a log in case questions arise
                if (!$arr = get_option("ppc_deleted_{$operation}_exc_attachments"))
                    $arr = [];

                $arr = array_merge($arr, array_unique($item_ids));
                update_option("ppc_deleted_{$operation}_exc_attachments", $arr);
            }
        }
    }

    // ===== end 2.1.35 cleanup =====

    public static function remove_group_members_pk()
    {
        global $wpdb;

        if ($tableindices = $wpdb->get_results("SHOW INDEX FROM $wpdb->pp_group_members")) {

            foreach ($tableindices as $tableindex) {
                if ('PRIMARY' == $tableindex->Key_name) {
                    // phpcs:delete WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->query("ALTER TABLE $wpdb->pp_group_members MODIFY group_id bigint(20) unsigned NOT NULL default '0'");   // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
                    $wpdb->query("ALTER TABLE $wpdb->pp_group_members MODIFY user_id bigint(20) unsigned NOT NULL default '0'");    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
                    $wpdb->query("ALTER TABLE $wpdb->pp_group_members DROP PRIMARY KEY");                                           // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
                    $wpdb->query("ALTER TABLE $wpdb->pp_group_members ADD INDEX `pp_group_user` (`group_id`,`user_id`)");           // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
                }
            }
        }
    }
}

<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class ItemDelete
{
    public static function itemDeleted($item_source, $item_id)
    {
        global $wpdb;

        require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php');

        // delete role assignments for deleted term

        // Direct query of plugin table on post / term deletion
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($eitem_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT eitem_id FROM $wpdb->ppc_exception_items AS i"
                . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id WHERE e.via_item_source = %s"
                . " AND i.item_id = %d",

                $item_source,
                $item_id
            )
        )) {
            DB\PermissionsUpdate::removeExceptionItemsById($eitem_ids);

            // Propagated roles will be converted to direct-assigned roles if the original progenetor goes away.  Removal of a "link" in the parent/child propagation chain has no effect.
            $eitem_id_csv = implode("', '", array_map('intval', $eitem_ids));

            // Direct query of plugin table on post / term deletion
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                "UPDATE $wpdb->ppc_exception_items SET inherited_from = '0' WHERE inherited_from IN ('$eitem_id_csv')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
        }
    }
}

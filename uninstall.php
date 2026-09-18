<?php
if (get_option('presspermit_delete_settings_on_uninstall')) {
    global $wpdb;

    // Since stored settings are shared among all installed versions, 
    // all copies of the plugin (both Pro and Free) need to be deleted (not just deactivated) before deleting any settings.
    $permissions_plugin_count = 0;

    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    $_plugins = get_plugins();
    
    foreach($_plugins as $_plugin) {
        if (!empty($_plugin['Title']) && in_array($_plugin['Title'], ['PublishPress Permissions', 'PublishPress Permissions Pro'])) {
            $permissions_plugin_count++;
        }
    }
    
    if ($permissions_plugin_count === 1) {
        $orig_site_id = get_current_blog_id();

        // Uninstall must finish synchronously in this one request (there's no plugin code left to
        // resume a deferred/async cleanup once it completes), so on a large multisite network this
        // can still take a while. Avoid at least loading every site ID into memory at once by
        // paging through get_sites() in bounded batches, and lift the execution time limit where
        // the host allows it, rather than fetching every site ID and running unbounded.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $site_batch_size = 100;
        $site_offset = 0;

        do {
            if (function_exists('get_sites')) {
                $site_ids = get_sites(['fields' => 'ids', 'number' => $site_batch_size, 'offset' => $site_offset]);
            } else {
                $site_ids = (0 === $site_offset) ? (array) $orig_site_id : [];
            }

            foreach ($site_ids as $_blog_id) {
                if (is_multisite()) {
                	switch_to_blog($_blog_id);
                }

                if (!empty($wpdb->options)) {
                    @$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%presspermit%'");
                    @$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'ppperm_%'");
                }

                delete_option('ppcc_version');
                delete_option('ppce_version');
                delete_option('ppi_version');
                delete_option('ppm_version');
                delete_option('ppp_version');
                delete_option('pps_version');

                wp_load_alloptions(true);

                if (!empty($wpdb->postmeta)) {
                    @$wpdb->query(
                        "DELETE FROM $wpdb->postmeta WHERE meta_key IN ("
                        . "'_pp_wpml_mirrored_exceptions', '_rs_file_key', '_last_attachment_ids', '_last_category_ids', '_last_post_tag_ids', '_pp_last_parent', '_pp_sync_author_id', '_pp_is_autodraft', '_pp_is_auto_inserted'"
                        . ")"
                    );
                }

                // phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange

                if (!empty($wpdb->pp_groups)) {
                    @$wpdb->query("DROP TABLE IF EXISTS $wpdb->pp_groups");
                }

                if (!empty($wpdb->pp_group_members)) {
                    @$wpdb->query("DROP TABLE IF EXISTS $wpdb->pp_group_members");
                }

                if (!empty($wpdb->ppc_roles)) {
                    @$wpdb->query("DROP TABLE IF EXISTS $wpdb->ppc_roles");
                }

                if (!empty($wpdb->ppc_exceptions)) {
                    @$wpdb->query("DROP TABLE IF EXISTS $wpdb->ppc_exceptions");
                }

                if (!empty($wpdb->ppc_exception_items)) {
                    @$wpdb->query("DROP TABLE IF EXISTS $wpdb->ppc_exception_items");
                }

                if (!empty($wpdb->pp_circles)) {
                    @$wpdb->query("DROP TABLE IF EXISTS $wpdb->pp_circles");
                }

                if (!empty($wpdb->ppi_runs)) {
                    @$wpdb->query("DROP TABLE IF EXISTS $wpdb->ppi_runs");
                    @$wpdb->query("DROP TABLE IF EXISTS $wpdb->ppi_imported");
                    @$wpdb->query("DROP TABLE IF EXISTS $wpdb->ppi_errors");
                }
            }

            $site_offset += $site_batch_size;
        } while (count($site_ids) === $site_batch_size);

        if (is_multisite()) {
        	switch_to_blog($orig_site_id);
        }
    }
}

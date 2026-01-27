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
        
        $site_ids = (function_exists('get_sites')) ? get_sites(['fields' => 'ids']) : (array) $orig_site_id;
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

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

        if (is_multisite()) {
        	switch_to_blog($orig_site_id);
        }
    }
}

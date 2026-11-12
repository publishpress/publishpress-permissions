<?php

namespace PublishPress\Permissions\DB;

\defined('ABSPATH') || exit;

class DatabaseSetup
{
    function __construct($last_db_ver = false)
    {
        self::updateSchema($last_db_ver);
    }

    private static function updateSchema($last_db_ver = false)
    {
        global $wpdb;

        // Prevent concurrent redundant execution
        if (did_action('presspermit_activate')) {
            return;
        }

        // Direct database query to create or modify plugin tables. Infrequent plugin install operation.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $charset_collate = '';

        if (!empty($wpdb->charset))
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";

        if (!empty($wpdb->collate))
            $charset_collate .= " COLLATE $wpdb->collate";

        // note: dbDelta requires two spaces after PRIMARY KEY, no spaces between KEY columns

        // Groups table def 
        $tabledefs = "CREATE TABLE $wpdb->pp_groups (
         ID bigint(20) NOT NULL auto_increment,
         group_name text NOT NULL,
         group_description text NOT NULL,
         metagroup_id varchar(64) NOT NULL default '',
         metagroup_type varchar(32) NOT NULL default '',
            PRIMARY KEY  (ID),
            KEY pp_grp_metaid (metagroup_type,metagroup_id) )
            $charset_collate
        ;
        ";

        // For existing installations, don't risk existing data violating unique key requirement
        if ($last_db_ver && defined('PRESSPERMIT_LEGACY_DB_SETUP')) {
            $key_clause = 'KEY pp_group_user';
        } elseif (defined('PRESSPERMIT_GROUP_MEMBERS_NO_PRIMARY_KEY')) {
            $key_clause = 'UNIQUE KEY pp_group_user';
        } else {
            $key_clause = 'PRIMARY KEY ';
        }

        // User2Group table def
        $tabledefs .= "CREATE TABLE $wpdb->pp_group_members (
         group_id bigint(20) unsigned NOT NULL default '0',
         user_id bigint(20) unsigned NOT NULL default '0',
         member_type varchar(32) NOT NULL default 'member',
         status varchar(32) NOT NULL default 'active',
         add_date_gmt datetime NOT NULL default '0000-00-00 00:00:00',
         date_limited tinyint(2) NOT NULL default '0',
         start_date_gmt datetime NOT NULL default '0000-00-00 00:00:00',
         end_date_gmt datetime NOT NULL default '2035-01-01 00:00:00',
            {$key_clause} (group_id,user_id),
            KEY pp_member_status (status,member_type),
            KEY pp_member_date (start_date_gmt,end_date_gmt,date_limited,user_id,group_id) )
            $charset_collate
        ;
        ";

        /*  ppc_roles: 
        // note: dbDelta requires two spaces after PRIMARY KEY, no spaces between KEY columns
        //
            agent_type: user / pp_group / bp_group / etc.
            agent_id: ID of user or group who the role is applied to
            role_name potentially contains "base_rolename:source_name:type_or_taxonomy:attribute:condition"
                                                (<=40)     (<=32)         (<=32)        (<=32)    (<=32)
            assigner_id: ID of user who stored the role
        */
        $tabledefs .= "CREATE TABLE $wpdb->ppc_roles (
         assignment_id bigint(20) unsigned NOT NULL auto_increment,
         agent_id bigint(20) unsigned NOT NULL,
         agent_type varchar(32) NOT NULL,
         role_name varchar(176) NOT NULL,
         assigner_id bigint(20) unsigned NOT NULL default '0',
            PRIMARY KEY  (assignment_id),
            KEY pp_role2agent (agent_type,agent_id,role_name),
            KEY pp_rolename (role_name) )
            $charset_collate
        ;
        ";

        /* ppc_exceptions:

            agent_type: user / pp_group / bp_group / etc.
            agent_id: ID of user or group who the exception is applied to
            for_item_source: data source of items which are affected by the exception
            for_item_type: which post or taxonomy type (post / page / category / etc) is affected by the exception (nullstring means all types that use specified for_item_source)
            for_item_status: posts of this status (or other property) are affected by the exception. Storage is "post_status:private" (nullstring means all stati)
            operation: read / edit / assign / parent / revise / etc.
            mod_type: include / exclude / additional
            via_item_source: data source of the item which triggers the exception (if for_item_type is page, via_item_source could be term)
            via_item_type: post type or taxonomy which triggers the exception (Needed for when post exceptions are based on term assignment. Nullstring means redundant / not applicable)
            assigner_id: ID of user who stored the exception
        */

        $tabledefs .= "CREATE TABLE $wpdb->ppc_exceptions (
         exception_id bigint(20) unsigned NOT NULL auto_increment,
         agent_type varchar(32) NOT NULL,
         agent_id bigint(20) unsigned NOT NULL,
         for_item_source varchar(32) NOT NULL,
         for_item_type varchar(32) NOT NULL default '',
         for_item_status varchar(127) NOT NULL default '',
         operation varchar(32) NOT NULL,
         mod_type varchar(32) NOT NULL,
         via_item_source varchar(32) NOT NULL,
         via_item_type varchar(32) NOT NULL default '',
         assigner_id bigint(20) unsigned NOT NULL default '0',
            PRIMARY KEY  (exception_id),
            KEY pp_exc (agent_type,agent_id,exception_id,operation,for_item_type),
            KEY pp_exc_mod (mod_type),
            KEY pp_exc_status (for_item_status) )
            $charset_collate
        ;
        ";

        /* ppc_exception_items:
        
            exception_id: foreign key to ppc_exceptions
            item_id: post ID or term_id
            assign_for: exception applies to item or its children?
            inherited_from: eitem_id value from propagating parent
            assigner_id: ID of user who stored the exception item
        */
        $tabledefs .= "CREATE TABLE $wpdb->ppc_exception_items (
         eitem_id bigint(20) unsigned NOT NULL auto_increment,
         exception_id bigint(20) unsigned NOT NULL,
         item_id bigint(20) unsigned NOT NULL,
         assign_for enum('item', 'children') NOT NULL default 'item',
         inherited_from bigint(20) unsigned NULL default '0',
         assigner_id bigint(20) unsigned NOT NULL default '0',
            PRIMARY KEY  (eitem_id),
            KEY pp_exception_item (item_id,exception_id,assign_for),
            KEY pp_eitem_fk (exception_id) )
            $charset_collate
        ;
        ";

        // apply all table definitions
        self::dbDelta($tabledefs);
    } //end updateSchema function

    public static function dbDelta($queries, $execute = true)
    {  // lifted from WP because forced inclusion of schema.php interferes with site creation
        global $wpdb;

        // Separate individual queries into an array
        if (!is_array($queries)) {
            $queries = explode(';', $queries);
            if ('' == $queries[count($queries) - 1]) array_pop($queries);
        }

        $cqueries = []; // Creation Queries
        $iqueries = []; // Insertion Queries
        $for_update = [];

        // Create a tablename index for an array ($cqueries) of queries
        foreach ($queries as $qry) {
            if (preg_match("|CREATE TABLE (?:IF NOT EXISTS )?([^ ]*)|", $qry, $matches)) {
                $cqueries[trim(strtolower($matches[1]), '`')] = $qry;
                $for_update[$matches[1]] = 'Created table ' . $matches[1];
            } else if (preg_match("|CREATE DATABASE ([^ ]*)|", $qry, $matches)) {
                array_unshift($cqueries, $qry);
            } else if (preg_match("|INSERT INTO ([^ ]*)|", $qry, $matches)) {
                $iqueries[] = $qry;
            } else if (preg_match("|UPDATE ([^ ]*)|", $qry, $matches)) {
                $iqueries[] = $qry;
            } else {
                // Unrecognized query type
            }
        }

        // Check to see which tables and fields exist
        if ($tables = $wpdb->get_col('SHOW TABLES;')) {
            // For every table in the database
            foreach ($tables as $table) {
                // If a table query exists for the database table...
                if (array_key_exists(strtolower($table), $cqueries)) {
                    // Clear the field and index arrays
                    $cfields = [];
                    $indices = [];

                    // Get all of the field names in the query from between the parens
                    preg_match("|\((.*)\)|ms", $cqueries[strtolower($table)], $match2);
                    $qryline = trim($match2[1]);

                    // Separate field lines into an array
                    $flds = explode("\n", $qryline);

                    // For every field line specified in the query
                    foreach ($flds as $fld) {
                        // Extract the field name
                        preg_match("|^([^ ]*)|", trim($fld), $fvals);
                        $fieldname = trim($fvals[1], '`');

                        // Verify the found field name
                        $validfield = true;
                        switch (strtolower($fieldname)) {
                            case '':
                            case 'primary':
                            case 'index':
                            case 'fulltext':
                            case 'unique':
                            case 'key':
                                $validfield = false;
                                $indices[] = trim(trim($fld), ", \n");
                                break;
                        }
                        $fld = trim($fld);

                        // If it's a valid field, add it to the field array
                        if ($validfield) {
                            $cfields[strtolower($fieldname)] = trim($fld, ", \n");
                        }
                    }

                    $table = sanitize_key($table);

                    // Fetch the table column structure from the database (NOTE: table variable is not to be quoted, so sanitized above)
                    $tablefields = $wpdb->get_results("DESCRIBE {$table};");  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                    // For every field in the table
                    foreach ($tablefields as $tablefield) {
                        // If the table field exists in the field array...
                        if (array_key_exists(strtolower($tablefield->Field), $cfields)) {
                            // Get the field type from the query
                            preg_match("|" . $tablefield->Field . " ([^ ]*( unsigned)?)|i", $cfields[strtolower($tablefield->Field)], $matches);
                            $fieldtype = $matches[1];

                            // Is actual field type different from the field type in query?
                            if ($tablefield->Type != $fieldtype) {
                                // Add a query to change the column type
                                $cqueries[] = "ALTER TABLE {$table} CHANGE COLUMN {$tablefield->Field} " . $cfields[strtolower($tablefield->Field)];
                                $for_update[$table . '.' . $tablefield->Field] = "Changed type of {$table}.{$tablefield->Field} from {$tablefield->Type} to {$fieldtype}";
                            }

                            // Get the default value from the array
                            if (preg_match("| DEFAULT '(.*)'|i", $cfields[strtolower($tablefield->Field)], $matches)) {
                                $default_value = $matches[1];
                                if ($tablefield->Default != $default_value) {
                                    // Add a query to change the column's default value
                                    $cqueries[] = "ALTER TABLE {$table} ALTER COLUMN {$tablefield->Field} SET DEFAULT '{$default_value}'";
                                    $for_update[$table . '.' . $tablefield->Field] = "Changed default value of {$table}.{$tablefield->Field} from {$tablefield->Default} to {$default_value}";
                                }
                            }

                            // Remove the field from the array (so it's not added)
                            unset($cfields[strtolower($tablefield->Field)]);
                        } else {
                            // This field exists in the table, but not in the creation queries?
                        }
                    }

                    // For every remaining field specified for the table
                    foreach ($cfields as $fieldname => $fielddef) {
                        // Push a query line into $cqueries that adds the field to that table
                        $cqueries[] = "ALTER TABLE {$table} ADD COLUMN $fielddef";
                        $for_update[$table . '.' . $fieldname] = 'Added column ' . $table . '.' . $fieldname;
                    }

                    $table = sanitize_key($table);

                    // Index stuff goes here
                    // Fetch the table index structure from the database  (NOTE: table variable is not to be quoted, so sanitized above)
                    $tableindices = $wpdb->get_results("SHOW INDEX FROM {$table};"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                    if ($tableindices) {
                        // Clear the index array
                        $index_ary = [];

                        // For every index in the table
                        foreach ($tableindices as $tableindex) {
                            // Add the index to the index data array
                            $keyname = $tableindex->Key_name;
                            $index_ary[$keyname]['columns'][] = ['fieldname' => $tableindex->Column_name, 'subpart' => $tableindex->Sub_part];
                            $index_ary[$keyname]['unique'] = ($tableindex->Non_unique == 0) ? true : false;
                        }

                        // For each actual index in the index array
                        foreach ($index_ary as $index_name => $index_data) {
                            // Build a create string to compare to the query
                            $index_string = '';
                            if ($index_name == 'PRIMARY') {
                                $index_string .= 'PRIMARY ';
                            } else if ($index_data['unique']) {
                                $index_string .= 'UNIQUE ';
                            }
                            $index_string .= 'KEY ';
                            if ($index_name != 'PRIMARY') {
                                $index_string .= $index_name;
                            }
                            $index_columns = '';
                            // For each column in the index
                            foreach ($index_data['columns'] as $column_data) {
                                if ($index_columns != '') $index_columns .= ',';
                                // Add the field to the column list string
                                $index_columns .= $column_data['fieldname'];
                                if ($column_data['subpart'] != '') {
                                    $index_columns .= '(' . $column_data['subpart'] . ')';
                                }
                            }
                            // Add the column list to the index create string
                            $index_string .= ' (' . $index_columns . ')';
                            if (!(($aindex = array_search($index_string, $indices)) === false)) {
                                unset($indices[$aindex]);
                            }
                        }
                    }

                    // For every remaining index specified for the table
                    foreach ((array) $indices as $index) {
                        // Check if the index already exists
                        $index_name = preg_match('/KEY\s+([^\s]+)/', $index, $matches) ? $matches[1] : '';
                        if ($index_name && !isset($index_ary[$index_name])) {
                            // Push a query line into $cqueries that adds the index to that table
                            $cqueries[] = "ALTER TABLE {$table} ADD $index";
                            $for_update[$table . '.' . $index_name] = 'Added index ' . $table . ' ' . $index;
                        }
                    }

                    // Remove the original table creation query from processing
                    unset($cqueries[strtolower($table)]);
                    unset($for_update[strtolower($table)]);
                } else {
                    // This table exists in the database, but not in the creation queries?
                }
            }
        }

        $allqueries = array_merge($cqueries, $iqueries);
        if ($execute) {
            foreach ($allqueries as $query) {
                // Execute the queries that were built and sanitized above. Longstanding code from WP core dbDelta().
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query($query);
            }
        }

        return $for_update;
    }
}

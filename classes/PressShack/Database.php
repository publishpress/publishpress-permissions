<?php

namespace PressShack;

\defined('ABSPATH') || exit;

class Database
{
    // deprecated
    public static function dbDelta($queries, $execute = true)
    { 
        if (defined('PRESSPERMIT_CLASSPATH')) {
            require_once(PRESSPERMIT_CLASSPATH . '/DB/DatabaseSetup.php');
            return \PublishPress\Permissions\DB\DatabaseSetup::dbDelta($tabledefs, $execute);
                        } else {
            return [];
        }
    }
}

<?php
defined('ABSPATH') || exit;

function _presspermit_legacy_key_status($refresh = false) {
    $opt_val = presspermit()->getOption('support_key');
    
    if (!is_array($opt_val) || count($opt_val) < 2) {
        return false;
    } else {
        if (is_array($opt_val) && count($opt_val) >= 2) {
        if (1 == $opt_val[0]) {
            return true;
        } elseif (-1 == $opt_val[0]) {
            return 'expired';
        }
        }
    }

    return false;
}
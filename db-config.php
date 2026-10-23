<?php
defined('ABSPATH') || exit;

global $wpdb;

$wpdb->ppc_roles = $wpdb->prefix . 'ppc_roles';
$wpdb->pp_groups = apply_filters('presspermit_groups_table', $wpdb->prefix . 'pp_groups');
$wpdb->pp_group_members = $wpdb->prefix . 'pp_group_members';
$wpdb->ppc_exceptions = $wpdb->prefix . 'ppc_exceptions';
$wpdb->ppc_exception_items = $wpdb->prefix . 'ppc_exception_items';

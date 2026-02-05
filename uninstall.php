<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('custom_advance_repeater_version');
delete_option('custom_advance_repeater_installed');
delete_option('custom_advance_repeater_debug');
delete_option('custom_advance_repeater_image_width');
delete_option('custom_advance_repeater_image_height');

// Delete database tables
global $wpdb;

$tables = array(
    $wpdb->prefix . 'custom_advance_repeater_fields',
    $wpdb->prefix . 'custom_advance_repeater_field_groups'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Clear any cached data that might be related
wp_cache_flush();
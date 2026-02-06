<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options (ensure these match your add_option keys)
delete_option('custom_advance_repeater_version');
delete_option('custom_advance_repeater_installed');
delete_option('custom_advance_repeater_debug');
delete_option('custom_advance_repeater_image_width');
delete_option('custom_advance_repeater_image_height');

// Delete database tables
global $wpdb;

// Corrected table names to match your database screenshot
$tables = array(
    $wpdb->prefix . 'carf_fields',
    $wpdb->prefix . 'carf_field_groups'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Clear any cached data
wp_cache_flush();
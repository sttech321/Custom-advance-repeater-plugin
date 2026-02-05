<?php
/*
Plugin Name: Custom Advance Repeater
Description: A WordPress plugin that allows you to manage dynamic repeater fields with various field types, including nested repeaters.
Support Email: St.tech321@gmail.com
Version: 1.8.1
Author: Supreme Technologies
Author URI: https://supremetechnologies.us/
Text Domain: custom-advance-repeater
Domain Path: /languages
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('carf_VERSION', '1.8.1');
define('carf_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('carf_PLUGIN_URL', plugin_dir_url(__FILE__));

// Require the core class
require_once carf_PLUGIN_DIR . 'includes/class-core.php';                   


// Initialize the plugin
function carf_init() {
    Custom_Advance_Repeater_Core::get_instance();
}
add_action('plugins_loaded', 'carf_init');

// --- Helper functions for theme developers (Kept global as per original) ---

if (!function_exists('carf_get_repeater')) {
    function carf_get_repeater($field_group, $post_id = null) {
        $plugin = Custom_Advance_Repeater_Core::get_instance();
        $post_id = $post_id ?: get_the_ID();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'carf_field_groups';
        $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE slug = %s", $field_group));
        
        if (!$group) {
            return array();
        }
        
        // Access Admin logic to check display rules
        if (!$plugin->admin->should_display_field_group($group, $post_id)) {
            return array();
        }
        
        // Access DB logic to get data
        return $plugin->db->get_field_data($post_id, $field_group);
    }
} 

if (!function_exists('carf_display_repeater')) {
    function carf_display_repeater($field_group, $post_id = null, $limit = -1) {
        $plugin = Custom_Advance_Repeater_Core::get_instance();
        $atts = array(
            'field' => $field_group,
            'post_id' => $post_id ?: get_the_ID(),
            'limit' => $limit
        );
        return $plugin->frontend->repeater_shortcode($atts);
    }
}

if (!function_exists('carf_get_field_groups')) {
    function carf_get_field_groups() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'carf_field_groups';
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
    }
}

// ==========================================================================
// HELPER FUNCTIONS (Add this to the bottom of custom-advance-repeater.php)
// ==========================================================================

if (!function_exists('urf_get_repeater_with_labels')) {
    function urf_get_repeater_with_labels($field_group_slug, $post_id = null) {
        $post_id = $post_id ?: get_the_ID();
        
        // 1. Get the Core Instance to access DB and Frontend classes
        $core = Custom_Advance_Repeater_Core::get_instance();
        
        // 2. Get the Field Group Configuration (to know field types)
        $group = $core->db->get_field_group_by_slug($field_group_slug);
        if (!$group) {
            return array();
        }

        // 3. Get the Raw Data from Database
        // We use the DB class method we defined earlier
        $raw_data = $core->db->get_field_data($post_id, $field_group_slug);
        if (empty($raw_data)) {
            return array();
        }

        // 4. Map configuration to field names for easier lookup
        $fields_config = maybe_unserialize($group->fields);
        $field_map = array();
        if (is_array($fields_config)) {
            foreach ($fields_config as $field) {
                $field_map[$field['name']] = $field;
            }
        }

        // 5. Process and Format the Data (Convert IDs to Images, Options to Labels)
        $formatted_data = array();

        foreach ($raw_data as $key => $value) {
            if (isset($field_map[$key])) {
                $field = $field_map[$key];
                
                // If it's a repeater, return the array as-is (for looping)
                if ($field['type'] === 'repeater') {
                    $formatted_data[$key] = $value;
                } else {
                    // For single fields, use the Frontend class to format value
                    // This handles Image IDs -> <img> tags and Select Values -> Labels
                    $formatted_data[$key] = $core->frontend->format_field_value($field, $value);
                }
            } else {
                // If no config found, return raw value
                $formatted_data[$key] = $value;
            }
        }

        return $formatted_data;
    }
}

// Check for updates
add_filter('site_transient_update_plugins', 'carf_check_for_update');

function carf_check_for_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    // URL to your info.json file from Step 1
    $remote_url = 'https://gitlab.com/public-group203419/repeater-field-wordpress-plugin/-/raw/main/info.json'; 
    
    $response = wp_remote_get($remote_url);

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
        $data = json_decode(wp_remote_retrieve_body($response));
        
        // If the version in the JSON is higher than the current plugin version
        if ($data && version_compare(carf_VERSION, $data->version, '<')) {
            $plugin_slug = plugin_basename(__FILE__); // custom-advance-repeater/custom-advance-repeater.php
            
            $obj = new stdClass();
            $obj->slug = 'custom-advance-repeater';
            $obj->plugin = $plugin_slug;
            $obj->new_version = $data->version;
            $obj->package = $data->download_url; // The link to the ZIP file
            
            $transient->response[$plugin_slug] = $obj;
        }
    }
    
    return $transient;
}
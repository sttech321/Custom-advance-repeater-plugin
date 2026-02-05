<?php
if (!defined('ABSPATH')) { exit; }

class Custom_Advance_Repeater_Ajax {

    public function __construct() {
        add_action('wp_ajax_carf_get_field_group', array($this, 'ajax_get_field_group'));
        add_action('wp_ajax_carf_get_pages', array($this, 'ajax_get_pages'));
    }

    public function ajax_get_field_group() {
        if (!check_ajax_referer('carf_ajax_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        $group_slug = sanitize_text_field($_POST['group_slug']);
        // Use Core singleton to access Database class
        $core = Custom_Advance_Repeater_Core::get_instance();
        $group = $core->db->get_field_group_by_slug($group_slug);
        
        if ($group) {
            wp_send_json_success($group);
        } else {
            wp_send_json_error('Field group not found');
        }
    }
    
    public function ajax_get_pages() {
        if (!check_ajax_referer('carf_ajax_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        $pages = get_posts($args);
        
        wp_send_json_success($pages);
    }
}
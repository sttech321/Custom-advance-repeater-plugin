<?php
if (!defined('ABSPATH')) { exit; }

require_once CAR_PLUGIN_DIR . 'includes/class-database.php';
require_once CAR_PLUGIN_DIR . 'includes/class-admin.php';
require_once CAR_PLUGIN_DIR . 'includes/class-frontend.php';
require_once CAR_PLUGIN_DIR . 'includes/class-ajax.php';

class Custom_Advance_Repeater_Core {
    
    private static $instance = null;
    
    public $db;
    public $admin;
    public $frontend;
    public $ajax;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Initialize Sub-classes
        $this->db = new Custom_Advance_Repeater_Database();
        $this->admin = new Custom_Advance_Repeater_Admin();
        $this->frontend = new Custom_Advance_Repeater_Frontend();
        $this->ajax = new Custom_Advance_Repeater_Ajax();

        $this->init_hooks();
        $this->check_version();
    }
    
    public function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(dirname(dirname(__FILE__)) . '/custom-advance-repeater.php', array($this->db, 'activate'));
        
        // Initialize
        add_action('init', array($this, 'init'));
        
        // Add image size for previews
        add_action('init', array($this, 'add_image_sizes'));

        // Save post hook (Delegated to Database class)
        add_action('save_post', array($this->db, 'save_post_data'), 10, 3);

        // Debug footer hook
        add_action('admin_footer-post.php', function() {
            global $post;
            if ($post) {
                $this->db->debug_database_state($post->ID);
            }
        });
    }
    
    public function init() {
        load_plugin_textdomain('custom-advance-repeater', false, dirname(plugin_basename(dirname(__FILE__))) . '/languages');
    }

    public function add_image_sizes() {
        add_image_size('car_thumbnail', 150, 150, true);
    }
    
    public function check_version() {
        $installed_version = get_option('car_version', '0');
        
        if (version_compare($installed_version, CAR_VERSION, '<')) {
            $this->db->upgrade_database();
            update_option('car_version', CAR_VERSION);
        }
    }

    // Helper logging function
    public function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Custom Advance Repeater DEBUG] ' . date('Y-m-d H:i:s') . ' - ' . $message);
        }
    }
}
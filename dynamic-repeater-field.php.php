<?php
/*
Plugin Name: Ultimate Repeater Field
Description: A WordPress plugin that allows you to manage dynamic repeater fields with various field types, including nested repeaters.
Version: 1.6.0
Author: Supreme
Text Domain: ultimate-repeater-field
Domain Path: /languages
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('URF_VERSION', '1.6.0');
define('URF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('URF_PLUGIN_URL', plugin_dir_url(__FILE__));

// Main Plugin Class
class Ultimate_Repeater_Field {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        $this->init_hooks();
        $this->check_version();
    }
    
    public function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize
        add_action('init', array($this, 'init'));
        
        // Admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // AJAX handlers
        add_action('wp_ajax_urf_get_field_group', array($this, 'ajax_get_field_group'));
        add_action('wp_ajax_urf_get_pages', array($this, 'ajax_get_pages'));
        
        // Frontend
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        add_shortcode('urf_repeater', array($this, 'repeater_shortcode'));
        
        // Save post hook
        add_action('save_post', array($this, 'save_post_data'), 10, 3);
        
        // Add image size for previews
        add_action('init', array($this, 'add_image_sizes'));
    }
    
    public function add_image_sizes() {
        add_image_size('urf_thumbnail', 150, 150, true);
    }
    
    public function check_version() {
        $installed_version = get_option('urf_version', '0');
        
        if (version_compare($installed_version, URF_VERSION, '<')) {
            $this->upgrade_database();
            update_option('urf_version', URF_VERSION);
        }
    }

    public function upgrade_database() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'urf_field_groups';
        
        // Check if pages column exists
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        $has_pages_column = false;
        
        foreach ($columns as $column) {
            if ($column->Field == 'pages') {
                $has_pages_column = true;
                break;
            }
        }
        
        // Add pages column if it doesn't exist
        if (!$has_pages_column) {
            $wpdb->query("ALTER TABLE $table_name ADD pages longtext NOT NULL DEFAULT '' AFTER post_types");
        }
        
        // Also run the full activation to ensure all tables are up to date
        $this->activate();
    }

    public function check_and_update_database() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'urf_field_groups';
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                slug varchar(255) NOT NULL,
                fields longtext NOT NULL,
                post_types longtext NOT NULL,
                pages longtext NOT NULL,
                settings longtext NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        $has_pages_column = false;
        
        foreach ($columns as $column) {
            if ($column->Field == 'pages') {
                $has_pages_column = true;
                break;
            }
        }
        
        if (!$has_pages_column) {
            $wpdb->query("ALTER TABLE $table_name ADD pages longtext NOT NULL DEFAULT '' AFTER post_types");
        }
    }

    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Main fields table
        $table_name = $wpdb->prefix . 'urf_fields';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            field_group varchar(255) NOT NULL,
            row_index int(11) DEFAULT 0,
            field_name varchar(255) NOT NULL,
            field_type varchar(50) NOT NULL,
            field_value longtext NOT NULL,
            parent_field varchar(255) DEFAULT NULL,
            parent_row_index int(11) DEFAULT NULL,
            field_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY field_group (field_group),
            KEY field_name (field_name),
            KEY parent_field (parent_field)
        ) $charset_collate;";
        
        // Field groups table
        $table_groups = $wpdb->prefix . 'urf_field_groups';
        
        $sql2 = "CREATE TABLE IF NOT EXISTS $table_groups (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            fields longtext NOT NULL,
            post_types longtext NOT NULL,
            pages longtext NOT NULL,
            settings longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
        
        $this->check_and_update_database();
        
        update_option('urf_version', URF_VERSION);
        update_option('urf_installed', time());
    }
    
    public function deactivate() {
        // Optional cleanup
    }
    
    public function init() {
        load_plugin_textdomain('ultimate-repeater-field', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Ultimate Repeater', 'ultimate-repeater-field'),
            __('Ultimate Repeater', 'ultimate-repeater-field'),
            'manage_options',
            'ultimate-repeater',
            array($this, 'admin_dashboard'),
            'dashicons-list-view',
            30
        );
        
        add_submenu_page(
            'ultimate-repeater',
            __('Field Groups', 'ultimate-repeater-field'),
            __('Field Groups', 'ultimate-repeater-field'),
            'manage_options',
            'urf-field-groups',
            array($this, 'field_groups_page')
        );
        
        add_submenu_page(
            'ultimate-repeater',
            __('Add New Field Group', 'ultimate-repeater-field'),
            __('Add New', 'ultimate-repeater-field'),
            'manage_options',
            'urf-add-field-group',
            array($this, 'add_field_group_page')
        );
    }
    
    public function admin_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php _e('Ultimate Repeater Field', 'ultimate-repeater-field'); ?></h1>
            
            <div class="urf-dashboard">
                <div class="urf-card">
                    <h2><?php _e('Getting Started', 'ultimate-repeater-field'); ?></h2>
                    <ol>
                        <li><?php _e('Create Field Groups with your desired fields', 'ultimate-repeater-field'); ?></li>
                        <li><?php _e('Assign field groups to post types or specific pages', 'ultimate-repeater-field'); ?></li>
                        <li><?php _e('Edit posts/pages to add repeater data', 'ultimate-repeater-field'); ?></li>
                        <li><?php _e('Display data in your theme using shortcodes or functions', 'ultimate-repeater-field'); ?></li>
                    </ol>
                </div>
                
                <div class="urf-card">
                    <h2><?php _e('Available Field Types', 'ultimate-repeater-field'); ?></h2>
                    <ul>
                        <li><strong><?php _e('Text', 'ultimate-repeater-field'); ?></strong> - <?php _e('Simple text input', 'ultimate-repeater-field'); ?></li>
                        <li><strong><?php _e('Textarea', 'ultimate-repeater-field'); ?></strong> - <?php _e('Multi-line text', 'ultimate-repeater-field'); ?></li>
                        <li><strong><?php _e('Image Upload', 'ultimate-repeater-field'); ?></strong> - <?php _e('Upload images with preview', 'ultimate-repeater-field'); ?></li>
                        <li><strong><?php _e('Select Dropdown', 'ultimate-repeater-field'); ?></strong> - <?php _e('Select from options', 'ultimate-repeater-field'); ?></li>
                        <li><strong><?php _e('Checkbox', 'ultimate-repeater-field'); ?></strong> - <?php _e('Multiple checkboxes', 'ultimate-repeater-field'); ?></li>
                        <li><strong><?php _e('Radio Buttons', 'ultimate-repeater-field'); ?></strong> - <?php _e('Single selection', 'ultimate-repeater-field'); ?></li>
                        <li><strong><?php _e('Color Picker', 'ultimate-repeater-field'); ?></strong> - <?php _e('Color selection', 'ultimate-repeater-field'); ?></li>
                        <li><strong><?php _e('Date Picker', 'ultimate-repeater-field'); ?></strong> - <?php _e('Date selection', 'ultimate-repeater-field'); ?></li>
                        <li><strong><?php _e('Repeater Field', 'ultimate-repeater-field'); ?></strong> - <?php _e('Nested repeater with sub-fields', 'ultimate-repeater-field'); ?></li>
                    </ul>
                </div>
                
                <div class="urf-card">
                    <h2><?php _e('New in Version 1.6.0', 'ultimate-repeater-field'); ?></h2>
                    <ul>
                        <li><strong><?php _e('Nested Repeater Support', 'ultimate-repeater-field'); ?></strong> - <?php _e('Repeater fields inside repeater fields', 'ultimate-repeater-field'); ?></li>
                        <li><strong><?php _e('Multi-level Nesting', 'ultimate-repeater-field'); ?></strong> - <?php _e('Support for deeply nested structures', 'ultimate-repeater-field'); ?></li>
                        <li><strong><?php _e('Improved UI', 'ultimate-repeater-field'); ?></strong> - <?php _e('Better interface for managing nested fields', 'ultimate-repeater-field'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <style>
            .urf-dashboard {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .urf-card {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .urf-card h2 {
                margin-top: 0;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
            }
            .urf-card ul, .urf-card ol {
                padding-left: 20px;
            }
            .urf-card li {
                margin-bottom: 8px;
            }
			

        </style>
        <?php
    }
    
    public function field_groups_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'urf_field_groups';
        $fields_table = $wpdb->prefix . 'urf_fields';
        
        if (isset($_GET['delete'])) {
            $id = intval($_GET['delete']);
            $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
            
            if ($group) {
                $wpdb->delete($table_name, array('id' => $id));
                $wpdb->delete($fields_table, array('field_group' => $group->slug));
                
                $nested_pattern = 'nested_' . $group->slug . '_%';
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $fields_table WHERE field_group LIKE %s",
                    $nested_pattern
                ));
                
                echo '<div class="notice notice-success"><p>' . __('Field group deleted successfully!', 'ultimate-repeater-field') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Field group not found!', 'ultimate-repeater-field') . '</p></div>';
            }
        }
        
        $field_groups = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Field Groups', 'ultimate-repeater-field'); ?>
                <a href="<?php echo admin_url('admin.php?page=urf-add-field-group'); ?>" class="page-title-action">
                    <?php _e('Add New', 'ultimate-repeater-field'); ?>
                </a>
            </h1>
            
            <?php if (empty($field_groups)): ?>
                <div class="notice notice-info">
                    <p><?php _e('No field groups found. Create your first field group!', 'ultimate-repeater-field'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'ultimate-repeater-field'); ?></th>
                            <th><?php _e('Slug', 'ultimate-repeater-field'); ?></th>
                            <th><?php _e('Post Types', 'ultimate-repeater-field'); ?></th>
                            <th><?php _e('Specific Pages', 'ultimate-repeater-field'); ?></th>
                            <th><?php _e('Fields Count', 'ultimate-repeater-field'); ?></th>
                            <th><?php _e('Actions', 'ultimate-repeater-field'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($field_groups as $group): 
                            $fields = maybe_unserialize($group->fields);
                            $post_types = maybe_unserialize($group->post_types);
                            $pages = maybe_unserialize($group->pages);
                        ?>
                            <tr>
                                <td><?php echo esc_html($group->name); ?></td>
                                <td><code><?php echo esc_html($group->slug); ?></code></td>
                                <td><?php 
                                    if (is_array($post_types)) {
                                        if (in_array('all', $post_types)) {
                                            echo __('All', 'ultimate-repeater-field');
                                        } else {
                                            echo implode(', ', $post_types);
                                        }
                                    } else {
                                        echo __('All', 'ultimate-repeater-field');
                                    }
                                ?></td>
                                <td>
                                    <?php 
                                    if (is_array($pages) && !empty($pages)) {
                                        $page_titles = array();
                                        foreach ($pages as $page_id) {
                                            $page = get_post($page_id);
                                            if ($page) {
                                                $page_titles[] = $page->post_title;
                                            }
                                        }
                                        echo implode(', ', $page_titles);
                                    } else {
                                        echo __('None', 'ultimate-repeater-field');
                                    }
                                    ?>
                                </td>
                                <td><?php echo is_array($fields) ? count($fields) : 0; ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=urf-add-field-group&edit=' . $group->id); ?>" class="button button-small">
                                        <?php _e('Edit', 'ultimate-repeater-field'); ?>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=urf-field-groups&delete=' . $group->id); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this field group?', 'ultimate-repeater-field'); ?>');">
                                        <?php _e('Delete', 'ultimate-repeater-field'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function add_field_group_page() {
    global $wpdb;
    
    $success_message = '';
    $error_message = '';
    
    if (isset($_GET['saved']) && $_GET['saved'] == '1') {
        $success_message = '<div class="notice notice-success"><p>' . __('Field group saved successfully!', 'ultimate-repeater-field') . '</p></div>';
    }
    
    if (isset($_POST['save_field_group'], $_POST['urf_field_group_nonce'])) {
        if (!wp_verify_nonce($_POST['urf_field_group_nonce'], 'urf_save_field_group')) {
            $error_message = '<div class="notice notice-error"><p>Security check failed</p></div>';
        } elseif (!current_user_can('manage_options')) {
            $error_message = '<div class="notice notice-error"><p>Permission denied</p></div>';
        } else {
            $result = $this->save_field_group();
            
            if ($result === true) {
                $redirect_url = !empty($_POST['group_id'])
                    ? admin_url('admin.php?page=urf-add-field-group&edit=' . intval($_POST['group_id']) . '&saved=1')
                    : admin_url('admin.php?page=urf-field-groups&saved=1');
                
                echo '<script type="text/javascript">
                    window.location.href = ' . json_encode($redirect_url) . ';
                </script>';
                exit;
            } else {
                $error_message = '<div class="notice notice-error"><p>' . esc_html($result) . '</p></div>';
            }
        }
    }
    
    $group_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $group = $group_id ? $this->get_field_group($group_id) : null;
    $selected_types = $group ? maybe_unserialize($group->post_types) : array('post', 'page');
    
    ?>
    <div class="wrap">
        <h1><?php echo $group ? __('Edit Field Group', 'ultimate-repeater-field') : __('Add New Field Group', 'ultimate-repeater-field'); ?></h1>
        
        <?php 
        echo $error_message;
        echo $success_message;
        ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('urf_save_field_group', 'urf_field_group_nonce'); ?>
            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="group_name"><?php _e('Group Name', 'ultimate-repeater-field'); ?> *</label></th>
                    <td>
                        <input type="text" id="group_name" name="group_name" class="regular-text" 
                               value="<?php echo $group ? esc_attr($group->name) : ''; ?>" required>
                        <p class="description"><?php _e('Enter a descriptive name for this field group', 'ultimate-repeater-field'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="group_slug"><?php _e('Group Slug', 'ultimate-repeater-field'); ?> *</label></th>
                    <td>
                        <input type="text" id="group_slug" name="group_slug" class="regular-text" 
                               value="<?php echo $group ? esc_attr($group->slug) : ''; ?>" required>
                        <p class="description"><?php _e('Unique identifier (lowercase, no spaces)', 'ultimate-repeater-field'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label><?php _e('Display Logic', 'ultimate-repeater-field'); ?></label></th>
                    <td>
                        <p>
                            <label>
                                <input type="radio" name="display_logic" value="all" <?php echo !$group || (empty($selected_types) && empty($group->pages)) || (is_array($selected_types) && in_array('all', $selected_types)) ? 'checked' : ''; ?>>
                                <?php _e('Show on all posts/pages', 'ultimate-repeater-field'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="radio" name="display_logic" value="post_types" <?php echo $group && (!empty($selected_types) || !empty($group->pages)) && (!is_array($selected_types) || !in_array('all', $selected_types)) ? 'checked' : ''; ?>>
                                <?php _e('Show on specific post types or pages', 'ultimate-repeater-field'); ?>
                            </label>
                        </p>
                    </td>
                </tr>
                
                <tr class="display-options" style="display: none;">
                    <th scope="row"><label><?php _e('Post Types', 'ultimate-repeater-field'); ?></label></th>
                    <td>
                        <div style="margin-bottom: 10px;">
                            <label>
                                <input type="checkbox" name="all_post_types" value="1" class="all-post-types">
                                <?php _e('All Post Types', 'ultimate-repeater-field'); ?>
                            </label>
                        </div>
                        <div class="post-types-container" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 10px;">
                            <?php
                            $post_types = get_post_types(array('public' => true), 'objects');
                            $all_post_types_selected = $group && is_array($selected_types) && in_array('all', $selected_types);
                            
                            foreach ($post_types as $post_type):
                                if ($post_type->name == 'attachment') continue;
                                $checked = $all_post_types_selected ? '' : ((is_array($selected_types) && in_array($post_type->name, $selected_types)) ? 'checked' : '');
                            ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($post_type->name); ?>" class="post-type-checkbox" <?php echo $checked; ?> data-post-type="<?php echo esc_attr($post_type->name); ?>">
                                    <?php echo esc_html($post_type->label); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="description"><?php _e('Select which post types this field group should appear on', 'ultimate-repeater-field'); ?></p>
                    </td>
                </tr>
                
                <tr class="display-options pages-section" style="display: none;">
                    <th scope="row"><label><?php _e('Specific Pages', 'ultimate-repeater-field'); ?></label></th>
                    <td>
                        <div class="specific-pages-container">
                            <div style="margin-bottom: 10px;">
                                <button type="button" class="button button-small" id="select-pages-btn">
                                    <?php _e('Select Pages', 'ultimate-repeater-field'); ?>
                                </button>
                                <button type="button" class="button button-small" id="clear-pages-btn" style="margin-left: 5px;">
                                    <?php _e('Clear Selection', 'ultimate-repeater-field'); ?>
                                </button>
                            </div>
                            
                            <div id="selected-pages-container" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; margin-bottom: 10px;">
                                <?php
                                $selected_pages = $group ? maybe_unserialize($group->pages) : array();
                                
                                if (is_array($selected_pages) && !empty($selected_pages)) {
                                    foreach ($selected_pages as $page_id) {
                                        $page = get_post($page_id);
                                        if ($page) {
                                            echo '<div class="selected-page" data-page-id="' . esc_attr($page_id) . '">';
                                            echo '<span>' . esc_html($page->post_title) . '</span>';
                                            echo '<input type="hidden" name="pages[]" value="' . esc_attr($page_id) . '">';
                                            echo ' <a href="#" class="remove-page" style="color: #dc3232; text-decoration: none;">×</a>';
                                            echo '</div>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                            
                            <div id="pages-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999;">
                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; width: 80%; max-width: 600px; max-height: 80%; overflow: hidden; display: flex; flex-direction: column;">
                                    <div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                                        <h3 style="margin: 0;"><?php _e('Select Pages', 'ultimate-repeater-field'); ?></h3>
                                        <button type="button" id="close-modal" style="background: none; border: none; font-size: 20px; cursor: pointer;">×</button>
                                    </div>
                                    <div style="padding: 20px; overflow-y: auto; flex-grow: 1;">
                                        <input type="text" id="page-search" placeholder="<?php _e('Search pages...', 'ultimate-repeater-field'); ?>" style="width: 100%; padding: 8px; margin-bottom: 10px;">
                                        <div id="pages-list" style="max-height: 300px; overflow-y: auto;">
                                            <!-- Pages will be loaded via AJAX -->
                                        </div>
                                    </div>
                                    <div style="padding: 15px 20px; border-top: 1px solid #ddd; text-align: right;">
                                        <button type="button" id="add-selected-pages" class="button button-primary"><?php _e('Add Selected Pages', 'ultimate-repeater-field'); ?></button>
                                    </div>
                                </div>
                            </div>
                            
                            <p class="description"><?php _e('Select specific pages where this field group should appear', 'ultimate-repeater-field'); ?></p>
                        </div>
                    </td>
                </tr>
            </table>
            
            <h2><?php _e('Fields', 'ultimate-repeater-field'); ?></h2>
            
            <div id="urf-fields-container">
                <?php
                $fields_count = 0;
                if ($group) {
                    $fields = maybe_unserialize($group->fields);
                    if ($fields && is_array($fields)) {
                        $fields_count = count($fields);
                        foreach ($fields as $index => $field) {
                            $this->render_field_row($index, $field);
                        }
                    }
                }
                ?>
            </div>
            
            <button type="button" id="urf-add-field" class="button button-secondary">
                <span class="dashicons dashicons-plus"></span> <?php _e('Add Field', 'ultimate-repeater-field'); ?>
            </button>
            
            <hr>
            
            <p class="submit">
                <button type="submit" name="save_field_group" value="1" class="button button-primary button-large">
                    <?php _e('Save Field Group', 'ultimate-repeater-field'); ?>
                </button>
                <?php if ($group): ?>
                    <a href="<?php echo admin_url('admin.php?page=urf-field-groups'); ?>" class="button button-secondary" style="margin-left: 10px;">
                        <?php _e('Cancel', 'ultimate-repeater-field'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let fieldIndex = <?php echo $fields_count; ?>;
        
        // Display logic toggle
        const displayLogicRadios = document.querySelectorAll('input[name="display_logic"]');
        const displayOptions = document.querySelectorAll('.display-options');
        const pagesSection = document.querySelector('.pages-section');
        
        const allPostTypesCheckbox = document.querySelector('.all-post-types');
        const postTypesContainer = document.querySelector('.post-types-container');
        
        function toggleDisplayOptions() {
            const selectedValue = document.querySelector('input[name="display_logic"]:checked').value;
            if (selectedValue === 'post_types') {
                displayOptions.forEach(opt => opt.style.display = 'table-row');
                
                const allSelected = <?php echo ($group && is_array($selected_types) && in_array('all', $selected_types)) ? 'true' : 'false'; ?>;
                if (allSelected && allPostTypesCheckbox) {
                    allPostTypesCheckbox.checked = true;
                    postTypesContainer.style.display = 'none';
                    pagesSection.style.display = 'none';
                } else {
                    checkPagesPostType();
                }
            } else {
                displayOptions.forEach(opt => opt.style.display = 'none');
            }
        }
        
        displayLogicRadios.forEach(radio => {
            radio.addEventListener('change', toggleDisplayOptions);
        });
        
        if (allPostTypesCheckbox) {
            allPostTypesCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    postTypesContainer.style.display = 'none';
                    document.querySelectorAll('.post-type-checkbox').forEach(cb => {
                        cb.checked = false;
                    });
                    pagesSection.style.display = 'none';
                } else {
                    postTypesContainer.style.display = 'block';
                    checkPagesPostType();
                }
            });
            
            <?php 
            if ($group && is_array($selected_types) && in_array('all', $selected_types)) {
                echo 'allPostTypesCheckbox.checked = true;';
                echo 'postTypesContainer.style.display = "none";';
                echo 'pagesSection.style.display = "none";';
            }
            ?>
        }
        
        function checkPagesPostType() {
            const pageCheckbox = document.querySelector('.post-type-checkbox[data-post-type="page"]');
            
            if (pageCheckbox && pageCheckbox.checked) {
                pagesSection.style.display = 'table-row';
            } else {
                pagesSection.style.display = 'none';
                if (!pageCheckbox || !pageCheckbox.checked) {
                    document.querySelector('#selected-pages-container').innerHTML = '';
                }
            }
        }
        
        document.querySelectorAll('.post-type-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                checkPagesPostType();
                
                if (allPostTypesCheckbox && allPostTypesCheckbox.checked) {
                    allPostTypesCheckbox.checked = false;
                    postTypesContainer.style.display = 'block';
                }
            });
        });
        
        toggleDisplayOptions();
        
        // Page selection modal
        const selectPagesBtn = document.getElementById('select-pages-btn');
        const clearPagesBtn = document.getElementById('clear-pages-btn');
        const pagesModal = document.getElementById('pages-modal');
        const closeModalBtn = document.getElementById('close-modal');
        const pageSearch = document.getElementById('page-search');
        const pagesList = document.getElementById('pages-list');
        const addSelectedPagesBtn = document.getElementById('add-selected-pages');
        const selectedPagesContainer = document.getElementById('selected-pages-container');
        
        function loadPages(search = '') {
            pagesList.innerHTML = '<p><?php _e('Loading pages...', 'ultimate-repeater-field'); ?></p>';
            
            const data = new FormData();
            data.append('action', 'urf_get_pages');
            data.append('search', search);
            data.append('nonce', '<?php echo wp_create_nonce('urf_ajax_nonce'); ?>');
            
            fetch(ajaxurl, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    pagesList.innerHTML = '';
                    if (data.data.length > 0) {
                        data.data.forEach(page => {
                            const pageDiv = document.createElement('div');
                            pageDiv.className = 'page-item';
                            pageDiv.style.cssText = 'padding: 8px; border-bottom: 1px solid #eee;';
                            
                            const label = document.createElement('label');
                            label.style.cssText = 'display: flex; align-items: center; cursor: pointer;';
                            
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.value = page.ID;
                            checkbox.className = 'page-checkbox';
                            
                            const existingPages = selectedPagesContainer.querySelectorAll('input[name="pages[]"]');
                            Array.from(existingPages).forEach(input => {
                                if (input.value == page.ID) {
                                    checkbox.checked = true;
                                }
                            });
                            
                            const titleSpan = document.createElement('span');
                            titleSpan.textContent = page.post_title + ' (ID: ' + page.ID + ')';
                            titleSpan.style.marginLeft = '8px';
                            
                            label.appendChild(checkbox);
                            label.appendChild(titleSpan);
                            pageDiv.appendChild(label);
                            pagesList.appendChild(pageDiv);
                        });
                    } else {
                        pagesList.innerHTML = '<p><?php _e('No pages found.', 'ultimate-repeater-field'); ?></p>';
                    }
                } else {
                    pagesList.innerHTML = '<p><?php _e('Error loading pages.', 'ultimate-repeater-field'); ?></p>';
                }
            })
            .catch(error => {
                pagesList.innerHTML = '<p><?php _e('Error loading pages.', 'ultimate-repeater-field'); ?></p>';
            });
        }
        
        if (selectPagesBtn) {
            selectPagesBtn.addEventListener('click', function() {
                pagesModal.style.display = 'block';
                loadPages();
            });
        }
        
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', function() {
                pagesModal.style.display = 'none';
            });
        }
        
        window.addEventListener('click', function(event) {
            if (event.target === pagesModal) {
                pagesModal.style.display = 'none';
            }
        });
        
        if (pageSearch) {
            pageSearch.addEventListener('input', function() {
                loadPages(this.value);
            });
        }
        
        if (addSelectedPagesBtn) {
            addSelectedPagesBtn.addEventListener('click', function() {
                const selectedCheckboxes = pagesList.querySelectorAll('.page-checkbox:checked');
                selectedCheckboxes.forEach(checkbox => {
                    const pageId = checkbox.value;
                    
                    if (!selectedPagesContainer.querySelector(`[data-page-id="${pageId}"]`)) {
                        const pageTitle = checkbox.parentElement.querySelector('span').textContent.split(' (ID:')[0];
                        
                        const pageDiv = document.createElement('div');
                        pageDiv.className = 'selected-page';
                        pageDiv.dataset.pageId = pageId;
                        pageDiv.style.cssText = 'margin-bottom: 5px; padding: 5px; background: #fff; border: 1px solid #ddd;';
                        
                        pageDiv.innerHTML = `
                            <span>${pageTitle}</span>
                            <input type="hidden" name="pages[]" value="${pageId}">
                            <a href="#" class="remove-page" style="color: #dc3232; text-decoration: none; margin-left: 10px;">×</a>
                        `;
                        
                        selectedPagesContainer.appendChild(pageDiv);
                    }
                });
                
                pagesList.querySelectorAll('.page-checkbox').forEach(cb => {
                    cb.checked = false;
                });
                
                pagesModal.style.display = 'none';
            });
        }
        
        if (clearPagesBtn) {
            clearPagesBtn.addEventListener('click', function() {
                if (confirm('<?php _e('Are you sure you want to clear all selected pages?', 'ultimate-repeater-field'); ?>')) {
                    selectedPagesContainer.innerHTML = '';
                }
            });
        }
        
        if (selectedPagesContainer) {
            selectedPagesContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-page')) {
                    e.preventDefault();
                    e.target.closest('.selected-page').remove();
                }
            });
        }
        
        // Add field
        document.getElementById('urf-add-field').addEventListener('click', function() {
            const container = document.getElementById('urf-fields-container');
            const newFieldRow = createFieldRow(fieldIndex);
            container.appendChild(newFieldRow);
            fieldIndex++;
            
            updateFieldIndices();
        });
        
        // Remove field
        document.addEventListener('click', function(e) {
            if (e.target.closest('.urf-remove-field')) {
                e.preventDefault();
                if (confirm('<?php _e('Are you sure you want to remove this field?', 'ultimate-repeater-field'); ?>')) {
                    const row = e.target.closest('.urf-field-row');
                    row.remove();
                    updateFieldIndices();
                }
            }
        });
        
        // Remove subfield
        document.addEventListener('click', function(e) {
            if (e.target.closest('.urf-remove-subfield')) {
                e.preventDefault();
                if (confirm('<?php _e('Are you sure you want to remove this subfield?', 'ultimate-repeater-field'); ?>')) {
                    const row = e.target.closest('.urf-subfield-row');
                    row.remove();
                }
            }
        });
        
        // Remove nested subfield
        document.addEventListener('click', function(e) {
            if (e.target.closest('.urf-remove-nested-subfield')) {
                e.preventDefault();
                if (confirm('<?php _e('Are you sure you want to remove this nested subfield?', 'ultimate-repeater-field'); ?>')) {
                    const row = e.target.closest('.urf-nested-subfield-row');
                    row.remove();
                }
            }
        });
        
        // ===========================================
        // FIXED: LABEL TO NAME AUTO-GENERATION - ALWAYS UPDATE
        // ===========================================
        
        // SIMPLE AND RELIABLE FUNCTION FOR ALL FIELD LEVELS
        function handleLabelToNameConversion(target) {
            // Check if this is a label input field
            let isLabelField = false;
            let fieldLevel = null;
            
            if (target.classList.contains('urf-field-label')) {
                isLabelField = true;
                fieldLevel = 0;
            } 
            else if (target.name && target.name.includes('[label]')) {
                isLabelField = true;
                const nameStr = target.name;
                const subfieldsCount = (nameStr.match(/\[subfields\]/g) || []).length;
                
                if (subfieldsCount === 1) {
                    fieldLevel = 1;
                } else if (subfieldsCount >= 2) {
                    fieldLevel = 2;
                } else {
                    fieldLevel = 0;
                }
            }
            
            if (!isLabelField) {
                return;
            }
            
            // Get the label value
            const label = target.value;
            
            // Generate proper slug from the ENTIRE label
            let name = '';
            
            if (label && label.trim() !== '') {
                // SIMPLIFIED CONVERSION - NO COMPLEX REGEX
                // 1. Lowercase everything
                name = label.toLowerCase();
                
                // 2. Replace spaces and special characters with underscores
                // Keep letters, numbers, and spaces, replace everything else with space
                name = name.replace(/[^a-z0-9\s]/g, ' ');
                
                // 3. Replace multiple spaces with single space
                name = name.replace(/\s+/g, ' ');
                
                // 4. Trim whitespace
                name = name.trim();
                
                // 5. Replace spaces with underscores
                name = name.replace(/\s/g, '_');
                
                // 6. Replace multiple underscores with single underscore
                name = name.replace(/_+/g, '_');
                
                // 7. Trim underscores from start and end
                name = name.replace(/^_+|_+$/g, '');
                
                // 8. If we ended up with empty string, use a simple default
                if (name === '') {
                    name = 'field_' + Math.floor(Math.random() * 1000);
                }
            } else {
                return;
            }
            
            // Find the corresponding name field
            let nameField = null;
            
            switch (fieldLevel) {
                case 0: // Main fields
                    const row = target.closest('.urf-field-row');
                    nameField = row?.querySelector('.urf-field-name');
                    break;
                    
                case 1: // Subfields
                    const subRow = target.closest('.urf-subfield-row');
                    nameField = subRow?.querySelector('input[name*="[name]"]');
                    break;
                    
                case 2: // Nested2 subfields
                    const nestedRow = target.closest('.urf-nested-subfield-row');
                    nameField = nestedRow?.querySelector('input[name*="[name]"]');
                    break;
            }
            
            // ALWAYS UPDATE - not just when empty
            if (nameField) {
                console.log('Auto-filling name field with:', name, 'for label:', label);
                nameField.value = name;
                
                // Trigger change event for validation
                const event = new Event('change', { bubbles: true });
                nameField.dispatchEvent(event);
            }
        }
        
        // Event listeners for all field levels
        document.addEventListener('input', function(e) {
            // Debounce the function to avoid excessive calls
            clearTimeout(window.urfDebounceTimer);
            window.urfDebounceTimer = setTimeout(() => {
                handleLabelToNameConversion(e.target);
            }, 100);
        });
        
        document.addEventListener('keyup', function(e) {
            // Handle on keyup for better responsiveness
            if (e.key.length === 1 || e.key === 'Backspace' || e.key === 'Delete' || e.key === ' ') {
                clearTimeout(window.urfDebounceTimer);
                window.urfDebounceTimer = setTimeout(() => {
                    handleLabelToNameConversion(e.target);
                }, 50);
            }
        });
        
        // Also handle paste events
        document.addEventListener('paste', function(e) {
            setTimeout(() => {
                handleLabelToNameConversion(e.target);
            }, 10);
        });
        
        // Initialize existing fields on page load
        function initializeExistingFields() {
            console.log('Initializing existing fields...');
            
            // Process existing main fields
            document.querySelectorAll('.urf-field-label').forEach(function(input) {
                setTimeout(() => {
                    handleLabelToNameConversion(input);
                }, 50);
            });
            
            // Process existing subfields
            document.querySelectorAll('.urf-subfield-row input[name*="[label]"]').forEach(function(input) {
                setTimeout(() => {
                    handleLabelToNameConversion(input);
                }, 50);
            });
            
            // Process existing nested2 subfields
            document.querySelectorAll('.urf-nested-subfield-row input[name*="[label]"]').forEach(function(input) {
                setTimeout(() => {
                    handleLabelToNameConversion(input);
                }, 50);
            });
        }
        
        // Initialize when DOM is loaded
        setTimeout(initializeExistingFields, 500);
        
        // Show/hide options based on field type
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('urf-field-type')) {
                const row = e.target.closest('.urf-field-row');
                const type = e.target.value;
                const optionsDiv = row.querySelector('.urf-field-options');
                
                if (['select', 'checkbox', 'radio'].includes(type)) {
                    optionsDiv.style.display = 'block';
                    const currentIndex = Array.from(document.querySelectorAll('.urf-field-row')).indexOf(row);
                    optionsDiv.innerHTML = `
                        <label><?php _e('Options (one per line)', 'ultimate-repeater-field'); ?></label>
                        <textarea name="fields[${currentIndex}][options]" class="widefat" rows="3" placeholder="My Option 1"></textarea>
                        <p class="description"><?php _e('Enter options one per line. Values will be auto-generated from labels.', 'ultimate-repeater-field'); ?></p>
                    `;
                } else if (type === 'repeater') {
                    optionsDiv.style.display = 'block';
                    const currentIndex = Array.from(document.querySelectorAll('.urf-field-row')).indexOf(row);
                    optionsDiv.innerHTML = `
                        <label><?php _e('Sub Fields', 'ultimate-repeater-field'); ?></label>
                        <div class="urf-subfields-container" data-parent-index="${currentIndex}">
                            <!-- Subfields will be added here -->
                        </div>
                        <button type="button" class="button button-small urf-add-subfield" data-parent-index="${currentIndex}">
                            <span class="dashicons dashicons-plus"></span> <?php _e('Add Sub Field', 'ultimate-repeater-field'); ?>
                        </button>
                        <p class="description"><?php _e('Add fields that will appear inside this repeater', 'ultimate-repeater-field'); ?></p>
                    `;
                } else {
                    optionsDiv.style.display = 'none';
                }
            }
        });
        
        // Handle subfield type change
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('urf-subfield-type')) {
                const row = e.target.closest('.urf-subfield-row');
                const type = e.target.value;
                const optionsDiv = row.querySelector('.urf-subfield-options');
                
                if (['select', 'checkbox', 'radio', 'repeater'].includes(type)) {
                    optionsDiv.style.display = 'block';
                    
                    if (type === 'repeater') {
                        const fieldRow = row.closest('.urf-field-row');
                        const parentIndex = Array.from(document.querySelectorAll('.urf-field-row')).indexOf(fieldRow);
                        const subIndex = Array.from(fieldRow.querySelectorAll('.urf-subfield-row')).indexOf(row);
                        
                        optionsDiv.innerHTML = `
                            <label><?php _e('Sub Fields', 'ultimate-repeater-field'); ?></label>
                            <div class="urf-subfields-container" data-parent-index="${parentIndex}" data-sub-index="${subIndex}">
                                <!-- Nested subfields will be added here -->
                            </div>
                            <button type="button" class="button button-small urf-add-nested-subfield" data-parent-index="${parentIndex}" data-sub-index="${subIndex}">
                                <span class="dashicons dashicons-plus"></span> <?php _e('Add Sub Field', 'ultimate-repeater-field'); ?>
                            </button>
                            <p class="description"><?php _e('Add fields that will appear inside this repeater', 'ultimate-repeater-field'); ?></p>
                        `;
                    }
                } else {
                    optionsDiv.style.display = 'none';
                }
            }
        });
        
        // Add subfield
        document.addEventListener('click', function(e) {
            if (e.target.closest('.urf-add-subfield')) {
                e.preventDefault();
                const button = e.target.closest('.urf-add-subfield');
                const parentIndex = button.dataset.parentIndex;
                const container = button.parentElement.querySelector('.urf-subfields-container');
                
                const subfieldsCount = container.querySelectorAll('.urf-subfield-row').length;
                
                const newSubfield = createSubfieldRow(parentIndex, subfieldsCount);
                container.appendChild(newSubfield);
                
                // Initialize label-to-name for the new subfield
                setTimeout(() => {
                    const labelInput = newSubfield.querySelector('input[name*="[label]"]');
                    if (labelInput) {
                        // Add event listener for the new input
                        labelInput.addEventListener('input', function() {
                            handleLabelToNameConversion(this);
                        });
                    }
                }, 100);
            }
        });
        
        // Add nested subfield
        document.addEventListener('click', function(e) {
            if (e.target.closest('.urf-add-nested-subfield')) {
                e.preventDefault();
                const button = e.target.closest('.urf-add-nested-subfield');
                const parentIndex = button.dataset.parentIndex;
                const subIndex = button.dataset.subIndex;
                const container = button.parentElement.querySelector('.urf-subfields-container');
                
                const nestedSubfieldsCount = container.querySelectorAll('.urf-nested-subfield-row').length;
                const newNestedSubfield = createNestedSubfieldRow(parentIndex, subIndex, nestedSubfieldsCount);
                container.appendChild(newNestedSubfield);
                
                // Initialize label-to-name for the new nested subfield
                setTimeout(() => {
                    const labelInput = newNestedSubfield.querySelector('input[name*="[label]"]');
                    if (labelInput) {
                        // Add event listener for the new input
                        labelInput.addEventListener('input', function() {
                            handleLabelToNameConversion(this);
                        });
                    }
                }, 100);
            }
        });
        
        // Initialize field type changes
        document.querySelectorAll('.urf-field-type').forEach(function(select) {
            select.dispatchEvent(new Event('change'));
        });
        
        // Initialize subfield type changes
        document.querySelectorAll('.urf-subfield-type').forEach(function(select) {
            select.dispatchEvent(new Event('change'));
        });
        
        function createFieldRow(index) {
            const div = document.createElement('div');
            div.className = 'urf-field-row';
            div.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                    <h3 style="margin: 0;"><?php _e('Field', 'ultimate-repeater-field'); ?> #<span class="field-index">${index + 1}</span></h3>
                    <a href="#" class="urf-remove-field" style="color: #dc3232; text-decoration: none;">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Remove', 'ultimate-repeater-field'); ?>
                    </a>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label><?php _e('Field Type', 'ultimate-repeater-field'); ?> *</label>
                        <select name="fields[${index}][type]" class="urf-field-type widefat" required>
                            <option value="text"><?php _e('Text', 'ultimate-repeater-field'); ?></option>
                            <option value="textarea"><?php _e('Textarea', 'ultimate-repeater-field'); ?></option>
                            <option value="image"><?php _e('Image Upload', 'ultimate-repeater-field'); ?></option>
                            <option value="select"><?php _e('Select Dropdown', 'ultimate-repeater-field'); ?></option>
                            <option value="checkbox"><?php _e('Checkbox', 'ultimate-repeater-field'); ?></option>
                            <option value="radio"><?php _e('Radio Buttons', 'ultimate-repeater-field'); ?></option>
                            <option value="color"><?php _e('Color Picker', 'ultimate-repeater-field'); ?></option>
                            <option value="date"><?php _e('Date Picker', 'ultimate-repeater-field'); ?></option>
                            <option value="repeater"><?php _e('Repeater Field', 'ultimate-repeater-field'); ?></option>
                        </select>
                    </div>
                    
                    <div>
                        <label><?php _e('Field Label', 'ultimate-repeater-field'); ?> *</label>
                        <input type="text" name="fields[${index}][label]" class="urf-field-label widefat" required placeholder="<?php _e('My Field Label', 'ultimate-repeater-field'); ?>">
                    </div>
                    
                    <div>
                        <label><?php _e('Field Name', 'ultimate-repeater-field'); ?> *</label>
                        <input type="text" name="fields[${index}][name]" class="urf-field-name widefat" required placeholder="<?php _e('my_field_name', 'ultimate-repeater-field'); ?>">
                        <p class="description"><?php _e('Lowercase, underscores, no spaces', 'ultimate-repeater-field'); ?></p>
                    </div>
                </div>
                
                <div class="urf-field-options" style="display: none; margin-bottom: 15px;">
                    <!-- Options or subfields will be added here based on field type -->
                </div>
                
                <div>
                    <label>
                        <input type="checkbox" name="fields[${index}][required]" value="1">
                        <?php _e('Required Field', 'ultimate-repeater-field'); ?>
                    </label>
                </div>
            `;
            
            // Add event listener for the new field
            const labelInput = div.querySelector('.urf-field-label');
            const nameInput = div.querySelector('.urf-field-name');
            
            if (labelInput && nameInput) {
                labelInput.addEventListener('input', function() {
                    handleLabelToNameConversion(this);
                });
            }
            
            return div;
        }
        
        function createSubfieldRow(parentIndex, subIndex) {
            const div = document.createElement('div');
            div.className = 'urf-subfield-row';
            div.style.cssText = 'border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #f9f9f9;';
            div.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                    <strong><?php _e('Sub Field', 'ultimate-repeater-field'); ?></strong>
                    <a href="#" class="urf-remove-subfield" style="color: #dc3232; text-decoration: none;">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Remove', 'ultimate-repeater-field'); ?>
                    </a>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">
                    <div>
                        <label><?php _e('Field Type', 'ultimate-repeater-field'); ?> *</label>
                        <select name="fields[${parentIndex}][subfields][${subIndex}][type]" class="widefat urf-subfield-type" required>
                            <option value="text"><?php _e('Text', 'ultimate-repeater-field'); ?></option>
                            <option value="textarea"><?php _e('Textarea', 'ultimate-repeater-field'); ?></option>
                            <option value="image"><?php _e('Image Upload', 'ultimate-repeater-field'); ?></option>
                            <option value="select"><?php _e('Select Dropdown', 'ultimate-repeater-field'); ?></option>
                            <option value="checkbox"><?php _e('Checkbox', 'ultimate-repeater-field'); ?></option>
                            <option value="radio"><?php _e('Radio Buttons', 'ultimate-repeater-field'); ?></option>
                            <option value="repeater"><?php _e('Repeater Field', 'ultimate-repeater-field'); ?></option>
                        </select>
                    </div>
                    
                    <div>
                        <label><?php _e('Field Label', 'ultimate-repeater-field'); ?> *</label>
                        <input type="text" name="fields[${parentIndex}][subfields][${subIndex}][label]" class="widefat" required placeholder="<?php _e('My Sub Field Label', 'ultimate-repeater-field'); ?>">
                    </div>
                    
                    <div>
                        <label><?php _e('Field Name', 'ultimate-repeater-field'); ?> *</label>
                        <input type="text" name="fields[${parentIndex}][subfields][${subIndex}][name]" class="widefat" required placeholder="<?php _e('my_sub_field_name', 'ultimate-repeater-field'); ?>">
                        <p class="description"><?php _e('Lowercase, underscores, no spaces', 'ultimate-repeater-field'); ?></p>
                    </div>
                </div>
                
                <div class="urf-subfield-options" style="display: none; margin-bottom: 10px;">
                    <!-- Options will be added based on field type -->
                </div>
                
                <div>
                    <label>
                        <input type="checkbox" name="fields[${parentIndex}][subfields][${subIndex}][required]" value="1">
                        <?php _e('Required Field', 'ultimate-repeater-field'); ?>
                    </label>
                </div>
            `;
            
            // Add event listener for the new subfield
            const labelInput = div.querySelector('input[name*="[label]"]');
            const nameInput = div.querySelector('input[name*="[name]"]');
            
            if (labelInput && nameInput) {
                labelInput.addEventListener('input', function() {
                    handleLabelToNameConversion(this);
                });
            }
            
            return div;
        }
        
        function createNestedSubfieldRow(parentIndex, subIndex, nestedIndex) {
            const div = document.createElement('div');
            div.className = 'urf-nested-subfield-row';
            div.style.cssText = 'border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; background: #f0f0f0;';
            div.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                    <strong><?php _e('Nested Sub Field', 'ultimate-repeater-field'); ?></strong>
                    <a href="#" class="urf-remove-nested-subfield" style="color: #dc3232; text-decoration: none;">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Remove', 'ultimate-repeater-field'); ?>
                    </a>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">
                    <div>
                        <label><?php _e('Field Type', 'ultimate-repeater-field'); ?> *</label>
                        <select name="fields[${parentIndex}][subfields][${subIndex}][subfields][${nestedIndex}][type]" class="widefat" required>
                            <option value="text"><?php _e('Text', 'ultimate-repeater-field'); ?></option>
                            <option value="textarea"><?php _e('Textarea', 'ultimate-repeater-field'); ?></option>
                            <option value="image"><?php _e('Image Upload', 'ultimate-repeater-field'); ?></option>
                            <option value="select"><?php _e('Select Dropdown', 'ultimate-repeater-field'); ?></option>
                            <option value="checkbox"><?php _e('Checkbox', 'ultimate-repeater-field'); ?></option>
                            <option value="radio"><?php _e('Radio Buttons', 'ultimate-repeater-field'); ?></option>
                        </select>
                    </div>
                    
                    <div>
                        <label><?php _e('Field Label', 'ultimate-repeater-field'); ?> *</label>
                        <input type="text" name="fields[${parentIndex}][subfields][${subIndex}][subfields][${nestedIndex}][label]" class="widefat" required placeholder="<?php _e('My Nested Field Label', 'ultimate-repeater-field'); ?>">
                    </div>
                    
                    <div>
                        <label><?php _e('Field Name', 'ultimate-repeater-field'); ?> *</label>
                        <input type="text" name="fields[${parentIndex}][subfields][${subIndex}][subfields][${nestedIndex}][name]" class="widefat" required placeholder="<?php _e('my_nested_field_name', 'ultimate-repeater-field'); ?>">
                        <p class="description"><?php _e('Lowercase, underscores, no spaces', 'ultimate-repeater-field'); ?></p>
                    </div>
                </div>
                
                <div>
                    <label>
                        <input type="checkbox" name="fields[${parentIndex}][subfields][${subIndex}][subfields][${nestedIndex}][required]" value="1">
                        <?php _e('Required Field', 'ultimate-repeater-field'); ?>
                    </label>
                </div>
            `;
            
            // Add event listener for the new nested subfield
            const labelInput = div.querySelector('input[name*="[label]"]');
            const nameInput = div.querySelector('input[name*="[name]"]');
            
            if (labelInput && nameInput) {
                labelInput.addEventListener('input', function() {
                    handleLabelToNameConversion(this);
                });
            }
            
            return div;
        }
        
        function updateFieldIndices() {
            const rows = document.querySelectorAll('.urf-field-row');
            rows.forEach(function(row, index) {
                row.querySelector('.field-index').textContent = index + 1;
                
                const inputs = row.querySelectorAll('[name]');
                inputs.forEach(function(input) {
                    const oldName = input.name;
                    const match = oldName.match(/fields\[(\d+)\]/);
                    if (match) {
                        const newName = oldName.replace(/fields\[\d+\]/g, `fields[${index}]`);
                        input.name = newName;
                    }
                });
                
                const subfieldsContainer = row.querySelector('.urf-subfields-container');
                if (subfieldsContainer) {
                    subfieldsContainer.dataset.parentIndex = index;
                }
                
                const addSubfieldBtn = row.querySelector('.urf-add-subfield');
                if (addSubfieldBtn) {
                    addSubfieldBtn.dataset.parentIndex = index;
                }
            });
            fieldIndex = rows.length;
        }
    });
    </script>
    <?php
}
    
    public function render_field_row($index, $field) {
        ?>
        <div class="urf-field-row" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                <h3 style="margin: 0;"><?php _e('Field', 'ultimate-repeater-field'); ?> #<span class="field-index"><?php echo $index + 1; ?></span></h3>
                <a href="#" class="urf-remove-field" style="color: #dc3232; text-decoration: none;">
                    <span class="dashicons dashicons-trash"></span> <?php _e('Remove', 'ultimate-repeater-field'); ?>
                </a>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                <div>
                    <label><?php _e('Field Type', 'ultimate-repeater-field'); ?> *</label>
                    <select name="fields[<?php echo $index; ?>][type]" class="urf-field-type widefat" required>
                        <option value="text" <?php selected($field['type'] ?? '', 'text'); ?>><?php _e('Text', 'ultimate-repeater-field'); ?></option>
                        <option value="textarea" <?php selected($field['type'] ?? '', 'textarea'); ?>><?php _e('Textarea', 'ultimate-repeater-field'); ?></option>
                        <option value="image" <?php selected($field['type'] ?? '', 'image'); ?>><?php _e('Image Upload', 'ultimate-repeater-field'); ?></option>
                        <option value="select" <?php selected($field['type'] ?? '', 'select'); ?>><?php _e('Select Dropdown', 'ultimate-repeater-field'); ?></option>
                        <option value="checkbox" <?php selected($field['type'] ?? '', 'checkbox'); ?>><?php _e('Checkbox', 'ultimate-repeater-field'); ?></option>
                        <option value="radio" <?php selected($field['type'] ?? '', 'radio'); ?>><?php _e('Radio Buttons', 'ultimate-repeater-field'); ?></option>
                        <option value="color" <?php selected($field['type'] ?? '', 'color'); ?>><?php _e('Color Picker', 'ultimate-repeater-field'); ?></option>
                        <option value="date" <?php selected($field['type'] ?? '', 'date'); ?>><?php _e('Date Picker', 'ultimate-repeater-field'); ?></option>
                        <option value="repeater" <?php selected($field['type'] ?? '', 'repeater'); ?>><?php _e('Repeater Field', 'ultimate-repeater-field'); ?></option>
                    </select>
                </div>
                
                <div>
                    <label><?php _e('Field Label', 'ultimate-repeater-field'); ?> *</label>
                    <input type="text" name="fields[<?php echo $index; ?>][label]" 
                           value="<?php echo esc_attr($field['label'] ?? ''); ?>" 
                           class="urf-field-label widefat" required>
                </div>
                
                <div>
                    <label><?php _e('Field Name', 'ultimate-repeater-field'); ?> *</label>
                    <input type="text" name="fields[<?php echo $index; ?>][name]" 
                           value="<?php echo esc_attr($field['name'] ?? ''); ?>" 
                           class="urf-field-name widefat" required>
                    <p class="description"><?php _e('Lowercase, underscores, no spaces', 'ultimate-repeater-field'); ?></p>
                </div>
            </div>
            
            <div class="urf-field-options" style="display: <?php echo in_array($field['type'] ?? '', ['select', 'checkbox', 'radio', 'repeater']) ? 'block' : 'none'; ?>; margin-bottom: 15px;">
                <?php if (in_array($field['type'] ?? '', ['select', 'checkbox', 'radio'])): ?>
					<label><?php _e('Options (one per line)', 'ultimate-repeater-field'); ?></label>
					<?php
					$options_text = '';
					if (!empty($field['options'])) {
						$lines = explode("\n", $field['options']);
						$labels = [];
						foreach ($lines as $line) {
							$line = trim($line);
							if (!empty($line)) {
								if (strpos($line, '|') !== false) {
									list($value, $label) = explode('|', $line, 2);
									$labels[] = trim($label);
								} else {
									$labels[] = $line;
								}
							}
						}
						$options_text = implode("\n", $labels);
					}
					?>
					<textarea name="fields[<?php echo $index; ?>][options]" class="widefat" rows="3" placeholder="My Option 1"><?php echo esc_textarea($options_text); ?></textarea>
					<p class="description"><?php _e('Enter options one per line.', 'ultimate-repeater-field'); ?></p>

                <?php elseif (($field['type'] ?? '') === 'repeater'): ?>
                    <label><?php _e('Sub Fields', 'ultimate-repeater-field'); ?></label>
                    <div class="urf-subfields-container" data-parent-index="<?php echo $index; ?>">
                        <?php
                        if (isset($field['subfields']) && is_array($field['subfields'])) {
                            foreach ($field['subfields'] as $sub_index => $subfield) {
                                $this->render_subfield_row($index, $sub_index, $subfield);
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="button button-small urf-add-subfield" data-parent-index="<?php echo $index; ?>">
                        <span class="dashicons dashicons-plus"></span> <?php _e('Add Sub Field', 'ultimate-repeater-field'); ?>
                    </button>
                    <p class="description"><?php _e('Add fields that will appear inside this repeater', 'ultimate-repeater-field'); ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label>
                    <input type="checkbox" name="fields[<?php echo $index; ?>][required]" value="1" <?php checked($field['required'] ?? false, true); ?>>
                    <?php _e('Required Field', 'ultimate-repeater-field'); ?>
                </label>
            </div>
        </div>
        <?php
    }
    
    public function render_subfield_row($parent_index, $sub_index, $subfield = array()) {
        ?>
        <div class="urf-subfield-row" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #f9f9f9;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                <strong><?php _e('Sub Field', 'ultimate-repeater-field'); ?></strong>
                <a href="#" class="urf-remove-subfield" style="color: #dc3232; text-decoration: none;">
                    <span class="dashicons dashicons-trash"></span> <?php _e('Remove', 'ultimate-repeater-field'); ?>
                </a>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">
                <div>
                    <label><?php _e('Field Type', 'ultimate-repeater-field'); ?> *</label>
                    <select name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][type]" class="widefat urf-subfield-type" required>
                        <option value="text" <?php selected($subfield['type'] ?? '', 'text'); ?>><?php _e('Text', 'ultimate-repeater-field'); ?></option>
                        <option value="textarea" <?php selected($subfield['type'] ?? '', 'textarea'); ?>><?php _e('Textarea', 'ultimate-repeater-field'); ?></option>
                        <option value="image" <?php selected($subfield['type'] ?? '', 'image'); ?>><?php _e('Image Upload', 'ultimate-repeater-field'); ?></option>
                        <option value="select" <?php selected($subfield['type'] ?? '', 'select'); ?>><?php _e('Select Dropdown', 'ultimate-repeater-field'); ?></option>
                        <option value="checkbox" <?php selected($subfield['type'] ?? '', 'checkbox'); ?>><?php _e('Checkbox', 'ultimate-repeater-field'); ?></option>
                        <option value="radio" <?php selected($subfield['type'] ?? '', 'radio'); ?>><?php _e('Radio Buttons', 'ultimate-repeater-field'); ?></option>
                        <option value="color" <?php selected($subfield['type'] ?? '', 'color'); ?>><?php _e('Color Picker', 'ultimate-repeater-field'); ?></option>
                        <option value="date" <?php selected($subfield['type'] ?? '', 'date'); ?>><?php _e('Date Picker', 'ultimate-repeater-field'); ?></option>
                        <!-- ADDED: Repeater option for subfields -->
                        <option value="repeater" <?php selected($subfield['type'] ?? '', 'repeater'); ?>><?php _e('Repeater Field', 'ultimate-repeater-field'); ?></option>
                    </select>
                </div>
                
                <div>
                    <label><?php _e('Field Label', 'ultimate-repeater-field'); ?> *</label>
                    <input type="text" name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][label]" 
                           value="<?php echo esc_attr($subfield['label'] ?? ''); ?>" 
                           class="widefat" required>
                </div>
                
                <div>
                    <label><?php _e('Field Name', 'ultimate-repeater-field'); ?> *</label>
                    <input type="text" name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][name]" 
                           value="<?php echo esc_attr($subfield['name'] ?? ''); ?>" 
                           class="widefat" required>
                    <p class="description"><?php _e('Lowercase, underscores, no spaces', 'ultimate-repeater-field'); ?></p>
                </div>
            </div>
            
            <div class="urf-subfield-options" style="display: <?php echo in_array($subfield['type'] ?? '', ['select', 'checkbox', 'radio', 'repeater']) ? 'block' : 'none'; ?>; margin-bottom: 10px;">
                <?php if (in_array($subfield['type'] ?? '', ['select', 'checkbox', 'radio'])): ?>
					<label><?php _e('Options', 'ultimate-repeater-field'); ?></label>
					<?php
					$options_text = '';
					if (!empty($subfield['options'])) {
						$lines = explode("\n", $subfield['options']);
						$labels = [];
						foreach ($lines as $line) {
							$line = trim($line);
							if (!empty($line)) {
								if (strpos($line, '|') !== false) {
									list($value, $label) = explode('|', $line, 2);
									$labels[] = trim($label);
								} else {
									$labels[] = $line;
								}
							}
						}
						$options_text = implode("\n", $labels);
					}
					?>
					<textarea name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][options]" class="widefat" rows="2"><?php echo esc_textarea($options_text); ?></textarea>
					<p class="description"><?php _e('Enter options one per line.', 'ultimate-repeater-field'); ?></p>

                <?php elseif (($subfield['type'] ?? '') === 'repeater'): ?>
                    <label><?php _e('Sub Fields', 'ultimate-repeater-field'); ?></label>
                    <div class="urf-subfields-container" data-parent-index="<?php echo $parent_index; ?>" data-sub-index="<?php echo $sub_index; ?>">
                        <?php
                        if (isset($subfield['subfields']) && is_array($subfield['subfields'])) {
                            foreach ($subfield['subfields'] as $nested_sub_index => $nested_subfield) {
                                $this->render_nested_subfield_row($parent_index, $sub_index, $nested_sub_index, $nested_subfield);
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="button button-small urf-add-nested-subfield" data-parent-index="<?php echo $parent_index; ?>" data-sub-index="<?php echo $sub_index; ?>">
                        <span class="dashicons dashicons-plus"></span> <?php _e('Add Sub Field', 'ultimate-repeater-field'); ?>
                    </button>
                    <p class="description"><?php _e('Add fields that will appear inside this repeater', 'ultimate-repeater-field'); ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label>
                    <input type="checkbox" name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][required]" value="1" <?php checked($subfield['required'] ?? false, true); ?>>
                    <?php _e('Required Field', 'ultimate-repeater-field'); ?>
                </label>
            </div>
        </div>
        <?php
    }
    
    // NEW FUNCTION: Render nested subfield row
    public function render_nested_subfield_row($parent_index, $sub_index, $nested_index, $subfield = array()) {
        ?>
        <div class="urf-nested-subfield-row" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; background: #f0f0f0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                <strong><?php _e('Nested Sub Field', 'ultimate-repeater-field'); ?></strong>
                <a href="#" class="urf-remove-nested-subfield" style="color: #dc3232; text-decoration: none;">
                    <span class="dashicons dashicons-trash"></span> <?php _e('Remove', 'ultimate-repeater-field'); ?>
                </a>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">
                <div>
                    <label><?php _e('Field Type', 'ultimate-repeater-field'); ?> *</label>
                    <select name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][subfields][<?php echo $nested_index; ?>][type]" class="widefat" required>
                        <option value="text" <?php selected($subfield['type'] ?? '', 'text'); ?>><?php _e('Text', 'ultimate-repeater-field'); ?></option>
                        <option value="textarea" <?php selected($subfield['type'] ?? '', 'textarea'); ?>><?php _e('Textarea', 'ultimate-repeater-field'); ?></option>
                        <option value="image" <?php selected($subfield['type'] ?? '', 'image'); ?>><?php _e('Image Upload', 'ultimate-repeater-field'); ?></option>
                        <option value="select" <?php selected($subfield['type'] ?? '', 'select'); ?>><?php _e('Select Dropdown', 'ultimate-repeater-field'); ?></option>
                        <option value="checkbox" <?php selected($subfield['type'] ?? '', 'checkbox'); ?>><?php _e('Checkbox', 'ultimate-repeater-field'); ?></option>
                        <option value="radio" <?php selected($subfield['type'] ?? '', 'radio'); ?>><?php _e('Radio Buttons', 'ultimate-repeater-field'); ?></option>
                    </select>
                </div>
                
                <div>
                    <label><?php _e('Field Label', 'ultimate-repeater-field'); ?> *</label>
                    <input type="text" name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][subfields][<?php echo $nested_index; ?>][label]" 
                           value="<?php echo esc_attr($subfield['label'] ?? ''); ?>" 
                           class="widefat" required>
                </div>
                
                <div>
                    <label><?php _e('Field Name', 'ultimate-repeater-field'); ?> *</label>
                    <input type="text" name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][subfields][<?php echo $nested_index; ?>][name]" 
                           value="<?php echo esc_attr($subfield['name'] ?? ''); ?>" 
                           class="widefat" required>
                    <p class="description"><?php _e('Lowercase, underscores, no spaces', 'ultimate-repeater-field'); ?></p>
                </div>
            </div>
            
            <div>
                <label>
                    <input type="checkbox" name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][subfields][<?php echo $nested_index; ?>][required]" value="1" <?php checked($subfield['required'] ?? false, true); ?>>
                    <?php _e('Required Field', 'ultimate-repeater-field'); ?>
                </label>
            </div>
        </div>
        <?php
    }
    
    public function save_field_group() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'urf_field_groups';
        
        $this->check_and_update_database();
        
        $group_id = intval($_POST['group_id'] ?? 0);
        $name = sanitize_text_field($_POST['group_name'] ?? '');
        $slug = sanitize_title($_POST['group_slug'] ?? '');
        
        $display_logic = sanitize_text_field($_POST['display_logic'] ?? 'all');
        
        $post_types = array();
        $pages = array();
        
        if ($display_logic === 'post_types') {
            if (isset($_POST['all_post_types']) && $_POST['all_post_types'] == '1') {
                $post_types = array('all');
            } else {
                $post_types = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : array();
            }
            
            $pages = isset($_POST['pages']) ? array_map('intval', $_POST['pages']) : array();
            $settings = array();
        } else {
            $post_types = array('all');
            $pages = array();
            $settings = array();
        }
        
        if (empty($name)) {
            return __('Group name is required!', 'ultimate-repeater-field');
        }
        
        if (empty($slug)) {
            return __('Group slug is required!', 'ultimate-repeater-field');
        }
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            $this->activate();
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            if (!$table_exists) {
                return __('Database table does not exist. Please deactivate and reactivate the plugin.', 'ultimate-repeater-field');
            }
        }
        
        if (!$group_id) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE slug = %s",
                $slug
            ));
            
            if ($existing) {
                return __('A field group with this slug already exists!', 'ultimate-repeater-field');
            }
        } else {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE slug = %s AND id != %d",
                $slug,
                $group_id
            ));
            
            if ($existing) {
                return __('A field group with this slug already exists!', 'ultimate-repeater-field');
            }
        }
        
        $fields = array();
        if (isset($_POST['fields']) && is_array($_POST['fields'])) {
            foreach ($_POST['fields'] as $field) {
                if (!empty($field['label']) && !empty($field['name'])) {
                    $field_data = array(
                        'type' => sanitize_text_field($field['type']),
                        'label' => sanitize_text_field($field['label']),
                        'name' => sanitize_text_field($field['name']),
                        'options' => isset($field['options']) ? sanitize_textarea_field($field['options']) : '',
                        'required' => isset($field['required']) ? true : false
                    );
			
					

					if (in_array($field['type'], ['select', 'checkbox', 'radio']) && !empty($field_data['options'])) {
						$lines = explode("\n", $field_data['options']);
						$processed_options = [];
						foreach ($lines as $line) {
							$line = trim($line);
							if (!empty($line)) {
								if (strpos($line, '|') !== false) {
									// Extract label from existing value|label format
									list($existing_value, $label) = explode('|', $line, 2);
									$label = trim($label);
								} else {
									// Use the whole line as label
									$label = $line;
								}
								// Always regenerate value from label using sanitize_title (slug-like)
								$value = sanitize_title($label);
								$processed_options[] = $value . '|' . $label;
							}
						}
						$field_data['options'] = implode("\n", $processed_options);
					}

					// For subfields (nested repeaters level 1)
					if (in_array($subfield['type'], ['select', 'checkbox', 'radio']) && !empty($subfield_data['options'])) {
						$lines = explode("\n", $subfield_data['options']);
						$processed_options = [];
						foreach ($lines as $line) {
							$line = trim($line);
							if (!empty($line)) {
								if (strpos($line, '|') !== false) {
									// Extract label from existing value|label format
									list($existing_value, $label) = explode('|', $line, 2);
									$label = trim($label);
								} else {
									// Use the whole line as label
									$label = $line;
								}
								// Always regenerate value from label using sanitize_title (slug-like)
								$value = sanitize_title($label);
								$processed_options[] = $value . '|' . $label;
							}
						}
						$subfield_data['options'] = implode("\n", $processed_options);
					}

					// For nested subfields (nested repeaters level 2)
					if (in_array($nested_subfield['type'], ['select', 'checkbox', 'radio']) && !empty($nested_subfield['options'])) {
						$lines = explode("\n", $nested_subfield['options']);
						$processed_options = [];
						foreach ($lines as $line) {
							$line = trim($line);
							if (!empty($line)) {
								if (strpos($line, '|') !== false) {
									// Extract label from existing value|label format
									list($existing_value, $label) = explode('|', $line, 2);
									$label = trim($label);
								} else {
									// Use the whole line as label
									$label = $line;
								}
								// Always regenerate value from label using sanitize_title (slug-like)
								$value = sanitize_title($label);
								$processed_options[] = $value . '|' . $label;
							}
						}
						$nested_subfield['options'] = implode("\n", $processed_options);
					}
					
                    // Handle repeater subfields
                    if ($field['type'] === 'repeater' && isset($field['subfields']) && is_array($field['subfields'])) {
                        $subfields = array();
                        foreach ($field['subfields'] as $subfield) {
                            if (!empty($subfield['label']) && !empty($subfield['name'])) {
                                $subfield_data = array(
                                    'type' => sanitize_text_field($subfield['type']),
                                    'label' => sanitize_text_field($subfield['label']),
                                    'name' => sanitize_text_field($subfield['name']),
                                    'options' => isset($subfield['options']) ? sanitize_textarea_field($subfield['options']) : '',
                                    'required' => isset($subfield['required']) ? true : false
                                );
                                
                                // Handle nested subfields for nested repeaters
                                if ($subfield['type'] === 'repeater' && isset($subfield['subfields']) && is_array($subfield['subfields'])) {
                                    $nested_subfields = array();
                                    foreach ($subfield['subfields'] as $nested_subfield) {
                                        if (!empty($nested_subfield['label']) && !empty($nested_subfield['name'])) {
                                            $nested_subfields[] = array(
                                                'type' => sanitize_text_field($nested_subfield['type']),
                                                'label' => sanitize_text_field($nested_subfield['label']),
                                                'name' => sanitize_text_field($nested_subfield['name']),
                                                'options' => isset($nested_subfield['options']) ? sanitize_textarea_field($nested_subfield['options']) : '',
                                                'required' => isset($nested_subfield['required']) ? true : false
                                            );
                                        }
                                    }
                                    $subfield_data['subfields'] = $nested_subfields;
                                }
                                
                                $subfields[] = $subfield_data;
                            }
                        }
                        $field_data['subfields'] = $subfields;
                    }
                    
                    $fields[] = $field_data;
                }
            }
        }
        
        if (empty($fields)) {
            return __('Please add at least one field!', 'ultimate-repeater-field');
        }
        
        $field_names = array();
        foreach ($fields as $field) {
            if (in_array($field['name'], $field_names)) {
                return __('Duplicate field name found: ' . $field['name'], 'ultimate-repeater-field');
            }
            $field_names[] = $field['name'];
        }
        
        $data = array(
            'name' => $name,
            'slug' => $slug,
            'post_types' => maybe_serialize($post_types),
            'pages' => maybe_serialize($pages),
            'fields' => maybe_serialize($fields),
            'settings' => maybe_serialize($settings),
            'created_at' => current_time('mysql')
        );
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        $existing_columns = array();
        foreach ($columns as $column) {
            $existing_columns[] = $column->Field;
        }
        
        $format = array();
        foreach ($existing_columns as $col) {
            if (isset($data[$col])) {
                $format[] = '%s';
            }
        }
        
        if ($group_id) {
            unset($data['created_at']);
        }
        
        if ($group_id) {
            $result = $wpdb->update($table_name, $data, array('id' => $group_id), $format, array('%d'));
            if ($result === false) {
                return __('Error updating field group!', 'ultimate-repeater-field');
            }
        } else {
            $result = $wpdb->insert($table_name, $data, $format);
            if ($result === false) {
                return __('Error creating field group!', 'ultimate-repeater-field');
            }
            $group_id = $wpdb->insert_id;
        }
        
        return true;
    }
    
    public function get_field_group($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'urf_field_groups';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }
    
    public function admin_enqueue_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php')) && strpos($hook, 'ultimate-repeater') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_editor();
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        add_action('admin_print_footer_scripts', array($this, 'add_inline_scripts'), 99);
    }
    
    public function add_inline_scripts() {
    $screen = get_current_screen();
    if (!in_array($screen->base, array('post', 'page'))) {
        return;
    }
    ?>
    <style>
        /* Calendar fix styles */
        .ui-datepicker {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            width: 300px !important;
            z-index: 100001 !important;
        }

        .ui-datepicker-header {
            background: #f8f9fa;
            border: none;
            border-radius: 3px;
            padding: 8px 0;
            margin-bottom: 8px;
        }

        .ui-datepicker-title {
            font-size: 14px;
            font-weight: 600;
            color: #23282d;
        }

        .ui-datepicker-prev,
        .ui-datepicker-next {
            cursor: pointer;
            top: 8px !important;
            width: 30px;
            height: 30px;
            border: 1px solid #ddd;
            border-radius: 3px;
            background: white;
        }

        .ui-datepicker-prev:hover,
        .ui-datepicker-next:hover {
            background: #f1f1f1;
        }

        .ui-datepicker-prev span,
        .ui-datepicker-next span {
            display: none;
        }

        .ui-datepicker-prev:before {
            content: "←";
            position: absolute;
            left: 10px;
            top: 5px;
            color: #555;
        }

        .ui-datepicker-next:before {
            content: "→";
            position: absolute;
            right: 10px;
            top: 5px;
            color: #555;
        }

        .ui-datepicker-calendar {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .ui-datepicker-calendar th {
            padding: 8px 0;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        .ui-datepicker-calendar td {
            padding: 5px;
            text-align: center;
        }

        .ui-datepicker-calendar td a {
            display: block;
            padding: 8px;
            text-decoration: none;
            color: #23282d;
            border-radius: 3px;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .ui-datepicker-calendar td a:hover {
            background: #f0f0f0;
            border-color: #ddd;
        }

        .ui-datepicker-calendar td .ui-state-active {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
        }

        .ui-datepicker-calendar td .ui-state-highlight {
            background: #f0f8ff;
            color: #0073aa;
            border-color: #b3d9ff;
        }

        .ui-datepicker-calendar td .ui-state-default {
            border: 1px solid #e0e0e0;
            background: white;
        }

        .ui-datepicker-calendar td.ui-datepicker-other-month a {
            color: #a0a0a0;
            background: #f9f9f9;
        }

        .ui-datepicker-buttonpane {
            border-top: 1px solid #eee;
            padding: 10px 0 0;
            margin-top: 10px;
        }

        .ui-datepicker-current,
        .ui-datepicker-close {
            padding: 6px 12px;
            font-size: 12px;
            line-height: 1.5;
            border-radius: 3px;
            border: 1px solid #ddd;
            background: #f7f7f7;
            color: #23282d;
            cursor: pointer;
        }

        .ui-datepicker-current:hover,
        .ui-datepicker-close:hover {
            background: #f0f0f0;
            border-color: #ccc;
        }

        .ui-datepicker-today a {
            background: #f7f7f7 !important;
            border-color: #ddd !important;
            font-weight: bold;
        }

        /* Make sure calendar appears above everything */
        .ui-datepicker.ui-widget.ui-widget-content {
            z-index: 100001 !important;
            position: absolute !important;
        }

        .ui-datepicker.ui-widget {
            font-family: inherit !important;
        }

        /* Fix for nested datepickers */
        .urf-nested-table .ui-datepicker {
            width: 280px !important;
            font-size: 12px !important;
        }

        .urf-nested-table .ui-datepicker-calendar td a {
            padding: 6px !important;
            font-size: 11px !important;
        }

        .urf-nested-table .ui-datepicker-calendar th {
            padding: 6px 0 !important;
            font-size: 11px !important;
        }

        .ui-datepicker select.ui-datepicker-month,
        .ui-datepicker select.ui-datepicker-year {
            height: 28px;
            padding: 3px;
            font-size: 12px;
            border: 1px solid #ddd;
            border-radius: 3px;
            background: white;
            color: #23282d;
        }

        /* Ensure proper positioning */
        .ui-datepicker.ui-widget-content {
            z-index: 999999 !important;
            position: absolute;
        }

        /* Fix for datepicker in tables */
        .urf-nested-table .hasDatepicker {
            width: 100% !important;
            box-sizing: border-box !important;
        }

        /* Additional URF styles remain the same */
        .urf-field-group {
            margin: 25px 0;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e0e6ed;
            padding: 25px;
        }

        .urf-field-group h2 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 20px;
            margin: -25px -25px 25px -25px;
            text-align: left;
            font-weight: 600;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            position: relative;
        }

        .urf-vertical-fields {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #e2e8f0;
            margin: 0;
            display: block;
            position: relative;
            overflow: hidden;
        }

        .urf-vertical-fields::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4CAF50);
            background-size: 300% 100%;
            animation: gradientAnimation 3s ease infinite;
        }

        @keyframes gradientAnimation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .urf-vertical-field {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .urf-vertical-field:last-child {
            margin-bottom: 0;
        }

        .urf-vertical-field:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }

        .urf-vertical-field::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(to bottom, #667eea, #764ba2);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .urf-vertical-field:hover::after {
            opacity: 1;
        }

        .urf-vertical-field label {
            display: block;
            font-weight: 700;
            font-size: 15px;
            color: #1e293b;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .urf-vertical-field label::before {
            content: '📋';
            font-size: 16px;
            opacity: 0.7;
        }

        .urf-vertical-field input[type="text"],
        .urf-vertical-field textarea,
        .urf-vertical-field select {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: white;
            color: #334155;
        }

        .urf-vertical-field input[type="text"]:focus,
        .urf-vertical-field textarea:focus,
        .urf-vertical-field select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: #f8fafc;
        }

        .urf-vertical-field textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
        }

        .urf-file-upload-container {
            margin-top: 10px;
        }

        .urf-upload-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            border: none !important;
            padding: 12px 24px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3) !important;
        }

        .urf-upload-button:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4) !important;
        }

        .urf-file-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .urf-file-item {
            position: relative;
            border: 2px solid #e2e8f0;
            padding: 10px;
            background: white;
            border-radius: 10px;
            max-width: 180px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .urf-file-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }

        .urf-image-preview {
            max-width: 100%;
            height: auto;
            display: block;
            border-radius: 6px;
        }

        .urf-remove-file {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            text-align: center;
            line-height: 24px;
            cursor: pointer;
            font-size: 12px;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* NESTED REPEATER STYLES */
        .urf-nested-repeater {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 20px;
            border-radius: 12px;
            margin-top: 15px;
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }

        /* LEVEL 1 TABLE STYLES */
        .urf-nested-table {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.05);
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .urf-nested-table th {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%) !important;
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border: none;
            border-bottom: 2px solid #3d8b40;
        }

        .urf-nested-table td {
            padding: 12px;
            background: white;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            position: relative;
        }

        .urf-nested-table tr:last-child td {
            border-bottom: none;
        }

        .urf-nested-table tr:hover td {
            background-color: #f8fafc;
        }

        .urf-row-handle {
            width: 60px;
            text-align: center;
            cursor: move;
            background: linear-gradient(135deg, #f6f8ff 0%, #f0f4ff 100%);
            vertical-align: middle;
            border-right: 1px solid #f0f4f8;
        }

        .urf-row-actions {
            width: 80px;
            text-align: center;
            background: linear-gradient(135deg, #f6f8ff 0%, #f0f4ff 100%);
            vertical-align: middle;
            border-left: 1px solid #f0f4f8;
        }

        /* LEVEL 2 NESTED REPEATER STYLES */
        .urf-nested-repeater .urf-nested-repeater {
            background: linear-gradient(135deg, #f1f8ff 0%, #e8f4ff 100%);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #dbeafe;
            margin: 10px 0;
        }

        .urf-nested-repeater .urf-nested-table {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .urf-nested-repeater .urf-nested-table th {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            padding: 12px 10px;
            font-size: 13px;
            border-bottom: 2px solid #1d4ed8;
        }

        .urf-nested-repeater .urf-nested-table td {
            padding: 10px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        /* FIX FOR INPUTS IN TABLES */
        .urf-nested-table input[type="text"],
        .urf-nested-table textarea,
        .urf-nested-table select,
        .urf-nested-table .urf-file-upload-container,
        .urf-nested-table .urf-upload-button,
        .urf-nested-table .urf-colorpicker,
        .urf-nested-table .urf-datepicker {
            width: 100% !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            display: block !important;
        }

        .urf-nested-table input[type="text"],
        .urf-nested-table textarea,
        .urf-nested-table select {
            padding: 10px 12px !important;
            border: 2px solid #e2e8f0 !important;
            border-radius: 6px !important;
            font-size: 14px !important;
            background: white !important;
            min-height: 44px !important;
        }

        .urf-nested-table textarea {
            min-height: 80px !important;
            resize: vertical !important;
        }

        .urf-nested-table .urf-file-upload-container {
            margin-top: 5px !important;
        }

        .urf-nested-table .urf-upload-button {
            padding: 8px 16px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
        }

        .urf-nested-table .urf-colorpicker {
            height: 44px !important;
        }

        .urf-nested-table .urf-datepicker {
            cursor: pointer !important;
        }

        /* FILE PREVIEWS IN TABLES */
        .urf-nested-table .urf-file-preview {
            margin: 10px 0 5px 0 !important;
        }

        .urf-nested-table .urf-file-item {
            max-width: 150px !important;
            margin: 5px 0 !important;
            padding: 8px !important;
        }

        .urf-nested-table .urf-image-preview {
            max-height: 100px !important;
        }

        /* ADD ROW BUTTONS */
        .urf-add-nested-row,
        .urf-add-nested2-row {
            margin: 15px 0 10px !important;
            padding: 10px 20px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            cursor: pointer !important;
        }

        .urf-add-nested-row {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%) !important;
            color: white !important;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3) !important;
        }

        .urf-add-nested2-row {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            color: white !important;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3) !important;
        }

        .urf-add-nested-row:hover,
        .urf-add-nested2-row:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15) !important;
        }

        /* SORTABLE AND DRAG HANDLES */
        .urf-row-handle {
            width: 50px;
            text-align: center;
            cursor: move;
            position: relative;
        }

        .urf-row-handle .dashicons-menu {
            color: #94a3b8;
            font-size: 20px;
        }

        .urf-row-handle:hover .dashicons-menu {
            color: #64748b;
        }

        .urf-sortable-placeholder {
            background: #f0f4ff !important;
            border: 2px dashed #667eea !important;
            height: 60px !important;
            opacity: 0.7;
        }

        /* REMOVE BUTTONS */
        .urf-remove-nested-row,
        .urf-remove-nested2-row {
            color: #dc2626 !important;
            text-decoration: none !important;
            cursor: pointer !important;
            display: inline-block !important;
            padding: 5px !important;
            border-radius: 4px !important;
            transition: all 0.2s ease !important;
            background: transparent !important;
            border: none !important;
        }

        .urf-remove-nested-row:hover,
        .urf-remove-nested2-row:hover {
            color: #b91c1c !important;
            background: #fee2e2 !important;
            transform: scale(1.1);
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 1200px) {
            .urf-nested-table-container {
                overflow-x: auto;
            }
            
            .urf-nested-table {
                min-width: 800px;
            }
        }

        @media (max-width: 768px) {
            .urf-field-group {
                padding: 15px;
                margin: 15px 0;
            }
            
            .urf-vertical-fields {
                padding: 15px;
            }
            
            .urf-vertical-field {
                padding: 15px;
            }
            
            .urf-nested-repeater {
                padding: 15px;
            }
            
            .urf-add-nested-row,
            .urf-add-nested2-row {
                padding: 8px 16px !important;
                font-size: 12px !important;
            }
        }

        /* FIX FOR NESTED2 IN TABLES - SPECIFIC FIX */
        .urf-nested-repeater .urf-nested-table td .urf-nested-repeater {
            margin: 10px 0 0 0 !important;
            padding: 12px !important;
        }

        .urf-nested-repeater .urf-nested-table .urf-add-nested2-row {
            margin: 10px 0 5px 0 !important;
            padding: 8px 16px !important;
            font-size: 12px !important;
        }

        /* Ensure proper cell widths */
        .urf-nested-table th:first-child,
        .urf-nested-table td:first-child {
            width: 60px;
            min-width: 60px;
            max-width: 60px;
        }

        .urf-nested-table th:last-child,
        .urf-nested-table td:last-child {
            width: 80px;
            min-width: 80px;
            max-width: 80px;
        }

        /* Fix for nested tables inside table cells */
        .nested-field-wrapper {
            min-width: 200px;
            position: relative;
        }

        /* Row index styling */
        .nested-row-index,
        .nested2-row-index {
            font-weight: 600;
            color: #475569;
            font-size: 13px;
        }

        /* Hover effects for better UX */
        .urf-nested-tbody tr {
            transition: background-color 0.2s ease;
        }

        .urf-nested-tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Color picker fix */
        .urf-nested-table .wp-picker-container {
            width: 100% !important;
        }

        .urf-nested-table .wp-picker-input-wrap {
            width: 100% !important;
        }

        .urf-nested-table .wp-picker-input-wrap input[type="text"] {
            width: 100% !important;
        }

        /* Date picker fix - Enhanced */
        .urf-nested-table .hasDatepicker {
            background: white url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%2364748b" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>') no-repeat right 10px center !important;
            background-size: 20px !important;
            padding-right: 40px !important;
            cursor: pointer !important;
        }

        /* Fix for datepicker in nested tables */
        .urf-nested-table .ui-datepicker {
            font-size: 13px !important;
            min-width: 280px !important;
        }

        .urf-nested-table .ui-datepicker-calendar th {
            padding: 6px 0 !important;
            font-size: 11px !important;
        }

        .urf-nested-table .ui-datepicker-calendar td a {
            padding: 6px !important;
            font-size: 11px !important;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('URF: DOM loaded, starting initialization');
            
            (function($) {
                'use strict';
                
                function urfLog(msg) {
                    console.log('URF: ' + msg);
                }
                
                function isJQueryUILoaded() {
                    return typeof $.ui !== 'undefined' && 
                           typeof $.ui.sortable !== 'undefined' && 
                           typeof $.ui.datepicker !== 'undefined';
                }
                
                function waitForJQueryUI(callback) {
                    var attempts = 0;
                    var maxAttempts = 10;
                    
                    function check() {
                        attempts++;
                        if (isJQueryUILoaded()) {
                            urfLog('jQuery UI loaded successfully');
                            callback(true);
                        } else if (attempts < maxAttempts) {
                            setTimeout(check, 500);
                        } else {
                            urfLog('ERROR: jQuery UI not loaded after ' + maxAttempts + ' attempts');
                            callback(false);
                        }
                    }
                    
                    check();
                }
                
                function initDatepicker() {
                    if (typeof $.fn.datepicker === 'function') {
                        // Initialize all datepickers with proper calendar settings
                        $('.urf-datepicker').datepicker({
                            dateFormat: 'yy-mm-dd',
                            changeMonth: true,
                            changeYear: true,
                            yearRange: '-100:+10',
                            showButtonPanel: true,
                            showOtherMonths: true,
                            selectOtherMonths: true,
                            // Fix calendar positioning and styling
                            beforeShow: function(input, inst) {
                                setTimeout(function() {
                                    if (inst.dpDiv) {
                                        inst.dpDiv.css({
                                            'z-index': '100001',
                                            'font-size': '13px',
                                            'line-height': '1.4',
                                            'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif'
                                        });
                                        
                                        // Fix header styles
                                        inst.dpDiv.find('.ui-datepicker-header').css({
                                            'background': '#f8f9fa',
                                            'border': 'none',
                                            'border-radius': '3px',
                                            'padding': '8px 0',
                                            'margin-bottom': '8px'
                                        });
                                        
                                        // Fix calendar table styles
                                        inst.dpDiv.find('.ui-datepicker-calendar').css({
                                            'width': '100%',
                                            'border-collapse': 'collapse',
                                            'margin': '0'
                                        });
                                        
                                        // Fix navigation buttons
                                        inst.dpDiv.find('.ui-datepicker-prev, .ui-datepicker-next').css({
                                            'cursor': 'pointer',
                                            'top': '8px',
                                            'width': '30px',
                                            'height': '30px',
                                            'border': '1px solid #ddd',
                                            'border-radius': '3px',
                                            'background': 'white'
                                        });
                                    }
                                }, 0);
                                
                                return {};
                            },
                            // Fix for showing calendar properly
                            onClose: function(dateText, inst) {
                                // Clean up
                            }
                        });
                        
                        urfLog('Datepickers initialized with calendar fix');
                    } else {
                        urfLog('ERROR: Datepicker function not available');
                    }
                }
                
                function initColorpicker() {
                    if (typeof $.fn.wpColorPicker === 'function') {
                        $('.urf-colorpicker').each(function() {
                            if (!$(this).hasClass('wp-color-picker')) {
                                $(this).wpColorPicker();
                            }
                        });
                        urfLog('Colorpickers initialized');
                    }
                }
                
                function initImageUpload() {
                    // Use event delegation for dynamically created upload buttons
                    $(document).on('click', '.urf-upload-button', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        var $button = $(this);
                        var $container = $button.closest('.urf-file-upload-container');
                        var $input = $container.find('.urf-file-input');
                        var $preview = $container.find('.urf-file-preview');
                        var maxFiles = $button.data('max') || 1;
                        var currentFiles = $preview.children().length;
                        
                        if (currentFiles >= maxFiles && maxFiles > 0) {
                            alert('<?php _e('Maximum images reached', 'ultimate-repeater-field'); ?>');
                            return;
                        }
                        
                        var frame = wp.media({
                            title: '<?php _e('Select or Upload Image', 'ultimate-repeater-field'); ?>',
                            button: { text: '<?php _e('Use this image', 'ultimate-repeater-field'); ?>' },
                            library: { type: 'image' },
                            multiple: maxFiles > 1
                        });
                        
                        frame.on('select', function() {
                            var attachments = frame.state().get('selection').toJSON();
                            
                            if (maxFiles === 1) {
                                $preview.empty();
                                $input.val('');
                            }
                            
                            $.each(attachments, function(i, attachment) {
                                if (attachment.type === 'image') {
                                    var fileHtml = '<div class="urf-file-item" data-attachment-id="' + attachment.id + '">' +
                                        '<img src="' + attachment.url + '" class="urf-image-preview">' +
                                        '<div class="urf-file-name">' + attachment.filename + '</div>' +
                                        '<button type="button" class="urf-remove-file dashicons dashicons-no-alt" title="<?php _e('Remove', 'ultimate-repeater-field'); ?>"></button>' +
                                        '</div>';
                                    
                                    $preview.append(fileHtml);
                                    
                                    if (maxFiles === 1) {
                                        $input.val(attachment.id);
                                    } else {
                                        var currentValue = $input.val();
                                        if (currentValue) {
                                            var values = currentValue.split(',');
                                            values.push(attachment.id);
                                            $input.val(values.join(','));
                                        } else {
                                            $input.val(attachment.id);
                                        }
                                    }
                                }
                            });
                            
                            frame.close();
                        });
                        
                        frame.on('close', function() {
                            frame.detach();
                        });
                        
                        frame.open();
                    });
                    
                    // Remove file event
                    $(document).on('click', '.urf-remove-file', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        var $removeBtn = $(this);
                        var $fileItem = $removeBtn.closest('.urf-file-item');
                        var $container = $fileItem.closest('.urf-file-upload-container');
                        var $input = $container.find('.urf-file-input');
                        
                        if ($input.hasClass('multiple-images')) {
                            // Handle multiple images
                            var attachmentId = $fileItem.data('attachment-id');
                            var currentValue = $input.val();
                            if (currentValue) {
                                var values = currentValue.split(',');
                                var index = values.indexOf(attachmentId.toString());
                                if (index > -1) {
                                    values.splice(index, 1);
                                    $input.val(values.join(','));
                                }
                            }
                        } else {
                            $input.val('');
                        }
                        
                        $fileItem.remove();
                    });
                }
                
                // ==============================================
                // LEVEL 1 NESTED REPEATER FUNCTIONS
                // ==============================================
                
                function initNestedRepeaters() {
                    console.log('[URF] Initializing level 1 repeaters...');
                    
                    // Helper function to get proper row count
                    function getRowCount($tbody) {
                        return $tbody.find('tr[data-nested-index]').not('.urf-clone-nested-row').length;
                    }
                    
                    // Helper function to update row indices
                    function updateRowIndices($tbody, skipFirstRow = false) {
                        var rows = $tbody.find('tr[data-nested-index]').not('.urf-clone-nested-row');
                        var startIndex = skipFirstRow ? 1 : 0;
                        
                        rows.each(function(newIndex) {
                            if (skipFirstRow && newIndex === 0) return; // Skip first row if needed
                            
                            var $row = $(this);
                            var oldIndex = parseInt($row.data('nested-index'));
                            
                            if (oldIndex !== newIndex) {
                                console.log('[URF] Reindexing row from', oldIndex, 'to', newIndex);
                                
                                // Update data attribute
                                $row.data('nested-index', newIndex);
                                $row.attr('data-nested-index', newIndex);
                                
                                // Update display index
                                var $displaySpan = $row.find('.nested-row-index');
                                if ($displaySpan.length === 0) {
                                    $displaySpan = $('<span>').addClass('nested-row-index');
                                    $row.find('.urf-row-handle').append($displaySpan);
                                }
                                $displaySpan.text(newIndex + 1);
                                
                                // Update all name attributes with new index
                                $row.find('[name]').each(function() {
                                    var name = $(this).attr('name');
                                    if (name) {
                                        // Replace numeric indices in the 4th position (parts[3])
                                        var parts = name.split('[');
                                        if (parts.length >= 5) {
                                            // Check if parts[3] is a number (not a placeholder)
                                            var currentPart = parts[3].replace(']', '');
                                            if (!isNaN(currentPart)) {
                                                parts[3] = newIndex + ']';
                                                var newName = parts.join('[');
                                                $(this).attr('name', newName);
                                            }
                                        }
                                    }
                                });
                            }
                        });
                    }
                    
                    // Fix existing rows on initialization
                    function fixExistingRows() {
                        $('.urf-nested-tbody').each(function() {
                            var $tbody = $(this);
                            updateRowIndices($tbody);
                        });
                    }
                    
                    // Run fix on page load
                    fixExistingRows();
                    
                    // Add level 1 nested row - using event delegation with namespace
                    $(document).off('click.urfAdd').on('click.urfAdd', '.urf-add-nested-row', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        console.log('[URF] Adding new level 1 row...');
                        
                        var $button = $(this);
                        var $nestedRepeater = $button.closest('.urf-nested-repeater');
                        var $table = $nestedRepeater.find('.urf-nested-table');
                        var $tbody = $table.find('tbody.urf-nested-tbody');
                        var $cloneRow = $tbody.find('.urf-clone-nested-row');
                        
                        if ($cloneRow.length === 0) {
                            console.error('URF: Clone row not found');
                            return;
                        }
                        
                        // Get proper row count
                        var nextIndex = getRowCount($tbody);
                        
                        console.log('[URF] Current row count:', nextIndex, 'New index:', nextIndex);
                        
                        // Clone the row
                        var $newRow = $cloneRow.clone();
                        $newRow.removeClass('urf-clone-nested-row').show();
                        
                        // Update all placeholders with new index
                        $newRow.find('[name]').each(function() {
                            var name = $(this).attr('name');
                            if (name) {
                                // Replace both placeholder formats
                                name = name.replace(/\[__NESTED_INDEX__\]/g, '[' + nextIndex + ']');
                                name = name.replace(/\$__NESTED_INDEX__\$/g, '[' + nextIndex + ']');
                                $(this).attr('name', name);
                            }
                        });
                        
                        // Update IDs
                        $newRow.find('[id]').each(function() {
                            var id = $(this).attr('id');
                            if (id) {
                                id = id.replace(/__NESTED_INDEX__/g, nextIndex);
                                $(this).attr('id', id);
                            }
                        });
                        
                        // Set data attribute and display
                        $newRow.attr('data-nested-index', nextIndex);
                        
                        // Ensure display span exists
                        var $displaySpan = $newRow.find('.nested-row-index');
                        if ($displaySpan.length === 0) {
                            $displaySpan = $('<span>').addClass('nested-row-index');
                            $newRow.find('.urf-row-handle').append($displaySpan);
                        }
                        $displaySpan.text(nextIndex + 1);
                        
                        // Insert before clone row
                        $cloneRow.before($newRow);
                        
                        console.log('[URF] Added row at index', nextIndex);
                        
                        // Reinitialize field controls - including datepicker
                        setTimeout(function() {
                            initFieldControls($newRow);
                        }, 100);
                    });
                    
                    // Remove level 1 nested row - using event delegation with namespace
                    $(document).off('click.urfRemove').on('click.urfRemove', '.urf-remove-nested-row', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Prevent multiple clicks
                        if ($(this).data('processing')) return;
                        $(this).data('processing', true);
                        
                        console.log('[URF] Removing level 1 row...');
                        
                        var $button = $(this);
                        var $row = $button.closest('tr');
                        var $tbody = $row.closest('tbody');
                        var removedIndex = parseInt($row.data('nested-index'));
                        
                        // Store reference for cleanup
                        var rowData = {
                            index: removedIndex,
                            tbody: $tbody
                        };
                        
                        // Single confirmation check
                        if (window.URF_ConfirmRemove === undefined) {
                            window.URF_ConfirmRemove = true;
                            
                            if (confirm('Are you sure you want to remove this row?')) {
                                console.log('[URF] Removing row at index', removedIndex);
                                
                                // Remove the row
                                $row.remove();
                                
                                // Reindex remaining rows
                                updateRowIndices($tbody);
                                
                                console.log('[URF] Reindexing complete');
                            }
                            
                            setTimeout(function() {
                                window.URF_ConfirmRemove = undefined;
                            }, 100);
                        }
                        
                        $(this).data('processing', false);
                    });
                    
                    // Sortable for level 1 - Initialize only once
                    if (typeof $.fn.sortable === 'function') {
                        $('.urf-nested-tbody').each(function() {
                            var $tbody = $(this);
                            
                            // Check if already sortable
                            if (!$tbody.hasClass('ui-sortable')) {
                                $tbody.sortable({
                                    handle: '.urf-row-handle',
                                    axis: 'y',
                                    placeholder: 'urf-sortable-placeholder',
                                    forcePlaceholderSize: true,
                                    items: 'tr[data-nested-index]', // Only sort level 1 rows
                                    start: function(e, ui) {
                                        ui.placeholder.height(ui.item.height());
                                        console.log('[URF] Started sorting level 1 rows');
                                    },
                                    update: function(event, ui) {
                                        console.log('[URF] Sorting complete, reindexing...');
                                        var $tbody = $(this);
                                        updateRowIndices($tbody);
                                        console.log('[URF] Sorting reindex complete');
                                    }
                                });
                            }
                        });
                    }
                    
                    console.log('[URF] Level 1 repeaters initialized');
                }

                // Helper function to initialize field controls
                function initFieldControls($row) {
                    // Datepickers - FIXED with proper calendar settings
                    $row.find('.urf-datepicker').each(function() {
                        if (typeof $.fn.datepicker === 'function' && !$(this).hasClass('hasDatepicker')) {
                            $(this).datepicker({
                                dateFormat: 'yy-mm-dd',
                                changeMonth: true,
                                changeYear: true,
                                yearRange: '-100:+10',
                                showButtonPanel: true,
                                showOtherMonths: true,
                                selectOtherMonths: true,
                                beforeShow: function(input, inst) {
                                    setTimeout(function() {
                                        if (inst.dpDiv) {
                                            inst.dpDiv.css({
                                                'z-index': '100001',
                                                'font-size': '13px',
                                                'line-height': '1.4',
                                                'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
                                                'width': '300px'
                                            });
                                            
                                            // Fix for nested tables
                                            if ($(input).closest('.urf-nested-table').length) {
                                                inst.dpDiv.css({
                                                    'font-size': '12px',
                                                    'width': '280px'
                                                });
                                            }
                                        }
                                    }, 0);
                                    return {};
                                }
                            }).addClass('hasDatepicker');
                        }
                    });
                    
                    // Color pickers
                    $row.find('.urf-colorpicker').each(function() {
                        if (typeof $.fn.wpColorPicker === 'function' && !$(this).hasClass('wp-color-picker')) {
                            $(this).wpColorPicker().addClass('wp-color-picker');
                        }
                    });
                    
                    // Add widefat class to form elements
                    $row.find('input[type="text"], textarea, select').addClass('widefat');
                }

                // Initialize on document ready
                $(document).ready(function() {
                    console.log('[URF] Document ready, initializing...');
                    
                    // Clear any existing URF event handlers to prevent duplicates
                    $(document).off('click.urfAdd');
                    $(document).off('click.urfRemove');
                    
                    // Initialize nested repeaters
                    initNestedRepeaters();
                    
                    console.log('[URF] Initialization complete');
                });
                
                // ==============================================
                // LEVEL 2 NESTED REPEATER FUNCTIONS
                // ==============================================

                function initNested2Repeaters() {
                    // Add level 2 nested row
                    $(document).on('click', '.urf-add-nested2-row', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        var $button = $(this);
                        var $nested2Repeater = $button.closest('.urf-nested-repeater');
                        var $table = $nested2Repeater.find('.urf-nested-table');
                        var $tbody = $table.find('tbody.urf-nested-tbody');
                        var $cloneRow = $tbody.find('.urf-clone-nested2-row');
                        
                        if ($cloneRow.length === 0) {
                            console.error('Nested2 repeater clone row not found');
                            return;
                        }
                        
                        // FIXED: Use max existing index + 1 to ensure unique, sequential indices
                        var maxIndex = -1;
                        $tbody.find('tr:not(.urf-clone-nested2-row)').each(function() {
                            var idx = parseInt($(this).data('nested2-index'));
                            if (!isNaN(idx) && idx > maxIndex) maxIndex = idx;
                        });
                        var nested2RowCount = maxIndex + 1;
                        console.log('Adding new row at index: ' + nested2RowCount);
                        
                        var $newRow = $cloneRow.clone();
                        $newRow.removeClass('urf-clone-nested2-row').show();
                        
                        // FIXED: Use parts-based replacement for consistency and reliability
                        $newRow.find('[name]').each(function() {
                            var name = $(this).attr('name');
                            if (name) {
                                var parts = name.split('[');
                                if (parts.length >= 6) { // Nested2 names have at least 7 parts
                                    parts[5] = nested2RowCount + ']'; // parts[5] is the nested2_index]
                                    name = parts.join('[');
                                }
                                $(this).attr('name', name);
                                console.log('Updated name: ' + name);
                            }
                        });
                        
                        $newRow.find('[id]').each(function() {
                            var id = $(this).attr('id');
                            if (id) {
                                id = id.replace(/__NESTED2_INDEX__/g, nested2RowCount);
                                $(this).attr('id', id);
                                console.log('Updated id: ' + id);
                            }
                        });
                        
                        $newRow.attr('data-nested2-index', nested2RowCount);
                        $newRow.find('.nested2-row-index').text(nested2RowCount + 1);
                        
                        // Insert before the clone row
                        $cloneRow.before($newRow);
                        
                        // Reinitialize all field controls for the new row - including datepicker fix
                        setTimeout(function() {
                            // Datepickers with proper calendar
                            $newRow.find('.urf-datepicker').each(function() {
                                if (typeof $.fn.datepicker === 'function' && !$(this).hasClass('hasDatepicker')) {
                                    $(this).datepicker({
                                        dateFormat: 'yy-mm-dd',
                                        changeMonth: true,
                                        changeYear: true,
                                        yearRange: '-100:+10',
                                        showButtonPanel: true,
                                        showOtherMonths: true,
                                        selectOtherMonths: true,
                                        beforeShow: function(input, inst) {
                                            setTimeout(function() {
                                                if (inst.dpDiv) {
                                                    inst.dpDiv.css({
                                                        'z-index': '100001',
                                                        'font-size': '12px',
                                                        'line-height': '1.4',
                                                        'width': '280px'
                                                    });
                                                }
                                            }, 0);
                                            return {};
                                        }
                                    }).addClass('hasDatepicker');
                                }
                            });
                            
                            // Color pickers
                            $newRow.find('.urf-colorpicker').each(function() {
                                if (typeof $.fn.wpColorPicker === 'function' && !$(this).hasClass('wp-color-picker')) {
                                    $(this).wpColorPicker().addClass('wp-color-picker');
                                }
                            });
                            
                            // Make sure all inputs have proper styling
                            $newRow.find('input[type="text"], textarea, select').addClass('widefat');
                            
                        }, 100);
                    });
                    
                    // Remove level 2 nested row - FIXED: Force full reindexing to 0,1,2,... and use reliable parts-based targeting
                    $(document).on('click', '.urf-remove-nested2-row', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        if ($(this).data('processing')) return;
                        $(this).data('processing', true);
                        
                        if (confirm('<?php _e('Are you sure you want to remove this nested row?', 'ultimate-repeater-field'); ?>')) {
                            var $row = $(this).closest('tr');
                            var $tbody = $row.closest('tbody');
                            
                            $row.remove();
                            
                            // Force reindexing of all remaining rows to sequential 0,1,2,... (remove the oldIndex check to ensure it always happens)
                            $tbody.find('tr:not(.urf-clone-nested2-row)').each(function(newIndex) {
                                var $currentRow = $(this);
                                
                                // Always update to ensure sequential indices
                                $currentRow.data('nested2-index', newIndex);
                                $currentRow.attr('data-nested2-index', newIndex);
                                $currentRow.find('.nested2-row-index').text(newIndex + 1);
                                
                                // Update all input names in this row - use parts-based targeting for reliability
                                $currentRow.find('[name]').each(function() {
                                    var name = $(this).attr('name');
                                    if (name) {
                                        var parts = name.split('[');
                                        if (parts.length >= 6) { // Nested2 names have at least 7 parts
                                            parts[5] = newIndex + ']'; // parts[5] is the nested2_index]
                                            name = parts.join('[');
                                        }
                                        $(this).attr('name', name);
                                        console.log('Updated name: ' + name);
                                    }
                                });
                                
                                // Update IDs too
                                $currentRow.find('[id]').each(function() {
                                    var id = $(this).attr('id');
                                    if (id && id.includes('__NESTED2_INDEX__')) {
                                        var newId = id.replace(/__NESTED2_INDEX__/g, newIndex);
                                        $(this).attr('id', newId);
                                        console.log('Updated id: ' + id);
                                    }
                                });
                            });
                        }
                        
                        $(this).data('processing', false);
                    });
                    
                    // Sortable for level 2 - FIXED: Same force reindexing logic
                    if (typeof $.fn.sortable === 'function') {
                        $('.urf-nested-tbody').sortable({
                            handle: '.urf-row-handle',
                            axis: 'y',
                            placeholder: 'urf-sortable-placeholder',
                            forcePlaceholderSize: true,
                            start: function(e, ui) {
                                ui.placeholder.height(ui.item.height());
                            },
                            update: function(event, ui) {
                                var $tbody = $(this);
                                // Force reindexing after sorting
                                $tbody.find('tr:not(.urf-clone-nested2-row)').each(function(newIndex) {
                                    var $currentRow = $(this);
                                    
                                    $currentRow.data('nested2-index', newIndex);
                                    $currentRow.attr('data-nested2-index', newIndex);
                                    $currentRow.find('.nested2-row-index').text(newIndex + 1);
                                    
                                    // Update all input names in this row - same parts-based targeting
                                    $currentRow.find('[name]').each(function() {
                                        var name = $(this).attr('name');
                                        if (name) {
                                            var parts = name.split('[');
                                            if (parts.length >= 6) {
                                                parts[5] = newIndex + ']';
                                                name = parts.join('[');
                                            }
                                            $(this).attr('name', name);
                                        }
                                    });
                                });
                            }
                        });
                    }
                    
                    urfLog('Level 2 nested repeaters initialized');
                }

                // ==============================================
                // MAIN INITIALIZATION FUNCTION
                // ==============================================
                
                function initURF() {
                    if ($('body').hasClass('urf-initialized')) {
                        urfLog('URF already initialized, skipping');
                        return;
                    }
                    
                    urfLog('Starting URF initialization');
                    
                    waitForJQueryUI(function(success) {
                        if (success) {
                            initDatepicker();
                            initColorpicker();
                            initImageUpload();
                            initNestedRepeaters();
                            initNested2Repeaters();
                            
                            $('body').addClass('urf-initialized');
                            
                            urfLog('URF initialization complete');
                        } else {
                            urfLog('URF initialization failed - jQuery UI not loaded');
                            initImageUpload();
                            initNestedRepeaters();
                            initNested2Repeaters();
                            $('body').addClass('urf-initialized');
                        }
                    });
                }
                
                // Initialize everything
                initURF();
                
                // Reinitialize when new content is added via AJAX
                $(document).ajaxComplete(function() {
                    if (!$('body').hasClass('urf-initialized')) {
                        initURF();
                    }
                });
                
            })(jQuery);
        });
    </script>
    <?php
}
     
    public function add_meta_boxes() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'urf_field_groups';
        
        $current_screen = get_current_screen();
        if (!$current_screen) return;
        
        $all_field_groups = $wpdb->get_results("SELECT * FROM $table_name");
        
        foreach ($all_field_groups as $group) {
            if ($this->should_display_field_group($group, get_the_ID())) {
                add_meta_box(
                    'urf_field_group_' . $group->slug,
                    $group->name,
                    array($this, 'render_field_group_meta_box'),
                    $current_screen->post_type,
                    'normal',
                    'high',
                    array('group' => $group)
                );
            }
        }
    }
    
    public function should_display_field_group($group, $post_id) {
        $post_types = maybe_unserialize($group->post_types);
        $pages = maybe_unserialize($group->pages);
        
        if (empty($post_types) && empty($pages)) {
            return false;
        }
        
        $current_post_type = get_post_type($post_id);
        
        if (is_array($post_types) && in_array('all', $post_types)) {
            if (is_array($pages) && !empty($pages)) {
                return in_array($post_id, $pages);
            }
            return true;
        }
        
        if (!is_array($post_types) || !in_array($current_post_type, $post_types)) {
            return false;
        }
        
        if (is_array($pages) && !empty($pages)) {
            return in_array($post_id, $pages);
        }
        
        return true;
    }
    
    public function render_field_group_meta_box($post, $metabox) {
    $group = $metabox['args']['group'];
    $fields = maybe_unserialize($group->fields);
    $data = $this->get_field_data($post->ID, $group->slug);
    
    if (empty($fields)) {
        echo '<p>' . __('No fields configured for this group.', 'ultimate-repeater-field') . '</p>';
        return;
    }
    
    $field_values = $this->get_single_field_values($post->ID, $group->slug);
    
    ?>
    
    <div class="urf-field-group" data-group="<?php echo esc_attr($group->slug); ?>">
        <input type="hidden" name="urf_field_group[]" value="<?php echo esc_attr($group->slug); ?>">
        <input type="hidden" name="urf_nonce_<?php echo esc_attr($group->slug); ?>" value="<?php echo wp_create_nonce('urf_save_fields_' . $group->slug); ?>">
        
        <div class="urf-vertical-fields">
            <?php foreach ($fields as $field): 
                $field_name = $field['name'];
                $field_value = isset($field_values[$field_name]) ? $field_values[$field_name] : '';
                
                if ($field['type'] === 'repeater') continue;
                
                $input_name = "urf_data[{$group->slug}][{$field_name}]";
                $input_id = "urf_{$group->slug}_{$field_name}";
            ?>
                <div class="urf-vertical-field">
                    <label>
                        <?php echo esc_html($field['label']); ?>
                        <?php if ($field['required'] ?? false): ?>
                            <span style="color: #dc3232;">*</span>
                        <?php endif; ?>
                    </label>
                    <div>
                        <?php $this->render_field_input($field, $input_name, $input_id, $field_value, 0, $group->slug, 0); ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php 
            foreach ($fields as $field): 
                if ($field['type'] !== 'repeater') continue;
                
                $repeater_data = isset($data[$field['name']]) ? $data[$field['name']] : array();
                $subfields = $field['subfields'] ?? array();
            ?>
                <div class="urf-vertical-field">
                    <label>
                        <?php echo esc_html($field['label']); ?>
                        <?php if ($field['required'] ?? false): ?>
                            <span style="color: #dc3232;">*</span>
                        <?php endif; ?>
                    </label>
                    
                    <div class="urf-nested-repeater" data-field-name="<?php echo esc_attr($field['name']); ?>" data-row-index="0">
                        <table class="urf-repeater-table urf-nested-table" style="margin-top: 0;">
                            <thead>
                                <tr>
                                    <th class="urf-row-handle">#</th>
                                    <?php foreach ($subfields as $subfield): ?>
                                        <th><?php echo esc_html($subfield['label']); ?>
                                            <?php if ($subfield['required'] ?? false): ?>
                                                <span style="color: #dc3232;">*</span>
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="urf-row-actions"><?php _e('Actions', 'ultimate-repeater-field'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="urf-nested-tbody">
                                <?php if (!empty($repeater_data)): ?>
                                    <?php foreach ($repeater_data as $nested_index => $nested_row): ?>
                                        <?php $this->render_nested_repeater_row($subfields, $field['name'], $group->slug, 0, $nested_index, $nested_row); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <!-- ONLY SHOW CLONE ROW, NO INITIAL EMPTY ROW -->
                                <?php $this->render_nested_repeater_row($subfields, $field['name'], $group->slug, 0, '__NESTED_INDEX__', array(), true); ?>
                            </tbody>
                        </table>
                        
                        <!-- LEVEL 1 ADD ROW BUTTON -->
                        <button type="button" class="button button-small urf-add-nested-row" 
                                data-field-name="<?php echo esc_attr($field['name']); ?>" 
                                data-row-index="0">
                            <span class="dashicons dashicons-plus"></span> <?php _e('Add Row', 'ultimate-repeater-field'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
    
    public function get_single_field_values($post_id, $group_slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'urf_fields';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT field_name, field_value FROM $table_name 
             WHERE post_id = %d AND field_group = %s 
             AND parent_field IS NULL AND parent_row_index IS NULL",
            $post_id,
            $group_slug
        ));
        
        $field_values = array();
        foreach ($results as $row) {
            $field_values[$row->field_name] = maybe_unserialize($row->field_value);
        }
        
        return $field_values;
    }
    
    public function render_field_input($field, $name, $id, $value, $row_index, $group_slug, $parent_row_index = 0) {
        $field_type = $field['type'] ?? 'text';
        $required = $field['required'] ?? false;
        
        if (is_array($value) && $field_type !== 'checkbox' && $field_type !== 'repeater') {
            $value = !empty($value) ? reset($value) : '';
        }
        
        switch ($field_type) {
            case 'text':
                ?>
                <input type="text" 
                       name="<?php echo $name; ?>" 
                       id="<?php echo $id; ?>" 
                       value="<?php echo esc_attr($value); ?>" 
                       class="widefat"
                       <?php echo $required ? 'required' : ''; ?>>
                <?php
                break;
                
            case 'textarea':
                ?>
                <textarea name="<?php echo $name; ?>" 
                          id="<?php echo $id; ?>" 
                          class="widefat" 
                          rows="3"
                          <?php echo $required ? 'required' : ''; ?>><?php echo esc_textarea($value); ?></textarea>
                <?php
                break;
                
            case 'image':
                $image_value = $value;
                if (is_array($image_value) && !empty($image_value)) {
                    $image_value = reset($image_value);
                }
                
                if (empty($image_value) || $image_value == '0') {
                    $image_value = '';
                }
                ?>
                <div class="urf-file-upload-container" data-field-type="image">
                    <button type="button" class="button urf-upload-button" data-multiple="false">
                        <?php _e('Select Image', 'ultimate-repeater-field'); ?>
                    </button>
                    <input type="hidden" name="<?php echo $name; ?>" id="<?php echo $id; ?>" value="<?php echo esc_attr($image_value); ?>" class="urf-file-input">
                    
                    <div class="urf-file-preview">
                        <?php if (!empty($image_value)): 
                            if (is_numeric($image_value)) {
                                $image_url = wp_get_attachment_url($image_value);
                                $image_thumb = wp_get_attachment_image($image_value, 'thumbnail');
                                $filename = basename($image_url);
                            } else {
                                $image_url = $image_value;
                                $image_thumb = '<img src="' . esc_url($image_value) . '" class="urf-image-preview">';
                                $filename = basename($image_value);
                            }
                        ?>
                            <div class="urf-file-item" data-attachment-id="<?php echo esc_attr($image_value); ?>">
                                <?php echo $image_thumb; ?>
                                <div class="urf-file-name"><?php echo esc_html($filename); ?></div>
                                <button type="button" class="urf-remove-file dashicons dashicons-no-alt" title="<?php _e('Remove', 'ultimate-repeater-field'); ?>"></button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                break;
                
            case 'select':
                $select_value = $value;
                if (is_array($select_value)) {
                    $select_value = !empty($select_value) ? reset($select_value) : '';
                }
                ?>
                <select name="<?php echo $name; ?>" 
                        id="<?php echo $id; ?>" 
                        class="widefat"
                        <?php echo $required ? 'required' : ''; ?>>
                    <option value=""><?php _e('-- Select --', 'ultimate-repeater-field'); ?></option>
                    <?php
                    $options = explode("\n", $field['options'] ?? '');
                    foreach ($options as $option) {
                        $option = trim($option);
                        if (empty($option)) continue;
                        
                        if (strpos($option, '|') !== false) {
                            list($opt_value, $opt_label) = explode('|', $option, 2);
                        } else {
                            $opt_value = $opt_label = $option;
                        }
                        ?>
                        <option value="<?php echo esc_attr(trim($opt_value)); ?>" <?php selected($select_value, trim($opt_value)); ?>>
                            <?php echo esc_html(trim($opt_label)); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
                <?php
                break;
                
            case 'checkbox':
                ?>
                <div class="urf-checkbox-group">
                    <?php
                    $values = is_array($value) ? $value : array($value);
                    $options = explode("\n", $field['options'] ?? '');
                    foreach ($options as $option) {
                        $option = trim($option);
                        if (empty($option)) continue;
                        
                        if (strpos($option, '|') !== false) {
                            list($opt_value, $opt_label) = explode('|', $option, 2);
                        } else {
                            $opt_value = $opt_label = $option;
                        }
                        $opt_id = $id . '_' . sanitize_title($opt_value);
                        ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" 
                                   name="<?php echo $name; ?>[]" 
                                   id="<?php echo $opt_id; ?>" 
                                   value="<?php echo esc_attr(trim($opt_value)); ?>"
                                   <?php checked(in_array(trim($opt_value), $values)); ?>>
                            <?php echo esc_html(trim($opt_label)); ?>
                        </label>
                        <?php
                    }
                    ?>
                </div>
                <?php
                break;
                
            case 'radio':
                $radio_value = $value;
                if (is_array($radio_value)) {
                    $radio_value = !empty($radio_value) ? reset($radio_value) : '';
                }
                ?>
                <div class="urf-radio-group">
                    <?php
                    $options = explode("\n", $field['options'] ?? '');
                    foreach ($options as $option) {
                        $option = trim($option);
                        if (empty($option)) continue;
                        
                        if (strpos($option, '|') !== false) {
                            list($opt_value, $opt_label) = explode('|', $option, 2);
                        } else {
                            $opt_value = $opt_label = $option;
                        }
                        $opt_id = $id . '_' . sanitize_title($opt_value);
                        ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="radio" 
                                   name="<?php echo $name; ?>" 
                                   id="<?php echo $opt_id; ?>" 
                                   value="<?php echo esc_attr(trim($opt_value)); ?>"
                                   <?php checked($radio_value, trim($opt_value)); ?>
                                   <?php echo $required ? 'required' : ''; ?>>
                            <?php echo esc_html(trim($opt_label)); ?>
                        </label>
                        <?php
                    }
                    ?>
                </div>
                <?php
                break;
                
            case 'color':
                $color_value = $value;
                if (is_array($color_value)) {
                    $color_value = !empty($color_value) ? reset($color_value) : '';
                }
                ?>
                <input type="text" 
                       name="<?php echo $name; ?>" 
                       id="<?php echo $id; ?>" 
                       value="<?php echo esc_attr($color_value); ?>" 
                       class="urf-colorpicker"
                       data-default-color="#ffffff">
                <?php
                break;
                
            case 'date':
                $date_value = $value;
                if (is_array($date_value)) {
                    $date_value = !empty($date_value) ? reset($date_value) : '';
                }
                ?>
                <input type="text" 
                       name="<?php echo $name; ?>" 
                       id="<?php echo $id; ?>" 
                       value="<?php echo esc_attr($date_value); ?>" 
                       class="urf-datepicker widefat"
                       <?php echo $required ? 'required' : ''; ?>>
                <?php
                break;
                
            default:
                $default_value = $value;
                if (is_array($default_value)) {
                    $default_value = !empty($default_value) ? reset($default_value) : '';
                }
                ?>
                <input type="text" 
                       name="<?php echo $name; ?>" 
                       id="<?php echo $id; ?>" 
                       value="<?php echo esc_attr($default_value); ?>" 
                       class="widefat"
                       <?php echo $required ? 'required' : ''; ?>>
                <?php
                break;
        }
    }
    
    public function render_nested_repeater_row($subfields, $parent_field_name, $group_slug, $parent_row_index, $nested_index, $nested_row, $is_clone = false) {
    $row_class = $is_clone ? 'urf-clone-nested-row' : '';
    $display = $is_clone ? 'style="display: none;"' : '';
    
    $index_name = $is_clone ? '__NESTED_INDEX__' : $nested_index;
    $display_index = $is_clone ? '0' : (is_numeric($nested_index) ? (intval($nested_index) + 1) : '1');
    
    ?>
    <tr class="<?php echo $row_class; ?>" <?php echo $display; ?> data-nested-index="<?php echo $index_name; ?>">
        <td class="urf-row-handle" style="width: 60px; min-width: 60px; max-width: 60px;">
            <span class="dashicons dashicons-menu"></span>
            <span class="nested-row-index"><?php echo $display_index; ?></span>
        </td>
        
        <td style="width: 100%;" colspan="<?php echo count($subfields); ?>">
            <div style="display: flex; flex-direction: column; gap: 20px; padding: 15px; background: #f8fafc; border-radius: 8px;">
                <?php foreach ($subfields as $subfield_index => $subfield): 
                    $subfield_name = $subfield['name'];
                    $field_value = isset($nested_row[$subfield_name]) ? $nested_row[$subfield_name] : '';
                    
                    $input_name = "urf_data[{$group_slug}][{$parent_field_name}][{$index_name}][{$subfield_name}]";
                    $input_id = "urf_{$group_slug}_{$parent_field_name}_{$index_name}_{$subfield_name}";
                ?>
                    <div class="urf-subfield-container" style="background: white; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: <?php echo ($subfield_index === count($subfields) - 1) ? '0' : '15px'; ?>;">
                        <div style="display: flex; align-items: flex-start; gap: 15px; margin-bottom: 15px;">
                            <div style="flex: 0 0 150px;">
                                <label style="font-weight: 600; color: #1e293b; display: block; margin-bottom: 8px;">
                                    <?php echo esc_html($subfield['label']); ?>
                                    <?php if ($subfield['required'] ?? false): ?>
                                        <span style="color: #dc3232;">*</span>
                                    <?php endif; ?>
                                </label>
                               
                            </div>
                            
                            <div style="flex: 1;">
                                <?php 
                                if (isset($subfield['type']) && $subfield['type'] === 'repeater') {
                                    $nested_repeater_data = is_array($field_value) ? $field_value : array();
                                    $nested_subfields = isset($subfield['subfields']) ? $subfield['subfields'] : array();
                                    ?>
                                    <div class="urf-nested-repeater" 
                                         data-field-name="<?php echo esc_attr($subfield_name); ?>" 
                                         data-parent-field="<?php echo esc_attr($parent_field_name); ?>"
                                         data-row-index="<?php echo esc_attr($index_name); ?>">
                                        
                                       
                                        
                                        <div class="urf-nested-table-container" style="overflow-x: auto;">
                                            <table class="urf-repeater-table urf-nested-table" style="margin-top: 10px; width: 100%;">
                                                <thead>
                                                    <tr>
                                                        <th class="urf-row-handle" style="width: 50px;">#</th>
                                                        <?php foreach ($nested_subfields as $nested_subfield): ?>
                                                            <th style="min-width: 150px;"><?php echo esc_html($nested_subfield['label']); ?>
                                                                <?php if ($nested_subfield['required'] ?? false): ?>
                                                                    <span style="color: #dc3232;">*</span>
                                                                <?php endif; ?>
                                                            </th>
                                                        <?php endforeach; ?>
                                                        <th class="urf-row-actions" style="width: 80px;"><?php _e('Actions', 'ultimate-repeater-field'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody class="urf-nested-tbody">
                                                    <?php if (!empty($nested_repeater_data)): 
                                                        $nested2_index = 0;
                                                        foreach ($nested_repeater_data as $nested2_row): ?>
                                                            <?php $this->render_nested2_repeater_row($nested_subfields, $subfield_name, $group_slug, $parent_field_name, $index_name, $nested2_index, $nested2_row); ?>
                                                            <?php $nested2_index++; ?>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                    
                                                    <?php $this->render_nested2_repeater_row($nested_subfields, $subfield_name, $group_slug, $parent_field_name, $index_name, '__NESTED2_INDEX__', array(), true); ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <button type="button" class="button button-small urf-add-nested2-row" 
                                                data-field-name="<?php echo esc_attr($subfield_name); ?>" 
                                                data-parent-field="<?php echo esc_attr($parent_field_name); ?>"
                                                data-row-index="<?php echo esc_attr($index_name); ?>"
                                                style="margin-top: 10px;">
                                            <span class="dashicons dashicons-plus"></span> <?php _e('Add Row', 'ultimate-repeater-field'); ?>
                                        </button>
                                    </div>
                                    <?php
                                } else {
                                    // Regular field (not a nested repeater)
                                    $this->render_field_input($subfield, $input_name, $input_id, $field_value, $index_name, $group_slug, $parent_row_index);
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </td>
        
        <td class="urf-row-actions" style="width: 80px; min-width: 80px; max-width: 80px;">
            <a href="#" class="urf-remove-nested-row dashicons dashicons-trash" title="<?php _e('Remove Row', 'ultimate-repeater-field'); ?>"></a>
        </td>
    </tr>
    <?php
}
public function render_nested2_repeater_row($subfields, $field_name, $group_slug, $parent_field_name, $parent_row_index, $nested2_index, $nested2_row, $is_clone = false) {
    // For non-clone rows, check if the row is empty and should be skipped
    if (!$is_clone) {
        $row_is_empty = true;
        foreach ($nested2_row as $value) {
            if (!empty($value) && $value !== '' && $value !== null) {
                $row_is_empty = false;
                break;
            }
        }
        
        // Don't render completely empty rows (only render if it's a clone or has content)
        if ($row_is_empty) {
            return;
        }
    }
    
    $row_class = $is_clone ? 'urf-clone-nested2-row' : '';
    $display = $is_clone ? 'style="display: none;"' : '';
    
    $index_name = $is_clone ? '__NESTED2_INDEX__' : $nested2_index;
    $display_index = $is_clone ? '0' : (is_numeric($nested2_index) ? (intval($nested2_index) + 1) : '1');
    
    ?>
    <tr class="<?php echo $row_class; ?>" <?php echo $display; ?> data-nested2-index="<?php echo $index_name; ?>">
        <td class="urf-row-handle" style="width: 60px; min-width: 60px; max-width: 60px;">
            <span class="dashicons dashicons-menu"></span>
            <span class="nested2-row-index"><?php echo $display_index; ?></span>
        </td>
        
        <td style="width: 100%;" colspan="<?php echo count($subfields); ?>">
            <div style="display: flex; flex-direction: column; gap: 15px; padding: 15px; background: #f8fafc; border-radius: 8px;">
                <?php foreach ($subfields as $subfield): 
                    $subfield_name = $subfield['name'];
                    $field_value = isset($nested2_row[$subfield_name]) ? $nested2_row[$subfield_name] : '';
                    
                    // Create unique name for nested2 repeater field
                    $input_name = "urf_data[{$group_slug}][{$parent_field_name}][{$parent_row_index}][{$field_name}][{$index_name}][{$subfield_name}]";
                    $input_id = "urf_{$group_slug}_{$parent_field_name}_{$parent_row_index}_{$field_name}_{$index_name}_{$subfield_name}";
                ?>
                    <div class="urf-subfield-container" style="background: white; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: <?php echo ($subfield_index === count($subfields) - 1) ? '0' : '15px'; ?>;">
                        <div style="display: flex; align-items: flex-start; gap: 15px; margin-bottom: 15px;">
                            <div style="flex: 0 0 150px;">
                                <label style="font-weight: 600; color: #1e293b; display: block; margin-bottom: 8px;">
                                    <?php echo esc_html($subfield['label']); ?>
                                    <?php if ($subfield['required'] ?? false): ?>
                                        <span style="color: #dc3232;">*</span>
                                    <?php endif; ?>
                                </label>
                            </div>
                            
                            <div style="flex: 1;">
                                <?php $this->render_field_input($subfield, $input_name, $input_id, $field_value, $index_name, $group_slug, $parent_row_index); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </td>
        
        <td class="urf-row-actions" style="width: 80px; min-width: 80px; max-width: 80px; vertical-align: middle;">
            <a href="#" class="urf-remove-nested2-row dashicons dashicons-trash" title="<?php _e('Remove Row', 'ultimate-repeater-field'); ?>"></a>
        </td>
    </tr>
    <?php
}

    
    public function save_post_data($post_id, $post, $update) {
    // Start logging
    $this->log('=============================================');
    $this->log('SAVE_POST_DATA STARTED for Post ID: ' . $post_id);
    $this->log('=============================================');
    
    if (
        !isset($_POST['urf_field_group']) ||
        !is_array($_POST['urf_field_group']) ||
        !isset($_POST['urf_data'])
    ) {
        $this->log('ERROR: No urf_field_group or urf_data in POST');
        return;
    }

    $this->log('POST data keys: ' . print_r(array_keys($_POST), true));
    $this->log('urf_field_group: ' . print_r($_POST['urf_field_group'], true));
    
    if (isset($_POST['urf_data'])) {
        $this->log('urf_data structure preview:');
        foreach ($_POST['urf_data'] as $group => $data) {
            $this->log('  Group: ' . $group);
            if (is_array($data)) {
                foreach ($data as $field => $value) {
                    if (is_array($value)) {
                        $this->log('    Field: ' . $field . ' (array with ' . count($value) . ' items)');
                    } else {
                        $this->log('    Field: ' . $field . ' = ' . substr(strval($value), 0, 50));
                    }
                }
            }
        }
    }

    if (
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
        !current_user_can('edit_post', $post_id)
    ) {
        $this->log('ERROR: DOING_AUTOSAVE or no permission');
        return;
    }

    // Verify nonces
    foreach ($_POST['urf_field_group'] as $group_slug) {
        $nonce_key = 'urf_nonce_' . $group_slug;
        if (
            empty($_POST[$nonce_key]) ||
            !wp_verify_nonce($_POST[$nonce_key], 'urf_save_fields_' . $group_slug)
        ) {
            $this->log('ERROR: Nonce verification failed for group: ' . $group_slug);
            return;
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'urf_fields';

    // Get field groups to identify image fields
    $group_table = $wpdb->prefix . 'urf_field_groups';
    $image_fields = [];
    
    foreach ($_POST['urf_field_group'] as $group_slug) {
        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$group_table} WHERE slug = %s",
            $group_slug
        ));
        
        if ($group) {
            $fields = maybe_unserialize($group->fields);
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if ($field['type'] === 'image') {
                        $image_fields[$field['name']] = true;
                    }
                }
            }
        }
    }

    $this->log('Processing ' . count($_POST['urf_data']) . ' field groups');
    
    // Process each field group
    foreach ($_POST['urf_data'] as $group_slug => $data) {
        $this->log('--- Processing Group: ' . $group_slug . ' ---');
        
        if (!in_array($group_slug, $_POST['urf_field_group'], true)) {
            $this->log('Skipping: Group not in allowed list');
            continue;
        }

        // Get field group configuration
        $group = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$group_table} WHERE slug = %s", $group_slug)
        );
        
        if (!$group) {
            $this->log('ERROR: Group not found in database');
            continue;
        }
        
        $fields = maybe_unserialize($group->fields);
        $repeater_fields = [];
        $nested_repeater_fields = []; // For level 2 nested repeaters
        
        if (is_array($fields)) {
            foreach ($fields as $field) {
                if ($field['type'] === 'repeater') {
                    $repeater_fields[$field['name']] = $field;
                    $this->log('Found repeater field: ' . $field['name']);
                    
                    // Check for nested repeaters inside this repeater
                    if (isset($field['subfields'])) {
                        foreach ($field['subfields'] as $subfield) {
                            if ($subfield['type'] === 'repeater') {
                                $nested_repeater_fields[$field['name'] . '_' . $subfield['name']] = $subfield;
                                $this->log('Found nested2 repeater: ' . $field['name'] . '_' . $subfield['name']);
                            }
                        }
                    }
                }
            }
        }

        // Delete existing data for this group
        $this->log('Deleting existing data for group: ' . $group_slug);
        $wpdb->delete($table_name, [
            'post_id'     => $post_id,
            'field_group' => $group_slug
        ]);

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name}
                 WHERE post_id = %d
                 AND field_group LIKE %s",
                $post_id,
                'nested_' . $group_slug . '\_%'
            )
        );

        $field_order = 0;
        
        // Process single fields (non-repeaters)
        $this->log('Processing single fields...');
        foreach ($data as $field_name => $field_value) {
            // Skip if it's a repeater field
            if (isset($repeater_fields[$field_name])) {
                $this->log('Skipping repeater field: ' . $field_name);
                continue;
            }

            // Skip empty image fields
            if (isset($image_fields[$field_name]) && empty($field_value)) {
                $this->log('Skipping empty image field: ' . $field_name);
                continue;
            }

            // Skip empty values
            if (
                $field_value === '' ||
                $field_value === null ||
                (is_array($field_value) && empty($field_value))
            ) {
                $this->log('Skipping empty field: ' . $field_name);
                continue;
            }

            $this->log('Saving field: ' . $field_name . ' = ' . (is_array($field_value) ? print_r($field_value, true) : $field_value));
            
            $wpdb->insert($table_name, [
                'post_id'          => $post_id,
                'field_group'      => $group_slug,
                'row_index'        => 0,
                'field_name'       => $field_name,
                'field_type'       => 'single',
                'field_value'      => is_array($field_value)
                    ? maybe_serialize($field_value)
                    : (string) $field_value,
                'parent_field'     => null,
                'parent_row_index' => null,
                'field_order'      => $field_order,
                'created_at'       => current_time('mysql')
            ]);
            
            $field_order++;
        }

        // Process repeater fields (level 1 and 2)
        $this->log('Processing repeater fields...');
        foreach ($repeater_fields as $repeater_name => $repeater_config) {
            $this->log('--- Processing Repeater: ' . $repeater_name . ' ---');
            
            if (!isset($data[$repeater_name]) || !is_array($data[$repeater_name])) {
                $this->log('No data found for repeater: ' . $repeater_name);
                continue;
            }

            $repeater_data = $data[$repeater_name];
            $this->log('Raw repeater data keys: ' . print_r(array_keys($repeater_data), true));
            
            // Debug: Log the structure of nested2 data
            if (isset($repeater_data[0])) {
                $this->log('First row data structure:');
                foreach ($repeater_data[0] as $field => $value) {
                    if (is_array($value)) {
                        $this->log('  Field ' . $field . ' is array with keys: ' . print_r(array_keys($value), true));
                        if (isset($value[0])) {
                            $this->log('    First nested row keys: ' . print_r(array_keys($value[0]), true));
                        }
                    }
                }
            }
            
            // Remove placeholder/empty entries
            $clean_repeater_data = [];
            $row_index = 0;
            
            $this->log('Cleaning repeater data...');
            foreach ($repeater_data as $row_key => $row) {
                $this->log('Processing row key: ' . $row_key);
                
                // Skip if not an array or if it's a placeholder
                if (!is_array($row) || 
                    $row_key === '__INDEX__' || 
                    $row_key === '__NESTED_INDEX__') {
                    $this->log('  Skipping (placeholder or not array)');
                    continue;
                }
                
                $clean_row = [];
                $has_content = false;
                
                $this->log('  Row content: ' . print_r($row, true));
                
                foreach ($row as $field_name => $field_value) {
                    $this->log('    Field: ' . $field_name);
                    
                    // Check if this is a nested2 repeater field
                    $is_nested2_repeater = false;
                    $nested2_key = $repeater_name . '_' . $field_name;
                    
                    if (isset($nested_repeater_fields[$nested2_key])) {
                        $is_nested2_repeater = true;
                        $this->log('    Detected as nested2 repeater');
                        
                        // Process nested2 repeater data
                        if (is_array($field_value) && !empty($field_value)) {
                            $clean_nested2_data = [];
                            $nested2_row_index = 0;
                            
                            $this->log('    Processing nested2 data with ' . count($field_value) . ' rows');
                            
                            foreach ($field_value as $nested2_key => $nested2_row) {
                                $this->log('      Nested2 row key: ' . $nested2_key);
                                
                                // Skip placeholders
                                if ($nested2_key === '__NESTED2_INDEX__' || !is_array($nested2_row)) {
                                    $this->log('      Skipping (placeholder or not array)');
                                    continue;
                                }
                                
                                $clean_nested2_row = [];
                                $nested2_has_content = false;
                                
                                $this->log('      Nested2 row content: ' . print_r($nested2_row, true));
                                
                                foreach ($nested2_row as $nested2_field_name => $nested2_field_value) {
                                    $this->log('        Nested2 field: ' . $nested2_field_name . ' = ' . $nested2_field_value);
                                    
                                    // Skip empty values but keep zeros and '0'
                                    if (!empty($nested2_field_value) || 
                                        $nested2_field_value === '0' || 
                                        $nested2_field_value === 0 ||
                                        $nested2_field_value === '') {
                                        
                                        $nested2_has_content = true;
                                        $clean_nested2_row[$nested2_field_name] = $nested2_field_value;
                                    } else {
                                        $this->log('        Skipping empty value');
                                    }
                                }
                                
                                if ($nested2_has_content) {
                                    $this->log('      Saving nested2 row at index: ' . $nested2_row_index);
                                    $clean_nested2_data[$nested2_row_index] = $clean_nested2_row;
                                    $nested2_row_index++;
                                } else {
                                    $this->log('      Skipping empty nested2 row');
                                }
                            }
                            
                            if (!empty($clean_nested2_data)) {
                                $has_content = true;
                                $clean_row[$field_name] = $clean_nested2_data;
                                $this->log('    Added nested2 data with ' . count($clean_nested2_data) . ' rows');
                            }
                        }
                    } else {
                        // Regular field (not nested2 repeater)
                        $this->log('    Regular field value: ' . $field_value);
                        
                        if (!empty($field_value) || 
                            $field_value === '0' || 
                            $field_value === 0) {
                            $has_content = true;
                            $clean_row[$field_name] = $field_value;
                        } else {
                            $this->log('    Skipping empty value');
                        }
                    }
                }
                
                if ($has_content) {
                    $this->log('  Row has content, adding at index: ' . $row_index);
                    $clean_repeater_data[$row_index] = $clean_row;
                    $row_index++;
                } else {
                    $this->log('  Row has no content, skipping');
                }
            }

            $this->log('Clean repeater data has ' . count($clean_repeater_data) . ' rows');
            
            // Save the cleaned repeater data
            if (!empty($clean_repeater_data)) {
                $this->log('Saving cleaned repeater data to database...');
                $nested_order = 0;
                
                foreach ($clean_repeater_data as $row_index => $row) {
                    $this->log('  Saving parent row index: ' . $row_index . ' as nested_order: ' . $nested_order);
                    
                    $field_counter = 0;
                    foreach ($row as $field_name => $field_value) {
                        $this->log('    Field: ' . $field_name);
                        
                        // Check if this is nested2 repeater data
                        $is_nested2_data = false;
                        $nested2_key = $repeater_name . '_' . $field_name;
                        
                        if (isset($nested_repeater_fields[$nested2_key])) {
                            $is_nested2_data = true;
                            $this->log('    Detected as nested2 repeater data');
                            
                            // Save nested2 repeater data
                            if (is_array($field_value)) {
                                $nested2_order = 0;
                                $this->log('    Saving ' . count($field_value) . ' nested2 rows');
                                
                                foreach ($field_value as $nested2_row_index => $nested2_row) {
                                    $this->log('      Saving nested2 row index: ' . $nested2_row_index . ' as row_index: ' . $nested2_order);
                                    
                                    $nested2_field_counter = 0;
                                    foreach ($nested2_row as $nested2_field_name => $nested2_field_value) {
                                        $this->log('        Field: ' . $nested2_field_name . ' = ' . $nested2_field_value);
                                        
                                        // Skip empty values but keep zeros and '0'
                                        if (empty($nested2_field_value) && 
                                            $nested2_field_value !== '0' && 
                                            $nested2_field_value !== 0) {
                                            $this->log('        Skipping empty value');
                                            continue;
                                        }
                                        
                                        $this->log('        Inserting into database');
                                        
                                        $wpdb->insert($table_name, [
                                            'post_id'          => $post_id,
                                            'field_group'      => 'nested_' . $group_slug . '_' . $repeater_name,
                                            'row_index'        => $nested2_order,
                                            'field_name'       => $nested2_field_name,
                                            'field_type'       => 'nested2',
                                            'field_value'      => is_array($nested2_field_value)
                                                ? maybe_serialize($nested2_field_value)
                                                : (string) $nested2_field_value,
                                            'parent_field'     => $field_name,
                                            'parent_row_index' => $nested_order,
                                            'field_order'      => $nested2_field_counter,
                                            'created_at'       => current_time('mysql')
                                        ]);
                                        
                                        $nested2_field_counter++;
                                    }
                                    $nested2_order++;
                                }
                            }
                        } else {
                            // Save regular nested field data
                            $this->log('    Regular nested field value: ' . $field_value);
                            
                            if (empty($field_value) && $field_value !== '0' && $field_value !== 0) {
                                $this->log('    Skipping empty value');
                                continue;
                            }
                            
                            $this->log('    Inserting into database');
                            
                            $wpdb->insert($table_name, [
                                'post_id'          => $post_id,
                                'field_group'      => 'nested_' . $group_slug . '_' . $repeater_name,
                                'row_index'        => 0,
                                'field_name'       => $field_name,
                                'field_type'       => 'nested',
                                'field_value'      => is_array($field_value)
                                    ? maybe_serialize($field_value)
                                    : (string) $field_value,
                                'parent_field'     => $repeater_name,
                                'parent_row_index' => $nested_order,
                                'field_order'      => $field_counter,
                                'created_at'       => current_time('mysql')
                            ]);
                        }
                        
                        $field_counter++;
                    }
                    
                    $nested_order++;
                }
                
                $this->log('Saved ' . $nested_order . ' parent rows for repeater: ' . $repeater_name);
            } else {
                $this->log('No clean repeater data to save');
            }
        }
    }
    
    $this->log('=============================================');
    $this->log('SAVE_POST_DATA COMPLETED for Post ID: ' . $post_id);
    $this->log('=============================================');
}

// Add this logging method to the class
private function log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[URF DEBUG] ' . date('Y-m-d H:i:s') . ' - ' . $message);
    }
}

// Add this method to the Ultimate_Repeater_Field class
public function debug_database_state($post_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'urf_fields';
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE post_id = %d 
         ORDER BY field_group, parent_row_index, row_index, field_order",
        $post_id
    ));
    
    $this->log('=== DATABASE STATE for Post ' . $post_id . ' ===');
    $this->log('Total rows: ' . count($results));
    
    foreach ($results as $row) {
        $log_entry = sprintf(
            "ID: %d | Group: %s | Field: %s | Type: %s | Row: %d | Parent: %s | ParentRow: %s | Value: %s",
            $row->id,
            $row->field_group,
            $row->field_name,
            $row->field_type,
            $row->row_index,
            $row->parent_field ?: 'NULL',
            $row->parent_row_index ?: 'NULL',
            substr(strval($row->field_value), 0, 50)
        );
        $this->log($log_entry);
    }
    
    $this->log('=== END DATABASE STATE ===');
}

// Call this method when viewing a post

    private function is_image_field_in_nested($group_slug, $repeater_name, $field_name) {
        global $wpdb;
        $group_table = $wpdb->prefix . 'urf_field_groups';
        
        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$group_table} WHERE slug = %s",
            $group_slug
        ));
        
        if (!$group) {
            return false;
        }
        
        $fields = maybe_unserialize($group->fields);
        if (!is_array($fields)) {
            return false;
        }
        
        foreach ($fields as $field) {
            if ($field['type'] === 'repeater' && $field['name'] === $repeater_name) {
                $subfields = $field['subfields'] ?? [];
                foreach ($subfields as $subfield) {
                    if ($subfield['name'] === $field_name && $subfield['type'] === 'image') {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    public function get_field_data($post_id, $group_slug) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'urf_fields';

    $group_table = $wpdb->prefix . 'urf_field_groups';
    $group = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $group_table WHERE slug = %s",
        $group_slug
    ));

    if (!$group || !$this->should_display_field_group($group, $post_id)) {
        return [];
    }

    $single_fields = $this->get_single_field_values($post_id, $group_slug);
    
    $repeater_fields = [];
    $fields = maybe_unserialize($group->fields);
    
    if (is_array($fields)) {
        foreach ($fields as $field) {
            if ($field['type'] === 'repeater') {
                $repeater_name = $field['name'];
                
                $nested_group = 'nested_' . $group_slug . '_' . $repeater_name;

                $nested_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_name
                     WHERE post_id = %d AND field_group = %s
                     ORDER BY parent_row_index, field_order",
                    $post_id,
                    $nested_group
                ));

                $grouped_data = [];
                foreach ($nested_rows as $row) {
                    $value = maybe_unserialize($row->field_value);
                    $n = (int) $row->parent_row_index;
                    $field_type = $row->field_type;
                    
                    // Handle nested2 repeater data differently
                    if ($field_type === 'nested2') {
                        if (!isset($grouped_data[$n])) {
                            $grouped_data[$n] = [];
                        }
                        
                        $parent_field = $row->parent_field;
                        $row_index = (int) $row->row_index;
                        
                        if (!isset($grouped_data[$n][$parent_field])) {
                            $grouped_data[$n][$parent_field] = [];
                        }
                        
                        if (!isset($grouped_data[$n][$parent_field][$row_index])) {
                            $grouped_data[$n][$parent_field][$row_index] = [];
                        }
                        
                        $grouped_data[$n][$parent_field][$row_index][$row->field_name] = $value;
                    } else {
                        $grouped_data[$n][$row->field_name] = $value;
                    }
                }
                
                // Filter out empty rows
                $filtered_data = [];
                foreach ($grouped_data as $row_index => $row_data) {
                    // Check if row has any non-empty values
                    $has_content = false;
                    foreach ($row_data as $field_name => $field_value) {
                        if (is_array($field_value)) {
                            // For nested repeaters
                            foreach ($field_value as $nested_row) {
                                if (!empty($nested_row)) {
                                    $has_content = true;
                                    break 2;
                                }
                            }
                        } else if (!empty($field_value) && $field_value !== '' && $field_value !== null) {
                            $has_content = true;
                            break;
                        }
                    }
                    
                    if ($has_content) {
                        $filtered_data[] = $row_data;
                    }
                }
                
                $repeater_fields[$repeater_name] = $filtered_data;
            }
        }
    }

    $data = array_merge($single_fields, $repeater_fields);
    
    return $data;
}
    
    public function frontend_enqueue_scripts() {
        ?>
        <style>
        .urf-frontend-output {
            margin: 40px 0;
        }
        
        .urf-frontend-field-group {
            margin-bottom: 40px;
            padding: 30px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            border-left: 5px solid #667eea;
        }
        
        .urf-frontend-field-group h3 {
            margin-top: 0;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
            color: #1e293b;
            font-size: 22px;
            font-weight: 700;
        }
        
        .urf-frontend-field {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 2px dashed #e2e8f0;
            position: relative;
        }
        
        .urf-frontend-field:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .urf-frontend-label {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 17px;
            color: #1e293b;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid #667eea;
            position: relative;
        }
        
        .urf-frontend-label::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #667eea;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .urf-frontend-value {
            font-size: 16px;
            line-height: 1.8;
            color: #475569;
            padding-left: 20px;
            position: relative;
        }
        
        .urf-frontend-value::before {
            content: '→';
            position: absolute;
            left: 0;
            color: #667eea;
            font-weight: bold;
        }
        
        .urf-frontend-value img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            margin: 15px 0;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .urf-frontend-nested-repeater {
            margin-top: 20px;
            padding: 25px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            border-left: 4px solid #4CAF50;
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .urf-frontend-nested-row {
            padding: 20px;
            margin-bottom: 15px;
            background: white;
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .urf-frontend-nested-row:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .urf-frontend-nested-field {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .urf-frontend-nested-field:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .urf-frontend-nested-field strong {
            min-width: 140px;
            color: #1e293b;
            font-weight: 600;
            font-size: 15px;
            padding: 8px 12px;
            background: #f1f5f9;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        @media (max-width: 768px) {
            .urf-frontend-field-group {
                padding: 20px;
                margin-bottom: 25px;
            }
            
            .urf-frontend-field {
                margin-bottom: 20px;
                padding-bottom: 20px;
            }
            
            .urf-frontend-label {
                font-size: 16px;
            }
            
            .urf-frontend-value {
                font-size: 15px;
                padding-left: 15px;
            }
            
            .urf-frontend-nested-field {
                flex-direction: column;
                gap: 8px;
            }
        }
        </style>
        <?php
    }
    
    public function repeater_shortcode($atts) {
        $atts = shortcode_atts(array(
            'field' => '',
            'post_id' => get_the_ID(),
            'limit' => -1,
            'layout' => 'default'
        ), $atts);
        
        if (empty($atts['field'])) {
            return '<div class="urf-error">' . __('Please specify a field name', 'ultimate-repeater-field') . '</div>';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'urf_field_groups';
        $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE slug = %s", $atts['field']));
        
        if (!$group) {
            return '<div class="urf-error">' . __('Field group not found!', 'ultimate-repeater-field') . '</div>';
        }
        
        if (!$this->should_display_field_group($group, $atts['post_id'])) {
            return '';
        }
        
        $data = $this->get_field_data($atts['post_id'], $atts['field']);
        
        if (empty($data)) {
            return '';
        }
        
        $fields = maybe_unserialize($group->fields);
        
        ob_start();
        ?>
        <div class="urf-frontend-output">
            <div class="urf-frontend-field-group">
                <h3><?php echo esc_html($group->name); ?></h3>
                
                <?php foreach ($fields as $field): 
                    $field_name = $field['name'];
                    $field_value = isset($data[$field_name]) ? $data[$field_name] : '';
                    
                    if (empty($field_value) && $field_value !== '0' && $field_value !== 0) {
                        continue;
                    }
                    
                    if ($field['type'] === 'repeater') {
                        ?>
                        <div class="urf-frontend-field urf-nested-repeater-field">
                            <div class="urf-frontend-label"><?php echo esc_html($field['label']); ?></div>
                            <div class="urf-frontend-nested-repeater">
                                <?php 
                                if (is_array($field_value)) {
                                    foreach ($field_value as $nested_index => $nested_row) {
                                        ?>
                                        <div class="urf-frontend-nested-row">
                                            <?php 
                                            $subfields = $field['subfields'] ?? array();
                                            foreach ($subfields as $subfield) {
                                                $subfield_value = isset($nested_row[$subfield['name']]) ? $nested_row[$subfield['name']] : '';
                                                if (empty($subfield_value) && $subfield_value !== '0' && $subfield_value !== 0) {
                                                    continue;
                                                }
                                                ?>
                                                <div class="urf-frontend-nested-field">
                                                    <strong><?php echo esc_html($subfield['label']); ?>:</strong>
                                                    <?php echo $this->format_field_value($subfield, $subfield_value); ?>
                                                </div>
                                                <?php
                                            }
                                            ?>
                                        </div>
                                        <?php
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="urf-frontend-field">
                            <div class="urf-frontend-label"><?php echo esc_html($field['label']); ?></div>
                            <div class="urf-frontend-value">
                                <?php echo $this->format_field_value($field, $field_value); ?>
                            </div>
                        </div>
                        <?php
                    }
                endforeach; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    public function format_field_value($field, $value) {
        $field_type = $field['type'] ?? 'text';
        
        if (is_array($value)) {
            if ($field_type === 'checkbox') {
                $options_string = $field['options'] ?? '';
                if (!empty($options_string)) {
                    $lines = explode("\n", $options_string);
                    $options_map = array();
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;
                        
                        if (strpos($line, '|') !== false) {
                            list($val, $label) = explode('|', $line, 2);
                            $options_map[trim($val)] = trim($label);
                        } else {
                            $options_map[trim($line)] = trim($line);
                        }
                    }
                    
                    $labels = array();
                    foreach ($value as $val) {
                        $val = trim($val);
                        if (isset($options_map[$val])) {
                            $labels[] = $options_map[$val];
                        } else {
                            $labels[] = $val;
                        }
                    }
                    return implode(', ', $labels);
                }
                return implode(', ', $value);
            }
            
            $value = !empty($value) ? reset($value) : '';
        }
        
        if (!in_array($field_type, ['select', 'checkbox', 'radio'])) {
            if ($field_type === 'image' && !empty($value)) {
                if (is_numeric($value)) {
                    $image_html = wp_get_attachment_image($value, 'medium');
                } else {
                    $image_html = '<img src="' . esc_url($value) . '" alt="" style="max-width: 100%; height: auto;">';
                }
                return $image_html;
            }
            return nl2br(esc_html($value));
        }
        
        $options_string = $field['options'] ?? '';
        if (empty($options_string)) {
            return nl2br(esc_html($value));
        }
        
        $options_map = [];
        $lines = explode("\n", $options_string);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (strpos($line, '|') !== false) {
                list($val, $label) = explode('|', $line, 2);
                $options_map[trim($val)] = trim($label);
            } else {
                $val = trim($line);
                $options_map[$val] = $val;
            }
        }
        
        switch ($field_type) {
            case 'select':
                $value = trim($value);
                if (isset($options_map[$value])) {
                    return $options_map[$value];
                }
                return esc_html($value);
                
            case 'radio':
                $value = trim($value);
                if (isset($options_map[$value])) {
                    return $options_map[$value];
                }
                return esc_html($value);
                
            default:
                return nl2br(esc_html($value));
        }
    }

    public function get_field_data_with_labels($post_id, $group_slug) {
        $data = $this->get_field_data($post_id, $group_slug);
        
        if (empty($data)) {
            return array();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'urf_field_groups';
        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE slug = %s", 
            $group_slug
        ));
        
        if (!$group) {
            return $data;
        }
        
        $fields = maybe_unserialize($group->fields);
        if (!$fields || !is_array($fields)) {
            return $data;
        }
        
        $field_configs = array();
        foreach ($fields as $field) {
            $field_configs[$field['name']] = $field;
        }
        
        $data_with_labels = array();
        
        foreach ($data as $field_name => $field_value) {
            if (!isset($field_configs[$field_name])) {
                $data_with_labels[$field_name] = $field_value;
                continue;
            }
            
            $field_config = $field_configs[$field_name];
            $field_type = $field_config['type'] ?? 'text';
            
            if ($field_type === 'image' && is_array($field_value) && !empty($field_value)) {
                $data_with_labels[$field_name] = reset($field_value);
            }
            elseif (in_array($field_type, ['select', 'checkbox', 'radio'])) {
                $data_with_labels[$field_name] = $this->convert_value_to_label(
                    $field_config, 
                    $field_value
                );
            }
            else {
                $data_with_labels[$field_name] = $field_value;
            }
        }
        
        return $data_with_labels;
    }

    private function convert_value_to_label($field_config, $value) {
        $field_type = $field_config['type'] ?? 'text';
        
        if (!in_array($field_type, ['select', 'checkbox', 'radio'])) {
            return $value;
        }
        
        $options_string = $field_config['options'] ?? '';
        if (empty($options_string)) {
            return $value;
        }
        
        $lines = explode("\n", $options_string);
        $options_map = array();
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (strpos($line, '|') !== false) {
                list($opt_value, $opt_label) = explode('|', $line, 2);
                $options_map[trim($opt_value)] = trim($opt_label);
            } else {
                $val = trim($line);
                $options_map[$val] = $val;
            }
        }
        
        if ($field_type === 'checkbox') {
            if (!is_array($value)) {
                $value = array($value);
            }
            
            $labels = array();
            foreach ($value as $val) {
                $val = trim($val);
                if (isset($options_map[$val])) {
                    $labels[] = $options_map[$val];
                } else {
                    $labels[] = $val;
                }
            }
            return $labels;
        } 
        else if ($field_type === 'select' && is_array($value)) {
            $labels = array();
            foreach ($value as $val) {
                $val = trim($val);
                if (isset($options_map[$val])) {
                    $labels[] = $options_map[$val];
                } else {
                    $labels[] = $val;
                }
            }
            return $labels;
        }
        else {
            $value = trim($value);
            if (isset($options_map[$value])) {
                return $options_map[$value];
            }
            return $value;
        }
    }
    
    public function ajax_get_field_group() {
        if (!check_ajax_referer('urf_ajax_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        $group_slug = sanitize_text_field($_POST['group_slug']);
        $group = $this->get_field_group_by_slug($group_slug);
        
        if ($group) {
            wp_send_json_success($group);
        } else {
            wp_send_json_error('Field group not found');
        }
    }
    
    public function ajax_get_pages() {
        if (!check_ajax_referer('urf_ajax_nonce', 'nonce', false)) {
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
    
    public function get_field_group_by_slug($slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'urf_field_groups';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE slug = %s", $slug));
    }
}

// Initialize the plugin
function urf_init() {
    Ultimate_Repeater_Field::get_instance();
}
add_action('plugins_loaded', 'urf_init');

// Helper functions for theme developers
if (!function_exists('urf_get_repeater')) {
    function urf_get_repeater($field_group, $post_id = null) {
        $plugin = Ultimate_Repeater_Field::get_instance();
        $post_id = $post_id ?: get_the_ID();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'urf_field_groups';
        $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE slug = %s", $field_group));
        
        if (!$group) {
            return array();
        }
        
        if (!$plugin->should_display_field_group($group, $post_id)) {
            return array();
        }
        
        return $plugin->get_field_data($post_id, $field_group);
    }
}

if (!function_exists('urf_display_repeater')) {
    function urf_display_repeater($field_group, $post_id = null, $limit = -1) {
        $plugin = Ultimate_Repeater_Field::get_instance();
        $atts = array(
            'field' => $field_group,
            'post_id' => $post_id ?: get_the_ID(),
            'limit' => $limit
        );
        return $plugin->repeater_shortcode($atts);
    }
}

if (!function_exists('urf_get_field_groups')) {
    function urf_get_field_groups() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'urf_field_groups';
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
    }
	
}

add_action('admin_footer-post.php', function() {
    global $post;
    if ($post) {
        $plugin = Ultimate_Repeater_Field::get_instance();
        $plugin->debug_database_state($post->ID);
    }
});
<?php
/*
Plugin Name: Ultimate Repeater Field
Description: A WordPress plugin that allows you to manage dynamic repeater fields with various field types, including nested repeaters, similar to ACF Pro.
Version: 1.5.5
Author: Supreme
Text Domain: ultimate-repeater-field
Domain Path: /languages
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('URF_VERSION', '1.5.5');
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
        
        // Save post hook - Fixed priority
        add_action('save_post', array($this, 'save_post_data'), 10, 3);
        
        // Add image size for previews
        add_action('init', array($this, 'add_image_sizes'));
    }
    
    public function add_image_sizes() {
        add_image_size('urf_thumbnail', 150, 150, true);
    }
    
    // Add this function to your main plugin class
    public function check_version() {
        $installed_version = get_option('urf_version', '0');
        
        if (version_compare($installed_version, URF_VERSION, '<')) {
            // Run database upgrade
            $this->upgrade_database();
            update_option('urf_version', URF_VERSION);
        }
    }

    // Add this upgrade function
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
            error_log('URF: Added pages column to field_groups table during upgrade');
        }
        
        // Also run the full activation to ensure all tables are up to date
        $this->activate();
    }

    // Also modify your check_and_update_database() function to be more robust
    public function check_and_update_database() {
        global $wpdb;
        
        // Check if pages column exists
        $table_name = $wpdb->prefix . 'urf_field_groups';
        
        // First, check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            // Re-create the table
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
            error_log('URF: Recreated field_groups table');
        }
        
        // Now check for pages column
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        $has_pages_column = false;
        
        foreach ($columns as $column) {
            if ($column->Field == 'pages') {
                $has_pages_column = true;
                break;
            }
        }
        
        // If pages column doesn't exist, add it
        if (!$has_pages_column) {
            $wpdb->query("ALTER TABLE $table_name ADD pages longtext NOT NULL DEFAULT '' AFTER post_types");
            error_log('URF: Added pages column to field_groups table');
        }
    }

    // Call this function after dbDelta() in your activate() function
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
        
        // Check and update database structure
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
                    <h2><?php _e('New in Version 1.5.5', 'ultimate-repeater-field'); ?></h2>
                    <ul>
                        <li><strong><?php _e('Simplified Page Selection', 'ultimate-repeater-field'); ?></strong> - <?php _e('Removed "All Pages" checkbox for clearer page selection logic', 'ultimate-repeater-field'); ?></li>
                        <li><strong><?php _e('Improved Logic', 'ultimate-repeater-field'); ?></strong> - <?php _e('Simplified page selection interface and backend logic', 'ultimate-repeater-field'); ?></li>
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
		
		// Handle delete
		if (isset($_GET['delete'])) {
			$id = intval($_GET['delete']);
			
			// First, get the group slug before deleting
			$group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
			
			if ($group) {
				// Delete from field_groups table
				$wpdb->delete($table_name, array('id' => $id));
				
				// Also delete all associated data from urf_fields table
				$wpdb->delete($fields_table, array('field_group' => $group->slug));
				
				$nested_pattern = 'nested_' . $group->slug . '_%';
				$wpdb->query($wpdb->prepare(
					"DELETE FROM $fields_table WHERE field_group LIKE %s",
					$nested_pattern
				));
				
				// Delete nested data for this group too
				$wpdb->delete($fields_table, array('field_group' => 'nested_' . $group->slug));
				
				echo '<div class="notice notice-success"><p>' . __('Field group deleted successfully!', 'ultimate-repeater-field') . '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . __('Field group not found!', 'ultimate-repeater-field') . '</p></div>';
			}
		}
        
        // Get all field groups
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
		if (!ob_get_level()) {
			ob_start();
		}
		// Handle form submission
		$success_message = '';
		$error_message = '';
		
		// Check if we just saved and need to redirect
		if (isset($_GET['saved']) && $_GET['saved'] == '1') {
			$success_message = '<div class="notice notice-success"><p>' . __('Field group saved successfully!', 'ultimate-repeater-field') . '</p></div>';
		}
		
		if (isset($_POST['save_field_group'], $_POST['urf_field_group_nonce'])) {

		error_log('URF DEBUG: Save button clicked');

		if (!wp_verify_nonce($_POST['urf_field_group_nonce'], 'urf_save_field_group')) {
			error_log('URF DEBUG: Nonce verification failed');
			$error_message = '<div class="notice notice-error"><p>Security check failed</p></div>';
		} elseif (!current_user_can('manage_options')) {
			error_log('URF DEBUG: Permission denied');
			$error_message = '<div class="notice notice-error"><p>Permission denied</p></div>';
		} else {

			error_log('URF DEBUG: Calling save_field_group()');

			$result = $this->save_field_group();

			error_log('URF DEBUG: save_field_group() result → ' . print_r($result, true));

			if ($result === true) {

		error_log('URF DEBUG: Save successful, using JS redirect');

		$redirect_url = !empty($_POST['group_id'])
			? admin_url('admin.php?page=urf-add-field-group&edit=' . intval($_POST['group_id']) . '&saved=1')
			: admin_url('admin.php?page=urf-field-groups&saved=1');

		echo '<script type="text/javascript">
			window.location.href = ' . json_encode($redirect_url) . ';
		</script>';

		exit;
	}
	 else {
				error_log('URF DEBUG: Save failed with error');
				$error_message = '<div class="notice notice-error"><p>' . esc_html($result) . '</p></div>';
			}
		}
	}

		
		$group_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
		$group = $group_id ? $this->get_field_group($group_id) : null;
		
		// Get selected types for display
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
									// Don't preselect any individual post types when "all" is selected
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
								<p class="description"><em><?php _e('Note: Pages selection is only available when "page" post type is selected above.', 'ultimate-repeater-field'); ?></em></p>
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
			
			// Get initial post types data
			const allPostTypesCheckbox = document.querySelector('.all-post-types');
			const postTypesContainer = document.querySelector('.post-types-container');
			
			// Function to check if "all" post types are selected
			function checkAllPostTypesState() {
				const postTypeCheckboxes = document.querySelectorAll('.post-type-checkbox');
				let hasAllSelected = false;
				
				// Check if "all" was previously selected
				<?php if ($group && is_array($selected_types) && in_array('all', $selected_types)): ?>
					hasAllSelected = true;
				<?php endif; ?>
				
				return hasAllSelected;
			}
			
			function toggleDisplayOptions() {
				const selectedValue = document.querySelector('input[name="display_logic"]:checked').value;
				if (selectedValue === 'post_types') {
					displayOptions.forEach(opt => opt.style.display = 'table-row');
					
					// Check if all post types are selected
					const allSelected = checkAllPostTypesState();
					if (allSelected && allPostTypesCheckbox) {
						allPostTypesCheckbox.checked = true;
						postTypesContainer.style.display = 'none';
						pagesSection.style.display = 'none';
					} else {
						// Check if pages post type is selected
						checkPagesPostType();
					}
				} else {
					displayOptions.forEach(opt => opt.style.display = 'none');
				}
			}
			
			displayLogicRadios.forEach(radio => {
				radio.addEventListener('change', toggleDisplayOptions);
			});
			
			// All post types checkbox
			if (allPostTypesCheckbox) {
				allPostTypesCheckbox.addEventListener('change', function() {
					if (this.checked) {
						postTypesContainer.style.display = 'none';
						// Uncheck all post type checkboxes
						document.querySelectorAll('.post-type-checkbox').forEach(cb => {
							cb.checked = false;
						});
						// Hide pages section since all post types are selected
						pagesSection.style.display = 'none';
					} else {
						postTypesContainer.style.display = 'block';
						// Check if pages post type is selected
						checkPagesPostType();
					}
				});
				
				// Set initial state based on database value
				<?php 
				if ($group) {
					$post_types = maybe_unserialize($group->post_types);
					if (is_array($post_types) && in_array('all', $post_types)) {
						echo 'allPostTypesCheckbox.checked = true;';
						echo 'postTypesContainer.style.display = "none";';
						echo 'pagesSection.style.display = "none";';
					}
				}
				?>
			}
			
			// Check if pages post type is selected
			function checkPagesPostType() {
				const pageCheckbox = document.querySelector('.post-type-checkbox[data-post-type="page"]');
				
				if (pageCheckbox && pageCheckbox.checked) {
					// Show pages section
					pagesSection.style.display = 'table-row';
				} else {
					// Hide pages section
					pagesSection.style.display = 'none';
					// Clear selected pages if page post type is unchecked
					if (!pageCheckbox || !pageCheckbox.checked) {
						document.querySelector('#selected-pages-container').innerHTML = '';
					}
				}
			}
			
			// Post type checkboxes change event
			document.querySelectorAll('.post-type-checkbox').forEach(checkbox => {
				checkbox.addEventListener('change', function() {
					checkPagesPostType();
					
					// If any checkbox is checked, uncheck "All Post Types"
					if (allPostTypesCheckbox && allPostTypesCheckbox.checked) {
						allPostTypesCheckbox.checked = false;
						postTypesContainer.style.display = 'block';
					}
				});
			});
			
			// Initialize display options
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
			
			// Load pages via AJAX
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
								
								// Check if already selected
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
					console.error('Error:', error);
					pagesList.innerHTML = '<p><?php _e('Error loading pages.', 'ultimate-repeater-field'); ?></p>';
				});
			}
			
			// Open modal
			if (selectPagesBtn) {
				selectPagesBtn.addEventListener('click', function() {
					pagesModal.style.display = 'block';
					loadPages();
				});
			}
			
			// Close modal
			if (closeModalBtn) {
				closeModalBtn.addEventListener('click', function() {
					pagesModal.style.display = 'none';
				});
			}
			
			// Close modal on outside click
			window.addEventListener('click', function(event) {
				if (event.target === pagesModal) {
					pagesModal.style.display = 'none';
				}
			});
			
			// Search pages
			if (pageSearch) {
				pageSearch.addEventListener('input', function() {
					loadPages(this.value);
				});
			}
			
			// Add selected pages
			if (addSelectedPagesBtn) {
				addSelectedPagesBtn.addEventListener('click', function() {
					const selectedCheckboxes = pagesList.querySelectorAll('.page-checkbox:checked');
					selectedCheckboxes.forEach(checkbox => {
						const pageId = checkbox.value;
						
						// Check if already selected
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
					
					// Uncheck all checkboxes in modal
					pagesList.querySelectorAll('.page-checkbox').forEach(cb => {
						cb.checked = false;
					});
					
					pagesModal.style.display = 'none';
				});
			}
			
			// Clear all pages
			if (clearPagesBtn) {
				clearPagesBtn.addEventListener('click', function() {
					if (confirm('<?php _e('Are you sure you want to clear all selected pages?', 'ultimate-repeater-field'); ?>')) {
						selectedPagesContainer.innerHTML = '';
					}
				});
			}
			
			// Remove single page (event delegation)
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
				
				// Update field indices
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
			
			// Update field name based on label
			document.addEventListener('keyup', function(e) {
				if (e.target.classList.contains('urf-field-label')) {
					const row = e.target.closest('.urf-field-row');
					const label = e.target.value;
					const name = label.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
					const nameInput = row.querySelector('.urf-field-name');
					if (nameInput && !nameInput.value) {
						nameInput.value = name;
					}
				}
			});
			
			// Show/hide options based on field type
			document.addEventListener('change', function(e) {
				if (e.target.classList.contains('urf-field-type')) {
					const row = e.target.closest('.urf-field-row');
					const type = e.target.value;
					const optionsDiv = row.querySelector('.urf-field-options');
					
					if (['select', 'checkbox', 'radio'].includes(type)) {
						optionsDiv.style.display = 'block';
						const currentIndex = Array.from(row.parentNode.children).indexOf(row);
						optionsDiv.innerHTML = `
							<label><?php _e('Options (one per line)', 'ultimate-repeater-field'); ?></label>
							<textarea name="fields[${currentIndex}][options]" class="widefat" rows="3" placeholder="<?php _e('value|Label', 'ultimate-repeater-field'); ?>"></textarea>
							<p class="description"><?php _e('Enter options line by line in format: value|Label', 'ultimate-repeater-field'); ?></p>
						`;
					} else if (type === 'repeater') {
						optionsDiv.style.display = 'block';
						const currentIndex = Array.from(row.parentNode.children).indexOf(row);
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
					
					if (['select', 'checkbox', 'radio'].includes(type)) {
						optionsDiv.style.display = 'block';
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
					
					// Get current subfield count
					const subfieldsCount = container.querySelectorAll('.urf-subfield-row').length;
					
					// Create new subfield row
					const newSubfield = createSubfieldRow(parentIndex, subfieldsCount);
					container.appendChild(newSubfield);
				}
			});
			
			// Initialize field type changes
			document.querySelectorAll('.urf-field-type').forEach(function(select) {
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
							<input type="text" name="fields[${index}][label]" class="urf-field-label widefat" required>
						</div>
						
						<div>
							<label><?php _e('Field Name', 'ultimate-repeater-field'); ?> *</label>
							<input type="text" name="fields[${index}][name]" class="urf-field-name widefat" required>
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
							</select>
						</div>
						
						<div>
							<label><?php _e('Field Label', 'ultimate-repeater-field'); ?> *</label>
							<input type="text" name="fields[${parentIndex}][subfields][${subIndex}][label]" class="widefat" required>
						</div>
						
						<div>
							<label><?php _e('Field Name', 'ultimate-repeater-field'); ?> *</label>
							<input type="text" name="fields[${parentIndex}][subfields][${subIndex}][name]" class="widefat" required>
							<p class="description"><?php _e('Lowercase, underscores, no spaces', 'ultimate-repeater-field'); ?></p>
						</div>
					</div>
					
					<div class="urf-subfield-options" style="display: none; margin-bottom: 10px;">
						<label><?php _e('Options', 'ultimate-repeater-field'); ?></label>
						<textarea name="fields[${parentIndex}][subfields][${subIndex}][options]" class="widefat" rows="2"></textarea>
						<p class="description"><?php _e('Enter options line by line in format: value|Label', 'ultimate-repeater-field'); ?></p>
					</div>
					
					<div>
						<label>
							<input type="checkbox" name="fields[${parentIndex}][subfields][${subIndex}][required]" value="1">
							<?php _e('Required Field', 'ultimate-repeater-field'); ?>
						</label>
					</div>
				`;
				return div;
			}
			
			function updateFieldIndices() {
				const rows = document.querySelectorAll('.urf-field-row');
				rows.forEach(function(row, index) {
					row.querySelector('.field-index').textContent = index + 1;
					
					// Update all input names
					const inputs = row.querySelectorAll('[name]');
					inputs.forEach(function(input) {
						const oldName = input.name;
						const match = oldName.match(/fields\[(\d+)\]/);
						if (match) {
							const newName = oldName.replace(/fields\[\d+\]/g, `fields[${index}]`);
							input.name = newName;
						}
					});
					
					// Update subfields container data attribute
					const subfieldsContainer = row.querySelector('.urf-subfields-container');
					if (subfieldsContainer) {
						subfieldsContainer.dataset.parentIndex = index;
					}
					
					// Update add subfield button
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
                    <label><?php _e('Options', 'ultimate-repeater-field'); ?></label>
                    <textarea name="fields[<?php echo $index; ?>][options]" class="widefat" rows="3"><?php echo esc_textarea($field['options'] ?? ''); ?></textarea>
                    <p class="description"><?php _e('Enter options line by line in format: value|Label', 'ultimate-repeater-field'); ?></p>
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
            
            <div class="urf-subfield-options" style="display: <?php echo in_array($subfield['type'] ?? '', ['select', 'checkbox', 'radio']) ? 'block' : 'none'; ?>; margin-bottom: 10px;">
                <label><?php _e('Options', 'ultimate-repeater-field'); ?></label>
                <textarea name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][options]" class="widefat" rows="2"><?php echo esc_textarea($subfield['options'] ?? ''); ?></textarea>
                <p class="description"><?php _e('Enter options line by line in format: value|Label', 'ultimate-repeater-field'); ?></p>
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
    
    public function save_field_group() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'urf_field_groups';
		
		// Ensure database is up to date before saving
		$this->check_and_update_database();
		
		$group_id = intval($_POST['group_id'] ?? 0);
		$name = sanitize_text_field($_POST['group_name'] ?? '');
		$slug = sanitize_title($_POST['group_slug'] ?? '');
		
		// Get display logic
		$display_logic = sanitize_text_field($_POST['display_logic'] ?? 'all');
		
		// Initialize arrays
		$post_types = array();
		$pages = array();
		
		if ($display_logic === 'post_types') {
			// Get post types
			if (isset($_POST['all_post_types']) && $_POST['all_post_types'] == '1') {
				$post_types = array('all');
			} else {
				$post_types = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : array();
			}
			
			// Get pages
			$pages = isset($_POST['pages']) ? array_map('intval', $_POST['pages']) : array();
			
			// Store settings
			$settings = array();
		} else {
			// Show on all posts/pages
			$post_types = array('all');
			$pages = array();
			$settings = array();
		}
		
		// Validate required fields
		if (empty($name)) {
			return __('Group name is required!', 'ultimate-repeater-field');
		}
		
		if (empty($slug)) {
			return __('Group slug is required!', 'ultimate-repeater-field');
		}
		
		// Check if table exists
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
		if (!$table_exists) {
			$this->activate();
			$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
			if (!$table_exists) {
				return __('Database table does not exist. Please deactivate and reactivate the plugin.', 'ultimate-repeater-field');
			}
		}
		
		// Check if slug already exists
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
		
		// Process fields
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
					
					// Handle repeater subfields
					if ($field['type'] === 'repeater' && isset($field['subfields']) && is_array($field['subfields'])) {
						$subfields = array();
						foreach ($field['subfields'] as $subfield) {
							if (!empty($subfield['label']) && !empty($subfield['name'])) {
								$subfields[] = array(
									'type' => sanitize_text_field($subfield['type']),
									'label' => sanitize_text_field($subfield['label']),
									'name' => sanitize_text_field($subfield['name']),
									'options' => isset($subfield['options']) ? sanitize_textarea_field($subfield['options']) : '',
									'required' => isset($subfield['required']) ? true : false
								);
							}
						}
						$field_data['subfields'] = $subfields;
					}
					
					$fields[] = $field_data;
				}
			}
		}
		
		// Make sure we have fields
		if (empty($fields)) {
			return __('Please add at least one field!', 'ultimate-repeater-field');
		}
		
		// Check for duplicate field names
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
		
		// Check which columns exist to build the correct format
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
		
		// Remove created_at for updates
		if ($group_id) {
			unset($data['created_at']);
		}
		
		if ($group_id) {
			$result = $wpdb->update($table_name, $data, array('id' => $group_id), $format, array('%d'));
			if ($result === false) {
				error_log('URF Save Error: ' . $wpdb->last_error);
				return __('Error updating field group!', 'ultimate-repeater-field');
			}
		} else {
			$result = $wpdb->insert($table_name, $data, $format);
			if ($result === false) {
				error_log('URF Insert Error: ' . $wpdb->last_error);
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
        // Only enqueue on post edit pages and our plugin pages
        if (!in_array($hook, array('post.php', 'post-new.php')) && strpos($hook, 'ultimate-repeater') === false) {
            return;
        }
        
        // Load jQuery (WordPress already has it)
        wp_enqueue_script('jquery');
        
        // Load jQuery UI - WordPress bundles these together
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-datepicker');
        
        // Load WordPress media uploader
        wp_enqueue_media();
        
        // Color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // WYSIWYG editor
        wp_enqueue_editor();
        
        // Add jQuery UI styles
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        // Add custom CSS and JS inline
        add_action('admin_print_footer_scripts', array($this, 'add_inline_scripts'), 99);
    }
    
    public function add_inline_scripts() {
        // Only add scripts on post edit pages
        $screen = get_current_screen();
        if (!in_array($screen->base, array('post', 'page'))) {
            return;
        }
        ?>
        <style>
    /* ==================== */
/* URF CORE STYLES - COMPLETE REDESIGN */
/* ==================== */

.urf-repeater-wrapper {
    margin: 25px 0;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e0e6ed;
}

.urf-repeater-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 0;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
}

.urf-repeater-table th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 18px 20px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    position: relative;
}

.urf-repeater-table th:not(:first-child)::before {
    content: '';
    position: absolute;
    left: 0;
    top: 25%;
    height: 50%;
    width: 1px;
    background: rgba(255, 255, 255, 0.3);
}

.urf-repeater-table td {
    padding: 25px 0px;
    vertical-align: top;
    background: #fff;
    border-bottom: 1px solid #f0f4f8;
    transition: background-color 0.3s ease;
}

.urf-repeater-table tr:hover td {
    background: #f8fafc;
}

.urf-repeater-table tr:last-child td {
    border-bottom: none;
}

.urf-row-handle {
    width: 60px;
    text-align: center;
    cursor: move;
    background: linear-gradient(135deg, #f6f8ff 0%, #f0f4ff 100%);
    vertical-align: middle;
    border-right: 1px solid #f0f4f8;
}

.urf-row-handle .dashicons {
    font-size: 20px;
    color: #667eea;
    opacity: 0.7;
    transition: all 0.3s ease;
}

.urf-row-handle:hover .dashicons {
    opacity: 1;
    transform: scale(1.1);
}

.row-index {
    display: inline-block;
    min-width: 28px;
    height: 28px;
    line-height: 28px;
    background: #667eea;
    color: white;
    border-radius: 50%;
    font-weight: 600;
    font-size: 12px;
    margin-top: 5px;
    box-shadow: 0 2px 5px rgba(102, 126, 234, 0.3);
}

.urf-row-actions {
    width: 80px;
    text-align: center;
    background: linear-gradient(135deg, #f6f8ff 0%, #f0f4ff 100%);
    vertical-align: middle;
    border-left: 1px solid #f0f4f8;
}

.urf-remove-row {
    color: #ff4757;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(255, 71, 87, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
}

.urf-remove-row:hover {
    background: #ff4757;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
}

.urf-remove-row:active {
    transform: translateY(0);
}

.urf-add-row, .urf-add-sub-row, .urf-add-nested-row {
    margin: 20px 0 10px;
    cursor: pointer;
    padding: 12px 24px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    transition: all 0.3s ease !important;
    border: none !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}

.urf-add-row {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3) !important;
}

.urf-add-row:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4) !important;
}

.urf-add-row:active {
    transform: translateY(0) !important;
}

.urf-add-nested-row {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%) !important;
    color: white !important;
    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3) !important;
}

.urf-add-nested-row:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4) !important;
}

/* ==================== */
/* VERTICAL FIELDS LAYOUT - ENHANCED */
/* ==================== */

.urf-vertical-fields {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 12px;
    padding: 30px;
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
    padding: 25px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    margin-bottom: 25px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.urf-vertical-field:last-child {
    margin-bottom: 0;
    border-bottom: 1px solid #e2e8f0;
}

.urf-vertical-field:hover {
    transform: translateY(-3px);
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
    margin-bottom: 15px;
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

.urf-vertical-field input[type="text"]::placeholder,
.urf-vertical-field textarea::placeholder {
    color: #94a3b8;
}

.urf-vertical-field textarea {
    min-height: 120px;
    resize: vertical;
    line-height: 1.6;
}

.urf-vertical-field select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23667eea' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    background-size: 16px;
    padding-right: 45px;
    cursor: pointer;
}

.urf-vertical-field select:hover {
    border-color: #94a3b8;
}

.urf-vertical-field select option {
    padding: 12px;
    background: white;
    color: #334155;
}

/* ==================== */
/* FILE UPLOAD STYLES */
/* ==================== */

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

.urf-upload-button::before {
    content: '📁';
    font-size: 16px;
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

.urf-file-name {
    font-size: 12px;
    color: #64748b;
    margin-top: 8px;
    text-align: center;
    word-break: break-all;
    padding: 0 5px;
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

.urf-remove-file:hover {
    background: #ff3742;
    transform: scale(1.1);
}

.urf-remove-file::before {
    content: '×';
    font-size: 16px;
    font-weight: bold;
}

/* ==================== */
/* CHECKBOX & RADIO STYLES */
/* ==================== */

.urf-checkbox-group,
.urf-radio-group {
    margin-top: 10px;
}

.urf-checkbox-group label,
.urf-radio-group label {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    margin-bottom: 8px;
    background: #f8fafc;
    border-radius: 8px;
    border: 2px solid transparent;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.urf-checkbox-group label:hover,
.urf-radio-group label:hover {
    background: #f1f5f9;
    border-color: #e2e8f0;
    transform: translateX(5px);
}

.urf-checkbox-group label::before,
.urf-radio-group label::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #667eea;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.urf-checkbox-group label:hover::before,
.urf-radio-group label:hover::before {
    opacity: 1;
}

.urf-checkbox-group input[type="checkbox"],
.urf-radio-group input[type="radio"] {
    margin-right: 12px;
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: #667eea;
    position: relative;
}

.urf-checkbox-group input[type="checkbox"]:checked,
.urf-radio-group input[type="radio"]:checked {
    animation: checkboxPop 0.3s ease;
}

@keyframes checkboxPop {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

/* ==================== */
/* COLOR PICKER STYLES */
/* ==================== */

.urf-colorpicker {
    padding: 10px !important;
    border: 2px solid #e2e8f0 !important;
    border-radius: 8px !important;
    width: 80px !important;
    height: 45px !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
}

.urf-colorpicker:hover {
    border-color: #94a3b8 !important;
    transform: scale(1.05);
}

.urf-colorpicker:focus {
    outline: none;
    border-color: #667eea !important;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
}

/* ==================== */
/* DATE PICKER STYLES */
/* ==================== */

.urf-datepicker {
    background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23667eea' viewBox='0 0 16 16'%3E%3Cpath d='M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z'/%3E%3C/svg%3E") no-repeat right 16px center !important;
    background-size: 18px !important;
    padding-right: 50px !important;
    cursor: pointer !important;
}

.ui-datepicker {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.ui-datepicker-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px;
    margin-bottom: 15px;
}

.ui-datepicker-calendar td a {
    text-align: center;
    padding: 8px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.ui-datepicker-calendar td a:hover {
    background: #667eea;
    color: white;
}

.ui-datepicker-calendar .ui-state-active {
    background: #667eea;
    color: white;
}

/* ==================== */
/* NESTED REPEATER STYLES */
/* ==================== */

.urf-nested-repeater {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 20px;
    border-radius: 12px;
    margin-top: 15px;
    border: 1px solid #e2e8f0;
    position: relative;
    overflow: hidden;
}

.urf-nested-repeater::before {
    content: '🔄';
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 20px;
    opacity: 0.1;
    z-index: 0;
}

.urf-nested-table {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 3px 15px rgba(0, 0, 0, 0.05);
}

.urf-nested-table th {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%) !important;
    color: white;
    padding: 16px 20px;
}

.urf-nested-table td {
    padding: 20px;
    background: white;
    border-bottom: 1px solid #f1f5f9;
}

.urf-nested-table tr:last-child td {
    border-bottom: none;
}

/* ==================== */
/* ANIMATIONS & TRANSITIONS */
/* ==================== */

.urf-sortable-placeholder {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    border: 2px dashed #94a3b8;
    height: 60px;
    border-radius: 10px;
    margin: 5px 0;
}

.urf-clone-row,
.urf-clone-nested-row {
    display: none !important;
}

/* ==================== */
/* FIELD GROUP TITLE */
/* ==================== */

.urf-field-group {
    position: relative;
    margin-bottom: 40px;
}



/* ==================== */
/* RESPONSIVE DESIGN */
/* ==================== */

@media (max-width: 768px) {
    .urf-repeater-table th,
    .urf-repeater-table td {
        padding: 15px;
    }
    
    .urf-vertical-fields {
        padding: 20px;
    }
    
    .urf-vertical-field {
        padding: 20px;
    }
    
    .urf-file-item {
        max-width: 150px;
    }
    
    .urf-add-row,
    .urf-add-nested-row {
        padding: 10px 20px !important;
        font-size: 13px !important;
    }
}

@media (max-width: 480px) {
    .urf-repeater-wrapper {
        margin: 15px -10px;
        border-radius: 0;
    }
    
    .urf-vertical-field {
        padding: 15px;
    }
    
    .urf-file-item {
        max-width: 130px;
    }
}
</style>

        <script>
        // Wait for everything to load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('URF: DOM loaded, starting initialization');
            
            // Use jQuery in noConflict mode
            (function($) {
                'use strict';
                
                // Simple debug function
                function urfLog(msg) {
                    console.log('URF: ' + msg);
                }
                
                // Check if jQuery UI is loaded
                function isJQueryUILoaded() {
                    return typeof $.ui !== 'undefined' && 
                           typeof $.ui.sortable !== 'undefined' && 
                           typeof $.ui.datepicker !== 'undefined';
                }
                
                // Wait for jQuery UI to load
                function waitForJQueryUI(callback) {
                    var attempts = 0;
                    var maxAttempts = 10;
                    
                    function check() {
                        attempts++;
                        if (isJQueryUILoaded()) {
                            urfLog('jQuery UI loaded successfully');
                            callback(true);
                        } else if (attempts < maxAttempts) {
                            urfLog('Waiting for jQuery UI... attempt ' + attempts);
                            setTimeout(check, 500);
                        } else {
                            urfLog('ERROR: jQuery UI not loaded after ' + maxAttempts + ' attempts');
                            callback(false);
                        }
                    }
                    
                    check();
                }
                
                // Initialize datepicker safely
                function initDatepicker() {
                    if (typeof $.fn.datepicker === 'function') {
                        $('.urf-datepicker').datepicker({
                            dateFormat: 'yy-mm-dd',
                            changeMonth: true,
                            changeYear: true,
                            yearRange: '-100:+10'
                        });
                        urfLog('Datepickers initialized');
                    } else {
                        urfLog('WARNING: Datepicker not available - using text input');
                    }
                }
                
                // Initialize colorpicker
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
                
                // Initialize sortable
                function initSortable() {
                    if (typeof $.fn.sortable === 'function') {
                        $('.urf-repeater-table tbody').sortable({
                            handle: '.urf-row-handle',
                            axis: 'y',
                            placeholder: 'urf-sortable-placeholder',
                            forcePlaceholderSize: true,
                            start: function(e, ui) {
                                ui.placeholder.height(ui.item.height());
                            },
                            update: function() {
                                $(this).find('tr:not(.urf-clone-row)').each(function(index) {
                                    $(this).find('.row-index').text(index + 1);
                                });
                            }
                        });
                        urfLog('Sortable initialized');
                    } else {
                        urfLog('WARNING: Sortable not available - rows not draggable');
                    }
                }
                
                // Initialize WYSIWYG
                function initWysiwyg() {
                    $('.urf-wysiwyg').each(function() {
                        var textareaId = $(this).attr('id');
                        if (textareaId && !tinyMCE.get(textareaId)) {
                            tinymce.init({
                                selector: '#' + textareaId,
                                menubar: false,
                                toolbar: 'bold italic underline | bullist numlist | link unlink',
                                plugins: 'link lists',
                                height: 200,
                                setup: function(editor) {
                                    editor.on('change', function() {
                                        editor.save();
                                    });
                                }
                            });
                        }
                    });
                    urfLog('WYSIWYG editors initialized');
                }
                
                // Initialize IMAGE upload (only for images)
                // Initialize IMAGE upload (only for images) - FIXED VERSION
				function initImageUpload() {
					// Handle file removal with event delegation
					$(document).on('click', '.urf-remove-file', function(e) {
						e.preventDefault();
						e.stopPropagation();
						
						var $removeBtn = $(this);
						var $fileItem = $removeBtn.closest('.urf-file-item');
						var $container = $fileItem.closest('.urf-file-upload-container');
						var $input = $container.find('.urf-file-input');
						
						console.log('URF: Removing file from input:', $input.attr('name'));
						
						// Clear the input value
						$input.val('');
						
						// Remove the file preview
						$fileItem.remove();
						
						// If no files left, hide the preview container
						if ($container.find('.urf-file-item').length === 0) {
							$container.find('.urf-file-preview').empty();
						}
					});
					
					// Handle image upload button click
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
							
							// Clear existing previews if single selection
							if (maxFiles === 1) {
								$preview.empty();
								$input.val('');
							}
							
							$.each(attachments, function(i, attachment) {
								if (attachment.type === 'image') {
									// Create the file item HTML
									var fileHtml = '<div class="urf-file-item" data-attachment-id="' + attachment.id + '">' +
										'<img src="' + attachment.url + '" class="urf-image-preview">' +
										'<div class="urf-file-name">' + attachment.filename + '</div>' +
										'<button type="button" class="urf-remove-file dashicons dashicons-no-alt" title="<?php _e('Remove', 'ultimate-repeater-field'); ?>"></button>' +
										'</div>';
									
									$preview.append(fileHtml);
									
									// Update the input value
									if (maxFiles === 1) {
										$input.val(attachment.id);
									} else {
										// For multiple files, store as array
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
					
					urfLog('Image upload initialized with event delegation');
				}

                // Helper function to initialize image upload for a specific row
                function initImageUploadForRow($row) {
                    $row.find('.urf-upload-button').off('click').on('click', function(e) {
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
                            $.each(attachments, function(i, attachment) {
                                var fileHtml = '';
                                if (attachment.type === 'image') {
                                    fileHtml = '<div class="urf-file-item">' +
                                        '<img src="' + attachment.url + '" class="urf-image-preview">' +
                                        '<span class="urf-remove-file dashicons dashicons-no-alt"></span>' +
                                        '<input type="hidden" name="' + $input.attr('name') + '" value="' + attachment.id + '">' +
                                        '</div>';
                                }
                                $preview.append(fileHtml);
                            });
                            frame.close();
                        });
                        
                        frame.on('close', function() {
                            frame.detach();
                        });
                        
                        frame.open();
                    });
                    
                    $row.find('.urf-remove-file').off('click').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        $(this).closest('.urf-file-item').remove();
                    });
                }
                
                // **MAIN ADD ROW FUNCTION**
                function initAddRow() {
    urfLog('Initializing Add Row functionality');
    
    // Remove any existing handlers first to prevent duplicates
    $(document).off('click.urf-add-row').on('click.urf-add-row', '.urf-add-row', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('URF: Add Row button clicked');
        
        var $button = $(this);
        var groupSlug = $button.data('group');
        
        // Find the repeater wrapper
        var $wrapper = $button.closest('.urf-repeater-wrapper');
        if ($wrapper.length === 0) {
            console.error('URF: Repeater wrapper not found');
            return false;
        }
        
        // Find the table body
        var $tbody = $wrapper.find('tbody');
        if ($tbody.length === 0) {
            console.error('URF: Table body not found');
            return false;
        }
        
        // Get or create clone row
        var $cloneRow = $tbody.find('.urf-clone-row');
        
        if ($cloneRow.length === 0) {
            // Create clone from first row
            var $firstRow = $tbody.find('tr.urf-main-row').first();
            if ($firstRow.length === 0) {
                console.error('URF: No rows to clone');
                return false;
            }
            
            $cloneRow = $firstRow.clone();
            $cloneRow.addClass('urf-clone-row');
            $cloneRow.removeClass('urf-main-row');
            $cloneRow.removeAttr('data-main-row');
            $cloneRow.hide();
            
            // Clear values
            $cloneRow.find('input, textarea').val('');
            $cloneRow.find('select').prop('selectedIndex', 0);
            $cloneRow.find(':checked').prop('checked', false);
            $cloneRow.find('.urf-file-preview').empty();
            
            // Replace indices with __INDEX__
            $cloneRow.find('[name]').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/\[(\d+)\]/g, '[__INDEX__]');
                    $(this).attr('name', name);
                }
            });
            
            $cloneRow.find('[id]').each(function() {
                var id = $(this).attr('id');
                if (id) {
                    id = id.replace(/_(\d+)_/g, '_' + '__INDEX__' + '_');
                    $(this).attr('id', id);
                }
            });
            
            $tbody.append($cloneRow);
            console.log('URF: Created clone row');
        }
        
        // Count ONLY main rows (excluding clone row and any other rows)
        var rowCount = $tbody.find('tr.urf-main-row[data-main-row="true"]').length;
        console.log('URF: Current MAIN row count: ' + rowCount);
        
        // Clone the clone row
        var $newRow = $cloneRow.clone();
        $newRow.removeClass('urf-clone-row').addClass('urf-main-row').attr('data-main-row', 'true').show();
        
        // Update indices
        $newRow.find('[name]').each(function() {
            var name = $(this).attr('name');
            if (name) {
                // Regular field indices update
                name = name.replace(/\[__INDEX__\]/g, '[' + rowCount + ']');
                $(this).attr('name', name);
            }
        });
        
        // Update IDs
        $newRow.find('[id]').each(function() {
            var id = $(this).attr('id');
            if (id) {
                id = id.replace(/__INDEX__/g, rowCount);
                // Also replace in nested fields
                id = id.replace(/__NESTED_INDEX__/g, rowCount);
                $(this).attr('id', id);
            }
        });
        
        // Update row number
        $newRow.find('.row-index').text(rowCount + 1);
        $newRow.attr('data-row-index', rowCount);
        
        // Insert before clone row
        $cloneRow.before($newRow);
        
        // Initialize plugins for new row
        setTimeout(function() {
            // Datepicker
            $newRow.find('.urf-datepicker').each(function() {
                if (typeof $.fn.datepicker === 'function' && !$(this).hasClass('hasDatepicker')) {
                    $(this).datepicker({
                        dateFormat: 'yy-mm-dd',
                        changeMonth: true,
                        changeYear: true,
                        yearRange: '-100:+10'
                    }).addClass('hasDatepicker');
                }
            });
            
            // Colorpicker
            $newRow.find('.urf-colorpicker').each(function() {
                if (typeof $.fn.wpColorPicker === 'function' && !$(this).hasClass('wp-color-picker')) {
                    $(this).wpColorPicker().addClass('wp-color-picker');
                }
            });
            
            // Initialize image upload for new row
            
        }, 100);
        
        console.log('URF: Row added successfully! Index: ' + rowCount);
        return false;
    });
    
    // **FIXED: Remove row with COMPLETE reindexing**
    $(document)
        .off('click.urf-remove-main-row')
        .on('click.urf-remove-main-row', '.urf-remove-row', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if ($(this).data('processing')) return;
            $(this).data('processing', true);

            if (!confirm('Are you sure you want to remove this row?')) {
                $(this).data('processing', false);
                return;
            }

            var $row = $(this).closest('tr');
            var $table = $row.closest('table');
            var $tbody = $table.find('tbody');
            var $wrapper = $table.closest('.urf-repeater-wrapper');
            var groupSlug = $wrapper.find('.urf-add-row').data('group');

            // Get the row index
            var removedIndex = parseInt($row.attr('data-row-index')) || $row.index();
            console.log('URF: Removing row with index:', removedIndex);

            // **CRITICAL: Add hidden input to mark this row for deletion**
            var $deleteField = $('<input>').attr({
                type: 'hidden',
                name: 'urf_deleted_rows[' + groupSlug + '][]',
                value: removedIndex,
                'class': 'urf-delete-marker'
            });
            $wrapper.append($deleteField);
            
            // Remove row visually
            $row.remove();

            // **FIX: Reindex ALL remaining rows COMPLETELY**
            var allRows = $tbody.find('tr.urf-main-row[data-main-row="true"]');
            console.log('URF: Total rows after removal:', allRows.length);
            
            // Reset to 0 and reindex sequentially
            var newIndex = 0;
            allRows.each(function() {
                var $currentRow = $(this);
                var currentNameIndex = parseInt($currentRow.attr('data-row-index'));
                
                // Only reindex if needed (if current index > removedIndex or sequential order is broken)
                if (currentNameIndex !== newIndex) {
                    console.log('URF: Reindexing row from', currentNameIndex, 'to', newIndex);
                    
                    // Update row attributes
                    $currentRow.attr('data-row-index', newIndex);
                    $currentRow.data('row-index', newIndex);
                    $currentRow.find('.row-index').text(newIndex + 1);
                    
                    // **FIX: Update ALL field names (not just those with removedIndex)**
                    $currentRow.find('[name]').each(function() {
                        var name = $(this).attr('name');
                        if (name && name.includes(groupSlug)) {
                            // Extract current index from name
                            var regex = new RegExp('\\[' + groupSlug + '\\]\\[(\\d+)\\]');
                            var match = name.match(regex);
                            
                            if (match && match[1]) {
                                var oldIdx = match[1];
                                // Replace the old index with new index
                                name = name.replace(
                                    '[' + groupSlug + '][' + oldIdx + ']',
                                    '[' + groupSlug + '][' + newIndex + ']'
                                );
                                $(this).attr('name', name);
                            }
                        }
                    });

                    // **FIX: Update ALL field IDs**
                    $currentRow.find('[id]').each(function() {
                        var id = $(this).attr('id');
                        if (id && id.includes(groupSlug)) {
                            // Extract current index from ID
                            var regex = new RegExp('_' + groupSlug + '_(\\d+)_');
                            var match = id.match(regex);
                            
                            if (match && match[1]) {
                                var oldIdx = match[1];
                                // Replace the old index with new index
                                id = id.replace(
                                    '_' + groupSlug + '_' + oldIdx + '_',
                                    '_' + groupSlug + '_' + newIndex + '_'
                                );
                                $(this).attr('id', id);
                            }
                        }
                    });

                    // **FIX: Update nested repeaters**
                    $currentRow.find('.urf-nested-repeater').each(function() {
                        var $nestedWrapper = $(this);
                        var parentField = $nestedWrapper.data('field-name');
                        
                        // Update nested wrapper index
                        $nestedWrapper.attr('data-row-index', newIndex);
                        
                        // Update nested field names
                        $nestedWrapper.find('[name]').each(function() {
                            var name = $(this).attr('name');
                            if (name && name.includes(groupSlug) && name.includes(parentField)) {
                                // Extract current index from name
                                var regex = new RegExp('\\[' + groupSlug + '\\]\\[(\\d+)\\]\\[' + parentField + '\\]');
                                var match = name.match(regex);
                                
                                if (match && match[1]) {
                                    var oldIdx = match[1];
                                    name = name.replace(
                                        '[' + groupSlug + '][' + oldIdx + '][' + parentField + ']',
                                        '[' + groupSlug + '][' + newIndex + '][' + parentField + ']'
                                    );
                                    $(this).attr('name', name);
                                }
                            }
                        });

                        // Update nested field IDs
                        $nestedWrapper.find('[id]').each(function() {
                            var id = $(this).attr('id');
                            if (id && id.includes(groupSlug) && id.includes(parentField)) {
                                // Extract current index from ID
                                var regex = new RegExp('_' + groupSlug + '_(\\d+)_' + parentField + '_');
                                var match = id.match(regex);
                                
                                if (match && match[1]) {
                                    var oldIdx = match[1];
                                    id = id.replace(
                                        '_' + groupSlug + '_' + oldIdx + '_' + parentField + '_',
                                        '_' + groupSlug + '_' + newIndex + '_' + parentField + '_'
                                    );
                                    $(this).attr('id', id);
                                }
                            }
                        });
                    });
                } else {
                    // Just update display number for rows that don't need reindexing
                    $currentRow.find('.row-index').text(newIndex + 1);
                }
                
                newIndex++;
            });

            console.log('URF: Complete reindexing finished. Final row count:', newIndex);
            $(this).data('processing', false);
        });
}
                
                // Function to update nested row indices when parent row is removed
                function updateNestedRowIndices($parentRow, newParentIndex) {
                    var groupSlug = $parentRow.closest('.urf-field-group').data('group');
                    var oldParentIndex = $parentRow.data('row-index') || $parentRow.index();
                    
                    // Find all nested repeaters in this parent row
                    $parentRow.find('.urf-nested-repeater').each(function() {
                        var $nestedRepeater = $(this);
                        var parentFieldName = $nestedRepeater.data('field-name');
                        
                        // Update nested rows
                        $nestedRepeater.find('.urf-nested-tbody tr:not(.urf-clone-nested-row)').each(function(nestedIndex) {
                            var $nestedRow = $(this);
                            
                            // Update all field names in nested row
                            $nestedRow.find('[name]').each(function() {
                                var name = $(this).attr('name');
                                if (name) {
                                    // Update both parent index and nested index if needed
                                    name = name.replace(
                                        new RegExp('\\[' + groupSlug + '\\]\\[' + oldParentIndex + '\\]\\[' + parentFieldName + '\\]\\[(\\d+)\\]', 'g'),
                                        '[' + groupSlug + '][' + newParentIndex + '][' + parentFieldName + '][' + nestedIndex + ']'
                                    );
                                    $(this).attr('name', name);
                                }
                            });
                            
                            // Update IDs
                            $nestedRow.find('[id]').each(function() {
                                var id = $(this).attr('id');
                                if (id) {
                                    id = id.replace(
                                        new RegExp('_' + groupSlug + '_' + oldParentIndex + '_' + parentFieldName + '_(\\d+)_', 'g'),
                                        '_' + groupSlug + '_' + newParentIndex + '_' + parentFieldName + '_' + nestedIndex + '_'
                                    );
                                    $(this).attr('id', id);
                                }
                            });
                            
                            // Update data attribute
                            $nestedRow.attr('data-nested-index', nestedIndex);
                            $nestedRow.data('nested-index', nestedIndex);
                            
                            // Update display index
                            $nestedRow.find('.nested-row-index').text(nestedIndex + 1);
                        });
                    });
                }
                
                // Initialize nested repeater functionality
                function initNestedRepeaters() {
                    // Add nested row
                    $(document).off('click.urf-nested-add-row').on('click.urf-nested-add-row', '.urf-add-nested-row', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        var $button = $(this);
                        var $nestedRepeater = $button.closest('.urf-nested-repeater');
                        var $tbody = $nestedRepeater.find('.urf-nested-tbody');
                        var $cloneRow = $tbody.find('.urf-clone-nested-row');
                        
                        if ($cloneRow.length === 0) {
                            console.error('Nested repeater clone row not found');
                            return;
                        }
                        
                        // Count existing nested rows (excluding clone row)
                        var nestedRowCount = $tbody.find('tr:not(.urf-clone-nested-row)').length;
                        
                        // Clone the clone row
                        var $newRow = $cloneRow.clone();
                        $newRow.removeClass('urf-clone-nested-row').show();
                        
                        // Get parent info
                        var parentRowIndex = $button.data('row-index') || 0;
                        var parentFieldName = $nestedRepeater.data('field-name');
                        var groupSlug = $nestedRepeater.closest('.urf-field-group').data('group');
                        
                        console.log('URF: Adding nested row. Parent row:', parentRowIndex, 'Field:', parentFieldName, 'Group:', groupSlug);
                        
                        // Update all names and IDs
                        $newRow.find('[name]').each(function() {
                            var name = $(this).attr('name');
                            if (name) {
                                // Replace __NESTED_INDEX__ with actual index
                                name = name.replace(/\[__NESTED_INDEX__\]/g, '[' + nestedRowCount + ']');
                                $(this).attr('name', name);
                            }
                        });
                        
                        $newRow.find('[id]').each(function() {
                            var id = $(this).attr('id');
                            if (id) {
                                // Replace __NESTED_INDEX__ with actual index
                                id = id.replace(/__NESTED_INDEX__/g, nestedRowCount);
                                $(this).attr('id', id);
                            }
                        });
                        
                        // Update data attribute
                        $newRow.attr('data-nested-index', nestedRowCount);
                        
                        // Update display index
                        $newRow.find('.nested-row-index').text(nestedRowCount + 1);
                        
                        // Insert before clone row
                        $cloneRow.before($newRow);
                        
                        // Initialize plugins for new row
                        setTimeout(function() {
                            // Datepicker
                            $newRow.find('.urf-datepicker').each(function() {
                                if (typeof $.fn.datepicker === 'function' && !$(this).hasClass('hasDatepicker')) {
                                    $(this).datepicker({
                                        dateFormat: 'yy-mm-dd',
                                        changeMonth: true,
                                        changeYear: true,
                                        yearRange: '-100:+10'
                                    }).addClass('hasDatepicker');
                                }
                            });
                            
                            // Colorpicker
                            $newRow.find('.urf-colorpicker').each(function() {
                                if (typeof $.fn.wpColorPicker === 'function' && !$(this).hasClass('wp-color-picker')) {
                                    $(this).wpColorPicker().addClass('wp-color-picker');
                                }
                            });
                            
                            // Initialize image upload for new row
                           
                        }, 100);
                        
                        console.log('URF: Nested row added at index:', nestedRowCount);
                    });
                    
                    // Remove nested row
                    $(document).off('click.urf-remove-nested-row').on('click.urf-remove-nested-row', '.urf-remove-nested-row', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        if ($(this).data('processing')) return;
                        $(this).data('processing', true);
                        
                        if (confirm('<?php _e('Are you sure you want to remove this nested row?', 'ultimate-repeater-field'); ?>')) {
                            var $row = $(this).closest('tr');
                            var $tbody = $row.closest('tbody');
                            var $nestedRepeater = $tbody.closest('.urf-nested-repeater');
                            var removedIndex = parseInt($row.attr('data-nested-index')) || $row.index();
                            
                            console.log('URF: Removing nested row at index:', removedIndex);
                            
                            // Get parent info
                            var parentFieldName = $nestedRepeater.data('field-name');
                            var parentRowIndex = $nestedRepeater.data('row-index') || 0;
                            var groupSlug = $nestedRepeater.closest('.urf-field-group').data('group');
                            
                            console.log('URF: Parent info - Field:', parentFieldName, 'Parent Row:', parentRowIndex, 'Group:', groupSlug);
                            
                            // Remove the nested row
                            $row.remove();
                            
                            // Reindex all remaining nested rows
                            $tbody.find('tr:not(.urf-clone-nested-row)').each(function(newIndex) {
                                var $currentRow = $(this);
                                var oldIndex = parseInt($currentRow.attr('data-nested-index')) || newIndex;
                                
                                console.log('URF: Reindexing nested row from', oldIndex, 'to', newIndex);
                                
                                // Update data attribute
                                $currentRow.attr('data-nested-index', newIndex);
                                $currentRow.data('nested-index', newIndex);
                                
                                // Update display index
                                $currentRow.find('.nested-row-index').text(newIndex + 1);
                                
                                // Update ALL field names in this nested row
                                $currentRow.find('[name]').each(function() {
                                    var name = $(this).attr('name');
                                    if (name) {
                                        // Find and replace the nested index
                                        var regex = new RegExp(
                                            '\\[' + groupSlug + '\\]\\[' + parentRowIndex + '\\]\\[' + parentFieldName + '\\]\\[' + oldIndex + '\\]',
                                            'g'
                                        );
                                        name = name.replace(regex, '[' + groupSlug + '][' + parentRowIndex + '][' + parentFieldName + '][' + newIndex + ']');
                                        $(this).attr('name', name);
                                        
                                        console.log('URF: Updated field name from', $(this).attr('name'), 'to', name);
                                    }
                                });
                                
                                // Update IDs
                                $currentRow.find('[id]').each(function() {
                                    var id = $(this).attr('id');
                                    if (id && id.includes('_' + parentFieldName + '_')) {
                                        var parts = id.split('_');
                                        var oldIndexInId = parts[parts.indexOf(parentFieldName) + 1];
                                        if (oldIndexInId && !isNaN(oldIndexInId) && parseInt(oldIndexInId) === oldIndex) {
                                            var newId = id.replace(
                                                '_' + parentFieldName + '_' + oldIndexInId + '_',
                                                '_' + parentFieldName + '_' + newIndex + '_'
                                            );
                                            $(this).attr('id', newId);
                                            console.log('URF: Updated ID from', id, 'to', newId);
                                        }
                                    }
                                });
                            });
                            
                            console.log('URF: Nested row removal and reindexing complete');
                        }
                        
                        $(this).data('processing', false);
                    });
                    
                    // Make nested repeaters sortable with reindexing on update
                    if (typeof $.fn.sortable === 'function') {
                        $('.urf-nested-tbody').sortable({
                            handle: '.urf-row-handle',
                            axis: 'y',
                            placeholder: 'urf-sortable-placeholder',
                            forcePlaceholderSize: true,
                            start: function(e, ui) {
                                ui.placeholder.height(ui.item.height());
                            },
                            update: function() {
                                var $tbody = $(this);
                                var $nestedRepeater = $tbody.closest('.urf-nested-repeater');
                                var parentFieldName = $nestedRepeater.data('field-name');
                                var parentRowIndex = $nestedRepeater.data('row-index') || 0;
                                var groupSlug = $nestedRepeater.closest('.urf-field-group').data('group');
                                
                                $tbody.find('tr:not(.urf-clone-nested-row)').each(function(index) {
                                    var $row = $(this);
                                    var oldIndex = parseInt($row.attr('data-nested-index')) || index;
                                    
                                    // Update data attribute
                                    $row.attr('data-nested-index', index);
                                    $row.data('nested-index', index);
                                    
                                    // Update display index
                                    $row.find('.nested-row-index').text(index + 1);
                                    
                                    // Update field names
                                    $row.find('[name]').each(function() {
                                        var name = $(this).attr('name');
                                        if (name) {
                                            var regex = new RegExp(
                                                '\\[' + groupSlug + '\\]\\[' + parentRowIndex + '\\]\\[' + parentFieldName + '\\]\\[' + oldIndex + '\\]',
                                                'g'
                                            );
                                            name = name.replace(regex, '[' + groupSlug + '][' + parentRowIndex + '][' + parentFieldName + '][' + index + ']');
                                            $(this).attr('name', name);
                                        }
                                    });
                                    
                                    // Update IDs
                                    $row.find('[id]').each(function() {
                                        var id = $(this).attr('id');
                                        if (id && id.includes('_' + parentFieldName + '_')) {
                                            var parts = id.split('_');
                                            var oldIndexInId = parts[parts.indexOf(parentFieldName) + 1];
                                            if (oldIndexInId && !isNaN(oldIndexInId)) {
                                                var newId = id.replace(
                                                    '_' + parentFieldName + '_' + oldIndexInId + '_',
                                                    '_' + parentFieldName + '_' + index + '_'
                                                );
                                                $(this).attr('id', newId);
                                            }
                                        }
                                    });
                                });
                                
                                console.log('URF: Nested rows sorted and reindexed');
                            }
                        });
                    }
                    
                    urfLog('Nested repeaters initialized');
                }
                
                // Main initialization function
                function initURF() {
                    // Check if already initialized
                    if ($('body').hasClass('urf-initialized')) {
                        urfLog('URF already initialized, skipping');
                        return;
                    }
                    
                    urfLog('Starting URF initialization');
                    
                    // Wait for jQuery UI
                    waitForJQueryUI(function(success) {
                        if (success) {
                            // Initialize components
                            initDatepicker();
                            initColorpicker();
                            initSortable();
                            initWysiwyg();
                            initImageUpload();
                            initAddRow();
                            initNestedRepeaters();
                            
                            // Mark as initialized
                            $('body').addClass('urf-initialized');
                            
                            // Auto-add one row for new posts (only if no rows exist)
                            if (window.location.href.indexOf('post-new.php') > -1) {
                                setTimeout(function() {
                                    $('.urf-repeater-wrapper').each(function() {
                                        var $wrapper = $(this);
                                        var $tbody = $wrapper.find('tbody');
                                        var $rows = $tbody.find('tr:not(.urf-clone-row)');
                                        
                                        if ($rows.length === 0) {
                                            $wrapper.find('.urf-add-row').trigger('click');
                                        }
                                    });
                                }, 1000);
                            }
                            
                            urfLog('URF initialization complete');
                        } else {
                            urfLog('URF initialization failed - jQuery UI not loaded');
                            // Fallback: still initialize add row without jQuery UI
                            initAddRow();
                            initImageUpload();
                            initNestedRepeaters();
                            $('body').addClass('urf-initialized');
                        }
                    });
                }
                
                // Start initialization
                initURF();
                
            })(jQuery); // End jQuery wrapper
        });
        </script>
        <?php
    }
    
    public function add_meta_boxes() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'urf_field_groups';
        
        $current_screen = get_current_screen();
        if (!$current_screen) return;
        
        // Get ALL field groups
        $all_field_groups = $wpdb->get_results("SELECT * FROM $table_name");
        
        foreach ($all_field_groups as $group) {
            // Check if this field group should be displayed on this specific page/post
            if ($this->should_display_field_group($group, get_the_ID())) {
                add_meta_box(
                    'urf_field_group_' . $group->slug,
                    $group->name . ' (' . __('Repeater', 'ultimate-repeater-field') . ')',
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
		
		// If no display rules are set, don't show anything
		if (empty($post_types) && empty($pages)) {
			return false;
		}
		
		$current_post_type = get_post_type($post_id);
		
		// Check if 'all' is in post_types (show on all post types)
		if (is_array($post_types) && in_array('all', $post_types)) {
			// If showing on all post types, check if there are specific pages
			if (is_array($pages) && !empty($pages)) {
				// Show only on specific page IDs
				return in_array($post_id, $pages);
			}
			// No pages specified, show on all post types
			return true;
		}
		
		// Check if current post type is in the selected post types
		if (!is_array($post_types) || !in_array($current_post_type, $post_types)) {
			return false;
		}
		
		// If pages array is not empty, show only on specific pages
		if (is_array($pages) && !empty($pages)) {
			return in_array($post_id, $pages);
		}
		
		// If we get here, the post type matches and no specific pages are set
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
        
        ?>
        
        <div class="urf-field-group" data-group="<?php echo esc_attr($group->slug); ?>">
            <input type="hidden" name="urf_field_group[]" value="<?php echo esc_attr($group->slug); ?>">
            <input type="hidden" name="urf_nonce_<?php echo esc_attr($group->slug); ?>" value="<?php echo wp_create_nonce('urf_save_fields_' . $group->slug); ?>">
            
            <div class="urf-repeater-wrapper">
                <table class="urf-repeater-table">
                    <!--<thead>
                        <tr>
                            <th class="urf-row-handle">#</th>
                            <?php// foreach ($fields as $field): ?>
                                <th><?php //echo esc_html($field['label']); ?>
                                    <?php //if ($field['required'] ?? false): ?>
                                        <span style="color: #dc3232;">*</span>
                                    <?php //endif; ?>
                                </th>
                            <?php //endforeach; ?>
                            <th class="urf-row-actions"><?php // _e('Actions', 'ultimate-repeater-field'); ?></th>
                        </tr>
                    </thead>
					-->
                    <tbody id="urf-tbody-<?php echo esc_attr($group->slug); ?>">
                        <?php
                        // Existing rows
                        if (!empty($data)) {
                            foreach ($data as $row_index => $row) {
                                $this->render_repeater_row($group->slug, $fields, $row, $row_index);
                            }
                        } else {
                            // For new posts, show one empty row
                            $this->render_repeater_row($group->slug, $fields, array(), 0);
                        }
                        
                        // Clone row template (hidden, used for adding new rows)
                        $this->render_repeater_row($group->slug, $fields, array(), '__INDEX__', true);
                        ?>
                    </tbody>
                </table>
                
                <button type="button" class="button urf-add-row" data-group="<?php echo esc_attr($group->slug); ?>">
                    <span class="dashicons dashicons-plus"></span> <?php _e('Add Row', 'ultimate-repeater-field'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    public function render_repeater_row($group_slug, $fields, $row_data, $row_index, $is_clone = false) {
    $row_class = $is_clone ? 'urf-clone-row' : '';
    $display = $is_clone ? 'style="display: none;"' : '';
    
    // For clone rows, use placeholder __INDEX__
    $index_name = $is_clone ? '__INDEX__' : $row_index;
    
    // NEW: Add data attribute to track if this is a main row
    $data_attr = $is_clone ? '' : 'data-main-row="true"';
    
    ?>
    <tr class="<?php echo $row_class; ?> urf-main-row" <?php echo $display; ?> <?php echo $data_attr; ?> data-row-index="<?php echo $row_index; ?>">
        <td class="urf-row-handle">
            <span class="dashicons dashicons-menu"></span>
            <span class="row-index"><?php echo $is_clone ? '0' : ($row_index + 1); ?></span>
        </td>
        
        <td colspan="<?php echo count($fields) + 1; ?>">
            <div class="urf-vertical-fields" style="padding: 15px;">
                <?php foreach ($fields as $field): 
                    $field_name = $field['name'];
                    $field_value = isset($row_data[$field_name]) ? $row_data[$field_name] : '';
                    $input_name = "urf_data[{$group_slug}][{$index_name}][{$field_name}]";
                    $input_id = "urf_{$group_slug}_{$index_name}_{$field_name}";
                ?>
                    <div class="urf-vertical-field" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            <?php echo esc_html($field['label']); ?>
                            <?php if ($field['required'] ?? false): ?>
                                <span style="color: #dc3232;">*</span>
                            <?php endif; ?>
                        </label>
                        <div style="margin-bottom: 5px;">
                            <?php $this->render_field_input($field, $input_name, $input_id, $field_value, $index_name, $group_slug, $index_name); ?>
                        </div>
                        <?php if (!empty($field['description'])): ?>
                            <p class="description" style="margin-top: 5px; font-style: italic; color: #666;">
                                <?php echo esc_html($field['description']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </td>
        
        <td class="urf-row-actions">
            <a href="#" class="urf-remove-row dashicons dashicons-trash" title="<?php _e('Remove Row', 'ultimate-repeater-field'); ?>"></a>
        </td>
    </tr>
    <?php
}
    
    public function render_field_input($field, $name, $id, $value, $row_index, $group_slug, $parent_row_index = 0) {
        $field_type = $field['type'] ?? 'text';
        $required = $field['required'] ?? false;
        
        // Handle array values (especially for checkboxes)
        // Only collapse arrays for non-repeater, non-checkbox fields
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
				// Handle both single values and arrays
				$image_value = $value;
				if (is_array($image_value) && !empty($image_value)) {
					$image_value = reset($image_value);
				}
				
				// Clear value if it's empty or 0
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
                // FIX: Handle array values for select
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
                    // Ensure values is an array
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
                // FIX: Handle array values for radio
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
                // FIX: Handle array values for color
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
                // FIX: Handle array values for date
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
                
            case 'repeater':
                $subfields = $field['subfields'] ?? array();
                $repeater_data = is_array($value) ? $value : array();
                ?>
                <div class="urf-nested-repeater" data-field-name="<?php echo esc_attr($field['name']); ?>" data-row-index="<?php echo $parent_row_index; ?>">
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
                                    <?php $this->render_nested_repeater_row($subfields, $field['name'], $group_slug, $parent_row_index, $nested_index, $nested_row); ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php $this->render_nested_repeater_row($subfields, $field['name'], $group_slug, $parent_row_index, 0, array()); ?>
                            <?php endif; ?>
                            
                            <!-- Clone row template -->
                            <?php $this->render_nested_repeater_row($subfields, $field['name'], $group_slug, $parent_row_index, '__NESTED_INDEX__', array(), true); ?>
                        </tbody>
                    </table>
                    
                    <button type="button" class="button button-small urf-add-nested-row" 
                            data-parent-field="<?php echo esc_attr($field['name']); ?>" 
                            data-row-index="<?php echo $parent_row_index; ?>">
                        <span class="dashicons dashicons-plus"></span> <?php _e('Add Nested Row', 'ultimate-repeater-field'); ?>
                    </button>
                </div>
                <?php
                break;
                
            default:
                // Default to text input
                // FIX: Handle array values for default
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
        
        // For clone rows, use placeholder __INDEX__
        $index_name = $is_clone ? '__NESTED_INDEX__' : $nested_index;
        
        // Display index for UI (add 1 for human-readable display)
        $display_index = $is_clone ? '0' : (is_numeric($nested_index) ? (intval($nested_index) + 1) : '1');
        
        ?>
        <tr class="<?php echo $row_class; ?>" <?php echo $display; ?> data-nested-index="<?php echo $index_name; ?>">
            <td class="urf-row-handle">
                <span class="dashicons dashicons-menu"></span>
                <span class="nested-row-index"><?php echo $display_index; ?></span>
            </td>
            
            <?php foreach ($subfields as $subfield): 
                $subfield_name = $subfield['name'];
                $field_value = isset($nested_row[$subfield_name]) ? $nested_row[$subfield_name] : '';
                
                // IMPORTANT FIX: Use the actual parent row index, not the array position
                $input_name = "urf_data[{$group_slug}][{$parent_row_index}][{$parent_field_name}][{$index_name}][{$subfield_name}]";
                $input_id = "urf_{$group_slug}_{$parent_row_index}_{$parent_field_name}_{$index_name}_{$subfield_name}";
            ?>
                <td>
                    <?php $this->render_field_input($subfield, $input_name, $input_id, $field_value, $index_name, $group_slug, $parent_row_index); ?>
                </td>
            <?php endforeach; ?>
            
            <td class="urf-row-actions">
                <a href="#" class="urf-remove-nested-row dashicons dashicons-trash" title="<?php _e('Remove Row', 'ultimate-repeater-field'); ?>"></a>
            </td>
        </tr>
        <?php
    }
    
    public function save_post_data($post_id, $post, $update) {
		// Silence output
		$old_error_reporting = error_reporting(0);
		$old_display_errors  = ini_get('display_errors');
		ini_set('display_errors', '0');
		ob_start();

		// Required POST data
		if (
			!isset($_POST['urf_field_group']) ||
			!is_array($_POST['urf_field_group']) ||
			!isset($_POST['urf_data'])
		) {
			ob_end_clean();
			error_reporting($old_error_reporting);
			ini_set('display_errors', $old_display_errors);
			return;
		}

		// Autosave / permissions
		if (
			(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
			!current_user_can('edit_post', $post_id)
		) {
			ob_end_clean();
			error_reporting($old_error_reporting);
			ini_set('display_errors', $old_display_errors);
			return;
		}

		// Nonce validation
		foreach ($_POST['urf_field_group'] as $group_slug) {
			if (
				empty($_POST['urf_nonce_' . $group_slug]) ||
				!wp_verify_nonce(
					$_POST['urf_nonce_' . $group_slug],
					'urf_save_fields_' . $group_slug
				)
			) {
				ob_end_clean();
				error_reporting($old_error_reporting);
				ini_set('display_errors', $old_display_errors);
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

		foreach ($_POST['urf_data'] as $group_slug => $rows) {
			if (!in_array($group_slug, $_POST['urf_field_group'], true)) {
				continue;
			}

			/* -------------------------------------------------
			 * DELETE OLD DATA
			 * ------------------------------------------------- */
			// Delete main rows
			$wpdb->delete($table_name, [
				'post_id'     => $post_id,
				'field_group' => $group_slug
			]);

			// Delete ALL nested repeater rows for this group
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name}
					 WHERE post_id = %d
					 AND field_group LIKE %s",
					$post_id,
					'nested_' . $group_slug . '\_%'
				)
			);

			if (!is_array($rows)) {
				continue;
			}

			// Load field group config
			$group = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$group_table} WHERE slug = %s", $group_slug)
			);

			$repeater_fields = [];
			if ($group) {
				$fields = maybe_unserialize($group->fields);
				if (is_array($fields)) {
					foreach ($fields as $field) {
						if ($field['type'] === 'repeater') {
							$repeater_fields[$field['name']] = true;
						}
					}
				}
			}

			// Sort rows by their numerical index to maintain order
			ksort($rows, SORT_NUMERIC);
			$row_order = 0;

			foreach ($rows as $row_index => $row) {
				if ($row_index === '__INDEX__') {
					continue;
				}

				// Skip if this row has been removed (has no data)
				if (empty($row) || !is_array($row)) {
					continue;
				}

				/* ----------------------------
				 * MAIN FIELD VALUES
				 * ---------------------------- */
				foreach ($row as $field_name => $field_value) {
					if (isset($repeater_fields[$field_name])) {
						continue;
					}

					// CRITICAL FIX: Skip empty image fields
					if (isset($image_fields[$field_name]) && empty($field_value)) {
						continue;
					}

					if (
						$field_value === '' ||
						$field_value === null ||
						(is_array($field_value) && empty($field_value))
					) {
						continue;
					}

					$wpdb->insert($table_name, [
						'post_id'          => $post_id,
						'field_group'      => $group_slug,
						'row_index'        => $row_order,
						'field_name'       => $field_name,
						'field_type'       => 'custom',
						'field_value'      => is_array($field_value)
							? maybe_serialize($field_value)
							: (string) $field_value,
						'parent_field'     => null,
						'parent_row_index' => null,
						'field_order'      => $row_order,
						'created_at'       => current_time('mysql')
					]);
				}

				/* ----------------------------
				 * NESTED REPEATER VALUES
				 * ---------------------------- */
				foreach ($repeater_fields as $repeater_name => $_) {
					if (empty($row[$repeater_name]) || !is_array($row[$repeater_name])) {
						continue;
					}

					// Sort nested rows by their numerical index
					ksort($row[$repeater_name], SORT_NUMERIC);
					$nested_order = 0;

					foreach ($row[$repeater_name] as $nested_index => $nested_row) {
						if (
							$nested_index === '__INDEX__' ||
							$nested_index === '__NESTED_INDEX__' ||
							!is_array($nested_row)
						) {
							continue;
						}

						foreach ($nested_row as $sub_name => $sub_value) {
							// CRITICAL FIX: Skip empty image fields in nested repeaters
							if ($this->is_image_field_in_nested($group_slug, $repeater_name, $sub_name) && empty($sub_value)) {
								continue;
							}

							if (
								$sub_value === '' ||
								$sub_value === null ||
								(is_array($sub_value) && empty($sub_value))
							) {
								continue;
							}

							$wpdb->insert($table_name, [
								'post_id'          => $post_id,
								'field_group'      => 'nested_' . $group_slug . '_' . $repeater_name,
								'row_index'        => $row_order,
								'field_name'       => $sub_name,
								'field_type'       => 'nested',
								'field_value'      => is_array($sub_value)
									? maybe_serialize($sub_value)
									: (string) $sub_value,
								'parent_field'     => $repeater_name,
								'parent_row_index' => $nested_order,
								'field_order'      => $nested_order,
								'created_at'       => current_time('mysql')
							]);
						}

						$nested_order++;
					}
				}

				$row_order++;
			}
		}

		// Restore environment
		ob_end_clean();
		error_reporting($old_error_reporting);
		ini_set('display_errors', $old_display_errors);
	}

	// Add this helper method to check if a nested field is an image field
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

    // Get field group config
    $group_table = $wpdb->prefix . 'urf_field_groups';
    $group = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $group_table WHERE slug = %s",
        $group_slug
    ));

    if (!$group || !$this->should_display_field_group($group, $post_id)) {
        return [];
    }

    /* -------------------------------------------------
     * MAIN ROW DATA
     * ------------------------------------------------- */
    $main_results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name
         WHERE post_id = %d AND field_group = %s
         ORDER BY row_index, field_order",
        $post_id,
        $group_slug
    ));

    if (empty($main_results)) {
        return [];
    }

    $grouped_main = [];
    foreach ($main_results as $row) {
        $value = maybe_unserialize($row->field_value);
        $grouped_main[$row->row_index][$row->field_name] = $value;
    }

    /* -------------------------------------------------
     * GET REPEATER FIELDS
     * ------------------------------------------------- */
    $repeater_fields = [];
    $fields = maybe_unserialize($group->fields);

    if (is_array($fields)) {
        foreach ($fields as $field) {
            if ($field['type'] === 'repeater') {
                $repeater_fields[$field['name']] = true;
            }
        }
    }

    /* -------------------------------------------------
     * NESTED DATA — STRICT PER REPEATER
     * ------------------------------------------------- */
    $grouped_nested = [];

    foreach ($repeater_fields as $repeater_name => $_) {

        $nested_group = 'nested_' . $group_slug . '_' . $repeater_name;

        $nested_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
             WHERE post_id = %d AND field_group = %s
             ORDER BY row_index, parent_row_index, field_order",
            $post_id,
            $nested_group
        ));

        foreach ($nested_rows as $row) {
            $value = maybe_unserialize($row->field_value);

            $r = (int) $row->row_index;
            $n = (int) $row->parent_row_index;

            $grouped_nested[$repeater_name][$r][$n][$row->field_name] = $value;
        }
    }

    /* -------------------------------------------------
     * MERGE MAIN + NESTED
     * ------------------------------------------------- */
    $data = [];

    foreach ($grouped_main as $row_index => $row) {
        $row_data = $row;

        foreach ($repeater_fields as $repeater_name => $_) {
            if (isset($grouped_nested[$repeater_name][$row_index])) {
                ksort($grouped_nested[$repeater_name][$row_index]);
                $row_data[$repeater_name] = array_values(
                    $grouped_nested[$repeater_name][$row_index]
                );
            } else {
                $row_data[$repeater_name] = [];
            }
        }

        $data[] = $row_data;
    }

    return $data;
}

    
    // Helper function to write debug logs
    private function write_debug_log($log_entries) {
        $log_file = WP_CONTENT_DIR . '/urf_debug.log';
        $log_content = "[" . date('Y-m-d H:i:s') . "]\n";
        
        if (is_array($log_entries)) {
            $log_content .= implode("\n", $log_entries);
        } else {
            $log_content .= $log_entries;
        }
        
        $log_content .= "\n\n";
        
        // Append to log file
        file_put_contents($log_file, $log_content, FILE_APPEND);
    }
    
    public function frontend_enqueue_scripts() {
        // Add frontend styles
        ?>
        <style>
        /* ==================== */
        /* URF FRONTEND STYLES - MODERN DESIGN */
        /* ==================== */
        
        .urf-frontend-output {
            margin: 40px 0;
        }
        
        .urf-frontend-row {
            margin-bottom: 30px;
            padding: 30px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
            border-left: 5px solid #667eea;
        }
        
        .urf-frontend-row:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
        }
        
        .urf-frontend-row::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-radius: 0 0 0 100%;
            z-index: 0;
        }
        
        .urf-frontend-field {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 2px dashed #e2e8f0;
            position: relative;
            z-index: 1;
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
        
        .urf-frontend-value img:hover {
            transform: scale(1.02);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }
        
        .urf-frontend-value ul,
        .urf-frontend-value ol {
            padding-left: 25px;
            margin: 15px 0;
        }
        
        .urf-frontend-value li {
            margin-bottom: 10px;
            padding-left: 10px;
            position: relative;
        }
        
        .urf-frontend-value li::before {
            content: '✓';
            position: absolute;
            left: -15px;
            color: #4CAF50;
            font-weight: bold;
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
        
        .urf-frontend-nested-row::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, #4CAF50, #45a049);
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
        
        .urf-frontend-nested-field strong::before {
            content: '🏷️';
            font-size: 14px;
        }
        
        .urf-frontend-nested-field .urf-frontend-value {
            flex: 1;
            padding: 8px 0;
            padding-left: 0;
        }
        
        .urf-frontend-nested-field .urf-frontend-value::before {
            display: none;
        }
        
        /* Color chips for selected values */
        .urf-frontend-value .color-chip {
            display: inline-block;
            width: 25px;
            height: 25px;
            border-radius: 6px;
            margin-right: 8px;
            vertical-align: middle;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border: 2px solid white;
        }
        
        /* Badge style for tags/options */
        .urf-frontend-value .option-badge {
            display: inline-block;
            padding: 6px 12px;
            margin: 4px 8px 4px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 3px 8px rgba(102, 126, 234, 0.3);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .urf-frontend-row {
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
            
            .urf-frontend-nested-field strong {
                min-width: auto;
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .urf-frontend-row {
                padding: 15px;
                border-radius: 12px;
            }
            
            .urf-frontend-label {
                font-size: 15px;
            }
            
            .urf-frontend-value {
                font-size: 14px;
            }
            
            .urf-frontend-nested-repeater {
                padding: 15px;
            }
            
            .urf-frontend-nested-row {
                padding: 15px;
            }
        }
        
        /* Animation for row appearance */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .urf-frontend-row {
            animation: fadeInUp 0.6s ease forwards;
        }
        
        .urf-frontend-row:nth-child(odd) {
            animation-delay: 0.1s;
        }
        
        .urf-frontend-row:nth-child(even) {
            animation-delay: 0.2s;
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
		
		// First, check if this field group should be displayed on this post
		global $wpdb;
		$table_name = $wpdb->prefix . 'urf_field_groups';
		$group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE slug = %s", $atts['field']));
		
		if (!$group) {
			// Field group doesn't exist
			return '<div class="urf-error">' . __('Field group not found!', 'ultimate-repeater-field') . '</div>';
		}
		
		// Check if this field group should be displayed on this post
		if (!$this->should_display_field_group($group, $atts['post_id'])) {
			// Field group is not assigned to this post, don't show anything
			return '';
		}
		
		// Only get data if the field group is assigned to this post
		$data = $this->get_field_data($atts['post_id'], $atts['field']);
		
		if (empty($data)) {
			return '';
		}
		
		// Apply limit
		if ($atts['limit'] > 0) {
			$data = array_slice($data, 0, $atts['limit']);
		}
		
		ob_start();
		
		$fields = maybe_unserialize($group->fields);
		?>
		<div class="urf-frontend-output">
			<?php foreach ($data as $row_index => $row): ?>
				<div class="urf-frontend-row">
					<?php foreach ($fields as $field): 
						$field_name = $field['name'];
						$field_value = isset($row[$field_name]) ? $row[$field_name] : '';
						
						if (empty($field_value) && $field_value !== '0' && $field_value !== 0) {
							continue;
						}
						
						// Handle nested repeater fields
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
			<?php endforeach; ?>
		</div>
		<?php
		
		return ob_get_clean();
	}
    
    public function format_field_value($field, $value) {
        $field_type = $field['type'] ?? 'text';
        
        // Handle array values
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
            
            // For other field types, get the first value
            $value = !empty($value) ? reset($value) : '';
        }
        
        // For non-option fields, just return the raw value
        if (!in_array($field_type, ['select', 'checkbox', 'radio'])) {
            if ($field_type === 'image' && !empty($value)) {
                // For images, display the image
                if (is_numeric($value)) {
                    $image_url = wp_get_attachment_url($value);
                    $image_html = wp_get_attachment_image($value, 'medium');
                } else {
                    $image_url = $value;
                    $image_html = '<img src="' . esc_url($value) . '" alt="" style="max-width: 100%; height: auto;">';
                }
                return $image_html;
            }
            return nl2br(esc_html($value));
        }
        
        // Get options for the field
        $options_string = $field['options'] ?? '';
        if (empty($options_string)) {
            return nl2br(esc_html($value));
        }
        
        // Parse options into an array (value => label)
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
        
        // Now, handle the values based on field type
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
        // Get the data (values)
        $data = $this->get_field_data($post_id, $group_slug);
        
        if (empty($data)) {
            return array();
        }
        
        // Get field group configuration
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
        
        // Create field configuration lookup
        $field_configs = array();
        foreach ($fields as $field) {
            $field_configs[$field['name']] = $field;
        }
        
        // Convert values to labels
        $data_with_labels = array();
        
        foreach ($data as $row_index => $row) {
            $data_with_labels[$row_index] = array();
            
            foreach ($row as $field_name => $field_value) {
                if (!isset($field_configs[$field_name])) {
                    $data_with_labels[$row_index][$field_name] = $field_value;
                    continue;
                }
                
                $field_config = $field_configs[$field_name];
                $field_type = $field_config['type'] ?? 'text';
                
                // Handle image field
                if ($field_type === 'image' && is_array($field_value) && !empty($field_value)) {
                    $data_with_labels[$row_index][$field_name] = reset($field_value);
                }
                // Convert other fields to labels
                elseif (in_array($field_type, ['select', 'checkbox', 'radio'])) {
                    $data_with_labels[$row_index][$field_name] = $this->convert_value_to_label(
                        $field_config, 
                        $field_value
                    );
                }
                // Keep other fields as is
                else {
                    $data_with_labels[$row_index][$field_name] = $field_value;
                }
            }
        }
        
        return $data_with_labels;
    }

    // Helper function to convert value to label
    private function convert_value_to_label($field_config, $value) {
        $field_type = $field_config['type'] ?? 'text';
        
        // Only convert for fields with options
        if (!in_array($field_type, ['select', 'checkbox', 'radio'])) {
            return $value;
        }
        
        $options_string = $field_config['options'] ?? '';
        if (empty($options_string)) {
            return $value;
        }
        
        // Parse options
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
        
        // Convert based on field type
        if ($field_type === 'checkbox') {
            // Ensure value is array
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
            // Multiple select
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
            // Single select or radio
            $value = trim($value);
            if (isset($options_map[$value])) {
                return $options_map[$value];
            }
            return $value;
        }
    }
    
    public function ajax_get_field_group() {
        // Security check
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
        // Security check
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
        
        // First check if the field group exists and is assigned to this post
        global $wpdb;
        $table_name = $wpdb->prefix . 'urf_field_groups';
        $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE slug = %s", $field_group));
        
        if (!$group) {
            return array();
        }
        
        // Check if this field group should be displayed on this post
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

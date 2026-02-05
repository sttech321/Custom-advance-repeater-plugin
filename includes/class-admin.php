<?php
if (!defined('ABSPATH')) { exit; }

class Custom_Advance_Repeater_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Custom Advance Repeater', 'custom-advance-repeater'),
            __('Custom Advance Repeater', 'custom-advance-repeater'),
            'manage_options',
            'custom-advance-repeater',
            array($this, 'admin_dashboard'),
            'dashicons-list-view',
            30
        );
        
        add_submenu_page(
            'custom-advance-repeater',
            __('Field Groups', 'custom-advance-repeater'),
            __('Field Groups', 'custom-advance-repeater'),
            'manage_options',
            'carf-field-groups',
            array($this, 'field_groups_page')
        );
        
        add_submenu_page(
            'custom-advance-repeater',
            __('Add New Field Group', 'custom-advance-repeater'),
            __('Add New', 'custom-advance-repeater'),
            'manage_options',
            'carf-add-field-group',
            array($this, 'add_field_group_page')
        );
    }

    public function admin_enqueue_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php')) && strpos($hook, 'custom-advance-repeater') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style('carf-admin-css', carf_PLUGIN_URL . 'assets/css/admin.css', array(), carf_VERSION);
        
        // Libraries
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_editor();
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        // JS
        wp_enqueue_script('carf-admin-js', carf_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-datepicker', 'wp-color-picker'), carf_VERSION, true);
        
        // Localize vars to JS
        wp_localize_script('carf-admin-js', 'carf_admin_vars', array(
            'ajax_nonce' => wp_create_nonce('carf_ajax_nonce'),
            'ajax_url'   => admin_url('admin-ajax.php')
        ));
    }

    public function admin_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php _e('Custom Advance Repeater', 'custom-advance-repeater'); ?></h1>
            <div class="carf-dashboard">
                <div class="carf-card">
                    <h2><?php _e('Getting Started', 'custom-advance-repeater'); ?></h2>
                    <ol>
                        <li><?php _e('Create Field Groups with your desired fields', 'custom-advance-repeater'); ?></li>
                        <li><?php _e('Assign field groups to post types or specific pages', 'custom-advance-repeater'); ?></li>
                        <li><?php _e('Edit posts/pages to add repeater data', 'custom-advance-repeater'); ?></li>
                        <li><?php _e("Display data in your theme using the urf_get_repeater_with_labels('group slug', post id); functions", 'custom-advance-repeater'); ?></li>
                    </ol>
                </div>
                <div class="carf-card">
                    <h2><?php _e('Available Field Types', 'custom-advance-repeater'); ?></h2>
                    <ul>
                        <li><strong><?php _e('Text', 'custom-advance-repeater'); ?></strong> - <?php _e('Simple text input', 'custom-advance-repeater'); ?></li>
                        <li><strong><?php _e('Textarea', 'custom-advance-repeater'); ?></strong> - <?php _e('Multi-line text', 'custom-advance-repeater'); ?></li>
                        <li><strong><?php _e('Image Upload', 'custom-advance-repeater'); ?></strong> - <?php _e('Upload images with preview', 'custom-advance-repeater'); ?></li>
                        <li><strong><?php _e('Select Dropdown', 'custom-advance-repeater'); ?></strong> - <?php _e('Select from options', 'custom-advance-repeater'); ?></li>
                        <li><strong><?php _e('Checkbox', 'custom-advance-repeater'); ?></strong> - <?php _e('Multiple checkboxes', 'custom-advance-repeater'); ?></li>
                        <li><strong><?php _e('Radio Buttons', 'custom-advance-repeater'); ?></strong> - <?php _e('Single selection', 'custom-advance-repeater'); ?></li>
                        <li><strong><?php _e('Color Picker', 'custom-advance-repeater'); ?></strong> - <?php _e('Color selection', 'custom-advance-repeater'); ?></li>
                        <li><strong><?php _e('Date Picker', 'custom-advance-repeater'); ?></strong> - <?php _e('Date selection', 'custom-advance-repeater'); ?></li>
                        <li><strong><?php _e('Repeater Field', 'custom-advance-repeater'); ?></strong> - <?php _e('Nested repeater with sub-fields', 'custom-advance-repeater'); ?></li>
                    </ul>
                </div>
                <div class="carf-card">
                    <h2><?php _e('New in Version 1.6.0', 'custom-advance-repeater'); ?></h2>
                    <ul>
                        <li><strong><?php _e('Nested Repeater Support', 'custom-advance-repeater'); ?></strong> - <?php _e('Repeater fields inside repeater fields', 'custom-advance-repeater'); ?></li>
                        <li><strong><?php _e('Multi-level Nesting', 'custom-advance-repeater'); ?></strong> - <?php _e('Support for deeply nested structures', 'custom-advance-repeater'); ?></li>
                        <li><strong><?php _e('Improved UI', 'custom-advance-repeater'); ?></strong> - <?php _e('Better interface for managing nested fields', 'custom-advance-repeater'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <style>
            .carf-dashboard { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-top: 20px; }
            .carf-card { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .carf-card h2 { margin-top: 0; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
            .carf-card ul, .carf-card ol { padding-left: 20px; }
            .carf-card li { margin-bottom: 8px; }
        </style>
        <?php
    }

    public function field_groups_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'carf_field_groups';
        $fields_table = $wpdb->prefix . 'carf_fields';
        
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
                
                echo '<div class="notice notice-success"><p>' . __('Field group deleted successfully!', 'custom-advance-repeater') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Field group not found!', 'custom-advance-repeater') . '</p></div>';
            }
        }
        
        $field_groups = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Field Groups', 'custom-advance-repeater'); ?>
                <a href="<?php echo admin_url('admin.php?page=carf-add-field-group'); ?>" class="page-title-action">
                    <?php _e('Add New', 'custom-advance-repeater'); ?>
                </a>
            </h1>
            
            <?php if (empty($field_groups)): ?>
                <div class="notice notice-info">
                    <p><?php _e('No field groups found. Create your first field group!', 'custom-advance-repeater'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'custom-advance-repeater'); ?></th>
                            <th><?php _e('Slug', 'custom-advance-repeater'); ?></th>
                            <th><?php _e('Post Types', 'custom-advance-repeater'); ?></th>
                            <th><?php _e('Specific Pages', 'custom-advance-repeater'); ?></th>
                            <th><?php _e('Fields Count', 'custom-advance-repeater'); ?></th>
                            <th><?php _e('Actions', 'custom-advance-repeater'); ?></th>
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
                                            echo __('All', 'custom-advance-repeater');
                                        } else {
                                            echo implode(', ', $post_types);
                                        }
                                    } else {
                                        echo __('All', 'custom-advance-repeater');
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
                                        echo __('None', 'custom-advance-repeater');
                                    }
                                    ?>
                                </td>
                                <td><?php echo is_array($fields) ? count($fields) : 0; ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=carf-add-field-group&edit=' . $group->id); ?>" class="button button-small">
                                        <?php _e('Edit', 'custom-advance-repeater'); ?>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=carf-field-groups&delete=' . $group->id); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this field group?', 'custom-advance-repeater'); ?>');">
                                        <?php _e('Delete', 'custom-advance-repeater'); ?>
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
        $core = Custom_Advance_Repeater_Core::get_instance();
        
        $success_message = '';
        $error_message = '';
        
        if (isset($_GET['saved']) && $_GET['saved'] == '1') {
            $success_message = '<div class="notice notice-success"><p>' . __('Field group saved successfully!', 'custom-advance-repeater') . '</p></div>';
        }
        
        if (isset($_POST['save_field_group'], $_POST['carf_field_group_nonce'])) {
            if (!wp_verify_nonce($_POST['carf_field_group_nonce'], 'carf_save_field_group')) {
                $error_message = '<div class="notice notice-error"><p>Security check failed</p></div>';
            } elseif (!current_user_can('manage_options')) {
                $error_message = '<div class="notice notice-error"><p>Permission denied</p></div>';
            } else {
                $result = $core->db->save_field_group();
                
                if ($result === true) {
                    $redirect_url = !empty($_POST['group_id'])
                        ? admin_url('admin.php?page=carf-add-field-group&edit=' . intval($_POST['group_id']) . '&saved=1')
                        : admin_url('admin.php?page=carf-field-groups&saved=1');
                    
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
        $group = $group_id ? $core->db->get_field_group($group_id) : null;
        $selected_types = $group ? maybe_unserialize($group->post_types) : array('post', 'page');
        
        ?>
        <div class="wrap">
            <h1><?php echo $group ? __('Edit Field Group', 'custom-advance-repeater') : __('Add New Field Group', 'custom-advance-repeater'); ?></h1>
            
            <?php 
            echo $error_message;
            echo $success_message;
            ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('carf_save_field_group', 'carf_field_group_nonce'); ?>
                <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="group_name"><?php _e('Group Name', 'custom-advance-repeater'); ?> *</label></th>
                        <td>
                            <input type="text" id="group_name" name="group_name" class="regular-text" 
                                   value="<?php echo $group ? esc_attr($group->name) : ''; ?>" required>
                            <p class="description"><?php _e('Enter a descriptive name for this field group', 'custom-advance-repeater'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="group_slug"><?php _e('Group Slug', 'custom-advance-repeater'); ?> *</label></th>
                        <td>
                            <input type="text" id="group_slug" name="group_slug" class="regular-text" 
                                   value="<?php echo $group ? esc_attr($group->slug) : ''; ?>" required>
                            <p class="description"><?php _e('Unique identifier (lowercase, no spaces)', 'custom-advance-repeater'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label><?php _e('Show this field group', 'custom-advance-repeater'); ?></label></th>
                        <td>
                            <p>
                                <label>
                                    <input type="radio" name="display_logic" value="all" <?php echo !$group || (empty($selected_types) && empty($group->pages)) || (is_array($selected_types) && in_array('all', $selected_types)) ? 'checked' : ''; ?>>
                                    <?php _e('Show on all posts/pages', 'custom-advance-repeater'); ?>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="radio" name="display_logic" value="post_types" <?php echo $group && (!empty($selected_types) || !empty($group->pages)) && (!is_array($selected_types) || !in_array('all', $selected_types)) ? 'checked' : ''; ?>>
                                    <?php _e('Show on specific post types or pages', 'custom-advance-repeater'); ?>
                                </label>
                            </p>
                        </td>
                    </tr>
                    
                    <tr class="display-options" style="display: none;">
                        <th scope="row"><label><?php _e('Post Types', 'custom-advance-repeater'); ?></label></th>
                        <td>
                            <div style="margin-bottom: 10px;">
                                <label>
                                    <input type="checkbox" name="all_post_types" value="1" class="all-post-types">
                                    <?php _e('All Post Types', 'custom-advance-repeater'); ?>
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
                            <p class="description"><?php _e('Select which post types this field group should appear on', 'custom-advance-repeater'); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="display-options pages-section" style="display: none;">
                        <th scope="row"><label><?php _e('Specific Pages', 'custom-advance-repeater'); ?></label></th>
                        <td>
                            <div class="specific-pages-container">
                                <div style="margin-bottom: 10px;">
                                    <button type="button" class="button button-small" id="select-pages-btn">
                                        <?php _e('Select Pages', 'custom-advance-repeater'); ?>
                                    </button>
                                    <button type="button" class="button button-small" id="clear-pages-btn" style="margin-left: 5px;">
                                        <?php _e('Clear Selection', 'custom-advance-repeater'); ?>
                                    </button>
                                </div>
                                
                                <div id="selected-pages-container" class="selected_pages_container" style="display: none; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; margin-bottom: 10px;">
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
                                            <h3 style="margin: 0;"><?php _e('Select Pages', 'custom-advance-repeater'); ?></h3>
                                            <button type="button" id="close-modal" style="background: none; border: none; font-size: 20px; cursor: pointer;">×</button>
                                        </div>
                                        <div style="padding: 20px; overflow-y: auto; flex-grow: 1;">
                                            <input type="text" id="page-search" placeholder="<?php _e('Search pages...', 'custom-advance-repeater'); ?>" style="width: 100%; padding: 8px; margin-bottom: 10px;">
                                            <div id="pages-list" style="max-height: 300px; overflow-y: auto;">
                                                </div>
                                        </div>
                                        <div style="padding: 15px 20px; border-top: 1px solid #ddd; text-align: right;">
                                            <button type="button" id="add-selected-pages" class="button button-primary"><?php _e('Add Selected Pages', 'custom-advance-repeater'); ?></button>
                                        </div>
                                    </div>
                                </div>
                                
                                <p class="description"><?php _e('Select specific pages where this field group should appear', 'custom-advance-repeater'); ?></p>
                            </div>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Fields', 'custom-advance-repeater'); ?></h2>
                
                <div id="carf-fields-container">
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
                
                <button type="button" id="carf-add-field" class="button button-secondary add_field_btn">
                    <span class="dashicons dashicons-plus"></span> <?php _e('Add Field', 'custom-advance-repeater'); ?>
                </button>
                
                <hr>
                
                <p class="submit">
                    <button type="submit" name="save_field_group" value="1" class="button button-primary button-large">
                        <?php _e('Save Field Group', 'custom-advance-repeater'); ?>
                    </button>
                    <?php if ($group): ?>
                        <a href="<?php echo admin_url('admin.php?page=carf-field-groups'); ?>" class="button button-secondary" style="margin-left: 10px;">
                            <?php _e('Cancel', 'custom-advance-repeater'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <?php
        // Pass variables to admin.js to handle the specific logic of this page
        wp_localize_script('carf-admin-js', 'carf_field_group_config', array(
            'fields_count' => $fields_count,
            'is_all_types' => ($group && is_array($selected_types) && in_array('all', $selected_types)) ? true : false,
            'i18n' => array(
                'loading' => __('Loading pages...', 'custom-advance-repeater'),
                'no_pages' => __('No pages found.', 'custom-advance-repeater'),
                'error' => __('Error loading pages.', 'custom-advance-repeater'),
                'confirm_clear' => __('Are you sure you want to clear all selected pages?', 'custom-advance-repeater'),
                'confirm_remove_field' => __('Are you sure you want to remove this field?', 'custom-advance-repeater'),
                'confirm_remove_subfield' => __('Are you sure you want to remove this subfield?', 'custom-advance-repeater'),
                'confirm_remove_nested' => __('Are you sure you want to remove this nested subfield?', 'custom-advance-repeater'),
                'sub_fields_label' => __('Sub Fields', 'custom-advance-repeater'),
                'add_sub_field' => __('Add Sub Field', 'custom-advance-repeater'),
                'add_fields_desc' => __('Add fields that will appear inside this repeater', 'custom-advance-repeater'),
                'options_label' => __('Options (one per line)', 'custom-advance-repeater'),
                'options_desc' => __('Enter options one per line. Values will be auto-generated from labels.', 'custom-advance-repeater'),
                'text' => __('Text', 'custom-advance-repeater'),
                'textarea' => __('Textarea', 'custom-advance-repeater'),
                'image' => __('Image Upload', 'custom-advance-repeater'),
                'select' => __('Select Dropdown', 'custom-advance-repeater'),
                'checkbox' => __('Checkbox', 'custom-advance-repeater'),
                'radio' => __('Radio Buttons', 'custom-advance-repeater'),
                'color' => __('Color Picker', 'custom-advance-repeater'),
                'date' => __('Date Picker', 'custom-advance-repeater'),
                'repeater' => __('Repeater Field', 'custom-advance-repeater'),
                'field_label' => __('Field Label', 'custom-advance-repeater'),
                'field_label_placeholder' => __('My Field Label', 'custom-advance-repeater'),
                'field_name' => __('Field Name', 'custom-advance-repeater'),
                'field_name_placeholder' => __('my_field_name', 'custom-advance-repeater'),
                'field_name_desc' => __('Lowercase, underscores, no spaces', 'custom-advance-repeater'),
                'required_field' => __('Required Field', 'custom-advance-repeater'),
                'field_title' => __('Field', 'custom-advance-repeater'),
                'sub_field_title' => __('Sub Field', 'custom-advance-repeater'),
                'nested_sub_field_title' => __('Nested Sub Field', 'custom-advance-repeater'),
                'remove' => __('Remove', 'custom-advance-repeater'),
                'sub_label_placeholder' => __('My Sub Field Label', 'custom-advance-repeater'),
                'sub_name_placeholder' => __('my_sub_field_name', 'custom-advance-repeater'),
                'nested_label_placeholder' => __('My Nested Field Label', 'custom-advance-repeater'),
                'nested_name_placeholder' => __('my_nested_field_name', 'custom-advance-repeater')
            )
        ));
        ?>
        <?php
    }

    public function render_field_row($index, $field) {
        // Output provided in add_field_group_page PHP method above or via JS construction
        // This PHP helper is for rendering existing saved fields
        ?>
        <div class="carf-field-row" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;" class="panel_row">
                <h3 class="top_left_panel" style="margin: 0;"><?php _e('Field', 'custom-advance-repeater'); ?> #<span class="field-index"><?php echo $index + 1; ?></span></h3>
                <div class="top_right_panel">
                    <div class="required_checkbox">
                        <label>
                            <input type="checkbox" name="fields[<?php echo $index; ?>][required]" value="1" <?php checked($field['required'] ?? false, true); ?>>
                            <?php _e('Required Field', 'custom-advance-repeater'); ?>
                        </label>
                    </div>
                    <a href="#" class="carf-remove-field" style="color: #dc3232; text-decoration: none;">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Remove', 'custom-advance-repeater'); ?>
                    </a>
                </div>
            </div>
            
            <div class="inner_row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                <div class="inner_colm">
                    <label><?php _e('Field Type', 'custom-advance-repeater'); ?> *</label>
                    <select name="fields[<?php echo $index; ?>][type]" class="carf-field-type widefat" required>
                        <option value="text" <?php selected($field['type'] ?? '', 'text'); ?>><?php _e('Text', 'custom-advance-repeater'); ?></option>
                        <option value="textarea" <?php selected($field['type'] ?? '', 'textarea'); ?>><?php _e('Textarea', 'custom-advance-repeater'); ?></option>
                        <option value="image" <?php selected($field['type'] ?? '', 'image'); ?>><?php _e('Image Upload', 'custom-advance-repeater'); ?></option>
                        <option value="select" <?php selected($field['type'] ?? '', 'select'); ?>><?php _e('Select Dropdown', 'custom-advance-repeater'); ?></option>
                        <option value="checkbox" <?php selected($field['type'] ?? '', 'checkbox'); ?>><?php _e('Checkbox', 'custom-advance-repeater'); ?></option>
                        <option value="radio" <?php selected($field['type'] ?? '', 'radio'); ?>><?php _e('Radio Buttons', 'custom-advance-repeater'); ?></option>
                        <option value="color" <?php selected($field['type'] ?? '', 'color'); ?>><?php _e('Color Picker', 'custom-advance-repeater'); ?></option>
                        <option value="date" <?php selected($field['type'] ?? '', 'date'); ?>><?php _e('Date Picker', 'custom-advance-repeater'); ?></option>
                        <option value="repeater" <?php selected($field['type'] ?? '', 'repeater'); ?>><?php _e('Repeater Field', 'custom-advance-repeater'); ?></option>
                    </select>
                </div>
                
                <div class="inner_colm">
                    <label><?php _e('Field Label', 'custom-advance-repeater'); ?> *</label>
                    <input type="text" name="fields[<?php echo $index; ?>][label]" 
                           value="<?php echo esc_attr($field['label'] ?? ''); ?>" 
                           class="carf-field-label widefat" required>
                </div>
                
                <div class="inner_colm">
                    <label><?php _e('Field Name', 'custom-advance-repeater'); ?> *</label>
                    <input type="text" name="fields[<?php echo $index; ?>][name]" 
                           value="<?php echo esc_attr($field['name'] ?? ''); ?>" 
                           class="carf-field-name widefat" required>
                    <p class="description"><?php _e('Lowercase, underscores, no spaces', 'custom-advance-repeater'); ?></p>
                </div>
            </div>
            
            <div class="carf-field-options" style="display: <?php echo in_array($field['type'] ?? '', ['select', 'checkbox', 'radio', 'repeater']) ? 'block' : 'none'; ?>; margin-bottom: 15px;">
                <?php if (in_array($field['type'] ?? '', ['select', 'checkbox', 'radio'])): ?>
                    <label><?php _e('Options (one per line)', 'custom-advance-repeater'); ?></label>
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
                    <textarea name="fields[<?php echo $index; ?>][options]" class="widefat" rows="5" placeholder="My Option 1"><?php echo esc_textarea($options_text); ?></textarea>
                    <p class="description"><?php _e('Enter options one per line.', 'custom-advance-repeater'); ?></p>

                <?php elseif (($field['type'] ?? '') === 'repeater'): ?>
                    <label class="sub_label" style=" font-size: 16px;font-weight: 600;"><?php _e('Sub Fields', 'custom-advance-repeater'); ?></label>
                    <div class="carf-subfields-container" style="margin-top: 6px;" data-parent-index="<?php echo $index; ?>">
                        <?php
                        if (isset($field['subfields']) && is_array($field['subfields'])) {
                            foreach ($field['subfields'] as $sub_index => $subfield) {
                                $this->render_subfield_row($index, $sub_index, $subfield);
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="button button-small carf-add-subfield add_field_btn" data-parent-index="<?php echo $index; ?>">
                        <span class="dashicons dashicons-plus"></span> <?php _e('Add Sub Field', 'custom-advance-repeater'); ?>
                    </button>
                    <p class="description"><?php _e('Add fields that will appear inside this repeater', 'custom-advance-repeater'); ?></p>
                <?php endif; ?>
            </div>
            
            
        </div>
        <?php
    }

    public function render_subfield_row($parent_index, $sub_index, $subfield = array()) {
        ?>
        <div class="carf-subfield-row" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #f9f9f9;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;" class="panel_row">
                <strong class="top_left_panel"><?php _e('Sub Field', 'custom-advance-repeater'); ?></strong>
                <div class="top_right_panel">
                    <div class="required_checkbox">
                        <label>
                            <input type="checkbox" name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][required]" value="1" <?php checked($subfield['required'] ?? false, true); ?>>
                            <?php _e('Required Field', 'custom-advance-repeater'); ?>
                        </label>
                    </div>
                    <a href="#" class="carf-remove-subfield" style="color: #dc3232; text-decoration: none;">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Remove', 'custom-advance-repeater'); ?>
                    </a>
                </div>
            </div>
            
            <div class="inner_row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">
                <div class="inner_colm">
                    <label><?php _e('Field Type', 'custom-advance-repeater'); ?> *</label>
                    <select name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][type]" class="widefat carf-subfield-type" required>
                        <option value="text" <?php selected($subfield['type'] ?? '', 'text'); ?>><?php _e('Text', 'custom-advance-repeater'); ?></option>
                        <option value="textarea" <?php selected($subfield['type'] ?? '', 'textarea'); ?>><?php _e('Textarea', 'custom-advance-repeater'); ?></option>
                        <option value="image" <?php selected($subfield['type'] ?? '', 'image'); ?>><?php _e('Image Upload', 'custom-advance-repeater'); ?></option>
                        <option value="select" <?php selected($subfield['type'] ?? '', 'select'); ?>><?php _e('Select Dropdown', 'custom-advance-repeater'); ?></option>
                        <option value="checkbox" <?php selected($subfield['type'] ?? '', 'checkbox'); ?>><?php _e('Checkbox', 'custom-advance-repeater'); ?></option>
                        <option value="radio" <?php selected($subfield['type'] ?? '', 'radio'); ?>><?php _e('Radio Buttons', 'custom-advance-repeater'); ?></option>
                        <option value="color" <?php selected($subfield['type'] ?? '', 'color'); ?>><?php _e('Color Picker', 'custom-advance-repeater'); ?></option>
                        <option value="date" <?php selected($subfield['type'] ?? '', 'date'); ?>><?php _e('Date Picker', 'custom-advance-repeater'); ?></option>
                        <option value="repeater" <?php selected($subfield['type'] ?? '', 'repeater'); ?>><?php _e('Repeater Field', 'custom-advance-repeater'); ?></option>
                    </select>
                </div>
                
                <div class="inner_colm">
                    <label><?php _e('Field Label', 'custom-advance-repeater'); ?> *</label>
                    <input type="text" name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][label]" 
                           value="<?php echo esc_attr($subfield['label'] ?? ''); ?>" 
                           class="widefat" required>
                </div>
                
                <div class="inner_colm">
                    <label><?php _e('Field Name', 'custom-advance-repeater'); ?> *</label>
                    <input type="text" name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][name]" 
                           value="<?php echo esc_attr($subfield['name'] ?? ''); ?>" 
                           class="widefat" required>
                    <p class="description"><?php _e('Lowercase, underscores, no spaces', 'custom-advance-repeater'); ?></p>
                </div>
            </div>
            
            <div class="carf-subfield-options" style="display: <?php echo in_array($subfield['type'] ?? '', ['select', 'checkbox', 'radio', 'repeater']) ? 'block' : 'none'; ?>; margin-bottom: 10px;">
                <?php if (in_array($subfield['type'] ?? '', ['select', 'checkbox', 'radio'])): ?>
                    <label><?php _e('Options', 'custom-advance-repeater'); ?></label>
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
                    <textarea name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][options]" class="widefat" rows="5"><?php echo esc_textarea($options_text); ?></textarea>
                    <p class="description"><?php _e('Enter options one per line.', 'custom-advance-repeater'); ?></p>

                <?php elseif (($subfield['type'] ?? '') === 'repeater'): ?>
                    <label><?php _e('Sub Fields', 'custom-advance-repeater'); ?></label>
                    <div class="carf-subfields-container" data-parent-index="<?php echo $parent_index; ?>" data-sub-index="<?php echo $sub_index; ?>">
                        <?php
                        if (isset($subfield['subfields']) && is_array($subfield['subfields'])) {
                            foreach ($subfield['subfields'] as $nested_sub_index => $nested_subfield) {
                                $this->render_nested_subfield_row($parent_index, $sub_index, $nested_sub_index, $nested_subfield);
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="button button-small carf-add-nested-subfield add_field_btn" data-parent-index="<?php echo $parent_index; ?>" data-sub-index="<?php echo $sub_index; ?>">
                        <span class="dashicons dashicons-plus"></span> <?php _e('Add Sub Field', 'custom-advance-repeater'); ?>
                    </button>
                    <p class="description"><?php _e('Add fields that will appear inside this repeater', 'custom-advance-repeater'); ?></p>
                <?php endif; ?>
            </div>
            
            
        </div>
        <?php
    }

    public function render_nested_subfield_row($parent_index, $sub_index, $nested_index, $subfield = array()) {
        ?>
        <div class="carf-nested-subfield-row" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; background: #f0f0f0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;" class="panel_row">
                <strong class="top_left_panel"><?php _e('Nested Sub Field', 'custom-advance-repeater'); ?></strong>
                <div class="top_right_panel">
                     <div class="required_checkbox">
                        <label>
                            <input type="checkbox" name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][subfields][<?php echo $nested_index; ?>][required]" value="1" <?php checked($subfield['required'] ?? false, true); ?>>
                            <?php _e('Required Field', 'custom-advance-repeater'); ?>
                        </label>
                    </div>
                    <a href="#" class="carf-remove-nested-subfield" style="color: #dc3232; text-decoration: none;">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Remove', 'custom-advance-repeater'); ?>
                    </a>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;" class="inner_row">
                <div class="inner_colm">
                    <label><?php _e('Field Type', 'custom-advance-repeater'); ?> *</label>
                    <select name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][subfields][<?php echo $nested_index; ?>][type]" class="widefat carf-nested-subfield-type" required>
                        <option value="text" <?php selected($subfield['type'] ?? '', 'text'); ?>><?php _e('Text', 'custom-advance-repeater'); ?></option>
                        <option value="textarea" <?php selected($subfield['type'] ?? '', 'textarea'); ?>><?php _e('Textarea', 'custom-advance-repeater'); ?></option>
                        <option value="image" <?php selected($subfield['type'] ?? '', 'image'); ?>><?php _e('Image Upload', 'custom-advance-repeater'); ?></option>
                        <option value="select" <?php selected($subfield['type'] ?? '', 'select'); ?>><?php _e('Select Dropdown', 'custom-advance-repeater'); ?></option>
                        <option value="checkbox" <?php selected($subfield['type'] ?? '', 'checkbox'); ?>><?php _e('Checkbox', 'custom-advance-repeater'); ?></option>
                        <option value="radio" <?php selected($subfield['type'] ?? '', 'radio'); ?>><?php _e('Radio Buttons', 'custom-advance-repeater'); ?></option>
                    </select>
                </div>
                
                <div class="inner_colm">
                    <label><?php _e('Field Label', 'custom-advance-repeater'); ?> *</label>
                    <input type="text" name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][subfields][<?php echo $nested_index; ?>][label]" 
                           value="<?php echo esc_attr($subfield['label'] ?? ''); ?>" 
                           class="widefat" required>
                </div>
                
                <div class="inner_colm">
                    <label><?php _e('Field Name', 'custom-advance-repeater'); ?> *</label>
                    <input type="text" name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][subfields][<?php echo $nested_index; ?>][name]" 
                           value="<?php echo esc_attr($subfield['name'] ?? ''); ?>" 
                           class="widefat" required>
                    <p class="description"><?php _e('Lowercase, underscores, no spaces', 'custom-advance-repeater'); ?></p>
                </div>
            </div>
            
            <div class="carf-nested-subfield-options" style="display: <?php echo in_array($subfield['type'] ?? '', ['select', 'checkbox', 'radio']) ? 'block' : 'none'; ?>; margin-bottom: 10px;">
                <?php if (in_array($subfield['type'] ?? '', ['select', 'checkbox', 'radio'])): ?>
                    <label><?php _e('Options', 'custom-advance-repeater'); ?></label>
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
                    <textarea name="fields[<?php echo $parent_index; ?>][subfields][<?php echo $sub_index; ?>][subfields][<?php echo $nested_index; ?>][options]" class="widefat" rows="5"><?php echo esc_textarea($options_text); ?></textarea>
                    <p class="description"><?php _e('Enter options one per line.', 'custom-advance-repeater'); ?></p>
                <?php endif; ?>
            </div>
            
        </div>
        <?php
    }

    public function add_meta_boxes() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'carf_field_groups';
        
        $current_screen = get_current_screen();
        if (!$current_screen) return;
        
        $all_field_groups = $wpdb->get_results("SELECT * FROM $table_name");
        
        foreach ($all_field_groups as $group) {
            if ($this->should_display_field_group($group, get_the_ID())) {
                add_meta_box(
                    'carf_field_group_' . $group->slug,
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
        
        // Access DB class via singleton
        $core = Custom_Advance_Repeater_Core::get_instance();
        $data = $core->db->get_field_data($post->ID, $group->slug);
        
        if (empty($fields)) {
            echo '<p>' . __('No fields configured for this group.', 'custom-advance-repeater') . '</p>';
            return;
        }
        
        $field_values = $core->db->get_single_field_values($post->ID, $group->slug);
        
        ?>
        
        <div class="carf-field-group" data-group="<?php echo esc_attr($group->slug); ?>">
            <input type="hidden" name="carf_field_group[]" value="<?php echo esc_attr($group->slug); ?>">
            <input type="hidden" name="carf_nonce_<?php echo esc_attr($group->slug); ?>" value="<?php echo wp_create_nonce('carf_save_fields_' . $group->slug); ?>">
            
            <div class="carf-vertical-fields">
                <?php foreach ($fields as $field): 
                    $field_name = $field['name'];
                    $field_value = isset($field_values[$field_name]) ? $field_values[$field_name] : '';
                    
                    if ($field['type'] === 'repeater') continue;
                    
                    $input_name = "carf_data[{$group->slug}][{$field_name}]";
                    $input_id = "carf_{$group->slug}_{$field_name}";
                ?>
                    <div class="carf-vertical-field">
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
                    <div class="carf-vertical-field">
                        <label>
                            <?php echo esc_html($field['label']); ?>
                            <?php if ($field['required'] ?? false): ?>
                                <span style="color: #dc3232;">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <div class="carf-nested-repeater" data-field-name="<?php echo esc_attr($field['name']); ?>" data-row-index="0">
                            <table class="carf-repeater-table carf-nested-table" style="margin-top: 0;">
                                <tbody class="carf-nested-tbody">
                                    <?php if (!empty($repeater_data)): ?>
                                        <?php foreach ($repeater_data as $nested_index => $nested_row): ?>
                                            <?php $this->render_nested_repeater_row($subfields, $field['name'], $group->slug, 0, $nested_index, $nested_row); ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <?php $this->render_nested_repeater_row($subfields, $field['name'], $group->slug, 0, '__NESTED_INDEX__', array(), true); ?>
                                </tbody>
                            </table>
                            
                            <div style="padding: 0 15px;">
                                <button type="button" class="button button-small carf-add-nested-row" 
                                    data-field-name="<?php echo esc_attr($field['name']); ?>" 
                                    data-row-index="0">
                                    <span class="dashicons dashicons-plus"></span> <?php _e('Add Row', 'custom-advance-repeater'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
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
                          rows="5"
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
                <div class="carf-file-upload-container" data-field-type="image">
                    <button type="button" class="button carf-upload-button" data-multiple="false">
                        <?php _e('Select Image', 'custom-advance-repeater'); ?>
                    </button>
                    <input type="hidden" name="<?php echo $name; ?>" id="<?php echo $id; ?>" value="<?php echo esc_attr($image_value); ?>" class="carf-file-input">
                    
                    <div class="carf-file-preview">
                        <?php if (!empty($image_value)): 
                            if (is_numeric($image_value)) {
                                $image_url = wp_get_attachment_url($image_value);
                                $image_thumb = wp_get_attachment_image($image_value, 'thumbnail');
                                $filename = basename($image_url);
                            } else {
                                $image_url = $image_value;
                                $image_thumb = '<img src="' . esc_url($image_value) . '" class="carf-image-preview">';
                                $filename = basename($image_value);
                            }
                        ?>
                            <div class="carf-file-item" data-attachment-id="<?php echo esc_attr($image_value); ?>">
                                <?php echo $image_thumb; ?>
                                <div class="carf-file-name"><?php echo esc_html($filename); ?></div>
                                <button type="button" class="carf-remove-file dashicons dashicons-no-alt" title="<?php _e('Remove', 'custom-advance-repeater'); ?>"></button>
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
                    <option value=""><?php _e('-- Select --', 'custom-advance-repeater'); ?></option>
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
                <div class="carf-checkbox-group">
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
                <div class="carf-radio-group">
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
                       class="carf-colorpicker"
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
                       class="carf-datepicker widefat"
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
        $row_class = $is_clone ? 'carf-clone-nested-row' : '';
        $display = $is_clone ? 'style="display: none;"' : '';
        
        $index_name = $is_clone ? '__NESTED_INDEX__' : $nested_index;
        $display_index = $is_clone ? '0' : (is_numeric($nested_index) ? (intval($nested_index) + 1) : '1');
        
        ?>
        <tr class="<?php echo $row_class; ?>" <?php echo $display; ?> data-nested-index="<?php echo $index_name; ?>">
            <td class="carf-row-handle" style="width: 60px; min-width: 60px; max-width: 60px;">
                <span class="dashicons dashicons-menu"></span>
                <span class="nested-row-index"><?php echo $display_index; ?></span>
            </td>
            
            <td style="width: 100%;" colspan="<?php echo count($subfields); ?>">
                <div class="repeater_field_body" style="display: flex; flex-direction: column; border-radius: 8px;">
                    <?php foreach ($subfields as $subfield_index => $subfield): 
                        $subfield_name = $subfield['name'];
                        $field_value = isset($nested_row[$subfield_name]) ? $nested_row[$subfield_name] : '';
                        
                        $input_name = "carf_data[{$group_slug}][{$parent_field_name}][{$index_name}][{$subfield_name}]";
                        $input_id = "carf_{$group_slug}_{$parent_field_name}_{$index_name}_{$subfield_name}";
                    ?>
                        <div class="carf-subfield-container" style=" padding: 15px 0; margin-bottom: <?php echo ($subfield_index === count($subfields) - 1) ? '0' : '15px'; ?>;">
                            <div style="display: flex; gap: 15px; margin-bottom: 15px;" class="carf-inner-row">
                                <div style="flex: 0 0 180px;" class="carf-inner-label">
                                    <label style="font-weight: 600; color: #1e293b; display: block; margin-bottom: 8px;">
                                        <?php echo esc_html($subfield['label']); ?>
                                        <?php if ($subfield['required'] ?? false): ?>
                                            <span style="color: #dc3232;">*</span>
                                        <?php endif; ?>
                                    </label>
                                    
                                </div>
                                
                                <div style="flex: 1;" class="carf-inner-content">
                                    <?php 
                                    if (isset($subfield['type']) && $subfield['type'] === 'repeater') {
                                        $nested_repeater_data = is_array($field_value) ? $field_value : array();
                                        $nested_subfields = isset($subfield['subfields']) ? $subfield['subfields'] : array();
                                        ?>
                                        <div class="carf-nested-repeater" 
                                             data-field-name="<?php echo esc_attr($subfield_name); ?>" 
                                             data-parent-field="<?php echo esc_attr($parent_field_name); ?>"
                                             data-row-index="<?php echo esc_attr($index_name); ?>">
                                            
                                            <div class="carf-nested-table-container" style="overflow-x: auto;">
                                                <table class="carf-repeater-table carf-nested-table" style="margin-top: 10px; width: 100%;">
                                                    <tbody class="carf-nested-tbody">
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
                                            
                                            <button type="button" class="button button-small carf-add-nested2-row" 
                                                    data-field-name="<?php echo esc_attr($subfield_name); ?>" 
                                                    data-parent-field="<?php echo esc_attr($parent_field_name); ?>"
                                                    data-row-index="<?php echo esc_attr($index_name); ?>"
                                                    style="margin-top: 10px;">
                                                <span class="dashicons dashicons-plus"></span> <?php _e('Add Row', 'custom-advance-repeater'); ?>
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
            
            <td class="carf-row-actions" style="width: 40px; min-width: 40px; max-width: 40px;">
                <a href="#" class="carf-remove-nested-row dashicons dashicons-trash" title="<?php _e('Remove Row', 'custom-advance-repeater'); ?>"></a>
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
        
        $row_class = $is_clone ? 'carf-clone-nested2-row' : '';
        $display = $is_clone ? 'style="display: none;"' : '';
        
        $index_name = $is_clone ? '__NESTED2_INDEX__' : $nested2_index;
        $display_index = $is_clone ? '0' : (is_numeric($nested2_index) ? (intval($nested2_index) + 1) : '1');
        
        ?>
        <tr class="<?php echo $row_class; ?>" <?php echo $display; ?> data-nested2-index="<?php echo $index_name; ?>">
            <td class="carf-row-handle" style="width: 50px; min-width: 50px; max-width: 50px;">
                <span class="dashicons dashicons-menu"></span>
                <span class="nested2-row-index"><?php echo $display_index; ?></span>
            </td>
            
            <td style="width: 100%;" colspan="<?php echo count($subfields); ?>">
                <div style="display: flex;flex-direction: column;padding: 0 15px;background: #ffffff;border-radius: 8px;border: 1px solid #e2e8f0;">
                    <?php foreach ($subfields as $subfield_index => $subfield): 
                        $subfield_name = $subfield['name'];
                        $field_value = isset($nested2_row[$subfield_name]) ? $nested2_row[$subfield_name] : '';
                        
                        // Create unique name for nested2 repeater field
                        $input_name = "carf_data[{$group_slug}][{$parent_field_name}][{$parent_row_index}][{$field_name}][{$index_name}][{$subfield_name}]";
                        $input_id = "carf_{$group_slug}_{$parent_field_name}_{$parent_row_index}_{$field_name}_{$index_name}_{$subfield_name}";
                    ?>
                        <div class="carf-subfield-container" style="padding: 14px 0; margin-bottom: <?php echo ($subfield_index === count($subfields) - 1) ? '0' : '0'; ?>;">
                            <div style="display: flex; align-items: flex-start; gap: 15px;" class="carf-inner-row">
                                <div style="flex: 0 0 150px;" class="carf-inner-label">
                                    <label style="font-weight: 600; color: #1e293b; display: block; margin-bottom: 8px;">
                                        <?php echo esc_html($subfield['label']); ?>
                                        <?php if ($subfield['required'] ?? false): ?>
                                            <span style="color: #dc3232;">*</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                
                                <div style="flex: 1;" class="carf-inner-content">
                                    <?php $this->render_field_input($subfield, $input_name, $input_id, $field_value, $index_name, $group_slug, $parent_row_index); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </td>
            
            <td class="carf-row-actions" style="width: 40px; min-width: 40px; max-width: 40px; vertical-align: middle;">
                <a href="#" class="carf-remove-nested2-row dashicons dashicons-trash" title="<?php _e('Remove Row', 'custom-advance-repeater'); ?>"></a>
            </td>
        </tr>
        <?php
    }
}
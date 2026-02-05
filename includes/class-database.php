<?php
if (!defined('ABSPATH')) { exit; }

class Custom_Advance_Repeater_Database {

    public function upgrade_database() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'carf_field_groups';
        
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
        
        $table_name = $wpdb->prefix . 'carf_field_groups';
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            // Trigger activation logic if table missing
            $this->activate();
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
        $table_name = $wpdb->prefix . 'carf_fields';
        
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
        $table_groups = $wpdb->prefix . 'carf_field_groups';
        
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
        
        update_option('carf_version', carf_VERSION);
        update_option('carf_installed', time());
    }

    public function save_field_group() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'carf_field_groups';
        
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
            return __('Group name is required!', 'custom-advance-repeater');
        }
        
        if (empty($slug)) {
            return __('Group slug is required!', 'custom-advance-repeater');
        }
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            $this->activate();
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            if (!$table_exists) {
                return __('Database table does not exist. Please deactivate and reactivate the plugin.', 'custom-advance-repeater');
            }
        }
        
        if (!$group_id) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE slug = %s",
                $slug
            ));
            
            if ($existing) {
                return __('A field group with this slug already exists!', 'custom-advance-repeater');
            }
        } else {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE slug = %s AND id != %d",
                $slug,
                $group_id
            ));
            
            if ($existing) {
                return __('A field group with this slug already exists!', 'custom-advance-repeater');
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

                    // Process options formatting
                    if (in_array($field['type'], ['select', 'checkbox', 'radio']) && !empty($field_data['options'])) {
                        $lines = explode("\n", $field_data['options']);
                        $processed_options = [];
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line)) {
                                if (strpos($line, '|') !== false) {
                                    list($existing_value, $label) = explode('|', $line, 2);
                                    $label = trim($label);
                                } else {
                                    $label = $line;
                                }
                                $value = sanitize_title($label);
                                $processed_options[] = $value . '|' . $label;
                            }
                        }
                        $field_data['options'] = implode("\n", $processed_options);
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

                                // Process subfield options
                                if (in_array($subfield['type'], ['select', 'checkbox', 'radio']) && !empty($subfield_data['options'])) {
                                    $lines = explode("\n", $subfield_data['options']);
                                    $processed_options = [];
                                    foreach ($lines as $line) {
                                        $line = trim($line);
                                        if (!empty($line)) {
                                            if (strpos($line, '|') !== false) {
                                                list($existing_value, $label) = explode('|', $line, 2);
                                                $label = trim($label);
                                            } else {
                                                $label = $line;
                                            }
                                            $value = sanitize_title($label);
                                            $processed_options[] = $value . '|' . $label;
                                        }
                                    }
                                    $subfield_data['options'] = implode("\n", $processed_options);
                                }
                                
                                // Handle nested subfields for nested repeaters
                                if ($subfield['type'] === 'repeater' && isset($subfield['subfields']) && is_array($subfield['subfields'])) {
                                    $nested_subfields = array();
                                    foreach ($subfield['subfields'] as $nested_subfield) {
                                        if (!empty($nested_subfield['label']) && !empty($nested_subfield['name'])) {
                                            $nested_data = array(
                                                'type' => sanitize_text_field($nested_subfield['type']),
                                                'label' => sanitize_text_field($nested_subfield['label']),
                                                'name' => sanitize_text_field($nested_subfield['name']),
                                                'options' => isset($nested_subfield['options']) ? sanitize_textarea_field($nested_subfield['options']) : '',
                                                'required' => isset($nested_subfield['required']) ? true : false
                                            );

                                            // Process nested2 options
                                            if (in_array($nested_subfield['type'], ['select', 'checkbox', 'radio']) && !empty($nested_data['options'])) {
                                                $lines = explode("\n", $nested_data['options']);
                                                $processed_options = [];
                                                foreach ($lines as $line) {
                                                    $line = trim($line);
                                                    if (!empty($line)) {
                                                        if (strpos($line, '|') !== false) {
                                                            list($existing_value, $label) = explode('|', $line, 2);
                                                            $label = trim($label);
                                                        } else {
                                                            $label = $line;
                                                        }
                                                        $value = sanitize_title($label);
                                                        $processed_options[] = $value . '|' . $label;
                                                    }
                                                }
                                                $nested_data['options'] = implode("\n", $processed_options);
                                            }

                                            $nested_subfields[] = $nested_data;
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
            return __('Please add at least one field!', 'custom-advance-repeater');
        }
        
        $field_names = array();
        foreach ($fields as $field) {
            if (in_array($field['name'], $field_names)) {
                return __('Duplicate field name found: ' . $field['name'], 'custom-advance-repeater');
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
                return __('Error updating field group!', 'custom-advance-repeater');
            }
        } else {
            $result = $wpdb->insert($table_name, $data, $format);
            if ($result === false) {
                return __('Error creating field group!', 'custom-advance-repeater');
            }
            $group_id = $wpdb->insert_id;
        }
        
        return true;
    }

    public function get_field_group($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'carf_field_groups';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }

    public function get_field_group_by_slug($slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'carf_field_groups';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE slug = %s", $slug));
    }

    public function save_post_data($post_id, $post, $update) {
        $core = Custom_Advance_Repeater_Core::get_instance();
        
        // Start logging
        $core->log('=============================================');
        $core->log('SAVE_POST_DATA STARTED for Post ID: ' . $post_id);
        $core->log('=============================================');
        
        if (
            !isset($_POST['carf_field_group']) ||
            !is_array($_POST['carf_field_group']) ||
            !isset($_POST['carf_data'])
        ) {
            $core->log('ERROR: No carf_field_group or carf_data in POST');
            return;
        }

        $core->log('POST data keys: ' . print_r(array_keys($_POST), true));
        $core->log('carf_field_group: ' . print_r($_POST['carf_field_group'], true));
        
        if (isset($_POST['carf_data'])) {
            $core->log('carf_data structure preview:');
            foreach ($_POST['carf_data'] as $group => $data) {
                $core->log('  Group: ' . $group);
                if (is_array($data)) {
                    foreach ($data as $field => $value) {
                        if (is_array($value)) {
                            $core->log('    Field: ' . $field . ' (array with ' . count($value) . ' items)');
                        } else {
                            $core->log('    Field: ' . $field . ' = ' . substr(strval($value), 0, 50));
                        }
                    }
                }
            }
        }

        if (
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
            !current_user_can('edit_post', $post_id)
        ) {
            $core->log('ERROR: DOING_AUTOSAVE or no permission');
            return;
        }

        // Verify nonces
        foreach ($_POST['carf_field_group'] as $group_slug) {
            $nonce_key = 'carf_nonce_' . $group_slug;
            if (
                empty($_POST[$nonce_key]) ||
                !wp_verify_nonce($_POST[$nonce_key], 'carf_save_fields_' . $group_slug)
            ) {
                $core->log('ERROR: Nonce verification failed for group: ' . $group_slug);
                return;
            }
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'carf_fields';

        // Get field groups to identify image fields
        $group_table = $wpdb->prefix . 'carf_field_groups';
        $image_fields = [];
        
        foreach ($_POST['carf_field_group'] as $group_slug) {
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

        $core->log('Processing ' . count($_POST['carf_data']) . ' field groups');
        
        // Process each field group
        foreach ($_POST['carf_data'] as $group_slug => $data) {
            $core->log('--- Processing Group: ' . $group_slug . ' ---');
            
            if (!in_array($group_slug, $_POST['carf_field_group'], true)) {
                $core->log('Skipping: Group not in allowed list');
                continue;
            }

            // Get field group configuration
            $group = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$group_table} WHERE slug = %s", $group_slug)
            );
            
            if (!$group) {
                $core->log('ERROR: Group not found in database');
                continue;
            }
            
            $fields = maybe_unserialize($group->fields);
            $repeater_fields = [];
            $nested_repeater_fields = []; // For level 2 nested repeaters
            
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if ($field['type'] === 'repeater') {
                        $repeater_fields[$field['name']] = $field;
                        $core->log('Found repeater field: ' . $field['name']);
                        
                        // Check for nested repeaters inside this repeater
                        if (isset($field['subfields'])) {
                            foreach ($field['subfields'] as $subfield) {
                                if ($subfield['type'] === 'repeater') {
                                    $nested_repeater_fields[$field['name'] . '_' . $subfield['name']] = $subfield;
                                    $core->log('Found nested2 repeater: ' . $field['name'] . '_' . $subfield['name']);
                                }
                            }
                        }
                    }
                }
            }

            // Delete existing data for this group
            $core->log('Deleting existing data for group: ' . $group_slug);
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
            $core->log('Processing single fields...');
            foreach ($data as $field_name => $field_value) {
                // Skip if it's a repeater field
                if (isset($repeater_fields[$field_name])) {
                    $core->log('Skipping repeater field: ' . $field_name);
                    continue;
                }

                // Skip empty image fields
                if (isset($image_fields[$field_name]) && empty($field_value)) {
                    $core->log('Skipping empty image field: ' . $field_name);
                    continue;
                }

                // Skip empty values
                if (
                    $field_value === '' ||
                    $field_value === null ||
                    (is_array($field_value) && empty($field_value))
                ) {
                    $core->log('Skipping empty field: ' . $field_name);
                    continue;
                }

                $core->log('Saving field: ' . $field_name . ' = ' . (is_array($field_value) ? print_r($field_value, true) : $field_value));
                
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
            $core->log('Processing repeater fields...');
            foreach ($repeater_fields as $repeater_name => $repeater_config) {
                $core->log('--- Processing Repeater: ' . $repeater_name . ' ---');
                
                if (!isset($data[$repeater_name]) || !is_array($data[$repeater_name])) {
                    $core->log('No data found for repeater: ' . $repeater_name);
                    continue;
                }

                $repeater_data = $data[$repeater_name];
                $core->log('Raw repeater data keys: ' . print_r(array_keys($repeater_data), true));
                
                // Remove placeholder/empty entries
                $clean_repeater_data = [];
                $row_index = 0;
                
                $core->log('Cleaning repeater data...');
                foreach ($repeater_data as $row_key => $row) {
                    $core->log('Processing row key: ' . $row_key);
                    
                    // Skip if not an array or if it's a placeholder
                    if (!is_array($row) || 
                        $row_key === '__INDEX__' || 
                        $row_key === '__NESTED_INDEX__') {
                        $core->log('  Skipping (placeholder or not array)');
                        continue;
                    }
                    
                    $clean_row = [];
                    $has_content = false;
                    
                    $core->log('  Row content: ' . print_r($row, true));
                    
                    foreach ($row as $field_name => $field_value) {
                        $core->log('    Field: ' . $field_name);
                        
                        // Check if this is a nested2 repeater field
                        $nested2_key = $repeater_name . '_' . $field_name;
                        
                        if (isset($nested_repeater_fields[$nested2_key])) {
                            $core->log('    Detected as nested2 repeater');
                            
                            // Process nested2 repeater data
                            if (is_array($field_value) && !empty($field_value)) {
                                $clean_nested2_data = [];
                                $nested2_row_index = 0;
                                
                                $core->log('    Processing nested2 data with ' . count($field_value) . ' rows');
                                
                                foreach ($field_value as $nested2_key => $nested2_row) {
                                    $core->log('      Nested2 row key: ' . $nested2_key);
                                    
                                    // Skip placeholders
                                    if ($nested2_key === '__NESTED2_INDEX__' || !is_array($nested2_row)) {
                                        $core->log('      Skipping (placeholder or not array)');
                                        continue;
                                    }
                                    
                                    $clean_nested2_row = [];
                                    $nested2_has_content = false;
                                    
                                    $core->log('      Nested2 row content: ' . print_r($nested2_row, true));
                                    
                                    foreach ($nested2_row as $nested2_field_name => $nested2_field_value) {
                                        $core->log('        Nested2 field: ' . $nested2_field_name . ' = ' . $nested2_field_value);
                                        
                                        // Skip empty values but keep zeros and '0'
                                        if (!empty($nested2_field_value) || 
                                            $nested2_field_value === '0' || 
                                            $nested2_field_value === 0 ||
                                            $nested2_field_value === '') {
                                            
                                            $nested2_has_content = true;
                                            $clean_nested2_row[$nested2_field_name] = $nested2_field_value;
                                        } else {
                                            $core->log('        Skipping empty value');
                                        }
                                    }
                                    
                                    if ($nested2_has_content) {
                                        $core->log('      Saving nested2 row at index: ' . $nested2_row_index);
                                        $clean_nested2_data[$nested2_row_index] = $clean_nested2_row;
                                        $nested2_row_index++;
                                    } else {
                                        $core->log('      Skipping empty nested2 row');
                                    }
                                }
                                
                                if (!empty($clean_nested2_data)) {
                                    $has_content = true;
                                    $clean_row[$field_name] = $clean_nested2_data;
                                    $core->log('    Added nested2 data with ' . count($clean_nested2_data) . ' rows');
                                }
                            }
                        } else {
                            // Regular field (not nested2 repeater)
                            $core->log('    Regular field value: ' . $field_value);
                            
                            if (!empty($field_value) || 
                                $field_value === '0' || 
                                $field_value === 0) {
                                $has_content = true;
                                $clean_row[$field_name] = $field_value;
                            } else {
                                $core->log('    Skipping empty value');
                            }
                        }
                    }
                    
                    if ($has_content) {
                        $core->log('  Row has content, adding at index: ' . $row_index);
                        $clean_repeater_data[$row_index] = $clean_row;
                        $row_index++;
                    } else {
                        $core->log('  Row has no content, skipping');
                    }
                }

                $core->log('Clean repeater data has ' . count($clean_repeater_data) . ' rows');
                
                // Save the cleaned repeater data
                if (!empty($clean_repeater_data)) {
                    $core->log('Saving cleaned repeater data to database...');
                    $nested_order = 0;
                    
                    foreach ($clean_repeater_data as $row_index => $row) {
                        $core->log('  Saving parent row index: ' . $row_index . ' as nested_order: ' . $nested_order);
                        
                        $field_counter = 0;
                        foreach ($row as $field_name => $field_value) {
                            $core->log('    Field: ' . $field_name);
                            
                            // Check if this is nested2 repeater data
                            $is_nested2_data = false;
                            $nested2_key = $repeater_name . '_' . $field_name;
                            
                            if (isset($nested_repeater_fields[$nested2_key])) {
                                $is_nested2_data = true;
                                $core->log('    Detected as nested2 repeater data');
                                
                                // Save nested2 repeater data
                                if (is_array($field_value)) {
                                    $nested2_order = 0;
                                    $core->log('    Saving ' . count($field_value) . ' nested2 rows');
                                    
                                    foreach ($field_value as $nested2_row_index => $nested2_row) {
                                        $core->log('      Saving nested2 row index: ' . $nested2_row_index . ' as row_index: ' . $nested2_order);
                                        
                                        $nested2_field_counter = 0;
                                        foreach ($nested2_row as $nested2_field_name => $nested2_field_value) {
                                            $core->log('        Field: ' . $nested2_field_name . ' = ' . $nested2_field_value);
                                            
                                            // Skip empty values but keep zeros and '0'
                                            if (empty($nested2_field_value) && 
                                                $nested2_field_value !== '0' && 
                                                $nested2_field_value !== 0) {
                                                $core->log('        Skipping empty value');
                                                continue;
                                            }
                                            
                                            $core->log('        Inserting into database');
                                            
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
                                $core->log('    Regular nested field value: ' . $field_value);
                                
                                if (empty($field_value) && $field_value !== '0' && $field_value !== 0) {
                                    $core->log('    Skipping empty value');
                                    continue;
                                }
                                
                                $core->log('    Inserting into database');
                                
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
                    
                    $core->log('Saved ' . $nested_order . ' parent rows for repeater: ' . $repeater_name);
                } else {
                    $core->log('No clean repeater data to save');
                }
            }
        }
        
        $core->log('=============================================');
        $core->log('SAVE_POST_DATA COMPLETED for Post ID: ' . $post_id);
        $core->log('=============================================');
    }

    public function get_single_field_values($post_id, $group_slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'carf_fields';
        
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

    public function get_field_data($post_id, $group_slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'carf_fields';

        $group_table = $wpdb->prefix . 'carf_field_groups';
        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $group_table WHERE slug = %s",
            $group_slug
        ));

        // Note: Using core instance to access admin logic safely
        $core = Custom_Advance_Repeater_Core::get_instance();
        if (!$group || !$core->admin->should_display_field_group($group, $post_id)) {
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
                    
                    // Filter empty parent rows
                    $filtered_data = [];
                    foreach ($grouped_data as $row_index => $row_data) {
                        if (empty($row_data)) {
                            continue;
                        }
                        
                        $has_content = false;
                        
                        foreach ($row_data as $field_name => $field_value) {
                            if (is_array($field_value) && isset($field_value[0]) && is_array($field_value[0])) {
                                foreach ($field_value as $nested2_row) {
                                    if (!empty($nested2_row)) {
                                        $has_content = true;
                                        break 2;
                                    }
                                }
                            } 
                            else if (!empty($field_value) || $field_value === '0' || $field_value === 0 || $field_value === false) {
                                $has_content = true;
                                break;
                            }
                        }
                        
                        if ($has_content) {
                            foreach ($row_data as $field_name => &$field_value) {
                                if (is_array($field_value) && isset($field_value[0]) && is_array($field_value[0])) {
                                    $clean_nested2 = [];
                                    foreach ($field_value as $nested2_row) {
                                        if (!empty($nested2_row)) {
                                            $clean_nested2[] = $nested2_row;
                                        }
                                    }
                                    $field_value = $clean_nested2;
                                }
                            }
                            unset($field_value);
                            
                            $filtered_data[] = $row_data;
                        }
                    }
                    
                    $repeater_fields[$repeater_name] = $filtered_data;
                }
            }
        }

        $repeater_fields = array_filter($repeater_fields, function($data) {
            return !empty($data);
        });

        $data = array_merge($single_fields, $repeater_fields);
        
        return $data;
    }

    public function debug_database_state($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'carf_fields';
        
        $core = Custom_Advance_Repeater_Core::get_instance();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE post_id = %d 
             ORDER BY field_group, parent_row_index, row_index, field_order",
            $post_id
        ));
        
        $core->log('=== DATABASE STATE for Post ' . $post_id . ' ===');
        $core->log('Total rows: ' . count($results));
        
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
            $core->log($log_entry);
        }
        
        $core->log('=== END DATABASE STATE ===');
    }
}
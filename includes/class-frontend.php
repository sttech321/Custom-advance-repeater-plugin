<?php
if (!defined('ABSPATH')) { exit; }

class Custom_Advance_Repeater_Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        add_shortcode('carf_repeater', array($this, 'repeater_shortcode'));
    }

    public function frontend_enqueue_scripts() {
        wp_enqueue_style('carf-frontend-css', carf_PLUGIN_URL . 'assets/css/frontend.css', array(), carf_VERSION);
    }

    public function repeater_shortcode($atts) {
        $atts = shortcode_atts(array(
            'field' => '',
            'post_id' => get_the_ID(),
            'limit' => -1,
            'layout' => 'default'
        ), $atts);
        
        if (empty($atts['field'])) {
            return '<div class="carf-error">' . __('Please specify a field name', 'custom-advance-repeater') . '</div>';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'carf_field_groups';
        $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE slug = %s", $atts['field']));
        
        if (!$group) {
            return '<div class="carf-error">' . __('Field group not found!', 'custom-advance-repeater') . '</div>';
        }
        
        // Use Core singleton to access Admin methods for display checks
        $core = Custom_Advance_Repeater_Core::get_instance();
        if (!$core->admin->should_display_field_group($group, $atts['post_id'])) {
            return '';
        }
        
        $data = $core->db->get_field_data($atts['post_id'], $atts['field']);
        
        if (empty($data)) {
            return '';
        }
        
        $fields = maybe_unserialize($group->fields);
        
        ob_start();
        ?>
        <div class="carf-frontend-output">
            <div class="carf-frontend-field-group">
                <h3><?php echo esc_html($group->name); ?></h3>
                
                <?php foreach ($fields as $field): 
                    $field_name = $field['name'];
                    $field_value = isset($data[$field_name]) ? $data[$field_name] : '';
                    
                    if (empty($field_value) && $field_value !== '0' && $field_value !== 0) {
                        continue;
                    }
                    
                    if ($field['type'] === 'repeater') {
                        ?>
                        <div class="carf-frontend-field carf-nested-repeater-field">
                            <div class="carf-frontend-label"><?php echo esc_html($field['label']); ?></div>
                            <div class="carf-frontend-nested-repeater">
                                <?php 
                                if (is_array($field_value)) {
                                    foreach ($field_value as $nested_index => $nested_row) {
                                        ?>
                                        <div class="carf-frontend-nested-row">
                                            <?php 
                                            $subfields = $field['subfields'] ?? array();
                                            foreach ($subfields as $subfield) {
                                                $subfield_value = isset($nested_row[$subfield['name']]) ? $nested_row[$subfield['name']] : '';
                                                if (empty($subfield_value) && $subfield_value !== '0' && $subfield_value !== 0) {
                                                    continue;
                                                }
                                                ?>
                                                <div class="carf-frontend-nested-field">
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
                        <div class="carf-frontend-field">
                            <div class="carf-frontend-label"><?php echo esc_html($field['label']); ?></div>
                            <div class="carf-frontend-value">
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
                $result = [];

                if (!empty($options_string)) {
                    $lines = explode("\n", $options_string);
                    $options_map = [];

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

                    foreach ($value as $val) {
                        $val = trim($val);
                        $result[] = $options_map[$val] ?? $val;
                    }

                    return $result; // ✅ ARRAY
                }

                return $value; // ✅ ARRAY
            }

            $value = !empty($value) ? reset($value) : '';
        }

        
        if (!in_array($field_type, ['select', 'checkbox', 'radio'])) {
            if ($field_type === 'image') {
                // Just return the ID or value as text
                return esc_html($value);
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
}
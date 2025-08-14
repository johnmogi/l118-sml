<?php
/**
 * Admin Tools and Repair Utilities
 * 
 * Provides maintenance and repair tools for the School Manager Lite plugin
 * 
 * @package School_Manager_Lite
 * @since 1.2.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class School_Manager_Lite_Admin_Tools {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_repair_learndash_groups', array($this, 'ajax_repair_learndash_groups'));
        add_action('wp_ajax_repair_class_data', array($this, 'ajax_repair_class_data'));
    }
    
    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_submenu_page(
            'school-manager-lite',
            __('Advanced Tools', 'school-manager-lite'),
            __('Advanced Tools', 'school-manager-lite'),
            'manage_options',
            'school-manager-tools',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin tools page
     */
    public function admin_page() {
        global $wpdb;
        
        ?>
        <div class="wrap">
            <h1><?php _e('Advanced Tools & Repair', 'school-manager-lite'); ?></h1>
            
            <div class="notice notice-warning">
                <p><strong><?php _e('Warning:', 'school-manager-lite'); ?></strong> <?php _e('These tools perform database operations. Use with caution and always backup your database first.', 'school-manager-lite'); ?></p>
            </div>
            
            <div class="postbox-container" style="width: 100%;">
                
                <!-- LearnDash Groups Repair -->
                <div class="postbox">
                    <h2 class="hndle"><span><?php _e('LearnDash Groups Repair', 'school-manager-lite'); ?></span></h2>
                    <div class="inside">
                        <p><?php _e('Fix classes that are missing LearnDash groups. This will create groups, assign teachers as leaders, and enroll students.', 'school-manager-lite'); ?></p>
                        
                        <?php
                        // Check classes without groups
                        $classes_without_groups = $wpdb->get_results("
                            SELECT c.id, c.name, c.teacher_id, c.course_id 
                            FROM edc_school_classes c
                            WHERE c.group_id IS NULL OR c.group_id = 0 OR c.group_id = ''
                        ");
                        
                        if (empty($classes_without_groups)) {
                            echo '<div class="notice notice-success inline"><p>' . __('All classes have proper LearnDash groups!', 'school-manager-lite') . '</p></div>';
                        } else {
                            echo '<div class="notice notice-info inline"><p>' . sprintf(__('Found %d classes without LearnDash groups.', 'school-manager-lite'), count($classes_without_groups)) . '</p></div>';
                            
                            echo '<table class="wp-list-table widefat fixed striped">';
                            echo '<thead><tr>';
                            echo '<th>' . __('Class ID', 'school-manager-lite') . '</th>';
                            echo '<th>' . __('Class Name', 'school-manager-lite') . '</th>';
                            echo '<th>' . __('Teacher', 'school-manager-lite') . '</th>';
                            echo '</tr></thead><tbody>';
                            
                            foreach ($classes_without_groups as $class) {
                                echo '<tr>';
                                echo '<td>' . esc_html($class->id) . '</td>';
                                echo '<td>' . esc_html($class->name) . '</td>';
                                echo '<td>';
                                if ($class->teacher_id) {
                                    $teacher = get_user_by('id', $class->teacher_id);
                                    echo $teacher ? esc_html($teacher->display_name) : __('Unknown', 'school-manager-lite');
                                } else {
                                    echo __('No teacher assigned', 'school-manager-lite');
                                }
                                echo '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                        }
                        ?>
                        
                        <p>
                            <button type="button" class="button button-primary" id="repair-learndash-groups" <?php echo empty($classes_without_groups) ? 'disabled' : ''; ?>>
                                <?php _e('Repair LearnDash Groups', 'school-manager-lite'); ?>
                            </button>
                            <span class="spinner" style="float: none; margin-left: 10px;"></span>
                        </p>
                        
                        <div id="learndash-repair-results" style="margin-top: 20px;"></div>
                    </div>
                </div>
                
                <!-- Class Data Repair -->
                <div class="postbox">
                    <h2 class="hndle"><span><?php _e('Class Data Repair', 'school-manager-lite'); ?></span></h2>
                    <div class="inside">
                        <p><?php _e('Fix class data inconsistencies and refresh class listings.', 'school-manager-lite'); ?></p>
                        
                        <?php
                        // Check class data issues
                        $total_classes = $wpdb->get_var("SELECT COUNT(*) FROM edc_school_classes");
                        $classes_with_teachers = $wpdb->get_var("SELECT COUNT(*) FROM edc_school_classes WHERE teacher_id IS NOT NULL AND teacher_id != 0");
                        $classes_with_students = $wpdb->get_var("
                            SELECT COUNT(DISTINCT c.id) 
                            FROM edc_school_classes c
                            JOIN edc_school_student_classes sc ON c.id = sc.class_id
                        ");
                        ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Total Classes:', 'school-manager-lite'); ?></th>
                                <td><?php echo esc_html($total_classes); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Classes with Teachers:', 'school-manager-lite'); ?></th>
                                <td><?php echo esc_html($classes_with_teachers); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Classes with Students:', 'school-manager-lite'); ?></th>
                                <td><?php echo esc_html($classes_with_students); ?></td>
                            </tr>
                        </table>
                        
                        <p>
                            <button type="button" class="button button-secondary" id="repair-class-data">
                                <?php _e('Refresh Class Data', 'school-manager-lite'); ?>
                            </button>
                            <span class="spinner" style="float: none; margin-left: 10px;"></span>
                        </p>
                        
                        <div id="class-repair-results" style="margin-top: 20px;"></div>
                    </div>
                </div>
                
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // LearnDash Groups Repair
            $('#repair-learndash-groups').on('click', function() {
                var $btn = $(this);
                var $spinner = $btn.siblings('.spinner');
                var $results = $('#learndash-repair-results');
                
                $btn.prop('disabled', true);
                $spinner.addClass('is-active');
                $results.empty();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'repair_learndash_groups',
                        nonce: '<?php echo wp_create_nonce('repair_learndash_groups'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $results.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $results.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $results.html('<div class="notice notice-error"><p><?php _e('An error occurred while repairing groups.', 'school-manager-lite'); ?></p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            });
            
            // Class Data Repair
            $('#repair-class-data').on('click', function() {
                var $btn = $(this);
                var $spinner = $btn.siblings('.spinner');
                var $results = $('#class-repair-results');
                
                $btn.prop('disabled', true);
                $spinner.addClass('is-active');
                $results.empty();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'repair_class_data',
                        nonce: '<?php echo wp_create_nonce('repair_class_data'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $results.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            $results.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $results.html('<div class="notice notice-error"><p><?php _e('An error occurred while repairing class data.', 'school-manager-lite'); ?></p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            });
        });
        </script>
        
        <style>
        .postbox { margin-bottom: 20px; }
        .postbox .inside { padding: 15px; }
        .notice.inline { margin: 15px 0; }
        </style>
        <?php
    }
    
    /**
     * AJAX handler to repair LearnDash groups
     */
    public function ajax_repair_learndash_groups() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'repair_learndash_groups')) {
            wp_die(__('Security check failed', 'school-manager-lite'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'school-manager-lite')));
        }
        
        // Check if LearnDash is active
        if (!function_exists('learndash_get_groups')) {
            wp_send_json_error(array('message' => __('LearnDash is not active', 'school-manager-lite')));
        }
        
        global $wpdb;
        
        // Get classes without proper LearnDash groups
        $classes_without_groups = $wpdb->get_results("
            SELECT c.id, c.name, c.teacher_id, c.course_id 
            FROM edc_school_classes c
            WHERE c.group_id IS NULL OR c.group_id = 0 OR c.group_id = ''
        ");
        
        $fixed_count = 0;
        $errors = array();
        
        foreach ($classes_without_groups as $class) {
            try {
                // Create LearnDash group
                $group_name = sprintf(__('Class: %s', 'school-manager-lite'), $class->name);
                $group_id = wp_insert_post(array(
                    'post_title' => $group_name,
                    'post_type' => 'groups',
                    'post_status' => 'publish',
                    'post_author' => $class->teacher_id ? $class->teacher_id : get_current_user_id()
                ));
                
                if (is_wp_error($group_id)) {
                    $errors[] = sprintf(__('Failed to create group for class %s: %s', 'school-manager-lite'), $class->name, $group_id->get_error_message());
                    continue;
                }
                
                // Link group to class
                update_post_meta($group_id, 'school_manager_class_id', $class->id);
                
                // Add course to group if class has a course
                if (!empty($class->course_id) && function_exists('learndash_set_group_enrolled_courses')) {
                    learndash_set_group_enrolled_courses($group_id, array($class->course_id));
                }
                
                // Update class with group ID
                $wpdb->update(
                    'edc_school_classes',
                    array('group_id' => $group_id),
                    array('id' => $class->id),
                    array('%d'),
                    array('%d')
                );
                
                // Assign teacher as group leader
                if ($class->teacher_id && function_exists('ld_update_leader_group_access')) {
                    ld_update_leader_group_access($class->teacher_id, $group_id, false);
                }
                
                // Add existing students to the group
                $students = $wpdb->get_results($wpdb->prepare("
                    SELECT sc.student_id, s.user_id
                    FROM edc_school_student_classes sc
                    JOIN edc_school_students s ON sc.student_id = s.id
                    WHERE sc.class_id = %d
                ", $class->id));
                
                foreach ($students as $student) {
                    if ($student->user_id && function_exists('ld_update_group_access')) {
                        ld_update_group_access($student->user_id, $group_id, false);
                    }
                }
                
                $fixed_count++;
                
            } catch (Exception $e) {
                $errors[] = sprintf(__('Error fixing class %s: %s', 'school-manager-lite'), $class->name, $e->getMessage());
            }
        }
        
        $message = sprintf(__('Successfully repaired %d classes.', 'school-manager-lite'), $fixed_count);
        if (!empty($errors)) {
            $message .= ' ' . __('Errors:', 'school-manager-lite') . ' ' . implode(', ', $errors);
        }
        
        wp_send_json_success(array('message' => $message, 'fixed_count' => $fixed_count));
    }
    
    /**
     * AJAX handler to repair class data
     */
    public function ajax_repair_class_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'repair_class_data')) {
            wp_die(__('Security check failed', 'school-manager-lite'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'school-manager-lite')));
        }
        
        // Clear any cached class data
        wp_cache_flush();
        
        // Refresh transients
        delete_transient('school_manager_classes_list');
        delete_transient('school_manager_teachers_list');
        delete_transient('school_manager_students_list');
        
        wp_send_json_success(array('message' => __('Class data refreshed successfully.', 'school-manager-lite')));
    }
}

// Initialize the admin tools
School_Manager_Lite_Admin_Tools::instance();

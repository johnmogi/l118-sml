<?php
/**
 * LearnDash Group Fix Utility
 * 
 * Fixes empty LearnDash groups for existing classes and ensures proper integration
 * 
 * @package School_Manager_Lite
 * @since 1.2.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class School_Manager_Lite_LearnDash_Fix {
    
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
        add_action('wp_ajax_fix_learndash_groups', array($this, 'ajax_fix_groups'));
    }
    
    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_submenu_page(
            'school-manager-lite',
            __('Fix LearnDash Groups', 'school-manager-lite'),
            __('Fix Groups', 'school-manager-lite'),
            'manage_options',
            'fix-learndash-groups',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page for fixing groups
     */
    public function admin_page() {
        global $wpdb;
        
        // Get classes without proper LearnDash groups
        $classes_without_groups = $wpdb->get_results("
            SELECT c.id, c.name, c.teacher_id, c.course_id 
            FROM {$wpdb->prefix}edc_school_classes c
            WHERE c.group_id IS NULL OR c.group_id = 0 OR c.group_id = ''
        ");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Fix LearnDash Groups', 'school-manager-lite'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('This tool will create LearnDash groups for classes that don\'t have them and assign teachers and students properly.', 'school-manager-lite'); ?></p>
            </div>
            
            <?php if (empty($classes_without_groups)): ?>
                <div class="notice notice-success">
                    <p><?php _e('All classes have proper LearnDash groups assigned!', 'school-manager-lite'); ?></p>
                </div>
            <?php else: ?>
                <div class="card">
                    <h2><?php _e('Classes Missing LearnDash Groups', 'school-manager-lite'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Class ID', 'school-manager-lite'); ?></th>
                                <th><?php _e('Class Name', 'school-manager-lite'); ?></th>
                                <th><?php _e('Teacher', 'school-manager-lite'); ?></th>
                                <th><?php _e('Course ID', 'school-manager-lite'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes_without_groups as $class): ?>
                                <tr>
                                    <td><?php echo esc_html($class->id); ?></td>
                                    <td><?php echo esc_html($class->name); ?></td>
                                    <td>
                                        <?php 
                                        if ($class->teacher_id) {
                                            $teacher = get_user_by('id', $class->teacher_id);
                                            echo $teacher ? esc_html($teacher->display_name) : __('Unknown', 'school-manager-lite');
                                        } else {
                                            echo __('No teacher assigned', 'school-manager-lite');
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $class->course_id ? esc_html($class->course_id) : __('None', 'school-manager-lite'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p>
                        <button type="button" class="button button-primary" id="fix-groups-btn">
                            <?php _e('Fix All Groups', 'school-manager-lite'); ?>
                        </button>
                        <span class="spinner" style="float: none; margin-left: 10px;"></span>
                    </p>
                    
                    <div id="fix-results" style="margin-top: 20px;"></div>
                </div>
            <?php endif; ?>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#fix-groups-btn').on('click', function() {
                var $btn = $(this);
                var $spinner = $('.spinner');
                var $results = $('#fix-results');
                
                $btn.prop('disabled', true);
                $spinner.addClass('is-active');
                $results.empty();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fix_learndash_groups',
                        nonce: '<?php echo wp_create_nonce('fix_learndash_groups'); ?>'
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
                        $results.html('<div class="notice notice-error"><p><?php _e('An error occurred while fixing groups.', 'school-manager-lite'); ?></p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler to fix groups
     */
    public function ajax_fix_groups() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fix_learndash_groups')) {
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
            FROM {$wpdb->prefix}edc_school_classes c
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
                    $wpdb->prefix . 'edc_school_classes',
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
                    SELECT sc.student_id, u.ID as user_id
                    FROM {$wpdb->prefix}edc_school_student_classes sc
                    JOIN {$wpdb->prefix}edc_school_students s ON sc.student_id = s.id
                    JOIN {$wpdb->prefix}users u ON s.user_id = u.ID
                    WHERE sc.class_id = %d
                ", $class->id));
                
                foreach ($students as $student) {
                    if (function_exists('ld_update_group_access')) {
                        ld_update_group_access($student->user_id, $group_id, false);
                    }
                }
                
                $fixed_count++;
                
            } catch (Exception $e) {
                $errors[] = sprintf(__('Error fixing class %s: %s', 'school-manager-lite'), $class->name, $e->getMessage());
            }
        }
        
        $message = sprintf(__('Fixed %d classes successfully.', 'school-manager-lite'), $fixed_count);
        if (!empty($errors)) {
            $message .= ' ' . __('Errors:', 'school-manager-lite') . ' ' . implode(', ', $errors);
        }
        
        wp_send_json_success(array('message' => $message, 'fixed_count' => $fixed_count));
    }
}

// Initialize the fix utility
School_Manager_Lite_LearnDash_Fix::instance();

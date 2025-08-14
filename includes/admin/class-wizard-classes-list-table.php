<?php
/**
 * Wizard Classes List Table Class
 *
 * @package School_Manager_Lite
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load WP_List_Table if not loaded
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * School_Manager_Lite_Wizard_Classes_List_Table class
 * 
 * Extends WordPress WP_List_Table class to provide a custom table for class selection in wizard
 */
class School_Manager_Lite_Wizard_Classes_List_Table extends WP_List_Table {

    /**
     * Selected class ID
     */
    private $selected_class_id = 0;
    
    /**
     * Teacher ID filter
     */
    private $teacher_id = 0;

    /**
     * Class Constructor
     */
    public function __construct($teacher_id = 0) {
        parent::__construct(array(
            'singular' => 'wizard_class',
            'plural'   => 'wizard_classes',
            'ajax'     => false
        ));

        $this->selected_class_id = isset($_REQUEST['class_id']) ? intval($_REQUEST['class_id']) : 0;
        $this->teacher_id = $teacher_id;
    }

    /**
     * Get columns
     */
    public function get_columns() {
        $columns = array(
            'cb'            => '<input type="checkbox" />',
            'name'          => __('Class Name', 'school-manager-lite'),
            'description'   => __('Description', 'school-manager-lite'),
            'teacher'       => __('Teacher', 'school-manager-lite'),
            'students'      => __('Students', 'school-manager-lite'),
            'date_created'  => __('Created', 'school-manager-lite')
        );

        return $columns;
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'name'         => array('name', false),
            'date_created' => array('created_at', true) // true means it's already sorted
        );
        return $sortable_columns;
    }

    /**
     * Column default
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'description':
                return !empty($item->description) ? esc_html($item->description) : '—';
            case 'date_created':
                return date_i18n(get_option('date_format'), strtotime($item->created_at));
            case 'teacher':
                if (empty($item->teacher_id)) {
                    return '—';
                }
                $teacher = get_user_by('id', $item->teacher_id);
                return $teacher ? esc_html($teacher->display_name) : __('Unknown Teacher', 'school-manager-lite');
            case 'students':
                return sprintf(
                    _n('%d student', '%d students', $item->student_count, 'school-manager-lite'),
                    $item->student_count
                );
            default:
                return isset($item->$column_name) ? $item->$column_name : '';
        }
    }

    /**
     * Column cb
     */
    public function column_cb($item) {
        $checked = $this->selected_class_id == $item->id ? ' checked="checked"' : '';
        return sprintf(
            '<input type="radio" name="class_id" value="%s"%s />',
            $item->id,
            $checked
        );
    }

    /**
     * Column name
     */
    public function column_name($item) {
        return sprintf(
            '<label for="class-%s"><strong>%s</strong></label>',
            $item->id,
            esc_html($item->name)
        );
    }

    /**
     * Column teacher
     */
    public function column_teacher($item) {
        if (empty($item->teacher_id)) {
            return '—';
        }

        $teacher = get_user_by('id', $item->teacher_id);
        if ($teacher) {
            return esc_html($teacher->display_name);
        }

        return __('Unknown Teacher', 'school-manager-lite');
    }

    /**
     * Column students
     */
    public function column_students($item) {
        $student_manager = School_Manager_Lite_Student_Manager::instance();
        $students = $student_manager->get_students(array('class_id' => $item->id));
        
        $count = is_array($students) ? count($students) : 0;
        
        return sprintf(
            _n('%d student', '%d students', $count, 'school-manager-lite'),
            $count
        );
    }

    /**
     * Prepare items
     */
    public function prepare_items() {
        global $wpdb;
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        // Column headers
        $this->_column_headers = array($columns, $hidden, $sortable);

        // Handle search
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        
        // Handle sorting
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'name';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'ASC';
        
        // Build the query to get school classes from database
        $where_clauses = array();
        $query_params = array();
        
        // Add search filter
        if (!empty($search)) {
            $where_clauses[] = "(c.name LIKE %s OR c.description LIKE %s)";
            $query_params[] = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        // Add teacher filter
        if ($this->teacher_id > 0) {
            $where_clauses[] = "c.teacher_id = %d";
            $query_params[] = $this->teacher_id;
        }
        
        // Build WHERE clause
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Validate orderby
        $allowed_orderby = array('name', 'created_at', 'id');
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'name';
        }
        
        // Validate order
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        
        // Get classes with student count - using direct table names that we know exist
        $sql = "
            SELECT 
                c.id,
                c.name,
                c.description,
                c.teacher_id,
                c.created_at,
                COALESCE(COUNT(sc.student_id), 0) as student_count
            FROM edc_school_classes c
            LEFT JOIN edc_school_student_classes sc ON c.id = sc.class_id
            {$where_sql}
            GROUP BY c.id, c.name, c.description, c.teacher_id, c.created_at
            ORDER BY c.{$orderby} {$order}
        ";
        
        // Execute query with proper error handling
        if (!empty($query_params)) {
            $classes = $wpdb->get_results($wpdb->prepare($sql, $query_params));
        } else {
            $classes = $wpdb->get_results($sql);
        }
        
        // Handle database errors
        if ($wpdb->last_error) {
            // Fallback: try with WordPress prefix format
            $fallback_sql = str_replace('edc_school_classes', $wpdb->prefix . 'school_classes', $sql);
            $fallback_sql = str_replace('edc_school_student_classes', $wpdb->prefix . 'school_student_classes', $fallback_sql);
            
            if (!empty($query_params)) {
                $classes = $wpdb->get_results($wpdb->prepare($fallback_sql, $query_params));
            } else {
                $classes = $wpdb->get_results($fallback_sql);
            }
        }
        
        // Ensure we have an array
        if (!is_array($classes)) {
            $classes = array();
        }
        
        // Format the classes to match expected format
        $formatted_classes = array();
        foreach ($classes as $class) {
            $formatted_class = new stdClass();
            $formatted_class->id = (int) $class->id;
            $formatted_class->name = sanitize_text_field($class->name);
            $formatted_class->description = sanitize_textarea_field($class->description);
            $formatted_class->teacher_id = (int) $class->teacher_id;
            $formatted_class->created_at = $class->created_at;
            $formatted_class->student_count = (int) $class->student_count;
            
            $formatted_classes[] = $formatted_class;
        }
        
        $this->items = $formatted_classes;
    }

    /**
     * Display no items message
     */
    public function no_items() {
        _e('No classes found.', 'school-manager-lite');
    }
}

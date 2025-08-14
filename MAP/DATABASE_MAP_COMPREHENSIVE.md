# School Management System - Database Map

## Table of Contents
1. [Overview](#overview)
2. [Core Tables](#core-tables)
3. [WordPress Integration](#wordpress-integration)
4. [Data Flow](#data-flow)
5. [CRUD Operations](#crud-operations)
6. [Stored Procedures](#stored-procedures)
7. [Data Integrity Issues](#data-integrity-issues)
8. [Recommended Solutions](#recommended-solutions)
9. [Performance Optimization](#performance-optimization)
10. [Security Considerations](#security-considerations)

## Overview

The School Management System is built on WordPress and uses custom tables to manage:
- **Classes/Courses**: Educational content containers
- **Students**: Learners enrolled in classes
- **Teachers**: Instructors assigned to classes
- **Enrollments**: Student-class relationships
- **Promo Codes**: Access codes for class enrollment

## Core Tables

### 1. `edc_school_classes`
**Purpose**: Stores class/course information

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | BIGINT | Primary key | AUTO_INCREMENT, NOT NULL |
| `name` | VARCHAR(255) | Class name | NOT NULL |
| `description` | TEXT | Class description | NULL |
| `teacher_id` | BIGINT | References WordPress user ID | NOT NULL |
| `group_id` | BIGINT | LearnDash group ID | NULL |
| `course_id` | BIGINT | LearnDash course ID | NULL |
| `created_at` | DATETIME | Creation timestamp | DEFAULT CURRENT_TIMESTAMP |
| `updated_at` | DATETIME | Last update timestamp | ON UPDATE CURRENT_TIMESTAMP |

**Relationships**:
- One-to-Many with `edc_users` (teacher)
- One-to-Many with `edc_school_student_classes` (enrollments)
- One-to-Many with `edc_school_promo_codes`

### 2. `edc_school_students`
**Purpose**: Stores student information

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | BIGINT | Primary key | AUTO_INCREMENT, NOT NULL |
| `wp_user_id` | BIGINT | Links to WordPress user | NOT NULL |
| `name` | VARCHAR(255) | Student name | NOT NULL |
| `email` | VARCHAR(100) | Student email | NULL |
| `created_at` | DATETIME | Creation timestamp | DEFAULT CURRENT_TIMESTAMP |

**Relationships**:
- Many-to-One with `edc_users` (WordPress user)
- Many-to-Many with `edc_school_classes` through `edc_school_student_classes`

### 3. `edc_school_student_classes`
**Purpose**: Junction table for student enrollments

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | BIGINT | Primary key | AUTO_INCREMENT, NOT NULL |
| `student_id` | BIGINT | References student | NOT NULL |
| `class_id` | BIGINT | References class | NOT NULL |
| `created_at` | DATETIME | Enrollment timestamp | DEFAULT CURRENT_TIMESTAMP |

**Relationships**:
- Many-to-One with `edc_school_students`
- Many-to-One with `edc_school_classes`

### 4. `edc_school_promo_codes`
**Purpose**: Manages promo codes for class access

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | BIGINT | Primary key | AUTO_INCREMENT, NOT NULL |
| `code` | VARCHAR(50) | Unique promo code | UNIQUE, NOT NULL |
| `prefix` | VARCHAR(10) | Code prefix | NULL |
| `class_id` | BIGINT | References class | NOT NULL |
| `teacher_id` | BIGINT | References teacher | NOT NULL |
| `expiry_date` | DATETIME | Code expiration | NULL |
| `student_id` | BIGINT | Assigned student (optional) | NULL |
| `used_at` | DATETIME | Usage timestamp | NULL |
| `usage_limit` | INT | Maximum uses | DEFAULT 1 |
| `used_count` | INT | Current usage count | DEFAULT 0 |
| `created_at` | DATETIME | Creation timestamp | DEFAULT CURRENT_TIMESTAMP |

**Relationships**:
- Many-to-One with `edc_school_classes`
- Many-to-One with `edc_users` (teacher)
- Many-to-One with `edc_school_students` (optional)

## WordPress Integration

### 1. `edc_users`
**Purpose**: WordPress users table

| Column | Type | Description |
|--------|------|-------------|
| `ID` | BIGINT | Primary key |
| `user_login` | VARCHAR(60) | Username |
| `user_email` | VARCHAR(100) | Email address |
| `user_pass` | VARCHAR(255) | Password hash |
| `user_registered` | DATETIME | Registration timestamp |
| `user_status` | INT | User status |

### 2. `edc_usermeta`
**Purpose**: WordPress user metadata

| Column | Type | Description |
|--------|------|-------------|
| `umeta_id` | BIGINT | Primary key |
| `user_id` | BIGINT | Links to users.ID |
| `meta_key` | VARCHAR(255) | Metadata key |
| `meta_value` | LONGTEXT | Metadata value |

## Data Flow

### User Registration Flow
```
User Registration → Create WordPress User → Assign User Role → 
Student Role? → Create Student Record → Ready for Enrollment
Teacher Role? → Teacher Capabilities Added → Ready to Create Classes
```

### Class Enrollment Flow
```
Teacher Creates Class → Class Record Created → Promo Codes Generated → 
Students Use Promo Code → Enrollment Record Created → Student Access Granted
```

## CRUD Operations

### Create Operations

#### Create Class
```sql
INSERT INTO edc_school_classes (name, description, teacher_id, created_at, updated_at)
VALUES ('Advanced Mathematics', 'Calculus and Linear Algebra', 2, NOW(), NOW());
```

#### Enroll Student
```sql
INSERT INTO edc_school_student_classes (student_id, class_id, created_at)
VALUES (1, 1, NOW());
```

#### Generate Promo Code
```sql
INSERT INTO edc_school_promo_codes 
    (code, class_id, teacher_id, usage_limit, used_count, expiry_date, created_at)
VALUES 
    ('MATH2024', 1, 2, 25, 0, '2024-12-31 23:59:59', NOW());
```

### Read Operations

#### Get Classes with Teacher and Student Count
```sql
SELECT 
    c.id AS class_id,
    c.name AS class_name,
    c.description,
    COALESCE(u.user_login, 'Unassigned') AS teacher_username,
    COALESCE(um1.meta_value, '') AS teacher_first_name,
    COALESCE(um2.meta_value, '') AS teacher_last_name,
    COUNT(DISTINCT sc.student_id) AS student_count,
    c.created_at
FROM edc_school_classes c
LEFT JOIN edc_users u ON c.teacher_id = u.ID
LEFT JOIN edc_usermeta um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
LEFT JOIN edc_usermeta um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
LEFT JOIN edc_school_student_classes sc ON c.id = sc.class_id
GROUP BY c.id, c.name, c.description, u.user_login, um1.meta_value, um2.meta_value, c.created_at
ORDER BY c.name;
```

#### Get Students in a Class with Details
```sql
SELECT 
    s.id AS student_id,
    s.name AS student_name,
    s.email AS student_email,
    u.user_login AS username,
    sc.created_at AS enrollment_date,
    DATEDIFF(NOW(), sc.created_at) AS days_enrolled
FROM edc_school_students s
JOIN edc_school_student_classes sc ON s.id = sc.student_id
JOIN edc_users u ON s.wp_user_id = u.ID
WHERE sc.class_id = ?
ORDER BY sc.created_at DESC;
```

#### Get Promo Code Usage Statistics
```sql
SELECT 
    pc.code,
    pc.usage_limit,
    pc.used_count,
    (pc.usage_limit - pc.used_count) AS remaining_uses,
    CASE 
        WHEN pc.expiry_date < NOW() THEN 'Expired'
        WHEN pc.used_count >= pc.usage_limit THEN 'Exhausted'
        ELSE 'Active'
    END AS status,
    c.name AS class_name,
    u.user_login AS teacher_username
FROM edc_school_promo_codes pc
JOIN edc_school_classes c ON pc.class_id = c.id
JOIN edc_users u ON pc.teacher_id = u.ID
ORDER BY pc.created_at DESC;
```

### Update Operations

#### Update Class Information
```sql
UPDATE edc_school_classes 
SET 
    name = ?,
    description = ?,
    teacher_id = ?,
    updated_at = NOW() 
WHERE id = ?;
```

#### Transfer Student Between Classes
```sql
-- Remove from old class
DELETE FROM edc_school_student_classes 
WHERE student_id = ? AND class_id = ?;

-- Add to new class
INSERT INTO edc_school_student_classes (student_id, class_id, created_at)
VALUES (?, ?, NOW());
```

### Delete Operations

#### Remove Student from Class
```sql
DELETE FROM edc_school_student_classes 
WHERE student_id = ? AND class_id = ?;
```

#### Safe Class Deletion
```sql
-- Check for enrollments first
SELECT COUNT(*) as enrollment_count 
FROM edc_school_student_classes 
WHERE class_id = ?;

-- Delete only if no enrollments
DELETE FROM edc_school_classes 
WHERE id = ? 
AND NOT EXISTS (
    SELECT 1 FROM edc_school_student_classes 
    WHERE class_id = ?
);
```

## Stored Procedures

### Enroll Student with Promo Code
```sql
DELIMITER //
CREATE PROCEDURE EnrollStudentWithPromo(
    IN p_student_id BIGINT,
    IN p_promo_code VARCHAR(50)
)
BEGIN
    DECLARE v_class_id BIGINT;
    DECLARE v_usage_limit INT;
    DECLARE v_used_count INT;
    DECLARE v_expiry_date DATETIME;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION ROLLBACK;
    
    START TRANSACTION;
    
    -- Get promo code details
    SELECT class_id, usage_limit, used_count, expiry_date
    INTO v_class_id, v_usage_limit, v_used_count, v_expiry_date
    FROM edc_school_promo_codes
    WHERE code = p_promo_code;
    
    -- Validate promo code
    IF v_class_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid promo code';
    END IF;
    
    IF v_expiry_date < NOW() THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Promo code expired';
    END IF;
    
    IF v_used_count >= v_usage_limit THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Promo code usage limit reached';
    END IF;
    
    -- Check if already enrolled
    IF EXISTS (SELECT 1 FROM edc_school_student_classes WHERE student_id = p_student_id AND class_id = v_class_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student already enrolled in this class';
    END IF;
    
    -- Enroll student
    INSERT INTO edc_school_student_classes (student_id, class_id, created_at)
    VALUES (p_student_id, v_class_id, NOW());
    
    -- Update promo code usage
    UPDATE edc_school_promo_codes
    SET used_count = used_count + 1, used_at = NOW()
    WHERE code = p_promo_code;
    
    COMMIT;
END //
DELIMITER ;
```

### Get Class Statistics
```sql
DELIMITER //
CREATE PROCEDURE GetClassStatistics(IN p_class_id BIGINT)
BEGIN
    SELECT 
        c.name AS class_name,
        CONCAT(u.user_login, ' (', COALESCE(um1.meta_value, ''), ' ', COALESCE(um2.meta_value, ''), ')') AS teacher,
        COUNT(DISTINCT sc.student_id) AS total_students,
        COUNT(DISTINCT pc.id) AS total_promo_codes,
        SUM(pc.used_count) AS total_promo_uses,
        MIN(sc.created_at) AS first_enrollment,
        MAX(sc.created_at) AS latest_enrollment
    FROM edc_school_classes c
    LEFT JOIN edc_users u ON c.teacher_id = u.ID
    LEFT JOIN edc_usermeta um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
    LEFT JOIN edc_usermeta um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
    LEFT JOIN edc_school_student_classes sc ON c.id = sc.class_id
    LEFT JOIN edc_school_promo_codes pc ON c.id = pc.class_id
    WHERE c.id = p_class_id
    GROUP BY c.id, c.name, u.user_login, um1.meta_value, um2.meta_value;
END //
DELIMITER ;
```

## Data Integrity Issues

### Current Problems Identified
1. **Orphaned Classes**: Classes with `teacher_id = 0` or invalid teacher IDs
2. **Inconsistent Student Data**: Names like "1??", "2323" suggest test data or data corruption
3. **Missing Foreign Keys**: No referential integrity constraints
4. **No Data Validation**: Email formats, name lengths not validated
5. **Promo Code Orphans**: Promo codes pointing to non-existent classes

### Data Quality Assessment Query
```sql
-- Assessment of data quality issues
SELECT 
    'Classes without valid teachers' AS issue_type,
    COUNT(*) AS count
FROM edc_school_classes c
LEFT JOIN edc_users u ON c.teacher_id = u.ID
WHERE c.teacher_id = 0 OR u.ID IS NULL

UNION ALL

SELECT 
    'Students with invalid names' AS issue_type,
    COUNT(*) AS count
FROM edc_school_students
WHERE name REGEXP '^[0-9?]+$' OR LENGTH(name) < 2

UNION ALL

SELECT 
    'Promo codes for non-existent classes' AS issue_type,
    COUNT(*) AS count
FROM edc_school_promo_codes pc
LEFT JOIN edc_school_classes c ON pc.class_id = c.id
WHERE c.id IS NULL;
```

## Recommended Solutions

### 1. Database Schema Improvements

#### Add Foreign Key Constraints
```sql
-- Add foreign keys for data integrity
ALTER TABLE edc_school_classes 
ADD CONSTRAINT fk_classes_teacher 
FOREIGN KEY (teacher_id) REFERENCES edc_users(ID) ON DELETE SET NULL;

ALTER TABLE edc_school_students 
ADD CONSTRAINT fk_students_wp_user 
FOREIGN KEY (wp_user_id) REFERENCES edc_users(ID) ON DELETE CASCADE;

ALTER TABLE edc_school_student_classes 
ADD CONSTRAINT fk_enrollments_student 
FOREIGN KEY (student_id) REFERENCES edc_school_students(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_enrollments_class 
FOREIGN KEY (class_id) REFERENCES edc_school_classes(id) ON DELETE CASCADE;

ALTER TABLE edc_school_promo_codes 
ADD CONSTRAINT fk_promo_class 
FOREIGN KEY (class_id) REFERENCES edc_school_classes(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_promo_teacher 
FOREIGN KEY (teacher_id) REFERENCES edc_users(ID) ON DELETE CASCADE;
```

#### Add Status and Audit Fields
```sql
-- Add status field to classes
ALTER TABLE edc_school_classes 
ADD COLUMN status ENUM('active', 'inactive', 'archived') DEFAULT 'active' AFTER description,
ADD COLUMN max_students INT DEFAULT 30 AFTER status;

-- Add enrollment status
ALTER TABLE edc_school_student_classes 
ADD COLUMN status ENUM('enrolled', 'completed', 'withdrawn', 'failed') DEFAULT 'enrolled' AFTER created_at,
ADD COLUMN completion_date DATETIME NULL AFTER status,
ADD COLUMN grade VARCHAR(10) NULL AFTER completion_date;

-- Normalize student names
ALTER TABLE edc_school_students 
ADD COLUMN first_name VARCHAR(100) AFTER name,
ADD COLUMN last_name VARCHAR(100) AFTER first_name,
ADD COLUMN phone VARCHAR(20) AFTER email,
ADD COLUMN date_of_birth DATE AFTER phone;
```

### 2. Data Cleanup Scripts

#### Clean Invalid Teacher Assignments
```sql
-- Update classes with invalid teacher_id to NULL
UPDATE edc_school_classes 
SET teacher_id = NULL 
WHERE teacher_id NOT IN (SELECT ID FROM edc_users) 
OR teacher_id = 0;
```

#### Clean Student Data
```sql
-- Flag problematic student records for review
CREATE TEMPORARY TABLE student_cleanup AS
SELECT id, name, 
    CASE 
        WHEN name REGEXP '^[0-9?]+$' THEN 'Invalid name format'
        WHEN LENGTH(name) < 2 THEN 'Name too short'
        WHEN email IS NULL OR email = '' THEN 'Missing email'
        ELSE 'OK'
    END AS issue
FROM edc_school_students
WHERE name REGEXP '^[0-9?]+$' 
   OR LENGTH(name) < 2 
   OR email IS NULL 
   OR email = '';
```

### 3. Enhanced Security Measures

#### Create Audit Log Table
```sql
CREATE TABLE edc_school_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    record_id BIGINT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_values JSON,
    new_values JSON,
    user_id BIGINT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_user_action (user_id, action),
    INDEX idx_created_at (created_at)
);
```

#### Create Audit Triggers
```sql
-- Audit trigger for class changes
DELIMITER //
CREATE TRIGGER audit_classes_update
AFTER UPDATE ON edc_school_classes
FOR EACH ROW
BEGIN
    INSERT INTO edc_school_audit_log (table_name, record_id, action, old_values, new_values, user_id)
    VALUES (
        'edc_school_classes',
        NEW.id,
        'UPDATE',
        JSON_OBJECT('name', OLD.name, 'teacher_id', OLD.teacher_id, 'status', OLD.status),
        JSON_OBJECT('name', NEW.name, 'teacher_id', NEW.teacher_id, 'status', NEW.status),
        @current_user_id
    );
END //
DELIMITER ;
```

## Performance Optimization

### Recommended Indexes
```sql
-- Performance indexes
CREATE INDEX idx_classes_teacher_status ON edc_school_classes(teacher_id, status);
CREATE INDEX idx_classes_created ON edc_school_classes(created_at);
CREATE INDEX idx_students_wp_user ON edc_school_students(wp_user_id);
CREATE INDEX idx_students_email ON edc_school_students(email);
CREATE INDEX idx_enrollments_student_class ON edc_school_student_classes(student_id, class_id);
CREATE INDEX idx_enrollments_class_status ON edc_school_student_classes(class_id, status);
CREATE INDEX idx_promo_codes_class ON edc_school_promo_codes(class_id);
CREATE INDEX idx_promo_codes_teacher ON edc_school_promo_codes(teacher_id);
CREATE INDEX idx_promo_codes_expiry ON edc_school_promo_codes(expiry_date);
CREATE INDEX idx_usermeta_user_key ON edc_usermeta(user_id, meta_key);
```

### Query Optimization Views
```sql
-- Create view for active classes with teacher info
CREATE VIEW v_active_classes AS
SELECT 
    c.id,
    c.name,
    c.description,
    c.teacher_id,
    CONCAT(COALESCE(um1.meta_value, ''), ' ', COALESCE(um2.meta_value, '')) AS teacher_name,
    u.user_email AS teacher_email,
    COUNT(DISTINCT sc.student_id) AS student_count,
    c.created_at
FROM edc_school_classes c
LEFT JOIN edc_users u ON c.teacher_id = u.ID
LEFT JOIN edc_usermeta um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
LEFT JOIN edc_usermeta um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
LEFT JOIN edc_school_student_classes sc ON c.id = sc.class_id AND sc.status = 'enrolled'
WHERE c.status = 'active'
GROUP BY c.id, c.name, c.description, c.teacher_id, um1.meta_value, um2.meta_value, u.user_email, c.created_at;
```

## Security Considerations

### 1. Data Access Control
- Implement role-based access control (RBAC)
- Teachers can only access their own classes
- Students can only access classes they're enrolled in
- Administrators have full access

### 2. Input Validation
```sql
-- Add check constraints for data validation
ALTER TABLE edc_school_students 
ADD CONSTRAINT chk_email_format 
CHECK (email IS NULL OR email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$');

ALTER TABLE edc_school_promo_codes 
ADD CONSTRAINT chk_usage_limit 
CHECK (usage_limit > 0 AND used_count >= 0 AND used_count <= usage_limit);
```

### 3. Sensitive Data Protection
- Hash sensitive data where applicable
- Implement data retention policies
- Regular security audits of access patterns

---

This comprehensive database map provides a complete overview of the school management system's data structure, relationships, and recommended improvements for better performance, security, and data integrity.

School Management System - Database Map
1. Core Tables
1.1 edc_school_classes
Purpose: Stores class/course information
Primary Key: id (BIGINT)
Key Fields:
name (VARCHAR): Class name
teacher_id (BIGINT): References WordPress user ID
created_at (DATETIME)
updated_at (DATETIME)
Relationships:
One-to-Many with edc_users (teacher)
One-to-Many with edc_school_student_classes
1.2 edc_school_students
Purpose: Stores student information
Primary Key: id (BIGINT)
Key Fields:
wp_user_id (BIGINT): Links to WordPress user
name (VARCHAR): Student name
email (VARCHAR): Student email
class_id (BIGINT): Current class
created_at (DATETIME)
Relationships:
Many-to-One with edc_users (WordPress user)
Many-to-Many with edc_school_classes through edc_school_student_classes
1.3 edc_school_student_classes
Purpose: Junction table for student enrollments
Primary Key: Composite (student_id, class_id)
Key Fields:
student_id (BIGINT)
class_id (BIGINT)
created_at (DATETIME)
Relationships:
Many-to-One with edc_school_students
Many-to-One with edc_school_classes
1.4 edc_school_promo_codes
Purpose: Manages promo codes for class access
Primary Key: id (BIGINT)
Key Fields:
code (VARCHAR): Unique promo code
class_id (BIGINT)
teacher_id (BIGINT)
usage_limit (INT)
used_count (INT)
expiry_date (DATETIME)
created_at (DATETIME)
Relationships:
Many-to-One with edc_school_classes
Many-to-One with edc_users (teacher)
2. WordPress Integration
2.1 edc_users
Purpose: WordPress users table
Primary Key: ID (BIGINT)
Key Fields:
user_login (VARCHAR)
user_email (VARCHAR)
user_pass (VARCHAR)
user_registered (DATETIME)
user_status (INT)
2.2 edc_usermeta
Purpose: WordPress user metadata
Primary Key: umeta_id (BIGINT)
Key Fields:
user_id (BIGINT): Links to users.ID
meta_key (VARCHAR)
meta_value (LONGTEXT)
3. Data Flow
3.1 User Registration
User registers in WordPress (edc_users)
User role assigned via edc_usermeta (meta_key = 'wp_capabilities')
If student, record created in edc_school_students
3.2 Class Enrollment
Teacher creates class in edc_school_classes
Students enroll via edc_school_student_classes
Promo codes can be generated for self-enrollment
4. CRUD Operations
4.1 Create
sql
-- Create class
INSERT INTO edc_school_classes (name, teacher_id, created_at, updated_at)
VALUES ('New Class', 2, NOW(), NOW());

-- Enroll student
INSERT INTO edc_school_student_classes (student_id, class_id, created_at)
VALUES (1, 1, NOW());

-- Generate promo code
INSERT INTO edc_school_promo_codes 
    (code, class_id, teacher_id, usage_limit, used_count, expiry_date, created_at)
VALUES 
    ('PROMO123', 1, 2, 10, 0, '2023-12-31 23:59:59', NOW());
4.2 Read
sql
-- Get classes with student counts
SELECT 
    c.id, 
    c.name, 
    u.user_login as teacher,
    COUNT(sc.student_id) as student_count
FROM edc_school_classes c
LEFT JOIN edc_users u ON c.teacher_id = u.ID
LEFT JOIN edc_school_student_classes sc ON c.id = sc.class_id
GROUP BY c.id;

-- Get students in a class
SELECT 
    s.id, 
    s.name, 
    s.email
FROM edc_school_students s
JOIN edc_school_student_classes sc ON s.id = sc.student_id
WHERE sc.class_id = 1;
4.3 Update
sql
-- Update class teacher
UPDATE edc_school_classes 
SET teacher_id = 3, updated_at = NOW() 
WHERE id = 1;

-- Update student information
UPDATE edc_school_students 
SET email = 'new@email.com' 
WHERE id = 1;
4.4 Delete
sql
-- Remove student from class
DELETE FROM edc_school_student_classes 
WHERE student_id = 1 AND class_id = 1;

-- Delete class (only if no enrollments)
DELETE FROM edc_school_classes 
WHERE id = 1 
AND NOT EXISTS (
    SELECT 1 FROM edc_school_student_classes 
    WHERE class_id = 1
);
5. Observations and Recommendations
5.1 Data Integrity Issues
Some classes have teacher_id = 0 (no teacher assigned)
Inconsistent data in student names (some appear to be test data)
No foreign key constraints in the database
5.2 Recommended Improvements
Add Foreign Keys:
sql
ALTER TABLE edc_school_classes 
ADD CONSTRAINT fk_teacher 
FOREIGN KEY (teacher_id) REFERENCES edc_users(ID);
Add Status Field to classes (active/inactive/archived)
Normalize Student Data:
Split name into first_name/last_name
Add validation for email format
Add unique constraints
Add Audit Logging:
Track who made changes to enrollments
Log promo code usage
Add Indexes for better performance:
sql
CREATE INDEX idx_class_teacher ON edc_school_classes(teacher_id);
CREATE INDEX idx_student_class ON edc_school_student_classes(student_id, class_id);
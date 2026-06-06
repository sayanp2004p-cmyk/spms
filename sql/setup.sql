-- Database setup for Student Payment Management System

CREATE DATABASE IF NOT EXISTS student_payment_db;
USE student_payment_db;

-- Users table for authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    admission_date DATE NOT NULL,
    course VARCHAR(100),
    fee_amount DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add fee_amount column if it doesn't exist (for existing databases)
ALTER TABLE students ADD COLUMN IF NOT EXISTS fee_amount DECIMAL(10,2) DEFAULT 0;

-- Add class_type column if it doesn't exist (for existing databases)
ALTER TABLE students ADD COLUMN IF NOT EXISTS class_type VARCHAR(50) DEFAULT NULL;

-- Add icon column if it doesn't exist (for existing databases)
ALTER TABLE users ADD COLUMN IF NOT EXISTS icon VARCHAR(50) DEFAULT 'fas fa-user';

-- Add email_for_sending column if it doesn't exist (for existing databases)
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_for_sending VARCHAR(100) DEFAULT NULL;

-- Add app_password column if it doesn't exist (for existing databases)
ALTER TABLE users ADD COLUMN IF NOT EXISTS app_password VARCHAR(255) DEFAULT NULL;

-- Add student_id_format column to allow per-user ID format templates
ALTER TABLE users ADD COLUMN IF NOT EXISTS student_id_format VARCHAR(255) DEFAULT NULL;

-- Add student_id_prefix column to allow custom student ID prefixes per user
ALTER TABLE users ADD COLUMN IF NOT EXISTS student_id_prefix VARCHAR(50) DEFAULT NULL;

-- Add student_id_required column to indicate if student ID is required for this user
ALTER TABLE users ADD COLUMN IF NOT EXISTS student_id_required TINYINT(1) DEFAULT 0;
-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'card', 'bank_transfer', 'online') DEFAULT 'cash',
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    description TEXT,
    due_amount DECIMAL(10,2) DEFAULT 0,
    payment_code VARCHAR(10) UNIQUE,
    payment_month VARCHAR(7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

-- Add payment_month column if it doesn't exist (for existing databases)
ALTER TABLE payments ADD COLUMN IF NOT EXISTS payment_month VARCHAR(7) DEFAULT NULL;

CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_img VARCHAR(255) NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    course_details TEXT NOT NULL, -- Added for course details with formatting
    class_start_time TIME NOT NULL,
    class_end_time TIME NOT NULL,
    class_weeks VARCHAR(50) NOT NULL,
    course_fees DECIMAL(10,2) NOT NULL,
    class_type VARCHAR(20) NOT NULL,    
    monthly_fees VARCHAR(20) NULL DEFAULT NULL,
    has_offer TINYINT(1) DEFAULT 0,
    offer_percentage DECIMAL(5,2) DEFAULT NULL,
    offer_expire_date DATE DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS `application` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255),
  `guardian_name` VARCHAR(255),
  `present_address` TEXT,
  `permanent_address` TEXT,
  `mobile` VARCHAR(50),
  `email` VARCHAR(255),
  `gender` VARCHAR(10),
  `dob` DATE NULL,
  `blood_group` VARCHAR(50),
  `blood_pressure` VARCHAR(50),
  `weight` VARCHAR(50),
  `height` VARCHAR(50),
  `chronic_disease` TEXT,
  `contact_no` VARCHAR(50),
  `date_field` DATE NULL,
  `place` VARCHAR(255),
  `code_seq` INT DEFAULT 0,
  `form_code` VARCHAR(100),
  `course_id` INT NULL,
  `class_type` VARCHAR(50) NULL,
  `admission_fees` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
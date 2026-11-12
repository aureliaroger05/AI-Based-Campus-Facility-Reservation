-- AI-Based Campus Facility Reservation System Database Schema
-- Run: mysql -u root -p < db_init.sql (create DB first if needed)

CREATE DATABASE IF NOT EXISTS reservation_sys;
USE reservation_sys;

-- Users table (admin creates users)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'user') DEFAULT 'user',

  -- Backward compatible display name
  full_name VARCHAR(100),

  -- New structured name fields
  first_name VARCHAR(50) DEFAULT NULL,
  middle_name VARCHAR(50) DEFAULT NULL,
  last_name VARCHAR(50) DEFAULT NULL,
  suffix VARCHAR(20) DEFAULT NULL,

  -- Profile info
  birthdate DATE DEFAULT NULL,
  gender ENUM('male','female','other','prefer_not_to_say') DEFAULT NULL,

  email VARCHAR(100),
  phone VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  profile_image VARCHAR(255) DEFAULT NULL
);


-- Facilities table
CREATE TABLE facilities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  avail_slots JSON,  -- e.g., {"mon": ["09:00-12:00", "14:00-17:00"], ...}
  max_capacity INT DEFAULT 1,
  image VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Facility class schedule blocks (specific calendar dates)
CREATE TABLE class_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  facility_id INT NOT NULL,
  subject VARCHAR(255) NOT NULL,
  class_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_class_block (facility_id, class_date, start_time, end_time),
  INDEX idx_class_schedule_date (facility_id, class_date),
  CONSTRAINT fk_class_schedules_facility
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE
);

-- Reservations table (with QR and usage log for AI)
CREATE TABLE reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  facility_id INT,

  -- New schema (preferred)
  reserve_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,

  -- Backward compatible column (legacy UI/older DB imports)
  reserve_time TIME NULL,

  reason TEXT DEFAULT NULL,
  status ENUM('pending', 'confirmed', 'used', 'completed', 'cancelled') DEFAULT 'pending',
  qr_code_path VARCHAR(255),
  checkin_token CHAR(64) DEFAULT NULL,
  usage_confirmed BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE,

  -- Uniqueness for new model
  UNIQUE KEY unique_booking (facility_id, reserve_date, start_time, end_time),

  -- Uniqueness for legacy slot model (reserve_time)
  UNIQUE KEY unique_booking_legacy (facility_id, reserve_date, reserve_time)
);


-- Usage logs for AI forecasting
CREATE TABLE usage_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  facility_id INT,
  reserve_date DATE,
  usage_count INT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (facility_id) REFERENCES facilities(id)
);

-- Indexes for AI queries
CREATE INDEX idx_usage_date ON usage_logs(reserve_date);
CREATE INDEX idx_res_fac_date ON reservations(facility_id, reserve_date);

-- Initial admin account (password: admin123, hash it in PHP later or use:)
INSERT INTO users (username, password_hash, role, full_name) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Admin');  -- password: password

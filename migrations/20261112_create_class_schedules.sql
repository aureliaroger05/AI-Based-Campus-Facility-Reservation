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

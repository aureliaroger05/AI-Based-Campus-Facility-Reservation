ALTER TABLE reservations
    MODIFY status ENUM('pending', 'confirmed', 'used', 'completed', 'cancelled') DEFAULT 'pending';

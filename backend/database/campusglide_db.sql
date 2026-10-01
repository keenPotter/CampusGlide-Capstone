-- CampusGlide Fleet Management System Database
-- MySQL Database Schema
-- Created for Nueva Vizcaya State University Motor Pool

-- Create the database
CREATE DATABASE IF NOT EXISTS campusglide_db;
USE campusglide_db;

-- ==================== USERS TABLE ====================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20),
    role ENUM('administrator', 'driver', 'faculty', 'guard') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==================== VEHICLES TABLE ====================
CREATE TABLE vehicles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plate_number VARCHAR(20) UNIQUE NOT NULL,
    vehicle_model VARCHAR(100) NOT NULL,
    vehicle_type VARCHAR(50) NOT NULL,
    color VARCHAR(50),
    manufacture_year INT,
    capacity INT,
    mileage INT DEFAULT 0,
    status ENUM('available', 'in_use', 'maintenance', 'retired') DEFAULT 'available',
    last_maintenance_date DATE,
    next_maintenance_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==================== DRIVERS TABLE ====================
CREATE TABLE drivers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    license_number VARCHAR(50) UNIQUE NOT NULL,
    license_expiry_date DATE NOT NULL,
    contact_number VARCHAR(20),
    address VARCHAR(255),
    assigned_vehicle_id INT,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
);

-- ==================== VEHICLE REQUESTS TABLE ====================
CREATE TABLE vehicle_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    requester_id INT NOT NULL,
    request_date DATETIME NOT NULL,
    trip_date DATE NOT NULL,
    departure_time TIME NOT NULL,
    destination VARCHAR(255) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    estimated_return_time TIME,
    number_of_passengers INT,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    rejection_reason VARCHAR(500),
    approved_by INT,
    approved_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ==================== TRIPS TABLE ====================
CREATE TABLE trips (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_request_id INT NOT NULL UNIQUE,
    vehicle_id INT NOT NULL,
    driver_id INT NOT NULL,
    trip_date DATE NOT NULL,
    departure_time TIME NOT NULL,
    estimated_return_time TIME,
    destination VARCHAR(255) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    trip_status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    actual_departure_time TIME,
    actual_return_time TIME,
    actual_mileage INT,
    notes VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_request_id) REFERENCES vehicle_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE RESTRICT
);

-- ==================== TRIP LOGS (Guard Logging) TABLE ====================
CREATE TABLE trip_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    trip_id INT NOT NULL,
    guard_id INT NOT NULL,
    departure_recorded_at DATETIME,
    return_recorded_at DATETIME,
    guard_notes VARCHAR(500),
    vehicle_condition_departure VARCHAR(255),
    vehicle_condition_return VARCHAR(255),
    damages_reported VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (guard_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- ==================== VEHICLE MAINTENANCE TABLE ====================
CREATE TABLE vehicle_maintenance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id INT NOT NULL,
    maintenance_type ENUM('oil_change', 'repair', 'refueling', 'inspection', 'tire_service', 'other') NOT NULL,
    description VARCHAR(500),
    maintenance_date DATE NOT NULL,
    completion_date DATE,
    cost DECIMAL(10, 2),
    performed_by VARCHAR(100),
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    INDEX idx_vehicle_date (vehicle_id, maintenance_date)
);

-- ==================== VEHICLE-DRIVER ALLOCATION TABLE ====================
CREATE TABLE allocations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    trip_id INT NOT NULL UNIQUE,
    vehicle_id INT NOT NULL,
    driver_id INT NOT NULL,
    allocated_by INT NOT NULL,
    allocation_date DATETIME NOT NULL,
    notes VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE RESTRICT,
    FOREIGN KEY (allocated_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- ==================== NOTIFICATIONS TABLE ====================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_id INT NOT NULL,
    sender_id INT,
    notification_type VARCHAR(50),
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    related_entity_type VARCHAR(50),
    related_entity_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_recipient_read (recipient_id, is_read)
);

-- ==================== AUDIT LOG TABLE ====================
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user_date (user_id, created_at)
);

-- ==================== POST TRAVEL REPORTS TABLE ====================
CREATE TABLE post_travel_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id INT NOT NULL,
    trip_id INT,
    travel_date_from DATE NOT NULL,
    travel_date_to DATE,
    places_of_travel VARCHAR(500) NOT NULL,
    defects_observed TEXT,
    defects_incurred TEXT,
    remarks TEXT,
    drivers VARCHAR(500),
    arrival_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    INDEX idx_post_travel_vehicle_date (vehicle_id, travel_date_from),
    INDEX idx_post_travel_trip (trip_id)
);

-- ==================== FUEL USAGE RECORDS TABLE ====================
CREATE TABLE fuel_usage_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id INT NOT NULL,
    trip_id INT,
    record_date DATE NOT NULL,
    balance_in_tank DECIMAL(10,2) DEFAULT 0,
    issuance_from_stock DECIMAL(10,2) DEFAULT 0,
    fuel_purchased DECIMAL(10,2) DEFAULT 0,
    fuel_used DECIMAL(10,2) DEFAULT 0,
    end_trip_balance DECIMAL(10,2) DEFAULT 0,
    riv_no VARCHAR(100),
    riv_date DATE,
    or_no VARCHAR(100),
    or_date DATE,
    lubricating_oil DECIMAL(10,2),
    diesel_water DECIMAL(10,2),
    gear_oil DECIMAL(10,2),
    brake_fluid DECIMAL(10,2),
    flushing_oil DECIMAL(10,2),
    grease DECIMAL(10,2),
    drivers VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    INDEX idx_fuel_vehicle_date (vehicle_id, record_date),
    INDEX idx_fuel_trip (trip_id)
);

-- ==================== PREVENTIVE MAINTENANCE CHECKLISTS TABLE ====================
CREATE TABLE preventive_maintenance_checklists (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id INT NOT NULL,
    pmuv_no VARCHAR(100),
    inspection_date DATE NOT NULL,
    inspector_mechanic VARCHAR(255),
    current_mileage INT,
    last_oil_change DATE,
    last_air_filter_change DATE,
    last_cabin_filter_change DATE,
    last_oil_filter_change DATE,
    last_engine_tune_up DATE,
    belts_condition ENUM('excellent','good','poor'),
    hoses_condition ENUM('excellent','good','poor'),
    engine_condition ENUM('excellent','good','poor'),
    air_conditioning_condition ENUM('excellent','good','poor'),
    wipers_condition ENUM('excellent','good','poor'),
    headlights_condition ENUM('excellent','good','poor'),
    driving_lights_condition ENUM('excellent','good','poor'),
    brake_lights_condition ENUM('excellent','good','poor'),
    hazard_lights_condition ENUM('excellent','good','poor'),
    door_locks_condition ENUM('excellent','good','poor'),
    windows_windshield_condition ENUM('excellent','good','poor'),
    radio_condition ENUM('excellent','good','poor'),
    tires_condition ENUM('excellent','good','poor'),
    liquid_levels_condition ENUM('excellent','good','poor'),
    other_parts_condition ENUM('excellent','good','poor'),
    other_parts TEXT,
    remarks TEXT,
    supervisor_recommendation TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
    INDEX idx_pm_checklist_vehicle_date (vehicle_id, inspection_date)
);

-- ==================== INDEXES FOR PERFORMANCE ====================
CREATE INDEX idx_vehicle_requests_status ON vehicle_requests(status);
CREATE INDEX idx_vehicle_requests_trip_date ON vehicle_requests(trip_date);
CREATE INDEX idx_trips_vehicle ON trips(vehicle_id);
CREATE INDEX idx_trips_driver ON trips(driver_id);
CREATE INDEX idx_trips_trip_date ON trips(trip_date);
CREATE INDEX idx_maintenance_vehicle ON vehicle_maintenance(vehicle_id);
CREATE INDEX idx_maintenance_date ON vehicle_maintenance(maintenance_date);
CREATE INDEX idx_drivers_user ON drivers(user_id);

-- ==================== INSERT SAMPLE DATA FOR TESTING ====================

-- Insert admin user
INSERT INTO users (email, password, first_name, last_name, phone_number, role, is_active) 
VALUES ('admin@nvsu.edu.ph', '$2y$10$YourHashedPasswordHere', 'Maria', 'Administrator', '09123456789', 'administrator', TRUE);

-- Insert sample drivers
INSERT INTO users (email, password, first_name, last_name, phone_number, role, is_active)
VALUES 
('driver1@nvsu.edu.ph', '$2y$10$YourHashedPasswordHere', 'Juan', 'Dela Cruz', '09198765432', 'driver', TRUE),
('driver2@nvsu.edu.ph', '$2y$10$YourHashedPasswordHere', 'Pedro', 'Santos', '09187654321', 'driver', TRUE);

-- Insert sample faculty
INSERT INTO users (email, password, first_name, last_name, phone_number, role, is_active)
VALUES 
('faculty1@nvsu.edu.ph', '$2y$10$YourHashedPasswordHere', 'Dr. Antonio', 'Garcia', '09176543210', 'faculty', TRUE),
('faculty2@nvsu.edu.ph', '$2y$10$YourHashedPasswordHere', 'Prof. Rosario', 'Reyes', '09165432109', 'faculty', TRUE);

-- Insert sample guard
INSERT INTO users (email, password, first_name, last_name, phone_number, role, is_active)
VALUES ('guard@nvsu.edu.ph', '$2y$10$YourHashedPasswordHere', 'Rolando', 'Guard', '09154321098', 'guard', TRUE);

-- Insert sample vehicles
INSERT INTO vehicles (plate_number, vehicle_model, vehicle_type, color, manufacture_year, capacity, status)
VALUES 
('NCR 001', 'Toyota Hiace', 'van', 'White', 2019, 15, 'available'),
('NCR 002', 'Toyota Innova', 'suv', 'Silver', 2020, 8, 'available'),
('NCR 003', 'Mitsubishi L300', 'van', 'White', 2018, 12, 'available');

-- Insert sample drivers with user references
INSERT INTO drivers (user_id, license_number, license_expiry_date, contact_number, is_available)
VALUES 
(2, 'DL-12345-2026', '2026-12-31', '09198765432', TRUE),
(3, 'DL-12346-2027', '2027-06-30', '09187654321', TRUE);

-- ==================== VIEWS FOR COMMON QUERIES ====================

-- View for pending requests awaiting approval
CREATE VIEW pending_requests AS
SELECT 
    vr.id,
    vr.request_date,
    vr.trip_date,
    CONCAT(u.first_name, ' ', u.last_name) AS requester_name,
    vr.destination,
    vr.purpose,
    vr.number_of_passengers,
    vr.status
FROM vehicle_requests vr
JOIN users u ON vr.requester_id = u.id
WHERE vr.status = 'pending'
ORDER BY vr.request_date DESC;

-- View for scheduled trips
CREATE VIEW scheduled_trips AS
SELECT 
    t.id,
    t.trip_date,
    t.departure_time,
    t.destination,
    v.plate_number,
    CONCAT(d_user.first_name, ' ', d_user.last_name) AS driver_name,
    CONCAT(r_user.first_name, ' ', r_user.last_name) AS requester_name,
    t.trip_status
FROM trips t
JOIN vehicles v ON t.vehicle_id = v.id
JOIN drivers d ON t.driver_id = d.id
JOIN users d_user ON d.user_id = d_user.id
JOIN vehicle_requests vr ON t.vehicle_request_id = vr.id
JOIN users r_user ON vr.requester_id = r_user.id
WHERE t.trip_status IN ('scheduled', 'in_progress')
ORDER BY t.trip_date ASC, t.departure_time ASC;

-- View for vehicle maintenance schedule
CREATE VIEW maintenance_schedule AS
SELECT 
    v.id,
    v.plate_number,
    v.vehicle_model,
    vm.maintenance_type,
    vm.maintenance_date,
    vm.status,
    vm.description
FROM vehicles v
LEFT JOIN vehicle_maintenance vm ON v.id = vm.vehicle_id
WHERE vm.status IN ('scheduled', 'in_progress')
ORDER BY vm.maintenance_date ASC;

-- View for driver availability
CREATE VIEW driver_availability AS
SELECT 
    d.id,
    CONCAT(u.first_name, ' ', u.last_name) AS driver_name,
    u.phone_number,
    d.license_number,
    d.license_expiry_date,
    d.is_available,
    v.plate_number,
    v.vehicle_model
FROM drivers d
JOIN users u ON d.user_id = u.id
LEFT JOIN vehicles v ON d.assigned_vehicle_id = v.id
WHERE u.is_active = TRUE
ORDER BY d.is_available DESC, u.first_name ASC;


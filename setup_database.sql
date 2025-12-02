-- ============================================================
-- Water Monitoring System Database Setup
-- For XAMPP MySQL
-- ============================================================

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS water_monitoring;
USE water_monitoring;

-- ============================================================
-- Table: refilling_stations
-- Stores information about water monitoring stations
-- ============================================================
CREATE TABLE IF NOT EXISTS refilling_stations (
    station_id INT PRIMARY KEY AUTO_INCREMENT,
    station_name VARCHAR(255) NOT NULL,
    device_sensor_id VARCHAR(100) UNIQUE,
    location VARCHAR(255),
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: water_data
-- Stores sensor readings from ESP32 devices
-- ============================================================
CREATE TABLE IF NOT EXISTS water_data (
    waterdata_id INT PRIMARY KEY AUTO_INCREMENT,
    station_id INT NOT NULL,
    sensor_id VARCHAR(100),
    tds_value DOUBLE,
    ph_value DOUBLE,
    turbidity_value DOUBLE,
    lead_value DOUBLE,
    color_value DOUBLE,
    tds_status VARCHAR(50),
    ph_status VARCHAR(50),
    turbidity_status VARCHAR(50),
    lead_status VARCHAR(50),
    color_status VARCHAR(50),
    color_result VARCHAR(50),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES refilling_stations(station_id) ON DELETE CASCADE,
    INDEX idx_station_timestamp (station_id, timestamp),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Sample Data
-- Insert test station for development
-- ============================================================
INSERT INTO refilling_stations 
    (station_id, station_name, device_sensor_id, location, status) 
VALUES 
    (41, 'Test Station - XAMPP', 'ISUIT-WQTAMS-0001', 'Local Development', 'active')
ON DUPLICATE KEY UPDATE 
    station_name = VALUES(station_name),
    device_sensor_id = VALUES(device_sensor_id);

-- ============================================================
-- Optional: Insert sample sensor data for testing
-- ============================================================
INSERT INTO water_data 
    (station_id, sensor_id, tds_value, ph_value, turbidity_value, 
     lead_value, color_value, tds_status, ph_status, turbidity_status, 
     lead_status, color_status, color_result, timestamp)
VALUES 
    (41, 'ISUIT-WQTAMS-0001', 8.5, 6.5, 3.2, 0.008, 8.1, 
     'Safe', 'Neutral', 'Safe', 'Safe', 'Safe', 'Clear', NOW());

-- ============================================================
-- Verification Queries
-- ============================================================

-- Check if tables were created
SHOW TABLES;

-- Check station data
SELECT * FROM refilling_stations;

-- Check water data
SELECT * FROM water_data ORDER BY timestamp DESC LIMIT 10;

-- ============================================================
-- Useful Queries for Monitoring
-- ============================================================

-- Get latest reading for a station
-- SELECT * FROM water_data WHERE station_id = 41 ORDER BY timestamp DESC LIMIT 1;

-- Get all readings from last 24 hours
-- SELECT * FROM water_data WHERE timestamp >= NOW() - INTERVAL 24 HOUR ORDER BY timestamp DESC;

-- Count total readings per station
-- SELECT station_id, COUNT(*) as total_readings FROM water_data GROUP BY station_id;

-- Get average values for today
-- SELECT 
--     station_id,
--     AVG(tds_value) as avg_tds,
--     AVG(ph_value) as avg_ph,
--     AVG(turbidity_value) as avg_turbidity,
--     AVG(lead_value) as avg_lead
-- FROM water_data 
-- WHERE DATE(timestamp) = CURDATE()
-- GROUP BY station_id;

-- ============================================================
-- Cleanup Queries (Use with caution)
-- ============================================================

-- Delete old data (older than 30 days)
-- DELETE FROM water_data WHERE timestamp < NOW() - INTERVAL 30 DAY;

-- Reset water_data table (delete all readings)
-- TRUNCATE TABLE water_data;

-- Drop entire database (use only if you want to start fresh)
-- DROP DATABASE water_monitoring;

-- ============================================================
-- Success Message
-- ============================================================
SELECT 'Database setup completed successfully!' AS status;

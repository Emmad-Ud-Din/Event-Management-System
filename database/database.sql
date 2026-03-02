-- Database structure for Concert & Event Tracking Web Application
-- User Roles: Admin, Event Organizer, Normal User (Attendee)

CREATE DATABASE IF NOT EXISTS test;
USE test;

-- Users table with role system
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'organizer', 'user') NOT NULL DEFAULT 'user',
    status ENUM('active', 'pending', 'rejected', 'deactivated') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Events table
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organizer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    artist VARCHAR(255),
    event_date DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    image_path VARCHAR(255),
    status ENUM('approved', 'pending', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Event registrations (attendees)
CREATE TABLE IF NOT EXISTS event_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_registration (event_id, user_id)
);

-- Insert default admin user (password: admin123)
INSERT INTO users (email, password, full_name, role, status) VALUES
('admin@concert.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin', 'active');

-- Insert sample organizers (password: organizer123)
INSERT INTO users (email, password, full_name, role, status) VALUES
('organizer1@concert.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Organizer One', 'organizer', 'pending'),
('organizer2@concert.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Organizer Two', 'organizer', 'pending');

-- Insert sample events
INSERT INTO events (organizer_id, title, description, artist, event_date, location, status) VALUES
(2, 'Summer Music Festival', 'Annual summer music festival', 'Various Artists', '2025-07-15 18:00:00', 'Central Park', 'pending'),
(3, 'Jazz Night', 'Evening jazz concert', 'Jazz Band', '2025-08-20 20:00:00', 'Jazz Club', 'pending');


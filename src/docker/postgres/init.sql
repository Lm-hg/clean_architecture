-- ============================================
-- Clean Architecture Parking System - Database Schema
-- PostgreSQL Initialization Script
-- ============================================

-- Enable UUID extension for generating UUIDs
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================
-- ENUM TYPES
-- ============================================

-- User roles
CREATE TYPE user_role AS ENUM ('owner', 'customer', 'admin');

-- Reservation status
CREATE TYPE reservation_status AS ENUM ('pending', 'confirmed', 'cancelled', 'completed');

-- Payment status
CREATE TYPE payment_status AS ENUM ('pending', 'completed', 'failed', 'refunded');

-- ============================================
-- TABLES
-- ============================================

-- Users table (owners, customers, admins)
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role user_role NOT NULL DEFAULT 'customer',
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL
);

-- Parkings table (parking spots/locations)
CREATE TABLE parkings (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    owner_id UUID NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    price_per_hour DECIMAL(10, 2) NOT NULL,
    is_available BOOLEAN NOT NULL DEFAULT true,
    total_spots INTEGER NOT NULL DEFAULT 1,
    available_spots INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    
    -- Constraints
    CONSTRAINT fk_parking_owner FOREIGN KEY (owner_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_price_positive CHECK (price_per_hour > 0),
    CONSTRAINT chk_spots_positive CHECK (total_spots > 0),
    CONSTRAINT chk_available_spots CHECK (available_spots >= 0 AND available_spots <= total_spots)
);

-- Reservations table
CREATE TABLE reservations (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL,
    parking_id UUID NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    status reservation_status NOT NULL DEFAULT 'pending',
    total_price DECIMAL(10, 2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    
    -- Constraints
    CONSTRAINT fk_reservation_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_parking FOREIGN KEY (parking_id) 
        REFERENCES parkings(id) ON DELETE CASCADE,
    CONSTRAINT chk_end_after_start CHECK (end_time > start_time),
    CONSTRAINT chk_total_price_positive CHECK (total_price >= 0)
);

-- Payments table
CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    reservation_id UUID NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status payment_status NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(255),
    paid_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Constraints
    CONSTRAINT fk_payment_reservation FOREIGN KEY (reservation_id) 
        REFERENCES reservations(id) ON DELETE CASCADE,
    CONSTRAINT chk_amount_positive CHECK (amount > 0),
    CONSTRAINT uq_transaction_id UNIQUE (transaction_id)
);

-- ============================================
-- INDEXES for Performance
-- ============================================

-- Users indexes
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_deleted_at ON users(deleted_at);

-- Parkings indexes
CREATE INDEX idx_parkings_owner_id ON parkings(owner_id);
CREATE INDEX idx_parkings_city ON parkings(city);
CREATE INDEX idx_parkings_is_available ON parkings(is_available);
CREATE INDEX idx_parkings_deleted_at ON parkings(deleted_at);
CREATE INDEX idx_parkings_location ON parkings(latitude, longitude);

-- Reservations indexes
CREATE INDEX idx_reservations_user_id ON reservations(user_id);
CREATE INDEX idx_reservations_parking_id ON reservations(parking_id);
CREATE INDEX idx_reservations_status ON reservations(status);
CREATE INDEX idx_reservations_start_time ON reservations(start_time);
CREATE INDEX idx_reservations_deleted_at ON reservations(deleted_at);

-- Payments indexes
CREATE INDEX idx_payments_reservation_id ON payments(reservation_id);
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_payments_transaction_id ON payments(transaction_id);

-- ============================================
-- TRIGGERS for updated_at
-- ============================================

-- Function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply trigger to all tables
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_parkings_updated_at BEFORE UPDATE ON parkings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_reservations_updated_at BEFORE UPDATE ON reservations
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_payments_updated_at BEFORE UPDATE ON payments
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================
-- SEED DATA (Optional - for testing)
-- ============================================

-- Insert a test admin user (password: 'admin123' - hashed with bcrypt)
INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES
('admin@parking.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'admin');

-- Insert a test owner
INSERT INTO users (email, password_hash, first_name, last_name, phone, role) VALUES
('owner@parking.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Doe', '+33612345678', 'owner');

-- Insert a test customer
INSERT INTO users (email, password_hash, first_name, last_name, phone, role) VALUES
('customer@parking.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane', 'Smith', '+33687654321', 'customer');

-- Insert test parkings (using the owner's ID)
INSERT INTO parkings (owner_id, title, description, address, city, postal_code, latitude, longitude, price_per_hour, total_spots, available_spots)
SELECT 
    id,
    'Central Parking Spot',
    'Secure parking spot in the city center, close to metro station',
    '123 Rue de la Paix',
    'Paris',
    '75001',
    48.8566,
    2.3522,
    5.50,
    1,
    1
FROM users WHERE email = 'owner@parking.com';

INSERT INTO parkings (owner_id, title, description, address, city, postal_code, latitude, longitude, price_per_hour, total_spots, available_spots)
SELECT 
    id,
    'Airport Parking',
    'Long-term parking near Charles de Gaulle Airport',
    '456 Avenue de l''Aéroport',
    'Roissy-en-France',
    '95700',
    49.0097,
    2.5479,
    3.00,
    5,
    5
FROM users WHERE email = 'owner@parking.com';

-- ============================================
-- COMPLETION MESSAGE
-- ============================================

DO $$
BEGIN
    RAISE NOTICE '✅ Database schema initialized successfully!';
    RAISE NOTICE '📊 Tables created: users, parkings, reservations, payments';
    RAISE NOTICE '🔑 Indexes and constraints applied';
    RAISE NOTICE '🌱 Seed data inserted for testing';
END $$;

-- Migration: Create users table
-- Description: Creates the users table for storing user information
-- Date: 2024
-- This migration handles both new table creation and updating existing tables

-- Step 1: Create the table if it doesn't exist
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(36) PRIMARY KEY,
    role VARCHAR(50) NOT NULL CHECK (role IN ('admin', 'user', 'parking_owner')),
    first_name VARCHAR(255) NOT NULL CHECK (char_length(first_name) >= 2),
    name VARCHAR(255) NOT NULL CHECK (char_length(name) >= 2),
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL CHECK (char_length(password) >= 8),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    CONSTRAINT users_email_unique UNIQUE (email)
);

-- Step 2: Add first_name column if it doesn't exist (for existing tables)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'users' 
        AND column_name = 'first_name'
    ) THEN
        -- Add the column as nullable first
        ALTER TABLE users ADD COLUMN first_name VARCHAR(255);
        
        -- Update existing records
        -- Try to extract first name from existing 'name' field if it exists
        IF EXISTS (
            SELECT 1 
            FROM information_schema.columns 
            WHERE table_name = 'users' 
            AND column_name = 'name'
        ) THEN
            UPDATE users 
            SET first_name = CASE 
                WHEN position(' ' in name) > 0 THEN 
                    LEFT(name, position(' ' in name) - 1)
                ELSE 
                    COALESCE(name, 'User')
            END
            WHERE first_name IS NULL;
        ELSE
            UPDATE users SET first_name = 'User' WHERE first_name IS NULL;
        END IF;
        
        -- Now add the NOT NULL constraint
        ALTER TABLE users ALTER COLUMN first_name SET NOT NULL;
        
        -- Add the CHECK constraint
        IF NOT EXISTS (
            SELECT 1 FROM pg_constraint WHERE conname = 'chk_users_first_name_length'
        ) THEN
            ALTER TABLE users 
            ADD CONSTRAINT chk_users_first_name_length 
            CHECK (char_length(first_name) >= 2);
        END IF;
        
        RAISE NOTICE 'Column first_name added successfully';
    END IF;
END $$;

-- Step 3: Ensure 'name' column exists with correct constraints
DO $$
BEGIN
    -- Check if name column exists
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'users' 
        AND column_name = 'name'
    ) THEN
        -- Add the column
        ALTER TABLE users ADD COLUMN name VARCHAR(255);
        UPDATE users SET name = 'User' WHERE name IS NULL;
        ALTER TABLE users ALTER COLUMN name SET NOT NULL;
        
        -- Add the CHECK constraint
        ALTER TABLE users 
        ADD CONSTRAINT chk_users_name_length 
        CHECK (char_length(name) >= 2);
        
        RAISE NOTICE 'Column name added successfully';
    ELSE
        -- Update existing constraint if it has old value (>= 3)
        IF EXISTS (
            SELECT 1 
            FROM pg_constraint 
            WHERE conname = 'users_name_check' 
            AND pg_get_constraintdef(oid) LIKE '%char_length(name) >= 3%'
        ) THEN
            ALTER TABLE users DROP CONSTRAINT users_name_check;
        END IF;
        
        -- Add new constraint if it doesn't exist
        IF NOT EXISTS (
            SELECT 1 FROM pg_constraint WHERE conname = 'chk_users_name_length'
        ) THEN
            ALTER TABLE users 
            ADD CONSTRAINT chk_users_name_length 
            CHECK (char_length(name) >= 2);
        END IF;
    END IF;
END $$;

-- Index for faster email lookups
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- Index for role-based queries
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Index for created_at sorting
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at DESC);

-- Function to automatically update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Trigger to automatically update updated_at on UPDATE
DROP TRIGGER IF EXISTS update_users_updated_at ON users;
CREATE TRIGGER update_users_updated_at 
    BEFORE UPDATE ON users 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

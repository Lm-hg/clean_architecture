-- Migration: Add 'penalized' status to session_status enum
-- This migration adds the 'penalized' status to the existing session_status enum type

-- Note: PostgreSQL doesn't support adding values to ENUM types directly
-- We need to recreate the type with the new value
-- This is safe if no data depends on the old enum values

-- Step 1: Create a new enum type with the additional value
DO $$ 
BEGIN
    -- Check if the enum already has 'penalized'
    IF NOT EXISTS (
        SELECT 1 FROM pg_enum 
        WHERE enumlabel = 'penalized' 
        AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'session_status')
    ) THEN
        -- Add the new value to the existing enum
        ALTER TYPE session_status ADD VALUE IF NOT EXISTS 'penalized';
    END IF;
END $$;


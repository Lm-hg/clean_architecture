-- Migration: Ajouter 'active' au type ENUM reservation_status
-- Date: 2025-12-17

-- Ajouter la valeur 'active' au type enum
ALTER TYPE reservation_status ADD VALUE IF NOT EXISTS 'active';

SELECT 'Migration completed: active added to reservation_status' AS status;

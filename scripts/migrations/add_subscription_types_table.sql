-- Migration: Ajouter table subscription_types pour gérer les avantages d'abonnements
-- Date: 2025-01-XX
-- Description: Création de la table subscription_types pour séparer les types d'abonnements 
--              des abonnements actifs et permettre la gestion des avantages (benefits)

-- Créer la table subscription_types si elle n'existe pas
CREATE TABLE IF NOT EXISTS subscription_types (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    parking_id UUID NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    benefits TEXT,  -- JSON array stocké comme texte: ["Avantage 1", "Avantage 2"]
    price DECIMAL(10, 2) NOT NULL,
    duration_days INTEGER NOT NULL DEFAULT 30,
    -- Reference to MongoDB time slots document
    time_slots_id VARCHAR(24),  -- MongoDB ObjectId
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Constraints
    CONSTRAINT fk_subscription_type_parking FOREIGN KEY (parking_id) 
        REFERENCES parkings(id) ON DELETE CASCADE,
    CONSTRAINT chk_subscription_type_price_positive CHECK (price > 0),
    CONSTRAINT chk_subscription_type_duration_positive CHECK (duration_days > 0)
);

-- Ajouter colonne subscription_type_id à la table abonnements si elle n'existe pas
DO $$ 
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'abonnements' AND column_name = 'subscription_type_id'
    ) THEN
        ALTER TABLE abonnements 
        ADD COLUMN subscription_type_id UUID,
        ADD CONSTRAINT fk_abonnement_subscription_type 
            FOREIGN KEY (subscription_type_id) 
            REFERENCES subscription_types(id) 
            ON DELETE SET NULL;
    END IF;
END $$;

-- Créer index sur subscription_types
CREATE INDEX IF NOT EXISTS idx_subscription_types_parking_id ON subscription_types(parking_id);
CREATE INDEX IF NOT EXISTS idx_subscription_types_is_active ON subscription_types(is_active);

-- Créer index sur abonnements.subscription_type_id
CREATE INDEX IF NOT EXISTS idx_abonnements_subscription_type_id ON abonnements(subscription_type_id);

-- Message de confirmation
DO $$ 
BEGIN
    RAISE NOTICE 'Migration subscription_types terminée avec succès';
END $$;

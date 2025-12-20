-- Migration: Ajouter reservation_id et abonnement_id à la table stationnements
-- Date: 2025-12-17

-- Ajouter la colonne reservation_id
ALTER TABLE stationnements 
ADD COLUMN IF NOT EXISTS reservation_id UUID NULL,
ADD CONSTRAINT fk_stationnements_reservation 
    FOREIGN KEY (reservation_id) 
    REFERENCES reservations(id) 
    ON DELETE SET NULL;

-- Ajouter la colonne abonnement_id  
ALTER TABLE stationnements 
ADD COLUMN IF NOT EXISTS abonnement_id UUID NULL,
ADD CONSTRAINT fk_stationnements_abonnement 
    FOREIGN KEY (abonnement_id) 
    REFERENCES abonnements(id) 
    ON DELETE SET NULL;

-- Créer des index pour améliorer les performances
CREATE INDEX IF NOT EXISTS idx_stationnements_reservation_id ON stationnements(reservation_id);
CREATE INDEX IF NOT EXISTS idx_stationnements_abonnement_id ON stationnements(abonnement_id);

-- Afficher le résultat
SELECT 'Migration completed successfully' AS status;

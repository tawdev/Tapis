-- Script de migration pour ajouter les colonnes de dimensions et prix calculé à la table order_items
-- Date: 2024

USE tapis_db;

-- Ajouter les colonnes pour les dimensions et le prix calculé
ALTER TABLE order_items
ADD COLUMN IF NOT EXISTS length_cm DECIMAL(10, 2) NULL COMMENT 'Longueur en centimètres',
ADD COLUMN IF NOT EXISTS width_cm DECIMAL(10, 2) NULL COMMENT 'Largeur en centimètres',
ADD COLUMN IF NOT EXISTS surface_m2 DECIMAL(10, 4) NULL COMMENT 'Surface calculée en m²',
ADD COLUMN IF NOT EXISTS unit_price DECIMAL(10, 2) NULL COMMENT 'Prix unitaire au m² au moment de la commande',
ADD COLUMN IF NOT EXISTS calculated_price DECIMAL(10, 2) NULL COMMENT 'Prix calculé selon les dimensions (length × width × unit_price)';

-- Ajouter un index pour faciliter les recherches
CREATE INDEX IF NOT EXISTS idx_dimensions ON order_items(length_cm, width_cm);

-- Commentaire sur la table
ALTER TABLE order_items COMMENT = 'Items de commande avec support des dimensions personnalisées';


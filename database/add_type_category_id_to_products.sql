-- Migration : Ajouter la colonne type_category_id à la table products
-- Ce script ajoute la colonne pour lier les produits aux types de catégories

USE tapis_db;

-- Vérifier si la colonne existe avant de l'ajouter
-- Si votre version de MySQL ne supporte pas IF NOT EXISTS, exécutez d'abord :
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = 'tapis_db' AND TABLE_NAME = 'products' AND COLUMN_NAME = 'type_category_id';

-- Ajouter la colonne type_category_id
ALTER TABLE products 
ADD COLUMN type_category_id INT NULL AFTER category_id,
ADD FOREIGN KEY (type_category_id) REFERENCES types_categories(id) ON DELETE SET NULL,
ADD INDEX idx_type_category (type_category_id);

-- Vérification : Afficher la structure de la table
DESCRIBE products;


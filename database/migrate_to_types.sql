-- Migration : Restructuration de types_categories vers types
-- Ce script :
-- 1. Crée la table types
-- 2. Ajoute type_id à categories
-- 3. Migre les données de types_categories vers types
-- 4. Met à jour categories avec type_id
-- 5. Remplace type_category_id par type_id dans products
-- 6. Supprime la table types_categories

USE tapis_db;

-- Étape 1: Créer la table types
CREATE TABLE IF NOT EXISTS types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Étape 2: Ajouter type_id à categories
ALTER TABLE categories 
ADD COLUMN type_id INT NULL AFTER id,
ADD FOREIGN KEY (type_id) REFERENCES types(id) ON DELETE SET NULL,
ADD INDEX idx_type (type_id);

-- Étape 3: Migrer les données de types_categories vers types
-- Insérer les types uniques depuis types_categories
INSERT INTO types (name, description)
SELECT DISTINCT tc.name, tc.description
FROM types_categories tc
WHERE NOT EXISTS (
    SELECT 1 FROM types t WHERE t.name = tc.name
);

-- Étape 4: Mettre à jour categories avec type_id basé sur types_categories
-- Pour chaque catégorie, trouver le type associé via types_categories
UPDATE categories c
INNER JOIN types_categories tc ON tc.category_id = c.id
INNER JOIN types t ON t.name = tc.name
SET c.type_id = t.id
WHERE c.type_id IS NULL;

-- Étape 5: Créer une table temporaire pour mapper type_category_id vers type_id
CREATE TEMPORARY TABLE temp_type_mapping AS
SELECT 
    tc.id as old_type_category_id,
    t.id as new_type_id
FROM types_categories tc
INNER JOIN types t ON t.name = tc.name;

-- Étape 6: Ajouter type_id à products (temporairement pour la migration)
ALTER TABLE products 
ADD COLUMN type_id INT NULL AFTER category_id,
ADD INDEX idx_type_id_temp (type_id);

-- Étape 7: Migrer type_category_id vers type_id dans products
UPDATE products p
INNER JOIN temp_type_mapping ttm ON p.type_category_id = ttm.old_type_category_id
SET p.type_id = ttm.new_type_id;

-- Étape 8: Supprimer l'ancienne colonne type_category_id de products
ALTER TABLE products 
DROP FOREIGN KEY products_ibfk_2; -- Supprimer la clé étrangère (nom peut varier)
DROP INDEX idx_type_category;
DROP COLUMN type_category_id;

-- Étape 9: Ajouter la clé étrangère pour type_id dans products
ALTER TABLE products 
ADD FOREIGN KEY (type_id) REFERENCES types(id) ON DELETE SET NULL;

-- Étape 10: Supprimer la table types_categories
DROP TABLE IF EXISTS types_categories;

-- Nettoyer la table temporaire
DROP TEMPORARY TABLE IF EXISTS temp_type_mapping;

-- Vérification
SELECT 'Migration terminée avec succès!' as status;
SELECT 'Structure de la table types:' as info;
DESCRIBE types;
SELECT 'Structure de la table categories:' as info;
DESCRIBE categories;
SELECT 'Structure de la table products:' as info;
DESCRIBE products;


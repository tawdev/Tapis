-- Migration : Ajouter la colonne image à la table categories
-- Ce script est sûr à exécuter plusieurs fois (idempotent)

USE tapis_db;

-- IMPORTANT : Vérifiez d'abord si la colonne existe avant d'exécuter cette commande
-- Pour vérifier, exécutez cette requête :
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = 'tapis_db' AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'image';

-- Si aucun résultat n'est retourné, la colonne n'existe pas, exécutez alors :
ALTER TABLE categories ADD COLUMN image VARCHAR(255) NULL AFTER description;

-- Note : Si vous utilisez MySQL 8.0.19 ou supérieur, vous pouvez utiliser :
-- ALTER TABLE categories ADD COLUMN IF NOT EXISTS image VARCHAR(255) NULL AFTER description;

-- Vérification : Afficher la structure de la table
DESCRIBE categories;


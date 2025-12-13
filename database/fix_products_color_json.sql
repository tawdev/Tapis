-- Migration : Modifier le champ color de la table products pour supporter le JSON
-- Ce script modifie la colonne color pour permettre le stockage de couleurs multiples au format JSON

USE tapis_db;

-- Vérifier la structure actuelle
DESCRIBE products;

-- Modifier la colonne color en TEXT pour supporter du JSON plus long
-- Si votre colonne est déjà TEXT, cette commande ne changera rien
ALTER TABLE products 
MODIFY COLUMN color TEXT NULL 
COMMENT 'Couleurs du produit au format JSON: [{"name":"Rouge","index":1,"image":"path"},...] ou couleur simple (ancien format)';

-- Vérifier la nouvelle structure
DESCRIBE products;

-- Afficher un message de confirmation
SELECT 'Migration terminée : La colonne color supporte maintenant le format JSON.' AS result;


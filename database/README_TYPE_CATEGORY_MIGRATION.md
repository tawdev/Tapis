# Migration : Ajouter type_category_id aux produits

Ce script ajoute la colonne `type_category_id` à la table `products` pour lier les produits aux types de catégories (sous-catégories).

## Méthode 1 : Utilisation du script PHP (Recommandé)

1. Ouvrez votre navigateur et accédez à :
   ```
   http://localhost/Tapis/database/fix_products_type_category.php
   ```

2. Le script vérifiera automatiquement si la colonne existe et l'ajoutera si nécessaire.

3. Vous verrez un message de confirmation avec la structure de la table.

## Méthode 2 : Utilisation de SQL directement

1. Connectez-vous à votre base de données MySQL (via phpMyAdmin, MySQL Workbench, ou ligne de commande).

2. Sélectionnez votre base de données :
   ```sql
   USE tapis_db;
   ```

3. Vérifiez si la colonne existe :
   ```sql
   SELECT COLUMN_NAME 
   FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = 'tapis_db' 
   AND TABLE_NAME = 'products' 
   AND COLUMN_NAME = 'type_category_id';
   ```

4. Si la colonne n'existe pas, exécutez :
   ```sql
   ALTER TABLE products 
   ADD COLUMN type_category_id INT NULL AFTER category_id;
   
   ALTER TABLE products 
   ADD FOREIGN KEY (type_category_id) REFERENCES types_categories(id) ON DELETE SET NULL;
   
   ALTER TABLE products 
   ADD INDEX idx_type_category (type_category_id);
   ```

5. Vérifiez la structure :
   ```sql
   DESCRIBE products;
   ```

## Vérification

Après avoir exécuté la migration, la table `products` devrait avoir :
- La colonne `type_category_id` (INT, NULL) après `category_id`
- Une clé étrangère vers `types_categories(id)`
- Un index sur `type_category_id`

## Notes

- Le script est sûr à exécuter plusieurs fois (idempotent).
- Si la colonne existe déjà, le script ne fera rien.
- Les données existantes ne seront pas affectées (type_category_id sera NULL pour les produits existants).


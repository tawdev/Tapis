# Migration de la base de données - Ajout de la colonne image aux catégories

Ce script ajoute la colonne `image` à la table `categories` si elle n'existe pas déjà.

## Méthode 1 : Utilisation du script PHP (Recommandé)

1. Ouvrez votre navigateur et accédez à :
   ```
   http://localhost/Tapis/database/fix_categories_table.php
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
   AND TABLE_NAME = 'categories' 
   AND COLUMN_NAME = 'image';
   ```

4. Si la colonne n'existe pas, exécutez :
   ```sql
   ALTER TABLE categories 
   ADD COLUMN image VARCHAR(255) NULL AFTER description;
   ```

5. Vérifiez la structure :
   ```sql
   DESCRIBE categories;
   ```

## Vérification

Après avoir exécuté la migration, la table `categories` devrait avoir la structure suivante :

- `id` (INT, PRIMARY KEY)
- `name` (VARCHAR(100))
- `slug` (VARCHAR(100))
- `description` (TEXT)
- `image` (VARCHAR(255)) ← **Nouvelle colonne**
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

## Notes

- Le script est sûr à exécuter plusieurs fois (idempotent).
- Si la colonne existe déjà, le script ne fera rien.
- Les données existantes ne seront pas affectées.


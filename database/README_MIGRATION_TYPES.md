# Migration : types_categories → types

Ce document décrit la migration de la structure de base de données de `types_categories` vers `types`.

## Changements

### Nouvelle structure

1. **Table `types`** (nouvelle)
   - `id` (INT, PRIMARY KEY)
   - `name` (VARCHAR(100))
   - `description` (TEXT)
   - `created_at`, `updated_at`

2. **Table `categories`** (modifiée)
   - Ajout de `type_id` (INT, NULL, FOREIGN KEY vers `types.id`)

3. **Table `products`** (modifiée)
   - Remplacement de `type_category_id` par `type_id` (INT, NULL, FOREIGN KEY vers `types.id`)

4. **Table `types_categories`** (supprimée)
   - Cette table est supprimée après la migration

## Migration

### Méthode 1 : Script PHP (Recommandé)

1. Ouvrez votre navigateur et accédez à :
   ```
   http://localhost/Tapis/database/migrate_to_types.php
   ```

2. Le script exécutera automatiquement toutes les étapes de migration.

3. Vous verrez un rapport détaillé de chaque étape.

### Méthode 2 : Script SQL

1. Connectez-vous à votre base de données MySQL.

2. Exécutez le script :
   ```sql
   source database/migrate_to_types.sql
   ```

## Étapes de migration

1. Création de la table `types`
2. Ajout de `type_id` à `categories`
3. Migration des données de `types_categories` vers `types`
4. Mise à jour de `categories` avec `type_id`
5. Ajout de `type_id` à `products`
6. Migration de `type_category_id` vers `type_id` dans `products`
7. Suppression de `type_category_id` de `products`
8. Ajout de la clé étrangère pour `type_id` dans `products`
9. Suppression de la table `types_categories`

## Fichiers modifiés

- `product.php` : Utilise `type_id` et `type_name` au lieu de `type_category_id` et `type_category_name`
- `products.php` : Utilise `type_id` et `type_name`
- `admin/product_form.php` : Utilise `type_id`
- `cart.php` : Utilise `type_name`
- `admin/api/get_types.php` : Nouveau fichier API pour récupérer les types
- `database/schema.sql` : Structure mise à jour

## Notes importantes

- **Sauvegardez votre base de données avant d'exécuter la migration !**
- La migration préserve toutes les données existantes.
- Les relations entre produits et types sont maintenues.
- Après la migration, la table `types_categories` sera supprimée.

## Vérification

Après la migration, vérifiez que :
- La table `types` existe et contient des données
- La colonne `type_id` existe dans `categories` et `products`
- La colonne `type_category_id` n'existe plus dans `products`
- La table `types_categories` n'existe plus
- Les produits affichent correctement leur type


# Migration : Support des dimensions personnalisées dans les commandes

## Description

Cette migration ajoute le support des dimensions personnalisées (longueur et largeur en cm) et du calcul de prix basé sur la surface (m²) dans la table `order_items`.

## Colonnes ajoutées

Les colonnes suivantes sont ajoutées à la table `order_items` :

- `length_cm` : Longueur en centimètres (DECIMAL(10, 2), NULL)
- `width_cm` : Largeur en centimètres (DECIMAL(10, 2), NULL)
- `surface_m2` : Surface calculée en m² (DECIMAL(10, 4), NULL)
- `unit_price` : Prix unitaire au m² au moment de la commande (DECIMAL(10, 2), NULL)
- `calculated_price` : Prix calculé selon les dimensions (DECIMAL(10, 2), NULL)

## Méthodes d'installation

### Méthode 1 : Script SQL (Recommandé)

1. Connectez-vous à votre base de données MySQL/MariaDB
2. Exécutez le script SQL :

```bash
mysql -u votre_utilisateur -p tapis_db < database/add_dimensions_to_order_items.sql
```

Ou via phpMyAdmin :
- Ouvrez phpMyAdmin
- Sélectionnez la base de données `tapis_db`
- Allez dans l'onglet "SQL"
- Copiez-collez le contenu de `database/add_dimensions_to_order_items.sql`
- Cliquez sur "Exécuter"

### Méthode 2 : Script PHP automatique

1. Exécutez le script PHP depuis le navigateur ou en ligne de commande :

```bash
php database/fix_order_items_dimensions.php
```

Ou via navigateur :
```
http://localhost/Tapis/database/fix_order_items_dimensions.php
```

Le script vérifie automatiquement quelles colonnes existent déjà et ajoute uniquement celles qui manquent.

## Vérification

Pour vérifier que la migration a été effectuée avec succès, exécutez cette requête SQL :

```sql
DESCRIBE order_items;
```

Vous devriez voir les nouvelles colonnes :
- `length_cm`
- `width_cm`
- `surface_m2`
- `unit_price`
- `calculated_price`

## Compatibilité

- ✅ Compatible avec les anciennes commandes (colonnes NULL pour les produits sans dimensions)
- ✅ Les produits sans dimensions continuent de fonctionner normalement
- ✅ Les produits avec dimensions utilisent le prix calculé automatiquement

## Notes importantes

- Les colonnes sont **NULL** par défaut pour maintenir la compatibilité avec les anciennes commandes
- L'index `idx_dimensions` est créé pour améliorer les performances des requêtes sur les dimensions
- Le calcul de la surface se fait automatiquement : `surface_m2 = (length_cm × width_cm) / 10000`

## Fichiers modifiés

- `database/add_dimensions_to_order_items.sql` : Script SQL de migration
- `database/fix_order_items_dimensions.php` : Script PHP automatique
- `checkout.php` : Mise à jour pour enregistrer les dimensions dans les commandes
- `cart.php` : Affichage des dimensions dans le panier
- `api/add_to_cart.php` : Support des dimensions lors de l'ajout au panier


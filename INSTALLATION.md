# Guide d'Installation Rapide

## Installation en 5 minutes

### 1. Prérequis
- XAMPP installé et démarré (Apache + MySQL)

### 2. Base de données
1. Ouvrir http://localhost/phpmyadmin
2. Créer une base de données : `tapis_db`
3. Sélectionner la base `tapis_db`
4. Aller dans l'onglet "Importer"
5. Choisir le fichier `database/schema.sql`
6. Cliquer sur "Exécuter"

### 3. Configuration
1. Ouvrir `config/database.php`
2. Vérifier les paramètres de connexion (normalement OK par défaut pour XAMPP)
3. Vérifier l'URL du site : `http://localhost/Tapis`

### 4. Permissions
- S'assurer que le dossier `assets/images/products/` existe
- Donner les permissions d'écriture à ce dossier (normalement OK sur Windows)

### 5. Accès
- **Site** : http://localhost/Tapis
- **Admin** : http://localhost/Tapis/admin
  - Username: `admin`
  - Password: `admin123`

## Ajout d'images de produits

1. Se connecter à l'admin
2. Aller dans "Produits" > "Ajouter un produit"
3. Remplir les informations du produit
4. Dans "Images", sélectionner une ou plusieurs images
5. Formats acceptés : JPG, JPEG, PNG, WEBP
6. Taille max : 5MB par image

## Données de test

Le script SQL contient déjà :
- 5 catégories de tapis
- 5 produits de démonstration
- Les relations sont déjà configurées

## Problèmes courants

### Erreur de connexion à la base de données
- Vérifier que MySQL est démarré dans XAMPP
- Vérifier les identifiants dans `config/database.php`

### Images ne s'affichent pas
- Vérifier que le dossier `assets/images/products/` existe
- Vérifier les permissions du dossier
- Vérifier les chemins dans la base de données

### Erreur 404
- Vérifier que le dossier est bien dans `C:\xampp\htdocs\Tapis`
- Vérifier que Apache est démarré

## Support

Pour toute question, vérifier le fichier `README.md` pour plus de détails.


# Site E-commerce Tapis

Site e-commerce moderne spécialisé dans la vente de tapis, développé avec PHP, MySQL, JavaScript, HTML et CSS.

## 🚀 Installation

### Prérequis
- XAMPP (ou WAMP/MAMP) avec PHP 7.4+
- MySQL 5.7+
- Serveur web Apache

### Étapes d'installation

1. **Cloner ou copier le projet**
   ```bash
   Copier le dossier dans C:\xampp\htdocs\Tapis
   ```

2. **Créer la base de données**
   - Ouvrir phpMyAdmin (http://localhost/phpmyadmin)
   - Créer une nouvelle base de données nommée `tapis_db`
   - Importer le fichier `database/schema.sql`

3. **Configurer la base de données**
   - Ouvrir `config/database.php`
   - Modifier si nécessaire les constantes :
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'tapis_db');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

4. **Créer le dossier d'upload**
   - Créer le dossier `assets/images/products/` s'il n'existe pas
   - S'assurer que le dossier a les permissions d'écriture

5. **Configurer l'URL du site**
   - Dans `config/database.php`, modifier si nécessaire :
     ```php
     define('SITE_URL', 'http://localhost/Tapis');
     ```

6. **Accéder au site**
   - Frontend : http://localhost/Tapis
   - Admin : http://localhost/Tapis/admin
   - Identifiants admin par défaut :
     - Username: `admin`
     - Password: `admin123`

## 📁 Structure du projet

```
Tapis/
├── admin/              # Panneau d'administration
│   ├── index.php      # Dashboard
│   ├── login.php      # Connexion admin
│   ├── products.php   # Gestion produits
│   ├── categories.php # Gestion catégories
│   └── orders.php     # Gestion commandes
├── api/               # API backend
│   └── add_to_cart.php
├── assets/            # Ressources statiques
│   ├── css/
│   ├── js/
│   └── images/
├── config/            # Configuration
│   ├── database.php
│   └── functions.php
├── database/          # Scripts SQL
│   └── schema.sql
├── includes/          # Fichiers inclus
│   ├── header.php
│   └── footer.php
├── index.php          # Page d'accueil
├── products.php       # Liste produits
├── product.php        # Détails produit
├── cart.php           # Panier
├── checkout.php       # Paiement
└── tracking.php       # Suivi commande
```

## ✨ Fonctionnalités

### Frontend (Utilisateur)
- ✅ Page d'accueil avec catégories et produits en vedette
- ✅ Liste des produits avec filtres (prix, couleur, taille, type)
- ✅ Tri par nouveautés ou meilleures ventes
- ✅ Page détail produit avec slider d'images
- ✅ Panier avec gestion des quantités
- ✅ Checkout (paiement factice)
- ✅ Suivi de commande par numéro

### Backend (Admin)
- ✅ Dashboard avec statistiques
- ✅ Gestion complète des produits (CRUD)
- ✅ Upload de plusieurs images par produit
- ✅ Gestion des catégories
- ✅ Gestion des commandes avec changement de statut
- ✅ Protection par session

### Sécurité
- ✅ Protection XSS (htmlspecialchars)
- ✅ Protection SQL Injection (PDO avec prepared statements)
- ✅ Validation des formulaires
- ✅ Upload sécurisé des images

### Design
- ✅ Design moderne et élégant
- ✅ Responsive (mobile-friendly)
- ✅ Couleurs luxueuses adaptées aux tapis
- ✅ Animations et transitions fluides

## 🎨 Personnalisation

### Couleurs
Modifier les variables CSS dans `assets/css/style.css` :
```css
:root {
    --primary-color: #8B4513;    /* Couleur principale */
    --secondary-color: #D4AF37;  /* Couleur secondaire */
    --accent-color: #C9A961;     /* Couleur d'accent */
}
```

### Configuration Admin
Modifier les identifiants dans `config/database.php` :
```php
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');
```

## 📝 Notes importantes

1. **Sécurité en production** : 
   - Changer les identifiants admin
   - Utiliser des mots de passe forts
   - Activer HTTPS
   - Configurer correctement les permissions des fichiers

2. **Images** : 
   - Les images de test dans la base de données pointent vers des chemins qui n'existent pas encore
   - Ajouter vos propres images dans `assets/images/products/`

3. **Base de données** : 
   - Le script SQL contient des données de test
   - Les relations (Foreign Keys) sont correctement définies

## 🛠️ Technologies utilisées

- **Backend** : PHP 7.4+ avec PDO
- **Base de données** : MySQL 5.7+
- **Frontend** : HTML5, CSS3, JavaScript (Vanilla)
- **Pas de frameworks** : Code pur comme demandé

## 📄 Licence

Ce projet est créé pour un usage éducatif et commercial.

---

**Développé avec ❤️ pour le marché marocain**


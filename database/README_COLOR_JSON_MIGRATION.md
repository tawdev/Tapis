# Migration : Support JSON pour couleurs multiples

Ce script modifie la colonne `color` de la table `products` pour permettre le stockage de couleurs multiples au format JSON.

## Format de données

### Ancien format (compatible)
```
color = "Rouge"
```

### Nouveau format (JSON)
```json
[
  {
    "name": "Rouge",
    "index": 1,
    "image": "assets/images/products/color_red.jpg"
  },
  {
    "name": "Bleu",
    "index": 2,
    "image": "assets/images/products/color_blue.jpg"
  }
]
```

## Méthode 1 : Utilisation du script PHP (Recommandé)

1. Ouvrez votre navigateur et accédez à :
   ```
   http://localhost/Tapis/database/fix_products_color_json.php
   ```

2. Le script :
   - Vérifie la structure actuelle de la colonne `color`
   - Modifie la colonne en `TEXT` si nécessaire (pour supporter du JSON plus long)
   - Affiche les données existantes
   - Montre la structure finale de la table

3. Vous verrez un rapport détaillé avec toutes les informations.

## Méthode 2 : Utilisation de SQL directement

1. Connectez-vous à votre base de données MySQL (via phpMyAdmin, MySQL Workbench, ou ligne de commande).

2. Sélectionnez votre base de données :
   ```sql
   USE tapis_db;
   ```

3. Vérifiez la structure actuelle :
   ```sql
   DESCRIBE products;
   ```

4. Si la colonne `color` est de type `VARCHAR(50)` ou similaire, modifiez-la :
   ```sql
   ALTER TABLE products 
   MODIFY COLUMN color TEXT NULL 
   COMMENT 'Couleurs du produit au format JSON: [{"name":"Rouge","index":1,"image":"path"},...] ou couleur simple (ancien format)';
   ```

5. Vérifiez la nouvelle structure :
   ```sql
   DESCRIBE products;
   ```

## Vérification

Après avoir exécuté la migration, la table `products` devrait avoir :
- La colonne `color` de type `TEXT` (au lieu de `VARCHAR(50)`)
- La colonne peut stocker du JSON ou du texte simple (compatible avec l'ancien format)

## Notes importantes

- ✅ **Rétrocompatibilité** : Le système supporte toujours l'ancien format (couleur simple)
- ✅ **Sécurité** : Les données existantes ne seront pas perdues
- ✅ **Idempotent** : Le script peut être exécuté plusieurs fois sans problème
- ✅ **Flexible** : La colonne `TEXT` peut stocker jusqu'à 65,535 caractères (suffisant pour plusieurs couleurs avec images)

## Exemple d'utilisation dans le code PHP

```php
// Récupérer les couleurs
$product = $db->query("SELECT * FROM products WHERE id = 1")->fetch();
$colors = json_decode($product['color'], true);

if (json_last_error() === JSON_ERROR_NONE && is_array($colors)) {
    // Format JSON (nouveau)
    foreach ($colors as $color) {
        echo $color['name']; // "Rouge"
        echo $color['image']; // "path/to/image.jpg"
    }
} else {
    // Format texte simple (ancien)
    echo $product['color']; // "Rouge"
}
```

## Structure JSON attendue

```json
[
  {
    "name": "Nom de la couleur",
    "index": 1,
    "image": "chemin/vers/image.jpg" (optionnel)
  }
]
```

- `name` : Nom de la couleur (requis)
- `index` : Index de la couleur (1, 2, 3, etc.)
- `image` : Chemin vers l'image de la couleur (optionnel)


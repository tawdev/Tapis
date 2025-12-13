<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$db = getDB();

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $productId > 0; // Variable pour savoir si on modifie un produit existant
$product = null;
$productImages = [];

if ($productId > 0) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();

    if ($product) {
        $stmt = $db->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY is_primary DESC, display_order ASC");
        $stmt->execute([':id' => $productId]);
        $productImages = $stmt->fetchAll();
    }
}

// Récupérer les catégories
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Traitement du formulaire
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $shortDescription = trim($_POST['short_description'] ?? '');
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $salePrice = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $typeCategoryId = !empty($_POST['type_category_id']) ? (int)$_POST['type_category_id'] : null;
    $material = trim($_POST['material'] ?? '');
    $size = trim($_POST['size'] ?? '');
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    
    // Traitement des couleurs multiples
    $colorsCount = isset($_POST['colors_count']) ? (int)$_POST['colors_count'] : 0;
    $colorsArray = [];
    
    if ($colorsCount > 0 && $colorsCount <= 20) {
        for ($i = 1; $i <= $colorsCount; $i++) {
            $colorName = trim($_POST["color_{$i}"] ?? '');
            if (!empty($colorName)) {
                $colorsArray[] = [
                    'name' => clean($colorName),
                    'index' => $i
                ];
            }
        }
    }
    
    // Convertir en JSON pour stockage
    $color = !empty($colorsArray) ? json_encode($colorsArray, JSON_UNESCAPED_UNICODE) : '';
    $featured = isset($_POST['featured']) ? 1 : 0;
    $bestSeller = isset($_POST['best_seller']) ? 1 : 0;
    $status = isset($_POST['status']) ? clean($_POST['status']) : 'active';

    // Validation
    if (empty($name)) $errors[] = "Le nom est requis";
    if ($price <= 0) $errors[] = "Le prix doit être supérieur à 0";
    if ($categoryId <= 0) $errors[] = "La catégorie est requise";
    if ($salePrice && $salePrice >= $price) $errors[] = "Le prix promotionnel doit être inférieur au prix normal";
    
    // Validation du nombre de couleurs
    if ($colorsCount < 0 || $colorsCount > 20) {
        $errors[] = "Le nombre de couleurs doit être entre 0 et 20";
        $colorsCount = 0;
    }
    
    // Validation : si colorsCount > 0, au moins une couleur doit avoir un nom
    if ($colorsCount > 0 && empty($colorsArray)) {
        $errors[] = "Veuillez entrer au moins un nom de couleur";
    }

    if (empty($errors)) {
        try {
            $slug = generateSlug($name);
            if ($productId > 0) {
                // Mise à jour
                $stmt = $db->prepare("UPDATE products SET name = :name, slug = :slug, description = :description, short_description = :short_description, 
                                      price = :price, sale_price = :sale_price, category_id = :category_id, type_category_id = :type_category_id, material = :material, 
                                      size = :size, color = :color, stock = :stock, featured = :featured, best_seller = :best_seller, status = :status 
                                      WHERE id = :id");
                $stmt->execute([
                    ':id' => $productId,
                    ':name' => $name,
                    ':slug' => $slug,
                    ':description' => $description,
                    ':short_description' => $shortDescription,
                    ':price' => $price,
                    ':sale_price' => $salePrice,
                    ':category_id' => $categoryId,
                    ':type_category_id' => $typeCategoryId,
                    ':material' => $material,
                    ':size' => $size,
                    ':color' => $color,
                    ':stock' => $stock,
                    ':featured' => $featured,
                    ':best_seller' => $bestSeller,
                    ':status' => $status
                ]);
            } else {
                // Insertion
                $stmt = $db->prepare("INSERT INTO products (name, slug, description, short_description, price, sale_price, category_id, type_category_id, material, size, color, stock, featured, best_seller, status) 
                                      VALUES (:name, :slug, :description, :short_description, :price, :sale_price, :category_id, :type_category_id, :material, :size, :color, :stock, :featured, :best_seller, :status)");
                $stmt->execute([
                    ':name' => $name,
                    ':slug' => $slug,
                    ':description' => $description,
                    ':short_description' => $shortDescription,
                    ':price' => $price,
                    ':sale_price' => $salePrice,
                    ':category_id' => $categoryId,
                    ':type_category_id' => $typeCategoryId,
                    ':material' => $material,
                    ':size' => $size,
                    ':color' => $color,
                    ':stock' => $stock,
                    ':featured' => $featured,
                    ':best_seller' => $bestSeller,
                    ':status' => $status
                ]);
                $productId = $db->lastInsertId();
            }

            // Gestion des images générales (ancien système)
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['name'] as $key => $filename) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['images']['name'][$key],
                            'type' => $_FILES['images']['type'][$key],
                            'tmp_name' => $_FILES['images']['tmp_name'][$key],
                            'size' => $_FILES['images']['size'][$key]
                        ];
                        $result = uploadImage($file, $productId);
                        if ($result['success']) {
                            $isPrimary = ($key == 0 && count($productImages) == 0) ? 1 : 0;
                            $stmt = $db->prepare("INSERT INTO product_images (product_id, image_path, is_primary, display_order) VALUES (:product_id, :image_path, :is_primary, :display_order)");
                            $stmt->execute([
                                ':product_id' => $productId,
                                ':image_path' => $result['path'],
                                ':is_primary' => $isPrimary,
                                ':display_order' => count($productImages) + $key
                            ]);
                        }
                    }
                }
            }
            
            // Gestion des images de couleurs
            if ($colorsCount > 0 && $colorsCount <= 20 && !empty($colorsArray)) {
                // Parcourir les couleurs et ajouter les images
                foreach ($colorsArray as $index => &$colorData) {
                    $i = $colorData['index'];
                    $colorName = $colorData['name'];
                    
                    // Vérifier si une image a été uploadée pour cette couleur
                    if (isset($_FILES["upload_image_{$i}"]) && $_FILES["upload_image_{$i}"]['error'] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES["upload_image_{$i}"]['name'],
                            'type' => $_FILES["upload_image_{$i}"]['type'],
                            'tmp_name' => $_FILES["upload_image_{$i}"]['tmp_name'],
                            'size' => $_FILES["upload_image_{$i}"]['size']
                        ];
                        
                        $result = uploadImage($file, $productId);
                        if ($result['success']) {
                            // Insérer l'image
                            $isPrimary = (count($productImages) == 0 && $i == 1) ? 1 : 0;
                            $stmt = $db->prepare("INSERT INTO product_images (product_id, image_path, is_primary, display_order) VALUES (:product_id, :image_path, :is_primary, :display_order)");
                            $stmt->execute([
                                ':product_id' => $productId,
                                ':image_path' => $result['path'],
                                ':is_primary' => $isPrimary,
                                ':display_order' => count($productImages) + $i
                            ]);
                            
                            // Ajouter le chemin de l'image au tableau des couleurs
                            $colorData['image'] = $result['path'];
                        }
                    }
                }
                unset($colorData); // Libérer la référence
                
                // Mettre à jour le champ color avec le JSON complet incluant les images
                $color = json_encode($colorsArray, JSON_UNESCAPED_UNICODE);
                $stmt = $db->prepare("UPDATE products SET color = :color WHERE id = :id");
                $stmt->execute([
                    ':color' => $color,
                    ':id' => $productId
                ]);
            }

            // Rediriger vers la liste si modification, sinon rester sur le formulaire si création
            if ($isEdit) {
                // Modification : rediriger vers la liste des produits
                redirect('products.php?success=1');
            } else {
                // Création : rester sur le formulaire avec message de succès
                redirect('product_form.php?id=' . $productId . '&success=1');
            }
        } catch (Exception $e) {
            $errors[] = "Erreur: " . $e->getMessage();
        }
    }
}

if (isset($_GET['success'])) {
    $success = true;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $productId > 0 ? 'Modifier' : 'Ajouter'; ?> Produit - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <h1><?php echo $productId > 0 ? 'Modifier' : 'Ajouter'; ?> un Produit</h1>

            <?php if ($success): ?>
                <div class="alert alert-success">Produit enregistré avec succès !</div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo clean($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="admin-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Nom du produit *</label>
                        <input type="text" id="name" name="name" required value="<?php echo $product ? clean($product['name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="category_id">Catégorie *</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Sélectionner une catégorie</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($product && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo clean($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="type-category-group" style="display: none;">
                        <label for="type_category_id">Type de catégorie</label>
                        <select id="type_category_id" name="type_category_id">
                            <option value="">Sélectionner un type de catégorie</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="short_description">Description courte</label>
                    <input type="text" id="short_description" name="short_description" value="<?php echo $product ? clean($product['short_description']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="5"><?php echo $product ? clean($product['description']) : ''; ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Prix (MAD) *</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required value="<?php echo $product ? $product['price'] : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="sale_price">Prix promotionnel (MAD)</label>
                        <input type="number" id="sale_price" name="sale_price" step="0.01" min="0" value="<?php echo $product && $product['sale_price'] ? $product['sale_price'] : ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="material">Matériau</label>
                        <input type="text" id="material" name="material" value="<?php echo $product ? clean($product['material']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="size">Taille</label>
                        <input type="text" id="size" name="size" value="<?php echo $product ? clean($product['size']) : ''; ?>">
                    </div>

                </div>

                <!-- Section Couleurs multiples -->
                <div class="form-group" id="colors-section">
                    <h3 style="margin-bottom: 1rem; color: var(--primary-color);">🎨 Couleurs du produit</h3>
                    
                    <div class="form-group">
                        <label for="colors_count">Nombre de couleurs</label>
                        <input type="number" 
                               id="colors_count" 
                               name="colors_count" 
                               min="0" 
                               max="20" 
                               value="0"
                               onchange="updateColorFields()"
                               oninput="updateColorFields()">
                        <small style="color: var(--text-light); display: block; margin-top: 0.25rem;">
                            Entrez le nombre de couleurs disponibles pour ce produit (0 à 20)
                        </small>
                    </div>

                    <div id="color-fields-container">
                        <!-- Les champs de couleurs seront générés dynamiquement ici -->
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="stock">Stock</label>
                        <input type="number" id="stock" name="stock" min="0" value="<?php echo $product ? $product['stock'] : 0; ?>">
                    </div>

                    <div class="form-group">
                        <label for="status">Statut</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo ($product && $product['status'] === 'active') ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactive" <?php echo ($product && $product['status'] === 'inactive') ? 'selected' : ''; ?>>Inactif</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="featured" value="1" <?php echo ($product && $product['featured']) ? 'checked' : ''; ?>>
                        Produit en vedette
                    </label>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="best_seller" value="1" <?php echo ($product && $product['best_seller']) ? 'checked' : ''; ?>>
                        Meilleure vente
                    </label>
                </div>

                <div class="form-group">
                    <label for="images">Images (plusieurs fichiers possibles)</label>
                    <input type="file" id="images" name="images[]" multiple accept="image/*">
                </div>

                <?php if (count($productImages) > 0): ?>
                    <div class="form-group">
                        <label>Images actuelles</label>
                        <div class="product-images-list">
                            <?php foreach ($productImages as $image): ?>
                                <div class="product-image-item">
                                    <img src="../<?php echo clean($image['image_path']); ?>" alt="">
                                    <a href="image_delete.php?id=<?php echo $image['id']; ?>&product_id=<?php echo $productId; ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Supprimer cette image ?');">Supprimer</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <a href="products.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        // Charger les types de catégories selon la catégorie sélectionnée
        const categorySelect = document.getElementById('category_id');
        const typeCategoryGroup = document.getElementById('type-category-group');
        const typeCategorySelect = document.getElementById('type_category_id');
        
        // Fonction pour charger les types de catégories
        function loadTypeCategories(categoryId, selectedTypeId = null) {
            if (!categoryId || categoryId === '') {
                typeCategoryGroup.style.display = 'none';
                typeCategorySelect.innerHTML = '<option value="">Sélectionner un type de catégorie</option>';
                return;
            }
            
            // Afficher un indicateur de chargement
            typeCategorySelect.innerHTML = '<option value="">Chargement...</option>';
            typeCategorySelect.disabled = true;
            
            // Faire la requête AJAX
            fetch(`api/get_types_categories.php?category_id=${categoryId}`)
                .then(response => response.json())
                .then(data => {
                    typeCategorySelect.innerHTML = '<option value="">Sélectionner un type de catégorie</option>';
                    
                    if (data.types && data.types.length > 0) {
                        // Afficher le groupe de sélection
                        typeCategoryGroup.style.display = 'block';
                        
                        // Ajouter les options
                        data.types.forEach(type => {
                            const option = document.createElement('option');
                            option.value = type.id;
                            option.textContent = type.name;
                            if (selectedTypeId && type.id == selectedTypeId) {
                                option.selected = true;
                            }
                            typeCategorySelect.appendChild(option);
                        });
                    } else {
                        // Cacher le groupe si aucun type n'est disponible
                        typeCategoryGroup.style.display = 'none';
                    }
                    
                    typeCategorySelect.disabled = false;
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des types de catégories:', error);
                    typeCategorySelect.innerHTML = '<option value="">Erreur de chargement</option>';
                    typeCategoryGroup.style.display = 'none';
                    typeCategorySelect.disabled = false;
                });
        }
        
        // Écouter les changements de catégorie
        categorySelect.addEventListener('change', function() {
            const categoryId = this.value;
            loadTypeCategories(categoryId);
        });
        
        // Charger les types au chargement de la page si on est en mode édition
        <?php if ($product && isset($product['category_id']) && $product['category_id']): ?>
            // Charger les types pour la catégorie actuelle
            const currentCategoryId = <?php echo $product['category_id']; ?>;
            const currentTypeCategoryId = <?php echo isset($product['type_category_id']) && $product['type_category_id'] ? $product['type_category_id'] : 'null'; ?>;
            
            // Attendre que le DOM soit prêt
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    loadTypeCategories(currentCategoryId, currentTypeCategoryId);
                });
            } else {
                loadTypeCategories(currentCategoryId, currentTypeCategoryId);
            }
        <?php endif; ?>

        // ========== GESTION DES CHAMPS DE COULEURS DYNAMIQUES ==========
        
        // Fonction pour mettre à jour les champs de couleurs
        function updateColorFields() {
            const colorsCountInput = document.getElementById('colors_count');
            const container = document.getElementById('color-fields-container');
            
            // Récupérer le nombre de couleurs
            let colorsCount = parseInt(colorsCountInput.value) || 0;
            
            // Validation : entre 0 et 20
            if (colorsCount < 0) {
                colorsCount = 0;
                colorsCountInput.value = 0;
            }
            if (colorsCount > 20) {
                colorsCount = 20;
                colorsCountInput.value = 20;
                alert('Le nombre maximum de couleurs est de 20.');
            }
            
            // Vider le conteneur
            container.innerHTML = '';
            
            // Générer les champs pour chaque couleur
            for (let i = 1; i <= colorsCount; i++) {
                const colorField = document.createElement('div');
                colorField.className = 'color-field-group';
                colorField.style.cssText = 'margin-bottom: 1.5rem; padding: 1rem; border: 2px solid var(--border-color); border-radius: 8px; background: var(--light-color);';
                
                colorField.innerHTML = `
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color); font-size: 1.1rem;">
                        🎨 Couleur ${i}
                    </h4>
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="color_${i}">Nom de la couleur ${i} *</label>
                            <input type="text" 
                                   id="color_${i}" 
                                   name="color_${i}" 
                                   placeholder="Ex: Rouge, Bleu, Vert..."
                                   required
                                   style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-color); border-radius: 6px;">
                        </div>
                        <div class="form-group">
                            <label for="upload_image_${i}">Image pour la couleur ${i}</label>
                            <input type="file" 
                                   id="upload_image_${i}" 
                                   name="upload_image_${i}" 
                                   accept="image/*"
                                   style="width: 100%; padding: 0.5rem; border: 2px solid var(--border-color); border-radius: 6px;">
                            <small style="color: var(--text-light); display: block; margin-top: 0.25rem;">
                                Formats acceptés: JPG, PNG, WEBP (max 5MB)
                            </small>
                        </div>
                    </div>
                `;
                
                container.appendChild(colorField);
            }
            
            // Afficher un message si aucune couleur
            if (colorsCount === 0) {
                container.innerHTML = '<p style="color: var(--text-light); font-style: italic; text-align: center; padding: 1rem;">Aucune couleur définie. Entrez un nombre pour ajouter des couleurs.</p>';
            }
        }
        
        // Initialiser les champs au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            // Si on est en mode édition, charger les couleurs existantes
            <?php 
            if ($product && !empty($product['color'])) {
                // Essayer de décoder le JSON si c'est un JSON, sinon utiliser comme couleur simple
                $colorsData = json_decode($product['color'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($colorsData)) {
                    // Format JSON avec couleurs multiples
                    $colorsCount = count($colorsData);
                    echo "document.getElementById('colors_count').value = {$colorsCount};\n";
                    echo "updateColorFields();\n";
                    // Remplir les champs avec les valeurs existantes
                    foreach ($colorsData as $index => $colorData) {
                        $i = $index + 1;
                        if (isset($colorData['name'])) {
                            echo "if (document.getElementById('color_{$i}')) {\n";
                            echo "    document.getElementById('color_{$i}').value = " . json_encode($colorData['name'], JSON_HEX_APOS | JSON_HEX_QUOT) . ";\n";
                            echo "}\n";
                        }
                    }
                } else {
                    // Ancien format : couleur simple - ne rien faire pour l'instant
                    echo "// Ancien format de couleur détecté (couleur simple)\n";
                }
            } else {
                echo "updateColorFields(); // Initialiser avec 0 couleurs\n";
            }
            ?>
        });
    </script>
</body>
</html>


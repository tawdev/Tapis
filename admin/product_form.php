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

// Récupérer les types
$stmt = $db->query("SELECT * FROM types ORDER BY name");
$types = $stmt->fetchAll();

// Récupérer les catégories initiales si on est en édition et qu'un type est défini
$initialCategories = [];
if ($product && !empty($product['type_id'])) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE type_id = :type_id ORDER BY name");
    $stmt->execute([':type_id' => $product['type_id']]);
    $initialCategories = $stmt->fetchAll();
}

// Valeur initiale du champ couleurs (texte)
$initialColorsText = '';
if ($product && !empty($product['color'])) {
    $decodedColors = json_decode($product['color'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedColors)) {
        $names = array_map(function ($c) { return trim($c['name'] ?? ''); }, $decodedColors);
        $names = array_filter($names, fn($n) => $n !== '');
        $initialColorsText = implode(', ', $names);
    } else {
        $initialColorsText = trim($product['color']);
    }
}

// Valeurs initiales pour le sur mesure
$initialMaxWidth = '';
$initialMaxHeight = '';
if ($product && !empty($product['type_id']) && !empty($product['size'])) {
    $productTypeName = null;
    foreach ($types as $t) {
        if ((int)$t['id'] === (int)$product['type_id']) {
            $productTypeName = strtolower(trim($t['name']));
            break;
        }
    }
    if ($productTypeName === 'sur_mesure' || $productTypeName === 'sur mesure') {
        if (strpos($product['size'], 'x') !== false) {
            $parts = explode('x', strtolower($product['size']));
            if (count($parts) === 2) {
                $initialMaxWidth = trim($parts[0]);
                $initialMaxHeight = trim($parts[1]);
            }
        }
    }
}

// Couleurs disponibles globales (pour palette de cases à cocher)
$availableColorsAdmin = [];

// Couleurs de base proposées même si elles n'existent pas encore en base
$baseColors = [
    'Beige',
    'Blanch',
    'Bleu',
    'Green',
    'Gris',
    'Noir',
    'Rouge',
    'Jaune',
    'Orange',
    'Rose',
    'Marron',
    'Turquoise',
    'Violet'
];
foreach ($baseColors as $baseColor) {
    $key = mb_strtolower($baseColor, 'UTF-8');
    $availableColorsAdmin[$key] = $baseColor;
}

$stmt = $db->query("SELECT color FROM products WHERE color IS NOT NULL AND color <> ''");
while ($row = $stmt->fetch()) {
    $rawColor = trim($row['color']);
    if ($rawColor === '') continue;

    $decoded = json_decode($rawColor, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        foreach ($decoded as $c) {
            if (!empty($c['name'])) {
                $name = trim($c['name']);
                if ($name !== '') {
                    $key = mb_strtolower($name, 'UTF-8');
                    $availableColorsAdmin[$key] = $name;
                }
            }
        }
    } else {
        // Ancien format: texte simple
        $name = $rawColor;
        $key = mb_strtolower($name, 'UTF-8');
        $availableColorsAdmin[$key] = $name;
    }
}
// Ajouter aussi les couleurs initiales du produit (si nouvelles)
if (!empty($initialColorsText)) {
    $parts = preg_split('/[,\\n]+/', $initialColorsText);
    $parts = array_map('trim', $parts);
    foreach ($parts as $p) {
        if ($p === '') continue;
        $key = mb_strtolower($p, 'UTF-8');
        if (!isset($availableColorsAdmin[$key])) {
            $availableColorsAdmin[$key] = $p;
        }
    }
}
ksort($availableColorsAdmin);

// Traitement du formulaire
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $shortDescription = trim($_POST['short_description'] ?? '');
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $salePrice = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
    $typeId = !empty($_POST['type_id']) ? (int)$_POST['type_id'] : null;
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $typeCategoryId = (isset($_POST['type_category_id']) && $_POST['type_category_id'] !== '')
        ? (int)$_POST['type_category_id']
        : null;
    $colorsText = trim($_POST['colors_text'] ?? '');
    $material = trim($_POST['material'] ?? '');
    $size = trim($_POST['size'] ?? '');
    $maxWidth = isset($_POST['max_width']) ? trim($_POST['max_width']) : '';
    $maxHeight = isset($_POST['max_height']) ? trim($_POST['max_height']) : '';
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    
    // Traitement des couleurs sous forme de texte (séparées par virgule)
    $colorsArray = [];
    if (!empty($colorsText)) {
        $parts = preg_split('/[,\\n]+/', $colorsText);
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts, fn($p) => $p !== '');
        $index = 1;
        foreach ($parts as $p) {
            $colorsArray[] = [
                'name' => clean($p),
                'index' => $index++
            ];
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
    if ($typeId !== null && $typeId <= 0) $errors[] = "Le type sélectionné est invalide";
    if ($categoryId <= 0) $errors[] = "La catégorie est requise";
    if ($salePrice && $salePrice >= $price) $errors[] = "Le prix promotionnel doit être inférieur au prix normal";

    // Vérifier que la catégorie appartient au type sélectionné (si fourni)
    if ($categoryId > 0) {
        $stmt = $db->prepare("SELECT type_id FROM categories WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);
        $cat = $stmt->fetch();
        if (!$cat) {
            $errors[] = "La catégorie sélectionnée n'existe pas";
        } else {
            $categoryTypeId = $cat['type_id'] ?? null;
            if ($typeId && $categoryTypeId && (int)$categoryTypeId !== $typeId) {
                $errors[] = "La catégorie choisie n'appartient pas au type sélectionné";
            }
        }

        // Si un type de catégorie est sélectionné, vérifier qu'il appartient bien à cette catégorie
        if ($typeCategoryId !== null) {
            $stmt = $db->prepare("SELECT id FROM types_categorier WHERE id = :id AND category_id = :category_id");
            $stmt->execute([
                ':id' => $typeCategoryId,
                ':category_id' => $categoryId
            ]);
            $typeCatRow = $stmt->fetch();
            if (!$typeCatRow) {
                $errors[] = "Le type de catégorie sélectionné n'appartient pas à la catégorie choisie";
            }
        }
    }
    
    // Validation couleurs texte
    if (!empty($colorsText) && empty($colorsArray)) {
        $errors[] = "Veuillez saisir au moins un nom de couleur valide";
    }

    // Récupérer le nom du type (pour logique sur mesure)
    $typeName = null;
    if ($typeId) {
        $stmt = $db->prepare("SELECT name FROM types WHERE id = :id");
        $stmt->execute([':id' => $typeId]);
        $typeRow = $stmt->fetch();
        $typeName = $typeRow['name'] ?? null;
    }

    // Ajuster la taille pour le type sur mesure
    if ($typeName) {
        $normalizedType = strtolower(trim($typeName));
        if ($normalizedType === 'sur_mesure' || $normalizedType === 'sur mesure') {
            if ($maxWidth === '' || $maxHeight === '') {
                $errors[] = "Veuillez saisir la largeur et la hauteur maximum pour le type sur mesure";
            } else {
                $size = $maxWidth . 'x' . $maxHeight;
            }
        }
    }

    if (empty($errors)) {
        try {
            $slug = generateSlug($name);

            // S'assurer que le slug est unique pour les produits
            if ($productId > 0) {
                // Vérifier si le slug est déjà utilisé par un autre produit
                $stmt = $db->prepare("SELECT id FROM products WHERE slug = :slug AND id != :id");
                $stmt->execute([':slug' => $slug, ':id' => $productId]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Générer un slug unique en ajoutant un suffixe numérique
                    $baseSlug = $slug;
                    $counter = 1;
                    do {
                        $slug = $baseSlug . '-' . $counter;
                        $stmt = $db->prepare("SELECT id FROM products WHERE slug = :slug AND id != :id");
                        $stmt->execute([':slug' => $slug, ':id' => $productId]);
                        $existing = $stmt->fetch();
                        $counter++;
                    } while ($existing);
                }
            } else {
                // Insertion : vérifier si le slug existe déjà
                $stmt = $db->prepare("SELECT id FROM products WHERE slug = :slug");
                $stmt->execute([':slug' => $slug]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $baseSlug = $slug;
                    $counter = 1;
                    do {
                        $slug = $baseSlug . '-' . $counter;
                        $stmt = $db->prepare("SELECT id FROM products WHERE slug = :slug");
                        $stmt->execute([':slug' => $slug]);
                        $existing = $stmt->fetch();
                        $counter++;
                    } while ($existing);
                }
            }

            if ($productId > 0) {
                // Mise à jour
                $stmt = $db->prepare("UPDATE products SET name = :name, slug = :slug, description = :description, short_description = :short_description, 
                                      price = :price, sale_price = :sale_price, category_id = :category_id, type_id = :type_id, type_category_id = :type_category_id, material = :material, 
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
                    ':type_id' => $typeId,
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
                $stmt = $db->prepare("INSERT INTO products (name, slug, description, short_description, price, sale_price, category_id, type_id, type_category_id, material, size, color, stock, featured, best_seller, status) 
                                      VALUES (:name, :slug, :description, :short_description, :price, :sale_price, :category_id, :type_id, :type_category_id, :material, :size, :color, :stock, :featured, :best_seller, :status)");
                $stmt->execute([
                    ':name' => $name,
                    ':slug' => $slug,
                    ':description' => $description,
                    ':short_description' => $shortDescription,
                    ':price' => $price,
                    ':sale_price' => $salePrice,
                    ':category_id' => $categoryId,
                    ':type_id' => $typeId,
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
    <style>
        /* Cases à cocher de couleurs dans le back-office */
        .color-checkbox-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 0.5rem;
        }
        .color-checkbox-item {
            display: inline-flex;
            align-items: center;
        }
        .color-checkbox-item input[type="checkbox"] {
            display: none;
        }
        .color-checkbox-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.6rem;
            border-radius: 999px;
            border: 1px solid var(--border-color);
            background: #fff;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .color-checkbox-item input[type="checkbox"]:checked + .color-checkbox-pill {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(139,69,19,0.2);
            background: rgba(139,69,19,0.04);
            font-weight: 600;
        }
        .color-checkbox-pill:hover {
            border-color: var(--primary-color);
        }
        .color-checkbox-circle {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 1px solid rgba(0,0,0,0.2);
            box-shadow:
                inset 0 0 0 1px rgba(255,255,255,0.7),
                0 0 2px rgba(0,0,0,0.25);
        }
        .color-checkbox-circle-all {
            background: linear-gradient(135deg,#e53935,#fb8c00,#fdd835,#43a047,#1e88e5,#8e24aa);
        }
        .color-circle-beige { background:#f5f0e6; }
        .color-circle-blanc,
        .color-circle-blanch,
        .color-circle-white { background:#ffffff; }
        .color-circle-noir,
        .color-circle-black { background:#111111; }
        .color-circle-gris,
        .color-circle-gray,
        .color-circle-grey { background:#b0b0b0; }
        .color-circle-rouge,
        .color-circle-red { background:#e53935; }
        .color-circle-bleu,
        .color-circle-blue { background:#1e88e5; }
        .color-circle-vert,
        .color-circle-green { background:#43a047; }
        .color-circle-rose,
        .color-circle-pink { background:#ec407a; }
        .color-circle-jaune,
        .color-circle-yellow { background:#fdd835; }
        .color-circle-orange { background:#fb8c00; }
        .color-circle-marron,
        .color-circle-brown { background:#6d4c41; }
        .color-circle-turquoise { background:#1abc9c; }
        .color-circle-violet,
        .color-circle-purple { background:#8e24aa; }
    </style>
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

                    <div class="form-group" id="type-group">
                        <label for="type_id">Type *</label>
                        <select id="type_id" name="type_id" required>
                            <option value="">Sélectionner un type</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?php echo $type['id']; ?>" <?php echo ($product && $product['type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                    <?php echo clean($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="category_id">Catégorie *</label>
                        <select id="category_id" name="category_id" required disabled>
                            <option value="">Sélectionner une catégorie</option>
                            <?php if (!empty($initialCategories)): ?>
                                <?php foreach ($initialCategories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($product && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo clean($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small class="form-help">Choisissez d'abord un type pour afficher les catégories associées.</small>
                    </div>

                    <div class="form-group">
                        <label for="type_category_id">Type de catégorie (si défini pour cette catégorie)</label>
                        <select id="type_category_id" name="type_category_id" disabled>
                            <option value="">Aucun type de catégorie pour cette catégorie</option>
                        </select>
                        <small class="form-help">
                            Liste des types définis dans le tableau <code>types_categorier</code> pour la catégorie choisie (optionnel).
                        </small>
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
                        <label for="price"><span id="price-label">Prix (MAD) *</span></label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required value="<?php echo $product ? $product['price'] : ''; ?>">
                        <small class="form-help" id="price-help" style="display:block;margin-top:0.25rem;color:var(--text-light);"></small>
                    </div>

                    <div class="form-group">
                        <label for="sale_price"><span id="sale-price-label">Prix promotionnel (MAD)</span></label>
                        <input type="number" id="sale_price" name="sale_price" step="0.01" min="0" value="<?php echo $product && $product['sale_price'] ? $product['sale_price'] : ''; ?>">
                        <small class="form-help" id="sale-price-help" style="display:block;margin-top:0.25rem;color:var(--text-light);"></small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="material">Matériau</label>
                        <input type="text" id="material" name="material" value="<?php echo $product ? clean($product['material']) : ''; ?>">
                    </div>

                    <div class="form-group" id="size-group">
                        <label for="size">Taille</label>
                        <input type="text" id="size" name="size" value="<?php echo $product ? clean($product['size']) : ''; ?>">
                        <small class="form-help">Ex : 200x300</small>
                    </div>

                    <div class="form-group" id="size-surmesure-group" style="display: none;">
                        <label>Taille (sur mesure)</label>
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="max_width">Largeur max (cm)</label>
                                <input type="number" step="0.01" min="0" id="max_width" name="max_width" value="<?php echo clean($initialMaxWidth); ?>">
                            </div>
                            <div class="form-group">
                                <label for="max_height">Hauteur max (cm)</label>
                                <input type="number" step="0.01" min="0" id="max_height" name="max_height" value="<?php echo clean($initialMaxHeight); ?>">
                            </div>
                        </div>
                        <small class="form-help">Saisissez les dimensions maximales en cm (L x H) pour le sur mesure.</small>
                    </div>

                </div>

                <!-- Section Couleurs (saisie texte + cases à cocher) -->
                <div class="form-group">
                    <h3 style="margin-bottom: 0.75rem; color: var(--primary-color);">🎨 Couleurs du tapis</h3>
                    <label for="colors_text">Couleurs (séparées par des virgules)</label>
                    <input type="text"
                           id="colors_text"
                           name="colors_text"
                           placeholder="Ex : rouge, noir, bleu"
                           value="<?php echo clean($initialColorsText); ?>"
                           style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-color); border-radius: 6px;">
                    <small style="color: var(--text-light); display: block; margin-top: 0.25rem;">
                        Vous pouvez saisir des couleurs manuellement, ou utiliser les cases ci-dessous. Les deux seront synchronisés automatiquement.
                    </small>

                    <?php
                    // Couleurs déjà sélectionnées pour ce produit (pour cocher les cases)
                    $initialSelectedColors = [];
                    if (!empty($initialColorsText)) {
                        $parts = preg_split('/[,\\n]+/', $initialColorsText);
                        $parts = array_map('trim', $parts);
                        foreach ($parts as $p) {
                            if ($p === '') continue;
                            $initialSelectedColors[mb_strtolower($p, 'UTF-8')] = true;
                        }
                    }
                    ?>
                    <div class="color-checkbox-grid" id="color-checkbox-grid">
                        <!-- Case "toutes les couleurs" (ne modifie pas le texte, juste visuel) -->
                        <label class="color-checkbox-item">
                            <input type="checkbox" id="color_all_helper">
                            <span class="color-checkbox-pill">
                                <span class="color-checkbox-circle color-checkbox-circle-all"></span>
                                <span>Toutes les couleurs</span>
                            </span>
                        </label>

                        <?php foreach ($availableColorsAdmin as $key => $colorName):
                            $displayName = mb_convert_case(trim($colorName), MB_CASE_TITLE, 'UTF-8');
                            $isChecked = isset($initialSelectedColors[$key]);
                            $slug = preg_replace('/[^a-z0-9]+/i', '-', $key);
                        ?>
                            <label class="color-checkbox-item">
                                <input type="checkbox"
                                       class="color-checkbox-input"
                                       value="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>"
                                       <?php echo $isChecked ? 'checked' : ''; ?>>
                                <span class="color-checkbox-pill">
                                    <span class="color-checkbox-circle <?php echo 'color-circle-' . $slug; ?>"></span>
                                    <span><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
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
        // Charger les catégories selon le type sélectionné
        const typeSelect = document.getElementById('type_id');
        const categorySelect = document.getElementById('category_id');
        const typeCategorySelect = document.getElementById('type_category_id');
        
        function loadCategories(typeId, selectedCategoryId = null) {
            if (!typeId || typeId === '') {
                categorySelect.innerHTML = '<option value=\"\">Sélectionner une catégorie</option>';
                categorySelect.disabled = true;
                return;
            }
            
            categorySelect.innerHTML = '<option value=\"\">Chargement...</option>';
            categorySelect.disabled = true;
            
            fetch(`api/get_categories_by_type.php?type_id=${typeId}`)
                .then(response => response.json())
                .then(data => {
                    categorySelect.innerHTML = '<option value=\"\">Sélectionner une catégorie</option>';
                    
                    if (data.categories && data.categories.length > 0) {
                        data.categories.forEach(cat => {
                            const option = document.createElement('option');
                            option.value = cat.id;
                            option.textContent = cat.name;
                            if (selectedCategoryId && cat.id == selectedCategoryId) {
                                option.selected = true;
                            }
                            categorySelect.appendChild(option);
                        });
                        categorySelect.disabled = false;
                    } else {
                        categorySelect.innerHTML = '<option value=\"\">Aucune catégorie pour ce type</option>';
                        categorySelect.disabled = true;
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des catégories:', error);
                    categorySelect.innerHTML = '<option value=\"\">Erreur de chargement</option>';
                    categorySelect.disabled = true;
                });
        }

        // Charger les types_categorier selon la catégorie sélectionnée
        function loadTypesCategorier(categoryId, selectedTypeCategoryId = null) {
            if (!typeCategorySelect) {
                return;
            }

            if (!categoryId || categoryId === '') {
                typeCategorySelect.innerHTML = '<option value=\"\">Aucun type de catégorie pour cette catégorie</option>';
                typeCategorySelect.disabled = true;
                return;
            }

            typeCategorySelect.innerHTML = '<option value=\"\">Chargement...</option>';
            typeCategorySelect.disabled = true;

            fetch(`api/get_types_categorier_by_category.php?category_id=${categoryId}`)
                .then(response => response.json())
                .then(data => {
                    typeCategorySelect.innerHTML = '';

                    if (data.types && data.types.length > 0) {
                        const placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.textContent = 'Sélectionner un type de catégorie';
                        typeCategorySelect.appendChild(placeholder);

                        data.types.forEach(t => {
                            const option = document.createElement('option');
                            option.value = t.id;
                            option.textContent = t.name;
                            if (selectedTypeCategoryId && t.id == selectedTypeCategoryId) {
                                option.selected = true;
                            }
                            typeCategorySelect.appendChild(option);
                        });
                        typeCategorySelect.disabled = false;
                    } else {
                        typeCategorySelect.innerHTML = '<option value=\"\">Aucun type de catégorie pour cette catégorie</option>';
                        typeCategorySelect.disabled = true;
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des types de catégories:', error);
                    typeCategorySelect.innerHTML = '<option value=\"\">Erreur de chargement</option>';
                    typeCategorySelect.disabled = true;
                });
        }
        
        // Écouter les changements de type
        typeSelect.addEventListener('change', function() {
            const typeId = this.value;
            toggleSizeFields();
            loadCategories(typeId);
        });

        // Écouter les changements de catégorie pour charger les types_categorier
        if (categorySelect && typeCategorySelect) {
            categorySelect.addEventListener('change', function() {
                const categoryId = this.value;
                loadTypesCategorier(categoryId);
            });
        }
        
        // Chargement initial si édition
        <?php if ($product && isset($product['type_id']) && $product['type_id']): ?>
            const currentTypeId = <?php echo $product['type_id']; ?>;
            const currentCategoryId = <?php echo $product['category_id']; ?>;
            const currentTypeCategoryId = <?php echo isset($product['type_category_id']) && $product['type_category_id'] ? (int)$product['type_category_id'] : 'null'; ?>;
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    loadCategories(currentTypeId, currentCategoryId);
                    toggleSizeFields();
                    // Charger aussi les types_categorier si une catégorie est déjà définie
                    if (currentCategoryId) {
                        loadTypesCategorier(currentCategoryId, currentTypeCategoryId);
                    }
                });
            } else {
                loadCategories(currentTypeId, currentCategoryId);
                toggleSizeFields();
                if (currentCategoryId) {
                    loadTypesCategorier(currentCategoryId, currentTypeCategoryId);
                }
            }
        <?php else: ?>
            categorySelect.disabled = true;
            document.addEventListener('DOMContentLoaded', function() {
                toggleSizeFields();
            });
        <?php endif; ?>

        // Basculer les champs de taille selon le type (sur mesure vs classique)
        function toggleSizeFields() {
            const sizeGroup = document.getElementById('size-group');
            const sizeSurGroup = document.getElementById('size-surmesure-group');
            const sizeInput = document.getElementById('size');
            const typeOption = typeSelect.options[typeSelect.selectedIndex];
            const typeName = typeOption ? typeOption.textContent.toLowerCase().trim() : '';
            const isSurMesure = (typeName === 'sur_mesure' || typeName === 'sur mesure');

            if (isSurMesure) {
                sizeGroup.style.display = 'none';
                sizeSurGroup.style.display = 'block';
            } else {
                sizeGroup.style.display = 'block';
                sizeSurGroup.style.display = 'none';
            }

            // Adapter les labels des prix pour le sur mesure
            const priceLabel = document.getElementById('price-label');
            const salePriceLabel = document.getElementById('sale-price-label');
            const priceHelp = document.getElementById('price-help');
            const salePriceHelp = document.getElementById('sale-price-help');
            if (isSurMesure) {
                priceLabel.textContent = 'Prix au m² (MAD) *';
                salePriceLabel.textContent = 'Prix promo au m² (MAD)';
                priceHelp.textContent = 'Saisissez le prix par mètre carré.';
                salePriceHelp.textContent = 'Optionnel : prix promo par mètre carré.';
            } else {
                priceLabel.textContent = 'Prix (MAD) *';
                salePriceLabel.textContent = 'Prix promotionnel (MAD)';
                priceHelp.textContent = '';
                salePriceHelp.textContent = '';
            }
        }

        // Synchronisation cases à cocher couleurs <-> champ texte
        (function syncColorsCheckboxWithInput() {
            const colorsInput = document.getElementById('colors_text');
            const checkboxes = document.querySelectorAll('.color-checkbox-input');
            const allHelper = document.getElementById('color_all_helper');
            if (!colorsInput || !checkboxes.length) return;

            const normalize = (str) => (str || '').toLowerCase().trim();

            function updateInputFromCheckboxes() {
                const selected = [];
                checkboxes.forEach(cb => {
                    if (cb.checked) selected.push(cb.value.trim());
                });
                // Texte = uniquement les couleurs cochées, sans doublons
                const unique = Array.from(new Set(selected));
                colorsInput.value = unique.join(', ');

                // Mettre à jour "Toutes les couleurs"
                if (allHelper) {
                    const allChecked = unique.length === checkboxes.length;
                    allHelper.checked = allChecked;
                }
            }

            function updateCheckboxesFromInput() {
                const values = colorsInput.value
                    .split(/[,\\n]+/)
                    .map(s => s.trim())
                    .filter(s => s !== '');
                const set = new Set(values.map(v => normalize(v)));

                checkboxes.forEach(cb => {
                    cb.checked = set.has(normalize(cb.value));
                });

                // Mettre à jour "Toutes les couleurs"
                if (allHelper) {
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    allHelper.checked = allChecked;
                }
            }

            // Clic sur chaque case -> met à jour le texte
            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateInputFromCheckboxes);
            });

            // Clic sur "Toutes les couleurs"
            if (allHelper) {
                allHelper.addEventListener('change', function () {
                    const isChecked = this.checked;
                    checkboxes.forEach(cb => {
                        cb.checked = isChecked;
                    });
                    updateInputFromCheckboxes();
                });
            }

            // Modification manuelle du texte -> met à jour les cases
            colorsInput.addEventListener('input', function () {
                updateCheckboxesFromInput();
            });

            // Alignement initial
            updateCheckboxesFromInput();
            updateInputFromCheckboxes();
        })();
    </script>
</body>
</html>


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
    $material = trim($_POST['material'] ?? '');
    $size = trim($_POST['size'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $bestSeller = isset($_POST['best_seller']) ? 1 : 0;
    $status = isset($_POST['status']) ? clean($_POST['status']) : 'active';

    // Validation
    if (empty($name)) $errors[] = "Le nom est requis";
    if ($price <= 0) $errors[] = "Le prix doit être supérieur à 0";
    if ($categoryId <= 0) $errors[] = "La catégorie est requise";
    if ($salePrice && $salePrice >= $price) $errors[] = "Le prix promotionnel doit être inférieur au prix normal";

    if (empty($errors)) {
        try {
            $slug = generateSlug($name);
            if ($productId > 0) {
                // Mise à jour
                $stmt = $db->prepare("UPDATE products SET name = :name, slug = :slug, description = :description, short_description = :short_description, 
                                      price = :price, sale_price = :sale_price, category_id = :category_id, material = :material, 
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
                $stmt = $db->prepare("INSERT INTO products (name, slug, description, short_description, price, sale_price, category_id, material, size, color, stock, featured, best_seller, status) 
                                      VALUES (:name, :slug, :description, :short_description, :price, :sale_price, :category_id, :material, :size, :color, :stock, :featured, :best_seller, :status)");
                $stmt->execute([
                    ':name' => $name,
                    ':slug' => $slug,
                    ':description' => $description,
                    ':short_description' => $shortDescription,
                    ':price' => $price,
                    ':sale_price' => $salePrice,
                    ':category_id' => $categoryId,
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

            // Gestion des images
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

                    <div class="form-group">
                        <label for="color">Couleur</label>
                        <input type="text" id="color" name="color" value="<?php echo $product ? clean($product['color']) : ''; ?>">
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
</body>
</html>


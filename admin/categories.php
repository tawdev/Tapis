<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$db = getDB();

// Traitement du formulaire
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $typeId = !empty($_POST['type_id']) ? (int)$_POST['type_id'] : null;
        $imagePath = null;
        
        // Récupérer l'image existante si modification
        if ($action === 'edit' && $categoryId > 0) {
            $stmt = $db->prepare("SELECT image FROM categories WHERE id = :id");
            $stmt->execute([':id' => $categoryId]);
            $existingCategory = $stmt->fetch();
            $imagePath = $existingCategory['image'] ?? null;
        }
        
        // Gestion de l'upload d'image
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $errors[] = "Format d'image non autorisé (jpg, jpeg, png, webp uniquement)";
            } elseif ($file['size'] > 5000000) { // 5MB
                $errors[] = "Fichier trop volumineux (max 5MB)";
            } else {
                // Créer le dossier s'il n'existe pas
                $uploadDir = '../assets/images/categories/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Supprimer l'ancienne image si elle existe
                if ($imagePath && file_exists('../' . $imagePath)) {
                    unlink('../' . $imagePath);
                }
                
                // Générer un nom unique
                $filename = uniqid() . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $imagePath = 'assets/images/categories/' . $filename;
                } else {
                    $errors[] = "Erreur lors de l'upload de l'image";
                }
            }
        }
        
        if (empty($name)) {
            $errors[] = "Le nom est requis";
        } else {
            $slug = generateSlug($name);
            
            // Vérifier l'unicité du slug et générer un slug unique si nécessaire
            if ($action === 'add') {
                // Vérifier si le slug existe déjà
                $stmt = $db->prepare("SELECT id FROM categories WHERE slug = :slug");
                $stmt->execute([':slug' => $slug]);
                $existingCategory = $stmt->fetch();
                
                // Si le slug existe, ajouter un suffixe numérique
                if ($existingCategory) {
                    $baseSlug = $slug;
                    $counter = 1;
                    do {
                        $slug = $baseSlug . '-' . $counter;
                        $stmt = $db->prepare("SELECT id FROM categories WHERE slug = :slug");
                        $stmt->execute([':slug' => $slug]);
                        $existingCategory = $stmt->fetch();
                        $counter++;
                    } while ($existingCategory && $counter < 1000); // Limite de sécurité
                }
                
                $stmt = $db->prepare("INSERT INTO categories (name, slug, description, image, type_id) VALUES (:name, :slug, :description, :image, :type_id)");
                $stmt->execute([
                    ':name' => $name, 
                    ':slug' => $slug, 
                    ':description' => $description,
                    ':image' => $imagePath,
                    ':type_id' => $typeId
                ]);
                $success = true;
            } else {
                // Pour la mise à jour, vérifier que le slug n'est pas utilisé par une autre catégorie
                $stmt = $db->prepare("SELECT id FROM categories WHERE slug = :slug AND id != :id");
                $stmt->execute([':slug' => $slug, ':id' => $categoryId]);
                $existingCategory = $stmt->fetch();
                
                // Si le slug existe pour une autre catégorie, générer un slug unique
                if ($existingCategory) {
                    $baseSlug = $slug;
                    $counter = 1;
                    do {
                        $slug = $baseSlug . '-' . $counter;
                        $stmt = $db->prepare("SELECT id FROM categories WHERE slug = :slug AND id != :id");
                        $stmt->execute([':slug' => $slug, ':id' => $categoryId]);
                        $existingCategory = $stmt->fetch();
                        $counter++;
                    } while ($existingCategory && $counter < 1000); // Limite de sécurité
                }
                
                if ($imagePath) {
                    $stmt = $db->prepare("UPDATE categories SET name = :name, slug = :slug, description = :description, image = :image, type_id = :type_id WHERE id = :id");
                    $stmt->execute([
                        ':id' => $categoryId, 
                        ':name' => $name, 
                        ':slug' => $slug, 
                        ':description' => $description,
                        ':image' => $imagePath,
                        ':type_id' => $typeId
                    ]);
                } else {
                    $stmt = $db->prepare("UPDATE categories SET name = :name, slug = :slug, description = :description, type_id = :type_id WHERE id = :id");
                    $stmt->execute([
                        ':id' => $categoryId, 
                        ':name' => $name, 
                        ':slug' => $slug, 
                        ':description' => $description,
                        ':type_id' => $typeId
                    ]);
                }
                $success = true;
            }
        }
    } elseif ($action === 'delete') {
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        if ($categoryId > 0) {
            // Vérifier si la catégorie a des produits associés
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = :id");
            $stmt->execute([':id' => $categoryId]);
            $productCount = $stmt->fetch()['count'];
            
            if ($productCount > 0) {
                // Vérifier si certains de ces produits sont dans des commandes
                $stmt = $db->prepare("SELECT COUNT(DISTINCT oi.product_id) as count 
                                      FROM order_items oi 
                                      INNER JOIN products p ON oi.product_id = p.id 
                                      WHERE p.category_id = :id");
                $stmt->execute([':id' => $categoryId]);
                $productsInOrders = $stmt->fetch()['count'];
                
                if ($productsInOrders > 0) {
                    $errors[] = "Impossible de supprimer cette catégorie. Elle contient $productCount produit(s), dont $productsInOrders sont présents dans des commandes. Veuillez d'abord supprimer ou modifier les commandes concernées.";
                } else {
                    // Les produits ne sont pas dans des commandes, on peut les supprimer avec la catégorie
                    // (grâce à ON DELETE CASCADE sur products)
                    $stmt = $db->prepare("SELECT image FROM categories WHERE id = :id");
                    $stmt->execute([':id' => $categoryId]);
                    $category = $stmt->fetch();
                    
                    // Supprimer l'image si elle existe
                    if ($category && $category['image'] && file_exists('../' . $category['image'])) {
                        unlink('../' . $category['image']);
                    }
                    
                    $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
                    $stmt->execute([':id' => $categoryId]);
                    $success = true;
                }
            } else {
                // Aucun produit dans cette catégorie, on peut supprimer directement
                $stmt = $db->prepare("SELECT image FROM categories WHERE id = :id");
                $stmt->execute([':id' => $categoryId]);
                $category = $stmt->fetch();
                
                // Supprimer l'image si elle existe
                if ($category && $category['image'] && file_exists('../' . $category['image'])) {
                    unlink('../' . $category['image']);
                }
                
                $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
                $stmt->execute([':id' => $categoryId]);
                $success = true;
            }
        }
    }
}

// Récupérer les types pour le select
$stmt = $db->query("SELECT * FROM types ORDER BY name");
$types = $stmt->fetchAll();

// Récupérer les catégories avec le type
$stmt = $db->query("SELECT c.*, c.type_id, t.name as type_name, COUNT(p.id) as product_count 
                    FROM categories c 
                    LEFT JOIN types t ON c.type_id = t.id 
                    LEFT JOIN products p ON c.id = p.category_id 
                    GROUP BY c.id 
                    ORDER BY c.name");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <h1>Gestion des Catégories</h1>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✅</span>
                    <span>Opération réussie !</span>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">⚠️</span>
                    <div class="alert-content">
                        <strong>Erreur(s) :</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo clean($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formulaire d'ajout/modification -->
            <div class="admin-section">
                <h2 id="form-title">Ajouter une catégorie</h2>
                <form method="POST" enctype="multipart/form-data" class="admin-form" id="category-form">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="category_id" id="form-category-id" value="0">
                    
                    <div class="form-group">
                        <label for="name">Nom *</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="type_id">Type</label>
                        <select id="type_id" name="type_id">
                            <option value="">Aucun type</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo clean($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-help">Sélectionnez un type pour cette catégorie (optionnel)</small>
                    </div>

                    <div class="form-group">
                        <label for="image">Image de la catégorie</label>
                        <input type="file" id="image" name="image" accept="image/jpeg,image/jpg,image/png,image/webp">
                        <small class="form-help">Formats acceptés : JPG, PNG, WEBP (max 5MB)</small>
                        <div id="image-preview" class="image-preview-container">
                            <img id="preview-img" src="" alt="Aperçu" class="image-preview">
                        </div>
                        <div id="current-image" class="current-image-container"></div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">❌ Annuler</button>
                    </div>
                </form>
            </div>

            <!-- Liste des catégories -->
            <div class="admin-section">
                <h2>Liste des catégories</h2>
                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Nom</th>
                                <th>Type</th>
                                <th>Slug</th>
                                <th>Produits</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($categories) > 0): ?>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($category['image'])): ?>
                                                <img src="../<?php echo clean($category['image']); ?>" alt="<?php echo clean($category['name']); ?>" class="table-image">
                                            <?php else: ?>
                                                <span class="table-image-placeholder">Aucune image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo clean($category['name']); ?></strong></td>
                                        <td>
                                            <?php if (!empty($category['type_name'])): ?>
                                                <span style="padding: 0.25rem 0.75rem; background: var(--primary-color); color: white; border-radius: 12px; font-size: 0.85rem; font-weight: 600;">
                                                    <?php echo clean($category['type_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999; font-style: italic;">Aucun type</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?php echo clean($category['slug']); ?></code></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo $category['product_count']; ?> produit(s)</span>
                                        </td>
                                        <td>
                                            <div class="admin-actions">
                                                <button onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>', '<?php echo addslashes($category['description'] ?? ''); ?>', '<?php echo addslashes($category['image'] ?? ''); ?>', <?php echo $category['type_id'] ?? 'null'; ?>)" 
                                                        class="btn btn-sm btn-primary">✏️ Modifier</button>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?\n\n⚠️ Attention : Cette action supprimera tous les produits de cette catégorie.\n\nSi certains produits sont présents dans des commandes, la suppression sera bloquée.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">🗑️ Supprimer</button>
                                            </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <div class="empty-state-content">
                                            <span class="empty-icon">📁</span>
                                            <p>Aucune catégorie enregistrée</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        function editCategory(id, name, description, image, typeId) {
            document.getElementById('form-title').textContent = 'Modifier la catégorie';
            document.getElementById('form-action').value = 'edit';
            document.getElementById('form-category-id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('description').value = description;
            document.getElementById('type_id').value = typeId || '';
            
            // Afficher l'image actuelle si elle existe
            const currentImageDiv = document.getElementById('current-image');
            const imagePreview = document.getElementById('image-preview');
            if (image && image.trim() !== '') {
                currentImageDiv.innerHTML = '<p class="current-image-label"><strong>Image actuelle :</strong></p><img src="../' + image + '" alt="Image actuelle" class="current-image-preview">';
                imagePreview.style.display = 'none';
            } else {
                currentImageDiv.innerHTML = '';
            }
            
            // Réinitialiser le champ file
            document.getElementById('image').value = '';
            document.getElementById('category-form').scrollIntoView({ behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('form-title').textContent = 'Ajouter une catégorie';
            document.getElementById('form-action').value = 'add';
            document.getElementById('form-category-id').value = '0';
            document.getElementById('name').value = '';
            document.getElementById('description').value = '';
            document.getElementById('type_id').value = '';
            document.getElementById('image').value = '';
            document.getElementById('image-preview').style.display = 'none';
            document.getElementById('current-image').innerHTML = '';
        }

        // Aperçu de l'image avant upload
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('image-preview');
            const previewImg = document.getElementById('preview-img');
            const currentImage = document.getElementById('current-image');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                    currentImage.innerHTML = '';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    </script>
</body>
</html>


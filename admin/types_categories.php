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
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $typeCategoryId = isset($_POST['type_category_id']) ? (int)$_POST['type_category_id'] : 0;
        $imagePath = null;
        
        // Récupérer l'image existante si modification
        if ($action === 'edit' && $typeCategoryId > 0) {
            try {
                $stmt = $db->prepare("SELECT image FROM types_categories WHERE id = :id");
                $stmt->execute([':id' => $typeCategoryId]);
                $existingTypeCategory = $stmt->fetch();
                $imagePath = $existingTypeCategory['image'] ?? null;
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de la récupération des données : " . $e->getMessage();
            }
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
                $uploadDir = '../assets/images/types_categories/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0777, true)) {
                        $errors[] = "Impossible de créer le dossier d'upload";
                    }
                }
                
                if (empty($errors)) {
                    // Supprimer l'ancienne image si elle existe
                    if ($imagePath && file_exists('../' . $imagePath)) {
                        @unlink('../' . $imagePath);
                    }
                    
                    // Générer un nom unique
                    $filename = uniqid() . '_' . time() . '.' . $ext;
                    $targetPath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $imagePath = 'assets/images/types_categories/' . $filename;
                    } else {
                        $errors[] = "Erreur lors de l'upload de l'image";
                    }
                }
            }
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Erreur d'upload autre que "pas de fichier"
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => "Le fichier dépasse la taille maximale autorisée par le serveur",
                UPLOAD_ERR_FORM_SIZE => "Le fichier dépasse la taille maximale autorisée par le formulaire",
                UPLOAD_ERR_PARTIAL => "Le fichier n'a été que partiellement téléchargé",
                UPLOAD_ERR_NO_TMP_DIR => "Dossier temporaire manquant",
                UPLOAD_ERR_CANT_WRITE => "Échec de l'écriture du fichier sur le disque",
                UPLOAD_ERR_EXTENSION => "Une extension PHP a arrêté le téléchargement"
            ];
            $errorCode = $_FILES['image']['error'];
            $errors[] = $uploadErrors[$errorCode] ?? "Erreur inconnue lors de l'upload";
        }
        
        // Validation
        if (empty($name)) {
            $errors[] = "Le nom est requis";
        } elseif (strlen($name) > 100) {
            $errors[] = "Le nom ne doit pas dépasser 100 caractères";
        }
        
        if ($categoryId <= 0) {
            $errors[] = "La catégorie est requise";
        } else {
            // Vérifier que la catégorie existe
            try {
                $stmt = $db->prepare("SELECT id FROM categories WHERE id = :id");
                $stmt->execute([':id' => $categoryId]);
                if (!$stmt->fetch()) {
                    $errors[] = "La catégorie sélectionnée n'existe pas";
                }
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de la vérification de la catégorie";
            }
        }
        
        // Si pas d'erreurs, procéder à l'insertion/mise à jour
        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $stmt = $db->prepare("INSERT INTO types_categories (category_id, name, description, image) VALUES (:category_id, :name, :description, :image)");
                    $stmt->execute([
                        ':category_id' => $categoryId,
                        ':name' => $name, 
                        ':description' => $description ?: null,
                        ':image' => $imagePath
                    ]);
                    $success = true;
                    $successMessage = "Type de catégorie ajouté avec succès !";
                } else {
                    // Vérifier que le type de catégorie existe
                    $stmt = $db->prepare("SELECT id FROM types_categories WHERE id = :id");
                    $stmt->execute([':id' => $typeCategoryId]);
                    if (!$stmt->fetch()) {
                        $errors[] = "Le type de catégorie à modifier n'existe pas";
                    } else {
                        if ($imagePath) {
                            $stmt = $db->prepare("UPDATE types_categories SET category_id = :category_id, name = :name, description = :description, image = :image WHERE id = :id");
                            $stmt->execute([
                                ':id' => $typeCategoryId,
                                ':category_id' => $categoryId,
                                ':name' => $name, 
                                ':description' => $description ?: null,
                                ':image' => $imagePath
                            ]);
                        } else {
                            $stmt = $db->prepare("UPDATE types_categories SET category_id = :category_id, name = :name, description = :description WHERE id = :id");
                            $stmt->execute([
                                ':id' => $typeCategoryId,
                                ':category_id' => $categoryId,
                                ':name' => $name, 
                                ':description' => $description ?: null
                            ]);
                        }
                        $success = true;
                        $successMessage = "Type de catégorie modifié avec succès !";
                    }
                }
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $typeCategoryId = isset($_POST['type_category_id']) ? (int)$_POST['type_category_id'] : 0;
        if ($typeCategoryId > 0) {
            try {
                // Récupérer l'image avant suppression
                $stmt = $db->prepare("SELECT image FROM types_categories WHERE id = :id");
                $stmt->execute([':id' => $typeCategoryId]);
                $typeCategory = $stmt->fetch();
                
                if ($typeCategory) {
                    // Supprimer l'image si elle existe
                    if ($typeCategory['image'] && file_exists('../' . $typeCategory['image'])) {
                        @unlink('../' . $typeCategory['image']);
                    }
                    
                    // Supprimer le type de catégorie
                    $stmt = $db->prepare("DELETE FROM types_categories WHERE id = :id");
                    $stmt->execute([':id' => $typeCategoryId]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = true;
                        $successMessage = "Type de catégorie supprimé avec succès !";
                    } else {
                        $errors[] = "Aucun type de catégorie trouvé avec cet ID";
                    }
                } else {
                    $errors[] = "Type de catégorie introuvable";
                }
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de la suppression : " . $e->getMessage();
            }
        } else {
            $errors[] = "ID invalide pour la suppression";
        }
    }
}

// Récupérer les catégories pour le select
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Récupérer les types de catégories avec le nom de la catégorie parente
$stmt = $db->query("SELECT tc.*, c.name as category_name 
                    FROM types_categories tc 
                    LEFT JOIN categories c ON tc.category_id = c.id 
                    ORDER BY c.name, tc.name");
$typesCategories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Types de Catégories - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <h1>Gestion des Types de Catégories (Sous-catégories)</h1>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>✓ Succès !</strong> <?php echo clean($successMessage); ?>
                </div>
                <script>
                    // Recharger la page après 1.5 secondes pour voir les changements
                    setTimeout(function() {
                        window.location.href = window.location.pathname;
                    }, 1500);
                </script>
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

            <!-- Formulaire d'ajout/modification -->
            <div class="admin-section">
                <h2 id="form-title">Ajouter un type de catégorie</h2>
                <form method="POST" enctype="multipart/form-data" class="admin-form" id="type-category-form">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="type_category_id" id="form-type-category-id" value="0">
                    
                    <div class="form-group">
                        <label for="category_id">Catégorie *</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Sélectionner une catégorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo clean($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="name">Nom *</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="image">Image du type de catégorie</label>
                        <input type="file" id="image" name="image" accept="image/jpeg,image/jpg,image/png,image/webp">
                        <small>Formats acceptés : JPG, PNG, WEBP (max 5MB)</small>
                        <div id="image-preview" style="margin-top: 10px; display: none;">
                            <img id="preview-img" src="" alt="Aperçu" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                        <div id="current-image" style="margin-top: 10px;"></div>
                    </div>

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">Annuler</button>
                </form>
            </div>

            <!-- Liste des types de catégories -->
            <div class="admin-section">
                <h2>Liste des types de catégories</h2>
                <?php if (count($typesCategories) > 0): ?>
                    <div class="table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Image</th>
                                    <th style="width: 150px;">Catégorie</th>
                                    <th style="width: 200px;">Nom</th>
                                    <th>Description</th>
                                    <th style="width: 180px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($typesCategories as $typeCategory): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($typeCategory['image'])): ?>
                                                <img src="../<?php echo clean($typeCategory['image']); ?>" 
                                                     alt="<?php echo clean($typeCategory['name']); ?>" 
                                                     class="table-image"
                                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'70\' height=\'70\'%3E%3Crect width=\'70\' height=\'70\' fill=\'%23f0f0f0\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\' font-size=\'12\'%3ENo Image%3C/text%3E%3C/svg%3E';">
                                            <?php else: ?>
                                                <div class="table-image-placeholder">Aucune image</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong style="color: var(--primary-color);">
                                                <?php echo clean($typeCategory['category_name'] ?? 'N/A'); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <strong><?php echo clean($typeCategory['name']); ?></strong>
                                        </td>
                                        <td>
                                            <div style="max-width: 300px;">
                                                <?php 
                                                $description = $typeCategory['description'] ?? '';
                                                if (!empty($description)) {
                                                    echo clean(strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description);
                                                } else {
                                                    echo '<span style="color: #999; font-style: italic;">Aucune description</span>';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="admin-actions">
                                                <button onclick="editTypeCategory(
                                                    <?php echo $typeCategory['id']; ?>, 
                                                    <?php echo $typeCategory['category_id']; ?>, 
                                                    <?php echo json_encode($typeCategory['name'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 
                                                    <?php echo json_encode($typeCategory['description'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 
                                                    <?php echo json_encode($typeCategory['image'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT); ?>
                                                )" class="btn btn-sm btn-primary">Modifier</button>
                                                <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce type de catégorie ?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="type_category_id" value="<?php echo $typeCategory['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; background: var(--light-color); border-radius: 8px; color: var(--text-light);">
                        <p style="font-size: 1.1rem; margin: 0;">Aucun type de catégorie trouvé.</p>
                        <p style="margin-top: 0.5rem;">Commencez par ajouter un type de catégorie en utilisant le formulaire ci-dessus.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        function editTypeCategory(id, categoryId, name, description, image) {
            document.getElementById('form-title').textContent = 'Modifier le type de catégorie';
            document.getElementById('form-action').value = 'edit';
            document.getElementById('form-type-category-id').value = id;
            document.getElementById('category_id').value = categoryId;
            document.getElementById('name').value = name || '';
            document.getElementById('description').value = description || '';
            
            // Afficher l'image actuelle si elle existe
            const currentImageDiv = document.getElementById('current-image');
            const imagePreview = document.getElementById('image-preview');
            if (image && image.trim() !== '' && image !== 'null') {
                currentImageDiv.innerHTML = '<p><strong>Image actuelle :</strong></p><img src="../' + image + '" alt="Image actuelle" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid #ddd; margin-top: 5px;" onerror="this.style.display=\'none\'; this.parentElement.innerHTML=\'<p style=\'color:#999;\'>Image introuvable</p>\';">';
                imagePreview.style.display = 'none';
            } else {
                currentImageDiv.innerHTML = '';
            }
            
            // Réinitialiser le champ file
            document.getElementById('image').value = '';
            document.getElementById('type-category-form').scrollIntoView({ behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('form-title').textContent = 'Ajouter un type de catégorie';
            document.getElementById('form-action').value = 'add';
            document.getElementById('form-type-category-id').value = '0';
            document.getElementById('category_id').value = '';
            document.getElementById('name').value = '';
            document.getElementById('description').value = '';
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


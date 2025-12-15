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
        $typeId = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
        
        // Validation
        if (empty($name)) {
            $errors[] = "Le nom est requis";
        } elseif (strlen($name) > 100) {
            $errors[] = "Le nom ne doit pas dépasser 100 caractères";
        }
        
        // Si pas d'erreurs, procéder à l'insertion/mise à jour
        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    // Vérifier si le nom existe déjà
                    $stmt = $db->prepare("SELECT id FROM types WHERE name = :name");
                    $stmt->execute([':name' => $name]);
                    if ($stmt->fetch()) {
                        $errors[] = "Un type avec ce nom existe déjà";
                    } else {
                        $stmt = $db->prepare("INSERT INTO types (name, description) VALUES (:name, :description)");
                        $stmt->execute([
                            ':name' => $name, 
                            ':description' => $description ?: null
                        ]);
                        $success = true;
                        $successMessage = "Type ajouté avec succès !";
                    }
                } else {
                    // Vérifier que le type existe
                    $stmt = $db->prepare("SELECT id FROM types WHERE id = :id");
                    $stmt->execute([':id' => $typeId]);
                    if (!$stmt->fetch()) {
                        $errors[] = "Le type à modifier n'existe pas";
                    } else {
                        // Vérifier si le nom existe déjà pour un autre type
                        $stmt = $db->prepare("SELECT id FROM types WHERE name = :name AND id != :id");
                        $stmt->execute([':name' => $name, ':id' => $typeId]);
                        if ($stmt->fetch()) {
                            $errors[] = "Un type avec ce nom existe déjà";
                        } else {
                            $stmt = $db->prepare("UPDATE types SET name = :name, description = :description WHERE id = :id");
                            $stmt->execute([
                                ':id' => $typeId,
                                ':name' => $name, 
                                ':description' => $description ?: null
                            ]);
                            $success = true;
                            $successMessage = "Type modifié avec succès !";
                        }
                    }
                }
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $typeId = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
        if ($typeId > 0) {
            try {
                // Vérifier si le type est utilisé dans categories ou products
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM categories WHERE type_id = :id");
                $stmt->execute([':id' => $typeId]);
                $categoriesCount = $stmt->fetch()['count'];
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE type_id = :id");
                $stmt->execute([':id' => $typeId]);
                $productsCount = $stmt->fetch()['count'];
                
                if ($categoriesCount > 0 || $productsCount > 0) {
                    $errors[] = "Ce type ne peut pas être supprimé car il est utilisé par " . 
                               ($categoriesCount > 0 ? "$categoriesCount catégorie(s)" : '') . 
                               ($categoriesCount > 0 && $productsCount > 0 ? ' et ' : '') . 
                               ($productsCount > 0 ? "$productsCount produit(s)" : '');
                } else {
                    // Supprimer le type
                    $stmt = $db->prepare("DELETE FROM types WHERE id = :id");
                    $stmt->execute([':id' => $typeId]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = true;
                        $successMessage = "Type supprimé avec succès !";
                    } else {
                        $errors[] = "Aucun type trouvé avec cet ID";
                    }
                }
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de la suppression : " . $e->getMessage();
            }
        } else {
            $errors[] = "ID invalide pour la suppression";
        }
    }
}

// Récupérer tous les types
$stmt = $db->query("SELECT t.*, 
                    (SELECT COUNT(*) FROM categories WHERE type_id = t.id) as categories_count,
                    (SELECT COUNT(*) FROM products WHERE type_id = t.id) as products_count
                    FROM types t 
                    ORDER BY t.name");
$types = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Types - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <h1>Gestion des Types</h1>

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
                <h2 id="form-title">Ajouter un type</h2>
                <form method="POST" class="admin-form" id="type-form">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="type_id" id="form-type-id" value="0">
                    
                    <div class="form-group">
                        <label for="name">Nom *</label>
                        <input type="text" id="name" name="name" required maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">Annuler</button>
                </form>
            </div>

            <!-- Liste des types -->
            <div class="admin-section">
                <h2>Liste des types</h2>
                <?php if (count($types) > 0): ?>
                    <div class="table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width: 200px;">Nom</th>
                                    <th>Description</th>
                                    <th style="width: 120px;">Catégories</th>
                                    <th style="width: 120px;">Produits</th>
                                    <th style="width: 180px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($types as $type): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo clean($type['name']); ?></strong>
                                        </td>
                                        <td>
                                            <div style="max-width: 400px;">
                                                <?php 
                                                $description = $type['description'] ?? '';
                                                if (!empty($description)) {
                                                    echo clean(strlen($description) > 150 ? substr($description, 0, 150) . '...' : $description);
                                                } else {
                                                    echo '<span style="color: #999; font-style: italic;">Aucune description</span>';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="padding: 0.25rem 0.75rem; background: var(--primary-color); color: white; border-radius: 12px; font-size: 0.85rem; font-weight: 600;">
                                                <?php echo (int)$type['categories_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="padding: 0.25rem 0.75rem; background: var(--accent-color); color: white; border-radius: 12px; font-size: 0.85rem; font-weight: 600;">
                                                <?php echo (int)$type['products_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="admin-actions">
                                                <button onclick="editType(
                                                    <?php echo $type['id']; ?>, 
                                                    <?php echo json_encode($type['name'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 
                                                    <?php echo json_encode($type['description'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT); ?>
                                                )" class="btn btn-sm btn-primary">Modifier</button>
                                                <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce type ? Cette action est irréversible.');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="type_id" value="<?php echo $type['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" <?php echo ($type['categories_count'] > 0 || $type['products_count'] > 0) ? 'disabled title="Ce type est utilisé et ne peut pas être supprimé"' : ''; ?>>Supprimer</button>
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
                        <p style="font-size: 1.1rem; margin: 0;">Aucun type trouvé.</p>
                        <p style="margin-top: 0.5rem;">Commencez par ajouter un type en utilisant le formulaire ci-dessus.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        function editType(id, name, description) {
            document.getElementById('form-title').textContent = 'Modifier le type';
            document.getElementById('form-action').value = 'edit';
            document.getElementById('form-type-id').value = id;
            document.getElementById('name').value = name || '';
            document.getElementById('description').value = description || '';
            document.getElementById('type-form').scrollIntoView({ behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('form-title').textContent = 'Ajouter un type';
            document.getElementById('form-action').value = 'add';
            document.getElementById('form-type-id').value = '0';
            document.getElementById('name').value = '';
            document.getElementById('description').value = '';
        }
    </script>
</body>
</html>


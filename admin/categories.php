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
        
        if (empty($name)) {
            $errors[] = "Le nom est requis";
        } else {
            $slug = generateSlug($name);
            
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :description)");
                $stmt->execute([':name' => $name, ':slug' => $slug, ':description' => $description]);
                $success = true;
            } else {
                $stmt = $db->prepare("UPDATE categories SET name = :name, slug = :slug, description = :description WHERE id = :id");
                $stmt->execute([':id' => $categoryId, ':name' => $name, ':slug' => $slug, ':description' => $description]);
                $success = true;
            }
        }
    } elseif ($action === 'delete') {
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        if ($categoryId > 0) {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute([':id' => $categoryId]);
            $success = true;
        }
    }
}

// Récupérer les catégories
$stmt = $db->query("SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id GROUP BY c.id ORDER BY c.name");
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
                <div class="alert alert-success">Opération réussie !</div>
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
                <h2 id="form-title">Ajouter une catégorie</h2>
                <form method="POST" class="admin-form" id="category-form">
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

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">Annuler</button>
                </form>
            </div>

            <!-- Liste des catégories -->
            <div class="admin-section">
                <h2>Liste des catégories</h2>
                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Slug</th>
                                <th>Produits</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($categories) > 0): ?>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?php echo clean($category['name']); ?></td>
                                        <td><?php echo clean($category['slug']); ?></td>
                                        <td><?php echo $category['product_count']; ?></td>
                                        <td>
                                            <button onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>', '<?php echo addslashes($category['description']); ?>')" 
                                                    class="btn btn-sm btn-primary">Modifier</button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr ? Cette action supprimera tous les produits de cette catégorie.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">Aucune catégorie</td>
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
        function editCategory(id, name, description) {
            document.getElementById('form-title').textContent = 'Modifier la catégorie';
            document.getElementById('form-action').value = 'edit';
            document.getElementById('form-category-id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('description').value = description;
            document.getElementById('category-form').scrollIntoView({ behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('form-title').textContent = 'Ajouter une catégorie';
            document.getElementById('form-action').value = 'add';
            document.getElementById('form-category-id').value = '0';
            document.getElementById('name').value = '';
            document.getElementById('description').value = '';
        }
    </script>
</body>
</html>


<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$db = getDB();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Récupérer les produits
$stmt = $db->query("SELECT COUNT(*) as total FROM products");
$total = $stmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT p.*, c.name as category_name,
                      (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      ORDER BY p.created_at DESC 
                      LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <div class="admin-page-header">
                <h1>Gestion des Produits</h1>
                <a href="product_form.php" class="btn btn-primary">+ Ajouter un produit</a>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Produit enregistré avec succès !</div>
            <?php endif; ?>

            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if ($product['image']): ?>
                                            <img src="../<?php echo clean($product['image']); ?>" alt="" class="table-image">
                                        <?php else: ?>
                                            <div class="table-image-placeholder">-</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo clean($product['name']); ?></td>
                                    <td><?php echo clean($product['category_name']); ?></td>
                                    <td>
                                        <?php if ($product['sale_price']): ?>
                                            <span class="old-price"><?php echo formatPrice($product['price']); ?></span>
                                            <span class="current-price"><?php echo formatPrice($product['sale_price']); ?></span>
                                        <?php else: ?>
                                            <?php echo formatPrice($product['price']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $product['stock']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $product['status']; ?>">
                                            <?php echo $product['status'] === 'active' ? 'Actif' : 'Inactif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="product_form.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">Modifier</a>
                                        <a href="product_delete.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">Supprimer</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">Aucun produit</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination-wrapper">
                    <?php echo getPagination($page, $totalPages, 'products.php'); ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>


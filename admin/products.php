<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$db = getDB();

// Filtres
$search       = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterType   = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
$filterCat    = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Construire la clause WHERE dynamiquement
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE :search OR p.slug LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($filterType > 0) {
    $where[] = 'p.type_id = :type_id';
    $params[':type_id'] = $filterType;
}
if ($filterCat > 0) {
    $where[] = 'p.category_id = :category_id';
    $params[':category_id'] = $filterCat;
}
if ($filterStatus !== '' && in_array($filterStatus, ['active', 'inactive'], true)) {
    $where[] = 'p.status = :status';
    $params[':status'] = $filterStatus;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Compter le total avec filtres
$countSql = "SELECT COUNT(*) as total FROM products p $whereSql";
$stmt = $db->prepare($countSql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total = (int)$stmt->fetch()['total'];
$totalPages = max(1, ceil($total / $perPage));

// Récupérer les produits filtrés
$sql = "SELECT p.*, c.name as category_name, t.name AS type_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN types t ON p.type_id = t.id
        $whereSql
        ORDER BY p.created_at DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    // Les paramètres de filtre sont déjà liés plus haut
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Récupérer toutes les catégories et types pour les filtres
if ($filterType > 0) {
    $stmtCats = $db->prepare("SELECT id, name FROM categories WHERE type_id = :type_id ORDER BY name");
    $stmtCats->execute([':type_id' => $filterType]);
    $allCategories = $stmtCats->fetchAll();
} else {
    $allCategories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
}
$allTypes = $db->query("SELECT id, name FROM types ORDER BY name")->fetchAll();

// Base pour la pagination avec filtres
$paginationQuery = [
    'q'           => $search,
    'type_id'     => $filterType ?: null,
    'category_id' => $filterCat ?: null,
    'status'      => $filterStatus ?: null,
];
// Nettoyer les valeurs null pour éviter des paramètres vides
$paginationQuery = array_filter($paginationQuery, static function ($v) {
    return $v !== null && $v !== '';
});
$paginationBase = 'products.php';
if (!empty($paginationQuery)) {
    $paginationBase .= '?' . http_build_query($paginationQuery);
}
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

            <section class="admin-filters">
                <form method="GET" class="admin-filters-form">
                    <div class="filters-grid">
                        <div class="filter-group filter-group-search">
                            <label for="q">Recherche</label>
                            <input type="text"
                                   id="q"
                                   name="q"
                                   placeholder="Nom ou slug..."
                                   value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="filter-group">
                            <label for="type_id">Type</label>
                            <select id="type_id" name="type_id">
                                <option value="">Tous les types</option>
                                <?php foreach ($allTypes as $type): ?>
                                    <option value="<?php echo (int)$type['id']; ?>"
                                        <?php echo $filterType == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo clean($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="category_id">Catégorie</label>
                            <select id="category_id" name="category_id">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?php echo (int)$cat['id']; ?>"
                                        <?php echo $filterCat == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo clean($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="status">Statut</label>
                            <select id="status" name="status">
                                <option value="">Tous</option>
                                <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>
                    </div>

                    <div class="filters-actions">
                        <a href="products.php" class="btn btn-secondary">Réinitialiser</a>
                        <?php if ($search || $filterType || $filterCat || $filterStatus): ?>
                            <span class="filters-active-badge">
                                Filtres actifs
                            </span>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Produit enregistré avec succès !</div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✅</span>
                    <span><?php echo clean($_SESSION['success_message']); ?></span>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">⚠️</span>
                    <span><?php echo clean($_SESSION['error_message']); ?></span>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Type</th>
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
                                    <td><?php echo isset($product['type_name']) ? clean($product['type_name']) : '-'; ?></td>
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
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?\n\n⚠️ Attention : Si ce produit est présent dans des commandes, la suppression sera bloquée.');">Supprimer</a>
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
                    <?php echo getPagination($page, $totalPages, $paginationBase); ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        // Soumission automatique du formulaire de filtres lors des changements
        (function () {
            const form = document.querySelector('.admin-filters-form');
            if (!form) return;

            const searchInput = form.querySelector('#q');
            const selects = form.querySelectorAll('select');

            selects.forEach(sel => {
                sel.addEventListener('change', () => {
                    form.submit();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        form.submit();
                    }
                });
            }
        })();
    </script>
</body>
</html>


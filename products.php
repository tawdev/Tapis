<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

// Paramètres de filtrage
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$sort = isset($_GET['sort']) ? clean($_GET['sort']) : 'newest';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// Construction de la requête
$where = ["p.status = 'active'"];
$params = [];

if ($categoryId > 0) {
    $where[] = "p.category_id = :category_id";
    $params[':category_id'] = $categoryId;
}

if ($minPrice > 0) {
    $where[] = "COALESCE(p.sale_price, p.price) >= :min_price";
    $params[':min_price'] = $minPrice;
}

if ($maxPrice > 0) {
    $where[] = "COALESCE(p.sale_price, p.price) <= :max_price";
    $params[':max_price'] = $maxPrice;
}

if ($search) {
    $where[] = "(p.name LIKE :search OR p.description LIKE :search)";
    $params[':search'] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Tri
$orderBy = "p.created_at DESC";
switch ($sort) {
    case 'price_low':
        $orderBy = "COALESCE(p.sale_price, p.price) ASC";
        break;
    case 'price_high':
        $orderBy = "COALESCE(p.sale_price, p.price) DESC";
        break;
    case 'bestseller':
        $orderBy = "p.best_seller DESC, p.created_at DESC";
        break;
    case 'newest':
    default:
        $orderBy = "p.created_at DESC";
        break;
}

// Compter le total
$countSql = "SELECT COUNT(*) as total FROM products p WHERE $whereClause";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetch()['total'];

// Récupérer tous les produits
$sql = "SELECT p.*, c.name as category_name,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE $whereClause 
        ORDER BY $orderBy";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$products = $stmt->fetchAll();

// Récupérer les catégories
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="products-page">
        <div class="container">
            <!-- Header avec titre et compteur -->
            <div class="products-header">
                <div class="products-header-top">
                    <h1>Nos Produits</h1>
                    <div class="products-count-badge">
                        <span><?php echo $total; ?></span> produit<?php echo $total > 1 ? 's' : ''; ?>
                    </div>
                </div>
                <?php if ($search || $categoryId > 0 || $minPrice > 0 || $maxPrice > 0): ?>
                    <div class="active-filters">
                        <strong>Filtres actifs :</strong>
                        <?php if ($search): ?>
                            <span class="filter-tag">Recherche: <?php echo clean($search); ?></span>
                        <?php endif; ?>
                        <?php if ($categoryId > 0): ?>
                            <?php 
                            $catName = '';
                            foreach ($categories as $cat) {
                                if ($cat['id'] == $categoryId) {
                                    $catName = $cat['name'];
                                    break;
                                }
                            }
                            ?>
                            <span class="filter-tag">Catégorie: <?php echo clean($catName); ?></span>
                        <?php endif; ?>
                        <?php if ($minPrice > 0 || $maxPrice > 0): ?>
                            <span class="filter-tag">Prix: <?php echo $minPrice > 0 ? formatPrice($minPrice) : '0'; ?> - <?php echo $maxPrice > 0 ? formatPrice($maxPrice) : '∞'; ?></span>
                        <?php endif; ?>
                        <a href="products.php" class="clear-filters">✕ Effacer tous les filtres</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Filtres horizontaux -->
            <div class="filters-section">
                <form method="GET" action="products.php" class="filters-form-horizontal">
                    <?php if (isset($_GET['category'])): ?>
                        <input type="hidden" name="category" value="<?php echo $categoryId; ?>">
                    <?php endif; ?>
                    
                    <div class="filter-row">
                        <div class="filter-item">
                            <label>🔍 Recherche</label>
                            <input type="text" name="search" placeholder="Rechercher..." value="<?php echo $search; ?>">
                        </div>

                        <div class="filter-item">
                            <label>📁 Catégorie</label>
                            <select name="category">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo clean($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-item">
                            <label>💰 Prix</label>
                            <div class="price-range">
                                <input type="number" name="min_price" placeholder="Min" value="<?php echo $minPrice ?: ''; ?>" min="0">
                                <span>-</span>
                                <input type="number" name="max_price" placeholder="Max" value="<?php echo $maxPrice ?: ''; ?>" min="0">
                            </div>
                        </div>

                        

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Appliquer</button>
                            <a href="products.php" class="btn btn-secondary">Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Liste des produits -->
            <div class="products-content">

                    <?php if (count($products) > 0): ?>
                        <div class="products-grid">
                            <?php foreach ($products as $product): ?>
                                <div class="product-card">
                                    <a href="product.php?id=<?php echo $product['id']; ?>">
                                        <div class="product-image">
                                            <?php if ($product['image']): ?>
                                                <img src="<?php echo clean($product['image']); ?>" alt="<?php echo clean($product['name']); ?>">
                                            <?php else: ?>
                                                <div class="placeholder-image">Image</div>
                                            <?php endif; ?>
                                            <?php if ($product['sale_price'] || $product['best_seller']): ?>
                                                <div class="badges-container">
                                                    <?php if ($product['sale_price']): ?>
                                                        <span class="badge sale">Promotion</span>
                                                    <?php endif; ?>
                                                    <?php if ($product['best_seller']): ?>
                                                        <span class="badge best-seller">Bestseller</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-info">
                                            <h2><?php echo clean($product['name']); ?></h2>
                                            <p class="product-category"><?php echo clean($product['category_name']); ?></p>
                                            <div class="product-price">
                                                <?php if ($product['sale_price']): ?>
                                                    <span class="old-price"><?php echo formatPrice($product['price']); ?></span>
                                                    <span class="current-price"><?php echo formatPrice($product['sale_price']); ?></span>
                                                <?php else: ?>
                                                    <span class="current-price"><?php echo formatPrice($product['price']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                    <button class="btn-add-cart" data-product-id="<?php echo $product['id']; ?>">Ajouter au panier</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-products">
                            <p>Aucun produit trouvé.</p>
                        </div>
                    <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>


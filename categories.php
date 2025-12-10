<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

// Récupérer toutes les catégories avec le nombre de produits
$stmt = $db->query("SELECT c.*, COUNT(p.id) as product_count 
                    FROM categories c 
                    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
                    GROUP BY c.id 
                    ORDER BY c.name");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catégories - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="categories-page">
        <div class="container">
            <div class="page-header">
                <h1>Nos Catégories</h1>
                <p>Découvrez notre sélection de tapis par catégorie</p>
            </div>

            <?php if (count($categories) > 0): ?>
                <div class="categories-grid-detailed">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card-detailed">
                            <a href="products.php?category=<?php echo $category['id']; ?>">
                                <div class="category-image-detailed">
                                    <?php if ($category['image']): ?>
                                        <img src="<?php echo clean($category['image']); ?>" alt="<?php echo clean($category['name']); ?>">
                                    <?php else: ?>
                                        <div class="category-placeholder">
                                            <span class="category-icon">🏺</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="category-overlay">
                                        <span class="category-count"><?php echo $category['product_count']; ?> produit<?php echo $category['product_count'] > 1 ? 's' : ''; ?></span>
                                    </div>
                                </div>
                                <div class="category-content">
                                    <h2><?php echo clean($category['name']); ?></h2>
                                    <?php if ($category['description']): ?>
                                        <p><?php echo clean($category['description']); ?></p>
                                    <?php endif; ?>
                                    <span class="category-link">Voir les produits →</span>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-categories">
                    <p>Aucune catégorie disponible pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>


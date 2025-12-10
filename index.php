<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

// Récupérer les catégories
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Récupérer les produits en vedette
$stmt = $db->query("SELECT p.*, c.name as category_name, 
                    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.featured = 1 AND p.status = 'active' 
                    ORDER BY p.created_at DESC 
                    LIMIT 8");
$featuredProducts = $stmt->fetchAll();

// Récupérer les meilleures ventes
$stmt = $db->query("SELECT p.*, c.name as category_name,
                    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
                    COALESCE(SUM(oi.quantity), 0) as total_sold
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN order_items oi ON p.id = oi.product_id
                    WHERE p.status = 'active'
                    GROUP BY p.id
                    ORDER BY total_sold DESC, p.best_seller DESC
                    LIMIT 6");
$bestSellers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content">
                <h1>Découvrez Notre Collection de Tapis de Luxe</h1>
                <p>Des tapis authentiques et élégants pour transformer votre intérieur</p>
                <a href="products.php" class="btn btn-primary">Voir la Collection</a>
            </div>
        </section>

        <!-- Catégories -->
        <section class="categories-section">
            <div class="container">
                <h2 class="section-title">Nos Catégories</h2>
                <div class="categories-grid">
                    <?php foreach ($categories as $category): ?>
                        <a href="products.php?category=<?php echo $category['id']; ?>" class="category-card">
                            <div class="category-image">
                                <?php if ($category['image']): ?>
                                    <img src="<?php echo clean($category['image']); ?>" alt="<?php echo clean($category['name']); ?>">
                                <?php else: ?>
                                    <div class="placeholder-image"><?php echo substr($category['name'], 0, 1); ?></div>
                                <?php endif; ?>
                            </div>
                            <h3><?php echo clean($category['name']); ?></h3>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Produits en vedette -->
        <section class="products-section">
            <div class="container">
                <h2 class="section-title">Produits en Vedette</h2>
                <div class="products-grid">
                    <?php foreach ($featuredProducts as $product): ?>
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
            </div>
        </section>

        <!-- Meilleures ventes -->
        <section class="products-section bg-light">
            <div class="container">
                <h2 class="section-title">Meilleures Ventes</h2>
                <div class="products-grid">
                    <?php foreach ($bestSellers as $product): ?>
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
            </div>
        </section>

        <!-- Informations -->
        <section class="info-section">
            <div class="container">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon">🚚</div>
                        <h3>Livraison Gratuite</h3>
                        <p>Livraison gratuite à partir de 500 MAD</p>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">↩️</div>
                        <h3>Retour Gratuit</h3>
                        <p>30 jours pour changer d'avis</p>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">🔒</div>
                        <h3>Paiement Sécurisé</h3>
                        <p>Transactions 100% sécurisées</p>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">⭐</div>
                        <h3>Qualité Garantie</h3>
                        <p>Produits authentiques et de qualité</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>


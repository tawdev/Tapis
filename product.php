<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId == 0) {
    redirect('products.php');
}

// Récupérer le produit
$stmt = $db->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      WHERE p.id = :id AND p.status = 'active'");
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    redirect('products.php');
}

// Récupérer les images
$stmt = $db->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY is_primary DESC, display_order ASC");
$stmt->execute([':id' => $productId]);
$images = $stmt->fetchAll();

// Récupérer les produits similaires
$stmt = $db->prepare("SELECT p.*, 
                      (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
                      FROM products p 
                      WHERE p.category_id = :category_id AND p.id != :id AND p.status = 'active'
                      ORDER BY RAND()
                      LIMIT 4");
$stmt->execute([':category_id' => $product['category_id'], ':id' => $productId]);
$relatedProducts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($product['name']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="product-page">
        <div class="container">
            <nav class="breadcrumb">
                <a href="index.php">Accueil</a> / 
                <a href="products.php">Produits</a> / 
                <a href="products.php?category=<?php echo $product['category_id']; ?>"><?php echo clean($product['category_name']); ?></a> / 
                <span><?php echo clean($product['name']); ?></span>
            </nav>

            <div class="product-detail">
                <!-- Images du produit -->
                <div class="product-images">
                    <div class="product-main-image">
                        <?php if (count($images) > 0): ?>
                            <img id="main-image" src="<?php echo clean($images[0]['image_path']); ?>" alt="<?php echo clean($product['name']); ?>">
                        <?php else: ?>
                            <div class="placeholder-image large">Image</div>
                        <?php endif; ?>
                    </div>
                    <?php if (count($images) > 1): ?>
                        <div class="product-thumbnails">
                            <?php foreach ($images as $index => $image): ?>
                                <img src="<?php echo clean($image['image_path']); ?>" 
                                     alt="Image <?php echo $index + 1; ?>" 
                                     class="thumbnail <?php echo $index == 0 ? 'active' : ''; ?>"
                                     onclick="changeMainImage('<?php echo clean($image['image_path']); ?>', this)">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Informations du produit -->
                <div class="product-info-detail">
                    <h1><?php echo clean($product['name']); ?></h1>
                    <p class="product-category-link">
                        <a href="products.php?category=<?php echo $product['category_id']; ?>">
                            <?php echo clean($product['category_name']); ?>
                        </a>
                    </p>

                    <div class="product-price-detail">
                        <?php if ($product['sale_price']): ?>
                            <span class="old-price"><?php echo formatPrice($product['price']); ?></span>
                            <span class="current-price"><?php echo formatPrice($product['sale_price']); ?></span>
                            <?php 
                            $discount = round((($product['price'] - $product['sale_price']) / $product['price']) * 100);
                            ?>
                            <span class="discount-badge">-<?php echo $discount; ?>%</span>
                        <?php else: ?>
                            <span class="current-price"><?php echo formatPrice($product['price']); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($product['short_description']): ?>
                        <p class="product-short-desc"><?php echo clean($product['short_description']); ?></p>
                    <?php endif; ?>

                    <div class="product-specs">
                        <?php if ($product['material']): ?>
                            <div class="spec-item">
                                <strong>Matériau:</strong> <?php echo clean($product['material']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($product['size']): ?>
                            <div class="spec-item">
                                <strong>Taille:</strong> <?php echo clean($product['size']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($product['color']): ?>
                            <div class="spec-item">
                                <strong>Couleur:</strong> <?php echo clean($product['color']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="spec-item">
                            <strong>Stock:</strong> 
                            <span class="<?php echo $product['stock'] > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                <?php echo $product['stock'] > 0 ? 'En stock (' . $product['stock'] . ')' : 'Rupture de stock'; ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($product['description']): ?>
                        <div class="product-description">
                            <h3>Description</h3>
                            <p><?php echo nl2br(clean($product['description'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="product-actions">
                        <div class="quantity-selector">
                            <label>Quantité:</label>
                            <div class="quantity-controls">
                                <button type="button" onclick="decreaseQuantity()">-</button>
                                <input type="number" id="product-quantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
                                <button type="button" onclick="increaseQuantity()">+</button>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-large" 
                                id="add-to-cart-btn" 
                                data-product-id="<?php echo $product['id']; ?>"
                                <?php echo $product['stock'] == 0 ? 'disabled' : ''; ?>>
                            <?php echo $product['stock'] > 0 ? 'Ajouter au panier' : 'Rupture de stock'; ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Produits similaires -->
            <?php if (count($relatedProducts) > 0): ?>
                <section class="related-products">
                    <h2>Produits Similaires</h2>
                    <div class="products-grid">
                        <?php foreach ($relatedProducts as $related): ?>
                            <div class="product-card">
                                <a href="product.php?id=<?php echo $related['id']; ?>">
                                    <div class="product-image">
                                        <?php if ($related['image']): ?>
                                            <img src="<?php echo clean($related['image']); ?>" alt="<?php echo clean($related['name']); ?>">
                                        <?php else: ?>
                                            <div class="placeholder-image">Image</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-info">
                                        <h3><?php echo clean($related['name']); ?></h3>
                                        <div class="product-price">
                                            <?php if ($related['sale_price']): ?>
                                                <span class="old-price"><?php echo formatPrice($related['price']); ?></span>
                                                <span class="current-price"><?php echo formatPrice($related['sale_price']); ?></span>
                                            <?php else: ?>
                                                <span class="current-price"><?php echo formatPrice($related['price']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                                <button class="btn-add-cart" data-product-id="<?php echo $related['id']; ?>">Ajouter au panier</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        function changeMainImage(src, element) {
            document.getElementById('main-image').src = src;
            document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
            element.classList.add('active');
        }

        function increaseQuantity() {
            const input = document.getElementById('product-quantity');
            const max = parseInt(input.getAttribute('max'));
            if (parseInt(input.value) < max) {
                input.value = parseInt(input.value) + 1;
            }
        }

        function decreaseQuantity() {
            const input = document.getElementById('product-quantity');
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
            }
        }
    </script>
</body>
</html>


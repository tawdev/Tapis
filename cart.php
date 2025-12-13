<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

// Initialiser le panier
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Actions sur le panier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update':
                $productId = isset($_POST['product_id']) ? $_POST['product_id'] : '';
                $quantity = (int)$_POST['quantity'];
                
                // Chercher l'item dans le panier (peut être avec un ID modifié)
                $foundKey = null;
                foreach ($_SESSION['cart'] as $key => $item) {
                    $keyProductId = (int)explode('_', $key)[0];
                    if ($keyProductId == (int)$productId || $key == $productId) {
                        $foundKey = $key;
                        break;
                    }
                }
                
                if ($foundKey && isset($_SESSION['cart'][$foundKey])) {
                    if ($quantity > 0) {
                        if (is_array($_SESSION['cart'][$foundKey])) {
                            $_SESSION['cart'][$foundKey]['quantity'] = $quantity;
                        } else {
                            // Ancien format
                            $_SESSION['cart'][$foundKey] = $quantity;
                        }
                    } else {
                        unset($_SESSION['cart'][$foundKey]);
                    }
                }
                break;
            case 'remove':
                $productId = isset($_POST['product_id']) ? $_POST['product_id'] : '';
                
                // Chercher l'item dans le panier
                $foundKey = null;
                foreach ($_SESSION['cart'] as $key => $item) {
                    $keyProductId = (int)explode('_', $key)[0];
                    if ($keyProductId == (int)$productId || $key == $productId) {
                        $foundKey = $key;
                        break;
                    }
                }
                
                if ($foundKey && isset($_SESSION['cart'][$foundKey])) {
                    unset($_SESSION['cart'][$foundKey]);
                }
                break;
            case 'clear':
                $_SESSION['cart'] = [];
                break;
        }
        redirect('cart.php');
    }
}

// Récupérer les produits du panier
$cartItems = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    
    $stmt = $db->prepare("SELECT p.*, 
                          (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
                          c.name as category_name,
                          tc.name as type_category_name
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id
                          LEFT JOIN types_categories tc ON p.type_category_id = tc.id
                          WHERE p.id IN ($placeholders) AND p.status = 'active'");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll();
    
    foreach ($products as $product) {
        $productId = $product['id'];
        
        // Chercher l'item dans le panier (peut être avec un ID modifié pour les dimensions différentes)
        $cartItemData = null;
        foreach ($_SESSION['cart'] as $key => $item) {
            // Extraire l'ID du produit de la clé (peut être "id" ou "id_timestamp")
            $keyProductId = (int)explode('_', $key)[0];
            if ($keyProductId == $productId) {
                $cartItemData = $item;
                break;
            }
        }
        
        // Compatibilité avec l'ancien format
        if (!$cartItemData && isset($_SESSION['cart'][$productId])) {
            $cartItemData = $_SESSION['cart'][$productId];
        }
        
        if ($cartItemData) {
            // Nouveau format avec dimensions
            if (is_array($cartItemData)) {
                $quantity = $cartItemData['quantity'];
                $length = $cartItemData['length'] ?? 0;
                $width = $cartItemData['width'] ?? 0;
                $itemPrice = $cartItemData['item_price'] ?? ($product['sale_price'] ?: $product['price']);
                $subtotal = $itemPrice * $quantity;
            } else {
                // Ancien format (compatibilité)
                $quantity = $cartItemData;
                $length = 0;
                $width = 0;
                $itemPrice = $product['sale_price'] ?: $product['price'];
                $subtotal = $itemPrice * $quantity;
            }
            
            $total += $subtotal;
            
            $cartItems[] = [
                'product' => $product,
                'quantity' => $quantity,
                'length' => $length,
                'width' => $width,
                'item_price' => $itemPrice,
                'subtotal' => $subtotal
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panier - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="cart-page">
        <div class="container">
            <a href="products.php" class="btn-back">
                <span>←</span> Retour aux produits
            </a>
            
            <h1>Mon Panier</h1>

            <?php if (empty($cartItems)): ?>
                <div class="empty-cart">
                    <p>Votre panier est vide.</p>
                    <a href="products.php" class="btn btn-primary">Continuer les achats</a>
                </div>
            <?php else: ?>
                <div class="cart-layout">
                    <div class="cart-items">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Prix</th>
                                    <th>Quantité</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $item): 
                                    $product = $item['product'];
                                ?>
                                    <tr>
                                        <td class="cart-product">
                                            <a href="product.php?id=<?php echo $product['id']; ?>">
                                                <?php if ($product['image']): ?>
                                                    <img src="<?php echo clean($product['image']); ?>" alt="<?php echo clean($product['name']); ?>">
                                                <?php else: ?>
                                                    <div class="placeholder-image small">Image</div>
                                                <?php endif; ?>
                                                <div class="cart-product-info">
                                                    <h3><?php echo clean($product['name']); ?></h3>
                                                    <p style="color: var(--text-light); font-size: 0.9rem; margin: 0.25rem 0;">
                                                        <?php echo clean($product['category_name']); ?>
                                                        <?php if (!empty($product['type_category_name'])): ?>
                                                            <span style="margin-left: 0.5rem; padding: 0.25rem 0.75rem; background: var(--accent-color); color: var(--white); border-radius: 5px; font-size: 0.75rem; font-weight: 600;">
                                                                → <?php echo clean($product['type_category_name']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if ($item['length'] > 0 && $item['width'] > 0): ?>
                                                        <p style="color: var(--primary-color); font-weight: 600; margin: 0.25rem 0;">
                                                            Dimensions: <?php echo round($item['length']); ?> cm × <?php echo round($item['width']); ?> cm
                                                        </p>
                                                        <?php 
                                                        $surfaceM2 = ($item['length'] * $item['width']) / 10000;
                                                        ?>
                                                        <p style="color: var(--text-light); font-size: 0.9rem; margin: 0.25rem 0;">
                                                            Surface: <?php echo number_format($surfaceM2, 2, ',', ' '); ?> m²
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if ($product['size']): ?>
                                                        <p style="margin: 0.25rem 0;">Taille: <?php echo clean($product['size']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="cart-price">
                                            <?php if ($item['length'] > 0 && $item['width'] > 0): ?>
                                                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                                    <span style="font-size: 0.85rem; color: var(--text-light);">Prix unitaire:</span>
                                                    <?php if ($product['sale_price']): ?>
                                                        <span class="old-price" style="font-size: 0.9rem;"><?php echo formatPrice($product['price']); ?></span>
                                                        <span class="current-price"><?php echo formatPrice($product['sale_price']); ?> / m²</span>
                                                    <?php else: ?>
                                                        <span class="current-price"><?php echo formatPrice($product['price']); ?> / m²</span>
                                                    <?php endif; ?>
                                                    <span style="font-size: 0.85rem; color: var(--text-light); margin-top: 0.25rem;">Prix calculé:</span>
                                                    <strong style="color: var(--primary-color);"><?php echo formatPrice($item['item_price']); ?></strong>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($product['sale_price']): ?>
                                                    <span class="old-price"><?php echo formatPrice($product['price']); ?></span>
                                                    <span class="current-price"><?php echo formatPrice($product['sale_price']); ?></span>
                                                <?php else: ?>
                                                    <span class="current-price"><?php echo formatPrice($product['price']); ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cart-quantity">
                                            <form method="POST" class="quantity-form">
                                                <input type="hidden" name="action" value="update">
                                                <?php 
                                                // Trouver la clé exacte dans le panier
                                                $cartKey = $product['id'];
                                                foreach ($_SESSION['cart'] as $key => $cartItem) {
                                                    $keyProductId = (int)explode('_', $key)[0];
                                                    if ($keyProductId == $product['id']) {
                                                        $cartKey = $key;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <input type="hidden" name="product_id" value="<?php echo $cartKey; ?>">
                                                <div class="quantity-controls">
                                                    <button type="button" onclick="updateQuantity('<?php echo $cartKey; ?>', -1)">-</button>
                                                    <input type="number" 
                                                           name="quantity" 
                                                           value="<?php echo $item['quantity']; ?>" 
                                                           min="1" 
                                                           max="<?php echo $product['stock']; ?>"
                                                           onchange="this.form.submit()">
                                                    <button type="button" onclick="updateQuantity('<?php echo $cartKey; ?>', 1)">+</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td class="cart-subtotal">
                                            <?php echo formatPrice($item['subtotal']); ?>
                                        </td>
                                        <td class="cart-action">
                                            <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir retirer ce produit ?');">
                                                <input type="hidden" name="action" value="remove">
                                                <?php 
                                                // Trouver la clé exacte dans le panier
                                                $cartKey = $product['id'];
                                                foreach ($_SESSION['cart'] as $key => $cartItem) {
                                                    $keyProductId = (int)explode('_', $key)[0];
                                                    if ($keyProductId == $product['id']) {
                                                        $cartKey = $key;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <input type="hidden" name="product_id" value="<?php echo $cartKey; ?>">
                                                <button type="submit" class="btn-remove">✕</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="cart-actions">
                            <a href="products.php" class="btn btn-secondary">Continuer les achats</a>
                            <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir vider le panier ?');" style="display: inline;">
                                <input type="hidden" name="action" value="clear">
                                <button type="submit" class="btn btn-secondary">Vider le panier</button>
                            </form>
                        </div>
                    </div>

                    <div class="cart-summary">
                        <h2>Résumé de la commande</h2>
                        <div class="summary-item">
                            <span>Sous-total:</span>
                            <span><?php echo formatPrice($total); ?></span>
                        </div>
                        <div class="summary-item">
                            <span>Livraison:</span>
                            <span><?php echo $total >= 500 ? 'Gratuite' : '50 MAD'; ?></span>
                        </div>
                        <div class="summary-total">
                            <span>Total:</span>
                            <span><?php echo formatPrice($total + ($total >= 500 ? 0 : 50)); ?></span>
                        </div>
                        <a href="checkout.php" class="btn btn-primary btn-large btn-block">Passer la commande</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        function updateQuantity(productKey, change) {
            const form = event.target.closest('form');
            const input = form.querySelector('input[name="quantity"]');
            const currentValue = parseInt(input.value);
            const max = parseInt(input.getAttribute('max'));
            const newValue = currentValue + change;
            
            if (newValue >= 1 && newValue <= max) {
                input.value = newValue;
                form.submit();
            }
        }
    </script>
</body>
</html>


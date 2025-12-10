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
                $productId = (int)$_POST['product_id'];
                $quantity = (int)$_POST['quantity'];
                if (isset($_SESSION['cart'][$productId])) {
                    if ($quantity > 0) {
                        $_SESSION['cart'][$productId] = $quantity;
                    } else {
                        unset($_SESSION['cart'][$productId]);
                    }
                }
                break;
            case 'remove':
                $productId = (int)$_POST['product_id'];
                if (isset($_SESSION['cart'][$productId])) {
                    unset($_SESSION['cart'][$productId]);
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
                          (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
                          FROM products p 
                          WHERE p.id IN ($placeholders) AND p.status = 'active'");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll();
    
    foreach ($products as $product) {
        $quantity = $_SESSION['cart'][$product['id']];
        $price = $product['sale_price'] ?: $product['price'];
        $subtotal = $price * $quantity;
        $total += $subtotal;
        
        $cartItems[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $subtotal
        ];
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
                                                    <?php if ($product['size']): ?>
                                                        <p>Taille: <?php echo clean($product['size']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="cart-price">
                                            <?php if ($product['sale_price']): ?>
                                                <span class="old-price"><?php echo formatPrice($product['price']); ?></span>
                                                <span class="current-price"><?php echo formatPrice($product['sale_price']); ?></span>
                                            <?php else: ?>
                                                <span class="current-price"><?php echo formatPrice($product['price']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cart-quantity">
                                            <form method="POST" class="quantity-form">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <div class="quantity-controls">
                                                    <button type="button" onclick="updateQuantity(<?php echo $product['id']; ?>, -1)">-</button>
                                                    <input type="number" 
                                                           name="quantity" 
                                                           value="<?php echo $item['quantity']; ?>" 
                                                           min="1" 
                                                           max="<?php echo $product['stock']; ?>"
                                                           onchange="this.form.submit()">
                                                    <button type="button" onclick="updateQuantity(<?php echo $product['id']; ?>, 1)">+</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td class="cart-subtotal">
                                            <?php echo formatPrice($item['subtotal']); ?>
                                        </td>
                                        <td class="cart-action">
                                            <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir retirer ce produit ?');">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
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
        function updateQuantity(productId, change) {
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


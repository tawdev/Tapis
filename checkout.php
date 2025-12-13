<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

// Vérifier si on affiche le message de succès (ne pas vérifier le panier dans ce cas)
$success = false;
if (isset($_GET['success']) && isset($_SESSION['order_number'])) {
    $success = true;
    $orderNumber = $_SESSION['order_number'];
    unset($_SESSION['order_number']);
}

// Vérifier que le panier n'est pas vide (sauf si on affiche le message de succès)
if (!$success && empty($_SESSION['cart'])) {
    redirect('cart.php');
}

// Calculer le total (seulement si pas en mode succès)
$cartItems = [];
$subtotal = 0;
$shipping = 0;
$total = 0;

if (!$success && !empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $db->prepare("SELECT p.* FROM products p WHERE p.id IN ($placeholders) AND p.status = 'active'");
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
                $price = $product['sale_price'] ?: $product['price'];
                
                // Calculer le sous-total avec le prix calculé si disponible
                if ($length > 0 && $width > 0 && $itemPrice > 0) {
                    $subtotal += $itemPrice * $quantity;
                } else {
                    $subtotal += $price * $quantity;
                }
                
                // Récupérer la couleur si disponible
                $color = isset($cartItemData['color']) && !empty(trim($cartItemData['color'])) ? trim(clean($cartItemData['color'])) : null;
                
                $cartItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'length' => $length,
                    'width' => $width,
                    'color' => $color,
                    'item_price' => $itemPrice
                ];
            } else {
                // Ancien format (compatibilité)
                $quantity = $cartItemData;
                $price = $product['sale_price'] ?: $product['price'];
                $subtotal += $price * $quantity;
                $cartItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'length' => 0,
                    'width' => 0,
                    'item_price' => $price
                ];
            }
        }
    }

    $shipping = $subtotal >= 500 ? 0 : 50;
    $total = $subtotal + $shipping;
}

// Traitement du formulaire
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$success) {
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerEmail = trim($_POST['customer_email'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $customerCity = trim($_POST['customer_city'] ?? '');
    $customerPostalCode = trim($_POST['customer_postal_code'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if (empty($customerName)) $errors[] = "Le nom est requis";
    if (empty($customerEmail) || !isValidEmail($customerEmail)) $errors[] = "Email valide requis";
    if (empty($customerPhone)) $errors[] = "Le téléphone est requis";
    if (empty($customerAddress)) $errors[] = "L'adresse est requise";
    if (empty($customerCity)) $errors[] = "La ville est requise";

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Créer la commande
            $orderNumber = generateOrderNumber();
            $stmt = $db->prepare("INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, customer_address, customer_city, customer_postal_code, total_amount, notes, status) 
                                  VALUES (:order_number, :customer_name, :customer_email, :customer_phone, :customer_address, :customer_city, :customer_postal_code, :total_amount, :notes, 'pending')");
            $stmt->execute([
                ':order_number' => $orderNumber,
                ':customer_name' => $customerName,
                ':customer_email' => $customerEmail,
                ':customer_phone' => $customerPhone,
                ':customer_address' => $customerAddress,
                ':customer_city' => $customerCity,
                ':customer_postal_code' => $customerPostalCode,
                ':total_amount' => $total,
                ':notes' => $notes
            ]);

            $orderId = $db->lastInsertId();

            // Ajouter les items
            foreach ($cartItems as $item) {
                $product = $item['product'];
                $quantity = $item['quantity'];
                $price = $product['sale_price'] ?: $product['price'];
                
                // Récupérer les dimensions, le prix calculé et la couleur si disponibles
                $length = isset($item['length']) ? (float)$item['length'] : 0;
                $width = isset($item['width']) ? (float)$item['width'] : 0;
                $itemPrice = isset($item['item_price']) ? (float)$item['item_price'] : $price;
                $color = isset($item['color']) && !empty(trim($item['color'])) ? trim(clean($item['color'])) : null;
                
                // Calculer la surface si les dimensions sont disponibles
                $surfaceM2 = 0;
                if ($length > 0 && $width > 0) {
                    $surfaceM2 = ($length * $width) / 10000; // Conversion cm² en m²
                }
                
                // Utiliser le prix calculé si disponible, sinon prix unitaire × quantité
                $itemSubtotal = ($length > 0 && $width > 0 && $itemPrice > 0) ? $itemPrice * $quantity : $price * $quantity;

                $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal, length_cm, width_cm, surface_m2, unit_price, calculated_price, color) 
                                      VALUES (:order_id, :product_id, :product_name, :product_price, :quantity, :subtotal, :length_cm, :width_cm, :surface_m2, :unit_price, :calculated_price, :color)");
                $stmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $product['id'],
                    ':product_name' => $product['name'],
                    ':product_price' => $price,
                    ':quantity' => $quantity,
                    ':subtotal' => $itemSubtotal,
                    ':length_cm' => $length > 0 ? $length : null,
                    ':width_cm' => $width > 0 ? $width : null,
                    ':surface_m2' => $surfaceM2 > 0 ? $surfaceM2 : null,
                    ':unit_price' => $price,
                    ':calculated_price' => ($length > 0 && $width > 0 && $itemPrice > 0) ? $itemPrice : null,
                    ':color' => $color
                ]);
            }

            $db->commit();
            
            // Vider le panier
            $_SESSION['cart'] = [];
            $_SESSION['order_number'] = $orderNumber;
            
            redirect('checkout.php?success=1');
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Erreur lors de la création de la commande";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="checkout-page">
        <div class="container">
            <?php if ($success): ?>
                <div class="checkout-success" id="checkout-success">
                    <div class="success-icon-wrapper">
                        <div class="success-icon">✓</div>
                        <div class="success-circle"></div>
                    </div>
                    <h1>✅ Commande confirmée !</h1>
                    <div class="success-message-box">
                        <p class="success-main-message">🎉 Votre commande a été enregistrée avec succès !</p>
                        <p class="order-number">
                            <span class="order-label">Numéro de commande :</span>
                            <span class="order-value"><?php echo clean($orderNumber); ?></span>
                        </p>
                        <p class="success-info">📧 Vous recevrez un email de confirmation sous peu.</p>
                    </div>
                    <div class="redirect-message">
                        <span class="redirect-icon">⏱️</span>
                        <span>Redirection vers les produits dans <strong id="countdown">3</strong> secondes...</span>
                    </div>
                    <div class="success-actions">
                        <a href="tracking.php?order=<?php echo clean($orderNumber); ?>" class="btn btn-primary">📦 Suivre ma commande</a>
                        <a href="products.php" class="btn btn-secondary" id="redirect-link">🛍️ Continuer les achats</a>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        let countdown = 3;
                        const countdownElement = document.getElementById('countdown');
                        
                        // Fonction de redirection vers products.php
                        function redirectToProducts() {
                            window.location.href = 'products.php';
                        }
                        
                        // Compte à rebours
                        const timer = setInterval(function() {
                            countdown--;
                            if (countdownElement) {
                                countdownElement.textContent = countdown;
                            }
                            
                            if (countdown <= 0) {
                                clearInterval(timer);
                                redirectToProducts();
                            }
                        }, 1000);
                        
                        // Fallback : redirection après 3.5 secondes maximum
                        setTimeout(function() {
                            clearInterval(timer);
                            redirectToProducts();
                        }, 3500);
                    });
                </script>
            <?php else: ?>
                <a href="cart.php" class="btn-back">
                    <span>←</span> Retour au panier
                </a>
                
                <h1>Finaliser la commande</h1>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo clean($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="checkout-layout">
                    <div class="checkout-form-wrapper">
                        <form method="POST" class="checkout-form" id="checkout-form">
                            <h2>Informations de livraison</h2>
                            
                            <div class="form-group">
                                <label for="customer_name">Nom complet *</label>
                                <input type="text" id="customer_name" name="customer_name" required 
                                       value="<?php echo isset($_POST['customer_name']) ? clean($_POST['customer_name']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="customer_email">Email *</label>
                                <input type="email" id="customer_email" name="customer_email" required
                                       value="<?php echo isset($_POST['customer_email']) ? clean($_POST['customer_email']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="customer_phone">Téléphone *</label>
                                <input type="tel" id="customer_phone" name="customer_phone" required
                                       value="<?php echo isset($_POST['customer_phone']) ? clean($_POST['customer_phone']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="customer_address">Adresse *</label>
                                <textarea id="customer_address" name="customer_address" rows="3" required><?php echo isset($_POST['customer_address']) ? clean($_POST['customer_address']) : ''; ?></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="customer_city">Ville *</label>
                                    <input type="text" id="customer_city" name="customer_city" required
                                           value="<?php echo isset($_POST['customer_city']) ? clean($_POST['customer_city']) : ''; ?>">
                                </div>

                                <div class="form-group">
                                    <label for="customer_postal_code">Code postal</label>
                                    <input type="text" id="customer_postal_code" name="customer_postal_code"
                                           value="<?php echo isset($_POST['customer_postal_code']) ? clean($_POST['customer_postal_code']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes">Notes (optionnel)</label>
                                <textarea id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? clean($_POST['notes']) : ''; ?></textarea>
                            </div>

                            <h2>Méthode de paiement</h2>
                            <div class="payment-methods">
                                <div class="payment-method">
                                    <input type="radio" id="payment_cash" name="payment_method" value="cash" checked>
                                    <label for="payment_cash">Paiement à la livraison</label>
                                </div>
                                <div class="payment-method">
                                    <input type="radio" id="payment_card" name="payment_method" value="card" disabled>
                                    <label for="payment_card">Carte bancaire (Bientôt disponible)</label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-large btn-block">Confirmer la commande</button>
                        </form>
                    </div>

                    <div class="checkout-summary">
                        <h2>Résumé</h2>
                        <div class="summary-items">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="summary-item-row">
                                    <span><?php echo clean($item['product']['name']); ?> x<?php echo $item['quantity']; ?></span>
                                    <span><?php echo formatPrice(($item['product']['sale_price'] ?: $item['product']['price']) * $item['quantity']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="summary-totals">
                            <div class="summary-item">
                                <span>Sous-total:</span>
                                <span><?php echo formatPrice($subtotal); ?></span>
                            </div>
                            <div class="summary-item">
                                <span>Livraison:</span>
                                <span><?php echo formatPrice($shipping); ?></span>
                            </div>
                            <div class="summary-total">
                                <span>Total:</span>
                                <span><?php echo formatPrice($total); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>


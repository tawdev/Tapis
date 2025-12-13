<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$length = isset($_POST['length']) ? (float)$_POST['length'] : 0;
$width = isset($_POST['width']) ? (float)$_POST['width'] : 0;
$calculatedPrice = isset($_POST['calculated_price']) ? (float)$_POST['calculated_price'] : 0;
$color = isset($_POST['color']) && !empty(trim($_POST['color'])) ? trim(clean($_POST['color'])) : '';

if ($productId <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit;
}

$db = getDB();

// Vérifier que le produit existe et est en stock
$stmt = $db->prepare("SELECT id, name, price, sale_price, stock FROM products WHERE id = :id AND status = 'active'");
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
    exit;
}

if ($product['stock'] < $quantity) {
    echo json_encode(['success' => false, 'message' => 'Stock insuffisant']);
    exit;
}

// Initialiser le panier
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Calculer le prix unitaire
$unitPrice = $product['sale_price'] ?: $product['price'];

// Si des dimensions sont fournies, utiliser le prix calculé, sinon utiliser le prix unitaire
$itemPrice = ($length > 0 && $width > 0 && $calculatedPrice > 0) ? $calculatedPrice : $unitPrice;

// Structure de l'item du panier
$cartItem = [
    'quantity' => $quantity,
    'length' => $length,
    'width' => $width,
    'unit_price' => $unitPrice,
    'calculated_price' => $calculatedPrice,
    'item_price' => $itemPrice,
    'color' => $color
];

// Ajouter ou mettre à jour la quantité
// Utiliser une clé unique pour permettre plusieurs fois le même produit avec des dimensions/couleurs différentes
$cartKey = $productId;
if ($length > 0 && $width > 0 && $calculatedPrice > 0) {
    // Créer une clé unique pour ce produit avec ces dimensions et couleur
    $cartKey = $productId . '_' . md5($length . '_' . $width . '_' . $calculatedPrice . '_' . $color);
} elseif ($color) {
    // Si seulement la couleur est différente
    $cartKey = $productId . '_' . md5($color);
}

if (isset($_SESSION['cart'][$cartKey])) {
    // Si les dimensions et couleur sont identiques, augmenter la quantité
    $existingItem = $_SESSION['cart'][$cartKey];
    $newQuantity = $existingItem['quantity'] + $quantity;
    if ($newQuantity > $product['stock']) {
        echo json_encode(['success' => false, 'message' => 'Stock insuffisant']);
        exit;
    }
    $cartItem['quantity'] = $newQuantity;
    $_SESSION['cart'][$cartKey] = $cartItem;
} else {
    $_SESSION['cart'][$cartKey] = $cartItem;
}

// Compter le total des articles dans le panier (somme des quantités)
$cartCount = 0;
foreach ($_SESSION['cart'] as $item) {
    if (is_array($item)) {
        $cartCount += $item['quantity'];
    } else {
        // Compatibilité avec l'ancien format
        $cartCount += $item;
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Produit ajouté au panier',
    'cart_count' => $cartCount
]);


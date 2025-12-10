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

// Ajouter ou mettre à jour la quantité
if (isset($_SESSION['cart'][$productId])) {
    $newQuantity = $_SESSION['cart'][$productId] + $quantity;
    if ($newQuantity > $product['stock']) {
        echo json_encode(['success' => false, 'message' => 'Stock insuffisant']);
        exit;
    }
    $_SESSION['cart'][$productId] = $newQuantity;
} else {
    $_SESSION['cart'][$productId] = $quantity;
}

// Compter le total des articles dans le panier (somme des quantités)
$cartCount = 0;
foreach ($_SESSION['cart'] as $qty) {
    $cartCount += $qty;
}

echo json_encode([
    'success' => true,
    'message' => 'Produit ajouté au panier',
    'cart_count' => $cartCount
]);


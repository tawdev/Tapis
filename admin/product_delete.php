<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId > 0) {
    $db = getDB();
    
    // Vérifier si le produit est présent dans des commandes
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM order_items WHERE product_id = :id");
    $stmt->execute([':id' => $productId]);
    $orderItemsCount = $stmt->fetch()['count'];
    
    if ($orderItemsCount > 0) {
        // Le produit est dans des commandes, on ne peut pas le supprimer
        $_SESSION['error_message'] = "Impossible de supprimer ce produit. Il est présent dans $orderItemsCount commande(s). Veuillez d'abord supprimer ou modifier les commandes concernées.";
        redirect('products.php');
        exit;
    }
    
    // Récupérer les images avant suppression
    $stmt = $db->prepare("SELECT image_path FROM product_images WHERE product_id = :id");
    $stmt->execute([':id' => $productId]);
    $images = $stmt->fetchAll();
    
    // Supprimer les images physiques
    foreach ($images as $image) {
        deleteImage($image['image_path']);
    }
    
    // Supprimer le produit (les images seront supprimées automatiquement via CASCADE)
    $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    
    $_SESSION['success_message'] = "Produit supprimé avec succès.";
}

redirect('products.php');


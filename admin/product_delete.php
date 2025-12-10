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
}

redirect('products.php');


<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$imageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($imageId > 0) {
    $db = getDB();
    
    // Récupérer le chemin de l'image
    $stmt = $db->prepare("SELECT image_path FROM product_images WHERE id = :id");
    $stmt->execute([':id' => $imageId]);
    $image = $stmt->fetch();
    
    if ($image) {
        // Supprimer l'image physique
        deleteImage($image['image_path']);
        
        // Supprimer de la base de données
        $stmt = $db->prepare("DELETE FROM product_images WHERE id = :id");
        $stmt->execute([':id' => $imageId]);
    }
}

if ($productId > 0) {
    redirect('product_form.php?id=' . $productId);
} else {
    redirect('products.php');
}


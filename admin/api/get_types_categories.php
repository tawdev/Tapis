<?php
/**
 * API endpoint pour récupérer les types de catégories selon la catégorie sélectionnée
 */
session_start();
require_once dirname(__DIR__) . '/../config/database.php';
require_once dirname(__DIR__) . '/../config/functions.php';

// Vérifier que l'utilisateur est admin
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit;
}

header('Content-Type: application/json');

$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if ($categoryId <= 0) {
    echo json_encode(['types' => []]);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name FROM types_categories WHERE category_id = :category_id ORDER BY name");
    $stmt->execute([':category_id' => $categoryId]);
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['types' => $types]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des types de catégories']);
}


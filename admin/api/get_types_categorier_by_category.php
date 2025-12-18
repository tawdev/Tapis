<?php
/**
 * API pour récupérer les types_categorier d'une catégorie donnée
 */

require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if ($categoryId <= 0) {
    echo json_encode(['types' => []]);
    exit;
}

try {
    $stmt = $db->prepare("SELECT id, name FROM types_categorier WHERE category_id = :category_id ORDER BY name");
    $stmt->execute([':category_id' => $categoryId]);
    $types = $stmt->fetchAll();
    echo json_encode(['types' => $types]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des types de catégories: ' . $e->getMessage()]);
}



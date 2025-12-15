<?php
/**
 * API pour récupérer les catégories d'un type donné
 */

require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$typeId = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;

if ($typeId <= 0) {
    echo json_encode(['categories' => []]);
    exit;
}

try {
    $stmt = $db->prepare("SELECT id, name FROM categories WHERE type_id = :type_id ORDER BY name");
    $stmt->execute([':type_id' => $typeId]);
    $categories = $stmt->fetchAll();
    echo json_encode(['categories' => $categories]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des catégories: ' . $e->getMessage()]);
}


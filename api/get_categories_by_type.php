<?php
/**
 * API publique : récupérer les catégories en fonction d'un type.
 * - ?type_id=ID → catégories de ce type
 * - pas de type_id ou 0 → toutes les catégories
 */

require_once '../config/database.php';
require_once '../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$typeId = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;

try {
    if ($typeId > 0) {
        $stmt = $db->prepare("SELECT id, name FROM categories WHERE type_id = :type_id ORDER BY name");
        $stmt->execute([':type_id' => $typeId]);
    } else {
        $stmt = $db->query("SELECT id, name FROM categories ORDER BY name");
    }
    $categories = $stmt->fetchAll();
    echo json_encode(['categories' => $categories]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des catégories']);
}


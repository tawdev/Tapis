<?php
/**
 * API pour récupérer les types selon la catégorie sélectionnée
 * Remplace get_types_categories.php
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
    // Récupérer le type_id de la catégorie
    $stmt = $db->prepare("SELECT type_id FROM categories WHERE id = :category_id");
    $stmt->execute([':category_id' => $categoryId]);
    $category = $stmt->fetch();
    
    if ($category && !empty($category['type_id'])) {
        // Si la catégorie a un type_id, retourner ce type
        $stmt = $db->prepare("SELECT id, name, description FROM types WHERE id = :type_id");
        $stmt->execute([':type_id' => $category['type_id']]);
        $type = $stmt->fetch();
        
        if ($type) {
            echo json_encode(['types' => [$type]]);
        } else {
            echo json_encode(['types' => []]);
        }
    } else {
        // Si la catégorie n'a pas de type_id, retourner tous les types disponibles
        // ou seulement ceux associés à des catégories similaires
        $stmt = $db->query("SELECT DISTINCT t.id, t.name, t.description 
                            FROM types t
                            INNER JOIN categories c ON c.type_id = t.id
                            ORDER BY t.name");
        $types = $stmt->fetchAll();
        
        echo json_encode(['types' => $types]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des types: ' . $e->getMessage()]);
}


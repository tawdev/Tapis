<?php
/**
 * Script PHP pour ajouter automatiquement les colonnes de dimensions à la table order_items
 * Ce script vérifie si les colonnes existent et les ajoute si nécessaire
 */

require_once '../config/database.php';

try {
    $db = getDB();
    
    // Vérifier quelles colonnes existent déjà
    $stmt = $db->query("SHOW COLUMNS FROM order_items LIKE 'length_cm'");
    $lengthExists = $stmt->rowCount() > 0;
    
    $stmt = $db->query("SHOW COLUMNS FROM order_items LIKE 'width_cm'");
    $widthExists = $stmt->rowCount() > 0;
    
    $stmt = $db->query("SHOW COLUMNS FROM order_items LIKE 'surface_m2'");
    $surfaceExists = $stmt->rowCount() > 0;
    
    $stmt = $db->query("SHOW COLUMNS FROM order_items LIKE 'unit_price'");
    $unitPriceExists = $stmt->rowCount() > 0;
    
    $stmt = $db->query("SHOW COLUMNS FROM order_items LIKE 'calculated_price'");
    $calculatedPriceExists = $stmt->rowCount() > 0;
    
    $alterations = [];
    
    // Ajouter les colonnes manquantes
    if (!$lengthExists) {
        $db->exec("ALTER TABLE order_items ADD COLUMN length_cm DECIMAL(10, 2) NULL COMMENT 'Longueur en centimètres'");
        $alterations[] = "Colonne 'length_cm' ajoutée";
    }
    
    if (!$widthExists) {
        $db->exec("ALTER TABLE order_items ADD COLUMN width_cm DECIMAL(10, 2) NULL COMMENT 'Largeur en centimètres'");
        $alterations[] = "Colonne 'width_cm' ajoutée";
    }
    
    if (!$surfaceExists) {
        $db->exec("ALTER TABLE order_items ADD COLUMN surface_m2 DECIMAL(10, 4) NULL COMMENT 'Surface calculée en m²'");
        $alterations[] = "Colonne 'surface_m2' ajoutée";
    }
    
    if (!$unitPriceExists) {
        $db->exec("ALTER TABLE order_items ADD COLUMN unit_price DECIMAL(10, 2) NULL COMMENT 'Prix unitaire au m² au moment de la commande'");
        $alterations[] = "Colonne 'unit_price' ajoutée";
    }
    
    if (!$calculatedPriceExists) {
        $db->exec("ALTER TABLE order_items ADD COLUMN calculated_price DECIMAL(10, 2) NULL COMMENT 'Prix calculé selon les dimensions'");
        $alterations[] = "Colonne 'calculated_price' ajoutée";
    }
    
    // Vérifier si l'index existe
    $stmt = $db->query("SHOW INDEX FROM order_items WHERE Key_name = 'idx_dimensions'");
    $indexExists = $stmt->rowCount() > 0;
    
    if (!$indexExists) {
        $db->exec("CREATE INDEX idx_dimensions ON order_items(length_cm, width_cm)");
        $alterations[] = "Index 'idx_dimensions' créé";
    }
    
    if (empty($alterations)) {
        echo "✓ Toutes les colonnes existent déjà. Aucune modification nécessaire.\n";
    } else {
        echo "✓ Modifications effectuées avec succès:\n";
        foreach ($alterations as $alt) {
            echo "  - $alt\n";
        }
    }
    
} catch (PDOException $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Migration terminée avec succès!\n";


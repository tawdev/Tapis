<?php
/**
 * Script de migration pour ajouter la colonne type_category_id à la table products
 * Exécutez ce script une seule fois pour mettre à jour votre base de données
 */

require_once '../config/database.php';

$db = getDB();

try {
    // Vérifier si la colonne existe déjà
    $stmt = $db->query("SELECT COLUMN_NAME 
                        FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'products' 
                        AND COLUMN_NAME = 'type_category_id'");
    
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // Ajouter la colonne type_category_id
        $db->exec("ALTER TABLE products ADD COLUMN type_category_id INT NULL AFTER category_id");
        echo "✅ Colonne 'type_category_id' ajoutée avec succès à la table 'products'.\n";
        
        // Ajouter la clé étrangère
        try {
            $db->exec("ALTER TABLE products 
                      ADD FOREIGN KEY (type_category_id) REFERENCES types_categories(id) ON DELETE SET NULL");
            echo "✅ Clé étrangère ajoutée avec succès.\n";
        } catch (PDOException $e) {
            echo "⚠️  Clé étrangère : " . $e->getMessage() . "\n";
        }
        
        // Ajouter l'index
        try {
            $db->exec("ALTER TABLE products ADD INDEX idx_type_category (type_category_id)");
            echo "✅ Index ajouté avec succès.\n";
        } catch (PDOException $e) {
            echo "⚠️  Index : " . $e->getMessage() . "\n";
        }
    } else {
        echo "ℹ️ La colonne 'type_category_id' existe déjà dans la table 'products'.\n";
    }
    
    // Vérifier la structure de la table
    echo "\n📋 Structure actuelle de la table 'products' (colonnes pertinentes) :\n";
    $stmt = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY 
                        FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'products' 
                        AND COLUMN_NAME IN ('category_id', 'type_category_id')
                        ORDER BY ORDINAL_POSITION");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n";
    printf("%-20s %-20s %-10s %-10s\n", "Column", "Type", "Nullable", "Key");
    echo str_repeat("-", 60) . "\n";
    
    foreach ($columns as $column) {
        printf("%-20s %-20s %-10s %-10s\n",
            $column['COLUMN_NAME'],
            $column['COLUMN_TYPE'],
            $column['IS_NULLABLE'],
            $column['COLUMN_KEY'] ?? 'NULL'
        );
    }
    
    echo "\n✅ Migration terminée avec succès !\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la migration : " . $e->getMessage() . "\n";
    exit(1);
}


<?php
/**
 * Script de migration pour ajouter la colonne image à la table categories
 * Exécutez ce script une seule fois pour mettre à jour votre base de données
 */

require_once '../config/database.php';

$db = getDB();

try {
    // Vérifier si la colonne existe déjà
    $stmt = $db->query("SELECT COLUMN_NAME 
                        FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'categories' 
                        AND COLUMN_NAME = 'image'");
    
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // Ajouter la colonne image
        $db->exec("ALTER TABLE categories ADD COLUMN image VARCHAR(255) NULL AFTER description");
        echo "✅ Colonne 'image' ajoutée avec succès à la table 'categories'.\n";
    } else {
        echo "ℹ️ La colonne 'image' existe déjà dans la table 'categories'.\n";
    }
    
    // Vérifier la structure de la table
    echo "\n📋 Structure actuelle de la table 'categories' :\n";
    $stmt = $db->query("DESCRIBE categories");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n";
    printf("%-20s %-20s %-10s %-5s %-10s %-10s\n", "Field", "Type", "Null", "Key", "Default", "Extra");
    echo str_repeat("-", 85) . "\n";
    
    foreach ($columns as $column) {
        printf("%-20s %-20s %-10s %-5s %-10s %-10s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            $column['Key'],
            $column['Default'] ?? 'NULL',
            $column['Extra']
        );
    }
    
    echo "\n✅ Migration terminée avec succès !\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la migration : " . $e->getMessage() . "\n";
    exit(1);
}


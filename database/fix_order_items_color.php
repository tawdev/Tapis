<?php
/**
 * Script de migration pour ajouter la colonne color à la table order_items
 * Accédez à : http://localhost/Tapis/database/fix_order_items_color.php
 */

require_once '../config/database.php';

try {
    $db = getDB();
    
    echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Migration - Ajout colonne color</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 2rem; max-width: 800px; margin: 0 auto; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 1rem; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Migration : Ajout de la colonne 'color' à order_items</h1>
    <hr>";

    // Vérifier si la colonne existe déjà
    $stmt = $db->query("SHOW COLUMNS FROM order_items LIKE 'color'");
    $columnExists = $stmt->rowCount() > 0;
    
    if ($columnExists) {
        echo "<p class='success'>✅ La colonne 'color' existe déjà dans la table order_items.</p>";
        
        // Afficher les détails de la colonne
        $stmt = $db->query("SHOW COLUMNS FROM order_items WHERE Field = 'color'");
        $column = $stmt->fetch();
        echo "<p><strong>Type:</strong> {$column['Type']}, <strong>Null:</strong> {$column['Null']}, <strong>Default:</strong> " . ($column['Default'] ?? 'NULL') . "</p>";
    } else {
        echo "<p class='info'>ℹ️ La colonne 'color' n'existe pas. Création en cours...</p>";
        
        try {
            // Ajouter la colonne color
            $db->exec("ALTER TABLE order_items 
                       ADD COLUMN color VARCHAR(50) NULL COMMENT 'Couleur sélectionnée par le client' AFTER calculated_price");
            
            echo "<p class='success'>✅ Colonne 'color' ajoutée avec succès à la table order_items.</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>❌ Erreur lors de l'ajout de la colonne : " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p class='info'>💡 Vous pouvez exécuter manuellement cette commande SQL :</p>";
            echo "<pre>ALTER TABLE order_items ADD COLUMN color VARCHAR(50) NULL COMMENT 'Couleur sélectionnée par le client' AFTER calculated_price;</pre>";
        }
    }
    
    // Afficher la structure de la table
    echo "<h2>📋 Structure de la table order_items :</h2>";
    echo "<pre>";
    $stmt = $db->query("DESCRIBE order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $col['Default'] !== null ? " DEFAULT '{$col['Default']}'" : '';
        $comment = !empty($col['Comment']) ? " COMMENT '{$col['Comment']}'" : '';
        echo "  - {$col['Field']} ({$col['Type']}) {$null}{$default}{$comment}\n";
    }
    echo "</pre>";
    
    // Vérifier les données existantes
    $stmt = $db->query("SELECT COUNT(*) as total FROM order_items");
    $total = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM order_items WHERE color IS NOT NULL AND color != ''");
    $withColor = $stmt->fetch()['total'];
    
    echo "<h2>📊 Statistiques :</h2>";
    echo "<ul>";
    echo "<li><strong>Total d'items de commande :</strong> {$total}</li>";
    echo "<li class='success'><strong>Items avec couleur :</strong> {$withColor}</li>";
    echo "<li class='info'><strong>Items sans couleur :</strong> " . ($total - $withColor) . "</li>";
    echo "</ul>";
    
    echo "<p class='success'><strong>✅ Migration terminée avec succès !</strong></p>";
    echo "<p><a href='../admin/orders.php'>← Retour à l'admin</a></p>";
    echo "</body></html>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</body></html>";
    exit(1);
}


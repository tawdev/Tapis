<?php
/**
 * Script de migration pour modifier le champ color de la table products
 * pour supporter le stockage JSON des couleurs multiples
 * Accédez à : http://localhost/Tapis/database/fix_products_color_json.php
 */

require_once '../config/database.php';

try {
    $db = getDB();
    
    echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Migration - Support JSON pour couleurs multiples</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 2rem; max-width: 800px; margin: 0 auto; }
        .success { color: green; font-weight: bold; }
        .info { color: blue; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f5f5f5; padding: 1rem; border-radius: 5px; overflow-x: auto; }
        .step { margin: 1rem 0; padding: 1rem; border-left: 4px solid #007bff; background: #f8f9fa; }
    </style>
</head>
<body>
    <h1>Migration : Support JSON pour couleurs multiples</h1>
    <hr>";

    // Étape 1 : Vérifier la structure actuelle de la colonne color
    echo "<div class='step'>";
    echo "<h2>Étape 1 : Vérification de la colonne 'color'</h2>";
    
    $stmt = $db->query("SHOW COLUMNS FROM products WHERE Field = 'color'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($column) {
        echo "<p class='info'>✅ La colonne 'color' existe.</p>";
        echo "<p><strong>Type actuel:</strong> {$column['Type']}</p>";
        echo "<p><strong>Null:</strong> {$column['Null']}</p>";
        echo "<p><strong>Default:</strong> " . ($column['Default'] ?? 'NULL') . "</p>";
        
        $currentType = strtoupper($column['Type']);
        $needsUpdate = false;
        
        // Vérifier si le type est suffisant pour stocker du JSON
        if (strpos($currentType, 'TEXT') === false && strpos($currentType, 'VARCHAR') !== false) {
            // Si c'est VARCHAR, vérifier la taille
            preg_match('/VARCHAR\((\d+)\)/', $currentType, $matches);
            if (isset($matches[1]) && (int)$matches[1] < 2000) {
                $needsUpdate = true;
                echo "<p class='warning'>⚠️ Le type actuel ({$column['Type']}) peut être insuffisant pour stocker du JSON avec plusieurs couleurs.</p>";
                echo "<p class='info'>💡 Recommandation : Modifier en TEXT pour supporter des données JSON plus longues.</p>";
            } else {
                echo "<p class='success'>✅ Le type actuel devrait suffire pour stocker du JSON.</p>";
            }
        } elseif (strpos($currentType, 'TEXT') !== false) {
            echo "<p class='success'>✅ Le type TEXT est parfait pour stocker du JSON.</p>";
        } else {
            $needsUpdate = true;
            echo "<p class='warning'>⚠️ Type inattendu. Recommandation : Modifier en TEXT.</p>";
        }
        
        // Étape 2 : Modifier la colonne si nécessaire
        if ($needsUpdate) {
            echo "</div>";
            echo "<div class='step'>";
            echo "<h2>Étape 2 : Modification de la colonne</h2>";
            
            try {
                // Modifier la colonne en TEXT pour supporter du JSON plus long
                $db->exec("ALTER TABLE products MODIFY COLUMN color TEXT NULL COMMENT 'Couleurs du produit au format JSON: [{\"name\":\"Rouge\",\"index\":1,\"image\":\"path\"},...] ou couleur simple (ancien format)'");
                
                echo "<p class='success'>✅ Colonne 'color' modifiée avec succès en TEXT.</p>";
                
                // Vérifier la nouvelle structure
                $stmt = $db->query("SHOW COLUMNS FROM products WHERE Field = 'color'");
                $newColumn = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<p><strong>Nouveau type:</strong> {$newColumn['Type']}</p>";
                
            } catch (PDOException $e) {
                echo "<p class='error'>❌ Erreur lors de la modification : " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p class='info'>💡 Vous pouvez exécuter manuellement cette commande SQL :</p>";
                echo "<pre>ALTER TABLE products MODIFY COLUMN color TEXT NULL COMMENT 'Couleurs du produit au format JSON'</pre>";
            }
        } else {
            echo "<p class='info'>ℹ️ Aucune modification nécessaire. La colonne est déjà adaptée.</p>";
        }
    } else {
        echo "<p class='error'>❌ La colonne 'color' n'existe pas dans la table products.</p>";
        echo "<p class='info'>💡 Création de la colonne...</p>";
        
        try {
            $db->exec("ALTER TABLE products ADD COLUMN color TEXT NULL COMMENT 'Couleurs du produit au format JSON' AFTER size");
            echo "<p class='success'>✅ Colonne 'color' créée avec succès.</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>❌ Erreur lors de la création : " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    echo "</div>";
    
    // Étape 3 : Vérifier les données existantes
    echo "<div class='step'>";
    echo "<h2>Étape 3 : Vérification des données existantes</h2>";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM products");
    $total = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE color IS NOT NULL AND color != ''");
    $withColor = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT id, name, color FROM products WHERE color IS NOT NULL AND color != '' LIMIT 5");
    $productsWithColor = $stmt->fetchAll();
    
    echo "<ul>";
    echo "<li><strong>Total de produits :</strong> {$total}</li>";
    echo "<li><strong>Produits avec couleur :</strong> {$withColor}</li>";
    echo "</ul>";
    
    if (count($productsWithColor) > 0) {
        echo "<h3>Exemples de produits avec couleur :</h3>";
        echo "<pre>";
        foreach ($productsWithColor as $product) {
            $colorValue = $product['color'];
            $isJson = json_decode($colorValue, true);
            $format = ($isJson && json_last_error() === JSON_ERROR_NONE) ? 'JSON' : 'Texte simple';
            
            echo "ID: {$product['id']} - {$product['name']}\n";
            echo "Format: {$format}\n";
            if ($format === 'JSON') {
                echo "Couleurs: " . json_encode($isJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "Couleur: " . htmlspecialchars(substr($colorValue, 0, 100)) . "\n";
            }
            echo "---\n";
        }
        echo "</pre>";
    }
    
    echo "</div>";
    
    // Étape 4 : Structure finale
    echo "<div class='step'>";
    echo "<h2>Étape 4 : Structure finale de la table products</h2>";
    echo "<pre>";
    $stmt = $db->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $col['Default'] !== null ? " DEFAULT '{$col['Default']}'" : '';
        $comment = !empty($col['Comment']) ? " COMMENT '{$col['Comment']}'" : '';
        echo "  - {$col['Field']} ({$col['Type']}) {$null}{$default}{$comment}\n";
    }
    echo "</pre>";
    echo "</div>";
    
    // Étape 5 : Format JSON attendu
    echo "<div class='step'>";
    echo "<h2>Étape 5 : Format JSON attendu</h2>";
    echo "<p>Le champ <code>color</code> peut maintenant stocker :</p>";
    echo "<ol>";
    echo "<li><strong>Format JSON (nouveau) :</strong> Tableau de couleurs avec images</li>";
    echo "<li><strong>Format texte simple (ancien) :</strong> Compatible avec l'ancien système</li>";
    echo "</ol>";
    echo "<h3>Exemple de format JSON :</h3>";
    echo "<pre>";
    $exampleJson = [
        [
            'name' => 'Rouge',
            'index' => 1,
            'image' => 'assets/images/products/color_red.jpg'
        ],
        [
            'name' => 'Bleu',
            'index' => 2,
            'image' => 'assets/images/products/color_blue.jpg'
        ]
    ];
    echo json_encode($exampleJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
    echo "</div>";
    
    echo "<p class='success'><strong>✅ Migration terminée avec succès !</strong></p>";
    echo "<p><a href='../admin/products.php'>← Retour à l'admin</a></p>";
    echo "</body></html>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</body></html>";
    exit(1);
}


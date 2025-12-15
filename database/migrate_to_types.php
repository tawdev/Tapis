<?php
/**
 * Script de migration : Restructuration de types_categories vers types
 * 
 * Ce script :
 * 1. Crée la table types
 * 2. Ajoute type_id à categories
 * 3. Migre les données de types_categories vers types
 * 4. Met à jour categories avec type_id
 * 5. Remplace type_category_id par type_id dans products
 * 6. Supprime la table types_categories
 * 
 * ATTENTION: Faites une sauvegarde de votre base de données avant d'exécuter ce script!
 */

require_once '../config/database.php';

$db = getDB();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration vers types</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .step { margin: 15px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #007bff; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Migration : types_categories → types</h1>
        
        <?php
        try {
            $db->beginTransaction();
            
            // Étape 1: Créer la table types
            echo '<div class="step">';
            echo '<h3>Étape 1: Création de la table types</h3>';
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS types (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                echo '<p class="success">✅ Table types créée avec succès</p>';
            } catch (PDOException $e) {
                echo '<p class="error">❌ Erreur: ' . $e->getMessage() . '</p>';
                throw $e;
            }
            echo '</div>';
            
            // Étape 2: Ajouter type_id à categories
            echo '<div class="step">';
            echo '<h3>Étape 2: Ajout de type_id à categories</h3>';
            try {
                // Vérifier si la colonne existe
                $stmt = $db->query("SELECT COLUMN_NAME 
                                    FROM INFORMATION_SCHEMA.COLUMNS 
                                    WHERE TABLE_SCHEMA = DATABASE() 
                                    AND TABLE_NAME = 'categories' 
                                    AND COLUMN_NAME = 'type_id'");
                if (!$stmt->fetch()) {
                    $db->exec("ALTER TABLE categories 
                               ADD COLUMN type_id INT NULL AFTER id");
                    echo '<p class="success">✅ Colonne type_id ajoutée à categories</p>';
                    
                    // Ajouter la clé étrangère
                    try {
                        $db->exec("ALTER TABLE categories 
                                   ADD FOREIGN KEY (type_id) REFERENCES types(id) ON DELETE SET NULL");
                        echo '<p class="success">✅ Clé étrangère ajoutée</p>';
                    } catch (PDOException $e) {
                        echo '<p class="warning">⚠️ Clé étrangère: ' . $e->getMessage() . '</p>';
                    }
                    
                    // Ajouter l'index
                    try {
                        $db->exec("ALTER TABLE categories ADD INDEX idx_type (type_id)");
                        echo '<p class="success">✅ Index ajouté</p>';
                    } catch (PDOException $e) {
                        echo '<p class="warning">⚠️ Index: ' . $e->getMessage() . '</p>';
                    }
                } else {
                    echo '<p class="info">ℹ️ Colonne type_id existe déjà</p>';
                }
            } catch (PDOException $e) {
                echo '<p class="error">❌ Erreur: ' . $e->getMessage() . '</p>';
                throw $e;
            }
            echo '</div>';
            
            // Étape 3: Migrer les données de types_categories vers types
            echo '<div class="step">';
            echo '<h3>Étape 3: Migration des données vers types</h3>';
            try {
                $stmt = $db->query("SELECT COUNT(*) as count FROM types_categories");
                $typesCategoriesCount = $stmt->fetch()['count'];
                echo '<p class="info">ℹ️ ' . $typesCategoriesCount . ' types_categories trouvés</p>';
                
                $stmt = $db->query("INSERT INTO types (name, description)
                                    SELECT DISTINCT tc.name, tc.description
                                    FROM types_categories tc
                                    WHERE NOT EXISTS (
                                        SELECT 1 FROM types t WHERE t.name = tc.name
                                    )");
                $inserted = $stmt->rowCount();
                echo '<p class="success">✅ ' . $inserted . ' types insérés</p>';
            } catch (PDOException $e) {
                echo '<p class="error">❌ Erreur: ' . $e->getMessage() . '</p>';
                throw $e;
            }
            echo '</div>';
            
            // Étape 4: Mettre à jour categories avec type_id
            echo '<div class="step">';
            echo '<h3>Étape 4: Mise à jour de categories avec type_id</h3>';
            try {
                $stmt = $db->exec("UPDATE categories c
                                   INNER JOIN types_categories tc ON tc.category_id = c.id
                                   INNER JOIN types t ON t.name = tc.name
                                   SET c.type_id = t.id
                                   WHERE c.type_id IS NULL");
                echo '<p class="success">✅ ' . $stmt . ' catégories mises à jour</p>';
            } catch (PDOException $e) {
                echo '<p class="error">❌ Erreur: ' . $e->getMessage() . '</p>';
                throw $e;
            }
            echo '</div>';
            
            // Étape 5: Créer la table temporaire de mapping
            echo '<div class="step">';
            echo '<h3>Étape 5: Création du mapping type_category_id → type_id</h3>';
            try {
                $db->exec("CREATE TEMPORARY TABLE temp_type_mapping AS
                           SELECT 
                               tc.id as old_type_category_id,
                               t.id as new_type_id
                           FROM types_categories tc
                           INNER JOIN types t ON t.name = tc.name");
                echo '<p class="success">✅ Table temporaire créée</p>';
            } catch (PDOException $e) {
                echo '<p class="error">❌ Erreur: ' . $e->getMessage() . '</p>';
                throw $e;
            }
            echo '</div>';
            
            // Étape 6: Ajouter type_id à products
            echo '<div class="step">';
            echo '<h3>Étape 6: Ajout de type_id à products</h3>';
            try {
                $stmt = $db->query("SELECT COLUMN_NAME 
                                    FROM INFORMATION_SCHEMA.COLUMNS 
                                    WHERE TABLE_SCHEMA = DATABASE() 
                                    AND TABLE_NAME = 'products' 
                                    AND COLUMN_NAME = 'type_id'");
                if (!$stmt->fetch()) {
                    $db->exec("ALTER TABLE products 
                               ADD COLUMN type_id INT NULL AFTER category_id");
                    echo '<p class="success">✅ Colonne type_id ajoutée à products</p>';
                    
                    // Ajouter l'index temporaire
                    try {
                        $db->exec("ALTER TABLE products ADD INDEX idx_type_id_temp (type_id)");
                        echo '<p class="success">✅ Index temporaire ajouté</p>';
                    } catch (PDOException $e) {
                        echo '<p class="warning">⚠️ Index: ' . $e->getMessage() . '</p>';
                    }
                } else {
                    echo '<p class="info">ℹ️ Colonne type_id existe déjà</p>';
                }
            } catch (PDOException $e) {
                echo '<p class="error">❌ Erreur: ' . $e->getMessage() . '</p>';
                throw $e;
            }
            echo '</div>';
            
            // Étape 7: Migrer type_category_id vers type_id dans products
            echo '<div class="step">';
            echo '<h3>Étape 7: Migration type_category_id → type_id dans products</h3>';
            try {
                $stmt = $db->exec("UPDATE products p
                                   INNER JOIN temp_type_mapping ttm ON p.type_category_id = ttm.old_type_category_id
                                   SET p.type_id = ttm.new_type_id");
                echo '<p class="success">✅ ' . $stmt . ' produits mis à jour</p>';
            } catch (PDOException $e) {
                echo '<p class="error">❌ Erreur: ' . $e->getMessage() . '</p>';
                throw $e;
            }
            echo '</div>';
            
            // Étape 8: Supprimer l'ancienne colonne type_category_id
            echo '<div class="step">';
            echo '<h3>Étape 8: Suppression de type_category_id de products</h3>';
            try {
                // Supprimer la clé étrangère
                $stmt = $db->query("SELECT CONSTRAINT_NAME 
                                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                                    WHERE TABLE_SCHEMA = DATABASE() 
                                    AND TABLE_NAME = 'products' 
                                    AND COLUMN_NAME = 'type_category_id' 
                                    AND REFERENCED_TABLE_NAME = 'types_categories'");
                $fk = $stmt->fetch();
                if ($fk) {
                    $db->exec("ALTER TABLE products DROP FOREIGN KEY " . $fk['CONSTRAINT_NAME']);
                    echo '<p class="success">✅ Clé étrangère supprimée</p>';
                }
                
                // Supprimer l'index
                try {
                    $db->exec("ALTER TABLE products DROP INDEX idx_type_category");
                    echo '<p class="success">✅ Index supprimé</p>';
                } catch (PDOException $e) {
                    echo '<p class="warning">⚠️ Index: ' . $e->getMessage() . '</p>';
                }
                
                // Supprimer la colonne
                $db->exec("ALTER TABLE products DROP COLUMN type_category_id");
                echo '<p class="success">✅ Colonne type_category_id supprimée</p>';
            } catch (PDOException $e) {
                echo '<p class="error">❌ Erreur: ' . $e->getMessage() . '</p>';
                throw $e;
            }
            echo '</div>';
            
            // Étape 9: Ajouter la clé étrangère pour type_id dans products
            echo '<div class="step">';
            echo '<h3>Étape 9: Ajout de la clé étrangère type_id dans products</h3>';
            try {
                $db->exec("ALTER TABLE products 
                           ADD FOREIGN KEY (type_id) REFERENCES types(id) ON DELETE SET NULL");
                echo '<p class="success">✅ Clé étrangère ajoutée</p>';
            } catch (PDOException $e) {
                echo '<p class="warning">⚠️ Clé étrangère: ' . $e->getMessage() . '</p>';
            }
            echo '</div>';
            
            // Étape 10: Supprimer la table types_categories
            echo '<div class="step">';
            echo '<h3>Étape 10: Suppression de la table types_categories</h3>';
            try {
                $db->exec("DROP TABLE IF EXISTS types_categories");
                echo '<p class="success">✅ Table types_categories supprimée</p>';
            } catch (PDOException $e) {
                echo '<p class="error">❌ Erreur: ' . $e->getMessage() . '</p>';
                throw $e;
            }
            echo '</div>';
            
            $db->commit();
            
            echo '<div class="step" style="border-left-color: #28a745;">';
            echo '<h2 class="success">✅ Migration terminée avec succès!</h2>';
            echo '</div>';
            
            // Afficher les statistiques
            echo '<div class="step">';
            echo '<h3>📊 Statistiques</h3>';
            $stmt = $db->query("SELECT COUNT(*) as count FROM types");
            echo '<p>Types: ' . $stmt->fetch()['count'] . '</p>';
            $stmt = $db->query("SELECT COUNT(*) as count FROM categories WHERE type_id IS NOT NULL");
            echo '<p>Catégories avec type: ' . $stmt->fetch()['count'] . '</p>';
            $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE type_id IS NOT NULL");
            echo '<p>Produits avec type: ' . $stmt->fetch()['count'] . '</p>';
            echo '</div>';
            
        } catch (PDOException $e) {
            $db->rollBack();
            echo '<div class="step" style="border-left-color: #dc3545;">';
            echo '<h2 class="error">❌ Erreur lors de la migration</h2>';
            echo '<p class="error">' . $e->getMessage() . '</p>';
            echo '<p>Les modifications ont été annulées (rollback).</p>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>


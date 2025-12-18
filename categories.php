<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

// Récupérer toutes les catégories avec le nombre de produits et le type associé
$stmt = $db->query("SELECT c.*, t.name AS type_name, COUNT(p.id) as product_count 
                    FROM categories c 
                    LEFT JOIN types t ON c.type_id = t.id
                    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
                    GROUP BY c.id 
                    ORDER BY c.name");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catégories - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* 4 colonnes pour la grille des catégories sur desktop */
        .categories-grid-detailed {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1.75rem;
        }

        /* Si la dernière ligne contient exactement 2 cartes,
           les placer au centre (colonnes 2 et 3) comme dans ton exemple */
        @media (min-width: 1025px) {
            .categories-grid-detailed > .category-card-detailed:nth-last-child(2):nth-child(4n + 1) {
                grid-column: 2;
            }
            .categories-grid-detailed > .category-card-detailed:nth-last-child(1):nth-child(4n + 2) {
                grid-column: 3;
            }
        }

        @media (max-width: 1024px) {
            .categories-grid-detailed {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .categories-grid-detailed {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 480px) {
            .categories-grid-detailed {
                grid-template-columns: 1fr;
            }
        }

        /* Bouton de retour */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            margin-bottom: 1.5rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid var(--primary-color);
        }

        .back-button:hover {
            background: transparent;
            color: var(--primary-color);
            transform: translateX(-3px);
        }

        .back-button::before {
            content: "←";
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="categories-page">
        <div class="container">
            <a href="index.php" class="back-button">Retour à l'accueil</a>
            <div class="page-header">
                <h1>Nos Catégories</h1>
                <p>Découvrez notre sélection de tapis par catégorie</p>
            </div>

            <?php if (count($categories) > 0): ?>
                <div class="categories-grid-detailed">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card-detailed">
                            <a href="products.php?category=<?php echo $category['id']; ?>">
                                <div class="category-image-detailed">
                                    <?php if ($category['image']): ?>
                                        <img src="<?php echo clean($category['image']); ?>" alt="<?php echo clean($category['name']); ?>">
                                    <?php else: ?>
                                        <div class="category-placeholder">
                                            <span class="category-icon">🏺</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="category-overlay">
                                        <span class="category-count"><?php echo $category['product_count']; ?> produit<?php echo $category['product_count'] > 1 ? 's' : ''; ?></span>
                                    </div>
                                </div>
                                <div class="category-content">
                                    <h2><?php echo clean($category['name']); ?></h2>
                                    <?php if ($category['description']): ?>
                                        <p><?php echo clean($category['description']); ?></p>
                                    <?php endif; ?>
                                    <span class="category-link">Voir les produits →</span>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-categories">
                    <p>Aucune catégorie disponible pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>


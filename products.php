<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

// Paramètres de filtrage
$categoryId      = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$typeId          = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$typeCategoryId  = isset($_GET['type_category']) ? (int)$_GET['type_category'] : 0;
$sort = isset($_GET['sort']) ? clean($_GET['sort']) : 'newest';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$colorFilter = isset($_GET['color']) ? trim(clean($_GET['color'])) : '';

// Construction de la requête
$where = ["p.status = 'active'"];
$params = [];

if ($categoryId > 0) {
    $where[] = "p.category_id = :category_id";
    $params[':category_id'] = $categoryId;
}

if ($typeId > 0) {
    $where[] = "p.type_id = :type_id";
    $params[':type_id'] = $typeId;
}

if ($typeCategoryId > 0) {
    $where[] = "p.type_category_id = :type_category_id";
    $params[':type_category_id'] = $typeCategoryId;
}

if ($search) {
    $where[] = "(p.name LIKE :search_name OR p.description LIKE :search_desc)";
    $params[':search_name'] = "%$search%";
    $params[':search_desc'] = "%$search%";
}

// Filtre par couleur (cherche le nom de couleur dans le champ color, JSON ou texte simple)
if ($colorFilter !== '') {
    $where[] = "LOWER(p.color) LIKE :color_filter";
    $params[':color_filter'] = '%' . strtolower($colorFilter) . '%';
}

$whereClause = implode(' AND ', $where);

// Tri
$orderBy = "p.created_at DESC";
switch ($sort) {
    case 'price_low':
        $orderBy = "COALESCE(p.sale_price, p.price) ASC";
        break;
    case 'price_high':
        $orderBy = "COALESCE(p.sale_price, p.price) DESC";
        break;
    case 'bestseller':
        $orderBy = "p.best_seller DESC, p.created_at DESC";
        break;
    case 'newest':
    default:
        $orderBy = "p.created_at DESC";
        break;
}

// Compter le total
$countSql = "SELECT COUNT(*) as total FROM products p WHERE $whereClause";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetch()['total'];

// Récupérer tous les produits avec prix unitaire calculé
$sql = "SELECT p.*, c.name as category_name, t.name as type_name, tc.name AS type_category_name,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
        COALESCE(p.sale_price, p.price) as unit_price
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN types t ON p.type_id = t.id 
        LEFT JOIN types_categorier tc ON p.type_category_id = tc.id
        WHERE $whereClause 
        ORDER BY $orderBy";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$products = $stmt->fetchAll();

// Récupérer tous les types
$stmt = $db->query("SELECT * FROM types ORDER BY name");
$types = $stmt->fetchAll();

// Récupérer les catégories (filtrées par type si sélectionné)
if ($typeId > 0) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE type_id = :type_id ORDER BY name");
    $stmt->execute([':type_id' => $typeId]);
    $categories = $stmt->fetchAll();
} else {
    $stmt = $db->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
}

// Récupérer les types de catégories (dépendent de la catégorie sélectionnée)
if ($categoryId > 0) {
    $stmt = $db->prepare("SELECT tc.*, c.name AS category_name 
                          FROM types_categorier tc 
                          INNER JOIN categories c ON tc.category_id = c.id
                          WHERE tc.category_id = :category_id
                          ORDER BY tc.name");
    $stmt->execute([':category_id' => $categoryId]);
    $typeCategories = $stmt->fetchAll();
} else {
    // Aucune catégorie sélectionnée : pas de types de catégories proposés
    $typeCategories = [];
}

// Récupérer toutes les couleurs disponibles (à partir de tous les produits)
$availableColors = [];
$stmt = $db->query("SELECT color FROM products WHERE color IS NOT NULL AND color <> ''");
while ($row = $stmt->fetch()) {
    $rawColor = trim($row['color']);
    if ($rawColor === '') continue;

    $decoded = json_decode($rawColor, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        foreach ($decoded as $c) {
            if (!empty($c['name'])) {
                $name = trim($c['name']);
                if ($name !== '') {
                    $key = mb_strtolower($name, 'UTF-8');
                    $availableColors[$key] = $name;
                }
            }
        }
    } else {
        // Ancien format: texte simple
        $name = $rawColor;
        $key = mb_strtolower($name, 'UTF-8');
        $availableColors[$key] = $name;
    }
}
ksort($availableColors);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .type-category-btn:hover {
            background: #8B4513 !important;
            color: #FFFFFF !important;
            transform: translateY(-3px) !important;
            box-shadow: 0 6px 20px rgba(139,69,19,0.4) !important;
            border-color: #8B4513 !important;
            text-decoration: none !important;
        }
        .type-category-btn.active:hover {
            background: linear-gradient(135deg, #8B4513 0%, #2C1810 100%) !important;
            transform: translateY(-2px) !important;
        }

        /* Mise en page de la rangée de filtres */
        .filters-form-horizontal .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }
        .filters-form-horizontal .filter-item {
            flex: 1 1 190px;
            min-width: 0;
        }
        .filters-form-horizontal .filter-actions {
            flex: 0 0 auto;
        }

        /* === Select couleur personnalisé (nom + cercle de couleur) === */
        #color-filter {
            display: none; /* champ caché, utilisé seulement par le formulaire */
        }

        .color-select {
            position: relative;
            width: 100%;
            font-family: inherit;
        }

        .color-select-toggle {
            width: 100%;
            padding: 0.55rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 8px;
            border: 2px solid #c28a5b;
            background: #fff;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .color-select-toggle:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(194,138,91,0.25);
        }

        .color-select-label {
            flex: 1;
            text-align: left;
        }

        .color-select-caret {
            font-size: 0.75rem;
        }

        .color-select-options {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 4px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #ddd;
            box-shadow: 0 8px 18px rgba(0,0,0,0.12);
            max-height: 260px;
            overflow-y: auto;
            z-index: 20;
            display: none; /* affichée seulement quand .open est ajouté */
        }

        .color-select.open .color-select-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .color-option {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.6rem;
            cursor: pointer;
            font-size: 0.85rem;
            border-radius: 6px;
            margin: 2px;
        }

        /* Première ligne "Toutes les couleurs" sur toute la largeur */
        .color-option-all {
            grid-column: 1 / -1;
        }

        .color-option:hover {
            background: #f5f2ed;
        }

        .color-option.active {
            background: #f0e0d1;
        }

        .color-circle {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 1px solid rgba(0,0,0,0.18);
            box-shadow:
                inset 0 0 0 1px rgba(255,255,255,0.7),
                0 0 3px rgba(0,0,0,0.25);
        }

        .color-circle.all-colors {
            background: linear-gradient(135deg,#e53935,#fb8c00,#fdd835,#43a047,#1e88e5,#8e24aa);
        }

        /* Couleurs de base (adaptées aux noms possibles) */
        .color-circle-beige { background: #f5f0e6; }
        .color-circle-blanc,
        .color-circle-blanch,
        .color-circle-white { background: #ffffff; }
        .color-circle-noir,
        .color-circle-black { background: #111111; }
        .color-circle-gris,
        .color-circle-gray,
        .color-circle-grey { background: #b0b0b0; }
        .color-circle-rouge,
        .color-circle-red { background: #e53935; }
        .color-circle-bleu,
        .color-circle-blue { background: #1e88e5; }
        .color-circle-vert,
        .color-circle-green { background: #43a047; }
        .color-circle-rose,
        .color-circle-pink { background: #ec407a; }
        .color-circle-jaune,
        .color-circle-yellow { background: #fdd835; }
        .color-circle-orange { background: #fb8c00; }
        .color-circle-marron,
        .color-circle-brown { background: #6d4c41; }
        .color-circle-turquoise { background: #1abc9c; }
        .color-circle-violet,
        .color-circle-purple { background: #8e24aa; }
        @media (max-width: 767px) {
            .types-categories-filter {
                padding: 1.5rem !important;
                margin: 1.5rem 0 !important;
            }
            .types-categories-filter h3 {
                font-size: 1.1rem !important;
                margin-bottom: 1rem !important;
            }
            .types-categories-buttons {
                gap: 0.75rem !important;
            }
            .type-category-btn {
                padding: 0.7rem 1.25rem !important;
                font-size: 0.85rem !important;
                border-radius: 20px !important;
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

    <main class="products-page">
        <div class="container">
            <a href="index.php" class="back-button">Retour à l'accueil</a>
            <!-- Header avec titre et compteur -->
            <div class="products-header">
                <div class="products-header-top">
                    <h1>Nos Produits</h1>
                    <div class="products-count-badge">
                        <span><?php echo $total; ?></span> produit<?php echo $total > 1 ? 's' : ''; ?>
                    </div>
                </div>
                <?php if ($search || $categoryId > 0 || $typeId > 0 || $typeCategoryId > 0 || $colorFilter !== ''): ?>
                    <div class="active-filters">
                        <strong>Filtres actifs :</strong>
                        <?php if ($search): ?>
                            <span class="filter-tag">Recherche: <?php echo clean($search); ?></span>
                        <?php endif; ?>
                        <?php if ($categoryId > 0): ?>
                            <?php 
                            $catName = '';
                            foreach ($categories as $cat) {
                                if ($cat['id'] == $categoryId) {
                                    $catName = $cat['name'];
                                    break;
                                }
                            }
                            ?>
                            <span class="filter-tag">Catégorie: <?php echo clean($catName); ?></span>
                        <?php endif; ?>
                        <?php if ($typeId > 0): ?>
                            <?php 
                            $typeName = '';
                            foreach ($types as $type) {
                                if ($type['id'] == $typeId) {
                                    $typeName = $type['name'];
                                    break;
                                }
                            }
                            ?>
                            <span class="filter-tag">Type: <?php echo clean($typeName); ?></span>
                        <?php endif; ?>
                        <?php if ($colorFilter !== ''): ?>
                            <span class="filter-tag">Couleur: <?php echo clean($colorFilter); ?></span>
                        <?php endif; ?>
                        <?php if ($typeCategoryId > 0): ?>
                            <?php
                            $typeCategoryName = '';
                            if (!empty($typeCategories)) {
                                foreach ($typeCategories as $tc) {
                                    if ($tc['id'] == $typeCategoryId) {
                                        $typeCategoryName = $tc['name'];
                                        break;
                                    }
                                }
                            }
                            // Si pas trouvé (ex: catégorie non choisie), chercher directement en base
                            if ($typeCategoryName === '') {
                                $stmt = $db->prepare("SELECT name FROM types_categorier WHERE id = :id");
                                $stmt->execute([':id' => $typeCategoryId]);
                                $rowTc = $stmt->fetch();
                                if ($rowTc && !empty($rowTc['name'])) {
                                    $typeCategoryName = $rowTc['name'];
                                }
                            }
                            ?>
                            <?php if ($typeCategoryName !== ''): ?>
                                <span class="filter-tag">Type de catégorie: <?php echo clean($typeCategoryName); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="products.php" class="clear-filters">✕ Effacer tous les filtres</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Filtres horizontaux -->
            <div class="filters-section">
                <form method="GET" action="products.php" class="filters-form-horizontal" id="filter-form">
                    <div class="filter-row">
                        <div class="filter-item">
                            <label>🔍 Recherche</label>
                            <input type="text" name="search" id="search-filter" placeholder="Rechercher..." value="<?php echo $search; ?>">
                        </div>

                        <div class="filter-item">
                            <label>🏷️ Type</label>
                            <select name="type" id="type-filter">
                                <option value="">Tous les types</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" <?php echo $typeId == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo clean($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-item">
                            <label>📁 Catégorie</label>
                            <select name="category" id="category-filter">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo clean($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-item">
                            <label>Type de catégorie</label>
                            <select name="type_category" id="type-category-filter" <?php echo $categoryId === 0 ? 'disabled' : ''; ?>>
                                <option value="">
                                    <?php echo $categoryId === 0 ? 'Choisissez une catégorie' : 'Tous'; ?>
                                </option>
                                <?php if ($categoryId > 0): ?>
                                    <?php foreach ($typeCategories as $tc): ?>
                                        <option value="<?php echo (int)$tc['id']; ?>" <?php echo $typeCategoryId == $tc['id'] ? 'selected' : ''; ?>>
                                            <?php echo clean($tc['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="filter-item">
                            <label>🎨 Couleur</label>
                            <?php
                            $currentColorValue = trim($colorFilter);
                            $currentDisplay = 'Toutes les couleurs';
                            $currentClass = 'all-colors';
                            if ($currentColorValue !== '') {
                                $normalizedCurrent = mb_strtolower($currentColorValue, 'UTF-8');
                                if (isset($availableColors[$normalizedCurrent])) {
                                    $currentDisplay = mb_convert_case(trim($availableColors[$normalizedCurrent]), MB_CASE_TITLE, 'UTF-8');
                                    $currentClass = preg_replace('/[^a-z0-9]+/i', '-', $normalizedCurrent);
                                } else {
                                    $currentDisplay = mb_convert_case(trim($currentColorValue), MB_CASE_TITLE, 'UTF-8');
                                    $currentClass = preg_replace('/[^a-z0-9]+/i', '-', $normalizedCurrent);
                                }
                            }
                            ?>
                            <input type="hidden" name="color" id="color-filter" value="<?php echo htmlspecialchars($currentColorValue, ENT_QUOTES, 'UTF-8'); ?>">

                            <div class="color-select" id="color-select">
                                <button type="button" class="color-select-toggle" id="color-select-toggle">
                                    <span class="color-circle <?php echo $currentColorValue === '' ? 'all-colors' : 'color-circle-' . $currentClass; ?>"></span>
                                    <span class="color-select-label"><?php echo htmlspecialchars($currentDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="color-select-caret">▼</span>
                                </button>

                                <div class="color-select-options" id="color-select-options">
                                    <div class="color-option color-option-all<?php echo $currentColorValue === '' ? ' active' : ''; ?>"
                                         data-value=""
                                         data-class="all-colors">
                                        <span class="color-circle all-colors"></span>
                                        <span class="color-option-label">Toutes les couleurs</span>
                                    </div>
                                    <?php foreach ($availableColors as $key => $colorName): 
                                        $normalized = mb_strtolower(trim($colorName), 'UTF-8');
                                        $classSlug = preg_replace('/[^a-z0-9]+/i', '-', $normalized);
                                        $displayName = mb_convert_case(trim($colorName), MB_CASE_TITLE, 'UTF-8');
                                        $isActive = $currentColorValue !== '' && mb_strtolower($currentColorValue, 'UTF-8') === $normalized;
                                    ?>
                                        <div class="color-option<?php echo $isActive ? ' active' : ''; ?>"
                                             data-value="<?php echo htmlspecialchars($colorName, ENT_QUOTES, 'UTF-8'); ?>"
                                             data-class="<?php echo 'color-circle-' . $classSlug; ?>">
                                            <span class="color-circle <?php echo 'color-circle-' . $classSlug; ?>"></span>
                                            <span class="color-option-label"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="filter-actions">
                            <a href="products.php" class="btn btn-secondary">Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Boutons de filtrage par types de catégories supprimés (sélection via le formulaire horizontal) -->

            <!-- Liste des produits -->
            <div class="products-content">

                    <?php if (count($products) > 0): ?>
                        <div class="products-grid">
                            <?php foreach ($products as $product): ?>
                                <div class="product-card">
                                    <a href="product.php?id=<?php echo $product['id']; ?>">
                                        <div class="product-image">
                                            <?php if ($product['image']): ?>
                                                <img src="<?php echo clean($product['image']); ?>" alt="<?php echo clean($product['name']); ?>">
                                            <?php else: ?>
                                                <div class="placeholder-image">Image</div>
                                            <?php endif; ?>
                                            <?php if ($product['sale_price'] || $product['best_seller']): ?>
                                                <div class="badges-container">
                                                    <?php if ($product['sale_price']): ?>
                                                        <span class="badge sale">Promotion</span>
                                                    <?php endif; ?>
                                                    <?php if ($product['best_seller']): ?>
                                                        <span class="badge best-seller">Bestseller</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-info">
                                            <h2><?php echo clean($product['name']); ?></h2>
                                            <p class="product-category">
                                                <?php echo clean($product['category_name']); ?>
                                                <?php if (!empty($product['type_name'])): ?>
                                                    <span style="color: var(--primary-color); font-weight: 600;"> → <?php echo clean($product['type_name']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                            <div class="product-price">
                                                <?php if ($product['sale_price']): ?>
                                                    <span class="old-price"><?php echo formatPrice($product['price']); ?></span>
                                                    <span class="current-price"><?php echo formatPrice($product['sale_price']); ?></span>
                                                <?php else: ?>
                                                    <span class="current-price"><?php echo formatPrice($product['price']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                    <?php 
                                    // Décoder les couleurs du produit
                                    $productColors = [];
                                    $hasMultipleColors = false;
                                    if (!empty($product['color'])) {
                                        $colorsData = json_decode($product['color'], true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($colorsData) && count($colorsData) > 0) {
                                            $productColors = $colorsData;
                                            $hasMultipleColors = count($colorsData) > 1;
                                        } elseif (!empty(trim($product['color']))) {
                                            // Format simple (une seule couleur)
                                            $productColors = [['name' => trim($product['color']), 'image' => '']];
                                        }
                                    }
                                    $productColorsJson = htmlspecialchars(json_encode($productColors), ENT_QUOTES, 'UTF-8');

                                    // Dimensions max pour les types sur mesure (size stocké comme "LxH" en cm)
                                    $maxWidthCm = '';
                                    $maxHeightCm = '';
                                    if (!empty($product['type_name'])) {
                                        $typeNameLower = strtolower(trim($product['type_name']));
                                        if (($typeNameLower === 'sur_mesure' || $typeNameLower === 'sur mesure') && !empty($product['size']) && strpos($product['size'], 'x') !== false) {
                                            $parts = explode('x', strtolower($product['size']));
                                            $maxWidthCm = isset($parts[0]) ? trim($parts[0]) : '';
                                            $maxHeightCm = isset($parts[1]) ? trim($parts[1]) : '';
                                        }
                                    }
                                    ?>
                                    <button class="btn-add-cart" 
                                            type="button"
                                            data-product-id="<?php echo $product['id']; ?>"
                                            data-type-category="<?php echo !empty($product['type_name']) ? strtolower(trim($product['type_name'])) : ''; ?>"
                                            data-unit-price="<?php echo $product['unit_price']; ?>"
                                            data-product-colors="<?php echo $productColorsJson; ?>"
                                            data-max-width="<?php echo htmlspecialchars($maxWidthCm, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-max-height="<?php echo htmlspecialchars($maxHeightCm, ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="window.handleAddToCartClickFromList(event, this)">
                                        Ajouter au panier
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-products">
                            <p>Aucun produit trouvé.</p>
                        </div>
                    <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <!-- Modal pour les dimensions -->
    <div id="dimensions-modal" class="dimensions-modal" style="display: none;">
        <div class="modal-overlay" onclick="window.closeDimensionsModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2>Spécifier les dimensions</h2>
                <button class="modal-close" onclick="window.closeDimensionsModal()">×</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 1.5rem; color: var(--text-light);">
                    Veuillez entrer les dimensions de votre tapis pour calculer le prix exact.
                    <br>
                    <span id="modal-max-dimensions-info" style="font-size: 0.9rem; color: var(--text-light); display: none;">
                        <!-- Rempli dynamiquement pour les produits sur mesure -->
                    </span>
                </p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="dimension-input">
                        <label for="modal-length" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Longueur (cm) *</label>
                        <input type="number" 
                               id="modal-length" 
                               step="1" 
                               min="1" 
                               placeholder="0" 
                               style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-color); border-radius: 8px; font-size: 1rem; transition: border-color 0.3s;"
                               oninput="window.calculateModalPrice()">
                    </div>
                    <div class="dimension-input">
                        <label for="modal-width" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Largeur (cm) *</label>
                        <input type="number" 
                               id="modal-width" 
                               step="1" 
                               min="1" 
                               placeholder="0" 
                               style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-color); border-radius: 8px; font-size: 1rem; transition: border-color 0.3s;"
                               oninput="window.calculateModalPrice()">
                    </div>
                </div>
                <div id="modal-price-calculation" style="display: none; padding: 1rem; background: var(--light-color); border-radius: 8px; border: 2px solid var(--primary-color); margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="color: var(--text-light);">Dimensions:</span>
                        <strong id="modal-dimensions-display" style="color: var(--text-dark); font-size: 0.95rem;">0 cm × 0 cm</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="color: var(--text-light);">Surface:</span>
                        <strong id="modal-surface-area" style="color: var(--text-dark); font-size: 1.1rem;">0,00 m²</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="color: var(--text-light);">Prix unitaire:</span>
                        <strong id="modal-unit-price" style="color: var(--text-dark);">0,00 MAD / m²</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.75rem; border-top: 2px solid var(--border-color);">
                        <span style="font-size: 1.1rem; font-weight: 700; color: var(--primary-color);">Prix total:</span>
                        <strong id="modal-total-price" style="font-size: 1.5rem; color: var(--primary-color); font-weight: 700;">0,00 MAD</strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="window.closeDimensionsModal()">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirm-add-to-cart" disabled>
                    Ajouter au panier
                </button>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script>
        console.log('=== DEBUT DU SCRIPT PRODUCTS.PHP ===');
        
        // Variables globales
        window.currentProductId = null;
        window.currentUnitPrice = null;
        window.currentProductColors = [];
        window.currentMaxWidthCm = null;
        window.currentMaxHeightCm = null;
        window.currentIsSurMesure = false;
        
        // Fonction pour sélectionner une couleur dans la modal
        window.selectModalColor = function(element, colorName) {
            // Mettre à jour l'apparence des options de couleur
            document.querySelectorAll('#modal-colors-list .modal-color-option').forEach(opt => {
                opt.style.borderColor = 'var(--border-color)';
                opt.style.background = 'var(--light-color)';
            });
            element.style.borderColor = 'var(--primary-color)';
            element.style.background = 'rgba(139,69,19,0.1)';
            
            // Mettre à jour le champ caché
            const selectedColorInput = document.getElementById('modal-selected-color');
            if (selectedColorInput) {
                selectedColorInput.value = colorName;
            }
        };
        
        // Fonctions du modal (globales)
        window.openDimensionsModal = function(productId, unitPrice, productColors, maxWidthCm, maxHeightCm, isSurMesure) {
            const modal = document.getElementById('dimensions-modal');
            if (!modal) {
                console.error('Modal dimensions-modal introuvable!');
                alert('Erreur: Le formulaire de dimensions est introuvable');
                return;
            }
            
            // Stocker les informations du produit
            window.currentProductId = productId;
            window.currentUnitPrice = unitPrice;
            window.currentProductColors = productColors || [];
            window.currentMaxWidthCm = maxWidthCm ? parseFloat(maxWidthCm) : null;
            window.currentMaxHeightCm = maxHeightCm ? parseFloat(maxHeightCm) : null;
            window.currentIsSurMesure = !!isSurMesure;
            
            // Mettre à jour le prix unitaire affiché
            const unitPriceElement = document.getElementById('modal-unit-price');
            if (unitPriceElement) {
                unitPriceElement.textContent = formatPrice(unitPrice) + ' / m²';
            }

            // Mettre à jour le texte des dimensions max pour les produits sur mesure
            const maxInfo = document.getElementById('modal-max-dimensions-info');
            if (maxInfo) {
                if (window.currentIsSurMesure && (window.currentMaxWidthCm || window.currentMaxHeightCm)) {
                    const parts = [];
                    if (window.currentMaxWidthCm) parts.push(window.currentMaxWidthCm + ' cm');
                    if (window.currentMaxHeightCm) parts.push(window.currentMaxHeightCm + ' cm');
                    maxInfo.textContent = 'Dimensions maximales pour ce modèle : ' +
                        (window.currentMaxWidthCm && window.currentMaxHeightCm
                            ? window.currentMaxWidthCm + ' cm × ' + window.currentMaxHeightCm + ' cm'
                            : parts.join(' × '));
                    maxInfo.style.display = 'inline';
                } else {
                    maxInfo.textContent = '';
                    maxInfo.style.display = 'none';
                }
            }
            
            // Gérer l'affichage du sélecteur de couleur
            const colorSelector = document.getElementById('modal-color-selector');
            const colorsList = document.getElementById('modal-colors-list');
            const selectedColorInput = document.getElementById('modal-selected-color');
            
            if (window.currentProductColors && window.currentProductColors.length > 0) {
                // Afficher le sélecteur de couleur
                if (colorSelector) colorSelector.style.display = 'block';
                
                // Générer les options de couleur
                if (colorsList) {
                    colorsList.innerHTML = '';
                    window.currentProductColors.forEach((colorItem, index) => {
                        const colorName = colorItem.name || '';
                        const colorImage = colorItem.image || '';
                        const isFirst = index === 0;
                        
                        const colorOption = document.createElement('div');
                        colorOption.className = 'modal-color-option';
                        colorOption.style.cssText = 'cursor: pointer; padding: 0.5rem 1rem; border: 2px solid var(--border-color); border-radius: 8px; background: var(--light-color); transition: all 0.3s; ' + (isFirst ? 'border-color: var(--primary-color); background: rgba(139,69,19,0.1);' : '');
                        colorOption.setAttribute('data-color-name', colorName);
                        colorOption.onclick = function() { window.selectModalColor(this, colorName); };
                        
                        if (colorImage) {
                            const img = document.createElement('img');
                            img.src = colorImage;
                            img.alt = colorName;
                            img.style.cssText = 'width: 40px; height: 40px; object-fit: cover; border-radius: 6px; margin-right: 0.5rem; display: inline-block; vertical-align: middle;';
                            img.onerror = function() { this.style.display = 'none'; };
                            colorOption.appendChild(img);
                        }
                        
                        const span = document.createElement('span');
                        span.style.cssText = 'font-weight: 600; color: var(--text-dark);';
                        span.textContent = colorName;
                        colorOption.appendChild(span);
                        
                        colorsList.appendChild(colorOption);
                    });
                    
                    // Sélectionner la première couleur par défaut
                    if (window.currentProductColors.length > 0 && selectedColorInput) {
                        selectedColorInput.value = window.currentProductColors[0].name || '';
                    }
                }
            } else {
                // Masquer le sélecteur de couleur
                if (colorSelector) colorSelector.style.display = 'none';
                if (selectedColorInput) selectedColorInput.value = '';
            }
            
            console.log('Ouverture du modal pour produit:', productId, 'prix unitaire:', unitPrice, 'couleurs:', window.currentProductColors);
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Réinitialiser les champs
            const lengthInput = document.getElementById('modal-length');
            const widthInput = document.getElementById('modal-width');
            const priceCalc = document.getElementById('modal-price-calculation');
            const confirmBtn = document.getElementById('confirm-add-to-cart');
            
            if (lengthInput) lengthInput.value = '';
            if (widthInput) widthInput.value = '';
            if (priceCalc) priceCalc.style.display = 'none';
            if (confirmBtn) confirmBtn.disabled = true;
        };
        
        window.closeDimensionsModal = function() {
            const modal = document.getElementById('dimensions-modal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        };
        
        window.calculateModalPrice = function() {
            const length = parseFloat(document.getElementById('modal-length').value) || 0;
            const width = parseFloat(document.getElementById('modal-width').value) || 0;
            const priceCalculation = document.getElementById('modal-price-calculation');
            const dimensionsDisplay = document.getElementById('modal-dimensions-display');
            const surfaceArea = document.getElementById('modal-surface-area');
            const totalPrice = document.getElementById('modal-total-price');
            const confirmBtn = document.getElementById('confirm-add-to-cart');
            
            if (length > 0 && width > 0 && window.currentUnitPrice) {
                // Pour les produits sur mesure, vérifier que les dimensions ne dépassent pas les max
                if (window.currentIsSurMesure && (window.currentMaxWidthCm || window.currentMaxHeightCm)) {
                    if (window.currentMaxWidthCm && length > window.currentMaxWidthCm) {
                        if (typeof showNotification === 'function') {
                            showNotification('La longueur maximale pour ce modèle est de ' + window.currentMaxWidthCm + ' cm.', 'error');
                        } else {
                            alert('La longueur maximale pour ce modèle est de ' + window.currentMaxWidthCm + ' cm.');
                        }
                        return;
                    }
                    if (window.currentMaxHeightCm && width > window.currentMaxHeightCm) {
                        if (typeof showNotification === 'function') {
                            showNotification('La largeur maximale pour ce modèle est de ' + window.currentMaxHeightCm + ' cm.', 'error');
                        } else {
                            alert('La largeur maximale pour ce modèle est de ' + window.currentMaxHeightCm + ' cm.');
                        }
                        return;
                    }
                }
                // Convertir cm² en m² (1 m² = 10 000 cm²)
                const surfaceCm2 = length * width;
                const surfaceM2 = surfaceCm2 / 10000;
                const total = surfaceM2 * window.currentUnitPrice;
                
                // Afficher les dimensions
                dimensionsDisplay.textContent = Math.round(length) + ' cm × ' + Math.round(width) + ' cm';
                
                // Afficher la surface avec 2 décimales et format français
                const surfaceFormatted = surfaceM2.toFixed(2).replace('.', ',');
                surfaceArea.textContent = surfaceFormatted + ' m²';
                
                totalPrice.textContent = formatPrice(total);
                priceCalculation.style.display = 'block';
                confirmBtn.disabled = false;
            } else {
                priceCalculation.style.display = 'none';
                confirmBtn.disabled = true;
            }
        };
        
        function formatPrice(price) {
            // Format: 1 234,56 MAD (comme la fonction PHP)
            return price.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' MAD';
        }
        
        // Fonction pour gérer le clic sur "Ajouter au panier" depuis la liste
        window.handleAddToCartClickFromList = function(e, button) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            const productId = button.getAttribute('data-product-id');
            const typeCategory = button.getAttribute('data-type-category') || '';
            const unitPrice = parseFloat(button.getAttribute('data-unit-price')) || 0;
            const productColorsJson = button.getAttribute('data-product-colors') || '[]';
            const maxWidthCm = button.getAttribute('data-max-width') || '';
            const maxHeightCm = button.getAttribute('data-max-height') || '';
            
            let productColors = [];
            try {
                productColors = JSON.parse(productColorsJson);
            } catch (e) {
                console.error('Erreur lors du parsing des couleurs:', e);
            }
            
            console.log('=== handleAddToCartClickFromList APPELÉ ===');
            console.log('productId:', productId);
            console.log('typeCategory:', typeCategory);
            console.log('unitPrice:', unitPrice);
            console.log('productColors:', productColors);
            console.log('maxWidthCm:', maxWidthCm, 'maxHeightCm:', maxHeightCm);
            
            // Vérifier les types sans dimensions : authentique / fixe
            const lowerType = typeCategory.toLowerCase();
            const isAuthentique = lowerType === 'authentique' || lowerType === 'authentic';
            const isFixe = lowerType === 'fixe' || lowerType === 'fix';
            const isSurMesure = lowerType === 'sur_mesure' || lowerType === 'sur mesure';
            
            if (isAuthentique || isFixe) {
                console.log('→ Ajout direct (type sans dimensions)');
                // Ajouter directement au panier
                addToCartDirectly(productId, productColors.length > 0 ? productColors[0].name : '');
            } else {
                console.log('→ Ouverture du modal');
                // Ouvrir le modal pour les dimensions
                window.openDimensionsModal(productId, unitPrice, productColors, maxWidthCm, maxHeightCm, isSurMesure);
            }
            return false;
        };
        
        // Fonction pour ajouter directement au panier (sans dimensions)
        function addToCartDirectly(productId, color) {
            if (!productId) return;
            
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', 1);
            if (color) {
                formData.append('color', color);
            }
            
            fetch('api/add_to_cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof showNotification === 'function') {
                        showNotification(data.message, 'success');
                    }
                    if (typeof updateCartCount === 'function') {
                        updateCartCount(data.cart_count);
                    }
                } else {
                    if (typeof showNotification === 'function') {
                        showNotification(data.message || 'Erreur lors de l\'ajout au panier', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof showNotification === 'function') {
                    showNotification('Erreur lors de l\'ajout au panier', 'error');
                }
            });
        }
        
        // Gérer le clic sur "Ajouter au panier" dans le modal
        document.addEventListener('DOMContentLoaded', function() {
            const confirmBtn = document.getElementById('confirm-add-to-cart');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function() {
                    const length = parseFloat(document.getElementById('modal-length').value) || 0;
                    const width = parseFloat(document.getElementById('modal-width').value) || 0;
                    const productId = window.currentProductId;
                    const unitPrice = window.currentUnitPrice;
                    const selectedColorInput = document.getElementById('modal-selected-color');
                    const selectedColor = selectedColorInput ? selectedColorInput.value : '';
                    
                    // Vérifier si une couleur est requise
                    const colorSelector = document.getElementById('modal-color-selector');
                    if (colorSelector && colorSelector.style.display !== 'none' && !selectedColor) {
                        if (typeof showNotification === 'function') {
                            showNotification('Veuillez sélectionner une couleur', 'error');
                        } else {
                            alert('Veuillez sélectionner une couleur');
                        }
                        return;
                    }
                    
                    if (length <= 0 || width <= 0) {
                        if (typeof showNotification === 'function') {
                            showNotification('Veuillez entrer des dimensions valides', 'error');
                        } else {
                            alert('Veuillez entrer des dimensions valides');
                        }
                        return;
                    }

                    // Vérifier les dimensions max pour les produits sur mesure
                    if (window.currentIsSurMesure && (window.currentMaxWidthCm || window.currentMaxHeightCm)) {
                        if (window.currentMaxWidthCm && length > window.currentMaxWidthCm) {
                            if (typeof showNotification === 'function') {
                                showNotification('La longueur maximale pour ce modèle est de ' + window.currentMaxWidthCm + ' cm.', 'error');
                            } else {
                                alert('La longueur maximale pour ce modèle est de ' + window.currentMaxWidthCm + ' cm.');
                            }
                            return;
                        }
                        if (window.currentMaxHeightCm && width > window.currentMaxHeightCm) {
                            if (typeof showNotification === 'function') {
                                showNotification('La largeur maximale pour ce modèle est de ' + window.currentMaxHeightCm + ' cm.', 'error');
                            } else {
                                alert('La largeur maximale pour ce modèle est de ' + window.currentMaxHeightCm + ' cm.');
                            }
                            return;
                        }
                    }
                    
                    if (!productId || !unitPrice) {
                        console.error('ProductId ou unitPrice manquant!');
                        return;
                    }
                    
                    // Calculer le prix
                    const surfaceM2 = (length * width) / 10000;
                    const calculatedPrice = surfaceM2 * unitPrice;
                    
                    const btn = this;
                    btn.disabled = true;
                    btn.textContent = 'Ajout en cours...';
                    
                    // selectedColorInput et selectedColor sont déjà déclarés plus haut
                    
                    const formData = new FormData();
                    formData.append('product_id', productId);
                    formData.append('quantity', 1);
                    formData.append('length', length);
                    formData.append('width', width);
                    formData.append('calculated_price', calculatedPrice);
                    if (selectedColor) {
                        formData.append('color', selectedColor);
                    }
                    
                    fetch('api/add_to_cart.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (typeof showNotification === 'function') {
                                showNotification(data.message, 'success');
                            }
                            if (typeof updateCartCount === 'function') {
                                updateCartCount(data.cart_count);
                            }
                            window.closeDimensionsModal();
                        } else {
                            if (typeof showNotification === 'function') {
                                showNotification(data.message || 'Erreur lors de l\'ajout au panier', 'error');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        if (typeof showNotification === 'function') {
                            showNotification('Erreur lors de l\'ajout au panier', 'error');
                        }
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.textContent = 'Ajouter au panier';
                    });
                });
            }
        });
        
        // Fermer le modal avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.closeDimensionsModal();
            }
        });
        
        // Fonction pour appliquer automatiquement les filtres
        function applyFilters() {
            const form = document.getElementById('filter-form');
            if (form) {
                const formData = new FormData(form);
                const params = new URLSearchParams();
                
                // Ajouter tous les paramètres du formulaire
                for (const [key, value] of formData.entries()) {
                    if (value) {
                        params.append(key, value);
                    }
                }
                
                // Construire l'URL
                const url = 'products.php' + (params.toString() ? '?' + params.toString() : '');
                window.location.href = url;
            }
        }
        
        // Application automatique des filtres
        // Filtre par type -> met à jour les catégories puis applique
        const typeFilter = document.getElementById('type-filter');
        const categoryFilter = document.getElementById('category-filter');

        function loadCategoriesByType(typeId, selectedCategoryId = '') {
            if (!categoryFilter) return;
            categoryFilter.disabled = true;
            categoryFilter.innerHTML = '<option value=\"\">Chargement...</option>';

            const url = 'api/get_categories_by_type.php' + (typeId ? ('?type_id=' + typeId) : '');
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    categoryFilter.innerHTML = '<option value=\"\">Toutes les catégories</option>';
                    if (data.categories && Array.isArray(data.categories)) {
                        data.categories.forEach(cat => {
                            const opt = document.createElement('option');
                            opt.value = cat.id;
                            opt.textContent = cat.name;
                            if (selectedCategoryId && selectedCategoryId == cat.id) {
                                opt.selected = true;
                            }
                            categoryFilter.appendChild(opt);
                        });
                    }
                    categoryFilter.disabled = false;
                })
                .catch(() => {
                    categoryFilter.innerHTML = '<option value=\"\">Erreur de chargement</option>';
                    categoryFilter.disabled = true;
                });
        }

        if (typeFilter) {
            typeFilter.addEventListener('change', function() {
                const typeId = this.value;
                if (categoryFilter) {
                    categoryFilter.value = '';
                }
                loadCategoriesByType(typeId);
                applyFilters();
            });
        }

        // Filtre par catégorie - application immédiate
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                // Lorsqu'on change de catégorie, réinitialiser le type de catégorie
                const typeCategorySelect = document.getElementById('type-category-filter');
                if (typeCategorySelect) {
                    typeCategorySelect.value = '';
                }
                applyFilters();
            });
        }

        // Filtre par type de catégorie - application immédiate
        const typeCategoryFilter = document.getElementById('type-category-filter');
        if (typeCategoryFilter) {
            typeCategoryFilter.addEventListener('change', function() {
                applyFilters();
            });
        }
        
        const colorHiddenInput = document.getElementById('color-filter');

        // Select couleur personnalisé
        const colorSelectWrapper = document.getElementById('color-select');
        const colorSelectToggle = document.getElementById('color-select-toggle');
        const colorSelectOptions = document.getElementById('color-select-options');

        if (colorSelectWrapper && colorSelectToggle && colorSelectOptions && colorHiddenInput) {
            const labelSpan = colorSelectToggle.querySelector('.color-select-label');
            const mainCircle = colorSelectToggle.querySelector('.color-circle');
            const optionNodes = colorSelectOptions.querySelectorAll('.color-option');

            // Ouvrir / fermer la liste
            colorSelectToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                colorSelectWrapper.classList.toggle('open');
            });

            // Sélection d'une couleur
            optionNodes.forEach(opt => {
                opt.addEventListener('click', function () {
                    const value = this.getAttribute('data-value') || '';
                    const cls = this.getAttribute('data-class') || 'all-colors';
                    const text = this.querySelector('.color-option-label').textContent.trim();

                    // Mettre à jour le champ caché (utilisé par le formulaire PHP)
                    colorHiddenInput.value = value;

                    // Mettre à jour l'affichage principal
                    labelSpan.textContent = text;
                    mainCircle.className = 'color-circle ' + cls;

                    // Mettre à jour l'état actif
                    optionNodes.forEach(o => o.classList.remove('active'));
                    this.classList.add('active');

                    // Fermer la liste et appliquer les filtres
                    colorSelectWrapper.classList.remove('open');
                    applyFilters();
                });
            });

            // Fermer si clic à l'extérieur
            document.addEventListener('click', function () {
                colorSelectWrapper.classList.remove('open');
            });
        }
        
        // Filtre par recherche - application avec délai (debounce) pour éviter trop de requêtes
        const searchFilter = document.getElementById('search-filter');
        let searchTimeout = null;
        
        if (searchFilter) {
            searchFilter.addEventListener('input', function() {
                // Annuler le timeout précédent
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                // Créer un nouveau timeout pour appliquer le filtre après 500ms d'inactivité
                searchTimeout = setTimeout(function() {
                    applyFilters();
                }, 500);
            });
        }
        
        console.log('=== FIN DU SCRIPT PRODUCTS.PHP ===');
    </script>
</body>
</html>


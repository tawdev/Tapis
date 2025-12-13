<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

// Paramètres de filtrage
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$typeCategoryId = isset($_GET['type_category']) ? (int)$_GET['type_category'] : 0;
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$sort = isset($_GET['sort']) ? clean($_GET['sort']) : 'newest';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// Construction de la requête
$where = ["p.status = 'active'"];
$params = [];

if ($categoryId > 0) {
    $where[] = "p.category_id = :category_id";
    $params[':category_id'] = $categoryId;
}

if ($typeCategoryId > 0) {
    $where[] = "p.type_category_id = :type_category_id";
    $params[':type_category_id'] = $typeCategoryId;
}

if ($minPrice > 0) {
    $where[] = "COALESCE(p.sale_price, p.price) >= :min_price";
    $params[':min_price'] = $minPrice;
}

if ($maxPrice > 0) {
    $where[] = "COALESCE(p.sale_price, p.price) <= :max_price";
    $params[':max_price'] = $maxPrice;
}

if ($search) {
    $where[] = "(p.name LIKE :search_name OR p.description LIKE :search_desc)";
    $params[':search_name'] = "%$search%";
    $params[':search_desc'] = "%$search%";
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
$sql = "SELECT p.*, c.name as category_name, tc.name as type_category_name,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
        COALESCE(p.sale_price, p.price) as unit_price
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN types_categories tc ON p.type_category_id = tc.id 
        WHERE $whereClause 
        ORDER BY $orderBy";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$products = $stmt->fetchAll();

// Récupérer les catégories
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Récupérer les types de catégories selon la catégorie sélectionnée
$typesCategories = [];
if ($categoryId > 0) {
    $stmt = $db->prepare("SELECT * FROM types_categories WHERE category_id = :category_id ORDER BY name");
    $stmt->execute([':category_id' => $categoryId]);
    $typesCategories = $stmt->fetchAll();
}
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
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="products-page">
        <div class="container">
            <!-- Header avec titre et compteur -->
            <div class="products-header">
                <div class="products-header-top">
                    <h1>Nos Produits</h1>
                    <div class="products-count-badge">
                        <span><?php echo $total; ?></span> produit<?php echo $total > 1 ? 's' : ''; ?>
                    </div>
                </div>
                <?php if ($search || $categoryId > 0 || $typeCategoryId > 0 || $minPrice > 0 || $maxPrice > 0): ?>
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
                        <?php if ($typeCategoryId > 0): ?>
                            <?php 
                            $typeCatName = '';
                            foreach ($typesCategories as $typeCat) {
                                if ($typeCat['id'] == $typeCategoryId) {
                                    $typeCatName = $typeCat['name'];
                                    break;
                                }
                            }
                            ?>
                            <span class="filter-tag">Type: <?php echo clean($typeCatName); ?></span>
                        <?php endif; ?>
                        <?php if ($minPrice > 0 || $maxPrice > 0): ?>
                            <span class="filter-tag">Prix: <?php echo $minPrice > 0 ? formatPrice($minPrice) : '0'; ?> - <?php echo $maxPrice > 0 ? formatPrice($maxPrice) : '∞'; ?></span>
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
                            <label>💰 Prix</label>
                            <div class="price-range">
                                <input type="number" name="min_price" id="min-price-filter" placeholder="Min" value="<?php echo $minPrice ?: ''; ?>" min="0">
                                <span>-</span>
                                <input type="number" name="max_price" id="max-price-filter" placeholder="Max" value="<?php echo $maxPrice ?: ''; ?>" min="0">
                            </div>
                        </div>

                        <div class="filter-actions">
                            <a href="products.php" class="btn btn-secondary">Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Boutons de filtrage par types de catégories -->
            <?php if ($categoryId > 0 && count($typesCategories) > 0): ?>
                <div class="types-categories-filter" style="margin: 2rem 0; padding: 2rem; background: linear-gradient(135deg, #FFFFFF 0%, #FAFAFA 100%); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-top: 4px solid #8B4513; border-left: 4px solid #8B4513; display: block; width: 100%;">
                    <h3 style="margin-bottom: 1.5rem; color: #8B4513; font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; margin-top: 0;">
                        <span style="font-size: 1.5rem;">🏷️</span> Filtrer par type de catégorie :
                    </h3>
                    <div class="types-categories-buttons" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; width: 100%;">
                        <a href="products.php?category=<?php echo $categoryId; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $minPrice > 0 ? '&min_price=' . $minPrice : ''; ?><?php echo $maxPrice > 0 ? '&max_price=' . $maxPrice : ''; ?>" 
                           class="type-category-btn <?php echo $typeCategoryId == 0 ? 'active' : ''; ?>"
                           style="padding: 0.85rem 1.75rem; background: <?php echo $typeCategoryId == 0 ? 'linear-gradient(135deg, #8B4513 0%, #2C1810 100%)' : '#FFFFFF'; ?>; color: <?php echo $typeCategoryId == 0 ? '#FFFFFF' : '#333333'; ?>; border-radius: 25px; text-decoration: none; font-weight: <?php echo $typeCategoryId == 0 ? '700' : '600'; ?>; font-size: 0.95rem; transition: all 0.3s ease; border: 2px solid <?php echo $typeCategoryId == 0 ? '#8B4513' : '#E0E0E0'; ?>; display: inline-flex; align-items: center; justify-content: center; box-shadow: <?php echo $typeCategoryId == 0 ? '0 6px 20px rgba(139,69,19,0.4)' : '0 2px 5px rgba(0,0,0,0.05)'; ?>; position: relative; overflow: hidden; cursor: pointer; transform: <?php echo $typeCategoryId == 0 ? 'translateY(-2px)' : 'none'; ?>;">
                            Tous les types<?php echo $typeCategoryId == 0 ? ' ✓' : ''; ?>
                        </a>
                        <?php foreach ($typesCategories as $typeCat): ?>
                            <a href="products.php?category=<?php echo $categoryId; ?>&type_category=<?php echo $typeCat['id']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $minPrice > 0 ? '&min_price=' . $minPrice : ''; ?><?php echo $maxPrice > 0 ? '&max_price=' . $maxPrice : ''; ?>" 
                               class="type-category-btn <?php echo $typeCategoryId == $typeCat['id'] ? 'active' : ''; ?>"
                               style="padding: 0.85rem 1.75rem; background: <?php echo $typeCategoryId == $typeCat['id'] ? 'linear-gradient(135deg, #8B4513 0%, #2C1810 100%)' : '#FFFFFF'; ?>; color: <?php echo $typeCategoryId == $typeCat['id'] ? '#FFFFFF' : '#333333'; ?>; border-radius: 25px; text-decoration: none; font-weight: <?php echo $typeCategoryId == $typeCat['id'] ? '700' : '600'; ?>; font-size: 0.95rem; transition: all 0.3s ease; border: 2px solid <?php echo $typeCategoryId == $typeCat['id'] ? '#8B4513' : '#E0E0E0'; ?>; display: inline-flex; align-items: center; justify-content: center; box-shadow: <?php echo $typeCategoryId == $typeCat['id'] ? '0 6px 20px rgba(139,69,19,0.4)' : '0 2px 5px rgba(0,0,0,0.05)'; ?>; position: relative; overflow: hidden; cursor: pointer; transform: <?php echo $typeCategoryId == $typeCat['id'] ? 'translateY(-2px)' : 'none'; ?>;">
                                <?php echo clean($typeCat['name']); ?><?php echo $typeCategoryId == $typeCat['id'] ? ' ✓' : ''; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

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
                                                <?php if (!empty($product['type_category_name'])): ?>
                                                    <span style="color: var(--primary-color); font-weight: 600;"> → <?php echo clean($product['type_category_name']); ?></span>
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
                                    ?>
                                    <button class="btn-add-cart" 
                                            type="button"
                                            data-product-id="<?php echo $product['id']; ?>"
                                            data-type-category="<?php echo !empty($product['type_category_name']) ? strtolower(trim($product['type_category_name'])) : ''; ?>"
                                            data-unit-price="<?php echo $product['unit_price']; ?>"
                                            data-product-colors="<?php echo $productColorsJson; ?>"
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
                <!-- Sélecteur de couleur (si le produit a des couleurs) -->
                <div id="modal-color-selector" style="display: none; margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text-dark);">
                        🎨 Choisir une couleur *
                    </label>
                    <div id="modal-colors-list" style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <!-- Les options de couleur seront générées dynamiquement par JavaScript -->
                    </div>
                    <input type="hidden" id="modal-selected-color" name="selected_color" value="">
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
        window.openDimensionsModal = function(productId, unitPrice, productColors) {
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
            
            // Mettre à jour le prix unitaire affiché
            const unitPriceElement = document.getElementById('modal-unit-price');
            if (unitPriceElement) {
                unitPriceElement.textContent = formatPrice(unitPrice) + ' / m²';
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
            
            // Vérifier si le type est "authentique"
            const isAuthentique = typeCategory.toLowerCase() === 'authentique' || typeCategory.toLowerCase() === 'authentic';
            
            if (isAuthentique) {
                console.log('→ Ajout direct (authentique)');
                // Ajouter directement au panier
                addToCartDirectly(productId, productColors.length > 0 ? productColors[0].name : '');
            } else {
                console.log('→ Ouverture du modal');
                // Ouvrir le modal pour les dimensions
                window.openDimensionsModal(productId, unitPrice, productColors);
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
        // Filtre par catégorie - application immédiate
        const categoryFilter = document.getElementById('category-filter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                applyFilters();
            });
        }
        
        // Filtre par prix - application immédiate
        const minPriceFilter = document.getElementById('min-price-filter');
        const maxPriceFilter = document.getElementById('max-price-filter');
        
        if (minPriceFilter) {
            minPriceFilter.addEventListener('change', function() {
                applyFilters();
            });
        }
        
        if (maxPriceFilter) {
            maxPriceFilter.addEventListener('change', function() {
                applyFilters();
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


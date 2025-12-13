<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId == 0) {
    redirect('products.php');
}

// Récupérer le produit avec le type de catégorie
$stmt = $db->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug, tc.name as type_category_name
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      LEFT JOIN types_categories tc ON p.type_category_id = tc.id
                      WHERE p.id = :id AND p.status = 'active'");
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

// Vérifier si le type de catégorie est "authentique" (insensible à la casse)
$showDimensionsCalculator = true;
if (!empty($product['type_category_name'])) {
    $typeCategoryName = strtolower(trim($product['type_category_name']));
    if ($typeCategoryName === 'authentique' || $typeCategoryName === 'authentic') {
        $showDimensionsCalculator = false;
    }
}

if (!$product) {
    redirect('products.php');
}

// Récupérer les images
$stmt = $db->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY is_primary DESC, display_order ASC");
$stmt->execute([':id' => $productId]);
$images = $stmt->fetchAll();

// Traiter les couleurs (support JSON et format simple)
$colorsData = [];
$hasMultipleColors = false;
if (!empty($product['color'])) {
    $colorJson = json_decode($product['color'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($colorJson)) {
        // Format JSON (nouveau format avec couleurs multiples)
        $colorsData = $colorJson;
        $hasMultipleColors = count($colorsData) > 0;
    } else {
        // Format texte simple (ancien format)
        $colorsData = [['name' => $product['color'], 'index' => 1]];
        $hasMultipleColors = false;
    }
}

// Récupérer les produits similaires
// Priorité 1: Produits avec le même type_category_id si le produit actuel en a un
// Priorité 2: Produits avec le même category_id
$relatedProducts = [];

if (!empty($product['type_category_id'])) {
    // Le produit a un type_category_id, chercher les produits avec le même type_category_id
    $stmt = $db->prepare("SELECT p.*, 
                          (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
                          FROM products p 
                          WHERE p.type_category_id = :type_category_id AND p.id != :id AND p.status = 'active'
                          ORDER BY RAND()
                          LIMIT 4");
    $stmt->execute([':type_category_id' => $product['type_category_id'], ':id' => $productId]);
    $relatedProducts = $stmt->fetchAll();
}

// Si pas assez de produits (ou pas de type_category_id), compléter/remplacer avec des produits de la même catégorie
if (count($relatedProducts) < 4 && !empty($product['category_id'])) {
    $needed = 4 - count($relatedProducts);
    $excludeIds = [$productId];
    foreach ($relatedProducts as $rp) {
        $excludeIds[] = (int)$rp['id'];
    }
    
    // Construire la requête avec des placeholders sécurisés
    $placeholders = [];
    $params = [':category_id' => $product['category_id']];
    
    foreach ($excludeIds as $index => $excludeId) {
        $key = ':exclude_' . $index;
        $placeholders[] = $key;
        $params[$key] = $excludeId;
    }
    
    $excludeClause = !empty($placeholders) ? 'AND p.id NOT IN (' . implode(',', $placeholders) . ')' : '';
    
    $sql = "SELECT p.*, 
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
            FROM products p 
            WHERE p.category_id = :category_id $excludeClause AND p.status = 'active'
            ORDER BY RAND()
            LIMIT :limit";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $needed, PDO::PARAM_INT);
    $stmt->execute();
    $categoryProducts = $stmt->fetchAll();
    
    $relatedProducts = array_merge($relatedProducts, $categoryProducts);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($product['name']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="product-page">
        <div class="container">
            <a href="javascript:history.back()" class="btn-back">
                <span>←</span> Retour
            </a>
            

            <div class="product-detail">
                <!-- Images du produit -->
                <div class="product-images">
                    <div class="product-main-image">
                        <?php 
                        // Déterminer l'image principale : priorité à la première couleur avec image, sinon première image du produit
                        $mainImageSrc = '';
                        if ($hasMultipleColors && !empty($colorsData[0]['image'])) {
                            $mainImageSrc = $colorsData[0]['image'];
                        } elseif (count($images) > 0) {
                            $mainImageSrc = $images[0]['image_path'];
                        }
                        ?>
                        <?php if ($mainImageSrc): ?>
                            <img id="main-image" 
                                 src="<?php echo clean($mainImageSrc); ?>" 
                                 alt="<?php echo clean($product['name']); ?>"
                                 data-default-image="<?php echo clean($mainImageSrc); ?>"
                                 style="width: 100%; height: auto; border-radius: 8px; transition: opacity 0.3s ease;">
                        <?php else: ?>
                            <div class="placeholder-image large">Image</div>
                        <?php endif; ?>
                    </div>
                    <?php if (count($images) > 1): ?>
                        <div class="product-thumbnails">
                            <?php foreach ($images as $index => $image): ?>
                                <?php 
                                // Trouver la couleur correspondante à cette image
                                $matchingColor = null;
                                $matchingColorName = '';
                                if ($hasMultipleColors && count($colorsData) > 0) {
                                    $imagePath = $image['image_path'];
                                    $imageFileName = basename($imagePath);
                                    
                                    // Stratégie 1: Comparer par chemin exact
                                    foreach ($colorsData as $colorItem) {
                                        if (isset($colorItem['image']) && !empty($colorItem['image'])) {
                                            $colorImagePath = $colorItem['image'];
                                            $colorImageFileName = basename($colorImagePath);
                                            
                                            // Normaliser les chemins
                                            $normalizedImagePath = ltrim($imagePath, './');
                                            $normalizedColorPath = ltrim($colorImagePath, './');
                                            
                                            if ($colorImagePath === $imagePath || 
                                                $normalizedColorPath === $normalizedImagePath ||
                                                $colorImageFileName === $imageFileName ||
                                                strpos($imagePath, $colorImageFileName) !== false ||
                                                strpos($colorImagePath, $imageFileName) !== false) {
                                                $matchingColor = $colorItem;
                                                $matchingColorName = $colorItem['name'];
                                                break;
                                            }
                                        }
                                    }
                                    
                                    // Stratégie 2: Si pas de correspondance, associer par index/ordre
                                    // (Image 1 = Couleur 1, Image 2 = Couleur 2, etc.)
                                    if (empty($matchingColorName) && $index < count($colorsData)) {
                                        $matchingColor = $colorsData[$index];
                                        $matchingColorName = isset($colorsData[$index]['name']) ? $colorsData[$index]['name'] : '';
                                        // Debug
                                        // echo "<!-- Association par index: Image $index -> Couleur: $matchingColorName -->";
                                    }
                                }
                                ?>
                                <img src="<?php echo clean($image['image_path']); ?>" 
                                     alt="Image <?php echo $index + 1; ?>" 
                                     class="thumbnail <?php echo $index == 0 ? 'active' : ''; ?>"
                                     data-image-path="<?php echo clean($image['image_path']); ?>"
                                     data-color-name="<?php echo $matchingColorName ? clean($matchingColorName) : ''; ?>"
                                     onclick="changeMainImage('<?php echo clean($image['image_path']); ?>', this, '<?php echo $matchingColorName ? clean($matchingColorName) : ''; ?>')">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Informations du produit -->
                <div class="product-info-detail">
                    <h1><?php echo clean($product['name']); ?></h1>
                    <p class="product-category-link">
                        <a href="products.php?category=<?php echo $product['category_id']; ?>">
                            <?php echo clean($product['category_name']); ?>
                        </a>
                    </p>

                    <div class="product-price-detail">
                        <?php 
                        $unitPrice = $product['sale_price'] ? $product['sale_price'] : $product['price'];
                        ?>
                        <?php if ($product['sale_price']): ?>
                            <span class="old-price"><?php echo formatPrice($product['price']); ?></span>
                            <span class="current-price"><?php echo formatPrice($product['sale_price']); ?></span>
                            <?php 
                            $discount = round((($product['price'] - $product['sale_price']) / $product['price']) * 100);
                            ?>
                            <span class="discount-badge">-<?php echo $discount; ?>%</span>
                        <?php else: ?>
                            <span class="current-price"><?php echo formatPrice($product['price']); ?></span>
                        <?php endif; ?>
                        <div style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-light);">
                            Prix au m²
                        </div>
                    </div>


                    <?php if ($product['short_description']): ?>
                        <p class="product-short-desc"><?php echo clean($product['short_description']); ?></p>
                    <?php endif; ?>

                    <div class="product-specs">
                        <?php if ($product['material']): ?>
                            <div class="spec-item">
                                <strong>Matériau:</strong> <?php echo clean($product['material']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($product['size']): ?>
                            <div class="spec-item">
                                <strong>Taille:</strong> <?php echo clean($product['size']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($colorsData)): ?>
                            <div class="spec-item">
                                <strong>Couleur<?php echo $hasMultipleColors ? 's' : ''; ?>:</strong>
                                <?php if ($hasMultipleColors): ?>
                                    <!-- Sélecteur de couleurs multiples -->
                                    <div class="colors-selector" style="margin-top: 0.75rem;">
                                        <div class="colors-list" style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                                            <?php foreach ($colorsData as $index => $colorItem): ?>
                                                <?php 
                                                $colorName = isset($colorItem['name']) ? $colorItem['name'] : '';
                                                $colorImage = isset($colorItem['image']) ? $colorItem['image'] : '';
                                                $isFirst = $index === 0;
                                                ?>
                                                <div class="color-option" 
                                                     data-color-name="<?php echo clean($colorName); ?>"
                                                     data-color-image="<?php echo $colorImage ? clean($colorImage) : ''; ?>"
                                                     style="cursor: pointer; padding: 0.5rem 1rem; border: 2px solid var(--border-color); border-radius: 8px; background: var(--light-color); transition: all 0.3s; <?php echo $isFirst ? 'border-color: var(--primary-color); background: rgba(139,69,19,0.1);' : ''; ?>"
                                                     onclick="selectColor(this, '<?php echo clean($colorName); ?>', '<?php echo $colorImage ? clean($colorImage) : ''; ?>', <?php echo $index; ?>)">
                                                    <?php if ($colorImage): ?>
                                                        <img src="<?php echo clean($colorImage); ?>" 
                                                             alt="<?php echo clean($colorName); ?>"
                                                             style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; margin-right: 0.5rem; display: inline-block; vertical-align: middle;"
                                                             onerror="this.style.display='none';">
                                                    <?php endif; ?>
                                                    <span style="font-weight: 600; color: var(--text-dark);"><?php echo clean($colorName); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" id="selected-color" name="selected_color" value="<?php echo !empty($colorsData[0]['name']) ? clean($colorsData[0]['name']) : ''; ?>">
                                    </div>
                                <?php else: ?>
                                    <!-- Format simple (une seule couleur) -->
                                    <span><?php echo clean($colorsData[0]['name']); ?></span>
                                    <input type="hidden" id="selected-color" name="selected_color" value="<?php echo clean($colorsData[0]['name']); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="spec-item">
                            <strong>Stock:</strong> 
                            <span class="<?php echo $product['stock'] > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                <?php echo $product['stock'] > 0 ? 'En stock (' . $product['stock'] . ')' : 'Rupture de stock'; ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($product['description']): ?>
                        <div class="product-description">
                            <h3>Description</h3>
                            <p><?php echo nl2br(clean($product['description'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="product-actions">
                        <div class="quantity-selector">
                            <label>Quantité:</label>
                            <div class="quantity-controls">
                                <button type="button" onclick="decreaseQuantity()">-</button>
                                <input type="number" id="product-quantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
                                <button type="button" onclick="increaseQuantity()">+</button>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-large" 
                                id="add-to-cart-btn" 
                                type="button"
                                data-product-id="<?php echo $product['id']; ?>"
                                data-type-category="<?php echo !empty($product['type_category_name']) ? strtolower(trim($product['type_category_name'])) : ''; ?>"
                                data-custom-handler="true"
                                onclick="handleAddToCartClick(event)"
                                <?php echo $product['stock'] == 0 ? 'disabled' : ''; ?>>
                            <?php echo $product['stock'] > 0 ? 'Ajouter au panier' : 'Rupture de stock'; ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Produits similaires -->
            <?php if (count($relatedProducts) > 0): ?>
                <section class="related-products">
                    <h2>Produits Similaires</h2>
                    <div class="products-grid">
                        <?php foreach ($relatedProducts as $related): ?>
                            <div class="product-card">
                                <a href="product.php?id=<?php echo $related['id']; ?>">
                                    <div class="product-image">
                                        <?php if ($related['image']): ?>
                                            <img src="<?php echo clean($related['image']); ?>" alt="<?php echo clean($related['name']); ?>">
                                        <?php else: ?>
                                            <div class="placeholder-image">Image</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-info">
                                        <h3><?php echo clean($related['name']); ?></h3>
                                        <div class="product-price">
                                            <?php if ($related['sale_price']): ?>
                                                <span class="old-price"><?php echo formatPrice($related['price']); ?></span>
                                                <span class="current-price"><?php echo formatPrice($related['sale_price']); ?></span>
                                            <?php else: ?>
                                                <span class="current-price"><?php echo formatPrice($related['price']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                                <button class="btn-add-cart" data-product-id="<?php echo $related['id']; ?>">Ajouter au panier</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

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
                        <strong style="color: var(--text-dark);"><?php echo formatPrice($unitPrice); ?> / m²</strong>
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
    
    <!-- Script pour gérer le modal - AVANT main.js pour éviter les conflits -->
    <script>
        console.log('=== DEBUT DU SCRIPT PRODUCT.PHP ===');
        
        // Variables globales pour être accessibles partout
        window.unitPrice = <?php echo $unitPrice; ?>;
        window.isAuthentique = <?php echo $showDimensionsCalculator ? 'false' : 'true'; ?>;
        window.currentProductId = <?php echo $product['id']; ?>;
        
        console.log('Variables initialisées:');
        console.log('- unitPrice:', window.unitPrice);
        console.log('- isAuthentique:', window.isAuthentique, '(type:', typeof window.isAuthentique, ')');
        console.log('- currentProductId:', window.currentProductId);
        
        // Fonctions du modal (globales)
        window.openDimensionsModal = function() {
            const modal = document.getElementById('dimensions-modal');
            if (!modal) {
                console.error('Modal dimensions-modal introuvable!');
                alert('Erreur: Le formulaire de dimensions est introuvable');
                return;
            }
            console.log('Ouverture du modal');
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
            
            if (length > 0 && width > 0) {
                // Convertir cm² en m² (1 m² = 10 000 cm²)
                const surfaceCm2 = length * width;
                const surfaceM2 = surfaceCm2 / 10000;
                const total = surfaceM2 * unitPrice;
                
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
        
        window.addToCartDirectly = function() {
            const quantityInput = document.getElementById('product-quantity');
            const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
            const productId = currentProductId;
            
            if (!productId) return;
            
            const btn = document.getElementById('add-to-cart-btn');
            btn.disabled = true;
            btn.textContent = 'Ajout en cours...';
            
            // Récupérer la couleur sélectionnée
            const selectedColorInput = document.getElementById('selected-color');
            const selectedColor = selectedColorInput ? selectedColorInput.value : '';
            
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
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
                    showNotification(data.message, 'success');
                    updateCartCount(data.cart_count);
                } else {
                    showNotification(data.message || 'Erreur lors de l\'ajout au panier', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Erreur lors de l\'ajout au panier', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Ajouter au panier';
            });
        };
        
        // Fonction globale pour gérer le clic sur "Ajouter au panier"
        window.handleAddToCartClick = function(e) {
            console.log('=== handleAddToCartClick APPELÉ ===');
            
            if (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
            }
            
            console.log('isAuthentique:', window.isAuthentique);
            console.log('Type de isAuthentique:', typeof window.isAuthentique);
            
            // Si le type est "authentique", ajouter directement au panier
            if (window.isAuthentique === true || window.isAuthentique === 'true') {
                console.log('→ Ajout direct (authentique)');
                if (typeof window.addToCartDirectly === 'function') {
                    window.addToCartDirectly();
                } else {
                    console.error('ERREUR: addToCartDirectly n\'est pas une fonction!');
                }
            } else {
                // Sinon, ouvrir le modal pour les dimensions
                console.log('→ Ouverture du modal');
                if (typeof window.openDimensionsModal === 'function') {
                    window.openDimensionsModal();
                } else {
                    console.error('ERREUR: openDimensionsModal n\'est pas une fonction!');
                    alert('Erreur: Impossible d\'ouvrir le formulaire de dimensions');
                }
            }
            return false;
        };
        
        console.log('handleAddToCartClick définie:', typeof window.handleAddToCartClick);
        console.log('=== FIN DU SCRIPT PRODUCT.PHP ===');
        
        // Fermer le modal avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.closeDimensionsModal();
            }
        });
        
        // Gérer le clic sur "Ajouter au panier" dans le modal
        document.addEventListener('DOMContentLoaded', function() {
            const confirmBtn = document.getElementById('confirm-add-to-cart');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function() {
                    const length = parseFloat(document.getElementById('modal-length').value) || 0;
                    const width = parseFloat(document.getElementById('modal-width').value) || 0;
                    const quantityInput = document.getElementById('product-quantity');
                    const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
                    const productId = window.currentProductId;
                    
                    if (length <= 0 || width <= 0) {
                        showNotification('Veuillez entrer des dimensions valides', 'error');
                        return;
                    }
                    
                    // Calculer le prix
                    const surfaceM2 = (length * width) / 10000;
                    const calculatedPrice = surfaceM2 * window.unitPrice;
                    
                    const btn = this;
                    btn.disabled = true;
                    btn.textContent = 'Ajout en cours...';
                    
                    const formData = new FormData();
                    // Récupérer la couleur sélectionnée
                    const selectedColorInput = document.getElementById('selected-color');
                    const selectedColor = selectedColorInput ? selectedColorInput.value : '';
                    
                    formData.append('product_id', productId);
                    formData.append('quantity', quantity);
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
        
        // Logs de débogage
        console.log('Script product.php chargé');
        console.log('isAuthentique:', window.isAuthentique);
        console.log('Modal existe:', !!document.getElementById('dimensions-modal'));
        console.log('Bouton existe:', !!document.getElementById('add-to-cart-btn'));
        
        // Fonction pour changer l'image principale
        function changeMainImage(src, element, colorName, colorIndex) {
            console.log('changeMainImage appelé - src:', src, 'colorName:', colorName, 'colorIndex:', colorIndex);
            
            const mainImage = document.getElementById('main-image');
            if (mainImage) {
                // Ajouter un effet de fondu
                mainImage.style.opacity = '0.5';
                setTimeout(() => {
                    mainImage.src = src;
                    mainImage.style.opacity = '1';
                }, 150);
            }
            
            // Mettre à jour les miniatures actives
            document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
            if (element) {
                element.classList.add('active');
            }
            
            // Si une couleur correspond à cette image, la sélectionner automatiquement
            let colorToSelect = colorName;
            
            // Si pas de nom de couleur mais un index, trouver la couleur par index
            if ((!colorToSelect || colorToSelect.trim() === '') && colorIndex !== undefined && colorIndex !== null) {
                const colorOptions = document.querySelectorAll('.color-option');
                if (colorIndex < colorOptions.length) {
                    const colorOption = colorOptions[colorIndex];
                    colorToSelect = colorOption.getAttribute('data-color-name');
                    console.log('Couleur trouvée par index:', colorIndex, '->', colorToSelect);
                }
            }
            
            if (colorToSelect && colorToSelect.trim() !== '') {
                console.log('Recherche de la couleur:', colorToSelect);
                // Trouver l'option de couleur correspondante
                const colorOptions = document.querySelectorAll('.color-option');
                let colorFound = false;
                
                colorOptions.forEach((opt, idx) => {
                    const optColorName = opt.getAttribute('data-color-name');
                    
                    // Comparer par nom ou par index
                    if ((optColorName && optColorName.trim() === colorToSelect.trim()) || 
                        (colorIndex !== undefined && idx === colorIndex)) {
                        colorFound = true;
                        // Simuler un clic sur cette couleur pour mettre à jour l'apparence
                        opt.style.borderColor = 'var(--primary-color)';
                        opt.style.background = 'rgba(139,69,19,0.1)';
                        
                        // Mettre à jour le champ caché
                        const selectedColorInput = document.getElementById('selected-color');
                        if (selectedColorInput) {
                            const finalColorName = optColorName || colorToSelect;
                            selectedColorInput.value = finalColorName;
                            console.log('✅ Couleur sauvegardée:', finalColorName);
                        }
                        
                        // Désélectionner les autres couleurs
                        colorOptions.forEach(otherOpt => {
                            if (otherOpt !== opt) {
                                otherOpt.style.borderColor = 'var(--border-color)';
                                otherOpt.style.background = 'var(--light-color)';
                            }
                        });
                        
                        console.log('✅ Couleur automatiquement sélectionnée:', optColorName || colorToSelect);
                    }
                });
                
                if (!colorFound) {
                    console.warn('⚠️ Couleur non trouvée dans les options:', colorToSelect);
                }
            } else {
                // Si aucune couleur ne correspond, garder la sélection actuelle
                console.log('ℹ️ Aucune couleur associée à cette image (image générique)');
            }
        }
        
        // Fonction pour sélectionner une couleur
        function selectColor(element, colorName, colorImage, colorIndex) {
            console.log('=== Sélection de couleur ===');
            console.log('Couleur:', colorName);
            console.log('Image directe:', colorImage);
            console.log('Index couleur:', colorIndex);
            
            // Mettre à jour l'apparence des options de couleur
            document.querySelectorAll('.color-option').forEach(opt => {
                opt.style.borderColor = 'var(--border-color)';
                opt.style.background = 'var(--light-color)';
            });
            element.style.borderColor = 'var(--primary-color)';
            element.style.background = 'rgba(139,69,19,0.1)';
            
            // Mettre à jour le champ caché
            const selectedColorInput = document.getElementById('selected-color');
            if (selectedColorInput) {
                selectedColorInput.value = colorName;
                console.log('Couleur sauvegardée dans le champ caché:', selectedColorInput.value);
            }
            
            // Changer l'image principale
            const mainImage = document.getElementById('main-image');
            if (mainImage) {
                let imagePath = colorImage && colorImage.trim() !== '' ? colorImage.trim() : null;
                
                // Si pas d'image directe, chercher l'image correspondante par index
                if (!imagePath && colorIndex !== undefined && colorIndex !== null) {
                    const thumbnails = document.querySelectorAll('.thumbnail');
                    console.log('Recherche d\'image par index - Index couleur:', colorIndex, 'Nombre de thumbnails:', thumbnails.length);
                    
                    if (colorIndex < thumbnails.length) {
                        const matchingThumbnail = thumbnails[colorIndex];
                        imagePath = matchingThumbnail.getAttribute('data-image-path') || matchingThumbnail.src;
                        console.log('✅ Image trouvée par index:', colorIndex, '->', imagePath);
                    } else {
                        console.warn('⚠️ Index couleur (' + colorIndex + ') dépasse le nombre de thumbnails (' + thumbnails.length + ')');
                    }
                }
                
                if (imagePath) {
                    console.log('Changement de l\'image principale vers:', imagePath);
                    
                    // Ajouter un effet de fondu lors du changement
                    mainImage.style.opacity = '0.5';
                    
                    // Précharger l'image pour éviter les erreurs
                    const img = new Image();
                    img.onload = function() {
                        mainImage.src = imagePath;
                        mainImage.style.opacity = '1';
                        console.log('✅ Image chargée avec succès:', imagePath);
                        
                        // Mettre à jour la miniature active
                        document.querySelectorAll('.thumbnail').forEach(thumb => {
                            thumb.classList.remove('active');
                            const thumbImagePath = thumb.getAttribute('data-image-path') || thumb.src;
                            const thumbSrc = thumbImagePath.split('/').pop();
                            const newImageName = imagePath.split('/').pop();
                            // Comparer les chemins complets ou juste les noms de fichiers
                            if (thumbImagePath === imagePath || thumbSrc === newImageName || thumb.src.includes(newImageName)) {
                                thumb.classList.add('active');
                            }
                        });
                    };
                    img.onerror = function() {
                        console.error('Erreur lors du chargement de l\'image:', imagePath);
                        // Essayer différentes variantes du chemin
                        const pathVariants = [
                            imagePath,
                            '../' + imagePath,
                            imagePath.replace(/^\.\.\//, ''),
                            imagePath.startsWith('assets/') ? imagePath : 'assets/images/products/' + imagePath.split('/').pop()
                        ];
                        
                        let triedVariants = 0;
                        const tryNextVariant = function() {
                            if (triedVariants < pathVariants.length) {
                                const variant = pathVariants[triedVariants];
                                console.log('Essai avec le chemin:', variant);
                                const testImg = new Image();
                                testImg.onload = function() {
                                    mainImage.src = variant;
                                    mainImage.style.opacity = '1';
                                    console.log('✅ Image chargée avec le chemin alternatif:', variant);
                                    
                                    // Mettre à jour la miniature active
                                    document.querySelectorAll('.thumbnail').forEach(thumb => {
                                        thumb.classList.remove('active');
                                        const thumbImagePath = thumb.getAttribute('data-image-path') || thumb.src;
                                        if (thumbImagePath === variant || thumb.src.includes(variant.split('/').pop())) {
                                            thumb.classList.add('active');
                                        }
                                    });
                                };
                                testImg.onerror = function() {
                                    triedVariants++;
                                    tryNextVariant();
                                };
                                testImg.src = variant;
                            } else {
                                // Si aucune variante ne fonctionne, restaurer l'image par défaut
                                const defaultImage = mainImage.getAttribute('data-default-image');
                                if (defaultImage) {
                                    mainImage.src = defaultImage;
                                    mainImage.style.opacity = '1';
                                    console.warn('⚠️ Image non trouvée, restauration de l\'image par défaut');
                                } else {
                                    mainImage.style.opacity = '1';
                                }
                            }
                        };
                        tryNextVariant();
                    };
                    img.src = imagePath;
                } else {
                    console.log('ℹ️ Aucune image trouvée pour cette couleur (ni directe, ni par index)');
                    // Si pas d'image pour la couleur, restaurer l'image par défaut
                    const defaultImage = mainImage.getAttribute('data-default-image');
                    if (defaultImage) {
                        mainImage.src = defaultImage;
                        mainImage.style.opacity = '1';
                    }
                }
            } else {
                console.error('❌ Élément main-image introuvable');
            }
            
            console.log('=== Fin sélection de couleur ===');
        }

        function increaseQuantity() {
            const input = document.getElementById('product-quantity');
            const max = parseInt(input.getAttribute('max'));
            if (parseInt(input.value) < max) {
                input.value = parseInt(input.value) + 1;
            }
        }

        function decreaseQuantity() {
            const input = document.getElementById('product-quantity');
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
            }
        }

        function calculateTotalPrice() {
            const lengthInput = document.getElementById('length');
            const widthInput = document.getElementById('width');
            
            // Vérifier si les champs existent (pour les produits sans calculateur de dimensions)
            if (!lengthInput || !widthInput) {
                return;
            }
            
            const length = parseFloat(lengthInput.value) || 0;
            const width = parseFloat(widthInput.value) || 0;
            const priceCalculation = document.getElementById('price-calculation');
            const dimensionsDisplay = document.getElementById('dimensions-display');
            const surfaceArea = document.getElementById('surface-area');
            const totalPrice = document.getElementById('total-price');

            if (length > 0 && width > 0) {
                // Convertir cm² en m² (1 m² = 10 000 cm²)
                const surfaceCm2 = length * width;
                const surfaceM2 = surfaceCm2 / 10000;
                const total = surfaceM2 * unitPrice;
                
                // Afficher les dimensions
                if (dimensionsDisplay) {
                    dimensionsDisplay.textContent = Math.round(length) + ' cm × ' + Math.round(width) + ' cm';
                }
                
                // Afficher la surface avec 2 décimales et format français
                const surfaceFormatted = surfaceM2.toFixed(2).replace('.', ',');
                if (surfaceArea) {
                    surfaceArea.textContent = surfaceFormatted + ' m²';
                }
                
                if (totalPrice) {
                    totalPrice.textContent = formatPrice(total);
                }
                if (priceCalculation) {
                    priceCalculation.style.display = 'block';
                }
            } else {
                if (priceCalculation) {
                    priceCalculation.style.display = 'none';
                }
            }
        }

        window.formatPrice = function(price) {
            // Format: 1 234,56 MAD (comme la fonction PHP)
            return price.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' MAD';
        };
    </script>
    
    <script src="assets/js/main.js"></script>

        function increaseQuantity() {
            const input = document.getElementById('product-quantity');
            const max = parseInt(input.getAttribute('max'));
            if (parseInt(input.value) < max) {
                input.value = parseInt(input.value) + 1;
            }
        }

        function decreaseQuantity() {
            const input = document.getElementById('product-quantity');
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
            }
        }
        
    </script>
</body>
</html>


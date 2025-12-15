<?php
// Script utilitaire pour générer rapidement des produits de démo
// à partir des catégories / types existants et des images du dossier assets/images/products.
//
// Utilisation (une seule fois) :
//   1. Connectez-vous en admin.
//   2. Ouvrez /admin/seed_products_from_images.php?confirm=1 dans le navigateur.
//   3. Vérifiez les produits générés puis supprimez ce fichier si vous n'en avez plus besoin.

session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

// Nombre de produits à créer par catégorie (paramètre optionnel ?per_cat=3)
$perCategory = isset($_GET['per_cat']) ? max(1, (int)$_GET['per_cat']) : 1;

if (!isset($_GET['confirm']) || $_GET['confirm'] != '1') {
    echo '<h1>Génération de produits de démo</h1>';
    echo '<p>Ce script va créer automatiquement <strong>' . $perCategory . '</strong> produit(s) pour chaque catégorie existante, ';
    echo 'et leur associer des images trouvées dans <code>assets/images/products</code>.</p>';
    echo '<p><strong>ATTENTION :</strong> cette opération insère des données dans la base. ';
    echo 'Ne l\'exécutez qu\'une seule fois sur une base vide ou de test.</p>';
    echo '<p>Vous pouvez changer le nombre de produits par catégorie avec le paramètre <code>?per_cat=3</code> par exemple.</p>';
    echo '<p><a href="seed_products_from_images.php?confirm=1&amp;per_cat=' . $perCategory . '" style="padding:0.75rem 1.25rem;background:#2c7a7b;color:#fff;border-radius:6px;text-decoration:none;">Lancer la génération</a></p>';
    exit;
}

$db = getDB();

// Récupérer toutes les catégories avec leur type
$stmt = $db->query("SELECT c.id, c.name, c.slug, c.type_id, t.name AS type_name
                    FROM categories c
                    LEFT JOIN types t ON c.type_id = t.id
                    ORDER BY c.id");
$categories = $stmt->fetchAll();

if (count($categories) === 0) {
    echo 'Aucune catégorie trouvée. Veuillez d\'abord créer des catégories.';
    exit;
}

// Récupérer les images physiques dans le dossier assets/images/products
$imagesDir = realpath(__DIR__ . '/../assets/images/products');
$imageFiles = glob($imagesDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);

if (!$imageFiles || count($imageFiles) === 0) {
    echo 'Aucune image trouvée dans assets/images/products.';
    exit;
}

// Transformer en chemins relatifs utilisables par le site
$relativeImages = [];
foreach ($imageFiles as $fullPath) {
    $filename = basename($fullPath);
    $relativeImages[] = 'assets/images/products/' . $filename;
}

$inserted = 0;
$imageIndex = 0;

foreach ($categories as $cat) {
    $typeId = $cat['type_id'] ?: null;
    $typeLabel = $cat['type_name'] ? (' - ' . $cat['type_name']) : '';

    // Créer plusieurs produits par catégorie si demandé
    for ($i = 1; $i <= $perCategory; $i++) {
        // Choisir une image (on boucle sur la liste)
        $imagePath = $relativeImages[$imageIndex % count($relativeImages)];
        $imageIndex++;

        $suffixLabel = $perCategory > 1 ? ' #' . $i : '';
        $productName = 'Tapis ' . $cat['name'] . $typeLabel . $suffixLabel;
        $slugBase = generateSlug($productName);

        // S'assurer que le slug est unique
        $slug = $slugBase;
        $suffix = 1;
        $checkStmt = $db->prepare("SELECT COUNT(*) AS c FROM products WHERE slug = :slug");
        while (true) {
            $checkStmt->execute([':slug' => $slug]);
            $count = (int)$checkStmt->fetch()['c'];
            if ($count === 0) {
                break;
            }
            $suffix++;
            $slug = $slugBase . '-' . $suffix;
        }

        // Prix légèrement différent pour chaque produit
        $basePrice = 800 + ($cat['id'] * 100) + ($i - 1) * 50;

        $insertProduct = $db->prepare(
            "INSERT INTO products 
             (name, slug, description, short_description, price, sale_price, category_id, type_id, material, size, color, stock, featured, best_seller, status)
             VALUES
             (:name, :slug, :description, :short_description, :price, :sale_price, :category_id, :type_id, :material, :size, :color, :stock, :featured, :best_seller, :status)"
        );

        $description = 'Tapis ' . $cat['name'] . $typeLabel . ' de haute qualité, sélectionné automatiquement pour la démo.';
        $shortDescription = 'Tapis ' . $cat['name'] . $typeLabel . $suffixLabel;

        $insertProduct->execute([
            ':name' => $productName,
            ':slug' => $slug,
            ':description' => $description,
            ':short_description' => $shortDescription,
            ':price' => $basePrice,
            ':sale_price' => null,
            ':category_id' => $cat['id'],
            ':type_id' => $typeId,
            ':material' => 'Laine',
            ':size' => '200x300',
            ':color' => 'Beige',
            ':stock' => 10,
            ':featured' => 0,
            ':best_seller' => 0,
            ':status' => 'active',
        ]);

        $productId = $db->lastInsertId();

        // Insérer l'image principale dans product_images
        $insertImage = $db->prepare(
            "INSERT INTO product_images (product_id, image_path, is_primary, display_order)
             VALUES (:product_id, :image_path, 1, 1)"
        );
        $insertImage->execute([
            ':product_id' => $productId,
            ':image_path' => $imagePath,
        ]);

        $inserted++;
    }
}

echo '<h1>Génération terminée</h1>';
echo '<p>' . (int)$inserted . ' produits ont été créés (' . $perCategory . ' par catégorie) avec des images de <code>assets/images/products</code>.</p>';
echo '<p>Vous pouvez maintenant retourner à la gestion des produits.</p>';
echo '<p><a href="products.php">← Retour à la liste des produits</a></p>';



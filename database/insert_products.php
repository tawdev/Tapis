<?php
/**
 * Script pour insérer 3 produits pour chaque catégorie
 * À exécuter une seule fois depuis le navigateur ou la ligne de commande
 */

require_once '../config/database.php';
require_once '../config/functions.php';

$db = getDB();

// Récupérer les catégories pour obtenir leurs IDs
$stmt = $db->query("SELECT id, name FROM categories ORDER BY name");
$categories = [];
while ($row = $stmt->fetch()) {
    $categories[$row['name']] = $row['id'];
}

// Vérifier que toutes les catégories existent
$requiredCategories = ['Classique', 'Marocain', 'Moderne', 'Oriental', 'Turc'];
foreach ($requiredCategories as $catName) {
    if (!isset($categories[$catName])) {
        die("Erreur: La catégorie '$catName' n'existe pas dans la base de données.");
    }
}

// Produits à insérer
$products = [
    // Produits Classique
    [
        'name' => 'Tapis Persan Classique Isfahan',
        'description' => 'Magnifique tapis persan Isfahan aux motifs floraux raffinés. Tissé à la main avec de la soie et de la laine de qualité supérieure. Un véritable chef-d\'œuvre qui apportera élégance et sophistication à votre intérieur.',
        'short_description' => 'Tapis persan Isfahan authentique',
        'price' => 3499.00,
        'sale_price' => 2999.00,
        'category_id' => $categories['Classique'],
        'material' => 'Soie et Laine',
        'size' => '300x400',
        'color' => 'Bleu et Crème',
        'stock' => 5,
        'featured' => 1,
        'best_seller' => 1
    ],
    [
        'name' => 'Tapis Persan Nain Classique',
        'description' => 'Tapis persan Nain de grande qualité, reconnu pour sa finesse et ses motifs géométriques élégants. Fabriqué par des artisans expérimentés, ce tapis est un investissement pour les générations futures.',
        'short_description' => 'Tapis persan Nain premium',
        'price' => 4299.00,
        'sale_price' => null,
        'category_id' => $categories['Classique'],
        'material' => 'Soie et Laine',
        'size' => '250x350',
        'color' => 'Ivoire et Bleu',
        'stock' => 4,
        'featured' => 1,
        'best_seller' => 0
    ],
    [
        'name' => 'Tapis Persan Tabriz Classique',
        'description' => 'Superbe tapis persan Tabriz aux motifs centraux complexes et bordures ornementales. Tissé avec une densité élevée, ce tapis allie tradition et raffinement pour un intérieur d\'exception.',
        'short_description' => 'Tapis persan Tabriz traditionnel',
        'price' => 3899.00,
        'sale_price' => 3299.00,
        'category_id' => $categories['Classique'],
        'material' => 'Soie et Laine',
        'size' => '280x380',
        'color' => 'Rouge et Or',
        'stock' => 6,
        'featured' => 1,
        'best_seller' => 1
    ],
    
    // Produits Marocain
    [
        'name' => 'Tapis Marocain Azilal Authentique',
        'description' => 'Tapis marocain Azilal authentique, tissé à la main par des femmes berbères. Caractérisé par ses motifs géométriques abstraits et ses couleurs vives, ce tapis apporte une touche d\'authenticité à votre décoration.',
        'short_description' => 'Tapis marocain Azilal artisanal',
        'price' => 1899.00,
        'sale_price' => 1599.00,
        'category_id' => $categories['Marocain'],
        'material' => 'Laine',
        'size' => '200x300',
        'color' => 'Multicolore',
        'stock' => 10,
        'featured' => 1,
        'best_seller' => 1
    ],
    [
        'name' => 'Tapis Marocain Boucherouite Moderne',
        'description' => 'Tapis marocain Boucherouite aux couleurs éclatantes et motifs modernes. Créé à partir de matériaux recyclés, ce tapis écologique allie style contemporain et tradition marocaine.',
        'short_description' => 'Tapis marocain Boucherouite écologique',
        'price' => 1299.00,
        'sale_price' => null,
        'category_id' => $categories['Marocain'],
        'material' => 'Laine recyclée',
        'size' => '180x250',
        'color' => 'Rouge, Jaune, Bleu',
        'stock' => 15,
        'featured' => 0,
        'best_seller' => 0
    ],
    [
        'name' => 'Tapis Marocain Taznakht Premium',
        'description' => 'Luxueux tapis marocain Taznakht aux motifs géométriques complexes et couleurs terre. Tissé par des maîtres artisans, ce tapis représente l\'excellence de l\'artisanat marocain traditionnel.',
        'short_description' => 'Tapis marocain Taznakht de luxe',
        'price' => 2499.00,
        'sale_price' => 2199.00,
        'category_id' => $categories['Marocain'],
        'material' => 'Laine',
        'size' => '250x350',
        'color' => 'Terre et Beige',
        'stock' => 8,
        'featured' => 1,
        'best_seller' => 1
    ],
    
    // Produits Moderne
    [
        'name' => 'Tapis Moderne Minimaliste Gris',
        'description' => 'Tapis moderne au design minimaliste et épuré. Parfait pour les intérieurs contemporains, ce tapis apporte douceur et élégance sans surcharger l\'espace. Matériaux de qualité supérieure.',
        'short_description' => 'Tapis moderne minimaliste',
        'price' => 799.00,
        'sale_price' => 649.00,
        'category_id' => $categories['Moderne'],
        'material' => 'Laine synthétique',
        'size' => '200x300',
        'color' => 'Gris',
        'stock' => 20,
        'featured' => 1,
        'best_seller' => 1
    ],
    [
        'name' => 'Tapis Moderne Géométrique Coloré',
        'description' => 'Tapis moderne aux motifs géométriques audacieux et couleurs vives. Idéal pour donner du caractère à votre salon ou chambre. Design contemporain et tendance.',
        'short_description' => 'Tapis moderne géométrique',
        'price' => 999.00,
        'sale_price' => null,
        'category_id' => $categories['Moderne'],
        'material' => 'Laine et Coton',
        'size' => '180x250',
        'color' => 'Multicolore',
        'stock' => 18,
        'featured' => 1,
        'best_seller' => 0
    ],
    [
        'name' => 'Tapis Moderne Shaggy Épais',
        'description' => 'Tapis moderne shaggy ultra-doux et confortable. Parfait pour créer une ambiance cosy et chaleureuse. Sa texture épaisse apporte un confort exceptionnel sous les pieds.',
        'short_description' => 'Tapis moderne shaggy confortable',
        'price' => 1199.00,
        'sale_price' => 999.00,
        'category_id' => $categories['Moderne'],
        'material' => 'Polyester',
        'size' => '200x300',
        'color' => 'Beige',
        'stock' => 25,
        'featured' => 1,
        'best_seller' => 1
    ],
    
    // Produits Oriental
    [
        'name' => 'Tapis Oriental Kashan Authentique',
        'description' => 'Magnifique tapis oriental Kashan aux motifs floraux élaborés et couleurs riches. Tissé à la main selon les traditions ancestrales, ce tapis est un véritable trésor pour votre intérieur.',
        'short_description' => 'Tapis oriental Kashan traditionnel',
        'price' => 2799.00,
        'sale_price' => 2399.00,
        'category_id' => $categories['Oriental'],
        'material' => 'Soie et Laine',
        'size' => '250x350',
        'color' => 'Rouge et Bleu',
        'stock' => 7,
        'featured' => 1,
        'best_seller' => 1
    ],
    [
        'name' => 'Tapis Oriental Qom en Soie',
        'description' => 'Luxueux tapis oriental Qom en soie pure. Reconnu pour sa finesse exceptionnelle et ses motifs délicats, ce tapis représente le summum de l\'artisanat oriental. Un investissement de prestige.',
        'short_description' => 'Tapis oriental Qom soie pure',
        'price' => 5499.00,
        'sale_price' => null,
        'category_id' => $categories['Oriental'],
        'material' => 'Soie',
        'size' => '200x300',
        'color' => 'Crème et Or',
        'stock' => 3,
        'featured' => 1,
        'best_seller' => 0
    ],
    [
        'name' => 'Tapis Oriental Heriz Traditionnel',
        'description' => 'Superbe tapis oriental Heriz aux motifs centraux imposants et bordures géométriques. Caractérisé par sa durabilité et ses couleurs éclatantes, ce tapis est parfait pour les espaces de vie.',
        'short_description' => 'Tapis oriental Heriz durable',
        'price' => 3299.00,
        'sale_price' => 2899.00,
        'category_id' => $categories['Oriental'],
        'material' => 'Laine',
        'size' => '280x380',
        'color' => 'Rouge et Bleu',
        'stock' => 6,
        'featured' => 1,
        'best_seller' => 1
    ],
    
    // Produits Turc
    [
        'name' => 'Tapis Turc Kilim Moderne',
        'description' => 'Tapis turc Kilim aux motifs géométriques modernes et couleurs vives. Tissé à plat selon la technique traditionnelle, ce tapis apporte une touche d\'authenticité turque à votre décoration.',
        'short_description' => 'Tapis turc Kilim artisanal',
        'price' => 899.00,
        'sale_price' => 749.00,
        'category_id' => $categories['Turc'],
        'material' => 'Laine',
        'size' => '150x250',
        'color' => 'Rouge, Bleu, Jaune',
        'stock' => 12,
        'featured' => 1,
        'best_seller' => 1
    ],
    [
        'name' => 'Tapis Turc Oushak Élégant',
        'description' => 'Élégant tapis turc Oushak aux motifs floraux délicats et couleurs pastel. Reconnu pour son style raffiné, ce tapis s\'intègre parfaitement dans les intérieurs modernes et classiques.',
        'short_description' => 'Tapis turc Oushak raffiné',
        'price' => 2199.00,
        'sale_price' => null,
        'category_id' => $categories['Turc'],
        'material' => 'Laine',
        'size' => '200x300',
        'color' => 'Beige et Rose',
        'stock' => 9,
        'featured' => 1,
        'best_seller' => 0
    ],
    [
        'name' => 'Tapis Turc Hereke de Luxe',
        'description' => 'Luxueux tapis turc Hereke en soie et laine. Considéré comme l\'un des plus beaux tapis turcs, ce modèle allie finesse exceptionnelle et motifs complexes pour un résultat somptueux.',
        'short_description' => 'Tapis turc Hereke de prestige',
        'price' => 4599.00,
        'sale_price' => 3999.00,
        'category_id' => $categories['Turc'],
        'material' => 'Soie et Laine',
        'size' => '250x350',
        'color' => 'Crème et Bleu',
        'stock' => 4,
        'featured' => 1,
        'best_seller' => 1
    ]
];

// Insertion des produits
$inserted = 0;
$errors = [];

try {
    $db->beginTransaction();
    
    foreach ($products as $product) {
        $slug = generateSlug($product['name']);
        
        // Vérifier si le slug existe déjà
        $stmt = $db->prepare("SELECT id FROM products WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            // Slug existe, ajouter un suffixe
            $slug = $slug . '-' . time() . '-' . rand(1000, 9999);
        }
        
        $stmt = $db->prepare("INSERT INTO products (name, slug, description, short_description, price, sale_price, category_id, material, size, color, stock, featured, best_seller, status) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        
        $stmt->execute([
            $product['name'],
            $slug,
            $product['description'],
            $product['short_description'],
            $product['price'],
            $product['sale_price'],
            $product['category_id'],
            $product['material'],
            $product['size'],
            $product['color'],
            $product['stock'],
            $product['featured'],
            $product['best_seller']
        ]);
        
        $inserted++;
    }
    
    $db->commit();
    
    echo "<h2>✅ Insertion réussie !</h2>";
    echo "<p><strong>$inserted produits</strong> ont été insérés avec succès dans la base de données.</p>";
    echo "<p>Répartition par catégorie :</p>";
    echo "<ul>";
    echo "<li>Classique : 3 produits</li>";
    echo "<li>Marocain : 3 produits</li>";
    echo "<li>Moderne : 3 produits</li>";
    echo "<li>Oriental : 3 produits</li>";
    echo "<li>Turc : 3 produits</li>";
    echo "</ul>";
    echo "<p><a href='../admin/products.php'>Voir les produits dans l'admin</a></p>";
    
} catch (PDOException $e) {
    $db->rollBack();
    echo "<h2>❌ Erreur lors de l'insertion</h2>";
    echo "<p>Erreur : " . $e->getMessage() . "</p>";
}


<?php
// Script utilitaire pour modifier en masse les couleurs des produits de démo.
// Idéal après avoir généré beaucoup de produits "Beige" avec le script de seed.
//
// Utilisation :
//   1. Connectez-vous en admin.
//   2. Ouvrez /admin/randomize_product_colors.php dans le navigateur pour voir le récap.
//   3. Cliquez sur le bouton de confirmation pour appliquer les changements.

session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$db = getDB();

// Palette de couleurs possibles (mêmes noms que dans le front / back)
$availableColors = [
    'Beige',
    'Blanch',
    'Bleu',
    'Green',
    'Gris',
    'Noir',
    'Rouge',
    'Jaune',
    'Orange',
    'Rose',
    'Marron',
    'Turquoise',
    'Violet',
];

// Récupérer les produits actuellement "Beige" (simple texte ou JSON avec uniquement Beige)
$stmt = $db->prepare("SELECT id, name, color FROM products WHERE color = 'Beige'");
$stmt->execute();
$products = $stmt->fetchAll();
$total = count($products);

if (!isset($_GET['confirm']) || $_GET['confirm'] != '1') {
    echo '<h1>Modification de masse des couleurs</h1>';
    echo '<p>Produits trouvés avec couleur <strong>Beige</strong> (champ color = "Beige") : <strong>' . (int)$total . '</strong></p>';
    if ($total === 0) {
        echo '<p>Aucun produit à mettre à jour.</p>';
        echo '<p><a href="products.php">← Retour à la liste des produits</a></p>';
        exit;
    }
    echo '<p>Ce script va attribuer à chaque produit une couleur <strong>aléatoire</strong> ';
    echo 'parmi la palette suivante : ' . implode(', ', $availableColors) . '.</p>';
    echo '<p><strong>ATTENTION :</strong> cette opération modifie définitivement les données.</p>';
    echo '<p><a href="randomize_product_colors.php?confirm=1" style="padding:0.75rem 1.25rem;background:#c53030;color:#fff;border-radius:6px;text-decoration:none;">Appliquer la modification</a></p>';
    echo '<p><a href="products.php">Annuler et revenir à la liste des produits</a></p>';
    exit;
}

if ($total === 0) {
    echo '<h1>Aucun produit à mettre à jour</h1>';
    echo '<p>Aucun produit avec color = "Beige" n\'a été trouvé.</p>';
    echo '<p><a href="products.php">← Retour à la liste des produits</a></p>';
    exit;
}

// Mise à jour aléatoire
$update = $db->prepare("UPDATE products SET color = :color WHERE id = :id");
$updated = 0;

foreach ($products as $p) {
    // Choisir une couleur au hasard (différente de Beige si possible)
    $choices = $availableColors;
    // Si on veut éviter de rester sur Beige :
    $choicesWithoutBeige = array_values(array_filter($choices, fn($c) => strtolower($c) !== 'beige'));
    if (!empty($choicesWithoutBeige)) {
        $choices = $choicesWithoutBeige;
    }
    $randIndex = array_rand($choices);
    $newColor = $choices[$randIndex];

    $update->execute([
        ':color' => $newColor,
        ':id' => $p['id'],
    ]);
    $updated++;
}

echo '<h1>Couleurs mises à jour</h1>';
echo '<p>' . (int)$updated . ' produit(s) initialement "Beige" ont reçu une nouvelle couleur aléatoire.</p>';
echo '<p>Vous pouvez maintenant utiliser les filtres Couleur sur le site pour voir les produits répartis sur plusieurs couleurs.</p>';
echo '<p><a href="products.php">← Retour à la liste des produits</a></p>';



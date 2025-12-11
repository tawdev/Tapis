<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>À propos - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="about-page">
        <div class="container">
            <div class="page-header">
                <h1>À propos de nous</h1>
                <p>Découvrez l'histoire de waootapis</p>
            </div>

            <div class="about-content">
                <section class="about-section">
                    <div class="about-image">
                        <div class="about-placeholder">🏺</div>
                    </div>
                    <div class="about-text">
                        <h2>Notre Histoire</h2>
                        <p>Depuis notre création, Tapis s'est engagé à offrir à nos clients les plus beaux tapis du Maroc et du monde entier. Nous sélectionnons avec soin chaque pièce pour garantir qualité, authenticité et élégance.</p>
                        <p>Notre passion pour les tapis nous pousse à rechercher constamment les meilleures créations, qu'elles soient modernes, classiques, orientales ou traditionnelles marocaines.</p>
                    </div>
                </section>

                <section class="about-section reverse">
                    <div class="about-text">
                        <h2>Notre Mission</h2>
                        <p>Notre mission est de rendre accessible l'art et la beauté des tapis authentiques à tous nos clients. Nous croyons qu'un tapis n'est pas seulement un objet de décoration, mais une œuvre d'art qui transforme un espace.</p>
                        <p>Nous nous engageons à offrir :</p>
                        <ul class="about-list">
                            <li>✅ Des produits authentiques et de qualité supérieure</li>
                            <li>✅ Un service client exceptionnel</li>
                            <li>✅ Des prix compétitifs et transparents</li>
                            <li>✅ Une livraison rapide et sécurisée</li>
                        </ul>
                    </div>
                    <div class="about-image">
                        <div class="about-placeholder">🎨</div>
                    </div>
                </section>

                <section class="about-section">
                    <div class="about-image">
                        <div class="about-placeholder">⭐</div>
                    </div>
                    <div class="about-text">
                        <h2>Pourquoi nous choisir ?</h2>
                        <div class="features-grid">
                            <div class="feature-item">
                                <div class="feature-icon">🏆</div>
                                <h3>Qualité Garantie</h3>
                                <p>Tous nos tapis sont sélectionnés pour leur qualité exceptionnelle et leur authenticité.</p>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon">🚚</div>
                                <h3>Livraison Rapide</h3>
                                <p>Livraison gratuite à partir de 500 MAD partout au Maroc.</p>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon">↩️</div>
                                <h3>Retour Gratuit</h3>
                                <p>30 jours pour changer d'avis, retour gratuit et sans frais.</p>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon">💳</div>
                                <h3>Paiement Sécurisé</h3>
                                <p>Transactions 100% sécurisées pour votre tranquillité d'esprit.</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>


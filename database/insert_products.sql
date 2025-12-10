-- Script pour insérer 3 produits pour chaque catégorie
-- À exécuter après avoir créé les catégories

USE tapis_db;

-- Supprimer les anciens produits de test (optionnel)
-- DELETE FROM products WHERE id > 0;

-- Produits Classique (category_id = 2)
INSERT INTO products (name, slug, description, short_description, price, sale_price, category_id, material, size, color, stock, featured, best_seller, status) VALUES
('Tapis Persan Classique Isfahan', 'tapis-persan-isfahan', 'Magnifique tapis persan Isfahan aux motifs floraux raffinés. Tissé à la main avec de la soie et de la laine de qualité supérieure. Un véritable chef-d\'œuvre qui apportera élégance et sophistication à votre intérieur.', 'Tapis persan Isfahan authentique', 3499.00, 2999.00, 2, 'Soie et Laine', '300x400', 'Bleu et Crème', 5, 1, 1, 'active'),
('Tapis Persan Nain Classique', 'tapis-persan-nain', 'Tapis persan Nain de grande qualité, reconnu pour sa finesse et ses motifs géométriques élégants. Fabriqué par des artisans expérimentés, ce tapis est un investissement pour les générations futures.', 'Tapis persan Nain premium', 4299.00, NULL, 2, 'Soie et Laine', '250x350', 'Ivoire et Bleu', 4, 1, 0, 'active'),
('Tapis Persan Tabriz Classique', 'tapis-persan-tabriz', 'Superbe tapis persan Tabriz aux motifs centraux complexes et bordures ornementales. Tissé avec une densité élevée, ce tapis allie tradition et raffinement pour un intérieur d\'exception.', 'Tapis persan Tabriz traditionnel', 3899.00, 3299.00, 2, 'Soie et Laine', '280x380', 'Rouge et Or', 6, 1, 1, 'active'),

-- Produits Marocain (category_id = 5)
('Tapis Marocain Azilal Authentique', 'tapis-marocain-azilal', 'Tapis marocain Azilal authentique, tissé à la main par des femmes berbères. Caractérisé par ses motifs géométriques abstraits et ses couleurs vives, ce tapis apporte une touche d\'authenticité à votre décoration.', 'Tapis marocain Azilal artisanal', 1899.00, 1599.00, 5, 'Laine', '200x300', 'Multicolore', 10, 1, 1, 'active'),
('Tapis Marocain Boucherouite Moderne', 'tapis-marocain-boucherouite', 'Tapis marocain Boucherouite aux couleurs éclatantes et motifs modernes. Créé à partir de matériaux recyclés, ce tapis écologique allie style contemporain et tradition marocaine.', 'Tapis marocain Boucherouite écologique', 1299.00, NULL, 5, 'Laine recyclée', '180x250', 'Rouge, Jaune, Bleu', 15, 0, 0, 'active'),
('Tapis Marocain Taznakht Premium', 'tapis-marocain-taznakht', 'Luxueux tapis marocain Taznakht aux motifs géométriques complexes et couleurs terre. Tissé par des maîtres artisans, ce tapis représente l\'excellence de l\'artisanat marocain traditionnel.', 'Tapis marocain Taznakht de luxe', 2499.00, 2199.00, 5, 'Laine', '250x350', 'Terre et Beige', 8, 1, 1, 'active'),

-- Produits Moderne (category_id = 1)
('Tapis Moderne Minimaliste Gris', 'tapis-moderne-minimaliste-gris', 'Tapis moderne au design minimaliste et épuré. Parfait pour les intérieurs contemporains, ce tapis apporte douceur et élégance sans surcharger l\'espace. Matériaux de qualité supérieure.', 'Tapis moderne minimaliste', 799.00, 649.00, 1, 'Laine synthétique', '200x300', 'Gris', 20, 1, 1, 'active'),
('Tapis Moderne Géométrique Coloré', 'tapis-moderne-geometrique', 'Tapis moderne aux motifs géométriques audacieux et couleurs vives. Idéal pour donner du caractère à votre salon ou chambre. Design contemporain et tendance.', 'Tapis moderne géométrique', 999.00, NULL, 1, 'Laine et Coton', '180x250', 'Multicolore', 18, 1, 0, 'active'),
('Tapis Moderne Shaggy Épais', 'tapis-moderne-shaggy', 'Tapis moderne shaggy ultra-doux et confortable. Parfait pour créer une ambiance cosy et chaleureuse. Sa texture épaisse apporte un confort exceptionnel sous les pieds.', 'Tapis moderne shaggy confortable', 1199.00, 999.00, 1, 'Polyester', '200x300', 'Beige', 25, 1, 1, 'active'),

-- Produits Oriental (category_id = 3)
('Tapis Oriental Kashan Authentique', 'tapis-oriental-kashan', 'Magnifique tapis oriental Kashan aux motifs floraux élaborés et couleurs riches. Tissé à la main selon les traditions ancestrales, ce tapis est un véritable trésor pour votre intérieur.', 'Tapis oriental Kashan traditionnel', 2799.00, 2399.00, 3, 'Soie et Laine', '250x350', 'Rouge et Bleu', 7, 1, 1, 'active'),
('Tapis Oriental Qom en Soie', 'tapis-oriental-qom-soie', 'Luxueux tapis oriental Qom en soie pure. Reconnu pour sa finesse exceptionnelle et ses motifs délicats, ce tapis représente le summum de l\'artisanat oriental. Un investissement de prestige.', 'Tapis oriental Qom soie pure', 5499.00, NULL, 3, 'Soie', '200x300', 'Crème et Or', 3, 1, 0, 'active'),
('Tapis Oriental Heriz Traditionnel', 'tapis-oriental-heriz', 'Superbe tapis oriental Heriz aux motifs centraux imposants et bordures géométriques. Caractérisé par sa durabilité et ses couleurs éclatantes, ce tapis est parfait pour les espaces de vie.', 'Tapis oriental Heriz durable', 3299.00, 2899.00, 3, 'Laine', '280x380', 'Rouge et Bleu', 6, 1, 1, 'active'),

-- Produits Turc (category_id = 4)
('Tapis Turc Kilim Moderne', 'tapis-turk-kilim-moderne', 'Tapis turc Kilim aux motifs géométriques modernes et couleurs vives. Tissé à plat selon la technique traditionnelle, ce tapis apporte une touche d\'authenticité turque à votre décoration.', 'Tapis turc Kilim artisanal', 899.00, 749.00, 4, 'Laine', '150x250', 'Rouge, Bleu, Jaune', 12, 1, 1, 'active'),
('Tapis Turc Oushak Élégant', 'tapis-turk-oushak', 'Élégant tapis turc Oushak aux motifs floraux délicats et couleurs pastel. Reconnu pour son style raffiné, ce tapis s\'intègre parfaitement dans les intérieurs modernes et classiques.', 'Tapis turc Oushak raffiné', 2199.00, NULL, 4, 'Laine', '200x300', 'Beige et Rose', 9, 1, 0, 'active'),
('Tapis Turc Hereke de Luxe', 'tapis-turk-hereke', 'Luxueux tapis turc Hereke en soie et laine. Considéré comme l\'un des plus beaux tapis turcs, ce modèle allie finesse exceptionnelle et motifs complexes pour un résultat somptueux.', 'Tapis turc Hereke de prestige', 4599.00, 3999.00, 4, 'Soie et Laine', '250x350', 'Crème et Bleu', 4, 1, 1, 'active');


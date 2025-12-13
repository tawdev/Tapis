-- Base de données pour le site e-commerce de tapis
-- Créer la base de données : CREATE DATABASE tapis_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE tapis_db;

-- Table des catégories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(255) NULL COMMENT 'Chemin vers l\'image de la catégorie (ex: assets/images/categories/nom-image.jpg)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des types de catégories (Sous-catégories)
CREATE TABLE IF NOT EXISTS types_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL COMMENT 'Référence à la catégorie parente',
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255) NULL COMMENT 'Chemin vers l\'image du type de catégorie (ex: assets/images/types_categories/nom-image.jpg)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des types de catégories (Sous-catégories)
CREATE TABLE IF NOT EXISTS types_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL COMMENT 'Référence à la catégorie parente',
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255) NULL COMMENT 'Chemin vers l\'image du type de catégorie (ex: assets/images/types_categories/nom-image.jpg)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des produits
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    short_description VARCHAR(500),
    price DECIMAL(10, 2) NOT NULL,
    sale_price DECIMAL(10, 2) DEFAULT NULL,
    category_id INT NOT NULL,
    type_category_id INT NULL COMMENT 'Référence au type de catégorie (sous-catégorie)',
    material VARCHAR(100),
    size VARCHAR(50),
    color TEXT NULL COMMENT 'Couleurs du produit au format JSON: [{"name":"Rouge","index":1,"image":"path"},...] ou couleur simple (ancien format)',
    stock INT DEFAULT 0,
    featured BOOLEAN DEFAULT 0,
    best_seller BOOLEAN DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (type_category_id) REFERENCES types_categories(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_type_category (type_category_id),
    INDEX idx_status (status),
    INDEX idx_featured (featured),
    INDEX idx_best_seller (best_seller)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des images de produits
CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des commandes
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_address TEXT NOT NULL,
    customer_city VARCHAR(100) NOT NULL,
    customer_postal_code VARCHAR(20),
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_order_number (order_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des items de commande
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    length_cm DECIMAL(10, 2) NULL COMMENT 'Longueur en centimètres',
    width_cm DECIMAL(10, 2) NULL COMMENT 'Largeur en centimètres',
    surface_m2 DECIMAL(10, 4) NULL COMMENT 'Surface calculée en m²',
    unit_price DECIMAL(10, 2) NULL COMMENT 'Prix unitaire au m² au moment de la commande',
    calculated_price DECIMAL(10, 2) NULL COMMENT 'Prix calculé selon les dimensions (length × width × unit_price)',
    color VARCHAR(50) NULL COMMENT 'Couleur sélectionnée par le client',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id),
    INDEX idx_dimensions (length_cm, width_cm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des messages de contact
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion de données de test
-- Note: La colonne 'image' est optionnelle et peut être NULL
INSERT INTO categories (name, slug, description, image) VALUES
('Moderne', 'moderne', 'Tapis au design moderne et contemporain', NULL),
('Classique', 'classique', 'Tapis classiques intemporels', NULL),
('Oriental', 'oriental', 'Tapis orientaux authentiques', NULL),
('Turc', 'turk', 'Tapis turcs de qualité', NULL),
('Marocain', 'marocain', 'Tapis marocains traditionnels', NULL);

-- Insertion de produits de test
INSERT INTO products (name, slug, description, short_description, price, sale_price, category_id, material, size, color, stock, featured, best_seller) VALUES
('Tapis Moderne Élégant', 'tapis-moderne-elegant', 'Un magnifique tapis moderne qui apportera une touche d\'élégance à votre intérieur. Fabriqué avec des matériaux de qualité supérieure.', 'Tapis moderne élégant pour salon', 899.00, 699.00, 1, 'Laine', '200x300', 'Gris', 15, 1, 1),
('Tapis Oriental Traditionnel', 'tapis-oriental-traditionnel', 'Tapis oriental authentique aux motifs traditionnels. Parfait pour ajouter une touche d\'exotisme à votre décoration.', 'Tapis oriental traditionnel', 1299.00, NULL, 3, 'Soie et Laine', '250x350', 'Rouge', 8, 1, 0),
('Tapis Marocain Beni Ourain', 'tapis-marocain-beni-ourain', 'Tapis marocain Beni Ourain authentique, tissé à la main par des artisans marocains. Design minimaliste et élégant.', 'Tapis marocain Beni Ourain', 1599.00, 1299.00, 5, 'Laine', '200x300', 'Beige', 12, 1, 1),
('Tapis Turc Kilim', 'tapis-turk-kilim', 'Tapis turc Kilim aux couleurs vives et motifs géométriques. Idéal pour une décoration moderne et colorée.', 'Tapis turc Kilim', 799.00, NULL, 4, 'Laine', '150x250', 'Multicolore', 20, 0, 0),
('Tapis Classique Persan', 'tapis-classique-persan', 'Tapis persan classique aux motifs raffinés. Un investissement pour votre intérieur qui durera des générations.', 'Tapis persan classique', 2499.00, 1999.00, 2, 'Soie et Laine', '300x400', 'Bleu', 5, 1, 0);

-- Insertion d'images de test (les chemins seront mis à jour après l'upload)
INSERT INTO product_images (product_id, image_path, is_primary, display_order) VALUES
(1, 'assets/images/products/tapis-moderne-1.jpg', 1, 1),
(1, 'assets/images/products/tapis-moderne-2.jpg', 0, 2),
(2, 'assets/images/products/tapis-oriental-1.jpg', 1, 1),
(3, 'assets/images/products/tapis-marocain-1.jpg', 1, 1),
(4, 'assets/images/products/tapis-turk-1.jpg', 1, 1),
(5, 'assets/images/products/tapis-persan-1.jpg', 1, 1);

-- Insertion de données de test pour les types de catégories (Sous-catégories)
-- Note: La colonne 'image' est optionnelle et peut être NULL
INSERT INTO types_categories (category_id, name, description, image) VALUES
(1, 'Tapis Moderne Minimaliste', 'Tapis modernes avec design minimaliste et épuré', NULL),
(1, 'Tapis Moderne Coloré', 'Tapis modernes aux couleurs vives et contemporaines', NULL),
(2, 'Tapis Classique Persan', 'Tapis persans classiques traditionnels aux motifs raffinés', NULL),
(2, 'Tapis Classique Européen', 'Tapis classiques de style européen élégant', NULL),
(3, 'Tapis Oriental Traditionnel', 'Tapis orientaux aux motifs traditionnels authentiques', NULL),
(5, 'Tapis Marocain Beni Ourain', 'Tapis marocains Beni Ourain authentiques tissés à la main', NULL),
(5, 'Tapis Marocain Azilal', 'Tapis marocains Azilal colorés et vibrants', NULL);


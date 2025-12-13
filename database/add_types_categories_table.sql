-- Migration : Créer la table types_categories (Sous-catégories)
-- Ce script crée la table pour les types de catégories liés aux catégories principales

USE tapis_db;

-- Créer la table types_categories
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

-- Vérification : Afficher la structure de la table
DESCRIBE types_categories;


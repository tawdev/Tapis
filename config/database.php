<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'tapis_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuration du site
define('SITE_URL', 'http://localhost/Tapis');
define('SITE_NAME', 'Tapis - Votre Boutique de Tapis de Luxe');

// Configuration des chemins
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/assets/images/products/');
define('UPLOAD_URL', SITE_URL . '/assets/images/products/');

// Configuration admin
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // À changer en production

// Connexion à la base de données avec PDO
class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch(PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}

// Fonction helper pour obtenir la connexion
function getDB() {
    return Database::getInstance()->getConnection();
}


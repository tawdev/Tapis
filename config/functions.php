<?php
// Fonctions utilitaires

// Protection contre XSS
function clean($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Génération de slug
function generateSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// Formatage du prix
function formatPrice($price) {
    return number_format($price, 2, ',', ' ') . ' MAD';
}

// Génération de numéro de commande
function generateOrderNumber() {
    return 'TAP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Validation email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Upload d'image
function uploadImage($file, $productId = null) {
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'message' => 'Format d\'image non autorisé'];
    }
    
    if ($file['size'] > 5000000) { // 5MB
        return ['success' => false, 'message' => 'Fichier trop volumineux'];
    }
    
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $targetPath = UPLOAD_PATH . $filename;
    
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0777, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename, 'path' => 'assets/images/products/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Erreur lors de l\'upload'];
}

// Suppression d'image
function deleteImage($imagePath) {
    $fullPath = ROOT_PATH . '/' . $imagePath;
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
}

// Pagination
function getPagination($currentPage, $totalPages, $baseUrl) {
    $html = '<div class="pagination">';
    
    if ($currentPage > 1) {
        $html .= '<a href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">Précédent</a>';
    }
    
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $currentPage) {
            $html .= '<span class="active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a>';
        }
    }
    
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">Suivant</a>';
    }
    
    $html .= '</div>';
    return $html;
}

// Vérification session admin
function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Redirection
function redirect($url) {
    header("Location: " . $url);
    exit();
}


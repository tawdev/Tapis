<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Rediriger vers login si non connecté
if (!isAdmin()) {
    redirect('login.php');
}

$db = getDB();

// Statistiques
$stmt = $db->query("SELECT COUNT(*) as total FROM products");
$totalProducts = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM orders");
$totalOrders = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$pendingOrders = $stmt->fetch()['total'];

$stmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'delivered'");
$totalRevenue = $stmt->fetch()['total'] ?: 0;

// Nouveaux messages de contact
try {
    // Vérifier si la table existe
    $db->exec("CREATE TABLE IF NOT EXISTS contact_messages (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages WHERE status = 'new'");
    $newMessages = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $newMessages = 0;
}

// Dernières commandes
$stmt = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5");
$recentOrders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <h1>Tableau de bord</h1>

            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-content">
                        <h3><?php echo $totalProducts; ?></h3>
                        <p>Produits</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-content">
                        <h3><?php echo $totalOrders; ?></h3>
                        <p>Commandes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-content">
                        <h3><?php echo $pendingOrders; ?></h3>
                        <p>En attente</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-content">
                        <h3><?php echo formatPrice($totalRevenue); ?></h3>
                        <p>Chiffre d'affaires</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📧</div>
                    <div class="stat-content">
                        <h3><?php echo $newMessages; ?></h3>
                        <p>Nouveaux messages</p>
                    </div>
                    <?php if ($newMessages > 0): ?>
                        <a href="messages.php" class="stat-link">Voir les messages</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dernières commandes -->
            <div class="admin-section">
                <h2>Dernières commandes</h2>
                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Numéro</th>
                                <th>Client</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentOrders) > 0): ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><?php echo clean($order['order_number']); ?></td>
                                        <td><?php echo clean($order['customer_name']); ?></td>
                                        <td><?php echo formatPrice($order['total_amount']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php
                                                $statusLabels = [
                                                    'pending' => 'En attente',
                                                    'processing' => 'En traitement',
                                                    'shipped' => 'Expédiée',
                                                    'delivered' => 'Livrée',
                                                    'cancelled' => 'Annulée'
                                                ];
                                                echo $statusLabels[$order['status']] ?? $order['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <a href="order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">Voir</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">Aucune commande</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <a href="orders.php" class="btn btn-secondary">Voir toutes les commandes</a>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>


<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$db = getDB();

// Filtre par statut
$statusFilter = isset($_GET['status']) ? clean($_GET['status']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Construction de la requête
$where = [];
$params = [];

if ($statusFilter) {
    $where[] = "status = :status";
    $params[':status'] = $statusFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Compter le total
$countSql = "SELECT COUNT(*) as total FROM orders $whereClause";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

// Récupérer les commandes
$sql = "SELECT * FROM orders $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <h1>Gestion des Commandes</h1>

            <!-- Filtres -->
            <div class="admin-filters">
                <a href="orders.php" class="filter-btn <?php echo !$statusFilter ? 'active' : ''; ?>">Toutes</a>
                <a href="orders.php?status=pending" class="filter-btn <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">En attente</a>
                <a href="orders.php?status=processing" class="filter-btn <?php echo $statusFilter === 'processing' ? 'active' : ''; ?>">En traitement</a>
                <a href="orders.php?status=shipped" class="filter-btn <?php echo $statusFilter === 'shipped' ? 'active' : ''; ?>">Expédiées</a>
                <a href="orders.php?status=delivered" class="filter-btn <?php echo $statusFilter === 'delivered' ? 'active' : ''; ?>">Livrées</a>
            </div>

            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Numéro</th>
                            <th>Client</th>
                            <th>Email</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo clean($order['order_number']); ?></td>
                                    <td><?php echo clean($order['customer_name']); ?></td>
                                    <td><?php echo clean($order['customer_email']); ?></td>
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
                                <td colspan="7">Aucune commande</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination-wrapper">
                    <?php
                    $baseUrl = 'orders.php' . ($statusFilter ? '?status=' . $statusFilter : '');
                    echo getPagination($page, $totalPages, $baseUrl);
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>


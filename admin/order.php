<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($orderId == 0) {
    redirect('orders.php');
}

$db = getDB();

// Récupérer la commande
$stmt = $db->prepare("SELECT * FROM orders WHERE id = :id");
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    redirect('orders.php');
}

// Récupérer les items
$stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = :id");
$stmt->execute([':id' => $orderId]);
$orderItems = $stmt->fetchAll();

// Mise à jour du statut
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = clean($_POST['status']);
    $stmt = $db->prepare("UPDATE orders SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $newStatus, ':id' => $orderId]);
    $order['status'] = $newStatus;
    $success = true;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande #<?php echo clean($order['order_number']); ?> - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <div class="admin-page-header">
                <h1>Commande #<?php echo clean($order['order_number']); ?></h1>
                <a href="orders.php" class="btn btn-secondary">← Retour</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">Statut mis à jour avec succès !</div>
            <?php endif; ?>

            <div class="order-details-admin">
                <div class="order-info-grid">
                    <div class="order-section">
                        <h2>Informations de livraison</h2>
                        <p><strong><?php echo clean($order['customer_name']); ?></strong></p>
                        <p><?php echo clean($order['customer_address']); ?></p>
                        <p><?php echo clean($order['customer_city']); ?></p>
                        <?php if ($order['customer_postal_code']): ?>
                            <p>Code postal: <?php echo clean($order['customer_postal_code']); ?></p>
                        <?php endif; ?>
                        <p>Téléphone: <?php echo clean($order['customer_phone']); ?></p>
                        <p>Email: <?php echo clean($order['customer_email']); ?></p>
                    </div>

                    <div class="order-section">
                        <h2>Statut de la commande</h2>
                        <form method="POST" class="status-form">
                            <div class="form-group">
                                <select name="status" class="status-select">
                                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                    <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>En traitement</option>
                                    <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Expédiée</option>
                                    <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Livrée</option>
                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                                </select>
                            </div>
                            <button type="submit" name="update_status" class="btn btn-primary">Mettre à jour</button>
                        </form>
                    </div>
                </div>

                <div class="order-section">
                    <h2>Détails de la commande</h2>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Quantité</th>
                                <th>Prix unitaire</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td><?php echo clean($item['product_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo formatPrice($item['product_price']); ?></td>
                                    <td><?php echo formatPrice($item['subtotal']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="order-total">
                                <td colspan="3"><strong>Total</strong></td>
                                <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php if ($order['notes']): ?>
                    <div class="order-section">
                        <h2>Notes</h2>
                        <p><?php echo nl2br(clean($order['notes'])); ?></p>
                    </div>
                <?php endif; ?>

                <div class="order-section">
                    <p><strong>Date de commande:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                    <?php if ($order['updated_at'] != $order['created_at']): ?>
                        <p><strong>Dernière mise à jour:</strong> <?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>


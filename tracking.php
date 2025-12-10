<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

$orderNumber = isset($_GET['order']) ? clean($_GET['order']) : '';

$order = null;
$orderItems = [];

if ($orderNumber) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = :order_number");
    $stmt->execute([':order_number' => $orderNumber]);
    $order = $stmt->fetch();

    if ($order) {
        $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $order['id']]);
        $orderItems = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi de Commande - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="tracking-page">
        <div class="container">
            <h1>Suivi de Commande</h1>

            <?php if (!$orderNumber): ?>
                <div class="tracking-form">
                    <form method="GET" action="tracking.php">
                        <div class="form-group">
                            <label for="order">Numéro de commande</label>
                            <input type="text" id="order" name="order" placeholder="Ex: TAP-20241201-ABC123" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                    </form>
                </div>
            <?php elseif (!$order): ?>
                <div class="alert alert-error">
                    <p>Aucune commande trouvée avec ce numéro.</p>
                </div>
                <a href="tracking.php" class="btn btn-secondary">Nouvelle recherche</a>
            <?php else: ?>
                <div class="order-details">
                    <div class="order-header">
                        <div>
                            <h2>Commande #<?php echo clean($order['order_number']); ?></h2>
                            <p class="order-date">Date: <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                        </div>
                        <div class="order-status">
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
                        </div>
                    </div>

                    <!-- Timeline du statut -->
                    <div class="order-timeline">
                        <div class="timeline-item <?php echo in_array($order['status'], ['pending', 'processing', 'shipped', 'delivered']) ? 'active' : ''; ?>">
                            <div class="timeline-icon">1</div>
                            <div class="timeline-content">
                                <h3>Commande reçue</h3>
                                <p>Votre commande a été enregistrée</p>
                            </div>
                        </div>
                        <div class="timeline-item <?php echo in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'active' : ''; ?>">
                            <div class="timeline-icon">2</div>
                            <div class="timeline-content">
                                <h3>En traitement</h3>
                                <p>Votre commande est en cours de préparation</p>
                            </div>
                        </div>
                        <div class="timeline-item <?php echo in_array($order['status'], ['shipped', 'delivered']) ? 'active' : ''; ?>">
                            <div class="timeline-icon">3</div>
                            <div class="timeline-content">
                                <h3>Expédiée</h3>
                                <p>Votre commande a été expédiée</p>
                            </div>
                        </div>
                        <div class="timeline-item <?php echo $order['status'] === 'delivered' ? 'active' : ''; ?>">
                            <div class="timeline-icon">4</div>
                            <div class="timeline-content">
                                <h3>Livrée</h3>
                                <p>Votre commande a été livrée</p>
                            </div>
                        </div>
                    </div>

                    <div class="order-info-grid">
                        <div class="order-section">
                            <h3>Informations de livraison</h3>
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
                            <h3>Détails de la commande</h3>
                            <table class="order-items-table">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Quantité</th>
                                        <th>Prix</th>
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
                    </div>

                    <?php if ($order['notes']): ?>
                        <div class="order-notes">
                            <h3>Notes</h3>
                            <p><?php echo nl2br(clean($order['notes'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="tracking-actions">
                        <a href="tracking.php" class="btn btn-secondary">Nouvelle recherche</a>
                        <a href="products.php" class="btn btn-primary">Continuer les achats</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>


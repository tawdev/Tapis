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

// Récupérer les items avec les images des produits et la couleur
$stmt = $db->prepare("SELECT oi.*, 
                      (SELECT image_path FROM product_images WHERE product_id = oi.product_id AND is_primary = 1 LIMIT 1) as product_image
                      FROM order_items oi 
                      WHERE oi.order_id = :id");
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
                <div>
                    <h1>Commande #<?php echo clean($order['order_number']); ?></h1>
                    <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.75rem;">
                        <span class="status-badge status-<?php echo $order['status']; ?>" style="font-size: 0.95rem; padding: 0.7rem 1.3rem;">
                            <?php
                            $statusLabels = [
                                'pending' => '⏳ En attente',
                                'processing' => '⚙️ En traitement',
                                'shipped' => '🚚 Expédiée',
                                'delivered' => '✅ Livrée',
                                'cancelled' => '❌ Annulée'
                            ];
                            echo $statusLabels[$order['status']] ?? $order['status'];
                            ?>
                        </span>
                        <span style="color: var(--text-light); font-size: 0.95rem;">
                            📅 <?php echo date('d/m/Y à H:i', strtotime($order['created_at'])); ?>
                        </span>
                        <span style="color: var(--primary-color); font-weight: 700; font-size: 1.2rem;">
                            💰 <?php echo formatPrice($order['total_amount']); ?>
                        </span>
                    </div>
                </div>
                <a href="orders.php" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem;">
                    <span>←</span> Retour à la liste
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success" style="display: flex; align-items: center; gap: 0.5rem;">
                    <span>✅</span> Statut mis à jour avec succès !
                </div>
            <?php endif; ?>

            <div class="order-details-admin">
                <div class="order-info-grid">
                    <div class="order-section">
                        <h2 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                            <span>📍</span> Informations de livraison
                        </h2>
                        <div class="info-item">
                            <strong style="display: block; margin-bottom: 0.5rem; color: var(--primary-color); font-size: 1.1rem;">
                                <?php echo clean($order['customer_name']); ?>
                            </strong>
                            <p style="margin: 0.5rem 0; line-height: 1.8;">
                                <span style="color: var(--text-light);">📍</span> <?php echo clean($order['customer_address']); ?><br>
                                <span style="color: var(--text-light);">🏙️</span> <?php echo clean($order['customer_city']); ?>
                                <?php if ($order['customer_postal_code']): ?>
                                    - <?php echo clean($order['customer_postal_code']); ?>
                                <?php endif; ?>
                            </p>
                            <p style="margin: 0.5rem 0;">
                                <span style="color: var(--text-light);">📞</span> 
                                <a href="tel:<?php echo clean($order['customer_phone']); ?>" style="color: var(--primary-color); text-decoration: none;">
                                    <?php echo clean($order['customer_phone']); ?>
                                </a>
                            </p>
                            <p style="margin: 0.5rem 0;">
                                <span style="color: var(--text-light);">✉️</span> 
                                <a href="mailto:<?php echo clean($order['customer_email']); ?>" style="color: var(--primary-color); text-decoration: none;">
                                    <?php echo clean($order['customer_email']); ?>
                                </a>
                            </p>
                        </div>
                    </div>

                    <div class="order-section">
                        <h2 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                            <span>⚙️</span> Gestion du statut
                        </h2>
                        <form method="POST" class="status-form">
                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">
                                    Modifier le statut de la commande
                                </label>
                                <select name="status" class="status-select">
                                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>⏳ En attente</option>
                                    <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>⚙️ En traitement</option>
                                    <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>🚚 Expédiée</option>
                                    <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>✅ Livrée</option>
                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>❌ Annulée</option>
                                </select>
                            </div>
                            <button type="submit" name="update_status" class="btn btn-primary" style="width: 100%;">
                                💾 Mettre à jour le statut
                            </button>
                        </form>
                    </div>
                </div>

                <div class="order-section">
                    <h2 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                        <span>🛒</span> Détails de la commande
                        <span style="margin-left: auto; font-size: 0.9rem; font-weight: 500; color: var(--text-light);">
                            <?php echo count($orderItems); ?> article<?php echo count($orderItems) > 1 ? 's' : ''; ?>
                        </span>
                    </h2>
                    <div class="admin-table-container">
                        <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Image</th>
                                <th>Produit</th>
                                <th>Dimensions</th>
                                <th>Quantité</th>
                                <th>Prix unitaire</th>
                                <th>Prix calculé</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($item['product_image'])): ?>
                                            <img src="../<?php echo clean($item['product_image']); ?>" 
                                                 alt="<?php echo clean($item['product_name']); ?>" 
                                                 class="table-image"
                                                 style="width: 70px; height: 70px; object-fit: cover; border-radius: 8px; border: 2px solid var(--border-color);"
                                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'70\' height=\'70\'%3E%3Crect width=\'70\' height=\'70\' fill=\'%23f0f0f0\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\' font-size=\'12\'%3ENo Image%3C/text%3E%3C/svg%3E';">
                                        <?php else: ?>
                                            <div class="table-image-placeholder" style="width: 70px; height: 70px; background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--text-light); border: 2px solid var(--border-color); font-size: 0.8rem;">
                                                No Image
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                            <strong style="color: var(--text-dark); font-size: 1.05rem;">
                                                <?php echo clean($item['product_name']); ?>
                                            </strong>
                                            <?php if ($item['length_cm'] && $item['width_cm']): ?>
                                                <span style="display: inline-block; padding: 0.4rem 0.8rem; background: rgba(139,69,19,0.1); border-radius: 6px; color: var(--primary-color); font-size: 0.85rem; font-weight: 600;">
                                                    📐 Surface: <?php echo number_format($item['surface_m2'], 2, ',', ' '); ?> m²
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($item['color']) && trim($item['color']) !== ''): ?>
                                                <span style="display: inline-block; padding: 0.5rem 1rem; background: linear-gradient(135deg, rgba(139,69,19,0.15) 0%, rgba(139,69,19,0.1) 100%); border-radius: 8px; border: 2px solid rgba(139,69,19,0.3); color: var(--primary-color); font-size: 0.9rem; font-weight: 700; margin-top: 0.5rem;">
                                                    🎨 Couleur: <strong><?php echo clean($item['color']); ?></strong>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($item['length_cm'] && $item['width_cm']): ?>
                                            <div style="display: inline-block; padding: 0.5rem 1rem; background: linear-gradient(135deg, rgba(139,69,19,0.1) 0%, rgba(139,69,19,0.05) 100%); border-radius: 8px; border: 2px solid var(--primary-color);">
                                                <strong style="color: var(--primary-color); font-size: 1rem;">
                                                    <?php echo round($item['length_cm']); ?> cm × <?php echo round($item['width_cm']); ?> cm
                                                </strong>
                                            </div>
                                        <?php else: ?>
                                            <span style="padding: 0.5rem 1rem; background: var(--light-color); border-radius: 8px; color: var(--text-light); font-weight: 500;">
                                                Standard
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="display: inline-block; padding: 0.5rem 1rem; background: var(--light-color); border-radius: 8px; font-weight: 700; font-size: 1.1rem; color: var(--text-dark);">
                                            <?php echo $item['quantity']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                            <strong style="color: var(--text-dark);">
                                                <?php echo formatPrice($item['product_price']); ?>
                                            </strong>
                                            <?php if ($item['unit_price']): ?>
                                                <small style="color: var(--text-light); font-size: 0.85rem;">
                                                    / m²
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($item['calculated_price']): ?>
                                            <strong style="display: inline-block; padding: 0.5rem 1rem; background: linear-gradient(135deg, rgba(139,69,19,0.15) 0%, rgba(139,69,19,0.1) 100%); border-radius: 8px; color: var(--primary-color); font-size: 1.05rem;">
                                                <?php echo formatPrice($item['calculated_price']); ?>
                                            </strong>
                                        <?php else: ?>
                                            <span style="color: var(--text-light);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color: var(--primary-color); font-size: 1.15rem;">
                                            <?php echo formatPrice($item['subtotal']); ?>
                                        </strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="order-total">
                                <td colspan="6"><strong>Total</strong></td>
                                <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                            </tr>
                        </tfoot>
                        </table>
                    </div>
                </div>

                <?php if ($order['notes']): ?>
                    <div class="order-section">
                        <h2 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                            <span>📝</span> Notes de la commande
                        </h2>
                        <div style="background: var(--white); padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                            <p style="margin: 0; line-height: 1.8; color: var(--text-dark);"><?php echo nl2br(clean($order['notes'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="order-section">
                    <h2 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                        <span>📊</span> Informations de suivi
                    </h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <div style="background: var(--white); padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                            <p style="margin: 0; color: var(--text-light); font-size: 0.9rem; margin-bottom: 0.5rem;">Date de commande</p>
                            <p style="margin: 0; font-weight: 700; color: var(--text-dark); font-size: 1.1rem;">
                                📅 <?php echo date('d/m/Y à H:i', strtotime($order['created_at'])); ?>
                            </p>
                        </div>
                        <?php if ($order['updated_at'] != $order['created_at']): ?>
                            <div style="background: var(--white); padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--accent-color);">
                                <p style="margin: 0; color: var(--text-light); font-size: 0.9rem; margin-bottom: 0.5rem;">Dernière mise à jour</p>
                                <p style="margin: 0; font-weight: 700; color: var(--text-dark); font-size: 1.1rem;">
                                    🔄 <?php echo date('d/m/Y à H:i', strtotime($order['updated_at'])); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        <div style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%); padding: 1.5rem; border-radius: 8px; color: var(--white);">
                            <p style="margin: 0; opacity: 0.9; font-size: 0.9rem; margin-bottom: 0.5rem;">Montant total</p>
                            <p style="margin: 0; font-weight: 700; font-size: 1.5rem;">
                                <?php echo formatPrice($order['total_amount']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>


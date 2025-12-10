<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Rediriger vers login si non connecté
if (!isAdmin()) {
    redirect('login.php');
}

$db = getDB();

// Vérifier si la table existe
try {
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
} catch (PDOException $e) {
    // Table existe déjà ou erreur
}

// Récupérer le message
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("SELECT * FROM contact_messages WHERE id = ?");
$stmt->execute([$id]);
$message = $stmt->fetch();

if (!$message) {
    redirect('messages.php');
}

// Marquer comme lu si nouveau
if ($message['status'] === 'new') {
    $stmt = $db->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
    $stmt->execute([$id]);
    $message['status'] = 'read';
}

// Gérer les actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'replied') {
        $stmt = $db->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ?");
        $stmt->execute([$id]);
        redirect('message.php?id=' . $id . '&success=1');
    } elseif ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$id]);
        redirect('messages.php?success=2');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message #<?php echo $message['id']; ?> - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header-admin">
                <h1>Message de contact #<?php echo $message['id']; ?></h1>
                <a href="messages.php" class="btn btn-secondary">← Retour aux messages</a>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    Message marqué comme répondu.
                </div>
            <?php endif; ?>

            <div class="message-detail-card">
                <div class="message-header">
                    <div class="message-info">
                        <h2><?php echo clean($message['name']); ?></h2>
                        <p class="message-meta">
                            <strong>Email:</strong> <a href="mailto:<?php echo clean($message['email']); ?>"><?php echo clean($message['email']); ?></a>
                            <?php if ($message['phone']): ?>
                                | <strong>Téléphone:</strong> <a href="tel:<?php echo clean($message['phone']); ?>"><?php echo clean($message['phone']); ?></a>
                            <?php endif; ?>
                        </p>
                        <p class="message-meta">
                            <strong>Date:</strong> <?php echo date('d/m/Y à H:i', strtotime($message['created_at'])); ?>
                            | <strong>Statut:</strong> 
                            <?php
                            $statusLabels = [
                                'new' => ['label' => 'Nouveau', 'class' => 'badge-warning'],
                                'read' => ['label' => 'Lu', 'class' => 'badge-info'],
                                'replied' => ['label' => 'Répondu', 'class' => 'badge-success']
                            ];
                            $status = $statusLabels[$message['status']];
                            ?>
                            <span class="badge <?php echo $status['class']; ?>"><?php echo $status['label']; ?></span>
                        </p>
                    </div>
                </div>

                <div class="message-content">
                    <div class="message-field">
                        <label>Sujet:</label>
                        <div class="message-value"><?php echo clean($message['subject']); ?></div>
                    </div>

                    <div class="message-field">
                        <label>Message:</label>
                        <div class="message-text"><?php echo nl2br(clean($message['message'])); ?></div>
                    </div>
                </div>

                <div class="message-actions">
                    <a href="mailto:<?php echo clean($message['email']); ?>?subject=Re: <?php echo urlencode($message['subject']); ?>" 
                       class="btn btn-primary" target="_blank">
                        📧 Répondre par email
                    </a>
                    <?php if ($message['status'] !== 'replied'): ?>
                        <a href="?action=replied&id=<?php echo $message['id']; ?>" class="btn btn-success">
                            ✓ Marquer comme répondu
                        </a>
                    <?php endif; ?>
                    <a href="?action=delete&id=<?php echo $message['id']; ?>" 
                       class="btn btn-danger"
                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce message ?');">
                        🗑️ Supprimer
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>


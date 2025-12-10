<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Rediriger vers login si non connecté
if (!isAdmin()) {
    redirect('login.php');
}

$db = getDB();

// Vérifier si la table existe, sinon la créer
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

// Gérer les actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    if ($action === 'read') {
        $stmt = $db->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
        $stmt->execute([$id]);
        redirect('messages.php?success=1');
    } elseif ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$id]);
        redirect('messages.php?success=2');
    }
}

// Filtrer par statut
$statusFilter = $_GET['status'] ?? 'all';
$statusQuery = '';
if ($statusFilter !== 'all') {
    $statusQuery = "WHERE status = " . $db->quote($statusFilter);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Compter les messages
$stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages $statusQuery");
$totalMessages = $stmt->fetch()['total'];
$totalPages = ceil($totalMessages / $perPage);

// Récupérer les messages
$stmt = $db->query("SELECT * FROM contact_messages $statusQuery ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$messages = $stmt->fetchAll();

// Statistiques
$stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages WHERE status = 'new'");
$newMessages = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages de contact - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header-admin">
                <h1>Messages de contact</h1>
                <?php if ($newMessages > 0): ?>
                    <span class="badge badge-warning"><?php echo $newMessages; ?> nouveau(x)</span>
                <?php endif; ?>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php if ($_GET['success'] == 1): ?>
                        Message marqué comme lu.
                    <?php elseif ($_GET['success'] == 2): ?>
                        Message supprimé avec succès.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Filtres -->
            <div class="admin-filters">
                <a href="?status=all" class="filter-btn <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                    Tous (<?php 
                        $stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages");
                        echo $stmt->fetch()['total'];
                    ?>)
                </a>
                <a href="?status=new" class="filter-btn <?php echo $statusFilter === 'new' ? 'active' : ''; ?>">
                    Nouveaux (<?php echo $newMessages; ?>)
                </a>
                <a href="?status=read" class="filter-btn <?php echo $statusFilter === 'read' ? 'active' : ''; ?>">
                    Lus (<?php 
                        $stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages WHERE status = 'read'");
                        echo $stmt->fetch()['total'];
                    ?>)
                </a>
                <a href="?status=replied" class="filter-btn <?php echo $statusFilter === 'replied' ? 'active' : ''; ?>">
                    Répondu (<?php 
                        $stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages WHERE status = 'replied'");
                        echo $stmt->fetch()['total'];
                    ?>)
                </a>
            </div>

            <!-- Table des messages -->
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Sujet</th>
                            <th>Message</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($messages)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Aucun message trouvé.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <tr class="<?php echo $message['status'] === 'new' ? 'new-message' : ''; ?>">
                                    <td><?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?></td>
                                    <td><strong><?php echo clean($message['name']); ?></strong></td>
                                    <td><?php echo clean($message['email']); ?></td>
                                    <td><?php echo $message['phone'] ? clean($message['phone']) : '-'; ?></td>
                                    <td><?php echo clean($message['subject']); ?></td>
                                    <td>
                                        <div class="message-preview">
                                            <?php echo clean(substr($message['message'], 0, 100)); ?>
                                            <?php if (strlen($message['message']) > 100): ?>...<?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $statusLabels = [
                                            'new' => ['label' => 'Nouveau', 'class' => 'badge-warning'],
                                            'read' => ['label' => 'Lu', 'class' => 'badge-info'],
                                            'replied' => ['label' => 'Répondu', 'class' => 'badge-success']
                                        ];
                                        $status = $statusLabels[$message['status']];
                                        ?>
                                        <span class="badge <?php echo $status['class']; ?>"><?php echo $status['label']; ?></span>
                                    </td>
                                    <td>
                                        <div class="admin-actions">
                                            <a href="message.php?id=<?php echo $message['id']; ?>" class="btn btn-sm btn-primary" title="Voir le message">👁️</a>
                                            <?php if ($message['status'] === 'new'): ?>
                                                <a href="?action=read&id=<?php echo $message['id']; ?>&status=<?php echo $statusFilter; ?>" class="btn btn-sm btn-info" title="Marquer comme lu">✓</a>
                                            <?php endif; ?>
                                            <a href="?action=delete&id=<?php echo $message['id']; ?>&status=<?php echo $statusFilter; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Supprimer"
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce message ?');">🗑️</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>" class="pagination-btn">← Précédent</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>" 
                           class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>" class="pagination-btn">Suivant →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>


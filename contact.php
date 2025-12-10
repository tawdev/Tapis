<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validation
    if (empty($name)) $errors[] = "Le nom est requis";
    if (empty($email) || !isValidEmail($email)) $errors[] = "Email valide requis";
    if (empty($subject)) $errors[] = "Le sujet est requis";
    if (empty($message)) $errors[] = "Le message est requis";

    if (empty($errors)) {
        try {
            // Vérifier si la table existe, sinon la créer
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
            
            // Enregistrer le message en base de données
            $stmt = $db->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone ?: null, $subject, $message]);
            $success = true;
        } catch (PDOException $e) {
            $errors[] = "Une erreur est survenue. Veuillez réessayer plus tard.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="contact-page">
        <div class="container">
            <div class="page-header">
                <h1>Contactez-nous</h1>
                <p>Nous sommes là pour répondre à toutes vos questions</p>
            </div>

            <div class="contact-layout">
                <div class="contact-info">
                    <h2>Informations de contact</h2>
                    <div class="contact-item">
                        <div class="contact-icon">📧</div>
                        <div>
                            <h3>Email</h3>
                            <p>contact@tapis.ma</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">📞</div>
                        <div>
                            <h3>Téléphone</h3>
                            <p>+212 674-862173</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">📍</div>
                        <div>
                            <h3>Adresse</h3>
                            <p>N, TAW10, lot Iguder, 48 AV Alla El Fassi<br>Marrakech 40000, Morocco</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">🕒</div>
                        <div>
                            <h3>Heures d'ouverture</h3>
                            <p>Lundi - Vendredi: 9h - 18h<br>Samedi: 10h - 16h</p>
                        </div>
                    </div>
                </div>

                <div class="contact-form-wrapper">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <strong>Message envoyé avec succès !</strong>
                            <p>Nous vous répondrons dans les plus brefs délais.</p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo clean($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="contact-form" id="contact-form">
                        <h2>Envoyez-nous un message</h2>
                        
                        <div class="form-group">
                            <label for="name">Nom complet *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo isset($_POST['name']) ? clean($_POST['name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required
                                   value="<?php echo isset($_POST['email']) ? clean($_POST['email']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone">Téléphone</label>
                            <input type="tel" id="phone" name="phone"
                                   value="<?php echo isset($_POST['phone']) ? clean($_POST['phone']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="subject">Sujet *</label>
                            <select id="subject" name="subject" required>
                                <option value="">Sélectionner un sujet</option>
                                <option value="question" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'question') ? 'selected' : ''; ?>>Question sur un produit</option>
                                <option value="order" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'order') ? 'selected' : ''; ?>>Suivi de commande</option>
                                <option value="return" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'return') ? 'selected' : ''; ?>>Retour/Échange</option>
                                <option value="other" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'other') ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="6" required><?php echo isset($_POST['message']) ? clean($_POST['message']) : ''; ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-large btn-block">Envoyer le message</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>


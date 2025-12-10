<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Si déjà connecté, rediriger vers le dashboard
if (isAdmin()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        redirect('index.php');
    } else {
        $error = 'Identifiants incorrects';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body admin-login">
    <div class="login-container">
        <div class="login-box">
            <h1>Connexion Admin</h1>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo clean($error); ?></div>
            <?php endif; ?>
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
            </form>
            <a href="../index.php" class="back-link">← Retour au site</a>
        </div>
    </div>
</body>
</html>


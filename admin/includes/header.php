<?php
$current_page = basename($_SERVER['PHP_SELF']);
$pages = [
    'index.php' => 'Dashboard',
    'products.php' => 'Produits',
    'product_form.php' => 'Produits',
    'categories.php' => 'Catégories',
    'orders.php' => 'Commandes',
    'order.php' => 'Commandes',
    'messages.php' => 'Messages',
    'message.php' => 'Messages'
];
$active_section = $pages[$current_page] ?? '';
?>
<header class="admin-header">
    <div class="admin-header-content">
        <div class="admin-logo">
            <a href="index.php">Admin - Tapis</a>
        </div>
        <nav class="admin-nav">
            <ul>
                <li><a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">Dashboard</a></li>
                <li><a href="products.php" class="<?php echo (in_array($current_page, ['products.php', 'product_form.php'])) ? 'active' : ''; ?>">Produits</a></li>
                <li><a href="categories.php" class="<?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>">Catégories</a></li>
                <li><a href="orders.php" class="<?php echo (in_array($current_page, ['orders.php', 'order.php'])) ? 'active' : ''; ?>">Commandes</a></li>
                <li><a href="messages.php" class="<?php echo (in_array($current_page, ['messages.php', 'message.php'])) ? 'active' : ''; ?>">Messages</a></li>
            </ul>
        </nav>
        <div class="admin-header-actions">
            <a href="../index.php" target="_blank" title="Ouvrir le site dans un nouvel onglet">🌐 Voir le site</a>
            <a href="logout.php">🚪 Déconnexion</a>
        </div>
    </div>
</header>


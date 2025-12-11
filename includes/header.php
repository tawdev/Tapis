<?php
// Compter le total des articles dans le panier (somme des quantités)
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        $cartCount += (int)$qty;
    }
}
?>
<header class="header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="index.php">
                    <h1>waootapis</h1>
                </a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="products.php">Produits</a></li>
                    <li><a href="categories.php">Catégories</a></li>
                    <li><a href="about.php">À propos</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </nav>
            <div class="header-actions">
                <a href="cart.php" class="cart-icon">
                    <span>🛒</span>
                    <span class="cart-badge" id="cart-badge"><?php echo $cartCount; ?></span>
                </a>
                
            </div>
            <button class="mobile-menu-toggle">☰</button>
        </div>
    </div>
</header>


// Main JavaScript File

// Notification System
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? '#28a745' : '#dc3545'};
        color: white;
        border-radius: 5px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add to Cart with AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Handle add to cart buttons
    const addToCartButtons = document.querySelectorAll('.btn-add-cart, #add-to-cart-btn');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            const quantityInput = document.getElementById('product-quantity');
            const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
            
            if (!productId) return;
            
            // Disable button during request
            this.disabled = true;
            this.textContent = 'Ajout en cours...';
            
            // AJAX request
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            
            fetch('api/add_to_cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Update cart count in header
                    updateCartCount(data.cart_count);
                } else {
                    showNotification(data.message || 'Erreur lors de l\'ajout au panier', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Erreur lors de l\'ajout au panier', 'error');
            })
            .finally(() => {
                this.disabled = false;
                if (this.classList.contains('btn-add-cart')) {
                    this.textContent = 'Ajouter au panier';
                } else {
                    this.textContent = 'Ajouter au panier';
                }
            });
        });
    });
});

// Update cart count in header
function updateCartCount(count) {
    const cartBadge = document.getElementById('cart-badge');
    if (cartBadge) {
        cartBadge.textContent = count;
        // Animation pour attirer l'attention
        cartBadge.style.animation = 'none';
        setTimeout(() => {
            cartBadge.style.animation = 'cartPulse 0.5s ease';
        }, 10);
    }
    
    // Mettre à jour aussi les autres éléments si présents
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(el => {
        el.textContent = `(${count})`;
    });
}

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.style.borderColor = '#dc3545';
            
            field.addEventListener('input', function() {
                this.style.borderColor = '';
            }, { once: true });
        }
    });
    
    // Email validation
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        if (field.value && !isValidEmail(field.value)) {
            isValid = false;
            field.style.borderColor = '#dc3545';
            showNotification('Email invalide', 'error');
        }
    });
    
    return isValid;
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Checkout form validation
const checkoutForm = document.getElementById('checkout-form');
if (checkoutForm) {
    checkoutForm.addEventListener('submit', function(e) {
        if (!validateForm('checkout-form')) {
            e.preventDefault();
            showNotification('Veuillez remplir tous les champs requis', 'error');
        }
    });
}

// Mobile Menu Toggle
const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
const mainNav = document.querySelector('.main-nav');

if (mobileMenuToggle && mainNav) {
    mobileMenuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        mainNav.classList.toggle('active');
    });

    // Fermer le menu en cliquant en dehors
    document.addEventListener('click', function(e) {
        if (mainNav && mobileMenuToggle) {
            if (!mainNav.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                mainNav.classList.remove('active');
            }
        }
    });
}

// Image Slider for Product Page (Simple)
function initProductSlider() {
    const thumbnails = document.querySelectorAll('.product-thumbnails img');
    const mainImage = document.getElementById('main-image');
    
    if (thumbnails.length > 0 && mainImage) {
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                // Remove active class from all thumbnails
                thumbnails.forEach(t => t.classList.remove('active'));
                // Add active class to clicked thumbnail
                this.classList.add('active');
                // Update main image
                mainImage.src = this.src;
            });
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initProductSlider();
});

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Add CSS animations for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);


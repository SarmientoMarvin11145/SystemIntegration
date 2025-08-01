// Global Variables
let currentUser = null;
let cart = [];
let products = [];
let favorites = [];

// Initialize Application
document.addEventListener('DOMContentLoaded', function() {
    showLoadingScreen();
    initializeApp();
    loadProducts();
    
    // Hide loading screen after initialization
    setTimeout(() => {
        hideLoadingScreen();
        showPage('welcome');
    }, 2000);
});

// Loading Screen Functions
function showLoadingScreen() {
    const loadingScreen = document.getElementById('loading-screen');
    if (loadingScreen) {
        loadingScreen.style.display = 'flex';
        loadingScreen.style.opacity = '1';
    }
}

function hideLoadingScreen() {
    const loadingScreen = document.getElementById('loading-screen');
    if (loadingScreen) {
        loadingScreen.style.opacity = '0';
        setTimeout(() => {
            loadingScreen.style.display = 'none';
        }, 500);
    }
}

// Page Navigation
function showPage(pageId) {
    // Hide all pages
    const pages = document.querySelectorAll('.page');
    pages.forEach(page => {
        page.classList.remove('active');
    });
    
    // Show target page
    const targetPage = document.getElementById(pageId + '-page');
    if (targetPage) {
        targetPage.classList.add('active');
        
        // Special handling for products page
        if (pageId === 'products') {
            renderProducts();
            updateCartCount();
        }
    }
    
    // Close any open sidebars
    closeSidebar();
    hideCart();
}

// Authentication Functions
function handleLogin(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const email = formData.get('email');
    const password = formData.get('password');
    const remember = formData.get('remember');
    
    // Show loading state
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
    submitBtn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        // For demo purposes, accept any login
        if (email && password) {
            currentUser = {
                id: 1,
                name: 'John Doe',
                email: email,
                type: 'customer'
            };
            
            // Update sidebar user info
            updateUserInfo();
            
            showNotification('Login successful!', 'success');
            showPage('products');
        } else {
            showNotification('Please fill in all fields', 'error');
        }
        
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 1500);
}

function handleSignup(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    
    // Validate passwords match
    if (password !== confirmPassword) {
        showNotification('Passwords do not match', 'error');
        return;
    }
    
    // Show loading state
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
    submitBtn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        currentUser = {
            id: Date.now(),
            name: formData.get('firstname') + ' ' + formData.get('lastname'),
            email: formData.get('email'),
            phone: formData.get('phone'),
            address: formData.get('address'),
            type: formData.get('customer_type')
        };
        
        // Update sidebar user info
        updateUserInfo();
        
        showNotification('Account created successfully!', 'success');
        showPage('products');
        
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 2000);
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        currentUser = null;
        cart = [];
        favorites = [];
        updateUserInfo();
        updateCartCount();
        showNotification('Logged out successfully', 'success');
        showPage('welcome');
    }
}

// Password Functions
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Password Strength Checker
document.addEventListener('input', function(e) {
    if (e.target.id === 'signup-password') {
        checkPasswordStrength(e.target.value);
    }
});

function checkPasswordStrength(password) {
    const strengthBar = document.querySelector('.strength-fill');
    const strengthText = document.querySelector('.strength-text');
    
    let strength = 0;
    let feedback = 'Weak';
    
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    strengthBar.className = 'strength-fill';
    
    switch (strength) {
        case 0:
        case 1:
            strengthBar.classList.add('weak');
            feedback = 'Weak';
            break;
        case 2:
        case 3:
            strengthBar.classList.add('fair');
            feedback = 'Fair';
            break;
        case 4:
            strengthBar.classList.add('good');
            feedback = 'Good';
            break;
        case 5:
            strengthBar.classList.add('strong');
            feedback = 'Strong';
            break;
    }
    
    strengthText.textContent = feedback;
}

// Sidebar Functions
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = getOrCreateOverlay();
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.overlay');
    
    sidebar.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
}

function setActiveNav(element) {
    // Remove active class from all nav items
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => item.classList.remove('active'));
    
    // Add active class to clicked item
    element.classList.add('active');
}

function updateUserInfo() {
    const userName = document.querySelector('.user-name');
    const userType = document.querySelector('.user-type');
    
    if (currentUser) {
        userName.textContent = currentUser.name;
        userType.textContent = currentUser.type.charAt(0).toUpperCase() + currentUser.type.slice(1);
    } else {
        userName.textContent = 'Guest User';
        userType.textContent = 'Browser';
    }
}

// Products Functions
async function loadProducts() {
    // Simulate loading products from API
    products = [
        {
            id: 1,
            name: 'Premium Pork Belly',
            description: 'Fresh, high-quality pork belly perfect for BBQ and roasting',
            price: 280,
            unit: 'per kg',
            category: 'fresh',
            image: 'fas fa-meat',
            stock: 50,
            badge: 'Fresh'
        },
        {
            id: 2,
            name: 'Pork Shoulder',
            description: 'Tender pork shoulder ideal for slow cooking and stews',
            price: 260,
            unit: 'per kg',
            category: 'fresh',
            image: 'fas fa-drumstick-bite',
            stock: 30,
            badge: 'Popular'
        },
        {
            id: 3,
            name: 'Ground Pork',
            description: 'Freshly ground pork meat for burgers, meatballs, and more',
            price: 240,
            unit: 'per kg',
            category: 'ground',
            image: 'fas fa-hamburger',
            stock: 25,
            badge: 'Fresh'
        },
        {
            id: 4,
            name: 'Pork Chops',
            description: 'Premium cut pork chops, perfect for grilling and frying',
            price: 320,
            unit: 'per kg',
            category: 'fresh',
            image: 'fas fa-utensils',
            stock: 20,
            badge: 'Premium'
        },
        {
            id: 5,
            name: 'Pork Ribs',
            description: 'Succulent pork ribs, great for BBQ and slow cooking',
            price: 300,
            unit: 'per kg',
            category: 'fresh',
            image: 'fas fa-bone',
            stock: 15,
            badge: 'BBQ Special'
        },
        {
            id: 6,
            name: 'Pork Tenderloin',
            description: 'The most tender cut of pork, perfect for special occasions',
            price: 450,
            unit: 'per kg',
            category: 'specialty',
            image: 'fas fa-award',
            stock: 10,
            badge: 'Premium'
        },
        {
            id: 7,
            name: 'Pork Sausages',
            description: 'Homemade pork sausages with traditional spices',
            price: 280,
            unit: 'per kg',
            category: 'specialty',
            image: 'fas fa-hotdog',
            stock: 35,
            badge: 'Homemade'
        },
        {
            id: 8,
            name: 'Bacon Strips',
            description: 'Crispy bacon strips, perfect for breakfast',
            price: 380,
            unit: 'per kg',
            category: 'specialty',
            image: 'fas fa-bacon',
            stock: 22,
            badge: 'Breakfast'
        }
    ];
}

function renderProducts(filteredProducts = null) {
    const productsGrid = document.getElementById('products-grid');
    const productsToRender = filteredProducts || products;
    
    if (!productsGrid) return;
    
    productsGrid.innerHTML = '';
    
    productsToRender.forEach(product => {
        const productCard = createProductCard(product);
        productsGrid.appendChild(productCard);
    });
}

function createProductCard(product) {
    const card = document.createElement('div');
    card.className = 'product-card';
    card.innerHTML = `
        <div class="product-image">
            <i class="${product.image}"></i>
            <div class="product-badge">${product.badge}</div>
        </div>
        <div class="product-info">
            <h3 class="product-title">${product.name}</h3>
            <p class="product-description">${product.description}</p>
            <div class="product-price">
                <span class="price">₱${product.price}</span>
                <span class="price-unit">${product.unit}</span>
            </div>
            <div class="product-actions">
                <button class="btn-add-cart" onclick="addToCart(${product.id})">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
                <button class="btn-favorite ${favorites.includes(product.id) ? 'active' : ''}" 
                        onclick="toggleFavorite(${product.id})">
                    <i class="fas fa-heart"></i>
                </button>
            </div>
        </div>
    `;
    
    return card;
}

function searchProducts(query) {
    if (!query.trim()) {
        renderProducts();
        return;
    }
    
    const filteredProducts = products.filter(product =>
        product.name.toLowerCase().includes(query.toLowerCase()) ||
        product.description.toLowerCase().includes(query.toLowerCase())
    );
    
    renderProducts(filteredProducts);
}

function filterProducts(category, buttonElement) {
    // Update filter button states
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => btn.classList.remove('active'));
    buttonElement.classList.add('active');
    
    // Filter products
    if (category === 'all') {
        renderProducts();
    } else {
        const filteredProducts = products.filter(product => product.category === category);
        renderProducts(filteredProducts);
    }
}

// Cart Functions
function addToCart(productId) {
    const product = products.find(p => p.id === productId);
    if (!product) return;
    
    const existingItem = cart.find(item => item.id === productId);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            ...product,
            quantity: 1
        });
    }
    
    updateCartCount();
    updateCartDisplay();
    showNotification(`${product.name} added to cart!`, 'success');
}

function removeFromCart(productId) {
    cart = cart.filter(item => item.id !== productId);
    updateCartCount();
    updateCartDisplay();
    showNotification('Item removed from cart', 'info');
}

function updateCartQuantity(productId, newQuantity) {
    const item = cart.find(item => item.id === productId);
    if (item) {
        if (newQuantity <= 0) {
            removeFromCart(productId);
        } else {
            item.quantity = newQuantity;
            updateCartCount();
            updateCartDisplay();
        }
    }
}

function updateCartCount() {
    const cartCount = document.querySelector('.cart-count');
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    
    if (cartCount) {
        cartCount.textContent = totalItems;
        cartCount.style.display = totalItems > 0 ? 'flex' : 'none';
    }
}

function updateCartDisplay() {
    const cartItems = document.getElementById('cart-items');
    const cartSubtotal = document.getElementById('cart-subtotal');
    const cartTotal = document.getElementById('cart-total');
    
    if (!cartItems) return;
    
    if (cart.length === 0) {
        cartItems.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-shopping-cart" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                <p>Your cart is empty</p>
                <p style="color: #666; font-size: 0.9rem;">Add some delicious meat to get started!</p>
            </div>
        `;
    } else {
        cartItems.innerHTML = '';
        cart.forEach(item => {
            const cartItem = createCartItem(item);
            cartItems.appendChild(cartItem);
        });
    }
    
    // Update totals
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const deliveryFee = subtotal > 0 ? 50 : 0;
    const total = subtotal + deliveryFee;
    
    if (cartSubtotal) cartSubtotal.textContent = `₱${subtotal.toFixed(2)}`;
    if (cartTotal) cartTotal.textContent = `₱${total.toFixed(2)}`;
}

function createCartItem(item) {
    const cartItem = document.createElement('div');
    cartItem.className = 'cart-item';
    cartItem.innerHTML = `
        <div class="cart-item-image">
            <i class="${item.image}"></i>
        </div>
        <div class="cart-item-info">
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-price">₱${item.price} ${item.unit}</div>
            <div class="cart-item-controls">
                <button class="qty-btn" onclick="updateCartQuantity(${item.id}, ${item.quantity - 1})">
                    <i class="fas fa-minus"></i>
                </button>
                <input type="number" class="qty-input" value="${item.quantity}" min="1" 
                       onchange="updateCartQuantity(${item.id}, parseInt(this.value))">
                <button class="qty-btn" onclick="updateCartQuantity(${item.id}, ${item.quantity + 1})">
                    <i class="fas fa-plus"></i>
                </button>
                <button class="qty-btn" onclick="removeFromCart(${item.id})" style="margin-left: 0.5rem; color: #dc3545;">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    
    return cartItem;
}

function showCart() {
    const cartSidebar = document.getElementById('cart-sidebar');
    const overlay = getOrCreateOverlay();
    
    updateCartDisplay();
    cartSidebar.classList.add('active');
    overlay.classList.add('active');
}

function hideCart() {
    const cartSidebar = document.getElementById('cart-sidebar');
    const overlay = document.querySelector('.overlay');
    
    cartSidebar.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
}

function proceedToCheckout() {
    if (cart.length === 0) {
        showNotification('Your cart is empty!', 'error');
        return;
    }
    
    if (!currentUser) {
        showNotification('Please login to proceed with checkout', 'info');
        hideCart();
        showPage('login');
        return;
    }
    
    // For demo purposes, just show success message
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) + 50;
    showNotification(`Order placed successfully! Total: ₱${total.toFixed(2)}`, 'success');
    
    // Clear cart
    cart = [];
    updateCartCount();
    updateCartDisplay();
    hideCart();
}

// Favorites Functions
function toggleFavorite(productId) {
    const index = favorites.indexOf(productId);
    
    if (index > -1) {
        favorites.splice(index, 1);
        showNotification('Removed from favorites', 'info');
    } else {
        favorites.push(productId);
        showNotification('Added to favorites', 'success');
    }
    
    // Update favorite button state
    const favoriteBtn = document.querySelector(`[onclick="toggleFavorite(${productId})"]`);
    if (favoriteBtn) {
        favoriteBtn.classList.toggle('active');
    }
}

// Utility Functions
function getOrCreateOverlay() {
    let overlay = document.querySelector('.overlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'overlay';
        overlay.onclick = () => {
            closeSidebar();
            hideCart();
        };
        document.body.appendChild(overlay);
    }
    
    return overlay;
}

function showNotification(message, type = 'info') {
    // Remove existing notification
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${getNotificationIcon(type)}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${getNotificationColor(type)};
        color: white;
        padding: 1rem;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideInRight 0.3s ease;
        max-width: 350px;
    `;
    
    const content = notification.querySelector('.notification-content');
    content.style.cssText = `
        display: flex;
        align-items: center;
        gap: 0.5rem;
    `;
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = `
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 0.25rem;
        margin-left: auto;
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function getNotificationIcon(type) {
    switch (type) {
        case 'success': return 'fa-check-circle';
        case 'error': return 'fa-exclamation-triangle';
        case 'warning': return 'fa-exclamation-circle';
        default: return 'fa-info-circle';
    }
}

function getNotificationColor(type) {
    switch (type) {
        case 'success': return '#28a745';
        case 'error': return '#dc3545';
        case 'warning': return '#ffc107';
        default: return '#17a2b8';
    }
}

function initializeApp() {
    // Initialize user info
    updateUserInfo();
    
    // Initialize cart count
    updateCartCount();
    
    // Add event listeners
    document.addEventListener('click', function(e) {
        // Close dropdowns when clicking outside
        if (!e.target.closest('.sidebar') && !e.target.closest('.menu-toggle')) {
            closeSidebar();
        }
        
        if (!e.target.closest('.cart-sidebar') && !e.target.closest('[onclick="showCart()"]')) {
            hideCart();
        }
    });
    
    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
            hideCart();
        }
    });
    
    // Handle form validations
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', validateInput);
            input.addEventListener('input', clearValidationError);
        });
    });
}

function validateInput(e) {
    const input = e.target;
    const value = input.value.trim();
    
    // Remove existing error
    clearValidationError(e);
    
    // Email validation
    if (input.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            showInputError(input, 'Please enter a valid email address');
        }
    }
    
    // Phone validation
    if (input.type === 'tel' && value) {
        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
        if (!phoneRegex.test(value)) {
            showInputError(input, 'Please enter a valid phone number');
        }
    }
    
    // Password confirmation
    if (input.name === 'confirm_password' && value) {
        const password = document.getElementById('signup-password').value;
        if (value !== password) {
            showInputError(input, 'Passwords do not match');
        }
    }
}

function clearValidationError(e) {
    const input = e.target;
    const errorElement = input.parentElement.querySelector('.input-error');
    if (errorElement) {
        errorElement.remove();
    }
    input.style.borderColor = '#e9ecef';
}

function showInputError(input, message) {
    input.style.borderColor = '#dc3545';
    
    const errorElement = document.createElement('div');
    errorElement.className = 'input-error';
    errorElement.textContent = message;
    errorElement.style.cssText = `
        color: #dc3545;
        font-size: 0.8rem;
        margin-top: 0.25rem;
    `;
    
    input.parentElement.appendChild(errorElement);
}

// User Menu Functions
function toggleUserMenu() {
    // For demo purposes, just show a simple menu
    const userBtn = document.querySelector('.user-btn');
    
    // Remove existing menu
    const existingMenu = document.querySelector('.user-menu');
    if (existingMenu) {
        existingMenu.remove();
        return;
    }
    
    // Create user menu
    const userMenu = document.createElement('div');
    userMenu.className = 'user-menu';
    userMenu.innerHTML = `
        <div class="user-menu-item" onclick="showProfile()">
            <i class="fas fa-user"></i> Profile
        </div>
        <div class="user-menu-item" onclick="showOrderHistory()">
            <i class="fas fa-history"></i> Order History
        </div>
        <div class="user-menu-item" onclick="showSettings()">
            <i class="fas fa-cog"></i> Settings
        </div>
        <div class="user-menu-divider"></div>
        <div class="user-menu-item" onclick="logout()">
            <i class="fas fa-sign-out-alt"></i> Logout
        </div>
    `;
    
    userMenu.style.cssText = `
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        min-width: 180px;
        z-index: 1000;
        animation: fadeIn 0.2s ease;
    `;
    
    const userMenuItems = userMenu.querySelectorAll('.user-menu-item');
    userMenuItems.forEach(item => {
        item.style.cssText = `
            padding: 0.75rem 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s ease;
        `;
        
        item.addEventListener('mouseenter', () => {
            item.style.background = '#f8f9fa';
        });
        
        item.addEventListener('mouseleave', () => {
            item.style.background = 'transparent';
        });
    });
    
    const divider = userMenu.querySelector('.user-menu-divider');
    if (divider) {
        divider.style.cssText = `
            height: 1px;
            background: #e9ecef;
            margin: 0.5rem 0;
        `;
    }
    
    userBtn.style.position = 'relative';
    userBtn.appendChild(userMenu);
    
    // Close menu when clicking outside
    setTimeout(() => {
        document.addEventListener('click', function closeUserMenu(e) {
            if (!userBtn.contains(e.target)) {
                userMenu.remove();
                document.removeEventListener('click', closeUserMenu);
            }
        });
    }, 100);
}

// Placeholder functions for user menu items
function showProfile() {
    showNotification('Profile page - Coming soon!', 'info');
}

function showOrderHistory() {
    showNotification('Order history - Coming soon!', 'info');
}

function showSettings() {
    showNotification('Settings page - Coming soon!', 'info');
}

// Service Worker Registration (for PWA capabilities)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.log('SW registered: ', registration);
            })
            .catch((registrationError) => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}
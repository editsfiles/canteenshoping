<?php
// php/navbar.php - Premium Modern Dynamic Navigation Bar
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        $cartCount += (int)$qty;
    }
}

$navUserName = $_SESSION['user_name'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? 'Student';
$navUserInitial = !empty($navUserName) ? strtoupper(mb_substr($navUserName, 0, 1, 'UTF-8')) : 'S';
$curPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
?>
<!-- FontAwesome & Google Fonts for Header -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<style>
/* ─────────────────────────────────────────────────────────────
   MODERN PREMIUM NAVBAR STYLES
   ───────────────────────────────────────────────────────────── */
:root {
    --nav-primary: #10b981;
    --nav-primary-dark: #059669;
    --nav-primary-light: #ecfdf5;
    --nav-accent: #0ea5e9;
    --nav-dark: #0f172a;
    --nav-gray: #64748b;
    --nav-border: rgba(226, 232, 240, 0.8);
    --nav-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.08);
}

.canteen-navbar-wrapper {
    position: sticky;
    top: 0;
    z-index: 9999;
    width: 100%;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--nav-border);
    box-shadow: var(--nav-shadow);
    transition: all 0.3s ease;
    font-family: 'Plus Jakarta Sans', 'Poppins', sans-serif;
}

.canteen-navbar-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 20px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

/* BRAND LOGO */
.canteen-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: var(--nav-dark);
    font-weight: 800;
    font-size: 20px;
    letter-spacing: -0.5px;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}
.canteen-brand:hover {
    transform: scale(1.02);
}
.canteen-brand-icon {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 19px;
    box-shadow: 0 6px 14px rgba(16, 185, 129, 0.35);
}
.canteen-brand-text {
    display: flex;
    flex-direction: column;
    line-height: 1.15;
}
.canteen-brand-name {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
}
.canteen-brand-badge {
    font-size: 10.5px;
    font-weight: 700;
    color: #059669;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.canteen-status-dot {
    width: 6px;
    height: 6px;
    background: #10b981;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
    animation: pulseDot 2s infinite;
}
@keyframes pulseDot {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* DESKTOP NAV LINKS */
.canteen-nav-links {
    display: flex;
    align-items: center;
    gap: 8px;
    list-style: none;
    margin: 0;
    padding: 0;
}
.canteen-nav-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 15px;
    border-radius: 12px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    color: #475569;
    transition: all 0.2s ease;
    position: relative;
}
.canteen-nav-link i {
    font-size: 14px;
    color: #94a3b8;
    transition: color 0.2s ease;
}
.canteen-nav-link:hover {
    color: #0f172a;
    background: #f1f5f9;
}
.canteen-nav-link:hover i {
    color: var(--nav-primary);
}
.canteen-nav-link.active {
    color: var(--nav-primary-dark);
    background: #ecfdf5;
    font-weight: 700;
}
.canteen-nav-link.active i {
    color: var(--nav-primary);
}

/* CART BADGE COUNT */
.nav-cart-badge {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 20px;
    line-height: 1.2;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
    margin-left: 2px;
}

/* TOP RIGHT ACTIONS */
.canteen-nav-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* USER PROFILE PILL & DROPDOWN */
.user-profile-menu {
    position: relative;
}
.user-profile-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 14px 6px 6px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 40px;
    cursor: pointer;
    transition: all 0.25s ease;
    text-decoration: none;
}
.user-profile-btn:hover {
    background: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
.user-avatar-circle {
    width: 34px;
    height: 34px;
    background: linear-gradient(135deg, #10b981, #0ea5e9);
    color: #ffffff;
    font-size: 14px;
    font-weight: 800;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
}
.user-name-label {
    font-size: 13.5px;
    font-weight: 700;
    color: #1e293b;
    max-width: 110px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.user-chevron {
    font-size: 11px;
    color: #94a3b8;
    transition: transform 0.25s ease;
}

/* DROPDOWN MENU */
.user-dropdown-card {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    width: 220px;
    background: #ffffff;
    border: 1px solid var(--nav-border);
    border-radius: 16px;
    box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.12), 0 5px 15px rgba(0, 0, 0, 0.04);
    padding: 8px;
    z-index: 10000;
    animation: dropIn 0.2s ease;
}
.user-dropdown-card.show {
    display: block;
}
@keyframes dropIn {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.user-dropdown-header {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 6px;
}
.user-dropdown-greet {
    font-size: 11px;
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.user-dropdown-fullname {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.user-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 600;
    color: #334155;
    text-decoration: none;
    transition: all 0.2s ease;
}
.user-dropdown-item i {
    width: 18px;
    font-size: 14px;
    color: #64748b;
}
.user-dropdown-item:hover {
    background: #f8fafc;
    color: var(--nav-primary-dark);
}
.user-dropdown-item:hover i {
    color: var(--nav-primary);
}
.user-dropdown-item.logout {
    color: #dc2626;
}
.user-dropdown-item.logout i {
    color: #dc2626;
}
.user-dropdown-item.logout:hover {
    background: #fef2f2;
}

/* GUEST SIGN IN PILL */
.nav-signin-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: #ffffff !important;
    font-size: 13.5px;
    font-weight: 700;
    border-radius: 999px;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    transition: all 0.2s ease;
}
.nav-signin-pill:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.45);
    color: #ffffff !important;
}

/* MOBILE HAMBURGER BUTTON */
.canteen-menu-toggle {
    display: none;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    color: #334155;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    font-size: 18px;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.canteen-menu-toggle:hover {
    background: #f1f5f9;
    color: var(--nav-primary);
}

/* MOBILE DRAWER OVERLAY */
.canteen-mobile-drawer {
    display: none;
    position: fixed;
    top: 71px;
    left: 0;
    width: 100%;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--nav-border);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    padding: 18px 20px 24px;
    z-index: 9998;
    animation: slideDown 0.25s ease;
}
.canteen-mobile-drawer.show {
    display: block;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.mobile-nav-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
    text-decoration: none;
    margin-bottom: 6px;
    transition: all 0.2s ease;
}
.mobile-nav-item:hover, .mobile-nav-item.active {
    background: #ecfdf5;
    color: #059669;
}
.mobile-nav-item i {
    font-size: 16px;
    width: 24px;
}

/* NATIVE MOBILE BOTTOM NAVIGATION BAR (App & Mobile Viewports) */
.canteen-bottom-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border-top: 1px solid var(--nav-border);
    padding: 8px 12px 10px;
    z-index: 9990;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.06);
    justify-content: space-around;
    align-items: center;
}
.bottom-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    text-decoration: none;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
    position: relative;
    padding: 4px 10px;
    border-radius: 10px;
    transition: all 0.2s ease;
}
.bottom-nav-item i {
    font-size: 18px;
    transition: transform 0.2s ease;
}
.bottom-nav-item:hover, .bottom-nav-item.active {
    color: #10b981;
}
.bottom-nav-item.active i {
    transform: translateY(-2px);
}
.bottom-nav-badge {
    position: absolute;
    top: -2px;
    right: 4px;
    background: #ef4444;
    color: #ffffff;
    font-size: 10px;
    font-weight: 800;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 5px rgba(239, 68, 68, 0.4);
}

/* RESPONSIVENESS */
@media (max-width: 900px) {
    .canteen-nav-links {
        display: none;
    }
    .canteen-menu-toggle {
        display: flex;
    }
}
@media (max-width: 640px) {
    .canteen-navbar-container {
        padding: 0 12px;
        height: 62px;
    }
    .canteen-brand-name {
        font-size: 15px;
    }
    .canteen-brand-icon {
        width: 36px;
        height: 36px;
        font-size: 16px;
        border-radius: 10px;
    }
    .user-name-label {
        display: none;
    }
    .user-profile-btn {
        padding: 2px;
        background: transparent;
        border: none;
    }
    .user-avatar-circle {
        width: 36px;
        height: 36px;
        font-size: 14px;
    }
    .user-chevron {
        display: none;
    }
    .canteen-menu-toggle {
        width: 36px;
        height: 36px;
        font-size: 15px;
        border-radius: 10px;
    }
    .canteen-nav-right {
        gap: 6px;
    }
    .canteen-bottom-nav {
        display: flex;
    }
    /* Add bottom padding to body so fixed bottom nav doesn't cover content */
    body {
        padding-bottom: 74px !important;
    }
}
</style>

<header class="canteen-navbar-wrapper">
    <div class="canteen-navbar-container">

        <!-- BRAND LOGO -->
        <a href="index.php" class="canteen-brand" title="College Canteen">
            <div class="canteen-brand-icon">
                <i class="fa-solid fa-utensils"></i>
            </div>
            <div class="canteen-brand-text">
                <span class="canteen-brand-name">College Canteen</span>
                <span class="canteen-brand-badge">
                    <span class="canteen-status-dot"></span> Open Now
                </span>
            </div>
        </a>

        <!-- DESKTOP NAV LINKS -->
        <ul class="canteen-nav-links">
            <li>
                <a href="index.php" class="canteen-nav-link <?php echo ($curPage === 'index.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-house"></i> Home
                </a>
            </li>
            <li>
                <a href="menu.php" class="canteen-nav-link <?php echo ($curPage === 'menu.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-bowl-food"></i> Menu
                </a>
            </li>
            <li>
                <a href="cart.php" class="canteen-nav-link <?php echo ($curPage === 'cart.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-cart-shopping"></i> Cart
                    <?php if ($cartCount > 0): ?>
                        <span class="nav-cart-badge"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="my_orders.php" class="canteen-nav-link <?php echo ($curPage === 'my_orders.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-receipt"></i> My Orders
                </a>
            </li>
            <li>
                <a href="contact.php" class="canteen-nav-link <?php echo ($curPage === 'contact.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-headset"></i> Contact
                </a>
            </li>
        </ul>

        <!-- TOP RIGHT ACTIONS -->
        <div class="canteen-nav-right">

            <!-- USER PROFILE CAPSULE OR SIGN IN -->
            <?php if (!empty($_SESSION['user_id'])): ?>
            <div class="user-profile-menu">
                <button type="button" class="user-profile-btn" id="userProfileBtn" aria-label="User Profile Menu">
                    <div class="user-avatar-circle">
                        <?php echo $navUserInitial; ?>
                    </div>
                    <span class="user-name-label">
                        <?php echo htmlspecialchars($navUserName); ?>
                    </span>
                    <i class="fa-solid fa-chevron-down user-chevron" id="userChevron"></i>
                </button>

                <!-- DROPDOWN -->
                <div class="user-dropdown-card" id="userDropdownCard">
                    <div class="user-dropdown-header">
                        <div class="user-dropdown-greet">Signed in as</div>
                        <div class="user-dropdown-fullname"><?php echo htmlspecialchars($navUserName); ?></div>
                    </div>
                    <a href="profile.php" class="user-dropdown-item">
                        <i class="fa-solid fa-user-circle"></i> My Profile
                    </a>
                    <a href="my_orders.php" class="user-dropdown-item">
                        <i class="fa-solid fa-receipt"></i> Order History
                    </a>
                    <a href="cart.php" class="user-dropdown-item">
                        <i class="fa-solid fa-cart-shopping"></i> View Cart (<?php echo $cartCount; ?>)
                    </a>
                    <div style="height:1px; background:#f1f5f9; margin:4px 0;"></div>
                    <a href="logout.php" class="user-dropdown-item logout">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                    </a>
                </div>
            </div>
            <?php else: ?>
            <a href="login.php" class="nav-signin-pill">
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
                <span>Sign In</span>
            </a>
            <?php endif; ?>

            <!-- MOBILE MENU TOGGLE BUTTON -->
            <button type="button" class="canteen-menu-toggle" id="mobileMenuBtn" aria-label="Toggle navigation menu">
                <i class="fa-solid fa-bars" id="mobileMenuIcon"></i>
            </button>

        </div>

    </div>

    <!-- MOBILE SLIDE-DOWN DRAWER -->
    <div class="canteen-mobile-drawer" id="mobileDrawer">
        <a href="index.php" class="mobile-nav-item <?php echo ($curPage === 'index.php') ? 'active' : ''; ?>">
            <span><i class="fa-solid fa-house" style="color:#10b981;"></i> Home</span>
            <i class="fa-solid fa-chevron-right" style="font-size:12px; opacity:0.4;"></i>
        </a>
        <a href="menu.php" class="mobile-nav-item <?php echo ($curPage === 'menu.php') ? 'active' : ''; ?>">
            <span><i class="fa-solid fa-bowl-food" style="color:#0ea5e9;"></i> Browse Menu</span>
            <i class="fa-solid fa-chevron-right" style="font-size:12px; opacity:0.4;"></i>
        </a>
        <a href="cart.php" class="mobile-nav-item <?php echo ($curPage === 'cart.php') ? 'active' : ''; ?>">
            <span><i class="fa-solid fa-cart-shopping" style="color:#f59e0b;"></i> Food Cart</span>
            <?php if ($cartCount > 0): ?>
                <span class="nav-cart-badge"><?php echo $cartCount; ?> items</span>
            <?php else: ?>
                <i class="fa-solid fa-chevron-right" style="font-size:12px; opacity:0.4;"></i>
            <?php endif; ?>
        </a>
        <a href="my_orders.php" class="mobile-nav-item <?php echo ($curPage === 'my_orders.php') ? 'active' : ''; ?>">
            <span><i class="fa-solid fa-receipt" style="color:#8b5cf6;"></i> My Orders</span>
            <i class="fa-solid fa-chevron-right" style="font-size:12px; opacity:0.4;"></i>
        </a>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="profile.php" class="mobile-nav-item <?php echo ($curPage === 'profile.php') ? 'active' : ''; ?>">
            <span><i class="fa-solid fa-user" style="color:#10b981;"></i> My Account</span>
            <i class="fa-solid fa-chevron-right" style="font-size:12px; opacity:0.4;"></i>
        </a>
        <?php endif; ?>
        <a href="contact.php" class="mobile-nav-item <?php echo ($curPage === 'contact.php') ? 'active' : ''; ?>">
            <span><i class="fa-solid fa-headset" style="color:#ec4899;"></i> Help & Support</span>
            <i class="fa-solid fa-chevron-right" style="font-size:12px; opacity:0.4;"></i>
        </a>
        <div style="height:1px; background:#e2e8f0; margin:8px 0;"></div>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="logout.php" class="mobile-nav-item" style="color:#dc2626;">
            <span><i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out</span>
            <i class="fa-solid fa-chevron-right" style="font-size:12px; opacity:0.4;"></i>
        </a>
        <?php else: ?>
        <a href="login.php" class="mobile-nav-item" style="color:#10b981; font-weight:700;">
            <span><i class="fa-solid fa-arrow-right-to-bracket" style="color:#10b981;"></i> Sign In / Register</span>
            <i class="fa-solid fa-chevron-right" style="font-size:12px; opacity:0.4;"></i>
        </a>
        <?php endif; ?>
    </div>
</header>

<!-- NATIVE MOBILE BOTTOM BAR -->
<nav class="canteen-bottom-nav" aria-label="Mobile Bottom Navigation">
    <a href="index.php" class="bottom-nav-item <?php echo ($curPage === 'index.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </a>
    <a href="menu.php" class="bottom-nav-item <?php echo ($curPage === 'menu.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-bowl-food"></i>
        <span>Menu</span>
    </a>
    <a href="cart.php" class="bottom-nav-item <?php echo ($curPage === 'cart.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-cart-shopping"></i>
        <span>Cart</span>
        <?php if ($cartCount > 0): ?>
            <span class="bottom-nav-badge"><?php echo $cartCount; ?></span>
        <?php endif; ?>
    </a>
    <a href="my_orders.php" class="bottom-nav-item <?php echo ($curPage === 'my_orders.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-receipt"></i>
        <span>Orders</span>
    </a>
    <?php if (!empty($_SESSION['user_id'])): ?>
    <a href="profile.php" class="bottom-nav-item <?php echo ($curPage === 'profile.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>
    <?php else: ?>
    <a href="login.php" class="bottom-nav-item <?php echo ($curPage === 'login.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-arrow-right-to-bracket"></i>
        <span>Sign In</span>
    </a>
    <?php endif; ?>
</nav>

<script>
(function() {
    // 1. User Profile Dropdown Toggle
    const userBtn = document.getElementById("userProfileBtn");
    const userDropdown = document.getElementById("userDropdownCard");
    const userChevron = document.getElementById("userChevron");

    if (userBtn && userDropdown) {
        userBtn.addEventListener("click", function(e) {
            e.stopPropagation();
            const isOpen = userDropdown.classList.contains("show");
            if (isOpen) {
                userDropdown.classList.remove("show");
                if (userChevron) userChevron.style.transform = "rotate(0deg)";
            } else {
                userDropdown.classList.add("show");
                if (userChevron) userChevron.style.transform = "rotate(180deg)";
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", function(e) {
            if (!userDropdown.contains(e.target) && !userBtn.contains(e.target)) {
                userDropdown.classList.remove("show");
                if (userChevron) userChevron.style.transform = "rotate(0deg)";
            }
        });
    }

    // 2. Mobile Drawer Toggle
    const menuBtn = document.getElementById("mobileMenuBtn");
    const drawer = document.getElementById("mobileDrawer");
    const menuIcon = document.getElementById("mobileMenuIcon");

    if (menuBtn && drawer) {
        menuBtn.addEventListener("click", function(e) {
            e.stopPropagation();
            const isOpen = drawer.classList.contains("show");
            if (isOpen) {
                drawer.classList.remove("show");
                menuIcon.classList.replace("fa-xmark", "fa-bars");
            } else {
                drawer.classList.add("show");
                menuIcon.classList.replace("fa-bars", "fa-xmark");
            }
        });

        document.addEventListener("click", function(e) {
            if (!drawer.contains(e.target) && !menuBtn.contains(e.target)) {
                drawer.classList.remove("show");
                if (menuIcon) menuIcon.classList.replace("fa-xmark", "fa-bars");
            }
        });
    }
})();
</script>

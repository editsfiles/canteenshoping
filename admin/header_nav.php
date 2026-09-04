<?php
/**
 * Standardized Admin Header & Navigation Component
 * Parameters (optional):
 *   $activePage = 'dashboard' | 'products' | 'customers' | 'orders' | 'missing_callback' | 'reports' | 'messages'
 */
if (!isset($activePage)) {
    $activePage = basename($_SERVER['PHP_SELF'], '.php');
}
?>
<!-- TOP NAVIGATION BAR (Deep Purple with Light Cyan Text) -->
<header class="admin-top-nav">
    <a href="dashboard.php" class="admin-logo">
        <i class="fa-solid fa-utensils"></i>
        <span>College Canteen Admin</span>
    </a>

    <nav class="admin-menu">
        <a href="dashboard.php" class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-pie"></i> Dashboard
        </a>
        <a href="products.php" class="<?php echo in_array($activePage, ['products', 'add_product', 'edit_product']) ? 'active' : ''; ?>">
            <i class="fa-solid fa-burger"></i> Products
        </a>
        <a href="customers.php" class="<?php echo in_array($activePage, ['customers', 'customer_details']) ? 'active' : ''; ?>">
            <i class="fa-solid fa-users"></i> Customers
        </a>
        <a href="orders.php" class="<?php echo $activePage === 'orders' ? 'active' : ''; ?>">
            <i class="fa-solid fa-cart-shopping"></i> Orders
        </a>
        <a href="missing_callback.php" class="<?php echo $activePage === 'missing_callback' ? 'active' : ''; ?>">
            <i class="fa-solid fa-bolt"></i> Missing Callback
        </a>
        <a href="reports.php" class="<?php echo $activePage === 'reports' ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-line"></i> Reports
        </a>
        <a href="messages.php" class="<?php echo in_array($activePage, ['messages', 'view_message']) ? 'active' : ''; ?>">
            <i class="fa-solid fa-envelope"></i> Messages
        </a>
        <a href="logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</header>

<!-- DARK BAR WITH QUICK ACTION ICONS (Directly Below Top Nav) -->
<div class="admin-dark-bar">
    <div class="dark-bar-links">
        <a href="dashboard.php" class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" title="Dashboard Overview">
            <i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span>
        </a>
        <a href="products.php" class="<?php echo in_array($activePage, ['products', 'add_product', 'edit_product']) ? 'active' : ''; ?>" title="Manage Products">
            <i class="fa-solid fa-utensils"></i> <span>Products</span>
        </a>
        <a href="customers.php" class="<?php echo in_array($activePage, ['customers', 'customer_details']) ? 'active' : ''; ?>" title="Customer Accounts">
            <i class="fa-solid fa-user-group"></i> <span>Customers</span>
        </a>
        <a href="orders.php" class="<?php echo $activePage === 'orders' ? 'active' : ''; ?>" title="Live Orders">
            <i class="fa-solid fa-bell-concierge"></i> <span>Kitchen Orders</span>
        </a>
        <a href="missing_callback.php" class="<?php echo $activePage === 'missing_callback' ? 'active' : ''; ?>" title="Callback Recovery">
            <i class="fa-solid fa-rotate"></i> <span>Callbacks</span>
        </a>
        <a href="reports.php" class="<?php echo $activePage === 'reports' ? 'active' : ''; ?>" title="Financial Reports">
            <i class="fa-solid fa-file-invoice-dollar"></i> <span>Reports</span>
        </a>
        <a href="messages.php" class="<?php echo in_array($activePage, ['messages', 'view_message']) ? 'active' : ''; ?>" title="Customer Messages">
            <i class="fa-solid fa-comments"></i> <span>Messages</span>
        </a>
    </div>
    
    <div class="dark-bar-status">
        <span class="dark-bar-badge">
            <i class="fa-solid fa-circle" style="font-size:8px;"></i> Live Canteen System
        </span>
        <span><i class="fa-regular fa-clock"></i> <?php echo date('h:i A'); ?></span>
    </div>
</div>

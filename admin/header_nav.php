<?php
/**
 * Standardized Admin Header & Navigation Component
 * Parameters (optional):
 *   $activePage = 'dashboard' | 'products' | 'customers' | 'orders' | 'missing_callback' | 'reports' | 'messages'
 */
date_default_timezone_set('Asia/Kolkata');

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

<!-- DARK BAR WITH ICONS & REAL-TIME INDIAN STANDARD TIME (IST) (Directly Below Top Nav) -->
<div class="admin-dark-bar">
    <div class="dark-bar-links">
        <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:#64748b; margin-right:4px;">Quick Access:</span>
        <a href="dashboard.php" class="dark-icon-btn <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" title="Dashboard Overview">
            <i class="fa-solid fa-gauge-high"></i>
        </a>
        <a href="products.php" class="dark-icon-btn <?php echo in_array($activePage, ['products', 'add_product', 'edit_product']) ? 'active' : ''; ?>" title="Manage Products">
            <i class="fa-solid fa-burger"></i>
        </a>
        <a href="customers.php" class="dark-icon-btn <?php echo in_array($activePage, ['customers', 'customer_details']) ? 'active' : ''; ?>" title="Customer Accounts">
            <i class="fa-solid fa-user-group"></i>
        </a>
        <a href="orders.php" class="dark-icon-btn <?php echo $activePage === 'orders' ? 'active' : ''; ?>" title="Live Orders">
            <i class="fa-solid fa-bell-concierge"></i>
        </a>
        <a href="missing_callback.php" class="dark-icon-btn <?php echo $activePage === 'missing_callback' ? 'active' : ''; ?>" title="Callback Recovery">
            <i class="fa-solid fa-rotate"></i>
        </a>
        <a href="reports.php" class="dark-icon-btn <?php echo $activePage === 'reports' ? 'active' : ''; ?>" title="Financial Reports">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </a>
        <a href="messages.php" class="dark-icon-btn <?php echo in_array($activePage, ['messages', 'view_message']) ? 'active' : ''; ?>" title="Customer Messages">
            <i class="fa-solid fa-comments"></i>
        </a>
    </div>
    
    <div class="dark-bar-status">
        <span class="dark-bar-badge">
            <i class="fa-solid fa-circle" style="font-size:8px;"></i> Live Canteen System
        </span>
        <span class="dark-bar-time" title="Real-time Indian Standard Time (IST)">
            <i class="fa-regular fa-clock" style="color:#38bdf8;"></i>
            <span id="adminLiveClock"><?php echo date('h:i:s A'); ?> IST</span>
        </span>
    </div>
</div>

<script>
// Real-time ticking Indian Standard Time (IST) Clock
(function() {
    function updateAdminClock() {
        const el = document.getElementById('adminLiveClock');
        if (!el) return;
        try {
            const options = {
                timeZone: 'Asia/Kolkata',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const formatter = new Intl.DateTimeFormat('en-US', options);
            el.textContent = formatter.format(new Date()) + ' IST';
        } catch (e) {
            const now = new Date();
            el.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }) + ' IST';
        }
    }
    updateAdminClock();
    setInterval(updateAdminClock, 1000);
})();
</script>


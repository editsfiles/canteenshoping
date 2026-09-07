<?php
include("php/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];
$displayName = $_SESSION['user_name'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? 'Student';

// Dynamic Greeting based on time of day (Asia/Kolkata)
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = "Good Morning";
    $greetingEmoji = "☀️";
    $greetingSub = "Ready for a fresh, energizing campus breakfast?";
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = "Good Afternoon";
    $greetingEmoji = "🌤️";
    $greetingSub = "Time for a delicious, hot lunch & refreshing drinks!";
} elseif ($hour >= 17 && $hour < 22) {
    $greeting = "Good Evening";
    $greetingEmoji = "☕";
    $greetingSub = "Snack break! Grab some hot chai, samosas, or burgers.";
} else {
    $greeting = "Late Night Bites";
    $greetingEmoji = "🌙";
    $greetingSub = "Craving quick comfort food? Order your favorites.";
}

// Check for any active ongoing orders for this user
$activeOrder = null;
$activeStmt = @$conn->prepare("SELECT id, total_amount, payment_status, status FROM orders WHERE user_id = ? AND status NOT IN ('delivered', 'cancelled', 'Delivered', 'Cancelled') ORDER BY id DESC LIMIT 1");
if ($activeStmt) {
    $activeStmt->bind_param("i", $userId);
    $activeStmt->execute();
    $orderRes = $activeStmt->get_result();
    if ($orderRes && $orderRes->num_rows > 0) {
        $activeOrder = $orderRes->fetch_assoc();
    }
    $activeStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Home | College Canteen</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#10b981">
    <link rel="apple-touch-icon" href="uploads/Burger.jpg">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --primary-light: #ecfdf5;
            --accent: #0ea5e9;
            --dark: #0f172a;
            --text-muted: #64748b;
            --bg-page: #f8fafc;
        }

        body {
            background: var(--bg-page);
            font-family: 'Plus Jakarta Sans', 'Poppins', sans-serif;
            color: var(--dark);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* DYNAMIC HERO BANNER */
        .hero-section {
            position: relative;
            background: linear-gradient(135deg, #059669 0%, #10b981 40%, #0ea5e9 100%);
            padding: 48px 24px 70px;
            color: white;
            text-align: center;
            overflow: hidden;
        }

        .hero-glow {
            position: absolute;
            border-radius: 50%;
            filter: blur(70px);
            opacity: 0.35;
            pointer-events: none;
        }
        .hero-glow-1 {
            width: 320px;
            height: 320px;
            background: #34d399;
            top: -50px;
            left: -50px;
        }
        .hero-glow-2 {
            width: 340px;
            height: 340px;
            background: #38bdf8;
            bottom: -60px;
            right: -60px;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 720px;
            margin: 0 auto;
        }

        .greeting-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 7px 16px;
            border-radius: 30px;
            font-size: 13.5px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 16px;
            animation: fadeInDown 0.5s ease;
        }

        .hero-title {
            font-size: 38px;
            font-weight: 800;
            letter-spacing: -1px;
            line-height: 1.2;
            margin-bottom: 12px;
            color: #ffffff;
        }
        .hero-title span {
            color: #fef08a;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        }

        .hero-sub {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            margin-bottom: 28px;
            line-height: 1.5;
        }

        /* INTERACTIVE SEARCH FORM */
        .search-box-wrap {
            position: relative;
            max-width: 580px;
            margin: 0 auto 20px;
        }
        .hero-search-form {
            display: flex;
            align-items: center;
            background: #ffffff;
            border-radius: 40px;
            padding: 6px 8px 6px 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.18);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .hero-search-form:focus-within {
            transform: scale(1.02);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
        }
        .search-icon {
            color: #94a3b8;
            font-size: 18px;
            margin-right: 12px;
        }
        .search-input-field {
            flex: 1;
            border: none;
            outline: none;
            font-size: 15px;
            font-weight: 500;
            color: var(--dark);
            background: transparent;
            font-family: inherit;
        }
        .search-btn-submit {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: 30px;
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            white-space: nowrap;
        }
        .search-btn-submit:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: scale(1.03);
        }

        /* QUICK CATEGORY PILLS */
        .quick-pills {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }
        .quick-pill-item {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .quick-pill-item:hover {
            background: #ffffff;
            color: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        /* ACTIVE ORDER NOTIFICATION BANNER */
        .active-order-banner {
            max-width: 1200px;
            margin: -28px auto 28px;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }
        .active-order-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 16px 22px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #bbf7d0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .active-order-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .active-order-icon {
            width: 46px;
            height: 46px;
            background: #ecfdf5;
            color: #10b981;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .active-order-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
        }
        .active-order-desc {
            font-size: 12.5px;
            color: #64748b;
        }
        .active-order-btn {
            background: #10b981;
            color: #ffffff;
            text-decoration: none;
            padding: 9px 18px;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transition: all 0.2s;
        }
        .active-order-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        /* SECTION HEADER */
        .section-wrap {
            max-width: 1200px;
            margin: 0 auto 50px;
            padding: 0 20px;
        }
        .section-header-box {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .section-headline {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .section-subtext {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }
        .section-link-all {
            color: #10b981;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: gap 0.2s;
        }
        .section-link-all:hover {
            gap: 9px;
            color: #059669;
        }

        /* MODERN FOOD CATEGORIES GRID */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }
        .category-tile {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 24px;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }
        .category-tile:hover {
            transform: translateY(-6px);
            border-color: #cbd5e1;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
        }
        .category-icon-bubble {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin-bottom: 16px;
            transition: transform 0.3s ease;
        }
        .category-tile:hover .category-icon-bubble {
            transform: scale(1.1) rotate(4deg);
        }

        .cat-burger  { background: #fef3c7; }
        .cat-pizza   { background: #fee2e2; }
        .cat-drinks  { background: #e0f2fe; }
        .cat-snacks  { background: #ffedd5; }
        .cat-dessert { background: #fce7f3; }
        .cat-tea     { background: #ede9fe; }

        .category-name {
            font-size: 19px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
            letter-spacing: -0.3px;
        }
        .category-desc {
            font-size: 13.5px;
            color: #64748b;
            margin-bottom: 18px;
            line-height: 1.4;
        }
        .category-arrow-link {
            margin-top: auto;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #10b981;
            transition: gap 0.2s;
        }
        .category-tile:hover .category-arrow-link {
            gap: 9px;
            color: #059669;
        }

        /* WHY ORDER WITH US STRIP */
        .features-strip {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 32px 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            margin-bottom: 50px;
        }
        .feature-box {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .feature-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: #ecfdf5;
            color: #10b981;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .feature-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .feature-text {
            font-size: 13px;
            color: #64748b;
            line-height: 1.4;
        }

        /* FOOTER */
        .canteen-footer {
            background: #0f172a;
            color: #94a3b8;
            padding: 36px 20px 40px;
            text-align: center;
            font-size: 13.5px;
        }
        .canteen-footer strong {
            color: #ffffff;
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 36px 18px 50px;
            }
            .hero-title {
                font-size: 28px;
            }
            .hero-sub {
                font-size: 14px;
                margin-bottom: 22px;
            }
            .hero-search-form {
                padding: 4px 6px 4px 16px;
            }
            .search-btn-submit {
                padding: 10px 18px;
                font-size: 13.5px;
            }
            .categories-grid {
                grid-template-columns: 1fr 1fr;
                gap: 14px;
            }
            .category-tile {
                padding: 18px 16px;
            }
            .category-name {
                font-size: 16px;
            }
            .category-icon-bubble {
                width: 48px;
                height: 48px;
                font-size: 24px;
            }
        }
        @media (max-width: 480px) {
            .categories-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- MODERN NAVIGATION BAR -->
    <?php include("php/navbar.php"); ?>

    <!-- DYNAMIC HERO BANNER -->
    <section class="hero-section">
        <div class="hero-glow hero-glow-1"></div>
        <div class="hero-glow hero-glow-2"></div>

        <div class="hero-content">
            <div class="greeting-badge">
                <span><?php echo $greetingEmoji; ?></span>
                <span><?php echo $greeting; ?>, <?php echo htmlspecialchars($displayName); ?></span>
            </div>

            <h1 class="hero-title">
                Craving Delicious Food?<br>
                <span>Order Without Waiting</span>
            </h1>

            <p class="hero-sub">
                <?php echo $greetingSub; ?>
            </p>

            <!-- LIVE SEARCH BOX -->
            <div class="search-box-wrap">
                <form action="menu.php" method="GET" class="hero-search-form">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input 
                        type="text" 
                        name="search" 
                        class="search-input-field" 
                        placeholder="Search burgers, pizza, juices, snacks..." 
                        autocomplete="off"
                    >
                    <button type="submit" class="search-btn-submit">
                        <i class="fa-solid fa-arrow-right"></i>
                        <span>Search</span>
                    </button>
                </form>
            </div>

            <!-- QUICK SHORTCUT PILLS -->
            <div class="quick-pills">
                <a href="menu.php?search=Burger" class="quick-pill-item">🍔 Burgers</a>
                <a href="menu.php?search=Pizza" class="quick-pill-item">🍕 Pizza</a>
                <a href="menu.php?search=Juice" class="quick-pill-item">🥤 Drinks</a>
                <a href="menu.php?search=Fries" class="quick-pill-item">🍟 Snacks</a>
                <a href="menu.php?search=Tea" class="quick-pill-item">☕ Chai & Coffee</a>
                <a href="menu.php" class="quick-pill-item" style="background:rgba(255,255,255,0.3);">🍽 All Menu &rarr;</a>
            </div>
        </div>
    </section>

    <!-- ACTIVE ONGOING ORDER NOTIFICATION (If user has an active order) -->
    <?php if (!empty($activeOrder)): ?>
    <div class="active-order-banner">
        <div class="active-order-card">
            <div class="active-order-left">
                <div class="active-order-icon">
                    <i class="fa-solid fa-bell fa-shake"></i>
                </div>
                <div>
                    <div class="active-order-title">
                        Order #<?php echo (int)$activeOrder['id']; ?> is <?php echo ucfirst(htmlspecialchars($activeOrder['status'])); ?>!
                    </div>
                    <div class="active-order-desc">
                        Total ₹<?php echo number_format((float)$activeOrder['total_amount'], 2); ?> &bull; Payment: <?php echo ucfirst(htmlspecialchars($activeOrder['payment_status'])); ?>
                    </div>
                </div>
            </div>
            <a href="my_orders.php" class="active-order-btn">
                <span>Track Live Order</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- FOOD CATEGORIES SECTION -->
    <main class="section-wrap" style="margin-top: <?php echo empty($activeOrder) ? '36px' : '10px'; ?>;">

        <div class="section-header-box">
            <div>
                <h2 class="section-headline">Explore Food Categories</h2>
                <p class="section-subtext">Choose your favorite craving and skip the line</p>
            </div>
            <a href="menu.php" class="section-link-all">
                <span>View Full Menu</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="categories-grid">

            <!-- BURGERS -->
            <a href="menu.php?search=Burger" class="category-tile">
                <div class="category-icon-bubble cat-burger">🍔</div>
                <div class="category-name">Burgers</div>
                <div class="category-desc">Freshly grilled buns, crispy patties & melted cheese</div>
                <div class="category-arrow-link">
                    <span>Explore Burgers</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <!-- PIZZA -->
            <a href="menu.php?search=Pizza" class="category-tile">
                <div class="category-icon-bubble cat-pizza">🍕</div>
                <div class="category-name">Hot Pizza</div>
                <div class="category-desc">Loaded with mozzarella, herbs & mouthwatering toppings</div>
                <div class="category-arrow-link">
                    <span>Explore Pizza</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <!-- DRINKS -->
            <a href="menu.php?search=Juice" class="category-tile">
                <div class="category-icon-bubble cat-drinks">🥤</div>
                <div class="category-name">Drinks & Juices</div>
                <div class="category-desc">Chilled fruit juices, cold sodas, milkshakes & coolers</div>
                <div class="category-arrow-link">
                    <span>Explore Drinks</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <!-- SNACKS -->
            <a href="menu.php?search=Snack" class="category-tile">
                <div class="category-icon-bubble cat-snacks">🍟</div>
                <div class="category-name">Snacks & Fries</div>
                <div class="category-desc">Crispy French fries, samosas, rolls, cutlets & quick bites</div>
                <div class="category-arrow-link">
                    <span>Explore Snacks</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <!-- DESSERTS -->
            <a href="menu.php?search=Dessert" class="category-tile">
                <div class="category-icon-bubble cat-dessert">🍰</div>
                <div class="category-name">Desserts & Bakery</div>
                <div class="category-desc">Fresh pastries, cakes, ice creams & sweet treats</div>
                <div class="category-arrow-link">
                    <span>Explore Desserts</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <!-- COFFEE & TEA -->
            <a href="menu.php?search=Tea" class="category-tile">
                <div class="category-icon-bubble cat-tea">☕</div>
                <div class="category-name">Chai & Coffee</div>
                <div class="category-desc">Hot South Indian filter coffee, masala tea & boost</div>
                <div class="category-arrow-link">
                    <span>Explore Beverages</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

        </div>

        <!-- WHY ORDER WITH US -->
        <div class="features-strip" style="margin-top: 40px;">
            <div class="feature-box">
                <div class="feature-icon-wrap">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div>
                    <div class="feature-title">Skip The Canteen Queue</div>
                    <div class="feature-text">Pre-order during class or break and pick up when fresh and ready.</div>
                </div>
            </div>

            <div class="feature-box">
                <div class="feature-icon-wrap">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <div>
                    <div class="feature-title">Instant UPI Payments</div>
                    <div class="feature-text">Pay securely with Google Pay, PhonePe, Paytm or BHIM UPI in seconds.</div>
                </div>
            </div>

            <div class="feature-box">
                <div class="feature-icon-wrap">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <div class="feature-title">Live Kitchen Tracking</div>
                    <div class="feature-text">Track your food status from order placed to kitchen prep and delivery.</div>
                </div>
            </div>
        </div>

    </main>

    <!-- FOOTER -->
    <footer class="canteen-footer">
       <strong> <div>College Canteen Ordering Portal</div></strong>
    </footer>

    <!-- PWA & APP DOWNLOAD BANNER -->
    <?php include_once("php/install_pwa_banner.php"); ?>

</body>
</html>
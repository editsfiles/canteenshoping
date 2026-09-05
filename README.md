# 🍽 College Canteen Management & Online Ordering System

A full-featured College Canteen Management System built with PHP and MySQL. Includes online food ordering, automated cart and checkout, real-time UPI QR code payments with one-click UPI ID copy, 12-digit UTR confirmation, itemized invoices, kitchen status updates, and a comprehensive admin management portal.

---

## 🌟 Features

### 👨‍🎓 Student / Customer Portal
* **User Authentication**: Secure registration and login with bcrypt password hashing and OTP-based password resets via Gmail SMTP.
* **Interactive Food Menu**: Browse categories, search food items, adjust quantities, and add to cart with real-time price calculations.
* **Checkout & Order Processing**: 5% GST calculation, customer details confirmation, and instant order creation.
* **Dynamic UPI Payments**:
  * Scannable UPI QR code with a 10-minute live countdown timer.
  * Dedicated **Merchant UPI ID / VPA Card** with one-click copy for mobile app payments.
  * High-speed real-time polling to detect bank payment confirmation automatically.
  * **Manual 12-digit UTR / UPI Ref submission** to confirm payment directly from Google Pay, PhonePe, Paytm, or BHIM.
* **Itemized Invoices**: Printable order receipts detailing food items, unit prices, quantities, and GST totals.
* **Order Tracking ("My Orders")**: Track live preparation status (`Preparing`, `Ready`, `Delivered`).
* **Contact & Support**: Feedback form with database messaging.

### 👨‍🍳 Kitchen & Admin Dashboard
* **Real-Time Metrics**: Product counts, customer registrations, today's sales, and total completed revenue.
* **Kitchen Order Management**:
  * Table displaying exact items and quantities to prepare (e.g. `2x Chicken Biryani, 1x Tea`).
  * Instant status controls: update food status (`Preparing`, `Ready`, `Delivered`) and payment status (`Pending`, `Completed`, `Cancelled`).
  * Direct invoice links and gateway reference verification links.
* **Product Catalog Management**: Add, edit, and delete food items with image uploads, stock availability toggles, and decimal pricing.
* **Customer Management**: View registered students, spending totals, order history, and safe cascading account deletion.
* **Missing Callback Reconcile Panel**: Reconcile missed gateway webhooks with single-order checks and bulk recovery tools.
* **Sales Reports**: Daily, monthly, and custom date range sales reports with print-ready layouts.
* **Message Inbox**: View customer inquiries and direct reply shortcuts.

---

## 🛠 Tech Stack

* **Backend**: PHP (PHP 7.4+ / PHP 8.x)
* **Database**: MySQL / MariaDB (InnoDB, UTF-8)
* **Frontend**: HTML5, Vanilla CSS3, JavaScript (ES6+), FontAwesome Icons
* **Mailer**: PHPMailer (SMTP OTP Delivery)
* **Gateway**: UroPay UPI QR API & Webhook Handler

---

## 🚀 Quick Setup Instructions

### 1. Requirements
* [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8.x)
* Modern web browser

### 2. Database Installation
1. Start **Apache** and **MySQL** in XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin/`.
3. Create a new database named `canteen_db`.
4. Import `database_setup.sql`:
   ```bash
   mysql -u root canteen_db < database_setup.sql
   ```

### 3. Application Configuration
* **Database Connection**: Configured in `php/db.php`:
  ```php
  $conn = mysqli_connect("localhost", "root", "", "canteen_db");
  ```
* **Payment Gateway & Merchant UPI ID**: Configured in `config_uropay.php`:
  ```php
  define("UROPAY_API_KEY", "YOUR_API_KEY");
  define("CANTEEN_UPI_ID", "9952611859@slc");
  ```

---

## 🔑 Default Credentials

### Admin Kitchen Portal
* **URL**: `http://localhost/Canteenshoping/admin/login.php`
* **Username**: `admin`
* **Password**: `12345`

### Student Demo Account
* **URL**: `http://localhost/Canteenshoping/login.php`
* **Email**: `mohanraj.s4211@gmail.com`
* **Password**: `123456`

---

## 📁 Project Structure

```
Canteenshoping/
│
├── admin/                    # Kitchen and Admin Control Panel
│   ├── dashboard.php         # Analytics and revenue summary
│   ├── orders.php            # Kitchen orders table & status updates
│   ├── products.php          # Product catalog management
│   ├── add_product.php       # Add new food item
│   ├── edit_product.php      # Edit food item & upload image
│   ├── customers.php         # Customer profiles & search
│   ├── customer_details.php  # Detailed customer order history
│   ├── reports.php           # Financial & sales reporting
│   ├── missing_callback.php  # Gateway webhook reconcile panel
│   └── messages.php          # Inquiries and support messages
│
├── uploads/                  # Food item photos and thumbnails
├── phpmailer/                # PHPMailer library for OTP emails
├── php/
│   ├── db.php                # Database connection
│   ├── mail.php              # SMTP mail configuration
│   └── send_otp.php          # 6-digit OTP generator & dispatcher
│
├── index.php                 # Landing page
├── menu.php                  # Interactive food menu
├── cart.php                  # Shopping cart management
├── checkout.php              # Checkout and summary
├── create_order.php          # Order creation and items persistence
├── uropay_payment.php        # Real-time UPI QR, VPA copy, and UTR confirm
├── check_uropay_status.php   # Status polling API & manual UTR validator
├── webhook.php               # Gateway webhook receiver
├── invoice.php               # Itemized printable bill
├── my_orders.php             # Student order history & tracking
└── database_setup.sql        # Unified database schema
```

---

## 📄 License
This project is open-source and available for educational and commercial use.

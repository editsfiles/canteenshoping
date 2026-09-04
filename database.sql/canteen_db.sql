CREATE DATABASE IF NOT EXISTS canteen_db;
USE canteen_db;

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    regno VARCHAR(100) NOT NULL UNIQUE,
    department VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_id VARCHAR(255) DEFAULT NULL,
    merchant_order_id VARCHAR(255) DEFAULT NULL,
    payment_method VARCHAR(100) DEFAULT 'UroPay',
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    food_status VARCHAR(50) NOT NULL DEFAULT 'Preparing',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    otp VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO admins (username, password)
VALUES ('admin', '12345')
ON DUPLICATE KEY UPDATE password = VALUES(password);

INSERT INTO products (product_name, description, price, image, status)
VALUES
    ('Burger', 'Juicy grilled burger with fresh lettuce and tomato.', 120.00, 'Burger.jpg', 'Available'),
    ('Pizza', 'Cheesy pizza with tomato sauce and herb toppings.', 180.00, 'pizza.jpg', 'Available'),
    ('Dosa', 'Crispy masala dosa served with chutney and sambar.', 90.00, 'dosa.jpg', 'Available'),
    ('Coffee', 'Fresh hot coffee for a quick energy boost.', 60.00, 'coffee.jpg', 'Available');

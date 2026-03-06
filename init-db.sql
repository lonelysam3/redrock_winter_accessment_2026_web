-- 创建数据库
CREATE DATABASE IF NOT EXISTS shopping_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shopping_db;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    full_name VARCHAR(100),
    phone VARCHAR(20),
    gender ENUM('male', 'female', 'other') DEFAULT 'other',
    birthdate DATE,
    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
    province VARCHAR(50),
    city VARCHAR(50),
    district VARCHAR(50),
    detailed_address TEXT,
    zipcode VARCHAR(10),
    balance DECIMAL(10,2) DEFAULT 10000.00, -- 新用户默认有10,000元
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 分类表
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100),
    parent_id INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 商品表
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    sku VARCHAR(50),
    category_id INT,
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2),
    stock_quantity INT DEFAULT 0,
    sold_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    description TEXT,
    main_image VARCHAR(255),
    images TEXT,
    specifications TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    is_hot BOOLEAN DEFAULT FALSE,
    is_new BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- 购物车表
CREATE TABLE IF NOT EXISTS cart_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    selected_attributes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 订单表
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    final_amount DECIMAL(10,2) NOT NULL,
    shipping_address TEXT NOT NULL,
    payment_method VARCHAR(50),
    payment_status VARCHAR(20) DEFAULT 'pending',
    order_status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 订单商品表
CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(200) NOT NULL,
    product_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- 商品评价表
CREATE TABLE IF NOT EXISTS product_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT NOT NULL,
    is_approved BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 搜索历史表
CREATE TABLE IF NOT EXISTS search_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    keyword VARCHAR(200) NOT NULL,
    results_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 插入默认分类
INSERT INTO categories (name, slug, sort_order) VALUES
('手机数码', 'phone-digital', 1),
('家用电器', 'home-appliances', 2),
('服装鞋帽', 'clothing-shoes', 3),
('食品饮料', 'food-drinks', 4),
('美妆个护', 'beauty-care', 5);

-- 插入测试商品
INSERT INTO products (name, category_id, price, original_price, stock_quantity, description, main_image, is_active, is_new, is_hot) VALUES
('Rycarl的非iphone手机', 1, 8999.00, 9999.00, 50, '学长严选，自用99新！', 'iphone14pro.jpg', TRUE, TRUE, TRUE),
('三星Note 7', 2, 2999.00, 3499.00, 30, '民用炸弹！', 'xiaomi13u.jpg', TRUE, FALSE, TRUE),
('耐克绝不用新疆棉', 3, 599.00, 799.00, 100, '绝不用强制劳动的产品！', 'nike270.jpg', TRUE, FALSE, FALSE),
('康师傅冰红茶', 4, 48.00, 60.00, 200, 'Man! What can I say? Manba out!', 'starbucks-beans.jpg', TRUE, FALSE, FALSE),
('华为Mate 60', 1, 6999.00, 7999.00, 40, '遥遥领先！', 'xiaomi13u.jpg', TRUE, TRUE, TRUE),
('Mvegetable的电脑', 1, 3999.00, 4599.00, 25, 'Rycarl从水深火热中抢救而来，为什么水深火热你别管！', 'matebook14.jpg', TRUE, FALSE, TRUE);

-- 插入测试用户（密码：password123，已使用 bcrypt 哈希存储）
INSERT INTO users (username, password, email, balance) VALUES
('Rycarl_loves_rea1ity', '$2y$10$oF0.UQZdESChr6p25GTdReZh8AxFVIk7CQK.S2BvRwDmV6eVvhaWu', '1919810@cqupt.edu.cn', 10000.00);

-- 创建索引
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_active ON products(is_active);
CREATE INDEX idx_cart_user ON cart_items(user_id);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_reviews_product ON product_reviews(product_id);
CREATE INDEX idx_reviews_user ON product_reviews(user_id);
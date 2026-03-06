<?php
// 开启会话（如果还没开启）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 获取购物车数量（如果用户已登录）
$cartCount = 0;
if (isset($_SESSION['user_id']) && isset($pdo)) {
    require_once 'product_functions.php';
    $cartCount = getCartCount($pdo, $_SESSION['user_id']);
}

// 页面标题
$pageTitle = $pageTitle ?? '购物网站';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <!-- 统一CSS样式 -->
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #4ecdc4;
            --dark-color: #2d3436;
            --light-color: #f9f9f9;
            --gray-color: #636e72;
            --border-color: #e0e0e0;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft YaHei', '微软雅黑', sans-serif;
            background-color: #f8f9fa;
            color: var(--dark-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* 顶部栏 */
        .top-bar {
            background: var(--dark-color);
            color: white;
            padding: 8px 0;
            font-size: 14px;
        }
        
        .top-bar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-bar-links a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
        }
        
        .top-bar-links a:hover {
            color: var(--primary-color);
        }
        
        /* 主头部 */
        .main-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            padding: 15px 0;
        }
        
        /* 网站Logo */
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo-icon {
            color: var(--primary-color);
        }
        
        .logo-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* 搜索框 */
        .search-container {
            flex: 1;
            max-width: 600px;
            margin: 0 30px;
        }
        
        .search-form {
            display: flex;
            position: relative;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid var(--primary-color);
            border-radius: 30px;
            font-size: 16px;
            outline: none;
            transition: all 0.3s;
        }
        
        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }
        
        .search-button {
            position: absolute;
            right: 5px;
            top: 5px;
            background: var(--primary-color);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        
        .search-button:hover {
            background: #ff5252;
        }
        
        /* 用户导航 */
        .user-nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-link {
            position: relative;
            color: var(--dark-color);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-link:hover {
            background: #f5f5f5;
            color: var(--primary-color);
        }
        
        .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--primary-color);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        /* 分类导航 */
        .category-nav {
            background: white;
            border-top: 1px solid var(--border-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .category-list {
            display: flex;
            list-style: none;
            padding: 15px 0;
            overflow-x: auto;
        }
        
        .category-item {
            margin-right: 25px;
            white-space: nowrap;
        }
        
        .category-item a {
            color: var(--dark-color);
            text-decoration: none;
            padding: 8px 0;
            position: relative;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .category-item a:hover {
            color: var(--primary-color);
        }
        
        .category-item.active a {
            color: var(--primary-color);
        }
        
        .category-item.active a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--primary-color);
        }
        
        /* 主内容区 */
        .main-content {
            flex: 1;
            padding: 30px 0;
        }
        
        /* 页脚 */
        .main-footer {
            background: var(--dark-color);
            color: white;
            padding: 50px 0 20px;
            margin-top: auto;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-section h3 {
            font-size: 18px;
            margin-bottom: 20px;
            color: white;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: #bbb;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        .footer-social {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .social-icon:hover {
            background: var(--primary-color);
            transform: translateY(-3px);
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #444;
            color: #999;
            font-size: 14px;
        }
        
        /* 响应式设计 */
        @media (max-width: 992px) {
            .header-content {
                flex-wrap: wrap;
            }
            
            .search-container {
                order: 3;
                width: 100%;
                margin: 15px 0 0;
            }
            
            .user-nav {
                margin-left: auto;
            }
        }
        
        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .top-bar-content {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .category-list {
                padding: 10px 0;
            }
            
            .category-item {
                margin-right: 15px;
                font-size: 14px;
            }
        }
        
        /* 通用样式 */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            text-align: center;
        }
        
        .btn:hover {
            background: #ff5252;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.2);
        }
        
        .btn-secondary {
            background: var(--secondary-color);
        }
        
        .btn-secondary:hover {
            background: #3db5ac;
        }
        
        .btn-outline {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }
        
        /* 卡片样式 */
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        /* 消息提示 */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .alert-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
        }
    </style>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <?php if (isset($pageStyles)): ?>
        <!-- 页面特定的CSS -->
        <?php echo $pageStyles; ?>
    <?php endif; ?>
</head>
<body>
    <!-- 主头部 -->
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <!-- Logo -->
                <a href="products.php" class="logo">
                    <i class="fas fa-shopping-bag logo-icon"></i>
                    <span class="logo-text">购物网站</span>
                </a>
                
                <!-- 搜索框 -->
                <div class="search-container">
                    <form class="search-form" method="GET" action="products.php">
                        <input type="text" 
                               name="q" 
                               class="search-input" 
                               placeholder="搜索商品..." 
                               value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                        <button type="submit" class="search-button">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <!-- 用户导航 -->
                <nav class="user-nav">
                    <a href="products.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                        <i class="fas fa-store"></i>
                        <span>商城首页</span>
                    </a>
                    
                    <a href="cart.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i>
                        <span>购物车</span>
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-count"><?php echo $cartCount; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="welcome.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'welcome.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i>
                            <span>我的</span>
                        </a>
                        <a href="logout.php" class="nav-link" onclick="return confirm('确定要退出登录吗？')">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>退出</span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>登录</span>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
        
        <!-- 分类导航 -->
        <nav class="category-nav">
            <div class="container">
                <ul class="category-list">
                    <li class="category-item <?php echo empty($_GET['category']) ? 'active' : ''; ?>">
                        <a href="products.php">全部商品</a>
                    </li>
                    <?php
                    // 显示分类
                    if (isset($pdo)) {
                        try {
                            $categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
                            foreach ($categories as $category) {
                                $active = (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'active' : '';
                                echo '<li class="category-item ' . $active . '">';
                                echo '<a href="products.php?category=' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</a>';
                                echo '</li>';
                            }
                        } catch (Exception $e) {
                            // 静默失败
                        }
                    }
                    ?>
                </ul>
            </div>
        </nav>
    </header>
    
    <!-- 主内容区 -->
    <main class="main-content">
        <div class="container">
            <?php if (isset($message) && $message): ?>
                <div class="alert alert-<?php echo $message_type ?? 'success'; ?>">
                    <span><?php echo htmlspecialchars($message); ?></span>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

<?php
// orders.php - 查看所有订单
require_once 'db_connect.php';
require_once 'product_functions.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 获取当前用户信息
$user = getCurrentUser($pdo);
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// 分页参数
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;

// 获取用户订单
$ordersResult = getUserOrders($pdo, $user['id'], $limit, $page);
$orders = $ordersResult['orders'];
$totalPages = $ordersResult['pages'];
$totalOrders = $ordersResult['total'];

// 获取购物车数量
$cartCount = getCartCount($pdo, $user['id']);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的订单 - 购物网站</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* 使用和welcome.php相同的样式 */
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #4ecdc4;
            --dark-color: #2d3436;
            --light-color: #f9f9f9;
            --gray-color: #636e72;
            --border-color: #e0e0e0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft YaHei', '微软雅黑', sans-serif;
            background-color: #f5f5f5;
            color: var(--dark-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* 头部样式 */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-main {
            padding: 15px 0;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .logo i {
            margin-right: 5px;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-links a {
            color: var(--dark-color);
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            background: #f5f5f5;
        }
        
        .nav-links a.active {
            background: var(--primary-color);
            color: white;
        }
        
        .cart-count-badge {
            position: relative;
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
        
        /* 订单列表 */
        .orders-container {
            padding: 40px 0;
        }
        
        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .orders-title {
            font-size: 28px;
            color: var(--dark-color);
        }
        
        .orders-stats {
            background: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        /* 订单卡片 */
        .order-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .order-info {
            flex: 1;
        }
        
        .order-number {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .order-date {
            color: var(--gray-color);
            font-size: 14px;
        }
        
        .order-status {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 15px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        /* 订单商品 */
        .order-products {
            margin-bottom: 20px;
        }
        
        .order-product {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-product:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            overflow: hidden;
            border-radius: 4px;
            margin-right: 15px;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .product-price {
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .product-quantity {
            color: var(--gray-color);
            font-size: 14px;
        }
        
        /* 订单底部 */
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        
        .order-amount {
            font-size: 18px;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #ff5252;
        }
        
        .btn-outline {
            background: white;
            color: var(--dark-color);
            border: 1px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: #f5f5f5;
        }
        
        /* 分页 */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
        }
        
        .page-link {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--dark-color);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .page-link:hover {
            background: #f5f5f5;
        }
        
        .page-link.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        /* 空状态 */
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
        }
        
        .empty-orders i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-orders h3 {
            font-size: 24px;
            color: var(--gray-color);
            margin-bottom: 10px;
        }
        
        /* 页脚 */
        .footer {
            background: var(--dark-color);
            color: white;
            padding: 40px 0;
            margin-top: 60px;
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #444;
            margin-top: 30px;
            color: #bbb;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- 头部 -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="products.php" class="logo">
                    <i class="fas fa-shopping-bag"></i> 购物网站
                </a>
                
                <div class="nav-links">
                    <a href="products.php">
                        <i class="fas fa-store"></i> 商城首页
                    </a>
                    <a href="cart.php" class="cart-count-badge">
                        <i class="fas fa-shopping-cart"></i> 购物车
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-count"><?php echo $cartCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="welcome.php">
                        <i class="fas fa-user"></i> 个人中心
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- 订单列表 -->
    <main class="container">
        <div class="orders-container">
            <div class="orders-header">
                <h1 class="orders-title">
                    <i class="fas fa-clipboard-list"></i> 我的订单
                </h1>
                <div class="orders-stats">
                    共 <strong><?php echo $totalOrders; ?></strong> 个订单
                </div>
            </div>
            
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <div class="order-number">订单号：<?php echo $order['order_number']; ?></div>
                            <div class="order-date">
                                下单时间：<?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?>
                                <?php if (!empty($order['shipping_address'])): ?>
                                | 收货地址：<?php echo htmlspecialchars(mb_substr($order['shipping_address'], 0, 30)); ?>...
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="order-status status-<?php echo $order['order_status']; ?>">
                            <?php 
                            $statusTexts = [
                                'pending' => '待处理',
                                'processing' => '处理中',
                                'shipped' => '已发货',
                                'delivered' => '已送达',
                                'cancelled' => '已取消',
                                'completed' => '已完成'
                            ];
                            echo $statusTexts[$order['order_status']] ?? $order['order_status'];
                            ?>
                        </div>
                    </div>
                    
                    <div class="order-products">
                        <?php foreach ($order['items'] as $item): ?>
                        <div class="order-product">
                            <div class="product-image">
                                <?php if (!empty($item['main_image'])): ?>
                                    <img src="uploads/products/<?php echo htmlspecialchars($item['main_image']); ?>" alt="商品图片">
                                <?php else: ?>
                                    <div style="width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#666;">
                                        <i class="fas fa-box"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="product-price">¥<?php echo number_format($item['product_price'], 2); ?></div>
                                <div class="product-quantity">× <?php echo $item['quantity']; ?></div>
                            </div>
                            <div class="product-subtotal">¥<?php echo number_format($item['subtotal'], 2); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="order-footer">
                        <div class="order-amount">
                            实付：¥<?php echo number_format($order['final_amount'], 2); ?>
                        </div>
                        <div class="order-actions">
                            <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-outline">
                                查看详情
                            </a>
                            <?php if ($order['order_status'] == 'pending'): ?>
                            <a href="#" class="btn btn-outline">
                                取消订单
                            </a>
                            <?php elseif ($order['order_status'] == 'completed'): ?>
                            <a href="#" class="btn btn-outline">
                                再次购买
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- 分页 -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="page-link">上一页</a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="page-link">下一页</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-orders">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>暂无订单</h3>
                    <p>您还没有购买过任何商品</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-store"></i> 去商城逛逛
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- 页脚 -->
    <footer class="footer">
        <div class="container">
            <div class="copyright">
                &copy; 2023 购物网站 版权所有
            </div>
        </div>
    </footer>
</body>
</html>
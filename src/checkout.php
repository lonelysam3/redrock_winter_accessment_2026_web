<?php
// checkout.php - 订单结算页面
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

// 获取购物车商品
$cartItems = [];
$totalAmount = 0;

try {
    $sql = "SELECT ci.*, p.name, p.price, p.main_image, p.stock_quantity, 
                   (ci.quantity * p.price) as subtotal
            FROM cart_items ci 
            JOIN products p ON ci.product_id = p.id 
            WHERE ci.user_id = ? AND p.is_active = TRUE
            ORDER BY ci.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id']]);
    $cartItems = $stmt->fetchAll();
    
    // 计算总金额
    foreach ($cartItems as $item) {
        $totalAmount += $item['subtotal'];
    }
    
} catch (PDOException $e) {
    $message = '获取购物车失败: ' . $e->getMessage();
    $message_type = 'error';
}

// 检查购物车是否为空
if (empty($cartItems)) {
    header("Location: cart.php");
    exit();
}

// 处理支付提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '请求无效，请刷新页面后重试';
        $message_type = 'error';
    } elseif (isset($_POST['confirm_order'])) {
        try {
            $pdo->beginTransaction();
            
            // 检查库存
            foreach ($cartItems as $item) {
                if ($item['stock_quantity'] < $item['quantity']) {
                    throw new Exception("商品 {$item['name']} 库存不足，仅剩 {$item['stock_quantity']} 件");
                }
            }
            
            // 检查余额是否足够
            $userBalance = $user['balance']; // 单位：分
            $orderAmount = $totalAmount * 100; // 转换为分
            
            if ($userBalance < $orderAmount) {
                throw new Exception("账户余额不足，当前余额：¥" . number_format($userBalance / 100, 2));
            }
            
            // 生成订单号
            $orderNumber = date('YmdHis') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            // 获取收货地址
            $shippingAddress = '';
            if (!empty($user['province'])) {
                $shippingAddress = $user['province'] . $user['city'] . $user['district'] . $user['detailed_address'];
                if (!empty($user['zipcode'])) {
                    $shippingAddress .= "（邮编：{$user['zipcode']}）";
                }
            } else {
                $shippingAddress = '未设置收货地址';
            }
            
            // 创建订单
            $orderSql = "INSERT INTO orders (order_number, user_id, total_amount, final_amount, 
                        shipping_address, payment_method, payment_status, order_status) 
                        VALUES (?, ?, ?, ?, ?, '余额支付', 'paid', 'pending')";
            
            $orderStmt = $pdo->prepare($orderSql);
            $orderStmt->execute([
                $orderNumber,
                $user['id'],
                $totalAmount,
                $totalAmount,
                $shippingAddress
            ]);
            
            $orderId = $pdo->lastInsertId();
            
            // 创建订单商品
            foreach ($cartItems as $item) {
                $itemSql = "INSERT INTO order_items (order_id, product_id, product_name, 
                          product_price, quantity, subtotal) 
                          VALUES (?, ?, ?, ?, ?, ?)";
                
                $itemStmt = $pdo->prepare($itemSql);
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    $item['price'],
                    $item['quantity'],
                    $item['subtotal']
                ]);
                
                // 减少商品库存，增加销量
                $updateStockSql = "UPDATE products 
                                  SET stock_quantity = stock_quantity - ?, 
                                      sold_count = sold_count + ? 
                                  WHERE id = ?";
                $updateStmt = $pdo->prepare($updateStockSql);
                $updateStmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
            }
            
            // 扣除用户余额
            $newBalance = $userBalance - $orderAmount;
            $updateBalanceSql = "UPDATE users SET balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $updateBalanceStmt = $pdo->prepare($updateBalanceSql);
            $updateBalanceStmt->execute([$newBalance, $user['id']]);
            
            // 清空购物车
            $clearCartSql = "DELETE FROM cart_items WHERE user_id = ?";
            $clearStmt = $pdo->prepare($clearCartSql);
            $clearStmt->execute([$user['id']]);
            
            $pdo->commit();
            
            // 跳转到订单详情页面
            header("Location: order_detail.php?id=" . $orderId);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("订单创建失败: " . $e->getMessage());
            $message = '订单创建失败，请稍后再试';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单结算 - 购物网站</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
        
        /* 结算页面内容 */
        .checkout-container {
            padding: 40px 0;
        }
        
        .checkout-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .checkout-title {
            font-size: 28px;
            color: var(--dark-color);
        }
        
        .checkout-steps {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gray-color);
        }
        
        .step.active {
            color: var(--primary-color);
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .step.active .step-number {
            background: var(--primary-color);
            color: white;
        }
        
        /* 结算内容布局 */
        .checkout-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        
        @media (max-width: 992px) {
            .checkout-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* 收货地址 */
        .shipping-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title a {
            font-size: 14px;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .address-info {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .address-line {
            margin-bottom: 5px;
        }
        
        .no-address {
            text-align: center;
            padding: 30px;
            color: var(--gray-color);
        }
        
        .no-address i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        /* 商品列表 */
        .products-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .checkout-item {
            display: flex;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .checkout-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            overflow: hidden;
            border-radius: 4px;
            margin-right: 15px;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .item-price {
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .item-quantity {
            color: var(--gray-color);
        }
        
        .item-subtotal {
            font-weight: bold;
            min-width: 80px;
            text-align: right;
        }
        
        /* 订单摘要 */
        .order-summary {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            color: var(--gray-color);
        }
        
        .summary-row.total {
            font-size: 20px;
            font-weight: bold;
            color: var(--primary-color);
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            margin-top: 15px;
        }
        
        /* 余额信息 */
        .balance-info {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .balance-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .balance-amount {
            font-size: 18px;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .balance-after {
            border-top: 1px dashed var(--border-color);
            padding-top: 10px;
            margin-top: 10px;
        }
        
        /* 支付按钮 */
        .payment-button {
            width: 100%;
            padding: 18px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .payment-button:hover:not(:disabled) {
            background: #ff5252;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.2);
        }
        
        .payment-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        /* 消息提示 */
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
                        <span class="cart-count"><?php echo count($cartItems); ?></span>
                    </a>
                    <a href="welcome.php">
                        <i class="fas fa-user"></i> 个人中心
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- 消息提示 -->
    <?php if (isset($message)): ?>
        <div class="container">
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 结算内容 -->
    <main class="container">
        <div class="checkout-container">
            <div class="checkout-header">
                <h1 class="checkout-title">
                    <i class="fas fa-shopping-cart"></i> 订单结算
                </h1>
                <a href="cart.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> 返回购物车
                </a>
            </div>
            
            <!-- 步骤指示器 -->
            <div class="checkout-steps">
                <div class="step active">
                    <div class="step-number">1</div>
                    <span>确认订单</span>
                </div>
                <i class="fas fa-chevron-right"></i>
                <div class="step active">
                    <div class="step-number">2</div>
                    <span>支付订单</span>
                </div>
                <i class="fas fa-chevron-right"></i>
                <div class="step">
                    <div class="step-number">3</div>
                    <span>完成订单</span>
                </div>
            </div>
            
            <div class="checkout-content">
                <!-- 左侧：收货地址和商品信息 -->
                <div>
                    <!-- 收货地址 -->
                    <div class="shipping-section">
                        <h2 class="section-title">
                            收货地址
                            <a href="welcome.php">修改地址</a>
                        </h2>
                        
                        <?php if (!empty($user['detailed_address'])): ?>
                        <div class="address-info">
                            <div class="address-line">
                                <strong>收货人：</strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                            </div>
                            <div class="address-line">
                                <strong>联系电话：</strong><?php echo htmlspecialchars($user['phone'] ?: '未设置'); ?>
                            </div>
                            <div class="address-line">
                                <strong>收货地址：</strong>
                                <?php 
                                echo htmlspecialchars($user['province'] ?? '') . ' ';
                                echo htmlspecialchars($user['city'] ?? '') . ' ';
                                echo htmlspecialchars($user['district'] ?? '') . ' ';
                                echo htmlspecialchars($user['detailed_address'] ?? '');
                                if (!empty($user['zipcode'])) {
                                    echo "（邮编：" . htmlspecialchars($user['zipcode']) . "）";
                                }
                                ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="no-address">
                            <i class="fas fa-map-marker-alt"></i>
                            <h3>暂无收货地址</h3>
                            <p>请先设置收货地址后再下单</p>
                            <a href="welcome.php" class="btn btn-primary" style="margin-top: 15px;">
                                去设置地址
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 商品列表 -->
                    <div class="products-section">
                        <h2 class="section-title">商品清单</h2>
                        
                        <?php foreach ($cartItems as $item): ?>
                        <div class="checkout-item">
                            <div class="item-image">
                                <?php
                                $image_path = '';
                                if (!empty($item['main_image'])) {
                                    $image_path = 'uploads/products/' . $item['main_image'];
                                    if (!file_exists($image_path)) {
                                        $image_path = '';
                                    }
                                }
                                
                                if ($image_path && file_exists($image_path)) {
                                    echo '<img src="' . htmlspecialchars($image_path) . '" alt="' . htmlspecialchars($item['name']) . '">';
                                } else {
                                    $initial = mb_substr($item['name'], 0, 1, 'UTF-8');
                                    echo '<div style="width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#666;font-size:20px;font-weight:bold;">' . htmlspecialchars($initial) . '</div>';
                                }
                                ?>
                            </div>
                            <div class="item-details">
                                <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="item-price">¥<?php echo number_format($item['price'], 2); ?></div>
                                <div class="item-quantity">× <?php echo $item['quantity']; ?></div>
                            </div>
                            <div class="item-subtotal">
                                ¥<?php echo number_format($item['subtotal'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- 右侧：订单摘要 -->
                <div class="order-summary">
                    <h2 class="section-title">订单摘要</h2>
                    
                    <div class="summary-row">
                        <span>商品总价</span>
                        <span>¥<?php echo number_format($totalAmount, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>运费</span>
                        <span>¥0.00</span>
                    </div>
                    
                    <div class="summary-row total">
                        <span>应付总额</span>
                        <span>¥<?php echo number_format($totalAmount, 2); ?></span>
                    </div>
                    
                    <!-- 余额信息 -->
                    <div class="balance-info">
                        <div class="balance-row">
                            <span>账户余额</span>
                            <span class="balance-amount">¥<?php echo number_format($user['balance'] / 100, 2); ?></span>
                        </div>
                        
                        <div class="balance-row balance-after">
                            <span>支付后余额</span>
                            <span class="balance-amount" style="color: <?php echo ($user['balance'] / 100 - $totalAmount >= 0) ? 'var(--primary-color)' : 'red'; ?>">
                                ¥<?php echo number_format($user['balance'] / 100 - $totalAmount, 2); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- 支付按钮 -->
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                        <?php 
                        $hasAddress = !empty($user['detailed_address']);
                        $hasSufficientBalance = ($user['balance'] / 100) >= $totalAmount;
                        $canCheckout = $hasAddress && $hasSufficientBalance && !empty($cartItems);
                        ?>
                        
                        <button type="submit" name="confirm_order" class="payment-button" 
                                <?php echo $canCheckout ? '' : 'disabled'; ?>>
                            <i class="fas fa-credit-card"></i>
                            <?php if ($canCheckout): ?>
                                确认支付 ¥<?php echo number_format($totalAmount, 2); ?>
                            <?php elseif (!$hasAddress): ?>
                                请先设置收货地址
                            <?php elseif (!$hasSufficientBalance): ?>
                                余额不足
                            <?php else: ?>
                                无法支付
                            <?php endif; ?>
                        </button>
                        
                    </form>
                    
                </div>
            </div>
        </div>
    </main>

    <script>
        // 自动隐藏消息提示
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>
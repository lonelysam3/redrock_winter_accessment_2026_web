<?php
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
    $totalAmount = 0;
    foreach ($cartItems as $item) {
        $totalAmount += $item['subtotal'];
    }
    
} catch (PDOException $e) {
    error_log("获取购物车失败: " . $e->getMessage());
    $cartItems = [];
    $totalAmount = 0;
}

// 处理购物车操作
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证 CSRF 令牌
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header("Location: cart.php");
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    $item_id = intval($_POST['item_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($action === 'update_quantity' && $item_id) {
        try {
            // 检查库存
            $checkSql = "SELECT p.stock_quantity FROM cart_items ci 
                        JOIN products p ON ci.product_id = p.id 
                        WHERE ci.id = ? AND ci.user_id = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$item_id, $user['id']]);
            $product = $checkStmt->fetch();
            
            if ($product && $quantity <= $product['stock_quantity'] && $quantity > 0) {
                $updateSql = "UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ? AND user_id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$quantity, $item_id, $user['id']]);
                $message = '数量更新成功！';
                $message_type = 'success';
            } else {
                $message = '库存不足或数量无效';
                $message_type = 'error';
            }
            
            // 刷新页面
            header("Location: cart.php");
            exit();
            
        } catch (PDOException $e) {
            error_log("更新购物车失败: " . $e->getMessage());
            $message = '更新失败，请稍后再试';
            $message_type = 'error';
        }
    }
    
    if ($action === 'remove_item' && $item_id) {
        try {
            $deleteSql = "DELETE FROM cart_items WHERE id = ? AND user_id = ?";
            $deleteStmt = $pdo->prepare($deleteSql);
            $deleteStmt->execute([$item_id, $user['id']]);
            $message = '商品已移除';
            $message_type = 'success';
            
            // 刷新页面
            header("Location: cart.php");
            exit();
            
        } catch (PDOException $e) {
            error_log("移除购物车商品失败: " . $e->getMessage());
            $message = '移除失败，请稍后再试';
            $message_type = 'error';
        }
    }
    
    if ($action === 'clear_cart') {
        try {
            $clearSql = "DELETE FROM cart_items WHERE user_id = ?";
            $clearStmt = $pdo->prepare($clearSql);
            $clearStmt->execute([$user['id']]);
            $message = '购物车已清空';
            $message_type = 'success';
            
            // 刷新页面
            header("Location: cart.php");
            exit();
            
        } catch (PDOException $e) {
            error_log("清空购物车失败: " . $e->getMessage());
            $message = '清空失败，请稍后再试';
            $message_type = 'error';
        }
    }
    
    if ($action === 'checkout') {
        // 跳转到确认订单页面
        if (count($cartItems) > 0) {
            header("Location: checkout.php");
            exit();
        } else {
            $message = '购物车为空，无法结算';
            $message_type = 'error';
        }
    }
}

// 获取购物车商品数量
$cartCount = getCartCount($pdo, $user['id']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>购物车 - 购物网站</title>
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
        
        /* 购物车内容 */
        .cart-container {
            padding: 40px 0;
        }
        
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .cart-title {
            font-size: 28px;
            color: var(--dark-color);
        }
        
        .cart-actions {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #ff5252;
        }
        
        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #3db5ac;
        }
        
        .btn-outline {
            background: white;
            color: var(--dark-color);
            border: 1px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: #f5f5f5;
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
        
        /* 购物车内容 */
        .cart-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }
        
        @media (max-width: 992px) {
            .cart-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* 购物车商品列表 */
        .cart-items {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
            align-items: center;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 100px;
            height: 100px;
            overflow: hidden;
            border-radius: 4px;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .item-name a {
            color: var(--dark-color);
            text-decoration: none;
        }
        
        .item-name a:hover {
            color: var(--primary-color);
        }
        
        .item-price {
            color: var(--primary-color);
            font-weight: bold;
            font-size: 18px;
        }
        
        .item-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .quantity-btn {
            width: 35px;
            height: 35px;
            background: #f5f5f5;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .quantity-input {
            width: 50px;
            height: 35px;
            border: none;
            text-align: center;
            font-size: 16px;
        }
        
        .remove-btn {
            background: none;
            border: none;
            color: #ff6b6b;
            cursor: pointer;
            font-size: 18px;
        }
        
        /* 购物车摘要 */
        .cart-summary {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .summary-title {
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
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
        
        /* 空购物车 */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
        }
        
        .empty-cart i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-cart h3 {
            font-size: 24px;
            color: var(--gray-color);
            margin-bottom: 15px;
        }
        
        /* 继续购物推荐 */
        .continue-shopping {
            margin-top: 40px;
            padding: 30px;
            background: white;
            border-radius: 8px;
        }
        
        .continue-title {
            font-size: 20px;
            margin-bottom: 20px;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .product-card-mini {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .product-card-mini:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .product-image-mini {
            height: 150px;
            overflow: hidden;
        }
        
        .product-image-mini img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info-mini {
            padding: 15px;
        }
        
        .product-name-mini {
            font-size: 14px;
            margin-bottom: 10px;
            height: 40px;
            overflow: hidden;
        }
        
        .product-price-mini {
            font-size: 16px;
            font-weight: bold;
            color: var(--primary-color);
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
                    <a href="cart.php" class="cart-count-badge active">
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

    <!-- 消息提示 -->
    <?php if ($message): ?>
        <div class="container">
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 购物车内容 -->
    <main class="container">
        <div class="cart-container">
            <div class="cart-header">
                <h1 class="cart-title">
                    <i class="fas fa-shopping-cart"></i> 我的购物车
                </h1>
                
                <div class="cart-actions">
                    <a href="products.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> 继续购物
                    </a>
                    <?php if (!empty($cartItems)): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                        <button type="submit" name="action" value="clear_cart" class="btn btn-outline" onclick="return confirm('确定要清空购物车吗？')">
                            <i class="fas fa-trash"></i> 清空购物车
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($cartItems)): ?>
                <div class="cart-content">
                    <!-- 购物车商品列表 -->
                    <div class="cart-items">
                        <?php foreach ($cartItems as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <?php
                                // 构建图片路径
                                $image_path = '';
                                if (!empty($item['main_image'])) {
                                    $image_path = 'uploads/products/' . $item['main_image'];
                                    // 检查图片文件是否存在
                                    if (!file_exists($image_path)) {
                                        $image_path = ''; // 文件不存在，使用默认图片
                                    }
                                }
                                
                                // 使用图片或默认图片
                                if ($image_path && file_exists($image_path)) {
                                    echo '<img src="' . htmlspecialchars($image_path) . '" 
                                        alt="' . htmlspecialchars($item['name']) . '">';
                                } else {
                                    // 使用占位符，包含商品名称的首字母
                                    $initial = mb_substr($item['name'], 0, 1, 'UTF-8');
                                    echo '<div style="width:100px;height:100px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;border-radius:4px;">
                                            <span style="font-size:24px;color:#666;">' . htmlspecialchars($initial) . '</span>
                                        </div>';
                                }
                                ?>
                            </div>
                            
                            <div class="item-info">
                                <h3 class="item-name">
                                    <a href="product_detail.php?id=<?php echo $item['product_id']; ?>">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                </h3>
                                <div class="item-price">¥<?php echo number_format($item['price'], 2); ?></div>
                            </div>
                            
                            <div class="item-actions">
                                <form method="POST" class="quantity-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <div class="quantity-control">
                                        <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, -1)">-</button>
                                        <input type="number" name="quantity" class="quantity-input" 
                                               value="<?php echo $item['quantity']; ?>" 
                                               min="1" max="<?php echo $item['stock_quantity']; ?>"
                                               onchange="updateQuantityInput(<?php echo $item['id']; ?>, this.value)">
                                        <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)">+</button>
                                    </div>
                                    <input type="hidden" name="action" value="update_quantity">
                                </form>
                                
                                <div class="item-subtotal">¥<?php echo number_format($item['subtotal'], 2); ?></div>
                                
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="action" value="remove_item">
                                    <button type="submit" class="remove-btn" onclick="return confirm('确定要移除这件商品吗？')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="item-image">
                            <?php
                            $image_path = '';
                            if (!empty($item['main_image'])) {
                                if (file_exists('uploads/products/' . $item['main_image'])) {
                                    $image_path = 'uploads/products/' . $item['main_image'];
                                } else {
                                    $image_path = 'https://via.placeholder.com/100x100?text=No+Image';
                                }
                            } else {
                                $image_path = 'https://via.placeholder.com/100x100?text=No+Image';
                            }
                            ?>
                            <img src="<?php echo $image_path; ?>" 
                                alt="<?php echo htmlspecialchars($item['name']); ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- 购物车摘要 -->
                    <div class="cart-summary">
                        <h3 class="summary-title">订单摘要</h3>
                        
                        <div class="summary-row">
                            <span>商品数量</span>
                            <span><?php echo count($cartItems); ?> 件</span>
                        </div>
                        
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
                        
                        <form method="POST" style="margin-top: 25px;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                            <input type="hidden" name="action" value="checkout">
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 18px;">
                                <i class="fas fa-credit-card"></i> 去结算
                            </button>
                        </form>
                        
                        <div style="margin-top: 20px; text-align: center;">
                            <a href="products.php" style="color: var(--gray-color); text-decoration: none;">
                                <i class="fas fa-arrow-left"></i> 继续购物
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- 空购物车 -->
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>您的购物车是空的</h3>
                    <p>快去添加您心仪的商品吧！</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top: 20px; display: inline-block; padding: 12px 30px;">
                        <i class="fas fa-store"></i> 去商城逛逛
                    </a>
                </div>
                
                <!-- 推荐商品 -->
                <?php
                // 获取推荐商品
                try {
                    $recommendSql = "SELECT * FROM products WHERE is_active = TRUE AND stock_quantity > 0 ORDER BY RAND() LIMIT 4";
                    $recommendStmt = $pdo->query($recommendSql);
                    $recommendedProducts = $recommendStmt->fetchAll();
                } catch (PDOException $e) {
                    $recommendedProducts = [];
                }
                ?>
                
                <?php if (!empty($recommendedProducts)): ?>
                <div class="continue-shopping">
                    <h3 class="continue-title">猜你喜欢</h3>
                    <div class="product-grid">
                        <?php foreach ($recommendedProducts as $product): ?>
                        <div class="product-card-mini">
                            <div class="product-image-mini">
                                <a href="product_detail.php?id=<?php echo $product['id']; ?>">
                                    <img src="<?php echo $product['main_image'] ? 'uploads/products/' . $product['main_image'] : 'https://via.placeholder.com/200x150?text=No+Image'; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                </a>
                            </div>
                            <div class="product-info-mini">
                                <h4 class="product-name-mini">
                                    <a href="product_detail.php?id=<?php echo $product['id']; ?>" style="color: var(--dark-color); text-decoration: none;">
                                        <?php echo htmlspecialchars(mb_substr($product['name'], 0, 20)); ?>...
                                    </a>
                                </h4>
                                <div class="product-price-mini">¥<?php echo number_format($product['price'], 2); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // 更新商品数量
        function updateQuantity(itemId, delta) {
            const form = document.querySelector(`.quantity-form input[name="item_id"][value="${itemId}"]`).closest('form');
            const quantityInput = form.querySelector('.quantity-input');
            let quantity = parseInt(quantityInput.value) || 1;
            
            // 获取最大库存
            const maxStock = parseInt(quantityInput.getAttribute('max')) || 999;
            
            quantity += delta;
            if (quantity < 1) quantity = 1;
            if (quantity > maxStock) quantity = maxStock;
            
            quantityInput.value = quantity;
            
            // 提交表单
            form.submit();
        }
        
        // 输入框更新数量
        function updateQuantityInput(itemId, value) {
            const form = document.querySelector(`.quantity-form input[name="item_id"][value="${itemId}"]`).closest('form');
            const quantityInput = form.querySelector('.quantity-input');
            
            // 获取最大库存
            const maxStock = parseInt(quantityInput.getAttribute('max')) || 999;
            
            let quantity = parseInt(value) || 1;
            if (quantity < 1) quantity = 1;
            if (quantity > maxStock) quantity = maxStock;
            
            quantityInput.value = quantity;
            
            // 提交表单
            form.submit();
        }
        
        // 确认清空购物车
        function confirmClearCart() {
            return confirm('确定要清空购物车吗？此操作不可撤销。');
        }
    </script>
</body>
</html>
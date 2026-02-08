<?php
// create_direct_order.php - 创建直接购买订单
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

// 获取商品信息
$product_id = $_POST['product_id'] ?? 0;
$quantity = max(1, intval($_POST['quantity'] ?? 1));

if (!$product_id) {
    header("Location: products.php");
    exit();
}

// 获取商品详情
$product = getProductById($pdo, $product_id);
if (!$product) {
    header("Location: products.php?error=商品不存在");
    exit();
}

// 检查库存
if ($product['stock_quantity'] < $quantity) {
    header("Location: product_detail.php?id=" . $product_id . "&error=库存不足");
    exit();
}

// 检查收货地址
if (empty($user['detailed_address'])) {
    header("Location: buy_now.php?product_id=" . $product_id . "&quantity=" . $quantity . "&error=请先设置收货地址");
    exit();
}

// 计算总金额
$totalAmount = $product['price'] * $quantity;

// 检查余额是否足够
if (($user['balance'] / 100) < $totalAmount) {
    header("Location: buy_now.php?product_id=" . $product_id . "&quantity=" . $quantity . "&error=余额不足");
    exit();
}

try {
    $pdo->beginTransaction();
    
    // 生成订单号
    $orderNumber = date('YmdHis') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    // 获取收货地址
    $shippingAddress = $user['province'] . $user['city'] . $user['district'] . $user['detailed_address'];
    if (!empty($user['zipcode'])) {
        $shippingAddress .= "（邮编：{$user['zipcode']}）";
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
    $itemSql = "INSERT INTO order_items (order_id, product_id, product_name, 
              product_price, quantity, subtotal) 
              VALUES (?, ?, ?, ?, ?, ?)";
    
    $itemStmt = $pdo->prepare($itemSql);
    $subtotal = $product['price'] * $quantity;
    $itemStmt->execute([
        $orderId,
        $product['id'],
        $product['name'],
        $product['price'],
        $quantity,
        $subtotal
    ]);
    
    // 减少商品库存，增加销量
    $updateStockSql = "UPDATE products 
                      SET stock_quantity = stock_quantity - ?, 
                          sold_count = sold_count + ? 
                      WHERE id = ?";
    $updateStmt = $pdo->prepare($updateStockSql);
    $updateStmt->execute([$quantity, $quantity, $product['id']]);
    
    // 扣除用户余额
    $orderAmountInCents = $totalAmount * 100; // 转换为分
    $newBalance = $user['balance'] - $orderAmountInCents;
    $updateBalanceSql = "UPDATE users SET balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $updateBalanceStmt = $pdo->prepare($updateBalanceSql);
    $updateBalanceStmt->execute([$newBalance, $user['id']]);
    
    $pdo->commit();
    
    // 跳转到订单详情页面
    header("Location: order_detail.php?id=" . $orderId);
    exit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: buy_now.php?product_id=" . $product_id . "&quantity=" . $quantity . "&error=订单创建失败：" . urlencode($e->getMessage()));
    exit();
}
?>
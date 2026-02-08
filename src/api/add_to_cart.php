<?php
header('Content-Type: application/json');
session_start();
require_once '../db_connect.php';
require_once '../product_functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$product_id = intval($data['product_id'] ?? 0);
$quantity = intval($data['quantity'] ?? 1);

if ($product_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit();
}

// 检查商品是否存在和库存
try {
    $stmt = $pdo->prepare("SELECT id, stock_quantity FROM products WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => '商品不存在']);
        exit();
    }
    
    if ($product['stock_quantity'] < $quantity) {
        echo json_encode(['success' => false, 'message' => '库存不足']);
        exit();
    }
    
    // 添加到购物车
    $result = addToCart($pdo, $_SESSION['user_id'], $product_id, $quantity);
    
    if ($result) {
        $cartCount = getCartCount($pdo, $_SESSION['user_id']);
        echo json_encode(['success' => true, 'cart_count' => $cartCount]);
    } else {
        echo json_encode(['success' => false, 'message' => '添加到购物车失败']);
    }
    
} catch (PDOException $e) {
    error_log("添加到购物车API错误: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '服务器错误']);
}
?>
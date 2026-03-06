<?php
// buy_now.php - 直接购买商品（不经过购物车）
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
$product_id = $_GET['product_id'] ?? 0;
$quantity = max(1, intval($_GET['quantity'] ?? 1));

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

// 准备结算数据
$cartItems = [[
    'product_id' => $product['id'],
    'name' => $product['name'],
    'price' => $product['price'],
    'quantity' => $quantity,
    'main_image' => $product['main_image'],
    'stock_quantity' => $product['stock_quantity'],
    'subtotal' => $product['price'] * $quantity
]];

$totalAmount = $product['price'] * $quantity;
?>
<?php
$pageTitle = '直接购买';
$pageStyles = '
<style>
    .buy-now-container { padding: 40px 0; }
    .page-title { font-size: 28px; margin-bottom: 30px; color: var(--dark-color); }
    .product-summary { background: white; border-radius: 8px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .product-row { display: flex; align-items: center; padding: 15px; border-bottom: 1px solid var(--border-color); }
    .product-row:last-child { border-bottom: none; }
    .product-image { width: 80px; height: 80px; overflow: hidden; border-radius: 4px; margin-right: 15px; }
    .product-image img { width: 100%; height: 100%; object-fit: cover; }
    .product-info { flex: 1; }
    .product-name { font-weight: 500; margin-bottom: 5px; }
    .product-price { color: var(--primary-color); font-weight: bold; }
    .product-quantity { min-width: 100px; text-align: center; }
    .product-subtotal { min-width: 100px; text-align: right; font-weight: bold; }
    .shipping-section { background: white; border-radius: 8px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .section-title { font-size: 18px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color); }
    .address-info { padding: 15px; background: #f9f9f9; border-radius: 4px; }
    .address-line { margin-bottom: 5px; }
    .no-address { text-align: center; padding: 30px; color: var(--gray-color); }
    .order-summary { background: white; border-radius: 8px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; color: var(--gray-color); }
    .summary-row.total { font-size: 20px; font-weight: bold; color: var(--primary-color); padding-top: 15px; border-top: 1px solid var(--border-color); margin-top: 15px; }
    .balance-info { background: #f9f9f9; border-radius: 8px; padding: 15px; margin: 20px 0; }
    .balance-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .balance-amount { font-size: 18px; font-weight: bold; color: var(--primary-color); }
    .balance-after { border-top: 1px dashed var(--border-color); padding-top: 10px; margin-top: 10px; }
    .payment-button { width: 100%; padding: 18px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .payment-button:hover:not(:disabled) { background: #ff5252; transform: translateY(-2px); }
    .payment-button:disabled { background: #ccc; cursor: not-allowed; }
</style>
';
require_once 'header.php';
?>

        <div class="buy-now-container">
            <h1 class="page-title">
                <i class="fas fa-bolt"></i> 直接购买
            </h1>
            
            <!-- 商品信息 -->
            <div class="product-summary">
                <h2 class="section-title">购买商品</h2>
                <div class="product-row">
                    <div class="product-image">
                        <?php
                        $image_path = '';
                        if (!empty($product['main_image'])) {
                            $image_path = 'uploads/products/' . $product['main_image'];
                            if (!file_exists($image_path)) {
                                $image_path = '';
                            }
                        }
                        
                        if ($image_path && file_exists($image_path)) {
                            echo '<img src="' . htmlspecialchars($image_path) . '" alt="' . htmlspecialchars($product['name']) . '">';
                        } else {
                            $initial = mb_substr($product['name'], 0, 1, 'UTF-8');
                            echo '<div style="width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#666;font-size:20px;font-weight:bold;">' . htmlspecialchars($initial) . '</div>';
                        }
                        ?>
                    </div>
                    <div class="product-info">
                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                        <div class="product-price">¥<?php echo number_format($product['price'], 2); ?></div>
                    </div>
                    <div class="product-quantity">
                        × <?php echo $quantity; ?>
                    </div>
                    <div class="product-subtotal">
                        ¥<?php echo number_format($totalAmount, 2); ?>
                    </div>
                </div>
            </div>
            
            <!-- 收货地址 -->
            <div class="shipping-section">
                <h2 class="section-title">
                    收货地址
                    <a href="welcome.php" style="font-size: 14px; color: var(--primary-color); text-decoration: none;">修改</a>
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
                    <p>请先设置收货地址后才能购买</p>
                    <a href="welcome.php" class="btn" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 4px;">
                        去设置地址
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 订单摘要 -->
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
                <form method="POST" action="create_direct_order.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    <input type="hidden" name="quantity" value="<?php echo $quantity; ?>">
                    
                    <?php 
                    $hasAddress = !empty($user['detailed_address']);
                    $hasSufficientBalance = ($user['balance'] / 100) >= $totalAmount;
                    $canCheckout = $hasAddress && $hasSufficientBalance;
                    ?>
                    
                    <button type="submit" class="payment-button" 
                            <?php echo $canCheckout ? '' : 'disabled'; ?>>
                        <i class="fas fa-credit-card"></i>
                        <?php if ($canCheckout): ?>
                            立即支付 ¥<?php echo number_format($totalAmount, 2); ?>
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

<?php require_once 'footer.php'; ?>

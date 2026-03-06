<?php
// order_detail.php - 订单详情页面
require_once 'db_connect.php';
require_once 'product_functions.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 获取订单ID
$order_id = $_GET['id'] ?? 0;
if (!$order_id) {
    header("Location: welcome.php");
    exit();
}

// 获取订单详情
$order = getOrderDetails($pdo, $order_id, $_SESSION['user_id']);
if (!$order) {
    header("Location: welcome.php?error=订单不存在或无权查看");
    exit();
}

// 获取当前用户信息（用于显示余额）
$user = getCurrentUser($pdo);
?>
<?php
$pageTitle = '订单详情';
$pageStyles = '
<style>
    .order-detail-container { padding: 40px 0; }
    .order-header { background: white; border-radius: 8px; padding: 30px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .order-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
    .order-number { font-size: 24px; font-weight: bold; color: var(--dark-color); }
    .order-status { padding: 8px 20px; border-radius: 20px; font-weight: bold; font-size: 14px; }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-processing { background: #cce5ff; color: #004085; }
    .status-completed { background: #d4edda; color: #155724; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    .order-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
    .info-item { padding: 15px; background: #f9f9f9; border-radius: 4px; }
    .info-label { font-size: 14px; color: var(--gray-color); margin-bottom: 5px; }
    .info-value { font-weight: 500; }
    .order-items { background: white; border-radius: 8px; padding: 30px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .order-item { display: flex; align-items: center; padding: 20px 0; border-bottom: 1px solid var(--border-color); }
    .order-item:last-child { border-bottom: none; }
    .order-item-image { width: 80px; height: 80px; overflow: hidden; border-radius: 4px; margin-right: 15px; }
    .order-item-image img { width: 100%; height: 100%; object-fit: cover; }
    .order-item-details { flex: 1; }
    .order-item-name { font-weight: 500; margin-bottom: 5px; }
    .order-item-price { color: var(--primary-color); font-weight: bold; }
    .order-item-quantity { color: var(--gray-color); }
    .order-item-subtotal { font-weight: bold; min-width: 80px; text-align: right; }
    .order-summary { background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .summary-title { font-size: 20px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; color: var(--gray-color); }
    .summary-row.total { font-size: 20px; font-weight: bold; color: var(--primary-color); padding-top: 15px; border-top: 1px solid var(--border-color); margin-top: 15px; }
    .order-actions { display: flex; gap: 15px; margin-top: 30px; justify-content: center; }
</style>
';
require_once 'header.php';
?>

        <div class="order-detail-container">
            <!-- 订单头部信息 -->
            <div class="order-header">
                <div class="order-title">
                    <h1 class="order-number">订单号：<?php echo $order['order_number']; ?></h1>
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
                
                <div class="order-info-grid">
                    <div class="info-item">
                        <div class="info-label">下单时间</div>
                        <div class="info-value"><?php echo date('Y-m-d H:i:s', strtotime($order['created_at'])); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">支付方式</div>
                        <div class="info-value"><?php echo $order['payment_method'] ?? '余额支付'; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">支付状态</div>
                        <div class="info-value"><?php echo $order['payment_status'] == 'paid' ? '已支付' : '未支付'; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">订单金额</div>
                        <div class="info-value" style="color: var(--primary-color); font-weight: bold;">
                            ¥<?php echo number_format($order['final_amount'], 2); ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($order['shipping_address'])): ?>
                <div class="info-item" style="margin-top: 20px;">
                    <div class="info-label">收货地址</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['shipping_address']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 商品列表 -->
            <div class="order-items">
                <h2 class="summary-title">商品清单</h2>
                
                <?php foreach ($order['items'] as $item): ?>
                <div class="order-item">
                    <div class="order-item-image">
                        <?php
                        $image_path = '';
                        if (!empty($item['main_image'])) {
                            $image_path = 'uploads/products/' . $item['main_image'];
                            if (!file_exists($image_path)) {
                                $image_path = '';
                            }
                        }
                        
                        if ($image_path && file_exists($image_path)) {
                            echo '<img src="' . htmlspecialchars($image_path) . '" alt="商品图片">';
                        } else {
                            $initial = mb_substr($item['product_name'], 0, 1, 'UTF-8');
                            echo '<div style="width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#666;font-size:20px;font-weight:bold;">' . htmlspecialchars($initial) . '</div>';
                        }
                        ?>
                    </div>
                    <div class="order-item-details">
                        <div class="order-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                        <div class="order-item-price">¥<?php echo number_format($item['product_price'], 2); ?></div>
                        <div class="order-item-quantity">× <?php echo $item['quantity']; ?></div>
                    </div>
                    <div class="order-item-subtotal">
                        ¥<?php echo number_format($item['subtotal'], 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- 订单金额汇总 -->
            <div class="order-summary">
                <h2 class="summary-title">费用明细</h2>
                
                <div class="summary-row">
                    <span>商品总价</span>
                    <span>¥<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                
                <div class="summary-row">
                    <span>运费</span>
                    <span>¥0.00</span>
                </div>
                
                <div class="summary-row total">
                    <span>实付金额</span>
                    <span>¥<?php echo number_format($order['final_amount'], 2); ?></span>
                </div>
                
                <?php if ($user): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <div class="summary-row" style="margin-bottom: 0;">
                        <span>当前账户余额</span>
                        <span>¥<?php echo number_format($user['balance'] / 100, 2); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="order-actions">
                    <a href="products.php" class="btn btn-outline">
                        <i class="fas fa-store"></i> 继续购物
                    </a>
                    <a href="welcome.php" class="btn btn-primary">
                        <i class="fas fa-list"></i> 查看所有订单
                    </a>
                </div>
            </div>
        </div>

    </div><!-- close .container -->
</main>
</body>
</html>

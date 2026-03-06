<?php
// 设置页面标题
$pageTitle = '个人中心';

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

// 获取购物车数量
$cartCount = getCartCount($pdo, $user['id']);

// 获取用户订单
$ordersResult = getUserOrders($pdo, $user['id'], 5, 1);
$orders = $ordersResult['orders'];

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证 CSRF 令牌
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '请求无效，请刷新页面后重试';
        $message_type = 'error';
    } else {
    // 更新个人信息
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = $_POST['gender'] ?? 'other';
        $birthdate = $_POST['birthdate'] ?? '';
        
        try {
            $sql = "UPDATE users SET 
                    full_name = ?, 
                    email = ?, 
                    phone = ?, 
                    gender = ?, 
                    birthdate = ?,
                    updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$full_name, $email, $phone, $gender, $birthdate, $user['id']]);
            
            $message = '个人信息更新成功！';
            $message_type = 'success';
            
            // 刷新用户数据
            $user = getCurrentUser($pdo);
            
        } catch(PDOException $e) {
            error_log("更新个人信息失败: " . $e->getMessage());
            $message = '更新失败，请稍后再试';
            $message_type = 'error';
        }
    }
    
    // 更新收货地址
    if (isset($_POST['update_address'])) {
        $province = trim($_POST['province'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $detailed_address = trim($_POST['detailed_address'] ?? '');
        $zipcode = trim($_POST['zipcode'] ?? '');
        
        try {
            $sql = "UPDATE users SET 
                    province = ?, 
                    city = ?, 
                    district = ?, 
                    detailed_address = ?,
                    zipcode = ?,
                    updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$province, $city, $district, $detailed_address, $zipcode, $user['id']]);
            
            $message = '收货地址更新成功！';
            $message_type = 'success';
            
            // 刷新用户数据
            $user = getCurrentUser($pdo);
            
        } catch (PDOException $e) {
            error_log("更新收货地址失败: " . $e->getMessage());
            $message = '更新失败，请稍后再试';
            $message_type = 'error';
        }
    }
    
    // 处理头像上传
    if (isset($_POST['update_avatar']) && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $avatar = $_FILES['avatar'];
        
        // 文件大小限制：2MB
        $max_size = 2 * 1024 * 1024;
        if ($avatar['size'] > $max_size) {
            $message = '图片文件大小不能超过 2MB';
            $message_type = 'error';
        } elseif (!function_exists('finfo_open')) {
            $message = '服务器不支持文件类型检测，请联系管理员';
            $message_type = 'error';
        } else {
            // 使用 finfo 检测实际 MIME 类型，而非客户端提供的类型
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $actualType = $finfo->file($avatar['tmp_name']);
            if ($actualType === false) {
                $message = '无法检测文件类型，请重试';
                $message_type = 'error';
                $actualType = '';
            }
            
            $type_to_ext = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp'
            ];
            
            if (array_key_exists($actualType, $type_to_ext)) {
                // 根据实际检测的类型决定扩展名，不信任客户端文件名
                $extension = $type_to_ext[$actualType];
                $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
                $upload_path = 'uploads/avatars/' . $filename;
                
                // 确保上传目录存在
                if (!is_dir('uploads/avatars')) {
                    mkdir('uploads/avatars', 0755, true);
                }
                
                // 移动上传的文件
                if (move_uploaded_file($avatar['tmp_name'], $upload_path)) {
                    // 更新数据库
                    $sql = "UPDATE users SET avatar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$filename, $user['id']]);
                    
                    $message = '头像更新成功！';
                    $message_type = 'success';
                    
                    // 刷新用户数据
                    $user = getCurrentUser($pdo);
                    
                } else {
                    $message = '文件上传失败';
                    $message_type = 'error';
                }
            } else {
                $message = '只允许上传 JPG, PNG, GIF 或 WebP 格式的图片';
                $message_type = 'error';
            }
        }
    } // end update_avatar if
    } // end CSRF valid else block
}
?>

$pageStyles = '
<style>
    .cart-count-badge { position: relative; }
    .cart-count { position: absolute; top: -8px; right: -8px; background: var(--primary-color); color: white; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; }
    .dashboard { padding: 40px 0; }
    .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .dashboard-title { font-size: 28px; color: var(--dark-color); }
    .btn { display: inline-block; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: all 0.3s; text-decoration: none; text-align: center; }
    .btn-primary { background: var(--primary-color); color: white; }
    .btn-primary:hover { background: #ff5252; }
    .btn-outline { background: white; color: var(--dark-color); border: 1px solid var(--border-color); }
    .btn-outline:hover { background: #f5f5f5; }
    .user-card { display: grid; grid-template-columns: 200px 1fr; gap: 30px; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
    @media (max-width: 768px) { .user-card { grid-template-columns: 1fr; } }
    .user-avatar-section { text-align: center; }
    .user-avatar { width: 150px; height: 150px; border-radius: 50%; overflow: hidden; margin: 0 auto 20px; position: relative; }
    .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .change-avatar-btn { position: absolute; bottom: 0; right: 0; background: var(--primary-color); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 3px solid white; }
    .user-name { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
    .user-email { color: var(--gray-color); margin-bottom: 10px; }
    .form-section { background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
    .section-title { font-size: 20px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark-color); }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 16px; transition: border-color 0.3s; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--primary-color); outline: none; }
    .form-group.full-width { grid-column: 1 / -1; }
    .orders-section { background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .order-item { display: grid; grid-template-columns: 100px 2fr 1fr auto; gap: 20px; padding: 20px; border-bottom: 1px solid var(--border-color); align-items: center; }
    .order-item:last-child { border-bottom: none; }
    .order-image { width: 100px; height: 100px; overflow: hidden; border-radius: 4px; }
    .order-image img { width: 100%; height: 100%; object-fit: cover; }
    .order-info h4 { font-size: 18px; margin-bottom: 10px; }
    .order-info p { color: var(--gray-color); margin-bottom: 5px; }
    .order-amount { font-weight: bold; color: var(--primary-color); }
    .order-status { padding: 5px 15px; border-radius: 20px; font-size: 14px; font-weight: 600; }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-processing { background: #cce5ff; color: #004085; }
    .status-completed { background: #d4edda; color: #155724; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-state i { font-size: 60px; color: #ddd; margin-bottom: 20px; }
    .empty-state h3 { font-size: 20px; color: var(--gray-color); margin-bottom: 10px; }
</style>
';
?>
<?php $pageScripts = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const messages = document.querySelectorAll(".message");
        messages.forEach(message => {
            setTimeout(() => {
                message.style.opacity = "0";
                setTimeout(() => { message.remove(); }, 300);
            }, 5000);
        });
        
        const forms = document.querySelectorAll("form");
        forms.forEach(form => {
            form.addEventListener("submit", function(e) {
                const requiredFields = form.querySelectorAll("[required]");
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = "red";
                    } else {
                        field.style.borderColor = "";
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert("请填写所有必填字段");
                }
            });
        });
    });
</script>
';
require_once 'header.php'; ?>

        <div class="dashboard">
            <div class="dashboard-header">
                <h1 class="dashboard-title">
                    <i class="fas fa-user-circle"></i> 个人中心
                </h1>
                <div>
                    <a href="cart.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i> 查看购物车
                        <?php if ($cartCount > 0): ?>
                            <span>(<?php echo $cartCount; ?>件商品)</span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- 用户信息卡片 -->
            <div class="user-card">
                <div class="user-avatar-section">
                    <div class="user-avatar">
                        <?php
                        // Resolve avatar path: prefer user's uploaded avatar, fall back to
                        // default_avatar.png, and use empty string (initial placeholder) if neither exists.
                        $avatar_path = '';
                        
                        if (!empty($user['avatar'])) {
                            $avatar_path = 'uploads/avatars/' . $user['avatar'];
                            if (!file_exists($avatar_path)) {
                                $avatar_path = 'uploads/avatars/default_avatar.png';
                                if (!file_exists($avatar_path)) {
                                    $avatar_path = '';
                                }
                            }
                        } else {
                            $avatar_path = 'uploads/avatars/default_avatar.png';
                            if (!file_exists($avatar_path)) {
                                $avatar_path = '';
                            }
                        }
                        
                        if ($avatar_path && file_exists($avatar_path)): ?>
                            <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="用户头像">
                        <?php else: 
                            $initial = mb_substr($user['username'], 0, 1, 'UTF-8');
                            echo '<div style="width:100%;height:100%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:white;font-size:48px;font-weight:bold;">' . htmlspecialchars($initial) . '</div>';
                        endif; ?>
                        
                        <div class="change-avatar-btn" onclick="document.getElementById('avatarInput').click()">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" style="display: none;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                        <input type="file" id="avatarInput" name="avatar" accept="image/*" onchange="this.form.submit()">
                        <input type="hidden" name="update_avatar" value="1">
                    </form>
                    
                    <h3 class="user-name"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h3>
                    <p class="user-email"><?php echo htmlspecialchars($user['email'] ?: '未设置邮箱'); ?></p>
                    <div style="background: #f0f0f0; padding: 10px; border-radius: 4px; margin-top: 10px;">
                        <small>账户余额: <strong>¥<?php echo number_format($user['balance'] / 100, 2); ?></strong></small>
                    </div>
                </div>
                
                <div class="user-info-summary">
                    <h3 style="margin-bottom: 20px;">账户信息摘要</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <small style="color: var(--gray-color); display: block;">会员等级</small>
                            <strong>普通会员</strong>
                        </div>
                        <div>
                            <small style="color: var(--gray-color); display: block;">订单总数</small>
                            <strong><?php echo $ordersResult['total']; ?> 笔</strong>
                        </div>
                        <div>
                            <small style="color: var(--gray-color); display: block;">注册时间</small>
                            <strong><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></strong>
                        </div>
                        <div>
                            <small style="color: var(--gray-color); display: block;">最后登录</small>
                            <strong><?php echo date('Y-m-d H:i', strtotime($user['updated_at'])); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 个人信息表单 -->
            <div class="form-section">
                <h2 class="section-title">个人信息</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name">姓名</label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="username">用户名</label>
                            <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small style="color: var(--gray-color);">用户名不可修改</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">邮箱地址</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">手机号码</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">性别</label>
                            <select id="gender" name="gender">
                                <option value="male" <?php echo ($user['gender'] ?? 'other') == 'male' ? 'selected' : ''; ?>>男</option>
                                <option value="female" <?php echo ($user['gender'] ?? 'other') == 'female' ? 'selected' : ''; ?>>女</option>
                                <option value="other" <?php echo ($user['gender'] ?? 'other') == 'other' ? 'selected' : ''; ?>>其他</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="birthdate">出生日期</label>
                            <input type="date" id="birthdate" name="birthdate" 
                                   value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> 保存个人信息
                        </button>
                    </div>
                </form>
            </div>

            <!-- 收货地址表单 -->
            <div class="form-section">
                <h2 class="section-title">收货地址</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="province">省份</label>
                            <input type="text" id="province" name="province" 
                                   value="<?php echo htmlspecialchars($user['province'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="city">城市</label>
                            <input type="text" id="city" name="city" 
                                   value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="district">区县</label>
                            <input type="text" id="district" name="district" 
                                   value="<?php echo htmlspecialchars($user['district'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="zipcode">邮政编码</label>
                            <input type="text" id="zipcode" name="zipcode" 
                                   value="<?php echo htmlspecialchars($user['zipcode'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="detailed_address">详细地址</label>
                            <textarea id="detailed_address" name="detailed_address" rows="3"><?php echo htmlspecialchars($user['detailed_address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="submit" name="update_address" class="btn btn-primary">
                            <i class="fas fa-map-marker-alt"></i> 保存收货地址
                        </button>
                    </div>
                </form>
            </div>

            <!-- 订单列表 -->
            <div class="orders-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <h2 class="section-title" style="margin: 0;">最近订单</h2>
                    <a href="orders.php" class="btn btn-outline">查看全部订单</a>
                </div>
                
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                    <div class="order-item">
                        <div class="order-image">
                            <?php if (!empty($order['items'][0]['main_image'])): ?>
                                <img src="uploads/products/<?php echo htmlspecialchars($order['items'][0]['main_image']); ?>" alt="商品图片">
                            <?php else: ?>
                                <div style="width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#666;">
                                    <i class="fas fa-box"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-info">
                            <h4>订单号: <?php echo $order['order_number']; ?></h4>
                            <p>下单时间: <?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></p>
                            <p>商品数量: <?php echo $order['item_count']; ?> 件</p>
                        </div>
                        
                        <div class="order-amount">
                            ¥<?php echo number_format($order['final_amount'], 2); ?>
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
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>暂无订单</h3>
                        <p>您还没有购买过任何商品</p>
                        <a href="products.php" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-store"></i> 去商城逛逛
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- close .container -->
</main>
</body>
</html>

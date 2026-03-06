<?php
require_once 'db_connect.php';
require_once 'product_functions.php';

// 检查商品ID
$product_id = $_GET['id'] ?? 0;
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

// 获取购物车数量
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $cartCount = getCartCount($pdo, $_SESSION['user_id']);
}

// 处理添加到购物车
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: login.php");
        exit();
    }

    // 验证 CSRF 令牌
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        error_log("CSRF validation failed for add_to_cart: user_id=" . ($_SESSION['user_id'] ?? 'n/a') . " ip=" . ($_SERVER['REMOTE_ADDR'] ?? 'n/a'));
        $errorMessage = '请求无效，请刷新页面后重试';
    } else {
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        $result = addToCart($pdo, $_SESSION['user_id'], $product_id, $quantity);

        if ($result) {
            $cartCount = getCartCount($pdo, $_SESSION['user_id']);
            $successMessage = '商品已成功添加到购物车！';
        } else {
            $errorMessage = '添加失败，请稍后重试';
        }
    }
}

// 处理评价提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    // 验证 CSRF 令牌
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        error_log("CSRF validation failed for submit_review: user_id=" . ($_SESSION['user_id'] ?? 'n/a') . " ip=" . ($_SERVER['REMOTE_ADDR'] ?? 'n/a'));
        $reviewError = '请求无效，请刷新页面后重试';
    } else {
        $rating = intval($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
            try {
                $sql = "INSERT INTO product_reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$product_id, $_SESSION['user_id'], $rating, $comment]);
                $reviewSuccess = '评价提交成功！';

                // 刷新页面获取最新评价
                header("Location: product_detail.php?id=" . $product_id);
                exit();
            } catch (PDOException $e) {
                error_log("提交评价失败: " . $e->getMessage());
                $reviewError = '提交失败，请稍后重试';
            }
        } else {
            $reviewError = '请填写完整的评价信息';
        }
    }
}

// 页面标题
$pageTitle = $product['name'] . ' - 商品详情';
?>
<?php
$pageStyles = '
<style>
    .breadcrumb { background: white; padding: 15px 0; margin-bottom: 20px; }
    .breadcrumb-links { display: flex; align-items: center; gap: 10px; color: var(--gray-color); }
    .breadcrumb-links a { color: var(--gray-color); text-decoration: none; }
    .breadcrumb-links a:hover { color: var(--primary-color); }
    .product-detail { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; background: white; padding: 30px; border-radius: 8px; margin-bottom: 30px; }
    @media (max-width: 768px) { .product-detail { grid-template-columns: 1fr; } }
    .product-images { position: sticky; top: 100px; }
    .main-image { width: 100%; height: 400px; overflow: hidden; border-radius: 8px; margin-bottom: 15px; }
    .main-image img { width: 100%; height: 100%; object-fit: contain; }
    .thumbnail-images { display: flex; gap: 10px; overflow-x: auto; padding: 10px 0; }
    .thumbnail { width: 80px; height: 80px; border: 2px solid var(--border-color); border-radius: 4px; overflow: hidden; cursor: pointer; flex-shrink: 0; }
    .thumbnail.active { border-color: var(--primary-color); }
    .thumbnail img { width: 100%; height: 100%; object-fit: cover; }
    .product-info h1 { font-size: 28px; margin-bottom: 15px; }
    .product-meta { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; color: var(--gray-color); font-size: 14px; }
    .product-rating { display: flex; align-items: center; gap: 5px; color: #ffc107; }
    .product-sku { background: #f0f0f0; padding: 3px 10px; border-radius: 12px; }
    .product-price-section { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
    .current-price { font-size: 32px; font-weight: bold; color: var(--primary-color); margin-bottom: 5px; }
    .original-price { font-size: 18px; color: var(--gray-color); text-decoration: line-through; margin-right: 10px; }
    .discount { background: var(--primary-color); color: white; padding: 3px 8px; border-radius: 4px; font-size: 14px; }
    .stock-status { display: inline-block; padding: 5px 15px; background: var(--secondary-color); color: white; border-radius: 20px; font-size: 14px; margin-top: 10px; }
    .stock-status.out-of-stock { background: var(--primary-color); }
    .buy-options { margin: 30px 0; }
    .quantity-selector { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
    .quantity-control { display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 4px; overflow: hidden; }
    .quantity-btn { width: 40px; height: 40px; background: #f5f5f5; border: none; cursor: pointer; font-size: 18px; }
    .quantity-input { width: 60px; height: 40px; border: none; text-align: center; font-size: 16px; }
    .action-buttons { display: flex; gap: 15px; margin-bottom: 20px; }
    .btn-buy-now { flex: 1; background: var(--primary-color); color: white; border: none; padding: 15px; border-radius: 4px; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .btn-add-cart-large { flex: 1; background: white; color: var(--primary-color); border: 2px solid var(--primary-color); padding: 15px; border-radius: 4px; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .btn-buy-now:hover { background: #ff5252; }
    .btn-add-cart-large:hover { background: var(--primary-color); color: white; }
    .product-tabs { background: white; border-radius: 8px; overflow: hidden; margin-bottom: 30px; }
    .tab-headers { display: flex; border-bottom: 1px solid var(--border-color); }
    .tab-header { padding: 15px 30px; cursor: pointer; border-bottom: 3px solid transparent; }
    .tab-header.active { border-bottom-color: var(--primary-color); color: var(--primary-color); }
    .tab-content { padding: 30px; display: none; }
    .tab-content.active { display: block; }
    .spec-table { width: 100%; border-collapse: collapse; }
    .spec-table tr { border-bottom: 1px solid var(--border-color); }
    .spec-table td { padding: 12px; }
    .spec-table td:first-child { width: 150px; font-weight: bold; background: #f9f9f9; }
    .review-summary { display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px; }
    .average-rating { text-align: center; }
    .rating-score { font-size: 36px; font-weight: bold; color: var(--primary-color); }
    .rating-stars { color: #ffc107; margin: 5px 0; }
    .review-list { margin-top: 20px; }
    .review-item { padding: 20px; border-bottom: 1px solid var(--border-color); }
    .review-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
    .review-user { display: flex; align-items: center; gap: 10px; }
    .user-avatar { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; }
    .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .related-products { margin-top: 50px; }
    .section-title { font-size: 24px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--primary-color); }
    .related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
    .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
    .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
</style>
';
$pageScripts = '
<script>
    function changeMainImage(src) {
        document.getElementById("mainImage").src = src;
        document.querySelectorAll(".thumbnail").forEach(thumb => {
            thumb.classList.remove("active");
        });
        event.target.closest(".thumbnail").classList.add("active");
    }
    
    function switchTab(tabName) {
        document.querySelectorAll(".tab-header").forEach(header => {
            header.classList.remove("active");
        });
        event.target.classList.add("active");
        
        document.querySelectorAll(".tab-content").forEach(content => {
            content.classList.remove("active");
        });
        document.getElementById(tabName + "-tab").classList.add("active");
    }
    
    function changeQuantity(delta) {
        const input = document.querySelector(".quantity-input");
        let quantity = parseInt(input.value) || 1;
        quantity += delta;
        
        const maxQuantity = ' . (int)$product['stock_quantity'] . ';
        if (quantity < 1) quantity = 1;
        if (quantity > maxQuantity) quantity = maxQuantity;
        
        input.value = quantity;
    }
    
    function buyNow() {
        const quantity = document.querySelector(".quantity-input").value;
        const productId = ' . (int)$product['id'] . ';
        window.location.href = "buy_now.php?product_id=" + productId + "&quantity=" + quantity;
    }
    
    document.addEventListener("DOMContentLoaded", function() {
        const ratingInputs = document.querySelectorAll(".rating-input input");
        ratingInputs.forEach(input => {
            input.addEventListener("change", function() {
                const stars = this.closest(".rating-input").querySelectorAll(".fa-star");
                const rating = parseInt(this.value);
                
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.classList.remove("far");
                        star.classList.add("fas");
                    } else {
                        star.classList.remove("fas");
                        star.classList.add("far");
                    }
                });
            });
        });
    });
</script>
';
require_once 'header.php';
?>
        <!-- 消息提示 -->
        <?php if (isset($successMessage)): ?>
            <div class="message success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="message error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <!-- 商品详情主体 -->
        <div class="product-detail">
            <!-- 商品图片 -->
            <div class="product-images">
                <div class="main-image">
                    <img id="mainImage" src="<?php echo $product['main_image'] ? 'uploads/products/' . $product['main_image'] : 'https://via.placeholder.com/500x500?text=No+Image'; ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                </div>
                
                <?php if (!empty($product['images'])): ?>
                <div class="thumbnail-images">
                    <div class="thumbnail active" onclick="changeMainImage('<?php echo 'uploads/products/' . $product['main_image']; ?>')">
                        <img src="<?php echo 'uploads/products/' . $product['main_image']; ?>" alt="主图">
                    </div>
                    <?php foreach ($product['images'] as $image): ?>
                    <div class="thumbnail" onclick="changeMainImage('<?php echo 'uploads/products/' . $image; ?>')">
                        <img src="<?php echo 'uploads/products/' . $image; ?>" alt="商品图片">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- 商品信息 -->
            <div class="product-info">
                <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="product-meta">
                    <div class="product-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= floor($product['avg_rating'])): ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i <= $product['avg_rating']): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <span>(<?php echo $product['review_count']; ?>条评价)</span>
                    </div>
                    <div class="product-sku">货号: <?php echo $product['sku'] ?? '暂无'; ?></div>
                    <div>销量: <?php echo $product['sold_count']; ?></div>
                    <div>浏览: <?php echo $product['view_count']; ?></div>
                </div>
                
                <div class="product-price-section">
                    <div class="current-price">¥<?php echo number_format($product['price'], 2); ?></div>
                    
                    <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                        <?php 
                        $discount = round((($product['original_price'] - $product['price']) / $product['original_price']) * 100);
                        ?>
                        <div>
                            <span class="original-price">¥<?php echo number_format($product['original_price'], 2); ?></span>
                            <span class="discount">立省<?php echo $discount; ?>%</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="stock-status <?php echo $product['stock_quantity'] > 0 ? '' : 'out-of-stock'; ?>">
                        <?php if ($product['stock_quantity'] > 0): ?>
                            库存 <?php echo $product['stock_quantity']; ?> 件
                        <?php else: ?>
                            已售罄
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="product-description">
                    <h3>商品描述</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
                
                <!-- 购买选项 -->
                <div class="buy-options">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                        <div class="quantity-selector">
                            <label>数量:</label>
                            <div class="quantity-control">
                                <button type="button" class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                                <input type="number" name="quantity" class="quantity-input" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                                <button type="button" class="quantity-btn" onclick="changeQuantity(1)">+</button>
                            </div>
                            <span>库存 <?php echo $product['stock_quantity']; ?> 件</span>
                        </div>
                        
                        <div class="action-buttons">
                            <?php if ($product['stock_quantity'] > 0): ?>
                            <button type="submit" name="add_to_cart" class="btn-add-cart-large">
                                <i class="fas fa-cart-plus"></i> 加入购物车
                            </button>
                            <button type="button" class="btn-buy-now" onclick="buyNow()">
                                <i class="fas fa-bolt"></i> 立即购买
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn-add-cart-large" disabled>
                                <i class="fas fa-ban"></i> 已售罄
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 商品详情选项卡 -->
        <div class="product-tabs">
            <div class="tab-headers">
                <div class="tab-header active" onclick="switchTab('description')">商品详情</div>
                <div class="tab-header" onclick="switchTab('specifications')">规格参数</div>
                <div class="tab-header" onclick="switchTab('reviews')">商品评价</div>
                <div class="tab-header" onclick="switchTab('shipping')">配送说明</div>
            </div>
            
            <div class="tab-content active" id="description-tab">
                <h3>商品详情</h3>
                <div class="description-content">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>
            </div>
            
            <div class="tab-content" id="specifications-tab">
                <h3>规格参数</h3>
                <?php if (!empty($product['specifications'])): ?>
                    <table class="spec-table">
                        <?php foreach ($product['specifications'] as $key => $value): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($key); ?></td>
                            <td><?php echo htmlspecialchars($value); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>暂无规格参数信息</p>
                <?php endif; ?>
            </div>
            
            <div class="tab-content" id="reviews-tab">
                <div class="review-summary">
                    <div class="average-rating">
                        <div class="rating-score"><?php echo number_format($product['avg_rating'], 1); ?></div>
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= floor($product['avg_rating'])): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($i <= $product['avg_rating']): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <div><?php echo $product['review_count']; ?>条评价</div>
                    </div>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="review-form">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                            <h4>我要评价</h4>
                            <div class="rating-input">
                                <span>评分:</span>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label>
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" <?php echo $i == 5 ? 'checked' : ''; ?>>
                                        <i class="far fa-star"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <textarea name="comment" placeholder="写下您的评价..." rows="3" required></textarea>
                            <button type="submit" name="submit_review" class="btn-buy-now" style="margin-top: 10px;">提交评价</button>
                            
                            <?php if (isset($reviewSuccess)): ?>
                                <div class="message success"><?php echo htmlspecialchars($reviewSuccess); ?></div>
                            <?php elseif (isset($reviewError)): ?>
                                <div class="message error"><?php echo htmlspecialchars($reviewError); ?></div>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="review-list">
                    <?php if (!empty($product['reviews'])): ?>
                        <?php foreach ($product['reviews'] as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="review-user">
                                    <div class="user-avatar">
                                        <img src="<?php echo $review['avatar'] ? 'uploads/avatars/' . $review['avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($review['username']); ?>" 
                                             alt="<?php echo htmlspecialchars($review['username']); ?>">
                                    </div>
                                    <div>
                                        <div class="user-name"><?php echo htmlspecialchars($review['username']); ?></div>
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $review['rating']): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="review-date">
                                    <?php echo date('Y-m-d H:i', strtotime($review['created_at'])); ?>
                                </div>
                            </div>
                            <div class="review-comment">
                                <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; padding: 30px; color: var(--gray-color);">暂无评价</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="tab-content" id="shipping-tab">
                <h3>配送说明</h3>
                <div class="shipping-info">
                    <h4>配送范围</h4>
                    <p>全国大部分地区（港澳台及南山少林寺除外）</p>
                    
                    <h4>配送时间</h4>
                    <p>下单后1-3个工作日内发货，具体到货时间以物流为准</p>
                    
                    <h4>配送费用</h4>
                    <p>订单满114514元包邮，不满114514元收取1919810元运费</p>
                    
                    <h4>退换货政策</h4>
                    <p>收到商品7天内，如遇质量问题可申请退换货</p>
                </div>
            </div>
        </div>

        <!-- 相关商品 -->
        <?php if (!empty($product['related_products'])): ?>
        <div class="related-products">
            <h2 class="section-title">相关推荐</h2>
            <div class="related-grid">
                <?php foreach ($product['related_products'] as $related): ?>
                <div class="product-card" style="margin: 0;">
                    <div class="product-image">
                        <img src="<?php echo $related['main_image'] ? 'uploads/products/' . $related['main_image'] : 'https://via.placeholder.com/300x200?text=No+Image'; ?>" 
                             alt="<?php echo htmlspecialchars($related['name']); ?>">
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">
                            <a href="product_detail.php?id=<?php echo $related['id']; ?>">
                                <?php echo htmlspecialchars($related['name']); ?>
                            </a>
                        </h3>
                        <div class="product-price">
                            <span class="current-price">¥<?php echo number_format($related['price'], 2); ?></span>
                        </div>
                        <div class="product-actions">
                            <a href="product_detail.php?id=<?php echo $related['id']; ?>" class="btn-add-cart" style="width: 100%; text-align: center;">
                                查看详情
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div><!-- close .container -->
</main>
</body>
</html>

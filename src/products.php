<?php
// 设置页面标题
$pageTitle = '商城首页';

// 引入必要的文件
require_once 'db_connect.php';
require_once 'product_functions.php';

// 获取查询参数
$category_id = $_GET['category'] ?? 0;
$keyword = $_GET['q'] ?? '';
$min_price = $_GET['min_price'] ?? null;
$max_price = $_GET['max_price'] ?? null;
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;

// 记录搜索历史
if ($keyword && isset($_SESSION['user_id'])) {
    // 先搜索获取结果数量
    $tempResult = getProducts($pdo, [
        'keyword' => $keyword,
        'category_id' => $category_id,
        'min_price' => $min_price,
        'max_price' => $max_price,
        'limit' => 1
    ]);
    recordSearch($pdo, $keyword, $tempResult['total'], $_SESSION['user_id']);
}

// 获取商品数据
$params = [
    'category_id' => $category_id ?: null,
    'keyword' => $keyword,
    'min_price' => $min_price ? floatval($min_price) : null,
    'max_price' => $max_price ? floatval($max_price) : null,
    'sort' => $sort,
    'limit' => $limit,
    'page' => $page
];

$result = getProducts($pdo, $params);
$products = $result['products'];
$total = $result['total'];
$totalPages = $result['pages'];

// 获取分类信息
$currentCategory = null;
if ($category_id) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND is_active = 1");
    $stmt->execute([$category_id]);
    $currentCategory = $stmt->fetch();
}

// 获取热门搜索词
$hotKeywords = [];
try {
    $hotKeywords = getHotKeywords($pdo, 5);
} catch (Exception $e) {
    // 静默失败
}

// 页面特定的CSS
$pageStyles = '
<style>
    /* 商品网格样式 */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 25px;
        margin-top: 30px;
    }
    
    .product-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        border: 1px solid #f0f0f0;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        border-color: var(--primary-color);
    }
    
    .product-image {
        position: relative;
        height: 220px;
        overflow: hidden;
    }
    
    .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    
    .product-card:hover .product-image img {
        transform: scale(1.05);
    }
    
    .product-badges {
        position: absolute;
        top: 12px;
        left: 12px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .badge {
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        color: white;
    }
    
    .badge-new {
        background: var(--secondary-color);
    }
    
    .badge-hot {
        background: var(--primary-color);
    }
    
    .badge-sale {
        background: #ff9f43;
    }
    
    .product-info {
        padding: 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .product-category {
        font-size: 13px;
        color: var(--gray-color);
        margin-bottom: 8px;
    }
    
    .product-name {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 12px;
        line-height: 1.4;
        color: var(--dark-color);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 44px;
    }
    
    .product-name a {
        color: inherit;
        text-decoration: none;
    }
    
    .product-name a:hover {
        color: var(--primary-color);
    }
    
    .product-price {
        margin-top: auto;
        margin-bottom: 15px;
    }
    
    .current-price {
        font-size: 20px;
        font-weight: bold;
        color: var(--primary-color);
    }
    
    .original-price {
        font-size: 14px;
        color: var(--gray-color);
        text-decoration: line-through;
        margin-left: 8px;
    }
    
    .product-stock {
        font-size: 13px;
        margin-bottom: 15px;
    }
    
    .product-stock.in-stock {
        color: var(--secondary-color);
    }
    
    .product-stock.out-of-stock {
        color: var(--danger-color);
    }
    
    .product-actions {
        display: flex;
        gap: 10px;
    }
    
    .product-actions .btn {
        flex: 1;
        padding: 10px;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }
    
    /* 页面标题和筛选 */
    .page-header {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    }
    
    .page-title {
        font-size: 28px;
        margin-bottom: 10px;
        color: var(--dark-color);
    }
    
    .page-subtitle {
        color: var(--gray-color);
        font-size: 16px;
        margin-bottom: 20px;
    }
    
    .filter-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        padding-top: 20px;
        border-top: 1px solid #f0f0f0;
    }
    
    .filter-controls {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .filter-controls select {
        padding: 8px 15px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background: white;
        font-size: 14px;
    }
    
    /* 分页 */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 50px;
    }
    
    .pagination .btn {
        padding: 8px 16px;
        min-width: 40px;
    }
    
    .pagination .btn.active {
        background: var(--primary-color);
        color: white;
    }
    
    /* 空状态 */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 10px;
        margin: 40px 0;
    }
    
    .empty-state-icon {
        font-size: 60px;
        color: #ddd;
        margin-bottom: 20px;
    }
    
    .empty-state-title {
        font-size: 24px;
        color: var(--gray-color);
        margin-bottom: 15px;
    }
    
    .empty-state-text {
        color: #999;
        margin-bottom: 25px;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }
    
    /* 侧边栏筛选 */
    .sidebar-filter {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        margin-bottom: 30px;
    }
    
    .filter-section {
        margin-bottom: 30px;
    }
    
    .filter-section:last-child {
        margin-bottom: 0;
    }
    
    .filter-title {
        font-size: 18px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #f0f0f0;
        color: var(--dark-color);
    }
    
    .category-list-sidebar {
        list-style: none;
    }
    
    .category-list-sidebar li {
        margin-bottom: 8px;
    }
    
    .category-list-sidebar a {
        color: var(--dark-color);
        text-decoration: none;
        padding: 8px 12px;
        display: block;
        border-radius: 4px;
        transition: all 0.3s;
    }
    
    .category-list-sidebar a:hover {
        background: #f5f5f5;
        color: var(--primary-color);
    }
    
    .category-list-sidebar .active a {
        background: var(--primary-color);
        color: white;
        font-weight: 600;
    }
    
    .price-inputs {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .price-inputs input {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        font-size: 14px;
    }
    
    /* 响应式 */
    @media (max-width: 768px) {
        .products-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .product-image {
            height: 180px;
        }
        
        .filter-row {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-controls {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-controls select {
            width: 100%;
        }
    }
</style>
';

// 页面特定的JS
$pageScripts = '
<script>
    // 数量控制
    function changeQuantity(input, delta) {
        const current = parseInt(input.value) || 1;
        const max = parseInt(input.getAttribute("max")) || 999;
        const min = parseInt(input.getAttribute("min")) || 1;
        
        let newValue = current + delta;
        if (newValue < min) newValue = min;
        if (newValue > max) newValue = max;
        
        input.value = newValue;
        return newValue;
    }
    
    // 添加到购物车
    function addToCart(productId) {
        const quantity = document.getElementById("quantity-" + productId)?.value || 1;
        addToCart(productId, quantity);
    }
    
    // 页面加载后
    document.addEventListener("DOMContentLoaded", function() {
        // 筛选表单提交
        const filterForms = document.querySelectorAll(".filter-form");
        filterForms.forEach(form => {
            form.addEventListener("change", function() {
                this.submit();
            });
        });
        
        // 热门搜索词点击
        const hotKeywords = document.querySelectorAll(".hot-keyword");
        hotKeywords.forEach(keyword => {
            keyword.addEventListener("click", function(e) {
                e.preventDefault();
                const searchInput = document.querySelector(".search-input");
                if (searchInput) {
                    searchInput.value = this.textContent;
                    searchInput.closest("form").submit();
                }
            });
        });
    });
</script>
';

// 引入头部
require_once 'header.php';
?>

<!-- 页面标题区域 -->
<div class="page-header">
    <h1 class="page-title">
        <?php if ($currentCategory): ?>
            <?php echo htmlspecialchars($currentCategory['name']); ?>
        <?php elseif ($keyword): ?>
            搜索：<?php echo htmlspecialchars($keyword); ?>
        <?php else: ?>
            全部商品
        <?php endif; ?>
    </h1>
    
    <p class="page-subtitle">
        <?php if ($keyword): ?>
            共找到 <span style="color: var(--primary-color); font-weight: bold;"><?php echo $total; ?></span> 件相关商品
        <?php else: ?>
            共 <span style="color: var(--primary-color); font-weight: bold;"><?php echo $total; ?></span> 件商品
        <?php endif; ?>
    </p>
    
    <!-- 热门搜索词 -->
    <?php if (!$keyword && !empty($hotKeywords)): ?>
    <div style="margin-bottom: 15px;">
        <span style="color: var(--gray-color);">热门搜索：</span>
        <?php foreach ($hotKeywords as $hot): ?>
            <a href="products.php?q=<?php echo urlencode($hot['keyword']); ?>" 
               class="hot-keyword" 
               style="color: var(--primary-color); text-decoration: none; margin: 0 8px;">
                <?php echo htmlspecialchars($hot['keyword']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <div class="filter-row">
        <div class="filter-controls">
            <!-- 排序 -->
            <form method="GET" class="filter-form">
                <?php if ($category_id): ?>
                    <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                <?php endif; ?>
                <?php if ($keyword): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($keyword); ?>">
                <?php endif; ?>
                <select name="sort" onchange="this.form.submit()">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>新品优先</option>
                    <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>价格从低到高</option>
                    <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>价格从高到低</option>
                    <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>热销优先</option>
                </select>
            </form>
        </div>
        
        <!-- 价格筛选（简单版） -->
        <div class="filter-controls">
            <form method="GET" class="filter-form" style="display: flex; gap: 10px;">
                <?php if ($category_id): ?>
                    <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                <?php endif; ?>
                <?php if ($keyword): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($keyword); ?>">
                <?php endif; ?>
                <input type="number" name="min_price" placeholder="最低价" value="<?php echo $min_price; ?>" style="width: 100px;">
                <span>-</span>
                <input type="number" name="max_price" placeholder="最高价" value="<?php echo $max_price; ?>" style="width: 100px;">
                <button type="submit" class="btn btn-outline" style="padding: 8px 15px;">筛选</button>
                <?php if ($min_price || $max_price): ?>
                    <a href="products.php?<?php echo http_build_query(array_diff_key($_GET, ['min_price' => '', 'max_price' => ''])); ?>" 
                       class="btn" style="padding: 8px 15px; background: #999;">清除</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- 商品列表 -->
<?php if (!empty($products)): ?>
    <div class="products-grid">
        <?php foreach ($products as $product): ?>
        <div class="product-card">
            <div class="product-image">
                <!-- 商品图片 -->
                <?php
                $image_path = '';
                $image_alt = htmlspecialchars($product['name']);
                
                if (!empty($product['main_image'])) {
                    $image_path = 'uploads/products/' . $product['main_image'];
                    // 检查文件是否存在
                    if (!file_exists($image_path)) {
                        $image_path = '';
                    }
                }
                
                if ($image_path && file_exists($image_path)) {
                    echo '<img src="' . htmlspecialchars($image_path) . '" alt="' . $image_alt . '">';
                } else {
                    // 使用SVG占位符
                    $initial = mb_substr($product['name'], 0, 1, 'UTF-8');
                    $color = dechex(rand(0x000000, 0xFFFFFF));
                    echo '<div style="width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#666;">
                            <div style="text-align:center;">
                                <div style="font-size:48px;margin-bottom:10px;">' . htmlspecialchars($initial) . '</div>
                                <div style="font-size:14px;">暂无图片</div>
                            </div>
                          </div>';
                }
                ?>
                
                <!-- 商品标签 -->
                <div class="product-badges">
                    <?php if ($product['is_new']): ?>
                        <span class="badge badge-new">新品</span>
                    <?php endif; ?>
                    <?php if ($product['is_hot']): ?>
                        <span class="badge badge-hot">热卖</span>
                    <?php endif; ?>
                    <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                        <?php 
                        $discount = round((($product['original_price'] - $product['price']) / $product['original_price']) * 100);
                        ?>
                        <span class="badge badge-sale">-<?php echo $discount; ?>%</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="product-info">
                <div class="product-category">
                    <?php echo htmlspecialchars($product['category_name'] ?? '未分类'); ?>
                </div>
                
                <h3 class="product-name">
                    <a href="product_detail.php?id=<?php echo $product['id']; ?>">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </a>
                </h3>
                
                <div class="product-price">
                    <span class="current-price">¥<?php echo number_format($product['price'], 2); ?></span>
                    <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                        <span class="original-price">¥<?php echo number_format($product['original_price'], 2); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="product-stock <?php echo $product['stock_quantity'] > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                    <?php if ($product['stock_quantity'] > 0): ?>
                        库存 <?php echo $product['stock_quantity']; ?> 件
                    <?php else: ?>
                        缺货
                    <?php endif; ?>
                </div>
                
                <div class="product-actions">
                    <?php if ($product['stock_quantity'] > 0): ?>
                    <button class="btn" onclick="addToCart(<?php echo $product['id']; ?>)">
                        <i class="fas fa-cart-plus"></i> 加入购物车
                    </button>
                    <?php else: ?>
                    <button class="btn" disabled style="background: #ccc;">
                        <i class="fas fa-ban"></i> 已售罄
                    </button>
                    <?php endif; ?>
                    <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn btn-outline">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- 分页 -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn">
                <i class="fas fa-chevron-left"></i> 上一页
            </a>
        <?php endif; ?>
        
        <?php 
        // 显示页码
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
               class="btn <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn">
                下一页 <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
<?php else: ?>
    <!-- 空状态 -->
    <div class="empty-state">
        <div class="empty-state-icon">
            <i class="fas fa-search"></i>
        </div>
        <h3 class="empty-state-title">没有找到相关商品</h3>
        <p class="empty-state-text">
            <?php if ($keyword): ?>
                没有找到与 "<?php echo htmlspecialchars($keyword); ?>" 相关的商品，请尝试其他搜索词。
            <?php else: ?>
                暂时没有商品数据，请稍后再来。
            <?php endif; ?>
        </p>
        <div style="display: flex; gap: 15px; justify-content: center;">
            <a href="products.php" class="btn">浏览全部商品</a>
            <?php if ($keyword): ?>
                <a href="products.php" class="btn btn-outline">清除搜索</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
    </div><!-- close .container -->
</main>
</body>
</html>

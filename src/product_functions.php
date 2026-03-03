<?php
// 商品相关函数

// 获取商品列表
// 在函数开头添加调试信息（调试完可以删除）
function getProducts($pdo, $params = []) {
    $defaults = [
        'category_id' => null,
        'keyword' => '',
        'min_price' => null,
        'max_price' => null,
        'sort' => 'newest',
        'limit' => 12,
        'page' => 1,
        'featured' => false,
        'hot' => false,
        'new' => false
    ];
    
    $params = array_merge($defaults, $params);
    $offset = ($params['page'] - 1) * $params['limit'];
    
    // 构建基础查询
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.is_active = 1";
    
    $conditions = [];
    $values = [];
    
    // 分类筛选
    if ($params['category_id']) {
        $conditions[] = "p.category_id = ?";
        $values[] = $params['category_id'];
    }
    
    // 关键词搜索
    if ($params['keyword']) {
        $conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
        $keyword = "%" . $params['keyword'] . "%";
        $values[] = $keyword;
        $values[] = $keyword;
    }
    
    // 价格筛选
    if ($params['min_price'] !== null) {
        $conditions[] = "p.price >= ?";
        $values[] = $params['min_price'];
    }
    
    if ($params['max_price'] !== null) {
        $conditions[] = "p.price <= ?";
        $values[] = $params['max_price'];
    }
    
    // 添加条件
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    // 排序
    switch ($params['sort']) {
        case 'price_asc':
            $sql .= " ORDER BY p.price ASC";
            break;
        case 'price_desc':
            $sql .= " ORDER BY p.price DESC";
            break;
        case 'popular':
            $sql .= " ORDER BY p.sold_count DESC";
            break;
        case 'views':
            $sql .= " ORDER BY p.view_count DESC";
            break;
        case 'newest':
        default:
            $sql .= " ORDER BY p.created_at DESC";
            break;
    }
    
    // 修复：LIMIT 和 OFFSET 使用整数，而不是参数绑定
    $sql .= " LIMIT " . intval($params['limit']) . " OFFSET " . intval($offset);
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        $products = $stmt->fetchAll();
        
        // 获取总数（不需要分页参数）
        $countSql = "SELECT COUNT(*) as total FROM products p WHERE p.is_active = 1";
        if (!empty($conditions)) {
            $countSql .= " AND " . implode(" AND ", $conditions);
        }
        
        // 执行总数查询
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($values);  // 使用相同的参数
        $total = $countStmt->fetch()['total'];
        
        return [
            'products' => $products,
            'total' => $total,
            'pages' => ceil($total / $params['limit'])
        ];
        
    } catch (PDOException $e) {
        error_log("数据库查询错误: " . $e->getMessage() . " SQL: " . $sql);
        return ['products' => [], 'total' => 0, 'pages' => 0];
    }
}

// 获取单个商品详情
function getProductById($pdo, $id) {
    try {
        // 更新浏览数
        $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?")->execute([$id]);
        
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.id = ? AND p.is_active = TRUE";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if ($product) {
            // 解析JSON字段
            if ($product['images']) {
                $product['images'] = json_decode($product['images'], true) ?: [];
            } else {
                $product['images'] = [];
            }
            
            if ($product['specifications']) {
                $product['specifications'] = json_decode($product['specifications'], true) ?: [];
            } else {
                $product['specifications'] = [];
            }
            
            // 获取相关商品
            $relatedSql = "SELECT * FROM products 
                          WHERE category_id = ? AND id != ? AND is_active = TRUE 
                          ORDER BY RAND() LIMIT 4";
            $relatedStmt = $pdo->prepare($relatedSql);
            $relatedStmt->execute([$product['category_id'], $id]);
            $product['related_products'] = $relatedStmt->fetchAll();
            
            // 获取商品评价
            $reviewsSql = "SELECT r.*, u.username, u.avatar 
                          FROM product_reviews r 
                          JOIN users u ON r.user_id = u.id 
                          WHERE r.product_id = ? AND r.is_approved = TRUE 
                          ORDER BY r.created_at DESC LIMIT 10";
            $reviewsStmt = $pdo->prepare($reviewsSql);
            $reviewsStmt->execute([$id]);
            $product['reviews'] = $reviewsStmt->fetchAll();
            
            // 计算平均评分
            $ratingSql = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
                         FROM product_reviews 
                         WHERE product_id = ? AND is_approved = TRUE";
            $ratingStmt = $pdo->prepare($ratingSql);
            $ratingStmt->execute([$id]);
            $ratingInfo = $ratingStmt->fetch();
            $product['avg_rating'] = round($ratingInfo['avg_rating'] ?? 0, 1);
            $product['review_count'] = $ratingInfo['review_count'] ?? 0;
        }
        
        return $product;
        
    } catch (PDOException $e) {
        error_log("获取商品详情失败: " . $e->getMessage());
        return null;
    }
}

// 获取商品分类
function getCategories($pdo, $parent_id = 0) {
    try {
        $sql = "SELECT * FROM categories WHERE parent_id = ? AND is_active = TRUE ORDER BY sort_order, name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$parent_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("获取分类失败: " . $e->getMessage());
        return [];
    }
}

// 获取所有分类（树形结构）
function getAllCategories($pdo) {
    try {
        $sql = "SELECT * FROM categories WHERE is_active = TRUE ORDER BY parent_id, sort_order, name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $categories = $stmt->fetchAll();
        
        // 构建树形结构
        $tree = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] == 0) {
                $tree[$category['id']] = $category;
                $tree[$category['id']]['children'] = [];
            }
        }
        
        foreach ($categories as $category) {
            if ($category['parent_id'] != 0 && isset($tree[$category['parent_id']])) {
                $tree[$category['parent_id']]['children'][] = $category;
            }
        }
        
        return $tree;
        
    } catch (PDOException $e) {
        error_log("获取分类树失败: " . $e->getMessage());
        return [];
    }
}

// 获取热门搜索词
function getHotKeywords($pdo, $limit = 10) {
    try {
        $sql = "SELECT keyword, COUNT(*) as count 
                FROM search_history 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY keyword 
                ORDER BY count DESC 
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("获取热门搜索词失败: " . $e->getMessage());
        return [];
    }
}

// 记录搜索历史
function recordSearch($pdo, $keyword, $results_count, $user_id = null) {
    try {
        $sql = "INSERT INTO search_history (user_id, keyword, results_count) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $keyword, $results_count]);
        return true;
    } catch (PDOException $e) {
        error_log("记录搜索历史失败: " . $e->getMessage());
        return false;
    }
}

// 添加到购物车
function addToCart($pdo, $user_id, $product_id, $quantity = 1, $attributes = null) {
    try {
        // 检查是否已存在
        $checkSql = "SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$user_id, $product_id]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // 更新数量
            $newQuantity = $existing['quantity'] + $quantity;
            $updateSql = "UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$newQuantity, $existing['id']]);
            return 'updated';
        } else {
            // 新增
            $insertSql = "INSERT INTO cart_items (user_id, product_id, quantity, selected_attributes) VALUES (?, ?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSql);
            $attributesJson = $attributes ? json_encode($attributes) : null;
            $insertStmt->execute([$user_id, $product_id, $quantity, $attributesJson]);
            return 'added';
        }
    } catch (PDOException $e) {
        error_log("添加到购物车失败: " . $e->getMessage());
        return false;
    }
}

// 获取购物车商品数量
function getCartCount($pdo, $user_id) {
    try {
        $sql = "SELECT SUM(quantity) as count FROM cart_items WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("获取购物车数量失败: " . $e->getMessage());
        return 0;
    }
}

// 获取用户订单
function getUserOrders($pdo, $user_id, $limit = 10, $page = 1) {
    $offset = ($page - 1) * $limit;
    
    try {
        $sql = "SELECT o.*, 
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
                FROM orders o 
                WHERE o.user_id = ? 
                ORDER BY o.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $limit, $offset]);
        $orders = $stmt->fetchAll();
        
        // 获取每个订单的商品
        foreach ($orders as &$order) {
            $itemsSql = "SELECT oi.*, p.main_image 
                        FROM order_items oi 
                        LEFT JOIN products p ON oi.product_id = p.id 
                        WHERE oi.order_id = ?";
            $itemsStmt = $pdo->prepare($itemsSql);
            $itemsStmt->execute([$order['id']]);
            $order['items'] = $itemsStmt->fetchAll();
        }
        
        // 获取订单总数
        $countSql = "SELECT COUNT(*) as total FROM orders WHERE user_id = ?";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$user_id]);
        $total = $countStmt->fetch()['total'];
        
        return [
            'orders' => $orders,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ];
        
    } catch (PDOException $e) {
        error_log("获取用户订单失败: " . $e->getMessage());
        return ['orders' => [], 'total' => 0, 'pages' => 0];
    }
}

// 生成订单号
function generateOrderNumber() {
    return date('YmdHis') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

// 创建订单
function createOrder($pdo, $user_id, $cartItems, $shipping_address, $payment_method) {
    try {
        $pdo->beginTransaction();
        
        // 生成订单号
        $order_number = generateOrderNumber();
        
        // 计算总金额
        $total_amount = 0;
        foreach ($cartItems as $item) {
            $total_amount += $item['price'] * $item['quantity'];
        }
        
        // 计算最终金额（暂时不考虑运费和折扣）
        $final_amount = $total_amount;
        
        // 创建订单
        $orderSql = "INSERT INTO orders (order_number, user_id, total_amount, final_amount, 
                    shipping_address, payment_method, payment_status, order_status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending')";
        
        $orderStmt = $pdo->prepare($orderSql);
        $orderStmt->execute([
            $order_number,
            $user_id,
            $total_amount,
            $final_amount,
            $shipping_address,
            $payment_method
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // 创建订单商品
        foreach ($cartItems as $item) {
            $itemSql = "INSERT INTO order_items (order_id, product_id, product_name, 
                        product_price, quantity, subtotal) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            
            $itemStmt = $pdo->prepare($itemSql);
            $subtotal = $item['price'] * $item['quantity'];
            $itemStmt->execute([
                $order_id,
                $item['product_id'],
                $item['name'],
                $item['price'],
                $item['quantity'],
                $subtotal
            ]);
            
            // 减少商品库存
            $updateStockSql = "UPDATE products SET stock_quantity = stock_quantity - ?, 
                              sold_count = sold_count + ? WHERE id = ?";
            $updateStockStmt = $pdo->prepare($updateStockSql);
            $updateStockStmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
        }
        
        // 清空购物车
        $clearCartSql = "DELETE FROM cart_items WHERE user_id = ?";
        $clearCartStmt = $pdo->prepare($clearCartSql);
        $clearCartStmt->execute([$user_id]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'order_id' => $order_id,
            'order_number' => $order_number
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("创建订单失败: " . $e->getMessage());
        return ['success' => false, 'message' => '创建订单失败: ' . $e->getMessage()];
    }
}

// 获取订单详情
function getOrderDetails($pdo, $order_id, $user_id = null) {
    try {
        $sql = "SELECT o.* FROM orders o WHERE o.id = ?";
        $params = [$order_id];
        
        if ($user_id) {
            $sql .= " AND o.user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $order = $stmt->fetch();
        
        if (!$order) {
            return null;
        }
        
        // 获取订单商品
        $itemsSql = "SELECT oi.*, p.main_image 
                    FROM order_items oi 
                    LEFT JOIN products p ON oi.product_id = p.id 
                    WHERE oi.order_id = ?";
        $itemsStmt = $pdo->prepare($itemsSql);
        $itemsStmt->execute([$order_id]);
        $order['items'] = $itemsStmt->fetchAll();
        
        return $order;
        
    } catch (PDOException $e) {
        error_log("获取订单详情失败: " . $e->getMessage());
        return null;
    }
}
?>
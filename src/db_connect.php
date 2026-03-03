<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
// 数据库连接信息
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'shopping_db';
$username = getenv('DB_USER') ?: 'shopping_user';
$password = getenv('DB_PASSWORD') ?: 'shopping_password';        // 默认密码为空
try {
    // 创建数据库连接
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // 设置错误模式为异常
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 设置字符集
    $pdo->exec("SET NAMES 'utf8mb4'");
    
} catch(PDOException $e) {
    // 不向用户暴露数据库错误详情
    error_log("数据库连接失败: " . $e->getMessage());
    die("<div style='color:red;padding:20px;'>
        <h3>服务暂时不可用</h3>
        <p>请稍后再试或联系管理员</p>
    </div>");
}

// 获取当前登录用户信息的函数
function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

// 生成 CSRF 令牌
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// 验证 CSRF 令牌（验证后自动轮换，防止重放攻击）
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$token)) {
        return false;
    }
    // 验证成功后轮换令牌
    unset($_SESSION['csrf_token']);
    return true;
}
?>
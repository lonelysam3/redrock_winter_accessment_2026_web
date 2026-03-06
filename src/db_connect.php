<?php
header('Content-Type: text/html; charset=utf-8');

// 必须先检查 session 是否已启动，避免重复 session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 数据库连接信息（所有凭据均从环境变量读取，不使用硬编码默认值）
$host     = getenv('DB_HOST') ?: 'localhost';
$dbname   = getenv('DB_NAME') ?: 'shopping_db';
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

// 凭据缺失时快速失败，避免以空凭据连接数据库
if ($username === false || $username === '' || $password === false) {
    error_log("数据库凭据未配置：请在环境变量中设置 DB_USER 和 DB_PASSWORD");
    die("<div style='color:red;padding:20px;'>
        <h3>服务暂时不可用</h3>
        <p>请稍后再试或联系管理员</p>
    </div>");
}

try {
    // 创建数据库连接
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // 使用异常报告错误
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // 默认返回关联数组
            PDO::ATTR_EMULATE_PREPARES   => false,                   // 使用真正的预处理语句
        ]
    );

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
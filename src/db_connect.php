<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
// 数据库连接信息
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'shopping_db';
$username = getenv('DB_USER') ?: 'shopping_user';
$password = getenv('DB_PASSWORD') ?: 'shopping_password';

try {
    // 创建数据库连接
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // 设置错误模式为异常
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 设置字符集
    $pdo->exec("SET NAMES 'utf8mb4'");
    
} catch(PDOException $e) {
    // 如果连接失败，显示错误信息
    die("<div style='color:red;padding:20px;'>
        <h3>数据库连接失败</h3>
        <p>错误信息：" . $e->getMessage() . "</p>
        <p>请检查数据库连接配置</p>
        <p>Host: $host</p>
        <p>Database: $dbname</p>
        <p>User: $username</p>
    </div>");
}

// ... 其他代码 ...
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
?>
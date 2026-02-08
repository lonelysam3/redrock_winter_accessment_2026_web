<?php
require_once 'db_connect.php';

echo "<h1>数据库连接测试</h1>";

try {
    // 测试查询
    $stmt = $pdo->query("SELECT DATABASE() as db, USER() as user");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p style='color:green;'>✓ 数据库连接成功！</p>";
    echo "<p>当前数据库: " . $result['db'] . "</p>";
    echo "<p>连接用户: " . $result['user'] . "</p>";
    
    // 测试查询用户表
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>用户表记录数: " . $userCount['count'] . "</p>";
    
    // 显示所有环境变量
    echo "<h3>环境变量：</h3>";
    echo "<pre>";
    echo "DB_HOST: " . getenv('DB_HOST') . "\n";
    echo "DB_NAME: " . getenv('DB_NAME') . "\n";
    echo "DB_USER: " . getenv('DB_USER') . "\n";
    echo "DB_PASSWORD: " . getenv('DB_PASSWORD') . "\n";
    echo "</pre>";
    
} catch(PDOException $e) {
    echo "<p style='color:red;'>✗ 数据库查询失败: " . $e->getMessage() . "</p>";
}
?>
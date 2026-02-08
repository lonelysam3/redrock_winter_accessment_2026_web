<?php
// 启动会话（用于记住登录状态）
session_start();

// 引入数据库连接
require_once 'db_connect.php';

// 获取用户输入
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// 简单的输入验证
if (empty($username) || empty($password)) {
    header("Location: login.php?error=请填写用户名和密码");
    exit();
}

try {
    // 查询数据库，查找用户
    $sql = "SELECT * FROM users WHERE username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':username' => $username]);  // 确保与上面一致
    $user = $stmt->fetch();
    
    // 验证用户是否存在和密码是否正确
    if ($user && password_verify($password, $user['password'])) {
        // 登录成功
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        
        // 跳转到欢迎页面
        header("Location: welcome.php");
        exit();
    } else {
        // 登录失败
        header("Location: login.php?error=用户名或密码错误");
        exit();
    }
    
} catch(PDOException $e) {
    // 数据库查询出错
    header("Location: login.php?error=系统错误，请稍后再试");
    exit();
}
?>
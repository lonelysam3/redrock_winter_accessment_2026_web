<?php
// 启动会话（用于可能的会话操作）
session_start();

// 引入数据库连接
require_once 'db_connect.php';

// 设置页面标题
$pageTitle = '用户注册';

// 处理注册逻辑
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    
    // 验证输入
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = '请填写所有必填字段';
    } elseif ($password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } elseif (strlen($password) < 6) {
        $error = '密码至少需要6位';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = '用户名长度应在3-20个字符之间';
    } else {
        try {
            // 检查用户名是否已存在
            $sql = "SELECT id FROM users WHERE username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() > 0) {
                $error = '用户名已存在';
            } else {
                // 对密码进行哈希处理
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // 使用问号占位符避免参数绑定问题
                $sql = "INSERT INTO users (username, password, email, balance) VALUES (?, ?, ?, 100000)";
                $stmt = $pdo->prepare($sql);
                
                // 使用数组传递参数，确保参数顺序正确
                $stmt->execute([$username, $hashed_password, $email]);
                
                $success = '注册成功！正在跳转到登录页面...';
                header("refresh:2;url=login.php");
            }
        } catch(PDOException $e) {
            // 详细的错误信息（开发阶段可用）
            $error = '注册失败：' . $e->getMessage();
            
            // 生产环境使用简化的错误信息
            // $error = '注册失败，请稍后再试';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - 购物网站</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* 基础样式 */
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #4ecdc4;
            --dark-color: #2d3436;
            --light-color: #f9f9f9;
            --gray-color: #636e72;
            --border-color: #e0e0e0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft YaHei', '微软雅黑', sans-serif;
            background-color: #f5f5f5;
            color: var(--dark-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .container {
            max-width: 1200px;
            min-width: 1000px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-main {
            padding: 15px 0;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .logo i {
            margin-right: 5px;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-links a {
            color: var(--dark-color);
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            background: #f5f5f5;
        }
        
        .nav-links a.active {
            background: var(--primary-color);
            color: white;
        }
        
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* 注册页面样式 */
        .register-container {
            max-width: 450px;
            margin: 0 auto;
        }
        
        .register-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin: 30px 0;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .register-icon i {
            font-size: 36px;
            color: white;
        }
        
        .register-title {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        
        .register-subtitle {
            color: var(--gray-color);
            font-size: 16px;
        }
        
        .register-form .form-group {
            margin-bottom: 20px;
        }
        
        .register-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .register-form input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .register-form input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
            outline: none;
        }
        
        .register-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .register-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(78, 205, 196, 0.3);
        }
        
        .login-link {
            text-align: center;
            margin-top: 30px;
            color: var(--gray-color);
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        
        .password-requirements {
            font-size: 12px;
            color: var(--gray-color);
            margin-top: 5px;
        }
        
        .form-requirements {
            font-size: 12px;
            color: var(--gray-color);
            margin-top: 5px;
            line-height: 1.4;
        }
        
        @media (max-width: 576px) {
            .register-card {
                padding: 30px 20px;
            }
            
            .register-title {
                font-size: 24px;
            }
            
            .container {
                min-width: auto;
                padding: 0 10px;
            }
        }
        
        /* 响应式调整 */
        @media (max-width: 1000px) {
            .container {
                min-width: auto;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- 头部 -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="products.php" class="logo">
                    <i class="fas fa-shopping-bag"></i> 购物网站
                </a>
                
                <div class="nav-links">
                    <a href="products.php">
                        <i class="fas fa-store"></i> 商城首页
                    </a>
                    <a href="login.php" class="active">
                        <i class="fas fa-sign-in-alt"></i> 登录/注册
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- 消息提示 -->
    <?php if ($error || $success): ?>
        <div class="container">
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($error ?: $success); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 注册内容 -->
    <main class="container">
        <div class="register-container">
            <div class="register-card">
                <!-- 注册头部 -->
                <div class="register-header">
                    <div class="register-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h1 class="register-title">用户注册</h1>
                    <p class="register-subtitle">创建新账户，开始购物之旅</p>
                </div>
                
                <!-- 注册表单 -->
                <form class="register-form" method="POST" action="">
                    <div class="form-group">
                        <label for="username">用户名 *</label>
                        <input type="text" id="username" name="username" 
                               placeholder="请输入用户名（3-20位字符）" 
                               value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                               required minlength="3" maxlength="20">
                        <div class="form-requirements">用户名长度应在3-20个字符之间，只能包含字母、数字和下划线</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">邮箱地址</label>
                        <input type="email" id="email" name="email" 
                               placeholder="请输入邮箱（可选）" 
                               value="<?php echo htmlspecialchars($email ?? ''); ?>">
                        <div class="form-requirements">请输入有效的邮箱地址，用于密码找回和通知</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">密码 *</label>
                        <input type="password" id="password" name="password" 
                               placeholder="请输入密码（至少6位）" 
                               required minlength="6">
                        <div class="password-requirements">密码至少需要6位字符，建议使用字母、数字和符号的组合</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">确认密码 *</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               placeholder="请再次输入密码" 
                               required minlength="6">
                    </div>
                    
                    <button type="submit" class="register-button">
                        注册账号
                    </button>
                </form>
                
                <!-- 登录链接 -->
                <div class="login-link">
                    已有账号？ <a href="login.php">立即登录</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        // 表单验证
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.register-form');
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const email = document.getElementById('email');
            
            // 用户名验证（字母、数字、下划线）
            function validateUsername(username) {
                const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
                return usernameRegex.test(username);
            }
            
            // 密码强度验证
            function validatePassword(password) {
                // 至少6位
                if (password.length < 6) return false;
                // 可以添加更复杂的规则
                return true;
            }
            
            // 邮箱验证（可选）
            function validateEmail(email) {
                if (!email) return true; // 邮箱可选
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
            
            // 实时验证用户名
            username.addEventListener('blur', function() {
                const value = username.value.trim();
                if (value && !validateUsername(value)) {
                    username.style.borderColor = 'red';
                    showError(username, '用户名只能包含字母、数字和下划线，长度3-20位');
                } else {
                    username.style.borderColor = '';
                    clearError(username);
                }
            });
            
            // 实时验证邮箱
            email.addEventListener('blur', function() {
                const value = email.value.trim();
                if (value && !validateEmail(value)) {
                    email.style.borderColor = 'red';
                    showError(email, '请输入有效的邮箱地址');
                } else {
                    email.style.borderColor = '';
                    clearError(email);
                }
            });
            
            // 实时检查密码一致性
            confirmPassword.addEventListener('input', function() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.style.borderColor = 'red';
                    showError(confirmPassword, '两次输入的密码不一致');
                } else {
                    confirmPassword.style.borderColor = '';
                    clearError(confirmPassword);
                }
            });
            
            // 显示错误信息
            function showError(input, message) {
                clearError(input);
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.color = 'red';
                errorDiv.style.fontSize = '12px';
                errorDiv.style.marginTop = '5px';
                errorDiv.textContent = message;
                input.parentNode.appendChild(errorDiv);
            }
            
            // 清除错误信息
            function clearError(input) {
                const existingError = input.parentNode.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
            }
            
            // 表单提交验证
            form.addEventListener('submit', function(e) {
                let isValid = true;
                let errorMessage = '';
                
                // 验证用户名
                if (!validateUsername(username.value.trim())) {
                    isValid = false;
                    errorMessage = '用户名格式不正确（只能包含字母、数字和下划线，长度3-20位）';
                }
                
                // 验证邮箱
                else if (!validateEmail(email.value.trim())) {
                    isValid = false;
                    errorMessage = '邮箱格式不正确';
                }
                
                // 验证密码长度
                else if (!validatePassword(password.value)) {
                    isValid = false;
                    errorMessage = '密码长度至少为6位';
                }
                
                // 验证密码一致性
                else if (password.value !== confirmPassword.value) {
                    isValid = false;
                    errorMessage = '两次输入的密码不一致';
                }
                
                if (!isValid) {
                    e.preventDefault();
                    alert(errorMessage);
                }
                
                return isValid;
            });
            
            // 自动隐藏消息提示
            setTimeout(() => {
                const message = document.querySelector('.message');
                if (message) {
                    message.style.opacity = '0';
                    setTimeout(() => message.remove(), 300);
                }
            }, 5000);
        });
    </script>
</body>
</html>
<?php
// 启动会话
session_start();

// 设置页面标题
$pageTitle = '用户登录';

// 处理登录逻辑
$error = '';
$success = '';

// 如果有错误信息，显示它
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// 处理登录
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db_connect.php';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '请填写用户名和密码';
    } else {
        try {
            // 查询数据库，查找用户
            $sql = "SELECT * FROM users WHERE username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);  // 使用问号占位符，避免参数绑定问题
            $user = $stmt->fetch();
            
            // 验证用户是否存在和密码是否正确
            if ($user) {
                // 先检查密码是否为哈希格式（以 $2y$ 开头）
                if (strpos($user['password'], '$2y$') === 0) {
                    // 哈希密码验证
                    if (password_verify($password, $user['password'])) {
                        loginSuccess($user);
                    } else {
                        $error = '用户名或密码错误';
                    }
                } else {
                    // 明文密码验证（向后兼容）
                    if ($password === $user['password']) {
                        loginSuccess($user);
                    } else {
                        $error = '用户名或密码错误';
                    }
                }
            } else {
                $error = '用户名或密码错误';
            }
            
        } catch(PDOException $e) {
            $error = '系统错误，请稍后再试';
            // 调试模式下的错误信息
            // $error = '系统错误：' . $e->getMessage();
        }
    }
}

/**
 * 登录成功处理函数
 */
function loginSuccess($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    
    // 跳转到个人中心或返回页面
    $redirect = $_GET['redirect'] ?? 'welcome.php';
    header("Location: " . $redirect);
    exit();
}

// 页面特定的CSS
$pageStyles = '
<style>
    /* 登录页面样式 */
    .login-container {
        max-width: 450px;
        min-width: 400px;
        margin: 0 auto;
        padding: 20px 0;
    }
    
    .login-card {
        background: white;
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .login-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .login-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }
    
    .login-icon i {
        font-size: 36px;
        color: white;
    }
    
    .login-title {
        font-size: 28px;
        font-weight: 600;
        color: var(--dark-color);
        margin-bottom: 10px;
    }
    
    .login-subtitle {
        color: var(--gray-color);
        font-size: 16px;
    }
    
    .login-form .form-group {
        margin-bottom: 20px;
    }
    
    .login-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--dark-color);
    }
    
    .login-form input {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 16px;
        transition: all 0.3s;
    }
    
    .login-form input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        outline: none;
    }
    
    .login-options {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    
    .remember-me {
        display: flex;
        flex-wrap: nowrap;
        flex-direction: row;
        align-items: center;
        gap: 8px;
    }
    
    .forgot-password {
        color: var(--primary-color);
        text-decoration: none;
        font-size: 14px;
    }
    
    .login-button {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 18px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .login-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    }
    
    .login-divider {
        text-align: center;
        margin: 30px 0;
        position: relative;
    }
    
    .login-divider::before {
        content: "";
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        height: 1px;
        background: var(--border-color);
    }
    
    .login-divider span {
        background: white;
        padding: 0 20px;
        color: var(--gray-color);
        position: relative;
    }
    
    .register-link {
        text-align: center;
        margin-top: 30px;
        color: var(--gray-color);
    }
    
    .register-link a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 600;
    }
    
    /* 错误信息样式 */
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    
    .alert-error {
        background-color: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    
    .alert-success {
        background-color: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }
    
</style>
';

// 引入头部
require_once 'header.php';
?>

<div class="login-container">
    <div class="login-card">
        <!-- 登录头部 -->
        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-user-circle"></i>
            </div>
            <h1 class="login-title">用户登录</h1>
            <p class="login-subtitle">欢迎回来，请登录您的账户</p>
        </div>
        
        <!-- 错误信息 -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- 成功信息 -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- 登录表单 -->
        <form class="login-form" method="POST" action="">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" placeholder="请输入用户名" required
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" placeholder="请输入密码" required>
            </div>
            
            <div class="login-options">
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember" style="margin:0;">记住我</label>
                </div>
                <a href="#" class="forgot-password">忘记密码？</a>
            </div>
            
            <button type="submit" class="login-button">
                登录
            </button>
        </form>
        
        <!-- 注册链接 -->
        <div class="register-link">
            还没有账号？ <a href="register.php">立即注册</a>
        </div>
    </div>
</div>

<?php
session_start();
session_destroy();  // 销毁所有会话数据
header("Location: login.php");  // 跳转到登录页
exit();
?>
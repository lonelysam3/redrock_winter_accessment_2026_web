        </div><!-- close .container -->
    </main>

    <!-- 页脚 -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>购物网站</h3>
                    <p style="color: #bbb; line-height: 1.8;">您的一站式购物平台，提供优质商品和卓越购物体验。</p>
                </div>
                <div class="footer-section">
                    <h3>快速链接</h3>
                    <ul class="footer-links">
                        <li><a href="products.php">商城首页</a></li>
                        <li><a href="cart.php">购物车</a></li>
                        <li><a href="orders.php">我的订单</a></li>
                        <li><a href="welcome.php">个人中心</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>帮助中心</h3>
                    <ul class="footer-links">
                        <li><a href="#">如何购买</a></li>
                        <li><a href="#">支付方式</a></li>
                        <li><a href="#">退换货政策</a></li>
                        <li><a href="#">联系我们</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; 2024 购物网站 版权所有
            </div>
        </div>
    </footer>

    <!-- 页面特定的JS -->
    <?php if (isset($pageScripts)): ?>
        <?php echo $pageScripts; ?>
    <?php endif; ?>

    <!-- 通用JS -->
    <script>
        // 搜索建议
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.addEventListener('keyup', function(e) {
                    if (e.key === 'Enter') {
                        this.closest('form').submit();
                    }
                });
            }

            // 消息自动消失
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                });
            }, 5000);
        });

        // 添加到购物车函数
        function addToCart(productId, quantity = 1) {
            <?php if (!isset($_SESSION['user_id'])): ?>
                alert('请先登录！');
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                return;
            <?php endif; ?>

            fetch('api/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 更新购物车数量
                    const cartCount = document.querySelector('.cart-count');
                    if (cartCount) {
                        cartCount.textContent = parseInt(cartCount.textContent || 0) + 1;
                    } else {
                        const cartLink = document.querySelector('a[href="cart.php"]');
                        cartLink.innerHTML = '<i class="fas fa-shopping-cart"></i><span class="cart-count">1</span>';
                    }

                    // 显示成功消息
                    showMessage('商品已成功添加到购物车！', 'success');
                } else {
                    showMessage('添加失败: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('网络错误，请稍后重试', 'error');
            });
        }

        // 显示消息函数
        function showMessage(text, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <span>${text}</span>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            `;

            const container = document.querySelector('.container');
            const firstChild = container.firstChild;
            if (firstChild) {
                container.insertBefore(alertDiv, firstChild);
            } else {
                container.appendChild(alertDiv);
            }

            // 5秒后自动消失
            setTimeout(() => {
                alertDiv.style.opacity = '0';
                setTimeout(() => alertDiv.remove(), 300);
            }, 5000);
        }
    </script>
</body>
</html>

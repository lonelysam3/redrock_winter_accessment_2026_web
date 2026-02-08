##### 使用方法：

使用github actions构建（Docker Desktop总是抽风又没有报错信息只能用github了😭😭）

可使用以下命令拉取

docker pull ghcr.io/lonelysam3/redrock_winter_accessment_2026_web:aca05466e88336b3296e4af052b91c6d2ed93e21

调试时使用XMAPP集成环境

##### 已完成功能：

商品自动从数据库拉取

搜索商品

注册登录

收货地址与余额和账号绑定

购物车

下单并生成订单号

##### 目录结构：

```
├── src/
	├── buy_now.php
	├── index.php
	├── cart.php
	├── checkout.php
	├── create_direct_order.php
	├── db_connect.php
	├── header.php
	├── login.php
	├── login_process.php
	├── logout.php
	├── order_detail.php
	├── orders.php
	├── product_detail.php
	├── product_functions.php
	├── products.php
	├── register.php
	├── welcome.php
	└── uploads/          
    	├── avatars/
    	└── products/              
├── docker-compose.yml
├── Dockerfile
├── .dockerignore
├── README.md

```

##### 文件作用：

index.php: 跳转到商城首页
buy_now.php: 不加入购物车直接购买页面
cart.php：购物车页面
checkout.php：下单页面
create_direct_order.php：创建不加入购物车直接购买订单
db_connect.php：链接数据库器
header.php：网页头部样式定义器
login.php：登录页面
login_process.php：登录操作器
logout.php：登出器
order_detail.php：订单详情页面
orders.php：订单卡片
product_detail.php：产品详情页面
product_functions.php：产品功能（搜索商品，拉取价格等）
products.php：产品卡片
register.php：注册页面
welcome.php：个人中心页面

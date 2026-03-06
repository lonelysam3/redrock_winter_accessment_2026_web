# 使用官方 PHP 和 Apache 镜像
FROM php:8.1-apache

# 安装 PHP 扩展和系统依赖
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype-dev \
    libzip-dev \
    libssl-dev \
    zip \
    unzip \
    git \
    curl \
    vim \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mysqli zip opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 启用 Apache 的 rewrite 模块
RUN a2enmod rewrite

# 复制自定义的 php.ini
COPY php.ini /usr/local/etc/php/php.ini

# 设置工作目录
WORKDIR /var/www/html

# 复制应用程序文件
COPY src/ /var/www/html/

# 修改Apache运行用户为当前用户（解决权限问题）
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# 设置文件权限
RUN mkdir -p /var/www/html/uploads/avatars \
    && mkdir -p /var/www/html/uploads/products \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 755 /var/www/html/uploads

# 修改Apache配置以允许访问
RUN echo "<Directory /var/www/html>" >> /etc/apache2/apache2.conf \
    && echo "    Options Indexes FollowSymLinks" >> /etc/apache2/apache2.conf \
    && echo "    AllowOverride All" >> /etc/apache2/apache2.conf \
    && echo "    Require all granted" >> /etc/apache2/apache2.conf \
    && echo "</Directory>" >> /etc/apache2/apache2.conf

# 暴露端口
EXPOSE 80

# 启动 Apache
CMD ["apache2-foreground"]
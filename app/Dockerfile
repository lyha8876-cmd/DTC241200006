FROM php:8.2-apache

# Cài đặt extension pdo_mysql kết nối CSDL
RUN docker-php-ext-install pdo pdo_mysql

# Cấu hình bảo mật và phân quyền Non-root
WORKDIR /var/www/html
COPY index.php /var/www/html/index.php
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
USER www-data

FROM php:8.3-fpm

# Install MySQLi and PDO extensions required by your application
RUN docker-php-ext-install mysqli pdo pdo_mysql

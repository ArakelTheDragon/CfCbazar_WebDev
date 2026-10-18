FROM php:8.3-fpm

# Install system dependencies and required PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo pdo_mysql mysqli bcmath

# Set working directory
WORKDIR /var/www/html

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure default umask for new files
RUN echo "umask 0022" >> /root/.bashrc

EXPOSE 9000
CMD ["php-fpm"]
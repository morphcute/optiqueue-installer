FROM php:8.2-apache

# Install required PHP extensions (pdo_mysql, zip)
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    && docker-php-ext-install pdo pdo_mysql zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy installer files into Apache document root
COPY . /var/www/html/

# Set permissions
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

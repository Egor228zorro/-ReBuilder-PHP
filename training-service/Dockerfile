FROM php:8.1-apache

RUN a2enmod rewrite

# УСТАНАВЛИВАЕМ PostgreSQL драйвер для PHP
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# УСТАНАВЛИВАЕМ Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копируем исходный код
COPY . /var/www/html/

# УСТАНАВЛИВАЕМ зависимости через Composer
RUN composer install --no-dev --optimize-autoloader

# Меняем DocumentRoot на public
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/apache2.conf

# Устанавливаем права
RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html
EXPOSE 80
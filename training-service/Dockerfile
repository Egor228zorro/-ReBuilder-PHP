FROM php:8.1-apache

# Включаем mod_rewrite для Apache
RUN a2enmod rewrite

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip

# Копируем исходный код
COPY . /var/www/html/

# Копируем настройки Apache
COPY .docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Устанавливаем права
RUN chown -R www-data:www-data /var/www/html

# Рабочая директория
WORKDIR /var/www/html

EXPOSE 80
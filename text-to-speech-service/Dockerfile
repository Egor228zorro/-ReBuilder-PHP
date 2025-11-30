FROM php:8.1-apache

RUN a2enmod rewrite

COPY . /var/www/html/

RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/apache2.conf

RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html
EXPOSE 80
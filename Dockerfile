
FROM php:8.2-apache

WORKDIR /var/www/html

RUN docker-php-ext-install pdo_mysql mysqli \
    && a2enmod rewrite

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80

CMD ["apache2-foreground"]

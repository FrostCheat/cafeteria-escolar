FROM php:8.1-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN a2enmod rewrite

RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    mkdir -p /var/www/html/imgs/uploads && \
    chmod -R 777 /var/www/html/imgs/uploads

EXPOSE 80
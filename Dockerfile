FROM php:8.3-apache

# PHP MySQL driver used by config/db.php (mysqli)
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Allow .htaccess overrides so my_eshop/uploads/.htaccess (deny PHP execution) takes effect
RUN sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && a2enmod rewrite

# Copy the app into the image (docker-compose bind-mounts over this in dev for live edits)
COPY ./my_eshop/ /var/www/html/

# uploads/ must be writable by Apache for product image uploads
RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80

FROM php:8.3-apache

# PHP MySQL driver used by config/db.php (mysqli)
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Allow .htaccess overrides so my_eshop/uploads/.htaccess (deny PHP execution) takes effect
RUN sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && a2enmod rewrite

# Copy the app into the image (docker-compose bind-mounts over this in dev for live edits)
COPY ./my_eshop/ /var/www/html/

# uploads/ must be writable by Apache for product image uploads.
# On Render this disk is ephemeral — uploads go to Cloudinary instead (see
# config/media.php); locally it still works as before.
RUN mkdir -p /var/www/html/uploads/stores && chown -R www-data:www-data /var/www/html/uploads

# Render assigns the listening port via $PORT. Apache expands ${PORT} from the
# environment at startup, defaulting to 80 so local docker compose is unchanged.
ENV PORT=80
RUN sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

EXPOSE 80

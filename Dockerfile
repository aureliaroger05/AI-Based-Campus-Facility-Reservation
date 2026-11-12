FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev unzip \
    && docker-php-ext-install mbstring pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p uploads \
    && chown -R www-data:www-data uploads

EXPOSE 10000

CMD ["sh", "-c", "port=\"${PORT:-10000}\"; sed -i \"s/Listen 80/Listen ${port}/\" /etc/apache2/ports.conf; sed -i \"s/:80>/:${port}>/\" /etc/apache2/sites-available/000-default.conf; exec apache2-foreground"]

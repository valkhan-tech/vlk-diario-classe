# Imagem de DESENVOLVIMENTO (producao e HostGator, sem docker).
FROM php:8.4-apache

ENV DEBIAN_FRONTEND=noninteractive \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
        libicu-dev libonig-dev libsodium-dev \
        unzip git default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mysqli gd zip intl mbstring bcmath opcache exif sodium \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache: modulos, DocumentRoot e AllowOverride
RUN a2enmod rewrite headers expires \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    Options -Indexes +FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/docroot.conf \
    && a2enconf docroot

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-dev.ini
COPY docker/php/client.cnf /etc/mysql/conf.d/zz-dev-client.cnf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

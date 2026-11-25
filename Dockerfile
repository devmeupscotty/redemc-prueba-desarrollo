FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist
COPY src/ src/
RUN composer dump-autoload --no-dev --classmap-authoritative

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev \
    && docker-php-ext-install pdo_mysql intl \
    && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'expose_php = Off\n' > "$PHP_INI_DIR/conf.d/security.ini"

ENV PORT=80
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN a2enmod headers \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's/^Listen .*/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
        '<Directory ${APACHE_DOCUMENT_ROOT}>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        'ServerTokens Prod' \
        'ServerSignature Off' \
        > /etc/apache2/conf-available/app.conf \
    && a2enconf app

WORKDIR /var/www/html
COPY --from=vendor /app/vendor/ vendor/
COPY src/ src/
COPY templates/ templates/
COPY public/ public/

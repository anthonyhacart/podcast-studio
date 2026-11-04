# Image de dev/démo : FrankenPHP (PHP 8.4 + Caddy + Mercure), outils audio et navigateur pour Panther.
FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions \
        pdo_pgsql \
        intl \
        zip \
        opcache \
        apcu \
        pcntl

# ffmpeg : montage/normalisation audio des POC vocaux.
# chromium + driver : tests e2e Panther (micro et caméra simulés).
RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg chromium chromium-driver git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PANTHER_NO_SANDBOX=1 \
    PANTHER_CHROME_ARGUMENTS="--disable-dev-shm-usage" \
    PANTHER_CHROME_BINARY=/usr/bin/chromium

COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile
COPY docker/frankenphp/php.ini $PHP_INI_DIR/conf.d/zz-app.ini

WORKDIR /app

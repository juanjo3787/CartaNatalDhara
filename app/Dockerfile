# Compila los assets de front-end (Vite) para generar public/build.
FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .
RUN npm run build

# Compila swetest (Swiss Ephemeris) y descarga las efemerides oficiales.
# Swiss Ephemeris es AGPL: https://github.com/aloistr/swisseph
FROM debian:bookworm-slim AS ephemeris

RUN apt-get update \
    && apt-get install -y --no-install-recommends git ca-certificates build-essential \
    && rm -rf /var/lib/apt/lists/*

RUN git clone --depth 1 https://github.com/aloistr/swisseph.git /opt/swisseph-src

WORKDIR /opt/swisseph-src
RUN make swetests

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev \
    && docker-php-ext-install bcmath intl pdo_mysql zip pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=ephemeris /opt/swisseph-src/bin/swetest /usr/local/bin/swetest
COPY --from=ephemeris /opt/swisseph-src/ephe /opt/ephemeris

ENV ASTROLOGY_EPHEMERIS_PATH=/opt/ephemeris
ENV ASTROLOGY_SWETEST_BIN=/usr/local/bin/swetest

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN a2enmod rewrite \
    && sed -i 's/Listen 80/Listen 8000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:8000>/; s#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf
COPY docker/apache-workers.conf /etc/apache2/conf-enabled/report-workers.conf
RUN echo 'memory_limit=512M' > /usr/local/etc/php/conf.d/report-memory.ini
COPY docker/php-errors.ini /usr/local/etc/php/conf.d/report-errors.ini

COPY composer.json composer.lock* ./
RUN if [ -f composer.lock ]; then composer install --no-interaction --prefer-dist --no-progress --no-scripts; fi

COPY . .
COPY --from=assets /app/public/build /var/www/html/public/build
RUN composer dump-autoload --optimize
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000
CMD ["apache2-foreground"]

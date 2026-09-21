# Compila swetest (Swiss Ephemeris) y descarga las efemerides oficiales.
# Swiss Ephemeris es AGPL: https://github.com/aloistr/swisseph
FROM debian:bookworm-slim AS ephemeris

RUN apt-get update \
    && apt-get install -y --no-install-recommends git ca-certificates build-essential \
    && rm -rf /var/lib/apt/lists/*

RUN git clone --depth 1 https://github.com/aloistr/swisseph.git /opt/swisseph-src

WORKDIR /opt/swisseph-src
RUN make swetests

FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev \
    && docker-php-ext-install bcmath intl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=ephemeris /opt/swisseph-src/bin/swetest /usr/local/bin/swetest
COPY --from=ephemeris /opt/swisseph-src/ephe /opt/ephemeris

ENV ASTROLOGY_EPHEMERIS_PATH=/opt/ephemeris
ENV ASTROLOGY_SWETEST_BIN=/usr/local/bin/swetest

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN if [ -f composer.lock ]; then composer install --no-interaction --prefer-dist --no-progress --no-scripts; fi

COPY . .
RUN composer dump-autoload --optimize

EXPOSE 8000
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=8000"]

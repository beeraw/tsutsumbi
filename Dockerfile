# syntax=docker/dockerfile:1.7

# One system image for the application. It holds PHP, its extensions and the tools, never the code: the code is the
# git checkout of the server, mounted over /app, so a release is a git pull and this image is only rebuilt, on the
# server itself by deploy/server/tsutsumbi-deploy, when this file or docker/ change.
# The PHP patch is pinned so that a security release is a commit: 8.5.12 is the version running on rossem.
FROM dunglas/frankenphp:1-php8.5.12-trixie

ENV TZ=Indian/Mayotte \
    APP_ENV=prod \
    APP_DEBUG=0 \
    OPCACHE_VALIDATE_TIMESTAMPS=0 \
    XDG_CONFIG_HOME=/config \
    XDG_DATA_HOME=/data \
    COMPOSER_HOME=/tmp/composer

# imagemagick brings the libraries Imagick converts attachments with (WebP included); curl serves the health check;
# mariadb-client brings mariadb-dump, which the backup API streams the database with.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        imagemagick \
        tzdata \
        curl \
        mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# ctype, fileinfo, iconv, mbstring and zlib are compiled into the base image.
RUN install-php-extensions imagick intl opcache pdo_mysql zip

COPY docker/php/tsutsumbi.ini $PHP_INI_DIR/conf.d/zz-tsutsumbi.ini
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# The base image probes Caddy's admin API, which docker/frankenphp/Caddyfile turns off: left in place, the container
# would read as unhealthy. The app service checks /healthz instead (deploy/production/compose.yaml).
HEALTHCHECK NONE

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

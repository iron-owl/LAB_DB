FROM php:8.1-fpm

# установить необходимые пакеты (Debian-based)
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      libzip-dev \
      libonig-dev \
      zlib1g-dev \
      default-mysql-client \
 && docker-php-ext-install mysqli pdo pdo_mysql \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html


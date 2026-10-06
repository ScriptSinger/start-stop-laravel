FROM php:8.4-fpm-bookworm

ARG UID
ARG GID

ENV UID=${UID:-1000}
ENV GID=${GID:-1000}

WORKDIR /var/www/html

# Создаем пользователя и группу
RUN groupadd -g ${GID} laravel \
    && useradd -m -u ${UID} -g laravel -s /bin/bash laravel

# Настраиваем PHP-FPM под этого пользователя
RUN sed -i "s/^user = .*/user = laravel/" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/^group = .*/group = laravel/" /usr/local/etc/php-fpm.d/www.conf

# Установка зависимостей и расширений PHP (gd — нужен под миниатюры товаров,
# как в старом проекте system/library/cache + model/tool/image;
# mariadb-client — mariadb-dump для php artisan db:backup)
RUN apt-get update && apt-get install -y \
    git \
    mariadb-client \
    bash \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql zip gd opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

EXPOSE 9000
CMD ["php-fpm"]

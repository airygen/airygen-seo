FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    curl \
    && docker-php-ext-install pdo pdo_mysql mysqli zip gd

# PCOV: fast code-coverage driver for PHPUnit (kept off by default; the
# `make coverage` target enables it per-run via -d pcov.enabled=1).
# Built from the GitHub source tarball because pecl.php.net downloads 504
# intermittently and break image builds.
RUN set -eux; \
    curl -fsSL -o /tmp/pcov.tar.gz https://github.com/krakjoe/pcov/archive/refs/tags/v1.0.12.tar.gz; \
    mkdir -p /usr/src/pcov && tar -xzf /tmp/pcov.tar.gz -C /usr/src/pcov --strip-components=1; \
    cd /usr/src/pcov && phpize && ./configure && make -j"$(nproc)" && make install; \
    docker-php-ext-enable pcov; \
    echo "pcov.enabled=0" > "$PHP_INI_DIR/conf.d/pcov.ini"; \
    rm -rf /tmp/pcov.tar.gz /usr/src/pcov

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/;date.timezone =.*/date.timezone = Asia\/Taipei/g' $PHP_INI_DIR/php.ini \
    && sed -i 's/upload_max_filesize =.*/upload_max_filesize = 20M/g' $PHP_INI_DIR/php.ini \
    && sed -i 's/post_max_size =.*/post_max_size = 20M/g' $PHP_INI_DIR/php.ini \
    && sed -i 's/memory_limit =.*/memory_limit = 256M/g' $PHP_INI_DIR/php.ini \
    && sed -i 's/max_execution_time =.*/max_execution_time = 60/g' $PHP_INI_DIR/php.ini \
    && sed -i 's/expose_php =.*/expose_php = Off/g' $PHP_INI_DIR/php.ini

WORKDIR /var/www/html
RUN curl -O https://wordpress.org/latest.zip && \
    unzip latest.zip && \
    mv wordpress/* . && \
    rm -rf wordpress latest.zip

RUN chown -R www-data:www-data /var/www/html

RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

EXPOSE 8000

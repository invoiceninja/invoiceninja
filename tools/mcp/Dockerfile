FROM php:8.3-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libpng-dev libicu-dev libxml2-dev libgmp-dev libonig-dev && docker-php-ext-install -j4 bcmath pdo_mysql gd intl zip gmp mbstring soap && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends default-mysql-client && rm -rf /var/lib/apt/lists/*
RUN mkdir -p /admin-api
WORKDIR /app


RUN printf 'memory_limit=1G\n' > /usr/local/etc/php/conf.d/mcp-test.ini

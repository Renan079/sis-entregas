FROM php:8.3-fpm-bookworm

# Instala dependências do sistema, extensões PHP e ferramentas
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        mbstring \
        bcmath \
        xml \
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copia o Composer oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Copia arquivos de dependências primeiro (aproveita cache do Docker)
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copia o restante do código do backend e limpa caches locais antigos
COPY backend/ .
RUN rm -f bootstrap/cache/*.php

# Gera autoloader otimizado
RUN composer dump-autoload --optimize --no-dev

# Configura limites padrão do PHP
RUN echo "upload_max_filesize = 10M\npost_max_size = 12M\nmemory_limit = 256M\nmax_execution_time = 60" > /usr/local/etc/php/conf.d/uploads.ini

# Copia e habilita o entrypoint
COPY infra/docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
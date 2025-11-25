# use php 8.3 with apache
FROM php:8.3-apache

# 1. Install system dependencies
# Add 'libpq-dev' necessary for pgsql extension compilation
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# 2. Install PHP extensions (pdo_pgsql is indispensable)
# Install 'pdo' and 'pdo_pgsql' extensions
RUN docker-php-ext-install pdo pdo_pgsql zip gd

# 3. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Configure Apache (for the Front Controller)
# Ensure you have the .docker/vhost.conf file for redirection
COPY ./src/docker/.docker/vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite
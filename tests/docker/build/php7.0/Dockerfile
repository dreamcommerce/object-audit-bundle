FROM php:7.0-cli

RUN apt-get update
RUN apt-get install -y \
    curl \
    wget \
    git \
    libmcrypt-dev \
    libicu-dev \
    libicu52  \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install pdo \
    && docker-php-ext-install opcache \
    && docker-php-ext-install zip \
    && docker-php-ext-install bcmath

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
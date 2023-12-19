FROM php:8.2-fpm

# Copy composer.lock and composer.json    
COPY composer.lock composer.json /var/www/

# Set working directory
WORKDIR /var/www

# Clear cache
RUN apt-get update

RUN apt-get install libonig-dev --assume-yes \
    && apt-get clean
RUN apt-get install libzip-dev --assume-yes \
    && apt-get clean
RUN apt-get install libpng-dev --assume-yes \
    && apt-get clean
RUN apt-get install libjpeg-dev --assume-yes \
    && apt-get clean
RUN apt-get install libfreetype6-dev --assume-yes \
    && apt-get clean
RUN apt-get install libpq-dev --assume-yes \
    && apt-get clean 
RUN apt-get install libsodium-dev --assume-yes \
    && apt-get clean
RUN apt-get clean && rm -rf /var/lib/apt/lists/* 

# Install extensions
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install mbstring
RUN docker-php-ext-install zip
RUN docker-php-ext-install exif
RUN docker-php-ext-install pcntl
RUN docker-php-ext-install gd
RUN docker-php-ext-install pgsql
RUN docker-php-ext-install pdo_pgsql
RUN docker-php-ext-install sodium

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql
# Install dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    libzip-dev \
    && apt-get clean

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add user for laravel application
RUN groupadd -g 1000 www
RUN chmod g+rwx .
RUN useradd -u 1000 -ms /bin/bash -g www www

# Copy existing application directory contents
COPY . /var/www

# Copy existing application directory permissions
COPY --chown=www:www . /var/www

RUN chown -R www:www /var/www

# Change current user to www
USER www

RUN composer install --optimize-autoloader

RUN php artisan key:generate

RUN php artisan passport:keys 

RUN php artisan passport:install

RUN php artisan config:cache

RUN php artisan route:cache

RUN php artisan view:cache

RUN php artisan optimize

RUN php artisan storage:link


# Expose port 9000 and start php-fpm server
EXPOSE 9000

CMD ["php-fpm"]

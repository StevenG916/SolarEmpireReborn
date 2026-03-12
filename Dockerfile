FROM php:8.3-apache

ARG REPO_URL=https://github.com/StevenG916/SolarEmpireReborn.git
ARG REPO_BRANCH=main

# Install PHP extensions, git, and cron
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    git \
    cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Clone the repo into the web root
RUN git clone --depth=1 --branch=${REPO_BRANCH} ${REPO_URL} /var/www/html \
    && rm -rf /var/www/html/.git

# Make sure the img directory is writable for map generation
RUN mkdir -p /var/www/html/img \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/img

# Set up cron jobs for maintenance scripts
RUN echo "0 * * * * www-data php /var/www/html/run_hourly.php >> /var/log/se_hourly.log 2>&1" > /etc/cron.d/solar-empire \
    && echo "0 0 * * * www-data php /var/www/html/run_daily.php  >> /var/log/se_daily.log  2>&1" >> /etc/cron.d/solar-empire \
    && chmod 0644 /etc/cron.d/solar-empire

# PHP tuning
RUN echo "upload_max_filesize = 16M"        >> /usr/local/etc/php/conf.d/se.ini \
    && echo "post_max_size = 16M"           >> /usr/local/etc/php/conf.d/se.ini \
    && echo "max_execution_time = 120"      >> /usr/local/etc/php/conf.d/se.ini \
    && echo "session.gc_maxlifetime = 7200" >> /usr/local/etc/php/conf.d/se.ini

# Copy entrypoint from the cloned repo
RUN cp /var/www/html/docker/entrypoint.sh /entrypoint.sh \
    && chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]

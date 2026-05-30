FROM php:8.2-apache

# ── 系统依赖 ──
RUN apt-get update && apt-get install -y --no-install-recommends \
    libreoffice-calc \
    libreoffice-impress \
    python3-uno \
    imagemagick \
    ghostscript \
    python3 python3-pip \
    libnss3 libnspr4 libatk-bridge2.0-0 libdrm2 libxkbcommon0 libgbm1 libasound2 \
    fonts-wqy-zenhei \
    && rm -rf /var/lib/apt/lists/*

# ── PHP 扩展 dev 库 ──
RUN apt-get update && apt-get install -y --no-install-recommends \
    libonig-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libcurl4-openssl-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# ── PHP 扩展 ──
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mbstring zip gd curl xml \
    && docker-php-ext-enable mbstring zip gd curl xml

# ── Composer (install via script instead of multi-stage) ──
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ── Python + Playwright ──
RUN pip3 install --break-system-packages playwright numpy Pillow openpyxl pandas \
    && playwright install chromium \
    && playwright install-deps chromium \
    && chmod -R o+rX /root
ENV PLAYWRIGHT_BROWSERS_PATH=/root/.cache/ms-playwright

# ── Apache ──
RUN a2enmod rewrite
RUN echo 'memory_limit = 2048M' >> /usr/local/etc/php/conf.d/memory.ini
RUN mkdir -p /var/www/.cache/dconf && chown -R www-data:www-data /var/www/.cache
ENV HOME=/tmp
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# ── 项目 ──
WORKDIR /var/www/html
COPY . /var/www/html
RUN composer install --no-dev --optimize-autoloader && rm -rf /root/.composer

# ── 目录权限 ──
RUN mkdir -p data logs tmp uploads templates/backup \
    && chown -R www-data:www-data data logs tmp uploads templates templates/backup \
    && echo '{}' > data/settings.json && chown www-data:www-data data/settings.json

COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
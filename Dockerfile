FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT /var/www/html
# COPY ./src ${APACHE_DOCUMENT_ROOT}
COPY php.ini /usr/local/etc/php/
# tzdataをインストールし、タイムゾーンを設定する
RUN apt-get update && apt-get install -y tzdata \
    && ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime \
    && echo "Asia/Tokyo" > /etc/timezone \
    && dpkg-reconfigure -f noninteractive tzdata
RUN apt-get update;
RUN apt-get install -y \
  libpq-dev \
  libfreetype6-dev \
  libicu-dev \
  libjpeg62-turbo-dev \
  libzip-dev \
  unzip \
  zlib1g-dev \
  libwebp-dev \
  ;

RUN curl -sS https://getcomposer.org/installer \
  | php \
  && mv composer.phar /usr/bin/composer

RUN composer config -g repos.packagist composer https://packagist.jp

# COPY src/ /var/www/html/
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install zip gd mysqli pdo_mysql opcache intl
RUN pecl install apcu && echo "extension=apcu.so" > /usr/local/etc/php/conf.d/apc.ini
RUN a2enmod rewrite headers

# COPY ./entrypoint.sh ${APACHE_DOCUMENT_ROOT}/entrypoint.sh
# RUN chmod +x ${APACHE_DOCUMENT_ROOT}/entrypoint.sh

# ApacheのServerヘッダーを非表示にする
RUN echo "ServerTokens Prod" >> /etc/apache2/conf-available/security.conf && \
    echo "ServerSignature Off" >> /etc/apache2/conf-available/security.conf

# X-Frame-Options を SAMEORIGIN に設定
RUN echo '<IfModule mod_headers.c>\n    Header always set X-Frame-Options "SAMEORIGIN"\n</IfModule>' > /etc/apache2/conf-available/security-headers.conf && \
    a2enconf security-headers

# varはfargateのbind mountを使用
RUN mkdir -p ${APACHE_DOCUMENT_ROOT}/var && chmod 707 ${APACHE_DOCUMENT_ROOT}/var
RUN touch ${APACHE_DOCUMENT_ROOT}/var/.htaccess && \
      echo "Require all denied" > ${APACHE_DOCUMENT_ROOT}/var/.htaccess
VOLUME ["/tmp", "/var/run/apache2", "${APACHE_DOCUMENT_ROOT}/var"]

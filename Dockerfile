FROM php:8.4-cli
ARG UID=1000
ARG GID=1000
RUN sed -i "s|deb.debian.org|cdn-aws.deb.debian.org|g" /etc/apt/sources.list.d/debian.sources \
 && apt-get update \
 && apt-get install -y --no-install-recommends $PHPIZE_DEPS libicu-dev libonig-dev libzip-dev default-mysql-client git unzip \
 && docker-php-ext-install pdo_mysql bcmath intl sockets zip \
 && pecl install redis && docker-php-ext-enable redis \
 && apt-get purge -y $PHPIZE_DEPS \
 && apt-get autoremove -y \
 && rm -rf /var/lib/apt/lists/*
RUN groupadd -g $GID app && useradd -u $UID -g $GID -m app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
RUN chown -R app:app /app
USER app

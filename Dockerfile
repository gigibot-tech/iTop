FROM php:8.4-apache-bookworm

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		libfreetype6-dev \
		libjpeg62-turbo-dev \
		libpng-dev \
		libzip-dev \
		libicu-dev \
		libxml2-dev \
		pkg-config \
		default-mysql-client \
		nodejs \
		npm \
		git \
		ca-certificates \
		unzip \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install -j"$(nproc)" gd intl mysqli soap zip \
	&& php -m | grep -qi sodium \
	&& a2enmod rewrite \
	&& rm -rf /var/lib/apt/lists/*

RUN printf '%s\n' \
		'memory_limit=256M' \
		'upload_max_filesize=32M' \
		'post_max_size=32M' \
		'max_execution_time=180' \
		'date.timezone=Europe/Paris' \
		> /usr/local/etc/php/conf.d/itop.ini \
	&& sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . /var/www/html
RUN npm install --unsafe-perm
COPY docker/railway-start.sh /usr/local/bin/railway-start.sh
RUN chmod 755 /usr/local/bin/railway-start.sh \
	&& mkdir -p /opt/itop-seed-conf conf/production data log env-production \
	&& cp -a conf/. /opt/itop-seed-conf/ \
	&& chown -R www-data:www-data conf data log env-production

EXPOSE 8080
CMD ["/usr/local/bin/railway-start.sh"]

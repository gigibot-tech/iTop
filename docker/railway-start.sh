#!/bin/sh
set -eu
cd /var/www/html

PORT="${PORT:-8080}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p conf/production data log env-production

for file in index.php .htaccess web.config; do
	if [ ! -e "conf/$file" ] && [ -e "/opt/itop-seed-conf/$file" ]; then
		cp -a "/opt/itop-seed-conf/$file" "conf/$file"
	fi
done

if [ -n "${ITOP_CONFIG_B64:-}" ]; then
	printf '%s' "$ITOP_CONFIG_B64" | base64 -d > conf/production/config-itop.php
	php docker/apply-railway-config.php
fi

chown -R www-data:www-data conf data log env-production
find conf -type d -exec chmod 750 {} \;
find conf -type f -exec chmod 640 {} \;

if [ ! -f env-production/autoload.php ] && [ -f conf/production/config-itop.php ]; then
	echo "Compiling the iTop production environment"
	su -s /bin/sh www-data -c "php setup/unattended-install/unattended-install.php --use_itop_config --installation_xml=datamodels/2.x/installation.xml --param-file=docker/railway-upgrade.xml"
	chown -R www-data:www-data env-production data log conf
fi

a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true

exec apache2-foreground

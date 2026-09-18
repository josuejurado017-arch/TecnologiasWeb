#!/usr/bin/env bash

set -euo pipefail

if [[ ${EUID} -ne 0 ]]; then
    echo "Ejecute este instalador con sudo." >&2
    exit 1
fi

source_root="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
target_root="/var/www/html/TecnologiasWeb"
site_config="/etc/apache2/sites-available/tutorias.local.conf"

for required in php includes deploy/apache/tecnologiasweb.conf .env; do
    if [[ ! -e "${source_root}/${required}" ]]; then
        echo "Falta ${source_root}/${required}." >&2
        exit 1
    fi
done

source_root="$(realpath "${source_root}")"
if [[ -e "${target_root}" ]]; then
    target_root="$(realpath "${target_root}")"
else
    install -d -o root -g www-data -m 0750 "${target_root}"
    target_root="$(realpath "${target_root}")"
fi
if [[ "${source_root}" != "${target_root}" ]]; then
    rsync -a --delete \
        --exclude='.git/' \
        --exclude='.env' \
        "${source_root}/" "${target_root}/"
    install -o root -g www-data -m 0640 "${source_root}/.env" "${target_root}/.env"
else
    chgrp -R www-data "${target_root}"
fi
sed -i -E \
    -e 's|^APP_ENV=.*$|APP_ENV=production|' \
    -e 's|^APP_URL=.*$|APP_URL=http://tutorias.local|' \
    -e 's|^DB_HOST=.*$|DB_HOST=127.0.0.1|' \
    -e 's|^DB_PORT=.*$|DB_PORT=3306|' \
    "${target_root}/.env"

if [[ "${source_root}" != "${target_root}" ]]; then
    chown -R root:www-data "${target_root}"
    find "${target_root}" -type d -exec chmod 0750 {} +
    find "${target_root}" -type f -exec chmod 0640 {} +
fi

install -o root -g root -m 0644 \
    "${source_root}/deploy/apache/tecnologiasweb.conf" \
    "${site_config}"
printf 'ServerName tutorias.local\n' > /etc/apache2/conf-available/servername.conf

php_version="$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')"
php_apache_conf="/etc/php/${php_version}/apache2/conf.d"
if [[ -d "${php_apache_conf}" ]]; then
    printf 'pcre.jit=0\n' > "${php_apache_conf}/99-tutorias.ini"
fi

a2enmod rewrite >/dev/null
a2enconf servername >/dev/null
a2ensite tutorias.local >/dev/null
a2dissite 000-default >/dev/null 2>&1 || true

apache2ctl configtest
systemctl reload apache2

echo "TecnologiasWeb publicado en http://tutorias.local/"

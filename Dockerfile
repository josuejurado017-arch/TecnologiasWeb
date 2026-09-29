# Imagen de la aplicacion: Apache + PHP 8.2 con PDO MySQL.
# La base de datos vive en el servicio "db" definido en compose.yaml.
FROM php:8.2-apache

# zip: exportaciones a Excel (.xlsx, includes/Xlsx.php).
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite \
    && rm -f /etc/apache2/sites-enabled/000-default.conf

COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/99-tutorias.ini
COPY deploy/docker/apache-vhost.conf /etc/apache2/sites-available/tutorias.conf
RUN a2ensite tutorias \
    && printf 'ServerName tutoriasupds.local\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

WORKDIR /var/www/html/TecnologiasWeb
COPY . .

# Apache solo necesita leer el codigo. La unica carpeta con escritura es storage/, fuera
# de la raiz publica: guarda los documentos de solicitud de Modalidades de Grado (db/049).
# compose.yaml la monta como volumen para que sobreviva a reconstruir la imagen.
RUN chown -R root:www-data /var/www/html/TecnologiasWeb \
    && find /var/www/html/TecnologiasWeb -type d -exec chmod 0750 {} + \
    && find /var/www/html/TecnologiasWeb -type f -exec chmod 0640 {} + \
    && install -d -o www-data -g www-data -m 0750 /var/www/html/TecnologiasWeb/storage/mg_solicitudes

EXPOSE 80

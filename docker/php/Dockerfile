# PHP 8.3 con lo justo que pide el proyecto.
#
# Se usa la imagen `cli` y no `apache`/`fpm` porque en desarrollo el servidor lo
# levanta `artisan serve`, igual que fuera del contenedor. Un nginx acá sería
# una pieza más para mantener que no aporta nada.
FROM php:8.3-cli

# pdo_mysql es la única extensión que hay que agregar: mbstring y openssl ya
# vienen compiladas en la imagen oficial. `mariadb-client` no lo usa la app,
# sirve para entrar a la base a mano desde el contenedor.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip mariadb-client \
    && docker-php-ext-install pdo_mysql zip \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# La zona horaria del contenedor, para que PHP y la base cuenten lo mismo.
ENV TZ=America/Asuncion
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

WORKDIR /app

COPY entrada.sh /usr/local/bin/entrada
# Si el archivo se copió desde Windows con fin de línea CRLF, el contenedor
# contesta «bad interpreter» y no hay forma de adivinar por qué. Se limpia acá.
RUN sed -i 's/\r$//' /usr/local/bin/entrada && chmod +x /usr/local/bin/entrada

EXPOSE 8000
ENTRYPOINT ["entrada"]

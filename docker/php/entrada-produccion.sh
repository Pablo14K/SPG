#!/bin/sh
# Lo que hay que hacer antes de servir, cada vez que arranca el contenedor del
# SERVIDOR. Todo es idempotente: si ya está hecho, no lo rehace.
#
# La diferencia con `entrada.sh` —el de desarrollo— es que acá NO se inventa
# nada: si falta el `.env` o la APP_KEY, el contenedor se apaga en vez de
# arrancar a medias. En desarrollo arrancar igual es una comodidad; en el
# servidor, un sistema andando con la configuración equivocada es peor que un
# sistema que no arrancó, porque nadie se entera.
set -e

cd /app

falta() {
    echo "== SPG: $1"
    echo "== El contenedor se apaga a propósito: es preferible a servir mal."
    exit 1
}

# ---------------------------------------------------------------------------
# 1. La configuración tiene que estar, no se genera sola.
# ---------------------------------------------------------------------------
[ -f .env ] || falta "no hay .env montado (docker/php/env.produccion)."

# La APP_KEY va en `secretos.env`, no en el .env versionado. Se genera UNA vez
# con `openssl rand -base64 32` y se pega ahí; si se cambia después,
# las sesiones abiertas y todo lo cifrado dejan de leerse.
[ -n "$APP_KEY" ] || falta "APP_KEY vacía. Generala con:
       echo \"APP_KEY=base64:\$(openssl rand -base64 32)\"
   y pegala en docker/php/secretos.env"

[ -n "$DB_PASSWORD" ] || falta "DB_PASSWORD vacía. Está en docker/php/secretos.env."

# ---------------------------------------------------------------------------
# 2. Las dependencias, SIN las de desarrollo.
# ---------------------------------------------------------------------------
# `--no-dev` deja afuera PHPUnit y compañía, que en el servidor no hacen falta
# y son código de más expuesto. `--optimize-autoloader` arma el mapa de clases
# de una vez en lugar de resolverlas por convención en cada petición.
if [ ! -f vendor/autoload.php ] || [ ! -d vendor/dompdf/dompdf ]; then
    echo "== SPG: instalando dependencias de producción (la primera vez tarda) =="
    composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
fi

# ---------------------------------------------------------------------------
# 3. Lo que php-fpm necesita poder escribir.
# ---------------------------------------------------------------------------
# Los hijos de php-fpm corren como `www-data` y el proyecto viene montado del
# host con otro dueño. Sin esto, la primera sesión y el primer log fallan con
# «Permission denied», que en pantalla se ve como un 500 sin explicación.
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache \
         storage/logs storage/app/sifen bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# ---------------------------------------------------------------------------
# 4. Las cachés del framework.
# ---------------------------------------------------------------------------
# El orden importa: primero limpiar, porque una caché de la corrida anterior
# puede tener la URL o las credenciales viejas, y `optimize` no la pisa si no
# se la borra antes.
php artisan config:clear >/dev/null 2>&1 || true
php artisan optimize

# ---------------------------------------------------------------------------
# 5. La revisión de siempre, en su versión de servidor.
# ---------------------------------------------------------------------------
# Comprueba además APP_DEBUG, APP_ENV, APP_URL, la zona horaria, que no haya
# .env ni .sql dentro de public/ y que las rutinas respondan de verdad.
#
# NO frena el arranque: informa y sigue. Si frenara, una observación menor
# —un correo sin configurar— dejaría al salón sin sistema.
php artisan spg:diagnostico --produccion \
    || echo "== SPG: el diagnóstico marcó cosas para revisar (ver arriba) =="

echo "== SPG: php-fpm listo. El HTTP lo atiende Caddy. =="

exec php-fpm

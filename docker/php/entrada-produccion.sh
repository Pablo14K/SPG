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
# El `.env` viaja DENTRO de la imagen (docker/php/env.produccion, copiado en
# el Dockerfile). Antes se montaba del host, y en el panel de Hostinger eso
# resolvía a una ruta vacía: Docker la creaba como carpeta y el contenedor no
# arrancaba con «not a directory».
[ -f .env ] || falta "falta /app/.env — la imagen se construyó mal."

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
# Normalmente ya está: el Dockerfile las instala al construir, así que el
# arranque no depende de que Packagist conteste. Esto es la red por si la
# imagen se armó a mano.
if [ ! -f vendor/autoload.php ]; then
    echo "== SPG: vendor/ no está en la imagen, instalando (esto no debería pasar) =="
    composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
fi

# ---------------------------------------------------------------------------
# 3. Lo que php-fpm necesita poder escribir.
# ---------------------------------------------------------------------------
# Los hijos de php-fpm corren como `www-data`. `storage` y las dos carpetas de
# imágenes son VOLÚMENES: al crearse toman lo que traiga la imagen, pero uno
# viejo puede no tener una subcarpeta agregada después. Sin esto, la primera
# sesión o la primera foto que se sube fallan con «Permission denied», que en
# pantalla se ve como un 500 sin explicación.
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache \
         storage/logs storage/app/sifen bootstrap/cache \
         public/assets/servicios public/assets/logo
chown -R www-data:www-data storage bootstrap/cache \
         public/assets/servicios public/assets/logo

# ---------------------------------------------------------------------------
# 4. La base: si está vacía, se importa sola.
# ---------------------------------------------------------------------------
# **MariaDB corre sus guiones de arranque UNA sola vez, con el volumen vacío.**
# Eso deja un agujero que costó una tarde: si esa primera vez algo falló —el
# guion no estaba, el `.sql` tampoco— el volumen queda inicializado y la
# importación **no vuelve a intentarse nunca**. El sistema arranca contra una
# base sin una sola tabla, la pantalla de ingreso se dibuja igual (la marca cae
# en APP_NAME por su `catch`) y el 500 aparece recién al enviar el formulario.
#
# Por eso el importador vive acá y no en el contenedor de la base: esto corre
# en **cada arranque** y se puede preguntar cómo está la base antes de decidir.
#
# **Es idempotente y no destruye nada**: sólo importa si no hay prácticamente
# tablas. Con la base cargada no toca absolutamente nada.
# **Ojo con `printenv` acá: devuelve 1 cuando la variable no existe.** Con
# `set -e` puesto, eso mata el arranque *dentro de la sustitución de comandos*
# y el contenedor sale con 1 **sin imprimir una sola línea** — un log vacío, que
# es la peor pista posible. Por eso se usa la expansión indirecta, que nunca
# falla.
valor() {
    eval "v=\${$1-}"
    # Si no vino como variable del contenedor, sale del .env — el mismo orden
    # de precedencia que usa Laravel, porque Dotenv arranca inmutable.
    # `tr -d '\r"'` saca el retorno de carro (si el archivo viajó desde Windows)
    # y las comillas; `\r` es un escape que tr entiende, no la letra r.
    [ -n "$v" ] || v=$(sed -n "s/^$1=//p" /app/.env | head -1 | tr -d '\r"')
    printf '%s' "$v"
}

BD_HOST=$(valor DB_HOST); BD_NOMBRE=$(valor DB_DATABASE)
BD_USUARIO=$(valor DB_USERNAME); BD_CLAVE=$(valor DB_PASSWORD)
SQL="/app/basededatos/peluqueria_bd(base).sql"

# **`--skip-ssl` no es opcional acá.** El cliente que trae la imagen es MariaDB
# 11.8 y **exige TLS por defecto**; el servidor es 10.4 y no lo tiene
# configurado, así que sin esto toda consulta muere con
# «ERROR 2026: SSL is required, but the server does not support it». La
# conexión va por la red interna de Docker, que no sale a ninguna parte.
mysql_() { mysql --skip-ssl -h"$BD_HOST" -u"$BD_USUARIO" -p"$BD_CLAVE" --default-character-set=utf8mb4 "$@"; }

# **Primero se comprueba que la base CONTESTE, y esto es lo que vuelve seguro
# todo lo de abajo.** Sin esta línea, un fallo de conexión se veía idéntico a
# una base vacía —la consulta fallaba, el `|| echo 0` la convertía en cero— y el
# arranque se ponía a importar un `.sql` que empieza con `DROP TABLE IF EXISTS`.
# Nunca hay que confundir «no pude preguntar» con «no hay nada».
mysql_ -N -B -e "SELECT 1;" >/dev/null 2>&1 \
    || falta "la base no contesta en '$BD_HOST' con el usuario '$BD_USUARIO'.
   Probá:  docker compose -f docker-compose.produccion.yml exec bd mysql -uroot -p"

tablas=$(mysql_ -N -B -e "SELECT COUNT(*) FROM information_schema.tables
                           WHERE table_schema = '$BD_NOMBRE';")
# Sólo dígitos: si la consulta devolvió un aviso en vez de un número, `[ -lt ]`
# aborta el arranque con un error de shell que no dice nada.
tablas=$(printf '%s' "$tablas" | tr -dc '0-9')

# El umbral es 10 y no 0 a propósito: una base a medio importar tampoco sirve,
# y el esquema completo tiene 80 tablas. Con la base cargada esto da 97 —80
# tablas más 17 vistas— y no se hace nada.
if [ "${tablas:-0}" -lt 10 ]; then
    echo "== SPG: la base '$BD_NOMBRE' está vacía ($tablas tablas): importando =="

    if [ ! -f "$SQL" ]; then
        falta "no encuentro $SQL — la imagen se construyó mal."
    fi

    mysql_ -e "CREATE DATABASE IF NOT EXISTS \`$BD_NOMBRE\`
               CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    # `--default-character-set=utf8mb4` va en `mysql_` y NO es opcional: sin él
    # los acentos entran dobles y «Coloración» queda como «Coloraci├│n».
    mysql_ "$BD_NOMBRE" < "$SQL"

    rutinas=$(mysql_ -N -B -e "SELECT COUNT(*) FROM information_schema.routines
                                WHERE routine_schema = '$BD_NOMBRE';" 2>/dev/null || echo 0)
    echo "== SPG: importada · $(mysql_ -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$BD_NOMBRE';") tablas y vistas · $rutinas rutinas =="

    # **Las 60 rutinas SON la lógica de negocio.** Un import cortado a la mitad
    # deja el sistema andando y fallando de a poco, que es lo peor que puede
    # pasar acá. Se avisa fuerte, pero no se frena: con las tablas puestas se
    # puede entrar a mirar qué pasó.
    [ "${rutinas:-0}" -ge 60 ] || echo "== SPG: OJO, esperaba 60 rutinas y hay $rutinas: el import quedó a medias =="
fi

# ---------------------------------------------------------------------------
# 5. Las cachés del framework.
# ---------------------------------------------------------------------------
# El orden importa: primero limpiar, porque una caché de la corrida anterior
# puede tener la URL o las credenciales viejas, y `optimize` no la pisa si no
# se la borra antes.
php artisan config:clear >/dev/null 2>&1 || true
php artisan optimize

# ---------------------------------------------------------------------------
# 6. La revisión de siempre, en su versión de servidor.
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

#!/bin/sh
# Respaldo de la base, para correr DESDE EL SERVIDOR (no adentro de un
# contenedor). Se agenda en el cron del host:
#
#   0 3 * * * /docker/spg/docker/respaldo.sh >> /var/log/spg-respaldo.log 2>&1
#
# Por qué existe, y por qué el cron es del host y no del compose: los datos del
# salón viven en un **volumen de Docker**, y un `docker compose down -v` mal
# tipeado los borra sin preguntar. El volumen no es un respaldo — es el mismo
# disco. Esto es la diferencia entre perder un día y perder el año.
set -e

# **Se habla con el contenedor por su NOMBRE, no por el compose.** Antes esto
# hacía `cd` al proyecto y llamaba a `docker compose exec`, y eso ataba el
# respaldo a dos cosas que en el servidor no se cumplen: que el guion se corra
# desde la carpeta del proyecto, y que el nombre del proyecto de Compose sea el
# que Compose deduce del directorio —el panel de Hostinger le pone el suyo—.
# `container_name: spg_bd` es fijo, así que esto anda desde cualquier lado.
CONTENEDOR=${SPG_BD:-spg_bd}

# Dónde caen los volcados. Se puede cambiar con SPG_RESPALDOS.
DESTINO=${SPG_RESPALDOS:-/var/respaldos/spg}
FECHA=$(date +%Y-%m-%d_%H%M)
ARCHIVO="$DESTINO/peluqueria_bd_$FECHA.sql"

docker inspect "$CONTENEDOR" >/dev/null 2>&1 || {
    echo "No encuentro el contenedor '$CONTENEDOR'. ¿Está levantado el sistema?"
    echo "Mirá:  docker ps --format '{{.Names}}'"
    exit 1
}

mkdir -p "$DESTINO"

# `--routines --triggers --events` no es opcional en este proyecto: la lógica de
# negocio son 22 procedimientos, 41 funciones y 17 disparadores. Un volcado sin
# eso restaura las tablas y deja el sistema sin funcionar.
#
# `--single-transaction` para que el volcado sea coherente sin bloquear al
# salón mientras corre. La contraseña sale de la variable del propio contenedor,
# así que no queda escrita en el cron ni en el historial del shell.
docker exec "$CONTENEDOR" sh -c \
    'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" \
        --routines --triggers --events --single-transaction \
        --default-character-set=utf8mb4 peluqueria_bd' > "$ARCHIVO"

gzip -f "$ARCHIVO"
echo "$(date "+%Y-%m-%d %H:%M") · respaldo: ${ARCHIVO}.gz ($(du -h "${ARCHIVO}.gz" | cut -f1))"

# Un respaldo que llena el disco deja al sistema sin poder escribir, así que se
# conservan los últimos 14 días. Catorce y no siete: un error de datos puede
# tardar más de una semana en notarse.
find "$DESTINO" -name 'peluqueria_bd_*.sql.gz' -mtime +14 -delete

# ---------------------------------------------------------------------------
# **Esto todavía NO es un respaldo.** Un archivo guardado en el mismo servidor
# que la base se pierde con el servidor. Bajarlo a otra máquina, una vez por
# semana como mínimo:
#
#   scp root@servidor:/var/respaldos/spg/*.gz .
#
# Y probar que restaura, aunque sea una vez: un respaldo que nunca se restauró
# es una suposición, no un respaldo.
# ---------------------------------------------------------------------------

#!/bin/sh
# Respaldo diario de la base, para correr DESDE EL SERVIDOR (no adentro de un
# contenedor). Se agenda en el cron del host:
#
#   0 3 * * * /ruta/al/proyecto/docker/respaldo.sh >> /ruta/al/proyecto/respaldos/respaldo.log 2>&1
#
# Por qué existe, y por qué el cron es del host y no del compose: los datos del
# salón viven en un **volumen de Docker**, y un `docker compose down -v` mal
# tipeado los borra sin preguntar. El volumen no es un respaldo — es el mismo
# disco. Esto es la diferencia entre perder un día y perder el año.
set -e

cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.produccion.yml"
DESTINO="respaldos"
FECHA=$(date +%Y-%m-%d_%H%M)
ARCHIVO="$DESTINO/peluqueria_bd_$FECHA.sql"

mkdir -p "$DESTINO"

# `--routines --triggers --events` no es opcional en este proyecto: la lógica de
# negocio son 21 procedimientos, 39 funciones y 17 disparadores. Un volcado sin
# eso restaura las tablas y deja el sistema sin funcionar.
#
# `--single-transaction` para que el volcado sea coherente sin bloquear al
# salón mientras corre.
$COMPOSE exec -T bd sh -c \
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
#   scp usuario@servidor:/ruta/al/proyecto/respaldos/*.gz .
#
# Y probar que restaura, aunque sea una vez: un respaldo que nunca se restauró
# es una suposición, no un respaldo.
# ---------------------------------------------------------------------------

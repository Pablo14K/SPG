#!/bin/sh
# Motor de la simulación: 90 días, 5 momentos por día, cada uno un proceso
# aparte con su propio reloj falseado.
#
#   sh _sim/correr.sh <dia_desde> <dia_hasta>

set -u
DESDE=${1:-1}
HASTA=${2:-90}
BASE=2026-08-15          # día 1
LD=/usr/lib/x86_64-linux-gnu/faketime/libfaketime.so.1

export APP_ENV=testing
export APP_DEBUG=false
export DB_DATABASE=peluqueria_sim
export SESSION_DRIVER=file
export MAIL_MAILER=log
export LOG_LEVEL=debug
# Cada «momento» es un proceso con su propio reloj falseado. Con la caché en
# archivo, el limitador de peticiones guarda ventanas con la hora de un momento
# y las lee desde otro: un momento de las 16:00 veía el contador que dejó el de
# las 19:15 del mismo día y contestaba 429. Con `array` cada momento arranca
# limpio, que es lo que pasa de verdad cuando esos minutos están separados.
export CACHE_STORE=array

cd /app

corre() {   # corre <dia> <fase> <hora>
  FECHA=$(date -d "$BASE + $(($1-1)) day" +%Y-%m-%d)
  LD_PRELOAD=$LD FAKETIME="@$FECHA $3" php _sim/momento.php "$1" "$2" >> _sim/log/salida.txt 2>&1 \
    || echo "FALLO d$1 $2" >> _sim/log/salida.txt
}

D=$DESDE
while [ "$D" -le "$HASTA" ]; do
  if [ "$D" -eq 1 ]; then corre 1 init 07:00:00; fi
  corre "$D" apertura 07:40:00
  corre "$D" manana   12:45:00
  corre "$D" mediodia 13:05:00
  # Escenarios especiales: van EN ORDEN CRONOLÓGICO dentro del día
  case $D in
    2|19|47|76)  corre "$D" permisos     15:40:00 ;;
  esac
  case $D in
    3|11|23|38|52|67|81) corre "$D" anomalias 16:00:00 ;;
  esac
  case $D in
    7|29|58|85) corre "$D" concurrencia 16:20:00 ;;
  esac
  case $D in
    5|17|33|49|64|79|88) corre "$D" portal    16:40:00 ;;
  esac
  corre "$D" tarde    18:58:00
  corre "$D" cierre   19:15:00
  echo "== dia $D listo ==" >> _sim/log/salida.txt
  D=$((D+1))
done
echo "SIMULACION TERMINADA $DESDE..$HASTA" >> _sim/log/salida.txt

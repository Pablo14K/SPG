#!/bin/sh
# Motor de la simulación intensiva de 1 mes (30 días) contra la versión 7.36.1.
#
# Cada «momento» es un proceso aparte con su propio reloj falseado, así que la
# cronología es real: nada se registra antes de existir.
#
#   sh _sim30/correr.sh <dia_desde> <dia_hasta>

set -u
DESDE=${1:-1}
HASTA=${2:-30}
BASE=2026-09-01          # día 1
LD=/usr/lib/x86_64-linux-gnu/faketime/libfaketime.so.1

export APP_ENV=testing
export APP_DEBUG=false
export DB_DATABASE=peluqueria_sim
export SESSION_DRIVER=file
export MAIL_MAILER=log
export LOG_LEVEL=warning
# Con la caché en archivo, el limitador de peticiones guarda ventanas con la
# hora de un momento y las lee desde otro: un momento de las 16:00 veía el
# contador que dejó el de las 19:15 y contestaba 429. Con `array` cada momento
# arranca limpio, que es lo que pasa de verdad cuando esos minutos están
# separados en el tiempo.
export CACHE_STORE=array

cd /app

corre() {   # corre <dia> <fase> <hora>
  FECHA=$(date -d "$BASE + $(($1-1)) day" +%Y-%m-%d)
  LD_PRELOAD=$LD FAKETIME="@$FECHA $3" php _sim30/momento.php "$1" "$2" >> _sim30/log/salida.txt 2>&1 \
    || echo "FALLO d$1 $2" >> _sim30/log/salida.txt
}

D=$DESDE
while [ "$D" -le "$HASTA" ]; do
  if [ "$D" -eq 1 ]; then corre 1 init 07:00:00; fi
  corre "$D" apertura 07:40:00
  corre "$D" manana   12:45:00
  corre "$D" mediodia 13:05:00
  # **Multisucursal**: el segundo local se inaugura el día 2 y desde ahí opera
  # todos los días. Va después del mediodía para que las citas del local nuevo
  # convivan con las de la casa central dentro del mismo día.
  corre "$D" sucursales 14:20:00
  # Escenarios especiales, EN ORDEN CRONOLÓGICO dentro del día.
  # Con 30 días se comprimen: cada escenario corre más veces que en la de 60,
  # que es de lo que se trata la alta densidad.
  case $D in
    2|16|28)                 corre "$D" permisos     15:30:00 ;;
  esac
  case $D in
    3|13|24)                 corre "$D" anomalias    15:50:00 ;;
  esac
  case $D in
    7|19|27)                 corre "$D" concurrencia 16:10:00 ;;
  esac
  case $D in
    9|22)                    corre "$D" canjes       16:25:00 ;;
  esac
  case $D in
    18)                       corre "$D" bajas        16:40:00 ;;
  esac
  case $D in
    5|15|26)                 corre "$D" portal       16:55:00 ;;
  esac
  corre "$D" tarde    18:58:00
  corre "$D" cierre   19:15:00
  echo "== dia $D listo ==" >> _sim30/log/salida.txt
  D=$((D+1))
done
echo "SIMULACION TERMINADA $DESDE..$HASTA" >> _sim30/log/salida.txt

# MariaDB 10.4 con el volcado que se instala ADENTRO.
#
# El contexto es la raíz del proyecto. Antes esto eran dos montajes de host y en
# el panel de Hostinger resolvían a `/docker/spg`, así que MariaDB arrancaba
# **healthy con las bases vacías** — peor que fallar, porque no lo dice nadie.
FROM mariadb:10.4

# Sólo la base que se entrega: catálogo, un local y las dos cuentas del
# instalador, con cero operación. **El mes simulado del QA no viaja al
# servidor**: son clientas y facturas inventadas, y ese volcado lleva adentro
# dos correos reales que recibirían recordatorios de citas que no existen.
#
# Queda en /sql para poder importarlo a mano si hace falta.
COPY ["basededatos/peluqueria_bd(base).sql", "/sql/"]

# **No hay guion en /docker-entrypoint-initdb.d, y es a propósito.** MariaDB lo
# correría UNA sola vez, con el volumen vacío: si esa vez algo falla, la
# importación no se reintenta nunca. Quien importa es el contenedor de la
# aplicación, en cada arranque y sólo si la base está vacía. Ver
# `docker/php/entrada-produccion.sh`, paso 4.

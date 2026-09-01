# MariaDB 10.4 con los .sql y el guion de importación ADENTRO.
#
# El contexto es la raíz del proyecto. Antes esto eran dos montajes de host
# —`./basededatos:/sql` y `./docker/bd:/docker-entrypoint-initdb.d`— y en el
# panel de Hostinger resolvían a `/docker/spg`, que está vacío: MariaDB
# arrancaba **healthy con las bases vacías**, que es peor que fallar, porque no
# lo dice nadie. Ver DESPLIEGUE.md, punto 2.
FROM mariadb:10.4

# Los volcados que se importan. `peluqueria_bd(base).sql` es la base que se
# entrega —catálogo y cuentas, cero operación— y `1mes_simulacion.sql` es el mes
# del QA. Cuál usa la aplicación lo decide DB_DATABASE en env.produccion.
COPY basededatos/ /sql/

# MariaDB corre esto UNA sola vez, cuando el volumen está vacío.
COPY docker/bd/10-importar.sh /docker-entrypoint-initdb.d/

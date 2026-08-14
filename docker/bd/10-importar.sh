#!/bin/bash
# Importa las dos bases del proyecto. MariaDB corre esto UNA sola vez, cuando
# el volumen está vacío: si querés repetirlo, `docker compose down -v`.
#
# Son dos y cada una tiene su papel:
#
#   peluqueria_bd    la que se ENTREGA: esquema al día, sin operación
#   peluqueria_test  la de trabajo, con el mes simulado del QA
#
# Ninguno de los dos .sql lleva CREATE DATABASE ni USE adentro, así que respetan
# el nombre que se les pasa acá y no se pisan entre sí.

set -e

BASE="/sql/peluqueria_bd(base).sql"
SIMULACION="/sql/1mes_simulacion.sql"
DEMO="/sql/datos_demo.sql"

ejecutar() { mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" "$@"; }

crear() {
    ejecutar -e "CREATE DATABASE IF NOT EXISTS \`$1\`
                 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
}

importar() {
    local base="$1" archivo="$2"

    if [ ! -f "$archivo" ]; then
        echo "  !! No encuentro $archivo"
        echo "     El proyecto tiene que estar dentro de la carpeta Sistema_Gestion_Peluqueria,"
        echo "     que es de donde salen los .sql. La base '$base' queda vacía."
        return 0
    fi

    echo "  -> importando $base desde $(basename "$archivo")"
    ejecutar "$base" < "$archivo"

    local rutinas
    rutinas=$(ejecutar -N -B -e "SELECT COUNT(*) FROM information_schema.routines
                                  WHERE routine_schema = '$base';")
    echo "     listo: $rutinas rutinas"
}

echo "== SPG: preparando las bases =="

crear peluqueria_bd
crear peluqueria_test

importar peluqueria_bd   "$BASE"
importar peluqueria_test "$SIMULACION"

# Los datos de arranque van SÓLO en la base que se entrega: servicios,
# productos, proveedores, profesionales con turno y los timbrados. Sin esto una
# cuenta recién creada no puede hacer nada —no hay qué agendar ni con quién—,
# así que el sistema se puede probar apenas se instala.
#
# `peluqueria_test` no los necesita: ya viene con el mes simulado del QA.
if [ -f "$DEMO" ]; then
    echo "  -> cargando los datos de arranque en peluqueria_bd"
    ejecutar peluqueria_bd < "$DEMO"
fi

echo "== SPG: bases listas =="

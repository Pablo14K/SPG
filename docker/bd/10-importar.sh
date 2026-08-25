#!/bin/bash
# Importa las dos bases del proyecto. MariaDB corre esto UNA sola vez, cuando
# el volumen está vacío: si querés repetirlo, `docker compose down -v`.
#
# Son dos y cada una tiene su papel. La aplicación Docker apunta a
# `peluqueria_test`, que es la copia cargada que viaja dentro del ZIP; la otra
# queda disponible para instalar un salón desde cero:
#
#   peluqueria_bd    esquema al día, catálogo demo y cuentas de acceso
#   peluqueria_test  operación cargada: 172 citas, 63 facturas y 33 clientas
#
# Ninguno de los dos .sql lleva CREATE DATABASE ni USE adentro, así que respetan
# el nombre que se les pasa acá y no se pisan entre sí.

set -e

BASE="/sql/peluqueria_bd(base).sql"
SIMULACION="/sql/1mes_simulacion.sql"

# `--default-character-set=utf8mb4` NO es opcional: sin él los acentos entran
# rotos («Coloración» queda como «Coloraci├│n») y encima no coinciden con los
# del archivo, así que un INSERT IGNORE los vuelve a insertar.
ejecutar() { mysql --protocol=socket --default-character-set=utf8mb4 -uroot -p"${MYSQL_ROOT_PASSWORD}" "$@"; }

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


echo "== SPG: bases listas =="

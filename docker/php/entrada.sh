#!/bin/sh
# Lo que hay que hacer antes de servir, cada vez que arranca el contenedor.
# Todo es idempotente: si ya está hecho, no lo rehace.
set -e

cd /app

# 1. Las dependencias. El .env está en .gitignore, así que quien clona el
#    repositorio no lo tiene y `vendor/` tampoco.
# Se comprueba también Dompdf: `vendor/` vive en un volumen propio y puede
# haber sido creado antes de agregar una dependencia nueva al proyecto.
if [ ! -f vendor/autoload.php ] || [ ! -d vendor/dompdf/dompdf ]; then
    echo "== SPG: instalando dependencias (la primera vez tarda) =="
    composer install --no-interaction --prefer-dist
fi

# 2. La configuración.
if [ ! -f .env ]; then
    echo "== SPG: creando .env desde .env.example =="
    cp .env.example .env
fi

# APP_KEY vacía significa que nunca se generó: sin ella la sesión no funciona.
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# 3. Que no quede una caché de la corrida anterior con otras rutas.
php artisan config:clear >/dev/null 2>&1 || true

# 4. La revisión de siempre: conexión, relojes, rutinas, CHECKs, DEFINER.
#    No frena el arranque si algo falla — informa y sigue, así se puede entrar
#    al contenedor a ver qué pasó.
php artisan spg:diagnostico || echo "== SPG: el diagnóstico marcó cosas para revisar (ver arriba) =="

echo "== SPG: http://localhost:8000  ·  admin/admin123  ·  cliente/cliente123 =="

exec php artisan serve --host=0.0.0.0 --port=8000

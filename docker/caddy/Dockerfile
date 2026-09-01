# Caddy con el Caddyfile y los archivos estáticos ADENTRO.
#
# Caddy sirve el CSS, el JS y las fotos directamente, sin molestar a PHP, así
# que necesita ver `public/`. Antes lo montaba del host; en el panel eso
# resolvía a una carpeta vacía y el sitio quedaba sin estilos. Ver
# DESPLIEGUE.md, punto 2.
FROM caddy:2-alpine

COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile

# Sólo la carpeta pública: es lo único que Caddy tiene derecho a leer. El resto
# del proyecto —el `.env`, los `.sql`, la aplicación— no entra a esta imagen.
COPY public/ /app/public/

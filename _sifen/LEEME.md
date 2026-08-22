# Automatizador SIFEN — copia versionada

**Este proyecto es de terceros y vive acá por una sola razón: el repositorio del
SPG es el respaldo del TCC**, y hasta la 7.60.0 una parte del sistema que
funciona —la que genera el KuDE y manda el comprobante por correo— era una
carpeta suelta fuera de git. Lo que se tocara ahí no quedaba en ningún historial.

## Qué NO está acá, y por qué

| Falta | Motivo |
|---|---|
| `.env` | lleva la contraseña de aplicación de Gmail y el token de la API |
| `certs/` | un `.pem` en un repositorio es un `.pem` publicado, aunque sea de demostración |
| `logs/`, `salida/`, `procesados/` | 73 MB de corridas que envejecen al día siguiente |

Para levantarlo hace falta copiar `.env.example` a `.env` y completar esas
claves. `docker compose up` lo arranca solo: es un servicio más.

## Qué se le tocó desde el SPG

La **7.52.0** modificó cuatro archivos para que el comprobante salga a nombre
del salón y no del archivo de ejemplo:

- `src/TxtParser.php` — entiende el registro `EMI|` y el tipo de transacción
- `src/InvoiceFactory.php` — el emisor del `.txt` le gana al `.env`
- `motor/Service/InvoiceMapper.php` — lleva el nombre y la ciudad del local
- `motor/Service/KudeService.php` — la paleta del salón y los rótulos

**El SPG sigue hablándole sólo por HTTP.** No se copió código de un lado al
otro: esto es una copia para que exista respaldo, no una fusión.

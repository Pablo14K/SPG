# SPG — Sistema de Gestión para Peluquería

Sistema web de gestión para una peluquería de Luque, Paraguay. TCC de Ingeniería en
Informática. **Laravel 13 + MariaDB.**

---

## Lo primero que hay que entender: la lógica vive en la base

No es un Laravel normal. La base `peluqueria_bd` tiene **20 procedimientos, 30 funciones,
17 triggers, 17 vistas y 57 restricciones `CHECK`**, y ahí es donde están las reglas del
negocio. **PHP las consume, no las reimplementa.**

Antes de escribir un cálculo, buscá si ya existe la rutina:

| Necesitás | Usá |
|---|---|
| Stock de un producto | `fn_producto_stock(id)` — nunca se guarda, se suma `movimiento_inventario` |
| Agendar una cita | `sp_agendar_cita(...)`, siempre **dentro de una transacción** |
| Emitir un comprobante | `sp_emitir_factura(...)` |
| Saldo de caja | `fn_caja_saldo(id)` — solo cuenta el efectivo del cajón |
| La hora del reloj | `ahora_bd()`, **nunca `date()`** |

Tres consecuencias prácticas:

- **No se corren migraciones de Laravel** contra esta base. El esquema viene del `.sql`.
- **Nada de `RefreshDatabase` en las pruebas**: borraría el esquema con todas sus rutinas.
- **Sesiones, caché y colas van a archivo**, no a `database`, o Laravel crearía sus tablas
  dentro de la base que se entrega al salón.

El detalle completo está en `CLAUDE.md`, que es la documentación del proyecto.

---

## Cómo se levanta

**Con Docker, y sólo con Docker.** Pesa más al instalar (pide WSL2 y ~2 GB de RAM), pero
**fija MariaDB 10.4**, importa las dos bases solo y clava la zona horaria — que son las tres
cosas que más se rompen al armar el entorno en otra computadora, y las tres que hacen que
«en mi máquina anda» deje de significar algo.

> Hasta la 7.85.1 había una segunda forma, a mano con XAMPP. Se retiró: MariaDB tiene que ser
> **10.4** —las 78 `CHECK` y las 60 rutinas están escritas para ese motor— y el Apache de
> XAMPP trae **PHP 8.2** cuando Laravel 13 pide 8.3, así que igual había que instalar un PHP
> aparte. Dos caminos para lo mismo, y uno de ellos con la versión del motor sin garantizar.

---

## Con Docker, un solo comando

Hace falta [Docker Desktop](https://www.docker.com/products/docker-desktop/) (en Windows pide
WSL2 y virtualización habilitada en el firmware). Después:

```bash
docker compose up
```

La primera vez tarda unos minutos: baja las imágenes, instala las dependencias e **importa
las dos bases**. Cuando termina, imprime la dirección y los usuarios.

http://localhost:8000 · `admin` / `admin123` · `cliente` / `cliente123`

| Comando | Qué hace |
|---|---|
| `docker compose up` | arranca |
| `docker compose down` | apaga, **conservando** las bases |
| `docker compose down -v` | apaga y **borra** las bases, para empezar de cero |
| `docker compose exec app php artisan test` | corre las 158 pruebas |
| `docker compose exec app php artisan spg:diagnostico` | la revisión del entorno |
| `docker compose exec bd mysql -uroot -proot peluqueria_bd` | entrar a la base |

### El Automatizador SIFEN

Son **tres** contenedores: la base, la aplicación y el **Automatizador SIFEN**, que es el que
declara las facturas electrónicas ante la DNIT y le manda el PDF por correo a la clienta.

Es un proyecto aparte y vive fuera de esta carpeta, así que el compose lo busca **como carpeta
hermana** (`../Sifen_version/sifen_final/sifen_automatizador`). Si lo tenés en otro lado:

```bash
SPG_SIFEN_PATH=/ruta/a/sifen_automatizador docker compose up
```

**Si no lo tenés, no pasa nada**: ese contenedor avisa y se apaga solo, y el resto del sistema
funciona igual. Las facturas electrónicas se emiten y quedan **pendientes de declarar**, para
reintentarlas desde el comprobante cuando el servicio esté.

### Si actualizaste el proyecto y algo revienta al entrar

**Las bases se importan UNA sola vez**, cuando el volumen de MariaDB está vacío. Así que si ya
habías levantado el proyecto antes y después bajaste una versión nueva, tenés el **código
nuevo contra la base vieja**: todo anda hasta que abrís una pantalla que usa algo que se
agregó, y ahí salta un error como

```
SQLSTATE[42S22]: Columna desconocida 'tema' en la lista de campos
```

Se arregla volviendo a importar, que borra las bases y las carga de nuevo:

```bash
docker compose down -v
docker compose up
```

**El `-v` es lo que importa**: sin él, `down` y `up` no reimportan nada. Y ojo, borra los datos
que hayas cargado a mano.

Para saber si es eso antes de tocar nada:

```bash
docker compose exec app php artisan spg:diagnostico
```

Compara la base contra `basededatos/peluqueria_bd(base).sql` y te dice qué falta.

### Con qué base trabaja el contenedor

**El contenedor crea e importa las dos**, y la aplicación usa la que diga una sola línea de
`docker/php/env.docker`:

| Valor | Qué se ve al entrar |
|---|---|
| `DB_DATABASE=peluqueria_test` | **lo que viene puesto hoy**: la copia cargada que viaja en el ZIP — 172 citas, 63 facturas, 33 clientas, cobros y asistencia |
| `DB_DATABASE=peluqueria_bd` | la base limpia de entrega — catálogo y cuentas para iniciar un salón desde cero |

> Son esos dos nombres y no hay un tercero: **`peluqueria_bd_test` no existe**. Es el error
> fácil, y engaña — la pantalla de ingreso sigue contestando 200 porque no toca la base hasta
> que apretás Ingresar, y recién ahí sale «Unknown database». `spg:diagnostico` lo dice en la
> primera línea.

Se cambia esa línea y se reinicia:

```bash
docker compose restart app
```

**No hace falta `down -v`** ni volver a importar: las dos bases ya están adentro, sólo cambia
a cuál se conecta la aplicación. El contenedor hace `config:clear` en cada arranque, así que
no queda una caché vieja pisando el cambio.

> **Para entregar un salón desde cero, cambiar a `peluqueria_bd`**. Para una demo o
> una revisión funcional, dejar `peluqueria_test`, que es la que viaja cargada en el ZIP.
>
> Las **154 pruebas no dependen de esto**: `phpunit.xml` fija `peluqueria_test` por su cuenta,
> corran donde corran. Ojo con eso si trabajás sobre `peluqueria_test` en pantalla — las
> pruebas escriben sobre esa misma base (revierten con `DatabaseTransactions`, salvo la de
> concurrencia, que limpia a mano).

La base queda publicada en **el puerto 3307**. Desde afuera del contenedor (por ejemplo con
HeidiSQL o Workbench): `127.0.0.1:3307`, usuario `root`, contraseña `root`.

**El contenedor usa su propio `.env`** (`docker/php/env.docker`, montado encima del otro). No
alcanzaba con poner las credenciales como variables del contenedor: `php artisan serve` le
reenvía al servidor que atiende las peticiones **solo una lista blanca** de variables, así que
`DB_HOST` no llegaba y la web contestaba *Connection refused* mientras los comandos de consola
andaban bien.

**Las contraseñas van aparte**, en `docker/php/secretos.env`, que no se versiona:

```bash
cp docker/php/secretos.env.example docker/php/secretos.env   # y completar
```

Sin ese archivo el sistema levanta igual, sólo que **sin correo** — o sea sin código de
verificación, sin recuperación de contraseña, sin segundo factor y sin recordatorios.
`docker compose exec app php artisan spg:diagnostico` lo dice en su sección de correo, que
existe justamente porque un correo apagado no se nota: la pantalla sigue diciendo «te enviamos
un código».

### Todo se corre dentro del contenedor

```bash
docker compose exec app php artisan test
docker compose exec app php artisan spg:diagnostico
docker compose exec app php artisan spg:pendientes
docker compose exec -T bd mysql -uroot -proot peluqueria_test
```

> Esto es **solo para desarrollar**. El servidor tiene su propio compose —
> `docker-compose.produccion.yml`, con php-fpm detrás de Caddy, HTTPS y la base sin ningún
> puerto abierto—: ver `DESPLIEGUE.md`.

---

---

## Las pruebas

```bash
"C:/php/php.exe" artisan test
```

**158 pruebas**, y corren contra `peluqueria_test` — una base de verdad, con el esquema del
TCC. Cubren la concurrencia de la agenda (5 procesos en paralelo sobre el mismo hueco tienen
que dejar **una sola** cita), el arqueo de caja, los correlativos sin huecos y la jerarquía
de los 28 permisos.

---

## Cómo está armado

```
docker-compose.yml         el entorno en contenedores (opción A)
docker/
  bd/10-importar.sh        crea e importa las dos bases, la primera vez
  php/Dockerfile           PHP 8.3 con pdo_mysql
  php/entrada.sh           dependencias, APP_KEY, diagnóstico y a servir
  php/env.docker           el .env de dentro del contenedor (no pisa el de la PC)
app/
  Ayudas/formato.php       num() money() ahora_bd() recurso() flash() — funciones globales
  Servicios/
    Bd.php                 el puente a las rutinas de la base (OUT, cursores, transacciones)
    Agenda.php             huecos disponibles, reparto entre profesionales, agendar
    Permisos.php           los 28 submódulos y su jerarquía
    Notificaciones.php     la cola de avisos por correo
    WebAuthn.php           ingreso con huella, en PHP puro (CBOR, COSE→PEM, OpenSSL)
  Http/Controllers/        un controlador por módulo
  Console/Commands/        spg:diagnostico · spg:preparar-sql · spg:notificaciones
resources/views/           Blade, con el mismo Bootstrap y la paleta oro champagne
tests/Feature/             las 158 pruebas
DESPLIEGUE.md              cómo publicarlo en el VPS
ACTUALIZAR.md              guía de bolsillo: cómo actualizarlo después
```

---

## Dos avisos que ahorran horas

**La hora no se toma con `date()`, se toma con `ahora_bd()`.** Una base de zonas horarias de
PHP anterior a que Paraguay dejara sin efecto el horario de verano devuelve, en agosto, una
hora menos. `ahora_bd()` se la pregunta a MariaDB, que sí da la correcta — y eso saca a PHP de
la ecuación en cualquier máquina. Importa en el fichaje de asistencia, que registra la hora
del clic.

**Actualizar Docker es `down -v`, no reiniciar.** El guion que importa las dos bases corre
**una sola vez, con el volumen vacío**, así que sobre un volumen que ya tiene datos no vuelve
a correr: queda código nuevo contra base vieja, y no falla al arrancar sino cuando alguien
abre la pantalla que usa lo que se agregó.

```bash
docker compose down -v && docker compose up -d
```

**Antes de eso, volcá lo que tengas cargado**: `down -v` borra el volumen. El procedimiento
completo —con el orden y qué comprobar después— está en `CLAUDE.md`.

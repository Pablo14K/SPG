# SPG — Sistema de Gestión para Peluquería

Sistema web de gestión para una peluquería de Luque, Paraguay. TCC de Ingeniería en
Informática. **Laravel 13 + MariaDB.**

---

## Lo primero que hay que entender: la lógica vive en la base

No es un Laravel normal. La base `peluqueria_bd` tiene **20 procedimientos, 30 funciones,
17 triggers, 17 vistas y 54 restricciones `CHECK`**, y ahí es donde están las reglas del
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

## Dos formas de levantarlo

| | Cuándo conviene |
|---|---|
| **[Con Docker](#opción-a-con-docker-un-solo-comando)** | no tenés PHP 8.3 ni MariaDB, o querés que la versión del motor sea exactamente la misma que acá |
| **[A mano](#opción-b-a-mano-xampp--php-83)** | ya tenés XAMPP andando, o la máquina es justa de recursos |

Las dos dan lo mismo. Docker pesa más al instalar (pide WSL2 y ~2 GB de RAM), pero **fija
MariaDB 10.4**, importa las dos bases solo y clava la zona horaria — que son las tres cosas
que más se rompen al armar el entorno en otra computadora.

---

## Opción A — con Docker, un solo comando

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
| `docker compose exec app php artisan test` | corre las 49 pruebas |
| `docker compose exec app php artisan spg:diagnostico` | la revisión del entorno |
| `docker compose exec bd mysql -uroot -proot peluqueria_bd` | entrar a la base |

**El contenedor crea las dos bases, y la aplicación usa `peluqueria_bd`, la vacía** — el
sistema tal como lo encuentra el salón el primer día: sin citas, sin facturas y sin clientas
de nadie, con los catálogos cargados y las cuentas para entrar. `peluqueria_test` queda
igual de disponible, con el mes simulado del QA, y es contra ella que corren las 49 pruebas.

Para trabajar con los datos de prueba en pantalla, cambiá una línea de
`docker/php/env.docker` y reiniciá con `docker compose up -d`:

```dotenv
DB_DATABASE=peluqueria_test
```

La base queda publicada en **el puerto 3307**, no en el 3306, para que conviva con un XAMPP
ya instalado sin pelearse por el puerto. Desde afuera del contenedor (por ejemplo con
HeidiSQL o Workbench): `127.0.0.1:3307`, usuario `root`, contraseña `root`.

**El contenedor usa su propio `.env`** (`docker/php/env.docker`, montado encima del otro), así
que si además trabajás sin Docker tu `.env` de siempre queda intacto y los dos conviven. No
alcanzaba con poner las credenciales como variables del contenedor: `php artisan serve` le
reenvía al servidor que atiende las peticiones **solo una lista blanca** de variables, así que
`DB_HOST` no llegaba y la web contestaba *Connection refused* mientras los comandos de consola
andaban bien.

> Esto es **solo para desarrollar**. El VPS se despliega con el panel, no con contenedores:
> ver `DESPLIEGUE.md`.

---

## Opción B — a mano (XAMPP + PHP 8.3)

### 1. PHP 8.3 o más — el de XAMPP no sirve

Laravel 13 pide 8.3, y el Apache de XAMPP trae **8.2**. Por eso el proyecto **no se sirve
desde `htdocs`**: se levanta con `artisan serve` usando un PHP aparte.

```bash
"C:/php/php.exe" -v
```

Tiene que decir 8.3 o más. Si no lo tenés, bajá PHP 8.3 de [windows.php.net](https://windows.php.net/download/)
(el ZIP *Thread Safe* x64), descomprimilo en `C:\php` y en su `php.ini` activá al menos:
`extension=pdo_mysql`, `extension=mbstring`, `extension=openssl`, `extension=fileinfo`,
`extension=curl`, `extension=zip`.

De XAMPP se usa **solo MariaDB**. Apache no hace falta para el sistema nuevo.

### 2. Las dependencias

Si la carpeta vino con `vendor/` adentro, saltealo. Si no:

```bash
composer install
```

(Composer se baja de [getcomposer.org](https://getcomposer.org/download/); apuntalo al PHP 8.3.)

### 3. La base de datos

Se crean **dos**, y cada una tiene su papel:

```bash
/c/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE peluqueria_bd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE peluqueria_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

| Base | Qué es | Con qué se carga |
|---|---|---|
| `peluqueria_bd` | la que **se entrega**: esquema al día, sin operación | `basededatos/peluqueria_bd(base).sql` |
| `peluqueria_test` | la de trabajo: 172 citas, 62 facturas, 33 clientas | `basededatos/1mes_simulacion.sql` |

```bash
/c/xampp/mysql/bin/mysql.exe -u root peluqueria_bd   < "basededatos/peluqueria_bd(base).sql"
/c/xampp/mysql/bin/mysql.exe -u root peluqueria_test < "basededatos/1mes_simulacion.sql"
```

Ninguno de los dos archivos lleva `CREATE DATABASE` ni `USE` adentro, así que respetan el
nombre que les pasás y no pisan otra base.

> **Nunca importar con `--skip-grant-tables`**: las vistas y rutinas quedan con el DEFINER
> vacío y todo revienta con el error 1449.

### 4. La configuración

```bash
cp .env.example .env
"C:/php/php.exe" artisan key:generate
```

En `.env`, lo único que suele haber que tocar es `DB_PASSWORD` (en XAMPP, `root` va sin
contraseña, así que se deja vacío).

### 5. Comprobar antes de arrancar

```bash
"C:/php/php.exe" artisan spg:diagnostico
```

Revisa la conexión, que los dos relojes coincidan, que estén las 20 rutinas / 30 funciones /
17 triggers / 17 vistas / 54 CHECK, que las funciones **respondan de verdad** (ahí sale el
1449 si quedó mal el import) y que Laravel no haya ensuciado la base.

Tiene que terminar en **«Todo en orden.»**

### 6. Arrancar

```bash
"C:/php/php.exe" artisan serve --port=8000
```

http://localhost:8000

| Usuario | Contraseña | Qué ve |
|---|---|---|
| `admin` | `admin123` | todo: los 7 módulos |
| `cliente` | `cliente123` | el portal de la clienta |

---

## Las pruebas

```bash
"C:/php/php.exe" artisan test
```

**49 pruebas**, y corren contra `peluqueria_test` — una base de verdad, con el esquema del
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
tests/Feature/             las 49 pruebas
DESPLIEGUE.md              cómo publicarlo en el VPS
```

---

## Dos avisos que ahorran horas

**La hora no se toma con `date()`, se toma con `ahora_bd()`.** La base de zonas horarias de
PHP en este XAMPP es anterior a que Paraguay dejara sin efecto el horario de verano, así que
en agosto PHP devuelve una hora menos. `ahora_bd()` se la pregunta a MariaDB, que sí da la
correcta. Importa en el fichaje de asistencia.

**MariaDB se arranca y se apaga desde el panel de XAMPP.** Si se la mata de golpe, el log de
Aria queda a medio escribir y al arrancar de nuevo falla con *«Could not open mysql.plugin
table»*. Se arregla borrando `aria_log.00000001` y `aria_log_control` de
`C:\xampp\mysql\data`, que MariaDB recrea sola. Los datos no se pierden: son InnoDB.

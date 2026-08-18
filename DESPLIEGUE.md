# Despliegue del SPG en el VPS compartido

El sistema se publica en un **VPS que se comparte entre varios grupos de la facultad**
(Vefixy · 2 vCores, 4 GB RAM, 80 GB NVMe, root, panel Hestia o CyberPanel). No es un hosting
compartido tipo cPanel, y esa diferencia es la que decide si este proyecto se puede desplegar:
**un hosting compartido clásico no deja crear funciones ni triggers**, y acá *toda* la lógica
de negocio vive ahí — 20 procedimientos, 30 funciones, 17 triggers, 17 vistas y 56 `CHECK`.

Este documento es la lista de pasos en el orden que funciona. Los cuatro primeros son los que
**rompen algo sin avisar**: si se saltean, el sistema arranca igual y falla después, en
producción y con datos de un salón real.

> **Ensayado en la PC de desarrollo el 10/08/2026**: se creó una base vacía y un usuario
> limitado (sin acceso a `mysql.user`, como el del VPS), se preparó el `.sql` con
> `spg:preparar-sql`, se importó **como ese usuario** y se comprobó que las rutinas responden
> y que el ingreso funciona. Los comandos de abajo son los que se usaron.

---

## 0. Antes de pagar el servidor, confirmarle al proveedor

| Qué | Por qué |
|---|---|
| **PHP 8.3 o más** | lo pide Laravel 13. Hestia admite varias versiones a la vez, así que otro grupo puede seguir con 8.1 |
| **Permiso para crear rutinas y triggers** (`CREATE ROUTINE`, `ALTER ROUTINE`, `TRIGGER`, `EXECUTE`) | sin eso el sistema no funciona: la lógica está en la base |
| **SMTP saliente por el puerto 587** | por ahí salen el código de verificación, la recuperación de contraseña, el segundo factor y los recordatorios. Si lo bloquean, el registro de clientas no anda |
| **HTTPS con Let's Encrypt** | WebAuthn (el ingreso con huella) no funciona sin HTTPS |
| **Cron del panel** | reemplaza al Programador de tareas de Windows |

---

## 1. La zona horaria — el error que nadie nota

Un VPS recién instalado corre en **UTC**, y el de Vefixy está en Miami. `ahora_bd()` le
pregunta la hora a MariaDB, y MariaDB toma la del sistema operativo: **el fichaje de asistencia
quedaría 3 o 4 horas corrido**, y no se nota hasta ver una entrada marcada a las 12 de la noche.

```bash
sudo timedatectl set-timezone America/Asuncion
sudo systemctl restart mysql        # o mariadb, según el panel
```

Y **comprobarlo contra el reloj de pared**, que es el único chequeo que vale:

```bash
mysql -u spg_peluqueria -p -e "SELECT NOW();"
```

Si el panel no deja tocar la zona del sistema, la alternativa es fijarla en MySQL:
`default-time-zone = '-03:00'` en `my.cnf`. Paraguay quedó fijo en UTC−3 desde que dejó sin
efecto el horario de verano, así que ese valor no cambia en marzo ni en octubre.

---

## 2. Preparar el `.sql` — los DEFINER

Las 50 rutinas, los 17 triggers y las 17 vistas se crearon con ``DEFINER=`root`@`localhost` ``.
En el VPS **no entramos como root**: cada grupo tiene su usuario. Importado tal cual, MySQL
contesta **error 1449** la primera vez que algo llame a una función — es decir, en la pantalla
de ingreso — y el sistema entero deja de andar.

Hay un comando que lo resuelve y no toca el original:

```bash
php artisan spg:preparar-sql "Referencias/peluqueria_bd(base).sql" spg_peluqueria --host=localhost
```

Escribe `peluqueria_bd(base)_servidor.sql` con los **84 definidores** reemplazados y
`SQL SECURITY DEFINER` pasado a `INVOKER`, que no depende de que el definidor exista.

**Antes de importar**, el usuario necesita el permiso (esto se corre como root):

```sql
GRANT CREATE ROUTINE, ALTER ROUTINE, TRIGGER, EXECUTE ON peluqueria_bd.* TO 'spg_peluqueria'@'localhost';
SET GLOBAL log_bin_trust_function_creators = 1;   -- si el servidor tiene binlog activo
```

Sin `log_bin_trust_function_creators = 1`, **las funciones no se crean** y el import no falla:
termina «bien» y el sistema revienta al primer uso.

Recién ahí:

```bash
mysql -u spg_peluqueria -p peluqueria_bd < "peluqueria_bd(base)_servidor.sql"
```

---

## 3. La carpeta pública apunta a `public/`, no a la raíz

En el panel, el *document root* del subdominio va a `…/spg-laravel/public`.

Si apunta a la raíz del proyecto, **el `.env` con la contraseña de la base queda descargable
por HTTP**. Es lo mismo que ya se cuida al publicar en `htdocs`, pero acá el descuido se paga
con la base de un salón real.

---

## 4. Los 4 GB de RAM son de todos los grupos

**Nada de procesos residentes.** La cola de correos se despacha por cron, nunca con un worker
permanente ni con Supervisor:

```
* * * * * cd /home/spg/web/peluqueria.proyectosfacultad.com/spg-laravel && php artisan schedule:run >> /dev/null 2>&1
```

El scheduler ya tiene declarado `spg:notificaciones` cada diez minutos con
`withoutOverlapping()`, así que dos corridas no se pisan.

Y en producción, siempre:

```bash
php artisan optimize        # config, rutas y vistas en caché — también ahorra memoria
```

---

## 5. En el servidor no se compila nada

No hay Node ni hace falta: Bootstrap viene por CDN y `app.css` es un archivo propio. Se sube
el código y las dependencias se resuelven con:

```bash
composer install --no-dev --optimize-autoloader
```

---

## 6. El `.env` de producción

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://peluqueria.proyectosfacultad.com
APP_TIMEZONE=America/Asuncion

DB_DATABASE=peluqueria_bd
DB_USERNAME=spg_peluqueria
DB_PASSWORD=…

MAIL_MAILER=smtp
MAIL_HOST=…
MAIL_PORT=587
MAIL_ENCRYPTION=tls
```

Tres que se olvidan y se pagan:

- **`APP_URL` con el subdominio real.** De ahí salen los enlaces de los correos (reprogramar,
  cancelar, agregar al calendario). Con el valor de desarrollo, a la clienta le llega un enlace
  a `localhost`, que abre en su propia computadora y no lleva a ningún lado.
- **`APP_DEBUG=false`.** Con `true`, cualquier error le muestra al visitante la traza completa,
  con la contraseña de la base adentro.
- **`APP_KEY`**: si es una instalación nueva, `php artisan key:generate`.

**WebAuthn** toma el dominio como `rpId` de la propia petición, así que no hay nada que
configurar — pero las credenciales registradas en desarrollo **no sirven** en producción: cada
persona vuelve a registrar su huella la primera vez.

---

## 7. Comprobarlo, no darlo por bueno

```bash
php artisan spg:diagnostico --produccion
```

Revisa la conexión, los dos relojes, que estén las 20 rutinas / 30 funciones / 17 triggers /
17 vistas / 54 CHECK, que las funciones **respondan de verdad** (ahí sale el 1449 si quedó),
que los definidores existan, que no haya tablas de Laravel dentro de la base del TCC,
`APP_DEBUG`, `APP_ENV`, `APP_URL`, la zona horaria, que no haya `.env` ni `.sql` dentro de
`public/`, que la configuración esté cacheada y que el SMTP esté configurado.

Tiene que terminar en **«Todo en orden.»** Si algo falla, el mensaje dice qué hacer.

Después, a mano:

1. Entrar con `admin` y con `cliente` (las dos cuentas que trae la base).
2. Agendar una cita y ver que la agenda ofrezca horarios.
3. Fichar una entrada de asistencia y **mirar la hora que quedó** contra el reloj de pared.
4. Emitir un comprobante y ver el desglose del IVA.
5. Pedir un código por correo (recuperar contraseña) y comprobar que llega.

---

## 8. La base que se sube está vacía a propósito

`Referencias/peluqueria_bd(base).sql` es **la base que se entrega con el programa**: esquema
completo, catálogos del sistema, la sucursal 1 y las dos cuentas del instalador — y **cero
operación**. Un salón que instala el sistema no puede encontrarse con las citas, las facturas
ni las clientas de otro.

Si se toca la base —una tabla, una columna, un `CHECK`, una función, un trigger—, ese archivo
se regenera **en la misma tanda**:

```bash
/c/xampp/mysql/bin/mysqldump.exe -u root --routines --triggers --events --single-transaction --default-character-set=utf8mb4 peluqueria_bd > "Referencias/peluqueria_bd(base).sql"
```

Siempre con `mysqldump`, **nunca exportando desde phpMyAdmin**, que se come las 54
restricciones `CHECK` sin avisar.

Para probar con datos está `1mes_simulacion.sql`, que es el mes simulado del QA.

---

## Cuando el sistema no está: qué hace el salón

**Esto no es una función del sistema: es lo que el salón hace cuando el sistema
no está.** Va acá porque es la carpeta que se abre cuando algo se cayó, y porque
la mitad de las respuestas dependen de cómo quedó instalado.

La regla de fondo, y es la única que hay que recordar bajo presión:
**el salón sigue atendiendo y cobrando. Lo que se interrumpe es el registro, no
el trabajo.** Todo lo que pase durante la caída se anota en papel y se carga
después, con su fecha real.

### Qué se cayó, y qué se puede hacer igual

| Se cayó | Qué sigue andando | Qué no |
|---|---|---|
| **Internet** (el local sin conexión) | Nada del sistema: vive en el servidor | Agenda, cobro, comprobantes |
| **La luz** | Nada, salvo que haya batería en el equipo | Todo, incluida la impresora |
| **El servidor** (el sistema no responde, con internet OK) | Nada | Todo |
| **El Automatizador SIFEN** | **Todo el sistema.** Se factura y se cobra igual | Sólo la declaración ante la DNIT, que queda PENDIENTE y se reintenta |

> La última fila es la más importante y la que más se confunde: **que la DNIT no
> conteste no frena el salón.** La factura ya es válida cuando el sistema la
> numera; declararla es un paso posterior y con su propio botón. Está explicado
> en `CLAUDE.md`, sección *Facturación electrónica*.

### La planilla de contingencia

Una hoja impresa, en el mostrador, con una fila por atención:

```
Fecha · Hora · Clienta · Teléfono · Servicios · Profesional · Monto · Cómo pagó · Nº de recibo manual
```

Tres cosas que conviene tener resueltas **antes** de necesitarlas:

- **Un talonario de comprobantes preimpresos**, con su timbrado propio, para el
  caso en que la clienta pida factura. Es un timbrado distinto del electrónico y
  se pide a la SET aparte: si no está pedido de antes, el día de la caída no hay
  nada que hacer.
- **La agenda del día impresa cada mañana.** Es un clic desde Citas → Agenda, y
  sin ella una caída a las 9 deja al salón sin saber quién viene.
- **El teléfono de las clientas del día**, que sale en esa misma impresión: es lo
  que permite avisar si hay que reprogramar.

### Cuando el sistema vuelve

En este orden, y **el orden importa**:

1. **Abrir la caja** con el monto que había al empezar el día, no con el que hay
   ahora. El sistema calcula el saldo sumando lo que se le carga; si se abre con
   el efectivo actual, todo lo que se cargue después lo va a duplicar.
2. **Cargar las citas atendidas**, una por una, con su fecha y hora reales.
3. **Registrar la atención** de cada una, con los productos que se usaron.
4. **Cobrar**, con el medio que corresponda a cada una.
5. **Emitir los comprobantes.** Si se usó el talonario manual, el número impreso
   se anota en la referencia del cobro: es lo que después permite cruzarlos.
6. **Contar el cajón y compararlo** con el arqueo del sistema. Si no coincide,
   la diferencia se carga como movimiento de caja con el concepto explicado —no
   se ajusta a mano ni se deja pasar.

> **Lo que NO hay que hacer**: cargar todo con la fecha de hoy. Un mes cerrado
> con las citas del martes anotadas el jueves no le sirve a ningún informe, y es
> el tipo de error que no se puede deshacer sin anular comprobante por
> comprobante.

### Cómo se acorta la caída

- **Respaldo diario de la base**, con `mysqldump`, fuera del servidor. Es la
  diferencia entre perder un día y perder el año.
- **Un equipo de reserva** con el sistema apuntando al mismo servidor: si lo que
  se rompió es la computadora del mostrador, el salón sigue en cinco minutos.
- **La dirección del sistema anotada en papel**, en el mostrador. Suena tonto
  hasta el día en que hay que entrar desde un celular y nadie la recuerda.

# Despliegue del SPG

El sistema se publica en **`https://spg.columbiatcc.online`**, sobre un **VPS de Hostinger
con Docker**. El dominio se compró entre varios grupos de la facultad, así que el SPG vive en
un subdominio y **comparte el servidor con otros proyectos**: eso decide buena parte de lo que
está escrito acá abajo.

> **Este documento cambió de raíz cuando el VPS vino con Docker.** La versión anterior asumía
> un servidor con panel (Hestia/CyberPanel) y PHP y MariaDB instalados a mano, y sus cuatro
> pasos más peligrosos eran justamente los que el contenedor resuelve solo. Quedan anotados en
> *«Lo que Docker se llevó puesto»*, al final, porque el motivo de cada uno sigue siendo
> instructivo y porque si algún día hay que desplegar sin contenedores vuelven a valer.

---

## Lo que Docker resuelve solo, y lo que no

| Antes había que… | Con Docker |
|---|---|
| Reescribir los **84 `DEFINER`** con `spg:preparar-sql`, o error 1449 en la pantalla de ingreso | **No hace falta**: adentro del contenedor se importa y se consulta como root |
| Confirmarle al proveedor que hay **PHP 8.3** | **Ya está**: la imagen es `php:8.3-fpm` |
| Fijar la **zona horaria** del sistema operativo y de MySQL | **Ya está**: el compose clava `TZ` y `--default-time-zone=-03:00` |
| Pedir `CREATE ROUTINE`, `TRIGGER` y `log_bin_trust_function_creators` | **Ya está** en el compose |
| Importar el `.sql` a mano | **Se importa solo** en el primer arranque |

Lo que sigue siendo trabajo, y es de lo que trata este documento:

1. **El DNS**, que es de otro (el dominio se compró en grupo).
2. **HTTPS**, sin el cual el ingreso con huella no existe.
3. **Que la base y el SIFEN no queden escuchando en una IP pública.**
4. **Las contraseñas**, que en desarrollo son `root`/`root`.
5. **El planificador y el respaldo**, que en el compose de desarrollo no existen.

---

## 0. Antes de tocar el servidor

| Qué | Por qué |
|---|---|
| **Acceso al DNS de `columbiatcc.online`** | hay que crear un registro A. Si el dominio lo administra otro del grupo, es lo primero que hay que pedirle |
| **La IP del VPS** | la da el panel de Hostinger |
| **Acceso SSH como root** | todo lo demás se hace por ahí |
| **SMTP saliente por el 587** | por ahí salen el código de verificación, la recuperación de contraseña, el segundo factor y los recordatorios. Si Hostinger lo bloquea, **una clienta nueva no puede terminar de registrarse** |
| **Una contraseña de aplicación de Gmail NUEVA** | ver el aviso de *«Antes de publicar»* — la que está en uso hay que rotarla |

---

## 1. El DNS: que el subdominio llegue al VPS

Un registro **A**, en el panel donde se administre `columbiatcc.online`:

```
Tipo   Nombre   Valor              TTL
A      spg      <IP del VPS>       3600
```

**Dónde se carga depende de dónde apunten los nameservers del dominio**, y no de quién lo
pagó:

| Si los nameservers están en… | Se carga en |
|---|---|
| Hostinger | el propio panel: **Administrador de DNS** |
| el registrador donde se compró (Namecheap, GoDaddy…) | ahí, no en Hostinger |

> **El dominio se compró en grupo, así que este paso puede no ser tuyo.** Es lo primero que
> hay que pedir, porque todo lo demás depende de que resuelva — y es lo único de esta lista
> que no se arregla desde el servidor.

Y comprobarlo **antes de seguir**, porque el certificado depende de esto:

```bash
dig +short spg.columbiatcc.online
```

Tiene que devolver la IP del VPS. **Si todavía no propagó, no sigas**: Caddy le pide el
certificado a Let's Encrypt apenas arranca, y Let's Encrypt admite **cinco intentos fallidos
por semana y por dominio**. Cinco arranques con el DNS a medias dejan el subdominio sin
certificado hasta la semana siguiente.

---

## 2. El panel de Hostinger: por dónde SÍ y por dónde NO

El VPS trae el **Administrador de Docker**, y ahí hay una pantalla que parece hecha para
esto y no lo es: **Compuesto → URL**, la que pide «pega aquí la URL de tu archivo Docker
Compose». Baja **solamente ese archivo** y lo levanta. Para este proyecto se rompen tres
cosas, y ninguna avisa:

| Qué falta | Qué pasa |
|---|---|
| `basededatos/` | MariaDB importa desde ahí en el primer arranque. Sin la carpeta arranca **una base sin una sola tabla** |
| El código y el `Caddyfile` | los montajes quedan como carpetas vacías: Caddy sirve la nada y php-fpm no encuentra `artisan` |
| `docker/php/secretos.env` | habría que escribir las contraseñas **dentro del compose**, o sea dentro del repositorio publicado |

La tercera es la que decide: el trabajo de la 7.85.1 fue justamente sacar las credenciales
del archivo versionado, y esa pantalla obliga a volver a meterlas.

**La puerta correcta es «Consola web»**, arriba a la derecha en esa misma pantalla: es una
terminal en el navegador, con root. Todo lo de abajo se hace ahí.

> Si algún día se quisiera usar el Administrador de Docker igual, habría que **hornear** el
> código, los `.sql` y el `Caddyfile` dentro de imágenes propias y publicarlas en un
> registro. Es más trabajo y deja las credenciales en peor lugar; se anota por si aparece
> el caso, no como recomendación.

---

## 3. El servidor: Docker, cortafuegos y hora

En la consola web, comprobar que Docker esté:

```bash
docker --version && docker compose version
```

**El cortafuegos se administra desde el panel** —*Seguridad → Firewall*—, no con `ufw`.
Hostinger aplica sus reglas por fuera del sistema operativo, así que las dos capas juntas
confunden: se abre un puerto adentro, sigue cerrado afuera, y se pierde media tarde
buscándolo en el lugar equivocado.

Las reglas son tres: **22 (SSH), 80 y 443**. Nada más.

> **El 3307 de la base y el 8090 del SIFEN no se abren nunca.** Los contenedores se hablan
> por la red interna de Docker, y `docker-compose.produccion.yml` no publica esos puertos
> justamente para que no dependa de que el cortafuegos esté bien puesto.

> **El 80 no es opcional aunque todo vaya por HTTPS**: Let's Encrypt valida por ahí, así que
> sin el 80 abierto el certificado no se emite ni se renueva.

> **Ojo con `ufw enable` si igual se lo usa**: sin una regla para el 22 puesta **antes**, el
> comando corta la propia sesión SSH y se entra sólo por la consola web del panel.

Y la zona horaria del host, que no cuesta nada y evita confundirse leyendo los logs:

```bash
timedatectl set-timezone America/Asuncion
```


---

## 4. Subir el proyecto

Desde la consola web, con git —que es lo que hace que actualizar después sea un `git pull`
y no volver a subir un ZIP entero:

```bash
git clone https://github.com/Pablo14K/SPG.git /opt/spg && cd /opt/spg
```

Si el repositorio es **privado**, git va a pedir usuario y contraseña, y GitHub ya no acepta
la contraseña de la cuenta: hay que darle un **token personal** (`Settings → Developer
settings → Personal access tokens`, permiso `repo`) como si fuera la contraseña.

**Lo que tiene que estar sí o sí**, porque el arranque depende de ello:

- `basededatos/` — de ahí importa MariaDB en el primer arranque;
- `docker/` entero;
- `docker-compose.produccion.yml`.

No hace falta excluir nada por seguridad: **la raíz que sirve Caddy es `public/`**, así que
todo lo demás —el `.env`, los `.sql`, `CLAUDE.md`, `tests/`— no es alcanzable por HTTP.

---

## 5. Las credenciales

```bash
cp docker/php/secretos.env.example docker/php/secretos.env
```

Y completar. Las tres que **si faltan el contenedor se apaga a propósito**, en vez de arrancar
a medias con la configuración equivocada:

```dotenv
APP_KEY=                 # se genera abajo
MYSQL_ROOT_PASSWORD=     # una larga y al azar
DB_PASSWORD=             # LA MISMA que la de arriba
SPG_DOMINIO=spg.columbiatcc.online
SPG_EMAIL_TLS=           # a dónde avisa Let's Encrypt si un certificado no se renueva
MAIL_USERNAME=           # la cuenta de Gmail
MAIL_PASSWORD=           # la contraseña de aplicación NUEVA
MAIL_FROM_ADDRESS=       # la MISMA cuenta que se autentica, o Gmail rechaza
```

La `APP_KEY` se genera una vez y se pega ahí:

```bash
docker compose -f docker-compose.produccion.yml run --rm app php artisan key:generate --show
```

> **Se genera UNA vez y no se cambia.** Si cambia, las sesiones abiertas y todo lo cifrado
> dejan de leerse.

> **`secretos.env` no está en el repositorio y tiene que llegar al servidor igual.** Se copia
> a mano, con `scp` o pegándolo por SSH. Es el mismo archivo que viaja adentro del ZIP para
> quien sólo quiere probar el sistema — ver *«El ZIP y el repositorio son dos canales
> distintos»* en `CLAUDE.md`.

---

## 6. Levantar

```bash
cd /opt/spg
docker compose -f docker-compose.produccion.yml up -d --build
docker compose -f docker-compose.produccion.yml logs -f
```

La primera vez tarda: construye la imagen, instala las dependencias con Composer e **importa
las dos bases**. En el log de `bd` tiene que decir «listo: 60 rutinas» por base.

Se instala **`peluqueria_bd`**, la base que se entrega: catálogo, un local y las dos cuentas
del instalador, con **cero operación**. No `peluqueria_test`, que es el mes simulado del QA —
son clientas y facturas inventadas, y ese volcado lleva adentro **dos correos reales** que
recibirían recordatorios de citas que no existen.

---

## 7. Comprobarlo, no darlo por bueno

```bash
docker compose -f docker-compose.produccion.yml exec app php artisan spg:diagnostico --produccion
```

Revisa la conexión, los dos relojes, que estén los 21 procedimientos / 39 funciones / 17
disparadores / 17 vistas / 78 `CHECK`, que las funciones **respondan de verdad**, que la base
coincida con el `.sql` que se entrega, `APP_DEBUG`, `APP_ENV`, `APP_URL`, la zona horaria, que
no haya `.env` ni `.sql` dentro de `public/`, que la configuración esté cacheada y que el SMTP
tenga con qué autenticarse.

Tiene que terminar en **«Todo en orden.»**

Después, a mano, y esto no se puede saltear:

1. Abrir `https://spg.columbiatcc.online` y ver que **el candado esté** (sin HTTPS no hay
   ingreso con huella).
2. Entrar con `admin` y con `cliente`.
3. Agendar una cita y ver que la agenda ofrezca horarios.
4. **Fichar una entrada de asistencia y mirar la hora que quedó contra el reloj de pared.**
   Es la comprobación que más veces salvó a este proyecto.
5. Emitir un comprobante y ver el desglose del IVA.
6. Pedir un código por correo (recuperar contraseña), comprobar **que llega** y que el enlace
   del correo diga `https://spg.columbiatcc.online`, no `localhost`.

Y lo que le falta CARGAR al salón —que es otra pregunta— lo contesta:

```bash
docker compose -f docker-compose.produccion.yml exec app php artisan spg:pendientes
```

---

## 8. El planificador y el respaldo

**El planificador ya está**: es el servicio `cron` del compose, que corre `schedule:run` cada
minuto. Sin él no salen los recordatorios, las citas vencidas no se cierran y las señas sin
confirmar no se sueltan nunca. Se comprueba que esté vivo:

```bash
docker compose -f docker-compose.produccion.yml logs cron | tail
```

**El respaldo hay que agendarlo**, y es lo único que se agenda en el host:

```bash
chmod +x docker/respaldo.sh
crontab -e
```

```
0 3 * * * /opt/spg/docker/respaldo.sh >> /opt/spg/respaldos/respaldo.log 2>&1
```

> **El volumen de Docker NO es un respaldo: es el mismo disco.** Un `docker compose down -v`
> mal tipeado borra la base sin preguntar. Y un archivo guardado en el mismo servidor tampoco
> alcanza — hay que **bajarlo a otra máquina**, una vez por semana como mínimo:
>
> ```bash
> scp root@<IP>:/opt/spg/respaldos/*.gz .
> ```
>
> Un respaldo que nunca se restauró es una suposición, no un respaldo.

---

## 9. Antes de publicar: las cuatro cosas que este proyecto venía postergando

`CLAUDE.md` dice que estas se avisan **al desplegar y no antes**, para no repetirlas en cada
tanda de desarrollo. Éste es el momento.

| Qué | Quién |
|---|---|
| **Rotar la contraseña de aplicación de Gmail.** La que está en uso quedó en el historial de git (commits `0de5fb6` y `e18367b`) y **sacarla de un archivo no la borra del historial**: cualquiera con el repositorio la lee con `git show`. Se genera una nueva en `myaccount.google.com/apppasswords`, se revoca la vieja y se pone la nueva en `secretos.env` | **El usuario** |
| **La base que se instala es `peluqueria_bd`**, la limpia. Comprobado: cero correos reales adentro | hecho |
| **`APP_DEBUG=false`, `APP_URL` con el subdominio, `LOG_LEVEL=warning`** | hecho en `env.produccion` |
| **Qué no se sube**: con Docker no aplica, porque la raíz servida es `public/`. Lo que sí importa es que `secretos.env` **nunca** entre al repositorio | hecho (`.gitignore`) |

Dos consecuencias que conviene tener presentes el día uno:

- **Las credenciales de huella registradas en desarrollo no sirven acá.** WebAuthn toma el
  dominio como `rpId`, así que cada persona vuelve a registrar la suya la primera vez. No es
  un error: es cómo funciona.
- **La facturación electrónica sale apagada** (`SIFEN_ACTIVO=false`). Se enciende recién
  cuando el salón declare de verdad ante la DNIT, y para eso el Automatizador necesita sus
  certificados, que no están en el repositorio a propósito.

---

## 10. Actualizar el sistema, después

```bash
cd /opt/spg
git pull                                    # o subir los archivos nuevos
docker compose -f docker-compose.produccion.yml up -d --build
```

**El `--build` no es opcional y el reinicio tampoco**: en el servidor OPcache corre con
`validate_timestamps=0`, así que no vuelve a mirar el disco nunca. Sin reiniciar el
contenedor, se sigue sirviendo el código viejo **sin que nada avise** — que es exactamente la
clase de error que este proyecto documenta una y otra vez.

**Si el cambio tocó el esquema de la base**, el guion de importación no lo va a aplicar: corre
una sola vez, con el volumen vacío. Hay que aplicar el cambio a mano sobre la base andando
(nunca `down -v`, que borraría la operación del salón), y `spg:diagnostico` es el que dice si
la base quedó atrás:

```bash
docker compose -f docker-compose.produccion.yml exec app php artisan spg:diagnostico --produccion
```

---

## Lo que Docker se llevó puesto

Queda anotado porque el motivo de cada uno sigue valiendo, y porque si algún día hay que
desplegar sin contenedores vuelven a hacer falta:

- **Los 84 `DEFINER`.** Las rutinas se crearon con ``DEFINER=`root`@`localhost` ``. Importadas
  con el usuario limitado de un panel, MySQL contesta **error 1449** la primera vez que algo
  llame a una función — o sea en la pantalla de ingreso. Lo resuelve
  `php artisan spg:preparar-sql <archivo> <usuario>`, que reescribe los definidores y pasa
  `SQL SECURITY DEFINER` a `INVOKER`. **Adentro del contenedor no hace falta porque se conecta
  como root**, y eso no compra inseguridad: la base no publica ningún puerto.
- **`log_bin_trust_function_creators = 1`.** Sin esto, con el binlog activo **las funciones no
  se crean y el import no falla**: termina «bien» y el sistema revienta al primer uso. En el
  compose está puesto.
- **La zona horaria.** Un VPS recién instalado corre en **UTC**. `ahora_bd()` sella el fichaje
  con la hora de la **base**, así que quedaría tres o cuatro horas corrido, y no se nota hasta
  ver una entrada marcada a medianoche.
- **La carpeta pública apuntando a `public/`.** Si apunta a la raíz, el `.env` con la
  contraseña de la base queda descargable por HTTP.

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

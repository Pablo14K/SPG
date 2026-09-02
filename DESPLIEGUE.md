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

Tiene que devolver la IP del VPS. **Si todavía no propagó, no sigas**: Traefik le pide el
certificado a Let's Encrypt apenas ve el proyecto, y Let's Encrypt admite **cinco intentos
fallidos por semana y por dominio**. Cinco intentos con el DNS a medias dejan el subdominio sin
certificado hasta la semana siguiente.

---

## 1b. Traefik: el proxy que reparte los dominios

**El SPG no publica ningún puerto.** Quien escucha el 80 y el 443 del VPS es **Traefik**, que
se despliega una vez desde el panel —está entre las plantillas de proyecto— y reparte cada
pedido al contenedor que corresponde según el dominio. Así conviven varios proyectos de la
facultad en el mismo servidor sin pelearse por los puertos web.

Lo que hay que saber antes de desplegar el SPG:

| Qué | Detalle |
|---|---|
| La red | **`traefik-proxy`**, y la crea la plantilla de Traefik |
| Entrypoint | `websecure` |
| Resolvedor de certificados | `letsencrypt` |
| Quién saca el certificado | Traefik, solo, al ver las etiquetas del contenedor |

> **`traefik-proxy` está declarada como `external` en el compose del SPG.** Eso significa que
> este proyecto **no la crea**: si Traefik no está desplegado todavía, el `up` falla diciendo
> que la red no existe. Es lo correcto — sin proxy el sistema no sería alcanzable desde
> afuera, y más vale enterarse ahí que buscándolo en el navegador.

### Por qué sigue habiendo un Caddy adentro

Es la pregunta obvia al ver dos servidores web. **Traefik habla HTTP y php-fpm habla
FastCGI**: no se pueden conectar directamente. Hace falta algo que traduzca, y de paso sirva
los archivos estáticos —el CSS, el JS, las fotos de los servicios— sin molestar a PHP.

```
navegador --HTTPS--> Traefik --HTTP--> Caddy --FastCGI--> php-fpm
```

Caddy ya no saca certificados ni escucha el 443: sólo el 80 de su propia red interna.

### Las dos piezas sin las cuales los correos salen con enlaces rotos

Con el TLS terminando en Traefik, **php-fpm ve una conexión HTTP en claro**. Si nadie le dice
lo contrario, Laravel arma los enlaces con `http://` — y eso se paga donde más duele: los
correos de reprogramar, cancelar y agregar la cita al calendario le llegan a la clienta
apuntando a HTTP, que con HSTS puesto el navegador ni abre.

Hacen falta **las dos**, y con una sola no alcanza:

1. **`trusted_proxies` en el Caddyfile.** Traefik manda `X-Forwarded-Proto: https`, pero Caddy
   la **reescribe** con el esquema de la conexión que él recibió. Comprobado: la cabecera
   llegaba a PHP como `http` aunque el proxy mandara `https`.
2. **`trustProxies` en `bootstrap/app.php`**, para que Laravel le crea a esa cabecera.

Las dos confían **sólo en rangos privados**: confiar en cualquier origen dejaría que un
visitante mintiera sobre el esquema y sobre su propia IP.

---

## 2. Desplegar desde el panel — el camino elegido

El VPS trae el **Administrador de Docker**, y su pantalla **Compuesto → URL** hace más de lo
que su texto promete: detecta que la dirección es de GitHub y **clona el repositorio entero**
antes de construir.

```
Detected GIT platform: github-raw
Trying HTTPS clone: https://github.com/Pablo14K/SPG.git
Cloning into '/tmp/hstgr-…'...
Image spg-app Built · Image spg-cron Built
```

Así que `basededatos/`, el código y el `Caddyfile` llegan, y los dos `build` funcionan. Se
pega esta URL y se le pone nombre al proyecto:

```
https://raw.githubusercontent.com/Pablo14K/SPG/refs/heads/master/docker-compose.produccion.yml
```

### El precio, escrito para que nadie lo descubra tarde

**`docker/php/secretos.env` está versionado**, y es la única forma de que el panel lo vea:
cada despliegue clona a un `/tmp/hstgr-…` **nuevo**, así que un archivo dejado a mano en el
servidor no sobrevive al siguiente.

Eso significa que la contraseña de la base, la `APP_KEY`, la clave del correo y el token del
SIFEN **quedan legibles para cualquiera que abra el repositorio**, y no se pueden borrar del
historial. Con el repositorio **público**, los rastreadores automáticos lo indexan en
segundos: no alcanza con que esté publicado «un rato».

| Si el repositorio es… | Qué significa |
|---|---|
| **Público** | asumir las credenciales como comprometidas y **rotarlas después de desplegar** |
| **Privado** | mismo mecanismo, exposición mucho menor — sólo hay que comprobar que el panel pueda clonar con credenciales |

> **La alternativa sin ese precio es la «Consola web»** —el botón de arriba a la derecha en
> esa misma pantalla—: ahí el archivo se crea una vez en el servidor y no viaja nunca. Está
> escrita completa en los puntos 4 a 6.

### Cuando falla, el panel no dice por qué

Si a `secretos.env` le falta alguna variable, MariaDB arranca sin contraseña y se apaga. Lo
que se ve arriba es esto, que no sirve de nada:

```
Container spg_bd Error dependency bd failed to start
dependency failed to start: container spg_bd is unhealthy
```

**El error de verdad está en el log del contenedor:**

```bash
docker logs spg_bd
```

Ahí se lee `database is uninitialized and password option is not specified`. Es el patrón de
siempre de este proyecto: algo falta y el mensaje apunta a otro lado.

> **Y ojo con lo que aconseja el asistente del panel ante ese error.** Dice que no se borren
> los volúmenes «porque MariaDB está inicializando», y es al revés: ese arranque **no importó
> nada** —murió antes—. Desde la 7.87.2 eso ya no obliga a nada: **el arranque de la
> aplicación importa la base si la encuentra vacía**, así que un volumen que quedó a medias se
> arregla en el despliegue siguiente. Lo que sí hay que limpiar son las carpetas fantasma de
> `/docker/spg` — ver más abajo.

### Por qué el compose de producción no tiene NI UN montaje de host

El panel **construye desde el clon y despliega desde otro lado**, y esa
separación es la que hay que entender:

| Fase | Dónde ocurre |
|---|---|
| Clonar y **construir** las imágenes | `/tmp/hstgr-…`, con el repositorio entero |
| **Levantar** los contenedores | `/docker/spg`, que tiene **sólo el compose** |

Comprobado mirando la carpeta en el servidor: `/docker/spg` tiene el compose
—renombrado `docker-compose.yml`—, un `.env` que arma el panel, `docker/`, y
**carpetas vacías** con los nombres de los montajes del compose viejo. No hay
`app/`, ni `config/`, ni `composer.json`: **el proyecto no se copia ahí**.

Por eso los `build.context` funcionan (salen del clon) y los volúmenes con ruta
no (se resuelven contra `/docker/spg`).

**La diferencia no es dónde buscan, es qué hacen cuando no encuentran:**

| Qué | Si la ruta falta |
|---|---|
| `build.context` | **falla ruidosamente** — el despliegue se detiene |
| `env_file` | **falla ruidosamente**, nombrando el archivo |
| `volumes` con ruta | **Docker la crea como CARPETA, en silencio** |

Esa tercera fila es la que costó la tarde. Con `/docker/spg` a medias, en el
primer intento Docker inventó carpetas donde tenía que haber archivos, y de ahí
salieron los dos síntomas:

- `./basededatos:/sql` y `./docker/bd:/docker-entrypoint-initdb.d` quedaron
  vacíos, así que **el guion de importación nunca corrió** y MariaDB llegó a
  **`healthy` con las bases vacías** — peor que fallar, porque no lo dice nadie;
- `./docker/php/env.produccion:/app/.env` se creó como **carpeta**, y una carpeta
  inventada **bloquea la copia del proyecto para siempre**: el despliegue
  siguiente reventó con *«not a directory: Are you trying to mount a directory
  onto a file?»*.

Por eso todo lo que antes se montaba ahora **se hornea en la imagen**, con cuatro
Dockerfile que usan la raíz del proyecto como contexto:

| Imagen | Qué lleva adentro |
|---|---|
| `docker/php/Dockerfile.produccion` | el código, `vendor/` ya instalado con `--no-dev`, y `env.produccion` como `/app/.env` |
| `docker/bd/Dockerfile` | los `.sql` en `/sql` y el guion en `/docker-entrypoint-initdb.d` |
| `docker/caddy/Dockerfile` | el `Caddyfile` y `public/` |
| `docker/sifen/Dockerfile` | el Automatizador |

**Lo que se gana no es que deje de depender del disco** —el contexto de build
sigue saliendo de `/docker/spg`— sino que **ya no hay ningún fallo silencioso**:
si el proyecto no llegó, el build se detiene y lo dice, en vez de servir una
base vacía.

Lo único que queda del host es `docker/php/secretos.env`, por `env_file`, que
también falla ruidosamente. Es la razón por la que ese archivo está versionado.

Lo que persiste vive en **volúmenes con nombre**, que no dependen de ninguna
ruta: `datos_bd`, `almacenamiento` —las sesiones, los logs y las copias de los
comprobantes electrónicos—, `imagenes_servicios` e `imagenes_logo` —compartidos
entre PHP, que las escribe, y Caddy, que las sirve— y los tres de Caddy.

> **Consecuencia al actualizar**: el código viaja en la imagen, así que un
> `git pull` no alcanza — hay que **reconstruir**. El panel lo hace en cada
> despliegue; por SSH es `up -d --build`.

### Si un despliegue anterior falló, hay dos cosas que limpiar

**1. Las carpetas fantasma en `/docker/spg`.** Una carpeta llamada
`env.produccion` donde tenía que haber un archivo bloquea la copia del proyecto
en cada despliegue siguiente:

```bash
find /docker/spg -name env.produccion -o -name Caddyfile | xargs -r ls -ld
```

Lo que aparezca como directorio (`d` al principio) se borra:

```bash
rm -rf /docker/spg/docker/php/env.produccion /docker/spg/docker/caddy/Caddyfile
```

**2. El volumen de la base ya NO hace falta tocarlo.** Desde la 7.87.2 el arranque de la
aplicación comprueba la base y **la importa sola si está vacía**, así que un volumen que quedó
inicializado a medias se arregla en el despliegue siguiente sin entrar por consola. En el log
del contenedor `spg_app` se ve:

```
== SPG: la base 'peluqueria_bd' está vacía (0 tablas): importando ==
== SPG: importada · 97 tablas y vistas · 60 rutinas ==
```

Con la base ya cargada no dice nada de eso y **no toca un solo dato**: sólo importa si hay
menos de 10 tablas. Y si no puede ni conectarse, **se apaga diciéndolo** en vez de confundir
«no pude preguntar» con «no hay nada» — que era lo peligroso, porque el `.sql` empieza con
`DROP TABLE IF EXISTS`.

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
> sin el 80 abierto el certificado no se emite ni se renueva. Los dos puertos los usa
> **Traefik**, no el SPG — este proyecto no publica ninguno.

> **Ojo con `ufw enable` si igual se lo usa**: sin una regla para el 22 puesta **antes**, el
> comando corta la propia sesión SSH y se entra sólo por la consola web del panel.

Y la zona horaria del host, que no cuesta nada y evita confundirse leyendo los logs:

```bash
timedatectl set-timezone America/Asuncion
```


---

## 4. Subir el proyecto — *sólo para el camino de la consola web*

> Los puntos 4, 5 y 6 son **la alternativa** al panel: el archivo de secretos se crea una
> vez en el servidor y no viaja en el repositorio. Si desplegás desde el Administrador de
> Docker (punto 2), saltá directo al **punto 7**.

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
SPG_EMAIL_TLS=           # heredado de cuando Caddy sacaba el certificado; hoy lo hace Traefik
MAIL_USERNAME=           # la cuenta de Gmail
MAIL_PASSWORD=           # la contraseña de aplicación NUEVA
MAIL_FROM_ADDRESS=       # la MISMA cuenta que se autentica, o Gmail rechaza
```

La `APP_KEY` se genera una vez y se pega ahí:

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)"
```

> **Se genera con `openssl` y no con `artisan key:generate`, y no es capricho.** Ese comando necesita el contenedor levantado, y el contenedor **se apaga a propósito cuando la `APP_KEY` está vacía** — que es justo el momento en que uno la necesita. Además, en el primer arranque `vendor/` todavía no existe. Lo que `artisan` hace es exactamente esto: 32 bytes al azar en base64.

> **Se genera UNA vez y no se cambia.** Si cambia, las sesiones abiertas y todo lo cifrado
> dejan de leerse.

> **`secretos.env` no está en el repositorio y tiene que llegar al servidor igual.** Se copia
> a mano, con `scp` o pegándolo por SSH. Es el mismo archivo que viaja adentro del ZIP para
> quien sólo quiere probar el sistema — ver *«El ZIP y el repositorio son dos canales
> distintos»* en `CLAUDE.md`.

---

## 6. Levantar

**Si antes se intentó desde el Administrador de Docker, hay que limpiar primero.** Los
contenedores llevan nombre fijo (`spg_bd`, `spg_app`…), así que los que quedaron del intento
fallido chocan con éstos. Y el volumen de la base **tiene que salir**: MariaDB corre el guion
de importación **una sola vez, con el volumen vacío**, así que uno a medio inicializar deja
el sistema andando contra una base sin tablas y sin decir nada.

```bash
docker rm -f spg_bd spg_app spg_web spg_cron spg_sifen
```

```bash
docker volume ls -q | grep -E 'datos_bd|vendor_app|caddy_' | xargs -r docker volume rm
```

> **No hay nada que perder ahí.** Ese arranque murió antes de importar: la base nunca llegó a
> existir. Borrar el volumen es lo único que garantiza que el `.sql` se cargue.

Y ahora sí:

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

> **Ojo con la ruta del proyecto, que depende de por dónde desplegaste:**
>
> | Camino | Dónde está el proyecto |
> |---|---|
> | Administrador de Docker (punto 2) | **en ninguna carpeta del host**: viaja dentro de las imágenes. En `/docker/spg` está sólo el compose |
> | Consola web (puntos 4 a 6) | donde lo clonaste, en este documento `/opt/spg` |
>
> Por eso los comandos que necesitan un archivo del proyecto —el guion de actualización, por
> ejemplo— lo sacan **de adentro del contenedor** con `docker exec spg_app cat …`. Para salir
> de dudas: `ls -d /docker/spg /opt/spg 2>/dev/null`.

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
0 3 * * * /docker/spg/docker/respaldo.sh >> /var/log/spg-respaldo.log 2>&1
```

> **El volumen de Docker NO es un respaldo: es el mismo disco.** Un `docker compose down -v`
> mal tipeado borra la base sin preguntar. Y un archivo guardado en el mismo servidor tampoco
> alcanza — hay que **bajarlo a otra máquina**, una vez por semana como mínimo:
>
> ```bash
> scp root@<IP>:/var/respaldos/spg/*.gz .
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

## 10. Actualizar el sistema con el salón andando

> **Para el día a día está `ACTUALIZAR.md`**, que son los mismos pasos sin los porqués — dos
> páginas para tener al lado. Esta sección explica *por qué* cada cosa es así.

**Son dos casos y conviene saber en cuál estás antes de tocar nada**, porque uno es apretar
un botón y el otro toca la base de un salón con datos reales.

La pregunta que los separa: **¿el cambio tocó el esquema de la base?** Y tiene una respuesta
mecánica, porque la regla del proyecto es que al tocar una tabla, una columna, un `CHECK`, una
vista, una función o un disparador se regenera `basededatos/peluqueria_bd(base).sql` **en la
misma tanda**. Así que alcanza con mirar si ese archivo cambió:

```bash
git log --name-only --oneline -5 | grep -c "peluqueria_bd(base).sql"
```

**0** → sólo código, caso A. **Más de 0** → tocó la base, caso B.

---

### Caso A · Sólo código (la mayoría de las correcciones)

Un arreglo de PHP, de una vista, del CSS o del JavaScript. Son dos pasos:

1. Subir el cambio: `git push origin master`.
2. En el panel, **Administrador de Docker → tu proyecto → Implementar** (la misma URL del
   compose de siempre).

El panel vuelve a clonar, **reconstruye las imágenes** —el código viaja adentro— y recrea los
contenedores. Eso es todo: la base no se toca, y el arranque no la va a importar porque no
está vacía.

> **El rebuild no es opcional.** En el servidor OPcache corre con `validate_timestamps=0`, o
> sea que **no vuelve a mirar el disco nunca**: sin reconstruir y recrear el contenedor se
> sigue sirviendo el código viejo **sin que nada avise**. Es la forma exacta en que este
> proyecto se rompe siempre, y el panel lo hace solo en cada despliegue.

**Comprobar que la versión nueva es la que está corriendo** — se lee de adentro de la imagen,
que es lo que de verdad se está sirviendo:

```bash
docker exec spg_app grep -m1 "'version'" config/spg.php
```

Tiene que decir la que acabás de subir. Si dice la anterior, el despliegue no reconstruyó.

---

### Caso B · El cambio tocó la base

**Acá el despliegue NO alcanza, y hay que saberlo de antemano.** El importador del arranque
sólo actúa con la base vacía —es a propósito: sobre una cargada, importar sería borrar la
operación del salón— así que una columna nueva **no se aplica sola**.

El sistema te lo dice, y ése es el punto: `spg:diagnostico` compara la base contra el `.sql`
que se entrega y nombra lo que falta.

```bash
docker exec spg_app php artisan spg:diagnostico --produccion
```

También sale en el log del contenedor en cada arranque. Si aparece algo como «faltan
`preferencia_usuario.tema`», el orden es este y **el primer paso no se saltea**:

**1. Respaldo antes de tocar nada.** Es la única red que hay:

```bash
/docker/spg/docker/respaldo.sh
```

**2. Aplicar el guion de esa versión.** Vive en `basededatos/actualizaciones/`, con la fecha y
la versión en el nombre, y viaja en el repositorio como cualquier otro archivo:

```bash
docker exec spg_app cat /app/basededatos/actualizaciones/2026-09-01_7.88.0.sql | docker exec -i spg_bd sh -c 'mysql --skip-ssl -uroot -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8mb4 peluqueria_bd'
```

> **El guion se lee de DENTRO del contenedor de la aplicación, no del disco del servidor.**
> Desplegando por el panel, el proyecto no queda en ninguna carpeta del host —ver el punto 2—:
> lo único que hay es el compose. El código, y con él `basededatos/`, viaja **dentro de la
> imagen**, así que ahí es donde está el archivo. `docker exec spg_app cat …` lo saca y se lo
> pasa a MariaDB por la tubería.

> **Los guiones de `actualizaciones/` no tocan datos y se pueden volver a correr.** Una rutina
> —función, procedimiento, disparador, vista— se reemplaza entera con `DROP … IF EXISTS` +
> `CREATE`, así que aplicar dos veces el mismo archivo deja exactamente lo mismo. Comprobado:
> corrido sobre una base que ya lo tenía, las 182 citas, las 64 facturas y las 63 rutinas
> quedaron idénticas.
>
> **Una columna o una tabla nueva es otra cosa** y va con `ALTER TABLE` en ese mismo archivo,
> pero ahí sí conviene mirar el guion antes de correrlo dos veces.

> **`docker/bd/` ya NO sirve para esto, y conviene saberlo porque es el consejo que da todo el
> mundo.** MariaDB corre lo que haya ahí **una sola vez, con el volumen vacío**: sobre una base
> con datos no se ejecuta nunca. Desde la 7.87.2 este proyecto ni siquiera lo usa — el
> importador vive en el arranque de la aplicación. Poner ahí una columna nueva es escribirla
> para nadie.

**3. Volver a comprobar.** El diagnóstico tiene que terminar en «Todo en orden».

> **Nunca `docker compose down -v` en el servidor.** El `-v` borra el volumen, y ahí vive la
> operación del salón: las citas, las facturas, los cobros. En desarrollo es lo que se hace
> para reimportar de cero; acá es perder el año. Si hiciera falta empezar de cero, se restaura
> desde el respaldo.

> **Y si el cambio es de rutinas** —una función, un procedimiento, un disparador— acordate de
> que el `.sql` las trae con `DROP ... IF EXISTS` adelante, así que reaplicar sólo esa parte es
> seguro. Lo que no se puede reaplicar entero es el volcado completo, que empieza tirando las
> tablas.

---

### Volver atrás

Como el código viaja en la imagen y la imagen se construye desde un commit, deshacer es
volver el repositorio y redesplegar:

```bash
git revert <commit> && git push origin master
```

Y otra vez **Implementar** en el panel. La base no se toca, así que un revert de código es
inocuo. **Si el cambio había tocado el esquema, el revert del código no revierte la base** —
eso se hace con SQL, o restaurando el respaldo del paso 1.

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

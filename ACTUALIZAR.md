# Actualizar el SPG — guía de bolsillo

Para el servidor de todos los días. Lo largo —por qué cada cosa es así— está en
`DESPLIEGUE.md`; acá van sólo los pasos, **los que están comprobados en el VPS**.

---

## Dónde se hace cada cosa

| Lugar | Cómo se llega | Para qué |
|---|---|---|
| **Consola web** | hPanel → VPS → botón arriba a la derecha | **acá se actualiza**, y se hacen los respaldos |
| **El panel** | hPanel → VPS → Administrador de Docker → Proyectos | ver los contenedores y sus logs |
| **Terminal de un contenedor** | en el panel, `Terminal` en la tarjeta de `spg_app` | comandos del proyecto (`php artisan …`) |

Dos cosas que conviene tener claras antes de empezar:

- **La rama es `main`.** `master` se borró: el repositorio tiene una sola rama y
  el clon por defecto ya trae el código.
- **El proyecto NO vive en ninguna carpeta del servidor.** El código viaja dentro
  de las imágenes; en `/docker/spg` sólo queda el compose. Por eso lo que
  necesita un archivo del proyecto se corre **dentro de `spg_app`**, donde está
  en `/app`.

---

## 1 · Sólo cambió código

PHP, vistas, CSS, JavaScript. **Dos pasos.**

**1.** En tu computadora:

```bash
git push origin main
```

**2.** En la **Consola web** del VPS, una sola línea:

```bash
cd /tmp && rm -rf spg-deploy && git clone https://github.com/Pablo14K/SPG.git spg-deploy && cd spg-deploy && docker compose -f docker-compose.produccion.yml -p spg up -d --build
```

Clona, reconstruye las cuatro imágenes y recrea los contenedores. Tarda unos
minutos la primera vez y bastante menos después, porque Docker reusa lo que no
cambió.

> **`-p spg` no es opcional.** Es el nombre del proyecto, y de él dependen los
> volúmenes: con `-p spg` se reusan los que ya están —`spg_datos_bd`,
> `spg_almacenamiento`, las imágenes de los servicios— y **los datos del salón
> quedan intactos**. Sin esa bandera, Compose deduce el nombre del directorio
> (`spg-deploy`) y crearía volúmenes nuevos y vacíos: el sistema levantaría
> **como si fuera una instalación de cero**.

> **Y nunca `down -v`.** En desarrollo es lo normal; acá borra la base del salón.

### Comprobar que salió

```bash
docker exec spg_app grep -m1 "'version'" config/spg.php
```

Tiene que decir la versión que acabás de subir. Y que el sitio responde:

```bash
curl -sI https://spg.columbiatcc.online/ | head -3
```

**302** hacia `/entrar` es lo correcto.

---

## 2 · También cambió la base

**¿Cómo sé si cambió?** Mirá si el commit tocó `basededatos/peluqueria_bd(base).sql`
— la regla del proyecto obliga a regenerarlo en la misma tanda que cualquier
cambio de tabla, columna, `CHECK`, vista o rutina:

```bash
git log --name-only --oneline -5 | grep -c "peluqueria_bd(base).sql"
```

**0** → es el caso 1. **Más de 0** → seguí acá.

### Paso 1 · Respaldo, antes de nada

Desde la **Consola web**:

```bash
mkdir -p /var/respaldos/spg && docker exec spg_bd sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --routines --triggers --events --single-transaction --default-character-set=utf8mb4 peluqueria_bd' > /var/respaldos/spg/peluqueria_bd_$(date +%F_%H%M).sql
```

Y comprobá que pesó algo — un archivo de 0 bytes es lo mismo que no tener nada:

```bash
ls -lh /var/respaldos/spg/
```

### Paso 2 · Subir y desplegar

Igual que el caso 1: `git push origin main` y la línea de la Consola web.

### Paso 3 · Aplicar el cambio de la base

Desde la misma **Consola web**:

```bash
docker exec spg_app sh -c 'mysql --skip-ssl -hbd -uroot -p"$DB_PASSWORD" --default-character-set=utf8mb4 peluqueria_bd < basededatos/actualizaciones/2026-09-03_7.90.0.sql'
```

Cambiá el nombre por el de la versión que estés aplicando. Los guiones viven en
`basededatos/actualizaciones/`, uno por versión que toca la base:

```bash
docker exec spg_app ls basededatos/actualizaciones/
```

> **Se pueden volver a correr sin miedo.** Una rutina se reemplaza entera
> (`DROP … IF EXISTS` + `CREATE`) y una columna se agrega sólo si falta, así que
> aplicar dos veces el mismo archivo deja exactamente lo mismo y **no toca ni
> una fila**.

### Paso 4 · Comprobar

```bash
docker exec spg_app php artisan spg:diagnostico --produccion
```

Tiene que terminar en **«Todo en orden.»** Si marca que faltan rutinas o
columnas, el guion del paso 3 no corrió.

---

## Por qué no se usa el botón «Update» del panel

Existe, y sería más cómodo, pero **en este VPS falla**: aborta con
`python3: can't open file '/.hstgr-….list.py'`, que es de la propia herramienta
de Hostinger y no del proyecto. Y falla de la peor manera —**deja los
contenedores como estaban**—, así que el panel dice que no se pudo implementar y
el sistema sigue sirviendo la versión anterior sin que nada más lo indique.

Se reconoce enseguida: los contenedores siguen mostrando el tiempo de actividad
viejo.

```bash
docker ps --format 'table {{.Names}}\t{{.Status}}'
```

Si dicen `Up 2 days` justo después de un despliegue, no se recreó nada.

> **Y el panel todavía tiene guardada la URL con `master`**, la rama que se
> borró. Si algún día querés volver a usar el botón, hay que rehacer el proyecto
> con **Componer → URL** apuntando a
> `https://github.com/Pablo14K/SPG/blob/main/docker-compose.produccion.yml`, con
> el mismo nombre de proyecto `spg`. Mientras tanto, la línea de la Consola web
> hace exactamente lo mismo y está comprobada.

---

## Si algo sale mal

| Síntoma | Dónde mirar |
|---|---|
| El `up --build` falla | lo que imprime en la consola, que ahí sí se ve entero |
| Un contenedor no levanta | `docker logs spg_bd` (o `spg_app`) |
| El sitio da 500 | `docker exec spg_app tail -30 storage/logs/laravel-$(date +%F).log` |
| El sitio no responde | ¿está Traefik levantado? `docker ps --filter name=traefik` |
| Sigue el código viejo | no se recreó: repetí el `up --build` y mirá el tiempo de actividad |
| `no such file or directory` al clonar | quedó en otra rama: el repositorio tiene **sólo `main`** |

### Volver atrás

El código sale de un commit, así que revertir es revertir el repositorio:

```bash
git revert <commit> && git push origin main
```

Y la línea del despliegue otra vez. **Si el cambio había tocado la base, el
revert del código no la revierte**: eso se arregla con SQL, o restaurando el
respaldo del paso 1.

> **Nunca `docker compose down -v` ni «Delete» del proyecto con datos del
> salón adentro.** Ahí viven las citas, las facturas y los cobros. Para empezar
> de cero se restaura desde el respaldo.

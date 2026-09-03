# Actualizar el SPG — guía de bolsillo

Para el servidor de todos los días. Lo largo —por qué cada cosa es así— está en
`DESPLIEGUE.md`; acá van sólo los pasos.

---

## Dónde se hace cada cosa

| Lugar | Cómo se llega | Para qué |
|---|---|---|
| **El panel** | hPanel → VPS → Administrador de Docker → Proyectos | desplegar, actualizar, ver logs |
| **Terminal de un contenedor** | en el panel, `Terminal` dentro de la tarjeta de `spg_app` o `spg_bd` | correr comandos del sistema o de la base |
| **Consola web** | botón arriba a la derecha en la misma pantalla | lo del servidor: respaldos, `docker` |

> **El proyecto NO está en ninguna carpeta del servidor.** El código viaja dentro
> de las imágenes; en `/docker/spg` sólo queda el compose. Por eso los comandos
> que necesitan un archivo del proyecto se corren **desde la terminal de
> `spg_app`**, donde el proyecto está en `/app`.

---

## 1 · Sólo cambió código

PHP, vistas, CSS, JavaScript. **Dos pasos.**

**1.** En tu computadora:

```bash
git push origin master
```

**2.** En el panel: menú **⋮** del proyecto → **Update**.

Eso vuelve a clonar el repositorio, reconstruye las imágenes y recrea los
contenedores. La base no se toca.

### Comprobar que salió

Terminal de **`spg_app`**:

```bash
grep -m1 "'version'" config/spg.php
```

Tiene que decir la versión que acabás de subir. **Si dice la anterior, el
despliegue no reconstruyó** — probá el camino largo: botón **Componer → URL**,
la misma dirección de siempre y **el mismo nombre de proyecto** (`spg`).

> **El reinicio no alcanza.** «Restart» levanta los mismos contenedores con la
> misma imagen, o sea el mismo código. Y como OPcache corre con
> `validate_timestamps=0`, sin reconstruir se sigue sirviendo el código viejo
> **sin que nada avise**.

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

Igual que el caso 1: `git push origin master`, y **⋮ → Update** en el panel.

### Paso 3 · Aplicar el cambio de la base

Terminal de **`spg_app`** (ahí está el proyecto y el cliente de MariaDB):

```bash
mysql --skip-ssl -hbd -uroot -p"$DB_PASSWORD" --default-character-set=utf8mb4 peluqueria_bd < basededatos/actualizaciones/2026-09-03_7.90.0.sql
```

Cambiá el nombre del archivo por el de la versión que estés aplicando. Los
guiones viven en `basededatos/actualizaciones/`, uno por versión que toca la
base; para ver los que hay:

```bash
ls basededatos/actualizaciones/
```

> **Se pueden volver a correr sin miedo.** Una rutina se reemplaza entera
> (`DROP … IF EXISTS` + `CREATE`), así que aplicar dos veces el mismo archivo
> deja exactamente lo mismo y **no toca ni una fila**.

### Paso 4 · Comprobar

Terminal de **`spg_app`**:

```bash
php artisan spg:diagnostico --produccion
```

Tiene que terminar en **«Todo en orden.»** Si marca que faltan rutinas o
columnas, el guion del paso 3 no corrió.

---

## Si algo sale mal

| Síntoma | Dónde mirar |
|---|---|
| El despliegue falla | Consola web: `tail -40 /docker/spg/.build.log` |
| Un contenedor no levanta | panel → **⋮ → View logs**, o `docker logs spg_bd` |
| El sitio da 500 | terminal de `spg_app`: `tail -30 storage/logs/laravel-$(date +%F).log` |
| El sitio no responde | ¿está Traefik levantado? Es otro proyecto en el panel |
| Sigue el código viejo | no se reconstruyó: repetí con **Componer → URL** |
| Despliega con **0 contenedores** | mirá el final del `.build.log` — ver abajo |

### «Project build failed» con las imágenes ya construidas

Si el log termina en algo como *«network X declared as external, but could not
be found»*, las imágenes se construyeron bien y lo que falló es el `up`: Compose
esperaba una red que no existe.

```bash
docker network ls
```

**Este proyecto no necesita ninguna red creada a mano.** Traefik corre en modo
`host` y alcanza los contenedores por su IP, así que el compose del SPG usa sólo
su red propia. Si volviera a aparecer una red `external`, es que alguien la
agregó — se saca y listo.

### Volver atrás

El código sale de un commit, así que revertir es revertir el repositorio:

```bash
git revert <commit> && git push origin master
```

Y **Update** otra vez. **Si el cambio había tocado la base, el revert del código
no la revierte**: eso se arregla con SQL, o restaurando el respaldo del paso 1.

> **Nunca `docker compose down -v` ni «Delete» del proyecto con datos del
> salón adentro.** Ahí viven las citas, las facturas y los cobros. Para empezar
> de cero se restaura desde el respaldo.

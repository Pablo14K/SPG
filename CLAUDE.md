# SPG — Sistema de Gestión para Peluquería

Sistema web de gestión para una peluquería de Luque, Paraguay. TCC de Ingeniería en Informática.

**Laravel 13 + MariaDB 10.4.** Para levantarlo: `README.md`. Para publicarlo: `DESPLIEGUE.md`.

> El sistema nació sin framework (PHP puro, front controller `index.php?r=…`) y se migró a
> Laravel en la versión **6.0.0**, por pedido de la tutora. Aquella versión quedó archivada.
> **La migración cambió la arquitectura, no las reglas**: las 50 rutinas de la base, los 17
> triggers y todo lo que este documento dice sobre facturación, caja, agenda, turnos y
> permisos siguen valiendo igual, porque siguen viviendo donde siempre — en la base.

## Regla número uno: la lógica de negocio vive en la base de datos

La base (`peluqueria_bd`) tiene **20 procedimientos, 30 funciones, 17 triggers y 17 vistas**,
más **54 restricciones `CHECK`**.
Laravel **consume** esa lógica, no la reimplementa: nada de reescribirla en Eloquent.
Antes de escribir un cálculo en PHP, buscá si ya existe la función o el procedimiento.

**El puente es `App\Servicios\Bd`**, que resuelve las tres cosas molestas de llamar rutinas
desde PDO: los parámetros de salida (`Bd::idDe()`), cerrar el cursor después de un `CALL`
—sin eso la consulta siguiente falla con *unbuffered queries*— y las transacciones
(`Bd::enTransaccion()`).

| Necesitás | Usá |
|---|---|
| Stock de un producto | `fn_producto_stock(id)` — nunca se guarda, se suma `movimiento_inventario` según el signo E/S |
| Agendar una cita | `sp_agendar_cita(...)` — valida disponibilidad con `fn_verificar_disponibilidad` |
| Ver qué horarios hay libres | `App\Servicios\Agenda` — arma los huecos y le pregunta a `fn_verificar_disponibilidad` cuáles sirven |
| Avisar/recordar al cliente | `App\Servicios\Notificaciones` — llena y despacha la cola de `notificacion` |
| Reprogramar / cancelar | `sp_reprogramar_cita`, `sp_cancelar_cita` |
| Emitir comprobante | `sp_emitir_factura(...)` — numera con `fn_timbrado_vigente` + `fn_siguiente_correlativo` |
| Qué se factura | **`cita_servicio`, no `servicio_realizado`.** Ver el aviso de abajo |
| Anular factura / cobro | `sp_anular_factura`, `sp_anular_cobro` — marcan estado, nunca borran |
| Nota de crédito | `sp_emitir_nota_credito(...)` — copia el detalle y numera con el timbrado del tipo 5 |
| Seña de reserva | `sp_registrar_sena(...)` — cobro atado a la cita, sin factura todavía |
| Revertir liquidación / anular pago a proveedor | `sp_revertir_pago_personal`, `sp_anular_pago_proveedor` |
| Sumar o descontar puntos | `sp_registrar_puntos(...)` — el CHECK `chk_mp_tipo` solo admite `ACUMULA` (+), `CANJE` (−) y `AJUSTE` (≠0) |
| Totales de factura | `fn_factura_subtotal / _descuento / _total / _saldo` |
| Registrar cobro | `sp_registrar_cobro(...)` — una llamada por medio de pago |
| Detalle de tarjeta / cheque | `cobro_tarjeta`, `cobro_banco` — 1 a 1 con el cobro |
| Confirmar compra | `sp_confirmar_compra(...)` — genera los movimientos de stock |
| Movimiento de stock manual | `sp_registrar_movimiento_inventario(...)` |
| Saldo de caja | `fn_caja_saldo(id)` |
| Nivel / visitas del cliente | `fn_cliente_nivel`, `fn_cliente_visitas`, `fn_cliente_puntos` |
| Comisión de un servicio | `fn_comision_servicio(id_servicio_realizado)` |
| Quién trabaja tal día | `turno_laboral` ⋈ `turno_dia` ⋈ `usuario_turno` — ver la sección **Turnos** |
| Cuánto dura una cita | `fn_cita_duracion(id)` — el bloque **más largo**, no la suma: los profesionales trabajan en paralelo |
| Cuánto le toca a uno en esa cita | `fn_cita_duracion_de(id_cita, id_usuario)` — es lo que le bloquea la agenda |
| Convertir 30 ml a stock | `consumo_a_stock()` / `stock_a_consumo()` de `app/Ayudas/formato.php` |
| La hora real del reloj | `ahora_bd()` de `app/Ayudas/formato.php`, **nunca `date()`** — ver la sección **La hora** |

Los triggers hacen cumplir reglas por su cuenta: `trg_movinv_bi` bloquea salidas sin stock,
`trg_factura_bi` valida vigencia y rango del timbrado, `trg_produtil_ai` descuenta stock al
registrar un consumo. **No hace falta duplicar esas validaciones**, pero sí conviene atrapar la `QueryException`
y traducirla con **`Bd::traducir($e, $mapa, $porDefecto)`**, que convierte el error de
MariaDB en algo que la persona entienda.

## Regla número dos: la base se mantiene normalizada, sin redundancia

**No se aceptan faltas a la 3FN ni datos repetidos.** Es un requisito del TCC: el modelo se
presenta como 3FN estricta, así que cualquier tabla o columna nueva tiene que respetarla.
Antes de agregar una columna, preguntate si ese dato ya vive en otro lado.

Las tres cosas que hay que evitar, con el ejemplo de este proyecto:

| Qué evitar | Por qué falla | Cómo se resuelve acá |
|---|---|---|
| **Valores no atómicos** (1FN) | una columna con una lista adentro no se puede consultar ni indexar | los días de un turno van en `turno_dia`, una fila por día, **nunca** `'LMXJVS'` en un `VARCHAR` (así lo hace `PersonalController::turnoGuardar`) |
| **Datos repetidos en varias tablas** | se actualiza en un lado y queda viejo en el otro | nombre, cédula, teléfono y email van **solo** en `persona`; `usuario`, `cliente` y `proveedor` la referencian |
| **Columnas derivadas guardadas** (3FN) | el valor guardado y el real se separan | el stock, los totales y los saldos **no se guardan**: los calculan `fn_producto_stock`, `fn_factura_total`, `fn_caja_saldo` |

**Datos de personas: siempre `persona`.** Si aparece una entidad nueva que tiene nombre y
contacto (un empleado externo, un contacto de proveedor), **no le agregues `nombre` y
`telefono` propios**: enlazala a `persona` con `id_persona`. Para escribir se usan
`Persona::guardar()` y `Persona::porDocumento()`, que son el único lugar que toca esa tabla.

> **Atomicidad y redundancia no son lo mismo, y conviene saber distinguirlas para el
> documento del TCC.** Que `usuario` y `cliente` repitieran el nombre **no** era una falta
> de 1FN —cada columna guardaba un solo valor—, era redundancia de entidad. La 1FN se
> rompe cuando una columna guarda varios valores juntos.

## Versión del sistema

**Se usa versionado semántico `X.Y.Z`, y hay que subirlo en cada cambio.** Vive en
`config/spg.php` (`spg.version`) y se muestra en el pie de todas las pantallas.

| Dígito | Cuándo sube | Qué significa | Ejemplo |
|---|---|---|---|
| **X** · Mayor | Cambio estructural grande | **Rompe la compatibilidad**: lo que andaba antes puede dejar de andar o necesitar configurarse de cero | 1.4.2 → **2**.0.0 |
| **Y** · Menor | Función, herramienta o característica visual nueva | **No rompe nada**: el sistema sigue funcionando igual, solo hace más cosas | 1.4.2 → 1.**5**.0 |
| **Z** · Parche | Corrección de un error, de seguridad o de una falla técnica | **No agrega nada** ni cambia la interfaz | 1.4.2 → 1.4.**3** |

Al subir un dígito, los de la derecha vuelven a cero: después de `5.1.4`, una función
nueva da `5.2.0`, no `5.2.4`.

Cuatro cosas en la misma tanda, siempre juntas:

1. `version` en `config/spg.php`;
2. `version_fecha`, con la fecha del cambio;
3. una fila en el historial de acá abajo, diciendo **qué** cambió (no «varios ajustes»);
4. **el commit** — ver acá abajo.

### Al terminar un cambio, commitealo sin que haya que pedirlo

**Terminar un cambio incluye commitearlo.** No hay que esperar a que lo pidan: cuando el
cambio está hecho, probado y con la versión subida, se commitea. Un árbol de trabajo con
veinte archivos sueltos de tres cambios distintos ya no se puede separar en commits que se
entiendan, y este repositorio es el respaldo del TCC.

Qué es «terminado»: las pruebas en verde (`php artisan test`), `spg:diagnostico` sin
observaciones si se tocó la base, y los cuatro puntos de arriba hechos.

**El mensaje sigue el formato del proyecto** — mirá `git log` antes de escribirlo:

```
X.Y.Z — qué cambió, en minúscula y en español

El porqué, no el qué: el diff ya dice qué líneas cambiaron. Lo que no se puede
recuperar después es por qué estaba mal, qué se probó y qué se decidió no hacer.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
```

Dos cosas que ya salieron mal y conviene no repetir:

- **Escribí el mensaje en un archivo y usá `git commit -F archivo`.** Con `-m` y comillas, un
  mensaje largo en español se corta o se le cuelan caracteres: los commits `3860155` y el
  primer intento de la 6.3.0 quedaron con un `@` pegado al título por usar sintaxis de
  PowerShell en Bash.
- **Si estás en `master`, abrí una rama antes** (`claude/<versión>-<tema>`). El merge a
  `master` lo decide el usuario, no se hace solo.

### Historial

| Versión | Fecha | Cambio |
|---|---|---|
| 6.5.0 | 11/08/2026 | **La espera se ve.** El sistema navega a la vieja usanza —cada clic pide una página entera— y el navegador **no muestra nada** entre el clic y la respuesta: con la base cargada, una lista con filtros o un informe tardan, y esa espera en blanco se lee como «se colgó» cuando en realidad está trabajando. Entran tres piezas, todas en oro y sin un color nuevo: una **barra de 3 px arriba de todo** mientras la página va y viene, el **ícono del botón que se vuelve spinner** sin cambiar de ancho (así la fila no salta), y un **spinner suelto** para los bloques que se llenan por `fetch` —los días y horas de la agenda, que son el peor caso: el cálculo mira turnos, citas y ausencias de 60 días—. Cuatro detalles que no son adorno: la barra **no aparece hasta los 250 ms**, porque si la respuesta llega antes el parpadeo molesta más que la espera; **las descargas no la encienden**, que si no un `?export=csv` la dejaba girando para siempre porque la página no navega; se apaga en `pageshow`, porque volver con «atrás» restaura la página con la barra tal como quedó; y **es un adorno que puede faltar** — las vistas con JS propio declaran su respaldo, así que si `app.js` no carga, agendar sigue funcionando. El refresco del portal cada 20 segundos no enciende nada a propósito. Respeta `prefers-reduced-motion` |
| 6.4.0 | 11/08/2026 | **Correcciones de la auditoría de QA del 11/08/2026** (486 operaciones sobre la base vacía, 88/100, APTO CON OBSERVACIONES). La grave: **el Profesional podía fijar cuánto cobra el salón.** El rol traía `servicios.catalogo`, `.categorias` y `.descuentos` de fábrica, y con eso bajó una coloración de 280.000 a **1.000**, la dio de baja y puso una promo al **99 %** — que `sp_emitir_factura` aplica sola. El middleware funcionaba perfecto: sobraba el permiso. Se quitan los tres del `.sql` que se entrega; sigue viendo los servicios donde los necesita (Nueva cita y Registrar atención son de `citas.*`). También: **el fichaje retroactivo sellaba la hora del reloj** —quedó registrada una entrada a las 15:06 de un día en que nadie apretó nada—, así que ahora se pide la hora y tiene que caer dentro del turno; **el teléfono no se validaba** y entraba `abc-!!!`; **`persona.direccion` no la capturaba ninguna pantalla**; y la auditoría de un cambio de precio decía sólo el nombre del servicio, ahora deja **de cuánto a cuánto**, igual que la matriz de permisos, que anota qué clave ganó y cuál perdió cada rol. Entra **`.env.produccion.example`**, porque desplegar con el `.env` de desarrollo deja `APP_DEBUG=true` (traza con la contraseña de la base a la vista) y enlaces de correo apuntando a `localhost`. **La contraseña de Gmail sale del repositorio**: `env.docker` vuelve a `MAIL_MAILER=log`. **51 pruebas** |
| 6.3.2 | 11/08/2026 | **Registrar atención dejaba de andar por dos motivos distintos, y los dos mentían.** Uno: cargar un producto sin stock contestaba «marcaste un producto en un servicio que no quedó como realizado» —un mensaje que no tiene nada que ver y manda a corregir lo que no está mal—. La causa es de manual: **`QueryException` hereda de `PDOException`, que hereda de `RuntimeException`**, así que el `catch (RuntimeException)` se comía todos los errores de la base y el `catch (Throwable)` de abajo, que sí tenía el mensaje de stock, era **código inalcanzable**. Dos: para una cita futura pedía fichar el día de la cita, y Asistencia rechaza fichar un día que no llegó — la persona quedaba dando vueltas entre dos pantallas, y en el mes simulado eso pasa en **83 de 172 citas**. Ahora se distingue «la cita todavía no llegó» de «falta el fichaje», y cuando falta de verdad **se ficha desde la misma pantalla**, sin ir a Asistencia y volver. De paso, el aviso decía «se agregaron N servicios que no estaban en la cita» **siempre**, porque `DB::insert()` devuelve si la consulta corrió, no si escribió una fila, y con `INSERT IGNORE` eso es `true` igual. El checkbox de Servicios pasa a llamarse **«Requiere atención exclusiva»**. Notas de crédito **verificadas** de punta a punta: numeran con el timbrado del tipo 5, copian el detalle, van con signo −1 y revierten los puntos. **50 pruebas** |
| 6.3.1 | 11/08/2026 | **El contenedor vuelve a arrancar contra `peluqueria_test`**, el mes simulado, para poder mirar las pantallas con datos de verdad: una lista vacía no muestra si la paginación, los filtros y las dos exportaciones andan. Es la línea `DB_DATABASE` de `docker/php/env.docker`, y se documenta en los dos lados **cómo se cambia** —basta `docker compose restart app`, sin `down -v`, que además borraría las bases— porque el contenedor ya crea e importa las dos al arrancar. **Antes de entregar hay que volver a `peluqueria_bd`**, que es la que se instala en el salón |
| 6.3.0 | 11/08/2026 | **Los listados también bajan en PDF**, con la misma decisión que ya estaba tomada en Informes: no hay librería, se maqueta para A4 y el navegador guarda como PDF. Una sola vista (`listado/imprimir`) sirve a las doce listas y deja escritos los filtros en el papel, porque si no dos PDF de la misma pantalla salen idénticos de encabezado. De paso aparecieron **dos pantallas rotas de verdad**: `webauthn/preguntar` se dibujaba **sin una línea de JavaScript** —`auth/marco` no tenía `@stack('scripts')`, así que todo el `@push` se perdía en silencio— y dejaba al usuario nuevo **encerrado** entre el ingreso y el panel, con los dos botones muertos; y **exportar la auditoría devolvía 500** desde siempre, porque `auditoria()` declaraba `: View` y devolvía un archivo. Además, **las altas rápidas dejan de borrar lo cargado**: la migración a Laravel se quedó a mitad de camino —`app.js` seguía adjuntando el borrador y ya no lo leía nadie—, así que crear una sucursal desde la ficha vaciaba nombre, apellido, usuario y email. Lo resuelve `App\Servicios\Borrador`, que **no** es `withInput()` a secas: el alta rápida manda su propio formulario, y varios de sus campos se llaman igual que los del grande (`nombre` está en los dos). Se corrigen los días **Mié** y **Sáb**, que salían cortados a mitad de byte por `substr`, y se sacan dos avisos que le explicaban a la persona un permiso que ya tenía. En el portal, el «¿Con quién?» de toda la cita se va: cada servicio ya trae el suyo. **49 pruebas** |
| 6.2.0 | 11/08/2026 | **Personal y Configuración se unen en un solo módulo, Seguridad**, con el fondo oscuro que tenía Configuración: las dos tarjetas contestaban la misma pregunta —quién entra, qué puede hacer y qué quedó registrado— y separadas obligaban a saltar de una a la otra para dar de alta a alguien y darle permisos. Los módulos pasan de 8 a 7 y los permisos siguen siendo **28**, ahora ocho submódulos de `seguridad`. Las rutas quedan bajo `/seguridad` y las trece vistas en una sola carpeta. **Lo que estaba guardado no se pierde ni crece**: `permisos.equivalencias` traduce las claves viejas al leerlas, y el módulo padre viejo se traduce a **sus** submódulos, no a `seguridad` a secas — traducirlo al padre nuevo le habría regalado los roles y la auditoría a quien solo administraba al personal. De paso, la prueba nueva que abre las doce pantallas destapó que **Auditoría nunca había funcionado en Laravel**: consultaba `a.fecha` y la columna es `fecha_hora`, así que devolvía 500 desde la 6.1.1 |
| 6.1.5 | 11/08/2026 | **Se termina de pasar esta documentación a Laravel.** En la 6.1.1 se reescribieron las secciones de arquitectura, pero en las demás quedaban 16 referencias a archivos y funciones del sistema archivado —`canales_contacto()` de `helpers.php`, `relaciones_pantallas()` de `view.php`, los `migrar_*()`, `personal_activo()`, `install.php`—, que mandaban a buscar código que en este proyecto no existe. Ahora nombran lo real: `Contacto::canales()`, `config/navegacion.php`, `Permisos::esAdmin()`, `FacturacionController::pagarProveedor`. Cada nombre se verificó contra el código antes de escribirlo. Las únicas menciones al sistema viejo que quedan están marcadas como historia, a propósito |
| 6.1.4 | 11/08/2026 | **El aviso del ingreso con huella dejaba de mentir.** Desde el celular, entrando por la IP de red de la PC, decía «este equipo no tiene lector» — falso, y manda a buscar un problema de hardware inexistente. Lo real es que los navegadores **sólo exponen WebAuthn en contexto seguro** (HTTPS o `localhost`), así que por `http://192.168.x.x` la API no existe; y detrás hay una segunda pared: el `rpId` sale del dominio y la especificación **no admite direcciones IP**. `SPGBio.estado()` ahora distingue los cuatro casos —sin HTTPS, por IP, navegador viejo, sin sensor— y cada uno dice qué pasa y si va a andar en el servidor |
| 6.1.3 | 11/08/2026 | **El correo del contenedor sale de verdad**: `docker/php/env.docker` pasa de `log` a SMTP por Gmail, así que el código de verificación, la recuperación de contraseña, el segundo factor y los recordatorios llegan al buzón. Al configurarlo aparecieron **tres nombres de variable obsoletos** que circulan en apuntes viejos: Laravel 13 lee `MAIL_MAILER`, `MAIL_SCHEME` y `MAIL_FROM_ADDRESS`, **no** `MAIL_TRANSPORT`, `MAIL_ENCRYPTION` ni `MAIL_FROM_EMAIL` — con los viejos el sistema se queda en `log` y no manda nada sin dar ningún error. El remitente además tiene que ser la misma cuenta que se autentica, porque Gmail rechaza un `From` de otro dominio. **Ojo: este archivo lleva ahora una contraseña de aplicación de Google**; se revoca desde `myaccount.google.com/apppasswords` sin tocar la cuenta |
| 6.1.2 | 10/08/2026 | **El contenedor arranca contra `peluqueria_bd`, la base vacía**, y no contra la cargada: así lo que se ve al entrar es el sistema tal como lo encuentra el salón el primer día. `peluqueria_test` se sigue creando igual —es contra ella que corren las 38 pruebas, y eso lo fija `phpunit.xml` por su cuenta—, así que se cambia una línea de `docker/php/env.docker` para trabajar con los datos del QA en pantalla |
| 6.1.1 | 10/08/2026 | **El proyecto pasa a ser autosuficiente y esta documentación deja de describir el sistema viejo.** Los dos `.sql`, el guion de limpieza y la paleta se mudan adentro (`basededatos/`, `Referencias/`), así que la carpeta se puede sacar de `Sistema_Gestion_Peluqueria` y el proyecto anterior se archiva — antes, movida de lugar, el `docker compose up` importaba dos bases vacías sin avisar. Se reescriben para Laravel las secciones de **Arquitectura**, **Convenciones**, **listados**, **altas rápidas**, **submódulos**, **la hora**, **entorno**, **publicación** y **migraciones**, y se agrega una de **pruebas** |
| 6.1.0 | 10/08/2026 | **El proyecto se puede levantar en otra computadora sin armar el entorno a mano**: `docker compose up` fija MariaDB 10.4 (que es lo que importa: las 54 CHECK y las 50 rutinas están escritas para ese motor), importa las dos bases solo y clava la zona horaria. Convive con XAMPP — la base va al 3307. Además se corrigen tres cosas que hacían fallar la entrega: el `.env.example` era el genérico de Laravel y mandaba sesiones, caché y colas a `database`, o sea que el framework creaba **sus tablas dentro de la base del TCC**; el `README.md` seguía siendo el de Laravel; y **`basededatos/1mes_simulacion.sql` había quedado viejo** —48 rutinas en vez de 50, sin `fn_promo_vigente` ni `fn_descuento_monto_factura` de la 5.5.0—, así que se regeneró |
| 6.0.0 | 10/08/2026 | **El sistema pasa a Laravel 13**, por pedido de la tutora. Cambia la arquitectura, no lo que hace: los 8 módulos, el portal de la clienta y las cuentas quedan iguales, con el mismo Bootstrap y la misma paleta. **La lógica sigue en la base**: las 50 rutinas y los 17 triggers no se tocaron, Laravel los consume. Entra lo que el framework aporta —middleware y Gates para los 28 permisos, componentes Blade, Mailables, scheduler— y una **batería de 38 pruebas** que cubre lo que el QA del mes había validado a mano: concurrencia de la agenda con procesos en paralelo, arqueo de caja, correlativos sin huecos y la jerarquía de permisos. Sube la **X** porque el despliegue se hace de cero |
| 5.5.0 | 08/08/2026 | **Las promociones con vigencia por fin se aplican solas.** `sp_emitir_factura` ya no mira únicamente el descuento del nivel: compara el del nivel con la mejor promoción vigente y aplica **el que más le convenga al cliente, nunca los dos sumados**. Pantalla nueva para elegir **a qué servicios aplica** una promo (`servicio_descuento`, que existía en la base y no tenía cómo cargarse). Además, **el saldo de caja pasa a ser el arqueo físico**: sólo lo mueve el efectivo, y un pago al proveedor por transferencia ya no vacía el cajón. Un egreso en efectivo mayor al disponible se rechaza, así la caja no queda en negativo |
| 5.4.1 | 08/08/2026 | Correcciones salidas de la simulación de un mes (ver `Pruebas_QA/INFORME_QA_MES.md`). **Una compra con el nombre mal tipeado ya no crea un producto duplicado**: la pantalla manda el id cuando el producto existe, el servidor compara el nombre normalizado, y si igual resulta nuevo lo dice por su nombre. **La franja del fichaje se valida en el servidor**, no sólo en la vista. El **Profesional pierde `facturacion.pagos` y `facturacion.proveedores`**, que le dejaban pagarle a proveedores y revertir liquidaciones ajenas. `persona_error()` valida largos y formato de cédula/RUC (antes MariaDB recortaba en silencio). Los avisos internos de stock se cierran solos al reponer. El rechazo por CSRF devuelve **403 con una pantalla explicada**, no un 500 |
| 5.4.0 | 07/08/2026 | Los ocho módulos se dan **por partes**, no todo o nada: 28 permisos en vez de 8. Configuración → Roles se rehace con un bloque por rol y una casilla maestra por módulo. La migración pasa cada permiso de módulo entero a sus submódulos **sin repartir Timbrados ni Excepciones**, que eran del Administrador: el alcance de cada rol queda igual que antes |
| 5.3.0 | 07/08/2026 | Cita con varios profesionales a la vez (`cita_servicio.id_usuario`, la cita dura el bloque más largo) y servicios que ocupan a la clienta entera. Consumo fraccionado de productos (30 ml de un frasco de 1 L), portal de la clienta durante la atención con desglose en vivo y pedidos, informes con vista de impresión y varios medios de contacto en el pie. Además, los timbrados vuelven a ser del Administrador: al mudarlos a Facturación quedaban al alcance del Profesional |
| 5.2.0 | 07/08/2026 | Timbrados pasa a Facturación y Catálogos se reparte (categorías de producto a Inventario, niveles a Fidelización). Turnos solapados validados, alta rápida de turno y de proveedor, aviso de stock por reponer, agenda limitada al profesional, fichaje obligatorio para atender, caja obligatoria en todo movimiento de dinero, vuelto en el cobro y período Histórico en informes |
| 5.1.0 | 06/08/2026 | El cambio de contraseña pide un segundo factor: código al correo. Función nueva y nada de lo anterior deja de andar, así que sube la **Y** |
| 5.0.0 | 06/08/2026 | Punto de partida del versionado. Turnos por días, permisos por submódulo, asistencia por fichaje, prototipo de listado con filtros y paginación, informes parametrizados y Centro de Ayuda y Soporte |

## Arquitectura

Laravel 13 sobre PHP 8.3, con **150 rutas declaradas una por una** en `routes/web.php` — nada
de `Route::resource`, porque las pantallas de este sistema no son un CRUD parejo.

**Lo que NO se usa de Laravel, y es a propósito:**

| No se usa | Por qué |
|---|---|
| **Eloquent** para el negocio | la lógica vive en la base. Se consulta con `DB::select()` y se llama a las rutinas con `Bd::` |
| **Migraciones** | el esquema viene de `basededatos/peluqueria_bd(base).sql`. `database/migrations/` está vacío a propósito: correr `artisan migrate` crearía tablas de Laravel dentro de la base que se entrega |
| `database` como driver de sesión, caché y cola | por lo mismo. Van a **archivo** (ver `.env.example`) |
| Vite / Node | Bootstrap viene por CDN y `app.css` es un archivo propio. En el servidor no se compila nada |

```
app/
  Ayudas/formato.php       Funciones globales, cargadas por composer («files»):
                           money() cant() num() entero() fecha() fecha_larga()
                           ahora_bd() recurso() flash() estado_badge()
                           producto_fraccionado() consumo_a_stock() stock_a_consumo()
  Servicios/               La capa propia. Todo estático, sin estado.
    Bd.php                 El puente a las rutinas: idDe() enTransaccion() traducir()
    Agenda.php             Huecos, reparto entre profesionales, agendar con candado
    Permisos.php           Los 28 submódulos y su jerarquía
    Sesion.php             Ingreso y datos de la sesión
    Seguridad.php          Códigos de un solo uso (token_seguridad)
    WebAuthn.php           Huella en PHP puro (CBOR, COSE→PEM, OpenSSL)
    Facturacion.php        Emitir, cobrar, anular, nota de crédito, puntos
    Caja.php               Caja abierta y saldo
    Persona.php            El único lugar que escribe en `persona`
    Notificaciones.php     Cola de avisos: ausencias, bajas y recordatorios
    Calendario.php         Archivo .ics de la cita (hora flotante, ver su sección)
    Listado.php            Prototipo de listas: filtros(), paginacion(), exportar() CSV/PDF
    Borrador.php           No perder lo escrito al usar un alta rápida
    Navegacion.php         Migas, accesos rápidos y catálogo de pantallas
    Auditoria.php          registrar() registrarComo() anotarMotivo()
    Contacto.php           Centro de Ayuda y Soporte
  Http/Controllers/        Uno por módulo, más Auth, Cuenta, Portal, CitaToken, Webauthn.
                           La excepción es Seguridad: son 1500 líneas y no gana nada
                           juntarlas, así que SeguridadController tiene sólo el landing y
                           las pantallas siguen repartidas entre PersonalController
                           (usuarios, turnos, comisiones, asistencia) y
                           ConfiguracionController (sucursales, roles, contacto, auditoría)
  Http/Middleware/         ExigeSesion · ExigePersonal · ExigeModulo · ExigeAdmin
  Mail/                    AvisoCita · CodigoSeguridad
  Console/Commands/        spg:diagnostico · spg:preparar-sql · spg:notificaciones
config/
  spg.php                  Versión, puntos, agenda, timbrado
  navegacion.php           Los cuatro niveles de navegación, en un solo lugar
  permisos.php             Los 28 submódulos
resources/views/
  layout/app.blade.php     Encabezado, barra de módulos y pie: envuelve todo
  components/              <x-encabezado> <x-filtros> <x-paginacion> <x-landing>
  <modulo>/                Una carpeta por módulo
routes/
  web.php                  Las 150 rutas, agrupadas por módulo con su middleware
  console.php              El scheduler: spg:notificaciones cada diez minutos
public/assets/             app.css · imprimir.css · app.js · webauthn.js
basededatos/               Los .sql (ver «Solo hay DOS archivos .sql»)
tests/Feature/             Las 51 pruebas
```

> **Las rutas son explícitas y eso resuelve un problema que el sistema viejo tenía.** Antes el
> router armaba la URL desde el nombre de la función, así que un ayudante interno llamado
> `reportes_datos()` quedaba servido en `?r=reportes/datos`: se lo podía llamar sin argumentos
> y **sin pasar por el guardia** de la pantalla real. Con `routes/web.php` eso no puede pasar:
> lo que no está declarado no es alcanzable, y un método privado del controlador tampoco.

## Convenciones al escribir código

- **Todo en español**: nombres de métodos, variables, comentarios y mensajes al usuario. Los
  nombres de clase van en `PascalCase` y los métodos en `camelCase`, como pide PSR-12, pero
  **en español** (`Agenda::motivoHuecoPerdido()`, no `lostSlotReason()`). Los mensajes le
  hablan de vos al usuario («Elegí un proveedor», «No podés…»), en tono paraguayo.
- **Toda acción POST valida en el servidor.** El patrón del proyecto es: leer con `$request`,
  encadenar `if/elseif` sobre una variable `$error`, y si hay error `flash($error, 'error')`
  más `redirect()`. Las validaciones del navegador (`required`, `pattern`) son una ayuda, no
  la garantía.
- **Nunca `(float) $request->input(...)` para montos.** Los campos de dinero se muestran con
  separador de miles («7.000»), así que se parsean con `num()` (y `entero()` para cantidades
  enteras).
- **En las vistas, `{{ }}`**, que escapa solo. `{!! !!}` sólo con HTML que armó el propio
  sistema, nunca con algo que vino del usuario.
- **CSRF**: `@csrf` en todo formulario. Laravel lo verifica solo; el rechazo devuelve **419**,
  con su pantalla en `resources/views/errors/`.
- **Auditá lo que importa** con `Auditoria::registrar($accion, $modulo, $tabla, $id, $detalle)`.
  **Pero fijate primero si la base ya lo audita sola.** Los triggers `trg_factura_au`,
  `trg_cobro_au`, `trg_pagopersonal_au` y `trg_pagoproveedor_au` escriben en `auditoria`
  cuando el estado pasa a anulado o revertido, tomando el usuario de `@usuario_actual`
  (que dejan puesto los `sp_anular_*` / `sp_revertir_*`). En esos casos llamar a `registrar()`
  deja **dos filas por la misma acción**: usá `Auditoria::anotarMotivo($tabla, $id, $motivo)`,
  que le agrega el motivo a la fila que escribió el trigger.
- **Cuidado con `catch (RuntimeException)`: se come los errores de la base.**
  `Illuminate\Database\QueryException` hereda de `PDOException`, que hereda de
  `RuntimeException`. Así que un `catch (RuntimeException)` puesto para atrapar los avisos
  propios **también atrapa todo lo que levantan los disparadores y procedimientos**, y un
  `catch (Throwable)` escrito debajo para traducirlos queda **inalcanzable**: no se ejecuta
  nunca y no hay forma de notarlo leyendo el código. Le pasó a «Registrar atención», donde un
  producto sin stock contestaba «marcaste un producto en un servicio que no quedó como
  realizado». **El `catch (QueryException)` va primero**, con `Bd::traducir()`, y después el
  de las excepciones propias.
- **Verificá pertenencia**: que la cita sea de ese cliente, que la factura no esté anulada, etc.
  No confíes en los campos ocultos del formulario (ej. el cliente se toma de la cita, no del POST).
- Los `id` de catálogos se validan contra la base antes de usarlos.
- **Nunca repitas un marcador con nombre en una consulta.** La conexión abre PDO con
  `ATTR_EMULATE_PREPARES` en `false`, así que MySQL prepara de verdad y **no admite `:q` dos
  veces**. Para buscar en varias columnas está `Listado::likeVarias()`.

## Identidad visual (preferencia del usuario — respetarla siempre)

La paleta está definida en `Referencias/WhatsApp Image 2026-07-14 at 18.33.52.jpeg` y vive
como variables CSS al principio de `public/assets/css/app.css`. **No inventar colores nuevos
ni usar los de Bootstrap.**

**Base — neutros de la identidad.** Se usan neutros *cálidos* a propósito, no blancos ni
grises puros: le dan calidez al conjunto y combinan mejor con el oro que los tonos fríos.
La diferencia es sutil pero se siente.

| Color | Hex | Dónde |
|---|---|---|
| Negro rico | `#0D0D0D` | nav · footer |
| Carbón | `#1A1A1A` | hero · secciones |
| Gris oscuro | `#555555` | texto muted |
| Gris cálido | `#E0DDD8` | bordes |
| Blanco hueso | `#F7F5F2` | fondo de página |
| Blanco puro | `#FFFFFF` | cards |

**Acento — oro champagne.**

| Color | Hex | Dónde |
|---|---|---|
| Oro principal | `#C9A84C` | botones · logo |
| Oro claro | `#E8CC80` | hover · íconos |
| Oro oscuro | `#8A6C1E` | activo · pressed |

> **Regla del oro:** que aparezca **solo donde hay acción o jerarquía** — botón de reserva,
> nombre del local, precio destacado, hover de menú. **Si se usa en demasiados lugares pierde
> impacto.** Para texto y bordes van los neutros cálidos, nunca el oro.

Dónde está permitido el oro hoy: botones primarios (`.btn-oro`), logo y nombre del local,
hover y estado activo (tarjetas, menú, pestañas), casilla marcada, anillo de foco, importes
destacados (`.val.oro`, saldo de caja), el badge del estado **En proceso**, los accesos
rápidos (`.spg-chip`) y las altas rápidas dentro de un formulario (`.btn-rapido`).

**Tres intensidades de oro, según el peso de la acción** — respetarlas para que la jerarquía
se lea sola:

| Nivel | Clase | Aspecto | Uso |
|---|---|---|---|
| Principal | `.btn-oro` | relleno oro macizo | la acción principal de la pantalla (una sola) |
| Secundario | `.spg-chip`, `.btn-rapido` | tres estados, ver abajo | atajos de navegación y altas rápidas |
| Terciario | `.spg-rol-chip`, `.e-warn` | solo contorno, fondo transparente | información con jerarquía, no acción |

El nivel secundario tiene **tres estados** y hay que mantener los tres:

| Estado | Fondo | Texto | Contraste | Para qué |
|---|---|---|---|---|
| Reposo | `--oro-suave` `#F1E3B9` | `--oro-texto` `#6B5314` | 5,7:1 | se lee dorado de una |
| Hover | `--oro` `#C9A84C` | `--negro` | 8,5:1 | mismo peso que el botón principal |
| Al tocar (`:active`) | `--blanco` | `--negro` | 19,4:1 | destello que confirma el clic |

Dos avisos para no romperlo:

- **`--oro-suave` es para fondos de acción; `--oro-tinte` (`#FBF1D8`, más pálido) es para
  badges y avisos.** No intercambiarlos: el tinte de los badges sobre un botón se ve casi
  blanco, que es justo lo que se quiso evitar.
- **Sobre `--oro-suave` el texto va en `--oro-texto`, no en `--oro-oscuro`.** Con el oro
  oscuro el contraste cae a 3,9:1 y la letra chica no cumple AA.

`.btn-rapido` define sus estados con las variables `--bs-btn-*`, no con reglas sueltas:
si no, el estado activo propio de Bootstrap pisa el nuestro.

Lo neutro (`.btn-outline-neutro`) queda para Cancelar, editar, activar/desactivar y demás
acciones secundarias que **no** son atajos ni altas rápidas. Si todo fuera dorado, el botón
principal dejaría de destacarse.

**Colores semánticos** — los únicos fuera de la identidad, y solo para comunicar estado,
nunca para decorar. Están declarados como variables; no escribir hex sueltos en las vistas
(hay utilidades `.txt-ok`, `.txt-no`, `.txt-oro`).

| Variable | Hex | Significado |
|---|---|---|
| `--verde` / `--verde-tinte` | `#2F5D2F` / `#E9EFE6` | salió bien (Atendida, Emitida, Abierta) |
| `--rojo` / `--rojo-tinte` | `#993535` / `#F7ECEC` | se anuló o se canceló |
| `--oro-tinte` | `#FBF1D8` | acento suave (estado vivo, avisos) |

Criterio de los badges de estado (`estado_badge()` + clases `.e-*`): lo que está **en curso**
lleva el acento dorado, lo que está simplemente **agendado o cerrado** va en neutros cálidos,
y el **resultado** en los semánticos. Así el badge dorado señala algo en vez de ser adorno.

**Bootstrap trae su propio azul (`#0d6efd`) y grises fríos compilados.** En `app.css` están
sobrescritas las variables `--bs-*` y, además, pisados a mano los componentes que traen el
color compilado y no salen por variable: `.form-check-input:checked` (casillas e interruptores),
`.nav-pills`, `.dropdown-menu`, anillos de foco, tablas y alertas. **Si agregás un componente
nuevo de Bootstrap, revisá que no aparezca azul**; si aparece, sumá el override ahí.

## Interfaz

- Bootstrap 5.3 + Bootstrap Icons **por CDN**, con la paleta de arriba aplicada encima.
- **Cuatro niveles de navegación, y cada uno responde una pregunta distinta.** Si se saca
  alguno, la anterior vuelve a quedar sin respuesta:
  | Nivel | Dónde | Qué responde |
  |---|---|---|
  | Barra de módulos (`.spg-nav`) | fija bajo el encabezado | *¿a qué otro módulo voy?* — el actual va marcado en oro |
  | Migas (`.spg-migas`) | arriba del título | *¿dónde estoy y cómo vuelvo?* |
  | Tarjetas | panel → módulo → submódulos | *¿qué hay dentro de este módulo?* |
  | Accesos rápidos (`.spg-chip`) | bajo las migas | *¿qué suelo hacer después de esto?* |
  Los dos primeros salen solos del encabezado; los accesos rápidos se configuran en
  `config/navegacion.php`, no en cada vista.
- El **pie** tiene cuatro bloques: identidad, **Secciones** (los módulos del rol, en tres columnas),
  **Centro de Ayuda y Soporte** y la **versión**. Se dice «Secciones» y no «Módulos» porque
  módulo es la palabra del desarrollo, no la de quien usa el sistema.

### Centro de Ayuda y Soporte

Por dónde el cliente le escribe al salón. Se cargan en **Seguridad → Contacto y soporte**
y salen en el pie; si no hay ninguno, el bloque no se dibuja.

**Son varios, no uno.** `contacto_soporte` es una lista (`id_contacto`, `canal`, `valor`,
`etiqueta`, `orden`): el salón puede publicar dos WhatsApp, un Instagram y un correo, y
ordenarlos. Los canales posibles se declaran en **`Contacto::canales()`**
—hoy WhatsApp, Telegram, Instagram, Facebook, teléfono, correo y sitio web— y de ahí salen
solos el selector del formulario, el ícono y el texto de ayuda; para sumar otro alcanza con
agregarlo ahí. La `etiqueta` propia le gana a la del canal, porque con dos WhatsApp hace
falta distinguirlos («WhatsApp» y «WhatsApp turnos»).

**Un contacto mal cargado no dibuja un enlace roto**: `Contacto::delSalon()` descarta la fila
cuyo valor `Contacto::url()` no supo convertir, en vez de publicar un `href` que no lleva a
ningún lado.

**Es UNO para todo el salón, no uno por sucursal.** Se evaluó por sucursal y no cierra: el
cliente entra por un único portal y no está atado a ningún local — `usuario.id_sucursal` es
NULL para los clientes y `cita` tampoco guarda sucursal (se deduce del profesional, y a la
misma persona la puede atender gente de locales distintos). El pie tendría que elegir una
sucursal sin ningún criterio. El canal de soporte es del negocio, no del local.

`Contacto::url()` arma el enlace del chat y acepta las tres formas en que la gente tiene
guardado su contacto: el enlace entero, un usuario o canal, o el número.

- **El número se normaliza antes de armar el enlace** (se le sacan espacios, guiones y
  paréntesis, y se le antepone el código de país). Sin eso, un
  `0981123456` escrito como se marca acá daba `wa.me/0981123456`: un enlace que abre y no
  encuentra a nadie. `wa.me` va sin el `+`, `t.me` con él.
- **Un usuario de Telegram tiene que empezar con letra.** Con `[A-Za-z0-9_]` a secas, un
  número entraba como usuario y armaba `t.me/0981123456`.
- **Solo se aceptan `http` y `https`.** El valor termina en un `href` del pie de todas las
  pantallas: sin esa comprobación, alguien con acceso a Configuración podría guardar un
  `javascript:` y dejarlo inyectado en todo el sistema.
### La espera tiene que verse

El sistema navega a la vieja usanza: cada clic pide una página nueva y **el navegador no
muestra nada** entre el clic y la respuesta. Con la base cargada, una lista con filtros o un
informe tardan lo suyo, y esa espera en blanco se lee como «se colgó» cuando en realidad
está trabajando. Son tres piezas, y cada una responde a una espera distinta:

| Pieza | Cuándo | Qué hace |
|---|---|---|
| `.spg-barra-carga` | la página entera se está yendo a buscar | barra de 3 px arriba de todo, en oro |
| `.btn.cargando` | ese botón disparó algo y está esperando | el ícono se vuelve spinner, sin cambiar el ancho |
| `.spg-spinner` | un pedazo de pantalla se llena por `fetch` | spinner suelto, con `.spg-cargando-texto` al lado |

Se maneja con **`SPGCarga`** (`app.js`), y las pantallas con JS propio lo usan en vez de
inventar el suyo:

```js
SPGCarga.envolver(fetch(url), bloqueQueSeVaARehacer)   // barra + atenúa el bloque
SPGCarga.ocupar(boton) / SPGCarga.liberar(boton)
```

Cuatro detalles que no son adorno y conviene no perder al tocarlo:

- **La barra no aparece hasta los 250 ms.** Si la respuesta llega antes, un parpadeo molesta
  más que la espera.
- **Las descargas no la encienden.** Un `?export=csv` baja un archivo y la página se queda
  donde está: la barra quedaría girando para siempre. Tampoco la encienden las anclas, los
  `target="_blank"`, `mailto:`, ni el clic con Ctrl.
- **Se apaga en `pageshow`.** Volver con «atrás» restaura la página desde la caché del
  navegador con la barra tal como quedó.
- **Es un adorno, y tiene que poder faltar.** Las vistas con JS propio declaran
  `var SPGCarga = window.SPGCarga || { envolver: function (p) { return p; } };`, así que si
  `app.js` no cargó, agendar sigue funcionando. Misma idea que la salida de la pantalla de la
  huella.

El refresco automático del portal durante la atención **no** enciende nada: pasa cada 20
segundos por su cuenta, y una barra parpadeando sola sería peor que el silencio.

- **Un `@push('scripts')` sólo llega si el layout tiene `@stack('scripts')`.** Si no, Blade
  **no avisa**: la pantalla se dibuja entera y sin una línea de JavaScript. Hoy lo declaran
  `layout/app.blade.php` y `auth/marco.blade.php`; **si agregás un layout, acordate del
  stack**. Es lo que dejó a `webauthn/preguntar` con los dos botones muertos, y como esa
  pantalla se mete entre el ingreso y el panel, el usuario nuevo quedaba **encerrado**.
  > De ahí sale la otra regla: **la salida de una pantalla no puede depender del JavaScript.**
  > «Ahora no» es un `<form>` de verdad y su acción contesta redirect o JSON según quién
  > llame, así que funciona con el JS roto. Si hacés una pantalla de la que haya que poder
  > salir, dejale una salida que ande sin scripts.
- **CSS y JS se enlazan con `recurso('css/app.css')`, no con el `asset()` de Laravel.**
  `recurso()` le pega la fecha de modificación del archivo como `?v=`; sin eso el navegador se
  queda con la versión vieja en caché y los cambios de estilo no se ven.
- Campos de dinero: `class="input-miles"` (+ `data-decimales="2"` si admite fracciones,
  `data-min` / `data-max`). El JS los formatea al escribir y `num()` los interpreta en el servidor.
- Acciones destructivas o irreversibles: `data-confirmar="¿Texto de la advertencia?"` en el botón.
- Buscador sobre un `<select>` largo dentro de un formulario: `<input data-filtra="#idDelSelect">`
  o `data-filtra=".clase"`. **Es solo para filtrar opciones en el navegador**, no es el buscador
  de una lista: para eso está el prototipo de listado de abajo.

### El prototipo de listado: filtros y paginación

**Todas las pantallas de lista se dibujan igual.** Antes cada una resolvía esto por su cuenta
—o no lo resolvía: había **un solo buscador en todo el sistema** (Clientes) y **ninguna lista
paginaba**. Las consultas cortaban con `LIMIT 200` sin avisar, así que a partir de la fila 201
los datos no existían para el usuario. Eso es peor que no paginar, porque no se nota.

Las piezas son `App\Servicios\Listado` y tres componentes Blade:

```php
// En el controlador
$f = Listado::filtros([
    'q'      => ['tipo' => 'texto',  'etiqueta' => 'Buscar', 'ph' => 'Nombre o cédula'],
    'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado', 'opciones' => ['' => 'Todos', '1' => 'Activos']],
    'desde'  => ['tipo' => 'fecha',  'etiqueta' => 'Desde'],
]);

$w = ['1=1'];
$par = [];
if (Listado::hay($f, 'q')) {
    $w[] = Listado::likeVarias(['pe.nombre', 'pe.cedula'], Listado::valor($f, 'q'), 'q', $par);
}
$desde = 'FROM … WHERE ' . implode(' AND ', $w);

if (Listado::pideExport()) {                    // «csv» o «pdf»
    return Listado::exportar('clientes', ['Cliente', 'Cédula'], $filas, $f, 'Clientes');
}

$pag  = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));
$filas = DB::select("SELECT … $desde ORDER BY … LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par);
```

```blade
{{-- En la vista --}}
<x-encabezado sub="…" :accion="['ruta' => 'clientes.form', 't' => 'Nuevo cliente', 'ic' => 'person-plus']" />
<x-filtros :f="$f" />
<x-paginacion :pag="$pag" :f="$f" />
```

Cuatro reglas al sumar una lista nueva:

- **El `WHERE` se arma una sola vez** y lo comparten el `COUNT(*)` y la consulta de la página.
  Si se separan, el «de 137» del pie deja de coincidir con lo que se ve.
- **Nunca repitas un marcador con nombre.** MySQL prepara de verdad (`ATTR_EMULATE_PREPARES`
  en `false`) y **no admite `:q` dos veces**. La búsqueda de Clientes usaba `:q` cuatro veces
  y reventaba con *Invalid parameter number* apenas se escribía algo: el único buscador del
  sistema no funcionaba. Para eso está `Listado::likeVarias()`.
- **Los filtros van por GET**, así el resultado tiene su propia URL y se puede compartir o
  recargar. `Listado::filtros()` ya sanea: un `select` solo acepta una opción que exista y una
  fecha solo acepta `Y-m-d`, así que lo que devuelve entra directo en la consulta preparada.
- **Lo que se baja es lo filtrado sin límite de página**, no la página que se está viendo: si
  la persona filtró marzo, el archivo trae todo marzo. `Listado::csv()` le pone el BOM (si no,
  Excel en Windows abre el archivo en ANSI y rompe las ñ) y separa con punto y coma.
- **Se baja en CSV o en PDF**, y los dos salen de las mismas filas que arma el controlador:
  el CSV para seguir trabajando los datos en una planilla, el PDF para imprimirlo o mandarlo
  sin que el que lo recibe tenga que abrir Excel. **No hay librería de PDF**: `Listado::pdf()`
  dibuja `listado/imprimir` maquetada para A4 y el navegador la guarda, la misma decisión que
  ya estaba tomada en Reportes. El PDF **deja escritos los filtros** en el encabezado, porque
  si no dos exportaciones de la misma pantalla con filtros distintos salen idénticas.
- **Cuidado con la firma del método**: la pantalla pasa a devolver un archivo además de una
  vista, así que tiene que declarar `View|StreamedResponse`. Con `: View` a secas revienta con
  un TypeError que **no se ve abriendo la pantalla**, sólo al apretar el botón — le pasó a
  Auditoría, que devolvía 500 al exportar desde que existe el botón.

Las migas de pan (`Panel › Módulo › Pantalla`) las arma sola `<x-encabezado>` leyendo
`config/navegacion.php`: la vista no las declara, así no se desfasan al renombrar una pantalla.

### Altas rápidas y no perder lo que la persona ya escribió

Crear una sucursal desde «Nuevo usuario», un cliente desde «Nueva cita» o un producto desde
«Cargar stock» manda **su propio POST y vuelve con un redirect**, así que la pantalla se
dibuja de nuevo y todo lo tipeado se perdía: había que cargar otra vez nombre, apellido,
usuario, email.

**`->withInput()` a secas NO alcanza acá, y de hecho empeora las cosas.** Es la trampa de
esta pantalla, así que conviene entenderla antes de tocarla: el alta rápida es **otro
formulario**, y el navegador manda sólo los campos del formulario que se envió. Los del
grande **no viajan**. Con `withInput()` lo único que se flashea son los campos del alta
rápida — y varios se llaman igual que los del grande: `nombre` está en la ficha de la persona
y también en la de la sucursal. El resultado es la ficha con el nombre de la sucursal adentro,
que es exactamente el error que este proyecto ya tuvo una vez por otro motivo.

Lo resuelve **`App\Servicios\Borrador`**, en dos mitades que hay que poner **las dos**:

| Dónde | Qué |
|---|---|
| La vista del alta rápida | `data-borrador="#formPrincipal"` en su `<form>` |
| El controlador | `return Borrador::conservar($destino, $request);` |

`app.js` escucha el envío, serializa el formulario que le indica el atributo y lo manda en un
campo oculto `_borrador`; `Borrador` lo devuelve a la sesión y `old()` lo encuentra al
redibujar. **La contraseña nunca entra al borrador.**

```php
return Borrador::conservar(redirect()->route('citas.form', ['cliente' => $idCliente]), $request);
```

```blade
<input name="nombre" value="{{ old('nombre', $cliente->nombre ?? '') }}">
```

**Y el formulario grande tiene que leer `old()` en todos sus campos**, o el borrador vuelve y
la pantalla lo ignora. Es la mitad que más se olvida: Cargar stock no lo hacía en **ninguno**
de sus campos, así que aunque el dato volviera no se veía.

> Esto venía del sistema anterior (`borrador_guardar()` / `borrador_valor()`), y la migración
> a Laravel se quedó a mitad de camino: **el JS se trajo y el lado del servidor no**. Durante
> varias versiones `app.js` estuvo adjuntando un borrador que ya no leía nadie, y como
> ninguna vista declaraba `data-borrador` tampoco llegaba a mandarse. Parecía código muerto y
> era media función.

En las vistas Blade, además, **las variables del layout no pisan a las del controlador**, que
era un problema real del sistema viejo —`view()` hacía `extract()` antes de incluir el
encabezado, y la pantalla de Nuevo usuario llegó a mostrar el nombre de quien estaba logueado
en el campo Nombre—. Blade tiene su propio ámbito por componente, así que no puede repetirse.

## Roles

| id | Rol | Alcance |
|---|---|---|
| 1 | Administrador | Superadministrador: ve todo, único que gestiona cuentas, roles y excepciones de agenda |
| 2 | Profesional | El empleado que atiende: citas, clientes, cobros · de Seguridad, **solo su asistencia**. **No administra el módulo Servicios** — ver el aviso de abajo |
| 3 | Asistente administrativo | Operación diaria: citas, clientes, servicios, inventario, facturación, reportes · de Seguridad, turnos, comisiones y asistencia |
| 4 | Cliente | Portal del cliente. No es personal (`rol.es_personal = 0`) |

Se pueden crear, editar y eliminar roles desde **Seguridad → Roles** (tabla `rol_modulo`).
Los roles 1 y 4 están protegidos porque el código los referencia.

**Nunca escribas `id_rol IN (1,2,3)`**: filtrá con `JOIN rol r … WHERE r.es_personal = 1`, así
los roles nuevos funcionan sin tocar código. El Administrador se detecta con
`Permisos::esAdmin()`, y para exigirlo en una ruta está el middleware `admin`.

### Submódulos: ningún módulo es todo o nada

**Los siete módulos se dan por partes**: son **28 permisos**, no 7. Quien registra la atención
no tiene por qué agendar; quien cobra no tiene por qué anular una liquidación al personal;
el Profesional ficha su asistencia sin ver las cuentas de sus compañeras. La clave es
`modulo.submodulo` y sigue siendo **un valor atómico por fila**, así que la 1FN se mantiene.

| Módulo | Se divide en |
|---|---|
| `citas` | `.agenda` · `.atencion` · `.ausencias` |
| `clientes` | `.registro` · `.fidelizacion` · `.valoraciones` |
| `servicios` | `.catalogo` · `.categorias` · `.descuentos` |
| `inventario` | `.productos` · `.stock` · `.compras` · `.proveedores` |
| `facturacion` | `.facturas` · `.cobros` · `.caja` · `.pagos` · `.proveedores` · `.timbrados` |
| `reportes` | no se divide: es una sola pantalla |
| `seguridad` | `.usuarios` · `.roles` · `.turnos` · `.asistencia` · `.comisiones` · `.sucursales` · `.contacto` · `.auditoria` |

Todo sale de **`config/permisos.php`**: la matriz de Seguridad → Roles
(`Permisos::matriz()`), las claves que acepta el POST (`Permisos::claves()`) y las etiquetas
de los mensajes «Sin permiso» (`Permisos::nombreModulo()`). **Lo único que NO sale solo son
los guardias**: cada ruta tiene que pedir su clave a mano.

**El guardia es el middleware `modulo:`**, declarado ruta por ruta en `routes/web.php`:

```php
Route::middleware(['sesion', 'personal'])->group(function () {
    Route::prefix('seguridad')->name('seguridad.')->group(function () {
        Route::get('/', [SeguridadController::class, 'index'])->middleware('modulo:seguridad');
        Route::get('turnos', [PersonalController::class, 'turnos'])->middleware('modulo:seguridad.turnos');
    });
});
```

`Permisos::rolPuede()` resuelve la jerarquía en los dos sentidos:

- quien tiene el módulo padre (`facturacion`) tiene **todos** sus submódulos — es la red que
  deja andar a un rol guardado antes de que ese módulo se dividiera;
- quien tiene **algún** submódulo (`seguridad.asistencia`) entra al módulo, porque si no no
  tendría cómo llegar hasta él. Por eso los `index` piden el padre y todo lo demás la clave
  fina.

Tres reglas al dividir un módulo nuevo, y las tres se olvidan fácil:

1. **La ruta pide la clave específica**: `->middleware('modulo:seguridad.turnos')`, nunca
   `modulo:seguridad`. El único que pide el padre es el landing del módulo.
2. **La tarjeta del landing se filtra** con `Permisos::tarjetasPermitidas()`, que recibe la
   lista de tarjetas con la clave en el campo `'p'` (va como campo y no como clave del arreglo
   porque Agenda y Nueva cita son las dos `citas.agenda`, y un arreglo no admite la clave
   repetida).
3. **`config/navegacion.php` lleva la misma clave** en cada pantalla, o los accesos rápidos
   ofrecen un atajo hacia algo que contesta «Sin permiso».

En las vistas se usa **`Permisos::puede('inventario.proveedores')`** —declarando arriba
`@php use App\Servicios\Permisos; @endphp`— para no dibujar lo que la persona no va a poder
usar: el alta rápida de cliente dentro de Nueva cita pide `clientes.registro`, la de proveedor
dentro de Nueva compra pide `inventario.proveedores`, y el aviso de «no hay timbrado vigente»
solo ofrece el botón a quien tenga `facturacion.timbrados`.

> **Esconder el botón no es el control.** El middleware es el que decide, y hay una prueba que
> lo verifica: `ReglasDeNegocioTest` entra como Profesional y comprueba que la ruta de
> Timbrados conteste **403**, no que el botón no esté.

> **El módulo Servicios NO es del Profesional, y es la corrección más importante de la
> 6.4.0.** El rol lo traía de fábrica (`servicios.catalogo`, `.categorias`, `.descuentos`) y
> la auditoría del 11/08/2026 lo usó para **bajar una coloración de 280.000 a 1.000, darla de
> baja y poner una promoción al 99 %** — que `sp_emitir_factura` aplica sola, sin que nadie la
> autorice. Ver los servicios y **fijar cuánto cobra el salón** eran la misma clave.
>
> Quien atiende los ve igual donde los necesita: Nueva cita y Registrar atención son de
> `citas.*` y listan el catálogo completo. **Si algún día hace falta que el Profesional vea la
> lista de Servicios sin poder tocarla, es un permiso nuevo de sólo lectura, no
> `servicios.catalogo`.** Lo fija `ReglasDeNegocioTest::el_profesional_no_administra_precios_ni_promociones`.

> **Al mudar una pantalla de módulo, revisá contra qué rol queda.** Timbrados vivía en
> Configuración, que ningún rol salvo el Administrador tiene; al pasarla a Facturación quedó
> al alcance de cualquiera con ese módulo. Por eso es su propio submódulo.

**La creación de cuentas sigue siendo del Administrador y punto** (middleware `admin` en la
ruta del formulario de usuario), sin importar la matriz. Y ojo con `seguridad.roles`:
quien lo tenga puede editar la matriz, incluida la suya.

### Renombrar o juntar módulos: lo que quedó guardado

Los permisos de cada rol viven en `rol_modulo`, así que **cambiarle el nombre a un módulo deja
huérfanas las filas que ya estaban escritas**. No dan error: el rol simplemente pierde la
pantalla, en silencio, que es la peor forma de romperlo.

Por eso `config/permisos.php` tiene **`equivalencias`**, que `Permisos::leer()` aplica al leer
—y `ConfiguracionController::roles()` al dibujar la matriz, o las casillas saldrían en blanco—.
Al guardar la matriz las filas quedan escritas con el nombre nuevo, así que es un puente, no
una capa permanente: cuando ninguna base tenga claves viejas, el arreglo se puede vaciar.

**La regla al juntar dos módulos: el padre viejo se traduce a SUS submódulos, nunca al padre
nuevo.** `personal` no equivale a `seguridad`, equivale a los cuatro submódulos que Personal
tenía. Traducirlo al padre nuevo le regalaría los roles, las sucursales y la auditoría a quien
solo administraba al personal — un permiso de más es tan grave como uno de menos.
`ReglasDeNegocioTest::un_rol_guardado_con_las_claves_viejas_no_pierde_ni_gana_permisos` lo
comprueba en las dos direcciones.

Y hay que hacer las dos cosas, no una: traducir para las bases que están andando **y** dejar
`basededatos/peluqueria_bd(base).sql` con las claves nuevas, que es el archivo con el que se
instala.

## La hora

**No uses `date()` para sellar un momento que después se le muestra a una persona: usá
`ahora_bd()`.** Le pregunta la hora a MariaDB (una sola vez por petición, con caché) en vez
de confiar en la de PHP.

El motivo es que **la base de zonas horarias de PHP se desactualiza y nadie lo nota**. La del
XAMPP de la PC de desarrollo es la **2023.3**, anterior a que Paraguay dejara sin efecto el
horario de verano: en agosto cree que estamos en UTC−4 y devuelve **15:19 cuando el reloj
marca 16:19**. Preguntarle a la base saca a PHP de la ecuación.

Donde importa hoy: el **fichaje de asistencia**, que registra la hora del clic. Un fichaje
una hora corrido no sirve para nada.

**Eso obliga a que la base tenga bien la hora, y eso cambia según dónde corra:**

| Entorno | Qué hay que hacer |
|---|---|
| XAMPP | nada: MariaDB toma la zona de Windows, que está bien |
| Docker | ya está resuelto — `docker-compose.yml` le pasa `--default-time-zone=-03:00`. Hace falta porque la imagen de MariaDB 10.4 **también** trae tzdata vieja y `America/Asuncion` le da UTC−4 |
| VPS | fijar `timedatectl set-timezone America/Asuncion` y **comprobarlo con `SELECT NOW()`** contra el reloj de pared. Un VPS recién instalado corre en UTC |

`php artisan spg:diagnostico` compara los dos relojes y avisa si no coinciden.

> El `.ics` del calendario resuelve el mismo problema de otra forma —manda **hora flotante**,
> sin `Z` y sin convertir— porque ahí el que interpreta es el teléfono del cliente. Ver la
> sección *Recordatorio en el calendario del cliente*. **Las dos soluciones conviven: no
> unifiques una con la otra.**

Si algún día se actualiza la tzdata de PHP (o se fija `date.timezone` a un huso de UTC−3
fijo), `ahora_bd()` sigue dando lo mismo y no hay nada que tocar.

## Facturación (Paraguay / SET)

Formato según el **Manual Técnico del SIFEN v150**, grupo C:

- Timbrado: **8 dígitos**
- Establecimiento: **3 dígitos** · Punto de expedición: **3 dígitos** · Correlativo: **7 dígitos**
- Número impreso del comprobante: `001-001-0000001` → **13 dígitos**

Los timbrados se administran en **Facturación → Timbrados**, con validación en la app y
`CHECK` en la base. La pantalla está ahí y no en Configuración
porque el timbrado es del comprobante: quien factura es quien se da cuenta de que está por
vencer.

> **Timbrados es su propio submódulo (`facturacion.timbrados`), no viene con Facturación.**
> Cuando la pantalla estaba en Configuración ningún otro rol tenía ese módulo, así que de
> hecho la administraba solo el Administrador; mudarla sin más habría puesto el alta y la
> baja de timbrados al alcance de cualquiera con `facturacion`. Con la clave aparte, el salón
> decide en la matriz quién los administra. Ver **Submódulos**.

**Cuidado con los nombres de los `CHECK`**: conviven `chk_timbrado_rango` y
`chk_timbrado_fechas`, del esquema original, con `chk_timbrado_rango7`, que es la estricta —
la que hace cumplir el tope de 9.999.999. Se llama distinto porque el nombre ya estaba
ocupado. Si agregás una regla nueva, elegí un nombre libre.

Cada comprobante tiene su pantalla de detalle e impresión en **Facturación → Facturas → Ver**
(`facturacion/factura_ver`), que arma todo desde `vw_detalle_factura` y `vw_factura_impuestos`.
El IVA en Paraguay va incluido en el precio: se desglosa, no se suma.

> **`sp_emitir_factura` factura desde `cita_servicio`, no desde `servicio_realizado`.**
> Por eso «Registrar atención» **saca de `cita_servicio`** los servicios que se agendaron y no
> se realizaron (los que quedaron sin tildar y sin atención registrada). Si no, al cliente se
> le cobraba un servicio que no recibió. Al tocar esa pantalla, cuidar que la cita siga
> reflejando **lo que se hizo**, no lo que se había reservado.

**El pago mixto no es una función aparte: es el modelo.** `cobro` es **cada pago**, no el pago
de la factura. Una factura puede tener todos los cobros que haga falta, cada uno con su medio y
su monto, y `fn_factura_saldo` los resta a todos. Por eso el modal de cobro trabaja con líneas
(`metodo[]`, `monto[]`) y hace una llamada a `sp_registrar_cobro` por línea, **todo dentro de una
transacción**: si una línea falla, no queda media factura cobrada.

El detalle del medio va a su tabla 1 a 1 según el `tipo` de `metodo_pago`: `cobro_tarjeta`
(marca, débito/crédito, cuotas, últimos 4, boleta, autorización) para `TARJETA`, y `cobro_banco`
(banco, nº de cheque, nº de operación, fecha) para `BANCO` y `CHEQUE`. **Los triggers de la base
verifican que el detalle corresponda al tipo de medio**, así que en PHP no hace falta repetir esa
validación: alcanza con traducir el error. Si llegan datos de tarjeta en una línea de efectivo
(un POST forjado: la pantalla los oculta), se descartan — el cobro se registra igual.

El cierre de caja muestra el **arqueo por medio de pago**, separando lo que tiene que estar
físicamente en el cajón (`tipo = EFECTIVO`) de lo que entró por tarjeta, banco o cheque.

**La seña no se vincula a la factura.** Se cobra antes de atender, así que queda como un
cobro con `id_cita` y `id_factura` NULL. `fn_factura_saldo` **ya descuenta los cobros de la
cita** además de los de la factura: si además se la vinculara a la factura, se restaría dos
veces. La pantalla del comprobante la muestra igual, con el badge «seña», porque
`vw_factura_resumen.cobrado` también la suma y si no la lista no cuadraría.

**Anular no es borrar.** La numeración de la SET no puede tener huecos, así que
`sp_anular_factura` y `sp_anular_cobro` solo cambian el estado y el comprobante sigue
apareciendo en el listado, con el sello «Anulada». La base exige el orden: primero se anulan
los cobros, después la factura. Toda anulación pide un motivo, que va a `auditoria`.

## Turnos

**El turno es una plantilla, no una fecha**: un nombre, un horario y los días de la semana en
que se trabaja. *Turno Mañana · 08:00 a 12:00 · lunes a sábado.* Se define una vez en
**Seguridad → Turnos** y se le asigna a cada persona desde su ficha.

```
turno_laboral   id_turno · id_sucursal · nombre · hora_inicio · hora_fin · activo
turno_dia       id_turno · dia_semana        1 fila por día (1FN)
usuario_turno   id_usuario · id_turno        N:M, el mismo turno lo comparte el equipo
```

**`dia_semana` va de 1 (lunes) a 7 (domingo)**, que es lo que dan `date('N')` en PHP y
`WEEKDAY()+1` en la base. No es el `DAYOFWEEK()` de MySQL (que arranca en domingo): si mezclás
los dos, la agenda se corre un día.

> El modelo anterior guardaba **una fila por día y por persona** (`id_usuario` + `fecha`). Para
> que el salón abriera de lunes a sábado había que cargar 26 filas por empleada por mes, y el 1
> de cada mes la agenda se quedaba **sin ningún horario disponible**, porque
> `fn_verificar_disponibilidad` exige que la cita entre en un turno. El modelo por plantilla
> ya viene en el `.sql` que se entrega; la conversión desde el modelo viejo la hacía una
> migración del sistema anterior y no hace falta repetirla.

`asistencia` cuelga de (persona, turno, fecha) — `uq_asistencia_dia` — y guarda `justificada`:
NULL presente · 1 falta con permiso · 0 falta sin permiso.

**Seguridad → Asistencia es el listado de quiénes trabajan ese día**, sacado de los turnos
asignados. **No se escriben horarios a mano**: se ficha con un botón y queda la hora del clic
(`ahora_bd()`, ver la sección *La hora*). El botón de Entrada se habilita solo dentro de la
franja del turno, con una hora de gracia antes y dos después. Quien administra los turnos
puede fichar por otro y marcar faltas; el Profesional solo ve y ficha lo suyo.

## Agenda y disponibilidad

`fn_verificar_disponibilidad` es la única autoridad sobre si un horario sirve. Mira tres
cosas: **ausencias**, **turno laboral** y **solape con otra cita**. La versión original no
miraba el turno, y por eso se podía agendar un domingo a las 3 de la mañana; la versión que
viene en el `.sql` ya lo mira. Si el profesional no tiene **ningún** turno asignado
no se bloquea nada — se entiende que el salón todavía no usa la agenda de turnos, mismo
criterio permisivo que `fn_puede_realizar`.

**Dos personas pidiendo el mismo hueco a la vez.** `sp_agendar_cita` y
`sp_reprogramar_cita` toman un candado sobre la fila del profesional
(`SELECT id_usuario … FOR UPDATE`) **antes** de consultar la disponibilidad. Sin eso,
preguntaban y después insertaban, y con peticiones simultáneas las tres recibían "está
libre" antes de que ninguna hubiera insertado: se midieron **47 citas en 16 franjas, con 46
solapes**. El candado serializa por profesional, así que la segunda petición espera y cuando
pregunta ya ve la cita de la primera.

> **El candado solo vale si quien llama abrió una transacción**, porque se suelta al hacer
> commit. Por eso `Agenda::agendar()` y `Agenda::reprogramar()` envuelven la llamada en
> `Bd::enTransaccion()`, y todos los caminos pasan por ahí. Si agregás otro que agende o
> reprograme, **hacelo pasar por esos métodos** o la protección no existe.
>
> No es teoría: `tests/Feature/ConcurrenciaAgendaTest.php` lanza 5 procesos simultáneos
> contra el mismo hueco y exige que quede **una sola** cita. Sin la transacción entran dos.

Un índice único sobre `(id_usuario, fecha_hora)` **no** sirve como alternativa: las citas
canceladas quedan en la tabla, así que impediría volver a vender un horario liberado, y
tampoco cubre los solapes parciales (una de 10:00 por 45 min contra otra de 10:15).

Las pantallas **no dejan escribir una fecha a mano**: `App\Servicios\Agenda` arma los huecos
y los sirve como JSON (`citas/disponibilidad` para el personal, `portal/disponibilidad` para
el cliente), y el JS de `app.js` pinta días y horas. Sin profesional elegido se juntan los
huecos de todo el equipo y al guardar se asigna el primero libre.

**Para pintar la pantalla, los huecos se calculan en PHP; para guardar, decide la base.**
`Agenda::datosProfesional()` trae turnos (por día de la semana), citas y ausencias en **tres
consultas** y `Agenda::slotsProfesional()` arma los huecos en memoria. Antes se le preguntaba a
`fn_verificar_disponibilidad` hueco por hueco: el calendario de 60 días daba ~12.000
consultas y tardaba **38 segundos**, y bajo concurrencia cortaba peticiones por timeout.
Ahora tarda **0,11 s**.

> Es la única parte del sistema donde PHP replica una regla de la base, y se hace a propósito
> por costo. **La autoridad sigue siendo `fn_verificar_disponibilidad`**, que se consulta de
> nuevo al guardar, dentro del candado del procedimiento. Si cambiás las reglas de
> disponibilidad en la base, **hay que reflejarlas en `Agenda::slotsProfesional()`**, o la
> pantalla va a ofrecer horarios que el servidor rechaza. Hay una prueba que compara los dos
> caminos hueco por hueco (2.430 candidatos con el modelo viejo, 1.955 con el de turnos por
> día; cero discrepancias en los dos). **Rehacela cada vez que toques las reglas.**

Cuando alguien elige un horario que la pantalla mostraba libre y al guardar ya no lo está,
`Agenda::motivoHuecoPerdido()` mira la base y explica **por qué**: si se lo ganó otra persona
(«Ese horario lo tomó otra persona mientras completabas la reserva»), si hay una ausencia
cargada, o si el profesional no atiende a esa hora. Sin eso, el cliente solo veía «no
disponible» y no sabía si cambiar de hora o de profesional.

### Una cita, varios profesionales

En la peluquería es normal que a la misma clienta la atiendan dos personas a la vez: una le
hace el color mientras la otra le hace las uñas. Por eso **cada servicio de la cita puede
tener su profesional**: `cita_servicio.id_usuario` (NULL = lo hace el de la cita).

**La cita dura el bloque más largo, no la suma.** `fn_cita_duracion` agrupa los servicios por
profesional, suma dentro de cada grupo y devuelve el **máximo**: color 45 min + uñas 30 min
en paralelo son **45 minutos de cita**, no 75. Si los dos servicios los hace la misma
persona, el grupo es uno solo y vuelven a sumarse. `fn_cita_duracion_de(cita, usuario)` da
el bloque de una sola persona, que es lo que se le ocupa **a ella** en la agenda: por eso la
manicurista queda libre a los 30 aunque la cita siga.

- La disponibilidad se pregunta **para cada profesional del reparto**, con su propio bloque.
  `Agenda::validarReparto()` y `Agenda::duracionReparto()` lo resuelven antes de guardar.
- Sin reparto (el caso de siempre) no cambia nada: un solo grupo, un solo profesional.

**Servicios que ocupan a la clienta entera.** `servicio.requiere_exclusividad` marca los que
no se pueden hacer al mismo tiempo que otro: una coloración y una keratina se pisan —las dos
son sobre el pelo—, un lavado y una pedicura no. La regla se aplica **entre profesionales
distintos**: si la misma persona hace los dos, van uno después del otro y no hay conflicto,
así que se permite.

## Cambio de contraseña: segundo factor

**Cambiar la contraseña desde Mi cuenta pide dos cosas: la contraseña actual y un código
que llega al correo.** Saber la contraseña vieja no alcanza — si alguien se sienta en una
computadora con la sesión abierta, o si la contraseña se filtró, con un solo paso le cambian
la clave a la dueña de la cuenta y la dejan afuera.

El flujo son dos acciones, y la primera **no toca la base**:

| Paso | Acción | Qué hace |
|---|---|---|
| 1 | `CuentaController::password` | Valida la actual y la nueva, deja la nueva **ya hasheada en la sesión** y manda el código (`token_seguridad`, tipo `CAMBIO_PASSWORD`) |
| 2 | `CuentaController::passwordConfirmar` | Valida el código y recién ahí escribe `usuario.password_hash` |

Detalles que no hay que perder al tocar esto:

- **En texto plano la contraseña nueva no se guarda en ningún lado**, ni por esos minutos:
  a la sesión va el hash.
- **Un pedido que nunca se confirma no cambia nada.** Vence a los 30 minutos, igual que el
  código, y `CuentaController::passwordCancelar` lo descarta y quema el token.
- Al aplicar el cambio se hace **`session()->regenerate()`**: contraseña nueva, sesión nueva.
- **Sin correo cargado no se deja cambiar.** Es el único canal del segundo factor; permitirlo
  igual sería dejar abierto justo el agujero que esto tapa. Pasa solo con cuentas viejas: el
  registro y el alta de personal exigen email.
- La recuperación de contraseña (*me olvidé*) ya usaba código por correo desde antes: son dos
  caminos distintos y cada uno tiene su tipo de token.

## Avisos y recordatorios

`App\Servicios\Notificaciones` llena y despacha la cola de `notificacion`, que antes se
llenaba y no la vaciaba nadie. Los correos se arman con Mailables (`App\Mail\AvisoCita` y
`CodigoSeguridad`) y sus plantillas Blade de `resources/views/correo/`.

- **El profesional no va a estar** (ausencia cargada o baja del personal): se avisa a cada
  cliente con cita en ese rango, con un enlace para reprogramar o cambiar de profesional.
- **Recordatorio**: con la anticipación que cada cliente elige en *Portal → Mis
  recordatorios* (`preferencia_recordatorio`, 1 día por defecto).
- **El enlace del correo** (`token_cita`) permite reprogramar o cancelar **sin iniciar
  sesión**: la mayoría de las clientas que agendan en el local no tienen cuenta. El token es
  la credencial, dura 30 días y muere al cancelar.
- Los procedimientos de la base crean sus avisos con canal `WHATSAPP` (`sp_agendar_cita`,
  `sp_cancelar_cita`, `sp_generar_recordatorios`). Como esa integración no existe todavía,
  el despachador **también los toma y los manda por correo**, corrigiendo el canal. Si no,
  quedaban en PENDIENTE para siempre.
- El despacho lo hace el comando **`php artisan spg:notificaciones`**, que el scheduler corre
  **cada diez minutos** con `withoutOverlapping()` (ver `routes/console.php`). En el servidor
  eso lo dispara una sola línea de cron: `* * * * * … php artisan schedule:run`.
- `Auditoria::registrar()` no sirve para lo que hace el cliente desde el enlace (no hay sesión
  y `auditoria.id_usuario` es NOT NULL): se usa `Auditoria::registrarComo()`, a nombre de su
  cuenta si la tiene y si no del profesional de la cita.

## Recordatorio en el calendario del cliente

`App\Servicios\Calendario` arma un archivo **.ics** (iCalendar, RFC 5545) para que el cliente se
guarde la cita en el calendario de su teléfono. Es PHP puro, sin librerías ni servicios de
terceros: lo abren por igual Android, iPhone, Google Calendar y Outlook. El bloque `VALARM`
que va adentro es la alarma del dispositivo, y su anticipación sale de la preferencia que el
cliente eligió en *Portal → Mis recordatorios* (2 horas si no configuró nada).

Así el recordatorio queda por dos vías independientes: el correo que despacha
`Notificaciones` y la alerta del propio teléfono, que suena aunque no abra el correo.
El botón está en el portal (`portal/index`, `portal/citas`) y en la pantalla que se abre
desde el enlace del correo, donde la credencial es el `token_cita` y no hay sesión.

> **Las horas van en «hora flotante»: sin `Z` y sin convertir a UTC.** Es a propósito y no
> hay que "corregirlo". La base de zonas horarias de PHP en este XAMPP es la **2023.3**,
> anterior a que Paraguay dejara sin efecto el horario de verano, así que PHP cree que en
> agosto estamos en UTC−4 cuando en realidad quedamos fijos en UTC−3. Si se convirtiera a
> UTC, al cliente le llegaría la cita **una hora corrida**. Sin conversión no hay desfase
> posible: el teléfono lee 17:00 y muestra 17:00. Por lo mismo, el enlace a Google Calendar
> manda la hora local con `ctz=America/Asuncion` y deja que Google haga la conversión, que
> su base de zonas horarias sí está al día.

## Inventario: el frasco y el mililitro

**Lo que se compra y lo que se gasta no se miden igual.** El shampoo se compra por frasco de
1 litro y se usa de a 30 ml; los sachets de keratina se compran y se usan de a uno. Antes se
cargaba el consumo en la unidad de compra y quien registraba la atención tenía que hacer la
división de cabeza: escribía «1» y descontaba un frasco entero.

El producto lleva dos columnas opcionales: **`contenido`** (cuánto trae el envase) y
**`unidad_consumo`** (en qué se gasta). Con las dos cargadas el producto es *fraccionado* y
la pantalla pide mililitros; sin ellas se comporta como siempre.

| Función (`app/Ayudas/formato.php`) | Para qué |
|---|---|
| `producto_fraccionado($p)` | ¿este producto se gasta por partes? |
| `consumo_a_stock($p, 30)` | lo que escribió la persona → lo que se descuenta (0,03 frascos) |
| `stock_a_consumo($p, 0.5)` | al revés, para mostrar cuánto queda (500 ml) |
| `unidad_consumo($p)` | «ml» o la unidad de medida del producto |

**El stock se sigue guardando en la unidad de compra**, que es la que factura el proveedor y
la que espera `fn_producto_stock`: la conversión pasa al entrar y al salir, nunca queda
guardada en dos unidades. `movimiento_inventario.cantidad` es `DECIMAL`, así que 0,03 entra
sin redondear a cero.

**El aviso de reposición** sale de `vw_producto_bajo_stock` y lo dibuja el componente Blade
`<x-aviso-stock>` en el panel y en Inventario, diciendo con cuánta plata hay que ir a comprar.
Es una sola consulta por petición (el resultado se cachea en memoria), porque el aviso
aparece en varias pantallas.

## El portal de la clienta durante la atención

Mientras la atienden, la clienta ve en su teléfono **lo que le están haciendo y cuánto va**
(`portal/atencion`). Los servicios cargados, quién hace cada uno, los productos que le están
usando, el total y la seña ya descontada.

- **Se refresca sola cada 20 segundos** contra `portal/atencion_json`, y **no consulta con la
  pestaña en segundo plano**: no tiene sentido gastarle los datos del celular por una pantalla
  que nadie está mirando. No hay websockets — es una consulta chica y a este ritmo alcanza.
- **Pedir algo más** (`cita_pedido`) es un texto libre, no un servicio del catálogo: la
  clienta no tiene por qué saber cómo se llama «esmaltado semipermanente» en el sistema. El
  pedido le aparece a quien la atiende, que confirma precio y tiempo **en persona**.
- **El modal avisa que eso aumenta el costo** antes de enviar. Un pedido no agrega nada a la
  cuenta por sí solo: lo carga el profesional si se puede hacer.
- Solo se puede pedir con la cita **En proceso**. Cuando el estado cambia, el JS recarga la
  pantalla entera en vez de repintar los números: cambian también los botones.

> El total que ve la clienta es **lo cargado hasta ese momento**, no un comprobante. El
> comprobante lo emite el salón al terminar, con `sp_emitir_factura` y desde `cita_servicio`
> como siempre.

## Informes

**Reportes → Informes** arma informes parametrizados (rango de fechas, con el atajo
*Histórico* para «todo lo que haya») y los imprime. En `ReportesController`, los métodos
privados `rango()` y `datos()` resuelven el período y traen los datos; `index()` los muestra
e `imprimir()` los saca en A4 con `public/assets/css/imprimir.css`.

**No hay librería de PDF**: se imprime desde el navegador y se elige «Guardar como PDF», que
es lo que hace todo el mundo igual. Una librería de PDF traería Composer al proyecto y no
agregaría nada que el navegador no haga.

> En el sistema anterior estos dos ayudantes **no podían llamarse** `reportes_rango` ni
> `reportes_datos`: el router armaba la URL desde el nombre de la función y los servía como
> pantallas, sin argumentos y sin guardia. Con Laravel el problema desapareció —las rutas se
> declaran una por una en `routes/web.php` y un método privado no es alcanzable desde afuera—,
> pero conviene saberlo si alguna vez se lee código de la versión vieja.

## Fidelización

Dos cosas distintas que conviene no mezclar:

- **El nivel** (Bronce → Platino) lo calcula `fn_cliente_nivel` por **cantidad de visitas**.
  No hay nada que hacer desde PHP.
- **Los puntos** los acumula la app al emitir el comprobante: `Facturacion::acumularPuntos()`
  llama a `sp_registrar_puntos` con 1 punto cada `spg.puntos_cada_gs` guaraníes (en
  `config/spg.php`, hoy 10.000). Al anular el comprobante,
  `Facturacion::revertirPuntos()` registra el movimiento contrario en vez de borrar el
  original, para que el historial del cliente muestre lo que pasó.

### Descuentos y promociones: se aplica UNO SOLO, el mejor

Hay **dos fuentes** de descuento y no se acumulan:

| Fuente | De dónde sale | Quién la carga |
|---|---|---|
| **Nivel de fidelización** | `fn_cliente_descuento` → `nivel.id_descuento`, por cantidad de visitas (Plata 5 %, Oro 10 %, Platino 15 %) | Nadie: es automático |
| **Promoción** | Cualquier `descuento` que **no** esté atado a un nivel, dentro de su vigencia | Servicios → Descuentos |

`sp_emitir_factura` calcula cuánto descontaría cada una **sobre esa factura** y aplica la que
más le convenga al cliente. **Nunca las dos**: así el descuento máximo que puede salir es el
mayor de los que el salón publicó, sin sorpresas de margen. Si querés cambiar ese criterio,
está en un solo lugar (el `IF v_m_promo > v_m_nivel` del procedimiento).

**Una promoción puede aplicar a toda la factura o sólo a ciertos servicios**, según haya
filas en `servicio_descuento`. «20 % en coloración» no descuenta la manicura de la misma
factura. `sp_aplicar_descuento` ya sabía leer esa tabla, pero **no había pantalla para
cargarla**: se agregó en el formulario del descuento.

> Antes de esto, `sp_emitir_factura` sólo consultaba `fn_cliente_descuento`, así que **las
> promociones cargadas en Servicios → Descuentos no llegaban nunca a una factura**: la
> pantalla parecía andar y no hacía nada. `fn_descuento_monto` ya validaba vigencia y topeaba
> el descuento al total, o sea que faltaba únicamente conectarla.

> **La integración con SIFEN NO está implementada y no debe implementarse sin pedido explícito.**
> El manual se usó solo como referencia de formato. Existen carpetas aparte (`C:\sifen_final`,
> "sifen_automatizador") que son otro proyecto: no mezclarlas con este.

## Caja

Se trabaja con **una sola caja abierta por vez** en todo el salón.
Sin caja abierta **no se mueve un guaraní**: quedaría fuera del arqueo y el cierre no cerraría.
`sp_registrar_cobro` busca una caja abierta *del propio usuario*; como la caja del salón puede
haberla abierto otra persona, el controlador reasigna `cobro.id_caja` a la caja abierta.
Lo mismo con `sp_pagar_compra`. La cierra quien la abrió, o el Administrador.

**`exigeCaja($queIbaAHacer)` de `FacturacionController` es el guardián**, y está en
las nueve acciones que tocan plata: cobrar, anular un cobro, emitir y anular factura, la
nota de crédito, la seña, el pago a proveedor y su anulación, y el pago al personal. Antes
solo lo pedía el cobro, así que una nota de crédito o un pago a proveedor entraban con la
caja cerrada y aparecían recién al día siguiente, en el arqueo equivocado. El mensaje dice
**qué se iba a hacer** («Abrí la caja antes de emitir la nota de crédito»), no un «no se
puede» a secas. **Si agregás otra acción que mueva dinero, llamala también.**

### El saldo de la caja es el EFECTIVO, no el movimiento del día

**`fn_caja_saldo()` devuelve lo que tiene que estar físicamente en el cajón para contarlo al
cerrar.** Por eso **sólo lo mueve el efectivo**:

```
monto_inicial + cobros EFECTIVO + otros_ingresos − egresos − pagos_proveedor EFECTIVO = saldo
        (caja)          (cobro)   (movimiento_caja)              (pago_proveedor)
```

Lo que entra por tarjeta, transferencia o cheque **se registra igual pero no toca el cajón**:
va a la cuenta del salón. Lo mismo al salir — pagarle a un proveedor por transferencia no
saca un guaraní del cajón. Antes se sumaba y restaba todo junto sin mirar el medio, así que
un cobro con tarjeta engordaba el arqueo y una transferencia lo vaciaba: en la simulación la
caja llegó a **Gs. −1.284.000** pagando desde un cajón que tenía 300.000.

`vw_caja_resumen` expone **las dos mitades**, para que el cierre pueda mostrar el arqueo
físico y el movimiento total: `cobros_efectivo` / `cobros_otros` / `cobros`,
`pagos_prov_efectivo` / `pagos_prov_otros` / `pagos_proveedor`.

**Un egreso en efectivo mayor al disponible se rechaza** (`FacturacionController::pagarProveedor`), con
un mensaje que dice cuánto hay en el cajón. Los pagos por banco no se frenan: no salen de ahí.

> **Si agregás otra salida de dinero**, tiene que restarse en `fn_caja_saldo()` **sólo cuando
> es en efectivo**, exponerse como columna en `vw_caja_resumen` separando efectivo de lo
> demás, y validar el disponible antes de registrarla. Si no, el arqueo vuelve a mentir.

## Entorno

Hay **dos formas de levantarlo en desarrollo**, y dan lo mismo. Los pasos están en
`README.md`; acá va lo que conviene tener presente al programar.

| | Cómo |
|---|---|
| **Docker** | `docker compose up`. Fija MariaDB 10.4, importa las dos bases solo y clava la zona horaria. La base queda en el **3307**, para convivir con un XAMPP ya instalado |
| **A mano** | XAMPP para MariaDB + un PHP 8.3 aparte, y `php artisan serve` |

> **El Apache de XAMPP no sirve para este proyecto: trae PHP 8.2 y Laravel 13 pide 8.3.** Por
> eso el sistema **no se publica en `htdocs`** —como se hacía con la versión anterior— sino
> que se sirve con `artisan serve` o desde el contenedor.

La configuración va en `.env` (`.env.example` es la plantilla). Dentro del contenedor, en
`docker/php/env.docker`, que se monta encima: así las dos formas conviven sin pisarse.

**Cambiar de base en Docker es una línea de `docker/php/env.docker`**, porque el contenedor
crea e importa las dos en el primer arranque (`docker/bd/10-importar.sh`):

| Valor | Para qué |
|---|---|
| `DB_DATABASE=peluqueria_test` | ver las pantallas con datos: 172 citas, 62 facturas, 33 clientas |
| `DB_DATABASE=peluqueria_bd` | ver el sistema como lo recibe el salón: catálogos y nada más |

Se cambia y se reinicia con `docker compose restart app`. **`down -v` no hace falta y además
borra las bases**: las dos ya están adentro, lo único que cambia es a cuál se conecta la
aplicación. `entrada.sh` corre `config:clear` en cada arranque, así que no queda una caché de
configuración pisando el cambio.

> **Esto no toca las pruebas**: `phpunit.xml` fija `peluqueria_test` por su cuenta. Pero si
> trabajás sobre `peluqueria_test` en pantalla, tené presente que las pruebas escriben sobre
> **esa misma base** — revierten con `DatabaseTransactions`, salvo `ConcurrenciaAgendaTest`,
> que limpia a mano en `tearDown()`.
>
> **Antes de entregar hay que dejarlo en `peluqueria_bd`**, que es la base que se instala.

> **Cuidado con `artisan serve` y las variables de entorno.** Sólo le reenvía al servidor que
> atiende las peticiones una **lista blanca** (`APP_ENV`, `PATH`, las de Xdebug…). Definir
> `DB_HOST` como variable del contenedor **no llega a la web**, aunque los comandos de consola
> anden perfecto — el síntoma es *Connection refused* sólo en el navegador. Por eso el
> contenedor tiene su `.env` propio y no variables sueltas.

**Producción — VPS compartido con otros grupos de la facultad.** Ver la sección de abajo:
**no es el mismo entorno que esta PC**, y hay cuatro diferencias que rompen cosas en silencio.

### El servidor de producción: es un VPS con root, no un hosting compartido

El sistema se va a publicar en un **VPS que se comparte entre varios grupos de la facultad**
(hasta 8), no en un hosting compartido tipo cPanel. La diferencia es la que decide si este
proyecto puede desplegarse o no: **un hosting compartido clásico no deja crear funciones ni
triggers**, y acá *toda* la lógica de negocio vive ahí. Con acceso root, sí se puede.

| | |
|---|---|
| Proveedor | Vefixy · VPS Starter (Miami) |
| Recursos | 2 vCores Ryzen 9, **4 GB RAM**, 80 GB NVMe — **compartidos entre todos los grupos** |
| Acceso | root · panel Hestia o CyberPanel |
| PHP | **8.3 o más**, que es lo que pide Laravel 13. Con root se instala la versión que haga falta, y Hestia admite varias a la vez (otro grupo puede seguir con 8.1). **Confirmarlo antes de pagar** |
| Base | MySQL / MariaDB, **un usuario y una base por grupo** (no se entra como `root`) |
| Dirección | un subdominio del dominio común, ej. `peluqueria.proyectosfacultad.com` |
| HTTPS | Let's Encrypt, gratis y renovado por el panel |
| Tareas programadas | cron del panel — es lo que reemplaza al Programador de tareas de Windows |

> **Los pasos concretos, en el orden que funciona, están en `DESPLIEGUE.md`.** Acá quedan los porqués; allá, los comandos. Dos ayudas del
> proyecto Laravel: **`php artisan spg:preparar-sql <archivo> <usuario>`** reescribe los 84
> `DEFINER` del volcado y pasa `SQL SECURITY DEFINER` a `INVOKER`, y
> **`php artisan spg:diagnostico --produccion`** revisa las diez cosas de esta sección y dice
> qué hacer con cada una. El despliegue se ensayó entero en la PC de desarrollo el 10/08/2026,
> con una base vacía y un usuario limitado: importó y arrancó.

**Las cinco reglas del despliegue.** Las cuatro primeras rompen algo sin avisar:

1. **La zona horaria del servidor está en Miami.** `ahora_bd()` le pregunta la hora a MariaDB,
   y MariaDB toma la del sistema operativo: en un VPS recién instalado eso es **UTC**, así que
   el fichaje de asistencia quedaría **4 horas corrido** y nadie se daría cuenta hasta ver una
   entrada marcada a las 12 de la noche. Al desplegar hay que fijar
   `timedatectl set-timezone America/Asuncion` (o `default-time-zone='-03:00'` en MySQL) y
   **comprobarlo con `SELECT NOW()` contra el reloj de pared** antes de dar nada por bueno.
   > El motivo original de `ahora_bd()` —la tzdata vieja de este XAMPP— **no existe en el
   > servidor**, que trae PHP actualizado. Igual no se saca: es la misma función y da lo mismo
   > en los dos lados. Lo que cambia es *qué* hay que configurar para que dé bien.

2. **Los `DEFINER` del dump apuntan a `root@localhost` y en el servidor no somos root.**
   Las 30 funciones, 20 procedimientos, 17 triggers y 17 vistas se crearon con ese definidor.
   Importados con el usuario del grupo, MySQL contesta **error 1449** y el sistema entero deja
   de andar —es el mismo error que ya está documentado más arriba—. Antes de importar hay que
   reemplazar el definidor por el usuario real, y ese usuario necesita
   `CREATE ROUTINE`, `ALTER ROUTINE`, `TRIGGER` y `EXECUTE`. Si el servidor tiene el binlog
   activo con `log_bin_trust_function_creators = 0`, las funciones **no se crean**: hay que
   ponerlo en 1 desde root.

3. **La carpeta pública del sitio tiene que apuntar a `public/`**, no a la raíz del proyecto.
   Si apunta a la raíz, el `.env` con la contraseña de la base queda descargable por HTTP, y
   `basededatos/` le entrega el esquema completo a cualquiera que pida la URL. Es un descuido
   de un minuto que se paga con la base de un salón real; `spg:diagnostico --produccion` lo
   comprueba.

4. **Los 4 GB de RAM son de todos los grupos, no nuestros.** Nada de procesos residentes:
   la cola de correos se despacha con `queue:work --stop-when-empty` disparado por cron, nunca
   con un worker permanente ni con Supervisor. Y en producción va siempre `APP_DEBUG=false`
   más `php artisan optimize` (config, rutas y vistas cacheadas), que además ahorra memoria.

5. **En el servidor no se compila nada.** No hay Node ni hace falta: Bootstrap viene por CDN
   y `app.css` es un archivo propio. Lo que se sube es el código y, si algún día se usa Vite,
   el `public/build` ya compilado. `vendor/` se resuelve con
   `composer install --no-dev --optimize-autoloader`.

Tres cosas más que hay que dejar acomodadas en el `.env` de producción, porque el sistema las
usa para armar enlaces y credenciales:

- **`APP_URL` con el subdominio real.** De ahí salen los enlaces de los correos (reprogramar,
  cancelar, agendar en el calendario). Con el valor de desarrollo, el cliente recibe un enlace
  a `localhost` que no abre en ningún lado.
- **SMTP saliente por el puerto 587.** Es por donde salen el código de verificación, la
  recuperación de contraseña, el segundo factor y los recordatorios. Está en la lista de cosas
  a confirmarle al proveedor antes de pagar; si lo bloquean, el registro de clientes no
  funciona.
- **WebAuthn necesita HTTPS y el dominio como `rpId`.** Hoy anda en `localhost` por la
  excepción que hacen los navegadores. Las credenciales registradas en desarrollo **no sirven**
  en producción: cada persona vuelve a registrar su huella la primera vez.

  > **Por eso el ingreso con huella NO se puede probar desde el celular en la red local**, y no
  > es un problema del teléfono. Entrando por `http://192.168.x.x:8000` fallan las dos
  > condiciones a la vez: no es contexto seguro —el navegador ni define `PublicKeyCredential`—
  > y el `rpId` sería una dirección IP, que la especificación no admite. Recién en el servidor,
  > con el subdominio y HTTPS, funciona. `SPGBio.estado()` lo explica en pantalla en vez de
  > echarle la culpa al equipo.

### `peluqueria_bd` es la base que se entrega: esquema al día, sin datos

**`peluqueria_bd` no es una base de trabajo: es la que se sube con el programa.** Tiene que
estar siempre **actualizada en esquema y vacía de operación**. Un salón que instala el sistema
no puede encontrarse con las citas, las facturas ni las clientas de otro.

Lo que **queda** y lo que **se borra**:

| Queda | Se borra |
|---|---|
| Los catálogos del sistema: `rol`, `rol_modulo`, `estado_*`, `tipo_*`, `metodo_pago`, `condicion_venta`, `nivel`, los 3 `descuento` de nivel, `categoria_servicio`, `categoria_producto` | Toda la operación: citas, atención, consumo, facturas, cobros, caja, compras, movimientos, asistencia, puntos, notificaciones, calificaciones, auditoría |
| `spg_migracion` — sobra: la dejó el sistema anterior y Laravel no la usa, pero borrarla no aporta nada | El catálogo comercial: `servicio`, `producto`, `proveedor`, `timbrado`, `comision`, `contacto_soporte` |
| La sucursal 1, que el `admin` referencia (`usuario.id_sucursal = 1`) | Turnos, `turno_dia`, `usuario_turno` |
| Las dos cuentas del instalador: `admin` y `cliente`, con sus dos filas de `persona` y la ficha de `cliente` | Cualquier otro usuario, persona o cliente · roles creados a mano · credenciales WebAuthn y tokens |

**Los catálogos no son «datos»: sin ellos el sistema no arranca.** Borrar `estado_cita` o
`metodo_pago` no deja una base limpia, deja una base rota.

Hay un guion listo en `basededatos/limpiar_base.sql` que hace exactamente esto y reinicia los
`AUTO_INCREMENT`. Se corre con `SET FOREIGN_KEY_CHECKS = 0`, así que **después hay que
comprobar que no quedaron huérfanos**.

> **Si volvés a cargar datos para probar, acordate de vaciarla antes de entregar**, y de
> regenerar el archivo base en la misma tanda. Para probar con datos está `peluqueria_test`
> (ver *Probar cambios sin tocar la base real*), que es justamente para eso.

#### Solo hay DOS archivos `.sql`, y cada uno tiene su papel

Todos los demás se borraron a propósito: con cinco volcados parecidos encima del escritorio,
tarde o temprano se carga el equivocado y se prueba contra un esquema que ya no existe.

| Archivo | Qué es | Cuándo se usa |
|---|---|---|
| **`basededatos/peluqueria_bd(base).sql`** | La base **que se entrega**: esquema completo y sólo lo mínimo para entrar (catálogos del sistema, la sucursal 1 y las cuentas `admin` y `cliente`) | Instalar el sistema en un salón, o levantar una base limpia |
| **`basededatos/1mes_simulacion.sql`** | El mes simulado del QA: 172 citas, 62 facturas, 33 clientas | Cargar `peluqueria_test` para probar con datos de verdad |

> **`peluqueria_bd(base).sql` tiene que estar SIEMPRE al día.** No es un respaldo viejo: es el
> archivo que se entrega y con el que se instala. Cada vez que cambie **una tabla, una columna,
> un `CHECK`, una vista, una función, un procedimiento, un disparador o una migración**, hay
> que regenerarlo en la misma tanda. Si queda atrás, el salón que instale el sistema arranca
> con un esquema que ya no es el que espera el código.

Se regenera siempre con `mysqldump`, nunca exportando desde phpMyAdmin:

```bash
/c/xampp/mysql/bin/mysqldump.exe -u root --routines --triggers --events --single-transaction --default-character-set=utf8mb4 peluqueria_bd > "basededatos/peluqueria_bd(base).sql"
```

Antes de regenerarlo, comprobá que la base esté **vacía de operación** (la tabla de arriba dice
qué queda y qué se borra); si tiene datos de prueba, pasale primero `basededatos/limpiar_base.sql`.

### Una sola carpeta, y qué NO se sube al servidor

**El proyecto vive en una sola carpeta y se edita ahí.** No hay copia a `htdocs`: eso era del
sistema anterior, que lo servía Apache. Ahora se sirve con `artisan serve` o con el
contenedor, así que se prueba sobre el mismo código que se edita.

- URL en desarrollo: `http://localhost:8000`
- Usuarios de prueba: `admin` / `admin123` · `cliente` / `cliente123`

**Lo que no se sube al servidor**, porque todo lo que quede bajo la raíz pública se descarga
por HTTP:

| No subir | Por qué |
|---|---|
| `basededatos/` | los `.sql` entregan el esquema completo a cualquiera que pida la URL |
| `CLAUDE.md`, `Referencias/` | documentación interna del TCC |
| `tests/`, `docker/` | no hacen falta en producción (`composer install --no-dev`) |

La defensa de fondo es otra, y es la que no hay que equivocar: **la carpeta pública del sitio
apunta a `public/`, nunca a la raíz del proyecto.** Si apunta a la raíz, el `.env` con la
contraseña de la base queda descargable. `spg:diagnostico --produccion` lo comprueba.

> **No importar el .sql con `--skip-grant-tables`**: las vistas y procedimientos quedan con
> DEFINER vacío y todo revienta con el error 1449.

### Probar cambios sin tocar la base real

```bash
/c/xampp/mysql/bin/mysql.exe -u root -e "DROP DATABASE IF EXISTS peluqueria_test; CREATE DATABASE peluqueria_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
/c/xampp/mysql/bin/mysql.exe -u root peluqueria_test < "basededatos/1mes_simulacion.sql"
```

Con `basededatos/1mes_simulacion.sql` la base de prueba queda con datos de verdad —172 citas, 62 facturas,
33 clientas—, que es lo que hace falta para que una prueba signifique algo. Si lo que querés es
una base limpia, cargá `basededatos/peluqueria_bd(base).sql` en su lugar.

**Los dos archivos se cargan sin peligro en otra base**: ninguno lleva adentro `CREATE DATABASE`
ni `USE`, así que respetan el nombre que les pasás en la línea de comandos. Eso es justamente lo
que hacía peligroso al script original del TCC, que sí los traía y escribía sobre la base real
aunque le pidieras otra.

> **Si el dump del mes simulado quedó viejo respecto del esquema**, regeneralo con `mysqldump`
> desde una `peluqueria_test` que sí esté al día. Ya pasó: quedó con 48 rutinas en vez de 50,
> sin `fn_promo_vigente` ni `fn_descuento_monto_factura`. `spg:diagnostico` lo detecta.

Los dos motivos de usar siempre `mysqldump` y nunca el export de phpMyAdmin:

- El export de phpMyAdmin **perdía las 54 restricciones `CHECK`**, así que la copia de pruebas
  aceptaba valores que la base real rechaza y una prueba podía dar un falso OK. Pasó de verdad
  con `movimiento_punto.tipo`. El `mysqldump` las conserva.
- El archivo queda **sin `CREATE DATABASE` ni `USE`**, que es lo que permite cargarlo en una base
  de pruebas sin pisar la real.

Después de regenerarlo, comprobar que reproduce la base: cargarlo en una base vacía y contrastar
tablas, vistas, rutinas, triggers y CHECKs contra `peluqueria_bd`.

**Las 51 pruebas corren contra `peluqueria_test`**, no contra una base de mentira: es la única
forma de que signifiquen algo, porque lo que se está probando son las rutinas de la base.

> **Nunca uses `RefreshDatabase`.** Borraría el esquema del TCC con sus 50 rutinas y sus 17
> triggers. Las pruebas que escriben usan `DatabaseTransactions`, que revierte al terminar.
> La única que no puede usarlo es `ConcurrenciaAgendaTest`, porque mide justamente qué ven
> entre sí varias conexiones: esa limpia a mano en `tearDown()`.

## Cambiar el esquema de la base

**No se usan migraciones de Laravel.** `database/migrations/` está vacío a propósito y correr
`artisan migrate` sería un error: crearía las tablas del framework dentro de la base que se
entrega al salón. El esquema tiene una sola fuente, **`basededatos/peluqueria_bd(base).sql`**.

Para cambiar una tabla, una columna, un `CHECK`, una vista, una función, un procedimiento o un
disparador, el circuito es este:

1. Aplicar el cambio **en la base**, con SQL.
2. Probarlo contra `peluqueria_test`, y correr `php artisan test`.
3. **Regenerar `basededatos/peluqueria_bd(base).sql`** con `mysqldump` — en la misma tanda, no
   «después». Si queda atrás, el salón que instale el sistema arranca con un esquema que ya no
   es el que espera el código.
4. Comprobar con `php artisan spg:diagnostico` que siguen estando las 20 rutinas, 30 funciones,
   17 triggers, 17 vistas y 54 `CHECK`.
5. Respetar la **regla número dos**: 3FN, sin datos repetidos ni columnas derivadas guardadas.

**Cuidado con el orden al reestructurar una tabla.** MariaDB no deja soltar un índice mientras
una clave foránea se apoya en él, y no deja borrar una columna que está dentro de un índice.
El orden que funciona es **clave foránea → índice → columna**, y cuando hay que reemplazar un
índice único que sostiene una FK, el nuevo se crea **antes** y tiene que empezar por la misma
columna (por eso `uq_asistencia_dia` es `(id_turno, id_usuario, fecha)` y no al revés).

> El sistema anterior traía `app/migrations.php`, que creaba en cada arranque las tablas y
> columnas que el `.sql` original del TCC no tenía —`persona`, `token_seguridad`,
> `credencial_webauthn`, `rol_modulo`, `turno_dia`, `cita_servicio.id_usuario` y varias más— y
> se reparaba solo al reimportar. **Ya no hace falta: todo eso está dentro del `.sql`.** Si
> alguna vez se lee código de aquella versión, ese archivo explica de dónde salieron esas
> tablas.

## Las pruebas

```bash
"C:/php/php.exe" artisan test          # o: docker compose exec app php artisan test
```

**51 pruebas** contra `peluqueria_test`. No prueban PHP: prueban que **las reglas de la base
se sigan cumpliendo**, que es donde vive el negocio.

| Archivo | Qué cuida |
|---|---|
| `ReglasDeNegocioTest` | que un horario tomado deje de ofrecerse; que la cita dure el bloque más largo y no la suma; que el saldo de caja cuente **sólo** el efectivo; que los correlativos vayan seguidos y sin repetir; que la seña se descuente una vez y no dos; que anular conserve el número; que el stock salga de los movimientos y no se pueda sacar de más; y las cinco reglas de permisos, incluido el 403 real de una ruta y que un rol guardado con las claves viejas no pierda ni gane nada |
| `ConcurrenciaAgendaTest` | lanza **5 procesos simultáneos** contra el mismo hueco y exige que quede **una sola** cita |
| `HuellaTest` | que la pantalla de la huella se dibuje **con su JavaScript** y que «Ahora no» funcione **sin** él: es la única pantalla que se mete entre el ingreso y el panel, así que si algo falla ahí la persona no entra |
| el resto | ingreso, permisos por rol, pantallas que responden |

Cuatro cosas que hay que saber antes de tocarlas:

- **Nunca `RefreshDatabase`.** Borraría el esquema con sus 50 rutinas. Las que escriben usan
  `DatabaseTransactions`.
- **`ConcurrenciaAgendaTest` no puede correr dentro de una transacción**, porque mide qué ven
  entre sí conexiones distintas. Limpia a mano en `tearDown()`, con
  `tests/reservar_en_paralelo.php` como proceso hijo.
- **Una cita sin filas en `cita_servicio` dura CERO minutos** (`fn_cita_duracion` sale de ahí),
  así que no se pisa con nada. Una prueba de solapes que agende sin servicios pasa siempre sin
  medir nada — ya pasó al escribirla.
- **`AccesoTest::las_pantallas_de_seguridad_se_dibujan_enteras` abre las doce pantallas del
  módulo.** Un `route()` con el nombre viejo o una columna mal escrita no se notan hasta que
  alguien abre la pantalla: revientan al dibujarla, no al arrancar. Fue lo que destapó que
  **Auditoría consultaba `a.fecha` y la columna es `fecha_hora`**, o sea que devolvía 500 desde
  la 6.1.1 sin que nadie lo viera. Si sumás una pantalla al módulo, agregala a esa lista.

## Fuera de alcance del TCC

Pasarelas de pago, SIFEN, app móvil nativa (se cubre con el navegador responsivo),
**notas de débito** (descartadas por el usuario) y **SMS / WhatsApp**, que quedaron
**en pausa por decisión del usuario**: se encienden cargando credenciales de un proveedor.
Mientras no haya canal de celular, el **correo es obligatorio en el registro**, porque una cuenta creada solo
con celular nunca recibiría su código. Multisucursal y facturación **sí** se implementaron aunque excedían el
alcance declarado: conviene justificarlo en el documento del TCC.

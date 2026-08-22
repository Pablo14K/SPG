-- =====================================================================
--  Deja la base EN ESTADO DE ENTREGA: esquema al día, catálogo demo
--  completo y CERO operación.
--
--  Reemplaza a `limpiar_base.sql`, que es anterior a la 7.13.0 y borra el
--  catálogo comercial —servicios, productos, proveedores, timbrados— que
--  desde entonces SÍ se entrega: correrlo deja la base sin nada que agendar.
--
--  Se corre sobre una COPIA, nunca sobre la base de trabajo, y el volcado
--  se genera desde esa copia. Regenerarlo desde la base con la que se
--  estuvo probando es lo que metió «Bella Estilo», un logo, una sucursal
--  de más y filas de operación en el .sql de la 7.42.0.
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 0;

-- --- Citas y atención -------------------------------------------------
TRUNCATE TABLE producto_utilizado;
TRUNCATE TABLE servicio_realizado;
TRUNCATE TABLE cita_servicio;
TRUNCATE TABLE cita_pedido;
TRUNCATE TABLE token_cita;
TRUNCATE TABLE calificacion;
TRUNCATE TABLE sena_solicitud;
TRUNCATE TABLE cita;
TRUNCATE TABLE ausencia_agenda;

-- --- Dinero que entra -------------------------------------------------
TRUNCATE TABLE cobro_tarjeta;
TRUNCATE TABLE cobro_banco;
TRUNCATE TABLE cobro;
TRUNCATE TABLE factura_descuento;
TRUNCATE TABLE detalle_factura;
TRUNCATE TABLE factura_electronica;
TRUNCATE TABLE factura;
TRUNCATE TABLE movimiento_caja;
TRUNCATE TABLE caja;

-- --- Dinero que sale --------------------------------------------------
--  **Estas siete faltaban**, y por eso el .sql se entregó con una compra
--  ajena adentro, colgada de una sucursal que ya no existía. Lo encontró
--  la simulación del 20/08.
TRUNCATE TABLE detalle_pago_personal;
TRUNCATE TABLE pago_personal;
TRUNCATE TABLE detalle_pago_proveedor;
TRUNCATE TABLE pago_proveedor;
TRUNCATE TABLE compra_cuota;
TRUNCATE TABLE detalle_compra;
TRUNCATE TABLE compra;

-- --- Inventario, fidelización, personal, avisos -----------------------
TRUNCATE TABLE movimiento_inventario;
TRUNCATE TABLE movimiento_punto;
TRUNCATE TABLE canje;
TRUNCATE TABLE asistencia;
TRUNCATE TABLE notificacion;
TRUNCATE TABLE auditoria;

-- --- La marca es del salón que instala, no del que probó --------------
UPDATE configuracion SET nombre_salon = 'Peluquería Luque', logo = NULL
 WHERE id_configuracion = 1;

-- --- Un solo local ----------------------------------------------------
--  El segundo lo crea cada salón cuando lo abre. Se sueltan primero las
--  filas que lo referencian: con las claves desactivadas, borrar la
--  sucursal a secas deja huérfanos que nadie ve hasta que algo los pisa.
DELETE FROM usuario_sucursal   WHERE id_sucursal <> 1;
DELETE FROM servicio_sucursal  WHERE id_sucursal <> 1;
DELETE FROM producto_sucursal  WHERE id_sucursal <> 1;
DELETE FROM canjeable_sucursal WHERE id_sucursal <> 1;
DELETE FROM timbrado           WHERE id_sucursal <> 1;
DELETE FROM comision           WHERE id_sucursal <> 1;
DELETE FROM usuario_turno WHERE id_turno IN (SELECT id_turno FROM turno_laboral WHERE id_sucursal <> 1);
DELETE FROM turno_dia     WHERE id_turno IN (SELECT id_turno FROM turno_laboral WHERE id_sucursal <> 1);
DELETE FROM turno_laboral WHERE id_sucursal <> 1;
UPDATE usuario SET id_sucursal = 1 WHERE id_sucursal <> 1;
DELETE FROM sucursal WHERE id_sucursal <> 1;

SET FOREIGN_KEY_CHECKS = 1;

-- --- Los contadores vuelven a empezar ---------------------------------
ALTER TABLE cita      AUTO_INCREMENT = 1;
ALTER TABLE factura   AUTO_INCREMENT = 1;
ALTER TABLE cobro     AUTO_INCREMENT = 1;
ALTER TABLE caja      AUTO_INCREMENT = 1;
ALTER TABLE compra    AUTO_INCREMENT = 1;
ALTER TABLE auditoria AUTO_INCREMENT = 1;
ALTER TABLE sucursal  AUTO_INCREMENT = 2;

-- ---------------------------------------------------------------------------
--  4) EL CATÁLOGO DEMO SE REHACE, NO SE CONSERVA
-- ---------------------------------------------------------------------------
--  Lo de acá abajo es lo que MÁS se ensucia, porque son las tablas que se
--  tocan probando: se le cambia el turno a alguien, se le marcan servicios,
--  se agrega un producto. Como el volcado sale de una base que estuvo en uso,
--  todo eso viaja al archivo que instala el salón — y ahí ya nadie sabe qué
--  era demo y qué era prueba.
--
--  El caso concreto: el `.sql` que se entregaba hasta la 7.62.0 tenía a Marta
--  SIN turno (o sea invisible en la agenda), a Rocío y a Sofía SIN servicios
--  (o sea ofrecidas para todo), el Turno Mañana sin martes ni sábado, y
--  «Coloración completa» DADA DE BAJA — un resto de la auditoría del
--  11/08/2026, cuando el QA la desactivó para probar que el Profesional podía.
--
--  Por eso se vacían y se vuelven a cargar desde `datos_demo.sql`, que es el
--  único lugar donde ese catálogo está escrito a propósito.
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM usuario_servicio;
DELETE FROM usuario_turno;
DELETE FROM turno_dia;
DELETE FROM turno_laboral;
DELETE FROM canjeable_sucursal;
DELETE FROM servicio_canjeable;
DELETE FROM producto_sucursal;
DELETE FROM servicio_sucursal;
DELETE FROM comision;
DELETE FROM timbrado;
DELETE FROM producto;
DELETE FROM servicio;
DELETE FROM proveedor;

-- ---------------------------------------------------------------------------
--  5) LAS PERSONAS DE VERDAD NO SE ENTREGAN
-- ---------------------------------------------------------------------------
--  **Es lo más grave que puede llevarse un volcado**, y se llevó tres: el
--  `.sql` que se entregaba tenía dos clientas reales con su nombre completo y
--  su Gmail, más el correo personal del desarrollador en la cuenta `cliente`.
--  Ese archivo lo instala un salón que no tiene nada que ver con ellas.
--
--  Quedan sólo `admin` y `cliente`, que son las dos cuentas del instalador, y
--  el equipo demo lo vuelve a crear `datos_demo.sql`.
DELETE FROM preferencia_cliente      WHERE id_cliente NOT IN (SELECT id_cliente FROM (SELECT c.id_cliente FROM cliente c JOIN usuario u ON u.id_usuario = c.id_usuario WHERE u.username = 'cliente') t);
DELETE FROM preferencia_recordatorio WHERE id_cliente NOT IN (SELECT id_cliente FROM (SELECT c.id_cliente FROM cliente c JOIN usuario u ON u.id_usuario = c.id_usuario WHERE u.username = 'cliente') t);
DELETE FROM notificacion             WHERE id_cliente IS NOT NULL;
DELETE FROM cliente                  WHERE id_usuario IS NULL OR id_usuario NOT IN (SELECT id_usuario FROM usuario WHERE username = 'cliente');

DELETE FROM credencial_webauthn WHERE id_usuario NOT IN (SELECT id_usuario FROM usuario WHERE username IN ('admin','cliente'));
DELETE FROM token_seguridad     WHERE id_usuario NOT IN (SELECT id_usuario FROM usuario WHERE username IN ('admin','cliente'));
DELETE FROM preferencia_usuario WHERE id_usuario NOT IN (SELECT id_usuario FROM usuario WHERE username IN ('admin','cliente'));
DELETE FROM usuario_sucursal    WHERE id_usuario NOT IN (SELECT id_usuario FROM usuario WHERE username IN ('admin','cliente'));
DELETE FROM usuario             WHERE username NOT IN ('admin','cliente');

-- Y las `persona` que quedaron sin dueño: nadie las referencia ya.
DELETE FROM persona
 WHERE id_persona NOT IN (SELECT id_persona FROM usuario)
   AND id_persona NOT IN (SELECT id_persona FROM cliente)
   AND id_persona NOT IN (SELECT id_persona FROM proveedor);

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
--  DESPUÉS DE ESTO HAY QUE CORRER `datos_demo.sql`
-- ---------------------------------------------------------------------------
--      mysql -u root peluqueria_bd < basededatos/dejar_lista.sql
--      mysql -u root peluqueria_bd < basededatos/datos_demo.sql
--
--  Son dos pasos y hacen falta los dos: esto deja la base limpia, y el otro
--  vuelve a poner el catálogo con el que el salón puede probar el sistema.

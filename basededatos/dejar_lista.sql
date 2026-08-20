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

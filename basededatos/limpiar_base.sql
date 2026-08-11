-- =====================================================================
--  Deja `peluqueria_bd` en estado de fábrica: esquema al día, sin datos.
--  Conserva los catálogos del sistema (sin ellos el sistema no arranca),
--  la sucursal 1 y las dos cuentas del instalador: admin y cliente.
--  Respaldo previo: 1mes_simulacion.sql
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 0;

-- --- Operación: citas, atención y consumo ---------------------------
TRUNCATE TABLE producto_utilizado;
TRUNCATE TABLE servicio_realizado;
TRUNCATE TABLE cita_servicio;
TRUNCATE TABLE cita_pedido;
TRUNCATE TABLE token_cita;
TRUNCATE TABLE calificacion;
TRUNCATE TABLE cita;
TRUNCATE TABLE ausencia_agenda;

-- --- Dinero: facturación, cobros y caja ------------------------------
TRUNCATE TABLE cobro_tarjeta;
TRUNCATE TABLE cobro_banco;
TRUNCATE TABLE cobro;
TRUNCATE TABLE factura_descuento;
TRUNCATE TABLE detalle_factura;
TRUNCATE TABLE factura;
TRUNCATE TABLE movimiento_caja;
TRUNCATE TABLE caja;
TRUNCATE TABLE detalle_pago_personal;
TRUNCATE TABLE pago_personal;
TRUNCATE TABLE detalle_pago_proveedor;
TRUNCATE TABLE pago_proveedor;

-- --- Inventario y compras --------------------------------------------
TRUNCATE TABLE movimiento_inventario;
TRUNCATE TABLE detalle_compra;
TRUNCATE TABLE compra;
TRUNCATE TABLE producto;
TRUNCATE TABLE proveedor;

-- --- Catálogo comercial del salón ------------------------------------
TRUNCATE TABLE servicio;
TRUNCATE TABLE comision;
TRUNCATE TABLE timbrado;

-- --- Personal, turnos y asistencia -----------------------------------
TRUNCATE TABLE asistencia;
TRUNCATE TABLE usuario_turno;
TRUNCATE TABLE turno_dia;
TRUNCATE TABLE turno_laboral;

-- --- Fidelización, avisos y preferencias -----------------------------
TRUNCATE TABLE movimiento_punto;
TRUNCATE TABLE notificacion;
TRUNCATE TABLE preferencia_recordatorio;
TRUNCATE TABLE preferencia_usuario;
TRUNCATE TABLE contacto_soporte;

-- --- Seguridad y auditoría -------------------------------------------
TRUNCATE TABLE credencial_webauthn;
TRUNCATE TABLE token_seguridad;
TRUNCATE TABLE auditoria;

-- --- Promociones de prueba: quedan sólo los 3 descuentos de nivel ----
DELETE FROM descuento WHERE id_descuento NOT IN (1,2,3);

-- --- Roles: quedan los 4 del sistema. El 6 (Gerente) era de la prueba
DELETE FROM rol_modulo WHERE id_rol NOT IN (1,2,3,4);
DELETE FROM rol        WHERE id_rol NOT IN (1,2,3,4);

-- --- Cuentas: sólo admin (1) y cliente (2), como deja el instalador --
DELETE FROM usuario_sucursal WHERE id_usuario <> 1;
DELETE FROM cliente          WHERE id_cliente <> 1;
DELETE FROM usuario          WHERE id_usuario NOT IN (1,2);
DELETE FROM persona          WHERE id_persona NOT IN (1,2);

-- --- Una sola sucursal -----------------------------------------------
DELETE FROM sucursal WHERE id_sucursal <> 1;

-- --- Numeración desde donde corresponde ------------------------------
ALTER TABLE persona   AUTO_INCREMENT = 3;
ALTER TABLE usuario   AUTO_INCREMENT = 3;
ALTER TABLE cliente   AUTO_INCREMENT = 2;
ALTER TABLE sucursal  AUTO_INCREMENT = 2;
ALTER TABLE descuento AUTO_INCREMENT = 4;
ALTER TABLE rol       AUTO_INCREMENT = 5;

SET FOREIGN_KEY_CHECKS = 1;

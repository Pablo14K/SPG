-- =====================================================================
-- SGP 7.110.0 — La cuenta bancaria del local, para saber si hay plata
--               antes de pagarle a un profesional o a un proveedor
-- =====================================================================
--
-- **Un pago por transferencia no lo frenaba nada.** El efectivo sí: desde la
-- 5.5.0 no se puede sacar del cajón más de lo que hay, y `fn_caja_saldo` lo
-- sabe porque el sistema conoce cada movimiento de ese cajón. Con el banco no
-- pasaba: el propio código lo decía al lado del `if` —«los pagos por banco no
-- se frenan: no salen del cajón, salen de la cuenta»— y de la cuenta no se
-- sabía nada. O sea que se podía liquidar el mes entero contra una cuenta
-- vacía, y enterarse cuando el banco rechazara la transferencia.
--
-- ---------------------------------------------------------------------
-- 1) `dato_pago_sucursal.saldo_declarado` — lo que el salón leyó en su banco
-- ---------------------------------------------------------------------
--
-- **La cuenta ya estaba modelada**: `dato_pago_sucursal` es el registro de las
-- cuentas del salón desde la 7.67.0 —entidad, tipo, número, titular, alias, y
-- por sucursal—. Lo único que le faltaba era cuánta plata tiene.
--
-- **Y eso NO se puede derivar, así que se guarda.** El sistema conoce lo que
-- SALE de la cuenta —los pagos que él mismo registró— pero no lo que entra: una
-- transferencia de una clienta llega al banco sin pasar por acá, y `cobro` no
-- dice a qué cuenta del salón cayó. Reconstruir el saldo sumando cobros sería
-- inventarlo.
--
-- Lo que se guarda entonces es un **hecho observado**: cuánto decía el banco y
-- cuándo se miró. Es exactamente el criterio de `caja.monto_contado`, que
-- tampoco se deduce de nada y por eso sí se guarda — y de paso el mismo por el
-- que la diferencia del arqueo NO se guarda: ésa sí se calcula.
--
-- **Los dos van juntos o ninguno.** Un saldo sin fecha no dice nada —¿de
-- cuándo?— y una fecha sin saldo tampoco. Lo hace cumplir un CHECK.
--
-- **Admite NULL a propósito**, igual que el conteo de la caja: de una cuenta
-- que nadie declaró no se sabe el saldo, y **`fn_cuenta_saldo` devuelve NULL**
-- en vez de cero. Un cero ahí se leería como «no hay plata», que es afirmar
-- algo que nadie comprobó.

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'dato_pago_sucursal'
                AND column_name = 'saldo_declarado');
SET @sql := IF(@hay = 0,
    'ALTER TABLE dato_pago_sucursal
       ADD COLUMN saldo_declarado DECIMAL(14,2) NULL AFTER observacion,
       ADD COLUMN saldo_declarado_en DATETIME NULL AFTER saldo_declarado',
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @hay := (SELECT COUNT(*) FROM information_schema.check_constraints
              WHERE constraint_schema = DATABASE() AND constraint_name = 'chk_dpago_saldo');
SET @sql := IF(@hay = 0,
    'ALTER TABLE dato_pago_sucursal
       ADD CONSTRAINT chk_dpago_saldo CHECK (
           (saldo_declarado IS NULL AND saldo_declarado_en IS NULL)
        OR (saldo_declarado IS NOT NULL AND saldo_declarado_en IS NOT NULL AND saldo_declarado >= 0))',
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 2) De QUÉ cuenta salió cada pago
-- ---------------------------------------------------------------------
--
-- Es la misma columna que `id_caja` en esas dos tablas, un renglón más allá:
-- con dos cuentas —la del salón y la de la propietaria, o una por sucursal— sin
-- esto no hay forma de saber cuál se vació.
--
-- **NULL vale y significa «no se dijo»**, igual que `id_caja` en los pagos
-- viejos: lo que se pagó antes de esta versión no tiene cuenta anotada, y
-- `fn_cuenta_saldo` sólo cuenta desde la fecha del saldo declarado, así que un
-- pago sin cuenta no descuadra a nadie.

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'pago_proveedor'
                AND column_name = 'id_dato_pago');
SET @sql := IF(@hay = 0,
    'ALTER TABLE pago_proveedor
       ADD COLUMN id_dato_pago INT UNSIGNED NULL AFTER id_caja,
       ADD CONSTRAINT fk_pagoprov_dpago FOREIGN KEY (id_dato_pago)
           REFERENCES dato_pago_sucursal (id_dato_pago)',
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'pago_personal'
                AND column_name = 'id_dato_pago');
SET @sql := IF(@hay = 0,
    'ALTER TABLE pago_personal
       ADD COLUMN id_dato_pago INT UNSIGNED NULL AFTER id_caja,
       ADD CONSTRAINT fk_pagopers_dpago FOREIGN KEY (id_dato_pago)
           REFERENCES dato_pago_sucursal (id_dato_pago)',
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 3) `fn_cuenta_saldo` — cuánto queda, con lo que el sistema PUEDE afirmar
-- ---------------------------------------------------------------------
--
-- **Es un PISO, no un saldo exacto, y esa diferencia es el diseño entero.**
-- Se parte del saldo declarado y se le restan los pagos que el sistema hizo
-- desde entonces; **lo que entró no se suma**, porque el sistema no lo sabe.
-- Así el número sólo puede quedar por DEBAJO del real, nunca por encima — que
-- es la única dirección segura para la pregunta que contesta: «¿alcanza para
-- pagar esto?».
--
-- Por eso **avisa y no bloquea**, al revés que el efectivo. El saldo del cajón
-- es exacto: el sistema conoce cada guaraní que entró y salió, así que rechazar
-- un egreso mayor es correcto. Éste es aproximado por abajo, y bloquear con un
-- número que sabemos incompleto frenaría un pago legítimo — apagar algo que hoy
-- funciona, que es justo lo que la regla del proyecto manda no hacer sin
-- preguntar.
--
-- Sólo cuentan los pagos **posteriores al saldo declarado**: lo anterior ya
-- estaba descontado cuando el salón miró el banco. Volver a declarar el saldo
-- es, literalmente, hacer el arqueo de la cuenta.

DROP FUNCTION IF EXISTS fn_cuenta_saldo;
DELIMITER $$
CREATE FUNCTION fn_cuenta_saldo(p_id_dato_pago INT UNSIGNED)
RETURNS DECIMAL(14,2)
READS SQL DATA
BEGIN
  DECLARE v_base  DECIMAL(14,2) DEFAULT NULL;
  DECLARE v_desde DATETIME DEFAULT NULL;
  DECLARE v_prov  DECIMAL(14,2) DEFAULT 0;
  DECLARE v_pers  DECIMAL(14,2) DEFAULT 0;

  SELECT saldo_declarado, saldo_declarado_en INTO v_base, v_desde
  FROM dato_pago_sucursal WHERE id_dato_pago = p_id_dato_pago;

  -- Nadie lo declaró: NO se sabe. Devolver 0 sería afirmar que está vacía.
  IF v_base IS NULL OR v_desde IS NULL THEN
    RETURN NULL;
  END IF;

  SELECT COALESCE(SUM(fn_pago_proveedor_monto(pp.id_pago_proveedor)), 0) INTO v_prov
  FROM pago_proveedor pp
  WHERE pp.id_dato_pago = p_id_dato_pago
    AND pp.id_estado_pago_proveedor = 1
    AND pp.fecha >= v_desde;

  SELECT COALESCE(SUM(fn_pago_personal_monto(pg.id_pago_personal)), 0) INTO v_pers
  FROM pago_personal pg
  WHERE pg.id_dato_pago = p_id_dato_pago
    AND pg.id_estado_pago = 1
    AND pg.fecha >= v_desde;

  RETURN v_base - v_prov - v_pers;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 4) `cita_servicio.terminado_en` — cada profesional cierra SU parte
-- ---------------------------------------------------------------------
--
-- **Una cita de 120 minutos dejaba ocupadas 120 minutos a las dos.** La
-- clienta pide mechas con Lucía y manicura con Rocío: Rocío termina lo suyo en
-- 10 minutos y seguía apareciendo ocupada las dos horas, porque «atendida» era
-- un estado de la CITA y no había forma de decir que una parte ya terminó. Con
-- eso la agenda le negaba a Rocío casi dos horas que tenía libres.
--
-- **Es un hecho nuevo y no se deduce de nada**, así que la 3FN se mantiene:
-- `servicio_realizado.fecha_hora` es cuándo se REGISTRÓ una atención, que es
-- otra entidad y otro momento —un servicio se puede cerrar sin haberse
-- realizado, y ahí no hay fila ninguna—.
--
-- **Admite NULL y eso es «todavía no terminó»**, que es como nace toda cita.

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'cita_servicio'
                AND column_name = 'terminado_en');
SET @sql := IF(@hay = 0,
    'ALTER TABLE cita_servicio ADD COLUMN terminado_en DATETIME NULL AFTER orden',
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- **Y con eso la agenda se libera sola, tocando UNA función.**
--
-- `fn_cita_duracion_de` dice cuánto le ocupa esa cita a esa persona, y
-- `fn_verificar_disponibilidad` descarta el solape con `> 0`: si lo que le
-- queda por hacer es nada, deja de estar ocupada y el resto del motor se
-- entera solo. **El espejo de PHP no hay que tocarlo**: `Agenda` llama a esta
-- misma función desde su consulta, así que la pantalla y el guardado no se
-- pueden desfasar — que es el riesgo que este proyecto tiene anotado cada vez
-- que se toca una regla de disponibilidad.

DROP FUNCTION IF EXISTS fn_cita_duracion_de;
DELIMITER $$
CREATE FUNCTION fn_cita_duracion_de(p_id_cita INT UNSIGNED, p_id_usuario INT UNSIGNED)
RETURNS INT(11)
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_dur INT DEFAULT 0;

  SELECT COALESCE(SUM(COALESCE(ps.duracion_min, s.duracion_min)), 0) INTO v_dur
    FROM cita_servicio cs
    JOIN cita c     ON c.id_cita = cs.id_cita
    JOIN servicio s ON s.id_servicio = cs.id_servicio
    LEFT JOIN usuario u ON u.id_usuario = p_id_usuario
    LEFT JOIN persona_servicio ps
           ON ps.id_persona = u.id_persona
          AND ps.id_servicio = s.id_servicio AND ps.activo = 1
   WHERE cs.id_cita = p_id_cita
     AND COALESCE(cs.id_usuario, c.id_usuario) = p_id_usuario
     -- Lo que ya cerró no le ocupa más la agenda.
     AND cs.terminado_en IS NULL;

  RETURN v_dur;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 5) Cobrar y facturar pasan a ser del Administrador y del Asistente
-- ---------------------------------------------------------------------
--
-- **Decisión del usuario.** El rol Profesional venía con `facturacion.cobros` y
-- `facturacion.facturas` desde siempre, y la 7.29.0 los conservó a propósito
-- —«sacarle eso lo dejaría sin poder trabajar en el mostrador»—. Hoy el salón
-- decide lo contrario: quien atiende, atiende; quien cobra, cobra.
--
-- **La consecuencia hay que tenerla presente, y es la misma que aquella vez
-- anotó para la caja**: si el salón abre con una profesional sola, **no va a
-- poder cobrar ni emitir un comprobante** hasta que llegue alguien con
-- permiso. La cita se atiende y se cierra igual; lo que espera es el dinero.
--
-- **Sólo se toca el rol 2, que es el que se entrega.** Un rol que el salón haya
-- creado desde la pantalla de Roles es suyo y se administra desde ahí — meterse
-- con él sería decidir por el salón algo que el salón ya decidió.
--
-- Re-ejecutable: borrar lo que ya no está no hace nada.

DELETE rm FROM rol_modulo rm
 WHERE rm.id_rol = 2
   AND rm.modulo IN ('facturacion.cobros', 'facturacion.facturas');

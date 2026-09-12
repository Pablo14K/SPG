-- =====================================================================
-- SGP 7.121.0 — La cuenta bancaria entra a Tesorería como una CAJA
--               dedicada al banco: lo que entra por transferencia se
--               suma, lo que sale se resta, y la caja queda para el
--               efectivo
-- =====================================================================
--
-- `dato_pago_sucursal` nació en la 7.67.0 como «a dónde le decimos a la
-- clienta que transfiera», y en la 7.110.0 ganó el saldo declarado y
-- pasó a ser de donde salen las transferencias a proveedores y al
-- personal. Con eso ya no es un dato de pago: **es la cuenta bancaria
-- del salón**, y así se llama desde hoy — `cuenta_bancaria`, con su
-- clave `id_cuenta`.
--
-- Lo que cambia de fondo, pedido por el usuario:
--
--   · **Lo que ENTRA por transferencia se suma a la cuenta**, incluidas
--     las señas. Hasta acá `fn_cuenta_saldo` sólo restaba —el sistema
--     «no veía lo que entra»— y el saldo era un piso. Ahora `cobro`
--     dice a qué cuenta cayó (`cobro.id_cuenta`) y la función lo suma.
--   · **Un movimiento manual puede ser de la cuenta y no del cajón**:
--     `movimiento_caja.id_cuenta`, con `id_caja` que pasa a admitir
--     NULL y un CHECK que exige uno de los dos y no ambos.
--   · **Cuál cuenta se le muestra a la clienta lo decide un
--     interruptor** (`para_senas`): hasta hoy se le mostraban todas las
--     activas, así que las activas de hoy quedan marcadas para que
--     nada cambie hasta que el salón elija.
--
-- Nada de esto toca datos: los pagos ya anotados conservan su cuenta
-- (la columna se renombra, no se recrea) y los cobros viejos quedan sin
-- cuenta, que es «no se dijo», igual que `id_caja` en los pagos de
-- antes de la 7.36.3.
--
-- Re-ejecutable: cada paso mira `information_schema` antes de actuar.
--
--     docker exec sgp_app sh -c 'mysql --skip-ssl -hbd -uroot -p"$DB_PASSWORD" \
--       --default-character-set=utf8mb4 peluqueria_bd \
--       < basededatos/actualizaciones/2026-09-12_7.121.0.sql'
--
-- Al terminar: `docker exec sgp_app php artisan sgp:diagnostico --produccion`
-- tiene que decir 88 CHECK, 22 procedimientos, 43 funciones y «Todo en orden».

-- --- 1. La tabla se llama por lo que es ---------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'dato_pago_sucursal');
SET @ya  := (SELECT COUNT(*) FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'cuenta_bancaria');
SET @sql := IF(@hay = 1 AND @ya = 0,
  'RENAME TABLE dato_pago_sucursal TO cuenta_bancaria', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- Y su clave también. MariaDB 10.4 renombra la columna aunque haya claves
-- foráneas apoyadas en ella: las FK siguen apuntando bien (comprobado).
SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                AND column_name = 'id_dato_pago');
SET @sql := IF(@hay = 1,
  'ALTER TABLE cuenta_bancaria CHANGE id_dato_pago id_cuenta INT UNSIGNED NOT NULL AUTO_INCREMENT',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- --- 2. Las dos tablas que ya decían de qué cuenta salió el pago --------
SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'pago_proveedor'
                AND column_name = 'id_dato_pago');
SET @sql := IF(@hay = 1,
  'ALTER TABLE pago_proveedor CHANGE id_dato_pago id_cuenta INT UNSIGNED NULL', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'pago_personal'
                AND column_name = 'id_dato_pago');
SET @sql := IF(@hay = 1,
  'ALTER TABLE pago_personal CHANGE id_dato_pago id_cuenta INT UNSIGNED NULL', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- --- 3. Los nombres de las restricciones siguen a la tabla --------------
-- Son cosméticos para el sistema y no para el documento del TCC, donde
-- `chk_dpago_*` colgando de `cuenta_bancaria` se lee como un resto.
-- Cada uno: si existe el nombre viejo y no el nuevo, se suelta y se
-- vuelve a crear con el nuevo. Los índices nuevos se crean ANTES de
-- soltar los viejos, porque las FK de `id_sucursal` e `id_metodo_pago`
-- se apoyan en ellos.
SET @viejo := (SELECT COUNT(*) FROM information_schema.check_constraints
                WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                  AND constraint_name = 'chk_dpago_entidad');
SET @sql := IF(@viejo = 1, 'ALTER TABLE cuenta_bancaria DROP CONSTRAINT chk_dpago_entidad', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @hay := (SELECT COUNT(*) FROM information_schema.check_constraints
              WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                AND constraint_name = 'chk_cuenta_entidad');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cuenta_bancaria ADD CONSTRAINT chk_cuenta_entidad CHECK (CHAR_LENGTH(TRIM(entidad)) >= 2)', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @viejo := (SELECT COUNT(*) FROM information_schema.check_constraints
                WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                  AND constraint_name = 'chk_dpago_titular');
SET @sql := IF(@viejo = 1, 'ALTER TABLE cuenta_bancaria DROP CONSTRAINT chk_dpago_titular', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @hay := (SELECT COUNT(*) FROM information_schema.check_constraints
              WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                AND constraint_name = 'chk_cuenta_titular');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cuenta_bancaria ADD CONSTRAINT chk_cuenta_titular CHECK (CHAR_LENGTH(TRIM(titular)) >= 3)', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @viejo := (SELECT COUNT(*) FROM information_schema.check_constraints
                WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                  AND constraint_name = 'chk_dpago_alias_tipo');
SET @sql := IF(@viejo = 1, 'ALTER TABLE cuenta_bancaria DROP CONSTRAINT chk_dpago_alias_tipo', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @hay := (SELECT COUNT(*) FROM information_schema.check_constraints
              WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                AND constraint_name = 'chk_cuenta_alias_tipo');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cuenta_bancaria ADD CONSTRAINT chk_cuenta_alias_tipo CHECK (alias_tipo IS NULL OR alias_tipo IN (''CI'',''RUC'',''CELULAR'',''EMAIL''))', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @viejo := (SELECT COUNT(*) FROM information_schema.check_constraints
                WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                  AND constraint_name = 'chk_dpago_saldo');
SET @sql := IF(@viejo = 1, 'ALTER TABLE cuenta_bancaria DROP CONSTRAINT chk_dpago_saldo', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @hay := (SELECT COUNT(*) FROM information_schema.check_constraints
              WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                AND constraint_name = 'chk_cuenta_saldo');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cuenta_bancaria ADD CONSTRAINT chk_cuenta_saldo CHECK (
      (saldo_declarado IS NULL AND saldo_declarado_en IS NULL)
   OR (saldo_declarado IS NOT NULL AND saldo_declarado_en IS NOT NULL AND saldo_declarado >= 0))', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- El único y el índice: primero el nuevo, después se suelta el viejo.
SET @hay := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = 'cuenta_bancaria' AND index_name = 'uq_cuenta_nro');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cuenta_bancaria ADD UNIQUE KEY uq_cuenta_nro (id_sucursal, id_metodo_pago, numero_cuenta)', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @viejo := (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'cuenta_bancaria' AND index_name = 'uq_dpago_cuenta');
SET @sql := IF(@viejo > 0, 'ALTER TABLE cuenta_bancaria DROP INDEX uq_dpago_cuenta', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @hay := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = 'cuenta_bancaria' AND index_name = 'ix_cuenta_suc');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cuenta_bancaria ADD KEY ix_cuenta_suc (id_sucursal, activo, orden)', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @viejo := (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'cuenta_bancaria' AND index_name = 'ix_dpago_suc');
SET @sql := IF(@viejo > 0, 'ALTER TABLE cuenta_bancaria DROP INDEX ix_dpago_suc', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- Las claves foráneas: se sueltan y se vuelven a declarar con su nombre.
-- El índice que la FK vieja había creado (`fk_dpago_metodo`) queda con
-- ese nombre y se reemplaza igual que los otros.
SET @viejo := (SELECT COUNT(*) FROM information_schema.table_constraints
                WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                  AND constraint_name = 'fk_dpago_metodo' AND constraint_type = 'FOREIGN KEY');
SET @sql := IF(@viejo = 1, 'ALTER TABLE cuenta_bancaria DROP FOREIGN KEY fk_dpago_metodo', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @hay := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = 'cuenta_bancaria' AND index_name = 'ix_cuenta_metodo');
SET @sql := IF(@hay = 0, 'ALTER TABLE cuenta_bancaria ADD KEY ix_cuenta_metodo (id_metodo_pago)', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @viejo := (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'cuenta_bancaria' AND index_name = 'fk_dpago_metodo');
SET @sql := IF(@viejo > 0, 'ALTER TABLE cuenta_bancaria DROP INDEX fk_dpago_metodo', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @hay := (SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                AND constraint_name = 'fk_cuenta_metodo' AND constraint_type = 'FOREIGN KEY');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cuenta_bancaria ADD CONSTRAINT fk_cuenta_metodo FOREIGN KEY (id_metodo_pago) REFERENCES metodo_pago (id_metodo_pago)', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @viejo := (SELECT COUNT(*) FROM information_schema.table_constraints
                WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                  AND constraint_name = 'fk_dpago_sucursal' AND constraint_type = 'FOREIGN KEY');
SET @sql := IF(@viejo = 1, 'ALTER TABLE cuenta_bancaria DROP FOREIGN KEY fk_dpago_sucursal', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @hay := (SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                AND constraint_name = 'fk_cuenta_sucursal' AND constraint_type = 'FOREIGN KEY');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cuenta_bancaria ADD CONSTRAINT fk_cuenta_sucursal FOREIGN KEY (id_sucursal) REFERENCES sucursal (id_sucursal)', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- Y las de los dos pagos, que nombraban `dpago`.
SET @viejo := (SELECT COUNT(*) FROM information_schema.table_constraints
                WHERE constraint_schema = DATABASE() AND table_name = 'pago_proveedor'
                  AND constraint_name = 'fk_pagoprov_dpago' AND constraint_type = 'FOREIGN KEY');
SET @sql := IF(@viejo = 1, 'ALTER TABLE pago_proveedor DROP FOREIGN KEY fk_pagoprov_dpago', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @hay := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = 'pago_proveedor' AND index_name = 'ix_pprov_cuenta');
SET @sql := IF(@hay = 0, 'ALTER TABLE pago_proveedor ADD KEY ix_pprov_cuenta (id_cuenta)', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @viejo := (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'pago_proveedor' AND index_name = 'fk_pagoprov_dpago');
SET @sql := IF(@viejo > 0, 'ALTER TABLE pago_proveedor DROP INDEX fk_pagoprov_dpago', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @hay := (SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE constraint_schema = DATABASE() AND table_name = 'pago_proveedor'
                AND constraint_name = 'fk_pprov_cuenta' AND constraint_type = 'FOREIGN KEY');
SET @sql := IF(@hay = 0,
  'ALTER TABLE pago_proveedor ADD CONSTRAINT fk_pprov_cuenta FOREIGN KEY (id_cuenta) REFERENCES cuenta_bancaria (id_cuenta)', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @viejo := (SELECT COUNT(*) FROM information_schema.table_constraints
                WHERE constraint_schema = DATABASE() AND table_name = 'pago_personal'
                  AND constraint_name = 'fk_pagopers_dpago' AND constraint_type = 'FOREIGN KEY');
SET @sql := IF(@viejo = 1, 'ALTER TABLE pago_personal DROP FOREIGN KEY fk_pagopers_dpago', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @hay := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = 'pago_personal' AND index_name = 'ix_pp_cuenta');
SET @sql := IF(@hay = 0, 'ALTER TABLE pago_personal ADD KEY ix_pp_cuenta (id_cuenta)', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @viejo := (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'pago_personal' AND index_name = 'fk_pagopers_dpago');
SET @sql := IF(@viejo > 0, 'ALTER TABLE pago_personal DROP INDEX fk_pagopers_dpago', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @hay := (SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE constraint_schema = DATABASE() AND table_name = 'pago_personal'
                AND constraint_name = 'fk_pp_cuenta' AND constraint_type = 'FOREIGN KEY');
SET @sql := IF(@hay = 0,
  'ALTER TABLE pago_personal ADD CONSTRAINT fk_pp_cuenta FOREIGN KEY (id_cuenta) REFERENCES cuenta_bancaria (id_cuenta)', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- --- 4. Cuál se le muestra a la clienta para la seña -------------------
-- Es un interruptor y no «todas las activas»: una cuenta puede existir
-- para pagarle a proveedores y no ser la que el salón quiere que la
-- clienta use. Las activas de hoy quedan marcadas para que nada cambie
-- hasta que el salón elija.
SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                AND column_name = 'para_senas');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cuenta_bancaria ADD COLUMN para_senas TINYINT(1) NOT NULL DEFAULT 0 AFTER activo', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
SET @sql := IF(@hay = 0, 'UPDATE cuenta_bancaria SET para_senas = 1 WHERE activo = 1', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- --- 5. A qué cuenta cayó el cobro -------------------------------------
-- NULL es «no se dijo» —el efectivo, la tarjeta, y todo cobro anterior a
-- esta versión—. Sólo las líneas por transferencia, cheque o billetera lo
-- llevan, y la pantalla lo pregunta línea por línea.
SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'cobro' AND column_name = 'id_cuenta');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cobro
     ADD COLUMN id_cuenta INT UNSIGNED NULL AFTER id_caja,
     ADD KEY ix_cobro_cuenta (id_cuenta),
     ADD CONSTRAINT fk_cobro_cuenta FOREIGN KEY (id_cuenta)
         REFERENCES cuenta_bancaria (id_cuenta) ON DELETE SET NULL', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- --- 6. El movimiento manual puede ser de la cuenta ---------------------
-- `id_caja` deja de ser obligatorio y entra `id_cuenta`: un gasto pagado
-- por transferencia o un retiro por el banco sale de la cuenta y no del
-- cajón. El CHECK exige exactamente uno de los dos.
SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'movimiento_caja' AND column_name = 'id_cuenta');
SET @sql := IF(@hay = 0,
  'ALTER TABLE movimiento_caja
     MODIFY id_caja INT UNSIGNED NULL,
     ADD COLUMN id_cuenta INT UNSIGNED NULL AFTER id_caja,
     ADD KEY ix_mc_cuenta (id_cuenta),
     ADD CONSTRAINT fk_mc_cuenta FOREIGN KEY (id_cuenta)
         REFERENCES cuenta_bancaria (id_cuenta)', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @hay := (SELECT COUNT(*) FROM information_schema.check_constraints
              WHERE constraint_schema = DATABASE() AND table_name = 'movimiento_caja'
                AND constraint_name = 'chk_mc_donde');
SET @sql := IF(@hay = 0,
  'ALTER TABLE movimiento_caja ADD CONSTRAINT chk_mc_donde CHECK (
      (id_caja IS NOT NULL AND id_cuenta IS NULL) OR (id_caja IS NULL AND id_cuenta IS NOT NULL))', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- --- 7. `fn_cuenta_saldo`: ahora suma lo que ENTRA -----------------------
-- Hasta acá era un piso: partía de lo declarado y sólo restaba pagos,
-- porque «el sistema no ve lo que entra al banco». Desde que el cobro
-- dice a qué cuenta cayó, sí lo ve — por eso ahora suma los cobros a
-- esa cuenta, y los movimientos manuales sobre ella con su signo. Sigue
-- devolviendo NULL cuando nadie declaró el saldo: NULL es «no se sabe»,
-- no cero. Y sigue contando sólo desde la fecha de la declaración: volver
-- a declararlo es hacer el arqueo de la cuenta.
DROP FUNCTION IF EXISTS fn_cuenta_saldo;
DELIMITER $$
CREATE FUNCTION fn_cuenta_saldo(p_id_cuenta INT UNSIGNED)
RETURNS DECIMAL(14,2)
READS SQL DATA
BEGIN
  DECLARE v_base   DECIMAL(14,2) DEFAULT NULL;
  DECLARE v_desde  DATETIME DEFAULT NULL;
  DECLARE v_cobros DECIMAL(14,2) DEFAULT 0;
  DECLARE v_ing    DECIMAL(14,2) DEFAULT 0;
  DECLARE v_egr    DECIMAL(14,2) DEFAULT 0;
  DECLARE v_prov   DECIMAL(14,2) DEFAULT 0;
  DECLARE v_pers   DECIMAL(14,2) DEFAULT 0;

  SELECT saldo_declarado, saldo_declarado_en INTO v_base, v_desde
  FROM cuenta_bancaria WHERE id_cuenta = p_id_cuenta;

  -- Sin declarar no se sabe: NULL, y que la pantalla lo diga con palabras.
  IF v_base IS NULL OR v_desde IS NULL THEN
    RETURN NULL;
  END IF;

  -- Lo que entró: las transferencias que las clientas hicieron a ESTA
  -- cuenta, señas incluidas. Sólo los cobros vigentes.
  SELECT COALESCE(SUM(co.monto), 0) INTO v_cobros
  FROM cobro co
  WHERE co.id_cuenta = p_id_cuenta
    AND co.id_estado_cobro = 1
    AND co.fecha >= v_desde;

  -- Lo cargado a mano sobre la cuenta: un gasto por transferencia sale,
  -- y un ingreso que alguien cargue entra. Sólo los activos.
  SELECT COALESCE(SUM(CASE WHEN tipo = 'INGRESO' THEN monto END), 0),
         COALESCE(SUM(CASE WHEN tipo = 'EGRESO'  THEN monto END), 0)
    INTO v_ing, v_egr
  FROM movimiento_caja
  WHERE id_cuenta = p_id_cuenta AND activo = 1 AND fecha >= v_desde;

  SELECT COALESCE(SUM(fn_pago_proveedor_monto(pp.id_pago_proveedor)), 0) INTO v_prov
  FROM pago_proveedor pp
  WHERE pp.id_cuenta = p_id_cuenta
    AND pp.id_estado_pago_proveedor = 1
    AND pp.fecha >= v_desde;

  SELECT COALESCE(SUM(fn_pago_personal_monto(pg.id_pago_personal)), 0) INTO v_pers
  FROM pago_personal pg
  WHERE pg.id_cuenta = p_id_cuenta
    AND pg.id_estado_pago = 1
    AND pg.fecha >= v_desde;

  RETURN v_base + v_cobros + v_ing - v_egr - v_prov - v_pers;
END$$
DELIMITER ;

-- --- 8. El permiso cambia de módulo ------------------------------------
-- «Datos de pago» vivía en Configuración; la cuenta bancaria es de
-- Tesorería, al lado de las cajas. Lo guardado en `rol_modulo` se
-- traduce (`permisos.equivalencias` lo hace al leer, y acá queda
-- escrito), así ningún rol pierde la pantalla en silencio.
UPDATE rol_modulo SET modulo = 'facturacion.cuentas' WHERE modulo = 'configuracion.pagos';
